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
require_once __DIR__ . '/../Students.php';
require_once __DIR__ . '/../Permissions.php';

// Check if user can view quizzes (students can view their own, teachers/admins can view all)
if (!Permission::canViewOwnQuizzes() && !Permission::canManageQuizzes()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$userId = $_SESSION['user']['id'];

header('Content-Type: application/json');

try {
    $pdo = Db::getConnection();
    $quizzesModel = new Quizzes($pdo);
    $studentsModel = new Students($pdo);
    
    // Get student ID from user ID
    $studentId = $studentsModel->getStudentIdByUserId($userId);
    
    if (!$studentId) {
        // If no student record exists, create one for the existing user
        try {
            $stmt = $pdo->prepare("INSERT INTO students (user_id, course, year_level) VALUES (?, 'BSIT', '1st Year')");
            $stmt->execute([$userId]);
            $studentId = $pdo->lastInsertId();
        } catch (Exception $e) {
            throw new Exception('Failed to create student record: ' . $e->getMessage());
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'datatable';
        
        switch ($action) {
            case 'datatable':
                // Get period filter if provided
                $periodFilter = $_GET['period'] ?? '';
                
                // Get quizzes for the student's subject (Computer Programming 1)
                $sql = "
                    SELECT 
                        q.*,
                        l.title as lesson_title,
                        gp.name as grading_period_name,
                        qa.score,
                        qa.finished_at as taken_at
                    FROM quizzes q
                    LEFT JOIN lessons l ON q.lesson_id = l.id
                    LEFT JOIN grading_periods gp ON q.grading_period_id = gp.id
                    LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.student_id = ? AND qa.status = 'submitted'
                    WHERE l.subject_id = 1
                ";
                
                $params = [$studentId];
                
                // Apply grading period filter if specified
                if ($periodFilter && !empty($periodFilter)) {
                    $sql .= " AND LOWER(gp.name) LIKE ?";
                    $params[] = '%' . strtolower($periodFilter) . '%';
                }
                
                $sql .= " ORDER BY q.open_at ASC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $studentQuizzes = [];
                foreach ($quizzes as $quiz) {
                    $studentQuizzes[] = [
                        'id' => $quiz['id'],
                        'title' => $quiz['title'],
                        'description' => $quiz['description'],
                        'lesson_title' => $quiz['lesson_title'],
                        'grading_period_name' => $quiz['grading_period_name'],
                        'max_score' => $quiz['max_score'],
                        'time_limit_minutes' => $quiz['time_limit_minutes'],
                        'attempts_allowed' => $quiz['attempts_allowed'],
                        'display_mode' => $quiz['display_mode'],
                        'open_at' => $quiz['open_at'],
                        'close_at' => $quiz['close_at'],
                        'score' => $quiz['score'],
                        'taken_at' => $quiz['taken_at']
                    ];
                }
                
                // Return DataTables format
                echo json_encode([
                    'draw' => intval($_GET['draw'] ?? 1),
                    'recordsTotal' => count($studentQuizzes),
                    'recordsFiltered' => count($studentQuizzes),
                    'data' => $studentQuizzes
                ]);
                break;
                
            case 'get_quiz':
                $id = (int)($_GET['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid quiz ID');
                }
                
                $stmt = $pdo->prepare("
                    SELECT 
                        q.*,
                        l.title as lesson_title,
                        gp.name as grading_period_name,
                        qa.score,
                        qa.finished_at as taken_at
                    FROM quizzes q
                    LEFT JOIN lessons l ON q.lesson_id = l.id
                    LEFT JOIN grading_periods gp ON q.grading_period_id = gp.id
                    LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.student_id = ? AND qa.status = 'submitted'
                    WHERE q.id = ? AND l.subject_id = 1
                ");
                $stmt->execute([$studentId, $id]);
                $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$quiz) {
                    throw new Exception('Quiz not found');
                }
                
                echo json_encode(['success' => true, 'data' => $quiz]);
                break;
                
            case 'get_result':
                $id = (int)($_GET['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid quiz ID');
                }
                
                $stmt = $pdo->prepare("
                    SELECT 
                        qa.score,
                        qa.finished_at as taken_at,
                        q.title as quiz_title,
                        q.max_score
                    FROM quiz_attempts qa
                    LEFT JOIN quizzes q ON qa.quiz_id = q.id
                    WHERE qa.quiz_id = ? AND qa.student_id = ? AND qa.status = 'submitted'
                    ORDER BY qa.finished_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$id, $studentId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$result) {
                    throw new Exception('Quiz result not found');
                }
                
                echo json_encode(['success' => true, 'data' => $result]);
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