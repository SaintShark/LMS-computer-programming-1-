<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['idAccount']) || empty($_SESSION['idAccount'])) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit();
}

// Include database connection and required classes
require_once __DIR__ . '/../Db.php';
require_once __DIR__ . '/../JobOrders.php';
require_once __DIR__ . '/../OrderCosts.php';
require_once __DIR__ . '/../Reports.php';
require_once __DIR__ . '/../Clients.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    $conn = Db::getConnection();
    $jobOrders = new JobOrders($conn);
    $orderCosts = new OrderCosts($conn);
    $reports = new Reports($conn);
    $clients = new Clients($conn);

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'getBulkExpensesReport':
            // DataTables server-side processing for bulk expenses report
            $draw = $_POST['draw'] ?? 1;
            $start = $_POST['start'] ?? 0;
            $length = $_POST['length'] ?? 25;
            $search = $_POST['search']['value'] ?? '';
            $dateFrom = $_POST['dateFrom'] ?? '';
            $dateTo = $_POST['dateTo'] ?? '';
            $clientId = $_POST['clientId'] ?? '';
            $status = $_POST['status'] ?? '';
            
            // Get order column
            $orderColumn = $_POST['order'][0]['column'] ?? 0;
            $orderDir = $_POST['order'][0]['dir'] ?? 'desc';
            
            // Map DataTables column index to database column
            $columns = [
                0 => 'jo.DateCreated',
                1 => 'jo.JONumber',
                2 => 'c.ClientName',
                3 => 'jo.OrderStatus',
                4 => 'jo.TotalAmount',
                5 => 'total_expenses',
                6 => 'profit_loss'
            ];
            
            $order = [];
            if (isset($columns[$orderColumn])) {
                $order[] = [
                    'column' => $columns[$orderColumn],
                    'dir' => $orderDir
                ];
            }
            
            $data = $reports->getBulkExpensesReport($search, $dateFrom, $dateTo, $clientId, $status, $start, $length, $order);
            $totalRecords = $reports->getBulkExpensesReportCount($search, $dateFrom, $dateTo, $clientId, $status);
            $filteredRecords = $totalRecords;
            
            echo json_encode([
                'status' => 1,
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data
            ]);
            break;

        case 'getExpensesByJobOrder':
            $jobOrderId = $_POST['jobOrderId'] ?? 0;
            if ($jobOrderId) {
                $expenses = $orderCosts->getExpensesByJobOrder($jobOrderId);
                echo json_encode(['status' => 1, 'data' => $expenses]);
            } else {
                echo json_encode(['status' => 0, 'message' => 'Invalid job order ID']);
            }
            break;

        case 'getJobOrderFinancialSummary':
            $jobOrderId = $_POST['jobOrderId'] ?? 0;
            if ($jobOrderId) {
                $summary = $orderCosts->calculateProfitLoss($jobOrderId);
                $jobOrderInfo = $orderCosts->getJobOrderInfo($jobOrderId);
                echo json_encode([
                    'status' => 1, 
                    'data' => [
                        'summary' => $summary,
                        'jobOrderInfo' => $jobOrderInfo
                    ]
                ]);
            } else {
                echo json_encode(['status' => 0, 'message' => 'Invalid job order ID']);
            }
            break;

        case 'exportBulkExpensesReport':
            $dateFrom = $_POST['dateFrom'] ?? '';
            $dateTo = $_POST['dateTo'] ?? '';
            $clientId = $_POST['clientId'] ?? '';
            $status = $_POST['status'] ?? '';
            $format = $_POST['format'] ?? 'excel'; // excel, pdf, csv
            
            if (empty($dateFrom) || empty($dateTo)) {
                echo json_encode(['status' => 0, 'message' => 'Date range is required']);
                break;
            }
            
            $reportData = $reports->getBulkExpensesReport('', $dateFrom, $dateTo, $clientId, $status, 0, 10000, []);
            
            if ($format === 'excel') {
                $result = $reports->exportToExcel($reportData, $dateFrom, $dateTo);
            } elseif ($format === 'pdf') {
                $result = $reports->exportToPDF($reportData, $dateFrom, $dateTo);
            } elseif ($format === 'csv') {
                $result = $reports->exportToCSV($reportData, $dateFrom, $dateTo);
            } else {
                echo json_encode(['status' => 0, 'message' => 'Invalid export format']);
                break;
            }
            
            if ($result['status']) {
                echo json_encode(['status' => 1, 'data' => $result['data'], 'message' => 'Report exported successfully']);
            } else {
                echo json_encode(['status' => 0, 'message' => $result['message']]);
            }
            break;

        case 'getReportSummary':
            $dateFrom = $_POST['dateFrom'] ?? '';
            $dateTo = $_POST['dateTo'] ?? '';
            $clientId = $_POST['clientId'] ?? '';
            $status = $_POST['status'] ?? '';
            
            $summary = $reports->getReportSummary($dateFrom, $dateTo, $clientId, $status);
            echo json_encode(['status' => 1, 'data' => $summary]);
            break;

        case 'getClients':
            $stmt = $conn->prepare("SELECT IdClient, ClientName FROM clients WHERE ClientStatus = 0 ORDER BY ClientName");
            $stmt->execute();
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 1, 'data' => $clients]);
            break;

        case 'getStatusOptions':
            $statusOptions = [
                ['value' => '', 'label' => 'All Status'],
                ['value' => '0', 'label' => 'Pending'],
                ['value' => '1', 'label' => 'Ongoing'],
                ['value' => '2', 'label' => 'Completed'],
                ['value' => '3', 'label' => 'Cancelled']
            ];
            echo json_encode(['status' => 1, 'data' => $statusOptions]);
            break;

        default:
            echo json_encode(['status' => 0, 'message' => 'Invalid action']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 0, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
