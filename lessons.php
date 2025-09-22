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

// Check if user has permission to view lessons
if (!Permission::canViewLessons()) {
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
            Lessons Management
            <?php elseif (Permission::isTeacher()): ?>
            Computer Programming 1 - Lessons
            <?php else: ?>
            Lessons
            <?php endif; ?>
        </h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Lessons</li>
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
                                All Lessons
                                <?php elseif (Permission::isTeacher()): ?>
                                Computer Programming 1 Lessons
                                <?php else: ?>
                                Available Lessons
                                <?php endif; ?>
                            </h5>
                            <div class="btn-group">
                                <?php if (Permission::canAddLessons()): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#lessonModal">
                                    Add Lesson
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Lessons Table -->
                        <div class="table-responsive">
                            <table id="lessonsTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Content</th>
                                        <th>Created</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data will be loaded via DataTables -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Lesson Modal -->
<div class="modal fade" id="lessonModal" tabindex="-1" aria-labelledby="lessonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Lesson</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="lessonForm">
                <div class="modal-body">
                    <input type="hidden" id="formAction" name="action" value="create_lesson">
                    <input type="hidden" id="lessonId" name="id" value="">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="subject_id" class="form-label">Subject <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" id="subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
                                <!-- Options will be populated via JavaScript -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="title" class="form-label">Lesson Title <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required
                                placeholder="Enter lesson title">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="content" class="form-label">Lesson Content <span
                                    class="text-danger">*</span></label>
                            <textarea class="form-control" id="content" name="content" rows="15" required
                                placeholder="Enter lesson content here..."></textarea>
                            <div class="form-text">
                                <small class="text-muted">Minimum 10 characters required. Use proper formatting for
                                    better readability.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Lesson</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Pass permissions to JavaScript -->
<script>
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.canViewLessons = <?php echo Permission::canViewLessons() ? 'true' : 'false'; ?>;
    window.canAddLessons = <?php echo Permission::canAddLessons() ? 'true' : 'false'; ?>;
    window.canEditLessons = <?php echo Permission::canEditLessons() ? 'true' : 'false'; ?>;
    window.canDeleteLessons = <?php echo Permission::canDeleteLessons() ? 'true' : 'false'; ?>;
    window.isAdmin = <?php echo Permission::isAdmin() ? 'true' : 'false'; ?>;
    window.isTeacher = <?php echo Permission::isTeacher() ? 'true' : 'false'; ?>;
    window.isStudent = <?php echo Permission::isStudent() ? 'true' : 'false'; ?>;
</script>
</body>

</html>
