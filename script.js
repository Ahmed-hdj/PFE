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