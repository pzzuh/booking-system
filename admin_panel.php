<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; }
        .sidebar {
            width: 250px; height: 100vh; background: #1a1a2e;
            position: fixed; left: 0; top: 0;
            display: flex; flex-direction: column;
            padding: 20px 0;
        }
        .sidebar .logo {
            padding: 20px; text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .sidebar .logo img {
            width: 70px; height: 70px; border-radius: 50%;
            object-fit: cover; margin-bottom: 10px;
        }
        .sidebar .logo h3 { color: #fff; font-size: 14px; }
        .sidebar .logo p { color: #aaa; font-size: 12px; }
        .sidebar nav { flex: 1; }
        .sidebar nav a {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 20px; color: #ccc;
            text-decoration: none; font-size: 14px;
            transition: all 0.3s;
        }
        .sidebar nav a:hover, .sidebar nav a.active {
            background: rgba(255,255,255,0.1);
            color: #fff; border-left: 3px solid #4CAF50;
        }
        .sidebar nav a i { width: 20px; text-align: center; }
        .logout-btn {
            padding: 15px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .logout-btn a {
            display: flex; align-items: center; gap: 12px;
            color: #ff6b6b; text-decoration: none;
            font-size: 14px; padding: 10px;
            border-radius: 8px; transition: all 0.3s;
        }
        .logout-btn a:hover {
            background: rgba(255,107,107,0.15);
        }
        .main-content {
            margin-left: 250px; padding: 30px;
            min-height: 100vh;
        }
        .header {
            background: #fff; padding: 20px 30px;
            border-radius: 12px; margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex; justify-content: space-between; align-items: center;
        }
        .header h1 { font-size: 24px; color: #1a1a2e; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-card {
            background: #fff; padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex; align-items: center; gap: 20px;
        }
        .stat-icon {
            width: 55px; height: 55px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: #fff;
        }
        .stat-icon.blue { background: #4361ee; }
        .stat-icon.green { background: #4CAF50; }
        .stat-icon.orange { background: #ff9f1c; }
        .stat-icon.red { background: #e63946; }
        .stat-info h3 { font-size: 28px; color: #1a1a2e; font-weight: 700; }
        .stat-info p { font-size: 13px; color: #888; }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="logo">
        <img src="assets/logo.png" alt="Logo" onerror="this.style.display='none'">
        <h3>Booking System</h3>
        <p>Admin Panel</p>
    </div>
    <nav>
        <a href="admin_panel.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="admin_users.php"><i class="fas fa-users"></i> Users</a>
        <a href="admin_bookings.php"><i class="fas fa-calendar-check"></i> Bookings</a>
        <a href="admin_facilities.php"><i class="fas fa-building"></i> Facilities</a>
        <a href="admin_items.php"><i class="fas fa-box"></i> Items</a>
    </nav>
    <div class="logout-btn">
        <a href="logout.php" onclick="return confirmLogout()">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <h1>Dashboard</h1>
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3 id="totalUsers">--</h3>
                <p>Total Users</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-info">
                <h3 id="totalBookings">--</h3>
                <p>Total Bookings</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-building"></i></div>
            <div class="stat-info">
                <h3 id="totalFacilities">--</h3>
                <p>Facilities</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-box"></i></div>
            <div class="stat-info">
                <h3 id="totalItems">--</h3>
                <p>Items</p>
            </div>
        </div>
    </div>
</div>

<script>
function confirmLogout() {
    return confirm('Are you sure you want to logout?');
}

// Load dashboard stats
fetch('api/get_stats.php')
    .then(r => r.json())
    .then(data => {
        if (data.users !== undefined) document.getElementById('totalUsers').textContent = data.users;
        if (data.bookings !== undefined) document.getElementById('totalBookings').textContent = data.bookings;
        if (data.facilities !== undefined) document.getElementById('totalFacilities').textContent = data.facilities;
        if (data.items !== undefined) document.getElementById('totalItems').textContent = data.items;
    })
    .catch(() => {}); // silently fail if API not ready
</script>
</body>
</html>
