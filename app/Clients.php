<?php

class Clients
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getAllClients($search = '', $start = 0, $length = 25, $order = [])
    {
        $sql = "SELECT * FROM clients";
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(ClientName LIKE :search OR ContactPerson LIKE :search OR ContactNumber LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        if (!empty($order)) {
            $column = $order[0]['column'] ?? 'ClientName';
            $dir = $order[0]['dir'] ?? 'asc';
            $sql .= " ORDER BY $column $dir";
        } else {
            $sql .= " ORDER BY ClientName ASC";
        }
        
        $sql .= " LIMIT :start, :length";
        $stmt = $this->conn->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClientById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM clients WHERE IdClient = :id LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createClient($postData)
    {
        // Check if client name already exists
        if ($this->isClientNameExists($postData['ClientName'])) {
            throw new Exception('Client name already exists. Please use a different name.');
        }
        
        $stmt = $this->conn->prepare("INSERT INTO clients (ClientName, ClientAddress, ContactPerson, ContactNumber, EmailAddress, ClientStatus) 
                                     VALUES (:ClientName, :ClientAddress, :ContactPerson, :ContactNumber, :EmailAddress, :ClientStatus)");
        $stmt->bindParam(':ClientName', $postData['ClientName'], PDO::PARAM_STR);
        $stmt->bindParam(':ClientAddress', $postData['ClientAddress'], PDO::PARAM_STR);
        $stmt->bindParam(':ContactPerson', $postData['ContactPerson'], PDO::PARAM_STR);
        $stmt->bindParam(':ContactNumber', $postData['ContactNumber'], PDO::PARAM_STR);
        $stmt->bindParam(':EmailAddress', $postData['EmailAddress'], PDO::PARAM_STR);
        $stmt->bindParam(':ClientStatus', $postData['ClientStatus'], PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0 ? $this->conn->lastInsertId() : false;
    }

    public function updateClient($postData)
    {
        // Check if client name already exists (excluding current client)
        if ($this->isClientNameExists($postData['ClientName'], $postData['IdClient'])) {
            throw new Exception('Client name already exists. Please use a different name.');
        }
        
        $stmt = $this->conn->prepare("UPDATE clients SET 
                                     ClientName = :ClientName, 
                                     ClientAddress = :ClientAddress, 
                                     ContactPerson = :ContactPerson, 
                                     ContactNumber = :ContactNumber, 
                                     EmailAddress = :EmailAddress, 
                                     ClientStatus = :ClientStatus 
                                     WHERE IdClient = :IdClient");
        $stmt->bindParam(':ClientName', $postData['ClientName'], PDO::PARAM_STR);
        $stmt->bindParam(':ClientAddress', $postData['ClientAddress'], PDO::PARAM_STR);
        $stmt->bindParam(':ContactPerson', $postData['ContactPerson'], PDO::PARAM_STR);
        $stmt->bindParam(':ContactNumber', $postData['ContactNumber'], PDO::PARAM_STR);
        $stmt->bindParam(':EmailAddress', $postData['EmailAddress'], PDO::PARAM_STR);
        $stmt->bindParam(':ClientStatus', $postData['ClientStatus'], PDO::PARAM_INT);
        $stmt->bindParam(':IdClient', $postData['IdClient'], PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function deleteClient($id)
    {
        // Check if client has job orders
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM job_orders WHERE ClientId = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            throw new Exception('Cannot delete client. Client has associated job orders.');
        }
        
        $stmt = $this->conn->prepare("DELETE FROM clients WHERE IdClient = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function isClientNameExists($clientName, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) as count FROM clients WHERE ClientName = :clientName";
        $params = [':clientName' => $clientName];
        
        if ($excludeId) {
            $sql .= " AND IdClient != :excludeId";
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

    public function searchClients($search, $limit = 10)
    {
        $stmt = $this->conn->prepare("SELECT IdClient, ClientName, ContactPerson, ContactNumber, EmailAddress 
                                     FROM clients 
                                     WHERE ClientStatus = 0 AND (ClientName LIKE :search OR ContactPerson LIKE :search)
                                     ORDER BY ClientName ASC 
                                     LIMIT :limit");
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveClients()
    {
        $stmt = $this->conn->prepare("SELECT IdClient, ClientName, ContactPerson, ContactNumber 
                                     FROM clients 
                                     WHERE ClientStatus = 0 
                                     ORDER BY ClientName ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClientsCount($search = '')
    {
        $sql = "SELECT COUNT(*) as count FROM clients";
        $params = [];
        
        if (!empty($search)) {
            $sql .= " WHERE (ClientName LIKE :search OR ContactPerson LIKE :search OR ContactNumber LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
}
