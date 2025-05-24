<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to save places']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$lieu_id = $data['lieu_id'] ?? null;

if (!$lieu_id) {
    echo json_encode(['success' => false, 'message' => 'Missing lieu_id']);
    exit();
}

try {
    // Check if the save already exists
    $check_stmt = $pdo->prepare("SELECT * FROM lieu_saves WHERE lieu_id = ? AND user_id = ?");
    $check_stmt->execute([$lieu_id, $_SESSION['user_id']]);
    $existing_save = $check_stmt->fetch();

    if ($existing_save) {
        // Unsave the lieu
        $stmt = $pdo->prepare("DELETE FROM lieu_saves WHERE lieu_id = ? AND user_id = ?");
        $stmt->execute([$lieu_id, $_SESSION['user_id']]);
        echo json_encode(['success' => true, 'action' => 'unsaved']);
    } else {
        // Save the lieu
        $stmt = $pdo->prepare("INSERT INTO lieu_saves (lieu_id, user_id) VALUES (?, ?)");
        $stmt->execute([$lieu_id, $_SESSION['user_id']]);
        echo json_encode(['success' => true, 'action' => 'saved']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 