<?php
$page_title = "Daftar Channel";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

// Handle re-sync request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resync_channels'])) {
    try {
        // Ambil kredensial Google milik pengguna
        $credentials = get_current_user_google_credentials($pdo);
        if (!$credentials) {
            throw new Exception("Kredensial Google Anda (Client ID/Secret) belum diatur.");
        }

        $profile_id_to_sync = $_POST['profile_id'] ?? 0;

        $stmt = $pdo->prepare("SELECT * FROM oauth_profiles WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $profile_id_to_sync, ':user_id' => $_SESSION['user_id']]);
        $profile = $stmt->fetch();

        if ($profile) {
            $client = get_google_client($credentials['client_id'], $credentials['client_secret']);
            $access_token = $client->fetchAccessTokenWithRefreshToken(decrypt_data($profile['refresh_token']));
            $client->setAccessToken($access_token);

            $youtube = new Google_Service_YouTube($client);
            $channelsResponse = $youtube->channels->listChannels('snippet,statistics', ['mine' => true]);

            $synced_count = 0;
            foreach ($channelsResponse->getItems() as $channel) {
                $stmt_check = $pdo->prepare("SELECT id FROM channels WHERE youtube_channel_id = :channel_id");
                $stmt_check->execute([':channel_id' => $channel->getId()]);
                $existing_channel_id = $stmt_check->fetchColumn();

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
                        ':oauth_id' => $profile['id'],
                        ':channel_id' => $channel->getId(),
                        ':title' => $channel->getSnippet()->getTitle(),
                        ':thumb' => $channel->getSnippet()->getThumbnails()->getDefault()->getUrl(),
                        ':subs' => $channel->getStatistics()->getSubscriberCount()
                    ]);
                }
                $synced_count++;
            }
            log_activity($pdo, $_SESSION['user_id'], 'Sinkronisasi ulang channel berhasil', 'oauth_profile', $profile['id'], "{$synced_count} channel disinkronkan.");
            $_SESSION['success_message'] = "Sinkronisasi ulang channel untuk profil '{$profile['email']}' berhasil. {$synced_count} channel ditemukan.";

        } catch (Exception $e) {
            $_SESSION['error_message'] = "Gagal melakukan sinkronisasi: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Profil OAuth tidak ditemukan.";
    }
    redirect('/admin/channels.php');
}


// Fetch all channels linked to the user's OAuth profiles
$stmt = $pdo->prepare(
    "SELECT c.*, o.email as oauth_email, o.id as oauth_id
     FROM channels c
     JOIN oauth_profiles o ON c.oauth_profile_id = o.id
     WHERE o.user_id = :user_id
     ORDER BY o.email, c.title"
);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$channels = $stmt->fetchAll();

// Group channels by oauth profile for re-sync buttons
$profiles_with_channels = [];
foreach ($channels as $channel) {
    if (!isset($profiles_with_channels[$channel['oauth_id']])) {
        $profiles_with_channels[$channel['oauth_id']] = $channel['oauth_email'];
    }
}


include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><?php echo htmlspecialchars($page_title); ?></h1>
    <div class="btn-group">
        <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-solid fa-arrows-rotate me-2"></i> Sinkronisasi Ulang
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php if (empty($profiles_with_channels)): ?>
                 <li><a class="dropdown-item disabled" href="#">Tidak ada profil untuk disinkronkan</a></li>
            <?php else: ?>
                <?php foreach ($profiles_with_channels as $id => $email): ?>
                    <li>
                        <form action="channels.php" method="POST" class="d-inline">
                            <input type="hidden" name="profile_id" value="<?php echo $id; ?>">
                            <button type="submit" name="resync_channels" class="dropdown-item">Sinkronkan '<?php echo htmlspecialchars($email); ?>'</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>

<p>Ini adalah daftar semua channel YouTube yang terhubung dengan akun-akun Google Anda. Data diperbarui setiap kali Anda menambahkan atau mengautentikasi ulang profil OAuth.</p>

<div class="card shadow">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 10%;">Thumbnail</th>
                        <th>Nama Channel</th>
                        <th>Subscriber</th>
                        <th>Profil OAuth Terkait</th>
                        <th>Channel ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($channels)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                Tidak ada channel yang ditemukan. <br>
                                Coba <a href="<?php echo APP_URL; ?>/admin/oauth_profiles.php">tambahkan profil OAuth</a> untuk memulai.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($channels as $channel): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo htmlspecialchars($channel['thumbnail_url']); ?>" alt="Thumbnail" class="img-thumbnail rounded-circle" width="60">
                                </td>
                                <td><strong><?php echo htmlspecialchars($channel['title']); ?></strong></td>
                                <td><?php echo number_format($channel['subscriber_count']); ?></td>
                                <td>
                                    <i class="fa-brands fa-google text-muted me-2"></i>
                                    <?php echo htmlspecialchars($channel['oauth_email']); ?>
                                </td>
                                <td><code class="user-select-all"><?php echo htmlspecialchars($channel['youtube_channel_id']); ?></code></td>
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