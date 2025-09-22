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

// Check if user has permission to view announcements
// All roles can view announcements, but only admin/teacher can manage them
if (!Permission::canManageAnnouncements() && !Permission::isStudent()) {
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
            Announcements Management
            <?php elseif (Permission::isTeacher()): ?>
            My Announcements Management
            <?php elseif (Permission::isStudent()): ?>
            Announcements
            <?php endif; ?>
        </h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Announcements</li>
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
                                All Announcements
                                <?php elseif (Permission::isTeacher()): ?>
                                My Announcements
                                <?php elseif (Permission::isStudent()): ?>
                                Latest Announcements
                                <?php endif; ?>
                            </h5>
                            <div class="btn-group gap-2">
                                <button type="button" class="btn btn-outline-info"
                                    onclick="refreshAnnouncementsTable()" title="Refresh">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                                <?php if (Permission::canAddAnnouncements()): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#announcementModal">
                                    <i class="bi bi-plus-circle"></i> Add New Announcement
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="announcementsTable">
                                <thead>
                                    <tr>
                                        <?php if (!Permission::isStudent()): ?>
                                        <th>ID</th>
                                        <?php endif; ?>
                                        <th>Title</th>
                                        <th>Message</th>
                                        <?php if (!Permission::isStudent()): ?>
                                        <th>Created By</th>
                                        <th>Role</th>
                                        <?php endif; ?>
                                        <th>Created At</th>
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

<!-- Announcement Details Modal -->
<div class="modal fade" id="announcementDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="announcementDetailsTitle">Announcement Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="announcementDetailsContent">
                    <!-- Announcement details will be loaded here -->
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

<!-- Announcement Modal -->
<?php if (Permission::canAddAnnouncements()): ?>
<div class="modal fade" id="announcementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Create New Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="announcementForm">
                    <input type="hidden" id="announcementId" name="id">
                    <input type="hidden" name="action" id="formAction" value="create_announcement">

                    <div class="mb-3">
                        <label for="title" class="form-label">
                            <i class="bi bi-card-heading"></i> Title <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="title" name="title" maxlength="255"
                            placeholder="Enter announcement title..." required>
                        <div class="form-text">
                            <small class="text-muted" id="titleCount">0/255 characters</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label">
                            <i class="bi bi-chat-text"></i> Message <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="message" name="message" rows="8" placeholder="Enter announcement message..."
                            required></textarea>
                        <div class="form-text">
                            <small class="text-muted" id="messageCount">0 characters</small>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Announcement Guidelines</h6>
                        <ul class="mb-0">
                            <li>Keep titles concise and descriptive</li>
                            <li>Use clear, professional language</li>
                            <li>Include all relevant information</li>
                            <li>Consider your audience (students, teachers, or all users)</li>
                        </ul>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="saveAnnouncement()">
                    <i class="bi bi-check-circle"></i> <span id="submitButtonText">Create Announcement</span>
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

<!-- Announcements DataTables JS -->
<script src="assets/js/dataTables/announcementsDataTables.js"></script>

<!-- Pass permissions to JavaScript -->
<script>
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.canManageAnnouncements = <?php echo Permission::canManageAnnouncements() ? 'true' : 'false'; ?>;
    window.canAddAnnouncements = <?php echo Permission::canAddAnnouncements() ? 'true' : 'false'; ?>;
    window.canEditAnnouncements = <?php echo Permission::canEditAnnouncements() ? 'true' : 'false'; ?>;
    window.canDeleteAnnouncements = <?php echo Permission::canDeleteAnnouncements() ? 'true' : 'false'; ?>;
    window.isAdmin = <?php echo Permission::isAdmin() ? 'true' : 'false'; ?>;
    window.isTeacher = <?php echo Permission::isTeacher() ? 'true' : 'false'; ?>;
    window.isStudent = <?php echo Permission::isStudent() ? 'true' : 'false'; ?>;
    window.userId = <?php echo $user['id']; ?>;
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
