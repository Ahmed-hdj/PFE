<?php
session_start();
require_once 'config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set proper JSON header
header('Content-Type: application/json');

// Get the search query from POST request
$searchQuery = isset($_POST['search']) ? trim($_POST['search']) : '';

if (empty($searchQuery)) {
    echo json_encode([
        'success' => false,
        'message' => 'Search query is required'
    ]);
    exit;
}

try {
    // Log the search query for debugging
    error_log("Search query: " . $searchQuery);

    // Prepare the search query with LIKE for partial matches
    $stmt = $pdo->prepare("
        SELECT DISTINCT l.*, c.category_name, u.username as author_name,
        COALESCE(GROUP_CONCAT(DISTINCT li.image_url), 'images and videos/default-place.jpg') as images,
        COALESCE(AVG(lr.rating), 0) as average_rating,
        COUNT(DISTINCT lr.rating_id) as total_ratings,
        CASE WHEN ls.lieu_id IS NOT NULL THEN 1 ELSE 0 END as is_saved
        FROM lieu l
        JOIN categories c ON l.category_id = c.category_id
        JOIN users u ON l.user_id = u.user_id
        LEFT JOIN lieu_images li ON l.lieu_id = li.lieu_id
        LEFT JOIN lieu_ratings lr ON l.lieu_id = lr.lieu_id
        LEFT JOIN lieu_saves ls ON l.lieu_id = ls.lieu_id AND ls.user_id = ?
        WHERE (
            LOWER(l.title) LIKE LOWER(?) OR 
            LOWER(l.content) LIKE LOWER(?) OR 
            LOWER(c.category_name) LIKE LOWER(?) OR 
            LOWER(u.username) LIKE LOWER(?)
        )
        AND l.status = 'approved'
        GROUP BY l.lieu_id, c.category_name, u.username, ls.lieu_id
        ORDER BY l.created_at DESC
    ");

    // Add wildcards for partial matching
    $searchPattern = "%{$searchQuery}%";
    $userId = $_SESSION['user_id'] ?? 0;
    
    // Log the parameters for debugging
    error_log("User ID: " . $userId);
    error_log("Search pattern: " . $searchPattern);

    $stmt->execute([
        $userId,
        $searchPattern,
        $searchPattern,
        $searchPattern,
        $searchPattern
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log the number of results for debugging
    error_log("Number of results: " . count($results));

    // Format the results
    $formattedResults = array_map(function($place) {
        $images = !empty($place['images']) ? explode(',', $place['images']) : ['images and videos/default-place.jpg'];
        return [
            'lieu_id' => $place['lieu_id'],
            'title' => $place['title'],
            'content' => $place['content'],
            'category_name' => $place['category_name'],
            'author_name' => $place['author_name'],
            'images' => $images,
            'average_rating' => round((float)$place['average_rating'], 1),
            'total_ratings' => (int)$place['total_ratings'],
            'is_saved' => (bool)$place['is_saved']
        ];
    }, $results);

    echo json_encode([
        'success' => true,
        'data' => $formattedResults,
        'debug' => [
            'query' => $searchQuery,
            'results_count' => count($results)
        ]
    ]);

} catch(PDOException $e) {
    error_log("Search error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error performing search: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
} 