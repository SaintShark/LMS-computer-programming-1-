<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Include required files
require_once __DIR__ . '/../Db.php';
require_once __DIR__ . '/../Permissions.php';
require_once __DIR__ . '/../Semesters.php';

// Check permissions
if (!Permission::canManageSemesters()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

try {
    $semesters = new Semesters();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'datatable':
            // DataTable server-side processing
            $draw = intval($_GET['draw'] ?? 1);
            $start = intval($_GET['start'] ?? 0);
            $length = intval($_GET['length'] ?? 10);
            $search = $_GET['search']['value'] ?? '';
            $orderColumn = intval($_GET['order'][0]['column'] ?? 0);
            $orderDir = $_GET['order'][0]['dir'] ?? 'desc';
            
            $data = $semesters->getPaginated($start, $length, $search, $orderColumn, $orderDir);
            $totalRecords = $semesters->getTotalCount();
            $filteredRecords = $semesters->getTotalCount($search);
            
            $response = [
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data
            ];
            
            echo json_encode($response);
            break;
            
        case 'list':
            // Get all semesters for export
            $data = $semesters->getAllForDataTable();
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'get_semester':
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                throw new Exception('Semester ID is required');
            }
            
            $semester = $semesters->getById($id);
            if (!$semester) {
                throw new Exception('Semester not found');
            }
            
            echo json_encode(['success' => true, 'data' => $semester]);
            break;
            
        case 'create_semester':
            if (!Permission::canAddSemesters()) {
                throw new Exception('Access denied');
            }
            
            $name = $_POST['name'] ?? '';
            $academic_year = $_POST['academic_year'] ?? '';
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $status = $_POST['status'] ?? 'active';
            
            // Validation
            if (empty($name) || empty($academic_year) || empty($start_date) || empty($end_date)) {
                throw new Exception('All fields are required');
            }
            
            // Validate dates
            if (strtotime($start_date) >= strtotime($end_date)) {
                throw new Exception('End date must be after start date');
            }
            
            // Check for overlapping semesters
            if (!$semesters->validateDates($start_date, $end_date)) {
                throw new Exception('Semester dates overlap with existing semester');
            }
            
            $data = [
                'name' => $name,
                'academic_year' => $academic_year,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'status' => $status
            ];
            
            $semesterId = $semesters->create($data);
            echo json_encode(['success' => true, 'message' => 'Semester created successfully', 'id' => $semesterId]);
            break;
            
        case 'update_semester':
            if (!Permission::canEditSemesters()) {
                throw new Exception('Access denied');
            }
            
            $id = $_POST['id'] ?? '';
            $name = $_POST['name'] ?? '';
            $academic_year = $_POST['academic_year'] ?? '';
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $status = $_POST['status'] ?? 'active';
            
            // Validation
            if (empty($id) || empty($name) || empty($academic_year) || empty($start_date) || empty($end_date)) {
                throw new Exception('All fields are required');
            }
            
            // Validate dates
            if (strtotime($start_date) >= strtotime($end_date)) {
                throw new Exception('End date must be after start date');
            }
            
            // Check for overlapping semesters (excluding current one)
            if (!$semesters->validateDates($start_date, $end_date, $id)) {
                throw new Exception('Semester dates overlap with existing semester');
            }
            
            $data = [
                'name' => $name,
                'academic_year' => $academic_year,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'status' => $status
            ];
            
            $semesters->update($id, $data);
            echo json_encode(['success' => true, 'message' => 'Semester updated successfully']);
            break;
            
        case 'delete_semester':
            if (!Permission::canDeleteSemesters()) {
                throw new Exception('Access denied');
            }
            
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                throw new Exception('Semester ID is required');
            }
            
            $semesters->delete($id);
            echo json_encode(['success' => true, 'message' => 'Semester deleted successfully']);
            break;
            
        case 'dropdown':
            // Get semesters for dropdown
            $data = $semesters->getForDropdown();
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'active':
            // Get active semester
            $semester = $semesters->getActive();
            echo json_encode(['success' => true, 'data' => $semester]);
            break;
            
        case 'statistics':
            $stats = $semesters->getStatistics();
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
