<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

// Log incoming request
error_log("Received request: " . print_r($_POST, true));
error_log("Received files: " . print_r($_FILES, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Handle different actions
$action = $_POST['action'] ?? '';
error_log("Action: " . $action);

switch ($action) {
    case 'add':
        addLieu();
        break;
    case 'add_images':
        addLieuImages();
        break;
    // Add other cases for modify, delete, etc. later if needed
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        break;
}

function addLieu() {
    global $pdo, $current_user_id;
    
    try {
        // Validate required fields
        $required_fields = ['title', 'content', 'location', 'wilaya', 'category'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                echo json_encode([
                    'success' => false,
                    'message' => "Missing required field: $field"
                ]);
                return;
            }
        }

        // Get user role
        $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$current_user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
            return;
        }

        // Set status based on user role
        $status = ($user['role'] === 'admin') ? 'approved' : 'pending';
        error_log("User role: " . $user['role'] . ", Status: " . $status);

        // Start transaction
        $pdo->beginTransaction();

        // Insert lieu
        $stmt = $pdo->prepare("
            INSERT INTO lieu (user_id, title, content, location, wilaya_id, category_id, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $current_user_id,
            $_POST['title'],
            $_POST['content'],
            $_POST['location'],
            $_POST['wilaya'],
            $_POST['category'],
            $status
        ]);

        $lieu_id = $pdo->lastInsertId();
        error_log("Inserted lieu with ID: " . $lieu_id);

        // Handle image uploads
        if (isset($_FILES['images'])) {
            $upload_dir = '../uploads/lieu_images/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $uploaded_images = [];
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = uniqid() . '_' . $_FILES['images']['name'][$key];
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        // Insert image record
                        $stmt = $pdo->prepare("
                            INSERT INTO lieu_images (lieu_id, user_id, image_url) 
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([
                            $lieu_id,
                            $current_user_id,
                            'uploads/lieu_images/' . $file_name
                        ]);
                        $uploaded_images[] = 'uploads/lieu_images/' . $file_name;
                        error_log("Uploaded image: " . $file_name);
                    } else {
                        error_log("Failed to move uploaded file: " . $file_name);
                    }
                }
            }
        }

        // Commit transaction
        $pdo->commit();
        error_log("Transaction committed successfully");

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => $status === 'pending' ? 
                'Place added successfully! It will be visible after admin approval.' : 
                'Place added successfully!',
            'lieu_id' => $lieu_id,
            'status' => $status
        ]);
        exit();

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error in addLieu: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error adding place: ' . $e->getMessage()
        ]);
        exit();
    }
}

function addLieuImages() {
    global $pdo, $current_user_id;

    try {
        // Validate required fields
        $required_fields = ['lieu_id'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                echo json_encode([
                    'success' => false,
                    'message' => "Missing required field: $field"
                ]);
                return;
            }
        }

        $lieu_id = intval($_POST['lieu_id']);

        // Check if lieu exists and get its author
        $lieu_stmt = $pdo->prepare("SELECT user_id FROM lieu WHERE lieu_id = ?");
        $lieu_stmt->execute([$lieu_id]);
        $lieu = $lieu_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lieu) {
            echo json_encode([
                'success' => false,
                'message' => 'Lieu not found'
            ]);
            return;
        }

        // Check if user is admin
        $user_stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $user_stmt->execute([$current_user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

        $is_admin = $user && $user['role'] === 'admin';
        $is_author = $lieu['user_id'] == $current_user_id;

        // Allow adding images if user is admin or the author of the lieu
        if (!$is_admin && !$is_author) {
            echo json_encode([
                'success' => false,
                'message' => 'User not authorized to add images to this lieu.'
            ]);
            return;
        }

        // Handle image uploads
        if (isset($_FILES['images'])) {
            $upload_dir = '../uploads/lieu_images/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $uploaded_images = [];
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = uniqid() . '_' . $_FILES['images']['name'][$key];
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        // Insert image record
                        $stmt = $pdo->prepare("
                            INSERT INTO lieu_images (lieu_id, user_id, image_url) 
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([
                            $lieu_id,
                            $current_user_id,
                            'uploads/lieu_images/' . $file_name
                        ]);
                        $uploaded_images[] = 'uploads/lieu_images/' . $file_name;
                        error_log("Uploaded image: " . $file_name);
                    } else {
                        error_log("Failed to move uploaded file: " . $file_name);
                    }
                }
            }
            
             if (empty($uploaded_images)) {
                  echo json_encode([
                      'success' => false,
                      'message' => 'No valid images uploaded.'
                  ]);
                  return;
             }
        } else {
             echo json_encode([
                 'success' => false,
                 'message' => 'No image files received.'
             ]);
             return;
        }

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Pictures added successfully!',
            'uploaded_images' => $uploaded_images
        ]);
        exit();

    } catch (Exception $e) {
        error_log("Error in addLieuImages: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error adding pictures: ' . $e->getMessage()
        ]);
        exit();
    }
}
?> 