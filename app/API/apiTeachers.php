<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../Teachers.php';
require_once '../Permissions.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check permissions
if (!Permission::canManageUsers()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$teachers = new Teachers();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'datatable':
            // DataTable server-side processing
            $draw = intval($_GET['draw'] ?? 1);
            $start = intval($_GET['start'] ?? 0);
            $length = intval($_GET['length'] ?? 10);
            $search = $_GET['search']['value'] ?? '';
            $orderColumn = intval($_GET['order'][0]['column'] ?? 0);
            $orderDir = $_GET['order'][0]['dir'] ?? 'desc';
            
            $data = $teachers->getPaginated($start, $length, $search, $orderColumn, $orderDir);
            $totalRecords = $teachers->getTotalCount();
            $filteredRecords = $teachers->getTotalCount($search);
            
            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data
            ]);
            break;
            
        case 'list':
            // Get all teachers for export
            $data = $teachers->getAllForDataTable();
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'get_teacher':
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                throw new Exception('Teacher ID is required');
            }
            
            $teacher = $teachers->getById($id);
            if (!$teacher) {
                throw new Exception('Teacher not found');
            }
            
            echo json_encode(['success' => true, 'data' => $teacher]);
            break;
            
        case 'create_teacher':
            $first_name = $_POST['first_name'] ?? '';
            $last_name = $_POST['last_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $department = $_POST['department'] ?? '';
            
            // Validation
            if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($department)) {
                throw new Exception('All fields are required');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }
            
            if (strlen($password) < 6) {
                throw new Exception('Password must be at least 6 characters');
            }
            
            // Check if email already exists
            if ($teachers->emailExists($email)) {
                throw new Exception('Email already exists');
            }
            
            $data = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'password' => $password,
                'department' => $department
            ];
            
            $teacherId = $teachers->create($data);
            echo json_encode(['success' => true, 'message' => 'Teacher created successfully', 'id' => $teacherId]);
            break;
            
        case 'update_teacher':
            $id = $_POST['id'] ?? '';
            $first_name = $_POST['first_name'] ?? '';
            $last_name = $_POST['last_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $department = $_POST['department'] ?? '';
            
            // Validation
            if (empty($id) || empty($first_name) || empty($last_name) || empty($email) || empty($department)) {
                throw new Exception('All required fields must be filled');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }
            
            if (!empty($password) && strlen($password) < 6) {
                throw new Exception('Password must be at least 6 characters');
            }
            
            // Check if email already exists (excluding current teacher)
            if ($teachers->emailExists($email, $id)) {
                throw new Exception('Email already exists');
            }
            
            $data = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'department' => $department
            ];
            
            // Only include password if provided
            if (!empty($password)) {
                $data['password'] = $password;
            }
            
            $teachers->update($id, $data);
            echo json_encode(['success' => true, 'message' => 'Teacher updated successfully']);
            break;
            
        case 'delete_teacher':
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                throw new Exception('Teacher ID is required');
            }
            
            $teachers->delete($id);
            echo json_encode(['success' => true, 'message' => 'Teacher deleted successfully']);
            break;
            
        case 'check_email':
            $email = $_GET['email'] ?? '';
            $exclude_id = $_GET['exclude_id'] ?? null;
            
            if (empty($email)) {
                throw new Exception('Email is required');
            }
            
            $exists = $teachers->emailExists($email, $exclude_id);
            echo json_encode(['exists' => $exists]);
            break;
            
        case 'statistics':
            $stats = $teachers->getStatistics();
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
