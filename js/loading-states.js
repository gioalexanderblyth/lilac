/**
 * Loading States Utility
 * Provides loading indicators and skeleton loaders
 */

(function() {
    'use strict';

    /**
     * Create a loading spinner
     */
    function createSpinner(size = 'medium') {
        const sizes = {
            small: 'w-4 h-4',
            medium: 'w-8 h-8',
            large: 'w-12 h-12'
        };

        const spinner = document.createElement('div');
        spinner.className = `loading-spinner inline-block ${sizes[size] || sizes.medium}`;
        spinner.innerHTML = `
            <svg class="animate-spin ${sizes[size] || sizes.medium} text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        `;
        return spinner;
    }

    /**
     * Show loading overlay
     */
    function showLoadingOverlay(message = 'Loading...') {
        const overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.className = 'fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center';
        overlay.innerHTML = `
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 flex flex-col items-center gap-4">
                ${createSpinner('large').outerHTML}
                <p class="text-gray-700 dark:text-gray-300 font-medium">${escapeHtml(message)}</p>
            </div>
        `;
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
        return overlay;
    }

    /**
     * Hide loading overlay
     */
    function hideLoadingOverlay() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.remove();
            document.body.style.overflow = '';
        }
    }

    /**
     * Create skeleton loader
     */
    function createSkeletonLoader(type = 'card') {
        const loaders = {
            card: `
                <div class="animate-pulse bg-gray-200 dark:bg-gray-700 rounded-lg p-6">
                    <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-3/4 mb-4"></div>
                    <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-full mb-2"></div>
                    <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-5/6"></div>
                </div>
            `,
            list: `
                <div class="animate-pulse space-y-4">
                    <div class="h-12 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    <div class="h-12 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    <div class="h-12 bg-gray-200 dark:bg-gray-700 rounded"></div>
                </div>
            `,
            table: `
                <div class="animate-pulse">
                    <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded mb-2"></div>
                    <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded mb-2"></div>
                    <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded mb-2"></div>
                </div>
            `
        };

        const div = document.createElement('div');
        div.innerHTML = loaders[type] || loaders.card;
        return div.firstElementChild;
    }

    /**
     * Wrap async function with loading state
     */
    function withLoading(asyncFn, message = 'Loading...') {
        return async function(...args) {
            const overlay = showLoadingOverlay(message);
            try {
                const result = await asyncFn.apply(this, args);
                hideLoadingOverlay();
                return result;
            } catch (error) {
                hideLoadingOverlay();
                throw error;
            }
        };
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Export to window
    window.createSpinner = createSpinner;
    window.showLoadingOverlay = showLoadingOverlay;
    window.hideLoadingOverlay = hideLoadingOverlay;
    window.createSkeletonLoader = createSkeletonLoader;
    window.withLoading = withLoading;

})();

