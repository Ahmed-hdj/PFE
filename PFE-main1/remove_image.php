<?php
session_start();
require_once 'config/database.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$lieu_id = isset($data['lieu_id']) ? intval($data['lieu_id']) : 0;
$image_url = $data['image_url'] ?? '';

if ($lieu_id <= 0 || empty($image_url)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Delete image record from database
    $stmt = $pdo->prepare("DELETE FROM lieu_images WHERE lieu_id = ? AND image_url = ?");
    $stmt->execute([$lieu_id, $image_url]);

    // Delete image file if it exists
    if (file_exists($image_url)) {
        unlink($image_url);
    }

    // Commit transaction
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Image removed successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error removing image: ' . $e->getMessage()]);
} 