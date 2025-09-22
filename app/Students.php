<?php
class Students {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new student
     */
    public function create($data) {
        try {
            $this->pdo->beginTransaction();
            
            // First, create user account
            $userStmt = $this->pdo->prepare("
                INSERT INTO users (first_name, last_name, email, password, role) 
                VALUES (?, ?, ?, ?, 'student')
            ");
            
            $hashedPassword = md5($data['password']); // Using MD5 as per database.sql
            $userStmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $hashedPassword
            ]);
            
            $userId = $this->pdo->lastInsertId();
            
            // Then, create student profile
            $studentStmt = $this->pdo->prepare("
                INSERT INTO students (user_id, course, year_level) 
                VALUES (?, ?, ?)
            ");
            
            $studentStmt->execute([
                $userId,
                $data['course'],
                $data['year_level']
            ]);
            
            $studentId = $this->pdo->lastInsertId();
            
            $this->pdo->commit();
            return $studentId;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    /**
     * Get all students with user information
     */
    public function getAll() {
        $stmt = $this->pdo->prepare("
            SELECT 
                s.*,
                u.first_name,
                u.last_name,
                u.email,
                u.created_at as account_created,
                COUNT(asub.id) as activity_submissions,
                COUNT(qr.id) as quiz_submissions,
                AVG(qr.score) as average_grade
            FROM students s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN activity_submissions asub ON s.id = asub.student_id
            LEFT JOIN quiz_results qr ON s.id = qr.student_id
            GROUP BY s.id
            ORDER BY s.created_at DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get students formatted for DataTables
     */
    public function getAllForDataTable() {
        $students = $this->getAll();
        $data = [];
        
        foreach ($students as $student) {
            $data[] = [
                'id' => $student['id'],
                'full_name' => htmlspecialchars($student['first_name'] . ' ' . $student['last_name']),
                'email' => htmlspecialchars($student['email']),
                'course' => htmlspecialchars($student['course']),
                'year_level' => htmlspecialchars($student['year_level']),
                'activity_submissions' => $student['activity_submissions'],
                'quiz_submissions' => $student['quiz_submissions'],
                'average_grade' => $student['average_grade'] ? round($student['average_grade'], 2) : 'N/A',
                'created_at' => date('M d, Y', strtotime($student['created_at'])),
                'status' => $this->getStudentStatus($student),
                'actions' => $student['id']
            ];
        }
        
        return $data;
    }
    
    /**
     * Get student by ID
     */
    public function getById($id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                s.*,
                u.first_name,
                u.last_name,
                u.email,
                u.created_at as account_created
            FROM students s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get student by email
     */
    public function getByEmail($email) {
        $stmt = $this->pdo->prepare("
            SELECT 
                s.*,
                u.first_name,
                u.last_name,
                u.email,
                u.created_at as account_created
            FROM students s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update student
     */
    public function update($id, $data) {
        try {
            $this->pdo->beginTransaction();
            
            // Get student info to update user account if needed
            $student = $this->getById($id);
            if (!$student) {
                throw new Exception('Student not found');
            }
            
            // Update student profile
            $studentStmt = $this->pdo->prepare("
                UPDATE students 
                SET course = ?, year_level = ?
                WHERE id = ?
            ");
            
            $studentStmt->execute([
                $data['course'],
                $data['year_level'],
                $id
            ]);
            
            // Update user account
            $userUpdateFields = [];
            $userParams = [];
            
            $userUpdateFields[] = "first_name = ?";
            $userParams[] = $data['first_name'];
            
            $userUpdateFields[] = "last_name = ?";
            $userParams[] = $data['last_name'];
            
            $userUpdateFields[] = "email = ?";
            $userParams[] = $data['email'];
            
            if (isset($data['password'])) {
                $userUpdateFields[] = "password = ?";
                $userParams[] = md5($data['password']);
            }
            
            $userParams[] = $student['user_id'];
            
            $userStmt = $this->pdo->prepare("
                UPDATE users 
                SET " . implode(', ', $userUpdateFields) . " 
                WHERE id = ?
            ");
            $userStmt->execute($userParams);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    /**
     * Delete student
     */
    public function delete($id) {
        try {
            $this->pdo->beginTransaction();
            
            // Get student info
            $student = $this->getById($id);
            if (!$student) {
                throw new Exception('Student not found');
            }
            
            // Check if student has submissions
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM (
                    SELECT id FROM submitted_activities WHERE student_id = ?
                    UNION ALL
                    SELECT id FROM submissions WHERE student_id = ?
                ) as submissions
            ");
            $stmt->execute([$id, $student['user_id']]);
            $submissionCount = $stmt->fetchColumn();
            
            if ($submissionCount > 0) {
                throw new Exception('Cannot delete student with existing submissions');
            }
            
            // Delete student record (this will cascade to user due to foreign key)
            $stmt = $this->pdo->prepare("DELETE FROM students WHERE id = ?");
            $stmt->execute([$id]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    /**
     * Get student submissions
     */
    public function getSubmissions($studentId) {
        // Get activity submissions
        $activityStmt = $this->pdo->prepare("
            SELECT 
                'activity' as type,
                a.title,
                sa.submission_text,
                sa.file_path,
                sa.status,
                sa.grading_status,
                sa.submitted_at,
                a.max_score
            FROM submitted_activities sa
            LEFT JOIN activities a ON sa.activity_id = a.id
            WHERE sa.student_id = ?
            ORDER BY sa.submitted_at DESC
        ");
        $activityStmt->execute([$studentId]);
        $activitySubmissions = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get quiz submissions
        $quizStmt = $this->pdo->prepare("
            SELECT 
                'quiz' as type,
                q.title,
                s.answers,
                s.score,
                s.created_at as submitted_at,
                q.max_score
            FROM submissions s
            LEFT JOIN quizzes q ON s.quiz_id = q.id
            WHERE s.student_id = ?
            ORDER BY s.created_at DESC
        ");
        $quizStmt->execute([$studentId]);
        $quizSubmissions = $quizStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_merge($activitySubmissions, $quizSubmissions);
    }
    
    /**
     * Get student statistics
     */
    public function getStatistics($studentId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(sa.id) as total_activity_submissions,
                COUNT(CASE WHEN sa.grading_status = 'graded' THEN 1 END) as graded_activities,
                COUNT(sb.id) as total_quiz_submissions,
                AVG(sb.score) as average_quiz_score,
                AVG(g.final_grade) as overall_grade
            FROM students s
            LEFT JOIN submitted_activities sa ON s.id = sa.student_id
            LEFT JOIN submissions sb ON s.user_id = sb.student_id
            LEFT JOIN grades g ON s.id = g.student_id
            WHERE s.id = ?
        ");
        
        $stmt->execute([$studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get student status based on activity
     */
    private function getStudentStatus($student) {
        $activitySubmissions = $student['activity_submissions'];
        $quizSubmissions = $student['quiz_submissions'];
        $averageGrade = $student['average_grade'];
        
        if ($averageGrade === null) {
            return '<span class="badge bg-secondary">No Grades</span>';
        } elseif ($averageGrade >= 90) {
            return '<span class="badge bg-success">Excellent</span>';
        } elseif ($averageGrade >= 80) {
            return '<span class="badge bg-primary">Good</span>';
        } elseif ($averageGrade >= 70) {
            return '<span class="badge bg-warning">Average</span>';
        } else {
            return '<span class="badge bg-danger">At Risk</span>';
        }
    }
    
    /**
     * Get students with pagination for DataTables server-side processing
     */
    public function getPaginated($start, $length, $search = '', $orderColumn = 0, $orderDir = 'desc') {
        $columns = ['id', 'full_name', 'email', 'course', 'year_level', 'created_at'];
        $orderColumn = $columns[$orderColumn] ?? 'id';
        
        $searchCondition = '';
        $params = [];
        
        if (!empty($search)) {
            $searchCondition = "WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR s.course LIKE ? OR s.year_level LIKE ?";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        // Get total count
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM students s
            $searchCondition
        ");
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetchColumn();
        
        // Get filtered count
        $filteredRecords = $totalRecords;
        
        // Get data
        $dataStmt = $this->pdo->prepare("
            SELECT 
                s.*,
                u.username,
                u.created_at as account_created,
                COUNT(sa.id) as activity_submissions,
                COUNT(sb.id) as quiz_submissions,
                AVG(g.final_grade) as average_grade
            FROM students s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN submitted_activities sa ON s.id = sa.student_id
            LEFT JOIN submissions sb ON s.user_id = sb.student_id
            LEFT JOIN grades g ON s.id = g.student_id
            $searchCondition
            GROUP BY s.id
            ORDER BY $orderColumn $orderDir
            LIMIT $start, $length
        ");
        $dataStmt->execute($params);
        $students = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format data for DataTables
        $data = [];
        foreach ($students as $student) {
            $data[] = [
                'id' => $student['id'],
                'full_name' => htmlspecialchars($student['first_name'] . ' ' . $student['last_name']),
                'email' => htmlspecialchars($student['email']),
                'course' => htmlspecialchars($student['course']),
                'year_level' => htmlspecialchars($student['year_level']),
                'activity_submissions' => $student['activity_submissions'],
                'quiz_submissions' => $student['quiz_submissions'],
                'average_grade' => $student['average_grade'] ? round($student['average_grade'], 2) : 'N/A',
                'created_at' => date('M d, Y', strtotime($student['created_at'])),
                'status' => $this->getStudentStatus($student),
                'actions' => $student['id']
            ];
        }
        
        return [
            'data' => $data,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords
        ];
    }
    
    
    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            // Get the user_id for the student to exclude
            $studentStmt = $this->pdo->prepare("SELECT user_id FROM students WHERE id = ?");
            $studentStmt->execute([$excludeId]);
            $userId = $studentStmt->fetchColumn();
            
            if ($userId) {
                $sql .= " AND id != ?";
                $params[] = $userId;
            }
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Get student ID by user ID
     */
    public function getStudentIdByUserId($userId) {
        $sql = "SELECT id FROM students WHERE user_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : null;
    }
}
?>
