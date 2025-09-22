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
require_once __DIR__ . '/../Interventions.php';
require_once __DIR__ . '/../Permissions.php';

// Check permissions - all roles can view interventions, but management requires permissions
$canManage = Permission::canManageInterventions();
$userRole = $_SESSION['user']['role'];
$userId = $_SESSION['user']['id'];

header('Content-Type: application/json');

try {
    $interventionsModel = new Interventions();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        // Check if user can manage interventions for POST actions
        if (!$canManage && in_array($_POST['action'], ['create_intervention', 'update_intervention', 'delete_intervention', 'get_intervention_statistics', 'export_interventions'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden - Management access required']);
            exit();
        }
        
        switch ($_POST['action']) {
            case 'create_intervention':
                // Check add permission
                if (!Permission::canAddInterventions()) {
                    throw new Exception('You do not have permission to add interventions');
                }
                
                $data = [
                    'student_id' => (int)($_POST['student_id'] ?? 0),
                    'subject_id' => (int)($_POST['subject_id'] ?? 0),
                    'notes' => trim($_POST['notes'] ?? ''),
                    'notify_teacher' => (int)($_POST['notify_teacher'] ?? 0)
                ];
                
                // Validate data
                $errors = $interventionsModel->validate($data);
                if (!empty($errors)) {
                    throw new Exception(implode(', ', $errors));
                }
                
                $result = $interventionsModel->create($data);
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Intervention created successfully']);
                } else {
                    throw new Exception('Failed to create intervention');
                }
                break;
                
            case 'update_intervention':
                // Check edit permission
                if (!Permission::canEditInterventions()) {
                    throw new Exception('You do not have permission to edit interventions');
                }
                
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid intervention ID');
                }
                
                // Check if intervention exists
                $intervention = $interventionsModel->getById($id);
                if (!$intervention) {
                    throw new Exception('Intervention not found');
                }
                
                // Check ownership for teachers
                if ($userRole === 'teacher' && $intervention['created_by'] != $userId) {
                    throw new Exception('You can only edit your own interventions');
                }
                
                $data = [
                    'student_id' => (int)($_POST['student_id'] ?? 0),
                    'subject_id' => (int)($_POST['subject_id'] ?? 0),
                    'notes' => trim($_POST['notes'] ?? ''),
                    'notify_teacher' => (int)($_POST['notify_teacher'] ?? 0)
                ];
                
                // Validate data
                $errors = $interventionsModel->validate($data);
                if (!empty($errors)) {
                    throw new Exception(implode(', ', $errors));
                }
                
                $result = $interventionsModel->update($id, $data);
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Intervention updated successfully']);
                } else {
                    throw new Exception('Failed to update intervention');
                }
                break;
                
            case 'delete_intervention':
                // Check delete permission (Admin only)
                if (!Permission::canDeleteInterventions()) {
                    throw new Exception('You do not have permission to delete interventions');
                }
                
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid intervention ID');
                }
                
                $intervention = $interventionsModel->getById($id);
                if (!$intervention) {
                    throw new Exception('Intervention not found');
                }
                
                $result = $interventionsModel->delete($id);
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Intervention deleted successfully']);
                } else {
                    throw new Exception('Failed to delete intervention');
                }
                break;
                
            case 'get_intervention':
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid intervention ID');
                }
                
                $result = $interventionsModel->getById($id);
                if (!$result) {
                    throw new Exception('Intervention not found');
                }
                
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_intervention_statistics':
                $result = $interventionsModel->getStatistics();
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'export_interventions':
                $interventions = $interventionsModel->getAll();
                echo json_encode(['success' => true, 'data' => $interventions]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'list':
                $interventions = $interventionsModel->getAll();
                echo json_encode(['success' => true, 'data' => $interventions]);
                break;
                
            case 'datatable':
                $interventions = $interventionsModel->getAllForDataTable();
                echo json_encode(['data' => $interventions]);
                break;
                
            case 'get_intervention':
                $id = (int)($_GET['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid intervention ID');
                }
                
                $result = $interventionsModel->getById($id);
                if (!$result) {
                    throw new Exception('Intervention not found');
                }
                
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_recent':
                $limit = (int)($_GET['limit'] ?? 5);
                $result = $interventionsModel->getRecent($limit);
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_by_student':
                $studentId = (int)($_GET['student_id'] ?? 0);
                
                if ($studentId <= 0) {
                    throw new Exception('Invalid student ID');
                }
                
                $result = $interventionsModel->getByStudentId($studentId);
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_by_creator':
                $creatorId = (int)($_GET['creator_id'] ?? $userId);
                $result = $interventionsModel->getByCreator($creatorId);
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'search':
                $searchTerm = trim($_GET['search'] ?? '');
                
                if (empty($searchTerm)) {
                    throw new Exception('Search term is required');
                }
                
                $result = $interventionsModel->search($searchTerm);
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_statistics':
                $result = $interventionsModel->getStatistics();
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_count_by_creator':
                $result = $interventionsModel->getCountByCreator();
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_students':
                $result = $interventionsModel->getAllStudents();
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_subjects':
                $result = $interventionsModel->getAllSubjects();
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } else {
        throw new Exception('Invalid request method or missing action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
