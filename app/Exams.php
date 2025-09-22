<?php

require_once __DIR__ . '/Db.php';

class Exams {
    private $pdo;

    public function __construct() {
        $this->pdo = Db::getConnection();
    }

    /**
     * Create a new exam
     */
    public function create($data) {
        try {
            $sql = "INSERT INTO exams (lesson_id, grading_period_id, title, description, max_score, 
                    time_limit_minutes, attempts_allowed, display_mode, open_at, close_at, created_by) 
                    VALUES (:lesson_id, :grading_period_id, :title, :description, :max_score, 
                    :time_limit_minutes, :attempts_allowed, :display_mode, :open_at, :close_at, :created_by)";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($data);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Exam created successfully',
                    'id' => $this->pdo->lastInsertId()
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to create exam'];
            }
        } catch (PDOException $e) {
            error_log("Create exam error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    /**
     * Update an exam
     */
    public function update($id, $data) {
        try {
            $sql = "UPDATE exams SET 
                    lesson_id = :lesson_id,
                    grading_period_id = :grading_period_id,
                    title = :title,
                    description = :description,
                    max_score = :max_score,
                    time_limit_minutes = :time_limit_minutes,
                    attempts_allowed = :attempts_allowed,
                    display_mode = :display_mode,
                    open_at = :open_at,
                    close_at = :close_at,
                    updated_at = NOW()
                    WHERE id = :id";
            
            $data['id'] = $id;
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($data);
            
            if ($result && $stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Exam updated successfully'];
            } else {
                return ['success' => false, 'message' => 'No changes made or exam not found'];
            }
        } catch (PDOException $e) {
            error_log("Update exam error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    /**
     * Get exam by ID
     */
    public function getById($id) {
        try {
            $sql = "SELECT e.*, l.title as lesson_name, gp.name as grading_period_name,
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                    FROM exams e
                    LEFT JOIN lessons l ON e.lesson_id = l.id
                    LEFT JOIN grading_periods gp ON e.grading_period_id = gp.id
                    LEFT JOIN users u ON e.created_by = u.id
                    WHERE e.id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            $exam = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($exam) {
                return ['success' => true, 'data' => $exam];
            } else {
                return ['success' => false, 'message' => 'Exam not found'];
            }
        } catch (PDOException $e) {
            error_log("Get exam error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    /**
     * Delete an exam
     */
    public function delete($id) {
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            // Delete exam questions first (if they exist)
            $deleteQuestionsSQL = "DELETE FROM exam_questions WHERE exam_id = :id";
            $stmt = $this->pdo->prepare($deleteQuestionsSQL);
            $stmt->execute(['id' => $id]);
            
            // Delete exam attempts (if they exist)
            $deleteAttemptsSQL = "DELETE FROM exam_attempts WHERE exam_id = :id";
            $stmt = $this->pdo->prepare($deleteAttemptsSQL);
            $stmt->execute(['id' => $id]);
            
            // Delete the exam
            $deleteExamSQL = "DELETE FROM exams WHERE id = :id";
            $stmt = $this->pdo->prepare($deleteExamSQL);
            $result = $stmt->execute(['id' => $id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->pdo->commit();
                return ['success' => true, 'message' => 'Exam deleted successfully'];
            } else {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Exam not found'];
            }
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Delete exam error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    /**
     * Get exams for DataTable (Teachers/Admins)
     */
    public function getExamsForDataTable($start, $length, $search, $orderBy, $orderDir, $userId) {
        try {
            // Base query
            $baseSQL = "FROM exams e
                        LEFT JOIN lessons l ON e.lesson_id = l.id
                        LEFT JOIN grading_periods gp ON e.grading_period_id = gp.id
                        LEFT JOIN users u ON e.created_by = u.id";
            
            // Where clause
            $whereSQL = " WHERE 1=1";
            $params = [];
            
            // Role-based filtering
            if (!Permission::isAdmin()) {
                $whereSQL .= " AND e.created_by = :user_id";
                $params['user_id'] = $userId;
            }
            
            // Search functionality
            if (!empty($search)) {
                $whereSQL .= " AND (e.title LIKE :search OR l.title LIKE :search OR gp.name LIKE :search)";
                $params['search'] = "%$search%";
            }
            
            // Count total records
            $countSQL = "SELECT COUNT(*) as total $baseSQL $whereSQL";
            $stmt = $this->pdo->prepare($countSQL);
            $stmt->execute($params);
            $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Main query with ordering and pagination
            $dataSQL = "SELECT e.*, l.title as lesson_name, gp.name as grading_period_name,
                        CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                        $baseSQL $whereSQL
                        ORDER BY $orderBy $orderDir
                        LIMIT :start, :length";
            
            $stmt = $this->pdo->prepare($dataSQL);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);
            $stmt->bindValue(':length', $length, PDO::PARAM_INT);
            $stmt->execute();
            
            $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'total' => $totalRecords,
                'filtered' => $totalRecords, // For simplicity, using same value
                'data' => $exams
            ];
        } catch (PDOException $e) {
            error_log("Get exams for DataTable error: " . $e->getMessage());
            return [
                'total' => 0,
                'filtered' => 0,
                'data' => []
            ];
        }
    }

    /**
     * Get exams for students with their scores
     */
    public function getExamsForStudent($start, $length, $search, $orderBy, $orderDir, $studentId, $period = '') {
        try {
            // Base query with student scores
            $baseSQL = "FROM exams e
                        LEFT JOIN lessons l ON e.lesson_id = l.id
                        LEFT JOIN grading_periods gp ON e.grading_period_id = gp.id
                        LEFT JOIN users u ON e.created_by = u.id
                        LEFT JOIN (
                            SELECT exam_id, student_id, 
                                   MAX(score) as best_score,
                                   COUNT(*) as attempts_count
                            FROM exam_attempts 
                            WHERE student_id = :student_id
                            GROUP BY exam_id
                        ) ea ON e.id = ea.exam_id";
            
            // Where clause - only show exams that are published
            $whereSQL = " WHERE 1=1";
            $params = ['student_id' => $studentId];
            
            // Period filter
            if (!empty($period)) {
                $whereSQL .= " AND LOWER(gp.name) = :period";
                $params['period'] = strtolower($period);
            }
            
            // Search functionality
            if (!empty($search)) {
                $whereSQL .= " AND (e.title LIKE :search OR l.title LIKE :search OR gp.name LIKE :search)";
                $params['search'] = "%$search%";
            }
            
            // Count total records
            $countSQL = "SELECT COUNT(*) as total $baseSQL $whereSQL";
            $stmt = $this->pdo->prepare($countSQL);
            $stmt->execute($params);
            $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Main query
            $dataSQL = "SELECT e.*, l.title as lesson_name, gp.name as grading_period_name,
                        CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                        ea.best_score as student_score,
                        ea.attempts_count as student_attempts
                        $baseSQL $whereSQL
                        ORDER BY $orderBy $orderDir
                        LIMIT :start, :length";
            
            $stmt = $this->pdo->prepare($dataSQL);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':start', $start, PDO::PARAM_INT);
            $stmt->bindValue(':length', $length, PDO::PARAM_INT);
            $stmt->execute();
            
            $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'total' => $totalRecords,
                'filtered' => $totalRecords,
                'data' => $exams
            ];
        } catch (PDOException $e) {
            error_log("Get exams for student error: " . $e->getMessage());
            return [
                'total' => 0,
                'filtered' => 0,
                'data' => []
            ];
        }
    }

    /**
     * Get exam details for student with their score
     */
    public function getExamForStudent($examId, $studentId) {
        try {
            $sql = "SELECT e.*, l.title as lesson_name, gp.name as grading_period_name,
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                    ea.best_score as student_score,
                    ea.attempts_count as student_attempts
                    FROM exams e
                    LEFT JOIN lessons l ON e.lesson_id = l.id
                    LEFT JOIN grading_periods gp ON e.grading_period_id = gp.id
                    LEFT JOIN users u ON e.created_by = u.id
                    LEFT JOIN (
                        SELECT exam_id, student_id, 
                               MAX(score) as best_score,
                               COUNT(*) as attempts_count
                        FROM exam_attempts 
                        WHERE student_id = :student_id AND exam_id = :exam_id
                        GROUP BY exam_id
                    ) ea ON e.id = ea.exam_id
                    WHERE e.id = :exam_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'exam_id' => $examId,
                'student_id' => $studentId
            ]);
            $exam = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($exam) {
                return ['success' => true, 'data' => $exam];
            } else {
                return ['success' => false, 'message' => 'Exam not found'];
            }
        } catch (PDOException $e) {
            error_log("Get exam for student error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    /**
     * Get all exams (for dropdowns, etc.)
     */
    public function getAll() {
        try {
            $sql = "SELECT e.*, l.title as lesson_name, gp.name as grading_period_name
                    FROM exams e
                    LEFT JOIN lessons l ON e.lesson_id = l.id
                    LEFT JOIN grading_periods gp ON e.grading_period_id = gp.id
                    ORDER BY e.title";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'data' => $exams];
        } catch (PDOException $e) {
            error_log("Get all exams error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    /**
     * Check if exam is available for student
     */
    public function isExamAvailable($examId, $studentId) {
        try {
            $sql = "SELECT e.*, 
                    COALESCE(ea.attempts_count, 0) as attempts_used
                    FROM exams e
                    LEFT JOIN (
                        SELECT exam_id, COUNT(*) as attempts_count
                        FROM exam_attempts 
                        WHERE student_id = :student_id AND exam_id = :exam_id
                    ) ea ON e.id = ea.exam_id
                    WHERE e.id = :exam_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'exam_id' => $examId,
                'student_id' => $studentId
            ]);
            
            $exam = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$exam) {
                return ['success' => false, 'message' => 'Exam not found'];
            }
            
            // Check if exam is within the open/close time window
            $now = new DateTime();
            $openAt = new DateTime($exam['open_at']);
            $closeAt = new DateTime($exam['close_at']);
            
            if ($now < $openAt) {
                return ['success' => false, 'message' => "Exam is not yet open. Opens on " . $openAt->format('Y-m-d H:i:s')];
            }
            
            if ($now > $closeAt) {
                return ['success' => false, 'message' => 'Exam has closed'];
            }
            
            // Check attempts limit
            if ($exam['attempts_allowed'] && $exam['attempts_used'] >= $exam['attempts_allowed']) {
                return ['success' => false, 'message' => 'You have already used all your attempts for this exam'];
            }
            
            return ['success' => true, 'data' => $exam];
        } catch (Exception $e) {
            error_log("Check exam availability error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error checking exam availability'];
        }
    }
}
?>
