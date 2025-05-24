<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to rate a place']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['lieu_id']) || !isset($data['rating'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$lieu_id = intval($data['lieu_id']);
$user_id = $_SESSION['user_id'];
$rating = intval($data['rating']);

// Validate rating value
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Check if user has already rated this place
    $check_stmt = $pdo->prepare("SELECT rating_id FROM lieu_ratings WHERE lieu_id = ? AND user_id = ?");
    $check_stmt->execute([$lieu_id, $user_id]);
    $existing_rating = $check_stmt->fetch();

    if ($existing_rating) {
        // Update existing rating
        $update_stmt = $pdo->prepare("UPDATE lieu_ratings SET rating = ? WHERE rating_id = ?");
        $update_stmt->execute([$rating, $existing_rating['rating_id']]);
    } else {
        // Insert new rating
        $insert_stmt = $pdo->prepare("INSERT INTO lieu_ratings (lieu_id, user_id, rating) VALUES (?, ?, ?)");
        $insert_stmt->execute([$lieu_id, $user_id, $rating]);
    }

    // Calculate new average rating
    $avg_stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM lieu_ratings WHERE lieu_id = ?");
    $avg_stmt->execute([$lieu_id]);
    $rating_stats = $avg_stmt->fetch();
    $average_rating = round($rating_stats['avg_rating'], 1);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Rating submitted successfully',
        'average_rating' => $average_rating,
        'total_ratings' => $rating_stats['total_ratings']
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 