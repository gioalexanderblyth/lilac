/**
 * Toast Notification System
 * Provides user-friendly notifications instead of alert() dialogs
 */

(function() {
    'use strict';

    class ToastNotification {
        constructor() {
            this.container = null;
            this.initializeContainer();
        }

        // FIX: Create container if it doesn't exist - with proper DOM readiness check
        initializeContainer() {
            // Try to find existing container
            this.container = document.getElementById('toast-container');
            
            if (this.container) {
                return; // Container already exists
            }
            
            // Wait for body to be available
            const tryCreate = () => {
                if (!document.body) {
                    return false;
                }
                
                try {
                    this.container = document.createElement('div');
                    this.container.id = 'toast-container';
                    this.container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; pointer-events: none;';
                    document.body.appendChild(this.container);
                    console.log('Toast container created and appended to DOM');
                    return true;
                } catch (error) {
                    console.error('Error creating toast container:', error);
                    return false;
                }
            };
            
            // Try immediately if body is available
            if (tryCreate()) {
                return;
            }
            
            // If body not ready, wait for DOMContentLoaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    if (!this.container) {
                        tryCreate();
                    }
                });
            } else {
                // DOMContentLoaded already fired, but body might still be null
                // Use polling as fallback
                const checkInterval = setInterval(() => {
                    if (tryCreate()) {
                        clearInterval(checkInterval);
                    }
                }, 50);
                
                // Timeout after 2 seconds
                setTimeout(() => {
                    clearInterval(checkInterval);
                    if (!this.container) {
                        console.warn('Toast container could not be created after 2 seconds');
                    }
                }, 2000);
            }
        }

        // Get or create container (called when needed)
        getOrCreateContainer() {
            if (this.container && document.body && document.body.contains(this.container)) {
                return this.container;
            }
            
            // Try to find existing one
            this.container = document.getElementById('toast-container');
            if (this.container && document.body && document.body.contains(this.container)) {
                return this.container;
            }
            
            // Try to create it
            this.initializeContainer();
            return this.container;
        }

        // Show notification with null check
        show(message, type = 'info', duration = 3000) {
            try {
                // Ensure container exists
                if (!this.container) {
                    this.container = this.getOrCreateContainer();
                }
                
                if (!this.container) {
                    console.error('Toast container not available');
                    // Fallback to console
                    console.log(`[Toast ${type.toUpperCase()}]: ${message}`);
                    return;
                }

                const toast = document.createElement('div');
                toast.className = `toast-notification toast-${type} transform translate-x-full opacity-0 transition-all duration-300 ease-in-out`;
                
                // Determine icon and colors based on type
                const icons = {
                    success: 'check_circle',
                    error: 'error',
                    warning: 'warning',
                    info: 'info'
                };

                const colors = {
                    success: 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200',
                    error: 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200',
                    warning: 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800 text-yellow-800 dark:text-yellow-200',
                    info: 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200'
                };

                toast.innerHTML = `
                    <div class="border rounded-lg shadow-lg p-4 ${colors[type] || colors.info} flex items-start gap-3">
                        <span class="material-symbols-outlined flex-shrink-0">${icons[type] || icons.info}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium break-words">${this.escapeHtml(message)}</p>
                        </div>
                        <button onclick="this.closest('.toast-notification').remove()" class="flex-shrink-0 ml-2 hover:opacity-70 transition-opacity">
                            <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                    </div>
                `;

                // Safe append with error handling - ensure container is in DOM
                const container = this.getOrCreateContainer();
                if (!container) {
                    console.error('Toast container not available');
                    console.log(`[Toast ${type.toUpperCase()}]: ${message}`);
                    return null;
                }
                
                // Double-check container is in DOM
                if (!document.body || !document.body.contains(container)) {
                    console.warn('Toast container not in DOM, attempting to re-append');
                    if (document.body) {
                        try {
                            document.body.appendChild(container);
                        } catch (e) {
                            console.error('Failed to append container to body:', e);
                            console.log(`[Toast ${type.toUpperCase()}]: ${message}`);
                            return null;
                        }
                    } else {
                        console.error('document.body not available');
                        console.log(`[Toast ${type.toUpperCase()}]: ${message}`);
                        return null;
                    }
                }
                
                try {
                    container.appendChild(toast);
                } catch (error) {
                    console.error('Error appending toast to container:', error);
                    console.log(`[Toast ${type.toUpperCase()}]: ${message}`);
                    return null;
                }

                // Animate in
                setTimeout(() => {
                    toast.classList.remove('translate-x-full', 'opacity-0');
                }, 10);

                // Auto remove after duration
                if (duration > 0) {
                    setTimeout(() => {
                        toast.classList.add('translate-x-full', 'opacity-0');
                        setTimeout(() => {
                            if (toast.parentNode) {
                                toast.remove();
                            }
                        }, 300);
                    }, duration);
                }

                return toast;
            } catch (error) {
                console.error('Error displaying toast:', error);
                // Fallback to console
                console.log(`[Toast ${type.toUpperCase()}]: ${message}`);
                return null;
            }
        }

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize with error handling
    let toastInstance = null;
    
    try {
        toastInstance = new ToastNotification();
    } catch (error) {
        console.error('Toast initialization failed:', error);
        // Create fallback object
        toastInstance = {
            show: (msg, type) => console.log(`[${type || 'info'}] ${msg}`)
        };
    }

    // Export to window object
    function showToast(message, type = 'info', duration = 3000) {
        if (toastInstance && toastInstance.show) {
            return toastInstance.show(message, type, duration);
        } else {
            console.log(`[Toast ${type.toUpperCase()}]: ${message}`);
        }
    }

    window.showToast = showToast;
    window.showSuccess = (message, duration) => showToast(message, 'success', duration);
    window.showError = (message, duration) => showToast(message, 'error', duration || 5000);
    window.showWarning = (message, duration) => showToast(message, 'warning', duration);
    window.showInfo = (message, duration) => showToast(message, 'info', duration);

    // Replace alert() with toast notifications globally
    const originalAlert = window.alert;
    window.alert = function(message) {
        console.warn('alert() called. Consider using showToast() instead.');
        showToast(message, 'info', 5000);
        return originalAlert(message);
    };

})();
