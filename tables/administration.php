<?php
require '../php/db.php';

// Add or Edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = intval($_POST['user_id'] ?? 0);
    $username = $conn->real_escape_string(trim($_POST['username']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $role = in_array($_POST['role'], ['admin', 'teacher']) ? $_POST['role'] : 'teacher';
    $is_locked = isset($_POST['is_locked']) ? 1 : 0;
    $password = trim($_POST['password'] ?? '');

    if (!empty($password)) {
        $hashed = hash('sha256', $password);
        $pwd_sql = ", password='$hashed'";
    } else {
        $pwd_sql = "";
    }

    if ($userId > 0) {
        $sql = "UPDATE administration SET username='$username', email='$email', role='$role', is_locked=$is_locked $pwd_sql WHERE id=$userId";
        $msg = $conn->query($sql) ? "User updated successfully." : "Update error: " . $conn->error;
    } else {
        if ($password === '') {
            $msg = "Password is required for new user.";
        } else {
            $sql = "INSERT INTO administration (username, password, email, role, is_locked)
                    VALUES ('$username', '$hashed', '$email', '$role', $is_locked)";
            $msg = $conn->query($sql) ? "User added successfully." : "Insertion error: " . $conn->error;
        }
    }

    header("Location: administration.php?msg=" . urlencode($msg));
    exit();
}

// Delete user
if (isset($_GET['delete'])) {
    $conn->query("DELETE FROM administration WHERE id=" . intval($_GET['delete']));
    header("Location: administration.php?msg=" . urlencode("User deleted."));
    exit();
}

// Lock/Unlock user
if (isset($_GET['lock']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['lock'] == '0') {
        $conn->query("UPDATE administration SET is_locked=0, login_attempts=0 WHERE id=$id");
        $actionMsg = "User unlocked, attempts reset.";
    } else {
        $conn->query("UPDATE administration SET is_locked=1 WHERE id=$id");
        $actionMsg = "User locked.";
    }
    header("Location: administration.php?msg=" . urlencode($actionMsg));
    exit();
}

$msg = $_GET['msg'] ?? '';
$result = $conn->query("SELECT * FROM administration ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Administration Panel</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f6f9;
      margin: 0;
      padding: 20px;
      color: #333;
    }
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      margin-bottom: 25px;
    }
    h1 {
      margin: 0;
      font-size: 24px;
    }
    .btn {
      padding: 8px 14px;
      border: none;
      border-radius: 4px;
      color: white;
      cursor: pointer;
      text-decoration: none;
      margin: 4px 0;
    }
    .btn-add { background: #2980b9; }
    .btn-edit { background: #27ae60; }
    .btn-delete { background: #c0392b; }
    .btn-lock { background: #f39c12; }
    .btn-unlock { background: #27ae60; }
    .btn:hover { opacity: 0.9; }
    .msg {
      padding: 10px;
      background: #d4edda;
      border: 1px solid #c3e6cb;
      margin-bottom: 20px;
      border-radius: 5px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      box-shadow: 0 0 5px rgba(0,0,0,0.1);
    }
    th, td {
      padding: 12px;
      border: 1px solid #ddd;
      text-align: left;
    }
    th {
      background: #2c3e50;
      color: white;
    }
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 999;
    }
    .modal {
      background: white;
      width: 90%;
      max-width: 400px;
      margin: 80px auto;
      padding: 20px;
      border-radius: 6px;
      position: relative;
    }
    .modal h3 { margin-top: 0; }
    .modal label {
      display: block;
      margin: 12px 0 4px;
    }
    .modal input, .modal select {
      width: 100%;
      padding: 8px;
      margin-bottom: 10px;
    }
    .modal-close {
      position: absolute;
      top: 8px;
      right: 12px;
      font-size: 20px;
      cursor: pointer;
      color: #888;
    }
    .modal-buttons {
      text-align: right;
    }
    .modal-buttons button {
      margin-left: 10px;
    }
    .back-link {
      display: inline-block;
      margin-top: 20px;
      text-decoration: none;
      color: #2980b9;
      font-weight: bold;
    }
    .back-link:hover {
      text-decoration: underline;
    }

    @media (max-width: 768px) {
      table, thead, tbody, th, td, tr {
        display: block;
      }
      thead tr {
        display: none;
      }
      tbody tr {
        margin-bottom: 15px;
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 10px;
      }
      td {
        position: relative;
        padding-left: 50%;
      }
      td::before {
        position: absolute;
        left: 15px;
        top: 12px;
        white-space: nowrap;
        font-weight: bold;
      }
      td:nth-of-type(1)::before { content: "ID"; }
      td:nth-of-type(2)::before { content: "Username"; }
      td:nth-of-type(3)::before { content: "Email"; }
      td:nth-of-type(4)::before { content: "Role"; }
      td:nth-of-type(5)::before { content: "Locked"; }
      td:nth-of-type(6)::before { content: "Attempts"; }
      td:nth-of-type(7)::before { content: "Created"; }
      td:nth-of-type(8)::before { content: "Actions"; }
    }
  </style>
</head>
<body>

<div class="header">
  <h1>Administration Panel</h1>
  <button class="btn btn-add" onclick="openModal(0)">Add New User</button>
</div>

<?php if ($msg): ?>
  <div class="msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<table>
  <thead>
    <tr>
      <th></th><th>Username</th><th>Email</th><th>Role</th>
      <th>Locked</th><th>Attempts</th><th>Created At</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php $result->data_seek(0); while ($u = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $u['id'] ?></td>
        <td><?= htmlspecialchars($u['username']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><?= $u['role'] ?></td>
        <td><?= $u['is_locked'] ? 'Yes' : 'No' ?></td>
        <td><?= $u['login_attempts'] ?></td>
        <td><?= $u['created_at'] ?></td>
        <td>
          <button class="btn btn-edit" onclick="openModal(<?= $u['id'] ?>)">Edit</button>
          <a href="?delete=<?= $u['id'] ?>" class="btn btn-delete" onclick="return confirm('Delete this user?')">Delete</a>
          <?php if ($u['is_locked']): ?>
            <a href="?lock=0&id=<?= $u['id'] ?>" class="btn btn-unlock">Unlock</a>
          <?php else: ?>
            <a href="?lock=1&id=<?= $u['id'] ?>" class="btn btn-lock">Lock</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<a href="../php/master_panel.php" class="back-link">← Back to Master Panel</a>

<div class="modal-overlay" id="userModal">
  <div class="modal">
    <span class="modal-close" onclick="closeModal()">&times;</span>
    <h3 id="modalTitle">Add / Edit User</h3>
    <form method="post">
      <input type="hidden" name="user_id" id="user_id">
      <label>Username:<input type="text" name="username" id="username" required></label>
      <label>Email:<input type="email" name="email" id="email" required></label>
      <label>Role:
        <select name="role" id="role">
          <option value="admin">Admin</option>
          <option value="teacher">Teacher</option>
        </select>
      </label>
      <label><input type="checkbox" name="is_locked" id="is_locked"> Locked</label>
      <label>Password (leave blank to keep current):<input type="password" name="password" id="password"></label>
      <div class="modal-buttons">
        <button type="submit" class="btn btn-edit">Save</button>
        <button type="button" class="btn btn-delete" onclick="closeModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
  const users = {};
  <?php $result->data_seek(0); while ($u = $result->fetch_assoc()): ?>
    users[<?= $u['id'] ?>] = {
      username: "<?= addslashes($u['username']) ?>",
      email: "<?= addslashes($u['email']) ?>",
      role: "<?= $u['role'] ?>",
      is_locked: <?= $u['is_locked'] ?>
    };
  <?php endwhile; ?>

  function openModal(id) {
    document.getElementById('user_id').value = id;
    const modalTitle = document.getElementById('modalTitle');

    if (id === 0) {
      modalTitle.textContent = 'Add New User';
      document.getElementById('username').value = '';
      document.getElementById('email').value = '';
      document.getElementById('role').value = 'teacher';
      document.getElementById('is_locked').checked = false;
    } else {
      const u = users[id];
      modalTitle.textContent = 'Edit User';
      document.getElementById('username').value = u.username;
      document.getElementById('email').value = u.email;
      document.getElementById('role').value = u.role;
      document.getElementById('is_locked').checked = u.is_locked == 1;
    }

    document.getElementById('password').value = '';
    document.getElementById('userModal').style.display = 'block';
  }

  function closeModal() {
    document.getElementById('userModal').style.display = 'none';
  }
</script>

</body>
</html>
