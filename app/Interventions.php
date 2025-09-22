<?php

require_once 'Db.php';

class Interventions {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Db::getConnection();
    }
    
    /**
     * Get all interventions
     */
    public function getAll() {
        $stmt = $this->pdo->query("
            SELECT i.*, 
                   s.id as student_id,
                   CONCAT(u.first_name, ' ', u.last_name) as student_name,
                   u.email,
                   s.course,
                   s.year_level,
                   sub.name as subject_name,
                   CASE WHEN i.notify_teacher = 1 THEN 'Yes' ELSE 'No' END as notify_teacher_display
            FROM interventions i
            LEFT JOIN students s ON i.student_id = s.id
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN subjects sub ON i.subject_id = sub.id
            ORDER BY i.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get interventions for DataTable
     */
    public function getAllForDataTable() {
        $interventions = $this->getAll();
        
        $data = [];
        foreach ($interventions as $intervention) {
            $data[] = [
                'student_name' => $intervention['student_name'],
                'subject_name' => $intervention['subject_name'],
                'notes' => $intervention['notes'],
                'notify_teacher' => $intervention['notify_teacher'],
                'created_at' => $intervention['created_at'],
                'actions' => $intervention['id'] // Pass ID for actions column
            ];
        }
        
        return $data;
    }
    
    /**
     * Get intervention by ID
     */
    public function getById($id) {
        $stmt = $this->pdo->prepare("
            SELECT i.*, 
                   s.id as student_id,
                   CONCAT(u.first_name, ' ', u.last_name) as student_name,
                   u.email,
                   s.course,
                   s.year_level,
                   sub.name as subject_name,
                   i.notify_teacher
            FROM interventions i
            LEFT JOIN students s ON i.student_id = s.id
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN subjects sub ON i.subject_id = sub.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new intervention
     */
    public function create($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO interventions (student_id, subject_id, notes, notify_teacher)
            VALUES (?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['student_id'],
            $data['subject_id'],
            $data['notes'],
            $data['notify_teacher'] ?? 0
        ]);
    }
    
    /**
     * Update intervention
     */
    public function update($id, $data) {
        $stmt = $this->pdo->prepare("
            UPDATE interventions 
            SET student_id = ?, subject_id = ?, notes = ?, notify_teacher = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $data['student_id'],
            $data['subject_id'],
            $data['notes'],
            $data['notify_teacher'] ?? 0,
            $id
        ]);
    }
    
    /**
     * Delete intervention
     */
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM interventions WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Get interventions by student ID
     */
    public function getByStudentId($studentId) {
        $stmt = $this->pdo->prepare("
            SELECT i.*, 
                   s.id as student_id,
                   CONCAT(u.first_name, ' ', u.last_name) as student_name,
                   u.email,
                   s.course,
                   s.year_level,
                   sub.name as subject_name,
                   i.notify_teacher
            FROM interventions i
            LEFT JOIN students s ON i.student_id = s.id
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN subjects sub ON i.subject_id = sub.id
            WHERE i.student_id = ?
            ORDER BY i.created_at DESC
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get interventions by creator
     */
    public function getByCreator($creatorId) {
        // Since created_by field doesn't exist, return all interventions for now
        // This method can be removed or modified based on requirements
        return $this->getAll();
    }
    
    /**
     * Search interventions
     */
    public function search($searchTerm) {
        $stmt = $this->pdo->prepare("
            SELECT i.*, 
                   s.id as student_id,
                   CONCAT(u.first_name, ' ', u.last_name) as student_name,
                   u.email,
                   s.course,
                   s.year_level,
                   sub.name as subject_name,
                   i.notify_teacher
            FROM interventions i
            LEFT JOIN students s ON i.student_id = s.id
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN subjects sub ON i.subject_id = sub.id
            WHERE CONCAT(u.first_name, ' ', u.last_name) LIKE ? 
               OR u.email LIKE ?
               OR s.course LIKE ?
               OR s.year_level LIKE ?
               OR sub.name LIKE ?
               OR i.notes LIKE ?
            ORDER BY i.created_at DESC
        ");
        $searchPattern = "%$searchTerm%";
        $stmt->execute([$searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get intervention statistics
     */
    public function getStatistics() {
        // Total interventions
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM interventions");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Interventions by type
        $stmt = $this->pdo->query("
            SELECT intervention_type, COUNT(*) as count 
            FROM interventions 
            GROUP BY intervention_type 
            ORDER BY count DESC
        ");
        $byType = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent interventions (last 30 days)
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as recent 
            FROM interventions 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $recent = $stmt->fetch(PDO::FETCH_ASSOC)['recent'];
        
        // Most active students (students with most interventions)
        $stmt = $this->pdo->query("
            SELECT s.id as student_id, 
                   CONCAT(u.first_name, ' ', u.last_name) as student_name,
                   u.email,
                   s.course,
                   s.year_level,
                   COUNT(i.id) as intervention_count
            FROM students s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN interventions i ON s.id = i.student_id
            GROUP BY s.id
            HAVING intervention_count > 0
            ORDER BY intervention_count DESC
            LIMIT 5
        ");
        $activeStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'total' => $total,
            'by_type' => $byType,
            'recent' => $recent,
            'active_students' => $activeStudents
        ];
    }
    
    /**
     * Get recent interventions
     */
    public function getRecent($limit = 5) {
        $stmt = $this->pdo->prepare("
            SELECT i.*, 
                   s.id as student_id,
                   CONCAT(u.first_name, ' ', u.last_name) as student_name,
                   u.email,
                   s.course,
                   s.year_level,
                   sub.name as subject_name,
                   i.notify_teacher
            FROM interventions i
            LEFT JOIN students s ON i.student_id = s.id
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN subjects sub ON i.subject_id = sub.id
            ORDER BY i.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get count by creator
     */
    public function getCountByCreator() {
        // Since created_by field doesn't exist, return empty array for now
        // This method can be removed or modified based on requirements
        return [];
    }
    
    /**
     * Get all students for dropdown
     */
    public function getAllStudents() {
        $stmt = $this->pdo->query("
            SELECT s.id, 
                   CONCAT(u.first_name, ' ', u.last_name) as full_name,
                   u.email,
                   s.course,
                   s.year_level
            FROM students s
            LEFT JOIN users u ON s.user_id = u.id
            ORDER BY u.first_name, u.last_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all subjects for dropdown
     */
    public function getAllSubjects() {
        $stmt = $this->pdo->query("
            SELECT id, name, description, year_level, course
            FROM subjects
            ORDER BY name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Validate intervention data
     */
    public function validate($data) {
        $errors = [];
        
        if (empty($data['student_id'])) {
            $errors[] = 'Student is required';
        }
        
        if (empty($data['subject_id'])) {
            $errors[] = 'Subject is required';
        }
        
        if (empty($data['notes'])) {
            $errors[] = 'Notes are required';
        }
        
        return $errors;
    }
}
?>
