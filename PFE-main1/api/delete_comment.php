<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}

$current_user_id = $_SESSION['user_id'];

try {
    // Get comment ID from POST request
    $data = json_decode(file_get_contents('php://input'), true);
    $comment_id = $data['comment_id'] ?? null;

    if (!$comment_id) {
        echo json_encode(['success' => false, 'message' => 'Comment ID is required.']);
        exit();
    }

    // Fetch comment details to check ownership or admin status
    $comment_stmt = $pdo->prepare("SELECT user_id FROM lieu_comments WHERE comment_id = ?");
    $comment_stmt->execute([$comment_id]);
    $comment = $comment_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$comment) {
        echo json_encode(['success' => false, 'message' => 'Comment not found.']);
        exit();
    }

    // Check if user is admin
    $user_stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $user_stmt->execute([$current_user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    $is_admin = $user && $user['role'] === 'admin';
    $is_author = $comment['user_id'] == $current_user_id;

    // Allow deletion if user is admin or the author of the comment
    if (!$is_admin && !$is_author) {
        echo json_encode(['success' => false, 'message' => 'User not authorized to delete this comment.']);
        exit();
    }

    // Delete the comment
    $delete_stmt = $pdo->prepare("DELETE FROM lieu_comments WHERE comment_id = ?");
    $delete_stmt->execute([$comment_id]);

    if ($delete_stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Comment deleted successfully.']);
    } else {
        // This case should ideally not happen if the comment was found, but as a safeguard
        echo json_encode(['success' => false, 'message' => 'Comment not found or could not be deleted.']);
    }

} catch (PDOException $e) {
    error_log('Error deleting comment: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('Error deleting comment: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 