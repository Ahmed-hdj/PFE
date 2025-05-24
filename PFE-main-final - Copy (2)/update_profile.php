<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to update your profile'
    ]);
    exit();
}

// Check if this is a profile update request
if (!isset($_POST['update_profile'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    $user_id = $_SESSION['user_id'];
    $updates = [];
    $params = [];

    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Validate file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($file_ext, $allowed_types)) {
            throw new Exception('Invalid file type. Only JPG, JPEG, PNG, GIF and WebP files are allowed.');
        }

        // Create upload directory if it doesn't exist
        $upload_dir = 'uploads/profile_pictures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generate unique filename
        $new_filename = uniqid() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Delete old profile picture if exists
            $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $old_picture = $stmt->fetchColumn();

            if ($old_picture && file_exists($upload_dir . $old_picture)) {
                unlink($upload_dir . $old_picture);
            }

            $updates[] = "profile_picture = ?";
            $params[] = $new_filename;
        }
    }

    // Handle other profile fields
    if (isset($_POST['username']) && !empty($_POST['username'])) {
        $updates[] = "username = ?";
        $params[] = $_POST['username'];
    }

    if (isset($_POST['email']) && !empty($_POST['email'])) {
        // Validate email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$_POST['email'], $user_id]);
        if ($stmt->fetch()) {
            throw new Exception('Email is already taken');
        }

        $updates[] = "email = ?";
        $params[] = $_POST['email'];
    }

    // If there are updates to make
    if (!empty($updates)) {
        $params[] = $user_id; // Add user_id for WHERE clause

        $query = "UPDATE users SET " . implode(", ", $updates) . " WHERE user_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No changes to update'
        ]);
    }

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>