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

// Check if user has permission to view grades
if (!Permission::canManageGrades() && !Permission::canViewOwnGrades()) {
    header('Location: index.php');
    exit();
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
            Grades Management
            <?php elseif (Permission::isTeacher()): ?>
            Student Grades Management
            <?php elseif (Permission::isStudent()): ?>
            My Grades
            <?php endif; ?>
        </h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Grades</li>
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
                                All Student Grades
                                <?php elseif (Permission::isTeacher()): ?>
                                My Students' Grades
                                <?php elseif (Permission::isStudent()): ?>
                                My Academic Grades
                                <?php endif; ?>
                            </h5>
                            <div class="btn-group gap-2">
                                <button type="button" class="btn btn-outline-info" onclick="refreshGradesTable()"
                                    title="Refresh">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                                <?php if (Permission::canManageGrades()): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#gradeModal">
                                    Add New Grade
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="gradesTable">
                                <thead>
                                    <tr>
                                        <?php if (Permission::isStudent()): ?>
                                        <th>Grading Period</th>
                                        <th>Activity Score</th>
                                        <th>Quiz Score</th>
                                        <th>Exam Score</th>
                                        <th>Period Grade</th>
                                        <th>Status</th>
                                        <?php else: ?>
                                        <th>Student Name</th>
                                        <th>Grading Period</th>
                                        <th>Activity Score</th>
                                        <th>Quiz Score</th>
                                        <th>Exam Score</th>
                                        <th>Period Grade</th>
                                        <th>Status</th>
                                        <?php if (Permission::canManageGrades()): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                        <?php endif; ?>
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

<!-- Grade Modal (Admin/Teacher only) -->
<?php if (Permission::canManageGrades()): ?>
<div class="modal fade" id="gradeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Grade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="gradeForm">
                    <input type="hidden" id="gradeId" name="id">
                    <input type="hidden" name="action" id="formAction" value="create_grade">

                    <div id="studentInfo"></div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="student_id" class="form-label">Select Student <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" id="student_id" name="student_id" required>
                                <option value="">Select Student</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="subject_id" class="form-label">Subject <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" id="subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="semester_id" class="form-label">Semester <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" id="semester_id" name="semester_id" required>
                                <option value="">Select Semester</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="grading_period_id" class="form-label">Grading Period <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" id="grading_period_id" name="grading_period_id" required>
                                <option value="">Select Grading Period</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="activity_score" class="form-label">Activity Score <span
                                    class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="activity_score" name="activity_score"
                                min="0" max="100" step="0.01" placeholder="0.00">
                        </div>
                        <div class="col-md-4">
                            <label for="quiz_score" class="form-label">Quiz Score <span
                                    class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quiz_score" name="quiz_score"
                                min="0" max="100" step="0.01" placeholder="0.00">
                        </div>
                        <div class="col-md-4">
                            <label for="exam_score" class="form-label">Exam Score <span
                                    class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="exam_score" name="exam_score"
                                min="0" max="100" step="0.01" placeholder="0.00">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="period_grade" class="form-label">Period Grade <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-light" id="period_grade"
                                name="period_grade" placeholder="0.00" readonly style="cursor: not-allowed;">
                            <div class="form-text">Automatically calculated: Activity (40%) + Quiz (30%) + Exam (30%)
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-outline-info d-block w-100" id="autoCalculateBtn">
                                <i class="bi bi-calculator"></i> Auto Calculate
                            </button>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-light" id="status_display" readonly
                                placeholder="Pending" style="cursor: not-allowed;">
                            <input type="hidden" id="status" name="status" value="pending">
                            <div class="form-text">Automatically set based on grade</div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Grade Calculation</h6>
                        <ul class="mb-0">
                            <li><strong>Activity Score:</strong> 40% of period grade</li>
                            <li><strong>Quiz Score:</strong> 30% of period grade</li>
                            <li><strong>Exam Score:</strong> 30% of period grade</li>
                            <li><strong>Period Grade:</strong> Automatically calculated based on weighted average</li>
                            <li><strong>Status:</strong> Automatically set (Pass ≥75, Fail <75, Pending=0)</li>
                        </ul>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveGrade()">Submit</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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

<!-- Grades DataTables JS -->
<script src="assets/js/dataTables/gradesDataTables.js"></script>

<!-- Pass permissions to JavaScript -->
<script>
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.canManageGrades = <?php echo Permission::canManageGrades() ? 'true' : 'false'; ?>;
    window.canViewOwnGrades = <?php echo Permission::canViewOwnGrades() ? 'true' : 'false'; ?>;
    window.isAdmin = <?php echo Permission::isAdmin() ? 'true' : 'false'; ?>;
    window.isTeacher = <?php echo Permission::isTeacher() ? 'true' : 'false'; ?>;
    window.isStudent = <?php echo Permission::isStudent() ? 'true' : 'false'; ?>;
    window.userId = <?php echo $user['id']; ?>;
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
