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

// Check if user has permission to manage students
// Only admin and teacher can access students management
if (!Permission::canManageStudents()) {
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
            Students Management
            <?php elseif (Permission::isTeacher()): ?>
            My Students Management
            <?php endif; ?>
        </h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Students</li>
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
                                All Students List
                                <?php elseif (Permission::isTeacher()): ?>
                                My Students List
                                <?php endif; ?>
                            </h5>
                            <div class="btn-group gap-2">
                                <button type="button" class="btn btn-outline-info" onclick="refreshStudentsTable()"
                                    title="Refresh">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="exportStudentsData()"
                                    title="Export Data">
                                    <i class="bi bi-download"></i>
                                </button>
                                <?php if (Permission::canAddStudents()): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#studentModal">
                                    Add New Student
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="studentsTable">
                                <thead>
                                    <tr>
                                        <th>Full Name</th>
                                        <th>Activity</th>
                                        <th>Quiz</th>
                                        <th>Average Grade</th>
                                        <th>Date Created </th>
                                        <th>Status</th>
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

<!-- Student Modal -->
<div class="modal fade" id="studentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="studentForm">
                    <input type="hidden" id="studentId" name="id">
                    <input type="hidden" name="action" id="formAction" value="create_student">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">
                                <i class="bi bi-person"></i> First Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="first_name" name="first_name"
                                placeholder="Enter first name..." required>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">
                                <i class="bi bi-person"></i> Last Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="last_name" name="last_name"
                                placeholder="Enter last name..." required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">
                                <i class="bi bi-envelope"></i> Email <span class="text-danger">*</span>
                            </label>
                            <input type="email" class="form-control" id="email" name="email"
                                placeholder="Enter email address..." required>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">
                                <i class="bi bi-lock"></i> Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="Enter password..." required>
                        </div>
                    </div>

                    <!-- Course and Year Level are automatically set to BSIT - 1st Year -->
                    <input type="hidden" id="course" name="course" value="BSIT">
                    <input type="hidden" id="year_level" name="year_level" value="1st">


                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="saveStudent()">
                    Submit
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Set current user role and permissions for JavaScript use
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.canManageStudents = <?php echo Permission::canManageStudents() ? 'true' : 'false'; ?>;
    window.canAddStudents = <?php echo Permission::canAddStudents() ? 'true' : 'false'; ?>;
    window.canEditStudents = <?php echo Permission::canEditStudents() ? 'true' : 'false'; ?>;
    window.canDeleteStudents = <?php echo Permission::canDeleteStudents() ? 'true' : 'false'; ?>;
    window.isAdmin = <?php echo Permission::isAdmin() ? 'true' : 'false'; ?>;
    window.isTeacher = <?php echo Permission::isTeacher() ? 'true' : 'false'; ?>;
    window.userId = <?php echo $user['id']; ?>;
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
