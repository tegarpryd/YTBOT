-- Skema Database untuk Platform Uploader YouTube (Versi 2)
-- Target: MySQL

-- Tabel untuk pengguna platform (admin, user)
-- Menambahkan kolom untuk menyimpan Client ID dan Secret per pengguna (dienkripsi)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
  `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  `google_client_id` TEXT,
  `google_client_secret` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk menyimpan profil OAuth Google yang terhubung
CREATE TABLE `oauth_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `profile_name` VARCHAR(100) NOT NULL,
  `google_user_id` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `access_token` TEXT NOT NULL,
  `refresh_token` TEXT NOT NULL, -- Harus dienkripsi
  `expires_in` INT NOT NULL,
  `token_created_at` BIGINT NOT NULL,
  `scopes` TEXT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk menyimpan daftar channel dari setiap profil OAuth
CREATE TABLE `channels` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `oauth_profile_id` INT NOT NULL,
  `youtube_channel_id` VARCHAR(255) NOT NULL UNIQUE,
  `title` VARCHAR(255) NOT NULL,
  `thumbnail_url` VARCHAR(255),
  `subscriber_count` INT DEFAULT 0,
  `is_default` BOOLEAN DEFAULT FALSE,
  `fetched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`oauth_profile_id`) REFERENCES `oauth_profiles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk mencatat riwayat upload video
CREATE TABLE `uploads` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `channel_id` INT NOT NULL,
  `youtube_video_id` VARCHAR(50),
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `tags` TEXT,
  `privacy_status` ENUM('public', 'private', 'unlisted') NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_size` BIGINT,
  `duration_seconds` INT,
  `is_short` BOOLEAN NOT NULL DEFAULT FALSE,
  `upload_status` ENUM('pending', 'uploading', 'processing', 'success', 'failed') NOT NULL DEFAULT 'pending',
  `error_message` TEXT,
  `uploaded_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`channel_id`) REFERENCES `channels`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel untuk log aktivitas sistem
CREATE TABLE `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT,
  `action` VARCHAR(255) NOT NULL,
  `target_type` VARCHAR(50),
  `target_id` INT,
  `details` TEXT,
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel baru untuk menyimpan konfigurasi aplikasi
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_name` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Insert data awal
-- Ganti 'admin_password' dengan hash password yang aman saat implementasi
INSERT INTO `users` (`username`, `email`, `password`, `role`, `status`) VALUES
('admin', 'admin@example.com', '$2y$10$g.N.UeR0sD/y.0a/8.0c.eA2G7C1bE3F4H5I6J7K8L9M0N1O2P3Q', 'admin', 'active');

-- Masukkan beberapa pengaturan default ke dalam tabel settings
INSERT INTO `settings` (`setting_name`, `setting_value`) VALUES
('app_name', 'YouTube Multi-Channel Uploader'),
('app_url', 'http://localhost/youtube-uploader'), -- Sesuaikan dengan URL Anda
('session_lifetime', '3600'),
('encryption_key', 'your-super-secret-and-strong-encryption-key'), -- Ganti ini!
('encryption_cipher', 'AES-256-CBC');