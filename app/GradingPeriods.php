<?php

require_once 'Db.php';

class GradingPeriods {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Db::getConnection();
    }
    
    /**
     * Create a new grading period
     */
    public function create($data) {
        try {
            $sql = "INSERT INTO grading_periods (semester_id, name, start_date, end_date, weight_percent, status) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['semester_id'],
                $data['name'],
                $data['start_date'],
                $data['end_date'],
                $data['weight_percent'],
                $data['status']
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get all grading periods with semester information
     */
    public function getAll() {
        $sql = "SELECT 
                    gp.id,
                    gp.semester_id,
                    gp.name,
                    gp.start_date,
                    gp.end_date,
                    gp.weight_percent,
                    gp.status,
                    gp.created_at,
                    s.name as semester_name,
                    s.academic_year
                FROM grading_periods gp
                INNER JOIN semesters s ON gp.semester_id = s.id
                ORDER BY s.academic_year DESC, gp.start_date ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get grading periods data for DataTable
     */
    public function getAllForDataTable() {
        $gradingPeriods = $this->getAll();
        
        $data = [];
        foreach ($gradingPeriods as $period) {
            $data[] = [
                'id' => $period['id'],
                'semester_id' => $period['semester_id'],
                'semester_name' => $period['semester_name'],
                'academic_year' => $period['academic_year'],
                'name' => ucfirst($period['name']),
                'start_date' => date('M d, Y', strtotime($period['start_date'])),
                'end_date' => date('M d, Y', strtotime($period['end_date'])),
                'weight_percent' => number_format($period['weight_percent'], 2) . '%',
                'status' => ucfirst($period['status']),
                'created_at' => date('M d, Y', strtotime($period['created_at']))
            ];
        }
        
        return $data;
    }
    
    /**
     * Get grading period by ID
     */
    public function getById($id) {
        $sql = "SELECT 
                    gp.id,
                    gp.semester_id,
                    gp.name,
                    gp.start_date,
                    gp.end_date,
                    gp.weight_percent,
                    gp.status,
                    gp.created_at,
                    s.name as semester_name,
                    s.academic_year
                FROM grading_periods gp
                INNER JOIN semesters s ON gp.semester_id = s.id
                WHERE gp.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update grading period
     */
    public function update($id, $data) {
        try {
            $sql = "UPDATE grading_periods SET semester_id = ?, name = ?, start_date = ?, end_date = ?, weight_percent = ?, status = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['semester_id'],
                $data['name'],
                $data['start_date'],
                $data['end_date'],
                $data['weight_percent'],
                $data['status'],
                $id
            ]);
            
            return true;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Delete grading period
     */
    public function delete($id) {
        try {
            // Check if grading period has activities or quizzes
            $checkSql = "SELECT COUNT(*) as count FROM activities WHERE grading_period_id = ?";
            $stmt = $this->pdo->prepare($checkSql);
            $stmt->execute([$id]);
            $activitiesCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $checkSql = "SELECT COUNT(*) as count FROM quizzes WHERE grading_period_id = ?";
            $stmt = $this->pdo->prepare($checkSql);
            $stmt->execute([$id]);
            $quizzesCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($activitiesCount > 0 || $quizzesCount > 0) {
                throw new Exception("Cannot delete grading period. It has associated activities or quizzes.");
            }
            
            $sql = "DELETE FROM grading_periods WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            
            return true;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get all semesters for dropdown
     */
    public function getAllSemesters() {
        $sql = "SELECT id, name, academic_year, status FROM semesters ORDER BY academic_year DESC, name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get grading periods by semester ID
     */
    public function getBySemesterId($semesterId) {
        $sql = "SELECT 
                    gp.id,
                    gp.semester_id,
                    gp.name,
                    gp.start_date,
                    gp.end_date,
                    gp.weight_percent,
                    gp.status,
                    gp.created_at
                FROM grading_periods gp
                WHERE gp.semester_id = ?
                ORDER BY gp.start_date ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$semesterId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get current active grading period
     */
    public function getCurrentActive() {
        $sql = "SELECT 
                    gp.id,
                    gp.semester_id,
                    gp.name,
                    gp.start_date,
                    gp.end_date,
                    gp.weight_percent,
                    gp.status,
                    s.name as semester_name,
                    s.academic_year
                FROM grading_periods gp
                INNER JOIN semesters s ON gp.semester_id = s.id
                WHERE gp.status = 'active' 
                AND s.status = 'active'
                AND CURDATE() BETWEEN gp.start_date AND gp.end_date
                ORDER BY gp.start_date ASC
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get grading period statistics
     */
    public function getStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_periods,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_periods,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_periods,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_periods,
                    AVG(weight_percent) as average_weight
                FROM grading_periods";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get paginated grading periods
     */
    public function getPaginated($start = 0, $length = 10, $search = '', $orderColumn = 0, $orderDir = 'desc') {
        $columns = ['gp.id', 's.name', 's.academic_year', 'gp.name', 'gp.start_date', 'gp.end_date', 'gp.weight_percent', 'gp.status'];
        $orderBy = $columns[$orderColumn] ?? 'gp.created_at';
        
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE (s.name LIKE ? OR s.academic_year LIKE ? OR gp.name LIKE ? OR gp.status LIKE ?)";
            $searchParam = "%{$search}%";
            $params = [$searchParam, $searchParam, $searchParam, $searchParam];
        }
        
        $sql = "SELECT 
                    gp.id,
                    gp.semester_id,
                    gp.name,
                    gp.start_date,
                    gp.end_date,
                    gp.weight_percent,
                    gp.status,
                    gp.created_at,
                    s.name as semester_name,
                    s.academic_year
                FROM grading_periods gp
                INNER JOIN semesters s ON gp.semester_id = s.id
                {$whereClause}
                ORDER BY {$orderBy} {$orderDir}
                LIMIT {$start}, {$length}";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format data for DataTable
        $formattedData = [];
        foreach ($data as $period) {
            $formattedData[] = [
                'id' => $period['id'],
                'semester_id' => $period['semester_id'],
                'semester_name' => $period['semester_name'],
                'academic_year' => $period['academic_year'],
                'name' => ucfirst($period['name']),
                'start_date' => date('M d, Y', strtotime($period['start_date'])),
                'end_date' => date('M d, Y', strtotime($period['end_date'])),
                'weight_percent' => number_format($period['weight_percent'], 2) . '%',
                'status' => ucfirst($period['status']),
                'created_at' => date('M d, Y', strtotime($period['created_at']))
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
            $whereClause = "WHERE (s.name LIKE ? OR s.academic_year LIKE ? OR gp.name LIKE ? OR gp.status LIKE ?)";
            $searchParam = "%{$search}%";
            $params = [$searchParam, $searchParam, $searchParam, $searchParam];
        }
        
        $sql = "SELECT COUNT(*) as count 
                FROM grading_periods gp
                INNER JOIN semesters s ON gp.semester_id = s.id
                {$whereClause}";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
    
    /**
     * Validate grading period dates
     */
    public function validateDates($semesterId, $startDate, $endDate, $excludeId = null) {
        $sql = "SELECT id FROM grading_periods 
                WHERE semester_id = ? 
                AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?))
                AND id != ?";
        
        $params = [$semesterId, $startDate, $startDate, $endDate, $endDate];
        if ($excludeId) {
            $params[] = $excludeId;
        } else {
            $params[] = 0; // Use 0 to exclude nothing when no excludeId
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return empty($conflicts);
    }
    
    /**
     * Get grading periods for dropdown
     */
    public function getForDropdown($semesterId = null) {
        $sql = "SELECT 
                    gp.id,
                    gp.name,
                    gp.start_date,
                    gp.end_date,
                    s.name as semester_name,
                    s.academic_year
                FROM grading_periods gp
                INNER JOIN semesters s ON gp.semester_id = s.id";
        
        $params = [];
        if ($semesterId) {
            $sql .= " WHERE gp.semester_id = ?";
            $params[] = $semesterId;
        }
        
        $sql .= " ORDER BY s.academic_year DESC, gp.start_date ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
