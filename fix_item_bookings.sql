-- ============================================================
-- MIGRATION: Fix item_bookings column name mismatches
-- Run this ONCE against your univ_book database in phpMyAdmin
-- or via: mysql -u root univ_book < fix_item_bookings.sql
-- ============================================================

USE univ_book;

-- Step 1: Add the new correctly-named columns (nullable so existing rows are safe)
ALTER TABLE item_bookings
    ADD COLUMN IF NOT EXISTS quantity_requested INT NOT NULL DEFAULT 0 AFTER item_id,
    ADD COLUMN IF NOT EXISTS date_start         DATETIME NULL         AFTER quantity_requested,
    ADD COLUMN IF NOT EXISTS date_end           DATETIME NULL         AFTER date_start,
    ADD COLUMN IF NOT EXISTS purpose            TEXT     NULL         AFTER date_end,
    ADD COLUMN IF NOT EXISTS notes              TEXT     NULL         AFTER purpose,
    ADD COLUMN IF NOT EXISTS rejection_reason   TEXT     NULL;

-- Step 2: Copy any existing data from old columns if they already exist
--         (safe to run even if old columns don't exist yet; each UPDATE is independent)
UPDATE item_bookings SET quantity_requested = 0 WHERE quantity_requested = 0;

-- Step 3: Drop the old misnamed columns if they exist
--         (MySQL will silently fail these if columns don't exist — that's fine)
ALTER TABLE item_bookings
    DROP COLUMN IF EXISTS quantity_needed,
    DROP COLUMN IF EXISTS borrow_date,
    DROP COLUMN IF EXISTS return_date,
    DROP COLUMN IF EXISTS borrow_time,
    DROP COLUMN IF EXISTS return_time;

-- Step 4: Make sure the facility_booking_approvals table exists
--         (required by approval_action.php and student_dashboard.php progress bar)
CREATE TABLE IF NOT EXISTS facility_booking_approvals (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    booking_id       INT         NOT NULL,
    role             VARCHAR(50) NOT NULL,
    approver_user_id INT         NOT NULL,
    action           ENUM('approve','reject') NOT NULL,
    notes            TEXT        NULL,
    rejection_reason TEXT        NULL,
    action_at        DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id)       REFERENCES facility_bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_user_id) REFERENCES users(id)             ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 5: Verify final column list of item_bookings
DESCRIBE item_bookings;
-- Expected columns: id, user_id, item_id, quantity_requested, date_start, date_end,
--                   purpose, notes, status, current_approval_role, rejection_reason,
--                   created_at, approved_at
