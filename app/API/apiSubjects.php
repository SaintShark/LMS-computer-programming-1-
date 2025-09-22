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
require_once __DIR__ . '/../Subjects.php';

// Check permissions - Admin and Teacher can manage subjects
if (!Permission::canManageStudents()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

try {
    $subjects = new Subjects();
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
            
            $data = $subjects->getPaginated($start, $length, $search, $orderColumn, $orderDir);
            $totalRecords = $subjects->getTotalCount();
            $filteredRecords = $subjects->getTotalCount($search);
            
            $response = [
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data
            ];
            
            echo json_encode($response);
            break;
            
        case 'list':
            // Get all subjects for export
            $data = $subjects->getAllForDataTable();
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'get_subject':
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                throw new Exception('Subject ID is required');
            }
            
            $subject = $subjects->getById($id);
            if (!$subject) {
                throw new Exception('Subject not found');
            }
            
            echo json_encode(['success' => true, 'data' => $subject]);
            break;
            
        case 'create_subject':
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $year_level = $_POST['year_level'] ?? '';
            $course = $_POST['course'] ?? '';
            
            // Validation
            if (empty($name) || empty($year_level) || empty($course)) {
                throw new Exception('Name, Year Level, and Course are required');
            }
            
            // Check if subject name already exists
            if ($subjects->nameExists($name)) {
                throw new Exception('Subject name already exists');
            }
            
            $data = [
                'name' => $name,
                'description' => $description,
                'year_level' => $year_level,
                'course' => $course
            ];
            
            $subjectId = $subjects->create($data);
            echo json_encode(['success' => true, 'message' => 'Subject created successfully', 'id' => $subjectId]);
            break;
            
        case 'update_subject':
            $id = $_POST['id'] ?? '';
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $year_level = $_POST['year_level'] ?? '';
            $course = $_POST['course'] ?? '';
            
            // Validation
            if (empty($id) || empty($name) || empty($year_level) || empty($course)) {
                throw new Exception('All fields are required');
            }
            
            // Check if subject name already exists (excluding current subject)
            if ($subjects->nameExists($name, $id)) {
                throw new Exception('Subject name already exists');
            }
            
            $data = [
                'name' => $name,
                'description' => $description,
                'year_level' => $year_level,
                'course' => $course
            ];
            
            $subjects->update($id, $data);
            echo json_encode(['success' => true, 'message' => 'Subject updated successfully']);
            break;
            
        case 'delete_subject':
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                throw new Exception('Subject ID is required');
            }
            
            $subjects->delete($id);
            echo json_encode(['success' => true, 'message' => 'Subject deleted successfully']);
            break;
            
        case 'dropdown':
            // Get subjects for dropdown
            $data = $subjects->getForDropdown();
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'by_course':
            // Get subjects by course
            $course = $_GET['course'] ?? '';
            if (empty($course)) {
                throw new Exception('Course is required');
            }
            
            $data = $subjects->getByCourse($course);
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'by_year_level':
            // Get subjects by year level
            $yearLevel = $_GET['year_level'] ?? '';
            if (empty($yearLevel)) {
                throw new Exception('Year level is required');
            }
            
            $data = $subjects->getByYearLevel($yearLevel);
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'statistics':
            $stats = $subjects->getStatistics();
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'unique_courses':
            $courses = $subjects->getUniqueCourses();
            echo json_encode(['success' => true, 'data' => $courses]);
            break;
            
        case 'unique_year_levels':
            $yearLevels = $subjects->getUniqueYearLevels();
            echo json_encode(['success' => true, 'data' => $yearLevels]);
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
