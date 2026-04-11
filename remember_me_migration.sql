-- Run this once in your database to support "Remember Me" functionality
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS remember_token VARCHAR(64) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS remember_expires DATETIME NULL DEFAULT NULL,
    ADD INDEX IF NOT EXISTS idx_remember_token (remember_token);
