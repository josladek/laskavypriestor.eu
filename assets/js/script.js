// Láskavý Priestor - Main JavaScript

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize components
    initFormValidation();
    initTooltips();
    initImageLazyLoading();
    initScrollAnimations();
    initNavigationHighlight();
    initClassFilters();
    initRegistrationForms();
});

// Form Validation
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
}

// Bootstrap Tooltips
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Lazy Loading for Images
function initImageLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback for older browsers
        images.forEach(img => {
            img.src = img.dataset.src;
            img.classList.remove('lazy');
        });
    }
}

// Scroll Animations
function initScrollAnimations() {
    const animateElements = document.querySelectorAll('.animate-on-scroll');
    
    if ('IntersectionObserver' in window) {
        const scrollObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, {
            threshold: 0.1
        });
        
        animateElements.forEach(el => scrollObserver.observe(el));
    }
}

// Navigation Active State
function initNavigationHighlight() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentPath) {
            link.classList.add('active');
        }
    });
}

// Class Filters
function initClassFilters() {
    const filterForm = document.getElementById('classFilters');
    if (!filterForm) return;
    
    const filterInputs = filterForm.querySelectorAll('select, input');
    const classCards = document.querySelectorAll('.class-card');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', filterClasses);
    });
    
    function filterClasses() {
        const filters = {
            type: document.getElementById('typeFilter')?.value || '',
            level: document.getElementById('levelFilter')?.value || '',
            date: document.getElementById('dateFilter')?.value || '',
            instructor: document.getElementById('instructorFilter')?.value || ''
        };
        
        classCards.forEach(card => {
            const cardData = {
                type: card.dataset.type || '',
                level: card.dataset.level || '',
                date: card.dataset.date || '',
                instructor: card.dataset.instructor || ''
            };
            
            const shouldShow = Object.keys(filters).every(key => {
                return !filters[key] || cardData[key] === filters[key];
            });
            
            if (shouldShow) {
                card.style.display = 'block';
                card.classList.add('fade-in');
            } else {
                card.style.display = 'none';
                card.classList.remove('fade-in');
            }
        });
        
        updateResultsCount();
    }
    
    function updateResultsCount() {
        const visibleCards = document.querySelectorAll('.class-card:not([style*="display: none"])');
        const countElement = document.getElementById('resultsCount');
        if (countElement) {
            countElement.textContent = `${visibleCards.length} ${visibleCards.length === 1 ? 'lekcia' : 'lekcií'}`;
        }
    }
}

// Registration Forms
function initRegistrationForms() {
    const registrationForms = document.querySelectorAll('.registration-form');
    
    registrationForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const button = form.querySelector('button[type="submit"]');
            const originalText = button.textContent;
            
            // Show loading state
            button.disabled = true;
            button.innerHTML = '<span class="spinner"></span> Registrujem...';
            
            // Submit form data
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message || 'Registrácia bola úspešná!');
                    if (data.redirect) {
                        setTimeout(() => window.location.href = data.redirect, 1500);
                    }
                } else {
                    showAlert('error', data.message || 'Nastala chyba pri registrácii.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Nastala chyba pri komunikácii so serverom.');
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = originalText;
            });
        });
    });
}

// Alert System
function showAlert(type, message, duration = 5000) {
    const alertContainer = document.getElementById('alertContainer') || createAlertContainer();
    
    const alertElement = document.createElement('div');
    alertElement.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
    alertElement.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alertElement);
    
    // Auto dismiss
    setTimeout(() => {
        if (alertElement.parentNode) {
            alertElement.remove();
        }
    }, duration);
}

function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alertContainer';
    container.className = 'position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

// Credit Package Selection
function selectCreditPackage(packageId) {
    const packages = document.querySelectorAll('.pricing-card');
    packages.forEach(card => card.classList.remove('selected'));
    
    const selectedCard = document.querySelector(`[data-package-id="${packageId}"]`);
    if (selectedCard) {
        selectedCard.classList.add('selected');
    }
    
    // Update form
    const packageInput = document.getElementById('selectedPackage');
    if (packageInput) {
        packageInput.value = packageId;
    }
}

// Registration Confirmation
function confirmRegistration(className, price, isCredit) {
    const paymentMethod = isCredit ? 'kreditom' : 'na mieste';
    const message = `Naozaj sa chcete registrovať na ${className}?\nCena: ${price}€ (platba ${paymentMethod})`;
    
    return confirm(message);
}

// Cancellation Confirmation
function confirmCancellation(className, refundAmount) {
    let message = `Naozaj chcete zrušiť registráciu na ${className}?`;
    if (refundAmount > 0) {
        message += `\nBude vám vrátený kredit vo výške ${refundAmount}€.`;
    }
    
    return confirm(message);
}

// Format Date for Display
function formatDate(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        weekday: 'long'
    };
    return new Date(dateString).toLocaleDateString('sk-SK', options);
}

// Format Time for Display
function formatTime(timeString) {
    return timeString.substring(0, 5); // HH:MM format
}

// Smooth Scroll to Element
function scrollToElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

// Update URL without page reload
function updateURL(newURL) {
    if (history.pushState) {
        history.pushState(null, null, newURL);
    }
}

// Debounce Function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Throttle Function
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Copy to Clipboard
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showAlert('success', 'Skopírované do schránky!', 2000);
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            showAlert('success', 'Skopírované do schránky!', 2000);
        } catch (err) {
            showAlert('error', 'Nepodarilo sa skopírovať', 2000);
        }
        document.body.removeChild(textArea);
    }
}

// Export functions for global use
window.YogaApp = {
    showAlert,
    selectCreditPackage,
    confirmRegistration,
    confirmCancellation,
    formatDate,
    formatTime,
    scrollToElement,
    copyToClipboard
};