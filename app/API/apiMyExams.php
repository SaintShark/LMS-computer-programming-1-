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

// Check permissions - only students can access this API
if (!Permission::canViewOwnQuizzes() || Permission::canManageQuizzes()) {
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
            
        case 'get_exam':
            handleGetExam($exams, $user);
            break;
            
        case 'get_result':
            handleGetResult($exams, $user);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("API My Exams Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

/**
 * Handle DataTable Request for Students
 */
function handleDataTableRequest($exams, $user) {
    $draw = intval($_POST['draw'] ?? 1);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $searchValue = $_POST['search']['value'] ?? '';
    $orderColumn = intval($_POST['order'][0]['column'] ?? 0);
    $orderDir = $_POST['order'][0]['dir'] ?? 'asc';
    $period = $_POST['period'] ?? '';

    // Column mapping for students
    $columns = ['title', 'description', 'time_limit_minutes', 'attempts_allowed', 'status', 'student_score'];
    $orderBy = $columns[$orderColumn] ?? 'title';

    // Get exams for student with their scores
    $result = $exams->getExamsForStudent($start, $length, $searchValue, $orderBy, $orderDir, $user['id'], $period);

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $result['total'],
        'recordsFiltered' => $result['filtered'],
        'data' => $result['data']
    ]);
}

/**
 * Handle Get Exam Details for Student
 */
function handleGetExam($exams, $user) {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
        return;
    }

    // Get exam details with student's score
    $result = $exams->getExamForStudent($id, $user['id']);
    echo json_encode($result);
}

/**
 * Handle Get Student's Exam Result
 */
function handleGetResult($exams, $user) {
    $examId = $_GET['exam_id'] ?? null;
    if (!$examId) {
        echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
        return;
    }

    try {
        $pdo = Db::getConnection();
        
        // Get student's exam attempt with details
        $sql = "SELECT ea.*, e.title as exam_title, e.max_score as exam_max_score,
                CONCAT(u.first_name, ' ', u.last_name) as student_name
                FROM exam_attempts ea
                JOIN exams e ON ea.exam_id = e.id
                JOIN users u ON ea.student_id = u.id
                WHERE ea.exam_id = :exam_id AND ea.student_id = :student_id
                ORDER BY ea.completed_at DESC
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'exam_id' => $examId,
            'student_id' => $user['id']
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo json_encode(['success' => true, 'data' => $result]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No exam attempt found']);
        }
    } catch (PDOException $e) {
        error_log("Get exam result error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}
?>
