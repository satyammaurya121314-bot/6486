<?php
// includes/doctor_nav.php
$base_url = get_base_url();
$cur = basename($_SERVER['PHP_SELF']);
$user = current_user();
if (!isset($pdo)) {
    $pdo = get_db_connection();
}
$nav_doc_num = get_doctor_seq_number($pdo, $user['id'] ?? 0);
?>
<aside class="dashboard-sidebar">
    <div class="sidebar-profile">
        <div class="profile-name">Dr. <?= htmlspecialchars($user['name'] ?? 'Doctor') ?></div>
        <div class="profile-role">Doctor Desk &bull; <strong style="color: var(--primary);">Desk #<?= $nav_doc_num ?></strong></div>
    </div>

    <ul class="sidebar-menu">
        <li class="sidebar-item <?= $cur === 'dashboard.php' ? 'active' : '' ?>">
            <a href="<?= $base_url ?>/doctor/dashboard.php">Dashboard</a>
        </li>
        <li class="sidebar-item <?= $cur === 'queue.php' ? 'active' : '' ?>">
            <a href="<?= $base_url ?>/doctor/queue.php">Live Patient Queue</a>
        </li>
        <li class="sidebar-item <?= $cur === 'appointments.php' ? 'active' : '' ?>">
            <a href="<?= $base_url ?>/doctor/appointments.php">All Appointments</a>
        </li>
        <li class="sidebar-item <?= $cur === 'patients.php' ? 'active' : '' ?>">
            <a href="<?= $base_url ?>/doctor/patients.php">My Patients</a>
        </li>
        <li class="sidebar-item <?= $cur === 'profile.php' ? 'active' : '' ?>">
            <a href="<?= $base_url ?>/doctor/profile.php">Availability & Profile</a>
        </li>
        <li class="sidebar-item">
            <a href="<?= $base_url ?>/logout.php" style="color: var(--danger);">Logout</a>
        </li>
    </ul>
</aside>
