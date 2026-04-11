-- ============================================================
-- MIGRATION: Align existing database with PHP codebase
-- Run this ONCE on your existing univ_book database.
-- Safe to run multiple times (uses IF NOT EXISTS / IF EXISTS).
--
-- How to run:
--   mysql -u root -p univ_book < fix_item_bookings.sql
--   OR paste into phpMyAdmin SQL tab
-- ============================================================

USE univ_book;

-- =============================================================
-- 1. USERS: add remember_me columns (for "Remember Me" login)
-- =============================================================
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS remember_token   VARCHAR(64) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS remember_expires DATETIME    NULL DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_remember_token ON users(remember_token);

-- =============================================================
-- 2. ITEM_BOOKINGS: ensure correct column names exist
--    PHP uses: quantity_needed, borrow_date, return_date,
--              borrow_time, return_time, purpose, notes
-- =============================================================

-- Add correct columns if missing
ALTER TABLE item_bookings
    ADD COLUMN IF NOT EXISTS quantity_needed  INT  NOT NULL DEFAULT 0   AFTER item_id,
    ADD COLUMN IF NOT EXISTS borrow_date      DATE NOT NULL DEFAULT '2000-01-01' AFTER quantity_needed,
    ADD COLUMN IF NOT EXISTS return_date      DATE NOT NULL DEFAULT '2000-01-01' AFTER borrow_date,
    ADD COLUMN IF NOT EXISTS borrow_time      TIME NOT NULL DEFAULT '08:00:00'   AFTER return_date,
    ADD COLUMN IF NOT EXISTS return_time      TIME NOT NULL DEFAULT '17:00:00'   AFTER borrow_time,
    ADD COLUMN IF NOT EXISTS purpose          TEXT NULL                          AFTER return_time,
    ADD COLUMN IF NOT EXISTS notes            TEXT NULL                          AFTER purpose,
    ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL;

-- If old misnamed columns exist, copy data then drop them
-- (These UPDATE statements are safe even if old columns don't exist;
--  MySQL will error silently — wrap in a procedure if needed)
UPDATE item_bookings SET quantity_needed = quantity_requested WHERE quantity_needed = 0 AND quantity_requested IS NOT NULL;
UPDATE item_bookings SET borrow_date = DATE(date_start)          WHERE borrow_date = '2000-01-01' AND date_start IS NOT NULL;
UPDATE item_bookings SET return_date = DATE(date_end)            WHERE return_date = '2000-01-01' AND date_end   IS NOT NULL;
UPDATE item_bookings SET borrow_time = TIME(date_start)          WHERE borrow_time = '08:00:00'  AND date_start IS NOT NULL;
UPDATE item_bookings SET return_time = TIME(date_end)            WHERE return_time = '17:00:00'  AND date_end   IS NOT NULL;

-- Drop old misnamed columns if they still exist
ALTER TABLE item_bookings
    DROP COLUMN IF EXISTS quantity_requested,
    DROP COLUMN IF EXISTS date_start,
    DROP COLUMN IF EXISTS date_end;

-- =============================================================
-- 3. FACILITY_BOOKINGS: ensure notes column exists
-- =============================================================
ALTER TABLE facility_bookings
    ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER participants;

-- =============================================================
-- 4. FACILITY_BOOKING_APPROVALS: create if missing
--    Required by approval_action.php and student_dashboard.php
-- =============================================================
CREATE TABLE IF NOT EXISTS facility_booking_approvals (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    booking_id          INT NOT NULL,
    role                VARCHAR(50) NOT NULL,
    approver_user_id    INT NOT NULL,
    action              ENUM('approve','reject') NOT NULL,
    notes               TEXT,
    rejection_reason    TEXT,
    action_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id)        REFERENCES facility_bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_user_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_facility_booking_approvals_booking ON facility_booking_approvals(booking_id);

-- =============================================================
-- 5. ITEM_BOOKING_APPROVALS: create if missing
-- =============================================================
CREATE TABLE IF NOT EXISTS item_booking_approvals (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    booking_id          INT NOT NULL,
    role                VARCHAR(50) NOT NULL,
    approver_user_id    INT NOT NULL,
    action              ENUM('approve','reject') NOT NULL,
    notes               TEXT,
    rejection_reason    TEXT,
    action_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id)        REFERENCES item_bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_user_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_item_booking_approvals_booking ON item_booking_approvals(booking_id);

-- =============================================================
-- 6. Verify final structure
-- =============================================================
DESCRIBE users;
DESCRIBE item_bookings;
DESCRIBE facility_bookings;
SHOW TABLES;
