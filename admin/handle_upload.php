<?php
// Set header untuk output JSON
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Fungsi untuk mengirim response JSON dan menghentikan skrip
function json_response($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit();
}

// 1. Validasi Awal & Keamanan
// =============================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response('error', 'Metode permintaan tidak valid.');
}

if (!is_logged_in()) {
    json_response('error', 'Akses ditolak. Anda harus login.');
}

if (empty($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
    json_response('error', 'Upload file gagal atau tidak ada file yang dipilih. Kode Error: ' . ($_FILES['video_file']['error'] ?? 'N/A'));
}

$channel_id = $_POST['channel_id'] ?? null;
$title = $_POST['title'] ?? null;
$privacy_status = $_POST['privacy_status'] ?? null;

if (empty($channel_id) || empty($title) || empty($privacy_status)) {
    json_response('error', 'Data form tidak lengkap.');
}

// 2. Persiapan File & Database
// =============================
$video_file = $_FILES['video_file'];
$upload_dir = UPLOAD_DIR;
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
$file_extension = pathinfo($video_file['name'], PATHINFO_EXTENSION);
$safe_filename = uniqid('video_', true) . '.' . $file_extension;
$file_path = $upload_dir . '/' . $safe_filename;

if (!move_uploaded_file($video_file['tmp_name'], $file_path)) {
    json_response('error', 'Gagal memindahkan file yang diupload ke direktori tujuan.');
}

// Verifikasi channel dan dapatkan info OAuth terkait
$stmt = $pdo->prepare(
    "SELECT o.*
     FROM channels c
     JOIN oauth_profiles o ON c.oauth_profile_id = o.id
     WHERE c.id = :channel_id AND o.user_id = :user_id"
);
$stmt->execute([':channel_id' => $channel_id, ':user_id' => $_SESSION['user_id']]);
$oauth_profile = $stmt->fetch();

if (!$oauth_profile) {
    unlink($file_path); // Hapus file jika channel tidak valid
    json_response('error', 'Channel tidak valid atau Anda tidak memiliki izin untuk menggunakannya.');
}

// Simpan data awal ke tabel uploads
$stmt_upload = $pdo->prepare(
    "INSERT INTO uploads (user_id, channel_id, title, description, tags, privacy_status, file_path, file_size, upload_status)
     VALUES (:user_id, :channel_id, :title, :description, :tags, :privacy_status, :file_path, :file_size, 'uploading')"
);
$stmt_upload->execute([
    ':user_id' => $_SESSION['user_id'],
    ':channel_id' => $channel_id,
    ':title' => $title,
    ':description' => $_POST['description'] ?? '',
    ':tags' => $_POST['tags'] ?? '',
    ':privacy_status' => $privacy_status,
    ':file_path' => $file_path,
    ':file_size' => $video_file['size']
]);
$upload_id = $pdo->lastInsertId();


// 3. Proses Upload ke YouTube
// =============================
try {
    $client = get_google_client();
    $decrypted_refresh_token = decrypt_data($oauth_profile['refresh_token']);

    // Refresh token jika perlu
    $client->fetchAccessTokenWithRefreshToken($decrypted_refresh_token);
    $new_access_token = $client->getAccessToken();

    // Update token di database jika ada yang baru
    $stmt_update_token = $pdo->prepare(
        "UPDATE oauth_profiles SET access_token = :access_token, token_created_at = :now WHERE id = :id"
    );
    $stmt_update_token->execute([
        ':access_token' => $new_access_token['access_token'],
        ':now' => time(),
        ':id' => $oauth_profile['id']
    ]);

    $youtube = new Google_Service_YouTube($client);

    // Buat objek video
    $video = new Google_Service_YouTube_Video();

    // Set Snippet
    $snippet = new Google_Service_YouTube_VideoSnippet();
    $snippet->setTitle($title);
    $snippet->setDescription($_POST['description'] ?? '');
    $tags = !empty($_POST['tags']) ? explode(',', $_POST['tags']) : [];
    $snippet->setTags(array_map('trim', $tags));
    $video->setSnippet($snippet);

    // Set Status
    $status = new Google_Service_YouTube_VideoStatus();
    $status->setPrivacyStatus($privacy_status);
    $video->setStatus($status);

    // Mulai proses upload resumable
    $chunkSizeBytes = 1 * 1024 * 1024; // 1MB
    $client->setDefer(true);

    $insertRequest = $youtube->videos->insert('snippet,status', $video);

    $media = new Google_Http_MediaFileUpload(
        $client,
        $insertRequest,
        'video/*',
        null,
        true,
        $chunkSizeBytes
    );
    $media->setFileSize($video_file['size']);

    // Upload file per chunk
    $upload_status = false;
    $handle = fopen($file_path, "rb");
    while (!$upload_status && !feof($handle)) {
        $chunk = fread($handle, $chunkSizeBytes);
        $upload_status = $media->nextChunk($chunk);
    }
    fclose($handle);
    $client->setDefer(false);

    if ($upload_status) {
        // 4. Sukses Upload
        // =================
        $youtube_video_id = $upload_status->getId();

        // Update database
        $stmt_success = $pdo->prepare(
            "UPDATE uploads SET upload_status = 'success', youtube_video_id = :video_id, uploaded_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        $stmt_success->execute([':video_id' => $youtube_video_id, ':id' => $upload_id]);

        log_activity($pdo, $_SESSION['user_id'], 'Video berhasil diupload', 'upload', $upload_id, json_encode(['video_id' => $youtube_video_id, 'title' => $title]));

        unlink($file_path); // Hapus file lokal setelah sukses

        json_response('success', 'Video berhasil diupload!', ['videoId' => $youtube_video_id]);

    } else {
        // Ini seharusnya tidak terjadi jika tidak ada exception, tapi sebagai fallback
        throw new Exception("Proses upload tidak mengembalikan status sukses.");
    }

} catch (Exception $e) {
    // 5. Gagal Upload
    // =================
    $error_message = $e->getMessage();

    // Update database
    $stmt_fail = $pdo->prepare(
        "UPDATE uploads SET upload_status = 'failed', error_message = :error WHERE id = :id"
    );
    $stmt_fail->execute([':error' => $error_message, ':id' => $upload_id]);

    log_activity($pdo, $_SESSION['user_id'], 'Gagal upload video', 'upload', $upload_id, $error_message);

    // Jangan hapus file jika gagal, mungkin perlu di-debug
    // unlink($file_path);

    json_response('error', 'Upload gagal: ' . $error_message);
}
?>