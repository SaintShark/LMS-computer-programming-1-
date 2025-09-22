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

// Check if user can manage quizzes
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
                $orderColumn = intval($_GET['order'][0]['column'] ?? 7); // Default to submitted_at
                $orderDir = $_GET['order'][0]['dir'] ?? 'desc';
                
                // Column mapping
                $columns = [
                    0 => 'student_name',
                    1 => 'quiz_title', 
                    2 => 'subject_name',
                    3 => 'grading_period',
                    4 => 'attempt_number',
                    5 => 'score',
                    6 => 'status',
                    7 => 'submitted_at',
                    8 => 'actions'
                ];
                
                $orderBy = $columns[$orderColumn] ?? 'qa.finished_at';
                
                // Base query
                $baseQuery = "
                    FROM quiz_attempts qa
                    INNER JOIN quizzes q ON qa.quiz_id = q.id
                    INNER JOIN lessons l ON q.lesson_id = l.id
                    INNER JOIN subjects s ON l.subject_id = s.id
                    INNER JOIN grading_periods gp ON q.grading_period_id = gp.id
                    INNER JOIN students st ON qa.student_id = st.id
                    INNER JOIN users u ON st.user_id = u.id
                ";
                
                // Base where clause - no teacher filtering needed for single-teacher system
                $whereClause = "WHERE 1=1";
                $params = [];
                
                // Add grading period filter
                $gradingPeriodFilter = $_GET['grading_period'] ?? '';
                error_log("Quiz Submissions API - Grading Period Filter: " . $gradingPeriodFilter);
                if (!empty($gradingPeriodFilter) && $gradingPeriodFilter !== 'all' && $gradingPeriodFilter !== '') {
                    $whereClause .= " AND gp.name = ?";
                    $params[] = $gradingPeriodFilter;
                    error_log("Quiz Submissions API - Applied grading period filter: " . $gradingPeriodFilter);
                }
                
                // Add status filter
                $statusFilter = $_GET['status'] ?? '';
                error_log("Quiz Submissions API - Status Filter: " . $statusFilter);
                if (!empty($statusFilter) && $statusFilter !== 'all' && $statusFilter !== '') {
                    $whereClause .= " AND qa.status = ?";
                    $params[] = $statusFilter;
                    error_log("Quiz Submissions API - Applied status filter: " . $statusFilter);
                }
                
                // Add search filter
                if (!empty($searchValue)) {
                    $whereClause .= " AND (
                        CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR
                        q.title LIKE ? OR
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
                    $totalCountWhereClause .= " AND qa.status = ?";
                    $totalCountParams[] = $statusFilter;
                }
                
                $totalCountQuery = "SELECT COUNT(*) as count " . $baseQuery . " " . $totalCountWhereClause;
                $totalCountStmt = $pdo->prepare($totalCountQuery);
                $totalCountStmt->execute($totalCountParams);
                $totalRecords = $totalCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                // Get paginated data
                $dataQuery = "
                    SELECT 
                        qa.id,
                        qa.quiz_id,
                        qa.student_id,
                        qa.attempt_number,
                        qa.score,
                        qa.max_score,
                        qa.status,
                        qa.started_at,
                        qa.finished_at as submitted_at,
                        CASE 
                            WHEN qa.finished_at IS NOT NULL AND qa.started_at IS NOT NULL 
                            THEN TIMESTAMPDIFF(SECOND, qa.started_at, qa.finished_at)
                            ELSE NULL 
                        END as time_spent_seconds,
                        q.title as quiz_title,
                        q.max_score as quiz_max_score,
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
                    switch ($submission['status']) {
                        case 'submitted':
                            $statusBadge = '<span class="badge bg-success">Submitted</span>';
                            break;
                        case 'in_progress':
                            $statusBadge = '<span class="badge bg-warning">In Progress</span>';
                            break;
                        case 'timeout':
                            $statusBadge = '<span class="badge bg-danger">Timeout</span>';
                            break;
                        default:
                            $statusBadge = '<span class="badge bg-light text-dark">' . ucfirst($submission['status']) . '</span>';
                    }
                    
                    $submittedAt = $submission['submitted_at'] ? 
                        date('M d, Y H:i', strtotime($submission['submitted_at'])) : 'Not finished';
                    
                    $formattedData[] = [
                        'student_name' => htmlspecialchars($submission['student_name']),
                        'quiz_title' => htmlspecialchars($submission['quiz_title']),
                        'subject_name' => htmlspecialchars($submission['subject_name']),
                        'grading_period' => htmlspecialchars($submission['grading_period']),
                        'attempt_number' => $submission['attempt_number'],
                        'score' => $submission['score'] . '/' . $submission['max_score'] . ' (' . $percentage . '%)',
                        'status' => $statusBadge,
                        'submitted_at' => $submittedAt,
                        'actions' => '<div class="btn-group gap-2" role="group">' .
                            '<button class="btn btn-outline-primary" onclick="viewQuizSubmissionDetails(' . $submission['id'] . ')" title="View Details">' .
                                '<i class="bi bi-eye"></i>' .
                            '</button>' .
                            ($submission['status'] === 'submitted' ? 
                                '<button class="btn btn-outline-success" onclick="gradeQuizSubmission(' . $submission['id'] . ')" title="Add Comments">' .
                                    '<i class="bi bi-chat-left-text"></i>' .
                                '</button>' : '') .
                            '</div>'
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
                        qa.*,
                        CASE 
                            WHEN qa.finished_at IS NOT NULL AND qa.started_at IS NOT NULL 
                            THEN TIMESTAMPDIFF(SECOND, qa.started_at, qa.finished_at)
                            ELSE NULL 
                        END as time_spent_seconds,
                        q.title as quiz_title,
                        q.description as quiz_description,
                        q.time_limit_minutes,
                        s.name as subject_name,
                        gp.name as grading_period,
                        CONCAT(u.first_name, ' ', u.last_name) as student_name,
                        u.email as student_email
                    FROM quiz_attempts qa
                    INNER JOIN quizzes q ON qa.quiz_id = q.id
                    INNER JOIN lessons l ON q.lesson_id = l.id
                    INNER JOIN subjects s ON l.subject_id = s.id
                    INNER JOIN grading_periods gp ON q.grading_period_id = gp.id
                    INNER JOIN students st ON qa.student_id = st.id
                    INNER JOIN users u ON st.user_id = u.id
                    WHERE qa.id = ?
                ";
                
                // Execute query (no teacher filtering needed for single-teacher system)
                $stmt = $pdo->prepare($query);
                $stmt->execute([$submissionId]);
                
                $submission = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$submission) {
                    throw new Exception('Submission not found');
                }
                
                // Get quiz answers
                $answersQuery = "
                    SELECT 
                        qa.*,
                        qq.question_text,
                        qq.question_type,
                        qq.score as question_score
                    FROM quiz_answers qa
                    INNER JOIN quiz_questions qq ON qa.question_id = qq.id
                    WHERE qa.attempt_id = ?
                    ORDER BY qq.id
                ";
                $answersStmt = $pdo->prepare($answersQuery);
                $answersStmt->execute([$submissionId]);
                $answers = $answersStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get question options for multiple choice questions
                foreach ($answers as &$answer) {
                    if ($answer['question_type'] === 'multiple_choice') {
                        $optionsQuery = "
                            SELECT * FROM quiz_choices 
                            WHERE question_id = ? 
                            ORDER BY id
                        ";
                        $optionsStmt = $pdo->prepare($optionsQuery);
                        $optionsStmt->execute([$answer['question_id']]);
                        $answer['options'] = $optionsStmt->fetchAll(PDO::FETCH_ASSOC);
                    }
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
            case 'grade_quiz_submission':
                // For now, just return success since the database doesn't have teacher_comments field
                // This can be extended in the future when the database schema is updated
                echo json_encode(['success' => true, 'message' => 'Quiz submission reviewed successfully']);
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
