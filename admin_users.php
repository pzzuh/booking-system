<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireRole('admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Users</title>
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
        .card { background: #fff; border-radius: 12px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card-header h2 { font-size: 18px; color: #1a1a2e; }
        .btn { padding: 10px 18px; border: none; border-radius: 8px;
            cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #4361ee; color: #fff; }
        .btn-primary:hover { background: #3451d1; }
        .btn-success { background: #4CAF50; color: #fff; }
        .btn-success:hover { background: #43a047; }
        .btn-warning { background: #ff9f1c; color: #fff; }
        .btn-warning:hover { background: #e68a00; }
        .btn-danger { background: #e63946; color: #fff; }
        .btn-danger:hover { background: #c62828; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #f0f2f5; font-size: 14px; }
        th { background: #f8f9fa; color: #555; font-weight: 600; font-size: 13px; }
        tr:hover { background: #fafbfc; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-active { background: #e8f5e9; color: #2e7d32; }
        .badge-inactive { background: #ffebee; color: #c62828; }
        .modal-overlay { display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal { background: #fff; border-radius: 12px; padding: 30px;
            width: 500px; max-width: 95%; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .modal h2 { margin-bottom: 20px; font-size: 20px; color: #1a1a2e; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 13px;
            color: #555; font-weight: 600; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 14px; border: 1px solid #ddd;
            border-radius: 8px; font-size: 14px; outline: none; transition: border 0.3s;
        }
        .form-group input:focus, .form-group select:focus { border-color: #4361ee; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert-danger { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
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
        <a href="admin_users.php" class="active"><i class="fas fa-users"></i> Users</a>
        <a href="admin_bookings.php"><i class="fas fa-calendar-check"></i> Bookings</a>
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
        <h1><i class="fas fa-users"></i> User Management</h1>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Add New User
        </button>
    </div>

    <div class="card">
        <div id="alertBox"></div>
        <table id="usersTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="usersBody">
                <tr><td colspan="7" style="text-align:center;padding:30px;color:#888;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal-overlay" id="userModal">
    <div class="modal">
        <h2 id="modalTitle">Add New User</h2>
        <input type="hidden" id="userId">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" id="userName" placeholder="Enter full name">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" id="userEmail" placeholder="Enter email">
        </div>
        <div class="form-group" id="passwordGroup">
            <label>Password</label>
            <input type="password" id="userPassword" placeholder="Enter password">
        </div>
        <div class="form-group">
            <label>Role</label>
            <select id="userRole">
                <option value="">-- Select Role --</option>
                <option value="admin">Admin</option>
                <option value="avp_admin">AVP Admin</option>
                <option value="vp_admin">VP Admin</option>
                <option value="ppss_director">PPSS Director</option>
                <option value="president">President</option>
                <option value="dean">Dean</option>
                <option value="adviser">Adviser</option>
                <option value="staff">Staff</option>
                <option value="dsa_director">DSA Director</option>
            </select>
        </div>
        <div class="form-group">
            <label>Department</label>
            <select id="userDepartment">
                <option value="">-- Select Department --</option>
                <option value="College of Engineering">College of Engineering</option>
                <option value="College of Business">College of Business</option>
                <option value="College of Education">College of Education</option>
                <option value="College of Arts and Sciences">College of Arts and Sciences</option>
                <option value="College of Nursing">College of Nursing</option>
                <option value="College of Computer Studies">College of Computer Studies</option>
                <option value="College of Law">College of Law</option>
                <option value="Graduate School">Graduate School</option>
                <option value="Administration">Administration</option>
                <option value="Office of Student Affairs">Office of Student Affairs</option>
                <option value="N/A">N/A</option>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select id="userStatus">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="modal-actions">
            <button class="btn" onclick="closeModal()" style="background:#f0f2f5;color:#555;">Cancel</button>
            <button class="btn btn-primary" onclick="saveUser()">Save User</button>
        </div>
    </div>
</div>

<script>
let editMode = false;

function showAlert(msg, type) {
    const box = document.getElementById('alertBox');
    box.innerHTML = `<div class="alert alert-${type}">${msg}</div>`;
    setTimeout(() => box.innerHTML = '', 4000);
}

function loadUsers() {
    fetch('api/users.php?action=list')
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('usersBody');
            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:#888;">No users found</td></tr>';
                return;
            }
            tbody.innerHTML = data.map((u, i) => `
                <tr>
                    <td>${i + 1}</td>
                    <td>${escHtml(u.name)}</td>
                    <td>${escHtml(u.email)}</td>
                    <td>${escHtml(u.role)}</td>
                    <td>${escHtml(u.department || 'N/A')}</td>
                    <td><span class="badge badge-${u.status === 'active' ? 'active' : 'inactive'}">${u.status}</span></td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick="openEditModal(${JSON.stringify(u).replace(/"/g,'&quot;')})">
                            <i class="fas fa-edit"></i> Update
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteUser(${u.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        })
        .catch(() => showAlert('Failed to load users', 'danger'));
}

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function openAddModal() {
    editMode = false;
    document.getElementById('modalTitle').textContent = 'Add New User';
    document.getElementById('userId').value = '';
    document.getElementById('userName').value = '';
    document.getElementById('userEmail').value = '';
    document.getElementById('userPassword').value = '';
    document.getElementById('userRole').value = '';
    document.getElementById('userDepartment').value = '';
    document.getElementById('userStatus').value = 'active';
    document.getElementById('passwordGroup').style.display = 'block';
    document.getElementById('userModal').classList.add('show');
}

function openEditModal(user) {
    editMode = true;
    document.getElementById('modalTitle').textContent = 'Update User';
    document.getElementById('userId').value = user.id;
    document.getElementById('userName').value = user.name;
    document.getElementById('userEmail').value = user.email;
    document.getElementById('userPassword').value = '';
    document.getElementById('userRole').value = user.role;
    document.getElementById('userDepartment').value = user.department || '';
    document.getElementById('userStatus').value = user.status;
    document.getElementById('passwordGroup').style.display = 'block';
    document.getElementById('userModal').classList.add('show');
}

function closeModal() {
    document.getElementById('userModal').classList.remove('show');
}

function saveUser() {
    const data = {
        action: editMode ? 'update' : 'add',
        id: document.getElementById('userId').value,
        name: document.getElementById('userName').value.trim(),
        email: document.getElementById('userEmail').value.trim(),
        password: document.getElementById('userPassword').value,
        role: document.getElementById('userRole').value,
        department: document.getElementById('userDepartment').value,
        status: document.getElementById('userStatus').value
    };

    if (!data.name || !data.email || !data.role || !data.department) {
        showAlert('Please fill in all required fields including department.', 'danger');
        return;
    }
    if (!editMode && !data.password) {
        showAlert('Password is required for new users.', 'danger');
        return;
    }

    fetch('api/users.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showAlert(res.message || 'User saved successfully!', 'success');
            closeModal();
            loadUsers();
        } else {
            showAlert(res.message || 'Error saving user.', 'danger');
        }
    })
    .catch(() => showAlert('Network error.', 'danger'));
}

function deleteUser(id) {
    if (!confirm('Delete this user?')) return;
    fetch('api/users.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) { showAlert('User deleted.', 'success'); loadUsers(); }
        else showAlert(res.message || 'Error deleting user.', 'danger');
    });
}

// Close modal when clicking outside
document.getElementById('userModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

loadUsers();
</script>
</body>
</html>
