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
require_once __DIR__ . '/../Students.php';
require_once __DIR__ . '/../Permissions.php';

// Check if user is student
if (!Permission::isStudent()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied - Students only']);
    exit();
}

$userId = $_SESSION['user']['id'];

header('Content-Type: application/json');

try {
    $pdo = Db::getConnection();
    $activitiesModel = new Activities($pdo);
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
        $action = $_GET['action'] ?? 'datatable'; // Default to datatable if no action specified
        
        switch ($action) {
            case 'datatable':
                // Get period filter if provided
                $periodFilter = $_GET['period'] ?? '';
                
                // Get activities for the student's subject (Computer Programming 1)
                $activities = $activitiesModel->getAll();
                
                // Filter activities for the student's subject
                $studentActivities = [];
                foreach ($activities as $activity) {
                    // Only show activities for Computer Programming 1 (subject_id = 1)
                    if ($activity['subject_id'] == 1) {
                        // Apply grading period filter if specified
                        if ($periodFilter && !empty($periodFilter)) {
                            $gradingPeriodName = strtolower($activity['grading_period_name']);
                            if (strpos($gradingPeriodName, strtolower($periodFilter)) === false) {
                                continue; // Skip this activity if it doesn't match the period filter
                            }
                        }
                        // Check if student has submitted this activity and get grade
                        $submissionStmt = $pdo->prepare("
                            SELECT 
                                asub.status, 
                                asub.submission_link, 
                                asub.submission_text, 
                                asub.file_path, 
                                asub.submitted_at,
                                ag.score,
                                ag.max_score,
                                CASE 
                                    WHEN ag.score IS NOT NULL AND ag.max_score IS NOT NULL 
                                    THEN CONCAT(ag.score, '/', ag.max_score)
                                    ELSE NULL
                                END as grade
                            FROM activity_submissions asub
                            LEFT JOIN activity_grades ag ON asub.id = ag.submission_id
                            WHERE asub.activity_id = ? AND asub.student_id = ? 
                            ORDER BY asub.submitted_at DESC 
                            LIMIT 1
                        ");
                        $submissionStmt->execute([$activity['id'], $studentId]);
                        $submission = $submissionStmt->fetch(PDO::FETCH_ASSOC);
                        
                        $studentActivities[] = [
                            'id' => $activity['id'],
                            'title' => $activity['title'],
                            'subject_name' => $activity['subject_name'],
                            'description' => $activity['description'],
                            'activity_file' => $activity['activity_file'],
                            'due_date' => $activity['due_date'],
                            'cutoff_date' => $activity['cutoff_date'],
                            'deduction_percent' => $activity['deduction_percent'],
                            'status' => $activity['status'],
                            'grading_period_name' => $activity['grading_period_name'],
                            'submission_status' => $submission ? $submission['status'] : null,
                            'submission_link' => $submission ? $submission['submission_link'] : null,
                            'submission_text' => $submission ? $submission['submission_text'] : null,
                            'file_path' => $submission ? $submission['file_path'] : null,
                            'submitted_at' => $submission ? $submission['submitted_at'] : null,
                            'grade' => $submission ? $submission['grade'] : null
                        ];
                    }
                }
                
                // Return DataTables format
                echo json_encode([
                    'draw' => intval($_GET['draw'] ?? 1),
                    'recordsTotal' => count($studentActivities),
                    'recordsFiltered' => count($studentActivities),
                    'data' => $studentActivities
                ]);
                break;
                
            case 'get_activity':
                $id = (int)($_GET['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid activity ID');
                }
                
                $activity = $activitiesModel->getById($id);
                if (!$activity) {
                    throw new Exception('Activity not found');
                }
                
                // Check if activity belongs to student's subject
                if ($activity['subject_id'] != 1) {
                    throw new Exception('Access denied - Activity not available');
                }
                
                // Get student submission if exists
                $submissionStmt = $pdo->prepare("
                    SELECT status, submission_link, submission_text, file_path, submitted_at 
                    FROM activity_submissions 
                    WHERE activity_id = ? AND student_id = ? 
                    ORDER BY submitted_at DESC 
                    LIMIT 1
                ");
                $submissionStmt->execute([$id, $studentId]);
                $submission = $submissionStmt->fetch(PDO::FETCH_ASSOC);
                
                // Add submission data to activity
                $activity['submission_status'] = $submission ? $submission['status'] : null;
                $activity['submission_link'] = $submission ? $submission['submission_link'] : null;
                $activity['submission_text'] = $submission ? $submission['submission_text'] : null;
                $activity['file_path'] = $submission ? $submission['file_path'] : null;
                $activity['submitted_at'] = $submission ? $submission['submitted_at'] : null;
                
                echo json_encode(['success' => true, 'data' => $activity]);
                break;
                
            case 'get_my_activities':
                // Get all activities for the student
                $activities = $activitiesModel->getAll();
                $studentActivities = [];
                
                foreach ($activities as $activity) {
                    if ($activity['subject_id'] == 1) { // Computer Programming 1
                        $studentActivities[] = $activity;
                    }
                }
                
                echo json_encode(['success' => true, 'data' => $studentActivities]);
                break;
                
                
            default:
                throw new Exception('Invalid action');
        }
        
    } else {
        // Handle POST requests or other methods
        $action = $_POST['action'] ?? $_GET['action'] ?? 'datatable';
        
        switch ($action) {
            case 'datatable':
                // Get activities for the student's subject (Computer Programming 1)
                $activities = $activitiesModel->getAll();
                
                // Filter activities for the student's subject
                $studentActivities = [];
                foreach ($activities as $activity) {
                    // Only show activities for Computer Programming 1 (subject_id = 1)
                    if ($activity['subject_id'] == 1) {
                        // Check if student has submitted this activity and get grade
                        $submissionStmt = $pdo->prepare("
                            SELECT 
                                asub.status, 
                                asub.submission_link, 
                                asub.submission_text, 
                                asub.file_path, 
                                asub.submitted_at,
                                ag.score,
                                ag.max_score,
                                CASE 
                                    WHEN ag.score IS NOT NULL AND ag.max_score IS NOT NULL 
                                    THEN CONCAT(ag.score, '/', ag.max_score)
                                    ELSE NULL
                                END as grade
                            FROM activity_submissions asub
                            LEFT JOIN activity_grades ag ON asub.id = ag.submission_id
                            WHERE asub.activity_id = ? AND asub.student_id = ? 
                            ORDER BY asub.submitted_at DESC 
                            LIMIT 1
                        ");
                        $submissionStmt->execute([$activity['id'], $studentId]);
                        $submission = $submissionStmt->fetch(PDO::FETCH_ASSOC);
                        
                        $studentActivities[] = [
                            'id' => $activity['id'],
                            'title' => $activity['title'],
                            'subject_name' => $activity['subject_name'],
                            'description' => $activity['description'],
                            'activity_file' => $activity['activity_file'],
                            'due_date' => $activity['due_date'],
                            'cutoff_date' => $activity['cutoff_date'],
                            'deduction_percent' => $activity['deduction_percent'],
                            'status' => $activity['status'],
                            'grading_period_name' => $activity['grading_period_name'],
                            'submission_status' => $submission ? $submission['status'] : null,
                            'submission_link' => $submission ? $submission['submission_link'] : null,
                            'submission_text' => $submission ? $submission['submission_text'] : null,
                            'file_path' => $submission ? $submission['file_path'] : null,
                            'submitted_at' => $submission ? $submission['submitted_at'] : null,
                            'grade' => $submission ? $submission['grade'] : null
                        ];
                    }
                }
                
                // Return DataTables format
                echo json_encode([
                    'draw' => intval($_GET['draw'] ?? 1),
                    'recordsTotal' => count($studentActivities),
                    'recordsFiltered' => count($studentActivities),
                    'data' => $studentActivities
                ]);
                break;
                
            case 'get_activity':
                $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid activity ID');
                }
                
                $activity = $activitiesModel->getById($id);
                if (!$activity) {
                    throw new Exception('Activity not found');
                }
                
                // Check if activity belongs to student's subject
                if ($activity['subject_id'] != 1) {
                    throw new Exception('Access denied - Activity not available');
                }
                
                // Get submission information for this activity
                $submissionStmt = $pdo->prepare("
                    SELECT status, submission_link, submission_text, submitted_at 
                    FROM activity_submissions 
                    WHERE activity_id = ? AND student_id = ? 
                    ORDER BY submitted_at DESC 
                    LIMIT 1
                ");
                $submissionStmt->execute([$id, $studentId]);
                $submission = $submissionStmt->fetch(PDO::FETCH_ASSOC);
                
                // Add submission data to activity
                $activity['submission_status'] = $submission ? $submission['status'] : null;
                $activity['submission_link'] = $submission ? $submission['submission_link'] : null;
                $activity['submission_text'] = $submission ? $submission['submission_text'] : null;
                $activity['submitted_at'] = $submission ? $submission['submitted_at'] : null;
                
                echo json_encode(['success' => true, 'data' => $activity]);
                break;
                
            case 'submit_activity':
                $activityId = (int)($_POST['activity_id'] ?? 0);
                $submissionLink = $_POST['submission_link'] ?? '';
                $submissionText = $_POST['submission_text'] ?? '';
                
                if ($activityId <= 0) {
                    throw new Exception('Invalid activity ID');
                }
                
                // Check if at least one submission method is provided
                $hasLink = !empty($submissionLink);
                $hasFile = isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK;
                
                if (!$hasLink && !$hasFile) {
                    throw new Exception('Please provide either a submission link or upload a file');
                }
                
                // Validate URL if provided
                if ($hasLink && !filter_var($submissionLink, FILTER_VALIDATE_URL)) {
                    throw new Exception('Invalid submission link format');
                }
                
                // Handle file upload if provided
                $submissionFilePath = null;
                if ($hasFile) {
                    $submissionFilePath = handleStudentFileUpload($_FILES['submission_file']);
                }
                
                // Check if activity exists and belongs to student's subject
                $activityStmt = $pdo->prepare("SELECT * FROM activities WHERE id = ? AND subject_id = 1");
                $activityStmt->execute([$activityId]);
                $activity = $activityStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$activity) {
                    throw new Exception('Activity not found or not available');
                }
                
                // Check if submission period has ended
                $cutoffDate = new DateTime($activity['cutoff_date']);
                $currentDate = new DateTime();
                
                if ($currentDate > $cutoffDate) {
                    throw new Exception('Submission period has ended. Cannot submit after cutoff date.');
                }
                
                // Check if submission already exists
                $existingStmt = $pdo->prepare("SELECT id FROM activity_submissions WHERE activity_id = ? AND student_id = ?");
                $existingStmt->execute([$activityId, $studentId]);
                $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    // Update existing submission
                    $updateStmt = $pdo->prepare("
                        UPDATE activity_submissions 
                        SET submission_link = ?, submission_text = ?, file_path = ?, status = 'submitted', updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$submissionLink, $submissionText, $submissionFilePath, $existing['id']]);
                } else {
                    // Create new submission
                    $insertStmt = $pdo->prepare("
                        INSERT INTO activity_submissions (activity_id, student_id, submission_link, submission_text, file_path, status) 
                        VALUES (?, ?, ?, ?, ?, 'submitted')
                    ");
                    $insertStmt->execute([$activityId, $studentId, $submissionLink, $submissionText, $submissionFilePath]);
                }
                
                echo json_encode(['success' => true, 'message' => 'Activity submitted successfully']);
                break;
                
            case 'unsubmit_activity':
                $activityId = (int)($_POST['activity_id'] ?? 0);
                
                if ($activityId <= 0) {
                    throw new Exception('Invalid activity ID');
                }
                
                // Check if activity exists and get cutoff date
                $activityStmt = $pdo->prepare("SELECT cutoff_date FROM activities WHERE id = ? AND subject_id = 1");
                $activityStmt->execute([$activityId]);
                $activity = $activityStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$activity) {
                    throw new Exception('Activity not found or not available');
                }
                
                // Check if submission period has ended
                $cutoffDate = new DateTime($activity['cutoff_date']);
                $currentDate = new DateTime();
                
                if ($currentDate > $cutoffDate) {
                    throw new Exception('Submission period has ended. Cannot unsubmit after cutoff date.');
                }
                
                // Update submission status to unsubmitted
                $updateStmt = $pdo->prepare("
                    UPDATE activity_submissions 
                    SET status = 'unsubmitted', updated_at = CURRENT_TIMESTAMP 
                    WHERE activity_id = ? AND student_id = ?
                ");
                $updateStmt->execute([$activityId, $studentId]);
                
                if ($updateStmt->rowCount() === 0) {
                    throw new Exception('No submission found to unsubmit');
                }
                
                echo json_encode(['success' => true, 'message' => 'Activity unsubmitted successfully']);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Handle file upload for student submissions
 */
function handleStudentFileUpload($file) {
    // Create uploads directory if it doesn't exist
    $uploadDir = __DIR__ . '/../../uploads/activities/student/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validate file
    $allowedTypes = ['c', 'cpp', 'h', 'txt', 'pdf', 'doc', 'docx', 'zip', 'rar'];
    $maxSize = 10 * 1024 * 1024; // 10MB
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $allowedTypes));
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('File size too large. Maximum size: 10MB');
    }
    
    // Get student surname from session
    $user = $_SESSION['user'];
    $surname = strtoupper($user['last_name']);
    
    // Generate filename with SURNAME_DATETIME format
    $currentDateTime = date('Y-m-d_H-i-s');
    $uniqueFileName = $surname . '_' . $currentDateTime . '.' . $fileExtension;
    $uploadPath = $uploadDir . $uniqueFileName;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to upload file');
    }
    
    return $uniqueFileName;
}
?>
