-- Migrasi schema hosting untuk versi multi-pocket.
-- Jalankan SETELAH import file dump lama: dennyar2_keuangan (1).sql
-- Catatan: file ini untuk dijalankan satu kali pada database yang baru di-import.

START TRANSACTION;

ALTER TABLE `categories`
  ADD COLUMN `user_id` INT NULL AFTER `id`,
  ADD COLUMN `pocket_id` INT NULL AFTER `user_id`,
  ADD COLUMN `budget_amount` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `type`,
  ADD COLUMN `budget_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `budget_amount`;

CREATE INDEX `idx_categories_scope` ON `categories` (`user_id`, `pocket_id`);
CREATE INDEX `idx_categories_name` ON `categories` (`name`);

CREATE TABLE `pockets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `group_id` VARCHAR(191) NULL,
  `budget_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `budget_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_user_pocket_name` (`user_id`, `name`),
  KEY `idx_pockets_group_id` (`group_id`),
  KEY `idx_pockets_user_active` (`user_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pockets` (`user_id`, `name`, `group_id`, `budget_amount`, `budget_enabled`, `is_active`)
SELECT `id`, 'Uang Belanja Ibu', NULL, 0, 0, 1
FROM `users`;

ALTER TABLE `expenses`
  ADD COLUMN `pocket_id` INT NULL AFTER `user_id`;

CREATE INDEX `idx_expenses_user_pocket_date` ON `expenses` (`user_id`, `pocket_id`, `date`);

UPDATE `expenses` e
JOIN `pockets` p ON p.`user_id` = e.`user_id` AND p.`name` = 'Uang Belanja Ibu'
SET e.`pocket_id` = p.`id`
WHERE e.`pocket_id` IS NULL;

CREATE TABLE `category_rules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `pocket_id` INT NULL,
  `category_id` INT NOT NULL,
  `keyword` VARCHAR(120) NOT NULL,
  `priority` INT NOT NULL DEFAULT 10,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_rules_user_pocket_active` (`user_id`, `pocket_id`, `is_active`),
  KEY `idx_rules_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `pocket_id` INT NULL,
  `name` VARCHAR(120) NOT NULL,
  `group_id` VARCHAR(191) NOT NULL,
  `weekly_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `weekly_day` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `monthly_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `monthly_day` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_weekly_sent_at` DATETIME NULL,
  `last_monthly_sent_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_notifications_user_active` (`user_id`, `is_active`),
  KEY `idx_notifications_pocket` (`pocket_id`),
  KEY `idx_notifications_group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `notification_setting_id` INT NULL,
  `user_id` INT NOT NULL,
  `period` VARCHAR(20) NOT NULL,
  `group_id` VARCHAR(191) NOT NULL,
  `status` VARCHAR(20) NOT NULL,
  `gateway_status` INT NULL,
  `error_message` TEXT NULL,
  `message_preview` TEXT NULL,
  `is_test` TINYINT(1) NOT NULL DEFAULT 0,
  `sent_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_notification_logs_user_sent` (`user_id`, `sent_at`),
  KEY `idx_notification_logs_setting` (`notification_setting_id`),
  KEY `idx_notification_logs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

-- Validasi cepat setelah migrasi:
-- SELECT p.name, COUNT(e.id) AS total_transaksi
-- FROM pockets p
-- LEFT JOIN expenses e ON e.pocket_id = p.id
-- GROUP BY p.id, p.name;
