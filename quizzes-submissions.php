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

// Check if user has permission to manage quizzes
if (!Permission::canManageQuizzes()) {
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
            Student Quiz Submissions Management
            <?php elseif (Permission::isTeacher()): ?>
            My Students' Quiz Submissions
            <?php endif; ?>
        </h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Quiz Submissions</li>
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
                                All Student Quiz Submissions
                                <?php elseif (Permission::isTeacher()): ?>
                                My Students' Quiz Submissions
                                <?php endif; ?>
                            </h5>
                            <div class="d-flex gap-2 align-items-center">
                                <select class="form-select" id="quizSubmissionsGradingPeriodFilter" style="width: auto;" onchange="filterByGradingPeriod()">
                                    <option value="">All Grading Periods</option>
                                    <option value="prelim">Prelim</option>
                                    <option value="midterm">Midterm</option>
                                    <option value="finals">Finals</option>
                                </select>
                                <select class="form-select" id="quizSubmissionsStatusFilter" style="width: auto;" onchange="filterByStatus()">
                                    <option value="">All Status</option>
                                    <option value="submitted">Submitted</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="timeout">Timeout</option>
                                </select>
                                <button type="button" class="btn btn-outline-info" onclick="testFilters()" title="Test Filters">
                                    <i class="bi bi-bug"></i> Test
                                </button>
                                <?php if (Permission::canManageQuizzes()): ?>
                                <button type="button" class="btn btn-outline-success" onclick="exportQuizSubmissionsData()"
                                    title="Export Data">
                                    <i class="bi bi-download"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="quizSubmissionsTable">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Quiz Title</th>
                                        <th>Subject</th>
                                        <th>Grading Period</th>
                                        <th>Attempt</th>
                                        <th>Score</th>
                                        <th>Status</th>
                                        <th>Submitted At</th>
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

<!-- Quiz Submission Details Modal -->
<div class="modal fade" id="quizSubmissionDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quizSubmissionDetailsTitle">Quiz Submission Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="quizSubmissionDetailsContent">
                    <!-- Quiz submission details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printQuizSubmissionBtn" onclick="printQuizSubmission()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Grade Quiz Submission Modal -->
<div class="modal fade" id="gradeQuizSubmissionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gradeQuizSubmissionTitle">Review Quiz Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="gradeQuizSubmissionForm">
                    <input type="hidden" id="gradeQuizSubmissionId" name="submission_id">
                    <input type="hidden" id="gradeQuizAction" name="action" value="grade_quiz_submission">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="quizScore" class="form-label">
                                    Score Achieved
                                </label>
                                <input type="number" class="form-control" id="quizScore" name="score" min="0"
                                    step="0.01" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="quizMaxScore" class="form-label">
                                    Max Score
                                </label>
                                <input type="number" class="form-control" id="quizMaxScore" name="max_score"
                                    readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="quizPercentage" class="form-label">
                                    Percentage
                                </label>
                                <input type="text" class="form-control" id="quizPercentage" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="quizTimeSpent" class="form-label">
                                    Time Spent
                                </label>
                                <input type="text" class="form-control" id="quizTimeSpent" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="quizAttemptNumber" class="form-label">
                                    Attempt Number
                                </label>
                                <input type="text" class="form-control" id="quizAttemptNumber" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="quizTeacherComments" class="form-label">
                            Teacher Comments
                        </label>
                        <textarea class="form-control" id="quizTeacherComments" name="teacher_comments" rows="4"
                            placeholder="Add feedback and comments for the student..."></textarea>
                    </div>

                    <div class="alert alert-info">
                        <strong>Note:</strong> Comments will be visible to students. Quiz scores are automatically calculated.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitQuizGrade()">
                    Save Comments
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

<!-- Quiz Submissions DataTables JS -->
<script src="assets/js/dataTables/quizSubmissionsDataTables.js"></script>

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
