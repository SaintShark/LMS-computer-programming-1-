<?php

require_once 'Db.php';

class Teachers {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Db::getConnection();
    }
    
    /**
     * Create a new teacher
     */
    public function create($data) {
        try {
            $this->pdo->beginTransaction();
            
            // First, create user account
            $userSql = "INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, 'teacher')";
            $userStmt = $this->pdo->prepare($userSql);
            $userStmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT)
            ]);
            
            $userId = $this->pdo->lastInsertId();
            
            // Then, create teacher record
            $teacherSql = "INSERT INTO teachers (user_id, department) VALUES (?, ?)";
            $teacherStmt = $this->pdo->prepare($teacherSql);
            $teacherStmt->execute([$userId, $data['department']]);
            
            $teacherId = $this->pdo->lastInsertId();
            
            $this->pdo->commit();
            return $teacherId;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    /**
     * Get all teachers with user information
     */
    public function getAll() {
        $sql = "SELECT 
                    t.id,
                    t.user_id,
                    t.department,
                    t.created_at,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.role
                FROM teachers t
                INNER JOIN users u ON t.user_id = u.id
                ORDER BY t.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get teachers data for DataTable
     */
    public function getAllForDataTable() {
        $teachers = $this->getAll();
        
        $data = [];
        foreach ($teachers as $teacher) {
            $data[] = [
                'id' => $teacher['id'],
                'user_id' => $teacher['user_id'],
                'full_name' => $teacher['first_name'] . ' ' . $teacher['last_name'],
                'first_name' => $teacher['first_name'],
                'last_name' => $teacher['last_name'],
                'email' => $teacher['email'],
                'department' => $teacher['department'],
                'created_at' => date('M d, Y', strtotime($teacher['created_at'])),
                'status' => 'Active'
            ];
        }
        
        return $data;
    }
    
    /**
     * Get teacher by ID
     */
    public function getById($id) {
        $sql = "SELECT 
                    t.id,
                    t.user_id,
                    t.department,
                    t.created_at,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.role
                FROM teachers t
                INNER JOIN users u ON t.user_id = u.id
                WHERE t.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update teacher
     */
    public function update($id, $data) {
        try {
            $this->pdo->beginTransaction();
            
            // Get teacher info to get user_id
            $teacher = $this->getById($id);
            if (!$teacher) {
                throw new Exception('Teacher not found');
            }
            
            $userId = $teacher['user_id'];
            
            // Update user information
            $userSql = "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?";
            $userStmt = $this->pdo->prepare($userSql);
            $userStmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $userId
            ]);
            
            // Update password if provided
            if (!empty($data['password'])) {
                $passwordSql = "UPDATE users SET password = ? WHERE id = ?";
                $passwordStmt = $this->pdo->prepare($passwordSql);
                $passwordStmt->execute([
                    password_hash($data['password'], PASSWORD_DEFAULT),
                    $userId
                ]);
            }
            
            // Update teacher information
            $teacherSql = "UPDATE teachers SET department = ? WHERE id = ?";
            $teacherStmt = $this->pdo->prepare($teacherSql);
            $teacherStmt->execute([$data['department'], $id]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    /**
     * Delete teacher
     */
    public function delete($id) {
        try {
            $this->pdo->beginTransaction();
            
            // Get teacher info to get user_id
            $teacher = $this->getById($id);
            if (!$teacher) {
                throw new Exception('Teacher not found');
            }
            
            $userId = $teacher['user_id'];
            
            // Check if teacher has any related data
            $checkSql = "SELECT COUNT(*) as count FROM activities WHERE created_by = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$userId]);
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                throw new Exception('Cannot delete teacher with existing activities');
            }
            
            // Delete teacher record
            $teacherSql = "DELETE FROM teachers WHERE id = ?";
            $teacherStmt = $this->pdo->prepare($teacherSql);
            $teacherStmt->execute([$id]);
            
            // Delete user record
            $userSql = "DELETE FROM users WHERE id = ?";
            $userStmt = $this->pdo->prepare($userSql);
            $userStmt->execute([$userId]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            // Get user_id from teacher id
            $teacher = $this->getById($excludeId);
            if ($teacher) {
                $sql .= " AND id != ?";
                $params[] = $teacher['user_id'];
            }
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
    
    /**
     * Get teacher statistics
     */
    public function getStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_teachers,
                    COUNT(CASE WHEN t.department = 'Computer Science' THEN 1 END) as cs_teachers,
                    COUNT(CASE WHEN t.department = 'Information Technology' THEN 1 END) as it_teachers
                FROM teachers t
                INNER JOIN users u ON t.user_id = u.id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get paginated teachers
     */
    public function getPaginated($start = 0, $length = 10, $search = '', $orderColumn = 0, $orderDir = 'desc') {
        $columns = ['t.id', 'u.first_name', 'u.last_name', 'u.email', 't.department', 't.created_at'];
        $orderBy = $columns[$orderColumn] ?? 't.created_at';
        
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR t.department LIKE ?)";
            $searchParam = "%{$search}%";
            $params = [$searchParam, $searchParam, $searchParam, $searchParam];
        }
        
        $sql = "SELECT 
                    t.id,
                    t.user_id,
                    t.department,
                    t.created_at,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.role
                FROM teachers t
                INNER JOIN users u ON t.user_id = u.id
                {$whereClause}
                ORDER BY {$orderBy} {$orderDir}
                LIMIT {$start}, {$length}";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format data for DataTable
        $formattedData = [];
        foreach ($data as $teacher) {
            $formattedData[] = [
                'id' => $teacher['id'],
                'user_id' => $teacher['user_id'],
                'full_name' => $teacher['first_name'] . ' ' . $teacher['last_name'],
                'first_name' => $teacher['first_name'],
                'last_name' => $teacher['last_name'],
                'email' => $teacher['email'],
                'department' => $teacher['department'],
                'created_at' => date('M d, Y', strtotime($teacher['created_at'])),
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
            $whereClause = "WHERE (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR t.department LIKE ?)";
            $searchParam = "%{$search}%";
            $params = [$searchParam, $searchParam, $searchParam, $searchParam];
        }
        
        $sql = "SELECT COUNT(*) as count 
                FROM teachers t
                INNER JOIN users u ON t.user_id = u.id
                {$whereClause}";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
}
?>
