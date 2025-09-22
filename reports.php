<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Check if logged in
if (!isset($_SESSION['idAccount']) || empty($_SESSION['idAccount'])) {
    header('Location: login.php');
    exit();
}

// Include layout components
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/topNav.php';
require_once __DIR__ . '/components/sideNav.php';
?>
<main id="main" class="main">
    <div class="container">
        <!-- Filters Section -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Manage Reports</h5>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <div class="input-group">
                                    <input type="text" id="searchInput" class="form-control shadow-none"
                                        placeholder="Search client name or job order number">
                                    <button class="btn btn-outline-secondary" type="button" id="reportsClearSearchBtn"
                                        style="display: none;">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control shadow-none" id="dateFrom"
                                    placeholder="Date From">
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control shadow-none" id="dateTo"
                                    placeholder="Date To">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select shadow-none" id="clientFilter">
                                    <option value="">All Clients</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select shadow-none" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="0">Pending</option>
                                    <option value="1">Ongoing</option>
                                    <option value="2">Completed</option>
                                    <option value="3">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger shadow-none w-100" id="exportPDFBtn">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Table Section -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Job Order Expenses Report</h5>
                        <!-- Table -->
                        <div class="table-responsive">
                            <table id="reportsTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date Created</th>
                                        <th>JO Number</th>
                                        <th>Client Name</th>
                                        <th>Status</th>
                                        <th>Total Amount</th>
                                        <th>Total Expenses</th>
                                        <th>Profit/Loss</th>
                                        <th>Author</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data will be loaded dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Include Job Order Expenses Modal -->
<?php require_once __DIR__ . '/components/modals/job_order_expenses_modal.php'; ?>

<?php require_once __DIR__ . '/components/footer.php'; ?>
</body>

</html>
