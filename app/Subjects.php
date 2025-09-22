<?php

class Subjects {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Db::getConnection();
    }
    
    /**
     * Create a new subject
     */
    public function create($data) {
        try {
            $sql = "INSERT INTO subjects (name, description, year_level, course) VALUES (?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['year_level'],
                $data['course']
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get all subjects
     */
    public function getAll() {
        $sql = "SELECT * FROM subjects ORDER BY course ASC, year_level ASC, name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get subject by ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM subjects WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update subject
     */
    public function update($id, $data) {
        try {
            $sql = "UPDATE subjects SET name = ?, description = ?, year_level = ?, course = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['year_level'],
                $data['course'],
                $id
            ]);
            
            return true;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Delete subject
     */
    public function delete($id) {
        try {
            // Check if subject has enrollments
            $checkSql = "SELECT COUNT(*) as count FROM enrollments WHERE subject_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$id]);
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                throw new Exception('Cannot delete subject with existing enrollments');
            }
            
            // Check if subject has lessons
            $checkSql2 = "SELECT COUNT(*) as count FROM lessons WHERE subject_id = ?";
            $checkStmt2 = $this->pdo->prepare($checkSql2);
            $checkStmt2->execute([$id]);
            $result2 = $checkStmt2->fetch(PDO::FETCH_ASSOC);
            
            if ($result2['count'] > 0) {
                throw new Exception('Cannot delete subject with existing lessons');
            }
            
            // Check if subject has activities
            $checkSql3 = "SELECT COUNT(*) as count FROM activities WHERE subject_id = ?";
            $checkStmt3 = $this->pdo->prepare($checkSql3);
            $checkStmt3->execute([$id]);
            $result3 = $checkStmt3->fetch(PDO::FETCH_ASSOC);
            
            if ($result3['count'] > 0) {
                throw new Exception('Cannot delete subject with existing activities');
            }
            
            // Check if subject has grades
            $checkSql4 = "SELECT COUNT(*) as count FROM grades WHERE subject_id = ?";
            $checkStmt4 = $this->pdo->prepare($checkSql4);
            $checkStmt4->execute([$id]);
            $result4 = $checkStmt4->fetch(PDO::FETCH_ASSOC);
            
            if ($result4['count'] > 0) {
                throw new Exception('Cannot delete subject with existing grades');
            }
            
            $sql = "DELETE FROM subjects WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            
            return true;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get subjects for dropdown
     */
    public function getForDropdown() {
        $sql = "SELECT id, name, course, year_level FROM subjects ORDER BY course ASC, year_level ASC, name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get subjects by course
     */
    public function getByCourse($course) {
        $sql = "SELECT * FROM subjects WHERE course = ? ORDER BY year_level ASC, name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$course]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get subjects by year level
     */
    public function getByYearLevel($yearLevel) {
        $sql = "SELECT * FROM subjects WHERE year_level = ? ORDER BY course ASC, name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$yearLevel]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get paginated subjects for DataTable
     */
    public function getPaginated($start = 0, $length = 10, $search = '', $orderColumn = 0, $orderDir = 'desc') {
        $columns = ['id', 'name', 'description', 'year_level', 'course', 'created_at'];
        $orderBy = $columns[$orderColumn] ?? 'created_at';
        
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE name LIKE ? OR description LIKE ? OR year_level LIKE ? OR course LIKE ?";
            $searchTerm = "%{$search}%";
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }
        
        $sql = "SELECT * FROM subjects {$whereClause} ORDER BY {$orderBy} {$orderDir} LIMIT {$start}, {$length}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get total count of subjects
     */
    public function getTotalCount($search = '') {
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE name LIKE ? OR description LIKE ? OR year_level LIKE ? OR course LIKE ?";
            $searchTerm = "%{$search}%";
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }
        
        $sql = "SELECT COUNT(*) as total FROM subjects {$whereClause}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
    
    /**
     * Get subjects formatted for DataTable
     */
    public function getAllForDataTable() {
        $subjects = $this->getAll();
        $data = [];
        
        foreach ($subjects as $subject) {
            $data[] = [
                'id' => $subject['id'],
                'name' => $subject['name'],
                'description' => $subject['description'] ?: 'No description',
                'year_level' => $subject['year_level'],
                'course' => $subject['course'],
                'created_at' => date('M d, Y', strtotime($subject['created_at']))
            ];
        }
        
        return $data;
    }
    
    /**
     * Get subject statistics
     */
    public function getStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_subjects,
                    COUNT(DISTINCT course) as total_courses,
                    COUNT(DISTINCT year_level) as total_year_levels,
                    COUNT(CASE WHEN course = 'BSIT' THEN 1 END) as bsit_subjects,
                    COUNT(CASE WHEN course = 'BSCS' THEN 1 END) as bscs_subjects,
                    COUNT(CASE WHEN course = 'BSIS' THEN 1 END) as bsis_subjects
                FROM subjects";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get unique courses
     */
    public function getUniqueCourses() {
        $sql = "SELECT DISTINCT course FROM subjects ORDER BY course ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Get unique year levels
     */
    public function getUniqueYearLevels() {
        $sql = "SELECT DISTINCT year_level FROM subjects ORDER BY year_level ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Check if subject name exists (for validation)
     */
    public function nameExists($name, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM subjects WHERE name = ?";
        $params = [$name];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }
}
?>
