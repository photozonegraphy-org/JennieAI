-- ══════════════════════════════════════════════════════════════
-- JennieAI — Complete SQL Schema (v2, fixed for InfinityFree)
-- users.id is INT(11) signed — all FK types match exactly
-- Run this entire file in phpMyAdmin SQL tab at once
-- ══════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;

-- ──────────────────────────────────────────────────────────────
-- 1. jennie_tokens
--    One row per user. Tracks token balance and reset time.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `jennie_tokens` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)      NOT NULL,
  `tokens_left` SMALLINT     NOT NULL DEFAULT 120,
  `tokens_max`  SMALLINT     NOT NULL DEFAULT 120,
  `reset_at`    DATETIME     NOT NULL,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jt_user` (`user_id`),
  CONSTRAINT `fk_jt_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
-- 2. jennie_history
--    Log of every tool run per user (last 6 shown on front page)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `jennie_history` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)      NOT NULL,
  `tool_id`    VARCHAR(64)  NOT NULL,
  `label`      VARCHAR(120) NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jh_user_time` (`user_id`, `created_at`),
  CONSTRAINT `fk_jh_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
-- 3. jennie_rate_limit
--    Prevents users from hammering manifest.php
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `jennie_rate_limit` (
  `id`           INT(11)  NOT NULL AUTO_INCREMENT,
  `user_id`      INT(11)  NOT NULL,
  `hits`         SMALLINT NOT NULL DEFAULT 1,
  `window_start` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rl_user` (`user_id`),
  CONSTRAINT `fk_rl_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
-- 4. jennie_admin_overrides
--    Audit log of every admin token action
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `jennie_admin_overrides` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)      NOT NULL,
  `admin_id`   INT(11)      NOT NULL,
  `action`     VARCHAR(40)  NOT NULL,
  `value`      INT(11)      NOT NULL DEFAULT 0,
  `note`       VARCHAR(255) NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ao_user`  (`user_id`),
  KEY `idx_ao_admin` (`admin_id`),
  CONSTRAINT `fk_ao_user`
    FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ao_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
-- 5. jennie_tool_stats  (no FK needed — tool_id is just a string)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `jennie_tool_stats` (
  `tool_id`               VARCHAR(64)      NOT NULL,
  `total_runs`            INT(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_tokens_consumed` INT(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_run_at`           DATETIME         NULL,
  PRIMARY KEY (`tool_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed all tool rows so UPDATE works without INSERT-OR-UPDATE logic
INSERT IGNORE INTO `jennie_tool_stats` (`tool_id`) VALUES
  ('compress-jpg'),
  ('compress-webp'),
  ('compress-png'),
  ('jpg-to-webp'),
  ('any-to-jpg'),
  ('any-to-png'),
  ('face-detect'),
  ('bg-remove'),
  ('exif-camera'),
  ('title-photo');

SET FOREIGN_KEY_CHECKS = 1;

-- ══════════════════════════════════════════════════════════════
-- Verify everything was created:
--   SHOW TABLES LIKE 'jennie_%';
-- ══════════════════════════════════════════════════════════════
