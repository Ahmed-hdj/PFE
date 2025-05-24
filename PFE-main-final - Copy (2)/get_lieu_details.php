<?php
session_start();
require_once 'config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug: Log session data
error_log('Session data: ' . print_r($_SESSION, true));
error_log('GET data: ' . print_r($_GET, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to view details']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get lieu ID from GET parameter
$lieu_id = isset($_GET['lieu_id']) ? intval($_GET['lieu_id']) : 0;

if (!$lieu_id) {
    echo json_encode(['success' => false, 'message' => 'Missing lieu ID']);
    exit();
}

try {
    // Check if the user is an admin
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $is_admin = ($user && strtolower($user['role']) === 'admin');

    // Fetch lieu details and images. Allow admin to view any lieu.
    $sql = "
        SELECT l.*, GROUP_CONCAT(li.image_url) as images
        FROM lieu l
        LEFT JOIN lieu_images li ON l.lieu_id = li.lieu_id
        WHERE l.lieu_id = ?";
        
    // Only add user_id condition if not admin
    if (!$is_admin) {
        $sql .= " AND l.user_id = ?";
    }
    
    $sql .= " GROUP BY l.lieu_id";

    $stmt = $pdo->prepare($sql);
    
    // Bind parameters based on whether user is admin
    if (!$is_admin) {
        $stmt->execute([$lieu_id, $user_id]);
    } else {
        $stmt->execute([$lieu_id]);
    }

    $lieu = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lieu) {
        // Convert images string to array
        $lieu['images'] = $lieu['images'] ? explode(',', $lieu['images']) : [];

        echo json_encode(['success' => true, 'lieu' => $lieu]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lieu not found or you do not have permission to modify it.']);
    }

} catch (PDOException $e) {
    // Log the error and return a generic message
    $error_message = "Database error in get_lieu_details.php: " . $e->getMessage();
    error_log($error_message);
    // Return a more specific error message in development, generic in production
    $response_message = (isset($pdo_debug_mode) && $pdo_debug_mode) ? $error_message : 'Database error occurred.';
    echo json_encode(['success' => false, 'message' => $response_message]);
}
?> 