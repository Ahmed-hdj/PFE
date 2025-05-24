<div class="relative h-72 image-gallery" data-gallery="<?php echo $place['lieu_id']; ?>">
    <img src="<?php echo htmlspecialchars($firstImage); ?>"
        alt="<?php echo htmlspecialchars($place['title']); ?>"
        class="w-full h-full object-cover transition-opacity duration-300 hover:opacity-80">
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
    <div class="absolute top-4 left-4 z-30">
        <button onclick="event.preventDefault(); showLieuOptions(<?php echo $place['lieu_id']; ?>)"
            class="bg-white p-2 rounded-full shadow-lg hover:bg-gray-100 transition-colors">
            <i class="fas fa-ellipsis-v text-gray-600"></i>
        </button>
        <div id="lieuOptions<?php echo $place['lieu_id']; ?>" class="hidden absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-40">
            <a href="modify-lieu.php?id=<?php echo $place['lieu_id']; ?>" class="block px-4 py-2 hover:bg-gray-100">
                <i class="fas fa-edit mr-2"></i> Modify
            </a>
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
        class="absolute top-4 right-4 bg-white p-2 rounded-full shadow-lg hover:bg-gray-100 transition-colors z-30 w-10">
        <i class="far fa-bookmark text-gray-600"></i>
    </button>
</div> 