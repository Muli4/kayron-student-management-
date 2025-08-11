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
            $username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '';
            $today = date('m-d');

            // Kenyan public holidays with emojis or flag images
            $holidays = [
                "01-01" => ["New Year's Day", "🎉"],
                "03-31" => ["Eid ul-Fitr", "🕌"],
                "04-18" => ["Good Friday", "✝️"],
                "04-21" => ["Easter Monday", "🌿"],
                "05-01" => ["Labour Day", "<img src='https://flagcdn.com/w80/ke.png' alt='Kenyan Flag' style='height: 20px; vertical-align: middle;'>"],
                "06-01" => ["Madaraka Day", "<img src='https://flagcdn.com/w80/ke.png' alt='Kenyan Flag' style='height: 20px; vertical-align: middle;'>"],
                "06-02" => ["Madaraka Day (Observed)", "<img src='https://flagcdn.com/w80/ke.png' alt='Kenyan Flag' style='height: 20px; vertical-align: middle;'>"],
                "06-07" => ["Eid ul-Adha", "🕋"],
                "10-10" => ["Mazingira Day", "🌱"],
                "10-20" => ["Mashujaa Day", "🦸🏿"],
                "12-12" => ["Jamhuri Day", "🎈"],
                "12-25" => ["Christmas Day", "🎄"],
                "12-26" => ["Boxing Day", "🎁"]
            ];

            // Display message
            if (array_key_exists($today, $holidays)) {
                [$holidayName, $emojiOrImage] = $holidays[$today];
                echo "Happy $holidayName $emojiOrImage, $username!";
            } elseif (!empty($username)) {
                echo "Welcome, $username!";
            } else {
                echo "Welcome!";
            }
            ?>
        </div>
        <div class="real-time-clock" id="realTimeClock">Loading time...</div>
    </div>
</header>
