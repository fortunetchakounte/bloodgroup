<?php
// admin/sidebar.php - Reusable sidebar component
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="logo">
            <div class="logo-icon">
                <i class="fas fa-tint"></i>
            </div>
            <div class="logo-text">Blood<span>Link</span></div>
        </a>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <i class="fas fa-user-shield"></i>
        </div>
        <div class="user-info">
            <h4><?php echo htmlspecialchars($_SESSION['user_name']); ?></h4>
            <p><i class="fas fa-circle"></i> Administrator</p>
        </div>
    </div>

    <div class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <ul>
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="donors.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'donors.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Donors</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="hospitals.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'hospitals.php' ? 'active' : ''; ?>">
                        <i class="fas fa-hospital"></i>
                        <span>Hospitals</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="requests.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'requests.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tint"></i>
                        <span>Blood Requests</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Management</div>
            <ul>
                <li class="nav-item">
                    <a href="validations.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'validations.php' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle"></i>
                        <span>Validations</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="backup.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'active' : ''; ?>">
                        <i class="fas fa-database"></i>
                        <span>Backups</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">System</div>
            <ul>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-circle"></i>
                        <span>Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="system.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'system.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span>System</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-sliders-h"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../public/logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</aside>