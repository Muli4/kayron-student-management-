<nav class="sidebar" id="sidebar">
    <h2 class="logo">Kayron</h2>
    <ul class="sidebar-links">
        <li><a href="../php/admin_dashboard.php"><i class='bx bx-grid-alt'></i> Dashboard </a></li>

        <!-- Students Dropdown -->
        <li class="dropdown">
            <a href="javascript:void(0)" class="dropdown-btn">
                <span><i class='bx bx-user-circle'></i> Students</span>
                <i class='bx bx-chevron-down arrow'></i>
            </a>
            <ul class="dropdown-content">
                <li><a href="../php/add-student.php"><i class='bx bx-user-plus'></i> Add Student</a></li>
                <li><a href="../php/manage-students.php"><i class='bx bx-group'></i> Manage Students</a></li>
                <li><a href="../php/classes.php"><i class='bx bx-chalkboard'></i> View Classes </a></li>
            </ul>
        </li>

        <!-- Teachers Dropdown  -->
        <li class="dropdown">
            <a href="javascript:void(0)" class="dropdown-btn">
                <span><i class='bx bx-chalkboard'></i> Teachers <span class="new">New</span></span>
                <i class='bx bx-chevron-down arrow'></i>
            </a>
            <ul class="dropdown-content">
                <li><a href="../teachers/add-teacher.php"><i class='bx bx-user-plus'></i> Add Teacher</a></li>
                <li><a href="#"><i class='bx bx-group'></i> Manage Teachers</a></li>
                <li><a href="#"><i class='bx bx-chalkboard-teacher'></i> View Teachers</a></li>
                <li><a href="#"><i class='bx bx-dollar-circle'></i> Manage Salaries</a></li>
                <li><a href="#"><i class='bx bx-task'></i> Teacher Attendance</a></li>
            </ul>
        </li> 

        <!-- Payments Dropdown -->
        <li class="dropdown">
            <a href="javascript:void(0)" class="dropdown-btn">
                <span><i class='bx bx-wallet'></i> Payments</span>
                <i class='bx bx-chevron-down arrow'></i>
            </a>
            <ul class="dropdown-content">
                <li><a href="../php/view-balances.php"><i class='bx bx-dollar-circle'></i> View Balances </a></li>
                <li><a href="../php/school-fee-handler.php"><i class='bx bx-credit-card-front'></i> Pay Fees</a></li>
                <li><a href="../php/purchase.php"><i class='bx bx-shopping-bag'></i> Buy Book | Uniform</a></li>
            </ul>
        </li>

        <!-- Others Dropdown -->
        <li class="dropdown">
            <a href="javascript:void(0)" class="dropdown-btn">
                <span><i class='bx bx-dots-horizontal-rounded'></i> Others </span>
                <i class='bx bx-chevron-down arrow'></i>
            </a>
            <ul class="dropdown-content">
                <li><a href="../php/exam-tracker.php"><i class='bx bx-book-open'></i> Exam Tracker <span class="new">new</span></a></li>
                <li><a href="../php/graduation-tracker.php"><i class='bx bx-user-check'></i> Graduation Tracker </a></li>
                <li><a href="../php/prize-giving-tracker.php"><i class='bx bx-gift'></i> Prize Giving Tracker</a></li>
            </ul>
        </li>

        <!-- Attendance Dropdown -->
        <li class="dropdown">
            <a href="javascript:void(0)" class="dropdown-btn">
                <span><i class='bx bx-calendar'></i> Attendance</span>
                <i class='bx bx-chevron-down arrow'></i>
            </a>
            <ul class="dropdown-content">
                <li><a href="../php/week.php"><i class='bx bx-calendar-event'></i> Register Week</a></li>
                <li><a href="../php/attendance_register.php"><i class='bx bx-task'></i> Mark Attendance <span class="updated">updated</span></a></li> 
            </ul>
        </li>

        <!-- Lunch Dropdown -->
        <li class="dropdown">
            <a href="javascript:void(0)" class="dropdown-btn">
                <span><i class='bx bx-restaurant'></i> Lunch</span>
                <i class='bx bx-chevron-down arrow'></i>
            </a>
            <ul class="dropdown-content">
                <li><a href="../php/lunch_payment.php"><i class='bx bx-time-five'></i> Lunch Tracker</a></li>
            </ul>
        </li>

        <!-- Reports Dropdown -->
        <li class="dropdown">
            <a href="javascript:void(0)" class="dropdown-btn">
                <span><i class='bx bx-bar-chart-square'></i> Reports</span>
                <i class='bx bx-chevron-down arrow'></i>
            </a>
            <ul class="dropdown-content">
                <li><a href="../php/view-receipts.php"><i class='bx bx-receipt'></i> Receipts</a></li>
                <li><a href="../php/weekly-report.php"><i class='bx bx-line-chart'></i> Weekly Report</a></li>
            </ul>
        </li>

        <!-- Account Dropdown -->
        <li class="dropdown">
            <a href="javascript:void(0)" class="dropdown-btn">
                <span><i class='bx bx-cog'></i> Account</span>
                <i class='bx bx-chevron-down arrow'></i>
            </a>
            <ul class="dropdown-content">
                <li><a href="../php/change-username.php"><i class='bx bx-id-card'></i> Change Username</a></li>
                <li><a href="../php/change-password.php"><i class='bx bx-lock-alt'></i> Change Password</a></li>
            </ul>
        </li>

        <!-- Ticket Log 
        <li class="dropdown">
            <a href="javascript:void(0)" class="dropdown-btn">
                <span><i class='bx bx-support'></i> Ticket Log </span>
                <i class='bx bx-chevron-down arrow'></i>
            </a>
            <ul class="dropdown-content">
                <li><a href="#"><i class='bx bx-message-square-add'></i> Create Ticket</a></li>
            </ul>
        </li> -->

        <li><a href="../php/logout.php"><i class='bx bx-log-out-circle'></i> Logout</a></li>
    </ul>
</nav>