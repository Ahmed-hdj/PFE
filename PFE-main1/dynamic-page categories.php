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
    } catch (PDOException $e) {
        error_log('Error fetching user data: ' . $e->getMessage());
    }
}

// Get category ID from URL
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Fetch category name from database
try {
    $stmt = $pdo->prepare("SELECT category_name FROM categories WHERE category_id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();
    $category_name = $category ? $category['category_name'] : 'Unknown Category';
} catch (PDOException $e) {
    $category_name = 'Error loading category name';
}

// Fetch places from the selected category
try {
    $stmt = $pdo->prepare("
        SELECT l.*, c.category_name, u.username as author_name,
        u.profile_picture as author_profile_picture,
        COALESCE(GROUP_CONCAT(li.image_url), 'images and videos/default-place.jpg') as images,
        COALESCE(AVG(lr.rating), 0) as average_rating,
        COUNT(DISTINCT lr.rating_id) as total_ratings,
        CASE WHEN ls.lieu_id IS NOT NULL THEN 1 ELSE 0 END as is_saved
        FROM lieu l
        JOIN categories c ON l.category_id = c.category_id
        JOIN users u ON l.user_id = u.user_id
        LEFT JOIN lieu_images li ON l.lieu_id = li.lieu_id
        LEFT JOIN lieu_ratings lr ON l.lieu_id = lr.lieu_id
        LEFT JOIN lieu_saves ls ON l.lieu_id = ls.lieu_id AND ls.user_id = ?
        WHERE l.category_id = ? AND l.status = 'approved'
        GROUP BY l.lieu_id
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'] ?? 0, $category_id]);
    $places = $stmt->fetchAll();
} catch (PDOException $e) {
    $places = [];
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
    <title><?php echo htmlspecialchars($category_name); ?> - Places to Visit</title>
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

        <div class="container mx-auto px-4 py-8">
            <div class="text-center mb-8">
                <?php
                $currentCategoryInfo = $categoryInfo[$category_name] ?? ['bgColor' => 'bg-gray-200', 'textColor' => 'text-gray-800', 'icon' => 'fas fa-tag'];
                ?>
                <h1
                    class="text-5xl font-extrabold text-center <?php echo $currentCategoryInfo['textColor']; ?> drop-shadow-md">
                    <i class="<?php echo $currentCategoryInfo['icon']; ?> mr-3"></i>
                    <?php echo htmlspecialchars($category_name); ?>
                </h1>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($places as $place): ?>
                    <a href="card-details.php?id=<?php echo $place['lieu_id']; ?>" class="block cursor-pointer group">
                        <div
                            class="bg-white rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 h-full">
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

                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                        <div class="absolute top-4 left-4 z-30">
                                            <button
                                                onclick="event.preventDefault(); showLieuOptions(<?php echo $place['lieu_id']; ?>)"
                                                class="bg-white p-2 rounded-full shadow-lg hover:bg-gray-100 transition-colors">
                                                <i class="fas fa-ellipsis-v text-gray-600"></i>
                                            </button>
                                            <div id="lieuOptions<?php echo $place['lieu_id']; ?>"
                                                class="hidden absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-40">
                                                <button
                                                    onclick="event.preventDefault(); showModifyLieuModal(<?php echo $place['lieu_id']; ?>)"
                                                    class="w-full text-left px-4 py-2 hover:bg-gray-100">
                                                    <i class="fas fa-edit mr-2"></i> Modify
                                                </button>
                                                <button
                                                    onclick="event.preventDefault(); deleteLieu(<?php echo $place['lieu_id']; ?>)"
                                                    class="w-full text-left px-4 py-2 text-red-600 hover:bg-gray-100">
                                                    <i class="fas fa-trash mr-2"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <!-- Navigation Arrows -->
                                    <button
                                        class="nav-arrow prev absolute left-4 top-1/2 -translate-y-1/2 bg-white p-3 rounded-full shadow-lg hover:bg-gray-100 transition-all z-30 cursor-pointer">
                                        <i class="fas fa-chevron-left text-gray-800 text-xl"></i>
                                    </button>
                                    <button
                                        class="nav-arrow next absolute right-4 top-1/2 -translate-y-1/2 bg-white p-3 rounded-full shadow-lg hover:bg-gray-100 transition-all z-30 cursor-pointer">
                                        <i class="fas fa-chevron-right text-gray-800 text-xl"></i>
                                    </button>
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <button
                                            onclick="event.preventDefault(); toggleSave(<?php echo $place['lieu_id']; ?>, this)"
                                            class="absolute top-4 right-4 bg-white p-2 rounded-full shadow-lg hover:bg-gray-100 transition-colors z-30 w-10 flex items-center justify-center">
                                            <i
                                                class="fa-bookmark <?php echo $place['is_saved'] ? 'fas text-red-500' : 'far text-gray-600'; ?>"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="p-4">
                                <h2 class="text-xl font-semibold mb-2"><?php echo htmlspecialchars($place['title']); ?></h2>
                                <p class="text-gray-600 text-sm mb-2">
                                    <?php echo htmlspecialchars(substr($place['content'], 0, 100)) . '...'; ?>
                                </p>

                                <?php
                                $currentCategoryInfo = $categoryInfo[$place['category_name']] ?? ['bgColor' => 'bg-gray-200', 'textColor' => 'text-gray-800', 'icon' => 'fas fa-tag'];
                                ?>
                                <div
                                    class="inline-block px-3 py-1 rounded-full text-sm font-semibold mb-2 <?php echo $currentCategoryInfo['bgColor']; ?> <?php echo $currentCategoryInfo['textColor']; ?>">
                                    <i class="<?php echo $currentCategoryInfo['icon']; ?> mr-1"></i>
                                    <?php echo htmlspecialchars($place['category_name']); ?>
                                </div>

                                <p class="text-gray-700 text-sm mb-4">Par <span
                                        class="font-medium"><?php echo htmlspecialchars($place['author_name']); ?></span>
                                </p>

                                <div class="flex items-center">
                                    <div class="flex items-center">
                                        <div class="text-yellow-400 mr-1">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i
                                                    class="fas fa-star <?php echo $i <= round($place['average_rating']) ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="text-sm text-gray-600">(<?php echo $place['total_ratings']; ?>)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <script>
            // Initialize slideshows
            document.addEventListener('DOMContentLoaded', function () {
                const galleries = document.querySelectorAll('.image-gallery');
                galleries.forEach(gallery => {
                    initializeSlideshow(gallery);
                });
            });

            function initializeSlideshow(container) {
                const images = container.querySelectorAll('img');
                const prevArrow = container.querySelector('.nav-arrow.prev');
                const nextArrow = container.querySelector('.nav-arrow.next');
                let currentIndex = 0;
                let interval;

                function goToSlide(index) {
                    images.forEach(img => img.style.display = 'none');
                    if (images[index]) {
                        images[index].style.display = 'block';
                    }
                    currentIndex = index;
                }

                // Auto advance slides
                function startSlideshow() {
                    interval = setInterval(() => {
                        currentIndex = (currentIndex + 1) % images.length;
                        goToSlide(currentIndex);
                    }, 5000);
                }

                // Pause on hover
                container.addEventListener('mouseenter', () => clearInterval(interval));
                container.addEventListener('mouseleave', startSlideshow);

                // Start the slideshow
                startSlideshow();

                // Navigation with arrows
                if (prevArrow) {
                    prevArrow.addEventListener('click', (event) => {
                        event.preventDefault();
                        currentIndex = (currentIndex - 1 + images.length) % images.length;
                        goToSlide(currentIndex);
                    });
                }

                if (nextArrow) {
                    nextArrow.addEventListener('click', (event) => {
                        event.preventDefault();
                        currentIndex = (currentIndex + 1) % images.length;
                        goToSlide(currentIndex);
                    });
                }
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

            function checkAuthAndShowAddPlaceForm() {
                <?php if (isset($_SESSION['user_id'])): ?>
                    showAddPlaceModal();
                <?php else: ?>
                    showAuthWarningModal();
                <?php endif; ?>
            }

            function showLoginModal() {
                // Implement login modal functionality
                window.location.href = 'index.php';
            }

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
                        console.error('Error fetching lieu details:', error);
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

            // Dummy functions for modals - replace with actual implementations
            function showAddPlaceModal() { console.log('Showing Add Place Modal'); }
            function showAuthWarningModal() { console.log('Showing Auth Warning Modal'); }

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

        <!-- Modify Lieu Modal -->
        <div id="modifyLieuModal"
            class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-2/3 lg:w-1/2 max-h-[90vh] flex flex-col">
                <div class="flex justify-between items-center p-4 border-b sticky top-0 bg-white z-10">
                    <h3 class="text-lg font-semibold">Modifier le lieu</h3>
                    <button onclick="hideModifyLieuModal()" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="p-4 overflow-y-auto flex-grow">
                    <form id="modifyLieuForm" enctype="multipart/form-data">
                        <input type="hidden" name="lieu_id" id="modifyLieuId">
                        <div class="mb-4">
                            <label for="modifyTitle" class="block text-sm font-medium text-gray-700">Title</label>
                            <input type="text" name="title" id="modifyTitle" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div class="mb-4">
                            <label for="modifyContent"
                                class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea name="content" id="modifyContent" rows="4" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="modifyLocation" class="block text-sm font-medium text-gray-700">Location</label>
                            <textarea name="location" id="modifyLocation" rows="2" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="modifyCategory" class="block text-sm font-medium text-gray-700">Category</label>
                            <select name="category" id="modifyCategory" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="modifyWilaya" class="block text-sm font-medium text-gray-700">Wilaya</label>
                            <select name="wilaya" id="modifyWilaya" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <?php foreach ($wilayas as $wilaya): ?>
                                    <option value="<?php echo $wilaya['wilaya_number']; ?>">
                                        <?php echo htmlspecialchars($wilaya['wilaya_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Current Images</label>
                            <div id="currentImagesPreview" class="mt-1 grid grid-cols-3 gap-2"></div>
                        </div>

                        <div class="mb-4">
                            <label for="new_images" class="block text-sm font-medium text-gray-700">Upload New
                                Images</label>
                            <input type="file" name="new_images[]" id="new_images" multiple accept="image/*" class="mt-1 block w-full text-sm text-gray-500
                                      file:mr-4 file:py-2 file:px-4
                                      file:rounded-full file:border-0
                                      file:text-sm file:font-semibold
                                      file:bg-blue-50 file:text-blue-700
                                      hover:file:bg-blue-100">
                            <div id="newImagesPreview" class="mt-2 grid grid-cols-3 gap-2"></div>
                        </div>
                    </form>
                </div>

                <div class="p-4 border-t sticky bottom-0 bg-white z-10">
                    <button type="submit" form="modifyLieuForm"
                        class="w-full bg-blue-500 text-white py-2 rounded-md hover:bg-blue-600 transition-colors">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>

        <!-- Auth Warning Modal -->
        <div id="authWarningModal"
            class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-1/3 p-6 text-center">
                <h3 class="text-lg font-semibold mb-4">Login Required</h3>
                <p class="text-gray-700 mb-6">You need to be logged in to perform this action.</p>
                <button onclick="hideAuthWarningModal(); showLoginModal()"
                    class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 transition-colors">Login</button>
            </div>
        </div>

        <!-- Add Place Modal - Placeholder for now -->
        <div id="addPlaceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-11/12 md:w-2/3 lg:w-1/2 max-h-[90vh] flex flex-col p-6">
                <h3 class="text-lg font-semibold mb-4">Add New Place (Placeholder)</h3>
                <p>Form for adding a new place will go here.</p>
                <button onclick="hideAddPlaceModal()" class="mt-4 bg-blue-500 text-white py-2 rounded-md">Close</button>
            </div>
        </div>

</body>

</html>