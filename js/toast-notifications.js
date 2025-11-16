/**
 * Toast Notification System
 * Provides user-friendly notifications instead of alert() dialogs
 */

(function() {
    'use strict';

    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 flex flex-col gap-3 max-w-sm w-full';
        document.body.appendChild(toastContainer);
    }

    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} type - Type of notification: 'success', 'error', 'warning', 'info'
     * @param {number} duration - Duration in milliseconds (default: 3000)
     */
    function showToast(message, type = 'info', duration = 3000) {
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
                    <p class="text-sm font-medium break-words">${escapeHtml(message)}</p>
                </div>
                <button onclick="this.closest('.toast-notification').remove()" class="flex-shrink-0 ml-2 hover:opacity-70 transition-opacity">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>
        `;

        toastContainer.appendChild(toast);

        // Animate in
        setTimeout(() => {
            toast.classList.remove('translate-x-full', 'opacity-0');
        }, 10);

        // Auto remove after duration
        if (duration > 0) {
            setTimeout(() => {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        return toast;
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Export to window object
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

