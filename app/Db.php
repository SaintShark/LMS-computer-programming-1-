<?php
    class Db {
        public static function connect() {
            try {
                $conn = new PDO("mysql:host=localhost;dbname=lms;charset=utf8mb4", 'root', '');
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                return $conn;
            } catch (PDOException $e) {
                die("Connection failed: " . $e->getMessage());
            }
        }
        
        public static function getConnection() {
            return self::connect();
        }
    }
?>