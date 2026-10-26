<?php
// patient/queue.php
// Day-Wise Digital Token Queue Tracking for Patients

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('patient');

$pdo = get_db_connection();
$patient_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Date and Doctor Filter
$filter_date = trim($_GET['date'] ?? '');
$filter_doctor_id = (int)($_GET['doctor_id'] ?? 0);
$appointment_id = (int)($_GET['appointment_id'] ?? 0);

// Fetch all currently available doctors for the doctor dropdown
$all_doctors = $pdo->query("SELECT doctor_id, doctor_name, specialization, available_time, availability_status FROM doctors WHERE availability_status = 'Available' ORDER BY doctor_id ASC")->fetchAll();
if (empty($all_doctors)) {
    // Fallback if none available
    $all_doctors = $pdo->query("SELECT doctor_id, doctor_name, specialization, available_time, availability_status FROM doctors ORDER BY doctor_id ASC")->fetchAll();
}

// If appointment_id provided, fetch appointment details first
$apt = null;
if ($appointment_id > 0) {
    $stmt = $pdo->prepare("
        SELECT a.*, d.doctor_name, d.specialization, d.available_time
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.appointment_id = ? AND a.patient_id = ?
    ");
    $stmt->execute([$appointment_id, $patient_id]);
    $apt = $stmt->fetch();
    if ($apt) {
        if (empty($filter_date)) $filter_date = $apt['appointment_date'];
        if ($filter_doctor_id <= 0) $filter_doctor_id = (int)$apt['doctor_id'];
    }
}

// Default filter_date to today if not provided
if (empty($filter_date)) {
    // If patient has an active appointment for today or future, default to that appointment's date
    $my_active_stmt = $pdo->prepare("
        SELECT appointment_date, doctor_id, appointment_id 
        FROM appointments 
        WHERE patient_id = ? AND appointment_date >= ? AND status IN ('Waiting', 'Serving')
        ORDER BY appointment_date ASC, appointment_time ASC 
        LIMIT 1
    ");
    $my_active_stmt->execute([$patient_id, $today]);
    $my_active = $my_active_stmt->fetch();
    if ($my_active) {
        $filter_date = $my_active['appointment_date'];
        if ($filter_doctor_id <= 0) $filter_doctor_id = (int)$my_active['doctor_id'];
    } else {
        $filter_date = $today;
    }
}

// Default doctor to first doctor if still not selected
if ($filter_doctor_id <= 0) {
    $filter_doctor_id = !empty($all_doctors) ? (int)$all_doctors[0]['doctor_id'] : 1;
}

// Fetch current doctor info
$doc_stmt = $pdo->prepare("SELECT * FROM doctors WHERE doctor_id = ?");
$doc_stmt->execute([$filter_doctor_id]);
$current_doctor = $doc_stmt->fetch();

// Check if patient has a token with this doctor on this filter_date
if (!$apt) {
    $apt_chk = $pdo->prepare("
        SELECT a.*, d.doctor_name, d.specialization, d.available_time
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.patient_id = ? AND a.doctor_id = ? AND a.appointment_date = ? AND a.status != 'Cancelled'
        ORDER BY a.appointment_id DESC 
        LIMIT 1
    ");
    $apt_chk->execute([$patient_id, $filter_doctor_id, $filter_date]);
    $apt = $apt_chk->fetch() ?: null;
}

// 1. Find currently serving token for this doctor and date
$serving_stmt = $pdo->prepare("
    SELECT token_number 
    FROM appointments 
    WHERE doctor_id = ? AND appointment_date = ? AND status = 'Serving'
    LIMIT 1
");
$serving_stmt->execute([$filter_doctor_id, $filter_date]);
$currently_serving = $serving_stmt->fetchColumn() ?: 'None';

// 2. Find next token in line for this date
$next_stmt = $pdo->prepare("
    SELECT token_number 
    FROM appointments 
    WHERE doctor_id = ? AND appointment_date = ? AND status = 'Waiting'
    ORDER BY appointment_id ASC
    LIMIT 1
");
$next_stmt->execute([$filter_doctor_id, $filter_date]);
$next_token = $next_stmt->fetchColumn() ?: 'None';

// 3. Calculate patients ahead
$patients_ahead = 0;
$estimated_wait = 'No active queue';

if ($apt) {
    if ($apt['status'] === 'Serving') {
        $patients_ahead = 0;
        $estimated_wait = 'Now being served';
    } elseif ($apt['status'] === 'Waiting') {
        $ahead_stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM appointments 
            WHERE doctor_id = ? 
              AND appointment_date = ? 
              AND status = 'Waiting' 
              AND appointment_id < ?
        ");
        $ahead_stmt->execute([$filter_doctor_id, $filter_date, $apt['appointment_id']]);
        $patients_ahead = (int)$ahead_stmt->fetchColumn();
        $estimated_wait = calculate_estimated_wait($patients_ahead, $apt['status']);
    } else {
        $estimated_wait = calculate_estimated_wait(0, $apt['status']);
    }
} else {
    $tot_wait_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND status = 'Waiting'
    ");
    $tot_wait_stmt->execute([$filter_doctor_id, $filter_date]);
    $tot_waiting = (int)$tot_wait_stmt->fetchColumn();
    $estimated_wait = $tot_waiting > 0 ? calculate_estimated_wait($tot_waiting, 'Waiting') : 'No waiting queue';
}

// 4. Fetch the full token lineup for this doctor and date
$lineup_stmt = $pdo->prepare("
    SELECT a.*, p.name as patient_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status != 'Cancelled'
    ORDER BY a.appointment_id ASC
");
$lineup_stmt->execute([$filter_doctor_id, $filter_date]);
$day_lineup = $lineup_stmt->fetchAll();

$is_today = ($filter_date === $today);
$is_future = ($filter_date > $today);
$is_past = ($filter_date < $today);

$page_title = "Live Token Queue - MediToken";
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/patient_nav.php'; ?>

    <main class="dashboard-main">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.75rem; margin-bottom: 0.25rem;">Live Digital Token Queue</h1>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Monitor daily doctor queues in real-time. Each day starts fresh with Token #1.</p>
            </div>
            <div>
                <a href="<?= $base_url ?>/patient/dashboard.php" class="btn btn-secondary">
                    &larr; Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Day / Date & Doctor Filter Bar -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
            <form method="GET" action="" id="queueFilterForm" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <span style="font-weight: 700; font-size: 0.9rem; color: var(--dark); margin-right: 0.25rem;">Select Day:</span>
                    
                    <a href="<?= $base_url ?>/patient/queue.php?date=<?= $today ?>&doctor_id=<?= $filter_doctor_id ?>" 
                       class="btn btn-sm <?= $filter_date === $today ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: var(--radius-full); padding: 0.35rem 0.85rem;">
                        Today (<?= date('d M') ?>) <?= $filter_date === $today ? '● Live' : '' ?>
                    </a>

                    <a href="<?= $base_url ?>/patient/queue.php?date=<?= $tomorrow ?>&doctor_id=<?= $filter_doctor_id ?>" 
                       class="btn btn-sm <?= $filter_date === $tomorrow ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: var(--radius-full); padding: 0.35rem 0.85rem;">
                        Tomorrow (<?= date('d M', strtotime('+1 day')) ?>)
                    </a>

                    <div style="display: flex; align-items: center; gap: 0.35rem; margin-left: 0.25rem;">
                        <input type="date" name="date" class="form-control" style="padding: 0.35rem 0.65rem; font-size: 0.85rem; width: auto;" value="<?= htmlspecialchars($filter_date) ?>" onchange="document.getElementById('queueFilterForm').submit();">
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <label for="doctor_id" style="font-weight: 700; font-size: 0.9rem; color: var(--dark); white-space: nowrap;">Doctor:</label>
                    <select name="doctor_id" id="doctor_id" class="form-select" style="padding: 0.35rem 0.75rem; font-size: 0.85rem; min-width: 200px;" onchange="document.getElementById('queueFilterForm').submit();">
                        <?php foreach ($all_doctors as $doc): ?>
                            <option value="<?= (int)$doc['doctor_id'] ?>" <?= $filter_doctor_id === (int)$doc['doctor_id'] ? 'selected' : '' ?>>
                                Dr. <?= htmlspecialchars($doc['doctor_name']) ?> (<?= htmlspecialchars($doc['specialization']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <!-- Day Banner Context -->
        <?php if ($is_today): ?>
            <div class="alert alert-success" style="font-weight: 600; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; border-left: 5px solid #059669; margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span class="status-pulse"></span>
                    <span><strong>TODAY'S LIVE QUEUE:</strong> <?= date('l, d M Y') ?> &bull; Active real-time updates directly from doctor's desk.</span>
                </div>
                <span class="status-badge badge-serving"><span class="status-pulse"></span> Auto-Syncing (3s)</span>
            </div>
        <?php elseif ($is_future): ?>
            <div class="alert alert-info" style="font-weight: 600; display: flex; align-items: center; gap: 0.5rem; border-left: 5px solid #0284c7; margin-bottom: 1.5rem;">
                <span>📅</span>
                <span><strong>SCHEDULED QUEUE FOR <?= format_date($filter_date) ?>:</strong> The queue for this day will start with Token #1 in the morning. Tokens are allocated on a first-come, first-served basis.</span>
            </div>
        <?php else: ?>
            <div class="alert alert-warning" style="font-weight: 600; display: flex; align-items: center; gap: 0.5rem; border-left: 5px solid #f59e0b; margin-bottom: 1.5rem;">
                <span>🕒</span>
                <span><strong>PAST DATE LOG FOR <?= format_date($filter_date) ?>:</strong> This date has passed. Please book a new token for today (<?= date('d M') ?>) or upcoming dates.</span>
            </div>
        <?php endif; ?>

        <!-- Serving Alert if this patient is currently serving -->
        <div id="queueActiveNotice">
            <?php if ($apt && $apt['status'] === 'Serving'): ?>
                <div class="alert alert-success" style="font-weight: 700; border-left: 6px solid #059669; animation: pulse 1.5s infinite; font-size: 1.05rem;">
                    🔔 YOUR TOKEN (<?= htmlspecialchars($apt['token_number']) ?>) IS NOW BEING SERVED! Please proceed to Dr. <?= htmlspecialchars($current_doctor['doctor_name']) ?>'s consultation room.
                </div>
            <?php elseif ($apt && $apt['status'] === 'Completed'): ?>
                <div class="alert alert-info">
                    This appointment was marked as completed. Thank you for consulting with us.
                </div>
            <?php endif; ?>
        </div>

        <!-- Hook element for AJAX real-time polling -->
        <div id="liveQueueTracker" 
             data-doctor-id="<?= (int)$filter_doctor_id ?>" 
             data-date="<?= htmlspecialchars($filter_date) ?>"
             data-appointment-id="<?= $apt ? (int)$apt['appointment_id'] : 0 ?>" 
             data-patient-id="<?= (int)$patient_id ?>"
             data-base-url="<?= $base_url ?>">
        </div>

        <!-- Doctor Consultation Header -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">CONSULTING DOCTOR</div>
                    <h2 style="font-size: 1.25rem; margin-top: 0.2rem;">
                        Dr. <?= htmlspecialchars($current_doctor['doctor_name'] ?? 'Doctor') ?>
                    </h2>
                    <div style="color: var(--primary); font-size: 0.875rem; font-weight: 600;"><?= htmlspecialchars($current_doctor['specialization'] ?? '') ?></div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">QUEUE DATE</div>
                    <div style="font-size: 1.15rem; font-weight: 800; color: var(--dark);"><?= format_date($filter_date) ?></div>
                    <div style="color: var(--dark-muted); font-size: 0.875rem;"><?= htmlspecialchars($current_doctor['available_time'] ?? '') ?></div>
                </div>
            </div>
        </div>

        <!-- Main High-Impact Token Board -->
        <div class="queue-board-card" style="margin-bottom: 2rem;">
            <div class="queue-board-header">
                <div>
                    <h3 style="font-size: 1.35rem; margin-bottom: 0.25rem;">Doctor's Digital Board &bull; <?= format_date($filter_date) ?></h3>
                    <p style="color: var(--text-muted); font-size: 0.85rem;">Token sequence runs fresh on each calendar date</p>
                </div>
                <div>
                    <span id="myStatusBadgeDisplay">
                        <?= $apt ? render_status_badge($apt['status']) : '<span class="status-badge badge-secondary">Not in this queue</span>' ?>
                    </span>
                </div>
            </div>

            <div class="token-highlight-grid">
                <!-- Currently Serving Box -->
                <div class="token-box active-serving">
                    <div class="token-box-label">CURRENTLY SERVING</div>
                    <div class="token-box-number" id="servingTokenDisplay"><?= htmlspecialchars($currently_serving) ?></div>
                    <div class="token-box-sub"><?= $is_today ? 'Inside Consultation' : 'Queue opens on ' . format_date($filter_date) ?></div>
                </div>

                <!-- Your Token Box -->
                <div class="token-box" style="border: 2px solid var(--primary-border); background: #ffffff;">
                    <div class="token-box-label" style="color: var(--primary);">YOUR TOKEN</div>
                    <div class="token-box-number" style="color: var(--primary);">
                        <?= $apt ? htmlspecialchars($apt['token_number']) : '-' ?>
                    </div>
                    <div class="token-box-sub">
                        <?= $apt ? 'Booked for ' . format_date($filter_date) : 'No token on this day' ?>
                    </div>
                </div>

                <!-- Next Token Box -->
                <div class="token-box">
                    <div class="token-box-label">NEXT IN LINE</div>
                    <div class="token-box-number" id="nextTokenDisplay"><?= htmlspecialchars($next_token) ?></div>
                    <div class="token-box-sub">Approaching Next</div>
                </div>

                <!-- Patients Ahead Box -->
                <div class="token-box">
                    <div class="token-box-label">PATIENTS AHEAD</div>
                    <div class="token-box-number" id="patientsAheadDisplay"><?= $apt ? $patients_ahead : '-' ?></div>
                    <div class="token-box-sub"><?= $apt ? 'In front of your token' : 'Based on your position' ?></div>
                </div>

                <!-- Estimated Wait Box -->
                <div class="token-box" style="grid-column: 1 / -1;">
                    <div class="token-box-label">ESTIMATED WAITING TIME</div>
                    <div class="token-box-number" id="estimatedWaitDisplay" style="font-size: 2.15rem; color: #0284c7;">
                        <?= htmlspecialchars($estimated_wait) ?>
                    </div>
                    <div class="token-box-sub">Approx. 15 minutes per consultation session</div>
                </div>
            </div>
        </div>

        <!-- Full Day's Token Order Lineup Table -->
        <div class="card" style="margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <h2 style="font-size: 1.2rem; margin: 0;">
                        Day Lineup for <?= format_date($filter_date) ?>
                    </h2>
                    <p style="font-size: 0.825rem; color: var(--text-muted); margin: 0.2rem 0 0 0;">
                        Tokens for this date ordered from first booking to last.
                    </p>
                </div>
                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    Total Booked: <strong style="color: var(--dark);"><?= count($day_lineup) ?></strong> patient(s)
                </div>
            </div>

            <?php if (empty($day_lineup)): ?>
                <div class="empty-state" style="padding: 2.5rem 1rem;">
                    <div class="empty-state-title">No tokens booked yet for <?= format_date($filter_date) ?>.</div>
                    <p class="empty-state-desc">When this day starts, whoever books the first appointment will get Token #1 (e.g. <?= get_doctor_prefix($pdo, $filter_doctor_id) ?>-001).</p>
                    <a href="<?= $base_url ?>/patient/book_appointment.php" class="btn btn-primary" style="margin-top: 1.25rem;">
                        + Book Token for this Date
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="custom-table" id="dayQueueTable">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Order</th>
                                <th>Token Number</th>
                                <th>Time Slot</th>
                                <th>Queue Participant</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($day_lineup as $index => $item): ?>
                                <?php $is_mine = ((int)$item['patient_id'] === (int)$patient_id); ?>
                                <tr style="<?= $is_mine ? 'background: rgba(5, 150, 105, 0.06); font-weight: 600;' : '' ?>">
                                    <td>
                                        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 700;">#<?= $index + 1 ?></span>
                                    </td>
                                    <td>
                                        <span style="font-family: 'Space Grotesk', sans-serif; font-size: 1.25rem; font-weight: 800; color: <?= $is_mine ? 'var(--primary)' : 'var(--dark)' ?>;">
                                            <?= htmlspecialchars($item['token_number']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($item['appointment_time']) ?></td>
                                    <td>
                                        <?php if ($is_mine): ?>
                                            <span style="background: var(--primary); color: #ffffff; padding: 0.2rem 0.65rem; border-radius: var(--radius-full); font-size: 0.775rem; font-weight: 700;">
                                                ★ Your Token
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.9rem;">Patient in line</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= render_status_badge($item['status']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
            <a href="<?= $base_url ?>/patient/appointments.php" class="btn btn-secondary">&larr; My Appointments</a>
            <a href="<?= $base_url ?>/patient/book_appointment.php" class="btn btn-primary">+ Book Another Appointment</a>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
