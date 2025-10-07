<?php
// File Callback OAuth (Versi 2 - Diperbaiki & Dibuat Lebih Kuat)

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

try {
    // Langkah 1: Dapatkan kredensial API milik pengguna yang sedang login.
    $credentials = get_current_user_google_credentials($pdo);
    if (!$credentials) {
        throw new Exception("Kredensial Google Anda (Client ID/Secret) belum diatur. Silakan atur di halaman Pengaturan.");
    }

    // Langkah 2: Inisialisasi Google Client dengan kredensial pengguna.
    $client = get_google_client($credentials['client_id'], $credentials['client_secret']);

    // Langkah 3: Validasi respons dari Google.
    if (isset($_GET['error'])) {
        throw new Exception('Proses otorisasi Google dibatalkan atau gagal: ' . htmlspecialchars($_GET['error']));
    }
    if (!isset($_GET['code'])) {
        throw new Exception('Kode otorisasi Google tidak ditemukan dalam respons. Coba lagi.');
    }

    // Langkah 4: Tukarkan kode otorisasi dengan access token.
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        throw new Exception('Gagal mendapatkan access token dari Google: ' . $token['error_description']);
    }

    $client->setAccessToken($token);

    // Langkah 5: Dapatkan informasi profil pengguna dari Google.
    $oauth2 = new Google_Service_Oauth2($client);
    $google_account_info = $oauth2->userinfo->get();
    $google_user_id = $google_account_info->getId();
    $email = $google_account_info->getEmail();

    // Langkah 6: Siapkan dan enkripsi refresh token.
    $stmt = $pdo->prepare("SELECT id, refresh_token FROM oauth_profiles WHERE google_user_id = :google_user_id AND user_id = :user_id");
    $stmt->execute([':google_user_id' => $google_user_id, ':user_id' => $_SESSION['user_id']]);
    $existing_profile = $stmt->fetch();

    if (isset($token['refresh_token'])) {
        // Jika Google memberikan refresh token baru, gunakan itu.
        $encrypted_refresh_token = encrypt_data($token['refresh_token']);
    } elseif ($existing_profile && !empty($existing_profile['refresh_token'])) {
        // Jika tidak ada refresh token baru tapi profil sudah ada, gunakan yang lama.
        $encrypted_refresh_token = $existing_profile['refresh_token'];
    } else {
        // Skenario terburuk: tidak ada refresh token baru dan tidak ada yang lama.
        throw new Exception('Refresh token tidak diterima dari Google. Coba cabut akses aplikasi dari pengaturan akun Google Anda, lalu hubungkan kembali.');
    }

    // Langkah 7: Simpan atau perbarui profil di database.
    if ($existing_profile) {
        // Profil sudah ada, perbarui token.
        $profile_id = $existing_profile['id'];
        $stmt = $pdo->prepare(
            "UPDATE oauth_profiles SET access_token = :access_token, refresh_token = :refresh_token, expires_in = :expires_in, token_created_at = :now WHERE id = :id"
        );
        $stmt->execute([
            ':access_token' => $token['access_token'],
            ':refresh_token' => $encrypted_refresh_token,
            ':expires_in' => $token['expires_in'],
            ':now' => time(),
            ':id' => $profile_id
        ]);
    } else {
        // Profil baru, buat entri baru.
        $stmt = $pdo->prepare(
            "INSERT INTO oauth_profiles (user_id, profile_name, google_user_id, email, access_token, refresh_token, expires_in, token_created_at, scopes)
             VALUES (:uid, :pname, :gid, :email, :atk, :rtk, :exp, :now, :scopes)"
        );
        $stmt->execute([
            ':uid' => $_SESSION['user_id'],
            ':pname' => $email, // Gunakan email sebagai nama profil default
            ':gid' => $google_user_id,
            ':email' => $email,
            ':atk' => $token['access_token'],
            ':rtk' => $encrypted_refresh_token,
            ':exp' => $token['expires_in'],
            ':now' => time(),
            ':scopes' => implode(' ', $client->getScopes())
        ]);
        $profile_id = $pdo->lastInsertId();
    }

    // Langkah 8: Sinkronkan channel YouTube.
    $youtube = new Google_Service_YouTube($client);
    $channelsResponse = $youtube->channels->listChannels('snippet,statistics', ['mine' => true]);

    foreach ($channelsResponse->getItems() as $channel) {
        $stmt_check = $pdo->prepare("SELECT id FROM channels WHERE youtube_channel_id = :channel_id");
        $stmt_check->execute([':channel_id' => $channel->getId()]);
        if ($stmt_check->fetch()) { // Channel sudah ada, update
            $updateStmt = $pdo->prepare("UPDATE channels SET title = :title, thumbnail_url = :thumb, subscriber_count = :subs WHERE youtube_channel_id = :cid");
            $updateStmt->execute([
                ':title' => $channel->getSnippet()->getTitle(),
                ':thumb' => $channel->getSnippet()->getThumbnails()->getDefault()->getUrl(),
                ':subs' => $channel->getStatistics()->getSubscriberCount(),
                ':cid' => $channel->getId()
            ]);
        } else { // Channel baru, insert
            $insertStmt = $pdo->prepare("INSERT INTO channels (oauth_profile_id, youtube_channel_id, title, thumbnail_url, subscriber_count) VALUES (:oid, :cid, :title, :thumb, :subs)");
            $insertStmt->execute([
                ':oid' => $profile_id,
                ':cid' => $channel->getId(),
                ':title' => $channel->getSnippet()->getTitle(),
                ':thumb' => $channel->getSnippet()->getThumbnails()->getDefault()->getUrl(),
                ':subs' => $channel->getStatistics()->getSubscriberCount()
            ]);
        }
    }

    log_activity($pdo, $_SESSION['user_id'], 'Profil OAuth berhasil dihubungkan/diperbarui', 'oauth_profile', $profile_id, json_encode(['email' => $email]));
    $_SESSION['success_message'] = 'Profil Google berhasil ditautkan dan channel telah disinkronkan!';

} catch (Exception $e) {
    // Menangkap semua kemungkinan error dan memberikan umpan balik yang jelas.
    error_log("Kesalahan Callback OAuth: " . $e->getMessage()); // Log untuk admin
    $_SESSION['error_message'] = 'Terjadi kesalahan: ' . $e->getMessage(); // Pesan untuk pengguna
}

// Selalu alihkan kembali ke halaman profil, di mana pesan sukses atau error akan ditampilkan.
redirect('/admin/oauth_profiles.php');
?>