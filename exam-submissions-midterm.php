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

// Check if user has permission to manage quizzes (exams use same permission)
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
            Midterm Exam Submissions Management
            <?php elseif (Permission::isTeacher()): ?>
            My Students' Midterm Exam Submissions
            <?php endif; ?>
        </h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="exam-submissions.php">Exam Submissions</a></li>
                <li class="breadcrumb-item active">Midterm Period</li>
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
                                Midterm Period Exam Submissions
                                <?php elseif (Permission::isTeacher()): ?>
                                My Students' Midterm Exam Submissions
                                <?php endif; ?>
                            </h5>
                            <div class="d-flex gap-2 align-items-center">
                                <select class="form-select" id="midtermExamStatusFilter" style="width: auto;" onchange="filterByStatus()">
                                    <option value="">All Status</option>
                                    <option value="completed">Completed</option>
                                    <option value="in_progress">In Progress</option>
                                </select>
                                <button type="button" class="btn btn-outline-info" onclick="testMidtermExamFilters()" title="Test Filters">
                                    <i class="bi bi-bug"></i> Test
                                </button>
                                <?php if (Permission::canManageQuizzes()): ?>
                                <button type="button" class="btn btn-outline-success" onclick="exportExamSubmissionsData()"
                                    title="Export Data">
                                    <i class="bi bi-download"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="midtermExamSubmissionsTable">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Exam Title</th>
                                        <th>Subject</th>
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

<!-- Exam Submission Details Modal -->
<div class="modal fade" id="examSubmissionDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="examSubmissionDetailsTitle">Exam Submission Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="examSubmissionDetailsContent">
                    <!-- Exam submission details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printExamSubmissionBtn" onclick="printExamSubmission()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Grade Exam Submission Modal -->
<div class="modal fade" id="gradeExamSubmissionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gradeExamSubmissionTitle">Review Exam Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="gradeExamSubmissionForm">
                    <input type="hidden" id="gradeExamSubmissionId" name="submission_id">
                    <input type="hidden" id="gradeExamAction" name="action" value="grade_exam_submission">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="examScore" class="form-label">
                                    Score Achieved
                                </label>
                                <input type="number" class="form-control" id="examScore" name="score" min="0"
                                    step="0.01" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="examMaxScore" class="form-label">
                                    Max Score
                                </label>
                                <input type="number" class="form-control" id="examMaxScore" name="max_score"
                                    readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="examPercentage" class="form-label">
                                    Percentage
                                </label>
                                <input type="text" class="form-control" id="examPercentage" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="examTimeSpent" class="form-label">
                                    Time Spent
                                </label>
                                <input type="text" class="form-control" id="examTimeSpent" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="examStartedAt" class="form-label">
                                    Started At
                                </label>
                                <input type="text" class="form-control" id="examStartedAt" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="examTeacherComments" class="form-label">
                            Teacher Comments
                        </label>
                        <textarea class="form-control" id="examTeacherComments" name="teacher_comments" rows="4"
                            placeholder="Add feedback and comments for the student..."></textarea>
                    </div>

                    <div class="alert alert-info">
                        <strong>Note:</strong> Comments will be visible to students. Exam scores are automatically calculated.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitExamGrade()">
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
    window.isTeacher = <?php echo Permission::isTeacher() ? 'true' : 'false'; ?>;
    window.userId = <?php echo $user['id']; ?>;
    window.gradingPeriod = 'midterm'; // Set the grading period for this page
</script>

<!-- Custom JavaScript for Midterm Exam Submissions -->
<script>
// Global variables
var midtermExamSubmissionsTable;
var currentSubmissionId = null;

$(document).ready(function () {
    initializeMidtermExamSubmissionsTable();
    setupModalEvents();
});

/**
 * Initialize Midterm Exam Submissions DataTable
 */
function initializeMidtermExamSubmissionsTable() {
    midtermExamSubmissionsTable = $("#midtermExamSubmissionsTable").DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "app/API/apiExamSubmissions.php?action=datatable",
            type: "GET",
            data: function(d) {
                const statusEl = document.getElementById('midtermExamStatusFilter');
                const status = statusEl ? statusEl.value : '';
                
                console.log('Midterm Exam Submissions DataTable - Sending filters:', {
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
                    text: "Failed to load exam submissions data: " + error,
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
                "data": "exam_title", 
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
                        case 'completed':
                            badgeClass = 'badge bg-success';
                            badgeText = 'Completed';
                            break;
                        case 'in_progress':
                            badgeClass = 'badge bg-warning';
                            badgeText = 'In Progress';
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
                "width": "15%",
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
                        <button class="btn btn-outline-primary" onclick="displayExamSubmissionDetails(${data})" title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>
                    `;
                    
                    // Grade button (for admin/teacher)
                    if (window.canManageQuizzes) {
                        actions += `
                            <button class="btn btn-outline-success" onclick="populateGradeExamForm(${data})" title="Grade Submission">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        `;
                    }
                    
                    actions += '</div>';
                    return actions;
                }
            }
        ],
        "order": [[5, "desc"]],
        "pageLength": 10,
        "responsive": true,
        "language": {
            "processing": "Loading midterm exam submissions...",
            "emptyTable": "No midterm exam submissions found",
            "zeroRecords": "No matching midterm exam submissions found"
        }
    });
}

/**
 * Filter by status
 */
function filterByStatus() {
    const statusEl = document.getElementById('midtermExamStatusFilter');
    const selectedValue = statusEl ? statusEl.value : '';
    console.log('Midterm Exam Submissions - Status Filter Changed:', selectedValue);
    
    if (midtermExamSubmissionsTable) {
        midtermExamSubmissionsTable.ajax.reload();
    }
}

/**
 * Test midterm exam filters function
 */
function testMidtermExamFilters() {
    console.log('=== MIDTERM EXAM SUBMISSIONS FILTER DEBUG ===');
    
    const statusEl = document.getElementById('midtermExamStatusFilter');
    
    console.log('DOM Elements Check:', {
        statusFilter: !!statusEl,
        statusValue: statusEl ? statusEl.value : 'ELEMENT NOT FOUND'
    });
    
    const status = statusEl ? statusEl.value : '';
    
    // Test API directly
    const testUrl = `app/API/apiExamSubmissions.php?action=datatable&draw=999&start=0&length=10&grading_period=midterm&status=${status}`;
    console.log('Test URL:', testUrl);
    
    fetch(testUrl)
        .then(response => response.json())
        .then(data => {
            console.log('API Response:', data);
            alert(`Midterm Exam API Test Results:\nTotal Records: ${data.recordsTotal}\nFiltered Records: ${data.recordsFiltered}\nData Count: ${data.data.length}\n\nCheck console for full response.`);
        })
        .catch(error => {
            console.error('API Test Error:', error);
            alert('API Test Failed - Check console for details');
        });
}

// Reuse existing functions from exam submissions
function displayExamSubmissionDetails(id) {
    // Implementation would be similar to exam-submissions.php
    console.log('Display exam submission details:', id);
}

function populateGradeExamForm(id) {
    // Implementation would be similar to exam-submissions.php  
    console.log('Populate grade exam form:', id);
}

function submitExamGrade() {
    // Implementation would be similar to exam-submissions.php
    console.log('Submit exam grade');
}

function setupModalEvents() {
    // Setup modal events similar to exam-submissions.php
}

function exportExamSubmissionsData() {
    // Export functionality
    Swal.fire({
        icon: 'info',
        title: 'Coming Soon!',
        text: 'Export functionality will be available soon.'
    });
}

function printExamSubmission() {
    // Print functionality
    window.print();
}
</script>
</body>

</html>
