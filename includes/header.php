<header class="dashboard-header">
    <div class="header-left">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class='bx bx-menu'></i>
        </button>
        <h2 class="dashboard-title">Admin Dashboard</h2>
    </div>
    <div class="header-right">
        <div class="welcome-message">
            <?php
            if (isset($_SESSION['username'])) {
                echo "Welcome, " . htmlspecialchars($_SESSION['username']) . "!";
            } else {
                echo "Welcome!";
            }
            ?>
        </div>
        <div class="real-time-clock" id="realTimeClock">Loading time...</div>
    </div>
</header>
