<?php

class Semesters {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Db::getConnection();
    }
    
    /**
     * Create a new semester
     */
    public function create($data) {
        try {
            $sql = "INSERT INTO semesters (name, academic_year, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['academic_year'],
                $data['start_date'],
                $data['end_date'],
                $data['status']
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get all semesters
     */
    public function getAll() {
        $sql = "SELECT * FROM semesters ORDER BY academic_year DESC, name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get semester by ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM semesters WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update semester
     */
    public function update($id, $data) {
        try {
            $sql = "UPDATE semesters SET name = ?, academic_year = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['academic_year'],
                $data['start_date'],
                $data['end_date'],
                $data['status'],
                $id
            ]);
            
            return true;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Delete semester
     */
    public function delete($id) {
        try {
            // Check if semester has grading periods
            $checkSql = "SELECT COUNT(*) as count FROM grading_periods WHERE semester_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$id]);
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                throw new Exception('Cannot delete semester with existing grading periods');
            }
            
            $sql = "DELETE FROM semesters WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            
            return true;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get active semester
     */
    public function getActive() {
        $sql = "SELECT * FROM semesters WHERE status = 'active' ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get semesters for dropdown
     */
    public function getForDropdown() {
        $sql = "SELECT id, name, academic_year FROM semesters ORDER BY academic_year DESC, name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get paginated semesters for DataTable
     */
    public function getPaginated($start = 0, $length = 10, $search = '', $orderColumn = 0, $orderDir = 'desc') {
        $columns = ['id', 'name', 'academic_year', 'start_date', 'end_date', 'status', 'created_at'];
        $orderBy = $columns[$orderColumn] ?? 'created_at';
        
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE name LIKE ? OR academic_year LIKE ? OR status LIKE ?";
            $searchTerm = "%{$search}%";
            $params = [$searchTerm, $searchTerm, $searchTerm];
        }
        
        $sql = "SELECT * FROM semesters {$whereClause} ORDER BY {$orderBy} {$orderDir} LIMIT {$start}, {$length}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get total count of semesters
     */
    public function getTotalCount($search = '') {
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE name LIKE ? OR academic_year LIKE ? OR status LIKE ?";
            $searchTerm = "%{$search}%";
            $params = [$searchTerm, $searchTerm, $searchTerm];
        }
        
        $sql = "SELECT COUNT(*) as total FROM semesters {$whereClause}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
    
    /**
     * Get semesters formatted for DataTable
     */
    public function getAllForDataTable() {
        $semesters = $this->getAll();
        $data = [];
        
        foreach ($semesters as $semester) {
            $data[] = [
                'id' => $semester['id'],
                'name' => $semester['name'],
                'academic_year' => $semester['academic_year'],
                'start_date' => date('M d, Y', strtotime($semester['start_date'])),
                'end_date' => date('M d, Y', strtotime($semester['end_date'])),
                'status' => ucfirst($semester['status']),
                'created_at' => date('M d, Y', strtotime($semester['created_at']))
            ];
        }
        
        return $data;
    }
    
    /**
     * Get semester statistics
     */
    public function getStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_semesters,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_semesters,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_semesters,
                    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_semesters
                FROM semesters";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Validate semester dates
     */
    public function validateDates($startDate, $endDate, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM semesters WHERE 
                ((start_date <= ? AND end_date >= ?) OR 
                 (start_date <= ? AND end_date >= ?) OR 
                 (start_date >= ? AND end_date <= ?))";
        
        $params = [$startDate, $startDate, $endDate, $endDate, $startDate, $endDate];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] == 0;
    }
}
?>
