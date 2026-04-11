-- Run this SQL on your database to add the item_booking_approvals table
-- which is required for item booking approval history tracking.

CREATE TABLE IF NOT EXISTS item_booking_approvals (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    booking_id          INT NOT NULL,
    role                ENUM('adviser','staff','dsa_director','ppss_director','dean','avp_admin','vp_admin','president') NOT NULL,
    approver_user_id    INT,
    action              ENUM('approve','reject') NOT NULL,
    notes               TEXT,
    rejection_reason    TEXT,
    action_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES item_bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_item_booking_approvals_booking ON item_booking_approvals(booking_id);
