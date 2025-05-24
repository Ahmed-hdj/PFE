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

// Check if user is admin
if (!isset($user['role']) || $user['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Fetch pending places and images
try {
    // Fetch pending places
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

    // Fetch pending images
    $stmt = $pdo->prepare("
        SELECT li.*, l.title as lieu_title, u.username as uploader_name
        FROM lieu_images li
        JOIN lieu l ON li.lieu_id = l.lieu_id
        JOIN users u ON li.user_id = u.user_id
        WHERE li.status_photo = 'pending'
        ORDER BY li.uploaded_at DESC
    ");
    $stmt->execute();
    $pending_images = $stmt->fetchAll();
} catch (PDOException $e) {
    $pending_places = [];
    $pending_images = [];
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
                            <button onclick="toggleProfileDropdown()"
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
                            <div id="profileDropdown"
                                class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 hidden z-50">
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

            <!-- Pending Images Section -->
            <div class="mb-12">
                <h2 class="text-3xl font-bold mb-6 text-gray-800">
                    <i class="fas fa-images mr-2"></i>
                    Pending Images
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($pending_images)): ?>
                        <div class="col-span-full text-center py-8">
                            <p class="text-gray-600 text-lg">No pending images at the moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending_images as $image): ?>
                            <div class="bg-white rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300"
                                data-image-id="<?php echo $image['image_id']; ?>">
                                <div class="relative">
                                    <img src="<?php echo htmlspecialchars($image['image_url']); ?>" alt="Pending image"
                                        class="w-full h-64 object-cover">
                                    <div class="absolute top-4 right-4 z-30 flex gap-2">
                                        <button onclick="approveImage(<?php echo $image['image_id']; ?>)"
                                            class="bg-green-500 text-white p-2 rounded-full shadow-lg hover:bg-green-600 transition-colors">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button onclick="rejectImage(<?php echo $image['image_id']; ?>)"
                                            class="bg-red-500 text-white p-2 rounded-full shadow-lg hover:bg-red-600 transition-colors">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="p-4">
                                    <h3 class="font-semibold text-lg mb-2"><?php echo htmlspecialchars($image['lieu_title']); ?>
                                    </h3>
                                    <p class="text-gray-600 text-sm">
                                        Uploaded by: <?php echo htmlspecialchars($image['uploader_name']); ?>
                                    </p>
                                    <p class="text-gray-500 text-sm mt-1">
                                        <?php echo date('F j, Y', strtotime($image['uploaded_at'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Places Section -->
            <div>
                <h2 class="text-3xl font-bold mb-6 text-gray-800">
                    <i class="fas fa-map-marker-alt mr-2"></i>
                    Pending Places
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($pending_places)): ?>
                        <div class="col-span-full text-center py-8">
                            <p class="text-gray-600 text-lg">No pending places at the moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending_places as $place): ?>
                            <div class="bg-white rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300"
                                data-lieu-id="<?php echo $place['lieu_id']; ?>">
                                <div class="relative">
                                    <div class="relative h-72 image-gallery">
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
                                    <p class="text-gray-600 mb-4">
                                        <?php echo htmlspecialchars(substr($place['content'], 0, 150)) . '...'; ?>
                                    </p>

                                    <div
                                        class="inline-block px-3 py-1 rounded-full text-sm font-semibold mb-2 bg-blue-100 text-blue-800">
                                        <i class="fas fa-tag mr-1"></i>
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
                    fetch('api/update_lieu_status.php', {
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
                                const card = document.querySelector(`[data-lieu-id="${lieuId}"]`);
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
                    fetch('api/update_lieu_status.php', {
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
                                const card = document.querySelector(`[data-lieu-id="${lieuId}"]`);
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
            document.addEventListener('DOMContentLoaded', function () {
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

            // Search functionality
            document.getElementById('searchForm').addEventListener('submit', function (e) {
                e.preventDefault();
                const searchTerm = this.querySelector('input[name="search"]').value;
                window.location.href = `search.php?q=${encodeURIComponent(searchTerm)}`;
            });

            // Add lieu button functionality
            function checkAuthAndShowAddPlaceForm() {
                <?php if (isset($_SESSION['user_id'])): ?>
                    showAddPlaceModal();
                <?php else: ?>
                    showAuthWarningModal();
                <?php endif; ?>
            }

            // Search suggestions functionality
            const searchInput = document.querySelector('input[name="search"]');
            const searchSuggestions = document.getElementById('searchSuggestions');

            searchInput.addEventListener('input', function () {
                const searchTerm = this.value.trim();
                if (searchTerm.length > 2) {
                    fetch(`get_search_suggestions.php?q=${encodeURIComponent(searchTerm)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.length > 0) {
                                searchSuggestions.innerHTML = data.map(item => `
                                <div class="px-4 py-2 hover:bg-gray-100 cursor-pointer" onclick="selectSuggestion('${item}')">
                                    ${item}
                                </div>
                            `).join('');
                                searchSuggestions.classList.remove('hidden');
                            } else {
                                searchSuggestions.classList.add('hidden');
                            }
                        })
                        .catch(error => console.error('Error:', error));
                } else {
                    searchSuggestions.classList.add('hidden');
                }
            });

            function selectSuggestion(suggestion) {
                searchInput.value = suggestion;
                searchSuggestions.classList.add('hidden');
                document.getElementById('searchForm').submit();
            }

            // Close suggestions when clicking outside
            document.addEventListener('click', function (e) {
                if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
                    searchSuggestions.classList.add('hidden');
                }
            });

            // Profile dropdown functionality
            function toggleProfileDropdown() {
                const dropdown = document.getElementById('profileDropdown');
                dropdown.classList.toggle('hidden');
            }

            // Close profile dropdown when clicking outside
            document.addEventListener('click', function (event) {
                const profileDropdown = document.getElementById('profileDropdown');
                const profileButton = document.querySelector('[onclick="toggleProfileDropdown()"]');

                if (profileDropdown && profileButton && !profileDropdown.contains(event.target) && !profileButton.contains(event.target)) {
                    profileDropdown.classList.add('hidden');
                }
            });

            // Login modal functions
            function showLoginModal() {
                hideAuthWarningModal();
                document.getElementById('loginModal').classList.remove('hidden');
                document.getElementById('loginModal').classList.add('flex');
            }

            function hideLoginModal() {
                document.getElementById('loginModal').classList.remove('flex');
                document.getElementById('loginModal').classList.add('hidden');
            }

            function showSignupForm() {
                hideAuthWarningModal();
                document.getElementById('signupModal').classList.remove('hidden');
                document.getElementById('signupModal').classList.add('flex');
            }

            function hideSignupModal() {
                document.getElementById('signupModal').classList.remove('flex');
                document.getElementById('signupModal').classList.add('hidden');
            }

            function showAuthWarningModal() {
                document.getElementById('authWarningModal').classList.remove('hidden');
                document.getElementById('authWarningModal').classList.add('flex');
            }

            function hideAuthWarningModal() {
                document.getElementById('authWarningModal').classList.remove('flex');
                document.getElementById('authWarningModal').classList.add('hidden');
            }

            function showAddPlaceModal() {
                document.getElementById('addPlaceModal').classList.remove('hidden');
                document.getElementById('addPlaceModal').classList.add('flex');
            }

            function hideAddPlaceModal() {
                document.getElementById('addPlaceModal').classList.remove('flex');
                document.getElementById('addPlaceModal').classList.add('hidden');
            }

            // Handles image preview for the add place modal
            function previewAddPlaceImages(input) {
                const previewContainer = document.getElementById('addPlaceImagesPreview');
                previewContainer.innerHTML = '';
                if (input.files && input.files.length > 0) {
                    previewContainer.classList.remove('hidden');
                    Array.from(input.files).forEach((file, index) => {
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            const div = document.createElement('div');
                            div.className = 'relative group rounded-lg overflow-hidden';
                            div.innerHTML = `
                            <img src="${e.target.result}" alt="Preview" class="w-full h-24 object-cover">
                            <button type="button" onclick="removeAddPlaceImage(${index})" class="absolute top-2 right-2 bg-red-500 text-white p-1.5 rounded-full opacity-0 group-hover:opacity-100 transition-all hover:bg-red-600">
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

            // Removes an image preview from the add place modal
            function removeAddPlaceImage(index) {
                const input = document.getElementById('addPlaceImageUpload');
                const dt = new DataTransfer();
                const files = input.files;

                for (let i = 0; i < files.length; i++) {
                    if (i !== index) {
                        dt.items.add(files[i]);
                    }
                }

                input.files = dt.files;
                previewAddPlaceImages(input); // Refresh the preview
            }

            // Handles profile picture preview for signup modal
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

            // Note: Modify Lieu Modal functions (showModifyLieuModal, hideModifyLieuModal, loadCurrentImages, previewNewImages, removeNewImage)
            // and their associated form submission handler are also included below,
            // although they might not be directly used on the demands page unless
            // you plan to add modify functionality for pending demands.

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
                            // Hide lieu options dropdown if it was open
                            document.querySelectorAll('[id^="lieuOptions"]').forEach(menu => {
                                menu.classList.add('hidden');
                            });
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
                    // Find the image element by src and remove its parent div
                    const imgElement = document.querySelector(`#currentImages img[src="${imageUrl}"]`);
                    if (imgElement) {
                        imgElement.parentElement.remove();
                    }
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
                if (input.files && input.files.length > 0) {
                    previewContainer.classList.remove('hidden');
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
                previewNewImages(input); // Refresh the preview
            }

            // Submit handler for modify lieu form
            document.getElementById('modifyLieuForm').addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);

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

                fetch('index.php', { // Using index.php to handle the modification POST
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Lieu modified successfully');
                            hideModifyLieuModal();
                            // Refresh the page or update the specific card in the DOM
                            location.reload(); // Simple reload for now
                        } else {
                            alert(data.message || 'Error modifying lieu');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error modifying lieu. Please try again.');
                    });
            });

            function approveImage(imageId) {
                if (confirm('Are you sure you want to approve this image?')) {
                    fetch('api/update_image_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            image_id: imageId,
                            status: 'approved'
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const card = document.querySelector(`[data-image-id="${imageId}"]`);
                                card.remove();
                                alert('Image approved successfully');
                            } else {
                                alert(data.message || 'Error approving image');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error approving image. Please try again.');
                        });
                }
            }

            function rejectImage(imageId) {
                if (confirm('Are you sure you want to reject this image?')) {
                    fetch('api/update_image_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            image_id: imageId,
                            status: 'rejected'
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const card = document.querySelector(`[data-image-id="${imageId}"]`);
                                card.remove();
                                alert('Image rejected successfully');
                            } else {
                                alert(data.message || 'Error rejecting image');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error rejecting image. Please try again.');
                        });
                }
            }
        </script>
</body>

</html>