<?php
session_start();
require_once 'config/database.php';

// Get wilaya number from URL
$wilaya_number = isset($_GET['wilaya']) ? intval($_GET['wilaya']) : 0;

// Get user information if logged in
$user = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        // Handle error silently
    }
}

// Fetch wilaya name from database
try {
    $stmt = $pdo->prepare("SELECT wilaya_name FROM wilayas WHERE wilaya_number = ?");
    $stmt->execute([$wilaya_number]);
    $wilaya = $stmt->fetch();
    $wilaya_name = $wilaya ? $wilaya['wilaya_name'] : 'Unknown Wilaya';
} catch (PDOException $e) {
    $wilaya_name = 'Error loading wilaya name';
}

// Fetch places from the selected wilaya
try {
    $stmt = $pdo->prepare("
        SELECT l.*, c.category_name, u.username as author_name,
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
        WHERE l.wilaya_id = ? AND l.status = 'approved'
        GROUP BY l.lieu_id
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'] ?? 0, $wilaya_number]);
    $places = $stmt->fetchAll();
} catch (PDOException $e) {
    $places = [];
    $error_message = "Error: " . $e->getMessage();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            header('Location: ' . $_SERVER['PHP_SELF'] . '?wilaya=' . $wilaya_number);
            exit();
        } else {
            $login_error = "Invalid email or password";
        }
    } catch (PDOException $e) {
        $login_error = "Error: " . $e->getMessage();
    }
}

// Handle signup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm_password) {
        $signup_error = "Passwords do not match";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $signup_error = "Email already exists";
            } else {
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password]);

                // Log in the new user
                $_SESSION['user_id'] = $pdo->lastInsertId();
                header('Location: ' . $_SERVER['PHP_SELF'] . '?wilaya=' . $wilaya_number);
                exit();
            }
        } catch (PDOException $e) {
            $signup_error = "Error: " . $e->getMessage();
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF'] . '?wilaya=' . $wilaya_number);
    exit();
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
    <title><?php echo htmlspecialchars($wilaya_name); ?> - Places to Visit</title>
    <style>
        body {
            background-color: #f3f4f6;
        }

        .wilaya-name {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(45deg, #1a365d, #2c5282);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            letter-spacing: 0.05em;
            text-align: center;
            margin: 2rem auto;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #2c5282;
            width: fit-content;
        }

        .city-name {
            font-size: 4rem;
            font-weight: 800;
            background: linear-gradient(45deg, #1a365d, #2c5282);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            letter-spacing: 0.05em;
            text-align: center;
            margin: 1rem auto;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #2c5282;
            width: fit-content;
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
    <script>
        // Image gallery functionality
        document.addEventListener('DOMContentLoaded', function () {
            // Get the title from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const title = urlParams.get('title');
            const type = urlParams.get('type'); // 'category' or 'city'

            // Set the page title and heading
            document.title = title || 'Places to Visit';
            const pageTitle = document.getElementById('pageTitle');
            const pageHeading = document.getElementById('pageHeading');
            if (pageTitle) pageTitle.textContent = title || 'Places to Visit';
            if (pageHeading) pageHeading.textContent = title || 'Places to Visit';

            const attractions = {
                // Theme Park - A family-friendly amusement park with various rides and attractions
                park: {
                    images: [
                        'https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?w=800&auto=format&fit=crop&q=60',
                        'https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=800&auto=format&fit=crop&q=60',
                        'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?w=800&auto=format&fit=crop&q=60',
                        'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=800&auto=format&fit=crop&q=60',
                        'https://th.bing.com/th/id/OIP.6PwZs9c0PxgoWzQkJnY5QQHaEK?w=315&h=180&c=7&r=0&o=5&cb=iwc2&pid=1.7'
                    ],
                    comment: 'A family-friendly amusement park with various rides and attractions'
                },
                // Water Park - A refreshing water park with exciting slides and pools for summer fun
                waterpark: {
                    images: [
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60',
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60',
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60',
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60'
                    ],
                    comment: 'A refreshing water park with exciting slides and pools for summer fun'
                },
                // Cinema Complex - A modern cinema complex with multiple screens and comfortable seating
                cinema: {
                    images: [
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60',
                        'https://th.bing.com/th/id/R.431c8287a6f352f0663e8aa310fa77ae?rik=rW7LtnyZXjwUiQ&pid=ImgRaw&r=0',
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60',
                        'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=60'
                    ],
                    comment: 'A modern cinema complex with multiple screens and comfortable seating'
                }
            };

            const attractionComments = {
                park: [
                    {
                        name: 'Sarah Martin',
                        avatar: 'https://i.pravatar.cc/150?img=1',
                        time: '2 hours ago',
                        text: 'J\'ai visité cet endroit l\'année dernière et c\'était vraiment magnifique ! L\'architecture est impressionnante et l\'ambiance est unique. Je recommande vivement cette visite.'
                    },
                    {
                        name: 'Mohammed Ali',
                        avatar: 'https://i.pravatar.cc/150?img=2',
                        time: '5 hours ago',
                        text: 'Un lieu historique fascinant ! Les guides sont très compétents et les explications sont détaillées. N\'oubliez pas de prendre des photos, c\'est vraiment photogénique.'
                    },
                    {
                        name: 'Mohammed Ali',
                        avatar: 'https://i.pravatar.cc/150?img=2',
                        time: '5 hours ago',
                        text: 'Un lieu historique fascinant ! Les guides sont très compétents et les explications sont détaillées. N\'oubliez pas de prendre des photos, c\'est vraiment photogénique.'
                    },
                    {
                        name: 'Mohammed Ali',
                        avatar: 'https://i.pravatar.cc/150?img=2',
                        time: '5 hours ago',
                        text: 'Un lieu historique fascinant ! Les guides sont très compétents et les explications sont détaillées. N\'oubliez pas de prendre des photos, c\'est vraiment photogénique.'
                    },
                    {
                        name: 'Mohammed Ali',
                        avatar: 'https://i.pravatar.cc/150?img=2',
                        time: '5 hours ago',
                        text: 'Un lieu historique fascinant ! Les guides sont très compétents et les explications sont détaillées. N\'oubliez pas de prendre des photos, c\'est vraiment photogénique.'
                    }
                ],
                'notredame': [
                    {
                        name: 'Jean Dupont',
                        avatar: 'https://i.pravatar.cc/150?img=4',
                        time: '3 hours ago',
                        text: 'Un endroit magique ! La vue est spectaculaire et l\'histoire du lieu est captivante. Je reviendrai certainement.'
                    },
                    {
                        name: 'Leila Benali',
                        avatar: 'https://i.pravatar.cc/150?img=5',
                        time: '1 day ago',
                        text: 'La beauté naturelle de cet endroit est incomparable. Les couleurs au coucher du soleil sont à ne pas manquer.'
                    }
                ],
                'jardin': [
                    {
                        name: 'Emma Dubois',
                        avatar: 'https://i.pravatar.cc/150?img=13',
                        time: '1 day ago',
                        text: 'Un jardin magnifique avec une grande variété de plantes. Parfait pour une promenade paisible.'
                    },
                    {
                        name: 'Thomas Bernard',
                        avatar: 'https://i.pravatar.cc/150?img=14',
                        time: '2 days ago',
                        text: 'Un véritable havre de paix au cœur de la ville. Les allées sont bien entretenues et les plantes sont magnifiques.'
                    }
                ]
            };

            // Function to update card links with current images
            function updateCardLinks() {
                const cards = document.querySelectorAll('a[href*="card-details.html"]');
                cards.forEach(card => {
                    const id = card.getAttribute('href').split('id=')[1].split('&')[0];
                    const title = card.getAttribute('href').split('title=')[1].split('&')[0];
                    const attraction = attractions[id];
                    const comments = attractionComments[id];
                    if (attraction) {
                        const encodedImages = encodeURIComponent(JSON.stringify(attraction.images));
                        const encodedComments = encodeURIComponent(JSON.stringify(comments));
                        card.href = `card-details.html?id=${id}&title=${title}&images=${encodedImages}&comments=${encodedComments}`;
                    }
                });
            }

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
                        // Update the card link when image changes
                        updateCardLinks();
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

            // Initial update of card links
            updateCardLinks();
        });

        // Modal functions
        function showLoginModal() {
            document.getElementById('loginModal').classList.remove('hidden');
            document.getElementById('loginModal').classList.add('flex');
        }

        function hideLoginModal() {
            document.getElementById('loginModal').classList.add('hidden');
            document.getElementById('loginModal').classList.remove('flex');
        }

        function showSignupModal() {
            document.getElementById('signupModal').classList.remove('hidden');
            document.getElementById('signupModal').classList.add('flex');
        }

        function hideSignupModal() {
            document.getElementById('signupModal').classList.add('hidden');
            document.getElementById('signupModal').classList.remove('flex');
        }

        function showLoginForm() {
            hideSignupModal();
            showLoginModal();
        }

        function showSignupForm() {
            hideLoginModal();
            showSignupModal();
        }

        // Profile picture preview
        function previewProfilePicture(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('profilePreview').src = e.target.result;
                    document.getElementById('profilePreview').classList.remove('grayscale');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Close modals when clicking outside
        document.addEventListener('click', function (event) {
            const loginModal = document.getElementById('loginModal');
            const signupModal = document.getElementById('signupModal');

            if (event.target === loginModal) {
                hideLoginModal();
            }
            if (event.target === signupModal) {
                hideSignupModal();
            }
        });

        // Close modals when pressing Escape key
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hideLoginModal();
                hideSignupModal();
            }
        });

        function checkAuthAndShowAddPlaceForm() {
            <?php if (isset($_SESSION['user_id'])): ?>
                showAddPlaceModal();
            <?php else: ?>
                showAuthWarningModal();
            <?php endif; ?>
        }

        function showAddPlaceModal() {
            const modal = document.getElementById('addPlaceModal');
            modal.style.opacity = '0';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            // Trigger reflow
            modal.offsetHeight;
            modal.style.opacity = '1';
        }

        function hideAddPlaceModal() {
            const modal = document.getElementById('addPlaceModal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }, 200);
        }

        function showAuthWarningModal() {
            const modal = document.getElementById('authWarningModal');
            modal.style.opacity = '0';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            // Trigger reflow
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

        // Initialize all functionality when DOM is loaded
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize add place form
            const addPlaceForm = document.querySelector('#addPlaceForm');
            if (addPlaceForm) {
                addPlaceForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    console.log('Form submitted:', Object.fromEntries(formData));
                    hideAddPlaceModal();
                });
            }

            // Initialize login form
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    // Add your login logic here
                    // After successful login:
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('userRole', 'admin'); // This should come from your backend
                    updateAdminStatus();
                    hideLoginModal();
                });
            }

            // Check admin status
            checkAdminStatus();

            // Initialize search functionality
            console.log('DOM loaded, initializing search functionality...');

            // Initialize search functionality
            const searchForm = document.getElementById('searchForm');
            const searchInput = document.querySelector('input[name="search"]');
            const suggestionsContainer = document.getElementById('searchSuggestions');

            console.log('Search elements found:', {
                searchForm: !!searchForm,
                searchInput: !!searchInput,
                suggestionsContainer: !!suggestionsContainer
            });

            if (!searchForm || !searchInput || !suggestionsContainer) {
                console.error('Required search elements not found:', {
                    searchForm: !!searchForm,
                    searchInput: !!searchInput,
                    suggestionsContainer: !!suggestionsContainer
                });
                return;
            }

            let searchTimeout;

            // Search input handler
            searchInput.addEventListener('input', function () {
                console.log('Search input changed:', this.value);
                clearTimeout(searchTimeout);
                const query = this.value.trim();

                if (query.length < 2) {
                    suggestionsContainer.classList.add('hidden');
                    return;
                }

                searchTimeout = setTimeout(async () => {
                    try {
                        console.log('Fetching suggestions for:', query);
                        const response = await fetch(`search_suggestions.php?q=${encodeURIComponent(query)}`);
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        const suggestions = await response.json();
                        console.log('Suggestions received:', suggestions);

                        if (suggestions.length > 0) {
                            suggestionsContainer.innerHTML = suggestions.map(suggestion => `
                                <div class="p-2 hover:bg-gray-100 cursor-pointer flex items-center gap-2" 
                                     onclick="handleSuggestionClick('${suggestion.type}', ${suggestion.id}, '${suggestion.text.replace(/'/g, "\\'")}')">
                                    <i class="fas ${suggestion.type === 'place' ? 'fa-map-marker-alt' : 'fa-city'} text-gray-500"></i>
                                    <span>${suggestion.text}</span>
                                </div>
                            `).join('');
                            suggestionsContainer.classList.remove('hidden');
                        } else {
                            suggestionsContainer.classList.add('hidden');
                        }
                    } catch (error) {
                        console.error('Error fetching suggestions:', error);
                        suggestionsContainer.classList.add('hidden');
                    }
                }, 300);
            });

            // Search form submission handler
            searchForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                console.log('Search form submitted');
                const searchQuery = searchInput.value.trim();

                if (!searchQuery) {
                    console.log('Empty search query, returning');
                    return;
                }

                try {
                    console.log('Sending search request for:', searchQuery);
                    const response = await fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `search=${encodeURIComponent(searchQuery)}`
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    console.log('Search response received');
                    const result = await response.json();
                    console.log('Search result:', result);

                    if (result.success) {
                        // Find the container that holds the places
                        const container = document.querySelector('.grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3.gap-6');
                        if (!container) {
                            console.error('Places container not found');
                            return;
                        }

                        container.innerHTML = ''; // Clear existing content

                        if (result.data.length === 0) {
                            container.innerHTML = '<div class="col-span-full text-center py-8"><p class="text-gray-500 text-lg">No places found matching your search.</p></div>';
                            return;
                        }

                        result.data.forEach(place => {
                            const card = createPlaceCard(place);
                            container.appendChild(card);
                        });
                    } else {
                        console.error('Search error:', result.message);
                        alert(result.message || 'Error performing search');
                    }
                } catch (error) {
                    console.error('Search error:', error);
                    alert('Error performing search. Please try again.');
                }
            });

            // Close suggestions when clicking outside
            document.addEventListener('click', function (e) {
                if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                    suggestionsContainer.classList.add('hidden');
                }
            });
        });

        // Make handleSuggestionClick available globally
        window.handleSuggestionClick = function (type, id, text) {
            console.log('Suggestion clicked:', { type, id, text });
            const searchInput = document.querySelector('input[name="search"]');
            const suggestionsContainer = document.getElementById('searchSuggestions');

            if (searchInput && suggestionsContainer) {
                searchInput.value = text;
                suggestionsContainer.classList.add('hidden');

                if (type === 'place') {
                    window.location.href = `card-details.php?id=${id}`;
                } else {
                    window.location.href = `dynamic-page.php?wilaya=${id}`;
                }
            }
        };

        function createPlaceCard(place) {
            const card = document.createElement('a');
            card.href = `card-details.php?id=${place.lieu_id}`;
            card.className = 'block cursor-pointer group';

            const rating = place.average_rating || 0;
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

            const imageUrl = place.images && place.images.length > 0 ? place.images[0] : 'images and videos/default-place.jpg';

            card.innerHTML = `
                <div class="bg-white rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 h-full">
                    <div class="relative">
                        <div class="relative h-72 image-gallery" data-gallery="${place.lieu_id}">
                            <img src="${imageUrl}" alt="${place.title}"
                                class="w-full h-full object-cover transition-opacity duration-300 hover:opacity-80">
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                                                                                                <div class="absolute top-4 left-4 z-30">
                                                                                                                    <button onclick="event.preventDefault(); showLieuOptions(<?php echo $place['lieu_id']; ?>)"
                                                                                                                        class="bg-white p-2 rounded-full shadow-lg hover:bg-gray-100 transition-colors">
                                                                                                                        <i class="fas fa-ellipsis-v text-gray-600"></i>
                                                                                                                    </button>
                                                                                                                    <div id="lieuOptions${place.lieu_id}" class="hidden absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-40">
                                                                                                                        <button onclick="event.preventDefault(); showModifyLieuModal(<?php echo $place['lieu_id']; ?>, '<?php echo htmlspecialchars($place['title']); ?>', '<?php echo htmlspecialchars($place['content']); ?>', '<?php echo htmlspecialchars($place['location']); ?>', <?php echo $place['category_id']; ?>)"
                                                                                                                            class="w-full text-left px-4 py-2 hover:bg-gray-100">
                                                                                                                            <i class="fas fa-edit mr-2"></i> Modify
                                                                                                                        </button>
                                                                                                                        <button onclick="event.preventDefault(); deleteLieu(<?php echo $place['lieu_id']; ?>)" class="w-full text-left px-4 py-2 text-red-600 hover:bg-gray-100">
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
                            <button onclick="event.preventDefault(); toggleSave(<?php echo $place['lieu_id']; ?>, this)"
                                class="absolute top-4 right-4 bg-white p-2 rounded-full shadow-lg hover:bg-gray-100 transition-colors z-30 w-10 h-10 flex items-center justify-center save-button <?php echo $place['is_saved'] ? 'saved' : ''; ?>">
                                <i
                                    class="<?php echo $place['is_saved'] ? 'fas' : 'far'; ?> fa-bookmark text-gray-600"></i>
                            </button>
                        </div>
                        <div class="absolute bottom-0 left-0 right-0 flex justify-center gap-2 p-4 z-20 dots-container transition-transform duration-300">
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="text-xl font-bold mb-2 group-hover:underline"><?php echo htmlspecialchars($place['title']); ?></h3>
                        <div class="flex items-center mb-2">
                            <div class="flex">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                                                                        <?php if ($i <= $fullStars): ?>
                                                                                                                                                                                                                <i class="fas fa-star text-yellow-400"></i>
                                                                                                                        <?php elseif ($i === $fullStars + 1 && $halfStar): ?>
                                                                                                                                                                                                                <i class="fas fa-star-half-alt text-yellow-400"></i>
                                                                                                                        <?php else: ?>
                                                                                                                                                                                                                <i class="fas fa-star text-gray-300"></i>
                                                                                                                        <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            <span class="ml-2 text-gray-600"><?php echo number_format($rating, 1); ?></span>
                            <?php if ($place['total_ratings'] > 0): ?>
                                                                                                                    <span class="ml-2 text-gray-400">(<?php echo $place['total_ratings']; ?> ratings)</span>
                            <?php else: ?>
                                                                                                                    <span class="ml-2 text-gray-400">(No ratings yet)</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($place['category_name']); ?></p>
                        <div class="flex items-center mb-4">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($place['author_name']); ?>" 
                                 alt="<?php echo htmlspecialchars($place['author_name']); ?>"
                                 class="w-8 h-8 rounded-full mr-2">
                            <div>
                                <span class="text-gray-600">Par </span>
                                <span class="font-medium"><?php echo htmlspecialchars($place['author_name']); ?></span>
                            </div>
                        </div>
                        <p class="text-gray-700"><?php echo htmlspecialchars(substr($place['content'], 0, 150)) . (strlen($place['content']) > 150 ? '...' : ''); ?></p>
                    </div>
                </div>
            `;

            return card;
        }

        // Check if user is admin and show/hide the requests button
        function checkAdminStatus() {
            /* Commented out for testing
            const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
            const userRole = localStorage.getItem('userRole');
            const viewRequestsBtn = document.getElementById('viewRequestsBtn');
            
            if (isLoggedIn && userRole === 'admin') {
                viewRequestsBtn.classList.remove('hidden');
            } else {
                viewRequestsBtn.classList.add('hidden');
            }
            */
        }

        // Show requests modal
        function showRequestsModal() {
            const modal = document.getElementById('requestsModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            loadRequests(); // Function to load requests from backend
        }

        // Hide requests modal
        function hideRequestsModal() {
            const modal = document.getElementById('requestsModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }

        // Load requests from backend
        function loadRequests() {
            // This is where you would make an API call to get the requests
            // For now, we'll just show a message
            const requestsContainer = document.querySelector('#requestsModal .space-y-4');
            requestsContainer.innerHTML = '<p class="text-gray-600">Chargement des demandes...</p>';
        }

        // Update admin status when user logs in
        function updateAdminStatus() {
            checkAdminStatus();
        }

        // Add logout functionality
        function logout() {
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('userRole');
            updateAdminStatus();
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

        // Add three-point menu functionality
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

        // Add delete functionality
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
            const isMenuButton = event.target.closest('[onclick*="showLieuOptions"]');
            const isMenuContent = event.target.closest('[id^="lieuOptions"]');

            if (!isMenuButton && !isMenuContent) {
                document.querySelectorAll('[id^="lieuOptions"]').forEach(menu => {
                    menu.classList.add('hidden');
                });
            }
        });

        function showModifyLieuModal(lieuId, title, content, location, categoryId) {
            const modal = document.getElementById('modifyLieuModal');

            // Set the values in the form
            document.getElementById('modifyLieuId').value = lieuId;
            document.getElementById('modifyTitle').value = title;
            document.getElementById('modifyContent').value = content;
            document.getElementById('modifyLocation').value = location;
            document.getElementById('modifyCategory').value = categoryId;

            // Show the modal
            modal.style.opacity = '0';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            // Trigger reflow
            modal.offsetHeight;
            modal.style.opacity = '1';

            // Load current images
            loadCurrentImages(lieuId);
        }

        function hideModifyLieuModal() {
            const modal = document.getElementById('modifyLieuModal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }, 200);
        }

        function loadCurrentImages(lieuId) {
            const imagesContainer = document.getElementById('currentImages');
            imagesContainer.innerHTML = '<p class="text-gray-500">Loading images...</p>';

            // Fetch current images for the lieu
            fetch(`get_lieu_images.php?lieu_id=${lieuId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        imagesContainer.innerHTML = data.images.map(image => `
                            <div class="relative">
                                <img src="${image}" alt="Current image" class="w-full h-24 object-cover rounded">
                                <button type="button" onclick="removeImage('${image}')" class="absolute top-1 right-1 bg-red-500 text-white p-1 rounded-full">
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

        // Handle form submission
        document.getElementById('modifyLieuForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
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
</head>

<body class="open-sans" style="font-family: 'Open Sans', sans-serif;">
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

        <!-- Wilaya Name Display -->
        <div class="container mx-auto px-4 py-8">
            <div class="flex items-center justify-center mb-8">
                <div class="flex items-center bg-[#E6F7FF] p-4 rounded-lg">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-map-marker-alt text-[#1890FF] text-2xl"></i>
                    </div>
                    <h1 class="text-3xl font-bold text-[#1890FF]"><?php echo htmlspecialchars($wilaya_name); ?></h1>
                </div>
            </div>
        </div>

        <!-- Places Section -->
        <div class="container mx-auto px-4 py-16 max-w-[1152px]">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-8">
                <?php if (empty($places)): ?>
                <div class="col-span-full text-center py-8">
                    <p class="text-gray-500 text-lg">No places found in this wilaya yet.</p>
                </div>
                <?php else: ?>
                <?php foreach ($places as $place):
                    $images = explode(',', $place['images']);
                    $firstImage = !empty($images) ? $images[0] : 'images and videos/default-place.jpg';
                    $rating = round($place['average_rating'], 1);
                    $fullStars = floor($rating);
                    $halfStar = $rating - $fullStars >= 0.5;
                    ?>
                <a href="card-details.php?id=<?php echo $place['lieu_id']; ?>" class="block cursor-pointer group">
                    <div
                        class="bg-white rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 h-full">
                        <div class="relative">
                            <div class="relative h-72 image-gallery" data-gallery="<?php echo $place['lieu_id']; ?>">
                                <img src="<?php echo htmlspecialchars($firstImage); ?>"
                                    alt="<?php echo htmlspecialchars($place['title']); ?>"
                                    class="w-full h-full object-cover transition-opacity duration-300 hover:opacity-80">
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
                                            onclick="event.preventDefault(); showModifyLieuModal(<?php echo $place['lieu_id']; ?>, '<?php echo htmlspecialchars($place['title']); ?>', '<?php echo htmlspecialchars($place['content']); ?>', '<?php echo htmlspecialchars($place['location']); ?>', <?php echo $place['category_id']; ?>)"
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
                                <button
                                    onclick="event.preventDefault(); toggleSave(<?php echo $place['lieu_id']; ?>, this)"
                                    class="absolute top-4 right-4 bg-white p-2 rounded-full shadow-lg hover:bg-gray-100 transition-colors z-30 w-10 h-10 flex items-center justify-center save-button <?php echo $place['is_saved'] ? 'saved' : ''; ?>">
                                    <i
                                        class="<?php echo $place['is_saved'] ? 'fas' : 'far'; ?> fa-bookmark text-gray-600"></i>
                                </button>
                            </div>
                            <div
                                class="absolute bottom-0 left-0 right-0 flex justify-center gap-2 p-4 z-20 dots-container transition-transform duration-300">
                            </div>
                        </div>
                        <div class="p-4">
                            <h3 class="text-xl font-bold mb-2 group-hover:underline">
                                <?php echo htmlspecialchars($place['title']); ?>
                            </h3>
                            <div class="flex items-center mb-2">
                                <div class="flex">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $fullStars): ?>
                                    <i class="fas fa-star text-yellow-400"></i>
                                    <?php elseif ($i === $fullStars + 1 && $halfStar): ?>
                                    <i class="fas fa-star-half-alt text-yellow-400"></i>
                                    <?php else: ?>
                                    <i class="fas fa-star text-gray-300"></i>
                                    <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <span class="ml-2 text-gray-600"><?php echo number_format($rating, 1); ?></span>
                                <?php if ($place['total_ratings'] > 0): ?>
                                <span class="ml-2 text-gray-400">(<?php echo $place['total_ratings']; ?> ratings)</span>
                                <?php else: ?>
                                <span class="ml-2 text-gray-400">(No ratings yet)</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($place['category_name']); ?></p>
                            <div class="flex items-center mb-4">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($place['author_name']); ?>"
                                    alt="<?php echo htmlspecialchars($place['author_name']); ?>"
                                    class="w-8 h-8 rounded-full mr-2">
                                <div>
                                    <span class="text-gray-600">Par </span>
                                    <span
                                        class="font-medium"><?php echo htmlspecialchars($place['author_name']); ?></span>
                                </div>
                            </div>
                            <p class="text-gray-700">
                                <?php echo htmlspecialchars(substr($place['content'], 0, 150)) . (strlen($place['content']) > 150 ? '...' : ''); ?>
                            </p>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Login Modal -->
        <div id="loginModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
            <div class="bg-white p-8 rounded-lg w-96 shadow-xl relative border-[6px] border-[#327532]">
                <img src="images and videos/ChatGPT Image 29 avr. 2025, 00_41_42.png" alt=""
                    class="w-[90px] mx-auto mb-7">
                <div class="flex justify-center items-center mb-4">
                    <h2
                        class="text-2xl font-bold text-[#19a719] before:content-[''] before:w-[30%] before:h-[2px] before:bg-[#19a719] before:absolute  before:left-0 before:top-[166px] after:content-[''] after:w-[30%] after:h-[2px] after:bg-[#19a719] after:absolute  after:right-0 after:top-[166px]">
                        Connexion</h2>
                    <button onclick="hideLoginModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                        <i class="fas fa-times absolute top-[16px] right-[18px] text-[18px]"></i>
                    </button>
                </div>
                <?php if (isset($login_error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($login_error); ?></span>
                </div>
                <?php endif; ?>
                <form id="loginForm" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2 font-semibold tracking-[1px]">Email :</label>
                        <input type="email" name="email" required
                            class="w-full px-3 py-2 border rounded-full outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 border-[#a1a1a1]"
                            placeholder="Entrez votre email">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2 font-semibold tracking-[1px]">Mot de passe :</label>
                        <input type="password" name="password" required
                            class="w-full px-3 py-2 border rounded-full outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 border-[#a1a1a1] mb-[14px]"
                            placeholder="Entrez votre mot de passe">
                    </div>
                    <button type="submit" name="login"
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
                <?php if (isset($signup_error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($signup_error); ?></span>
                </div>
                <?php endif; ?>
                <form id="signupForm" method="POST" class="space-y-4">
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
                            <input type="file" id="profilePicture" name="profile_picture" accept="image/*"
                                class="hidden" onchange="previewProfilePicture(this)">
                        </div>
                        <p class="text-sm text-gray-500">Click to add a profile picture</p>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2 font-semibold tracking-[1px]">Nom complet :</label>
                        <input type="text" name="username" required
                            class="w-full px-3 py-2 border rounded-full outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 border-[#a1a1a1]"
                            placeholder="Entrez votre nom complet">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2 font-semibold tracking-[1px]">Email :</label>
                        <input type="email" name="email" required
                            class="w-full px-3 py-2 border rounded-full outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 border-[#a1a1a1]"
                            placeholder="Entrez votre email">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2 font-semibold tracking-[1px]">Mot de passe :</label>
                        <input type="password" name="password" required
                            class="w-full px-3 py-2 border rounded-full outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 border-[#a1a1a1]"
                            placeholder="Créez votre mot de passe">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2 font-semibold tracking-[1px]">Confirmer le mot de passe
                            :</label>
                        <input type="password" name="confirm_password" required
                            class="w-full px-3 py-2 border rounded-full outline-none focus:shadow-[0_0_6px_1px_#23c523] transition-all duration-300 border-[#a1a1a1] mb-[14px]"
                            placeholder="Confirmez votre mot de passe">
                    </div>
                    <button type="submit" name="signup"
                        class="bg-[#2fb52f] text-white py-2 rounded-full hover:opacity-80 transition-colors cursor-pointer font-bold w-full ">
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
                <p class="text-gray-600 mb-6">Please log in to add a place.</p>
                <div class="flex justify-end gap-4">
                    <button onclick="hideAuthWarningModal()"
                        class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors cursor-pointer">
                        Cancel
                    </button>
                    <button onclick="handleAuthWarningLogin()"
                        class="px-4 py-2 bg-blue-500 text-white rounded-full hover:bg-blue-600 transition-colors cursor-pointer">
                        Log In
                    </button>
                </div>
            </div>
        </div>

        <!-- Requests Modal -->
        <div id="requestsModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
            <div class="bg-white p-8 rounded-lg w-[800px] shadow-xl">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold">Demandes d'ajout de lieux</h2>
                    <button onclick="hideRequestsModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="space-y-4 max-h-[600px] overflow-y-auto">
                    <!-- Requests will be loaded here dynamically -->
                </div>
            </div>
        </div>

        <!-- Modify Lieu Modal -->
        <div id="modifyLieuModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
            <div class="bg-white p-8 rounded-lg w-[600px] shadow-xl">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold">Modifier le lieu</h2>
                    <button onclick="hideModifyLieuModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="modifyLieuForm" class="space-y-4">
                    <input type="hidden" id="modifyLieuId" name="lieu_id">
                    <div>
                        <label class="block text-gray-700 mb-2">Nom du lieu</label>
                        <input type="text" id="modifyTitle" name="title" class="w-full px-3 py-2 border rounded-full"
                            placeholder="Entrez le nom du lieu">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Description</label>
                        <textarea id="modifyContent" name="content" class="w-full px-3 py-2 border rounded-lg" rows="3"
                            placeholder="Entrez la description du lieu"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Location</label>
                        <textarea id="modifyLocation" name="location" class="w-full px-3 py-2 border rounded-lg"
                            rows="3" placeholder="Entrez la location du lieu"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Catégorie</label>
                        <select id="modifyCategory" name="category_id" class="w-full px-3 py-2 border rounded-full">
                            <option value="">Sélectionnez une catégorie</option>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT * FROM categories ORDER BY category_name");
                                while ($category = $stmt->fetch()) {
                                    echo '<option value="' . $category['category_id'] . '">' . htmlspecialchars($category['category_name']) . '</option>';
                                }
                            } catch (PDOException $e) {
                                echo "Error loading categories";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Images</label>
                        <div id="currentImages" class="grid grid-cols-3 gap-2 mb-2">
                            <!-- Current images will be displayed here -->
                        </div>
                        <input type="file" name="new_images[]" class="w-full px-3 py-2 border rounded-full" multiple
                            accept="image/*">
                        <p class="text-sm text-gray-500 mt-1">Upload new images to add to existing ones</p>
                    </div>
                    <button type="submit"
                        class="w-full bg-blue-500 text-white py-2 rounded-full hover:bg-blue-600 transition-colors cursor-pointer">
                        Modifier le lieu
                    </button>
                </form>
            </div>
        </div>

</body>

</html>