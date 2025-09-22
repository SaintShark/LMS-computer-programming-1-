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

// Check if user has permission to view activities
// All roles can view activities, but only admin/teacher can manage them
if (!Permission::canManageActivities() && !Permission::isStudent()) {
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
            Activities Management
            <?php elseif (Permission::isTeacher()): ?>
            My Activities Management
            <?php elseif (Permission::isStudent()): ?>
            Activities
            <?php endif; ?>
        </h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Activities</li>
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
                                All Activities List
                                <?php elseif (Permission::isTeacher()): ?>
                                My Activities List
                                <?php elseif (Permission::isStudent()): ?>
                                Available Activities
                                <?php endif; ?>
                            </h5>
                            <div class="btn-group gap-2">
                                <button type="button" class="btn btn-outline-info" onclick="refreshActivitiesTable()"
                                    title="Refresh">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                                <?php if (Permission::canAddActivities()): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#activityModal">
                                    Add New Activity
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="activitiesTable">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Description</th>
                                        <th>Due Date</th>
                                        <th>Grading Period</th>
                                        <th>Status</th>
                                        <?php if (Permission::canManageActivities()): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
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

<!-- Activity Details Modal -->
<div class="modal fade" id="activityDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="activityDetailsTitle">Activity Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="activityDetailsContent">
                    <!-- Activity details will be loaded here -->
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

<!-- Activity Modal -->
<?php if (Permission::canAddActivities()): ?>
<div class="modal fade" id="activityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Activity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="activityForm">
                    <input type="hidden" id="activityId" name="id">
                    <input type="hidden" name="action" id="formAction" value="create_activity">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="subject_id" class="form-label">
                                Subject <span class="text-danger">*</span>
                            </label>
                            <select class="form-control" id="subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
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
                            Activity Title <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="title" name="title"
                            placeholder="Enter activity title..." required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">
                            Description <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="description" name="description" rows="4"
                            placeholder="Enter activity description..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="activity_file" class="form-label">
                            Activity File
                        </label>
                        <input type="file" class="form-control" id="activity_file" name="activity_file" 
                            accept=".c,.cpp,.h,.txt,.pdf,.doc,.docx" 
                            onchange="previewFileName(this)">
                        <div class="form-text">
                            <small class="text-muted">
                                Upload template files, instructions, or resources for this activity. 
                                Supported formats: .c, .cpp, .h, .txt, .pdf, .doc, .docx
                            </small>
                        </div>
                        <div id="filePreview" class="mt-2" style="display: none;">
                            <div class="alert alert-info">
                                <span id="fileName"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="allow_from" class="form-label">
                                Allow From <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="allow_from" name="allow_from" required>
                        </div>
                        <div class="col-md-4">
                            <label for="due_date" class="form-label">
                                Due Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="due_date" name="due_date" required>
                        </div>
                        <div class="col-md-4">
                            <label for="cutoff_date" class="form-label">
                                Cutoff Date
                            </label>
                            <input type="date" class="form-control" id="cutoff_date" name="cutoff_date">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="reminder_date" class="form-label">
                                Reminder Date
                            </label>
                            <input type="date" class="form-control" id="reminder_date" name="reminder_date">
                        </div>
                        <div class="col-md-6">
                            <label for="deduction_percent" class="form-label">
                                Late Deduction (%)
                            </label>
                            <input type="number" class="form-control" id="deduction_percent"
                                name="deduction_percent" min="0" max="100" step="0.01"
                                placeholder="0.00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">
                            Status
                        </label>
                        <select class="form-control" id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="missed">Missed</option>
                        </select>
                    </div>

                    <div class="alert alert-info">
                        <h6>Activity Guidelines</h6>
                        <ul class="mb-0">
                            <li>Set clear due dates and cutoff dates</li>
                            <li>Provide detailed descriptions for students</li>
                            <li>Use appropriate grading periods</li>
                            <li>Set reasonable late deduction percentages</li>
                        </ul>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="saveActivity()">
                    <span id="submitButtonText">Create Activity</span>
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

<!-- Activities DataTables JS -->
<script src="assets/js/dataTables/activitiesDataTables.js"></script>

<!-- Pass permissions to JavaScript -->
<script>
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.canManageActivities = <?php echo Permission::canManageActivities() ? 'true' : 'false'; ?>;
    window.canAddActivities = <?php echo Permission::canAddActivities() ? 'true' : 'false'; ?>;
    window.canEditActivities = <?php echo Permission::canEditActivities() ? 'true' : 'false'; ?>;
    window.canDeleteActivities = <?php echo Permission::canDeleteActivities() ? 'true' : 'false'; ?>;
    window.isAdmin = <?php echo Permission::isAdmin() ? 'true' : 'false'; ?>;
    window.isTeacher = <?php echo Permission::isTeacher() ? 'true' : 'false'; ?>;
    window.isStudent = <?php echo Permission::isStudent() ? 'true' : 'false'; ?>;
    window.userId = <?php echo $user['id']; ?>;

    // Function to preview selected file name
    function previewFileName(input) {
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            fileName.textContent = `Selected: ${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
            filePreview.style.display = 'block';
        } else {
            filePreview.style.display = 'none';
        }
    }

    // Function to clear file preview
    function clearFilePreview() {
        const fileInput = document.getElementById('activity_file');
        const filePreview = document.getElementById('filePreview');
        
        fileInput.value = '';
        filePreview.style.display = 'none';
    }
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
