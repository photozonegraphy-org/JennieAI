-- ══════════════════════════════════════════════════════════════
-- JennieAI — Complete SQL Schema
-- Run this once in your PhotoZone Graphy database.
-- All tables use the same database as your existing users table.
-- ══════════════════════════════════════════════════════════════


-- ──────────────────────────────────────────────────────────────
-- 1. jennie_tokens
--    One row per user. Tracks their current token balance,
--    their max allowance, and when the balance next resets.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `jennie_tokens` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED    NOT NULL,
  `tokens_left` SMALLINT        NOT NULL DEFAULT 120,
  `tokens_max`  SMALLINT        NOT NULL DEFAULT 120,
  `reset_at`    DATETIME        NOT NULL,
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY  `uq_user` (`user_id`),
  CONSTRAINT  `fk_jt_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────
-- 2. jennie_history
--    A rolling log of every tool run per user.
--    The frontend reads the last 6 rows to show "Recent sessions."
--    Old rows are pruned automatically (see event below).
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `jennie_history` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED    NOT NULL,
  `tool_id`    VARCHAR(64)     NOT NULL,
  `label`      VARCHAR(120)    NOT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`user_id`, `created_at` DESC),
  CONSTRAINT `fk_jh_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────
-- 3. jennie_rate_limit
--    Prevents users from hammering manifest.php.
--    One row per user; resets automatically after 1 hour.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `jennie_rate_limit` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `hits`         SMALLINT     NOT NULL DEFAULT 1,
  `window_start` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rl_user` (`user_id`),
  CONSTRAINT `fk_rl_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────
-- 4. jennie_admin_overrides
--    Admin panel uses this to manually grant tokens to a user
--    or instantly renew their allowance.
--    The admin page reads + writes this table.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `jennie_admin_overrides` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `admin_id`    INT UNSIGNED NOT NULL,
  `action`      ENUM('add_tokens','set_max','instant_reset','ban','unban') NOT NULL,
  `value`       INT          NOT NULL DEFAULT 0 COMMENT 'tokens added, new max, or 0 for ban/unban',
  `note`        VARCHAR(255) NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_admin` (`admin_id`),
  CONSTRAINT `fk_ao_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ao_admin` FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────
-- 5. jennie_tool_stats  (optional but useful)
--    Aggregate stats per tool — helps you see which tools are
--    most used without scanning jennie_history on every page.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `jennie_tool_stats` (
  `tool_id`     VARCHAR(64)     NOT NULL,
  `total_runs`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `total_tokens_consumed` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `last_run_at` DATETIME        NULL,
  PRIMARY KEY (`tool_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed a row for each tool so UPDATE works without INSERT-or-UPDATE logic
INSERT IGNORE INTO `jennie_tool_stats` (`tool_id`) VALUES
  ('compress-jpg'), ('compress-webp'), ('compress-png'),
  ('jpg-to-webp'), ('any-to-jpg'), ('any-to-png'),
  ('title-photo'), ('title-seo'), ('title-social');


-- ──────────────────────────────────────────────────────────────
-- MYSQL EVENT: prune old history rows nightly
-- Keeps the table lean (only last 90 days per user).
-- Requires event_scheduler = ON in MySQL config.
-- ──────────────────────────────────────────────────────────────
DROP EVENT IF EXISTS `ev_prune_jennie_history`;
CREATE EVENT `ev_prune_jennie_history`
  ON SCHEDULE EVERY 1 DAY
  STARTS CURRENT_TIMESTAMP
  DO
    DELETE FROM `jennie_history`
    WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 90 DAY);


-- ──────────────────────────────────────────────────────────────
-- EXAMPLE DATA: give yourself 120 tokens to test with
-- Replace 1 with your actual admin user_id
-- ──────────────────────────────────────────────────────────────
-- INSERT INTO `jennie_tokens` (user_id, tokens_left, tokens_max, reset_at)
-- VALUES (1, 120, 120, DATE_ADD(NOW(), INTERVAL 2 HOUR))
-- ON DUPLICATE KEY UPDATE tokens_left = 120, reset_at = DATE_ADD(NOW(), INTERVAL 2 HOUR);


-- ══════════════════════════════════════════════════════════════
-- ADMIN PANEL QUERIES (copy-paste into your admin page)
-- ══════════════════════════════════════════════════════════════

-- View all users and their token status:
-- SELECT u.id, u.username, u.full_name, u.is_verified,
--        t.tokens_left, t.tokens_max, t.reset_at, t.updated_at
-- FROM users u
-- LEFT JOIN jennie_tokens t ON t.user_id = u.id
-- ORDER BY t.updated_at DESC;

-- Add 60 tokens to user_id = 42:
-- UPDATE jennie_tokens SET tokens_left = LEAST(tokens_max, tokens_left + 60) WHERE user_id = 42;

-- Instantly reset user_id = 42's tokens to full:
-- UPDATE jennie_tokens SET tokens_left = tokens_max, reset_at = DATE_ADD(NOW(), INTERVAL 2 HOUR) WHERE user_id = 42;

-- Set a custom token max for a Pro user (e.g. 240):
-- UPDATE jennie_tokens SET tokens_max = 240, tokens_left = 240 WHERE user_id = 42;

-- See top 10 most active JennieAI users this week:
-- SELECT u.username, COUNT(*) AS runs, SUM(1) AS sessions
-- FROM jennie_history h
-- JOIN users u ON u.id = h.user_id
-- WHERE h.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
-- GROUP BY h.user_id
-- ORDER BY runs DESC
-- LIMIT 10;

-- See tool usage breakdown:
-- SELECT tool_id, total_runs, total_tokens_consumed, last_run_at
-- FROM jennie_tool_stats
-- ORDER BY total_runs DESC;
