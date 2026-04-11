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
    <title>Admin - Bookings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; }
        .sidebar {
            width: 250px; height: 100vh; background: #1a1a2e;
            position: fixed; left: 0; top: 0;
            display: flex; flex-direction: column; padding: 20px 0;
        }
        .sidebar .logo { padding: 20px; text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar .logo h3 { color: #fff; font-size: 14px; }
        .sidebar .logo p { color: #aaa; font-size: 12px; }
        .sidebar nav a {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 20px; color: #ccc;
            text-decoration: none; font-size: 14px; transition: all 0.3s;
        }
        .sidebar nav a:hover, .sidebar nav a.active {
            background: rgba(255,255,255,0.1); color: #fff; border-left: 3px solid #4CAF50;
        }
        .sidebar nav a i { width: 20px; text-align: center; }
        .logout-btn { padding: 15px 20px; border-top: 1px solid rgba(255,255,255,0.1); }
        .logout-btn a {
            display: flex; align-items: center; gap: 12px;
            color: #ff6b6b; text-decoration: none; font-size: 14px;
            padding: 10px; border-radius: 8px; transition: all 0.3s;
        }
        .logout-btn a:hover { background: rgba(255,107,107,0.15); }
        .main-content { margin-left: 250px; padding: 30px; min-height: 100vh; }
        .header { background: #fff; padding: 20px 30px; border-radius: 12px;
            margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; color: #1a1a2e; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab-btn {
            padding: 10px 20px; border: 2px solid #ddd; background: #fff;
            border-radius: 8px; cursor: pointer; font-size: 14px;
            color: #555; transition: all 0.3s; font-weight: 600;
        }
        .tab-btn.active { border-color: #4361ee; background: #4361ee; color: #fff; }
        .tab-btn:hover:not(.active) { border-color: #4361ee; color: #4361ee; }
        .card { background: #fff; border-radius: 12px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .btn { padding: 8px 14px; border: none; border-radius: 8px;
            cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
        .btn-success { background: #4CAF50; color: #fff; }
        .btn-success:hover { background: #43a047; }
        .btn-danger { background: #e63946; color: #fff; }
        .btn-danger:hover { background: #c62828; }
        .btn-info { background: #4361ee; color: #fff; }
        .btn-info:hover { background: #3451d1; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #f0f2f5; font-size: 13px; }
        th { background: #f8f9fa; color: #555; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        tr:hover { background: #fafbfc; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-pending { background: #fff3e0; color: #e65100; }
        .badge-approved { background: #e8f5e9; color: #2e7d32; }
        .badge-rejected { background: #ffebee; color: #c62828; }
        .badge-cancelled { background: #f5f5f5; color: #616161; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert-danger { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .filters { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .filters select, .filters input {
            padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px;
            font-size: 14px; outline: none;
        }
        .filters select:focus, .filters input:focus { border-color: #4361ee; }
        .items-list { font-size: 12px; color: #666; }
        .items-list span { display: inline-block; background: #e3f2fd; color: #1565c0;
            padding: 2px 8px; border-radius: 12px; margin: 2px; }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="logo">
        <h3>Booking System</h3>
        <p>Admin Panel</p>
    </div>
    <nav>
        <a href="admin_panel.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="admin_users.php"><i class="fas fa-users"></i> Users</a>
        <a href="admin_bookings.php" class="active"><i class="fas fa-calendar-check"></i> Bookings</a>
        <a href="admin_facilities.php"><i class="fas fa-building"></i> Facilities</a>
        <a href="admin_items.php"><i class="fas fa-box"></i> Items</a>
    </nav>
    <div class="logout-btn">
        <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <h1><i class="fas fa-calendar-check"></i> Bookings Management</h1>
    </div>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('facility')">
            <i class="fas fa-building"></i> Facility Bookings
        </button>
        <button class="tab-btn" onclick="switchTab('item')">
            <i class="fas fa-box"></i> Item Bookings
        </button>
    </div>

    <div class="card">
        <div id="alertBox"></div>

        <div class="filters">
            <select id="statusFilter" onchange="loadBookings()">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <input type="date" id="dateFilter" onchange="loadBookings()" placeholder="Filter by date">
            <button class="btn btn-info" onclick="clearFilters()"><i class="fas fa-times"></i> Clear</button>
        </div>

        <!-- Facility Bookings Table -->
        <div id="facilityBookingsTab">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Booking ID</th>
                        <th>Requester</th>
                        <th>Facility</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="facilityBookingsBody">
                    <tr><td colspan="9" style="text-align:center;padding:30px;color:#888;">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Item Bookings Table -->
        <div id="itemBookingsTab" style="display:none;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Booking ID</th>
                        <th>Requester</th>
                        <th>Items Requested</th>
                        <th>Quantity</th>
                        <th>Date Needed</th>
                        <th>Return Date</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="itemBookingsBody">
                    <tr><td colspan="10" style="text-align:center;padding:30px;color:#888;">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let currentTab = 'facility';

function showAlert(msg, type) {
    const box = document.getElementById('alertBox');
    box.innerHTML = `<div class="alert alert-${type}">${msg}</div>`;
    setTimeout(() => box.innerHTML = '', 4000);
}

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.tab-btn').forEach((btn, i) => {
        btn.classList.toggle('active', (i === 0 && tab === 'facility') || (i === 1 && tab === 'item'));
    });
    document.getElementById('facilityBookingsTab').style.display = tab === 'facility' ? 'block' : 'none';
    document.getElementById('itemBookingsTab').style.display = tab === 'item' ? 'block' : 'none';
    loadBookings();
}

function clearFilters() {
    document.getElementById('statusFilter').value = '';
    document.getElementById('dateFilter').value = '';
    loadBookings();
}

function loadBookings() {
    const status = document.getElementById('statusFilter').value;
    const date = document.getElementById('dateFilter').value;
    const params = new URLSearchParams({ type: currentTab });
    if (status) params.append('status', status);
    if (date) params.append('date', date);

    fetch(`api/bookings.php?${params}`)
        .then(r => r.json())
        .then(data => {
            if (currentTab === 'facility') renderFacilityBookings(data);
            else renderItemBookings(data);
        })
        .catch(() => showAlert('Failed to load bookings', 'danger'));
}

function renderFacilityBookings(data) {
    const tbody = document.getElementById('facilityBookingsBody');
    if (!data.length) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:30px;color:#888;">No facility bookings found</td></tr>';
        return;
    }
    tbody.innerHTML = data.map((b, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>#${b.id}</td>
            <td>${escHtml(b.requester_name)}<br><small style="color:#888">${escHtml(b.department || '')}</small></td>
            <td>${escHtml(b.facility_name)}</td>
            <td>${escHtml(b.booking_date)}</td>
            <td>${escHtml(b.start_time)} - ${escHtml(b.end_time)}</td>
            <td>${escHtml(b.purpose)}</td>
            <td><span class="badge badge-${b.status}">${b.status}</span></td>
            <td>${getActionButtons(b)}</td>
        </tr>
    `).join('');
}

function renderItemBookings(data) {
    const tbody = document.getElementById('itemBookingsBody');
    if (!data.length) {
        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:30px;color:#888;">No item bookings found</td></tr>';
        return;
    }
    tbody.innerHTML = data.map((b, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>#${b.id}</td>
            <td>${escHtml(b.requester_name)}<br><small style="color:#888">${escHtml(b.department || '')}</small></td>
            <td>
                <div class="items-list">
                    ${(b.items || b.item_name || 'N/A').split(',').map(item =>
                        `<span>${escHtml(item.trim())}</span>`
                    ).join('')}
                </div>
            </td>
            <td>${escHtml(b.quantity || 1)}</td>
            <td>${escHtml(b.date_needed || b.booking_date)}</td>
            <td>${escHtml(b.return_date || 'N/A')}</td>
            <td>${escHtml(b.purpose)}</td>
            <td><span class="badge badge-${b.status}">${b.status}</span></td>
            <td>${getActionButtons(b)}</td>
        </tr>
    `).join('');
}

function getActionButtons(b) {
    if (b.status === 'pending') {
        return `
            <button class="btn btn-success" style="margin-bottom:4px" onclick="updateStatus(${b.id}, 'approved')">
                <i class="fas fa-check"></i> Approve
            </button>
            <button class="btn btn-danger" onclick="updateStatus(${b.id}, 'rejected')">
                <i class="fas fa-times"></i> Reject
            </button>
        `;
    }
    return `<span style="color:#888;font-size:12px">${b.status.charAt(0).toUpperCase() + b.status.slice(1)}</span>`;
}

function updateStatus(id, status) {
    const action = status === 'approved' ? 'approve' : 'reject';
    if (!confirm(`Are you sure you want to ${action} this booking?`)) return;

    fetch('api/bookings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_status', id, status, type: currentTab })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showAlert(`Booking ${status} successfully!`, 'success');
            loadBookings();
        } else showAlert(res.message || 'Error updating booking', 'danger');
    })
    .catch(() => showAlert('Network error', 'danger'));
}

// Initial load
loadBookings();
</script>
</body>
</html>
