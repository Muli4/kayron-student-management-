<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

require '../php/db.php';

// ===== Add or Edit User =====
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

// ===== Delete User =====
if (isset($_GET['delete'])) {
    $conn->query("DELETE FROM administration WHERE id=" . intval($_GET['delete']));
    header("Location: administration.php?msg=" . urlencode("User deleted."));
    exit();
}

// ===== Lock/Unlock User =====
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administration Panel</title>
  <link rel="stylesheet" href="../style/style-sheet.css">
  <link rel="website icon" type="png" href="../images/school-logo.jpg">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <style>
/* Header and Add Button Alignment */
.content h1 {
    font-size: 2rem;
    margin: 0;
    display: inline-block;
    color: #1cc88a;
}

.header-container .btn-add {
    background: linear-gradient(135deg, #4e73df, #1cc88a);
    float: right; /* move button to the far right */
    margin-bottom: 15px;
    margin-top: 4px; /* align vertically with heading */
}
.btn {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    color: #fff;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    margin: 0 3px;
}
/* Clear floats after heading container */
.header-container {
    overflow: hidden; /* ensures parent contains floated elements */
    margin-bottom: 15px;
}

.btn-edit {
    background: #4e73df;
}

.btn-delete {
    background: #e74a3b;
}

.btn-lock {
    background: #ff6b6b;
}

.btn-unlock {
    background: #1cc88a;
}

.btn:hover {
    opacity: 0.85;
}

/* ===== TABLE STYLING ===== */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 0.95rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    border-radius: 8px;
    overflow: hidden;
}

table thead {
    background: linear-gradient(135deg, #4e73df, #1cc88a);
    color: #fff;
    font-weight: bold;
}

table th, table td {
    padding: 10px 12px;
    text-align: center;
    border-bottom: 1px solid #e0e0e0;
}

table tr:hover {
    background: rgba(30, 100, 150, 0.1);
}

/* ===== MESSAGE BOX ===== */
.msg {
    padding: 10px 15px;
    margin: 10px 0;
    border-radius: 6px;
    background: #1cc88a;
    color: #fff;
    font-weight: bold;
}

/* ===== MODAL STYLING ===== */
.modal-overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999;
    display: none; /* hidden by default */
    justify-content: center; /* horizontal center */
    align-items: center; /* vertical center */
    padding: 10px; /* small padding for mobile view */
    flex-direction: column; /* ensures content stacks properly if needed */
}
.modal {
    background: #fff;
    padding: 20px 25px;
    border-radius: 8px;
    width: 400px;
    max-width: 95%;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    position: relative; /* needed for close button */
}
.modal-close {
    position: absolute;
    top: 10px; right: 15px;
    font-size: 1.5rem;
    cursor: pointer;
}

.modal h3 {
    margin-bottom: 15px;
    color: #4e73df;
}

.modal label {
    display: block;
    margin-bottom: 10px;
    font-weight: bold;
}

.modal input[type="text"],
.modal input[type="email"],
.modal input[type="password"],
.modal select {
    width: 100%;
    padding: 7px 10px;
    margin-top: 4px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 0.95rem;
}

.modal-buttons {
    margin-top: 15px;
    text-align: right;
}

.modal-buttons .btn {
    margin-left: 5px;
}

/* ===== RESPONSIVE ===== */
@media(max-width: 768px){
    .modal { width: 90%; }
    table th, table td { font-size: 0.85rem; padding: 6px 8px; }
    .btn { font-size: 0.85rem; padding: 5px 10px; }
}
</style>

</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="dashboar-container">
    <?php include '../includes/admin-sidebar.php'; ?>
    <main class="content">
      <div class="header-container">
          <h1>Administration Panel</h1>
          <button class="btn btn-add" onclick="openModal(0)">Add New User</button>
      </div>

        <?php if ($msg): ?>
            <div class="msg"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Locked</th>
                    <th>Attempts</th>
                    <th>Created At</th>
                    <th>Actions</th>
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
    </main>
</div>

<!-- Modal -->
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
<?php include '../includes/footer.php'; ?>
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
    const modal = document.getElementById('userModal');
    const modalTitle = document.getElementById('modalTitle');

    document.getElementById('user_id').value = id;

    if (id === 0) {
        modalTitle.textContent = 'Add New User';
        document.getElementById('username').value = '';
        document.getElementById('email').value = '';
        document.getElementById('role').value = 'teacher';
        document.getElementById('is_locked').checked = false;
    } else {
        const u = users[id];
        if (!u) return; // safety check
        modalTitle.textContent = 'Edit User';
        document.getElementById('username').value = u.username;
        document.getElementById('email').value = u.email;
        document.getElementById('role').value = u.role;
        document.getElementById('is_locked').checked = u.is_locked == 1;
    }

    document.getElementById('password').value = '';

    // Show modal using flex to ensure proper centering
    modal.style.display = 'flex';
}

function closeModal() {
    const modal = document.getElementById('userModal');
    modal.style.display = 'none';
}

// Optional: close modal when clicking outside of it
document.getElementById('userModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        /* ===== Real-time clock ===== */
        function updateClock() {
            const clockElement = document.getElementById('realTimeClock');
            if (clockElement) {
                const now = new Date();
                const timeString = now.toLocaleTimeString();
                clockElement.textContent = timeString;
            }
        }
        updateClock(); 
        setInterval(updateClock, 1000);

        /* ===== Dropdowns: only one open ===== */
        document.querySelectorAll(".dropdown-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                const parent = btn.parentElement;
                document.querySelectorAll(".dropdown").forEach(drop => {
                    if (drop !== parent) {
                        drop.classList.remove("open");
                    }
                });
                parent.classList.toggle("open");
            });
        });

        /* ===== Keep dropdown open if current page matches a child link ===== */
        const currentUrl = window.location.pathname.split("/").pop();
        document.querySelectorAll(".dropdown").forEach(drop => {
            const links = drop.querySelectorAll("a");
            links.forEach(link => {
                const linkUrl = link.getAttribute("href");
                if (linkUrl && linkUrl.includes(currentUrl)) {
                    drop.classList.add("open");
                }
            });
        });

        /* ===== Sidebar toggle for mobile ===== */
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.querySelector('.toggle-btn');
        const overlay = document.createElement('div');
        overlay.classList.add('sidebar-overlay');
        document.body.appendChild(overlay);

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        /* ===== Close sidebar on outside click ===== */
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });

        /* ===== Auto logout after 5 mins inactivity ===== */
        let logoutTimer;
        function resetLogoutTimer() {
            clearTimeout(logoutTimer);
            logoutTimer = setTimeout(() => {
                window.location.href = '../php/logout.php';
            }, 300000); // 5 minutes
        }
        ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
            document.addEventListener(evt, resetLogoutTimer);
        });
        resetLogoutTimer();
    });
</script>
</body>
</html>
