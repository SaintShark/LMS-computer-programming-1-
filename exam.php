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

// Check if user has permission to view exams
if (!Permission::canManageQuizzes() && !Permission::canViewOwnQuizzes()) {
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
                Exams Management
            <?php elseif (Permission::isTeacher()): ?>
                My Exams Management
            <?php elseif (Permission::isStudent()): ?>
                Exams
            <?php endif; ?>
        </h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Exams</li>
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
                                    All Exams List
                                <?php elseif (Permission::isTeacher()): ?>
                                    My Exams List
                                <?php elseif (Permission::isStudent()): ?>
                                    Available Exams
                                <?php endif; ?>
                            </h5>
                            <div class="btn-group gap-2">
                                <?php if (Permission::canManageQuizzes()): ?>
                                    <button type="button" class="btn btn-outline-success" onclick="exportExamsData()"
                                        title="Export Data">
                                        <i class="bi bi-download"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if (Permission::canAddQuizzes()): ?>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#examModal">
                                        Add New Exam
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="examsTable">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <?php if (Permission::canManageQuizzes()): ?>
                                            <th>Lesson</th>
                                        <?php endif; ?>
                                        <th>Grading Period</th>
                                        <th>Max Score</th>
                                        <th>Time Limit</th>
                                        <?php if (Permission::canManageQuizzes()): ?>
                                            <th>Attempts</th>
                                        <?php endif; ?>
                                        <th>Status</th>
                                        <?php if (Permission::canViewOwnQuizzes()): ?>
                                            <th>Score</th>
                                        <?php endif; ?>
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

<!-- Exam Modal -->
<?php if (Permission::canAddQuizzes()): ?>
    <div class="modal fade" id="examModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Exam</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="examForm">
                        <input type="hidden" id="examId" name="id">
                        <input type="hidden" name="action" id="formAction" value="create_exam">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="lesson_id" class="form-label">
                                    Lesson <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="lesson_id" name="lesson_id" required>
                                    <option value="">Select Lesson</option>
                                    <!-- Options will be populated via JavaScript -->
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="grading_period_id" class="form-label">
                                    Grading Period <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="grading_period_id" name="grading_period_id" required>
                                    <option value="">Select Grading Period</option>
                                    <!-- Options will be populated via JavaScript -->
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="title" class="form-label">
                                Exam Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="title" name="title"
                                placeholder="Enter exam title..." required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">
                                Description
                            </label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                placeholder="Enter exam description/instructions..."></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="max_score" class="form-label">
                                    Max Score <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control" id="max_score" name="max_score" value="100"
                                    min="1" max="1000" placeholder="100" required>
                            </div>
                            <div class="col-md-4">
                                <label for="time_limit_minutes" class="form-label">
                                    Time Limit (minutes) <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control" id="time_limit_minutes"
                                    name="time_limit_minutes" min="30" max="300" placeholder="120" value="120" required>
                            </div>
                            <div class="col-md-4">
                                <label for="attempts_allowed" class="form-label">
                                    Max Attempts
                                </label>
                                <input type="number" class="form-control" id="attempts_allowed"
                                    name="attempts_allowed" value="1" min="1" max="3" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="display_mode" class="form-label">
                                Question Display Mode
                            </label>
                            <select class="form-control" id="display_mode" name="display_mode" required>
                                <option value="all">Show all questions in one page</option>
                                <option value="per_page">Five questions per page</option>
                                <option value="single">One question per page</option>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="open_at" class="form-label">
                                    Open Date/Time <span class="text-danger">*</span>
                                </label>
                                <input type="datetime-local" class="form-control" id="open_at" name="open_at" required>
                            </div>
                            <div class="col-md-6">
                                <label for="close_at" class="form-label">
                                    Close Date/Time <span class="text-danger">*</span>
                                </label>
                                <input type="datetime-local" class="form-control" id="close_at" name="close_at" required>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <h6><i class="bi bi-exclamation-triangle"></i> Exam Guidelines</h6>
                            <ul class="mb-0">
                                <li><strong>Exams are more formal assessments</strong> - typically longer and more comprehensive</li>
                                <li>Default time limit is 120 minutes (2 hours) for thorough evaluation</li>
                                <li>Limited attempts (usually 1-3) to maintain exam integrity</li>
                                <li>Choose appropriate lesson and grading period carefully</li>
                                <li>Set clear open/close dates for exam availability</li>
                                <li>Consider display mode based on exam complexity and length</li>
                                <li>Ensure adequate time for students to complete all questions</li>
                            </ul>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveExam()">
                        <span id="submitButtonText">Submit</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Exam Details Modal -->
<div class="modal fade" id="examDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="examDetailsModalTitle">
                    Exam Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="examDetailsContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Questions Management Modal -->
<?php if (Permission::canManageQuizzes()): ?>
<div class="modal fade" id="questionsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="questionsModalTitle">Manage Questions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="mb-0" id="quizTitleForQuestions">Exam Title</h6>
                        <small class="text-muted">Add and manage questions for this exam</small>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="addNewQuestion()">
                        Add Question
                    </button>
                </div>
                
                <!-- Questions List -->
                <div id="questionsList">
                    <div class="text-center py-4">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading questions...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Question Modal -->
<div class="modal fade" id="questionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="questionModalTitle">Add Question</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="questionForm">
                    <input type="hidden" id="questionId" name="id">
                    <input type="hidden" id="questionQuizId" name="quiz_id">
                    <input type="hidden" name="action" id="questionFormAction" value="create_question">
                    
                    <div class="mb-3">
                        <label for="questionText" class="form-label">
                            Question <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="questionText" name="question_text" rows="3"
                            placeholder="Enter your question..." required></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="questionType" class="form-label">
                                Question Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-control" id="questionType" name="question_type" required onchange="handleQuestionTypeChange()">
                                <option value="">Select Type</option>
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="checkbox">Checkbox (Multi-answer)</option>
                                <option value="text">Text Answer</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="questionScore" class="form-label">
                                Points <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" id="questionScore" name="score" 
                                value="5" min="1" max="100" required>
                        </div>
                    </div>
                    
                    <!-- Choices Section (for MCQ and Checkbox) -->
                    <div id="choicesSection" style="display: none;">
                        <label class="form-label">Answer Choices</label>
                        <div id="choicesList">
                            <!-- Choices will be added dynamically -->
                        </div>
                        <button type="button" class="btn btn-outline-secondary mt-2" onclick="addChoice()">
                            Add Choice
                        </button>
                    </div>
                    
                    <!-- Text Answer Section -->
                    <div id="textAnswerSection" style="display: none;">
                        <div class="alert alert-info">
                            <strong>Note:</strong> Text questions will be graded manually by the teacher.
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveQuestion()">
                    <span id="questionSubmitButtonText">Submit</span>
                </button>
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

<!-- Exams DataTables JS -->
<script src="assets/js/dataTables/examsDataTables.js"></script>

<!-- Pass permissions to JavaScript -->
<script>
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.canManageQuizzes = <?php echo Permission::canManageQuizzes() ? 'true' : 'false'; ?>;
    window.canAddQuizzes = <?php echo Permission::canAddQuizzes() ? 'true' : 'false'; ?>;
    window.canEditQuizzes = <?php echo Permission::canEditQuizzes() ? 'true' : 'false'; ?>;
    window.canDeleteQuizzes = <?php echo Permission::canDeleteQuizzes() ? 'true' : 'false'; ?>;
    window.canViewOwnQuizzes = <?php echo Permission::canViewOwnQuizzes() ? 'true' : 'false'; ?>;
    window.canTakeQuizzes = <?php echo Permission::canTakeQuizzes() ? 'true' : 'false'; ?>;
    window.isAdmin = <?php echo Permission::isAdmin() ? 'true' : 'false'; ?>;
    window.isTeacher = <?php echo Permission::isTeacher() ? 'true' : 'false'; ?>;
    window.isStudent = <?php echo Permission::isStudent() ? 'true' : 'false'; ?>;
    window.userId = <?php echo $user['id']; ?>;
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
