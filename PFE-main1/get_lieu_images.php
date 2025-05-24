<?php
session_start();
require_once 'config/database.php';

// Check if lieu_id is provided
if (!isset($_GET['lieu_id'])) {
    echo json_encode(['success' => false, 'message' => 'Lieu ID is required']);
    exit();
}

$lieu_id = intval($_GET['lieu_id']);

try {
    // Fetch images for the specified lieu
    $stmt = $pdo->prepare("SELECT image_url FROM lieu_images WHERE lieu_id = ? ORDER BY uploaded_at ASC");
    $stmt->execute([$lieu_id]);
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Return the images as JSON
    echo json_encode([
        'success' => true,
        'images' => $images
    ]);
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching images: ' . $e->getMessage()
    ]);
}
?> 