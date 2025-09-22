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

// Check if user has permission to manage activities
if (!Permission::canManageActivities()) {
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
            Student Activity Submissions Management
            <?php elseif (Permission::isTeacher()): ?>
            My Students' Activity Submissions
            <?php endif; ?>
        </h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Student Submissions</li>
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
                                All Student Activity Submissions
                                <?php elseif (Permission::isTeacher()): ?>
                                My Students' Activity Submissions
                                <?php endif; ?>
                            </h5>
                            <div class="d-flex gap-2 align-items-center">
                                <select class="form-select" id="teacherActivitiesGradingPeriodFilter" style="width: auto;" onchange="filterByGradingPeriod()">
                                    <option value="">All Grading Periods</option>
                                    <option value="prelim">Prelim</option>
                                    <option value="midterm">Midterm</option>
                                    <option value="finals">Finals</option>
                                </select>
                                <select class="form-select" id="teacherActivitiesStatusFilter" style="width: auto;" onchange="filterByStatus()">
                                    <option value="">All Status</option>
                                    <option value="submitted">Submitted</option>
                                    <option value="unsubmitted">Unsubmitted</option>
                                </select>
                                <button type="button" class="btn btn-outline-info" onclick="testTeacherActivityFilters()" title="Test Filters">
                                    <i class="bi bi-bug"></i> Test
                                </button>
                                <?php if (Permission::canManageActivities()): ?>
                                <button type="button" class="btn btn-outline-success" onclick="exportSubmissionsData()"
                                    title="Export Data">
                                    <i class="bi bi-download"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="submissionsTable">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Activity Title</th>
                                        <th>Subject</th>
                                        <th>Grading Period</th>
                                        <th>Status</th>
                                        <th>Grade</th>
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

<!-- Submission Details Modal -->
<div class="modal fade" id="submissionDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="submissionDetailsTitle">Submission Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="submissionDetailsContent">
                    <!-- Submission details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Grade Submission Modal -->
<div class="modal fade" id="gradeSubmissionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gradeSubmissionTitle">Grade Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="gradeSubmissionForm">
                    <input type="hidden" id="gradeSubmissionId" name="submission_id">
                    <input type="hidden" id="gradeAction" name="action" value="grade_submission">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gradeScore" class="form-label">
                                    Score
                                </label>
                                <input type="number" class="form-control" id="gradeScore" name="score" min="0"
                                    max="100" step="0.01" required>
                                <div class="form-text">Enter score out of 100</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gradeMaxScore" class="form-label">
                                    Max Score
                                </label>
                                <input type="number" class="form-control" id="gradeMaxScore" name="max_score"
                                    min="1" max="100" step="0.01" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="gradeComments" class="form-label">
                            Comments
                        </label>
                        <textarea class="form-control" id="gradeComments" name="comments" rows="4"
                            placeholder="Add feedback and comments for the student..."></textarea>
                    </div>

                    <div class="alert alert-info">
                        <strong>Note:</strong> Grades will be visible to students once submitted.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitGrade()">
                    Submit
                </button>
            </div>
        </div>
    </div>
</div>

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

<!-- Teacher Activities DataTables JS -->
<script src="assets/js/dataTables/teacherActivitiesDataTables.js"></script>

<!-- Pass permissions to JavaScript -->
<script>
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.canManageActivities = <?php echo Permission::canManageActivities() ? 'true' : 'false'; ?>;
    window.isAdmin = <?php echo Permission::isAdmin() ? 'true' : 'false'; ?>;
    window.isTeacher = <?php echo Permission::isTeacher() ? 'true' : 'false'; ?>;
    window.userId = <?php echo $user['id']; ?>;
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
