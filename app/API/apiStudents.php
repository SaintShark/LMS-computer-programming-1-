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
require_once __DIR__ . '/../Students.php';
require_once __DIR__ . '/../Permissions.php';

// Check permissions - only admin and teacher can manage students
if (!Permission::canManageStudents()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

header('Content-Type: application/json');

try {
    $pdo = Db::getConnection();
    $studentsModel = new Students($pdo);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_student':
                // Check add permission
                if (!Permission::canAddStudents()) {
                    throw new Exception('You do not have permission to add students');
                }
                
                $data = [
                    'first_name' => trim($_POST['first_name'] ?? ''),
                    'last_name' => trim($_POST['last_name'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'password' => trim($_POST['password'] ?? ''),
                    'course' => 'BSIT', // Automatically set to BSIT
                    'year_level' => '1st' // Automatically set to 1st Year
                ];
                
                // Validation
                if (empty($data['first_name']) || empty($data['last_name']) || 
                    empty($data['email']) || empty($data['password'])) {
                    throw new Exception('All required fields must be filled');
                }
                
                // Check if email already exists
                if ($studentsModel->emailExists($data['email'])) {
                    throw new Exception('Email already exists');
                }
                
                // Validate email format
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format');
                }
                
                $result = $studentsModel->create($data);
                echo json_encode(['success' => true, 'message' => 'Student created successfully', 'data' => $result]);
                break;
                
            case 'update_student':
                // Check edit permission
                if (!Permission::canEditStudents()) {
                    throw new Exception('You do not have permission to edit students');
                }
                
                $id = (int)($_POST['id'] ?? 0);
                $data = [
                    'first_name' => trim($_POST['first_name'] ?? ''),
                    'last_name' => trim($_POST['last_name'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'course' => 'BSIT', // Automatically set to BSIT
                    'year_level' => '1st' // Automatically set to 1st Year
                ];
                
                // Optional fields for update
                if (isset($_POST['password']) && !empty($_POST['password'])) {
                    $data['password'] = trim($_POST['password']);
                }
                
                if (empty($data['first_name']) || empty($data['last_name']) || 
                    empty($data['email']) || $id <= 0) {
                    throw new Exception('Invalid data provided');
                }
                
                // Check if email already exists (excluding current student)
                if ($studentsModel->emailExists($data['email'], $id)) {
                    throw new Exception('Email already exists');
                }
                
                // Validate email format
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format');
                }
                
                $result = $studentsModel->update($id, $data);
                echo json_encode(['success' => true, 'message' => 'Student updated successfully', 'data' => $result]);
                break;
                
            case 'delete_student':
                // Check delete permission (Admin only)
                if (!Permission::canDeleteStudents()) {
                    throw new Exception('You do not have permission to delete students');
                }
                
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid student ID');
                }
                
                $result = $studentsModel->delete($id);
                echo json_encode(['success' => true, 'message' => 'Student deleted successfully', 'data' => $result]);
                break;
                
            case 'get_student':
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid student ID');
                }
                
                $result = $studentsModel->getById($id);
                if (!$result) {
                    throw new Exception('Student not found');
                }
                
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_student_submissions':
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid student ID');
                }
                
                $submissions = $studentsModel->getSubmissions($id);
                echo json_encode(['success' => true, 'data' => $submissions]);
                break;
                
            case 'get_student_statistics':
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid student ID');
                }
                
                $statistics = $studentsModel->getStatistics($id);
                echo json_encode(['success' => true, 'data' => $statistics]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'list':
                $students = $studentsModel->getAll();
                echo json_encode(['success' => true, 'data' => $students]);
                break;
                
            case 'datatable':
                $students = $studentsModel->getAllForDataTable();
                echo json_encode(['data' => $students]);
                break;
                
            case 'get_student':
                $id = (int)($_GET['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid student ID');
                }
                
                $result = $studentsModel->getById($id);
                if (!$result) {
                    throw new Exception('Student not found');
                }
                
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
                
            case 'check_email':
                $email = trim($_GET['email'] ?? '');
                $excludeId = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : null;
                
                if (empty($email)) {
                    throw new Exception('Email is required');
                }
                
                $exists = $studentsModel->emailExists($email, $excludeId);
                echo json_encode(['success' => true, 'exists' => $exists]);
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
