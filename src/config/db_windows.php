<?php
// Windows-optimized database configuration
function get_db_connection_windows() {
    $hosts = ['127.0.0.1', 'localhost'];
    $dbname = 'muzic2';
    $username = 'root';
    $passwords = ['root', ''];
    
    $ports = [8889, 3306];
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 1,
    ];

    foreach ($hosts as $host) {
        foreach ($ports as $port) {
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            foreach ($passwords as $password) {
                try {
                    return new PDO($dsn, $username, $password, $opts);
                } catch (PDOException $e) {
                    continue;
                }
            }
        }
    }
    throw new Exception('Database connection failed: Unable to connect to MySQL on ports 8889 or 3306');
}
?>
