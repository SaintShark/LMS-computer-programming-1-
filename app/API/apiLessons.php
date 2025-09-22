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

// Check if user has any lesson permissions
if (!Permission::canViewLessons()) {
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
                // Get all lessons for dropdown
                $stmt = $pdo->prepare("
                    SELECT 
                        l.id,
                        l.title,
                        l.subject_id,
                        s.name as subject_name
                    FROM lessons l
                    LEFT JOIN subjects s ON l.subject_id = s.id
                    ORDER BY s.name, l.title
                ");
                $stmt->execute();
                $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $lessons]);
                break;
                
            case 'get_subjects':
                // Get all subjects for dropdown
                $stmt = $pdo->prepare("
                    SELECT 
                        id,
                        name,
                        description
                    FROM subjects
                    ORDER BY name ASC
                ");
                $stmt->execute();
                $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $subjects]);
                break;
                
            case 'datatable':
                // Handle DataTables server-side processing
                require_once __DIR__ . '/../Lessons.php';
                $lessonsObj = new Lessons();
                
                // Get DataTables parameters
                $draw = intval($_GET['draw'] ?? 1);
                $start = intval($_GET['start'] ?? 0);
                $length = intval($_GET['length'] ?? 10);
                $searchValue = $_GET['search']['value'] ?? '';
                $orderColumn = intval($_GET['order'][0]['column'] ?? 2); // Default to created_at
                $orderDir = $_GET['order'][0]['dir'] ?? 'desc';
                
                // Get paginated data
                $data = $lessonsObj->getPaginated($start, $length, $searchValue, $orderColumn, $orderDir);
                $totalRecords = $lessonsObj->getTotalCount();
                $filteredRecords = $lessonsObj->getTotalCount($searchValue);
                
                // Format data for DataTables
                $formattedData = [];
                foreach ($data as $lesson) {
                    $formattedData[] = [
                        'title' => htmlspecialchars($lesson['title']),
                        'content' => htmlspecialchars($lesson['content']),
                        'created_at' => $lesson['created_at'],
                        'status' => '<span class="badge bg-success">Active</span>',
                        'actions' => '
                            <button class="btn btn-outline-primary me-1" onclick="editLesson(' . $lesson['id'] . ')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteLesson(' . $lesson['id'] . ')">
                                <i class="bi bi-trash"></i>
                            </button>
                        '
                    ];
                }
                
                // Return DataTables response
                echo json_encode([
                    'draw' => $draw,
                    'recordsTotal' => $totalRecords,
                    'recordsFiltered' => $filteredRecords,
                    'data' => $formattedData
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_lesson':
                // Check add permission
                if (!Permission::canManageLessons()) {
                    throw new Exception('You do not have permission to add lessons');
                }
                
                require_once __DIR__ . '/../Lessons.php';
                $lessonsObj = new Lessons();
                
                $data = [
                    'subject_id' => (int)($_POST['subject_id'] ?? 0),
                    'title' => trim($_POST['title'] ?? ''),
                    'content' => trim($_POST['content'] ?? '')
                ];
                
                // Validation
                if (empty($data['title']) || $data['subject_id'] <= 0) {
                    throw new Exception('Title and Subject are required');
                }
                
                $lessonId = $lessonsObj->create($data);
                echo json_encode(['success' => true, 'message' => 'Lesson created successfully', 'id' => $lessonId]);
                break;
                
            case 'update_lesson':
                // Check edit permission
                if (!Permission::canManageLessons()) {
                    throw new Exception('You do not have permission to edit lessons');
                }
                
                require_once __DIR__ . '/../Lessons.php';
                $lessonsObj = new Lessons();
                
                $id = (int)($_POST['id'] ?? 0);
                $data = [
                    'subject_id' => (int)($_POST['subject_id'] ?? 0),
                    'title' => trim($_POST['title'] ?? ''),
                    'content' => trim($_POST['content'] ?? '')
                ];
                
                // Validation
                if ($id <= 0 || empty($data['title']) || $data['subject_id'] <= 0) {
                    throw new Exception('ID, Title and Subject are required');
                }
                
                $lessonsObj->update($id, $data);
                echo json_encode(['success' => true, 'message' => 'Lesson updated successfully']);
                break;
                
            case 'delete_lesson':
                // Check delete permission
                if (!Permission::canManageLessons()) {
                    throw new Exception('You do not have permission to delete lessons');
                }
                
                require_once __DIR__ . '/../Lessons.php';
                $lessonsObj = new Lessons();
                
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('Lesson ID is required');
                }
                
                $lessonsObj->delete($id);
                echo json_encode(['success' => true, 'message' => 'Lesson deleted successfully']);
                break;
                
            case 'get_lesson':
                require_once __DIR__ . '/../Lessons.php';
                $lessonsObj = new Lessons();
                
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('Lesson ID is required');
                }
                
                $lesson = $lessonsObj->getById($id);
                if (!$lesson) {
                    throw new Exception('Lesson not found');
                }
                
                echo json_encode(['success' => true, 'data' => $lesson]);
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