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
require_once __DIR__ . '/../Permissions.php';

// Check if user can manage grading periods or quizzes
if (!Permission::canManageGradingPeriods() && !Permission::canManageQuizzes()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $pdo = Db::getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'get_all':
            case 'dropdown':
                // Get all grading periods for dropdown
                $stmt = $pdo->prepare("
                    SELECT 
                        gp.id,
                        gp.name,
                        gp.start_date,
                        gp.end_date,
                        gp.status,
                        s.name as semester_name,
                        s.academic_year
                    FROM grading_periods gp
                    LEFT JOIN semesters s ON gp.semester_id = s.id
                    ORDER BY s.academic_year DESC, gp.start_date
                ");
                $stmt->execute();
                $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $periods]);
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