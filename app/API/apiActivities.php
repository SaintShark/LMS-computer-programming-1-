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
require_once __DIR__ . '/../Activities.php';
require_once __DIR__ . '/../Permissions.php';

// Check permissions - all roles can view activities, but management requires permissions
$canManage = Permission::canManageActivities();

header('Content-Type: application/json');

try {
    $pdo = Db::getConnection();
    $activitiesModel = new Activities($pdo);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        // Check if user can manage activities for POST actions
        if (!$canManage) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit();
        }
        switch ($_POST['action']) {
            case 'create_activity':
                // Check add permission
                if (!Permission::canAddActivities()) {
                    throw new Exception('You do not have permission to add activities');
                }
                
                // Handle file upload
                $activityFile = null;
                if (isset($_FILES['activity_file']) && $_FILES['activity_file']['error'] === UPLOAD_ERR_OK) {
                    $activityFile = handleFileUpload($_FILES['activity_file']);
                }
                
                $data = [
                    'subject_id' => (int)($_POST['subject_id'] ?? 0),
                    'grading_period_id' => (int)($_POST['grading_period_id'] ?? 0),
                    'title' => trim($_POST['title'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'activity_file' => $activityFile,
                    'allow_from' => $_POST['allow_from'] ?? null,
                    'due_date' => $_POST['due_date'] ?? null,
                    'cutoff_date' => $_POST['cutoff_date'] ?? null,
                    'reminder_date' => $_POST['reminder_date'] ?? null,
                    'deduction_percent' => (float)($_POST['deduction_percent'] ?? 0),
                    'status' => $_POST['status'] ?? 'active'
                ];
                
                if (empty($data['title']) || $data['subject_id'] <= 0 || $data['grading_period_id'] <= 0) {
                    throw new Exception('Title, Subject, and Grading Period are required');
                }
                
                $result = $activitiesModel->create($data);
                echo json_encode(['success' => true, 'message' => 'Activity created successfully', 'data' => $result]);
                break;
                
            case 'update_activity':
                // Check edit permission
                if (!Permission::canEditActivities()) {
                    throw new Exception('You do not have permission to edit activities');
                }
                
                $id = (int)($_POST['id'] ?? 0);
                
                // Get current activity to preserve existing file if no new file uploaded
                $currentActivity = $activitiesModel->getById($id);
                $activityFile = $currentActivity['activity_file'] ?? null;
                
                // Handle new file upload
                if (isset($_FILES['activity_file']) && $_FILES['activity_file']['error'] === UPLOAD_ERR_OK) {
                    // Delete old file if exists
                    if ($activityFile && file_exists(__DIR__ . '/../../uploads/activities/teacher/' . $activityFile)) {
                        unlink(__DIR__ . '/../../uploads/activities/teacher/' . $activityFile);
                    }
                    $activityFile = handleFileUpload($_FILES['activity_file']);
                }
                
                $data = [
                    'subject_id' => (int)($_POST['subject_id'] ?? 0),
                    'grading_period_id' => (int)($_POST['grading_period_id'] ?? 0),
                    'title' => trim($_POST['title'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'activity_file' => $activityFile,
                    'allow_from' => $_POST['allow_from'] ?? null,
                    'due_date' => $_POST['due_date'] ?? null,
                    'cutoff_date' => $_POST['cutoff_date'] ?? null,
                    'reminder_date' => $_POST['reminder_date'] ?? null,
                    'deduction_percent' => (float)($_POST['deduction_percent'] ?? 0),
                    'status' => $_POST['status'] ?? 'active'
                ];
                
                if (empty($data['title']) || $data['subject_id'] <= 0 || $data['grading_period_id'] <= 0 || $id <= 0) {
                    throw new Exception('Invalid data provided');
                }
                
                $result = $activitiesModel->update($id, $data);
                echo json_encode(['success' => true, 'message' => 'Activity updated successfully', 'data' => $result]);
                break;
                
            case 'delete_activity':
                // Check delete permission (Admin only)
                if (!Permission::canDeleteActivities()) {
                    throw new Exception('You do not have permission to delete activities');
                }
                
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid activity ID');
                }
                
                $result = $activitiesModel->delete($id);
                echo json_encode(['success' => true, 'message' => 'Activity deleted successfully', 'data' => $result]);
                break;
                
            case 'get_activity':
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid activity ID');
                }
                
                $result = $activitiesModel->getById($id);
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'list':
                $activities = $activitiesModel->getAll();
                echo json_encode(['success' => true, 'data' => $activities]);
                break;
                
            case 'datatable':
                $activities = $activitiesModel->getAllForDataTable();
                echo json_encode(['data' => $activities]);
                break;
                
            case 'get_subjects':
                // Get all subjects for dropdown
                $stmt = $pdo->prepare("SELECT id, name FROM subjects ORDER BY name");
                $stmt->execute();
                $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $subjects]);
                break;
                
            case 'get_grading_periods':
                // Get all grading periods for dropdown
                $stmt = $pdo->prepare("
                    SELECT gp.id, gp.name, s.academic_year 
                    FROM grading_periods gp 
                    JOIN semesters s ON gp.semester_id = s.id 
                    WHERE gp.status = 'active' 
                    ORDER BY gp.name
                ");
                $stmt->execute();
                $gradingPeriods = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $gradingPeriods]);
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

/**
 * Handle file upload for activity files
 */
function handleFileUpload($file) {
    // Create uploads directory if it doesn't exist
    $uploadDir = __DIR__ . '/../../uploads/activities/teacher/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validate file
    $allowedTypes = ['c', 'cpp', 'h', 'txt', 'pdf', 'doc', 'docx'];
    $maxSize = 10 * 1024 * 1024; // 10MB
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileName = pathinfo($file['name'], PATHINFO_FILENAME);
    
    if (!in_array($fileExtension, $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $allowedTypes));
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('File size too large. Maximum size: 10MB');
    }
    
    // Generate unique filename
    $uniqueFileName = $fileName . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
    $uploadPath = $uploadDir . $uniqueFileName;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to upload file');
    }
    
    return $uniqueFileName;
}
?>