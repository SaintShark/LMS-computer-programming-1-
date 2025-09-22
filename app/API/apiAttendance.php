<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include required classes
require_once __DIR__ . '/../Db.php';
require_once __DIR__ . '/../Permissions.php';

// Check permissions - only teachers/admins can manage attendance
if (!Permission::canManageQuizzes()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get action from request
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $pdo = Db::getConnection();
    $user = $_SESSION['user'];

    switch ($action) {
        case 'datatable':
            handleDataTableRequest($pdo);
            break;
            
        case 'get_students':
            handleGetStudents($pdo);
            break;
            
        case 'save_attendance':
            handleSaveAttendance($pdo);
            break;
            
        case 'update_attendance':
            handleUpdateAttendance($pdo);
            break;
            
        case 'get_attendance_details':
            handleGetAttendanceDetails($pdo);
            break;
            
        case 'delete_attendance':
            handleDeleteAttendance($pdo);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("API Attendance Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

/**
 * Handle DataTable Request for Attendance Records
 */
function handleDataTableRequest($pdo) {
    $draw = intval($_POST['draw'] ?? 1);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $searchValue = $_POST['search']['value'] ?? '';

    try {
        // Base query to get attendance summary by date and subject
        $baseSQL = "FROM (
                        SELECT 
                            a.attendance_date,
                            a.subject_id,
                            s.name as subject_name,
                            COUNT(*) as total_students,
                            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
                            SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_count
                        FROM attendance a
                        JOIN subjects s ON a.subject_id = s.id
                        GROUP BY a.attendance_date, a.subject_id, s.name
                    ) attendance_summary";

        // Where clause for search
        $whereSQL = " WHERE 1=1";
        $params = [];
        
        if (!empty($searchValue)) {
            $whereSQL .= " AND (subject_name LIKE :search OR attendance_date LIKE :search)";
            $params['search'] = "%$searchValue%";
        }

        // Count total records
        $countSQL = "SELECT COUNT(*) as total $baseSQL $whereSQL";
        $stmt = $pdo->prepare($countSQL);
        $stmt->execute($params);
        $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Main query with pagination
        $dataSQL = "SELECT * $baseSQL $whereSQL 
                    ORDER BY attendance_date DESC, subject_name ASC
                    LIMIT :start, :length";
        
        $stmt = $pdo->prepare($dataSQL);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();
        
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format dates for display
        foreach ($records as &$record) {
            $record['attendance_date'] = date('M j, Y', strtotime($record['attendance_date']));
        }

        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $records
        ]);

    } catch (PDOException $e) {
        error_log("Attendance datatable error: " . $e->getMessage());
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => []
        ]);
    }
}

/**
 * Get Students for Attendance
 */
function handleGetStudents($pdo) {
    $subjectId = $_GET['subject_id'] ?? null;
    $date = $_GET['date'] ?? null;

    if (!$subjectId || !$date) {
        echo json_encode(['success' => false, 'message' => 'Subject ID and date are required']);
        return;
    }

    try {
        // Get enrolled students for the subject
        $studentsSQL = "SELECT u.id, u.first_name, u.last_name, u.email
                        FROM users u
                        JOIN students st ON u.id = st.user_id
                        JOIN enrollments e ON st.id = e.student_id
                        WHERE e.subject_id = :subject_id AND u.role = 'student'
                        ORDER BY u.last_name, u.first_name";
        
        $stmt = $pdo->prepare($studentsSQL);
        $stmt->execute(['subject_id' => $subjectId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($students)) {
            echo json_encode(['success' => false, 'message' => 'No students enrolled in this subject']);
            return;
        }

        // Get existing attendance for this date and subject
        $attendanceSQL = "SELECT student_id, status 
                          FROM attendance 
                          WHERE subject_id = :subject_id AND attendance_date = :date";
        
        $stmt = $pdo->prepare($attendanceSQL);
        $stmt->execute([
            'subject_id' => $subjectId,
            'date' => $date
        ]);
        $existingAttendance = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        echo json_encode([
            'success' => true,
            'students' => $students,
            'attendance' => $existingAttendance
        ]);

    } catch (PDOException $e) {
        error_log("Get students error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

/**
 * Save Attendance
 */
function handleSaveAttendance($pdo) {
    $attendanceDate = $_POST['attendance_date'] ?? null;
    $subjectId = $_POST['subject_id'] ?? null;
    $attendanceData = json_decode($_POST['attendance_data'] ?? '{}', true);

    if (!$attendanceDate || !$subjectId || empty($attendanceData)) {
        echo json_encode(['success' => false, 'message' => 'Missing required data']);
        return;
    }

    try {
        $pdo->beginTransaction();

        // Check if attendance already exists for this date and subject
        $checkSQL = "SELECT COUNT(*) as count FROM attendance 
                     WHERE attendance_date = :date AND subject_id = :subject_id";
        $stmt = $pdo->prepare($checkSQL);
        $stmt->execute([
            'date' => $attendanceDate,
            'subject_id' => $subjectId
        ]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

        if ($exists) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Attendance already exists for this date and subject. Use edit instead.']);
            return;
        }

        // Insert attendance records
        $insertSQL = "INSERT INTO attendance (student_id, subject_id, attendance_date, status) 
                      VALUES (:student_id, :subject_id, :attendance_date, :status)";
        $stmt = $pdo->prepare($insertSQL);

        foreach ($attendanceData as $studentId => $status) {
            $stmt->execute([
                'student_id' => $studentId,
                'subject_id' => $subjectId,
                'attendance_date' => $attendanceDate,
                'status' => $status
            ]);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Attendance saved successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Save attendance error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to save attendance']);
    }
}

/**
 * Update Attendance
 */
function handleUpdateAttendance($pdo) {
    $attendanceDate = $_POST['attendance_date'] ?? null;
    $subjectId = $_POST['subject_id'] ?? null;
    $attendanceData = json_decode($_POST['attendance_data'] ?? '{}', true);

    if (!$attendanceDate || !$subjectId || empty($attendanceData)) {
        echo json_encode(['success' => false, 'message' => 'Missing required data']);
        return;
    }

    try {
        $pdo->beginTransaction();

        // Delete existing attendance for this date and subject
        $deleteSQL = "DELETE FROM attendance 
                      WHERE attendance_date = :date AND subject_id = :subject_id";
        $stmt = $pdo->prepare($deleteSQL);
        $stmt->execute([
            'date' => $attendanceDate,
            'subject_id' => $subjectId
        ]);

        // Insert new attendance records
        $insertSQL = "INSERT INTO attendance (student_id, subject_id, attendance_date, status) 
                      VALUES (:student_id, :subject_id, :attendance_date, :status)";
        $stmt = $pdo->prepare($insertSQL);

        foreach ($attendanceData as $studentId => $status) {
            $stmt->execute([
                'student_id' => $studentId,
                'subject_id' => $subjectId,
                'attendance_date' => $attendanceDate,
                'status' => $status
            ]);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Attendance updated successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Update attendance error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update attendance']);
    }
}

/**
 * Get Attendance Details
 */
function handleGetAttendanceDetails($pdo) {
    $date = $_GET['date'] ?? null;
    $subjectId = $_GET['subject_id'] ?? null;

    if (!$date || !$subjectId) {
        echo json_encode(['success' => false, 'message' => 'Date and subject ID are required']);
        return;
    }

    try {
        // Convert date format for comparison
        $formattedDate = date('Y-m-d', strtotime($date));

        // Get attendance details with student information
        $attendanceSQL = "SELECT a.*, u.first_name, u.last_name, s.name as subject_name
                          FROM attendance a
                          JOIN users u ON a.student_id = u.id
                          JOIN subjects s ON a.subject_id = s.id
                          WHERE a.attendance_date = :date AND a.subject_id = :subject_id
                          ORDER BY u.last_name, u.first_name";
        
        $stmt = $pdo->prepare($attendanceSQL);
        $stmt->execute([
            'date' => $formattedDate,
            'subject_id' => $subjectId
        ]);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($attendance)) {
            echo json_encode(['success' => false, 'message' => 'No attendance records found']);
            return;
        }

        // Group students by status
        $groupedData = [
            'date' => $formattedDate,
            'subject_name' => $attendance[0]['subject_name'],
            'present' => [],
            'absent' => [],
            'late' => [],
            'excused' => []
        ];

        foreach ($attendance as $record) {
            $student = [
                'id' => $record['student_id'],
                'first_name' => $record['first_name'],
                'last_name' => $record['last_name']
            ];
            $groupedData[$record['status']][] = $student;
        }

        echo json_encode([
            'success' => true,
            'data' => $groupedData
        ]);

    } catch (PDOException $e) {
        error_log("Get attendance details error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

/**
 * Delete Attendance
 */
function handleDeleteAttendance($pdo) {
    $attendanceDate = $_POST['attendance_date'] ?? null;
    $subjectId = $_POST['subject_id'] ?? null;

    if (!$attendanceDate || !$subjectId) {
        echo json_encode(['success' => false, 'message' => 'Date and subject ID are required']);
        return;
    }

    try {
        // Convert date format for comparison
        $formattedDate = date('Y-m-d', strtotime($attendanceDate));

        $deleteSQL = "DELETE FROM attendance 
                      WHERE attendance_date = :date AND subject_id = :subject_id";
        $stmt = $pdo->prepare($deleteSQL);
        $result = $stmt->execute([
            'date' => $formattedDate,
            'subject_id' => $subjectId
        ]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Attendance record deleted successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No attendance record found to delete']);
        }

    } catch (PDOException $e) {
        error_log("Delete attendance error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to delete attendance record']);
    }
}
?>
