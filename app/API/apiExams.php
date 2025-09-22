<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include required classes
require_once __DIR__ . '/../Db.php';
require_once __DIR__ . '/../Permissions.php';
require_once __DIR__ . '/../Exams.php';

// Check permissions
if (!Permission::canManageQuizzes() && !Permission::canViewOwnQuizzes()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get action from request
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $exams = new Exams();
    $user = $_SESSION['user'];

    switch ($action) {
        case 'datatable':
            handleDataTableRequest($exams, $user);
            break;
            
        case 'create_exam':
            handleCreateExam($exams);
            break;
            
        case 'update_exam':
            handleUpdateExam($exams);
            break;
            
        case 'get_exam':
            handleGetExam($exams);
            break;
            
        case 'delete_exam':
            handleDeleteExam($exams);
            break;
            
        case 'export':
            handleExport($exams, $user);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("API Exams Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

/**
 * Handle DataTable Request
 */
function handleDataTableRequest($exams, $user) {
    $draw = intval($_POST['draw'] ?? 1);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $searchValue = $_POST['search']['value'] ?? '';
    $orderColumn = intval($_POST['order'][0]['column'] ?? 0);
    $orderDir = $_POST['order'][0]['dir'] ?? 'asc';

    // Column mapping
    $columns = ['title', 'lesson_name', 'grading_period_name', 'max_score', 'time_limit_minutes', 'attempts_allowed', 'status'];
    $orderBy = $columns[$orderColumn] ?? 'title';

    // Get exams based on user role
    if (Permission::canManageQuizzes()) {
        // Teachers/Admins see their own exams or all exams
        $result = $exams->getExamsForDataTable($start, $length, $searchValue, $orderBy, $orderDir, $user['id']);
    } else {
        // Students see available exams with their scores
        $result = $exams->getExamsForStudent($start, $length, $searchValue, $orderBy, $orderDir, $user['id']);
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $result['total'],
        'recordsFiltered' => $result['filtered'],
        'data' => $result['data']
    ]);
}

/**
 * Handle Create Exam
 */
function handleCreateExam($exams) {
    if (!Permission::canAddQuizzes()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }

    $data = [
        'lesson_id' => $_POST['lesson_id'] ?? null,
        'grading_period_id' => $_POST['grading_period_id'] ?? null,
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'max_score' => intval($_POST['max_score'] ?? 100),
        'time_limit_minutes' => !empty($_POST['time_limit_minutes']) ? intval($_POST['time_limit_minutes']) : null,
        'attempts_allowed' => intval($_POST['attempts_allowed'] ?? 1),
        'display_mode' => $_POST['display_mode'] ?? 'all',
        'open_at' => $_POST['open_at'] ?? null,
        'close_at' => $_POST['close_at'] ?? null,
        'created_by' => $_SESSION['user']['id']
    ];

    // Validation
    if (empty($data['lesson_id']) || empty($data['grading_period_id']) || empty($data['title'])) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        return;
    }

    $result = $exams->create($data);
    echo json_encode($result);
}

/**
 * Handle Update Exam
 */
function handleUpdateExam($exams) {
    if (!Permission::canEditQuizzes()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }

    $id = $_POST['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
        return;
    }

    $data = [
        'lesson_id' => $_POST['lesson_id'] ?? null,
        'grading_period_id' => $_POST['grading_period_id'] ?? null,
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'max_score' => intval($_POST['max_score'] ?? 100),
        'time_limit_minutes' => !empty($_POST['time_limit_minutes']) ? intval($_POST['time_limit_minutes']) : null,
        'attempts_allowed' => intval($_POST['attempts_allowed'] ?? 1),
        'display_mode' => $_POST['display_mode'] ?? 'all',
        'open_at' => $_POST['open_at'] ?? null,
        'close_at' => $_POST['close_at'] ?? null
    ];

    $result = $exams->update($id, $data);
    echo json_encode($result);
}

/**
 * Handle Get Exam
 */
function handleGetExam($exams) {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
        return;
    }

    $result = $exams->getById($id);
    echo json_encode($result);
}

/**
 * Handle Delete Exam
 */
function handleDeleteExam($exams) {
    if (!Permission::canDeleteQuizzes()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }

    $id = $_POST['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
        return;
    }

    $result = $exams->delete($id);
    echo json_encode($result);
}

/**
 * Handle Export
 */
function handleExport($exams, $user) {
    if (!Permission::canManageQuizzes()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }

    // Get all exams for export
    $result = $exams->getExamsForDataTable(0, 999999, '', 'title', 'asc', $user['id']);
    
    if (!$result['data']) {
        echo json_encode(['success' => false, 'message' => 'No data to export']);
        return;
    }

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="exams_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // CSV headers
    fputcsv($output, [
        'ID',
        'Title',
        'Lesson',
        'Grading Period',
        'Max Score',
        'Time Limit (mins)',
        'Attempts Allowed',
        'Display Mode',
        'Open At',
        'Close At',
        'Created At'
    ]);

    // CSV data
    foreach ($result['data'] as $exam) {
        fputcsv($output, [
            $exam['id'],
            $exam['title'],
            $exam['lesson_name'],
            $exam['grading_period_name'],
            $exam['max_score'],
            $exam['time_limit_minutes'] ?: 'No limit',
            $exam['attempts_allowed'],
            $exam['display_mode'],
            $exam['open_at'],
            $exam['close_at'],
            $exam['created_at']
        ]);
    }

    fclose($output);
}
?>
