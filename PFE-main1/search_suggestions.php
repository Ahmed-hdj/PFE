<?php
session_start();
require_once 'config/database.php';

// Get the search query from GET request
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($searchQuery)) {
    echo json_encode([]);
    exit;
}

try {
    // Search in lieu titles
    $stmt = $pdo->prepare("
        SELECT DISTINCT l.title as text, 'place' as type, l.lieu_id as id
        FROM lieu l
        WHERE l.title LIKE ? AND l.status = 'approved'
        LIMIT 5
    ");
    $stmt->execute(["%{$searchQuery}%"]);
    $placeResults = $stmt->fetchAll();

    // Search in wilaya names
    $stmt = $pdo->prepare("
        SELECT DISTINCT wilaya_name as text, 'wilaya' as type, wilaya_number as id
        FROM wilayas
        WHERE wilaya_name LIKE ?
        LIMIT 5
    ");
    $stmt->execute(["%{$searchQuery}%"]);
    $wilayaResults = $stmt->fetchAll();

    // Combine and sort results
    $results = array_merge($placeResults, $wilayaResults);
    
    // Sort by text length to show shorter matches first
    usort($results, function($a, $b) {
        return strlen($a['text']) - strlen($b['text']);
    });

    // Limit total results
    $results = array_slice($results, 0, 10);

    echo json_encode($results);

} catch(PDOException $e) {
    echo json_encode([]);
} 