// Initialize search functionality
document.addEventListener('DOMContentLoaded', function() {
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

    if (searchForm && searchInput && suggestionsContainer) {
        let searchTimeout;

        // Search input handler
        searchInput.addEventListener('input', function() {
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
                }
            }, 300);
        });

        // Search form submission handler
        searchForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log('Search form submitted');
            const searchQuery = searchInput.value.trim();

            if (!searchQuery) {
                console.log('Empty search query, returning');
                return;
            }

            try {
                console.log('Sending search request for:', searchQuery);
                const response = await fetch('search.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `search=${encodeURIComponent(searchQuery)}`
                });

                console.log('Search response received');
                const result = await response.json();
                console.log('Search result:', result);
                
                if (result.success) {
                    const container = document.getElementById('attractionsContainer') || document.querySelector('.grid');
                    if (!container) {
                        console.error('No container found for search results');
                        return;
                    }
                    
                    container.innerHTML = ''; // Clear existing content

                    if (result.data.length === 0) {
                        container.innerHTML = '<div class="col-span-full text-center py-8"><p class="text-gray-500 text-lg">No places found matching your search.</p></div>';
                        return;
                    }

                    result.data.forEach(place => {
                        const card = createLieuCard ? createLieuCard(place) : createPlaceCard(place);
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
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                suggestionsContainer.classList.add('hidden');
            }
        });
    } else {
        console.error('Search elements not found:', {
            searchForm: !!searchForm,
            searchInput: !!searchInput,
            suggestionsContainer: !!suggestionsContainer
        });
    }
});

// Make handleSuggestionClick available globally
window.handleSuggestionClick = function(type, id, text) {
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