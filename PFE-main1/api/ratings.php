<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get average rating for a lieu
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if (!isset($_GET['lieu_id'])) {
            throw new Exception('Lieu ID is required');
        }

        $query = "SELECT 
                    AVG(rating) as average_rating,
                    COUNT(*) as total_ratings
                 FROM lieu_ratings 
                 WHERE lieu_id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET['lieu_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'average_rating' => round($result['average_rating'], 1) ?? 0,
                'total_ratings' => $result['total_ratings'] ?? 0
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// Add or update a rating
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isLoggedIn()) {
            throw new Exception('You must be logged in to rate');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['lieu_id']) || !isset($data['rating'])) {
            throw new Exception('Lieu ID and rating are required');
        }

        if ($data['rating'] < 1 || $data['rating'] > 5) {
            throw new Exception('Rating must be between 1 and 5');
        }

        // Check if user has already rated this lieu
        $checkQuery = "SELECT rating_id FROM lieu_ratings WHERE lieu_id = ? AND user_id = ?";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([$data['lieu_id'], $_SESSION['user_id']]);
        $existingRating = $checkStmt->fetch();

        if ($existingRating) {
            // Update existing rating
            $query = "UPDATE lieu_ratings SET rating = ? WHERE lieu_id = ? AND user_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$data['rating'], $data['lieu_id'], $_SESSION['user_id']]);
        } else {
            // Insert new rating
            $query = "INSERT INTO lieu_ratings (lieu_id, user_id, rating) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$data['lieu_id'], $_SESSION['user_id'], $data['rating']]);
        }

        // Get updated average rating
        $avgQuery = "SELECT AVG(rating) as average_rating, COUNT(*) as total_ratings FROM lieu_ratings WHERE lieu_id = ?";
        $avgStmt = $pdo->prepare($avgQuery);
        $avgStmt->execute([$data['lieu_id']]);
        $result = $avgStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'average_rating' => round($result['average_rating'], 1),
                'total_ratings' => $result['total_ratings']
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?> 