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

// Check if user can take quizzes (students only)
if (!Permission::canTakeQuizzes()) {
    header('Location: index.php');
    exit();
}

// Get quiz ID from URL
$quizId = (int)($_GET['id'] ?? 0);
if ($quizId <= 0) {
    header('Location: my-quizzes.php');
    exit();
}

// Include layout components
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/topNav.php';
require_once __DIR__ . '/components/sideNav.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Take Quiz</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="my-quizzes.php">My Quizzes</a></li>
                <li class="breadcrumb-item active">Take Quiz</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Quiz Loading Screen -->
        <div id="quizLoadingScreen" class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h5 class="mt-3">Loading Quiz...</h5>
                        <p class="text-muted">Please wait while we prepare your quiz.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quiz Information Screen -->
        <div id="quizInfoScreen" class="row" style="display: none;">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h3 id="quizTitle">Quiz Title</h3>
                            <p class="text-muted" id="quizDescription">Quiz Description</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Quiz Information</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li><strong>Time Limit:</strong> <span id="timeLimit">N/A</span></li>
                                            <li><strong>Max Score:</strong> <span id="maxScore">N/A</span></li>
                                            <li><strong>Attempts Allowed:</strong> <span id="attemptsAllowed">N/A</span></li>
                                            <li><strong>Total Questions:</strong> <span id="totalQuestions">N/A</span></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Instructions</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li>• Read each question carefully</li>
                                            <li>• You can navigate between questions</li>
                                            <li>• Your progress is automatically saved</li>
                                            <li>• Click "Finish Attempt" when done</li>
                                            <li id="timerInstruction">• The timer will start when you begin</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-secondary me-2" onclick="goBackToQuizzes()">
                                <i class="bi bi-arrow-left"></i> Back to Quizzes
                            </button>
                            <button type="button" class="btn btn-primary btn-lg" onclick="startQuiz()">
                                <i class="bi bi-play-circle"></i> Start Quiz
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quiz Taking Screen -->
        <div id="quizTakingScreen" class="row" style="display: none;">
            <div class="col-lg-12">
                <!-- Timer and Progress Bar -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0" id="quizTitleInProgress">Quiz Title</h6>
                                <small class="text-muted">Question <span id="currentQuestionNumber">1</span> of <span id="totalQuestionsCount">0</span></small>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div id="timerDisplay" class="badge bg-primary fs-6" style="display: none;">
                                    <i class="bi bi-clock"></i> <span id="timeRemaining">00:00</span>
                                </div>
                                <div class="progress" style="width: 150px;">
                                    <div class="progress-bar" role="progressbar" id="progressBar" 
                                         style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Question Content -->
                <div class="card">
                    <div class="card-body">
                        <form id="quizForm">
                            <div id="questionsContainer">
                                <!-- Questions will be loaded here -->
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Navigation Controls -->
                <div class="card mt-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <button type="button" class="btn btn-outline-secondary" id="prevBtn" onclick="previousQuestion()" disabled>
                                    <i class="bi bi-arrow-left"></i> Previous
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="nextBtn" onclick="nextQuestion()">
                                    Next <i class="bi bi-arrow-right"></i>
                                </button>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-info me-2" onclick="showQuestionNavigator()">
                                    <i class="bi bi-list-ol"></i> Question Navigator
                                </button>
                                <button type="button" class="btn btn-success" onclick="finishQuiz()">
                                    <i class="bi bi-check-circle"></i> Finish Attempt
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quiz Completed Screen -->
        <div id="quizCompletedScreen" class="row" style="display: none;">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h3 class="text-success">Quiz Completed!</h3>
                        <p class="text-muted mb-4">Your answers have been submitted successfully.</p>
                        
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Your Result</h6>
                                        <div id="quizResults">
                                            <!-- Results will be loaded here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="button" class="btn btn-primary" onclick="goBackToQuizzes()">
                                <i class="bi bi-arrow-left"></i> Back to My Quizzes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Question Navigator Modal -->
<div class="modal fade" id="questionNavigatorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Question Navigator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Click on any question number to jump to that question.</p>
                <div id="questionNavigatorList">
                    <!-- Question numbers will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmationTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="confirmationMessage">Are you sure?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmationButton" onclick="confirmAction()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="assets/jquery/jquery-3.7.1.min.js"></script>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Take Quiz JS -->
<script src="assets/js/takeQuiz.js"></script>

<!-- Pass quiz ID and user info to JavaScript -->
<script>
    window.quizId = <?php echo $quizId; ?>;
    window.userId = <?php echo $user['id']; ?>;
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.isStudent = <?php echo Permission::isStudent() ? 'true' : 'false'; ?>;
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
