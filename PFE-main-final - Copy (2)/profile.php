<?php
session_start();
require_once 'config/database.php';

// Initialize user variable
$user = null;
$user_initials = ''; // Initialize user_initials variable

// Get user information if logged in
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        // Generate user initials if user exists
        if ($user) {
            $name_parts = explode(' ', $user['username']);
            foreach ($name_parts as $part) {
                $user_initials .= strtoupper(substr($part, 0, 1));
            }
            $user_initials = substr($user_initials, 0, 2);
        }
    } catch (PDOException $e) {
        error_log('Error fetching user data: ' . $e->getMessage());
    }
}

// Add this function before any calls to it
function getCategoryInfo($categoryName)
{
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
} catch (PDOException $e) {
    error_log('Error fetching categories: ' . $e->getMessage());
    $categories_map = [];
}

// Fetch wilayas (might be needed for wilaya names in cards)
try {
    $wilayas_query = "SELECT * FROM wilayas ORDER BY wilaya_number";
    $wilayas_stmt = $pdo->query($wilayas_query);
    $wilayas_map = array_column($wilayas_stmt->fetchAll(PDO::FETCH_ASSOC), null, 'wilaya_number');
} catch (PDOException $e) {
    error_log('Error fetching wilayas: ' . $e->getMessage());
    $wilayas_map = [];
}

// Fetch saved places for the logged-in user
$saved_lieux = [];
if ($user) {
    try {
        // Fetch distinct saved lieu IDs for the user
        $saved_lieu_ids_query = "SELECT DISTINCT lieu_id FROM lieu_saves WHERE user_id = ?";
        $saved_lieu_ids_stmt = $pdo->prepare($saved_lieu_ids_query);
        $saved_lieu_ids_stmt->execute([$user['user_id']]);
        $saved_lieu_ids = $saved_lieu_ids_stmt->fetchAll(PDO::FETCH_COLUMN);

        // If there are saved lieu IDs, fetch the details for those lieux
        if (!empty($saved_lieu_ids)) {
            $placeholders = implode(',', array_fill(0, count($saved_lieu_ids), '?'));
            $saved_lieux_query = "SELECT l.*, c.category_name, w.wilaya_name,
                                GROUP_CONCAT(li.image_url) as images,
                                u.username as author_name,
                                (SELECT AVG(rating) FROM lieu_ratings WHERE lieu_id = l.lieu_id) as average_rating,
                                (SELECT COUNT(*) FROM lieu_ratings WHERE lieu_id = l.lieu_id) as total_ratings
                              FROM lieu l
                              LEFT JOIN categories c ON l.category_id = c.category_id
                              LEFT JOIN wilayas w ON l.wilaya_id = w.wilaya_number
                              LEFT JOIN lieu_images li ON l.lieu_id = li.lieu_id
                              LEFT JOIN users u ON l.user_id = u.user_id
                                 WHERE l.lieu_id IN ($placeholders)
                                 GROUP BY l.lieu_id
                                 ORDER BY l.created_at DESC";

            $saved_lieux_stmt = $pdo->prepare($saved_lieux_query);
            $saved_lieux_stmt->execute($saved_lieu_ids);
            $saved_lieux = $saved_lieux_stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $saved_lieux = []; // No saved places
        }

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

    } catch (PDOException $e) {
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
    <style>
        /* Add any specific styles needed for profile page cards if different from index */
        /* For example, if the .image-gallery height or card padding needs adjustment */
        .image-gallery {
            height: 288px;
            /* Example: Match h-72 from index.php */
        }

        .card-content {
            padding: 1rem;
            /* Example: Match p-4 from index.php */
        }

        .saved-places-grid {
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            /* Adjust minmax as needed */
        }
    </style>
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
                        <div class="relative group">
                            <button class="flex items-center gap-2 hover:bg-gray-100 p-2 rounded-full transition-colors">
                                <?php if ($user['profile_picture']): ?>
                                    <img src="uploads/profile_pictures/<?php echo htmlspecialchars($user['profile_picture']); ?>"
                                        alt="Profile" class="w-10 h-10 rounded-full object-cover">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                                        <span class="text-gray-600 font-medium text-lg">
                                            <?php echo $user_initials; ?>
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
                                <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                                    <a href="demandes.php" class="block px-4 py-2 hover:bg-gray-100">
                                        <i class="fas fa-list mr-2"></i> Demands
                                    </a>
                                <?php endif; ?>
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
                                <div id="profilePicture"
                                    class="w-full aspect-square rounded-lg bg-gray-200 flex items-center justify-center text-gray-600 font-medium text-9xl border-4 border-white shadow-lg">
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
                                <h2 id="displayName" class="text-3xl font-bold text-gray-800 mb-2">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </h2>
                                <p class="text-gray-600">Member since
                                    <?php echo date('Y-m-d', strtotime($user['created_at'])); ?>
                                </p>
                            </div>

                            <!-- Profile Information -->
                            <div class="bg-gray-100 rounded-lg p-6 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600">Full Name</label>
                                        <p id="viewFullName" class="mt-1 text-gray-800">
                                            <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600">Email</label>
                                        <p id="viewEmail" class="mt-1 text-gray-800">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600">Role</label>
                                        <p id="viewUsername" class="mt-1 text-gray-800">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </p>
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
                            <form id="editProfileForm" class="space-y-6" enctype="multipart/form-data">
                                <!-- Profile Picture Upload -->
                                <div class="flex items-center space-x-6">
                                    <div class="relative w-32 h-32">
                                        <img id="editProfilePicture"
                                            src="<?php echo $user && $user['profile_picture'] ? 'uploads/profile_pictures/' . htmlspecialchars($user['profile_picture']) : ''; ?>"
                                            alt="Profile Picture"
                                            class="w-full h-full rounded-full object-cover border-4 border-white shadow-lg <?php echo $user && $user['profile_picture'] ? '' : 'hidden'; ?>">
                                        <label for="profilePictureInput"
                                            class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 rounded-full cursor-pointer opacity-0 hover:opacity-100 transition-opacity <?php echo $user && $user['profile_picture'] ? '' : '' ?>">
                                            <i class="fas fa-camera text-white text-2xl"></i>
                                        </label>
                                        <input type="file" id="profilePictureInput" name="profile_picture"
                                            accept="image/*" class="hidden" onchange="previewProfilePicture(this)">
                                        <?php if (!$user || !$user['profile_picture']): ?>
                                            <div id="editProfilePicturePlaceholder"
                                                class="w-full h-full rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-medium text-4xl border-4 border-white shadow-lg">
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
                                            <input type="text" name="username" id="editFullName"
                                                value="<?php echo htmlspecialchars($user['username']); ?>"
                                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-600">Email</label>
                                            <input type="email" name="email" id="editEmail"
                                                value="<?php echo htmlspecialchars($user['email']); ?>"
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
                <div class="grid saved-places-grid gap-6" id="savedPlacesContainer">
                    <!-- Saved place cards will be loaded here by JavaScript -->
                </div>
                <?php if (empty($saved_lieux)): ?>
                    <p class="text-gray-600 text-center" id="noSavedPlacesMessage">You haven't saved any places yet.</p>
                <?php endif; ?>
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
                        <input type="email" class="w-full px-3 py-2 border rounded-full"
                            placeholder="Entrez votre email">
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
                        <input type="email" class="w-full px-3 py-2 border rounded-full"
                            placeholder="Entrez votre email">
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
                        <a href="#" onclick="showLoginForm()" class="text-blue-500 hover:text-blue-700 underline">Déjà
                            un
                            compte ? Se connecter</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Place Modal -->
        <div id="addPlaceModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
            <div
                class="bg-white p-8 rounded-lg w-[90%] max-w-[600px] max-h-[90vh] shadow-xl border-[6px] border-[#327532] overflow-hidden flex flex-col">
                <div class="flex justify-between items-center mb-4 bg-white sticky top-0 z-10 pb-4 border-b">
                    <h2 class="text-2xl font-bold">Ajouter un lieu</h2>
                    <button onclick="hideAddPlaceModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="overflow-y-auto flex-1">
                    <form id="addPlaceForm" class="space-y-4" method="POST" enctype="multipart/form-data">
                        <div>

                            <div class="relative h-[192px]">
                                <input type="file" name="images[]" id="imageInput"
                                    class="w-full h-full px-3 py-2 border-4 border-dashed border-[#7d7d7d] rounded-lg bg-[#dcdcdc] cursor-pointer opacity-0 absolute inset-0 z-10"
                                    multiple accept="image/*" required>
                                <label for="imageInput"
                                    class="w-full h-full border-4 border-dashed border-[#7d7d7d] rounded-lg bg-[#dcdcdc] flex flex-col items-center justify-center cursor-pointer absolute inset-0">
                                    <i class="fas fa-images text-4xl text-[#7d7d7d] mb-2"></i>
                                    <i class="fas fa-plus-circle text-2xl text-[#7d7d7d]"></i>
                                    <span class="text-[#7d7d7d] mt-2">Click to upload images</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Nom du lieu :</label>
                            <input type="text" name="title"
                                class="w-full px-3 py-2 border rounded-full outline-none border-[#a1a1a1]" required
                                placeholder="Entrez le nom du lieu">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Description :</label>
                            <textarea name="description" class="w-full px-3 py-2 border outline-none border-[#a1a1a1]"
                                rows="3" placeholder="Entrez la description du lieu" required></textarea>
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Location :</label>
                            <textarea name="location" class="w-full px-3 py-2 border outline-none border-[#a1a1a1]"
                                rows="3" placeholder="Entrez la location du lieu" required></textarea>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Wilaya :</label>
                            <select name="wilaya"
                                class="w-full px-3 py-2 border rounded-full outline-none border-[#a1a1a1]" required>
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
                    <button onclick="showLoginModal()"
                        class="px-4 py-2 bg-blue-500 text-white rounded-full hover:bg-blue-600 transition-colors cursor-pointer">
                        Log In
                    </button>
                </div>
            </div>
        </div>

        <script src="script.js"></script>
        <script src="search.js"></script>
        <script>
            // Embed saved places data from PHP
            const savedLieuxData = <?php echo json_encode($saved_lieux); ?>;

            // Function to get category styling information (Copy from index.php)
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
                    'Beaches': {
                        icon: 'fas fa-umbrella-beach',
                        bgColor: 'bg-[#FFF7E6]',
                        textColor: 'text-[#FFA940]'
                    },
                    'Desert': {
                        icon: 'fas fa-sun',
                        bgColor: 'bg-[#FFF1F0]',
                        textColor: 'text-[#FF4D4F]'
                    },
                    'Museums': {
                        icon: 'fas fa-museum',
                        bgColor: 'bg-[#F0F5FF]',
                        textColor: 'text-[#2F54EB]'
                    },
                    'Shopping': {
                        icon: 'fas fa-shopping-bag',
                        bgColor: 'bg-[#FFF0F6]',
                        textColor: 'text-[#EB2F96]'
                    },
                    'Nature': {
                        icon: 'fas fa-tree',
                        bgColor: 'bg-[#F6FFED]',
                        textColor: 'text-[#52C41A]'
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

                return categories[categoryName] ?? {
                    icon: 'fas fa-map-marker-alt',
                    bgColor: 'bg-gray-100',
                    textColor: 'text-gray-600'
                };
            }

            // Function to initialize slideshow (Copy from index.php)
            function initializeSlideshow(container) {
                const images = container.querySelectorAll('img');
                const dotsContainer = container.parentElement.querySelector('.dots-container');
                let currentIndex = 0;

                if (!dotsContainer) { // Check if dots container exists
                    return; // Exit if no dots container (e.g., only one image)
                }

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

                if (prevButton) {
                    prevButton.onclick = (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        goToSlide(currentIndex - 1);
                    };
                }
                if (nextButton) {
                    nextButton.onclick = (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        goToSlide(currentIndex + 1);
                    };
                }

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

                // Add auto-advance functionality (optional, can remove if not desired)
                let slideInterval = setInterval(() => goToSlide(currentIndex + 1), 5000);

                // Pause auto-advance on hover
                container.addEventListener('mouseenter', () => clearInterval(slideInterval));
                container.addEventListener('mouseleave', () => {
                    slideInterval = setInterval(() => goToSlide(currentIndex + 1), 5000);
                });
            }

            // Function to toggle save status (Copy from index.php)
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
                            // const icon = button.querySelector('i'); // icon is already defined above
                            if (data.action === 'saved') {
                                // Add a class to the icon when saved
                                icon.classList.remove('far');
                                icon.classList.add('fas');
                                // icon.classList.remove('text-gray-600'); // Removed based on previous request
                                // icon.classList.add('text-white');     // Removed based on previous request
                                icon.classList.add('text-gray-600'); // Keep or add dark gray color for saved
                            } else {
                                // Remove the class and revert color when unsaved
                                icon.classList.remove('fas');
                                icon.classList.add('far');
                                // icon.classList.remove('text-white'); // Removed based on previous request
                                // icon.classList.add('text-gray-600'); // Removed based on previous request
                                icon.classList.add('text-gray-600'); // Ensure dark gray color for unsaved
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

            // Function to create a lieu card element (Copy from index.php and adapt for profile page)
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
                const imageGalleryHtml = lieu.images && lieu.images.length > 0 ?
                    lieu.images.map((image, index) => `
                 <img src="${image}" alt="${lieu.title}" 
                      class="w-full h-full object-cover transition-opacity duration-300 hover:opacity-80"
                      style="display: ${index === 0 ? 'block' : 'none'}">
             `).join('') :
                    // Default image if no images
                    '<img src="images and videos/default-place.jpg" alt="Default Image" class="w-full h-full object-cover transition-opacity duration-300 hover:opacity-80" style="display: block;">';

                // Determine if the place is saved by the current user (always true on this page, but structure from index)
                const isSaved = 'fas'; // It's a saved places page, so icon is always filled

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
                             <!-- Navigation Arrows -->
                             <button class="nav-arrow prev absolute left-2 top-1/2 transform -translate-y-1/2 bg-white p-1 rounded-full shadow opacity-75 hover:opacity-100 transition-opacity z-20" onclick="event.preventDefault(); event.stopPropagation();"><i class="fas fa-chevron-left text-gray-800"></i></button>
                             <button class="nav-arrow next absolute right-2 top-1/2 transform -translate-y-1/2 bg-white p-1 rounded-full shadow opacity-75 hover:opacity-100 transition-opacity z-20" onclick="event.preventDefault(); event.stopPropagation();"><i class="fas fa-chevron-right text-gray-800"></i></button>
                             <!-- Dots for pagination -->
                             <div class="absolute bottom-2 left-0 right-0 flex justify-center space-x-1 z-20 dots-container"></div>
                             
                             <!-- Save button (Always saved on this page, but keeping structure) -->
                             <button onclick="event.preventDefault(); toggleSave(${lieu.lieu_id}, this)"
                                 class="absolute top-4 right-4 bg-white p-2 rounded-full shadow-lg hover:bg-gray-100 transition-colors z-30 w-10">
                                 <i class="fas fa-bookmark text-gray-600"></i>
                             </button>
                         </div>
                     </div>
                     <div class="p-4">
                         <h2 class="text-xl font-semibold mb-2">${lieu.title}</h2>
                         <p class="text-gray-600 text-sm mb-2">${lieu.content ? lieu.content.substring(0, 100) + (lieu.content.length > 100 ? '...' : '') : ''}</p>

                         <div class="inline-block px-3 py-1 rounded-full text-sm font-semibold mb-2 ${categoryInfo.bgColor} ${categoryInfo.textColor}">
                             <i class="${categoryInfo.icon} mr-1"></i>
                             ${lieu.category_name}
                         </div>
                         
                         <p class="text-gray-700 text-sm mb-4">Par <span class="font-medium">${lieu.author_name}</span></p>

                         <div class="flex items-center">
                             <div class="text-yellow-400 mr-1">
                                 ${starsHtml}
                             </div>
                             <span class="text-sm text-gray-600">(${lieu.total_ratings || 0})</span>
                         </div>
                     </div>
                 </div>
             `;

                // Initialize slideshow after adding to DOM
                setTimeout(() => {
                    const gallery = card.querySelector('.image-gallery');
                    if (gallery && lieu.images && lieu.images.length > 1) { // Only initialize if there are multiple images
                        initializeSlideshow(gallery);
                    } else if (gallery) { // If only one image or no images, hide arrows and dots
                        const prevButton = gallery.parentElement.querySelector('.prev');
                        const nextButton = gallery.parentElement.querySelector('.next');
                        const dotsContainer = gallery.parentElement.querySelector('.dots-container');
                        if (prevButton) prevButton.style.display = 'none';
                        if (nextButton) nextButton.style.display = 'none';
                        if (dotsContainer) dotsContainer.style.display = 'none';
                    }
                }, 0);

                return card;
            }

            // Function to display saved lieux
            function displaySavedLieux(lieux) {
                const container = document.getElementById('savedPlacesContainer');
                const noSavedPlacesMessage = document.getElementById('noSavedPlacesMessage');

                if (!container) {
                    console.error('Saved places container not found.');
                    return;
                }

                container.innerHTML = ''; // Clear existing content

                if (lieux && lieux.length > 0) {
                    if (noSavedPlacesMessage) noSavedPlacesMessage.classList.add('hidden'); // Hide message if places exist
                    lieux.forEach(lieu => {
                        const card = createLieuCard(lieu);
                        container.appendChild(card);
                    });
                } else {
                    if (noSavedPlacesMessage) noSavedPlacesMessage.classList.remove('hidden'); // Show message if no places
                }
            }

            // Modal functions (Ensure these are present or copied) - Adding necessary ones for card interactions
            function showLoginModal() { /* ... */ console.log('showLoginModal called'); } // Added console log for debugging
            function hideLoginModal() { /* ... */ console.log('hideLoginModal called'); }
            function showSignupForm() { /* ... */ console.log('showSignupForm called'); }
            function hideSignupModal() { /* ... */ console.log('hideSignupModal called'); }
            function showLoginForm() { /* ... */ console.log('showLoginForm called'); }
            function showAddPlaceModal() { /* ... */ console.log('showAddPlaceModal called'); }
            function hideAddPlaceModal() { /* ... */ console.log('hideAddPlaceModal called'); }
            function showAuthWarningModal() { /* ... */ console.log('showAuthWarningModal called'); }
            function hideAuthWarningModal() { /* ... */ console.log('hideAuthWarningModal called'); }
            function showLieuOptions(lieuId) { console.log('showLieuOptions called for lieu:', lieuId); /* ... */ }
            function deleteLieu(lieuId) { console.log('deleteLieu called for lieu:', lieuId); /* ... */ }
            function showModifyLieuModal(lieuId) { console.log('showModifyLieuModal called for lieu:', lieuId); /* ... */ }
            // Add other modal functions as needed (e.g., showModifyLieuModal, hideModifyLieuModal)

            // Helper functions (Ensure these are present or copied)
            function previewProfilePicture(input) {
                const preview = document.getElementById('editProfilePicture');
                const placeholder = document.getElementById('editProfilePicturePlaceholder');

                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        if (preview) {
                            preview.src = e.target.result;
                            preview.classList.remove('hidden');
                        }
                        if (placeholder) {
                            placeholder.classList.add('hidden');
                        }
                    }
                    reader.readAsDataURL(input.files[0]);
                }
            }

            // Function to toggle edit mode
            function toggleEditMode() {
                const profileView = document.getElementById('profileView');
                const profileEdit = document.getElementById('profileEdit');

                if (profileView && profileEdit) {
                    profileView.classList.toggle('hidden');
                    profileEdit.classList.toggle('hidden');
                }
            }

            // Handle profile form submission
            document.getElementById('editProfileForm').addEventListener('submit', async function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('update_profile', '1');

                try {
                    const response = await fetch('update_profile.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('Profile updated successfully!');
                        // Reload the page to show updated information
                        window.location.reload();
                    } else {
                        alert(result.message || 'Error updating profile');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error updating profile. Please try again.');
                }
            });

            // Event listener to display saved places when the DOM is ready
            document.addEventListener('DOMContentLoaded', function () {
                displaySavedLieux(savedLieuxData);
                // Initialize existing slideshows if any were rendered by PHP initially (though we removed the loop)
                // document.querySelectorAll('.image-gallery').forEach(initializeSlideshow);
            });
        </script>
</body>

</html>