<?php
session_start();
require_once 'config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$demand_id = $data['demand_id'] ?? null;
$status = $data['status'] ?? null;

if (!$demand_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE demands SET status = ? WHERE id = ?");
    $result = $stmt->execute([$status, $demand_id]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update demand']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 