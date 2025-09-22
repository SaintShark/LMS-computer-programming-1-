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

// Check permissions - only admin and teacher can manage activities
if (!Permission::canManageActivities()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden - Management access required']);
    exit();
}

$userRole = $_SESSION['user']['role'];
$userId = $_SESSION['user']['id'];

header('Content-Type: application/json');

try {
    $pdo = Db::getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'grade_submission':
                $submissionId = (int)($_POST['submission_id'] ?? 0);
                $score = (float)($_POST['score'] ?? 0);
                $maxScore = (float)($_POST['max_score'] ?? 0);
                $comments = trim($_POST['comments'] ?? '');
                
                if ($submissionId <= 0) {
                    throw new Exception('Invalid submission ID');
                }
                
                if ($score < 0 || $maxScore <= 0) {
                    throw new Exception('Invalid score values');
                }
                
                if ($score > $maxScore) {
                    throw new Exception('Score cannot be greater than max score');
                }
                
                // Check if submission exists
                $stmt = $pdo->prepare("
                    SELECT asub.*, a.title as activity_title, s.name as subject_name,
                           CONCAT(u.first_name, ' ', u.last_name) as student_name
                    FROM activity_submissions asub
                    LEFT JOIN activities a ON asub.activity_id = a.id
                    LEFT JOIN subjects s ON a.subject_id = s.id
                    LEFT JOIN students st ON asub.student_id = st.id
                    LEFT JOIN users u ON st.user_id = u.id
                    WHERE asub.id = ?
                ");
                $stmt->execute([$submissionId]);
                $submission = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$submission) {
                    throw new Exception('Submission not found');
                }
                
                // Check if grade already exists
                $stmt = $pdo->prepare("SELECT id FROM activity_grades WHERE submission_id = ?");
                $stmt->execute([$submissionId]);
                $existingGrade = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingGrade) {
                    // Update existing grade
                    $stmt = $pdo->prepare("
                        UPDATE activity_grades 
                        SET score = ?, max_score = ?, comments = ?, graded_by = ?, graded_at = NOW()
                        WHERE submission_id = ?
                    ");
                    $stmt->execute([$score, $maxScore, $comments, $userId, $submissionId]);
                } else {
                    // Create new grade
                    $stmt = $pdo->prepare("
                        INSERT INTO activity_grades (submission_id, score, max_score, comments, graded_by, graded_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$submissionId, $score, $maxScore, $comments, $userId]);
                }
                
                echo json_encode(['success' => true, 'message' => 'Grade submitted successfully']);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'datatable':
                // Handle DataTables server-side processing
                $draw = intval($_GET['draw'] ?? 1);
                $start = intval($_GET['start'] ?? 0);
                $length = intval($_GET['length'] ?? 10);
                $searchValue = $_GET['search']['value'] ?? '';
                $orderColumn = intval($_GET['order'][0]['column'] ?? 0);
                $orderDir = $_GET['order'][0]['dir'] ?? 'desc';
                
                // Get filters if provided
                $gradingPeriodFilter = $_GET['grading_period'] ?? '';
                $statusFilter = $_GET['status'] ?? '';
                $studentNameFilter = $_GET['student_name'] ?? '';
                
                error_log("Teacher Activities API - Grading Period Filter: " . $gradingPeriodFilter);
                error_log("Teacher Activities API - Status Filter: " . $statusFilter);
                
                // Column mapping
                $columns = [
                    0 => 'student_name',
                    1 => 'activity_title',
                    2 => 'subject_name', 
                    3 => 'grading_period_name',
                    4 => 'status',
                    5 => 'grade',
                    6 => 'actions'
                ];
                
                $orderBy = $columns[$orderColumn] ?? 'asub.submitted_at';
                
                // Base query - ensure all joins work with sample data
                $baseQuery = "
                    FROM activity_submissions asub
                    INNER JOIN activities a ON asub.activity_id = a.id
                    INNER JOIN subjects s ON a.subject_id = s.id
                    INNER JOIN grading_periods gp ON a.grading_period_id = gp.id
                    INNER JOIN students st ON asub.student_id = st.id
                    INNER JOIN users u ON st.user_id = u.id
                    LEFT JOIN activity_grades ag ON asub.id = ag.submission_id
                ";
                
                // Build WHERE clause
                $whereClause = "WHERE 1=1";
                $params = [];
                
                // Apply filters if specified
                if (!empty($gradingPeriodFilter) && $gradingPeriodFilter !== 'all' && $gradingPeriodFilter !== '') {
                    $whereClause .= " AND gp.name = ?";
                    $params[] = $gradingPeriodFilter;
                    error_log("Teacher Activities API - Applied grading period filter: " . $gradingPeriodFilter);
                }
                if (!empty($statusFilter) && $statusFilter !== 'all' && $statusFilter !== '') {
                    $whereClause .= " AND asub.status = ?";
                    $params[] = $statusFilter;
                    error_log("Teacher Activities API - Applied status filter: " . $statusFilter);
                }
                if ($studentNameFilter && !empty($studentNameFilter)) {
                    $whereClause .= " AND CONCAT(u.first_name, ' ', u.last_name) = ?";
                    $params[] = $studentNameFilter;
                }
                
                // Add search filter
                if (!empty($searchValue)) {
                    $whereClause .= " AND (
                        CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR
                        a.title LIKE ? OR
                        s.name LIKE ? OR
                        gp.name LIKE ?
                    )";
                    $searchParam = "%{$searchValue}%";
                    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
                }
                
                // Get filtered count
                $countQuery = "SELECT COUNT(*) as count " . $baseQuery . " " . $whereClause;
                $countStmt = $pdo->prepare($countQuery);
                $countStmt->execute($params);
                $filteredRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                // Get total count (with filters but without search)
                $totalCountWhereClause = "WHERE 1=1";
                $totalCountParams = [];
                
                // Add same filters as main query (except search)
                if (!empty($gradingPeriodFilter) && $gradingPeriodFilter !== 'all' && $gradingPeriodFilter !== '') {
                    $totalCountWhereClause .= " AND gp.name = ?";
                    $totalCountParams[] = $gradingPeriodFilter;
                }
                if (!empty($statusFilter) && $statusFilter !== 'all' && $statusFilter !== '') {
                    $totalCountWhereClause .= " AND asub.status = ?";
                    $totalCountParams[] = $statusFilter;
                }
                if ($studentNameFilter && !empty($studentNameFilter)) {
                    $totalCountWhereClause .= " AND CONCAT(u.first_name, ' ', u.last_name) = ?";
                    $totalCountParams[] = $studentNameFilter;
                }
                
                $totalCountQuery = "SELECT COUNT(*) as count " . $baseQuery . " " . $totalCountWhereClause;
                $totalCountStmt = $pdo->prepare($totalCountQuery);
                $totalCountStmt->execute($totalCountParams);
                $totalRecords = $totalCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                // Get paginated data
                $dataQuery = "
                    SELECT 
                        asub.id,
                        asub.submission_link,
                        asub.submission_text,
                        asub.file_path,
                        asub.status,
                        asub.submitted_at,
                        asub.updated_at,
                        a.title as activity_title,
                        a.description as activity_description,
                        s.name as subject_name,
                        gp.name as grading_period_name,
                        CONCAT(u.first_name, ' ', u.last_name) as student_name,
                        st.course,
                        st.year_level,
                        ag.score,
                        ag.max_score,
                        ag.comments,
                        CASE 
                            WHEN ag.score IS NOT NULL AND ag.max_score IS NOT NULL 
                            THEN CONCAT(ag.score, '/', ag.max_score)
                            ELSE 'Not Graded'
                        END as grade
                    " . $baseQuery . " " . $whereClause . "
                    ORDER BY {$orderBy} {$orderDir}
                    LIMIT {$start}, {$length}
                ";
                
                $dataStmt = $pdo->prepare($dataQuery);
                $dataStmt->execute($params);
                $submissions = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Format data for DataTables
                $formattedData = [];
                foreach ($submissions as $submission) {
                    $formattedData[] = [
                        'student_name' => htmlspecialchars($submission['student_name']),
                        'activity_title' => htmlspecialchars($submission['activity_title']),
                        'subject_name' => htmlspecialchars($submission['subject_name']),
                        'grading_period_name' => htmlspecialchars($submission['grading_period_name']),
                        'status' => $submission['status'],
                        'grade' => $submission['grade'],
                        'actions' => $submission['id']
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
                
            case 'get_submission':
                $id = (int)($_GET['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid submission ID');
                }
                
                $stmt = $pdo->prepare("
                    SELECT 
                        asub.*,
                        a.title as activity_title,
                        a.description as activity_description,
                        s.name as subject_name,
                        gp.name as grading_period_name,
                        CONCAT(u.first_name, ' ', u.last_name) as student_name,
                        st.course,
                        st.year_level,
                        ag.score,
                        ag.max_score,
                        ag.comments,
                        CASE 
                            WHEN ag.score IS NOT NULL AND ag.max_score IS NOT NULL 
                            THEN CONCAT(ag.score, '/', ag.max_score)
                            ELSE 'Not Graded'
                        END as grade
                    FROM activity_submissions asub
                    LEFT JOIN activities a ON asub.activity_id = a.id
                    LEFT JOIN subjects s ON a.subject_id = s.id
                    LEFT JOIN grading_periods gp ON a.grading_period_id = gp.id
                    LEFT JOIN students st ON asub.student_id = st.id
                    LEFT JOIN users u ON st.user_id = u.id
                    LEFT JOIN activity_grades ag ON asub.id = ag.submission_id
                    WHERE asub.id = ?
                ");
                $stmt->execute([$id]);
                $submission = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$submission) {
                    throw new Exception('Submission not found');
                }
                
                echo json_encode(['success' => true, 'data' => $submission]);
                break;
                
            case 'student_activities':
                // Get student activities with their submission status
                $studentNameFilter = $_GET['student_name'] ?? '';
                $periodFilter = $_GET['period'] ?? '';
                
                if (empty($studentNameFilter)) {
                    throw new Exception('Student name is required');
                }
                
                // First, get the student ID from the name
                $stmt = $pdo->prepare("
                    SELECT st.id as student_id 
                    FROM students st 
                    LEFT JOIN users u ON st.user_id = u.id 
                    WHERE CONCAT(u.first_name, ' ', u.last_name) = ?
                ");
                $stmt->execute([$studentNameFilter]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$student) {
                    throw new Exception('Student not found');
                }
                
                $studentId = $student['student_id'];
                
                $sql = "
                    SELECT 
                        a.id,
                        a.title as activity_title,
                        a.activity_file,
                        a.due_date,
                        a.cutoff_date,
                        s.name as subject_name,
                        gp.name as grading_period_name,
                        asub.submission_link,
                        asub.submission_text,
                        asub.file_path,
                        asub.status as submission_status,
                        asub.submitted_at,
                        ag.score,
                        ag.max_score,
                        CASE 
                            WHEN ag.score IS NOT NULL AND ag.max_score IS NOT NULL 
                            THEN CONCAT(ag.score, '/', ag.max_score)
                            ELSE 'Not Graded'
                        END as grade
                    FROM activities a
                    LEFT JOIN subjects s ON a.subject_id = s.id
                    LEFT JOIN grading_periods gp ON a.grading_period_id = gp.id
                    LEFT JOIN activity_submissions asub ON a.id = asub.activity_id AND asub.student_id = ?
                    LEFT JOIN activity_grades ag ON asub.id = ag.submission_id
                    WHERE a.subject_id = 1
                ";
                
                $params = [$studentId];
                
                // Apply grading period filter if specified
                if ($periodFilter && !empty($periodFilter)) {
                    $sql .= " AND LOWER(gp.name) LIKE ?";
                    $params[] = '%' . strtolower($periodFilter) . '%';
                }
                
                $sql .= " ORDER BY a.due_date DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $data = [];
                foreach ($activities as $activity) {
                    $data[] = [
                        'activity_title' => $activity['activity_title'],
                        'file_path' => $activity['file_path'],
                        'submission_link' => $activity['submission_link'],
                        'submission_text' => $activity['submission_text'],
                        'submitted_at' => $activity['submitted_at'],
                        'grade' => $activity['grade'],
                        'actions' => $activity['id']
                    ];
                }
                
                echo json_encode(['data' => $data]);
                break;
                
            case 'export':
                // Get all submissions for export
                $sql = "
                    SELECT 
                        asub.id,
                        asub.submission_link,
                        asub.submission_text,
                        asub.file_path,
                        asub.status,
                        asub.submitted_at,
                        a.title as activity_title,
                        s.name as subject_name,
                        gp.name as grading_period_name,
                        CONCAT(u.first_name, ' ', u.last_name) as student_name,
                        st.course,
                        st.year_level,
                        ag.score,
                        ag.max_score,
                        ag.comments,
                        CASE 
                            WHEN ag.score IS NOT NULL AND ag.max_score IS NOT NULL 
                            THEN CONCAT(ag.score, '/', ag.max_score)
                            ELSE 'Not Graded'
                        END as grade
                    FROM activity_submissions asub
                    LEFT JOIN activities a ON asub.activity_id = a.id
                    LEFT JOIN subjects s ON a.subject_id = s.id
                    LEFT JOIN grading_periods gp ON a.grading_period_id = gp.id
                    LEFT JOIN students st ON asub.student_id = st.id
                    LEFT JOIN users u ON st.user_id = u.id
                    LEFT JOIN activity_grades ag ON asub.id = ag.submission_id
                    WHERE asub.status = 'submitted'
                ";
                
                // For now, show all submissions since activities table doesn't have created_by
                // TODO: Add created_by column to activities table for proper teacher filtering
                $params = [];
                
                $sql .= " ORDER BY asub.submitted_at DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $submissions]);
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
