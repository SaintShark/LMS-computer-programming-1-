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
require_once __DIR__ . '/../Announcements.php';
require_once __DIR__ . '/../Permissions.php';

// Check permissions - all roles can view announcements, only admin/teacher can manage
$userRole = $_SESSION['user']['role'];
$userId = $_SESSION['user']['id'];

// For viewing announcements, all roles are allowed
$canManage = Permission::canManageAnnouncements();

header('Content-Type: application/json');

try {
    $pdo = Db::getConnection();
    $announcementsModel = new Announcements($pdo);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        // Check if user can manage announcements
        if (!$canManage) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit();
        }
        
        switch ($_POST['action']) {
            case 'create_announcement':
                // Check add permission
                if (!Permission::canAddAnnouncements()) {
                    throw new Exception('You do not have permission to create announcements');
                }
                
                $data = [
                    'title' => trim($_POST['title'] ?? ''),
                    'message' => trim($_POST['message'] ?? ''),
                    'created_by' => $userId
                ];
                
                // Validation
                if (empty($data['title']) || empty($data['message'])) {
                    throw new Exception('Title and message are required');
                }
                
                if (strlen($data['title']) > 255) {
                    throw new Exception('Title must be 255 characters or less');
                }
                
                $result = $announcementsModel->create($data);
                echo json_encode(['success' => true, 'message' => 'Announcement created successfully', 'data' => $result]);
                break;
                
            case 'update_announcement':
                // Check edit permission
                if (!Permission::canEditAnnouncements()) {
                    throw new Exception('You do not have permission to edit announcements');
                }
                
                $id = (int)($_POST['id'] ?? 0);
                $data = [
                    'title' => trim($_POST['title'] ?? ''),
                    'message' => trim($_POST['message'] ?? '')
                ];
                
                if ($id <= 0) {
                    throw new Exception('Invalid announcement ID');
                }
                
                if (empty($data['title']) || empty($data['message'])) {
                    throw new Exception('Title and message are required');
                }
                
                if (strlen($data['title']) > 255) {
                    throw new Exception('Title must be 255 characters or less');
                }
                
                // Check if user can edit this announcement
                $announcement = $announcementsModel->getById($id);
                if (!$announcement) {
                    throw new Exception('Announcement not found');
                }
                
                // Only admin can edit all announcements, teachers can only edit their own
                if ($userRole !== 'admin' && $announcement['created_by'] != $userId) {
                    throw new Exception('You can only edit your own announcements');
                }
                
                $result = $announcementsModel->update($id, $data);
                echo json_encode(['success' => true, 'message' => 'Announcement updated successfully', 'data' => $result]);
                break;
                
            case 'delete_announcement':
                // Check delete permission (Admin only)
                if (!Permission::canDeleteAnnouncements()) {
                    throw new Exception('You do not have permission to delete announcements');
                }
                
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid announcement ID');
                }
                
                // Check if user can delete this announcement
                $announcement = $announcementsModel->getById($id);
                if (!$announcement) {
                    throw new Exception('Announcement not found');
                }
                
                $result = $announcementsModel->delete($id);
                echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully', 'data' => $result]);
                break;
                
            case 'get_statistics':
                $result = $announcementsModel->getStatistics();
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_count_by_creator':
                $result = $announcementsModel->getCountByCreator();
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_monthly_count':
                $months = (int)($_POST['months'] ?? 12);
                $result = $announcementsModel->getMonthlyCount($months);
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'list':
                $announcements = $announcementsModel->getAll();
                echo json_encode(['success' => true, 'data' => $announcements]);
                break;
                
            case 'datatable':
                $announcements = $announcementsModel->getAllForDataTable();
                echo json_encode(['data' => $announcements]);
                break;
                
            case 'get_announcement':
                $id = (int)($_GET['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid announcement ID');
                }
                
                $result = $announcementsModel->getById($id);
                if (!$result) {
                    throw new Exception('Announcement not found');
                }
                
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_recent':
                $limit = (int)($_GET['limit'] ?? 5);
                $result = $announcementsModel->getRecent($limit);
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_by_creator':
                $creatorId = (int)($_GET['creator_id'] ?? 0);
                
                if ($creatorId <= 0) {
                    throw new Exception('Invalid creator ID');
                }
                
                $result = $announcementsModel->getByCreator($creatorId);
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'search':
                $searchTerm = trim($_GET['search'] ?? '');
                
                if (empty($searchTerm)) {
                    throw new Exception('Search term is required');
                }
                
                $result = $announcementsModel->search($searchTerm);
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_statistics':
                $result = $announcementsModel->getStatistics();
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_count_by_creator':
                $result = $announcementsModel->getCountByCreator();
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'get_monthly_count':
                $months = (int)($_GET['months'] ?? 12);
                $result = $announcementsModel->getMonthlyCount($months);
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
