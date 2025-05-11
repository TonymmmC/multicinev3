document.addEventListener('DOMContentLoaded', function() {
    // Manejar clics en los tags de sugerencias
    const suggestionTags = document.querySelectorAll('.suggestion-tag');
    const searchInput = document.querySelector('.search-input');
    const searchForm = document.querySelector('.search-form');
    
    suggestionTags.forEach(tag => {
        tag.addEventListener('click', function() {
            const searchValue = this.getAttribute('data-search-value');
            if (searchInput && searchForm) {
                searchInput.value = searchValue;
                searchForm.submit();
            }
        });
    });
});