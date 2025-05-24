<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get user information
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Fetch categories
try {
    $categories_query = "SELECT * FROM categories ORDER BY category_name";
    $categories_stmt = $pdo->query($categories_query);
    $categories = $categories_stmt->fetchAll();
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Fetch wilayas
try {
    // First check if the table exists
    $check_table = $pdo->query("SHOW TABLES LIKE 'wilayas'");
    if ($check_table->rowCount() == 0) {
        throw new PDOException("The wilayas table does not exist");
    }

    // Check if the required columns exist
    $check_columns = $pdo->query("SHOW COLUMNS FROM wilayas LIKE 'wilaya_number'");
    if ($check_columns->rowCount() == 0) {
        throw new PDOException("The wilaya_number column does not exist in the wilayas table");
    }

    $wilayas_query = "SELECT * FROM wilayas ORDER BY wilaya_number";
    $wilayas_stmt = $pdo->query($wilayas_query);
    $wilayas = $wilayas_stmt->fetchAll();
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_place'])) {
    try {
        // Start transaction
        $pdo->beginTransaction();

        // Insert into lieu table
        $lieu_query = "INSERT INTO lieu (user_id, category_id, wilaya_id, title, content, location, status) 
                      VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $lieu_stmt = $pdo->prepare($lieu_query);
        $lieu_stmt->execute([
            $_SESSION['user_id'],
            $_POST['category'],
            $_POST['wilaya'],
            $_POST['title'],
            $_POST['description'],
            $_POST['location']
        ]);
        
        $lieu_id = $pdo->lastInsertId();

        // Handle image uploads
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $upload_dir = 'uploads/places/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                $file_name = $_FILES['images']['name'][$key];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Generate unique filename
                $new_file_name = uniqid() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_file_name;

                if (move_uploaded_file($tmp_name, $upload_path)) {
                    // Insert image record
                    $image_query = "INSERT INTO lieu_images (lieu_id, user_id, image_url) VALUES (?, ?, ?)";
                    $image_stmt = $pdo->prepare($image_query);
                    $image_stmt->execute([$lieu_id, $_SESSION['user_id'], $upload_path]);
                }
            }
        }

        // Commit transaction
        $pdo->commit();
        
        // Redirect to prevent form resubmission
        header('Location: index.php?success=1');
        exit();

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $error_message = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap"
        rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - PFE</title>
</head>

<body class="open-sans" style="font-family: 'Open Sans', sans-serif;">
    <!-- ... (rest of the HTML and JS as previously provided) ... -->
    <?php if ($user['role'] === 'Admin'): ?>
        <span class="bg-blue-500 text-white px-2 py-0.5 rounded text-sm">Admin</span>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    fetch('index.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            // Optionally refresh the page or update the UI
                            window.location.reload();
                        } else {
                            alert(data.message || 'Error occurred');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error occurred while submitting the form');
                    });
                });
            }
        });
    </script>
</body>

</html> 