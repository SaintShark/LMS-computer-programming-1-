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

// Check if user has permission to view grading periods
if (!Permission::canManageGradingPeriods()) {
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
        <h1>Grading Periods Management</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Grading Periods</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title">Grading Periods List</h5>
                            <?php if (Permission::canAddGradingPeriods()): ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                data-bs-target="#gradingPeriodModal">
                                Add New Grading Period
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="gradingPeriodsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Semester</th>
                                        <th>Academic Year</th>
                                        <th>Period</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Weight</th>
                                        <th>Status</th>
                                        <?php if (Permission::canManageGradingPeriods()): ?>
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

<!-- Grading Period Modal -->
<?php if (Permission::canAddGradingPeriods()): ?>
<div class="modal fade" id="gradingPeriodModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Grading Period</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="gradingPeriodForm">
                    <input type="hidden" id="gradingPeriodId" name="id">
                    <input type="hidden" name="action" id="formAction" value="create_grading_period">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="semester_id" class="form-label">Semester <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" id="semester_id" name="semester_id" required>
                                <option value="">Select Semester</option>
                                <!-- Options will be populated via JavaScript -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="name" class="form-label">Period Name <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" id="name" name="name" required>
                                <option value="">Select Period</option>
                                <option value="prelim">Prelim</option>
                                <option value="midterm">Midterm</option>
                                <option value="finals">Finals</option>
                            </select>
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
                            <label for="weight_percent" class="form-label">Weight Percentage <span
                                    class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="weight_percent" name="weight_percent"
                                min="0" max="100" step="0.01" placeholder="30.00" required>
                            <div class="form-text">Enter the weight percentage for this grading period (e.g., 30.00 for
                                30%)</div>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="pending">Pending</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveGradingPeriod()">Submit</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // Set current user role and permissions for JavaScript use
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.canAddGradingPeriods = <?php echo Permission::canAddGradingPeriods() ? 'true' : 'false'; ?>;
    window.canEditGradingPeriods = <?php echo Permission::canEditGradingPeriods() ? 'true' : 'false'; ?>;
    window.canDeleteGradingPeriods = <?php echo Permission::canDeleteGradingPeriods() ? 'true' : 'false'; ?>;
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>

</body>

</html>
