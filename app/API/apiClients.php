<?php
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);
    require_once('../Db.php');

    spl_autoload_register(function ($class) {
        $classFile = '../' . $class . '.php';
        if (file_exists($classFile)) {
            require_once($classFile);
        } else {
            throw new Exception("Required class file not found: " . $class);
        }
    });

    $conn = Db::getConnection();
    $clients = new Clients($conn);

    $response = [
        'status' => 0,
        'message' => 'No action taken',
        'data' => null
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['get_clients'])) {
            try {
                $search = $_GET['search'] ?? '';
                $start = $_GET['start'] ?? 0;
                $length = $_GET['length'] ?? 25;
                $order = [];
                
                if (isset($_GET['order'])) {
                    $order = json_decode($_GET['order'], true);
                }
                
                $data = $clients->getAllClients($search, $start, $length, $order);
                $totalCount = $clients->getClientsCount($search);
                
                $response = [
                    'status' => 1,
                    'message' => 'Clients retrieved successfully',
                    'data' => [
                        'data' => $data,
                        'recordsTotal' => $totalCount,
                        'recordsFiltered' => $totalCount
                    ]
                ];
            } catch (Exception $e) {
                $response = [
                    'status' => 0,
                    'message' => $e->getMessage(),
                    'data' => null
                ];
            }
        } elseif (isset($_GET['get_client'])) {
            try {
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    throw new Exception('Client ID is required');
                }
                $data = $clients->getClientById($id);
                if ($data) {
                    $response = [
                        'status' => 1,
                        'message' => 'Client retrieved successfully',
                        'data' => $data
                    ];
                } else {
                    $response = [
                        'status' => 0,
                        'message' => 'Client not found',
                        'data' => null
                    ];
                }
            } catch (Exception $e) {
                $response = [
                    'status' => 0,
                    'message' => $e->getMessage(),
                    'data' => null
                ];
            }
        } elseif (isset($_GET['search_clients'])) {
            try {
                $search = $_GET['search'] ?? '';
                $limit = $_GET['limit'] ?? 10;
                
                if (empty($search)) {
                    throw new Exception('Search term is required');
                }
                
                $data = $clients->searchClients($search, $limit);
                $response = [
                    'status' => 1,
                    'message' => 'Clients search completed',
                    'data' => $data
                ];
            } catch (Exception $e) {
                $response = [
                    'status' => 0,
                    'message' => $e->getMessage(),
                    'data' => null
                ];
            }
        } elseif (isset($_GET['get_active_clients'])) {
            try {
                $data = $clients->getActiveClients();
                $response = [
                    'status' => 1,
                    'message' => 'Active clients retrieved successfully',
                    'data' => $data
                ];
            } catch (Exception $e) {
                $response = [
                    'status' => 0,
                    'message' => $e->getMessage(),
                    'data' => null
                ];
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['create_client'])) {
            try {
                $postData = $_POST;
                $clientId = $clients->createClient($postData);
                if ($clientId) {
                    $response = [
                        'status' => 1,
                        'message' => 'Client created successfully',
                        'data' => ['id' => $clientId]
                    ];
                } else {
                    $response = [
                        'status' => 0,
                        'message' => 'Failed to create client',
                        'data' => null
                    ];
                }
            } catch (Exception $e) {
                $response = [
                    'status' => 0,
                    'message' => $e->getMessage(),
                    'data' => null
                ];
            }
        } elseif (isset($_POST['update_client'])) {
            try {
                $postData = $_POST;
                $result = $clients->updateClient($postData);
                if ($result) {
                    $response = [
                        'status' => 1,
                        'message' => 'Client updated successfully',
                        'data' => null
                    ];
                } else {
                    $response = [
                        'status' => 0,
                        'message' => 'Failed to update client',
                        'data' => null
                    ];
                }
            } catch (Exception $e) {
                $response = [
                    'status' => 0,
                    'message' => $e->getMessage(),
                    'data' => null
                ];
            }
        } elseif (isset($_POST['delete_client'])) {
            try {
                $id = $_POST['id'] ?? null;
                if (!$id) {
                    throw new Exception('Client ID is required');
                }
                $result = $clients->deleteClient($id);
                if ($result) {
                    $response = [
                        'status' => 1,
                        'message' => 'Client deleted successfully',
                        'data' => null
                    ];
                } else {
                    $response = [
                        'status' => 0,
                        'message' => 'Failed to delete client',
                        'data' => null
                    ];
                }
            } catch (Exception $e) {
                $response = [
                    'status' => 0,
                    'message' => $e->getMessage(),
                    'data' => null
                ];
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
?>
