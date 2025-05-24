<?php
$host = 'localhost';
$dbname = 'pfe';
$username = 'root';
$password = '';

try {
    // Enable error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Create PDO connection with additional options
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        )
    );

    // Test the connection
    $pdo->query('SELECT 1');
    
} catch(PDOException $e) {
    error_log('Database Connection Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    die("Database connection failed. Please check your configuration.");
}
?> 