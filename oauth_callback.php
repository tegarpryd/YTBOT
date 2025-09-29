<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Proses ini memerlukan pengguna untuk login terlebih dahulu ke platform.
// Ini untuk menautkan profil OAuth baru ke akun pengguna platform yang ada.
require_login();

$client = get_google_client();

// Menangani error dari Google, misalnya jika pengguna menolak izin.
if (isset($_GET['error'])) {
    $_SESSION['error_message'] = 'Proses otorisasi Google dibatalkan atau gagal: ' . htmlspecialchars($_GET['error']);
    redirect('/admin/oauth_profiles.php');
}

// Memastikan kode otorisasi ada.
if (!isset($_GET['code'])) {
    $_SESSION['error_message'] = 'Kode otorisasi Google tidak ditemukan.';
    redirect('/admin/oauth_profiles.php');
}

try {
    // Tukarkan kode otorisasi dengan access token.
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        throw new Exception('Gagal mendapatkan access token: ' . $token['error_description']);
    }

    $client->setAccessToken($token);

    // Dapatkan informasi profil dari Google.
    $oauth2 = new Google_Service_Oauth2($client);
    $google_account_info = $oauth2->userinfo->get();
    $google_user_id = $google_account_info->getId();
    $email = $google_account_info->getEmail();

    // Enkripsi refresh token sebelum disimpan.
    if (!isset($token['refresh_token'])) {
        // Jika tidak ada refresh token, kemungkinan pengguna sudah pernah memberi izin
        // dan tidak mencabutnya. Kita perlu update access token saja jika profil sudah ada.
        // Untuk aplikasi baru, kita selalu minta 'consent' untuk memastikan refresh token didapat.
        $stmt = $pdo->prepare("SELECT id, refresh_token FROM oauth_profiles WHERE google_user_id = :google_user_id AND user_id = :user_id");
        $stmt->execute([':google_user_id' => $google_user_id, ':user_id' => $_SESSION['user_id']]);
        $existing_profile = $stmt->fetch();

        if (!$existing_profile || empty($existing_profile['refresh_token'])) {
             throw new Exception('Refresh token tidak diterima dari Google. Pastikan untuk mencabut akses aplikasi dari akun Google Anda dan coba lagi.');
        }
        $encrypted_refresh_token = $existing_profile['refresh_token']; // Gunakan refresh token lama
    } else {
        $encrypted_refresh_token = encrypt_data($token['refresh_token']);
    }

    // Simpan atau update profil OAuth di database.
    $stmt = $pdo->prepare("SELECT id FROM oauth_profiles WHERE google_user_id = :google_user_id AND user_id = :user_id");
    $stmt->execute([':google_user_id' => $google_user_id, ':user_id' => $_SESSION['user_id']]);
    $profile_id = $stmt->fetchColumn();

    $profile_name = $email; // Default profile name
    if ($profile_id) {
        // Update token
        $stmt = $pdo->prepare(
            "UPDATE oauth_profiles SET access_token = :access_token, refresh_token = :refresh_token, expires_in = :expires_in, token_created_at = :token_created_at
             WHERE id = :id"
        );
        $stmt->execute([
            ':access_token' => $token['access_token'],
            ':refresh_token' => $encrypted_refresh_token,
            ':expires_in' => $token['expires_in'],
            ':token_created_at' => time(),
            ':id' => $profile_id
        ]);
    } else {
        // Insert profil baru
        $stmt = $pdo->prepare(
            "INSERT INTO oauth_profiles (user_id, profile_name, google_user_id, email, access_token, refresh_token, expires_in, token_created_at, scopes)
             VALUES (:user_id, :profile_name, :google_user_id, :email, :access_token, :refresh_token, :expires_in, :token_created_at, :scopes)"
        );
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':profile_name' => $profile_name,
            ':google_user_id' => $google_user_id,
            ':email' => $email,
            ':access_token' => $token['access_token'],
            ':refresh_token' => $encrypted_refresh_token,
            ':expires_in' => $token['expires_in'],
            ':token_created_at' => time(),
            ':scopes' => implode(' ', $client->getScopes())
        ]);
        $profile_id = $pdo->lastInsertId();
    }

    // Setelah profil disimpan, ambil daftar channel YouTube.
    $youtube = new Google_Service_YouTube($client);
    $channelsResponse = $youtube->channels->listChannels('snippet,statistics', ['mine' => true]);

    foreach ($channelsResponse->getItems() as $channel) {
        $stmt = $pdo->prepare("SELECT id FROM channels WHERE youtube_channel_id = :channel_id");
        $stmt->execute([':channel_id' => $channel->getId()]);
        $existing_channel_id = $stmt->fetchColumn();

        if ($existing_channel_id) {
            $updateStmt = $pdo->prepare("UPDATE channels SET title = :title, thumbnail_url = :thumb, subscriber_count = :subs WHERE id = :id");
            $updateStmt->execute([
                ':title' => $channel->getSnippet()->getTitle(),
                ':thumb' => $channel->getSnippet()->getThumbnails()->getDefault()->getUrl(),
                ':subs' => $channel->getStatistics()->getSubscriberCount(),
                ':id' => $existing_channel_id
            ]);
        } else {
            $insertStmt = $pdo->prepare(
                "INSERT INTO channels (oauth_profile_id, youtube_channel_id, title, thumbnail_url, subscriber_count)
                 VALUES (:oauth_id, :channel_id, :title, :thumb, :subs)"
            );
            $insertStmt->execute([
                ':oauth_id' => $profile_id,
                ':channel_id' => $channel->getId(),
                ':title' => $channel->getSnippet()->getTitle(),
                ':thumb' => $channel->getSnippet()->getThumbnails()->getDefault()->getUrl(),
                ':subs' => $channel->getStatistics()->getSubscriberCount()
            ]);
        }
    }

    log_activity($pdo, $_SESSION['user_id'], 'Profil OAuth ditambahkan/diupdate', 'oauth_profile', $profile_id, json_encode(['email' => $email]));
    $_SESSION['success_message'] = 'Profil Google berhasil ditautkan dan channel telah disinkronkan.';

} catch (Exception $e) {
    log_activity($pdo, $_SESSION['user_id'], 'Gagal menautkan profil OAuth', 'oauth_profile', null, $e->getMessage());
    $_SESSION['error_message'] = 'Terjadi kesalahan: ' . $e->getMessage();
}

redirect('/admin/oauth_profiles.php');
?>