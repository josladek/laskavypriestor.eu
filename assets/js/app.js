// Láskavý Priestor - JavaScript funkcionalita

// Inicializácia po načítaní stránky
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide flash messages
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            if (alert.querySelector('.btn-close')) {
                const alertInstance = new bootstrap.Alert(alert);
                setTimeout(() => alertInstance.close(), 5000);
            }
        });
    }, 100);
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize modals
    const modalTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="modal"]'));
    modalTriggerList.map(function (modalTriggerEl) {
        return new bootstrap.Modal(modalTriggerEl);
    });
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Form validation enhancement
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                
                // Focus on first invalid field
                const firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
            form.classList.add('was-validated');
        });
        
        // Real-time validation
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (form.classList.contains('was-validated')) {
                    this.classList.toggle('is-valid', this.checkValidity());
                    this.classList.toggle('is-invalid', !this.checkValidity());
                }
            });
        });
    });
    
    // Dynamic credit calculator
    const creditInputs = document.querySelectorAll('.credit-calculator');
    creditInputs.forEach(input => {
        input.addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            const rate = parseFloat(this.dataset.rate) || 10;
            const credits = amount / rate;
            
            const display = document.querySelector(this.dataset.target);
            if (display) {
                display.textContent = credits.toFixed(1);
            }
        });
    });
    
    // Auto-refresh for time-sensitive content
    if (window.location.pathname.includes('/dashboard') || 
        window.location.pathname.includes('/admin')) {
        setInterval(function() {
            // Refresh specific elements every 30 seconds
            const refreshElements = document.querySelectorAll('[data-auto-refresh]');
            refreshElements.forEach(element => {
                const url = element.dataset.refreshUrl;
                if (url) {
                    fetch(url)
                        .then(response => response.text())
                        .then(html => {
                            element.innerHTML = html;
                        })
                        .catch(console.error);
                }
            });
        }, 30000);
    }
});

// Utility functions
function formatPrice(amount) {
    return new Intl.NumberFormat('sk-SK', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2
    }).format(amount);
}

function formatDate(dateString) {
    return new Intl.DateTimeFormat('sk-SK', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).format(new Date(dateString));
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = `
        top: 100px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
    `;
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// AJAX helper function
function ajaxRequest(url, options = {}) {
    const defaults = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    return fetch(url, Object.assign(defaults, options))
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        });
}

// Class registration helper
function registerForClass(classId, useCredit = false) {
    const formData = new FormData();
    formData.append('class_id', classId);
    formData.append('use_credit', useCredit ? '1' : '0');
    
    return fetch('/api/register-class.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            // Refresh the page or update UI
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.message, 'danger');
        }
        return data;
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Nastala chyba pri registrácii.', 'danger');
    });
}

// Navbar scroll effect
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        if (window.scrollY > 50) {
            navbar.style.background = 'rgba(255, 255, 255, 0.98)';
            navbar.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        } else {
            navbar.style.background = 'rgba(255, 255, 255, 0.95)';
            navbar.style.boxShadow = 'none';
        }
    }
});

// Image lazy loading
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
    
    document.querySelectorAll('img[data-src]').forEach(img => {
        imageObserver.observe(img);
    });
}