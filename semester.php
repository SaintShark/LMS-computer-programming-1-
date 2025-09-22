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

// Check if user has permission to manage semesters
if (!Permission::canManageSemesters()) {
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
        <h1>Semester Management</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Semesters</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title">Semesters List</h5>
                            <div class="btn-group gap-2">
                                <?php if (Permission::canAddSemesters()): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#semesterModal">
                                    Add New Semester
                                </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-success" onclick="exportSemesters()">
                                    <i class="bi bi-download"></i>
                                </button>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="semestersTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Academic Year</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <?php if (Permission::canManageSemesters()): ?>
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

<!-- Semester Modal -->
<?php if (Permission::canManageSemesters()): ?>
<div class="modal fade" id="semesterModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Semester</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="semesterForm">
                    <input type="hidden" id="semesterId" name="id">
                    <input type="hidden" name="action" id="formAction" value="create_semester">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Semester Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required
                                placeholder="e.g., 1st Semester, 2nd Semester">
                        </div>
                        <div class="col-md-6">
                            <label for="academic_year" class="form-label">Academic Year <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="academic_year" name="academic_year" required
                                placeholder="e.g., 2025-2026">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date <span
                                    class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="">Select Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Semester Information</h6>
                        <ul class="mb-0">
                            <li><strong>Active:</strong> Currently ongoing semester</li>
                            <li><strong>Inactive:</strong> Semester that has not started yet</li>
                            <li><strong>Completed:</strong> Semester that has finished</li>
                            <li><strong>Note:</strong> Only one semester can be active at a time</li>
                        </ul>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveSemester()">Submit</button>
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

<!-- Semesters DataTables JS -->
<script src="assets/js/dataTables/semestersDataTables.js"></script>

<!-- Pass permissions to JavaScript -->
<script>
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.canManageSemesters = <?php echo Permission::canManageSemesters() ? 'true' : 'false'; ?>;
    window.canAddSemesters = <?php echo Permission::canAddSemesters() ? 'true' : 'false'; ?>;
    window.canEditSemesters = <?php echo Permission::canEditSemesters() ? 'true' : 'false'; ?>;
    window.canDeleteSemesters = <?php echo Permission::canDeleteSemesters() ? 'true' : 'false'; ?>;
    window.isAdmin = <?php echo Permission::isAdmin() ? 'true' : 'false'; ?>;
    window.isTeacher = <?php echo Permission::isTeacher() ? 'true' : 'false'; ?>;
    window.isStudent = <?php echo Permission::isStudent() ? 'true' : 'false'; ?>;
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
