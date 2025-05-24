<?php
session_start();
require_once 'config/database.php';

// Get lieu ID from URL
$lieu_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get user information if logged in
$user = null;
$current_user_id = null;
if (isset($_SESSION['user_id'])) {
    $current_user_id = $_SESSION['user_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$current_user_id]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Error fetching user data: ' . $e->getMessage());
    }
}

// Check if lieu exists and is approved (or if user is admin or author)
try {
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
              WHERE l.lieu_id = ? AND (l.status = 'approved' OR u.user_id = ?)"; // Allow author to see their pending place

    $isAdmin = isset($user['role']) && $user['role'] === 'admin';

    if ($isAdmin) {
        // Admins can see all places regardless of status
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
                  WHERE l.lieu_id = ?
                  GROUP BY l.lieu_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$current_user_id ?? 0, $lieu_id]);
    } else {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$current_user_id ?? 0, $lieu_id, $current_user_id ?? 0]);
    }


    $lieu = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$lieu) {
        // If lieu not found or not approved/owned for non-admin, redirect
        header('Location: index.php');
        exit();
    }


    // Process images
    $lieu['images'] = $lieu['images'] ? explode(',', $lieu['images']) : [];
    if (empty($lieu['images'])) {
        $lieu['images'] = ['images and videos/default-place.jpg'];
    }

    // Process rating
    $lieu['average_rating'] = $lieu['average_rating'] ? round($lieu['average_rating'], 1) : 0;
    $lieu['total_ratings'] = $lieu['total_ratings'] ?: 0;

} catch (PDOException $e) {
    error_log('Error in card-details.php: ' . $e->getMessage());
    // Handle specific error case for invalid lieu_id or DB issues more gracefully if needed
    // For now, redirect to index page
    header('Location: index.php');
    exit();
}


function getCategoryInfo($categoryName)
{
    $categoryInfo = [
        'Cultural and Heritage' => ['bgColor' => 'bg-[#FFE5D9]', 'textColor' => 'text-[#FF7D5C]', 'icon' => 'fas fa-landmark'],
        'Religious' => ['bgColor' => 'bg-[#E6F7FF]', 'textColor' => 'text-[#1890FF]', 'icon' => 'fas fa-mosque'],
        'Beaches' => ['bgColor' => 'bg-[#E6F7FF]', 'textColor' => 'text-[#1890FF]', 'icon' => 'fas fa-umbrella-beach'],
        'Desert' => ['bgColor' => 'bg-[#FFF1F0]', 'textColor' => 'text-[#FF4D4F]', 'icon' => 'fas fa-sun'],
        'Museums' => ['bgColor' => 'bg-[#F0F5FF]', 'textColor' => 'text-[#2F54EB]', 'icon' => 'fas fa-museum'],
        'Shopping' => ['bgColor' => 'bg-[#FFF0F6]', 'textColor' => 'text-[#EB2F96]', 'icon' => 'fas fa-shopping-bag'],
        'Nature' => ['bgColor' => 'bg-[#F6FFED]', 'textColor' => 'text-[#52C41A]', 'icon' => 'fas fa-tree'],
        'Sport' => ['bgColor' => 'bg-[#E6FFFB]', 'textColor' => 'text-[#13C2C2]', 'icon' => 'fas fa-running'],
        'Amusement' => ['bgColor' => 'bg-[#F9F0FF]', 'textColor' => 'text-[#722ED1]', 'icon' => 'fas fa-ticket-alt']
    ];
    return $categoryInfo[$categoryName] ?? ['bgColor' => 'bg-gray-200', 'textColor' => 'text-gray-800', 'icon' => 'fas fa-tag'];
}

// Pass user ID and isAdmin flag to JavaScript
$current_user_id_js = json_encode($current_user_id);
$isAdmin_js = json_encode($isAdmin);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lieu['title']); ?> - Algeria Tourism</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>

<body class="bg-gray-50">
    <!-- Navigation -->
    <div class="pt-24">
        <div class="fixed top-0 left-0 right-0 flex items-center justify-between p-4 bg-white shadow-md z-50">
            <div class="container mx-auto px-4 max-w-[1152px] flex items-center justify-between">
                <a href="index.php">
                    <img src="images and videos/ChatGPT Image 29 avr. 2025, 00_41_42.png" alt="Logo"
                        class="h-16 w-auto">
                </a>
                <div class="flex gap-4">
                    <button onclick="checkAuthAndShowAddPlaceForm()"
                        class="bg-green-500 text-white px-6 py-2 rounded-full hover:bg-green-600 transition-colors whitespace-nowrap cursor-pointer flex items-center gap-2">
                        <i class="fas fa-plus"></i>
                        Ajouter un lieu
                    </button>
                </div>
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
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="flex items-center gap-4">
                        <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                            <span class="bg-blue-500 text-white px-2 py-0.5 rounded text-sm">Admin</span>
                        <?php endif; ?>
                        <div class="relative group">
                            <button class="flex items-center gap-2 hover:bg-gray-100 p-2 rounded-full transition-colors">
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
                            <div
                                class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 hidden group-hover:block">
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

        <!-- Main Content -->
        <div class="container mx-auto px-4 py-8">
            <!-- Place Details Header -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <h1 class="text-3xl font-bold mb-4 text-gray-800"><?php echo htmlspecialchars($lieu['title']); ?>
                </h1>
                <div class="flex items-center gap-4 mb-6">
                    <div class="flex items-center">
                        <div class="flex">
                            <?php
                            $rating = $lieu['average_rating'];
                            $fullStars = floor($rating);
                            $halfStar = $rating - $fullStars >= 0.5;

                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $fullStars) {
                                    echo '<i class="fas fa-star text-yellow-400"></i>';
                                } elseif ($i == $fullStars + 1 && $halfStar) {
                                    echo '<i class="fas fa-star-half-alt text-yellow-400"></i>';
                                } else {
                                    echo '<i class="fas fa-star text-gray-300"></i>';
                                }
                            }
                            ?>
                        </div>
                        <span class="ml-2 text-gray-700 font-medium"><?php echo number_format($rating, 1); ?></span>
                        <?php if ($lieu['total_ratings'] > 0): ?>
                            <span class="ml-1 text-gray-500">(<?php echo $lieu['total_ratings']; ?>)</span>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <button onclick="showRatingModal(<?php echo $lieu_id; ?>)"
                                class="ml-4 text-emerald-600 hover:text-emerald-700 transition-colors cursor-pointer flex items-center gap-1 font-medium">
                                <i class="fas fa-star"></i>
                                <span>Give Rating</span>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="h-5 w-px bg-gray-300 mx-2 hidden md:block "></div>
                    <div class="flex items-center">
                        <i class="fas fa-map-marker-alt text-gray-700 mr-2"></i>
                        <span class="text-gray-700"><?php echo htmlspecialchars($lieu['wilaya_name']); ?></span>
                    </div>
                    <div class="h-5 w-px bg-gray-300 mx-2 hidden md:block "></div>
                    <div class="flex items-center">
                        <i class="fa-regular fa-user text-gray-700 mr-2"></i>
                        <span class="text-gray-700 ">By <?php echo htmlspecialchars($lieu['author_name']); ?></span>
                    </div>
                </div>
                <p class="text-gray-700 mb-6 leading-relaxed">
                    <?php echo nl2br(htmlspecialchars($lieu['content'])); ?>
                </p>
                <div class="flex items-center gap-4">
                    <div class="flex items-center <?php
                    $categoryInfo = getCategoryInfo($lieu['category_name']);
                    echo $categoryInfo['bgColor'];
                    ?> p-2 rounded-full px-4 pl-2 pr-[26px] ">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2">
                            <i class="<?php echo $categoryInfo['icon'] . ' ' . $categoryInfo['textColor']; ?>"></i>
                        </div>
                        <span
                            class="<?php echo $categoryInfo['textColor']; ?> font-medium"><?php echo htmlspecialchars($lieu['category_name']); ?></span>
                    </div>
                    <div class="flex items-center bg-[#E6F7FF] p-2 rounded-full pl-4 py-2 pr-[26px] ">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2">
                            <i class="fas fa-map-marker-alt text-[#1890FF]"></i>
                        </div>
                        <span
                            class="text-[#1890FF] font-medium"><?php echo htmlspecialchars($lieu['location']); ?></span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Left Side - Comments -->
                <div class="bg-white rounded-lg shadow-lg p-6 flex flex-col h-[800px]">
                    <h2 class="text-2xl font-bold mb-6">Comments</h2>
                    <div class="space-y-6 flex-grow overflow-y-auto" id="commentsContainer">
                        <!-- Comments will be loaded dynamically -->
                    </div>
                    <!-- Add Comment Form -->
                    <div class="mt-auto pt-6 border-t">
                        <h3 class="text-lg font-semibold mb-4">Add a Comment</h3>
                        <form id="commentForm" class="space-y-4" onsubmit="return handleCommentSubmit(event)">
                            <textarea
                                class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                rows="4" placeholder="Write your comment..." required></textarea>
                            <button type="submit"
                                class="bg-green-500 text-white px-6 py-2 rounded-md hover:bg-green-600">Post
                                Comment</button>
                        </form>
                    </div>
                </div>

                <!-- Right Side - Images -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="relative">
                        <div class="relative h-[600px] image-gallery" id="mainImageGallery">
                            <img src="<?php echo htmlspecialchars($lieu['images'][0]); ?>"
                                alt="<?php echo htmlspecialchars($lieu['title']); ?>"
                                class="w-full h-full object-cover rounded-lg" id="mainImage">
                            <!-- Navigation Arrows -->
                            <button
                                class="nav-arrow prev absolute left-4 top-1/2 -translate-y-1/2 bg-white p-3 rounded-full shadow-lg hover:bg-gray-100 transition-all z-30 cursor-pointer">
                                <i class="fas fa-chevron-left text-gray-800 text-xl"></i>
                            </button>
                            <button
                                class="nav-arrow next absolute right-4 top-1/2 -translate-y-1/2 bg-white p-3 rounded-full shadow-lg hover:bg-gray-100 transition-all z-30 cursor-pointer">
                                <i class="fas fa-chevron-right text-gray-800 text-xl"></i>
                            </button>
                            <button onclick="toggleSave(<?php echo $lieu_id; ?>)"
                                class="absolute top-4 right-4 bg-white p-2 rounded-full shadow-lg hover:bg-gray-100 transition-colors z-30 w-10 save-button <?php echo $lieu['is_saved'] ? 'saved' : ''; ?>">
                                <i
                                    class="<?php echo $lieu['is_saved'] ? 'fas' : 'far'; ?> fa-bookmark text-gray-600"></i>
                            </button>
                        </div>
                        <!-- Dots Container -->
                        <div
                            class="absolute bottom-0 left-0 right-0 flex justify-center gap-2 p-4 z-20 dots-container transition-transform duration-300">
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-4 mt-4">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <button id="addPictureButton"
                                class="flex-1 bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 transition-colors cursor-pointer flex items-center justify-center gap-2">
                                <i class="fas fa-camera"></i>
                                Add Picture
                            </button>
                        <?php endif; ?>

                        <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                            <button onclick="showModifyLieuModal(<?php echo $lieu_id; ?>)"
                                class="flex-1 bg-yellow-500 text-white px-6 py-3 rounded-lg hover:bg-yellow-600 transition-colors cursor-pointer flex items-center justify-center gap-2">
                                <i class="fas fa-edit"></i>
                                Modify Lieu
                            </button>
                        <?php endif; ?>
                    </div>
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
                        <select name="category"
                            class="w-full px-3 py-2 border rounded-full outline-none border-[#a1a1a1]" required>
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

        <!-- Login Modal -->
        <div id="loginModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200 ">
            <div class="bg-white p-8 rounded-lg w-96 shadow-xl relative border-[6px] border-[#327532]">
                <img src="images and videos/ChatGPT Image 29 avr. 2025, 00_41_42.png" alt=""
                    class="w-[90px] mx-auto mb-7">
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

        <!-- Sign Up Modal -->
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
                        <label class="block text-gray-700 mb-2 font-semibold tracking-[1px]">Confirmer le mot de
                            passe
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
        <!-- Rating Modal -->
        <div id="ratingModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
            <div class="bg-white p-8 rounded-lg w-96 shadow-xl">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold">Give Rating</h2>
                    <button onclick="hideRatingModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="ratingForm" class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Rating</label>
                        <div class="flex items-center gap-4">
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                echo '<i class="fas fa-star text-gray-300 rating-star" data-rating="' . $i . '"></i>';
                            }
                            ?>
                        </div>
                    </div>
                    <input type="hidden" id="ratingLieuId" value="">
                    <input type="hidden" id="ratingValue" value="">
                    <button type="submit"
                        class="w-full bg-blue-500 text-white py-2 rounded-full hover:bg-blue-600 transition-colors cursor-pointer">
                        Submit
                    </button>
                </form>
            </div>
        </div>

        <!-- Auth Warning Modal -->
        <div id="authWarningModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
            <div class="bg-white p-8 rounded-lg w-[400px] shadow-xl">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold">Authentication Required</h2>
                    <button onclick="hideAuthWarningModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="text-gray-600 mb-6">You need to be logged in to perform this action.</p>
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

        <!-- Add Picture Modal -->
        <div id="addPictureModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
            <div class="bg-white p-8 rounded-lg w-[600px] shadow-xl">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold">Add Pictures</h2>
                    <button onclick="hideAddPictureModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="addPictureForm" class="space-y-4" enctype="multipart/form-data">
                    <input type="hidden" name="lieu_id" value="<?php echo $lieu_id; ?>">
                    <div>
                        <label class="block text-gray-700 mb-2">Select Images</label>
                        <input type="file" name="images[]" class="w-full px-3 py-2 border rounded-full" multiple
                            accept="image/*" required>
                    </div>
                    <button type="submit"
                        class="w-full bg-green-500 text-white py-2 rounded-full hover:bg-green-600 transition-colors cursor-pointer">
                        Upload Images
                    </button>
                </form>
            </div>
        </div>

        <script src="script.js"></script>
        <script src="search.js"></script>

        <script>
            // Pass user ID and isAdmin flag to JavaScript
            const currentUserId = <?php echo json_encode($current_user_id); ?>;
            const isAdmin = <?php echo json_encode($isAdmin); ?>;

            // Image Gallery Functionality
            let currentImageIndex = 0;
            const images = <?php echo json_encode($lieu['images']); ?>;
            const mainImage = document.getElementById('mainImage');
            const dotsContainer = document.querySelector('.dots-container');

            // Create dots
            images.forEach((_, index) => {
                const dot = document.createElement('div');
                dot.className = `w-3 h-3 rounded-full cursor-pointer ${index === 0 ? 'bg-green-500' : 'bg-gray-300'}`;
                dot.onclick = () => showImage(index);
                dotsContainer.appendChild(dot);
            });

            // Show image function
            function showImage(index) {
                currentImageIndex = index;
                mainImage.src = images[index];
                updateDots();
            }

            // Update dots
            function updateDots() {
                const dots = dotsContainer.children;
                for (let i = 0; i < dots.length; i++) {
                    dots[i].className = `w-3 h-3 rounded-full cursor-pointer ${i === currentImageIndex ? 'bg-green-500' : 'bg-gray-300'}`;
                }
            }

            // Navigation arrows
            document.querySelector('.nav-arrow.prev').onclick = () => {
                currentImageIndex = (currentImageIndex - 1 + images.length) % images.length;
                showImage(currentImageIndex);
            };

            document.querySelector('.nav-arrow.next').onclick = () => {
                currentImageIndex = (currentImageIndex + 1) % images.length;
                showImage(currentImageIndex);
            };

            // Comments functionality
            function createCommentElement(comment) {
                const div = document.createElement('div');
                div.className = 'comment bg-gray-50 p-4 rounded-lg cursor-pointer';
                div.dataset.commentId = comment.comment_id;
                div.dataset.authorId = comment.author_id;

                const profilePictureSrc = comment.profile_picture
                    ? `uploads/profile_pictures/${comment.profile_picture}`
                    : `https://ui-avatars.com/api/?name=${encodeURIComponent(comment.author_name)}`;

                div.innerHTML = `
                <div class="flex items-start gap-4">
                    <img src="${profilePictureSrc}"
                         alt="${comment.author_name}"
                         class="w-10 h-10 rounded-full object-cover"
                         onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(comment.author_name)}'">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-medium">${comment.author_name}</span>
                            <span class="text-gray-500 text-sm">${formatDate(comment.created_at)}</span>
                        </div>
                        <p class="text-gray-700">${comment.content}</p>
                    </div>
                </div>
            `;

                if (isAdmin || (currentUserId && currentUserId == comment.author_id)) {
                    div.addEventListener('click', function (e) {
                        const commentId = this.dataset.commentId;
                        if (confirm('Are you sure you want to delete this comment?')) {
                            deleteComment(commentId, this);
                        }
                    });
                } else {
                    div.classList.remove('cursor-pointer');
                }

                return div;
            }

            async function loadComments() {
                const container = document.getElementById('commentsContainer');
                container.innerHTML = '<p class="text-gray-500 text-center">Loading comments...</p>';

                try {
                    const response = await fetch(`api/comments.php?lieu_id=<?php echo $lieu_id; ?>`);
                    const result = await response.json();

                    if (result.success) {
                        container.innerHTML = '';

                        if (result.data.length === 0) {
                            container.innerHTML = '<p class="text-gray-500 text-center">No comments yet. Be the first to comment!</p>';
                            return;
                        }

                        result.data.forEach(comment => {
                            const commentElement = createCommentElement(comment);
                            container.appendChild(commentElement);
                        });
                    } else {
                        container.innerHTML = `<p class="text-red-500 text-center">Error loading comments: ${result.message}</p>`;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    container.innerHTML = `<p class="text-red-500 text-center">Error loading comments. Please try again later.</p>`;
                }
            }

            // Load comments when the page loads
            document.addEventListener('DOMContentLoaded', loadComments);

            // Handle comment form submission
            function handleCommentSubmit(event) {
                event.preventDefault();

                <?php if (!isset($_SESSION['user_id'])): ?>
                    showAuthWarningModal();
                    return false;
                <?php endif; ?>

                const textarea = event.target.querySelector('textarea');
                const commentText = textarea.value.trim();

                if (!commentText) {
                    alert('Please enter a comment');
                    return false;
                }

                fetch('api/comments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        lieu_id: <?php echo $lieu_id; ?>,
                        content: commentText
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Add the new comment to the top of the list
                            const container = document.getElementById('commentsContainer');
                            const commentElement = createCommentElement(data.data);
                            container.insertBefore(commentElement, container.firstChild);

                            // Clear the form
                            textarea.value = '';
                        } else {
                            if (data.message.includes('log in') || data.message.includes('authenticated')) {
                                showAuthWarningModal();
                            } else {
                                alert(data.message);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error posting comment. Please try again.');
                    });

                return false;
            }

            // Add event listener for the comment form
            document.getElementById('commentForm').addEventListener('submit', handleCommentSubmit);

            // Function to delete a comment
            async function deleteComment(commentId, commentElement) {
                try {
                    const response = await fetch('api/delete_comment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            comment_id: commentId
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        commentElement.remove();
                        alert('Comment deleted successfully');
                    } else {
                        alert(result.message || 'Error deleting comment');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error deleting comment. Please try again.');
                }
            }

            function formatDate(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const diff = now - date;

                if (diff < 60 * 1000) {
                    return 'Just now';
                }
                if (diff < 60 * 60 * 1000) {
                    const minutes = Math.floor(diff / (60 * 1000));
                    return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
                }
                if (diff < 24 * 60 * 60 * 1000) {
                    const hours = Math.floor(diff / (60 * 60 * 1000));
                    return `${hours} hour${hours === 1 ? '' : 's'} ago`;
                }
                if (diff < 7 * 24 * 60 * 60 * 1000) {
                    const days = Math.floor(diff / (24 * 60 * 60 * 1000));
                    return `${days} day${days === 1 ? '' : 's'} ago`;
                }
                return date.toLocaleDateString();
            }

            // Modal functions
            function showAddPlaceModal() {
                document.getElementById('addPlaceModal').classList.remove('hidden');
                document.getElementById('addPlaceModal').classList.add('flex');
            }

            function hideAddPlaceModal() {
                document.getElementById('addPlaceModal').classList.remove('flex');
                document.getElementById('addPlaceModal').classList.add('hidden');
            }

            function showLoginModal() {
                document.getElementById('loginModal').classList.remove('hidden');
                document.getElementById('loginModal').classList.add('flex');
            }

            function hideLoginModal() {
                document.getElementById('loginModal').classList.remove('flex');
                document.getElementById('loginModal').classList.add('hidden');
            }

            function showSignupForm() {
                hideLoginModal();
                document.getElementById('signupModal').classList.remove('hidden');
                document.getElementById('signupModal').classList.add('flex');
            }

            function hideSignupModal() {
                document.getElementById('signupModal').classList.remove('flex');
                document.getElementById('signupModal').classList.add('hidden');
            }

            // Rating Modal Functions
            function showRatingModal(lieuId) {
                document.getElementById('ratingModal').classList.remove('hidden');
                document.getElementById('ratingModal').classList.add('flex');
                document.getElementById('ratingLieuId').value = lieuId;
                document.getElementById('ratingValue').value = 0;
                resetStars();
            }

            function hideRatingModal() {
                document.getElementById('ratingModal').classList.add('hidden');
                document.getElementById('ratingModal').classList.remove('flex');
            }

            function resetStars() {
                const stars = document.querySelectorAll('.rating-star');
                stars.forEach(star => {
                    star.classList.remove('text-yellow-400');
                    star.classList.add('text-gray-300');
                });
            }

            // Add event listeners for star rating
            document.addEventListener('DOMContentLoaded', function () {
                const stars = document.querySelectorAll('.rating-star');
                stars.forEach(star => {
                    star.addEventListener('click', function () {
                        const rating = this.dataset.rating;
                        document.getElementById('ratingValue').value = rating;
                        resetStars();

                        stars.forEach(s => {
                            if (s.dataset.rating <= rating) {
                                s.classList.remove('text-gray-300');
                                s.classList.add('text-yellow-400');
                            }
                        });
                    });
                });

                document.getElementById('ratingForm').addEventListener('submit', function (e) {
                    e.preventDefault();
                    const lieuId = document.getElementById('ratingLieuId').value;
                    const rating = document.getElementById('ratingValue').value;

                    fetch('submit_rating.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            lieu_id: lieuId,
                            rating: rating
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                hideRatingModal();
                                location.reload();
                            } else {
                                alert('Error submitting rating: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error submitting rating. Please try again.');
                        });
                });
            });

            // Save functionality
            function toggleSave(lieuId) {
                <?php if (!isset($_SESSION['user_id'])): ?>
                    showAuthWarningModal();
                    return;
                <?php endif; ?>

                const button = document.querySelector('.save-button');
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
                            if (data.message.includes('log in') || data.message.includes('authenticated')) {
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

            // Add Picture Modal functions
            function showAddPictureModal() {
                <?php if (!isset($_SESSION['user_id'])): ?>
                    showAuthWarningModal();
                    return;
                <?php endif; ?>
                document.getElementById('addPictureModal').classList.remove('hidden');
                document.getElementById('addPictureModal').classList.add('flex');
            }

            function hideAddPictureModal() {
                document.getElementById('addPictureModal').classList.remove('flex');
                document.getElementById('addPictureModal').classList.add('hidden');
            }

            // Handle Add Picture form submission
            document.getElementById('addPictureForm').addEventListener('submit', async function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('action', 'add_images');

                try {
                    const response = await fetch('api/lieu.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert(result.message || 'Pictures uploaded successfully!');
                        hideAddPictureModal();
                        this.reset();
                        location.reload();
                    } else {
                        alert(result.message || 'Error uploading pictures');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error uploading pictures. Please try again.');
                }
            });

            // Add event listener for the Add Picture button
            document.getElementById('addPictureButton').addEventListener('click', showAddPictureModal);
        </script>
</body>

</html>