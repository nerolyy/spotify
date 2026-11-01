<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
@ini_set('default_socket_timeout', '5');

function get_db_connection() {
    $hosts = ['127.0.0.1', 'localhost'];
    $dbname = 'muzic2';
    $username = 'root';
    $passwords = ['root', ''];
    
    // MAMP чаще использует 8889, затем стандартный 3306.
    // Короткий timeout убирает долгие подвисания при "неверном" первом порте.
    $ports = [8889, 3306];
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 1,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    
    foreach ($hosts as $host) {
        foreach ($ports as $port) {
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            foreach ($passwords as $password) {
                try {
                    $pdo = new PDO($dsn, $username, $password, $opts);
                    return $pdo;
                } catch (PDOException $e) {
                    continue;
                }
            }
        }
    }
    
    // Если ни один порт не сработал
    throw new Exception('Database connection failed: Unable to connect to MySQL on ports 3306 or 8889');
} 