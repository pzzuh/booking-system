-- ============================================================
-- University Booking System Database — FIXED VERSION
-- School: Notre Dame of Marbel University (NDMU)
-- Database Name: univ_book
-- Fixed: item_bookings columns match PHP code
--        facility_booking_approvals table added (was missing)
--        facility_bookings notes column added
-- ============================================================

CREATE DATABASE IF NOT EXISTS univ_book CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE univ_book;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================
-- USERS
-- =============================================================
CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role ENUM(
        'student','adviser','staff',
        'dsa_director','ppss_director','dean',
        'avp_admin','vp_admin','president',
        'admin','janitor','security'
    ) NOT NULL DEFAULT 'student',
    phone           VARCHAR(20),
    department      VARCHAR(255),
    student_id      VARCHAR(50),
    profile_photo   VARCHAR(255),
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- FACILITIES
-- =============================================================
CREATE TABLE IF NOT EXISTS facilities (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    location    VARCHAR(255) NOT NULL,
    capacity    INT NOT NULL,
    photo_path  VARCHAR(255),
    description TEXT,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- ITEMS (equipment / supplies)
-- =============================================================
CREATE TABLE IF NOT EXISTS items (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(255) NOT NULL,
    category            VARCHAR(100) NOT NULL,
    quantity_available  INT NOT NULL DEFAULT 0,
    photo_path          VARCHAR(255),
    description         TEXT,
    is_active           TINYINT(1) DEFAULT 1,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- FACILITY BOOKINGS
-- FIXED: added `notes` column used by book_facility_form.php
-- =============================================================
CREATE TABLE IF NOT EXISTS facility_bookings (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    user_id                 INT NOT NULL,
    facility_id             INT NOT NULL,
    title                   VARCHAR(255) NOT NULL DEFAULT '',
    date_start              DATE NOT NULL,
    date_end                DATE NOT NULL,
    time_start              TIME NOT NULL DEFAULT '08:00:00',
    time_end                TIME NOT NULL DEFAULT '17:00:00',
    purpose                 TEXT,
    participants            INT DEFAULT 0,
    notes                   TEXT,
    status ENUM('pending','approved','rejected','fully_approved','cancelled') DEFAULT 'pending',
    current_approval_role ENUM(
        'adviser','staff','dsa_director','ppss_director',
        'dean','avp_admin','vp_admin','president'
    ) DEFAULT 'adviser',
    rejection_reason        TEXT,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at             TIMESTAMP NULL,
    FOREIGN KEY (user_id)       REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (facility_id)   REFERENCES facilities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- FACILITY BOOKING APPROVALS
-- FIXED: This table was entirely missing from the original SQL
--        but is referenced in approval_action.php and
--        student_dashboard.php (progressForFacilityBooking)
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

-- =============================================================
-- ITEM BOOKINGS
-- FIXED: columns renamed to match what book_item_form.php,
--        student_dashboard.php, and check_item_conflict.php use:
--          date_start     -> borrow_date  (DATE)
--          date_end       -> return_date  (DATE)
--          quantity_requested -> quantity_needed
--        ADDED: borrow_time, return_time, notes columns
-- =============================================================
CREATE TABLE IF NOT EXISTS item_bookings (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    user_id                 INT NOT NULL,
    item_id                 INT NOT NULL,
    quantity_needed         INT NOT NULL,
    borrow_date             DATE NOT NULL,
    return_date             DATE NOT NULL,
    borrow_time             TIME NOT NULL DEFAULT '08:00:00',
    return_time             TIME NOT NULL DEFAULT '17:00:00',
    purpose                 TEXT,
    notes                   TEXT,
    status ENUM('pending','approved','rejected','fully_approved','cancelled') DEFAULT 'pending',
    current_approval_role ENUM(
        'adviser','staff','dsa_director','ppss_director',
        'dean','avp_admin','vp_admin','president'
    ) DEFAULT 'adviser',
    rejection_reason        TEXT,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at             TIMESTAMP NULL,
    FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id)   REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- ITEM BOOKING APPROVALS
-- Required for item booking approval history tracking
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

-- =============================================================
-- MAINTENANCE ALERTS
-- =============================================================
CREATE TABLE IF NOT EXISTS maintenance_alerts (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    facility_id         INT NOT NULL,
    title               VARCHAR(255) NOT NULL,
    description         TEXT,
    severity            ENUM('low','medium','high','critical') DEFAULT 'medium',
    date_start          DATETIME NOT NULL,
    date_end            DATETIME NOT NULL,
    assigned_to_role    ENUM('janitor','security','staff','admin'),
    status              ENUM('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
    created_by_user_id  INT NOT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (facility_id)           REFERENCES facilities(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id)    REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- FAQ ENTRIES
-- =============================================================
CREATE TABLE IF NOT EXISTS faq_entries (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category    VARCHAR(100) NOT NULL,
    question    TEXT NOT NULL,
    answer      TEXT NOT NULL,
    sort_order  INT DEFAULT 0,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SYSTEM SETTINGS
-- =============================================================
CREATE TABLE IF NOT EXISTS system_settings (
    `key`   VARCHAR(100) PRIMARY KEY,
    `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- NOTIFICATIONS
-- =============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    title       VARCHAR(255) NOT NULL,
    message     TEXT NOT NULL,
    type        VARCHAR(50) DEFAULT 'info',
    booking_id  INT DEFAULT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- MESSAGES (contact form)
-- =============================================================
CREATE TABLE IF NOT EXISTS messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    message     TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- INDEXES
-- =============================================================
CREATE INDEX idx_users_email                        ON users(email);
CREATE INDEX idx_users_role                         ON users(role);
CREATE INDEX idx_facility_bookings_user_id          ON facility_bookings(user_id);
CREATE INDEX idx_facility_bookings_facility_id      ON facility_bookings(facility_id);
CREATE INDEX idx_facility_bookings_status           ON facility_bookings(status);
CREATE INDEX idx_facility_bookings_approval_role    ON facility_bookings(current_approval_role);
CREATE INDEX idx_facility_booking_approvals_booking ON facility_booking_approvals(booking_id);
CREATE INDEX idx_item_bookings_user_id              ON item_bookings(user_id);
CREATE INDEX idx_item_bookings_item_id              ON item_bookings(item_id);
CREATE INDEX idx_item_bookings_status               ON item_bookings(status);
CREATE INDEX idx_item_bookings_approval_role        ON item_bookings(current_approval_role);
CREATE INDEX idx_item_booking_approvals_booking      ON item_booking_approvals(booking_id);
CREATE INDEX idx_maintenance_alerts_facility_id     ON maintenance_alerts(facility_id);
CREATE INDEX idx_maintenance_alerts_role            ON maintenance_alerts(assigned_to_role);
CREATE INDEX idx_maintenance_alerts_status          ON maintenance_alerts(status);
CREATE INDEX idx_notifications_user_id              ON notifications(user_id);
CREATE INDEX idx_notifications_is_read              ON notifications(is_read);

-- =============================================================
-- DEFAULT ADMIN ACCOUNT
-- Email:    admin@ndmu.edu.ph
-- Password: Super@admin123
-- =============================================================
INSERT INTO users (full_name, email, password_hash, role, is_active) VALUES
('System Administrator', 'admin@ndmu.edu.ph',
 '$2b$12$JBdcE.z4YOsVXHwudM05FuPnw0mau37SEMb1lUtX.fekjr/SYyz16',
 'admin', 1);

-- =============================================================
-- SYSTEM SETTINGS DEFAULTS
-- =============================================================
INSERT INTO system_settings (`key`, `value`) VALUES
('school_name',             'Notre Dame of Marbel University'),
('address',                 'Marbel, Koronadal City, South Cotabato, Philippines'),
('site_description',        'Facility and Item Booking System'),
('contact_email',           'admin@ndmu.edu.ph'),
('timezone',                'Asia/Manila'),
('max_booking_days_ahead',  '30'),
('require_approval',        '1'),
('allow_weekend_bookings',  '0');

-- =============================================================
-- SAMPLE FAQ ENTRIES
-- =============================================================
INSERT INTO faq_entries (category, question, answer, sort_order, is_active) VALUES
('General', 'How do I book a facility?',
 'Log in to your account, go to Facilities, select a facility, pick your date and time, and submit. Your request will go through the approval workflow.',
 1, 1),
('General', 'How do I borrow equipment?',
 'Go to Items, choose the equipment you need, specify quantity and dates, and submit for approval.',
 2, 1),
('General', 'What is the approval process?',
 'Bookings follow a multi-level approval chain: Adviser → Staff → Dean → PPSS Director → DSA Director → AVP Admin → VP Admin → President.',
 3, 1),
('General', 'What role will I have after registering?',
 'All newly registered accounts are assigned the Student role by default. An administrator can change your role as needed.',
 4, 1),
('Facilities', 'Can I book on weekends?',
 'Weekend availability depends on system settings and the specific facility. Check the facility calendar for open slots.',
 5, 1),
('Items', 'How long can I borrow equipment?',
 'Borrowing periods vary by item. Check the item detail page for the maximum duration.',
 6, 1),
('Technical', 'I forgot my password. What do I do?',
 'Click "Forgot Password" on the login page and follow the instructions sent to your registered email.',
 7, 1);

-- =============================================================
-- SAMPLE FACILITIES
-- =============================================================
INSERT INTO facilities (name, location, capacity, is_active) VALUES
('NDMU Auditorium',         'Main Building, Ground Floor',          800,  1),
('AVP Conference Room',     'Administration Building, 2nd Floor',   40,   1),
('College Gymnasium',       'Sports Complex',                        1000, 1),
('Computer Laboratory 1',   'IT Building, 1st Floor',               30,   1),
('Library Study Room A',    'Library Building, 3rd Floor',          20,   1),
('Covered Court',           'Campus Grounds',                        500,  1),
('Multi-Purpose Hall',      'Student Center',                        200,  1);

-- =============================================================
-- SAMPLE ITEMS / EQUIPMENT
-- =============================================================
INSERT INTO items (name, category, quantity_available, is_active) VALUES
('LCD Projector',               'Equipment',    5,   1),
('Laptop',                      'Equipment',    10,  1),
('Wireless Microphone',         'Equipment',    8,   1),
('Extension Cord (10m)',         'Equipment',    15,  1),
('Portable Speaker System',     'Equipment',    3,   1),
('Whiteboard Markers Set',      'Supplies',     20,  1),
('Folding Tables',              'Furniture',    30,  1),
('Monobloc Chairs',             'Furniture',    200, 1);

SET FOREIGN_KEY_CHECKS = 1;
