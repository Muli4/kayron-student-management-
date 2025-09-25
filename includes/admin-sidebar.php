<nav class="sidebar" id="sidebar">
    <h2 class="logo">Master Panel</h2>
    <ul class="sidebar-links">

        <li><a href="./master_panel.php"><i class='bx bx-grid-alt'></i> Dashboard </a></li>
        <li><a href="./administration.php"><i class='bx bx-cog'></i> Administration</a></li>

        <!-- Students Dropdown -->
        <li class="dropdown">
            <a href="javascript:void(0)" class="dropdown-btn">
                <span><i class='bx bx-user-circle'></i> Students</span>
                <i class='bx bx-chevron-down arrow'></i>
            </a>
            <ul class="dropdown-content">
                <li><a href="../tables/student_records.php"><i class='bx bx-group'></i> Student Records</a></li>
                <li><a href="../tables/graduated_students.php"><i class='bx bx-user-check'></i> Graduated Students</a></li>
                <li><a href="../tables/edit_attendance.php"><i class='bx bx-task'></i> Attendance</a></li>
            </ul>
        </li>

        <!-- Fees Dropdown -->
        <li class="dropdown">
            <a href="javascript:void(0)" class="dropdown-btn">
                <span><i class='bx bx-wallet'></i> Fees</span>
                <i class='bx bx-chevron-down arrow'></i>
            </a>
            <ul class="dropdown-content">
                <li><a href="../admin/prize_giving.php"><i class='bx bx-restaurant'></i> Prize Giving</a></li>
                <li><a href="../admin/lunch_fees.php"><i class='bx bx-dollar-circle'></i> Lunch Fees</a></li>
                <li><a href="../tables/school_fee_transactions.php"><i class='bx bx-receipt'></i> School Fee Transactions</a></li>
                <li><a href="../tables/lunch_fee_transactions.php"><i class='bx bx-food-menu'></i> Lunch Fee Transactions</a></li>
                <li><a href="../tables/others.php"><i class='bx bx-dots-horizontal-rounded'></i> Other Fees</a></li>
                <li><a href="../tables/other_transactions.php"><i class='bx bx-transfer'></i> Other Transactions</a></li>
            </ul>
        </li>

        <!-- Purchases Dropdown -->
        <li class="dropdown">
            <a href="javascript:void(0)" class="dropdown-btn">
                <span><i class='bx bx-cart'></i> Purchases</span>
                <i class='bx bx-chevron-down arrow'></i>
            </a>
            <ul class="dropdown-content">
                <li><a href="../tables/book_prices.php"><i class='bx bx-book'></i> Book Prices</a></li>
                <li><a href="../tables/book_purchases.php"><i class='bx bx-book-add'></i> Book Purchases</a></li>
                <li><a href="../tables/uniform_prices.php"><i class='bx bx-shirt'></i> Uniform Prices</a></li>
                <li><a href="../tables/uniform_purchases.php"><i class='bx bx-cart-alt'></i> Uniform Purchases</a></li>
                <li><a href="../tables/purchase_transactions.php"><i class='bx bx-money'></i> Purchase Transactions</a></li>
            </ul>
        </li>

        <!-- Teachers -->
        <li><a href="../tables/teacher_records.php"><i class='bx bx-chalkboard'></i> Teacher Records</a></li>

        <!-- Timeline Dropdown -->
        <li class="dropdown">
            <a href="javascript:void(0)" class="dropdown-btn">
                <span><i class='bx bx-calendar'></i> Timelines</span>
                <i class='bx bx-chevron-down arrow'></i>
            </a>
            <ul class="dropdown-content">
                <li><a href="../tables/terms.php"><i class='bx bx-calendar-event'></i> Terms</a></li>
                <li><a href="../tables/weeks.php"><i class='bx bx-time'></i> Weeks</a></li>
                <li><a href="../tables/days.php"><i class='bx bx-timer'></i> Days</a></li>
            </ul>
        </li>

        <!-- Logout -->
        <li><a href="../php/logout.php"><i class='bx bx-log-out-circle'></i> Logout</a></li>
    </ul>
</nav>
