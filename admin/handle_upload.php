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

$channel_ids = $_POST['channel_ids'] ?? [];
$title = $_POST['title'] ?? null;
$privacy_status = $_POST['privacy_status'] ?? null;

if (empty($channel_ids) || !is_array($channel_ids)) {
    json_response('error', 'Pilih setidaknya satu channel tujuan.');
}
if (empty($title) || empty($privacy_status)) {
    json_response('error', 'Judul dan status privasi tidak boleh kosong.');
}

// 2. Persiapan File & Kredensial
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

// Ambil kredensial Google milik pengguna satu kali
try {
    $credentials = get_current_user_google_credentials($pdo);
    if (!$credentials) {
        throw new Exception("Kredensial Google Anda (Client ID/Secret) belum diatur.");
    }
    $client = get_google_client($credentials['client_id'], $credentials['client_secret']);
} catch (Exception $e) {
    unlink($file_path);
    json_response('error', $e->getMessage());
}

// 3. Proses Upload ke Setiap Channel (Loop)
// ===========================================
$results = [
    'success' => [],
    'failed' => []
];

foreach ($channel_ids as $channel_id) {
    // Verifikasi channel dan dapatkan info OAuth terkait
    $stmt = $pdo->prepare(
        "SELECT o.*, c.title as channel_title
         FROM channels c
         JOIN oauth_profiles o ON c.oauth_profile_id = o.id
         WHERE c.id = :channel_id AND o.user_id = :user_id"
    );
    $stmt->execute([':channel_id' => $channel_id, ':user_id' => $_SESSION['user_id']]);
    $oauth_profile = $stmt->fetch();

    if (!$oauth_profile) {
        $results['failed'][] = "Channel ID {$channel_id} tidak valid atau bukan milik Anda.";
        continue; // Lanjut ke channel berikutnya
    }

    $channel_title = $oauth_profile['channel_title'];

    // Simpan data awal ke tabel uploads untuk setiap channel
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

    try {
        // Refresh token jika perlu
        $client->fetchAccessTokenWithRefreshToken(decrypt_data($oauth_profile['refresh_token']));

        $youtube = new Google_Service_YouTube($client);
        $video = new Google_Service_YouTube_Video();
        $snippet = new Google_Service_YouTube_VideoSnippet();
        $snippet->setTitle($title);
        $snippet->setDescription($_POST['description'] ?? '');
        $snippet->setTags(!empty($_POST['tags']) ? explode(',', $_POST['tags']) : []);
        $video->setSnippet($snippet);
        $status = new Google_Service_YouTube_VideoStatus();
        $status->setPrivacyStatus($privacy_status);
        $video->setStatus($status);

        // Upload
        $chunkSizeBytes = 1 * 1024 * 1024;
        $client->setDefer(true);
        $insertRequest = $youtube->videos->insert('snippet,status', $video);
        $media = new Google_Http_MediaFileUpload($client, $insertRequest, 'video/*', null, true, $chunkSizeBytes);
        $media->setFileSize(filesize($file_path));

        $upload_status = false;
        $handle = fopen($file_path, "rb");
        while (!$upload_status && !feof($handle)) {
            $chunk = fread($handle, $chunkSizeBytes);
            $upload_status = $media->nextChunk($chunk);
        }
        fclose($handle);
        $client->setDefer(false);

        if ($upload_status) {
            $youtube_video_id = $upload_status->getId();
            $pdo->prepare("UPDATE uploads SET upload_status = 'success', youtube_video_id = :vid, uploaded_at = CURRENT_TIMESTAMP WHERE id = :id")->execute([':vid' => $youtube_video_id, ':id' => $upload_id]);
            log_activity($pdo, $_SESSION['user_id'], 'Video berhasil diupload', 'upload', $upload_id, json_encode(['video_id' => $youtube_video_id, 'channel' => $channel_title]));
            $results['success'][] = "Berhasil diupload ke channel '{$channel_title}'.";
        } else {
            throw new Exception("Proses upload tidak mengembalikan status sukses.");
        }

    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $pdo->prepare("UPDATE uploads SET upload_status = 'failed', error_message = :error WHERE id = :id")->execute([':error' => $error_message, ':id' => $upload_id]);
        log_activity($pdo, $_SESSION['user_id'], 'Gagal upload video', 'upload', $upload_id, $error_message);
        $results['failed'][] = "Gagal diupload ke channel '{$channel_title}': " . $error_message;
    }
}

// 4. Cleanup & Final Response
// =============================
unlink($file_path); // Hapus file lokal setelah semua proses selesai

$total_success = count($results['success']);
$total_failed = count($results['failed']);
$final_message = "Proses multi-upload selesai. Berhasil: {$total_success}, Gagal: {$total_failed}.";

json_response('success', $final_message, $results);
?>