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

// Set page title for Midterm
$pageTitle = 'My Quizzes - Midterm';

// Include Permissions class
require_once __DIR__ . '/app/Permissions.php';

// Check if user can view their own quizzes
if (!Permission::canViewOwnQuizzes()) {
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
        <h1><?php echo $pageTitle; ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="my-quizzes.php">My Quizzes</a></li>
                <li class="breadcrumb-item active">Midterm</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title">Midterm Quizzes List</h5>
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-info" onclick="refreshQuizzesTable()"
                                    title="Refresh">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="myQuizzesTable">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Description</th>
                                        <th>Time Limit</th>
                                        <th>Attempts</th>
                                        <th>Status</th>
                                        <th>Score</th>
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

<!-- Quiz Details Modal -->
<div class="modal fade" id="quizDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quizDetailsTitle">Quiz Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="quizDetailsContent">
                    <!-- Quiz details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="assets/jquery/jquery-3.7.1.min.js"></script>

<!-- DataTables JS -->
<script src="assets/js/dataTables/dataTables.js"></script>
<script src="assets/js/dataTables/dataTables.bootstrap5.js"></script>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Pass permissions to JavaScript -->
<script>
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.isStudent = <?php echo Permission::isStudent() ? 'true' : 'false'; ?>;
    window.canViewOwnQuizzes = <?php echo Permission::canViewOwnQuizzes() ? 'true' : 'false'; ?>;
    window.canTakeQuizzes = <?php echo Permission::canTakeQuizzes() ? 'true' : 'false'; ?>;
    window.userId = <?php echo $user['id']; ?>;
    window.periodFilter = 'midterm'; // Fixed to midterm for this page
</script>

<!-- My Quizzes DataTables JS -->
<script src="assets/js/dataTables/myQuizzesDataTables.js"></script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
