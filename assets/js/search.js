/**
 * Search Page JavaScript
 * Handles search form validation and interactive elements
 * Follows JavaScript separation rules - no inline JS allowed
 */

class SearchManager {
    constructor() {
        this.searchForm = null;
        this.searchInput = null;
        this.validationFeedback = null;
        this.isInitialized = false;
        
        this.init();
    }
    
    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupSearch());
        } else {
            this.setupSearch();
        }
    }
    
    setupSearch() {
        // Get form elements - updated selectors for new design
        this.searchForm = document.querySelector('.search-form');
        this.searchInput = document.querySelector('.search-input');
        this.validationFeedback = document.getElementById('searchValidationFeedback');
        
        if (!this.searchForm || !this.searchInput) {
            console.warn('Search form elements not found');
            return;
        }
        
        // Add form submission handler
        this.searchForm.addEventListener('submit', (e) => {
            const query = this.searchInput.value.trim();
            if (!this.validateSearchQuery(query)) {
                e.preventDefault();
                this.showValidationError();
                return false;
            }
            // Allow form to submit normally
        });
        
        // Add real-time validation
        this.searchInput.addEventListener('input', () => {
            this.validateSearchInput();
        });
        
        // Add keyboard shortcuts
        this.setupKeyboardShortcuts();
        
        // Add search result interactions
        this.setupSearchResultInteractions();
        
        this.isInitialized = true;
    }
    
    handleFormSubmission() {
        const query = this.searchInput.value.trim();
        
        if (this.validateSearchQuery(query)) {
            // Submit the form
            this.searchForm.submit();
        } else {
            this.showValidationError();
        }
    }
    
    validateSearchInput() {
        const query = this.searchInput.value.trim();
        
        if (query.length > 0) {
            this.hideValidationError();
        }
        
        // Real-time character count feedback
        this.updateCharacterCount(query);
    }
    
    validateSearchQuery(query) {
        // Check minimum length
        if (query.length < 2) {
            return false;
        }
        
        // Check maximum length
        if (query.length > 60) {
            return false;
        }
        
        // Check for valid characters (letters, numbers, spaces, and common punctuation)
        const validPattern = /^[a-zA-Z0-9\s\-_.,!?()]+$/;
        if (!validPattern.test(query)) {
            return false;
        }
        
        // Check for excessive whitespace
        if (query !== query.replace(/\s+/g, ' ')) {
            return false;
        }
        
        return true;
    }
    
    showValidationError() {
        if (this.validationFeedback) {
            this.validationFeedback.classList.add('show');
        }
        this.searchInput.style.borderColor = '#ff6b6b';
        this.searchInput.style.boxShadow = '0 0 0 3px rgba(255, 107, 107, 0.2)';
        
        // Focus on input
        this.searchInput.focus();
        
        // Reset border after 2 seconds
        setTimeout(() => {
            this.searchInput.style.borderColor = '';
            this.searchInput.style.boxShadow = '';
        }, 2000);
    }
    
    hideValidationError() {
        if (this.validationFeedback) {
            this.validationFeedback.classList.remove('show');
        }
        this.searchInput.style.borderColor = '';
        this.searchInput.style.boxShadow = '';
    }
    
    updateCharacterCount(query) {
        // Character count functionality removed as requested
        // Validation still occurs but without visual feedback
    }
    
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Focus search input with Ctrl+K or Cmd+K
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.searchInput.focus();
                this.searchInput.select();
            }
            
            // Clear search with Escape
            if (e.key === 'Escape' && document.activeElement === this.searchInput) {
                this.searchInput.value = '';
                this.hideValidationError();
            }
        });
    }
    
    setupSearchResultInteractions() {
        // Add click tracking for search results
        const searchResults = document.querySelectorAll('.search-result-item, .search-portfolio-card');
        
        searchResults.forEach(result => {
            result.addEventListener('click', (e) => {
                // Track search result clicks (for analytics if needed)
                const resultTitle = result.querySelector('.search-result-title, .search-portfolio-title');
                if (resultTitle) {
                    console.log('Search result clicked:', resultTitle.textContent);
                }
            });
        });
        
        // Add hover effects for better UX
        searchResults.forEach(result => {
            result.addEventListener('mouseenter', () => {
                result.style.cursor = 'pointer';
            });
        });
    }
    
    // Public method to validate search form (for external use)
    validateSearchForm() {
        const query = this.searchInput?.value.trim() || '';
        return this.validateSearchQuery(query);
    }
    
    // Cleanup method
    destroy() {
        if (this.searchForm) {
            this.searchForm.removeEventListener('submit', this.handleFormSubmission);
        }
        
        if (this.searchInput) {
            this.searchInput.removeEventListener('input', this.validateSearchInput);
        }
        
        this.isInitialized = false;
    }
}

// Setup navbar menu items
function setupNavbar() {
    const basePath = window.FULL_BASE_PATH || '';
    const menuItems = [
        { label: 'Home', href: basePath || '/' },
        { label: 'About', href: basePath + 'about' },
        { label: 'Portfolio', href: basePath + 'portfolio' },
        { label: 'Gallery', href: basePath + 'gallery' },
        { label: 'Blog', href: basePath + 'blog' },
        { label: 'Travel', href: basePath + 'travel' },
        { label: 'Updates', href: basePath + 'updates' },
        { label: 'Post-Code', href: basePath + 'post-code' },
        { label: 'Contact', href: basePath + 'contact' },
        { label: 'YunoBot', href: basePath + 'yunobot' },
        { label: 'Search', href: basePath + 'search', active: true }
    ];
    
    if (window.MinimalNavbar) {
        const navbar = new window.MinimalNavbar('.minimal-navbar', menuItems);
        window.minimalNavbarInstance = navbar;
    }
}

// Initialize search functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize navbar
    setupNavbar();
    
    // Initialize search manager
    const searchManager = new SearchManager();
    
    // Make validateSearchForm globally available for backward compatibility
    window.validateSearchForm = () => searchManager.validateSearchForm();
    
    // Add search result animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, observerOptions);
    
    // Observe search results for animation
    const searchResults = document.querySelectorAll('.search-result-item, .search-portfolio-card');
    searchResults.forEach(result => observer.observe(result));
    
    // Add search highlighting
    const searchQuery = new URLSearchParams(window.location.search).get('q');
    if (searchQuery) {
        highlightSearchTerms(searchQuery);
    }
    
    // Add copy search URL functionality
    const copySearchUrl = () => {
        const currentUrl = window.location.href;
        navigator.clipboard.writeText(currentUrl).then(() => {
            // Show feedback
            const feedback = document.createElement('div');
            feedback.textContent = 'Search URL copied to clipboard';
            feedback.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--color-green-medium);
                color: var(--color-yellow-light);
                padding: var(--space-sm);
                border-radius: var(--border-radius-md);
                font-family: 'Montserrat';
                font-size: 0.875rem;
                z-index: 1000;
                box-shadow: var(--shadow-md);
            `;
            document.body.appendChild(feedback);
            
            setTimeout(() => {
                feedback.remove();
            }, 3000);
        });
    };
    
    // Add keyboard shortcut for copying search URL
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'c' && e.shiftKey) {
            e.preventDefault();
            copySearchUrl();
        }
    });
});

// Function to highlight search terms in results
function highlightSearchTerms(query) {
    const searchTerms = query.toLowerCase().split(/\s+/).filter(term => term.length > 2);
    
    if (searchTerms.length === 0) return;
    
    const resultElements = document.querySelectorAll('.search-result-excerpt, .search-portfolio-description');
    
    resultElements.forEach(element => {
        let text = element.textContent;
        let highlightedText = text;
        
        searchTerms.forEach(term => {
            const regex = new RegExp(`(${term})`, 'gi');
            highlightedText = highlightedText.replace(regex, '<mark style="background: var(--color-yellow-light); color: var(--color-blue-darkest); padding: 0.1em 0.2em; border-radius: 0.2em;">$1</mark>');
        });
        
        if (highlightedText !== text) {
            element.innerHTML = highlightedText;
        }
    });
}

// Export for potential external use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SearchManager;
}
