<?php

require_once 'Db.php';

class Lessons {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Db::getConnection();
    }
    
    /**
     * Create a new lesson
     */
    public function create($data) {
        try {
            $sql = "INSERT INTO lessons (subject_id, title, content) VALUES (?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['subject_id'],
                $data['title'],
                $data['content']
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get all lessons with subject information
     */
    public function getAll() {
        $sql = "SELECT 
                    l.id,
                    l.subject_id,
                    l.title,
                    l.content,
                    l.created_at,
                    s.name as subject_name,
                    s.description as subject_description
                FROM lessons l
                INNER JOIN subjects s ON l.subject_id = s.id
                ORDER BY l.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get lessons data for DataTable
     */
    public function getAllForDataTable() {
        $lessons = $this->getAll();
        
        $data = [];
        foreach ($lessons as $lesson) {
            $data[] = [
                'id' => $lesson['id'],
                'subject_id' => $lesson['subject_id'],
                'title' => $lesson['title'],
                'content' => $lesson['content'],
                'subject_name' => $lesson['subject_name'],
                'created_at' => date('M d, Y', strtotime($lesson['created_at'])),
                'status' => 'Active'
            ];
        }
        
        return $data;
    }
    
    /**
     * Get lesson by ID
     */
    public function getById($id) {
        $sql = "SELECT 
                    l.id,
                    l.subject_id,
                    l.title,
                    l.content,
                    l.created_at,
                    s.name as subject_name,
                    s.description as subject_description
                FROM lessons l
                INNER JOIN subjects s ON l.subject_id = s.id
                WHERE l.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update lesson
     */
    public function update($id, $data) {
        try {
            $sql = "UPDATE lessons SET subject_id = ?, title = ?, content = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['subject_id'],
                $data['title'],
                $data['content'],
                $id
            ]);
            
            return true;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Delete lesson
     */
    public function delete($id) {
        try {
            // Check if lesson has any quizzes
            $checkSql = "SELECT COUNT(*) as count FROM quizzes WHERE lesson_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$id]);
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                throw new Exception('Cannot delete lesson with existing quizzes');
            }
            
            $sql = "DELETE FROM lessons WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            
            return true;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get lessons by subject ID
     */
    public function getBySubjectId($subjectId) {
        $sql = "SELECT 
                    l.id,
                    l.subject_id,
                    l.title,
                    l.content,
                    l.created_at,
                    s.name as subject_name
                FROM lessons l
                INNER JOIN subjects s ON l.subject_id = s.id
                WHERE l.subject_id = ?
                ORDER BY l.created_at ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$subjectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get lesson statistics
     */
    public function getStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_lessons,
                    COUNT(DISTINCT l.subject_id) as subjects_with_lessons,
                    AVG(LENGTH(l.content)) as avg_content_length
                FROM lessons l
                INNER JOIN subjects s ON l.subject_id = s.id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get paginated lessons
     */
    public function getPaginated($start = 0, $length = 10, $search = '', $orderColumn = 0, $orderDir = 'desc') {
        $columns = ['l.id', 'l.title', 's.name', 'l.created_at'];
        $orderBy = $columns[$orderColumn] ?? 'l.created_at';
        
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE (l.title LIKE ? OR s.name LIKE ? OR l.content LIKE ?)";
            $searchParam = "%{$search}%";
            $params = [$searchParam, $searchParam, $searchParam];
        }
        
        $sql = "SELECT 
                    l.id,
                    l.subject_id,
                    l.title,
                    l.content,
                    l.created_at,
                    s.name as subject_name,
                    s.description as subject_description
                FROM lessons l
                INNER JOIN subjects s ON l.subject_id = s.id
                {$whereClause}
                ORDER BY {$orderBy} {$orderDir}
                LIMIT {$start}, {$length}";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format data for DataTable
        $formattedData = [];
        foreach ($data as $lesson) {
            $formattedData[] = [
                'id' => $lesson['id'],
                'subject_id' => $lesson['subject_id'],
                'title' => $lesson['title'],
                'content' => $lesson['content'],
                'subject_name' => $lesson['subject_name'],
                'created_at' => date('M d, Y', strtotime($lesson['created_at'])),
                'status' => 'Active'
            ];
        }
        
        return $formattedData;
    }
    
    /**
     * Get total count for pagination
     */
    public function getTotalCount($search = '') {
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE (l.title LIKE ? OR s.name LIKE ? OR l.content LIKE ?)";
            $searchParam = "%{$search}%";
            $params = [$searchParam, $searchParam, $searchParam];
        }
        
        $sql = "SELECT COUNT(*) as count 
                FROM lessons l
                INNER JOIN subjects s ON l.subject_id = s.id
                {$whereClause}";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
    
    /**
     * Get all subjects for dropdown
     */
    public function getAllSubjects() {
        $sql = "SELECT id, name, description FROM subjects ORDER BY name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get lessons count by subject
     */
    public function getLessonsCountBySubject() {
        $sql = "SELECT 
                    s.id,
                    s.name,
                    COUNT(l.id) as lesson_count
                FROM subjects s
                LEFT JOIN lessons l ON s.id = l.subject_id
                GROUP BY s.id, s.name
                ORDER BY s.name ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
