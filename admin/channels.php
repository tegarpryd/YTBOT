<?php
// Halaman Daftar Channel (Versi 2 - Diperbaiki)
$page_title = "Daftar Channel";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

// Handle re-sync request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resync_channels'])) {
    try {
        $credentials = get_current_user_google_credentials($pdo);
        if (!$credentials) {
            throw new Exception("Kredensial Google Anda belum diatur.");
        }
        $client = get_google_client($credentials['client_id'], $credentials['client_secret']);

        $profile_id_to_sync = $_POST['profile_id'] ?? 0;
        $stmt_profile = $pdo->prepare("SELECT * FROM oauth_profiles WHERE id = :id AND user_id = :user_id");
        $stmt_profile->execute([':id' => $profile_id_to_sync, ':user_id' => $_SESSION['user_id']]);
        $profile = $stmt_profile->fetch();

        if (!$profile) {
            throw new Exception("Profil Google yang dipilih tidak ditemukan.");
        }

        // Ambil token baru menggunakan refresh token
        $client->fetchAccessTokenWithRefreshToken(decrypt_data($profile['refresh_token']));
        $youtube = new Google_Service_YouTube($client);
        $channelsResponse = $youtube->channels->listChannels('snippet,statistics', ['mine' => true]);

        $synced_count = 0;
        foreach ($channelsResponse->getItems() as $channel) {
            $synced_count++;
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
                    ':oid' => $profile['id'],
                    ':cid' => $channel->getId(),
                    ':title' => $channel->getSnippet()->getTitle(),
                    ':thumb' => $channel->getSnippet()->getThumbnails()->getDefault()->getUrl(),
                    ':subs' => $channel->getStatistics()->getSubscriberCount()
                ]);
            }
        }
        log_activity($pdo, $_SESSION['user_id'], 'Sinkronisasi ulang channel berhasil', 'oauth_profile', $profile['id'], "{$synced_count} channel disinkronkan.");
        $_SESSION['success_message'] = "Sinkronisasi ulang untuk profil '{$profile['email']}' berhasil. {$synced_count} channel ditemukan/diperbarui.";

    } catch (Exception $e) {
        error_log("Gagal sinkronisasi channel: " . $e->getMessage());
        $_SESSION['error_message'] = "Gagal melakukan sinkronisasi: " . $e->getMessage();
    }
    redirect('/admin/channels.php');
}


// Fetch all channels linked to the user's OAuth profiles
$stmt_channels = $pdo->prepare(
    "SELECT c.*, o.email as oauth_email
     FROM channels c
     JOIN oauth_profiles o ON c.oauth_profile_id = o.id
     WHERE o.user_id = :user_id
     ORDER BY o.email, c.title"
);
$stmt_channels->execute([':user_id' => $_SESSION['user_id']]);
$channels = $stmt_channels->fetchAll();

// Get profiles for the re-sync dropdown
$stmt_profiles = $pdo->prepare("SELECT id, email FROM oauth_profiles WHERE user_id = :user_id");
$stmt_profiles->execute([':user_id' => $_SESSION['user_id']]);
$profiles_for_sync = $stmt_profiles->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h2 mb-0">Daftar Channel Anda</h1>
    <div class="btn-group">
        <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" data-bs-toggle="tooltip" title="Ambil ulang daftar channel terbaru dari Google untuk profil yang dipilih.">
            <i class="fa-solid fa-arrows-rotate me-2"></i> Sinkronisasi Ulang
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php if (empty($profiles_for_sync)): ?>
                 <li><a class="dropdown-item disabled" href="#">Tidak ada profil untuk disinkronkan</a></li>
            <?php else: ?>
                <?php foreach ($profiles_for_sync as $profile): ?>
                    <li>
                        <form action="channels.php" method="POST" class="d-inline w-100">
                            <input type="hidden" name="profile_id" value="<?php echo $profile['id']; ?>">
                            <button type="submit" name="resync_channels" class="dropdown-item">Sinkronkan '<?php echo htmlspecialchars($profile['email']); ?>'</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>

<p class="text-muted mb-4">Berikut adalah semua channel YouTube yang telah berhasil disinkronkan dari Akun Google Anda. Channel yang ada di daftar ini siap untuk dipilih saat Anda mengunggah video.</p>

<div class="card shadow">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 10%;"></th>
                        <th>Nama Channel</th>
                        <th>Subscriber</th>
                        <th>Terkait dengan Akun</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($channels)): ?>
                        <tr>
                            <td colspan="4" class="text-center p-5">
                                <div class="display-4 text-muted mb-3"><i class="fa-solid fa-tv"></i></div>
                                <h4>Tidak Ada Channel Ditemukan</h4>
                                <p class="text-muted">Aplikasi ini belum menemukan channel YouTube dari profil Google Anda.</p>
                                <a href="<?php echo APP_URL; ?>/admin/oauth_profiles.php" class="btn btn-primary mt-2">
                                    <i class="fa-brands fa-google me-2"></i>Kelola Profil Google
                                </a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($channels as $channel): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo htmlspecialchars($channel['thumbnail_url']); ?>" alt="Thumbnail" class="img-thumbnail rounded-circle" width="50">
                                </td>
                                <td><strong><?php echo htmlspecialchars($channel['title']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($channel['youtube_channel_id']); ?></small></td>
                                <td><i class="fa-solid fa-users me-2 text-muted"></i><?php echo number_format($channel['subscriber_count']); ?></td>
                                <td>
                                    <i class="fa-brands fa-google text-muted me-2"></i>
                                    <?php echo htmlspecialchars($channel['oauth_email']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>