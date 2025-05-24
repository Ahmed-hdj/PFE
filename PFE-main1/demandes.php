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

// Check if user is admin
if (!isset($user['role']) || $user['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Fetch pending places
try {
    $stmt = $pdo->prepare("
        SELECT l.*, c.category_name, u.username as author_name,
        u.profile_picture as author_profile_picture,
        COALESCE(GROUP_CONCAT(li.image_url), 'images and videos/default-place.jpg') as images,
        COALESCE(AVG(lr.rating), 0) as average_rating,
        COUNT(DISTINCT lr.rating_id) as total_ratings
        FROM lieu l
        JOIN categories c ON l.category_id = c.category_id
        JOIN users u ON l.user_id = u.user_id
        LEFT JOIN lieu_images li ON l.lieu_id = li.lieu_id
        LEFT JOIN lieu_ratings lr ON l.lieu_id = lr.lieu_id
        WHERE l.status = 'pending'
        GROUP BY l.lieu_id
        ORDER BY l.created_at DESC
    ");
    $stmt->execute();
    $pending_places = $stmt->fetchAll();
} catch(PDOException $e) {
    $pending_places = [];
    $error_message = "Error: " . $e->getMessage();
}

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
    <title>Pending Demands - Algeria Tourism</title>
    <style>
        body {
            background-color: #f3f4f6;
            font-family: 'Open Sans', sans-serif;
        }

        .image-gallery {
            position: relative;
            overflow: hidden;
        }

        .image-gallery img {
            transition: opacity 0.3s ease;
        }

        .sliding {
            transition: transform 0.3s ease;
        }
    </style>
</head>

<body>
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

    <div class="container mx-auto px-4 py-8">
        <div class="text-center mb-8">
            <h1 class="text-5xl font-extrabold text-center text-blue-600 drop-shadow-md">
                <i class="fas fa-list mr-3"></i>
                Pending Demands
            </h1>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($pending_places)): ?>
                <div class="col-span-full text-center py-8">
                    <p class="text-gray-600 text-lg">No pending demands at the moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pending_places as $place): ?>
                    <div class="bg-white rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300">
                        <div class="relative">
                            <div class="relative h-72 image-gallery" data-gallery="<?php echo $place['lieu_id']; ?>">
                                <?php
                                $images = explode(',', $place['images']);
                                foreach ($images as $index => $image):
                                ?>
                                    <img src="<?php echo htmlspecialchars($image); ?>" 
                                         alt="<?php echo htmlspecialchars($place['title']); ?>"
                                         class="w-full h-full object-cover transition-opacity duration-300 hover:opacity-80"
                                         style="display: <?php echo $index === 0 ? 'block' : 'none'; ?>">
                                <?php endforeach; ?>
                                
                                <div class="absolute top-4 right-4 z-30 flex gap-2">
                                    <button onclick="approveLieu(<?php echo $place['lieu_id']; ?>)"
                                        class="bg-green-500 text-white p-2 rounded-full shadow-lg hover:bg-green-600 transition-colors">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button onclick="rejectLieu(<?php echo $place['lieu_id']; ?>)"
                                        class="bg-red-500 text-white p-2 rounded-full shadow-lg hover:bg-red-600 transition-colors">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($place['title']); ?></h3>
                            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars(substr($place['content'], 0, 150)) . '...'; ?></p>
                            
                            <?php
                                $currentCategoryInfo = $categoryInfo[$place['category_name']] ?? ['bgColor' => 'bg-gray-200', 'textColor' => 'text-gray-800', 'icon' => 'fas fa-tag'];
                            ?>
                            <div class="inline-block px-3 py-1 rounded-full text-sm font-semibold mb-2 <?php echo $currentCategoryInfo['bgColor']; ?> <?php echo $currentCategoryInfo['textColor']; ?>">
                                <i class="<?php echo $currentCategoryInfo['icon']; ?> mr-1"></i>
                                <?php echo htmlspecialchars($place['category_name']); ?>
                            </div>
                            
                            <div class="mt-4 text-gray-600">
                                <span class="font-medium"><?php echo htmlspecialchars($place['author_name']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
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

        function approveLieu(lieuId) {
            if (confirm('Are you sure you want to approve this place?')) {
                fetch('update_lieu_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        lieu_id: lieuId,
                        status: 'approved'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the card from the DOM
                        const card = document.querySelector(`[data-gallery="${lieuId}"]`).closest('.bg-white');
                        card.remove();
                        alert('Place approved successfully');
                    } else {
                        alert(data.message || 'Error approving place');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error approving place. Please try again.');
                });
            }
        }

        function rejectLieu(lieuId) {
            if (confirm('Are you sure you want to reject this place?')) {
                fetch('update_lieu_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        lieu_id: lieuId,
                        status: 'rejected'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the card from the DOM
                        const card = document.querySelector(`[data-gallery="${lieuId}"]`).closest('.bg-white');
                        card.remove();
                        alert('Place rejected successfully');
                    } else {
                        alert(data.message || 'Error rejecting place');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error rejecting place. Please try again.');
                });
            }
        }

        // Initialize image galleries
        document.addEventListener('DOMContentLoaded', function() {
            const galleries = document.querySelectorAll('.image-gallery');
            galleries.forEach(gallery => {
                const images = gallery.querySelectorAll('img');
                let currentIndex = 0;

                function showImage(index) {
                    images.forEach(img => img.style.display = 'none');
                    images[index].style.display = 'block';
                }

                // Auto advance slides
                setInterval(() => {
                    currentIndex = (currentIndex + 1) % images.length;
                    showImage(currentIndex);
                }, 5000);
            });
        });
    </script>
</body>

</html>