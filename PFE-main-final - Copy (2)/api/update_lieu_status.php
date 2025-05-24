<?php
session_start();
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Not authorized']);
        exit();
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
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

if (!in_array($status, ['approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // Update the lieu status
    $stmt = $pdo->prepare("UPDATE lieu SET status = ? WHERE lieu_id = ?");
    $result = $stmt->execute([$status, $lieu_id]);
    
    if ($result) {
        // If the lieu is rejected, also reject all its pending images
        if ($status === 'rejected') {
            $stmt = $pdo->prepare("
                UPDATE lieu_images 
                SET status_photo = 'rejected' 
                WHERE lieu_id = ? AND status_photo = 'pending'
            ");
            $stmt->execute([$lieu_id]);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update lieu status']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 