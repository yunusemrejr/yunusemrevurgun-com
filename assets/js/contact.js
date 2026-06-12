/**
 * Contact Page JavaScript
 * Handles interactive functionality for contact form
 * Follows JavaScript separation rules - no inline JS allowed
 */

(function() {
    'use strict';

    // DOM Ready utility function
    function domReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    // Contact Manager Class
    class ContactManager {
        constructor() {
            this.form = null;
            this.submitButton = null;
            this.isSubmitting = false;
            this.validationRules = {
                name: { required: true, minLength: 2, maxLength: 50 },
                email: { required: true, pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/ },
                subject: { required: true, minLength: 5, maxLength: 100 },
                message: { required: true, minLength: 10, maxLength: 1000 }
            };
        }

        init() {
            // Only initialize if we're on the contact page
            if (!this.isContactPage()) {
                return;
            }
            
            this.setupForm();
            this.setupValidation();
            this.setupCharacterCount();
            this.setupAccessibility();
            this.setupAnimations();
            this.setupKeyboardShortcuts();
            this.generateSubmissionId();
        }

        isContactPage() {
            // Check if we're on the contact page by looking for the contact form
            return document.getElementById('contactForm') !== null;
        }

        setupForm() {
            this.form = document.getElementById('contactForm');
            if (!this.form) return;

            this.submitButton = this.form.querySelector('.btn-submit') || this.form.querySelector('button[type="submit"]');
            
            // Add form event listeners
            this.form.addEventListener('submit', (e) => this.handleFormSubmission(e));
            this.form.addEventListener('input', (e) => this.handleFormInput(e));
            this.form.addEventListener('blur', (e) => this.handleFormBlur(e), true);
        }

        setupValidation() {
            // Add real-time validation
            if (!this.form) return;
            
            const inputs = this.form.querySelectorAll('input[type="text"], input[type="email"], textarea[name]');
            inputs.forEach(input => {
                input.addEventListener('input', () => this.validateField(input));
                input.addEventListener('blur', () => this.validateField(input));
            });
        }

        setupCharacterCount() {
            if (!this.form) return;
            
            const messageField = this.form.querySelector('#message');
            if (!messageField) return;

            // Create character count element
            const charCount = document.createElement('div');
            charCount.className = 'char-count';
            charCount.textContent = '0 / 1000 characters';
            messageField.parentNode.appendChild(charCount);

            // Update character count on input
            messageField.addEventListener('input', () => {
                const length = messageField.value.length;
                const maxLength = this.validationRules.message.maxLength;
                
                charCount.textContent = `${length} / ${maxLength} characters`;
                
                // Update styling based on character count
                charCount.classList.remove('warning', 'error');
                if (length > maxLength * 0.9) {
                    charCount.classList.add('warning');
                }
                if (length > maxLength) {
                    charCount.classList.add('error');
                }
            });
        }

        setupAccessibility() {
            if (!this.form) return;
            
            // Add ARIA labels and descriptions
            const formGroups = this.form.querySelectorAll('.form-group, .mb-3');
            formGroups.forEach(group => {
                const input = group.querySelector('.form-control');
                const label = group.querySelector('.form-label');
                
                if (input && label) {
                    input.setAttribute('aria-labelledby', label.id || this.generateId(label));
                    if (!label.id) {
                        label.id = this.generateId(label);
                    }
                }
            });

            // Add form role and aria-live region for validation messages
            this.form.setAttribute('role', 'form');
            this.form.setAttribute('aria-label', 'Contact form');
        }

        setupAnimations() {
            if (!this.form) return;
            
            // Add fade-in animation to form
            this.form.classList.add('fade-in');
            
            // Add focus effects to form groups
            const formGroups = this.form.querySelectorAll('.form-group, .mb-3');
            formGroups.forEach(group => {
                const input = group.querySelector('.form-control');
                if (input) {
                    input.addEventListener('focus', () => {
                        group.classList.add('focused');
                    });
                    
                    input.addEventListener('blur', () => {
                        if (!input.value) {
                            group.classList.remove('focused');
                        }
                    });
                }
            });
        }

        setupKeyboardShortcuts() {
            // Add keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                // Ctrl/Cmd + Enter to submit form
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    if (this.form && document.activeElement.closest('#contactForm')) {
                        e.preventDefault();
                        this.submitForm();
                    }
                }
                
                // Escape to clear form
                if (e.key === 'Escape' && this.form && document.activeElement.closest('#contactForm')) {
                    e.preventDefault();
                    this.clearForm();
                }
            });
        }

        generateSubmissionId() {
            // Generate a unique submission ID for additional protection
            const submissionIdField = document.getElementById('submissionId');
            if (submissionIdField) {
                const submissionId = this.generateRandomId();
                submissionIdField.value = submissionId;
            }
        }

        generateRandomId() {
            // Generate a random ID similar to PHP's random_bytes
            const array = new Uint8Array(16);
            crypto.getRandomValues(array);
            return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
        }

        handleFormSubmission(e) {
            e.preventDefault();
            this.submitForm();
        }

        handleFormInput(e) {
            if (e.target.matches('input[type="text"], input[type="email"], textarea[name]')) {
                this.validateField(e.target);
            }
        }

        handleFormBlur(e) {
            if (e.target.matches('input[type="text"], input[type="email"], textarea[name]')) {
                this.validateField(e.target);
            }
        }

        validateField(field) {
            const fieldName = field.name;
            const value = field.value.trim();
            const rules = this.validationRules[fieldName];
            
            if (!rules) return true;

            let isValid = true;
            let errorMessage = '';

            // Required validation
            if (rules.required && !value) {
                isValid = false;
                errorMessage = this.getRequiredMessage(fieldName);
            }
            // Length validation
            else if (value && rules.minLength && value.length < rules.minLength) {
                isValid = false;
                errorMessage = this.getMinLengthMessage(fieldName, rules.minLength);
            }
            else if (value && rules.maxLength && value.length > rules.maxLength) {
                isValid = false;
                errorMessage = this.getMaxLengthMessage(fieldName, rules.maxLength);
            }
            // Pattern validation
            else if (value && rules.pattern && !rules.pattern.test(value)) {
                isValid = false;
                errorMessage = this.getPatternMessage(fieldName);
            }

            this.updateFieldValidation(field, isValid, errorMessage);
            return isValid;
        }

        updateFieldValidation(field, isValid, errorMessage) {
            const feedback = field.parentNode.querySelector('.invalid-feedback');
            
            // Update field classes
            field.classList.remove('is-valid', 'is-invalid');
            field.classList.add(isValid ? 'is-valid' : 'is-invalid');
            
            // Update feedback message
            if (feedback) {
                feedback.textContent = errorMessage;
                feedback.style.display = isValid ? 'none' : 'block';
            }
            
            // Add shake animation for invalid fields
            if (!isValid) {
                field.classList.add('shake');
                setTimeout(() => field.classList.remove('shake'), 500);
            }
        }

        submitForm() {
            if (this.isSubmitting || !this.form) return;
            
            // Validate all fields
            const inputs = this.form.querySelectorAll('input[type="text"], input[type="email"], textarea[name]');
            let isFormValid = true;
            
            inputs.forEach(input => {
                if (!this.validateField(input)) {
                    isFormValid = false;
                }
            });
            
            if (!isFormValid) {
                this.showValidationError('Please correct the errors above before submitting.');
                return;
            }
            
            this.isSubmitting = true;
            this.setLoadingState(true);
            
            // Prepare form data
            const formData = new FormData(this.form);
            
            // Submit form via AJAX to dedicated API endpoint
            const apiPath = (window.FULL_BASE_PATH || '') + 'api/contact';
            fetch(apiPath, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If response is not JSON, it might be HTML (error page)
                    return response.text().then(text => {
                        throw new Error('Server returned HTML instead of JSON. This might indicate a server error.');
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    this.handleSubmissionSuccess(data.message);
                } else {
                    this.handleSubmissionError(data.message || 'Failed to send message. Please try again.');
                }
            })
            .catch(error => {
                console.error('Form submission error:', error);
                if (error.message.includes('HTML instead of JSON')) {
                    this.handleSubmissionError('Server error occurred. Please try again later or contact support.');
                } else {
                    this.handleSubmissionError('Failed to send message. Please try again later.');
                }
            })
            .finally(() => {
                this.isSubmitting = false;
                this.setLoadingState(false);
            });
        }

        handleSubmissionSuccess(message = 'Thank you for your message! I\'ll get back to you as soon as possible.') {
            this.setLoadingState(false);
            this.submitButton.classList.add('success');
            
            // Show success message
            this.showSuccessMessage(message);
            
            // Clear form after a delay
            setTimeout(() => {
                this.clearForm();
                this.submitButton.classList.remove('success');
            }, 3000);
        }

        handleSubmissionError(error) {
            this.setLoadingState(false);
            this.showValidationError(error);
            console.error('Form submission error:', error);
        }

        setLoadingState(loading) {
            if (!this.submitButton) return;
            if (loading) {
                this.form.classList.add('loading');
                this.submitButton.disabled = true;
                this.submitButton.textContent = 'Sending...';
            } else {
                this.form.classList.remove('loading');
                this.submitButton.disabled = false;
                this.submitButton.textContent = 'Send Message';
            }
        }

        clearForm() {
            if (!this.form) return;
            
            const inputs = this.form.querySelectorAll('input[type="text"], input[type="email"], textarea[name]');
            inputs.forEach(input => {
                input.value = '';
                input.classList.remove('is-valid', 'is-invalid');
                
                const feedback = input.parentNode.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.style.display = 'none';
                }
            });
            
            // Reset character count
            const charCount = this.form.querySelector('.char-count');
            if (charCount) {
                charCount.textContent = '0 / 1000 characters';
                charCount.classList.remove('warning', 'error');
            }
            
            // Remove focus effects
            const formGroups = this.form.querySelectorAll('.form-group, .mb-3');
            formGroups.forEach(group => group.classList.remove('focused'));
            
            // Regenerate submission ID for new submission
            this.generateSubmissionId();
        }

        showSuccessMessage(message) {
            this.showAlert(message, 'success');
        }

        showValidationError(message) {
            this.showAlert(message, 'danger');
        }

        showAlert(message, type) {
            // Remove existing alerts
            const existingAlerts = this.form.parentNode.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());
            
            // Create new alert
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alert.setAttribute('role', 'alert');
            alert.setAttribute('aria-live', 'polite');
            
            // Insert alert before form
            this.form.parentNode.insertBefore(alert, this.form);
            
            // Auto-remove alert after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }

        // Validation message helpers
        getRequiredMessage(fieldName) {
            const messages = {
                name: 'Please enter your name.',
                email: 'Please enter your email address.',
                subject: 'Please enter a subject.',
                message: 'Please enter your message.'
            };
            return messages[fieldName] || 'This field is required.';
        }

        getMinLengthMessage(fieldName, minLength) {
            const messages = {
                name: `Name must be at least ${minLength} characters long.`,
                subject: `Subject must be at least ${minLength} characters long.`,
                message: `Message must be at least ${minLength} characters long.`
            };
            return messages[fieldName] || `Must be at least ${minLength} characters long.`;
        }

        getMaxLengthMessage(fieldName, maxLength) {
            const messages = {
                name: `Name must be no more than ${maxLength} characters long.`,
                subject: `Subject must be no more than ${maxLength} characters long.`,
                message: `Message must be no more than ${maxLength} characters long.`
            };
            return messages[fieldName] || `Must be no more than ${maxLength} characters long.`;
        }

        getPatternMessage(fieldName) {
            const messages = {
                email: 'Please enter a valid email address.'
            };
            return messages[fieldName] || 'Please enter a valid value.';
        }

        generateId(element) {
            const text = element.textContent || element.getAttribute('for') || 'element';
            return text.toLowerCase().replace(/[^a-z0-9]/g, '-') + '-' + Math.random().toString(36).substr(2, 9);
        }

        destroy() {
            if (this.form) {
                this.form.removeEventListener('submit', this.handleFormSubmission);
                this.form.removeEventListener('input', this.handleFormInput);
                this.form.removeEventListener('blur', this.handleFormBlur);
            }
        }
    }

    // Initialize Contact Manager when DOM is ready
    domReady(() => {
        const contactManager = new ContactManager();
        contactManager.init();
        
        // Make it globally available for debugging
        window.contactManager = contactManager;
        
        // Initialize Minimal Navbar
        if (window.MinimalNavbar) {
            setupNavbar();
        }
    });
    
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
            { label: 'Contact', href: basePath + 'contact', active: true },
            { label: 'YunoBot', href: basePath + 'yunobot' }
        ];
        
        const navbar = new window.MinimalNavbar('.minimal-navbar', menuItems);
        window.minimalNavbarInstance = navbar;
    }

})();
