<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$lieu_id = $data['lieu_id'] ?? null;

if (!$lieu_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid lieu ID']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Delete associated images first
    $stmt = $pdo->prepare("DELETE FROM lieu_images WHERE lieu_id = ?");
    $stmt->execute([$lieu_id]);

    // Delete the lieu
    $stmt = $pdo->prepare("DELETE FROM lieu WHERE lieu_id = ?");
    $stmt->execute([$lieu_id]);

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Place deleted successfully']);
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error deleting place: ' . $e->getMessage()]);
} 