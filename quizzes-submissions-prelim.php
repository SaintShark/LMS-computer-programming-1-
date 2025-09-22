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

// Check if user has permission to manage quizzes
if (!Permission::canManageQuizzes()) {
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
            Prelim Quiz Submissions Management
            <?php elseif (Permission::isTeacher()): ?>
            My Students' Prelim Quiz Submissions
            <?php endif; ?>
        </h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="quizzes-submissions.php">Quiz Submissions</a></li>
                <li class="breadcrumb-item active">Prelim Period</li>
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
                                Prelim Period Quiz Submissions
                                <?php elseif (Permission::isTeacher()): ?>
                                My Students' Prelim Quiz Submissions
                                <?php endif; ?>
                            </h5>
                            <div class="d-flex gap-2 align-items-center">
                                <select class="form-select" id="prelimQuizStatusFilter" style="width: auto;" onchange="filterByStatus()">
                                    <option value="">All Status</option>
                                    <option value="submitted">Submitted</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="timeout">Timeout</option>
                                </select>
                                <button type="button" class="btn btn-outline-info" onclick="testPrelimQuizFilters()" title="Test Filters">
                                    <i class="bi bi-bug"></i> Test
                                </button>
                                <?php if (Permission::canManageQuizzes()): ?>
                                <button type="button" class="btn btn-outline-success" onclick="exportQuizSubmissionsData()"
                                    title="Export Data">
                                    <i class="bi bi-download"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="prelimQuizSubmissionsTable">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Quiz Title</th>
                                        <th>Subject</th>
                                        <th>Attempt</th>
                                        <th>Score</th>
                                        <th>Status</th>
                                        <th>Submitted At</th>
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

<!-- Quiz Submission Details Modal -->
<div class="modal fade" id="quizSubmissionDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quizSubmissionDetailsTitle">Quiz Submission Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="quizSubmissionDetailsContent">
                    <!-- Quiz submission details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printQuizSubmissionBtn" onclick="printQuizSubmission()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Grade Quiz Submission Modal -->
<div class="modal fade" id="gradeQuizSubmissionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gradeQuizSubmissionTitle">Review Quiz Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="gradeQuizSubmissionForm">
                    <input type="hidden" id="gradeQuizSubmissionId" name="submission_id">
                    <input type="hidden" id="gradeQuizAction" name="action" value="grade_quiz_submission">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="quizScore" class="form-label">
                                    Score Achieved
                                </label>
                                <input type="number" class="form-control" id="quizScore" name="score" min="0"
                                    step="0.01" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="quizMaxScore" class="form-label">
                                    Max Score
                                </label>
                                <input type="number" class="form-control" id="quizMaxScore" name="max_score"
                                    readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="quizPercentage" class="form-label">
                                    Percentage
                                </label>
                                <input type="text" class="form-control" id="quizPercentage" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="quizTimeSpent" class="form-label">
                                    Time Spent
                                </label>
                                <input type="text" class="form-control" id="quizTimeSpent" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="quizAttemptNumber" class="form-label">
                                    Attempt Number
                                </label>
                                <input type="text" class="form-control" id="quizAttemptNumber" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="quizTeacherComments" class="form-label">
                            Teacher Comments
                        </label>
                        <textarea class="form-control" id="quizTeacherComments" name="teacher_comments" rows="4"
                            placeholder="Add feedback and comments for the student..."></textarea>
                    </div>

                    <div class="alert alert-info">
                        <strong>Note:</strong> Comments will be visible to students. Quiz scores are automatically calculated.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitQuizGrade()">
                    Save Comments
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

<!-- Pass permissions to JavaScript -->
<script>
    window.currentUserRole = '<?php echo $userRole; ?>';
    window.canManageQuizzes = <?php echo Permission::canManageQuizzes() ? 'true' : 'false'; ?>;
    window.isAdmin = <?php echo Permission::isAdmin() ? 'true' : 'false'; ?>;
    window.isTeacher = <?php echo Permission::isTeacher() ? 'true' : 'false'; ?>';
    window.userId = <?php echo $user['id']; ?>;
    window.gradingPeriod = 'prelim'; // Set the grading period for this page
</script>

<!-- Custom JavaScript for Prelim Quiz Submissions -->
<script>
// Global variables
var prelimQuizSubmissionsTable;
var currentSubmissionId = null;

$(document).ready(function () {
    initializePrelimQuizSubmissionsTable();
    setupModalEvents();
});

/**
 * Initialize Prelim Quiz Submissions DataTable
 */
function initializePrelimQuizSubmissionsTable() {
    prelimQuizSubmissionsTable = $("#prelimQuizSubmissionsTable").DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "app/API/apiQuizSubmissions.php?action=datatable",
            type: "GET",
            data: function(d) {
                const statusEl = document.getElementById('prelimQuizStatusFilter');
                const status = statusEl ? statusEl.value : '';
                
                console.log('Prelim Quiz Submissions DataTable - Sending filters:', {
                    grading_period: 'prelim',
                    status: status
                });
                
                // Always filter by prelim period
                d.grading_period = 'prelim';
                
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
                    text: "Failed to load quiz submissions data: " + error,
                });
            },
        },
        "columns": [
            { 
                "data": "student_name", 
                "width": "20%",
                "render": function(data, type, row) {
                    return `<strong>${data}</strong>`;
                }
            },
            { 
                "data": "quiz_title", 
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
                "data": "attempt_number", 
                "width": "8%",
                "render": function(data, type, row) {
                    return `<span class="badge bg-info">${data}</span>`;
                }
            },
            { 
                "data": "score", 
                "width": "10%",
                "render": function(data, type, row) {
                    if (data && data !== 'Not Graded') {
                        return `<span class="badge bg-success">${data}</span>`;
                    }
                    return '<span class="badge bg-secondary">Not Graded</span>';
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
                        case 'in_progress':
                            badgeClass = 'badge bg-warning';
                            badgeText = 'In Progress';
                            break;
                        case 'timeout':
                            badgeClass = 'badge bg-danger';
                            badgeText = 'Timeout';
                            break;
                        default:
                            badgeClass = 'badge bg-secondary';
                            badgeText = data || 'Unknown';
                    }
                    
                    return `<span class="${badgeClass}">${badgeText}</span>`;
                }
            },
            { 
                "data": "submitted_at", 
                "width": "12%",
                "render": function(data, type, row) {
                    if (data && data !== '0000-00-00 00:00:00') {
                        return new Date(data).toLocaleString();
                    }
                    return 'N/A';
                }
            },
            { 
                "data": "actions", 
                "orderable": false,
                "width": "10%",
                "render": function(data, type, row) {
                    let actions = '<div class="btn-group gap-1" role="group">';
                    
                    // View Details button (always available)
                    actions += `
                        <button class="btn btn-outline-primary" onclick="displayQuizSubmissionDetails(${data})" title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>
                    `;
                    
                    // Grade button (for admin/teacher)
                    if (window.canManageQuizzes) {
                        actions += `
                            <button class="btn btn-outline-success" onclick="populateGradeQuizForm(${data})" title="Grade Submission">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        `;
                    }
                    
                    actions += '</div>';
                    return actions;
                }
            }
        ],
        "order": [[6, "desc"]],
        "pageLength": 10,
        "responsive": true,
        "language": {
            "processing": "Loading prelim quiz submissions...",
            "emptyTable": "No prelim quiz submissions found",
            "zeroRecords": "No matching prelim quiz submissions found"
        }
    });
}

/**
 * Filter by status
 */
function filterByStatus() {
    const statusEl = document.getElementById('prelimQuizStatusFilter');
    const selectedValue = statusEl ? statusEl.value : '';
    console.log('Prelim Quiz Submissions - Status Filter Changed:', selectedValue);
    
    if (prelimQuizSubmissionsTable) {
        prelimQuizSubmissionsTable.ajax.reload();
    }
}

/**
 * Test prelim quiz filters function
 */
function testPrelimQuizFilters() {
    console.log('=== PRELIM QUIZ SUBMISSIONS FILTER DEBUG ===');
    
    const statusEl = document.getElementById('prelimQuizStatusFilter');
    
    console.log('DOM Elements Check:', {
        statusFilter: !!statusEl,
        statusValue: statusEl ? statusEl.value : 'ELEMENT NOT FOUND'
    });
    
    const status = statusEl ? statusEl.value : '';
    
    // Test API directly
    const testUrl = `app/API/apiQuizSubmissions.php?action=datatable&draw=999&start=0&length=10&grading_period=prelim&status=${status}`;
    console.log('Test URL:', testUrl);
    
    fetch(testUrl)
        .then(response => response.json())
        .then(data => {
            console.log('API Response:', data);
            alert(`Prelim Quiz API Test Results:\nTotal Records: ${data.recordsTotal}\nFiltered Records: ${data.recordsFiltered}\nData Count: ${data.data.length}\n\nCheck console for full response.`);
        })
        .catch(error => {
            console.error('API Test Error:', error);
            alert('API Test Failed - Check console for details');
        });
}

// Reuse existing functions from quiz submissions
function displayQuizSubmissionDetails(id) {
    // Implementation would be similar to quizzes-submissions.php
    console.log('Display quiz submission details:', id);
}

function populateGradeQuizForm(id) {
    // Implementation would be similar to quizzes-submissions.php  
    console.log('Populate grade quiz form:', id);
}

function submitQuizGrade() {
    // Implementation would be similar to quizzes-submissions.php
    console.log('Submit quiz grade');
}

function setupModalEvents() {
    // Setup modal events similar to quizzes-submissions.php
}

function exportQuizSubmissionsData() {
    // Export functionality
    Swal.fire({
        icon: 'info',
        title: 'Coming Soon!',
        text: 'Export functionality will be available soon.'
    });
}

function printQuizSubmission() {
    // Print functionality
    window.print();
}
</script>
</body>

</html>
