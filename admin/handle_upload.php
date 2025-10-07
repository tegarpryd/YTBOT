<?php
// Backend Upload Handler (Versi 2 - Diperbaiki & Dibuat Lebih Kuat)
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Fungsi untuk mengirim respons JSON yang konsisten dan menghentikan skrip
function json_response($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit();
}

try {
    // 1. Validasi Awal & Keamanan
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metode permintaan tidak valid.');
    }
    if (!is_logged_in()) {
        throw new Exception('Akses ditolak. Anda harus login.');
    }
    if (empty($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload file gagal. Pastikan Anda memilih file video yang valid.');
    }

    $channel_ids = $_POST['channel_ids'] ?? [];
    $title = trim($_POST['title'] ?? '');
    $privacy_status = $_POST['privacy_status'] ?? '';

    if (empty($channel_ids) || !is_array($channel_ids)) {
        throw new Exception('Pilih setidaknya satu channel tujuan.');
    }
    if (empty($title) || empty($privacy_status)) {
        throw new Exception('Judul dan status privasi tidak boleh kosong.');
    }

    // 2. Persiapan File & Kredensial Pengguna
    $video_file = $_FILES['video_file'];
    $upload_dir = UPLOAD_DIR;
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $file_path = $upload_dir . '/' . uniqid('video_', true) . '.' . pathinfo($video_file['name'], PATHINFO_EXTENSION);

    if (!move_uploaded_file($video_file['tmp_name'], $file_path)) {
        throw new Exception('Gagal menyimpan file yang diupload di server.');
    }

    $credentials = get_current_user_google_credentials($pdo);
    if (!$credentials) {
        throw new Exception("Kredensial Google API Anda belum diatur. Silakan atur di halaman Pengaturan.");
    }
    $client = get_google_client($credentials['client_id'], $credentials['client_secret']);

    // 3. Proses Upload ke Setiap Channel (Loop dengan Isolasi Error)
    $results = ['success' => [], 'failed' => []];

    foreach ($channel_ids as $channel_id) {
        $upload_id = null;
        try {
            // Verifikasi kepemilikan channel dan dapatkan info profil OAuth
            $stmt = $pdo->prepare(
                "SELECT o.refresh_token, c.title as channel_title
                 FROM channels c JOIN oauth_profiles o ON c.oauth_profile_id = o.id
                 WHERE c.id = :cid AND o.user_id = :uid"
            );
            $stmt->execute([':cid' => $channel_id, ':uid' => $_SESSION['user_id']]);
            $channel_info = $stmt->fetch();

            if (!$channel_info) {
                throw new Exception("Channel tidak valid atau Anda tidak memiliki izin.");
            }

            $channel_title = $channel_info['channel_title'];

            // Buat entri log di database untuk proses upload ini
            $stmt_upload = $pdo->prepare("INSERT INTO uploads (user_id, channel_id, title, description, tags, privacy_status, file_path, file_size, upload_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'uploading')");
            $stmt_upload->execute([$_SESSION['user_id'], $channel_id, $title, $_POST['description'] ?? '', $_POST['tags'] ?? '', $privacy_status, $file_path, $video_file['size']]);
            $upload_id = $pdo->lastInsertId();

            // Lakukan autentikasi ulang untuk profil ini
            $client->fetchAccessTokenWithRefreshToken(decrypt_data($channel_info['refresh_token']));

            // Persiapan upload ke YouTube API
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

            // Proses upload resumable
            $client->setDefer(true);
            $insertRequest = $youtube->videos->insert('snippet,status', $video);
            $media = new Google_Http_MediaFileUpload($client, $insertRequest, 'video/*', null, true, 1 * 1024 * 1024);
            $media->setFileSize(filesize($file_path));

            $upload_status = false;
            $handle = fopen($file_path, "rb");
            while (!$upload_status && !feof($handle)) {
                $upload_status = $media->nextChunk(fread($handle, 1 * 1024 * 1024));
            }
            fclose($handle);
            $client->setDefer(false);

            if ($upload_status) {
                $youtube_video_id = $upload_status->getId();
                $pdo->prepare("UPDATE uploads SET upload_status = 'success', youtube_video_id = ?, uploaded_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$youtube_video_id, $upload_id]);
                log_activity($pdo, $_SESSION['user_id'], 'Video berhasil diupload', 'upload', $upload_id, json_encode(['video_id' => $youtube_video_id, 'channel' => $channel_title]));
                $results['success'][] = "Berhasil upload ke '{$channel_title}'.";
            } else {
                throw new Exception("Proses upload ke YouTube tidak mengembalikan status sukses.");
            }

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            error_log("Gagal upload ke channel ID {$channel_id}: " . $error_message);
            if ($upload_id) {
                $pdo->prepare("UPDATE uploads SET upload_status = 'failed', error_message = ? WHERE id = ?")->execute([$error_message, $upload_id]);
            }
            $results['failed'][] = "Gagal upload ke '{$channel_title}': " . substr($error_message, 0, 150) . "...";
        }
    }

    // 4. Cleanup & Final Response
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    $total_success = count($results['success']);
    $total_failed = count($results['failed']);
    $final_message = "Proses multi-upload selesai. Berhasil: {$total_success}, Gagal: {$total_failed}.";

    json_response('completed', $final_message, $results);

} catch (Exception $e) {
    // Menangkap error fatal sebelum loop dimulai (misal: file tidak ada, kredensial salah)
    if (!empty($file_path) && file_exists($file_path)) {
        unlink($file_path);
    }
    error_log("Kesalahan fatal pada handle_upload.php: " . $e->getMessage());
    json_response('error', $e->getMessage());
}
?>