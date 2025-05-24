<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$lieu_id = $data['lieu_id'] ?? null;
$status = $data['status'] ?? null;

if (!$lieu_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE lieu SET status = ? WHERE lieu_id = ?");
    $result = $stmt->execute([$status, $lieu_id]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update lieu request']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 