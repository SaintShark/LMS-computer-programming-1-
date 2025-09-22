<?php
class Activities {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new activity
     */
    public function create($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO activities (subject_id, grading_period_id, title, description, activity_file, allow_from, due_date, cutoff_date, reminder_date, deduction_percent, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['subject_id'],
            $data['grading_period_id'],
            $data['title'],
            $data['description'],
            $data['activity_file'] ?? null,
            $data['allow_from'],
            $data['due_date'],
            $data['cutoff_date'] ?? null,
            $data['reminder_date'] ?? null,
            $data['deduction_percent'] ?? 0,
            $data['status'] ?? 'active'
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Get all activities
     */
    public function getAll() {
        $stmt = $this->pdo->prepare("
            SELECT 
                a.*,
                s.name as subject_name,
                gp.name as grading_period_name,
                COUNT(asub.id) as submission_count
            FROM activities a
            LEFT JOIN subjects s ON a.subject_id = s.id
            LEFT JOIN grading_periods gp ON a.grading_period_id = gp.id
            LEFT JOIN activity_submissions asub ON a.id = asub.activity_id
            GROUP BY a.id
            ORDER BY a.created_at DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get activities formatted for DataTables
     */
    public function getAllForDataTable() {
        $activities = $this->getAll();
        $data = [];
        
        foreach ($activities as $activity) {
            $data[] = [
                'id' => $activity['id'],
                'title' => htmlspecialchars($activity['title']),
                'description' => htmlspecialchars($activity['description'] ?? ''),
                'due_date' => $activity['due_date'],
                'grading_period_name' => htmlspecialchars($activity['grading_period_name'] ?? 'No Period'),
                'status' => $activity['status'],
                'actions' => $activity['id']
            ];
        }
        
        return $data;
    }
    
    /**
     * Get activity by ID
     */
    public function getById($id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                a.*,
                s.name as subject_name,
                gp.name as grading_period_name
            FROM activities a
            LEFT JOIN subjects s ON a.subject_id = s.id
            LEFT JOIN grading_periods gp ON a.grading_period_id = gp.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update activity
     */
    public function update($id, $data) {
        $stmt = $this->pdo->prepare("
            UPDATE activities 
            SET subject_id = ?, grading_period_id = ?, title = ?, description = ?, activity_file = ?, allow_from = ?, due_date = ?, cutoff_date = ?, reminder_date = ?, deduction_percent = ?, status = ? 
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['subject_id'],
            $data['grading_period_id'],
            $data['title'],
            $data['description'],
            $data['activity_file'] ?? null,
            $data['allow_from'],
            $data['due_date'],
            $data['cutoff_date'] ?? null,
            $data['reminder_date'] ?? null,
            $data['deduction_percent'] ?? 0,
            $data['status'] ?? 'active',
            $id
        ]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Delete activity
     */
    public function delete($id) {
        // Check if there are submissions for this activity
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM activity_submissions WHERE activity_id = ?");
        $stmt->execute([$id]);
        $submissionCount = $stmt->fetchColumn();
        
        if ($submissionCount > 0) {
            throw new Exception('Cannot delete activity with existing submissions');
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM activities WHERE id = ?");
        $stmt->execute([$id]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get activity status based on dates
     */
    private function getActivityStatus($activity) {
        $now = date('Y-m-d');
        $dueDate = $activity['due_date'];
        $allowFrom = $activity['allow_from'];
        
        if ($allowFrom && $now < $allowFrom) {
            return '<span class="badge bg-secondary">Not Started</span>';
        } elseif ($dueDate && $now > $dueDate) {
            return '<span class="badge bg-danger">Overdue</span>';
        } elseif ($dueDate && $now == $dueDate) {
            return '<span class="badge bg-warning">Due Today</span>';
        } else {
            return '<span class="badge bg-success">Active</span>';
        }
    }
    
    /**
     * Get activities with pagination for DataTables server-side processing
     */
    public function getPaginated($start, $length, $search = '', $orderColumn = 0, $orderDir = 'desc') {
        $columns = ['id', 'title', 'max_score', 'due_date', 'submission_count', 'status'];
        $orderColumn = $columns[$orderColumn] ?? 'id';
        
        $searchCondition = '';
        $params = [];
        
        if (!empty($search)) {
            $searchCondition = "WHERE a.title LIKE ? OR a.description LIKE ?";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        // Get total count
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM activities a 
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
                COUNT(sa.id) as submission_count,
                COUNT(CASE WHEN sa.grading_status = 'graded' THEN 1 END) as graded_count
            FROM activities a
            LEFT JOIN submitted_activities sa ON a.id = sa.activity_id
            $searchCondition
            GROUP BY a.id
            ORDER BY $orderColumn $orderDir
            LIMIT $start, $length
        ");
        $dataStmt->execute($params);
        $activities = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format data for DataTables
        $data = [];
        foreach ($activities as $activity) {
            $data[] = [
                'id' => $activity['id'],
                'title' => htmlspecialchars($activity['title']),
                'max_score' => $activity['max_score'],
                'due_date' => $activity['due_date'] ? date('M d, Y', strtotime($activity['due_date'])) : 'No due date',
                'submission_count' => $activity['submission_count'],
                'graded_count' => $activity['graded_count'],
                'status' => $this->getActivityStatus($activity),
                'actions' => $activity['id']
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
