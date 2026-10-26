<?php
// api/queue_status.php
// Returns real-time day-wise queue status as JSON for live polling & synchronization

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = get_db_connection();

$doctor_id = (int)($_GET['doctor_id'] ?? 0);
$appointment_id = (int)($_GET['appointment_id'] ?? 0);
$patient_id = (int)($_GET['patient_id'] ?? 0);
$query_date = trim($_GET['date'] ?? '');

if ($patient_id <= 0 && is_logged_in() && ($_SESSION['user_role'] ?? '') === 'patient') {
    $patient_id = (int)$_SESSION['user_id'];
}

$apt_record = null;

// If appointment_id provided, fetch appointment details first
if ($appointment_id > 0) {
    $apt_stmt = $pdo->prepare("
        SELECT a.*, d.doctor_name, d.specialization, d.available_time
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.appointment_id = ?
    ");
    $apt_stmt->execute([$appointment_id]);
    $apt_record = $apt_stmt->fetch();
    if ($apt_record) {
        $doctor_id = (int)$apt_record['doctor_id'];
        if (empty($query_date)) {
            $query_date = $apt_record['appointment_date'];
        }
    }
}

// If no date specified yet, default to today
if (empty($query_date)) {
    $query_date = date('Y-m-d');
}

// If doctor_id is still missing, find doctor from patient's appointment on that date, or first doctor
if ($doctor_id <= 0) {
    if ($patient_id > 0) {
        $pat_apt_stmt = $pdo->prepare("
            SELECT a.*, d.doctor_name, d.specialization 
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.doctor_id
            WHERE a.patient_id = ? AND a.appointment_date = ? AND a.status IN ('Waiting', 'Serving')
            LIMIT 1
        ");
        $pat_apt_stmt->execute([$patient_id, $query_date]);
        $found = $pat_apt_stmt->fetch();
        if ($found) {
            $doctor_id = (int)$found['doctor_id'];
            $apt_record = $found;
        }
    }

    if ($doctor_id <= 0) {
        $first_doc = $pdo->query("SELECT doctor_id FROM doctors ORDER BY doctor_id ASC LIMIT 1")->fetchColumn();
        $doctor_id = (int)($first_doc ?: 1);
    }
}

// If apt_record is still null and patient_id > 0, check if this patient has appointment with this doctor on this date
if (!$apt_record && $patient_id > 0 && $doctor_id > 0) {
    $chk_stmt = $pdo->prepare("
        SELECT a.*, d.doctor_name, d.specialization
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.patient_id = ? AND a.doctor_id = ? AND a.appointment_date = ?
        ORDER BY a.appointment_id DESC
        LIMIT 1
    ");
    $chk_stmt->execute([$patient_id, $doctor_id, $query_date]);
    $apt_record = $chk_stmt->fetch() ?: null;
}

// Fetch doctor details if not already in apt_record
$doctor_name = $apt_record['doctor_name'] ?? '';
$specialization = $apt_record['specialization'] ?? '';
if (empty($doctor_name) && $doctor_id > 0) {
    $d_stmt = $pdo->prepare("SELECT doctor_name, specialization FROM doctors WHERE doctor_id = ?");
    $d_stmt->execute([$doctor_id]);
    $doc_info = $d_stmt->fetch();
    if ($doc_info) {
        $doctor_name = $doc_info['doctor_name'];
        $specialization = $doc_info['specialization'];
    }
}

// 1. Current Serving Token for this doctor and date
$serving_stmt = $pdo->prepare("
    SELECT token_number 
    FROM appointments 
    WHERE doctor_id = ? AND appointment_date = ? AND status = 'Serving'
    LIMIT 1
");
$serving_stmt->execute([$doctor_id, $query_date]);
$current_token = $serving_stmt->fetchColumn() ?: 'None';

// 2. Next Token in line
$next_stmt = $pdo->prepare("
    SELECT token_number 
    FROM appointments 
    WHERE doctor_id = ? AND appointment_date = ? AND status = 'Waiting'
    ORDER BY appointment_id ASC
    LIMIT 1
");
$next_stmt->execute([$doctor_id, $query_date]);
$next_token = $next_stmt->fetchColumn() ?: 'None';

// 3. Patients ahead and estimated wait
$patients_ahead = 0;
$your_token = $apt_record['token_number'] ?? 'None';
$status = $apt_record['status'] ?? 'None';

if ($apt_record) {
    if ($status === 'Serving') {
        $patients_ahead = 0;
    } elseif ($status === 'Waiting') {
        $ahead_stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM appointments 
            WHERE doctor_id = ? 
              AND appointment_date = ? 
              AND status = 'Waiting' 
              AND appointment_id < ?
        ");
        $ahead_stmt->execute([$doctor_id, $query_date, $apt_record['appointment_id']]);
        $patients_ahead = (int)$ahead_stmt->fetchColumn();
    }
} else {
    // Total waiting for doctor on this date
    $tot_wait_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND status = 'Waiting'
    ");
    $tot_wait_stmt->execute([$doctor_id, $query_date]);
    $patients_ahead = (int)$tot_wait_stmt->fetchColumn();
}

$estimated_wait = calculate_estimated_wait($patients_ahead, $status);

// 4. Day-Wise Sequence of all tokens for this doctor and date
$seq_stmt = $pdo->prepare("
    SELECT appointment_id, token_number, appointment_time, status, patient_id
    FROM appointments
    WHERE doctor_id = ? AND appointment_date = ? AND status != 'Cancelled'
    ORDER BY appointment_id ASC
");
$seq_stmt->execute([$doctor_id, $query_date]);
$all_tokens = $seq_stmt->fetchAll();

$tokens_list = [];
$pos = 1;
$waiting_count = 0;
foreach ($all_tokens as $t) {
    if ($t['status'] === 'Waiting') $waiting_count++;
    $is_you = ($patient_id > 0 && (int)$t['patient_id'] === $patient_id);
    $tokens_list[] = [
        'position'     => $pos++,
        'token_number' => $t['token_number'],
        'time'         => $t['appointment_time'],
        'status'       => $t['status'],
        'status_html'  => render_status_badge($t['status']),
        'is_you'       => $is_you,
    ];
}

$is_today = ($query_date === date('Y-m-d'));
$is_future = ($query_date > date('Y-m-d'));
$is_past = ($query_date < date('Y-m-d'));

echo json_encode([
    'success'               => true,
    'has_appointment'       => ($apt_record ? true : false),
    'doctor_id'             => $doctor_id,
    'doctor_name'           => $doctor_name,
    'specialization'        => $specialization,
    'appointment_id'        => $apt_record ? (int)$apt_record['appointment_id'] : 0,
    'date'                  => $query_date,
    'date_formatted'        => format_date($query_date),
    'is_today'              => $is_today,
    'is_future'             => $is_future,
    'is_past'               => $is_past,
    'current_token'         => $current_token,
    'next_token'            => $next_token,
    'your_token'            => $your_token,
    'patients_ahead'        => $patients_ahead,
    'waiting_count'         => $waiting_count,
    'total_booked'          => count($all_tokens),
    'status'                => $status,
    'status_html'           => render_status_badge($status),
    'estimated_wait'        => $estimated_wait,
    'tokens_list'           => $tokens_list,
    'timestamp'             => time(),
]);
exit;
