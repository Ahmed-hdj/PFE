function showLoginModal() {
    document.getElementById('loginModal').classList.remove('hidden');
    document.getElementById('loginModal').classList.add('flex');
}

function hideLoginModal() {
    document.getElementById('loginModal').classList.add('hidden');
    document.getElementById('loginModal').classList.remove('flex');
}

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
            'https://www.algerie-eco.com/wp-content/uploads/2018/07/jardin-dessai.jpg',
            'https://i.pinimg.com/originals/e1/fd/1e/e1fd1e0c4cad1b34c92f34aad2f980f1.jpg',
            'https://www.memoria.dz/wp-content/uploads/2021/01/Jardin-dessai-du-Hamma.jpg'
        ],
        basilique: [
            'https://tse1.mm.bing.net/th/id/OIP.C2dYgf0zNB2GfgzH3PIF5AHaEu?cb=iwc1&rs=1&pid=ImgDetMain',
            'https://dynamic-media-cdn.tripadvisor.com/media/photo-o/0c/70/54/dd/basilique-notre-dame-d.jpg?w=1200&h=-1&s=1',
            'https://upload.wikimedia.org/wikipedia/commons/c/c6/Basilique_Notre_Dame_d%27Afrique_Alger.JPG',
            'https://live.staticflickr.com/2845/33762601661_4f2c02490b_b.jpg',
            'https://www.algerie-monde.com/wp-content/uploads/2017/11/Basilique-Notre-Dame-dAfrique-Alger-768x512.jpg'
        ],
        casbah: [
            'https://c1.staticflickr.com/1/507/32124243810_ebb256b3a1_h.jpg',
            'https://www.elkhadra.com/fr/wp-content/uploads/2015/05/Casbah-ALGER.jpg',
            'https://th.bing.com/th/id/R.9d06a8e732ab805b85473b2ff13525ed?rik=bgjsgCZLrQDgcw&riu=http%3a%2f%2fprescriptor.info%2fimages%2f1731.jpg&ehk=uQSIIufIPWyPnAg5%2bc%2fWZTW%2bC1mr4LIRRp8GRcAuwbA%3d&risl=&pid=ImgRaw&r=0',
            'https://tse3.mm.bing.net/th/id/OIP.YneqOETf3OgP2JvXMvMiUgHaE7?cb=iwc1&rs=1&pid=ImgDetMain',
            'https://tse1.mm.bing.net/th/id/OIP.tMojzaC8DyhyGBXP-xDu8AHaJw?cb=iwc1&w=600&h=790&rs=1&pid=ImgDetMain',
            'https://tse3.mm.bing.net/th/id/OIP.sqPButrSPwz7DJ6unr_wQAHaE7?cb=iwc1&rs=1&pid=ImgDetMain',
            'https://th.bing.com/th/id/R.af0c811cf2ae38e67a46ec1463278498?rik=eLnW%2biaWENr%2bYA&riu=http%3a%2f%2fwww.tresorsdumonde.fr%2fwp-content%2fuploads%2f2015%2f04%2f42.jpg&ehk=6aoFbrn9NwWhgmTyZ9qXqsqaxqME3%2fl%2b4hq2afIxuqY%3d&risl=&pid=ImgRaw&r=0',
            'https://tse1.mm.bing.net/th/id/OIP.2w61wfHufLFW53zu2-D1jQHaEK?cb=iwc1&rs=1&pid=ImgDetMain',
            'https://tse1.mm.bing.net/th/id/OIP.SpTB_SqHmDQ4Zt-1smnk_gHaE7?cb=iwc1&rs=1&pid=ImgDetMain'
        ]
    };

    document.querySelectorAll('.image-gallery').forEach(gallery => {
        const galleryName = gallery.dataset.gallery;
        const images = attractions[galleryName];
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

        gallery.querySelector('.nav-arrow.prev').addEventListener('click', (e) => {
            e.preventDefault();
            const newIndex = (currentIndex - 1 + images.length) % images.length;
            updateImage(newIndex);
        });

        gallery.querySelector('.nav-arrow.next').addEventListener('click', (e) => {
            e.preventDefault();
            const newIndex = (currentIndex + 1) % images.length;
            updateImage(newIndex);
        });
    });
}); 