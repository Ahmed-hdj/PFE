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

<!-- Signup Modal -->
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
        <form id="addPlaceForm" class="space-y-4" method="POST" enctype="multipart/form-data">
            <div>
                <label class="block text-gray-700 mb-2">Nom du lieu</label>
                <input type="text" name="title" class="w-full px-3 py-2 border rounded-full" placeholder="Entrez le nom du lieu" required>
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Description</label>
                <textarea name="description" class="w-full px-3 py-2 border rounded-lg" rows="3" placeholder="Entrez la description du lieu" required></textarea>
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Location</label>
                <textarea name="location" class="w-full px-3 py-2 border rounded-lg" rows="3" placeholder="Entrez la location du lieu" required></textarea>
            </div>

            <div>
                <label class="block text-gray-700 mb-2">Wilaya</label>
                <select name="wilaya" class="w-full px-3 py-2 border rounded-full" required>
                    <option value="">Sélectionnez une wilaya</option>
                    <?php
                    try {
                        $wilayas_query = "SELECT * FROM wilayas ORDER BY wilaya_number";
                        $wilayas_stmt = $pdo->query($wilayas_query);
                        $wilayas = $wilayas_stmt->fetchAll();
                        foreach ($wilayas as $wilaya): ?>
                            <option value="<?php echo htmlspecialchars($wilaya['wilaya_number']); ?>">
                                <?php echo htmlspecialchars($wilaya['wilaya_number'] . ' - ' . $wilaya['wilaya_name']); ?>
                            </option>
                        <?php endforeach;
                    } catch(PDOException $e) {
                        echo "Error: " . $e->getMessage();
                    }
                    ?>
                </select>
            </div>

            <div>
                <label class="block text-gray-700 mb-2">Catégorie</label>
                <select name="category" class="w-full px-3 py-2 border rounded-full" required>
                    <option value="">Sélectionnez une catégorie</option>
                    <?php
                    try {
                        $categories_query = "SELECT * FROM categories ORDER BY category_name";
                        $categories_stmt = $pdo->query($categories_query);
                        $categories = $categories_stmt->fetchAll();
                        foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['category_id']); ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach;
                    } catch(PDOException $e) {
                        echo "Error: " . $e->getMessage();
                    }
                    ?>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 mb-2">Images</label>
                <input type="file" name="images[]" class="w-full px-3 py-2 border rounded-full" multiple accept="image/*" required>
            </div>
            <button type="submit" name="add_place" class="w-full bg-green-500 text-white py-2 rounded-full hover:bg-green-600 transition-colors cursor-pointer">
                Ajouter le lieu
            </button>
        </form>
    </div>
</div>

<!-- Auth Warning Modal -->
<div id="authWarningModal" class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
    <div class="bg-white p-8 rounded-lg w-[400px] shadow-xl">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold">Authentication Required</h2>
            <button onclick="hideAuthWarningModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <p class="text-gray-600 mb-6">Please log in to continue.</p>
        <div class="flex justify-end gap-4">
            <button onclick="hideAuthWarningModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors cursor-pointer">
                Cancel
            </button>
            <button onclick="handleAuthWarningLogin()" class="px-4 py-2 bg-blue-500 text-white rounded-full hover:bg-blue-600 transition-colors cursor-pointer">
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

<!-- Rating Modal -->
<div id="ratingModal"
    class="fixed inset-0 bg-black/70 backdrop-blur-[1px] hidden items-center justify-center z-50 transition-opacity duration-200">
    <div class="bg-white p-8 rounded-lg w-96 shadow-xl">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold">Rate this Place</h2>
            <button onclick="hideRatingModal()" class="text-gray-500 hover:text-gray-700 cursor-pointer">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="ratingForm" class="space-y-4">
            <input type="hidden" id="ratingLieuId" name="lieu_id">
            <div class="flex justify-center gap-2 mb-4">
                <button type="button" class="rating-star text-3xl text-gray-300 hover:text-yellow-400 transition-colors" data-rating="1">
                    <i class="fas fa-star"></i>
                </button>
                <button type="button" class="rating-star text-3xl text-gray-300 hover:text-yellow-400 transition-colors" data-rating="2">
                    <i class="fas fa-star"></i>
                </button>
                <button type="button" class="rating-star text-3xl text-gray-300 hover:text-yellow-400 transition-colors" data-rating="3">
                    <i class="fas fa-star"></i>
                </button>
                <button type="button" class="rating-star text-3xl text-gray-300 hover:text-yellow-400 transition-colors" data-rating="4">
                    <i class="fas fa-star"></i>
                </button>
                <button type="button" class="rating-star text-3xl text-gray-300 hover:text-yellow-400 transition-colors" data-rating="5">
                    <i class="fas fa-star"></i>
                </button>
            </div>
            <input type="hidden" name="rating" id="ratingValue" value="0">
            <button type="submit"
                class="w-full bg-blue-500 text-white py-2 rounded-full hover:bg-blue-600 transition-colors cursor-pointer">
                Submit Rating
            </button>
        </form>
    </div>
</div>

<script>
// Auth Warning Modal Functions
function showAuthWarningModal() {
    document.getElementById('authWarningModal').classList.remove('hidden');
    document.getElementById('authWarningModal').classList.add('flex');
}

function hideAuthWarningModal() {
    document.getElementById('authWarningModal').classList.remove('flex');
    document.getElementById('authWarningModal').classList.add('hidden');
}

function handleAuthWarningLogin() {
    hideAuthWarningModal();
    showLoginModal();
}
</script> 