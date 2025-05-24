<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get comments for a lieu
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if (!isset($_GET['lieu_id'])) {
            throw new Exception('Lieu ID is required');
        }

        // Updated query to ensure we get the full profile picture path and user_id
        $query = "SELECT c.*, u.username as author_name, u.user_id as author_id, 
                 CASE 
                    WHEN u.profile_picture IS NOT NULL AND u.profile_picture != '' 
                    THEN u.profile_picture
                    ELSE NULL 
                 END as profile_picture
                 FROM lieu_comments c 
                 JOIN users u ON c.user_id = u.user_id 
                 WHERE c.lieu_id = ? 
                 ORDER BY c.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET['lieu_id']]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Debug output
        error_log('Comments data: ' . print_r($comments, true));

        echo json_encode([
            'success' => true,
            'data' => $comments
        ]);
    } catch (Exception $e) {
        error_log('Error in comments.php: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// Add a new comment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isLoggedIn()) {
            throw new Exception('You must be logged in to comment');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['lieu_id']) || !isset($data['content'])) {
            throw new Exception('Lieu ID and comment content are required');
        }

        if (empty(trim($data['content']))) {
            throw new Exception('Comment content cannot be empty');
        }

        $query = "INSERT INTO lieu_comments (lieu_id, user_id, content) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $data['lieu_id'],
            $_SESSION['user_id'],
            $data['content']
        ]);

        // Fetch the newly created comment with user information, including user_id
        $query = "SELECT c.*, u.username as author_name, u.user_id as author_id, 
                 CASE 
                    WHEN u.profile_picture IS NOT NULL AND u.profile_picture != '' 
                    THEN u.profile_picture
                    ELSE NULL 
                 END as profile_picture
                 FROM lieu_comments c 
                 JOIN users u ON c.user_id = u.user_id 
                 WHERE c.comment_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pdo->lastInsertId()]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        // Debug output
        error_log('New comment data: ' . print_r($comment, true));

        echo json_encode([
            'success' => true,
            'data' => $comment
        ]);
    } catch (Exception $e) {
        error_log('Error in comments.php: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?> 