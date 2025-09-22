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

// Check if user has permission to manage activities
if (!Permission::canManageActivities()) {
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
            Midterm Student Activity Submissions Management
            <?php elseif (Permission::isTeacher()): ?>
            Midterm - My Students' Activity Submissions
            <?php endif; ?>
        </h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="teacher-activities.php">Student Submissions</a></li>
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
                            <h5 class="card-title">
                                <?php if (Permission::isAdmin()): ?>
                                Midterm Period - All Student Activity Submissions
                                <?php elseif (Permission::isTeacher()): ?>
                                Midterm Period - My Students' Activity Submissions
                                <?php endif; ?>
                            </h5>
                            <div class="d-flex gap-2 align-items-center">
                                <select class="form-select" id="midtermStatusFilter" style="width: auto;" onchange="filterByStatus()">
                                    <option value="">All Status</option>
                                    <option value="submitted">Submitted</option>
                                    <option value="unsubmitted">Unsubmitted</option>
                                </select>
                                <button type="button" class="btn btn-outline-info" onclick="testMidtermFilters()" title="Test Filters">
                                    <i class="bi bi-bug"></i> Test
                                </button>
                                <?php if (Permission::canManageActivities()): ?>
                                <button type="button" class="btn btn-outline-success" onclick="exportSubmissionsData()"
                                    title="Export Data">
                                    <i class="bi bi-download"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="midtermSubmissionsTable">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Activity Title</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Grade</th>
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

<!-- Submission Details Modal -->
<div class="modal fade" id="submissionDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="submissionDetailsTitle">Submission Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="submissionDetailsContent">
                    <!-- Submission details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Grade Submission Modal -->
<div class="modal fade" id="gradeSubmissionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gradeSubmissionTitle">Grade Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="gradeSubmissionForm">
                    <input type="hidden" id="gradeSubmissionId" name="submission_id">
                    <input type="hidden" id="gradeAction" name="action" value="grade_submission">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gradeScore" class="form-label">
                                    Score
                                </label>
                                <input type="number" class="form-control" id="gradeScore" name="score" min="0"
                                    max="100" step="0.01" required>
                                <div class="form-text">Enter score out of 100</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gradeMaxScore" class="form-label">
                                    Max Score
                                </label>
                                <input type="number" class="form-control" id="gradeMaxScore" name="max_score"
                                    min="1" max="100" step="0.01" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="gradeComments" class="form-label">
                            Comments
                        </label>
                        <textarea class="form-control" id="gradeComments" name="comments" rows="4"
                            placeholder="Add feedback and comments for the student..."></textarea>
                    </div>

                    <div class="alert alert-info">
                        <strong>Note:</strong> Grades will be visible to students once submitted.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitGrade()">
                    Submit Grade
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

<!-- Pass permissions to JavaScript -->
<script>
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.canManageActivities = <?php echo Permission::canManageActivities() ? 'true' : 'false'; ?>;
    window.isAdmin = <?php echo Permission::isAdmin() ? 'true' : 'false'; ?>;
    window.isTeacher = <?php echo Permission::isTeacher() ? 'true' : 'false'; ?>;
    window.userId = <?php echo $user['id']; ?>;
    window.gradingPeriod = 'midterm'; // Set the grading period for this page
</script>

<!-- Custom JavaScript for Midterm Activities -->
<script>
// Global variables
var midtermSubmissionsTable;
var currentSubmissionId = null;

$(document).ready(function () {
    initializeMidtermSubmissionsTable();
    setupModalEvents();
});

/**
 * Initialize Midterm Submissions DataTable
 */
function initializeMidtermSubmissionsTable() {
    midtermSubmissionsTable = $("#midtermSubmissionsTable").DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "app/API/apiTeacherActivities.php?action=datatable",
            type: "GET",
            data: function(d) {
                const statusEl = document.getElementById('midtermStatusFilter');
                const status = statusEl ? statusEl.value : '';
                
                console.log('Midterm Activities DataTable - Sending filters:', {
                    grading_period: 'midterm',
                    status: status
                });
                
                // Always filter by midterm period
                d.grading_period = 'midterm';
                
                if (status && status !== '') {
                    d.status = status;
                }
                
                return d;
            },
            error: function (xhr, error, thrown) {
                console.error("DataTables error:", error);
                console.error("XHR:", xhr);
                console.error("Response:", xhr.responseText);
                Swal.fire({
                    icon: "error",
                    title: "Error!",
                    text: "Failed to load submissions data: " + error,
                });
            },
        },
        "columns": [
            { 
                "data": "student_name", 
                "width": "25%",
                "render": function(data, type, row) {
                    return `<strong>${data}</strong>`;
                }
            },
            { 
                "data": "activity_title", 
                "width": "25%",
                "render": function(data, type, row) {
                    return data || 'N/A';
                }
            },
            { 
                "data": "subject_name", 
                "width": "15%",
                "render": function(data, type, row) {
                    return data ? `<span class="badge bg-primary">${data}</span>` : 'N/A';
                }
            },
            { 
                "data": "status", 
                "width": "10%",
                "render": function(data, type, row) {
                    let badgeClass = '';
                    let badgeText = '';
                    
                    switch(data) {
                        case 'submitted':
                            badgeClass = 'badge bg-success';
                            badgeText = 'Submitted';
                            break;
                        case 'unsubmitted':
                            badgeClass = 'badge bg-secondary';
                            badgeText = 'Unsubmitted';
                            break;
                        default:
                            badgeClass = 'badge bg-success';
                            badgeText = 'Submitted';
                    }
                    
                    return `<span class="${badgeClass}">${badgeText}</span>`;
                }
            },
            { 
                "data": "grade", 
                "width": "10%",
                "render": function(data, type, row) {
                    if (data && data !== 'Not Graded') {
                        return `<span class="badge bg-info">${data}</span>`;
                    }
                    return '<span class="badge bg-secondary">Not Graded</span>';
                }
            },
            { 
                "data": "actions", 
                "orderable": false,
                "width": "15%",
                "render": function(data, type, row) {
                    let actions = '<div class="btn-group gap-1" role="group">';
                    
                    // View Details button (always available)
                    actions += `
                        <button class="btn btn-outline-primary" onclick="viewSubmission(${data})" title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>
                    `;
                    
                    // Grade button (for admin/teacher)
                    if (window.canManageActivities) {
                        actions += `
                            <button class="btn btn-outline-success" onclick="gradeSubmission(${data})" title="Grade Submission">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        `;
                    }
                    
                    actions += '</div>';
                    return actions;
                }
            }
        ],
        "order": [[0, "asc"]],
        "pageLength": 10,
        "responsive": true,
        "language": {
            "processing": "Loading midterm submissions...",
            "emptyTable": "No midterm submissions found",
            "zeroRecords": "No matching midterm submissions found"
        }
    });
}

/**
 * Filter by status
 */
function filterByStatus() {
    const statusEl = document.getElementById('midtermStatusFilter');
    const selectedValue = statusEl ? statusEl.value : '';
    console.log('Midterm Activities - Status Filter Changed:', selectedValue);
    
    if (midtermSubmissionsTable) {
        midtermSubmissionsTable.ajax.reload();
    }
}

/**
 * Test midterm filters function
 */
function testMidtermFilters() {
    console.log('=== MIDTERM ACTIVITIES FILTER DEBUG ===');
    
    const statusEl = document.getElementById('midtermStatusFilter');
    
    console.log('DOM Elements Check:', {
        statusFilter: !!statusEl,
        statusValue: statusEl ? statusEl.value : 'ELEMENT NOT FOUND'
    });
    
    const status = statusEl ? statusEl.value : '';
    
    // Test API directly
    const testUrl = `app/API/apiTeacherActivities.php?action=datatable&draw=999&start=0&length=10&grading_period=midterm&status=${status}`;
    console.log('Test URL:', testUrl);
    
    fetch(testUrl)
        .then(response => response.json())
        .then(data => {
            console.log('API Response:', data);
            alert(`Midterm API Test Results:\nTotal Records: ${data.recordsTotal}\nFiltered Records: ${data.recordsFiltered}\nData Count: ${data.data.length}\n\nCheck console for full response.`);
        })
        .catch(error => {
            console.error('API Test Error:', error);
            alert('API Test Failed - Check console for details');
        });
}

// Reuse existing functions from teacher activities
function viewSubmission(id) {
    // Implementation would be similar to teacher-activities.php
    console.log('View submission:', id);
}

function gradeSubmission(id) {
    // Implementation would be similar to teacher-activities.php  
    console.log('Grade submission:', id);
}

function submitGrade() {
    // Implementation would be similar to teacher-activities.php
    console.log('Submit grade');
}

function setupModalEvents() {
    // Setup modal events similar to teacher-activities.php
}

function exportSubmissionsData() {
    // Export functionality
    Swal.fire({
        icon: 'info',
        title: 'Coming Soon!',
        text: 'Export functionality will be available soon.'
    });
}
</script>
</body>

</html>
