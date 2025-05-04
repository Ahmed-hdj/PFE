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

// Close modal when clicking outside
document.addEventListener('click', function (event) {
    const modal = document.getElementById('loginModal');
    if (event.target === modal) {
        hideLoginModal();
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

    function getVisibleCards() {
        if (window.innerWidth >= 1280) return 4; // xl breakpoint
        if (window.innerWidth >= 1024) return 3; // lg breakpoint
        if (window.innerWidth >= 640) return 2;  // sm breakpoint
        return 1; // mobile
    }

    function updateCarousel() {
        const cardWidth = cityCards[0].offsetWidth;
        const visibleCards = getVisibleCards();
        const maxPosition = -(cardWidth * (cityCards.length - visibleCards));

        // Adjust position if needed
        if (currentPosition < maxPosition) {
            currentPosition = maxPosition;
            citiesContainer.style.transform = `translateX(${currentPosition}px)`;
        }

        // Update buttons state
        prevButton.style.opacity = currentPosition === 0 ? '0.5' : '1';
        nextButton.style.opacity = currentPosition <= maxPosition ? '0.5' : '1';
        prevButton.disabled = currentPosition === 0;
        nextButton.disabled = currentPosition <= maxPosition;
    }

    prevButton.addEventListener('click', () => {
        if (currentPosition < 0) {
            const cardWidth = cityCards[0].offsetWidth;
            currentPosition += cardWidth;
            citiesContainer.style.transform = `translateX(${currentPosition}px)`;
            updateCarousel();
        }
    });

    nextButton.addEventListener('click', () => {
        const cardWidth = cityCards[0].offsetWidth;
        const visibleCards = getVisibleCards();
        const maxPosition = -(cardWidth * (cityCards.length - visibleCards));

        if (currentPosition > maxPosition) {
            currentPosition -= cardWidth;
            citiesContainer.style.transform = `translateX(${currentPosition}px)`;
            updateCarousel();
        }
    });

    // Initialize carousel
    updateCarousel();

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            currentPosition = 0;
            citiesContainer.style.transform = 'translateX(0)';
            updateCarousel();
        }, 250);
    });
});

// Attraction Images Navigation
document.addEventListener('DOMContentLoaded', function () {
    const attractions = {
        jardin: [
            'https://c1.staticflickr.com/1/276/32193070856_d5137fac58_h.jpg',
            'https://th.bing.com/th/id/R.0380587d9cf79d5940352d9f9ff21a18?rik=q28o%2bkBOoQh%2b%2bQ&pid=ImgRaw&r=0',
            'https://www.algerie-eco.com/wp-content/uploads/2018/07/jardin-dessai.jpg'
        ],
        basilique: [
            'https://tse1.mm.bing.net/th/id/OIP.C2dYgf0zNB2GfgzH3PIF5AHaEu?cb=iwc1&rs=1&pid=ImgDetMain',
            'https://dynamic-media-cdn.tripadvisor.com/media/photo-o/0c/70/54/dd/basilique-notre-dame-d.jpg?w=1200&h=-1&s=1',
            'https://upload.wikimedia.org/wikipedia/commons/c/c6/Basilique_Notre_Dame_d%27Afrique_Alger.JPG'
        ],
        casbah: [
            'https://c1.staticflickr.com/1/507/32124243810_ebb256b3a1_h.jpg',
            'https://www.elkhadra.com/fr/wp-content/uploads/2015/05/Casbah-ALGER.jpg',
            'https://th.bing.com/th/id/R.9d06a8e732ab805b85473b2ff13525ed?rik=bgjsgCZLrQDgcw&riu=http%3a%2f%2fprescriptor.info%2fimages%2f1731.jpg&ehk=uQSIIufIPWyPnAg5%2bc%2fWZTW%2bC1mr4LIRRp8GRcAuwbA%3d&risl=&pid=ImgRaw&r=0'
        ],

    };

    // Initialize galleries if they exist
    const galleries = document.querySelectorAll('.image-gallery');
    if (galleries.length > 0) {
        console.log('Initializing', galleries.length, 'image galleries');

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
            } else {
                console.warn('Navigation buttons not found for gallery:', galleryName);
            }
        });
    }
}); 