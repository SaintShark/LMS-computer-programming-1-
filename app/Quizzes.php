<?php
class Quizzes {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new quiz
     */
    public function create($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO quizzes (lesson_id, grading_period_id, title, description, max_score, time_limit_minutes, attempts_allowed, display_mode, open_at, close_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['lesson_id'],
            $data['grading_period_id'],
            $data['title'],
            $data['description'] ?? '',
            $data['max_score'],
            $data['time_limit_minutes'] ?? null,
            $data['attempts_allowed'] ?? 1,
            $data['display_mode'] ?? 'all',
            $data['open_at'],
            $data['close_at']
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Get all quizzes
     */
    public function getAll() {
        $stmt = $this->pdo->prepare("
            SELECT 
                q.*,
                l.title as lesson_title,
                l.subject_id,
                s.name as subject_name,
                gp.name as grading_period_name,
                gp.status as grading_period_status,
                COUNT(qr.id) as submission_count
            FROM quizzes q
            LEFT JOIN lessons l ON q.lesson_id = l.id
            LEFT JOIN subjects s ON l.subject_id = s.id
            LEFT JOIN grading_periods gp ON q.grading_period_id = gp.id
            LEFT JOIN quiz_results qr ON q.id = qr.quiz_id
            GROUP BY q.id
            ORDER BY q.created_at DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get quizzes formatted for DataTables
     */
    public function getAllForDataTable() {
        $quizzes = $this->getAll();
        $data = [];
        
        foreach ($quizzes as $quiz) {
            $data[] = [
                'id' => $quiz['id'],
                'title' => htmlspecialchars($quiz['title']),
                'lesson_title' => htmlspecialchars($quiz['lesson_title'] ?? 'No Lesson'),
                'grading_period_name' => htmlspecialchars($quiz['grading_period_name'] ?? 'No Period'),
                'max_score' => $quiz['max_score'],
                'time_limit_minutes' => $quiz['time_limit_minutes'],
                'submission_count' => $quiz['submission_count'],
                'created_at' => $quiz['created_at'],
                'status' => $this->getQuizStatus($quiz),
                'actions' => $quiz['id']
            ];
        }
        
        return $data;
    }
    
    /**
     * Get quiz by ID
     */
    public function getById($id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                q.*,
                l.title as lesson_title,
                l.subject_id,
                s.name as subject_name,
                gp.name as grading_period_name,
                gp.status as grading_period_status
            FROM quizzes q
            LEFT JOIN lessons l ON q.lesson_id = l.id
            LEFT JOIN subjects s ON l.subject_id = s.id
            LEFT JOIN grading_periods gp ON q.grading_period_id = gp.id
            WHERE q.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get quiz with questions (if you have a questions table)
     */
    public function getByIdWithQuestions($id) {
        $quiz = $this->getById($id);
        
        // If you have a questions table, you can add this:
        // $stmt = $this->pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order");
        // $stmt->execute([$id]);
        // $quiz['questions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $quiz;
    }
    
    /**
     * Update quiz
     */
    public function update($id, $data) {
        $stmt = $this->pdo->prepare("
            UPDATE quizzes 
            SET lesson_id = ?, grading_period_id = ?, title = ?, description = ?, max_score = ?, time_limit_minutes = ?, attempts_allowed = ?, display_mode = ?, open_at = ?, close_at = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['lesson_id'],
            $data['grading_period_id'],
            $data['title'],
            $data['description'] ?? '',
            $data['max_score'],
            $data['time_limit_minutes'] ?? null,
            $data['attempts_allowed'] ?? 1,
            $data['display_mode'] ?? 'all',
            $data['open_at'],
            $data['close_at'],
            $id
        ]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Delete quiz
     */
    public function delete($id) {
        // Check if there are submissions for this quiz
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM quiz_results WHERE quiz_id = ?");
        $stmt->execute([$id]);
        $submissionCount = $stmt->fetchColumn();
        
        if ($submissionCount > 0) {
            throw new Exception('Cannot delete quiz with existing submissions');
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM quizzes WHERE id = ?");
        $stmt->execute([$id]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get quiz submissions
     */
    public function getSubmissions($quizId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                qr.*,
                u.first_name,
                u.last_name,
                u.email,
                s.course,
                s.year_level
            FROM quiz_results qr
            LEFT JOIN students s ON qr.student_id = s.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE qr.quiz_id = ?
            ORDER BY qr.taken_at DESC
        ");
        
        $stmt->execute([$quizId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get quiz statistics
     */
    public function getStatistics($quizId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_submissions,
                COUNT(CASE WHEN score > 0 THEN 1 END) as completed_submissions,
                AVG(score) as average_score,
                MAX(score) as highest_score,
                MIN(score) as lowest_score
            FROM submissions 
            WHERE quiz_id = ?
        ");
        
        $stmt->execute([$quizId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get quiz status based on submissions and activity
     */
    private function getQuizStatus($quiz) {
        $gradingPeriodStatus = $quiz['grading_period_status'] ?? 'active';
        $submissionCount = $quiz['submission_count'];
        
        switch ($gradingPeriodStatus) {
            case 'completed':
                return 'completed';
            case 'inactive':
                return 'inactive';
            case 'pending':
                return 'pending';
            default:
                return 'active';
        }
    }
    
    /**
     * Get quizzes with pagination for DataTables server-side processing
     */
    public function getPaginated($start, $length, $search = '', $orderColumn = 0, $orderDir = 'desc') {
        $columns = ['id', 'title', 'description', 'max_score', 'submission_count', 'created_at'];
        $orderColumn = $columns[$orderColumn] ?? 'id';
        
        $searchCondition = '';
        $params = [];
        
        if (!empty($search)) {
            $searchCondition = "WHERE q.title LIKE ? OR q.description LIKE ? OR u.username LIKE ?";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        // Get total count
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM quizzes q
            LEFT JOIN users u ON q.author_id = u.id
            $searchCondition
        ");
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetchColumn();
        
        // Get filtered count
        $filteredRecords = $totalRecords;
        
        // Get data
        $dataStmt = $this->pdo->prepare("
            SELECT 
                q.*,
                COUNT(s.id) as submission_count,
                COUNT(CASE WHEN s.score > 0 THEN 1 END) as completed_count,
                AVG(s.score) as average_score
            FROM quizzes q
            LEFT JOIN submissions s ON q.id = s.quiz_id
            $searchCondition
            GROUP BY q.id
            ORDER BY $orderColumn $orderDir
            LIMIT $start, $length
        ");
        $dataStmt->execute($params);
        $quizzes = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format data for DataTables
        $data = [];
        foreach ($quizzes as $quiz) {
            $data[] = [
                'id' => $quiz['id'],
                'title' => htmlspecialchars($quiz['title']),
                'description' => htmlspecialchars(substr($quiz['description'], 0, 100)) . (strlen($quiz['description']) > 100 ? '...' : ''),
                'max_score' => $quiz['max_score'],
                'submission_count' => $quiz['submission_count'],
                'completed_count' => $quiz['completed_count'],
                'average_score' => $quiz['average_score'] ? round($quiz['average_score'], 2) : 0,
                'created_at' => date('M d, Y', strtotime($quiz['created_at'])),
                'status' => $this->getQuizStatus($quiz),
                'actions' => $quiz['id']
            ];
        }
        
        return [
            'data' => $data,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords
        ];
    }
    
}
?>
