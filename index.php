<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Check if logged in - updated to work with login.php session structure
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header('Location: login.php');
    exit(); 
}

// Get user information from session
$user = $_SESSION['user'];
$userRole = $user['role'];
$username = $user['first_name'] . ' ' . $user['last_name'];

// Get dashboard statistics from database
require_once __DIR__ . '/app/Db.php';
try {
    $pdo = Db::getConnection();

    // Get total counts for admin
    if ($userRole === 'admin') {
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
        $totalUsers = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
        $totalStudents = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'");
        $totalTeachers = $stmt->fetchColumn();

        $stmt = $pdo->query('SELECT COUNT(*) as count FROM activities');
        $totalActivities = $stmt->fetchColumn();
    }

    // Get teacher-specific counts
    if ($userRole === 'teacher') {
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM students');
        $myStudents = $stmt->fetchColumn();

        $stmt = $pdo->query('SELECT COUNT(*) as count FROM activities');
        $myActivities = $stmt->fetchColumn();

        $stmt = $pdo->query('SELECT COUNT(*) as count FROM quizzes');
        $myQuizzes = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM submitted_activities WHERE grading_status = 'not graded'");
        $pendingGrades = $stmt->fetchColumn();
    }

    // Get student-specific counts
    if ($userRole === 'student') {
        // Get student ID
        $stmt = $pdo->prepare('SELECT id FROM students WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $studentId = $stmt->fetchColumn();

        if ($studentId) {
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM activities');
            $myActivities = $stmt->fetchColumn();

            $stmt = $pdo->query('SELECT COUNT(*) as count FROM quizzes');
            $myQuizzes = $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM submitted_activities WHERE student_id = ?');
            $stmt->execute([$studentId]);
            $mySubmissions = $stmt->fetchColumn();

            // Get average grade
            $stmt = $pdo->prepare('SELECT AVG(final_grade) as avg_grade FROM grades WHERE student_id = ?');
            $stmt->execute([$studentId]);
            $avgGrade = $stmt->fetchColumn();
            $myGrade = $avgGrade ? number_format($avgGrade, 2) : 'N/A';
        }
    }
} catch (Exception $e) {
    // Handle database errors gracefully
    error_log('Dashboard statistics error: ' . $e->getMessage());
}

// Include layout components
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/topNav.php';
require_once __DIR__ . '/components/sideNav.php';
?>

<main id="main" class="main" style="background: #f6f9ff;">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Welcome to Learning Management System</h5>
                        <p class="card-text">
                            Hello, <strong><?php echo htmlspecialchars($username); ?></strong>!
                            You are logged in as: <span class="badge bg-primary"><?php echo ucfirst($userRole); ?></span>
                        </p>

                        <?php if ($userRole === 'admin'): ?>
                        <div class="alert alert-info">
                            <h6><i class="bi bi-shield-check"></i> Administrator Dashboard</h6>
                            <p>You have full access to manage users, activities, quizzes, and system settings.</p>
                        </div>

                        <!-- Admin Quick Stats -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4 class="mb-0"><?php echo $totalUsers ?? '0'; ?></h4>
                                                <p class="mb-0">Total Users</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-people fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4 class="mb-0"><?php echo $totalStudents ?? '0'; ?></h4>
                                                <p class="mb-0">Students</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-person-check fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4 class="mb-0"><?php echo $totalTeachers ?? '0'; ?></h4>
                                                <p class="mb-0">Teachers</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-person-workspace fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4 class="mb-0"><?php echo $totalActivities ?? '0'; ?></h4>
                                                <p class="mb-0">Activities</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-file-earmark-text fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php elseif ($userRole === 'teacher'): ?>
                        <div class="alert alert-success">
                            <h6><i class="bi bi-person-workspace"></i> Teacher Dashboard</h6>
                            <p>You can create activities, quizzes, grade submissions, and manage your students.</p>
                        </div>

                        <!-- Teacher Quick Stats -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4 class="mb-0"><?php echo $myStudents ?? '0'; ?></h4>
                                                <p class="mb-0">My Students</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-people fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4 class="mb-0"><?php echo $myActivities ?? '0'; ?></h4>
                                                <p class="mb-0">My Activities</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-file-earmark-text fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4 class="mb-0"><?php echo $myQuizzes ?? '0'; ?></h4>
                                                <p class="mb-0">My Quizzes</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-question-circle fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4 class="mb-0"><?php echo $pendingGrades ?? '0'; ?></h4>
                                                <p class="mb-0">Pending Grades</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-clock fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php elseif ($userRole === 'student'): ?>
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-person"></i> Student Dashboard</h6>
                            <p>You can view activities, submit assignments, take quizzes, and check your grades.</p>
                        </div>

                        <!-- Student Quick Stats -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4 class="mb-0"><?php echo $myActivities ?? '0'; ?></h4>
                                                <p class="mb-0">Available Activities</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-file-earmark-text fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4 class="mb-0"><?php echo $myQuizzes ?? '0'; ?></h4>
                                                <p class="mb-0">Available Quizzes</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-question-circle fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4 class="mb-0"><?php echo $mySubmissions ?? '0'; ?></h4>
                                                <p class="mb-0">My Submissions</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-send fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h4 class="mb-0"><?php echo $myGrade ?? 'N/A'; ?></h4>
                                                <p class="mb-0">Average Grade</p>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-trophy fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h6>Quick Actions</h6>
                                <div class="row g-2">
                                    <?php if ($userRole === 'admin'): ?>
                                    <div class="col-md-6">
                                        <a href="activities.php" class="btn btn-outline-primary w-100">
                                            <i class="bi bi-plus-circle"></i> Manage Activities
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="quizzes.php" class="btn btn-outline-success w-100">
                                            <i class="bi bi-question-circle"></i> Manage Quizzes
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="students.php" class="btn btn-outline-info w-100">
                                            <i class="bi bi-people"></i> Manage Students
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="teachers.php" class="btn btn-outline-warning w-100">
                                            <i class="bi bi-person-workspace"></i> Manage Teachers
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="grades.php" class="btn btn-outline-secondary w-100">
                                            <i class="bi bi-trophy"></i> View Grades
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="settings.php" class="btn btn-outline-dark w-100">
                                            <i class="bi bi-gear"></i> System Settings
                                        </a>
                                    </div>
                                    <?php elseif ($userRole === 'teacher'): ?>
                                    <div class="col-md-6">
                                        <a href="activities.php" class="btn btn-outline-primary w-100">
                                            <i class="bi bi-plus-circle"></i> Create Activity
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="quizzes.php" class="btn btn-outline-success w-100">
                                            <i class="bi bi-question-circle"></i> Create Quiz
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="students.php" class="btn btn-outline-info w-100">
                                            <i class="bi bi-people"></i> My Students
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="grades.php" class="btn btn-outline-warning w-100">
                                            <i class="bi bi-trophy"></i> Grade Submissions
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="interventions.php" class="btn btn-outline-danger w-100">
                                            <i class="bi bi-heart-pulse"></i> Interventions
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="announcements.php" class="btn btn-outline-secondary w-100">
                                            <i class="bi bi-megaphone"></i> Announcements
                                        </a>
                                    </div>
                                    <?php elseif ($userRole === 'student'): ?>
                                    <div class="col-md-6">
                                        <a href="activities.php" class="btn btn-outline-primary w-100">
                                            <i class="bi bi-list-check"></i> View Activities
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="quizzes.php" class="btn btn-outline-success w-100">
                                            <i class="bi bi-question-circle"></i> Take Quizzes
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="grades.php" class="btn btn-outline-warning w-100">
                                            <i class="bi bi-trophy"></i> My Grades
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="announcement.php" class="btn btn-outline-info w-100">
                                            <i class="bi bi-megaphone"></i> Announcements
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Recent Activity</h6>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <?php if ($userRole === 'admin'): ?>
                                        <p class="text-muted mb-2"><i class="bi bi-clock"></i> System Overview</p>
                                        <small class="text-muted">Monitor all system activities, user management, and
                                            performance metrics.</small>
                                        <?php elseif ($userRole === 'teacher'): ?>
                                        <p class="text-muted mb-2"><i class="bi bi-person-workspace"></i> Teaching
                                            Activities</p>
                                        <small class="text-muted">Track student submissions, grading progress, and
                                            class performance.</small>
                                        <?php elseif ($userRole === 'student'): ?>
                                        <p class="text-muted mb-2"><i class="bi bi-person"></i> Academic Progress</p>
                                        <small class="text-muted">Monitor your assignments, quiz results, and overall
                                            academic performance.</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
