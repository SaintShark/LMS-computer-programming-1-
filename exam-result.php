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
require_once __DIR__ . '/app/Db.php';

// Check if user can view exam results (students only)
if (!Permission::canViewOwnQuizzes() || Permission::canManageQuizzes()) {
    header('Location: index.php');
    exit();
}

// Get exam ID from URL
$examId = $_GET['id'] ?? null;
if (!$examId) {
    header('Location: my-exams.php');
    exit();
}

// Get exam result data
try {
    $pdo = Db::getConnection();
    
    // Get exam details and student's attempt
    $sql = "SELECT e.*, l.title as lesson_name, gp.name as grading_period_name,
            CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
            ea.id as attempt_id, ea.score, ea.max_score, ea.started_at, ea.completed_at, 
            ea.time_taken, ea.answers
            FROM exams e
            LEFT JOIN lessons l ON e.lesson_id = l.id
            LEFT JOIN grading_periods gp ON e.grading_period_id = gp.id
            LEFT JOIN users u ON e.created_by = u.id
            LEFT JOIN exam_attempts ea ON e.id = ea.exam_id AND ea.student_id = :student_id
            WHERE e.id = :exam_id
            ORDER BY ea.completed_at DESC
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'exam_id' => $examId,
        'student_id' => $user['id']
    ]);
    
    $examResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$examResult) {
        header('Location: my-exams.php');
        exit();
    }
    
    // Check if student has taken the exam
    if (!$examResult['attempt_id']) {
        header('Location: my-exams.php');
        exit();
    }
    
    // Get exam questions and student's answers
    $questionsSQL = "SELECT eq.*, 
                     GROUP_CONCAT(
                         CONCAT(ec.id, ':', ec.choice_text, ':', ec.is_correct) 
                         ORDER BY ec.order_number SEPARATOR '|'
                     ) as choices
                     FROM exam_questions eq
                     LEFT JOIN exam_choices ec ON eq.id = ec.question_id
                     WHERE eq.exam_id = :exam_id
                     GROUP BY eq.id
                     ORDER BY eq.order_number";
    
    $stmt = $pdo->prepare($questionsSQL);
    $stmt->execute(['exam_id' => $examId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process student answers
    $studentAnswers = json_decode($examResult['answers'], true) ?? [];
    
    // Calculate percentage
    $percentage = $examResult['max_score'] > 0 ? round(($examResult['score'] / $examResult['max_score']) * 100, 2) : 0;
    
    // Determine grade status
    $gradeStatus = $percentage >= 75 ? 'Passed' : 'Failed';
    $gradeClass = $percentage >= 75 ? 'success' : 'danger';
    
} catch (Exception $e) {
    error_log("Exam result error: " . $e->getMessage());
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
        <h1>Exam Result</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="my-exams.php">My Exams</a></li>
                <li class="breadcrumb-item active">Result</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <!-- Exam Result Summary -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Exam Summary</h5>
                        
                        <div class="text-center mb-4">
                            <div class="display-4 fw-bold text-<?php echo $gradeClass; ?>">
                                <?php echo $examResult['score']; ?>/<?php echo $examResult['max_score']; ?>
                            </div>
                            <div class="h5 text-<?php echo $gradeClass; ?>">
                                <?php echo $percentage; ?>% - <?php echo $gradeStatus; ?>
                            </div>
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border-end">
                                    <div class="h6 mb-1"><?php echo count($questions); ?></div>
                                    <small class="text-muted">Questions</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="h6 mb-1">
                                    <?php 
                                    $timeInMinutes = round($examResult['time_taken'] / 60);
                                    echo $timeInMinutes; ?> min
                                </div>
                                <small class="text-muted">Time Taken</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="exam-info">
                            <div class="mb-2">
                                <strong>Exam:</strong> <?php echo htmlspecialchars($examResult['title']); ?>
                            </div>
                            <div class="mb-2">
                                <strong>Lesson:</strong> <?php echo htmlspecialchars($examResult['lesson_name']); ?>
                            </div>
                            <div class="mb-2">
                                <strong>Period:</strong> 
                                <span class="badge bg-primary"><?php echo htmlspecialchars($examResult['grading_period_name']); ?></span>
                            </div>
                            <div class="mb-2">
                                <strong>Teacher:</strong> <?php echo htmlspecialchars($examResult['teacher_name']); ?>
                            </div>
                            <div class="mb-2">
                                <strong>Started:</strong> 
                                <small><?php echo date('M j, Y g:i A', strtotime($examResult['started_at'])); ?></small>
                            </div>
                            <div class="mb-2">
                                <strong>Completed:</strong> 
                                <small><?php echo date('M j, Y g:i A', strtotime($examResult['completed_at'])); ?></small>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <a href="my-exams.php" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-left"></i> Back to My Exams
                            </a>
                            <button type="button" class="btn btn-success" onclick="window.print()">
                                <i class="bi bi-printer"></i> Print Result
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Results -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Question Review</h5>
                        
                        <?php if (!empty($questions)): ?>
                            <?php foreach ($questions as $index => $question): ?>
                                <?php
                                // Process choices
                                $choices = [];
                                if ($question['choices']) {
                                    $choicesData = explode('|', $question['choices']);
                                    foreach ($choicesData as $choice) {
                                        $parts = explode(':', $choice, 3);
                                        if (count($parts) >= 3) {
                                            $choices[] = [
                                                'id' => $parts[0],
                                                'is_correct' => $parts[1] === '1',
                                                'text' => $parts[2]
                                            ];
                                        }
                                    }
                                }
                                
                                // Get student's answer
                                $studentAnswer = $studentAnswers[$question['id']] ?? null;
                                
                                // Determine if answer is correct (simplified logic)
                                $isCorrect = false;
                                if ($question['question_type'] === 'text') {
                                    $isCorrect = !empty($studentAnswer); // Text questions need manual grading
                                } else {
                                    // For multiple choice/checkbox, compare with correct answers
                                    $correctChoices = array_filter($choices, function($c) { return $c['is_correct']; });
                                    if (!empty($correctChoices) && !empty($studentAnswer)) {
                                        if (is_array($studentAnswer)) {
                                            // Checkbox type
                                            $correctIds = array_column($correctChoices, 'id');
                                            $isCorrect = empty(array_diff($correctIds, $studentAnswer)) && empty(array_diff($studentAnswer, $correctIds));
                                        } else {
                                            // Multiple choice
                                            $isCorrect = in_array($studentAnswer, array_column($correctChoices, 'id'));
                                        }
                                    }
                                }
                                ?>
                                
                                <div class="question-result mb-4 p-3 border rounded">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="mb-1">Question <?php echo $index + 1; ?></h6>
                                            <span class="badge bg-primary me-2"><?php echo $question['score']; ?> pts</span>
                                            <?php if ($question['question_type'] === 'text'): ?>
                                                <span class="badge bg-secondary">Text Answer</span>
                                            <?php elseif ($question['question_type'] === 'checkbox'): ?>
                                                <span class="badge bg-warning">Multiple Answer</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">Multiple Choice</span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <?php if ($isCorrect): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle"></i> Correct
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">
                                                    <i class="bi bi-x-circle"></i> <?php echo $question['question_type'] === 'text' ? 'Manual Review' : 'Incorrect'; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="question-text mb-3">
                                        <p><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                                    </div>
                                    
                                    <?php if ($question['question_type'] === 'text'): ?>
                                        <!-- Text Answer -->
                                        <div class="answer-section">
                                            <strong>Your Answer:</strong>
                                            <div class="mt-2 p-2 bg-light rounded">
                                                <?php if (!empty($studentAnswer)): ?>
                                                    <?php echo nl2br(htmlspecialchars($studentAnswer)); ?>
                                                <?php else: ?>
                                                    <em class="text-muted">No answer provided</em>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <!-- Multiple Choice/Checkbox -->
                                        <div class="choices-section">
                                            <?php foreach ($choices as $choice): ?>
                                                <?php
                                                $isStudentChoice = false;
                                                if (is_array($studentAnswer)) {
                                                    $isStudentChoice = in_array($choice['id'], $studentAnswer);
                                                } else {
                                                    $isStudentChoice = $studentAnswer == $choice['id'];
                                                }
                                                
                                                $choiceClass = '';
                                                $choiceIcon = '';
                                                
                                                if ($choice['is_correct'] && $isStudentChoice) {
                                                    $choiceClass = 'bg-success text-white';
                                                    $choiceIcon = '<i class="bi bi-check-circle-fill me-2"></i>';
                                                } elseif ($choice['is_correct']) {
                                                    $choiceClass = 'bg-light text-success border-success';
                                                    $choiceIcon = '<i class="bi bi-check-circle me-2"></i>';
                                                } elseif ($isStudentChoice) {
                                                    $choiceClass = 'bg-danger text-white';
                                                    $choiceIcon = '<i class="bi bi-x-circle-fill me-2"></i>';
                                                } else {
                                                    $choiceClass = 'bg-light';
                                                    $choiceIcon = '<i class="bi bi-circle me-2"></i>';
                                                }
                                                ?>
                                                <div class="choice-item p-2 mb-2 rounded <?php echo $choiceClass; ?>">
                                                    <?php echo $choiceIcon; ?>
                                                    <?php echo htmlspecialchars($choice['text']); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <h6>No Questions Available</h6>
                                <p class="mb-0">This exam doesn't have any questions yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Print Styles -->
<style>
@media print {
    .sidebar, .header, .pagetitle nav, .btn, .footer {
        display: none !important;
    }
    
    .main {
        margin: 0 !important;
        padding: 20px !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        page-break-inside: avoid;
    }
    
    .question-result {
        page-break-inside: avoid;
        margin-bottom: 20px !important;
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

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
