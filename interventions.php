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

// Check if user has permission to manage interventions
if (!Permission::canManageInterventions()) {
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
            Interventions Management
            <?php elseif (Permission::isTeacher()): ?>
            My Interventions Management
            <?php endif; ?>
        </h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Interventions</li>
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
                                All Interventions List
                                <?php elseif (Permission::isTeacher()): ?>
                                My Interventions List
                                <?php endif; ?>
                            </h5>
                            <div class="btn-group gap-2">
                                <button type="button" class="btn btn-outline-info" onclick="refreshInterventionsTable()" title="Refresh">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                                <?php if (Permission::canManageInterventions()): ?>
                                <button type="button" class="btn btn-outline-success" onclick="exportInterventionsData()" title="Export Data">
                                    <i class="bi bi-download"></i>
                                </button>
                                <?php if (Permission::canAddInterventions()): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#interventionModal">
                                    Add New Intervention
                                </button>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>


                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="interventionsTable">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Subject</th>
                                        <th>Notes</th>
                                        <th>Notify Teacher</th>
                                        <th>Created</th>
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

<!-- Intervention Modal -->
<?php if (Permission::canManageInterventions()): ?>
<div class="modal fade" id="interventionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Intervention</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="interventionForm">
                    <input type="hidden" id="interventionId" name="id">
                    <input type="hidden" name="action" id="formAction" value="create_intervention">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="student_id" class="form-label">
                                <i class="bi bi-person"></i> Select Student <span class="text-danger">*</span>
                            </label>
                            <select class="form-control" id="student_id" name="student_id" required>
                                <option value="">Select Student</option>
                                <!-- Options will be populated via JavaScript -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="subject_id" class="form-label">
                                <i class="bi bi-book"></i> Subject <span class="text-danger">*</span>
                            </label>
                            <select class="form-control" id="subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
                                <!-- Options will be populated via JavaScript -->
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">
                            <i class="bi bi-chat-text"></i> Intervention Notes <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="notes" name="notes" rows="4"
                            placeholder="Enter detailed notes about the intervention..." required></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="notify_teacher" name="notify_teacher" value="1">
                                <label class="form-check-label" for="notify_teacher">
                                    <i class="bi bi-bell"></i> Notify Teacher
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Intervention Guidelines</h6>
                        <ul class="mb-0">
                            <li>Select the appropriate student and subject for the intervention</li>
                            <li>Provide detailed notes about the intervention and its purpose</li>
                            <li>Include any follow-up actions or recommendations</li>
                            <li>Document student progress and expected outcomes</li>
                            <li>Use the notify teacher option to alert relevant staff</li>
                        </ul>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="saveIntervention()">
                    <span id="submitButtonText">Submit</span>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Intervention Details Modal -->
<div class="modal fade" id="interventionDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="interventionDetailsModalTitle">
                    <i class="bi bi-info-circle"></i> Intervention Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="interventionDetailsContent">
                    <!-- Content will be populated by JavaScript -->
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

<!-- Interventions DataTables JS -->
<script src="assets/js/dataTables/interventionsDataTables.js"></script>

<!-- Pass permissions to JavaScript -->
<script>
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.canManageInterventions = <?php echo Permission::canManageInterventions() ? 'true' : 'false'; ?>;
    window.canAddInterventions = <?php echo Permission::canAddInterventions() ? 'true' : 'false'; ?>;
    window.canEditInterventions = <?php echo Permission::canEditInterventions() ? 'true' : 'false'; ?>;
    window.canDeleteInterventions = <?php echo Permission::canDeleteInterventions() ? 'true' : 'false'; ?>;
    window.isAdmin = <?php echo Permission::isAdmin() ? 'true' : 'false'; ?>;
    window.isTeacher = <?php echo Permission::isTeacher() ? 'true' : 'false'; ?>;
    window.userId = <?php echo $user['id']; ?>;
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
