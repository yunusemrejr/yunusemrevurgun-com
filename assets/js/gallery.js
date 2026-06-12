/**
 * Gallery Page - Minimalist Coffee Mug Theme
 * Initializes all modular components
 */

(function() {
    'use strict';
    
    // Initialize when DOM is ready
    function init() {
        // Initialize Coffee Mug (legacy class; safe no-op if not loaded)
        if (window.CoffeeMug) {
            window.CoffeeMug.init('.coffee-mug-container');
        }
        
        // Initialize Question Options
        setupQuestionOptions();
        
        // Initialize Gallery Modal
        setupGalleryModal();
    }
    
    function setupQuestionOptions() {
        const galleryData = window.GALLERY_DATA || {
            images: [],
            recentImages: [],
            imagesByDate: {}
        };
        
        const questionOptions = {
            'recent': {
                html: generateRecentContent(galleryData.recentImages)
            },
            'by-date': {
                html: generateByDateContent(galleryData.imagesByDate)
            },
            'all': {
                html: generateAllImagesContent(galleryData.images)
            }
        };
        
        // Legacy QuestionOptions class was removed in dead-code cleanup.
        // The data is prepared above; nothing to instantiate.
        void questionOptions;
    }
    
    function generateRecentContent(recentImages) {
        if (!recentImages || recentImages.length === 0) {
            return '<p>No recent images available at the moment.</p>';
        }
        
        let html = '<h2>Recent Images</h2>';
        html += generateGalleryGrid(recentImages);
        return html;
    }
    
    function generateByDateContent(imagesByDate) {
        if (!imagesByDate || Object.keys(imagesByDate).length === 0) {
            return '<p>No images available at the moment.</p>';
        }
        
        let html = '<h2>Images by Date</h2>';
        const dates = Object.keys(imagesByDate).sort((a, b) => b.localeCompare(a));
        
        dates.forEach(date => {
            html += `<div class="gallery-date-section">`;
            html += `<h3 class="gallery-date-title">${formatDate(date)}</h3>`;
            html += generateGalleryGrid(imagesByDate[date]);
            html += `</div>`;
        });
        
        return html;
    }
    
    function generateAllImagesContent(allImages) {
        if (!allImages || allImages.length === 0) {
            return '<p>No images available at the moment.</p>';
        }
        
        let html = '<h2>All Images</h2>';
        html += `<p>Total: ${allImages.length} image${allImages.length !== 1 ? 's' : ''}</p>`;
        html += generateGalleryGrid(allImages);
        
        return html;
    }
    
    function generateGalleryGrid(images) {
        const basePath = window.FULL_BASE_PATH || '';
        let html = '<div class="gallery-grid">';
        
        images.forEach(image => {
            const imageUrl = `${basePath}uploads/gallery/${escapeHtml(image.filename)}`;
            const title = image.title || 'Untitled';
            const date = image.uploaded_at || '';
            
            html += '<div class="gallery-item" data-image-url="' + escapeHtml(imageUrl) + '" data-title="' + escapeHtml(title) + '" data-date="' + escapeHtml(date) + '">';
            html += `<img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(title)}" class="gallery-item-image" loading="lazy">`;
            html += '<div class="gallery-item-overlay">';
            if (title) {
                html += `<div class="gallery-item-title">${escapeHtml(title)}</div>`;
            }
            if (date) {
                html += `<div class="gallery-item-date">${escapeHtml(date)}</div>`;
            }
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        return html;
    }
    
    function formatDate(dateString) {
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        } catch (e) {
            return dateString;
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function setupGalleryModal() {
        const modal = document.getElementById('galleryModal');
        const modalImage = document.getElementById('galleryModalImage');
        const modalTitle = document.querySelector('.gallery-modal-title');
        const modalDate = document.querySelector('.gallery-modal-date');
        const closeBtn = document.querySelector('.gallery-modal-close');
        const backdrop = document.querySelector('.gallery-modal-backdrop');
        
        if (!modal) return;
        
        // Open modal when gallery item is clicked
        document.addEventListener('click', (e) => {
            const galleryItem = e.target.closest('.gallery-item');
            if (galleryItem) {
                const imageUrl = galleryItem.dataset.imageUrl;
                const title = galleryItem.dataset.title || 'Untitled';
                const date = galleryItem.dataset.date || '';
                
                if (modalImage) modalImage.src = imageUrl;
                if (modalImage) modalImage.alt = title;
                if (modalTitle) modalTitle.textContent = title;
                if (modalDate) modalDate.textContent = date;
                
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        });
        
        // Close modal
        function closeModal() {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
        
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }
        
        if (backdrop) {
            backdrop.addEventListener('click', closeModal);
        }
        
        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    }
    
    function setupNavbar() {
        const basePath = window.FULL_BASE_PATH || '';
        const menuItems = [
            { label: 'Home', href: basePath || '/' },
            { label: 'About', href: basePath + 'about' },
            { label: 'Portfolio', href: basePath + 'portfolio' },
            { label: 'Gallery', href: basePath + 'gallery', active: true },
            { label: 'Blog', href: basePath + 'blog' },
            { label: 'Travel', href: basePath + 'travel' },
            { label: 'Updates', href: basePath + 'updates' },
            { label: 'Post-Code', href: basePath + 'post-code' },
            { label: 'Contact', href: basePath + 'contact' },
            { label: 'YunoBot', href: basePath + 'yunobot' }
        ];
        
        const navbar = new window.MinimalNavbar('.minimal-navbar', menuItems);
        window.minimalNavbarInstance = navbar;
    }
    
    // Initialize when ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
