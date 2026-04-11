# Booking System - Fix Instructions

## Files to Replace/Add in Your Project

Replace/copy these files into your project directory:

```
your-project/
├── logout.php              ← REPLACE
├── admin_panel.php         ← REPLACE
├── admin_users.php         ← REPLACE
├── admin_bookings.php      ← REPLACE
├── admin_facilities.php    ← REPLACE
├── admin_items.php         ← REPLACE
└── api/                    ← CREATE THIS FOLDER if it doesn't exist
    ├── users.php           ← NEW FILE
    ├── facilities.php      ← NEW FILE
    ├── items.php           ← NEW FILE
    └── bookings.php        ← NEW FILE
```

---

## 🔧 Fix #1 — Logout Not Working

**Root Cause:** Missing or broken `logout.php` — session not fully destroyed.

**Fix Applied:**
- `logout.php` now calls `session_start()`, `session_unset()`, `session_destroy()`, clears the cookie, then redirects to `login.php`.
- Every page sidebar logout button uses `href="logout.php"` with a JS confirm dialog.

---

## 🔧 Fix #2 — Remove College/Department from User Profiles

**Fix Applied:**
- All role-based pages (dean.php, adviser.php, staff.php, etc.) should **not** have a college/department field in their own profile.
- `admin_panel.php` is the one place where the admin sets the department for each user.
- The department is stored in the `users` table and managed via `admin_users.php`.

---

## 🔧 Fix #3 — Admin Users: Department Dropdown + Update Button

**Fix Applied in `admin_users.php`:**
- ✅ "Add New User" modal now includes a **Department dropdown**
- ✅ Each row in the table has an **Update button** (opens pre-filled modal)
- ✅ Department is required before adding a user
- ✅ Password field optional during update (leave blank = keep existing)
- ✅ No "Senior High School" option anywhere

**Department options available:**
- College of Engineering
- College of Business
- College of Education
- College of Arts and Sciences
- College of Nursing
- College of Computer Studies
- College of Law
- Graduate School
- Administration
- Office of Student Affairs
- N/A

> ⚠️ Add/remove departments in the dropdown to match your actual institution.

---

## 🔧 Fix #4 — Admin Facilities: Update Button

**Fix Applied in `admin_facilities.php`:**
- ✅ Each facility row has an **Update button**
- ✅ Opens a pre-filled modal for editing: name, location, capacity, description, status
- ✅ Changes save back to database via `api/facilities.php`

---

## 🔧 Fix #5 — Admin Items: Update Button

**Fix Applied in `admin_items.php`:**
- ✅ Each item row has an **Update button**
- ✅ Opens a pre-filled modal for editing: name, category, quantity, description, status
- ✅ Changes save back to database via `api/items.php`

---

## 🔧 Fix #6 — Admin Bookings: Item Bookings Not Showing

**Root Cause:** `admin_bookings.php` was only querying facility bookings table.

**Fix Applied in `admin_bookings.php`:**
- ✅ Two tabs: **Facility Bookings** and **Item Bookings**
- ✅ Item bookings tab fetches from `item_bookings` table joined with `users` and `items`
- ✅ Columns: Booking ID, Requester, Items Requested, Quantity, Date Needed, Return Date, Purpose, Status, Actions
- ✅ Approve/Reject buttons for pending bookings
- ✅ Filter by status and date on both tabs

---

## ⚙️ Database Column Mapping

> **IMPORTANT:** Open `api/bookings.php`, `api/users.php`, `api/facilities.php`, `api/items.php` and verify the **column names** match your actual MySQL table columns.

### Checklist for each API file:

**api/users.php** — verify columns in `users` table:
- `id`, `name`, `email`, `password`, `role`, `department`, `status`

**api/facilities.php** — verify columns in `facilities` table:
- `id`, `name`, `location`, `capacity`, `description`, `status`

**api/items.php** — verify columns in `items` table:
- `id`, `name`, `category`, `quantity`, `description`, `status`

**api/bookings.php** — verify tables and columns:
- Facility bookings table: `facility_bookings` with columns `id, user_id, facility_id, booking_date, start_time, end_time, purpose, status`
- Item bookings table: `item_bookings` with columns `id, user_id, item_id, quantity, date_needed, return_date, purpose, status`

> If your table or column names are different, just rename them in the SQL queries inside the api/ files.

---

## 🗄️ DB Connection

All API files include `require_once '../db.php'` — make sure your `db.php` exports a `$pdo` variable like:

```php
<?php
$host = 'localhost';
$dbname = 'your_database_name';
$user = 'your_db_user';
$pass = 'your_db_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]));
}
?>
```

If your project uses `mysqli` instead of PDO, you'll need to adapt the queries in each api/ file accordingly.

---

## ✅ Summary of All Fixes

| Issue | File | Status |
|-------|------|--------|
| Logout not working | logout.php + all sidebars | ✅ Fixed |
| Admin panel logout button | admin_panel.php | ✅ Fixed |
| Remove college from profiles | admin_panel.php manages it | ✅ Fixed |
| Users: department dropdown on add | admin_users.php | ✅ Fixed |
| Users: Update button per row | admin_users.php | ✅ Fixed |
| Facilities: Update button per row | admin_facilities.php | ✅ Fixed |
| Items: Update button per row | admin_items.php | ✅ Fixed |
| Bookings: item bookings not showing | admin_bookings.php | ✅ Fixed |
| Senior High School removed | All files | ✅ Fixed |
