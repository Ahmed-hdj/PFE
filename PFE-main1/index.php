<?php
session_start();
require_once 'config/database.php';

// Get user information if logged in
$user = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Error fetching user data: ' . $e->getMessage());
    }
}

// Fetch categories
try {
    $categories_query = "SELECT * FROM categories ORDER BY category_name";
    $categories_stmt = $pdo->query($categories_query);
    $categories = $categories_stmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Fetch wilayas
try {
    $wilayas_query = "SELECT * FROM wilayas ORDER BY wilaya_number";
    $wilayas_stmt = $pdo->query($wilayas_query);
    $wilayas = $wilayas_stmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Handle form submission for adding places
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_place'])) {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php');
            exit();
        }

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

            // Return success response with lieu_id
            echo json_encode([
                'success' => true,
                'message' => 'Place added successfully',
                'lieu_id' => $lieu_id
            ]);
            exit();

        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
            exit();
        }
    } else if (isset($_POST['lieu_id'])) {
        // Debug: Log the received POST data
        error_log('Received POST data: ' . print_r($_POST, true));

        // Handle lieu modification
        try {
            // Validate required fields
            $required_fields = ['title', 'content', 'location', 'category_id', 'wilaya_id'];
            foreach ($required_fields as $field) {
                if (!isset($_POST[$field]) || empty($_POST[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }

            // Check if user is admin
            $user_stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
            $user_stmt->execute([$_SESSION['user_id']]);
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            $is_admin = $user && strtolower($user['role']) === 'admin';

            // Start transaction
            $pdo->beginTransaction();

            // Prepare the update query based on user role
            if ($is_admin) {
                $lieu_query = "UPDATE lieu SET 
                              title = ?, 
                              content = ?, 
                              location = ?, 
                              category_id = ?,
                              wilaya_id = ?
                              WHERE lieu_id = ?";
                $params = [
                    $_POST['title'],
                    $_POST['content'],
                    $_POST['location'],
                    $_POST['category_id'],
                    $_POST['wilaya_id'],
                    $_POST['lieu_id']
                ];
            } else {
                $lieu_query = "UPDATE lieu SET 
                              title = ?, 
                              content = ?, 
                              location = ?, 
                              category_id = ?,
                              wilaya_id = ?
                              WHERE lieu_id = ? AND user_id = ?";
                $params = [
                    $_POST['title'],
                    $_POST['content'],
                    $_POST['location'],
                    $_POST['category_id'],
                    $_POST['wilaya_id'],
                    $_POST['lieu_id'],
                    $_SESSION['user_id']
                ];
            }

            error_log('SQL Query: ' . $lieu_query);
            error_log('Parameters: ' . print_r($params, true));

            $lieu_stmt = $pdo->prepare($lieu_query);
            $result = $lieu_stmt->execute($params);

            if (!$result) {
                error_log('Database error: ' . print_r($lieu_stmt->errorInfo(), true));
                throw new Exception("Failed to update lieu information");
            }

            // Debug: Log the number of affected rows
            $affected_rows = $lieu_stmt->rowCount();
            error_log('Affected rows: ' . $affected_rows);

            if ($affected_rows === 0) {
                throw new Exception("No changes were made. Please check if you have permission to modify this lieu.");
            }

            // Handle new image uploads
            if (isset($_FILES['new_images']) && !empty($_FILES['new_images']['name'][0])) {
                $upload_dir = 'uploads/places/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                foreach ($_FILES['new_images']['tmp_name'] as $key => $tmp_name) {
                    $file_name = $_FILES['new_images']['name'][$key];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    // Generate unique filename
                    $new_file_name = uniqid() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_file_name;

                    if (move_uploaded_file($tmp_name, $upload_path)) {
                        // Insert image record
                        $image_query = "INSERT INTO lieu_images (lieu_id, user_id, image_url) VALUES (?, ?, ?)";
                        $image_stmt = $pdo->prepare($image_query);
                        $image_stmt->execute([$_POST['lieu_id'], $_SESSION['user_id'], $upload_path]);
                    }
                }
            }

            // Handle image deletions if any
            if (isset($_POST['deleted_images']) && is_array($_POST['deleted_images'])) {
                foreach ($_POST['deleted_images'] as $image_url) {
                    // Delete from database
                    $delete_query = "DELETE FROM lieu_images WHERE lieu_id = ? AND image_url = ? AND user_id = ?";
                    $delete_stmt = $pdo->prepare($delete_query);
                    $delete_stmt->execute([$_POST['lieu_id'], $image_url, $_SESSION['user_id']]);

                    // Delete file from server
                    if (file_exists($image_url)) {
                        unlink($image_url);
                    }
                }
            }

            // Commit transaction
            $pdo->commit();

            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Lieu modified successfully'
            ]);
            exit();

        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Error modifying lieu: ' . $e->getMessage()
            ]);
            exit();
        }
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
    <title>PFE</title>

    <style>
        @font-face {
            font-family: 'MyHandFont';
            src: url('font/AutumnFlowers-9YVZK.otf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
    </style>

</head>

<body class="open-sans" style="font-family: 'Open Sans', sans-serif;">
    <div class="pt-24">
        <div class="fixed top-0 left-0 right-0 flex items-center justify-between p-4 bg-white shadow-md z-50">
            <div class="container mx-auto px-4 max-w-[1152px] flex items-center justify-between">
                <a href="index.php">
                    <img src="images and videos/ChatGPT Image 29 avr. 2025, 00_41_42.png" alt="Logo"
                        class="h-16 w-auto">
                </a>
                <button onclick="checkAuthAndShowAddPlaceForm()"
                    class="bg-green-500 text-white px-6 py-2 rounded-full hover:bg-green-600 transition-colors whitespace-nowrap cursor-pointer flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    Ajouter un lieu
                </button>
                <div class="relative w-1/2">
                    <div class="relative">
                        <i
                            class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <form id="searchForm" class="w-full">
                            <input type="search" name="search" placeholder="Search for a Place or Wilaya"
                                class="pl-10 pr-14 py-2 border-[1px] border-[#a1a1a1] rounded-full w-full focus:outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 [&::-webkit-search-cancel-button]:cursor-pointer">
                            <button type="submit"
                                class="absolute right-[2px] top-[2px] h-[92%] text-gray-600 hover:text-gray-800 bg-gray-100 hover:bg-gray-200 rounded-r-full px-4 transition-colors flex items-center cursor-pointer">
                                <i class="fa-solid fa-magnifying-glass text-xl"></i>
                            </button>
                        </form>
                        <div id="searchSuggestions"
                            class="absolute left-0 right-0 mt-1 bg-white rounded-lg shadow-lg hidden z-50 max-h-60 overflow-y-auto">
                        </div>
                    </div>
                </div>
                <?php if ($user): ?>
                    <div class="flex items-center gap-4">
                        <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                            <span class="bg-blue-500 text-white px-2 py-0.5 rounded text-sm">Admin</span>
                        <?php endif; ?>
                        <div class="relative">
                            <button onclick="toggleProfileMenu()"
                                class="flex items-center gap-2 hover:bg-gray-100 p-2 rounded-full transition-colors cursor-pointer">
                                <?php if ($user['profile_picture']): ?>
                                    <img src="uploads/profile_pictures/<?php echo htmlspecialchars($user['profile_picture']); ?>"
                                        alt="Profile" class="w-10 h-10 rounded-full object-cover">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                                        <span class="text-gray-600 font-medium text-lg">
                                            <?php
                                            $initials = '';
                                            $name_parts = explode(' ', $user['username']);
                                            foreach ($name_parts as $part) {
                                                $initials .= strtoupper(substr($part, 0, 1));
                                            }
                                            echo substr($initials, 0, 2);
                                            ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <span class="font-medium"><?php echo htmlspecialchars($user['username']); ?></span>
                                <i class="fas fa-chevron-down text-gray-500"></i>
                            </button>
                            <div id="profileMenu"
                                class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 hidden">
                                <a href="profile.php" class="block px-4 py-2 hover:bg-gray-100 w-full text-left">
                                    <i class="fas fa-user mr-2"></i> Profile
                                </a>
                                <a href="demandes.php" class="block px-4 py-2 hover:bg-gray-100">
                                    <i class="fas fa-list mr-2"></i> Demands
                                </a>
                                <hr class="my-2">
                                <a href="logout.php"
                                    class="block w-full text-left px-4 py-2 text-red-600 hover:bg-gray-100">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <button onclick="showLoginModal()"
                        class="bg-gray-100 text-gray-700 px-6 py-2 rounded-full hover:bg-gray-200 transition-colors whitespace-nowrap cursor-pointer">
                        Se Connecter
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="relative h-[75vh] w-full before:absolute before:inset-0 before:bg-black/58 before:z-10">
        <div class="absolute inset-0 flex flex-col items-center justify-center z-20">
            <div class="text-7xl font-bold font-['MyHandFont'] text-white">Welcome to</div>
            <div class="text-[150px] tracking-[1px] font-bold font-['MyHandFont'] text-white"><span
                    class="text-green-500">Al</span><span class="text-red-500">ge</span>ria</div>
        </div>
        <video src="images and videos/Algeria from Above 4K UHD - A Cinematic Drone Journey (1).mp4" loop muted autoplay
            class="w-full h-full object-cover" playbackRate="1.5"></video>
    </div>

    <div class="container mx-auto px-4 py-16 max-w-[1152px]"
        style="padding-top:0;margin-top:-43px;position:relative;z-index:10;">


        <div class="grid gap-8">
            <!-- First Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Cultural and Heritage Card -->
                <a href="dynamic-page%20categories.php?category=1"
                    class="bg-white rounded-lg shadow-lg p-8 text-center hover:shadow-xl transition-all duration-300 cursor-pointer group border-[1px] border-gray-200 max-w[224px]">
                    <div
                        class="w-20 h-20 mx-auto mb-4 bg-[#FFE5D9] rounded-full flex items-center justify-center group-hover:bg-green-500 transition-all duration-300">
                        <i
                            class="fas fa-landmark text-3xl text-[#FF7D5C] group-hover:text-white transition-colors duration-300"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Cultural and Heritage</h3>
                </a>

                <!-- Religious Card -->
                <a href="dynamic-page%20categories.php?category=2"
                    class="bg-white rounded-lg shadow-lg p-8 text-center hover:shadow-xl transition-all duration-300 cursor-pointer group border-[1px] border-gray-200 max-w[224px]">
                    <div
                        class="w-20 h-20 mx-auto mb-4 bg-[#E6F7FF] rounded-full flex items-center justify-center group-hover:bg-green-500 transition-all duration-300">
                        <i
                            class="fas fa-mosque text-3xl text-[#1890FF] group-hover:text-white transition-colors duration-300"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Religious</h3>
                </a>

                <!-- Beaches Card -->
                <a href="dynamic-page%20categories.php?category=3"
                    class="bg-white rounded-lg shadow-lg p-8 text-center hover:shadow-xl transition-all duration-300 cursor-pointer group border-[1px] border-gray-200 max-w[224px]">
                    <div
                        class="w-20 h-20 mx-auto mb-4 bg-[#FFF7E6] rounded-full flex items-center justify-center group-hover:bg-green-500 transition-all duration-300">
                        <i
                            class="fas fa-umbrella-beach text-3xl text-[#FFA940] group-hover:text-white transition-colors duration-300"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Beaches</h3>
                </a>

                <!-- Desert Card -->
                <a href="dynamic-page%20categories.php?category=4"
                    class="bg-white rounded-lg shadow-lg p-8 text-center hover:shadow-xl transition-all duration-300 cursor-pointer group border-[1px] border-gray-200 max-w[224px]">
                    <div
                        class="w-20 h-20 mx-auto mb-4 bg-[#FFF1F0] rounded-full flex items-center justify-center group-hover:bg-green-500 transition-all duration-300">
                        <i
                            class="fas fa-sun text-3xl text-[#FF4D4F] group-hover:text-white transition-colors duration-300"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Desert</h3>
                </a>
            </div>

            <!-- Second Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-8">
                <!-- Museums Card -->
                <a href="dynamic-page%20categories.php?category=5"
                    class="bg-white rounded-lg shadow-lg p-8 text-center hover:shadow-xl transition-all duration-300 cursor-pointer group border-[1px] border-gray-200 max-w[224px]">
                    <div
                        class="w-20 h-20 mx-auto mb-4 bg-[#F0F5FF] rounded-full flex items-center justify-center group-hover:bg-green-500 transition-all duration-300">
                        <i
                            class="fas fa-museum text-3xl text-[#2F54EB] group-hover:text-white transition-colors duration-300"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Museums</h3>
                </a>

                <!-- Shopping Card -->
                <a href="dynamic-page%20categories.php?category=6"
                    class="bg-white rounded-lg shadow-lg p-8 text-center hover:shadow-xl transition-all duration-300 cursor-pointer group border-[1px] border-gray-200 max-w[224px]">
                    <div
                        class="w-20 h-20 mx-auto mb-4 bg-[#FFF0F6] rounded-full flex items-center justify-center group-hover:bg-green-500 transition-all duration-300">
                        <i
                            class="fas fa-shopping-bag text-3xl text-[#EB2F96] group-hover:text-white transition-colors duration-300"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Shopping</h3>
                </a>

                <!-- Nature Card -->
                <a href="dynamic-page%20categories.php?category=7"
                    class="bg-white rounded-lg shadow-lg p-8 text-center hover:shadow-xl transition-all duration-300 cursor-pointer group border-[1px] border-gray-200 max-w[224px]">
                    <div
                        class="w-20 h-20 mx-auto mb-4 bg-[#F6FFED] rounded-full flex items-center justify-center group-hover:bg-green-500 transition-all duration-300">
                        <i
                            class="fas fa-tree text-3xl text-[#52C41A] group-hover:text-white transition-colors duration-300"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Nature</h3>
                </a>

                <!-- Sport Card -->
                <a href="dynamic-page%20categories.php?category=8"
                    class="bg-white rounded-lg shadow-lg p-8 text-center hover:shadow-xl transition-all duration-300 cursor-pointer group border-[1px] border-gray-200 max-w[224px]">
                    <div
                        class="w-20 h-20 mx-auto mb-4 bg-[#E6FFFB] rounded-full flex items-center justify-center group-hover:bg-green-500 transition-all duration-300">
                        <i
                            class="fas fa-running text-3xl text-[#13C2C2] group-hover:text-white transition-colors duration-300"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Sport</h3>
                </a>

                <!-- Amusement Card -->
                <a href="dynamic-page%20categories.php?category=9"
                    class="bg-white rounded-lg shadow-lg p-8 text-center hover:shadow-xl transition-all duration-300 cursor-pointer group border-[1px] border-gray-200 max-w[224px]">
                    <div
                        class="w-20 h-20 mx-auto mb-4 bg-[#F9F0FF] rounded-full flex items-center justify-center group-hover:bg-green-500 transition-all duration-300">
                        <i
                            class="fas fa-ticket-alt text-3xl text-[#722ED1] group-hover:text-white transition-colors duration-300"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800">Amusement</h3>
                </a>
            </div>
        </div>
    </div>

    <!-- Popular Cities Section -->
    <div class="container mx-auto px-4 py-16 max-w-[1152px]">
        <hr class="border-t border-gray-200 mb-8">
        <h2 class="text-2xl font-bold mb-8">Villes populaires de Algérie</h2>
        <div class="relative">
            <!-- Navigation Arrows -->
            <button id="prevCity"
                class="absolute left-[-40px] top-1/2 -translate-y-1/2 bg-white p-4 rounded-full shadow-lg hover:bg-gray-200 transition-colors z-10 cursor-pointer active:scale-95">
                <i class="fas fa-chevron-left text-gray-700 text-xl"></i>
            </button>
            <button id="nextCity"
                class="absolute right-[-40px] top-1/2 -translate-y-1/2 bg-white p-4 rounded-full shadow-lg hover:bg-gray-200 transition-colors z-10 cursor-pointer active:scale-95">
                <i class="fas fa-chevron-right text-gray-700 text-xl"></i>
            </button>

            <div class="overflow-hidden">
                <div id="citiesContainer" class="flex transition-transform duration-300">
                    <!-- Alger Card -->
                    <div class="min-w-full sm:min-w-[50%] lg:min-w-[33.333%] xl:min-w-[25%] px-4">
                        <a href="dynamic-page.php?wilaya=16" class="block">
                            <div class="relative group cursor-pointer">
                                <div class="relative overflow-hidden rounded-lg">
                                    <img src="https://th.bing.com/th/id/R.d7d1306a7fa6ab5e5dbb9636f708e7fb?rik=FtrjcYqgIx%2fFvA&pid=ImgRaw&r=0"
                                        alt="Alger"
                                        class="w-full h-64 object-cover transition-opacity duration-300 group-hover:opacity-80">
                                </div>
                                <div class="mt-4">
                                    <h3 class="text-xl font-semibold">Alger</h3>
                                    <p class="text-gray-600">Algiers Province, Algérie</p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Oran Card -->
                    <div class="min-w-full sm:min-w-[50%] lg:min-w-[33.333%] xl:min-w-[25%] px-4">
                        <a href="dynamic-page.php?wilaya=31" class="block">
                            <div class="relative group cursor-pointer">
                                <div class="relative overflow-hidden rounded-lg">
                                    <img src="https://www.algerie-eco.com/wp-content/uploads/2020/04/oran.jpg"
                                        alt="Oran"
                                        class="w-full h-64 object-cover transition-opacity duration-300 group-hover:opacity-80">
                                </div>
                                <div class="mt-4">
                                    <h3 class="text-xl font-semibold">Oran</h3>
                                    <p class="text-gray-600">Oran Province, Algérie</p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Constantine Card -->
                    <div class="min-w-full sm:min-w-[50%] lg:min-w-[33.333%] xl:min-w-[25%] px-4">
                        <a href="dynamic-page.php?wilaya=25" class="block">
                            <div class="relative group cursor-pointer">
                                <div class="relative overflow-hidden rounded-lg">
                                    <img src="https://th.bing.com/th/id/R.44fb16e17e9cca7658945e24d59f44e1?rik=nJCVvSpPAsH9UA&pid=ImgRaw&r=0"
                                        alt="Constantine"
                                        class="w-full h-64 object-cover transition-opacity duration-300 group-hover:opacity-80">
                                </div>
                                <div class="mt-4">
                                    <h3 class="text-xl font-semibold">Constantine</h3>
                                    <p class="text-gray-600">Constantine Province, Algérie</p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Bejaia Card -->
                    <div class="min-w-full sm:min-w-[50%] lg:min-w-[33.333%] xl:min-w-[25%] px-4">
                        <a href="dynamic-page.php?wilaya=6" class="block">
                            <div class="relative group cursor-pointer">
                                <div class="relative overflow-hidden rounded-lg">
                                    <img src="https://1.bp.blogspot.com/-RNAnnRPsxS0/WUZe2ZYN57I/AAAAAAAAANY/IknXmb36R9U9UUwt6OJisHyTNHUpgbqMwCEwYBhgL/s1600/Bejaia-1.jpg"
                                        alt="Bejaia"
                                        class="w-full h-64 object-cover transition-opacity duration-300 group-hover:opacity-80">
                                </div>
                                <div class="mt-4">
                                    <h3 class="text-xl font-semibold">Bejaia</h3>
                                    <p class="text-gray-600">Bejaia Province, Algérie</p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Annaba Card -->
                    <div class="min-w-full sm:min-w-[50%] lg:min-w-[33.333%] xl:min-w-[25%] px-4">
                        <a href="dynamic-page.php?wilaya=23" class="block">
                            <div class="relative group cursor-pointer">
                                <div class="relative overflow-hidden rounded-lg">
                                    <img src="https://www.ilmondodiathena.com/wp-content/uploads/2019/03/cattedrale-marsiglia-1024x632.jpg"
                                        alt="Annaba"
                                        class="w-full h-64 object-cover transition-opacity duration-300 group-hover:opacity-80">
                                </div>
                                <div class="mt-4">
                                    <h3 class="text-xl font-semibold">Annaba</h3>
                                    <p class="text-gray-600">Annaba Province, Algérie</p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Tamanrasset Card -->
                    <div class="min-w-full sm:min-w-[50%] lg:min-w-[33.333%] xl:min-w-[25%] px-4">
                        <a href="dynamic-page.php?wilaya=11" class="block">
                            <div class="relative group cursor-pointer">
                                <div class="relative overflow-hidden rounded-lg">
                                    <img src="https://expatstraveltogether.com/wp-content/uploads/2023/09/Algeria_Sahara_Tamanrasset_People_Riding_Camel_on_the_Desert-scaled.jpg"
                                        alt="Tamanrasset"
                                        class="w-full h-64 object-cover transition-opacity duration-300 group-hover:opacity-80">
                                </div>
                                <div class="mt-4">
                                    <h3 class="text-xl font-semibold">Tamanrasset</h3>
                                    <p class="text-gray-600">Tamanrasset Province, Algérie</p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Tlemcen Card -->
                    <div class="min-w-full sm:min-w-[50%] lg:min-w-[33.333%] xl:min-w-[25%] px-4">
                        <a href="dynamic-page.php?wilaya=13" class="block">
                            <div class="relative group cursor-pointer">
                                <div class="relative overflow-hidden rounded-lg">
                                    <img src="https://tse1.mm.bing.net/th/id/OIP.Uq_I3gCjCp7BbLYCs2lezQHaE8?cb=iwc1&rs=1&pid=ImgDetMain"
                                        alt="Tlemcen"
                                        class="w-full h-64 object-cover transition-opacity duration-300 group-hover:opacity-80">
                                </div>
                                <div class="mt-4">
                                    <h3 class="text-xl font-semibold">Tlemcen</h3>
                                    <p class="text-gray-600">Tlemcen Province, Algérie</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <hr class="border-t border-gray-200 mt-8">
    </div>
    <!-- End Popular Cities Section -->

    <!-- Best Attractions Section -->
    <div class="container mx-auto px-4 pb-16 max-w-[1152px]">

        <h2 class="text-2xl font-bold mb-8">Meilleures attractions à Algérie</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="attractionsContainer">
            <!-- Cards will be dynamically inserted here -->
        </div>
        <hr class="border-t border-gray-200 mt-8">
    </div>
    <!-- End Best Attractions Section -->

    <!-- Login Modal -->
    <div id="loginModal"
        class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200 ">
        <div class="bg-white p-8 rounded-lg w-96 shadow-xl relative border-[6px] border-[#327532]">
            <img src="images and videos/ChatGPT Image 29 avr. 2025, 00_41_42.png" alt="" class="w-[90px] mx-auto mb-7">
            <div class="flex justify-center items-center mb-4 ">
                <h2
                    class="text-2xl font-bold text-[#19a719] before:content-[''] before:w-[30%] before:h-[2px] before:bg-[#19a719] before:absolute  before:left-0 before:top-[166px] after:content-[''] after:w-[30%] after:h-[2px] after:bg-[#19a719] after:absolute  after:right-0 after:top-[166px] ">
                    Connexion</h2>
                <button onclick="hideLoginModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                    <i class="fas fa-times absolute top-[16px] right-[18px] text-[18px]"></i>
                </button>
            </div>
            <form id="loginForm" class="space-y-4">
                <div>
                    <label class="block text-gray-700 mb-2 font-semibold tracking-[1px] ">Email :</label>
                    <input type="email" name="email"
                        class="w-full px-3 py-2 border rounded-full outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 border-[#a1a1a1] "
                        placeholder="Entrez votre email" required>
                </div>
                <div>
                    <label class="block text-gray-700 mb-2 font-semibold tracking-[1px] ">Mot de passe :</label>
                    <input type="password" name="password"
                        class="w-full px-3 py-2 border rounded-full outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 border-[#a1a1a1] mb-[14px]"
                        placeholder="Entrez votre mot de passe" required>
                </div>
                <button type="submit"
                    class="w-full bg-[#2fb52f] text-white py-2 rounded-full hover:opacity-80 transition-colors cursor-pointer font-bold">
                    Se Connecter
                </button>
                <div class="text-center mt-4">
                    <a href="#" onclick="showSignupForm()"
                        class="text-[#35b935] underline hover:opacity-80 transition-colors cursor-pointer font-bold tracking-[1px] text-[17px]">S'inscrire</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Signup Modal -->
    <div id="signupModal"
        class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
        <div class="bg-white p-8 rounded-lg w-96 shadow-xl border-[6px] border-[#327532]">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold">Inscription</h2>
                <button onclick="hideSignupModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="signupForm" class="space-y-4">
                <div class="flex flex-col items-center mb-4">
                    <div class="relative w-32 h-32 mb-4">
                        <img id="profilePreview"
                            src="https://as2.ftcdn.net/v2/jpg/05/49/98/39/1000_F_549983970_bRCkYfk0P6PP5fKbMhZMIb07mCJ6esXL.jpg"
                            alt="Profile Preview"
                            class="w-full h-full rounded-full object-cover border-[4px] grayscale border-dashed border-[#393434]">
                        <label for="profilePicture"
                            class="absolute inset-0 flex items-center justify-center bg-black/30 rounded-full cursor-pointer hover:bg-black/40 transition-colors">
                            <i class="fas fa-plus text-white text-2xl"></i>
                        </label>
                        <input type="file" id="profilePicture" accept="image/*" class="hidden"
                            onchange="previewProfilePicture(this)">
                    </div>
                    <p class="text-sm text-gray-500">Click to add a profile picture</p>
                </div>
                <div>
                    <label class="block text-gray-700 mb-2 font-semibold tracking-[1px]">Nom complet :</label>
                    <input type="text"
                        class="w-full px-3 py-2 border rounded-full outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 border-[#a1a1a1]"
                        placeholder="Entrez votre nom complet">
                </div>
                <div>
                    <label class="block text-gray-700 mb-2 font-semibold tracking-[1px]">Email :</label>
                    <input type="email"
                        class="w-full px-3 py-2 border rounded-full outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 border-[#a1a1a1]"
                        placeholder="Entrez votre email">
                </div>
                <div>
                    <label class="block text-gray-700 mb-2 font-semibold tracking-[1px]">Mot de passe :</label>
                    <input type="password"
                        class="w-full px-3 py-2 border rounded-full outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 border-[#a1a1a1]"
                        placeholder="Créez votre mot de passe">
                </div>
                <div>
                    <label class="block text-gray-700 mb-2 font-semibold tracking-[1px]">Confirmer le mot de passe
                        :</label>
                    <input type="password"
                        class="w-full px-3 py-2 border rounded-full outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 border-[#a1a1a1] mb-[14px]"
                        placeholder="Confirmez votre mot de passe">
                </div>
                <button type="submit"
                    class="bg-[#2fb52f] text-white py-2 rounded-full hover:opacity-80 transition-colors cursor-pointer font-bold w-full">
                    S'inscrire
                </button>
                <div class="text-center mt-4">
                    <a href="#" onclick="showLoginForm()"
                        class="text-[#35b935] underline hover:opacity-80 transition-colors cursor-pointer font-bold tracking-[1px] text-[17px]">Déjà
                        un
                        compte ? Se connecter</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Auth Warning Modal -->
    <div id="authWarningModal"
        class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
        <div class="bg-white p-8 rounded-lg w-[400px] shadow-xl ">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold">Authentication Required</h2>
                <button onclick="hideAuthWarningModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p class="text-gray-600 mb-6">Please log in to add a place.</p>
            <div class="flex justify-end gap-4">
                <button onclick="hideAuthWarningModal()"
                    class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors cursor-pointer">
                    Cancel
                </button>
                <button onclick="showLoginForm()"
                    class="px-4 py-2 bg-blue-500 text-white rounded-full hover:bg-blue-600 transition-colors cursor-pointer">
                    Log In
                </button>
            </div>
        </div>
    </div>

    <!-- Add Place Modal -->
    <div id="addPlaceModal"
        class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
        <div class="bg-white p-8 rounded-lg w-[600px] shadow-xl border-[6px] border-[#327532]">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold">Ajouter un lieu</h2>
                <button onclick="hideAddPlaceModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="addPlaceForm" class="space-y-4" method="POST" enctype="multipart/form-data">
                <div>

                    <div class="relative h-[192px]">
                        <input type="file" name="images[]" id="imageInput"
                            class="w-full h-full px-3 py-2 border-4 border-dashed border-[#7d7d7d] rounded-none bg-[#dcdcdc] cursor-pointer opacity-0 absolute inset-0 z-10"
                            multiple accept="image/*" required>
                        <label for="imageInput"
                            class="w-full h-full border-4 border-dashed border-[#7d7d7d] rounded-none bg-[#dcdcdc] flex flex-col items-center justify-center cursor-pointer absolute inset-0">
                            <i class="fas fa-images text-4xl text-[#7d7d7d] mb-2"></i>
                            <i class="fas fa-plus-circle text-2xl text-[#7d7d7d]"></i>
                            <span class="text-[#7d7d7d] mt-2">Click to upload images</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">Nom du lieu :</label>
                    <input type="text" name="title"
                        class="w-full px-3 py-2 border rounded-full outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 border-[#a1a1a1]"
                        required placeholder="Entrez le nom du lieu">
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">Description :</label>
                    <textarea name="description"
                        class="w-full px-3 py-2 border  outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 border-[#a1a1a1]"
                        rows="3" placeholder="Entrez la description du lieu" required></textarea>
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">Location :</label>
                    <textarea name="location"
                        class="w-full px-3 py-2 border  outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 border-[#a1a1a1]"
                        rows="3" placeholder="Entrez la location du lieu" required></textarea>
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">Wilaya :</label>
                    <select name="wilaya" class="w-full px-3 py-2 border rounded-full outline-none border-[#a1a1a1]"
                        required>
                        <option value="">Sélectionnez une wilaya</option>
                        <?php
                        try {
                            $wilayas_query = "SELECT * FROM wilayas ORDER BY wilaya_number";
                            $wilayas_stmt = $pdo->query($wilayas_query);
                            $wilayas = $wilayas_stmt->fetchAll();
                            foreach ($wilayas as $wilaya) {
                                echo '<option value="' . htmlspecialchars($wilaya['wilaya_number']) . '">' .
                                    htmlspecialchars($wilaya['wilaya_number'] . ' - ' . $wilaya['wilaya_name']) .
                                    '</option>';
                            }
                        } catch (PDOException $e) {
                            error_log('Error fetching wilayas: ' . $e->getMessage());
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">Catégorie :</label>
                    <select name="category" class="w-full px-3 py-2 border rounded-full outline-none border-[#a1a1a1]"
                        required>
                        <option value="">Sélectionnez une catégorie</option>
                        <?php
                        try {
                            $categories_query = "SELECT * FROM categories ORDER BY category_name";
                            $categories_stmt = $pdo->query($categories_query);
                            $categories = $categories_stmt->fetchAll();
                            foreach ($categories as $category) {
                                echo '<option value="' . htmlspecialchars($category['category_id']) . '">' .
                                    htmlspecialchars($category['category_name']) .
                                    '</option>';
                            }
                        } catch (PDOException $e) {
                            error_log('Error fetching categories: ' . $e->getMessage());
                        }
                        ?>
                    </select>
                </div>

                <button type="submit" name="add_place"
                    class="bg-[#2fb52f] text-white py-2 rounded-full hover:opacity-80 transition-colors cursor-pointer font-bold w-full">
                    Ajouter le lieu
                </button>
            </form>
        </div>
    </div>

    <!-- Modify Lieu Modal -->
    <div id="modifyLieuModal"
        class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
        <div class="bg-white p-8 rounded-lg w-[90%] max-w-[600px] max-h-[90vh] shadow-xl overflow-hidden flex flex-col">
            <div class="flex justify-between items-center mb-4 bg-white sticky top-0 z-10 pb-4 border-b">
                <h2 class="text-2xl font-bold">Modifier le lieu</h2>
                <button onclick="hideModifyLieuModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="overflow-y-auto flex-1">
                <form id="modifyLieuForm" class="space-y-4">
                    <input type="hidden" id="modifyLieuId" name="lieu_id">
                    <div>
                        <label class="block text-gray-700 mb-2">Nom du lieu</label>
                        <input type="text" id="modifyTitle" name="title" class="w-full px-3 py-2 border rounded-full"
                            placeholder="Entrez le nom du lieu" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Description</label>
                        <textarea id="modifyContent" name="content" class="w-full px-3 py-2 border rounded-lg" rows="3"
                            placeholder="Entrez la description du lieu" required></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Location</label>
                        <textarea id="modifyLocation" name="location" class="w-full px-3 py-2 border rounded-lg"
                            rows="3" placeholder="Entrez la location du lieu" required></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Wilaya</label>
                        <select id="modifyWilaya" name="wilaya_id" class="w-full px-3 py-2 border rounded-full"
                            required>
                            <option value="">Sélectionnez une wilaya</option>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT * FROM wilayas ORDER BY wilaya_number");
                                while ($wilaya = $stmt->fetch()) {
                                    echo '<option value="' . $wilaya['wilaya_number'] . '">' .
                                        htmlspecialchars($wilaya['wilaya_number'] . ' - ' . $wilaya['wilaya_name']) .
                                        '</option>';
                                }
                            } catch (PDOException $e) {
                                echo "Error loading wilayas";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Catégorie</label>
                        <select id="modifyCategory" name="category_id" class="w-full px-3 py-2 border rounded-full"
                            required>
                            <option value="">Sélectionnez une catégorie</option>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT * FROM categories ORDER BY category_name");
                                while ($category = $stmt->fetch()) {
                                    echo '<option value="' . $category['category_id'] . '">' .
                                        htmlspecialchars($category['category_name']) .
                                        '</option>';
                                }
                            } catch (PDOException $e) {
                                echo "Error loading categories";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Images</label>
                        <div id="currentImages"
                            class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-2 max-h-[200px] overflow-y-auto p-2 bg-gray-50 rounded-lg">
                            <!-- Current images will be displayed here -->
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <label class="flex-1">
                                    <input type="file" name="new_images[]" class="hidden" multiple accept="image/*"
                                        id="imageUpload" onchange="previewNewImages(this)">
                                    <div
                                        class="w-full px-3 py-2 border rounded-full cursor-pointer hover:bg-gray-50 transition-colors text-center">
                                        <i class="fas fa-cloud-upload-alt mr-2"></i>
                                        Select Images
                                    </div>
                                </label>
                                <button type="button" onclick="document.getElementById('imageUpload').click()"
                                    class="px-4 py-2 bg-gray-100 rounded-full hover:bg-gray-200 transition-colors">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div id="newImagesPreview"
                                class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-[200px] overflow-y-auto p-2 bg-gray-50 rounded-lg hidden">
                                <!-- New image previews will be displayed here -->
                            </div>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">You can select multiple images at once</p>
                    </div>
                </form>
            </div>
            <div class="sticky bottom-0 bg-white pt-4 border-t mt-4">
                <button type="submit" form="modifyLieuForm"
                    class="w-full bg-blue-500 text-white py-2 rounded-full hover:bg-blue-600 transition-colors cursor-pointer">
                    Modifier le lieu
                </button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script src="search.js"></script>
    <script>
        function previewProfilePicture(input) {
            const preview = document.getElementById('profilePreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function checkAuthAndShowAddPlaceForm() {
            <?php if (isset($_SESSION['user_id'])): ?>
                showAddPlaceModal();
            <?php else: ?>
                showAuthWarningModal();
            <?php endif; ?>
        }

        function showAddPlaceModal() {
            document.getElementById('addPlaceModal').classList.remove('hidden');
            document.getElementById('addPlaceModal').classList.add('flex');
        }

        function hideAddPlaceModal() {
            document.getElementById('addPlaceModal').classList.remove('flex');
            document.getElementById('addPlaceModal').classList.add('hidden');
        }

        function showAuthWarningModal() {
            document.getElementById('authWarningModal').classList.remove('hidden');
            document.getElementById('authWarningModal').classList.add('flex');
        }

        function hideAuthWarningModal() {
            document.getElementById('authWarningModal').classList.remove('flex');
            document.getElementById('authWarningModal').classList.add('hidden');
        }

        function showLoginForm() {
            hideAuthWarningModal();
            document.getElementById('loginModal').classList.remove('hidden');
            document.getElementById('loginModal').classList.add('flex');
        }

        function showSignupForm() {
            hideAuthWarningModal();
            document.getElementById('signupModal').classList.remove('hidden');
            document.getElementById('signupModal').classList.add('flex');
        }

        function hideLoginModal() {
            document.getElementById('loginModal').classList.remove('flex');
            document.getElementById('loginModal').classList.add('hidden');
        }

        function hideSignupModal() {
            document.getElementById('signupModal').classList.remove('flex');
            document.getElementById('signupModal').classList.add('hidden');
        }

        // Function to fetch and display lieu data
        async function fetchAndDisplayLieux() {
            try {
                console.log('Fetching lieux data...');
                const response = await fetch('fetch_lieux.php');
                console.log('Response received:', response);

                const result = await response.json();
                console.log('Data received:', result);

                if (result.success) {
                    const container = document.getElementById('attractionsContainer');
                    console.log('Container found:', container);

                    if (!container) {
                        console.error('Container element not found');
                        return;
                    }

                    container.innerHTML = ''; // Clear existing content

                    if (result.data.length === 0) {
                        console.log('No lieux data available');
                        container.innerHTML = '<p class="text-gray-600 text-center">No attractions available at the moment.</p>';
                        return;
                    }

                    console.log('Creating cards for', result.data.length, 'lieux');
                    result.data.forEach(lieu => {
                        console.log('Creating card for lieu:', lieu);
                        const card = createLieuCard(lieu);
                        container.appendChild(card);
                    });
                } else {
                    console.error('Error fetching data:', result.message);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        // Fetch and display lieux when the page loads
        document.addEventListener('DOMContentLoaded', fetchAndDisplayLieux);

        // Add slideshow functionality
        function initializeSlideshow(container) {
            const images = container.querySelectorAll('img');
            const dotsContainer = container.parentElement.querySelector('.dots-container');
            let currentIndex = 0;

            // Create dots
            dotsContainer.innerHTML = '';
            images.forEach((_, index) => {
                const dot = document.createElement('div');
                dot.className = `w-2 h-2 rounded-full bg-white/50 cursor-pointer transition-all ${index === 0 ? 'bg-white scale-125' : ''}`;
                dot.onclick = () => goToSlide(index);
                dotsContainer.appendChild(dot);
            });

            // Add click handlers for navigation arrows
            const prevButton = container.parentElement.querySelector('.prev');
            const nextButton = container.parentElement.querySelector('.next');

            prevButton.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                goToSlide(currentIndex - 1);
            };

            nextButton.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                goToSlide(currentIndex + 1);
            };

            function goToSlide(index) {
                // Hide all images
                images.forEach(img => img.style.display = 'none');

                // Update current index with wrapping
                currentIndex = (index + images.length) % images.length;

                // Show current image
                images[currentIndex].style.display = 'block';

                // Update dots
                const dots = dotsContainer.querySelectorAll('div');
                dots.forEach((dot, i) => {
                    dot.className = `w-2 h-2 rounded-full bg-white/50 cursor-pointer transition-all ${i === currentIndex ? 'bg-white scale-125' : ''}`;
                });
            }

            // Initialize first slide
            goToSlide(0);

            // Add auto-advance functionality
            let slideInterval = setInterval(() => goToSlide(currentIndex + 1), 5000);

            // Pause auto-advance on hover
            container.addEventListener('mouseenter', () => clearInterval(slideInterval));
            container.addEventListener('mouseleave', () => {
                slideInterval = setInterval(() => goToSlide(currentIndex + 1), 5000);
            });
        }

        // Update the createLieuCard function to initialize slideshow
        function createLieuCard(lieu) {
            const card = document.createElement('a');
            card.href = `card-details.php?id=${lieu.lieu_id}`;
            card.className = 'block cursor-pointer group';

            // Get category icon and color based on category
            const categoryInfo = getCategoryInfo(lieu.category_name);

            // Calculate star display
            const rating = lieu.average_rating || 0;
            const fullStars = Math.floor(rating);
            const halfStar = rating - fullStars >= 0.5;
            let starsHtml = '';

            for (let i = 1; i <= 5; i++) {
                if (i <= fullStars) {
                    starsHtml += '<i class="fas fa-star text-yellow-400"></i>';
                } else if (i === fullStars + 1 && halfStar) {
                    starsHtml += '<i class="fas fa-star-half-alt text-yellow-400"></i>';
                } else {
                    starsHtml += '<i class="fas fa-star text-gray-300"></i>';
                }
            }

            // Create image gallery HTML
            const imageGalleryHtml = lieu.images.map((image, index) => `
                <img src="${image}" alt="${lieu.title}" 
                     class="w-full h-full object-cover transition-opacity duration-300 hover:opacity-80"
                     style="display: ${index === 0 ? 'block' : 'none'}">
            `).join('');

            card.innerHTML = `
                <div class="bg-white rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 h-full">
                    <div class="relative">
                        <div class="relative h-72 image-gallery" data-gallery="${lieu.lieu_id}">
                            ${imageGalleryHtml}
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <div class="absolute top-4 left-4 z-30">
                                <button onclick="event.preventDefault(); showLieuOptions(${lieu.lieu_id})"
                                    class="bg-white p-2 rounded-full shadow-lg hover:bg-gray-100 transition-colors">
                                    <i class="fas fa-ellipsis-v text-gray-600"></i>
                                </button>
                                <div id="lieuOptions${lieu.lieu_id}" class="hidden absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-40">
                                    <button onclick="event.preventDefault(); showModifyLieuModal(${lieu.lieu_id})"
                                        class="w-full text-left px-4 py-2 hover:bg-gray-100">
                                        <i class="fas fa-edit mr-2"></i> Modify
                                    </button>
                                    <button onclick="event.preventDefault(); deleteLieu(${lieu.lieu_id})" class="w-full text-left px-4 py-2 text-red-600 hover:bg-gray-100">
                                        <i class="fas fa-trash mr-2"></i> Delete
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                            <button onclick="event.preventDefault(); toggleSave(${lieu.lieu_id}, this)"
                                class="absolute top-4 right-4 bg-white p-2 rounded-full shadow-lg hover:bg-gray-100 transition-colors z-30 w-10">
                                <i class="far fa-bookmark text-gray-600"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold mb-2 group-hover:underline">${lieu.title}</h3>
                        <p class="text-gray-700 mb-4">${lieu.content.substring(0, 150)}${lieu.content.length > 150 ? '...' : ''}</p>

                        <div class="inline-block px-3 py-1 rounded-full text-sm font-semibold mb-2 ${categoryInfo.bgColor} ${categoryInfo.textColor}">
                            <i class="${categoryInfo.icon} mr-1"></i>
                            ${lieu.category_name}
                        </div>
                        
                        <p class="text-gray-600 mb-2">Par ${lieu.author_name}</p>

                        <div class="flex items-center mb-2">
                            <div class="flex">
                                ${starsHtml}
                            </div>
                            <span class="ml-2 text-gray-600">${rating.toFixed(1)}</span>
                            ${lieu.total_ratings > 0
                    ? `<span class="ml-2 text-gray-400">(${lieu.total_ratings})</span>`
                    : `<span class="ml-2 text-gray-400">(No ratings yet)</span>`
                }
                        </div>
                    </div>
                </div>
            `;

            // Initialize slideshow after adding to DOM
            setTimeout(() => {
                const gallery = card.querySelector('.image-gallery');
                if (gallery) {
                    initializeSlideshow(gallery);
                }
            }, 0);

            return card;
        }

        // Function to get category styling information
        function getCategoryInfo(categoryName) {
            const categories = {
                'Cultural and Heritage': {
                    icon: 'fas fa-landmark',
                    bgColor: 'bg-[#FFE5D9]',
                    textColor: 'text-[#FF7D5C]'
                },
                'Religious': {
                    icon: 'fas fa-mosque',
                    bgColor: 'bg-[#E6F7FF]',
                    textColor: 'text-[#1890FF]'
                },
                'Nature': {
                    icon: 'fas fa-tree',
                    bgColor: 'bg-[#F6FFED]',
                    textColor: 'text-[#52C41A]'
                },
                'Desert': {
                    icon: 'fas fa-sun',
                    bgColor: 'bg-[#FFF1F0]',
                    textColor: 'text-[#FF4D4F]'
                },
                'Sport': {
                    icon: 'fas fa-running',
                    bgColor: 'bg-[#E6FFFB]',
                    textColor: 'text-[#13C2C2]'
                },
                'Amusement': {
                    icon: 'fas fa-ticket-alt',
                    bgColor: 'bg-[#F9F0FF]',
                    textColor: 'text-[#722ED1]'
                }
            };

            return categories[categoryName] || {
                icon: 'fas fa-map-marker-alt',
                bgColor: 'bg-gray-100',
                textColor: 'text-gray-600'
            };
        }

        // Add save functionality
        function toggleSave(lieuId, button) {
            <?php if (!isset($_SESSION['user_id'])): ?>
                showAuthWarningModal();
                return;
            <?php endif; ?>

            const icon = button.querySelector('i');

            fetch('save_lieu.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lieu_id: lieuId
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.action === 'saved') {
                            button.classList.add('saved');
                            icon.classList.remove('far');
                            icon.classList.add('fas');
                        } else {
                            button.classList.remove('saved');
                            icon.classList.remove('fas');
                            icon.classList.add('far');
                        }
                    } else {
                        if (data.message.includes('log in')) {
                            showAuthWarningModal();
                        } else {
                            alert(data.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving place. Please try again.');
                });
        }

        // Add form submission handler for adding places
        document.getElementById('addPlaceForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            try {
                const response = await fetch('api/lieu.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    hideAddPlaceModal();
                    this.reset();
                    fetchAndDisplayLieux();
                    alert(result.message || 'Place added successfully!');
                } else {
                    alert(result.message || 'Error adding place');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error adding place. Please try again.');
            }
        });

        // Add login form submission handler
        document.getElementById('loginForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    hideLoginModal();
                    // Reload the page to show the updated UI
                    window.location.reload();
                } else {
                    alert(result.message || 'Error logging in');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error logging in. Please try again.');
            }
        });

        // Fetch and display lieux when the page loads
        document.addEventListener('DOMContentLoaded', fetchAndDisplayLieux);

        function showLieuOptions(lieuId) {
            const optionsMenu = document.getElementById(`lieuOptions${lieuId}`);
            optionsMenu.classList.toggle('hidden');

            // Close other open menus
            document.querySelectorAll('[id^="lieuOptions"]').forEach(menu => {
                if (menu.id !== `lieuOptions${lieuId}`) {
                    menu.classList.add('hidden');
                }
            });
        }

        function deleteLieu(lieuId) {
            if (confirm('Are you sure you want to delete this place?')) {
                fetch('delete_lieu.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        lieu_id: lieuId
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remove the card from the DOM
                            const card = document.querySelector(`[data-gallery="${lieuId}"]`).closest('.block');
                            card.remove();
                            alert('Place deleted successfully');
                        } else {
                            alert(data.message || 'Error deleting place');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error deleting place. Please try again.');
                    });
            }
        }

        // Close menus when clicking outside
        document.addEventListener('click', function (event) {
            if (!event.target.closest('[id^="lieuOptions"]') && !event.target.closest('.fa-ellipsis-v')) {
                document.querySelectorAll('[id^="lieuOptions"]').forEach(menu => {
                    menu.classList.add('hidden');
                });
            }
        });

        function showModifyLieuModal(lieuId) {
            const modal = document.getElementById('modifyLieuModal');

            // Fetch the current lieu data
            fetch(`get_lieu_details.php?lieu_id=${lieuId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const lieu = data.lieu;
                        // Set the values in the form
                        document.getElementById('modifyLieuId').value = lieu.lieu_id;
                        document.getElementById('modifyTitle').value = lieu.title;
                        document.getElementById('modifyContent').value = lieu.content;
                        document.getElementById('modifyLocation').value = lieu.location;
                        document.getElementById('modifyCategory').value = lieu.category_id;
                        document.getElementById('modifyWilaya').value = lieu.wilaya_id;

                        // Show the modal
                        modal.style.opacity = '0';
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                        // Trigger reflow
                        modal.offsetHeight;
                        modal.style.opacity = '1';

                        // Load current images
                        loadCurrentImages(lieuId);
                    } else {
                        alert('Error loading lieu data: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading lieu data. Please try again.');
                });
        }

        function hideModifyLieuModal() {
            const modal = document.getElementById('modifyLieuModal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }, 200);
        }

        let deletedImages = [];

        function removeImage(imageUrl) {
            if (confirm('Are you sure you want to remove this image?')) {
                deletedImages.push(imageUrl);
                const imageElement = document.querySelector(`img[src="${imageUrl}"]`).parentElement;
                imageElement.remove();
            }
        }

        function loadCurrentImages(lieuId) {
            const imagesContainer = document.getElementById('currentImages');
            imagesContainer.innerHTML = '<p class="text-gray-500">Loading images...</p>';

            // Reset deleted images array
            deletedImages = [];

            // Fetch current images for the lieu
            fetch(`get_lieu_images.php?lieu_id=${lieuId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        imagesContainer.innerHTML = data.images.map(image => `
                            <div class="relative group">
                                <img src="${image}" alt="Current image" class="w-full h-24 object-cover rounded">
                                <button type="button" onclick="removeImage('${image}')" class="absolute top-1 right-1 bg-red-500 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition-opacity">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        `).join('');
                    } else {
                        imagesContainer.innerHTML = '<p class="text-red-500">Error loading images</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    imagesContainer.innerHTML = '<p class="text-red-500">Error loading images</p>';
                });
        }

        function previewNewImages(input) {
            const previewContainer = document.getElementById('newImagesPreview');
            previewContainer.innerHTML = '';
            previewContainer.classList.remove('hidden');

            if (input.files && input.files.length > 0) {
                Array.from(input.files).forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const div = document.createElement('div');
                        div.className = 'relative group';
                        div.innerHTML = `
                            <img src="${e.target.result}" alt="Preview" class="w-full h-24 object-cover rounded">
                            <button type="button" onclick="removeNewImage(${index})" class="absolute top-1 right-1 bg-red-500 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition-opacity">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                        previewContainer.appendChild(div);
                    }
                    reader.readAsDataURL(file);
                });
            } else {
                previewContainer.classList.add('hidden');
            }
        }

        function removeNewImage(index) {
            const input = document.getElementById('imageUpload');
            const dt = new DataTransfer();
            const files = input.files;

            for (let i = 0; i < files.length; i++) {
                if (i !== index) {
                    dt.items.add(files[i]);
                }
            }

            input.files = dt.files;
            previewNewImages(input);
        }

        // Update the form submission handler
        document.getElementById('modifyLieuForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            // Debug: Log form data before sending
            console.log('Form data before sending:');
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }

            // Add deleted images to formData
            deletedImages.forEach(imageUrl => {
                formData.append('deleted_images[]', imageUrl);
            });

            // Add all selected files to FormData
            const imageInput = document.getElementById('imageUpload');
            if (imageInput.files.length > 0) {
                for (let i = 0; i < imageInput.files.length; i++) {
                    formData.append('new_images[]', imageInput.files[i]);
                }
            }

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    // Debug: Log the raw response
                    console.log('Raw response:', response);
                    return response.json();
                })
                .then(data => {
                    // Debug: Log the parsed response data
                    console.log('Response data:', data);

                    if (data.success) {
                        alert('Lieu modified successfully');
                        hideModifyLieuModal();
                        // Refresh the page to show updated data
                        location.reload();
                    } else {
                        alert(data.message || 'Error modifying lieu');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error modifying lieu. Please try again.');
                });
        });

        function toggleProfileMenu() {
            const menu = document.getElementById('profileMenu');
            menu.classList.toggle('hidden');
        }

        // Close the menu when clicking outside
        document.addEventListener('click', function (event) {
            const menu = document.getElementById('profileMenu');
            const button = event.target.closest('button');

            if (!button && !menu.contains(event.target)) {
                menu.classList.add('hidden');
            }
        });
    </script>
</body>

</html>