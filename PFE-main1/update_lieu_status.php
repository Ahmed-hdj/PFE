<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to perform this action']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'You must be an admin to perform this action']);
        exit();
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error checking user role']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['lieu_id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$lieu_id = intval($data['lieu_id']);
$status = $data['status'];

// Validate status
if (!in_array($status, ['approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Update lieu status
    $stmt = $pdo->prepare("UPDATE lieu SET status = ? WHERE lieu_id = ?");
    $stmt->execute([$status, $lieu_id]);

    // If approved, you might want to do additional actions here
    // For example, send notifications, update statistics, etc.

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Place status updated successfully']);
} catch(PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error updating place status: ' . $e->getMessage()]);
} 