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
    $userAccount = new UserAccount($conn);

    $response = [
        'status' => 0,
        'message' => 'No action taken',
        'data' => null
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['get_user_accounts'])) {
            try {
                $search = $_GET['search'] ?? '';
                $status = $_GET['status'] ?? '';
                $start = $_GET['start'] ?? 0;
                $length = $_GET['length'] ?? 25;
                $order = [];
                
                if (isset($_GET['order'])) {
                    $order = json_decode($_GET['order'], true);
                }
                
                $data = $userAccount->getAllUserAccounts($search, $status, $start, $length, $order);
                $totalCount = $userAccount->getUserAccountsCount($search, $status);
                
                $response = [
                    'status' => 1,
                    'message' => 'User accounts retrieved successfully',
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
        } elseif (isset($_GET['get_user_account'])) {
            try {
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    throw new Exception('User Account ID is required');
                }
                $data = $userAccount->getUserAccountById($id);
                if ($data) {
                    $response = [
                        'status' => 1,
                        'message' => 'User account retrieved successfully',
                        'data' => $data
                    ];
                } else {
                    $response = [
                        'status' => 0,
                        'message' => 'User account not found',
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
        } elseif (isset($_GET['search_user_accounts'])) {
            try {
                $search = $_GET['search'] ?? '';
                $limit = $_GET['limit'] ?? 10;
                
                if (empty($search)) {
                    throw new Exception('Search term is required');
                }
                
                $data = $userAccount->searchUserAccounts($search, $limit);
                $response = [
                    'status' => 1,
                    'message' => 'User accounts search completed',
                    'data' => $data
                ];
            } catch (Exception $e) {
                $response = [
                    'status' => 0,
                    'message' => $e->getMessage(),
                    'data' => null
                ];
            }
        } elseif (isset($_GET['get_active_user_accounts'])) {
            try {
                $data = $userAccount->getActiveUserAccounts();
                $response = [
                    'status' => 1,
                    'message' => 'Active user accounts retrieved successfully',
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
        if (isset($_POST['create_user_account'])) {
            try {
                $postData = $_POST;
                $userAccountId = $userAccount->createUserAccount($postData);
                if ($userAccountId) {
                    $response = [
                        'status' => 1,
                        'message' => 'User account created successfully',
                        'data' => ['id' => $userAccountId]
                    ];
                } else {
                    $response = [
                        'status' => 0,
                        'message' => 'Failed to create user account',
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
        } elseif (isset($_POST['update_user_account'])) {
            try {
                $postData = $_POST;
                $result = $userAccount->updateUserAccount($postData);
                if ($result) {
                    $response = [
                        'status' => 1,
                        'message' => 'User account updated successfully',
                        'data' => null
                    ];
                } else {
                    $response = [
                        'status' => 0,
                        'message' => 'Failed to update user account',
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
        } elseif (isset($_POST['delete_user_account'])) {
            try {
                $id = $_POST['id'] ?? null;
                if (!$id) {
                    throw new Exception('User Account ID is required');
                }
                $result = $userAccount->deleteUserAccount($id);
                if ($result) {
                    $response = [
                        'status' => 1,
                        'message' => 'User account deleted successfully',
                        'data' => null
                    ];
                } else {
                    $response = [
                        'status' => 0,
                        'message' => 'Failed to delete user account',
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
