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
$image_id = $data['image_id'] ?? null;
$status = $data['status'] ?? null;

if (!$image_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

if (!in_array($status, ['approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // Get the image URL before updating
    $stmt = $pdo->prepare("SELECT image_url FROM lieu_images WHERE image_id = ?");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch();

    if (!$image) {
        echo json_encode(['success' => false, 'message' => 'Image not found']);
        exit();
    }

    // Update the image status
    $stmt = $pdo->prepare("UPDATE lieu_images SET status_photo = ? WHERE image_id = ?");
    $result = $stmt->execute([$status, $image_id]);
    
    if ($result) {
        // If the image is rejected, delete the file
        if ($status === 'rejected' && file_exists('../' . $image['image_url'])) {
            unlink('../' . $image['image_url']);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update image status']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 