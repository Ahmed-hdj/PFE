<?php
session_start();
require_once 'config/database.php';

// Initialize user variable
$user = null;

// Get user information if logged in
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    } catch(PDOException $e) {
        error_log('Error fetching user data: ' . $e->getMessage());
    }
}

// Add this function before any calls to it
function getCategoryInfo($categoryName) {
    $categories = [
        'Cultural and Heritage' => [
            'icon' => 'fas fa-landmark',
            'bgColor' => 'bg-[#FFE5D9]',
            'textColor' => 'text-[#FF7D5C]'
        ],
        'Religious' => [
            'icon' => 'fas fa-mosque',
            'bgColor' => 'bg-[#E6F7FF]',
            'textColor' => 'text-[#1890FF]'
        ],
        'Nature' => [
            'icon' => 'fas fa-tree',
            'bgColor' => 'bg-[#F6FFED]',
            'textColor' => 'text-[#52C41A]'
        ],
        'Desert' => [
            'icon' => 'fas fa-sun',
            'bgColor' => 'bg-[#FFF1F0]',
            'textColor' => 'text-[#FF4D4F]'
        ],
        'Sport' => [
            'icon' => 'fas fa-running',
            'bgColor' => 'bg-[#E6FFFB]',
            'textColor' => 'text-[#13C2C2]'
        ],
        'Amusement' => [
            'icon' => 'fas fa-ticket-alt',
            'bgColor' => 'bg-[#F9F0FF]',
            'textColor' => 'text-[#722ED1]'
        ]
    ];
    
    return $categories[$categoryName] ?? [
        'icon' => 'fas fa-map-marker-alt',
        'bgColor' => 'bg-gray-100',
        'textColor' => 'text-gray-600'
    ];
}

// Fetch categories (might be needed for category names in cards)
try {
    $categories_query = "SELECT * FROM categories ORDER BY category_name";
    $categories_stmt = $pdo->query($categories_query);
    $categories_map = array_column($categories_stmt->fetchAll(PDO::FETCH_ASSOC), null, 'category_id');
} catch(PDOException $e) {
    error_log('Error fetching categories: ' . $e->getMessage());
    $categories_map = [];
}

// Fetch wilayas (might be needed for wilaya names in cards)
try {
    $wilayas_query = "SELECT * FROM wilayas ORDER BY wilaya_number";
    $wilayas_stmt = $pdo->query($wilayas_query);
    $wilayas_map = array_column($wilayas_stmt->fetchAll(PDO::FETCH_ASSOC), null, 'wilaya_number');
} catch(PDOException $e) {
    error_log('Error fetching wilayas: ' . $e->getMessage());
    $wilayas_map = [];
}

// Fetch saved places for the logged-in user
$saved_lieux = [];
if ($user) {
    try {
        $saved_lieux_query = "SELECT l.*, c.category_name, w.wilaya_name,
                                GROUP_CONCAT(li.image_url) as images,
                                u.username as author_name,
                                (SELECT AVG(rating) FROM lieu_ratings WHERE lieu_id = l.lieu_id) as average_rating,
                                (SELECT COUNT(*) FROM lieu_ratings WHERE lieu_id = l.lieu_id) as total_ratings
                              FROM lieu l
                              JOIN lieu_saves ls ON l.lieu_id = ls.lieu_id
                              LEFT JOIN categories c ON l.category_id = c.category_id
                              LEFT JOIN wilayas w ON l.wilaya_id = w.wilaya_number
                              LEFT JOIN lieu_images li ON l.lieu_id = li.lieu_id
                              LEFT JOIN users u ON l.user_id = u.user_id
                              WHERE ls.user_id = ?
                              GROUP BY l.lieu_id";
        $saved_lieux_stmt = $pdo->prepare($saved_lieux_query);
        $saved_lieux_stmt->execute([$user['user_id']]);
        $saved_lieux = $saved_lieux_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process images for each lieu
        foreach ($saved_lieux as &$lieu) {
            $lieu['images'] = $lieu['images'] ? explode(',', $lieu['images']) : [];
             if (empty($lieu['images'])) {
                $lieu['images'] = ['images and videos/default-place.jpg'];
            }
             // Process rating
            $lieu['average_rating'] = $lieu['average_rating'] ? round($lieu['average_rating'], 1) : 0;
            $lieu['total_ratings'] = $lieu['total_ratings'] ?: 0;
        }

    } catch(PDOException $e) {
        error_log('Error fetching saved lieux: ' . $e->getMessage());
        $saved_lieux = []; // Ensure it's an empty array on error
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Algeria Tourism</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>

<body class="bg-gray-50">
    <!-- Navigation -->
    <div class="pt-24">
        <div class="fixed top-0 left-0 right-0 flex items-center justify-between p-4 bg-white shadow-md z-50">
            <a href="index.php">
                <img src="images and videos/ChatGPT Image 29 avr. 2025, 00_41_42.png" alt="Logo" class="h-16 w-auto">
            </a>
                <button onclick="checkAuthAndShowAddPlaceForm()"
                    class="bg-green-500 text-white px-6 py-2 rounded-full hover:bg-green-600 transition-colors whitespace-nowrap cursor-pointer flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    Ajouter un lieu
                </button>
            <div class="relative w-1/2">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <form id="searchForm" class="w-full">
                        <input type="search" name="search" placeholder="Search for a Place or Wilaya"
                            class="pl-10 pr-14 py-2 border-[1px] border-[#a1a1a1] rounded-full w-full focus:outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 [&::-webkit-search-cancel-button]:cursor-pointer">
                        <button type="submit"
                            class="absolute right-[2px] top-[2px] h-[92%] text-gray-600 hover:text-gray-800 bg-gray-100 hover:bg-gray-200 rounded-r-full px-4 transition-colors flex items-center cursor-pointer">
                            <i class="fa-solid fa-magnifying-glass text-xl"></i>
                        </button>
                    </form>
                    <div id="searchSuggestions" class="absolute left-0 right-0 mt-1 bg-white rounded-lg shadow-lg hidden z-50 max-h-60 overflow-y-auto">
                    </div>
                </div>
            </div>
            <?php if ($user): ?>
            <div class="flex items-center gap-4">
                <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                    <span class="bg-blue-500 text-white px-2 py-0.5 rounded text-sm">Admin</span>
                <?php endif; ?>
                <div class="relative group">
                    <button class="flex items-center gap-2 hover:bg-gray-100 p-2 rounded-full transition-colors">
                        <?php if ($user['profile_picture']): ?>
                            <img src="uploads/profile_pictures/<?php echo htmlspecialchars($user['profile_picture']); ?>"
                                 alt="Profile"
                                 class="w-10 h-10 rounded-full object-cover">
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
                    <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 hidden group-hover:block">
                        <a href="profile.php" class="block px-4 py-2 hover:bg-gray-100 w-full text-left">
                            <i class="fas fa-user mr-2"></i> Profile
                        </a>
                        <a href="demandes.php" class="block px-4 py-2 hover:bg-gray-100">
                            <i class="fas fa-list mr-2"></i> Demands
                        </a>
                        <hr class="my-2">
                        <a href="logout.php" class="block w-full text-left px-4 py-2 text-red-600 hover:bg-gray-100">
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

    <!-- Profile Section -->
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <!-- Profile Title -->
        <h1 class="text-4xl font-bold text-gray-800 mb-8">Profile</h1>

        <!-- Profile Card -->
        <div class="bg-white mt-5 shadow-lg rounded-lg">
            <!-- Profile Content -->
            <div class="flex flex-col md:flex-row gap-8 p-8">
                <!-- Profile Picture Section -->
                <div id="largeProfilePicture" class="md:w-1/3">
                    <div class="relative">
                        <?php if ($user && $user['profile_picture']): ?>
                        <img id="profilePicture"
                                src="uploads/profile_pictures/<?php echo htmlspecialchars($user['profile_picture']); ?>"
                            alt="Profile Picture"
                            class="w-full aspect-square rounded-lg object-cover border-4 border-white shadow-lg">
                        <?php else: ?>
                            <div id="profilePicture" class="w-full aspect-square rounded-lg bg-gray-200 flex items-center justify-center text-gray-600 font-medium text-9xl border-4 border-white shadow-lg">
                                <?php echo $user_initials; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Profile Information Section -->
                <div class="md:w-2/3">
                    <div id="profileView" class="space-y-6">
                        <!-- Profile Header Info -->
                        <div class="mb-8">
                            <h2 id="displayName" class="text-3xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($user['username']); ?></h2>
                            <p class="text-gray-600">Member since 2024</p>
                        </div>

                        <!-- Profile Information -->
                        <div class="bg-gray-100 rounded-lg p-6 space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-600">Full Name</label>
                                    <p id="viewFullName" class="mt-1 text-gray-800"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-600">Email</label>
                                    <p id="viewEmail" class="mt-1 text-gray-800"><?php echo htmlspecialchars($user['email']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-600">Role</label>
                                    <p id="viewUsername" class="mt-1 text-gray-800"><?php echo htmlspecialchars($user['role']); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Button -->
                        <div class="flex justify-end">
                            <button onclick="toggleEditMode()"
                                class="bg-blue-500 text-white px-6 py-2 rounded-full hover:bg-blue-600 transition-colors">
                                Change Information
                            </button>
                        </div>
                    </div>

                    <!-- Edit Form (Hidden by default) -->
                    <div id="profileEdit" class="hidden space-y-6">
                        <form id="editProfileForm" class="space-y-6">
                            <!-- Profile Picture Upload -->
                            <div class="flex items-center space-x-6">
                                <div class="relative w-32 h-32">
                                    <img id="editProfilePicture"
                                        src="<?php echo $user && $user['profile_picture'] ? 'uploads/profile_pictures/' . htmlspecialchars($user['profile_picture']) : ''; ?>"
                                        alt="Profile Picture"
                                        class="w-full h-full rounded-full object-cover border-4 border-white shadow-lg <?php echo $user && $user['profile_picture'] ? '' : 'hidden'; ?>">
                                    <label for="profilePictureInput"
                                        class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 rounded-full cursor-pointer opacity-0 hover:opacity-100 transition-opacity <?php echo $user && $user['profile_picture'] ? '' : '' /* or different style if needed */ ?>">
                                        <i class="fas fa-camera text-white text-2xl"></i>
                                    </label>
                                    <input type="file" id="profilePictureInput" name="profile_picture" accept="image/*" class="hidden"
                                        onchange="previewProfilePicture(this)">
                                    <?php if (!$user || !$user['profile_picture']): ?>
                                        <div id="editProfilePicturePlaceholder" class="w-full h-full rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-medium text-4xl border-4 border-white shadow-lg">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Profile Picture</h3>
                                    <p class="text-gray-600">Click on the image to change your profile picture</p>
                                </div>
                            </div>

                            <!-- Edit Form Fields -->
                            <div class="bg-white rounded-lg p-6 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600">Full Name</label>
                                        <input type="text" id="editFullName" value="<?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>"
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600">Email</label>
                                        <input type="email" id="editEmail" value="<?php echo htmlspecialchars($user['email']); ?>"
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>

                            <!-- Save/Cancel Buttons -->
                            <div class="flex justify-end space-x-4">
                                <button type="button" onclick="toggleEditMode()"
                                    class="bg-gray-200 text-gray-700 px-6 py-2 rounded-full hover:bg-gray-300 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="bg-blue-500 text-white px-6 py-2 rounded-full hover:bg-blue-600 transition-colors">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Saved Places Section -->
        <div class="mt-12">
            <h2 class="text-3xl font-bold text-gray-800 mb-6">Saved Places</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="savedPlacesContainer">
                <?php if (empty($saved_lieux)): ?>
                    <p class="text-gray-600 text-center">You haven't saved any places yet.</p>
                <?php else: ?>
                    <?php foreach ($saved_lieux as $lieu): ?>
                        <?php
                        // Get category icon and color based on category
                        $categoryInfo = getCategoryInfo($lieu['category_name']);

                        // Calculate star display
                        $rating = $lieu['average_rating'] ?: 0;
                        $fullStars = floor($rating);
                        $halfStar = $rating - $fullStars >= 0.5;
                        $starsHtml = '';

                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $fullStars) {
                                $starsHtml .= '<i class="fas fa-star text-yellow-400"></i>';
                            } elseif ($i == $fullStars + 1 && $halfStar) {
                                $starsHtml .= '<i class="fas fa-star-half-alt text-yellow-400"></i>';
                            } else {
                                $starsHtml .= '<i class="fas fa-star text-gray-300"></i>';
                            }
                        }

                        // Create image gallery HTML
                        $imageGalleryHtml = '';
                        if (!empty($lieu['images'])) {
                            foreach ($lieu['images'] as $index => $image) {
                                $imageGalleryHtml .= '<img src="' . htmlspecialchars($image) . '" alt="' . htmlspecialchars($lieu['title']) . '" ';
                                $imageGalleryHtml .= 'class="w-full h-full object-cover transition-opacity duration-300 hover:opacity-80" ';
                                $imageGalleryHtml .= 'style="display: ' . ($index === 0 ? 'block' : 'none') . ';">';
                            }
                        } else {
                             $imageGalleryHtml .= '<img src="images and videos/default-place.jpg" alt="Default Image" ';
                             $imageGalleryHtml .= 'class="w-full h-full object-cover transition-opacity duration-300 hover:opacity-80" ';
                             $imageGalleryHtml .= 'style="display: block;">';
                        }
                        ?>
                        <!-- Card for Saved Place -->
                        <a href="card-details.php?id=<?php echo $lieu['lieu_id']; ?>"
                    class="block cursor-pointer group">
                    <div
                        class="bg-white rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 h-full">
                        <div class="relative">
                                    <div class="relative h-72 image-gallery" data-gallery="<?php echo $lieu['lieu_id']; ?>">
                                        <?php echo $imageGalleryHtml; ?>
                                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                        <div class="absolute top-4 left-4 z-30">
                                            <button onclick="event.preventDefault(); showLieuOptions(<?php echo $lieu['lieu_id']; ?>)"
                                                class="bg-white p-2 rounded-full shadow-lg hover:bg-gray-100 transition-colors">
                                                <i class="fas fa-ellipsis-v text-gray-600"></i>
                                </button>
                                            <div id="lieuOptions<?php echo $lieu['lieu_id']; ?>" class="hidden absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-40">
                                                <button onclick="event.preventDefault(); showModifyLieuModal(<?php echo $lieu['lieu_id']; ?>)"
                                                    class="w-full text-left px-4 py-2 hover:bg-gray-100">
                                                    <i class="fas fa-edit mr-2"></i> Modify
                                </button>
                                                <button onclick="event.preventDefault(); deleteLieu(<?php echo $lieu['lieu_id']; ?>)" class="w-full text-left px-4 py-2 text-red-600 hover:bg-gray-100">
                                                    <i class="fas fa-trash mr-2"></i> Delete
                                </button>
                            </div>
                            </div>
                                        <?php endif; ?>
                                <!-- Navigation Arrows -->
                                        <button class="nav-arrow prev absolute left-4 top-1/2 -translate-y-1/2 bg-white p-3 rounded-full shadow-lg hover:bg-gray-100 transition-all z-30 cursor-pointer">
                                    <i class="fas fa-chevron-left text-gray-800 text-xl"></i>
                                </button>
                                        <button class="nav-arrow next absolute right-4 top-1/2 -translate-y-1/2 bg-white p-3 rounded-full shadow-lg hover:bg-gray-100 transition-all z-30 cursor-pointer">
                                    <i class="fas fa-chevron-right text-gray-800 text-xl"></i>
                                </button>
                                        <button onclick="event.preventDefault(); toggleSave(<?php echo $lieu['lieu_id']; ?>, this)"
                                            class="absolute top-4 right-4 bg-white p-2 rounded-full shadow-lg hover:bg-gray-100 transition-colors z-30 w-10 saved">
                                            <i class="fas fa-bookmark text-gray-600"></i>
                                </button>
                            </div>
                            <div
                                class="absolute bottom-0 left-0 right-0 flex justify-center gap-2 p-4 z-20 dots-container transition-transform duration-300">
                            </div>
                        </div>
                        <div class="p-6">
                                    <h3 class="text-xl font-bold mb-2 group-hover:underline"><?php echo htmlspecialchars($lieu['title']); ?></h3>
                            <div class="flex items-center mb-2">
                                <div class="flex">
                                            <?php echo $starsHtml; ?>
                                </div>
                                        <span class="ml-2 text-gray-600"><?php echo number_format($rating, 1); ?></span>
                                        <?php if ($lieu['total_ratings'] > 0): ?>
                                            <span class="ml-2 text-gray-400">(<?php echo $lieu['total_ratings']; ?>)</span>
                                        <?php endif; ?>
                            </div>
                                    <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($lieu['category_name']); ?></p>
                                    <div class="flex items-center mb-4 <?php echo $categoryInfo['bgColor']; ?> p-2 rounded-lg">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2">
                                            <i class="<?php echo $categoryInfo['icon'] . ' ' . $categoryInfo['textColor']; ?>"></i>
                                </div>
                                        <span class="text-gray-600"><?php echo htmlspecialchars($lieu['category_name']); ?></span>
                            </div>
                            <div class="flex items-center mb-4">
                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($lieu['author_name']); ?>" alt="<?php echo htmlspecialchars($lieu['author_name']); ?>"
                                    class="w-8 h-8 rounded-full mr-2">
                                <div>
                                    <span class="text-gray-600">Par </span>
                                            <span class="font-medium"><?php echo htmlspecialchars($lieu['author_name']); ?></span>
                                </div>
                            </div>
                                    <p class="text-gray-700"><?php echo htmlspecialchars(substr($lieu['content'], 0, 150)) . (strlen($lieu['content']) > 150 ? '...' : ''); ?></p>
                        </div>
                    </div>
                </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal"
        class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
        <div class="bg-white p-8 rounded-lg w-96 shadow-xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold">Connexion</h2>
                <button onclick="hideLoginModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="loginForm" class="space-y-4">
                <div>
                    <label class="block text-gray-700 mb-2">Email</label>
                    <input type="email" class="w-full px-3 py-2 border rounded-full" placeholder="Entrez votre email">
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">Mot de passe</label>
                    <input type="password" class="w-full px-3 py-2 border rounded-full"
                        placeholder="Entrez votre mot de passe">
                </div>
                <button type="submit"
                    class="w-full bg-blue-500 text-white py-2 rounded-full hover:bg-blue-600 transition-colors cursor-pointer">
                    Se Connecter
                </button>
                <div class="text-center mt-4">
                    <a href="#" onclick="showSignupForm()"
                        class="text-blue-500 hover:text-blue-700 underline">S'inscrire</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Sign Up Modal -->
    <div id="signupModal"
        class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
        <div class="bg-white p-8 rounded-lg w-96 shadow-xl">
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
                    <label class="block text-gray-700 mb-2">Nom complet</label>
                    <input type="text" class="w-full px-3 py-2 border rounded-full"
                        placeholder="Entrez votre nom complet">
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">Email</label>
                    <input type="email" class="w-full px-3 py-2 border rounded-full" placeholder="Entrez votre email">
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">Mot de passe</label>
                    <input type="password" class="w-full px-3 py-2 border rounded-full"
                        placeholder="Créez votre mot de passe">
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">Confirmer le mot de passe</label>
                    <input type="password" class="w-full px-3 py-2 border rounded-full"
                        placeholder="Confirmez votre mot de passe">
                </div>
                <button type="submit"
                    class="w-full bg-blue-500 text-white py-2 rounded-full hover:bg-blue-600 transition-colors cursor-pointer">
                    S'inscrire
                </button>
                <div class="text-center mt-4">
                    <a href="#" onclick="showLoginForm()" class="text-blue-500 hover:text-blue-700 underline">Déjà un
                        compte ? Se connecter</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Place Modal -->
    <div id="addPlaceModal"
        class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
        <div class="bg-white p-8 rounded-lg w-[600px] shadow-xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold">Ajouter un lieu</h2>
                <button onclick="hideAddPlaceModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="addPlaceForm" class="space-y-4">
                <div>
                    <label class="block text-gray-700 mb-2">Nom du lieu</label>
                    <input type="text" class="w-full px-3 py-2 border rounded-full" placeholder="Entrez le nom du lieu">
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">Description</label>
                    <textarea class="w-full px-3 py-2 border rounded-lg" rows="3"
                        placeholder="Entrez la description du lieu"></textarea>
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">Location</label>
                    <textarea class="w-full px-3 py-2 border rounded-lg" rows="3"
                        placeholder="Entrez la description du lieu"></textarea>
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">Catégorie</label>
                    <select class="w-full px-3 py-2 border rounded-full">
                        <option value="">Sélectionnez une catégorie</option>
                        <option value="religious">Religieux</option>
                        <option value="cultural">Culturel</option>
                        <option value="nature">Nature</option>
                        <option value="historical">Historique</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">Images</label>
                    <input type="file" class="w-full px-3 py-2 border rounded-full" multiple accept="image/*">
                </div>
                <button type="submit"
                    class="w-full bg-green-500 text-white py-2 rounded-full hover:bg-green-600 transition-colors cursor-pointer">
                    Ajouter le lieu
                </button>
            </form>
        </div>
    </div>

    <!-- Auth Warning Modal -->
    <div id="authWarningModal"
        class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
        <div class="bg-white p-8 rounded-lg w-96 shadow-xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold">Authentication Required</h2>
                <button onclick="hideAuthWarningModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p class="text-gray-600 mb-6">Please log in to add a new place.</p>
            <div class="flex gap-4">
                <button onclick="hideAuthWarningModal()"
                    class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-full hover:bg-gray-300 transition-colors cursor-pointer">
                    Cancel
                </button>
                <button onclick="handleAuthWarningLogin()"
                    class="flex-1 bg-blue-500 text-white py-2 rounded-full hover:bg-blue-600 transition-colors cursor-pointer">
                    Log In
                </button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script src="search.js"></script>
    <script>
        // Image gallery functionality
        document.addEventListener('DOMContentLoaded', function () {
            const attractions = {
                jardin: {
                    images: [
                        'https://c1.staticflickr.com/1/276/32193070856_d5137fac58_h.jpg',
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60',
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60',
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60'
                    ]
                },
                notredame: {
                    images: [
                        'https://th.bing.com/th/id/OIP.w6L4I-nCyzbyOAfVcNNY-AHaE8?w=275&h=183&c=7&r=0&o=5&cb=iwc1&pid=1.7',
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60',
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60',
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60'
                    ]
                },
                casbah: {
                    images: [
                        'https://c1.staticflickr.com/1/507/32124243810_ebb256b3a1_h.jpg',
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60',
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60',
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60'
                    ]
                }
            };

            // Initialize galleries if they exist
            const galleries = document.querySelectorAll('.image-gallery');
            if (galleries.length > 0) {
                console.log('Initializing', galleries.length, 'image galleries');

                galleries.forEach(gallery => {
                    const galleryName = gallery.dataset.gallery;
                    const attraction = attractions[galleryName];

                    if (!attraction) {
                        console.warn('No images found for gallery:', galleryName);
                        return;
                    }

                    const img = gallery.querySelector('img');
                    const dotsContainer = gallery.parentElement.querySelector('.dots-container');
                    let currentIndex = 0;
                    const maxVisibleDots = 5;

                    function createDots() {
                        dotsContainer.innerHTML = '';
                        const totalImages = attraction.images.length;
                        const startDot = Math.max(0, Math.min(currentIndex - 2, totalImages - maxVisibleDots));
                        const endDot = Math.min(startDot + maxVisibleDots, totalImages);

                        // Add sliding animation class
                        dotsContainer.classList.add('sliding');

                        // Remove animation class after transition
                        setTimeout(() => {
                            dotsContainer.classList.remove('sliding');
                        }, 300);

                        for (let i = startDot; i < endDot; i++) {
                            const dot = document.createElement('div');
                            dot.className = 'w-2 h-2 rounded-full bg-white transition-all duration-300';
                            dot.style.opacity = i === currentIndex ? '1' : '0.5';
                            dot.style.cursor = 'pointer';
                            dot.addEventListener('click', () => updateImage(i));
                            dotsContainer.appendChild(dot);
                        }
                    }

                    function updateImage(newIndex) {
                        currentIndex = newIndex;
                        img.src = attraction.images[currentIndex];
                        createDots(); // Recreate dots to show the correct range
                    }

                    // Initial dots creation
                    createDots();

                    const prevBtn = gallery.querySelector('.nav-arrow.prev');
                    const nextBtn = gallery.querySelector('.nav-arrow.next');

                    if (prevBtn && nextBtn) {
                        prevBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            const newIndex = (currentIndex - 1 + attraction.images.length) % attraction.images.length;
                            updateImage(newIndex);
                        });

                        nextBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            const newIndex = (currentIndex + 1) % attraction.images.length;
                            updateImage(newIndex);
                        });
                    } else {
                        console.warn('Navigation buttons not found for gallery:', galleryName);
                    }
                });
            }

            // Bookmark functionality
            const bookmarkButtons = document.querySelectorAll('.fa-bookmark');
            bookmarkButtons.forEach(button => {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.classList.toggle('fa-regular');
                    this.classList.toggle('fa-solid');
                    this.classList.toggle('text-gray-600');
                    this.classList.toggle('text-blue-500');
                });
            });
        });

        // Login modal functions
        function showLoginModal() {
            const modal = document.getElementById('loginModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function hideLoginModal() {
            const modal = document.getElementById('loginModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }

        // Signup modal functions
        function showSignupForm() {
            const loginModal = document.getElementById('loginModal');
            const signupModal = document.getElementById('signupModal');
            loginModal.classList.remove('flex');
            loginModal.classList.add('hidden');
            signupModal.classList.remove('hidden');
            signupModal.classList.add('flex');
        }

        function hideSignupModal() {
            const signupModal = document.getElementById('signupModal');
            signupModal.classList.remove('flex');
            signupModal.classList.add('hidden');
        }

        function showLoginForm() {
            const loginModal = document.getElementById('loginModal');
            const signupModal = document.getElementById('signupModal');
            signupModal.classList.remove('flex');
            signupModal.classList.add('hidden');
            loginModal.classList.remove('hidden');
            loginModal.classList.add('flex');
        }

        // Add Place modal functions
        function showAddPlaceModal() {
            const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
            if (isLoggedIn) {
                const modal = document.getElementById('addPlaceModal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            } else {
                showAuthWarningModal();
            }
        }

        function hideAddPlaceModal() {
            const modal = document.getElementById('addPlaceModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }

        // Auth Warning modal functions
        function showAuthWarningModal() {
            const modal = document.getElementById('authWarningModal');
            modal.style.opacity = '0';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.offsetHeight;
            modal.style.opacity = '1';
        }

        function hideAuthWarningModal() {
            const modal = document.getElementById('authWarningModal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }, 200);
        }

        function handleAuthWarningLogin() {
            hideAuthWarningModal();
            setTimeout(() => {
                showLoginModal();
            }, 200);
        }

        // Handle login form submission
        document.getElementById('loginForm').addEventListener('submit', function (e) {
            e.preventDefault();
            // Add your login logic here
            localStorage.setItem('isLoggedIn', 'true');
            localStorage.setItem('userRole', 'admin'); // For testing - remove in production
            hideLoginModal();
        });

        // Handle signup form submission
        document.getElementById('signupForm').addEventListener('submit', function (e) {
            e.preventDefault();
            // Add your signup logic here
            localStorage.setItem('isLoggedIn', 'true');
            hideSignupModal();
        });

        // Handle add place form submission
        document.getElementById('addPlaceForm').addEventListener('submit', function (e) {
            e.preventDefault();
            // Add your form submission logic here
            hideAddPlaceModal();
        });

        // Close modals when clicking outside
        document.addEventListener('click', function (event) {
            const loginModal = document.getElementById('loginModal');
            const signupModal = document.getElementById('signupModal');
            const addPlaceModal = document.getElementById('addPlaceModal');
            const authWarningModal = document.getElementById('authWarningModal');

            if (event.target === loginModal) {
                hideLoginModal();
            }
            if (event.target === signupModal) {
                hideSignupModal();
            }
            if (event.target === addPlaceModal) {
                hideAddPlaceModal();
            }
            if (event.target === authWarningModal) {
                hideAuthWarningModal();
            }
        });

        // Function to toggle between view and edit modes
        function toggleEditMode() {
            const viewSection = document.getElementById('profileView');
            const editSection = document.getElementById('profileEdit');
            const largeProfilePicture = document.getElementById('largeProfilePicture');

            if (viewSection.classList.contains('hidden')) {
                // Switch to view mode
                viewSection.classList.remove('hidden');
                editSection.classList.add('hidden');
                largeProfilePicture.classList.remove('hidden');
            } else {
                // Switch to edit mode
                viewSection.classList.add('hidden');
                editSection.classList.remove('hidden');
                largeProfilePicture.classList.add('hidden');

                // Populate edit form with current values
                document.getElementById('editFullName').value = document.getElementById('viewFullName').textContent;
                document.getElementById('editEmail').value = document.getElementById('viewEmail').textContent;
            }
        }

        // Function to preview profile picture
        function previewProfilePicture(input) {
            const preview = document.getElementById('editProfilePicture');
            const placeholder = document.getElementById('editProfilePicturePlaceholder');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    if (placeholder) {
                        placeholder.classList.add('hidden');
                    }
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                // If no file is selected, show placeholder if it exists
                preview.src = '';
                preview.classList.add('hidden');
                 if (placeholder) {
                    placeholder.classList.remove('hidden');
                }
            }
        }

        // Handle profile form submission
        document.getElementById('editProfileForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Profile updated successfully!');
            // Update view mode with new values
                    document.getElementById('viewFullName').textContent = data.user.full_name || data.user.username;
                    document.getElementById('viewEmail').textContent = data.user.email;
                    // Assuming we show username in view mode based on previous change, not role here
                    document.getElementById('viewUsername').textContent = data.user.username;
                    
                    // Update profile picture in view mode if changed
                    const viewProfilePicture = document.getElementById('profilePicture');
                    const viewProfilePictureParent = viewProfilePicture.parentElement;
                    const existingViewPlaceholder = viewProfilePictureParent.querySelector('#profilePicture.bg-gray-200');

                    if (data.user.profile_picture) {
                         const newImageUrl = 'uploads/profile_pictures/' + data.user.profile_picture;
                         if (viewProfilePicture.tagName === 'DIV') {
                             // If current view is a placeholder, replace it with img
                             const imgElement = document.createElement('img');
                             imgElement.id = 'profilePicture';
                             imgElement.alt = 'Profile Picture';
                             imgElement.className = 'w-full aspect-square rounded-lg object-cover border-4 border-white shadow-lg';
                             imgElement.src = newImageUrl;
                             viewProfilePictureParent.replaceChild(imgElement, viewProfilePicture);
                         } else {
                             // If current view is an image, update src
                            viewProfilePicture.src = newImageUrl;
                         }
                         if (existingViewPlaceholder) existingViewPlaceholder.classList.add('hidden'); // Hide existing placeholder if it exists

                    } else { // No profile picture after update
                         if (viewProfilePicture.tagName === 'IMG') {
                         // If a picture was removed, replace img with placeholder
                            const placeholderElement = document.createElement('div');
                            placeholderElement.id = 'profilePicture'; // Keep the same ID
                            placeholderElement.className = 'w-full aspect-square rounded-lg bg-gray-200 flex items-center justify-center text-gray-600 font-medium text-9xl border-4 border-white shadow-lg';
                             // Note: Initials for placeholder are handled by PHP on page load in the initial HTML rendering.
                             // The placeholder element should already exist with the correct initials.
                            viewProfilePictureParent.replaceChild(placeholderElement, viewProfilePicture);

                         } else if (existingViewPlaceholder) {
                             // If it was already a placeholder, make sure it's visible
                              existingViewPlaceholder.classList.remove('hidden');
                         }
            }

            // Switch back to view mode
            toggleEditMode();

            // Clear password field
            document.getElementById('editPassword').value = '';
                } else {
                    alert(data.message || 'Error updating profile');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating profile. Please try again.');
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const editForm = document.getElementById('editProfileForm');
            const profilePictureInput = document.getElementById('profilePicture');
            const profilePicturePreview = document.getElementById('profilePicturePreview');
            const currentProfilePicture = document.getElementById('currentProfilePicture');

            // Handle profile picture preview
            profilePictureInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profilePicturePreview.src = e.target.result;
                        profilePicturePreview.classList.remove('hidden');
                        currentProfilePicture.classList.add('hidden');
                    }
                    reader.readAsDataURL(file);
                }
            });

            // Handle form submission
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('update_profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update profile information in the UI
                        document.getElementById('userFullName').textContent = formData.get('full_name');
                        document.getElementById('userEmail').textContent = formData.get('email');
                        
                        // Update profile picture if changed
                        if (data.profile_picture) {
                            const profilePic = document.querySelector('.profile-pic');
                            profilePic.src = 'uploads/profile_pictures/' + data.profile_picture;
                        }

                        // Show success message
                        alert('Profile updated successfully!');
                        
                        // Hide edit form
                        document.getElementById('editProfileForm').classList.add('hidden');
                        document.getElementById('profileInfo').classList.remove('hidden');
                    } else {
                        alert(data.message || 'Error updating profile');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the profile');
                });
            });

            // Handle edit button click
            document.getElementById('editProfileBtn').addEventListener('click', function() {
                document.getElementById('profileInfo').classList.add('hidden');
                document.getElementById('editProfileForm').classList.remove('hidden');
            });

            // Handle cancel button click
            document.getElementById('cancelEditBtn').addEventListener('click', function() {
                document.getElementById('editProfileForm').classList.add('hidden');
                document.getElementById('profileInfo').classList.remove('hidden');
                // Reset form
                editForm.reset();
                // Reset profile picture preview
                profilePicturePreview.classList.add('hidden');
                currentProfilePicture.classList.remove('hidden');
            });
        });
    </script>
</body>

</html>