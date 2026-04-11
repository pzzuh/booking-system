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
    <title>Admin - Items</title>
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
        .btn { padding: 10px 18px; border: none; border-radius: 8px;
            cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #4361ee; color: #fff; }
        .btn-primary:hover { background: #3451d1; }
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
        .badge-available { background: #e8f5e9; color: #2e7d32; }
        .badge-unavailable { background: #ffebee; color: #c62828; }
        .modal-overlay { display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal { background: #fff; border-radius: 12px; padding: 30px;
            width: 500px; max-width: 95%; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .modal h2 { margin-bottom: 20px; font-size: 20px; color: #1a1a2e; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 13px;
            color: #555; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 14px; border: 1px solid #ddd;
            border-radius: 8px; font-size: 14px; outline: none; transition: border 0.3s;
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #4361ee; }
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
        <a href="admin_users.php"><i class="fas fa-users"></i> Users</a>
        <a href="admin_bookings.php"><i class="fas fa-calendar-check"></i> Bookings</a>
        <a href="admin_facilities.php"><i class="fas fa-building"></i> Facilities</a>
        <a href="admin_items.php" class="active"><i class="fas fa-box"></i> Items</a>
    </nav>
    <div class="logout-btn">
        <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <h1><i class="fas fa-box"></i> Items Management</h1>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Add Item
        </button>
    </div>

    <div class="card">
        <div id="alertBox"></div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="itemsBody">
                <tr><td colspan="6" style="text-align:center;padding:30px;color:#888;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Item Modal -->
<div class="modal-overlay" id="itemModal">
    <div class="modal">
        <h2 id="modalTitle">Add Item</h2>
        <input type="hidden" id="itemId">
        <div class="form-group">
            <label>Item Name</label>
            <input type="text" id="itemName" placeholder="Enter item name">
        </div>
        <div class="form-group">
            <label>Category</label>
            <select id="itemCategory">
                <option value="">-- Select Category --</option>
                <option value="Equipment">Equipment</option>
                <option value="Furniture">Furniture</option>
                <option value="Electronics">Electronics</option>
                <option value="Sports">Sports</option>
                <option value="Audio/Visual">Audio/Visual</option>
                <option value="Others">Others</option>
            </select>
        </div>
        <div class="form-group">
            <label>Quantity</label>
            <input type="number" id="itemQuantity" placeholder="Available quantity" min="0">
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea id="itemDescription" placeholder="Optional description"></textarea>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select id="itemStatus">
                <option value="available">Available</option>
                <option value="unavailable">Unavailable</option>
            </select>
        </div>
        <div class="modal-actions">
            <button class="btn" onclick="closeModal()" style="background:#f0f2f5;color:#555;">Cancel</button>
            <button class="btn btn-primary" onclick="saveItem()">Save</button>
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

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function loadItems() {
    fetch('api/items.php?action=list')
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('itemsBody');
            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:#888;">No items found</td></tr>';
                return;
            }
            tbody.innerHTML = data.map((item, i) => `
                <tr>
                    <td>${i + 1}</td>
                    <td>${escHtml(item.name)}</td>
                    <td>${escHtml(item.category)}</td>
                    <td>${escHtml(item.quantity)}</td>
                    <td><span class="badge badge-${item.status === 'available' ? 'available' : 'unavailable'}">${item.status}</span></td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick="openEditModal(${JSON.stringify(item).replace(/"/g,'&quot;')})">
                            <i class="fas fa-edit"></i> Update
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteItem(${item.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        })
        .catch(() => showAlert('Failed to load items', 'danger'));
}

function openAddModal() {
    editMode = false;
    document.getElementById('modalTitle').textContent = 'Add Item';
    document.getElementById('itemId').value = '';
    document.getElementById('itemName').value = '';
    document.getElementById('itemCategory').value = '';
    document.getElementById('itemQuantity').value = '';
    document.getElementById('itemDescription').value = '';
    document.getElementById('itemStatus').value = 'available';
    document.getElementById('itemModal').classList.add('show');
}

function openEditModal(item) {
    editMode = true;
    document.getElementById('modalTitle').textContent = 'Update Item';
    document.getElementById('itemId').value = item.id;
    document.getElementById('itemName').value = item.name;
    document.getElementById('itemCategory').value = item.category;
    document.getElementById('itemQuantity').value = item.quantity;
    document.getElementById('itemDescription').value = item.description || '';
    document.getElementById('itemStatus').value = item.status;
    document.getElementById('itemModal').classList.add('show');
}

function closeModal() {
    document.getElementById('itemModal').classList.remove('show');
}

function saveItem() {
    const data = {
        action: editMode ? 'update' : 'add',
        id: document.getElementById('itemId').value,
        name: document.getElementById('itemName').value.trim(),
        category: document.getElementById('itemCategory').value,
        quantity: document.getElementById('itemQuantity').value,
        description: document.getElementById('itemDescription').value.trim(),
        status: document.getElementById('itemStatus').value
    };

    if (!data.name || !data.category || data.quantity === '') {
        showAlert('Please fill in all required fields.', 'danger');
        return;
    }

    fetch('api/items.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showAlert(res.message || 'Saved!', 'success');
            closeModal();
            loadItems();
        } else showAlert(res.message || 'Error', 'danger');
    })
    .catch(() => showAlert('Network error', 'danger'));
}

function deleteItem(id) {
    if (!confirm('Delete this item?')) return;
    fetch('api/items.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) { showAlert('Deleted.', 'success'); loadItems(); }
        else showAlert(res.message || 'Error', 'danger');
    });
}

document.getElementById('itemModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

loadItems();
</script>
</body>
</html>
