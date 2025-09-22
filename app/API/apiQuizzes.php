<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../Db.php';
require_once __DIR__ . '/../Quizzes.php';
require_once __DIR__ . '/../Permissions.php';

// Check if user can manage quizzes
if (!Permission::canManageQuizzes()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied - Teachers/Admins only']);
    exit();
}

$userId = $_SESSION['user']['id'];

header('Content-Type: application/json');

try {
    $pdo = Db::getConnection();
    $quizzesModel = new Quizzes($pdo);
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'datatable';
        
        switch ($action) {
            case 'datatable':
                // Get quizzes for the teacher/admin
                $sql = "
                    SELECT 
                        q.*,
                        l.title as lesson_title,
                        gp.name as grading_period_name,
                        s.name as subject_name
                    FROM quizzes q
                    LEFT JOIN lessons l ON q.lesson_id = l.id
                    LEFT JOIN grading_periods gp ON q.grading_period_id = gp.id
                    LEFT JOIN subjects s ON l.subject_id = s.id
                    ORDER BY q.created_at DESC
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $quizData = [];
                foreach ($quizzes as $quiz) {
                    $quizData[] = [
                        'id' => $quiz['id'],
                        'title' => $quiz['title'],
                        'description' => $quiz['description'],
                        'lesson_title' => $quiz['lesson_title'],
                        'subject_name' => $quiz['subject_name'],
                        'grading_period_name' => $quiz['grading_period_name'],
                        'max_score' => $quiz['max_score'],
                        'time_limit_minutes' => $quiz['time_limit_minutes'],
                        'attempts_allowed' => $quiz['attempts_allowed'],
                        'display_mode' => $quiz['display_mode'],
                        'open_at' => $quiz['open_at'],
                        'close_at' => $quiz['close_at'],
                        'created_at' => $quiz['created_at']
                    ];
                }
                
                // Return DataTables format
                echo json_encode([
                    'draw' => intval($_GET['draw'] ?? 1),
                    'recordsTotal' => count($quizData),
                    'recordsFiltered' => count($quizData),
                    'data' => $quizData
                ]);
                break;
                
            case 'get_quiz':
                $id = (int)($_GET['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid quiz ID');
                }
                
                try {
                    $stmt = $pdo->prepare("
                        SELECT 
                            q.*,
                            l.title as lesson_title,
                            gp.name as grading_period_name,
                            s.name as subject_name
                        FROM quizzes q
                        LEFT JOIN lessons l ON q.lesson_id = l.id
                        LEFT JOIN grading_periods gp ON q.grading_period_id = gp.id
                        LEFT JOIN subjects s ON l.subject_id = s.id
                        WHERE q.id = ?
                    ");
                    $stmt->execute([$id]);
                    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$quiz) {
                        throw new Exception('Quiz not found');
                    }
                    
                    // Log the quiz data for debugging
                    error_log("Quiz data retrieved: " . json_encode($quiz));
                    
                    echo json_encode(['success' => true, 'data' => $quiz]);
                } catch (PDOException $e) {
                    error_log("Database error in get_quiz: " . $e->getMessage());
                    throw new Exception('Database error: ' . $e->getMessage());
                }
                break;
                
            case 'export':
                // Export quizzes data as CSV
                $sql = "
                    SELECT 
                        q.id,
                        q.title,
                        q.description,
                        l.title as lesson_title,
                        gp.name as grading_period_name,
                        s.name as subject_name,
                        q.max_score,
                        q.time_limit_minutes,
                        q.attempts_allowed,
                        q.display_mode,
                        q.open_at,
                        q.close_at,
                        q.created_at
                    FROM quizzes q
                    LEFT JOIN lessons l ON q.lesson_id = l.id
                    LEFT JOIN grading_periods gp ON q.grading_period_id = gp.id
                    LEFT JOIN subjects s ON l.subject_id = s.id
                    ORDER BY q.created_at DESC
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Set headers for CSV download
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="quizzes_export_' . date('Y-m-d') . '.csv"');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                // Create CSV output
                $output = fopen('php://output', 'w');
                
                // Add CSV headers
                fputcsv($output, [
                    'ID', 'Title', 'Description', 'Lesson', 'Subject', 'Grading Period', 
                    'Max Score', 'Time Limit (mins)', 'Attempts Allowed', 'Display Mode',
                    'Open Date', 'Close Date', 'Created Date'
                ]);
                
                // Add data rows
                foreach ($quizzes as $quiz) {
                    fputcsv($output, [
                        $quiz['id'],
                        $quiz['title'],
                        $quiz['description'],
                        $quiz['lesson_title'],
                        $quiz['subject_name'],
                        ucfirst($quiz['grading_period_name']),
                        $quiz['max_score'],
                        $quiz['time_limit_minutes'] ?: 'No limit',
                        $quiz['attempts_allowed'],
                        ucfirst(str_replace('_', ' ', $quiz['display_mode'])),
                        $quiz['open_at'],
                        $quiz['close_at'],
                        $quiz['created_at']
                    ]);
                }
                
                fclose($output);
                exit; // Important: exit after CSV output
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_quiz':
                // Validate required fields
                $requiredFields = ['lesson_id', 'grading_period_id', 'title', 'max_score', 'attempts_allowed', 'display_mode', 'open_at', 'close_at'];
                foreach ($requiredFields as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("Field '{$field}' is required");
                    }
                }
                
                // Validate dates
                $openAt = $_POST['open_at'];
                $closeAt = $_POST['close_at'];
                
                if (strtotime($openAt) >= strtotime($closeAt)) {
                    throw new Exception('Close date must be after open date');
                }
                
                // Create quiz
                $quizData = [
                    'lesson_id' => (int)$_POST['lesson_id'],
                    'grading_period_id' => (int)$_POST['grading_period_id'],
                    'title' => trim($_POST['title']),
                    'description' => trim($_POST['description'] ?? ''),
                    'max_score' => (int)$_POST['max_score'],
                    'time_limit_minutes' => !empty($_POST['time_limit_minutes']) ? (int)$_POST['time_limit_minutes'] : null,
                    'attempts_allowed' => (int)$_POST['attempts_allowed'],
                    'display_mode' => $_POST['display_mode'],
                    'open_at' => $openAt,
                    'close_at' => $closeAt
                ];
                
                $quizId = $quizzesModel->create($quizData);
                
                if ($quizId) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Quiz created successfully',
                        'quiz_id' => $quizId
                    ]);
                } else {
                    throw new Exception('Failed to create quiz');
                }
                break;
                
            case 'update_quiz':
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid quiz ID');
                }
                
                // Validate required fields
                $requiredFields = ['lesson_id', 'grading_period_id', 'title', 'max_score', 'attempts_allowed', 'display_mode', 'open_at', 'close_at'];
                foreach ($requiredFields as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("Field '{$field}' is required");
                    }
                }
                
                // Validate dates
                $openAt = $_POST['open_at'];
                $closeAt = $_POST['close_at'];
                
                if (strtotime($openAt) >= strtotime($closeAt)) {
                    throw new Exception('Close date must be after open date');
                }
                
                // Update quiz
                $quizData = [
                    'lesson_id' => (int)$_POST['lesson_id'],
                    'grading_period_id' => (int)$_POST['grading_period_id'],
                    'title' => trim($_POST['title']),
                    'description' => trim($_POST['description'] ?? ''),
                    'max_score' => (int)$_POST['max_score'],
                    'time_limit_minutes' => !empty($_POST['time_limit_minutes']) ? (int)$_POST['time_limit_minutes'] : null,
                    'attempts_allowed' => (int)$_POST['attempts_allowed'],
                    'display_mode' => $_POST['display_mode'],
                    'open_at' => $openAt,
                    'close_at' => $closeAt
                ];
                
                $success = $quizzesModel->update($id, $quizData);
                
                if ($success) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Quiz updated successfully'
                    ]);
                } else {
                    throw new Exception('Failed to update quiz');
                }
                break;
                
            case 'delete_quiz':
                if (!Permission::canDeleteQuizzes()) {
                    throw new Exception('Access denied - Admin only');
                }
                
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid quiz ID');
                }
                
                $success = $quizzesModel->delete($id);
                
                if ($success) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Quiz deleted successfully'
                    ]);
                } else {
                    throw new Exception('Failed to delete quiz');
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } else {
        throw new Exception('Invalid request method');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>