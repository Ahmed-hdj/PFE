<?php
require_once 'config/database.php';

header('Content-Type: application/json');

try {
    // Check database connection
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Fetch approved lieux with related information
    $query = "SELECT l.*, c.category_name, w.wilaya_name, 
              GROUP_CONCAT(li.image_url) as images,
              u.username as author_name,
              (SELECT AVG(rating) FROM lieu_ratings WHERE lieu_id = l.lieu_id) as average_rating,
              (SELECT COUNT(*) FROM lieu_ratings WHERE lieu_id = l.lieu_id) as total_ratings,
              (SELECT COUNT(*) FROM lieu_saves WHERE lieu_id = l.lieu_id AND user_id = ?) as is_saved
              FROM lieu l
              LEFT JOIN categories c ON l.category_id = c.category_id
              LEFT JOIN wilayas w ON l.wilaya_id = w.wilaya_number
              LEFT JOIN lieu_images li ON l.lieu_id = li.lieu_id
              LEFT JOIN users u ON l.user_id = u.user_id
              WHERE l.status = 'approved'
              GROUP BY l.lieu_id
              ORDER BY l.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id'] ?? 0]);
    $lieux = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log the number of lieux found
    error_log('Number of lieux found: ' . count($lieux));

    // Process each lieu
    foreach ($lieux as &$lieu) {
        // Process images
        $lieu['images'] = $lieu['images'] ? explode(',', $lieu['images']) : [];
        if (empty($lieu['images'])) {
            $lieu['images'] = ['images and videos/default-place.jpg'];
        }
        
        // Log each lieu's data
        error_log('Processing lieu: ' . json_encode($lieu));

        // Process average rating and total ratings
        $lieu['average_rating'] = round($lieu['average_rating'], 1) ?? 0;
        $lieu['total_ratings'] = $lieu['total_ratings'] ?? 0;
    }

    echo json_encode([
        'success' => true,
        'data' => $lieux
    ]);

} catch (Exception $e) {
    error_log('Error in fetch_lieux.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching lieux: ' . $e->getMessage()
    ]);
}
?> 