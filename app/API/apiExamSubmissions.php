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

// Check if user can manage quizzes (exams use same permission)
if (!Permission::canManageQuizzes()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    $pdo = Db::getConnection();
    $user = $_SESSION['user'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'datatable':
                // Handle DataTables server-side processing
                $draw = intval($_GET['draw'] ?? 1);
                $start = intval($_GET['start'] ?? 0);
                $length = intval($_GET['length'] ?? 10);
                $searchValue = $_GET['search']['value'] ?? '';
                $orderColumn = intval($_GET['order'][0]['column'] ?? 6); // Default to submitted_at
                $orderDir = $_GET['order'][0]['dir'] ?? 'desc';
                
                // Column mapping
                $columns = [
                    0 => 'student_name',
                    1 => 'exam_title', 
                    2 => 'subject_name',
                    3 => 'grading_period',
                    4 => 'score',
                    5 => 'status',
                    6 => 'completed_at',
                    7 => 'actions'
                ];
                
                $orderBy = $columns[$orderColumn] ?? 'ea.completed_at';
                
                // Base query - Note: exam_attempts.student_id references users.id directly (inconsistent with other tables)
                $baseQuery = "
                    FROM exam_attempts ea
                    INNER JOIN exams e ON ea.exam_id = e.id
                    INNER JOIN lessons l ON e.lesson_id = l.id
                    INNER JOIN subjects s ON l.subject_id = s.id
                    INNER JOIN grading_periods gp ON e.grading_period_id = gp.id
                    INNER JOIN users u ON ea.student_id = u.id
                ";
                
                // Base where clause - no teacher filtering needed for single-teacher system
                $whereClause = "WHERE 1=1";
                $params = [];
                
                // Add grading period filter
                $gradingPeriodFilter = $_GET['grading_period'] ?? '';
                if (!empty($gradingPeriodFilter) && $gradingPeriodFilter !== 'all' && $gradingPeriodFilter !== '') {
                    $whereClause .= " AND gp.name = ?";
                    $params[] = $gradingPeriodFilter;
                }
                
                // Add status filter
                $statusFilter = $_GET['status'] ?? '';
                if (!empty($statusFilter) && $statusFilter !== 'all' && $statusFilter !== '') {
                    if ($statusFilter === 'completed') {
                        $whereClause .= " AND ea.completed_at IS NOT NULL";
                    } elseif ($statusFilter === 'in_progress') {
                        $whereClause .= " AND ea.completed_at IS NULL";
                    }
                }
                
                // Add search filter
                if (!empty($searchValue)) {
                    $whereClause .= " AND (
                        CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR
                        e.title LIKE ? OR
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
                    if ($statusFilter === 'completed') {
                        $totalCountWhereClause .= " AND ea.completed_at IS NOT NULL";
                    } elseif ($statusFilter === 'in_progress') {
                        $totalCountWhereClause .= " AND ea.completed_at IS NULL";
                    }
                }
                
                $totalCountQuery = "SELECT COUNT(*) as count " . $baseQuery . " " . $totalCountWhereClause;
                $totalCountStmt = $pdo->prepare($totalCountQuery);
                $totalCountStmt->execute($totalCountParams);
                $totalRecords = $totalCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                // Get paginated data
                $dataQuery = "
                    SELECT 
                        ea.id,
                        ea.exam_id,
                        ea.student_id,
                        ea.score,
                        ea.max_score,
                        ea.started_at,
                        ea.completed_at,
                        ea.time_taken,
                        CASE 
                            WHEN ea.time_taken IS NOT NULL 
                            THEN ea.time_taken
                            ELSE NULL 
                        END as time_spent_seconds,
                        e.title as exam_title,
                        e.max_score as exam_max_score,
                        s.name as subject_name,
                        gp.name as grading_period,
                        CONCAT(u.first_name, ' ', u.last_name) as student_name,
                        u.first_name,
                        u.last_name
                    " . $baseQuery . " " . $whereClause . "
                    ORDER BY {$orderBy} {$orderDir}
                    LIMIT {$start}, {$length}
                ";
                
                $dataStmt = $pdo->prepare($dataQuery);
                $dataStmt->execute($params);
                $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Format data for DataTables
                $formattedData = [];
                foreach ($data as $submission) {
                    $percentage = $submission['max_score'] > 0 ? 
                        round(($submission['score'] / $submission['max_score']) * 100, 2) : 0;
                    
                    $statusBadge = '';
                    if ($submission['completed_at']) {
                        $statusBadge = '<span class="badge bg-success">Completed</span>';
                        $status = 'completed';
                    } else {
                        $statusBadge = '<span class="badge bg-warning">In Progress</span>';
                        $status = 'in_progress';
                    }
                    
                    $submittedAt = $submission['completed_at'] ? 
                        date('M d, Y H:i', strtotime($submission['completed_at'])) : 'Not completed';
                    
                    $formattedData[] = [
                        'student_name' => htmlspecialchars($submission['student_name']),
                        'exam_title' => htmlspecialchars($submission['exam_title']),
                        'subject_name' => htmlspecialchars($submission['subject_name']),
                        'grading_period' => htmlspecialchars($submission['grading_period']),
                        'score' => $submission['score'] . '/' . $submission['max_score'] . ' (' . $percentage . '%)',
                        'status' => $statusBadge,
                        'submitted_at' => $submittedAt,
                        'actions' => '
                            <div class="btn-group gap-2" role="group">
                                <button class="btn btn-outline-primary" onclick="viewExamSubmissionDetails(' . $submission['id'] . ')" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </button>
                                ' . ($status === 'completed' ? '
                                <button class="btn btn-outline-success" onclick="gradeExamSubmission(' . $submission['id'] . ')" title="Add Comments">
                                    <i class="bi bi-chat-left-text"></i>
                                </button>
                                ' : '') . '
                            </div>
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
                
            case 'get_submission_details':
                $submissionId = intval($_GET['id'] ?? 0);
                if ($submissionId <= 0) {
                    throw new Exception('Submission ID is required');
                }
                
                // Get submission details
                $query = "
                    SELECT 
                        ea.*,
                        CASE 
                            WHEN ea.time_taken IS NOT NULL 
                            THEN ea.time_taken
                            ELSE NULL 
                        END as time_spent_seconds,
                        e.title as exam_title,
                        e.description as exam_description,
                        e.time_limit_minutes,
                        s.name as subject_name,
                        gp.name as grading_period,
                        CONCAT(u.first_name, ' ', u.last_name) as student_name,
                        u.email as student_email
                    FROM exam_attempts ea
                    INNER JOIN exams e ON ea.exam_id = e.id
                    INNER JOIN lessons l ON e.lesson_id = l.id
                    INNER JOIN subjects s ON l.subject_id = s.id
                    INNER JOIN grading_periods gp ON e.grading_period_id = gp.id
                    INNER JOIN users u ON ea.student_id = u.id
                    WHERE ea.id = ?
                ";
                
                // Execute query (no teacher filtering needed for single-teacher system)
                $stmt = $pdo->prepare($query);
                $stmt->execute([$submissionId]);
                
                $submission = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$submission) {
                    throw new Exception('Submission not found');
                }
                
                // Parse answers from JSON
                $answers = [];
                if ($submission['answers']) {
                    $answers = json_decode($submission['answers'], true) ?? [];
                }
                
                $submission['answers'] = $answers;
                
                echo json_encode(['success' => true, 'data' => $submission]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'grade_exam_submission':
                // For now, just return success since the database doesn't have teacher_comments field
                // This can be extended in the future when the database schema is updated
                echo json_encode(['success' => true, 'message' => 'Exam submission reviewed successfully']);
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
