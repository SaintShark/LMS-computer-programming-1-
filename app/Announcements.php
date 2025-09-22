<?php
class Announcements {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new announcement
     */
    public function create($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO announcements (title, message, created_by, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $data['title'],
                $data['message'],
                $data['created_by']
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get all announcements with creator information
     */
    public function getAll() {
        $stmt = $this->pdo->prepare("
            SELECT 
                a.*,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                u.email as created_by_email,
                u.role as created_by_role
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            ORDER BY a.created_at DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get announcements formatted for DataTables
     */
    public function getAllForDataTable() {
        $announcements = $this->getAll();
        $data = [];
        
        foreach ($announcements as $announcement) {
            $data[] = [
                'id' => $announcement['id'],
                'title' => htmlspecialchars($announcement['title']),
                'message' => $this->truncateMessage($announcement['message'], 100),
                'created_by' => htmlspecialchars($announcement['created_by_name']),
                'created_by_role' => htmlspecialchars($announcement['created_by_role']),
                'created_at' => date('M d, Y H:i', strtotime($announcement['created_at'])),
                'time_ago' => $this->timeAgo($announcement['created_at']),
                'actions' => $announcement['id']
            ];
        }
        
        return $data;
    }
    
    /**
     * Get announcement by ID
     */
    public function getById($id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                a.*,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                u.email as created_by_email,
                u.role as created_by_role
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update announcement
     */
    public function update($id, $data) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE announcements 
                SET title = ?, message = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['title'],
                $data['message'],
                $id
            ]);
            
            return true;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Delete announcement
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->execute([$id]);
            return true;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get recent announcements (for dashboard/widgets)
     */
    public function getRecent($limit = 5) {
        $stmt = $this->pdo->prepare("
            SELECT 
                a.*,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                u.email as created_by_email,
                u.role as created_by_role
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            ORDER BY a.created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get announcements by creator
     */
    public function getByCreator($creatorId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                a.*,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                u.email as created_by_email,
                u.role as created_by_role
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            WHERE a.created_by = ?
            ORDER BY a.created_at DESC
        ");
        
        $stmt->execute([$creatorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get announcements statistics
     */
    public function getStatistics() {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_announcements,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_announcements,
                COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_announcements,
                COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as month_announcements
            FROM announcements
        ");
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get announcements by role (for role-based filtering)
     */
    public function getByRole($role = null) {
        $sql = "
            SELECT 
                a.*,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                u.email as created_by_email,
                u.role as created_by_role
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
        ";
        
        $params = [];
        if ($role) {
            $sql .= " WHERE u.role = ?";
            $params[] = $role;
        }
        
        $sql .= " ORDER BY a.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Search announcements
     */
    public function search($searchTerm) {
        $stmt = $this->pdo->prepare("
            SELECT 
                a.*,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                u.email as created_by_email,
                u.role as created_by_role
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            WHERE a.title LIKE ? OR a.message LIKE ?
            ORDER BY a.created_at DESC
        ");
        
        $searchTerm = "%$searchTerm%";
        $stmt->execute([$searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get announcements with pagination for DataTables server-side processing
     */
    public function getPaginated($start, $length, $search = '', $orderColumn = 0, $orderDir = 'desc') {
        $columns = ['id', 'title', 'created_by_name', 'created_at'];
        $orderColumn = $columns[$orderColumn] ?? 'id';
        
        $searchCondition = '';
        $params = [];
        
        if (!empty($search)) {
            $searchCondition = "WHERE a.title LIKE ? OR a.message LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        // Get total count
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            $searchCondition
        ");
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetchColumn();
        
        // Get filtered count
        $filteredRecords = $totalRecords;
        
        // Get data
        $dataStmt = $this->pdo->prepare("
            SELECT 
                a.*,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                u.email as created_by_email,
                u.role as created_by_role
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            $searchCondition
            ORDER BY $orderColumn $orderDir
            LIMIT $start, $length
        ");
        $dataStmt->execute($params);
        $announcements = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format data for DataTables
        $data = [];
        foreach ($announcements as $announcement) {
            $data[] = [
                'id' => $announcement['id'],
                'title' => htmlspecialchars($announcement['title']),
                'message' => $this->truncateMessage($announcement['message'], 100),
                'created_by' => htmlspecialchars($announcement['created_by_name']),
                'created_by_role' => htmlspecialchars($announcement['created_by_role']),
                'created_at' => date('M d, Y H:i', strtotime($announcement['created_at'])),
                'time_ago' => $this->timeAgo($announcement['created_at']),
                'actions' => $announcement['id']
            ];
        }
        
        return [
            'data' => $data,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords
        ];
    }
    
    /**
     * Truncate message for display
     */
    private function truncateMessage($message, $length = 100) {
        if (strlen($message) <= $length) {
            return htmlspecialchars($message);
        }
        
        return htmlspecialchars(substr($message, 0, $length)) . '...';
    }
    
    /**
     * Calculate time ago
     */
    private function timeAgo($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) {
            return 'Just now';
        } elseif ($time < 3600) {
            return floor($time / 60) . ' minutes ago';
        } elseif ($time < 86400) {
            return floor($time / 3600) . ' hours ago';
        } elseif ($time < 2592000) {
            return floor($time / 86400) . ' days ago';
        } else {
            return date('M d, Y', strtotime($datetime));
        }
    }
    
    /**
     * Get announcements count by creator
     */
    public function getCountByCreator() {
        $stmt = $this->pdo->prepare("
            SELECT 
                CONCAT(u.first_name, ' ', u.last_name) as full_name,
                u.email,
                u.role,
                COUNT(a.id) as announcement_count
            FROM users u
            LEFT JOIN announcements a ON u.id = a.created_by
            WHERE u.role IN ('admin', 'teacher')
            GROUP BY u.id, u.first_name, u.last_name, u.email, u.role
            ORDER BY announcement_count DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get monthly announcements count (for charts)
     */
    public function getMonthlyCount($months = 12) {
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count
            FROM announcements
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ");
        
        $stmt->execute([$months]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
