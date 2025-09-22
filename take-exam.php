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

// Include required classes
require_once __DIR__ . '/app/Permissions.php';

// Check if user can take exams (students only)
if (!Permission::canTakeQuizzes() || Permission::canManageQuizzes()) {
    header('Location: index.php');
    exit();
}

// Get exam ID from URL
$examId = $_GET['id'] ?? null;
if (!$examId) {
    header('Location: my-exams.php');
    exit();
}

// Include layout components
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/topNav.php';
require_once __DIR__ . '/components/sideNav.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Take Exam</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="my-exams.php">My Exams</a></li>
                <li class="breadcrumb-item active">Take Exam</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <!-- Loading Screen -->
                <div id="loadingScreen" class="card">
                    <div class="card-body text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h5>Loading Exam...</h5>
                        <p class="text-muted">Please wait while we prepare your exam.</p>
                    </div>
                </div>

                <!-- Exam Information Screen -->
                <div id="examInfoScreen" class="card" style="display: none;">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0" id="examTitle">Exam Title</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div id="examDescription" class="mb-3"></div>
                                
                                <div class="exam-details">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Lesson:</strong> <span id="examLesson"></span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Grading Period:</strong> <span id="examPeriod" class="badge bg-primary"></span>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Total Questions:</strong> <span id="examQuestions"></span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Max Score:</strong> <span id="examMaxScore"></span> points
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Time Limit:</strong> <span id="examTimeLimit"></span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Attempts Used:</strong> <span id="examAttempts"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-warning">
                                    <h6><i class="bi bi-exclamation-triangle"></i> Important Instructions</h6>
                                    <ul class="mb-0 small">
                                        <li>Read all questions carefully before answering</li>
                                        <li>Your answers are automatically saved</li>
                                        <li>You cannot go back once you submit</li>
                                        <li>Make sure you have stable internet connection</li>
                                        <li>Do not refresh or close the browser tab</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="button" class="btn btn-success btn-lg" onclick="startExam()">
                                <i class="bi bi-play-fill"></i> Start Exam
                            </button>
                            <a href="my-exams.php" class="btn btn-outline-secondary btn-lg ms-2">
                                <i class="bi bi-arrow-left"></i> Back to Exams
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Exam Taking Screen -->
                <div id="examTakingScreen" class="card" style="display: none;">
                    <div class="card-header bg-success text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0" id="examTakingTitle">Taking Exam</h5>
                            <div class="exam-timer">
                                <i class="bi bi-clock"></i> <span id="timeRemaining">--:--</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-9">
                                <!-- Question Content -->
                                <div id="questionContainer">
                                    <!-- Questions will be loaded here -->
                                </div>
                                
                                <!-- Navigation Buttons -->
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-outline-secondary" id="prevBtn" onclick="previousQuestion()" disabled>
                                        <i class="bi bi-arrow-left"></i> Previous
                                    </button>
                                    <div>
                                        <button type="button" class="btn btn-primary" id="nextBtn" onclick="nextQuestion()">
                                            Next <i class="bi bi-arrow-right"></i>
                                        </button>
                                        <button type="button" class="btn btn-success" id="submitBtn" onclick="submitExam()" style="display: none;">
                                            <i class="bi bi-check-circle"></i> Submit Exam
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <!-- Question Navigator -->
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Question Navigator</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="questionNavigator" class="question-nav">
                                            <!-- Question numbers will be loaded here -->
                                        </div>
                                        <div class="mt-3">
                                            <div class="d-flex align-items-center mb-2">
                                                <span class="nav-item answered me-2"></span>
                                                <small>Answered</small>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <span class="nav-item current me-2"></span>
                                                <small>Current</small>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <span class="nav-item unanswered me-2"></span>
                                                <small>Not Answered</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Exam Completed Screen -->
                <div id="examCompletedScreen" class="card" style="display: none;">
                    <div class="card-body text-center py-5">
                        <div class="text-success mb-4">
                            <i class="bi bi-check-circle-fill" style="font-size: 4rem;"></i>
                        </div>
                        <h3 class="text-success mb-3">Exam Submitted Successfully!</h3>
                        <p class="lead mb-4">Your exam has been submitted and your answers have been saved.</p>
                        
                        <div class="row justify-content-center mb-4">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5>Exam Summary</h5>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="h4 mb-1" id="totalAnswered">0</div>
                                                <small class="text-muted">Answered</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="h4 mb-1" id="totalQuestions">0</div>
                                                <small class="text-muted">Questions</small>
                                            </div>
                                            <div class="col-4">
                                                <div class="h4 mb-1" id="timeTaken">0</div>
                                                <small class="text-muted">Minutes</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-block">
                            <a href="my-exams.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-arrow-left"></i> Back to My Exams
                            </a>
                            <button type="button" class="btn btn-outline-success btn-lg" id="viewResultBtn" onclick="viewExamResult()">
                                <i class="bi bi-file-text"></i> View Result
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
</main>

<!-- Custom Styles -->
<style>
.question-nav {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
}

.nav-item {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s;
}

.nav-item.unanswered {
    background-color: #f8f9fa;
    border: 2px solid #dee2e6;
    color: #6c757d;
}

.nav-item.answered {
    background-color: #d1ecf1;
    border: 2px solid #bee5eb;
    color: #0c5460;
}

.nav-item.current {
    background-color: #007bff;
    border: 2px solid #0056b3;
    color: white;
}

.nav-item:hover {
    transform: scale(1.05);
}

.exam-timer {
    font-size: 1.2rem;
    font-weight: bold;
}

.question-content {
    min-height: 300px;
}

.choice-item {
    padding: 12px;
    margin: 8px 0;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.choice-item:hover {
    border-color: #007bff;
    background-color: #f8f9fa;
}

.choice-item.selected {
    border-color: #007bff;
    background-color: #e3f2fd;
}

.text-answer {
    min-height: 150px;
    resize: vertical;
}

@media (max-width: 768px) {
    .question-nav {
        grid-template-columns: repeat(4, 1fr);
    }
    
    .nav-item {
        width: 35px;
        height: 35px;
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

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Take Exam JS -->
<script src="assets/js/takeExam.js"></script>

<!-- Pass data to JavaScript -->
<script>
    window.examId = <?php echo json_encode($examId); ?>;
    window.userId = <?php echo json_encode($user['id']); ?>;
    window.userName = <?php echo json_encode($username); ?>;
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
