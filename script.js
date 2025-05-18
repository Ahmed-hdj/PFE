function showLoginModal() {
    const modal = document.getElementById('loginModal');
    modal.style.opacity = '0';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    // Trigger reflow
    modal.offsetHeight;
    modal.style.opacity = '1';
}

function hideLoginModal() {
    const modal = document.getElementById('loginModal');
    modal.style.opacity = '0';
    setTimeout(() => {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }, 200);
}

function showSignupForm() {
    const loginModal = document.getElementById('loginModal');
    const signupModal = document.getElementById('signupModal');
    loginModal.style.opacity = '0';
    setTimeout(() => {
        loginModal.classList.remove('flex');
        loginModal.classList.add('hidden');
        signupModal.style.opacity = '0';
        signupModal.classList.remove('hidden');
        signupModal.classList.add('flex');
        // Trigger reflow
        signupModal.offsetHeight;
        signupModal.style.opacity = '1';
    }, 200);
}

function hideSignupModal() {
    const signupModal = document.getElementById('signupModal');
    signupModal.style.opacity = '0';
    setTimeout(() => {
        signupModal.classList.remove('flex');
        signupModal.classList.add('hidden');
    }, 200);
}

function showLoginForm() {
    const loginModal = document.getElementById('loginModal');
    const signupModal = document.getElementById('signupModal');
    signupModal.style.opacity = '0';
    setTimeout(() => {
        signupModal.classList.remove('flex');
        signupModal.classList.add('hidden');
        loginModal.style.opacity = '0';
        loginModal.classList.remove('hidden');
        loginModal.classList.add('flex');
        // Trigger reflow
        loginModal.offsetHeight;
        loginModal.style.opacity = '1';
    }, 200);
}

// Close modal when clicking outside
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

// Handle search input clear button
const searchInput = document.querySelector('input[type="search"]');
const clearButton = searchInput.nextElementSibling;

searchInput.addEventListener('input', function () {
    clearButton.classList.toggle('hidden', !this.value);
});

// Cities Carousel
document.addEventListener('DOMContentLoaded', function () {
    const citiesContainer = document.getElementById('citiesContainer');
    const prevButton = document.getElementById('prevCity');
    const nextButton = document.getElementById('nextCity');
    const cityCards = document.querySelectorAll('#citiesContainer > div');
    let currentPosition = 0;
    let cardWidth;
    let containerWidth;

    function updateDimensions() {
        // Get accurate card width (including margins/padding)
        cardWidth = cityCards[0].offsetWidth;
        containerWidth = citiesContainer.parentElement.offsetWidth;
    }

    function getMaxPosition() {
        const totalWidth = cityCards.length * cardWidth;
        // Calculate maximum scroll position to align last card
        return Math.min(0, containerWidth - totalWidth);
    }

    function updateCarousel() {
        const maxPosition = getMaxPosition();

        // Apply boundaries
        currentPosition = Math.max(maxPosition, Math.min(0, currentPosition));

        citiesContainer.style.transform = `translateX(${currentPosition}px)`;

        // Update button states
        prevButton.disabled = currentPosition === 0;
        nextButton.disabled = currentPosition <= maxPosition;
    }

    // Navigation handlers
    prevButton.addEventListener('click', () => {
        currentPosition += cardWidth;
        updateCarousel();
    });

    nextButton.addEventListener('click', () => {
        currentPosition -= cardWidth;
        updateCarousel();
    });

    // Initialize and handle resize
    function initCarousel() {
        updateDimensions();
        currentPosition = 0;
        updateCarousel();
    }

    initCarousel();
    window.addEventListener('resize', initCarousel);
});

// Attraction Images Navigation
document.addEventListener('DOMContentLoaded', function () {
    const attractions = {
        jardin: [
            'https://c1.staticflickr.com/1/276/32193070856_d5137fac58_h.jpg',

            'https://th.bing.com/th/id/OIP.56cC238AcgBam2khD8-TswHaEo?w=283&h=180&c=7&r=0&o=5&cb=iwc2&pid=1.7'
        ],
        notredame: [
            'https://th.bing.com/th/id/OIP.w6L4I-nCyzbyOAfVcNNY-AHaE8?w=275&h=183&c=7&r=0&o=5&cb=iwc1&pid=1.7',
            'https://th.bing.com/th/id/OIP.9N9m2Oroyy5UlF9mNu9TSwHaGN?w=239&h=200&c=7&r=0&o=5&cb=iwc1&pid=1.7',
            'https://th.bing.com/th/id/OIP.v4BfLE6kVfdCRf5e2x456QHaGL?w=219&h=183&c=7&r=0&o=5&cb=iwc1&pid=1.7',
            'https://th.bing.com/th/id/OIP.56cC238AcgBam2khD8-TswHaEo?w=283&h=180&c=7&r=0&o=5&cb=iwc2&pid=1.7'

        ],
        casbah: [
            'https://c1.staticflickr.com/1/507/32124243810_ebb256b3a1_h.jpg',
            'https://th.bing.com/th/id/OIP.RHX29_wpn-1FSEUJae2jwgHaEK?w=321&h=180&c=7&r=0&o=5&cb=iwc2&pid=1.7',
            'https://th.bing.com/th/id/OIP.56cC238AcgBam2khD8-TswHaEo?w=283&h=180&c=7&r=0&o=5&cb=iwc2&pid=1.7'
        ]
    };

    // Sample comments for each attraction
    const attractionComments = {
        'casbah': [
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

    // Function to update card links with current images and comments
    function updateCardLinks() {
        const cards = document.querySelectorAll('a[href*="card-details.html"]');
        cards.forEach(card => {
            const href = card.getAttribute('href');
            const params = new URLSearchParams(href.split('?')[1]);
            const id = params.get('id');
            const title = params.get('title');

            // Get images and comments for this attraction
            const images = attractions[id];
            const comments = attractionComments[id] || [];

            if (images) {
                // Encode the images and comments arrays
                const encodedImages = encodeURIComponent(JSON.stringify(images));
                const encodedComments = encodeURIComponent(JSON.stringify(comments));

                // Update the href with the new encoded data
                card.href = `card-details.html?id=${id}&title=${title}&images=${encodedImages}&comments=${encodedComments}`;
            }
        });
    }

    // Initialize galleries if they exist
    const galleries = document.querySelectorAll('.image-gallery');
    if (galleries.length > 0) {
        galleries.forEach(gallery => {
            const galleryName = gallery.dataset.gallery;
            const images = attractions[galleryName];

            if (!images) {
                console.warn('No images found for gallery:', galleryName);
                return;
            }

            const img = gallery.querySelector('img');
            const dotsContainer = gallery.parentElement.querySelector('.dots-container');
            let currentIndex = 0;
            const maxVisibleDots = 5;

            function createDots() {
                dotsContainer.innerHTML = '';
                const totalImages = images.length;
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
                img.src = images[currentIndex];
                createDots(); // Recreate dots to show the correct range
            }

            // Initial dots creation
            createDots();

            const prevBtn = gallery.querySelector('.nav-arrow.prev');
            const nextBtn = gallery.querySelector('.nav-arrow.next');

            if (prevBtn && nextBtn) {
                prevBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const newIndex = (currentIndex - 1 + images.length) % images.length;
                    updateImage(newIndex);
                });

                nextBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const newIndex = (currentIndex + 1) % images.length;
                    updateImage(newIndex);
                });
            }
        });
    }

    // Call updateCardLinks after DOM is loaded
    updateCardLinks();
});

// Login form submission
document.querySelector('#loginModal form').addEventListener('submit', function (e) {
    e.preventDefault();
    const email = this.querySelector('input[type="email"]').value;
    const password = this.querySelector('input[type="password"]').value;

    // Here you would typically make an API call to verify credentials
    // For demo purposes, we'll just set a flag in localStorage
    localStorage.setItem('isLoggedIn', 'true');
    localStorage.setItem('userEmail', email);

    // Update UI to show logged in state
    const loginButton = document.querySelector('button[onclick="showLoginModal()"]');
    loginButton.textContent = 'Mon Compte';
    loginButton.onclick = showUserMenu;

    hideLoginModal();
});

// Logout functionality
function logout() {
    localStorage.removeItem('isLoggedIn');
    localStorage.removeItem('userEmail');

    // Update UI to show logged out state
    const loginButton = document.querySelector('button[onclick="showUserMenu()"]');
    loginButton.textContent = 'Se Connecter';
    loginButton.onclick = showLoginModal;

    hideUserMenu();
}

// Check authentication state on page load
document.addEventListener('DOMContentLoaded', function () {
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    if (isLoggedIn) {
        const loginButton = document.querySelector('button[onclick="showLoginModal()"]');
        loginButton.textContent = 'Mon Compte';
        loginButton.onclick = showUserMenu;
    }

    // Add event listener for the login button in auth warning modal
    const authWarningLoginBtn = document.querySelector('#authWarningModal button:last-child');
    if (authWarningLoginBtn) {
        authWarningLoginBtn.addEventListener('click', function () {
            hideAuthWarningModal();
            showLoginModal();
        });
    }
});

// Add Place functionality
function checkAuthAndShowAddPlaceForm() {
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    if (isLoggedIn) {
        showAddPlaceModal();
    } else {
        showAuthWarningModal();
    }
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

// Handle add place form submission
document.querySelector('#addPlaceForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    console.log('Form submitted:', Object.fromEntries(formData));
    hideAddPlaceModal();
}); 