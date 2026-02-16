/**
 * Notification Bar System
 * Displays notification bars for MOU/MOA notifications across all pages
 */

(function() {
    'use strict';
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Helper function to get time ago
    function getTimeAgo(dateString) {
        if (!dateString) return 'Just now';
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
        return date.toLocaleDateString();
    }
    
    // Get notifications from global scope or return empty array
    function getNotifications() {
        if (typeof window.getNotifications === 'function') {
            return window.getNotifications();
        }
        // Try to get from common variable names
        if (typeof notifications !== 'undefined' && Array.isArray(notifications)) {
            return notifications;
        }
        return [];
    }
    
    function getMouExpiredBarSeenSet() {
        if (!window.__mouExpiredBarSeenSet) {
            let ids = [];
            try {
                const raw = localStorage.getItem('mou_expired_bar_seen_ids_v1');
                if (raw) ids = JSON.parse(raw) || [];
            } catch (_) {
                ids = [];
            }
            window.__mouExpiredBarSeenSet = new Set((ids || []).map(v => String(v)));
        }
        return window.__mouExpiredBarSeenSet;
    }
    
    function shouldShowMouExpiredBar(notificationId) {
        if (notificationId == null) return true;
        
        // For expired MOUs: Always show if notification exists (not yet renewed)
        // Check if notification still exists in current notifications
        try {
            const currentNotifications = getNotifications();
            if (currentNotifications && Array.isArray(currentNotifications) && currentNotifications.length > 0) {
                const notificationExists = currentNotifications.some(
                    n => n.id == notificationId && n.type === 'mou_expired' && n.related_type === 'mou_moa'
                );
                
                if (notificationExists) {
                    // Notification exists = MOU is still expired and not renewed
                    // Always show it, clear localStorage so it can show again
                    removeMouBarSeenId(notificationId);
                    return true;
                } else {
                    // Notification no longer exists (was renewed/deleted)
                    removeMouBarSeenId(notificationId);
                    return false;
                }
            }
        } catch (_) {
            // If getNotifications fails, allow showing (will be verified when loaded)
        }
        
        // If notifications not loaded yet, allow showing (expired MOUs should persist until renewed)
        return true;
    }
    
    function markMouExpiredBarSeen(notificationId) {
        if (notificationId == null) return;
        const set = getMouExpiredBarSeenSet();
        set.add(String(notificationId));
        try {
            localStorage.setItem('mou_expired_bar_seen_ids_v1', JSON.stringify(Array.from(set)));
            // Store timestamp for cleanup purposes
            try {
                const raw = localStorage.getItem('mou_expired_bar_seen_timestamps_v1');
                const timestamps = raw ? JSON.parse(raw) : {};
                timestamps[String(notificationId)] = Date.now();
                localStorage.setItem('mou_expired_bar_seen_timestamps_v1', JSON.stringify(timestamps));
            } catch (_) {
                // ignore timestamp storage failures
            }
        } catch (_) {
            // ignore storage failures
        }
    }
    
    function removeMouBarSeenId(notificationId) {
        if (notificationId == null) return;
        const set = getMouExpiredBarSeenSet();
        if (set.has(String(notificationId))) {
            set.delete(String(notificationId));
            try {
                localStorage.setItem('mou_expired_bar_seen_ids_v1', JSON.stringify(Array.from(set)));
                // Also remove from timestamps if it exists
                try {
                    const raw = localStorage.getItem('mou_expired_bar_seen_timestamps_v1');
                    if (raw) {
                        const timestamps = JSON.parse(raw) || {};
                        delete timestamps[String(notificationId)];
                        localStorage.setItem('mou_expired_bar_seen_timestamps_v1', JSON.stringify(timestamps));
                    }
                } catch (_) {
                    // ignore timestamp cleanup failures
                }
                window.__mouExpiredBarSeenSet = null;
            } catch (_) {
                // ignore storage failures
            }
        }
    }
    
    function getOrCreateMouNotificationContainer() {
        let container = document.getElementById('mouNotificationBarsContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'mouNotificationBarsContainer';
            container.className = 'fixed bottom-4 right-4 z-[10000] flex flex-col items-end max-w-sm';
            document.body.appendChild(container);
        }
        return container;
    }
    
    function buildMouNotificationCard(notification) {
        const card = document.createElement('div');
        const notificationId = 'mou-notif-' + (notification?.id ?? 'unknown') + '-' + Date.now();
        card.id = notificationId;
        card.dataset.notificationId = notification?.id != null ? String(notification.id) : '';
        card.dataset.notificationType = notification?.type || '';
        
        const isExpired = notification?.type === 'mou_expired';
        const bgColor = isExpired ? 'bg-red-500' : 'bg-yellow-500';
        const icon = isExpired ? 'warning' : 'schedule';
        const iconColor = isExpired ? 'text-red-50' : 'text-yellow-50';
        
        card.className = `${bgColor} text-white rounded-lg p-4 transform transition-all duration-300 translate-x-full opacity-0 mou-notification-card`;
        card.style.minWidth = '320px';
        card.style.maxWidth = '400px';
        card.style.zIndex = 10001;
        card.setAttribute('data-related-type', notification?.related_type || '');
        card.setAttribute('data-related-id', notification?.related_id || '');
        
        card.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-full ${isExpired ? 'bg-red-600' : 'bg-yellow-600'} flex items-center justify-center">
                    <span class="material-symbols-outlined ${iconColor}">${icon}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="font-semibold text-sm mb-1 line-clamp-1">${escapeHtml(notification?.title || 'MOU/MOA Notification')}</h4>
                    <p class="text-xs opacity-90 line-clamp-2">${escapeHtml(notification?.message || '')}</p>
                    <p class="text-xs opacity-75 mt-1">${getTimeAgo(notification?.created_at)}</p>
                    <p class="text-[11px] opacity-80 mt-2">Drag right to dismiss</p>
                </div>
                <button onclick="window.removeNotificationPaper && window.removeNotificationPaper(this)" 
                        class="flex-shrink-0 text-white hover:text-gray-200 transition-colors opacity-70 hover:opacity-100 z-10 relative">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>
        `;
        
        // Click to open MOU/MOA Details modal or navigate to page
        if (notification?.related_id && notification?.related_type === 'mou_moa') {
            card.addEventListener('click', function(e) {
                // Don't trigger if it was a drag/swipe
                if (card.dataset.suppressClick === '1') return;
                // Don't trigger if clicking on a button
                if (e.target.closest('button')) return;
                
                // Try to open modal if function exists, otherwise navigate
                if (typeof window.showMouDetails === 'function') {
                    (async function() {
                        try {
                            const response = await fetch(`api/mou-moa.php?action=get&id=${notification.related_id}`);
                            const result = await response.json();
                            
                            if (result.success && result.data) {
                                window.showMouDetails(result.data);
                                
                                // Ensure modal is visible
                                const modal = document.getElementById('mouDetailsModal');
                                if (modal) {
                                    modal.classList.remove('hidden');
                                    modal.style.display = 'flex';
                                }
                                
                                // Highlight the entry if function exists
                                if (typeof highlightEntry === 'function') {
                                    highlightEntry(parseInt(notification.related_id));
                                }
                            } else {
                                window.location.href = `mou-moa.php?entry=${notification.related_id}`;
                            }
                        } catch (error) {
                            console.error('Error loading entry:', error);
                            window.location.href = `mou-moa.php?entry=${notification.related_id}`;
                        }
                    })();
                } else {
                    // Navigate to MOU/MOA page
                    window.location.href = `mou-moa.php?entry=${notification.related_id}`;
                }
                
                // Once opened, move to the next queued notification
                dismissCurrentMouNotificationBar(card, { animate: false });
            });
        }
        
        attachSwipeToDismiss(card);
        return card;
    }
    
    function renderNextMouNotificationBar() {
        if (!window.__mouBarQueue || window.__mouBarQueue.length === 0) {
            const existing = document.getElementById('mouNotificationBarsContainer');
            if (existing && existing.children.length === 0) existing.remove();
            return;
        }
        if (window.__mouBarShowing) return;
        
        const container = getOrCreateMouNotificationContainer();
        // Ensure only one card is visible at a time
        container.innerHTML = '';
        
        const next = window.__mouBarQueue.shift();
        const card = buildMouNotificationCard(next);
        container.appendChild(card);
        window.__mouBarShowing = true;
        
        // Animate in
        setTimeout(() => {
            card.classList.remove('translate-x-full');
            card.style.transform = 'translateX(0)';
            card.style.opacity = '1';
        }, 50);
    }
    
    function dismissCurrentMouNotificationBar(card, { animate = true } = {}) {
        const container = document.getElementById('mouNotificationBarsContainer');
        const el = card || (container ? container.querySelector('.mou-notification-card') : null);
        if (!el) {
            window.__mouBarShowing = false;
            renderNextMouNotificationBar();
            return;
        }
        
        // Mark "mou_expired" notifications as seen
        if (el.dataset.notificationType === 'mou_expired' && el.dataset.notificationId) {
            markMouExpiredBarSeen(el.dataset.notificationId);
        }
        
        if (!animate) {
            el.remove();
            window.__mouBarShowing = false;
            if (container && container.children.length === 0 && (!window.__mouBarQueue || window.__mouBarQueue.length === 0)) {
                container.remove();
            }
            renderNextMouNotificationBar();
            return;
        }
        
        el.style.transition = 'transform 0.25s ease, opacity 0.25s ease';
        el.style.transform = `translateX(${Math.max(el.offsetWidth, 360) + 80}px)`;
        el.style.opacity = '0';
        setTimeout(() => {
            if (el.parentElement) el.remove();
            window.__mouBarShowing = false;
            if (container && container.children.length === 0 && (!window.__mouBarQueue || window.__mouBarQueue.length === 0)) {
                container.remove();
            }
            renderNextMouNotificationBar();
        }, 260);
    }
    
    function attachSwipeToDismiss(card) {
        let startX = 0;
        let currentX = 0;
        let dragging = false;
        let moved = false;
        
        const onPointerDown = (e) => {
            // Ignore right-click / non-primary mouse button
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            // Don't start swipe from the close button
            if (e.target.closest('button')) return;
            
            dragging = true;
            moved = false;
            startX = e.clientX;
            currentX = 0;
            card.classList.add('mou-dragging');
            card.dataset.suppressClick = '0';
            try { card.setPointerCapture(e.pointerId); } catch (_) {}
        };
        
        const onPointerMove = (e) => {
            if (!dragging) return;
            const dx = e.clientX - startX;
            currentX = Math.max(0, dx); // only allow dragging to the right
            // Only consider it a "move" if moved more than 20px to avoid suppressing legitimate clicks
            if (currentX > 20) moved = true;
            
            const rotate = Math.min(6, currentX / 40); // subtle
            const opacity = Math.max(0.2, 1 - currentX / 320);
            card.style.transform = `translateX(${currentX}px) rotate(${rotate}deg)`;
            card.style.opacity = String(opacity);
        };
        
        const finish = () => {
            card.classList.remove('mou-dragging');
            const threshold = Math.min(140, (card.offsetWidth || 320) * 0.35);
            if (currentX >= threshold) {
                card.dataset.suppressClick = '1';
                dismissCurrentMouNotificationBar(card, { animate: true });
                return;
            }
            // Snap back
            card.style.transition = 'transform 0.2s ease, opacity 0.2s ease';
            card.style.transform = 'translateX(0)';
            card.style.opacity = '1';
            // Only suppress click if there was significant movement (more than 20px)
            if (moved && currentX > 20) {
                card.dataset.suppressClick = '1';
                setTimeout(() => { card.dataset.suppressClick = '0'; }, 250);
            } else {
                // Clear suppress flag immediately for small movements
                card.dataset.suppressClick = '0';
            }
        };
        
        const onPointerUp = (e) => {
            if (!dragging) return;
            dragging = false;
            try { card.releasePointerCapture(e.pointerId); } catch (_) {}
            finish();
        };
        
        const onPointerCancel = () => {
            if (!dragging) return;
            dragging = false;
            finish();
        };
        
        card.addEventListener('pointerdown', onPointerDown);
        card.addEventListener('pointermove', onPointerMove);
        card.addEventListener('pointerup', onPointerUp);
        card.addEventListener('pointercancel', onPointerCancel);
    }
    
    function removeNotificationPaper(button) {
        const card = button.closest('.mou-notification-card');
        dismissCurrentMouNotificationBar(card, { animate: true });
    }
    
    /**
     * Show notification bar for MOU/MOA notifications
     * @param {Object} notification - Notification object
     */
    function showMouNotificationBar(notification) {
        // Check if user has disabled notification bars
        const barsEnabled = localStorage.getItem('notification_bars_enabled') !== 'false';
        if (!barsEnabled) {
            console.log('Notification bars are disabled');
            return;
        }
        
        if (!notification || !notification.id) {
            console.error('Invalid notification object:', notification);
            return;
        }
        
        // For expired MOUs: Always show if notification exists (not yet renewed)
        if (notification?.type === 'mou_expired' && notification?.id != null) {
            if (!shouldShowMouExpiredBar(notification.id)) {
                console.log('Notification bar blocked for notification ID:', notification.id);
                return;
            }
        }
        
        // Play sound when notification bar appears for MOU/MOA notifications
        const isMouNotification = (notification.type === 'mou_expiring' || notification.type === 'mou_expired');
        if (isMouNotification) {
            console.log('[MOU Notification Bar] Playing sound for notification:', notification.id, notification.type, 'related_type:', notification.related_type);
            
            // Check if this notification has already played sound
            const hasPlayedSound = window.NotificationSound && 
                                 window.NotificationSound._hasPlayedSound && 
                                 window.NotificationSound._hasPlayedSound(notification.id);
            
            if (!hasPlayedSound) {
                // Directly call play function - bypass filter since we know it's a MOU notification
                if (window.NotificationSound && window.NotificationSound.play) {
                    console.log('[MOU Notification Bar] Directly calling NotificationSound.play()');
                    window.NotificationSound.play();
                    
                    // Mark this notification as having played sound
                    if (window.NotificationSound._markAsPlayed) {
                        window.NotificationSound._markAsPlayed(notification.id);
                    }
                } else if (window.NotificationSound && window.NotificationSound.checkAndPlay) {
                    console.log('[MOU Notification Bar] Calling NotificationSound.checkAndPlay');
                    window.NotificationSound.checkAndPlay([notification]);
                } else if (window.checkAndPlayMouNotificationSound) {
                    console.log('[MOU Notification Bar] Calling checkAndPlayMouNotificationSound wrapper');
                    window.checkAndPlayMouNotificationSound([notification]);
                } else {
                    console.warn('[MOU Notification Bar] NotificationSound not available!');
                }
            } else {
                console.log('[MOU Notification Bar] Sound already played for notification:', notification.id);
            }
        } else {
            console.log('[MOU Notification Bar] Not a MOU notification:', notification.type, notification.related_type);
        }
        
        console.log('Showing notification bar for:', notification.title, 'ID:', notification.id);
        
        // Initialize queue if needed
        if (!window.__mouBarQueue) window.__mouBarQueue = [];
        if (!window.__mouBarShowing) window.__mouBarShowing = false;
        if (!window.__mouBarEnqueuedIds) window.__mouBarEnqueuedIds = new Set();
        
        // Prevent duplicates within a single page load
        if (notification && notification.id != null && window.__mouBarEnqueuedIds.has(notification.id)) {
            return;
        }
        if (notification && notification.id != null) {
            window.__mouBarEnqueuedIds.add(notification.id);
        }
        
        window.__mouBarQueue.push(notification);
        renderNextMouNotificationBar();
    }
    
    /**
     * Process notifications and show bars for MOU/MOA notifications
     * @param {Array} notifications - Array of notification objects
     * @param {Array} previousNotifications - Previous notifications array (optional, for detecting new ones)
     */
    function processNotificationsForBars(notifications, previousNotifications) {
        if (!notifications || !Array.isArray(notifications)) {
            return;
        }
        
        // Get previous notification IDs if provided
        const previousIds = previousNotifications && Array.isArray(previousNotifications) 
            ? new Set(previousNotifications.map(n => n.id))
            : new Set();
        
        // Filter for MOU/MOA notifications
        const mouNotifications = notifications.filter(n => 
            (n.type === 'mou_expiring' || n.type === 'mou_expired') && 
            n.related_type === 'mou_moa'
        );
        
        // Show bars for new notifications or all on first load
        if (previousIds.size === 0) {
            // First load - show all expired MOU notifications
            const expiredMouNotifications = mouNotifications.filter(n => n.type === 'mou_expired');
            expiredMouNotifications.forEach(notif => {
                showMouNotificationBar(notif);
            });
        } else {
            // Show bars for new notifications only
            const newMouNotifications = mouNotifications.filter(n => !previousIds.has(n.id));
            newMouNotifications.forEach(notif => {
                showMouNotificationBar(notif);
            });
        }
    }
    
    // Make functions globally accessible
    window.showMouNotificationBar = showMouNotificationBar;
    window.removeNotificationPaper = removeNotificationPaper;
    window.processNotificationsForBars = processNotificationsForBars;
    
    // Initialize queue variables
    if (!window.__mouBarQueue) window.__mouBarQueue = [];
    if (!window.__mouBarShowing) window.__mouBarShowing = false;
    if (!window.__mouBarEnqueuedIds) window.__mouBarEnqueuedIds = new Set();
    
    console.log('[NotificationBar] Notification bar system initialized');
})();
