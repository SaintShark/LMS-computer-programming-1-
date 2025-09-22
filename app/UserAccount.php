<?php

class UserAccount
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getAllUserAccounts($search = '', $status = '', $start = 0, $length = 25, $order = [])
    {
        $sql = "SELECT IdUserAccount, FirstName, LastName, EmailAddress, Permission, UserStatus, DateRegistered 
                 FROM user_accounts";
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(CONCAT(FirstName, ' ', LastName) LIKE :search OR EmailAddress LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        if ($status !== '' && $status !== 'all') {
            $whereConditions[] = "UserStatus = :status";
            $params[':status'] = (int)$status;
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        if (!empty($order)) {
            $column = $order[0]['column'] ?? 'DateRegistered';
            $dir = $order[0]['dir'] ?? 'desc';
            $sql .= " ORDER BY $column $dir";
        } else {
            $sql .= " ORDER BY DateRegistered DESC";
        }
        
        $sql .= " LIMIT :start, :length";
        $stmt = $this->conn->prepare($sql);

        foreach ($params as $key => $value) {
            if ($key === ':status') {
                $stmt->bindValue($key, (int)$value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserAccountById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM user_accounts WHERE IdUserAccount = :id LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createUserAccount($postData)
    {
        // Check if email already exists
        if ($this->isEmailExists($postData['EmailAddress'])) {
            throw new Exception('Email address already exists. Please use a different email.');
        }
        
        // Hash passwords before binding
        $tempPassHash = md5($postData['TempPass']);
        $permPassHash = md5($postData['PermPass']);
        
        $stmt = $this->conn->prepare("INSERT INTO user_accounts (FirstName, LastName, EmailAddress, TempPass, PermPass, Permission, UserStatus) 
                                      VALUES (:FirstName, :LastName, :EmailAddress, :TempPass, :PermPass, :Permission, :UserStatus)");
        $stmt->bindParam(':FirstName', $postData['FirstName'], PDO::PARAM_STR);
        $stmt->bindParam(':LastName', $postData['LastName'], PDO::PARAM_STR);
        $stmt->bindParam(':EmailAddress', $postData['EmailAddress'], PDO::PARAM_STR);
        $stmt->bindParam(':TempPass', $tempPassHash, PDO::PARAM_STR);
        $stmt->bindParam(':PermPass', $permPassHash, PDO::PARAM_STR);
        $stmt->bindParam(':Permission', $postData['Permission'], PDO::PARAM_INT);
        $stmt->bindParam(':UserStatus', $postData['UserStatus'], PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0 ? $this->conn->lastInsertId() : false;
    }

    public function updateUserAccount($postData)
    {
        // Check if email already exists (excluding current user)
        if ($this->isEmailExists($postData['EmailAddress'], $postData['IdUserAccount'])) {
            throw new Exception('Email address already exists. Please use a different email.');
        }
        
        // Build dynamic SQL based on which password fields are provided
        $sql = "UPDATE user_accounts SET 
                FirstName = :FirstName, 
                LastName = :LastName, 
                EmailAddress = :EmailAddress, 
                Permission = :Permission, 
                UserStatus = :UserStatus";
        
        $params = [
            ':FirstName' => $postData['FirstName'],
            ':LastName' => $postData['LastName'],
            ':EmailAddress' => $postData['EmailAddress'],
            ':Permission' => $postData['Permission'],
            ':UserStatus' => $postData['UserStatus'],
            ':IdUserAccount' => $postData['IdUserAccount']
        ];
        
        // Only update passwords if they are provided
        if (!empty($postData['TempPass'])) {
            $sql .= ", TempPass = :TempPass";
            $params[':TempPass'] = md5($postData['TempPass']);
        }
        
        if (!empty($postData['PermPass'])) {
            $sql .= ", PermPass = :PermPass";
            $params[':PermPass'] = md5($postData['PermPass']);
        }
        
        $sql .= " WHERE IdUserAccount = :IdUserAccount";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            if (strpos($key, 'IdUserAccount') !== false) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } elseif (strpos($key, 'Permission') !== false || strpos($key, 'UserStatus') !== false) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function deleteUserAccount($id)
    {
        // Check if user account is used in job orders
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM job_orders WHERE OrderAuthor = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            throw new Exception('Cannot delete user account. It is being used in job orders.');
        }
        
        // Check if user account is used in order costs
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM order_costs WHERE EncodeAuthor = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            throw new Exception('Cannot delete user account. It is being used in order costs.');
        }
        
        $stmt = $this->conn->prepare("DELETE FROM user_accounts WHERE IdUserAccount = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function isEmailExists($email, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) as count FROM user_accounts WHERE EmailAddress = :email";
        $params = [':email' => $email];
        
        if ($excludeId) {
            $sql .= " AND IdUserAccount != :excludeId";
            $params[':excludeId'] = $excludeId;
        }
        
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    public function searchUserAccounts($search, $limit = 10)
    {
        $stmt = $this->conn->prepare("SELECT IdUserAccount, FirstName, LastName, EmailAddress, Permission, UserStatus 
                                      FROM user_accounts 
                                      WHERE (CONCAT(FirstName, ' ', LastName) LIKE :search OR EmailAddress LIKE :search)
                                      ORDER BY DateRegistered DESC 
                                      LIMIT :limit");
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserAccountsCount($search = '', $status = '')
    {
        $sql = "SELECT COUNT(*) as count FROM user_accounts";
        $params = [];
        
        if (!empty($search)) {
            $sql .= " WHERE (CONCAT(FirstName, ' ', LastName) LIKE :search OR EmailAddress LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        if ($status !== '' && $status !== 'all') {
            $whereClause = !empty($search) ? " AND UserStatus = :status" : " WHERE UserStatus = :status";
            $sql .= $whereClause;
            $params[':status'] = (int)$status;
        }
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            if ($key === ':status') {
                $stmt->bindValue($key, (int)$value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    public function getActiveUserAccounts()
    {
        $stmt = $this->conn->prepare("SELECT IdUserAccount, FirstName, LastName, EmailAddress 
                                      FROM user_accounts 
                                      WHERE UserStatus = 0 
                                      ORDER BY FirstName, LastName ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function authenticateUser($email, $password)
    {
        // Check if user exists and is active
        $stmt = $this->conn->prepare("SELECT * FROM user_accounts 
                                      WHERE EmailAddress = :email AND UserStatus = 0 
                                      LIMIT 1");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Hash the input password with MD5
            $hashedPassword = md5($password);
            
            // Check if password matches (TempPass or PermPass) - both are MD5 hashed
            if ($hashedPassword === $user['TempPass'] || $hashedPassword === $user['PermPass']) {
                return $user;
            }
        }
        
        return false;
    }
}
