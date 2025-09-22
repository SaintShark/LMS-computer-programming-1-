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

// Check if user has permission to manage subjects (Admin and Teacher)
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
        <h1>Subject Management</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Subjects</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title">Subjects List</h5>
                            <div class="btn-group gap-2">
                                <?php if (Permission::canManageStudents()): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#subjectModal">
                                    Add New Subject
                                </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-success" onclick="exportSubjects()">
                                    <i class="bi bi-download"></i>
                                </button>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="subjectsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Subject Name</th>
                                        <th>Description</th>
                                        <th>Year Level</th>
                                        <th>Course</th>
                                        <th>Created At</th>
                                        <?php if (Permission::canManageStudents()): ?>
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

<!-- Subject Modal -->
<?php if (Permission::canManageStudents()): ?>
<div class="modal fade" id="subjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="subjectForm">
                    <input type="hidden" id="subjectId" name="id">
                    <input type="hidden" name="action" id="formAction" value="create_subject">

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="name" class="form-label">Subject Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required
                                placeholder="e.g., Computer Programming 1, Data Structures">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                placeholder="Brief description of the subject"></textarea>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="year_level" class="form-label">Year Level <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" id="year_level" name="year_level" required>
                                <option value="">Select Year Level</option>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="course" class="form-label">Course <span class="text-danger">*</span></label>
                            <select class="form-control" id="course" name="course" required>
                                <option value="">Select Course</option>
                                <option value="BSIT">BSIT - Bachelor of Science in Information Technology</option>
                                <option value="BSCS">BSCS - Bachelor of Science in Computer Science</option>
                                <option value="BSIS">BSIS - Bachelor of Science in Information Systems</option>
                                <option value="BSCE">BSCE - Bachelor of Science in Computer Engineering</option>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Subject Information</h6>
                        <ul class="mb-0">
                            <li><strong>Subject Name:</strong> Must be unique across all subjects</li>
                            <li><strong>Year Level:</strong> The academic year this subject is taught</li>
                            <li><strong>Course:</strong> The degree program this subject belongs to</li>
                            <li><strong>Description:</strong> Optional detailed description of the subject</li>
                        </ul>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveSubject()">Submit</button>
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

<!-- Subjects DataTables JS -->
<script src="assets/js/dataTables/subjectsDataTables.js"></script>

<!-- Pass permissions to JavaScript -->
<script>
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.canManageStudents = <?php echo Permission::canManageStudents() ? 'true' : 'false'; ?>;
    window.canAddStudents = <?php echo Permission::canAddStudents() ? 'true' : 'false'; ?>;
    window.canEditStudents = <?php echo Permission::canEditStudents() ? 'true' : 'false'; ?>;
    window.canDeleteStudents = <?php echo Permission::canDeleteStudents() ? 'true' : 'false'; ?>;
    window.isAdmin = <?php echo Permission::isAdmin() ? 'true' : 'false'; ?>;
    window.isTeacher = <?php echo Permission::isTeacher() ? 'true' : 'false'; ?>;
    window.isStudent = <?php echo Permission::isStudent() ? 'true' : 'false'; ?>;
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
