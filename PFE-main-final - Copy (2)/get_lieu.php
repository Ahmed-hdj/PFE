<?php
session_start();
require_once 'config/database.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get lieu ID from request
$lieu_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($lieu_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid lieu ID']);
    exit();
}

try {
    // Get lieu data
    $stmt = $pdo->prepare("
        SELECT l.*, c.category_name,
        GROUP_CONCAT(li.image_url) as images
        FROM lieu l
        JOIN categories c ON l.category_id = c.category_id
        LEFT JOIN lieu_images li ON l.lieu_id = li.lieu_id
        WHERE l.lieu_id = ?
        GROUP BY l.lieu_id
    ");
    $stmt->execute([$lieu_id]);
    $lieu = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lieu) {
        // Convert images string to array
        $lieu['images'] = $lieu['images'] ? explode(',', $lieu['images']) : [];
        echo json_encode(['success' => true, 'lieu' => $lieu]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lieu not found']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 