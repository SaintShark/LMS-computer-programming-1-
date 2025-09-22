<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Check if logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Get user information
$user = $_SESSION['user'];
$userRole = $user['role'];
$username = $user['first_name'] . ' ' . $user['last_name'];

// Include Permissions class
require_once __DIR__ . '/app/Permissions.php';
require_once __DIR__ . '/app/Db.php';

// Check if user has permission to manage attendance (teachers/admins only)
if (!Permission::canManageQuizzes()) {
    header('Location: index.php');
    exit();
}

// Get subjects for the teacher/admin
try {
    $pdo = Db::getConnection();

    if (Permission::isAdmin()) {
        // Admin can see all subjects
        $subjectsSQL = 'SELECT id, name FROM subjects ORDER BY name';
        $stmt = $pdo->prepare($subjectsSQL);
        $stmt->execute();
    } else {
        // Teacher can only see their subjects (for now, we'll show all since we don't have teacher-subject mapping)
        $subjectsSQL = 'SELECT id, name FROM subjects ORDER BY name';
        $stmt = $pdo->prepare($subjectsSQL);
        $stmt->execute();
    }

    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Attendance page error: ' . $e->getMessage());
    $subjects = [];
}

// Include layout components
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/topNav.php';
require_once __DIR__ . '/components/sideNav.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>
            <?php if (Permission::isAdmin()): ?>
            Attendance Management
            <?php else: ?>
            My Class Attendance
            <?php endif; ?>
        </h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Attendance</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title">
                                <?php if (Permission::isAdmin()): ?>
                                All Classes Attendance
                                <?php else: ?>
                                My Class Attendance
                                <?php endif; ?>
                            </h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                data-bs-target="#attendanceModal">
                                Add Attendance
                            </button>
                        </div>

                        <!-- Attendance History Table -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Subject</th>
                                        <th>Total Students</th>
                                        <th>Present</th>
                                        <th>Absent</th>
                                        <th>Late</th>
                                        <th>Excused</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Add/Edit Attendance Modal -->
<div class="modal fade" id="attendanceModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attendanceModalTitle">Add Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Date and Subject Selection -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="attendanceDate" class="form-label">
                            Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" class="form-control" id="attendanceDate" name="attendance_date"
                            value="<?php echo date('Y-m-d'); ?>" required onchange="loadStudentsForAttendance()">
                    </div>
                    <div class="col-md-6">
                        <label for="subjectId" class="form-label">
                            Subject <span class="text-danger">*</span>
                        </label>
                        <select class="form-control" id="subjectId" name="subject_id" required
                            onchange="loadStudentsForAttendance()">
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Search Box -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="studentSearch" class="form-label">Search Students</label>
                        <input type="text" class="form-control" id="studentSearch"
                            placeholder="Type student name to search..." onkeyup="filterStudents()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Quick Actions</label>
                        <div class="btn-group w-100">
                            <button type="button" class="btn btn-outline-success" onclick="markAllAs('present')">
                                <i class="bi bi-check-all"></i> All Present
                            </button>
                            <button type="button" class="btn btn-outline-danger" onclick="markAllAs('absent')">
                                <i class="bi bi-x-circle"></i> All Absent
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Students List -->
                <div id="studentsContainer">
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-people fs-1"></i>
                        <p class="mt-2">Please select date and subject to load students</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveAttendance()" id="saveAttendanceBtn"
                    disabled>
                    <i class="bi bi-save"></i> <span id="saveAttendanceText">Save Attendance</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Attendance Details Modal -->
<div class="modal fade" id="viewAttendanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewAttendanceModalTitle">Attendance Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="attendanceDetailsContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editAttendanceBtn" onclick="editAttendance()">
                    <i class="bi bi-pencil"></i> Edit Attendance
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles -->
<style>
    .student-attendance-item {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        transition: all 0.2s;
    }

    .student-attendance-item:hover {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .student-info {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }

    .student-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(45deg, #007bff, #0056b3);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        margin-right: 12px;
    }

    .attendance-options {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .attendance-option {
        flex: 1;
        min-width: 80px;
    }

    .attendance-option input[type="radio"] {
        display: none;
    }

    .attendance-option label {
        display: block;
        padding: 8px 12px;
        text-align: center;
        border: 2px solid #dee2e6;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.9rem;
    }

    .attendance-option input[type="radio"]:checked+label {
        font-weight: bold;
    }

    .attendance-option input[value="present"]:checked+label {
        background-color: #d1ecf1;
        border-color: #28a745;
        color: #155724;
    }

    .attendance-option input[value="absent"]:checked+label {
        background-color: #f8d7da;
        border-color: #dc3545;
        color: #721c24;
    }

    .attendance-option input[value="late"]:checked+label {
        background-color: #fff3cd;
        border-color: #ffc107;
        color: #856404;
    }

    .attendance-option input[value="excused"]:checked+label {
        background-color: #e2e3e5;
        border-color: #6c757d;
        color: #383d41;
    }

    .student-hidden {
        display: none !important;
    }

    .attendance-stats {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat-card {
        flex: 1;
        text-align: center;
        padding: 10px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }

    .stat-number {
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 0.9rem;
        color: #6c757d;
    }

    @media (max-width: 768px) {
        .attendance-options {
            flex-direction: column;
        }

        .attendance-option {
            min-width: unset;
        }
    }
</style>

<!-- Vendor JS Files -->
<script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/chart.js/chart.umd.js"></script>
<script src="assets/vendor/echarts/echarts.min.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="assets/vendor/tinymce/tinymce.min.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>

<!-- Template Main JS File -->
<script src="assets/js/main.js"></script>

<!-- jQuery -->
<script src="assets/jquery/jquery-3.7.1.min.js"></script>

<!-- DataTables JS -->
<script src="assets/js/dataTables/dataTables.js"></script>
<script src="assets/js/dataTables/dataTables.bootstrap5.js"></script>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Attendance DataTables JS -->
<script src="assets/js/dataTables/attendanceDataTables.js"></script>

<!-- Pass permissions to JavaScript -->
<script>
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.canManageQuizzes = <?php echo Permission::canManageQuizzes() ? 'true' : 'false'; ?>;
    window.isAdmin = <?php echo Permission::isAdmin() ? 'true' : 'false'; ?>;
    window.isTeacher = <?php echo Permission::isTeacher() ? 'true' : 'false'; ?>;
    window.userId = <?php echo $user['id']; ?>;
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
