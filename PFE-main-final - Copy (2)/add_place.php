<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to add a place']);
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $location = $_POST['location'] ?? '';
        $wilaya_id = $_POST['wilaya'] ?? '';
        $category_id = $_POST['category'] ?? '';
        $user_id = $_SESSION['user_id'];

        // Validate required fields
        if (empty($title) || empty($description) || empty($location) || empty($wilaya_id) || empty($category_id)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit();
        }

        // Start transaction
        $pdo->beginTransaction();

        // Insert the place
        $stmt = $pdo->prepare("
            INSERT INTO lieu (title, content, location, wilaya_id, category_id, user_id, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$title, $description, $location, $wilaya_id, $category_id, $user_id]);
        $lieu_id = $pdo->lastInsertId();

        // Handle image uploads
        if (isset($_FILES['images'])) {
            $upload_dir = 'uploads/places/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $filename = uniqid() . '_' . $_FILES['images']['name'][$key];
                    $filepath = $upload_dir . $filename;

                    if (move_uploaded_file($tmp_name, $filepath)) {
                        // Insert image record
                        $stmt = $pdo->prepare("INSERT INTO lieu_images (lieu_id, image_url) VALUES (?, ?)");
                        $stmt->execute([$lieu_id, $filepath]);
                    }
                }
            }
        }

        // Commit transaction
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Place added successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 