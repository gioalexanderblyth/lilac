// Global Notification and Confirmation System
// Replaces browser alert(), confirm(), and prompt() with custom modals

// Toast Notification System
function showToast(message, type = 'info', duration = 5000) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-[10000] p-4 rounded-lg shadow-2xl max-w-sm transform transition-all duration-300 translate-x-full`;
    
    // Set colors based on type
    const colors = {
        success: 'bg-green-500 dark:bg-green-600 text-white',
        error: 'bg-red-500 dark:bg-red-600 text-white',
        warning: 'bg-yellow-500 dark:bg-yellow-600 text-white',
        info: 'bg-blue-500 dark:bg-blue-600 text-white'
    };
    
    const icons = {
        success: 'check_circle',
        error: 'error',
        warning: 'warning',
        info: 'info'
    };
    
    notification.className += ` ${colors[type] || colors.info}`;
    notification.innerHTML = `
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">${icons[type] || icons.info}</span>
            <span class="text-sm font-medium flex-1">${escapeHtml(message)}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-white hover:text-gray-200 transition-colors">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Auto remove after duration
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 300);
    }, duration);
}

// Confirmation Modal System
function showConfirm(message, title = 'Confirm', confirmText = 'Confirm', cancelText = 'Cancel') {
    return new Promise((resolve) => {
        // Create modal overlay
        const modal = document.createElement('div');
        modal.id = 'confirmModal';
        modal.className = 'fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-[10001]';
        
        modal.innerHTML = `
            <div class="bg-white dark:bg-card-dark rounded-2xl shadow-2xl w-full max-w-md mx-4 border border-border-light dark:border-border-dark">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-text-light dark:text-text-dark mb-4">${escapeHtml(title)}</h3>
                    <p class="text-sm text-text-muted-light dark:text-text-muted-dark mb-6 whitespace-pre-line">${escapeHtml(message)}</p>
                    <div class="flex justify-end gap-3">
                        <button id="confirmCancel" class="px-4 py-2 text-sm rounded-lg border border-border-light dark:border-border-dark text-text-light dark:text-text-dark hover:bg-gray-100 dark:hover:bg-white/10 transition-colors">
                            ${escapeHtml(cancelText)}
                        </button>
                        <button id="confirmOk" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-primary/90 transition-colors">
                            ${escapeHtml(confirmText)}
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        const handleConfirm = () => {
            modal.remove();
            resolve(true);
        };
        
        const handleCancel = () => {
            modal.remove();
            resolve(false);
        };
        
        modal.querySelector('#confirmOk').addEventListener('click', handleConfirm);
        modal.querySelector('#confirmCancel').addEventListener('click', handleCancel);
        
        // Close on overlay click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                handleCancel();
            }
        });
        
        // Close on Escape key
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                handleCancel();
                document.removeEventListener('keydown', handleEscape);
            }
        };
        document.addEventListener('keydown', handleEscape);
    });
}

// Prompt Modal System
function showPrompt(message, title = 'Input', defaultValue = '', placeholder = '') {
    return new Promise((resolve) => {
        // Create modal overlay
        const modal = document.createElement('div');
        modal.id = 'promptModal';
        modal.className = 'fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-[10001]';
        
        modal.innerHTML = `
            <div class="bg-white dark:bg-card-dark rounded-2xl shadow-2xl w-full max-w-md mx-4 border border-border-light dark:border-border-dark">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-text-light dark:text-text-dark mb-4">${escapeHtml(title)}</h3>
                    <p class="text-sm text-text-muted-light dark:text-text-muted-dark mb-4">${escapeHtml(message)}</p>
                    <input 
                        type="text" 
                        id="promptInput" 
                        value="${escapeHtml(defaultValue)}" 
                        placeholder="${escapeHtml(placeholder)}"
                        class="w-full px-3 py-2 border border-border-light dark:border-border-dark rounded-lg bg-white dark:bg-card-dark text-text-light dark:text-text-dark focus:outline-none focus:ring-2 focus:ring-primary mb-6"
                        autofocus
                    />
                    <div class="flex justify-end gap-3">
                        <button id="promptCancel" class="px-4 py-2 text-sm rounded-lg border border-border-light dark:border-border-dark text-text-light dark:text-text-dark hover:bg-gray-100 dark:hover:bg-white/10 transition-colors">
                            Cancel
                        </button>
                        <button id="promptOk" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-primary/90 transition-colors">
                            OK
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        const input = modal.querySelector('#promptInput');
        input.focus();
        input.select();
        
        const handleConfirm = () => {
            const value = input.value;
            modal.remove();
            resolve(value);
        };
        
        const handleCancel = () => {
            modal.remove();
            resolve(null);
        };
        
        modal.querySelector('#promptOk').addEventListener('click', handleConfirm);
        modal.querySelector('#promptCancel').addEventListener('click', handleCancel);
        
        // Handle Enter key
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                handleConfirm();
            } else if (e.key === 'Escape') {
                handleCancel();
            }
        });
        
        // Close on overlay click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                handleCancel();
            }
        });
        
        // Close on Escape key
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                handleCancel();
                document.removeEventListener('keydown', handleEscape);
            }
        };
        document.addEventListener('keydown', handleEscape);
    });
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (text == null) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Replace global alert, confirm, and prompt functions
window.originalAlert = window.alert;
window.originalConfirm = window.confirm;
window.originalPrompt = window.prompt;

window.alert = function(message) {
    showToast(message, 'info', 4000);
};

window.confirm = function(message) {
    // This won't work as a direct replacement since confirm() is synchronous
    // But we'll provide a helper that can be used with await
    console.warn('window.confirm() cannot be directly replaced. Use showConfirm() instead with async/await.');
    return showConfirm(message, 'Confirm');
};

window.prompt = function(message, defaultValue) {
    // This won't work as a direct replacement since prompt() is synchronous
    // But we'll provide a helper that can be used with await
    console.warn('window.prompt() cannot be directly replaced. Use showPrompt() instead with async/await.');
    return showPrompt(message, 'Input', defaultValue || '');
};

