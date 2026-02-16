
        // Notification System - Reusable for all pages
        // IMPORTANT: must run after DOM is ready, otherwise elements are null and the badge never updates.
        (function () {
            let notifications = [];
            let notificationBtn, notificationDropdown, notificationBadge, notificationList, noNotifications, markAllReadBtn, viewAllNotifications;

            // Initialize notification system after DOM is ready (so notificationBtn/dropdown exist)
            function runInitNotificationSystem() {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initNotificationSystem);
                } else {
                    setTimeout(initNotificationSystem, 0);
                }
            }
            runInitNotificationSystem();

            // Fallback: ensure bell opens dropdown even if init ran before DOM (event delegation)
            document.addEventListener('click', function(e) {
                var btn = document.getElementById('notificationBtn');
                var drop = document.getElementById('notificationDropdown');
                if (!btn || !drop) return;
                if (e.target === btn || btn.contains(e.target)) {
                    e.preventDefault();
                    e.stopPropagation();
                    drop.classList.toggle('hidden');
                    if (!drop.classList.contains('hidden') && window._loadNotificationsForAwards) {
                        window._loadNotificationsForAwards();
                    }
                }
            });

            function runWhenReady(fn) {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', fn);
                } else {
                    setTimeout(fn, 0);
                }
            }
            runWhenReady(function() {
                setTimeout(function() {
                    if (typeof updateNotificationBadge === 'function') {
                        updateNotificationBadge();
                    }
                }, 150);
            });

            // Define handler functions before they're used
            function handleNotificationListClick(event) {
                // Don't handle clicks on confirmation buttons
                if (event.target.closest('button') || event.target.closest('[onclick*="confirmMouRenewal"]')) {
                    return;
                }
                
                const target = event.target.closest('[data-notification-id]');
                if (!target) return;
                event.preventDefault();
                handleNotificationSelection(target);
            }
            
            function handleNotificationListKeydown(event) {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                const target = event.target.closest('[data-notification-id]');
                if (!target) return;
                event.preventDefault();
                handleNotificationSelection(target);
            }
            
            function initNotificationSystem() {
                notificationBtn = document.getElementById('notificationBtn');
                notificationDropdown = document.getElementById('notificationDropdown');
                notificationBadge = document.getElementById('notificationBadge');
                notificationList = document.getElementById('notificationList');
                noNotifications = document.getElementById('noNotifications');
                markAllReadBtn = document.getElementById('markAllReadBtn');
                viewAllNotifications = document.getElementById('viewAllNotifications');

                if (!notificationBtn || !notificationDropdown) {
                    setTimeout(initNotificationSystem, 100);
                    return;
                }

                if (notificationList) {
                    notificationList.addEventListener('click', handleNotificationListClick);
                    notificationList.addEventListener('keydown', handleNotificationListKeydown);
                }

                // Toggle dropdown
                notificationBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('hidden');
                    if (!notificationDropdown.classList.contains('hidden')) {
                        loadNotifications();
                    }
                });
                window._loadNotificationsForAwards = loadNotifications;

            // Close dropdown when clicking outside

            document.addEventListener('click', (e) => {

                if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {

                    notificationDropdown.classList.add('hidden');

                }

            });

            

            // Create all notifications modal if it doesn't exist
            function createAllNotificationsModal() {
                if (document.getElementById('allNotificationsModal')) {
                    return; // Modal already exists
                }
                
                const modalHTML = `
                    <div id="allNotificationsModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 dark:bg-black/70 backdrop-blur-sm hidden">
                        <div class="w-full max-w-4xl bg-card-light dark:bg-card-dark rounded-xl shadow-2xl m-4 flex flex-col max-h-[90vh] border border-border-light dark:border-border-dark">
                            <!-- Modal Header -->
                            <div class="p-6 border-b border-border-light dark:border-border-dark flex-shrink-0">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-xl font-semibold text-text-light dark:text-text-dark">All Notifications</h3>
                                        <p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">Manage your MOU/MOA notifications</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button id="markAllReadModalBtn" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 dark:bg-primary/20 rounded-lg hover:bg-primary/20 dark:hover:bg-primary/30">
                                            Mark All Read
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Filter Tabs -->
                            <div class="px-6 py-4 border-b border-border-light dark:border-border-dark flex-shrink-0">
                                <div class="flex space-x-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1">
                                    <button class="notification-tab px-4 py-2 text-sm font-medium rounded-md transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm" data-filter="all">
                                        All
                                    </button>
                                    <button class="notification-tab px-4 py-2 text-sm font-medium rounded-md transition-colors text-gray-600 dark:text-gray-400" data-filter="critical">
                                        Critical
                                    </button>
                                    <button class="notification-tab px-4 py-2 text-sm font-medium rounded-md transition-colors text-gray-600 dark:text-gray-400" data-filter="unread">
                                        Unread
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Notifications List -->
                            <div id="allNotificationsList" class="flex-1 overflow-y-auto p-6">
                                <div class="text-center py-12">
                                    <span class="material-symbols-outlined text-6xl text-text-muted-light dark:text-text-muted-dark mb-4 block">notifications_off</span>
                                    <p class="text-text-muted-light dark:text-text-muted-dark text-lg">Loading notifications...</p>
                                </div>
                            </div>
                            
                            <!-- Modal Footer -->
                            <div class="p-6 border-t border-border-light dark:border-border-dark flex-shrink-0">
                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-text-muted-light dark:text-text-muted-dark">
                                        <span id="notificationsCount">0</span> notifications
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button id="clearOldNotifications" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                            Clear All
                                        </button>
                                        <button id="closeAllNotificationsModalBtn2" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-background-dark/50 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-800">
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                setupAllNotificationsModalEvents();
            }
            
            // Store notifications for filtering
            let allNotificationsData = [];
            
            // Setup event listeners for the all notifications modal
            function setupAllNotificationsModalEvents() {
                const modal = document.getElementById('allNotificationsModal');
                if (!modal) return;
                
                const closeBtn2 = document.getElementById('closeAllNotificationsModalBtn2');
                const markAllReadBtn = document.getElementById('markAllReadModalBtn');
                const clearOldBtn = document.getElementById('clearOldNotifications');
                const tabs = document.querySelectorAll('.notification-tab');
                
                // Close modal handler
                if (closeBtn2) {
                    closeBtn2.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        closeAllNotificationsModal();
                    });
                }
                
                // Close modal when clicking outside
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeAllNotificationsModal();
                    }
                });
                
                // Close modal with Escape key
                const escapeHandler = function(e) {
                    if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                        closeAllNotificationsModal();
                    }
                };
                document.addEventListener('keydown', escapeHandler);
                
                // Mark all as read
                if (markAllReadBtn) {
                    markAllReadBtn.addEventListener('click', async function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        try {
                            const response = await fetch('api/notifications.php', {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({ action: 'mark_all_read' })
                            });
                            const data = await response.json();
                            if (data.success) {
                                await loadAllNotificationsIntoModal();
                                if (typeof updateNotificationBadge === 'function') {
                                    await updateNotificationBadge();
                                }
                            }
                        } catch (error) {
                            console.error('Error marking all as read:', error);
                        }
                    });
                }
                
                // Clear all notifications
                let isClearingAll = false;
                if (clearOldBtn) {
                    clearOldBtn.addEventListener('click', async function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        if (isClearingAll) return;
                        isClearingAll = true;
                        
                        try {
                            const response = await fetch('api/notifications.php', {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({ action: 'mark_all_read' })
                            });
                            if (response.ok) {
                                await loadAllNotificationsIntoModal();
                                if (typeof updateNotificationBadge === 'function') {
                                    await updateNotificationBadge();
                                }
                            }
                        } catch (error) {
                            console.error('Error clearing notifications:', error);
                        } finally {
                            isClearingAll = false;
                        }
                    });
                }
                
                // Tab filtering
                if (tabs && tabs.length > 0) {
                    tabs.forEach(tab => {
                        tab.addEventListener('click', function() {
                            // Update active tab
                            tabs.forEach(t => {
                                t.classList.remove('bg-white', 'dark:bg-gray-700', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                                t.classList.add('text-gray-600', 'dark:text-gray-400');
                            });
                            
                            this.classList.remove('text-gray-600', 'dark:text-gray-400');
                            this.classList.add('bg-white', 'dark:bg-gray-700', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                            
                            // Filter notifications
                            const filter = this.dataset.filter;
                            filterAllNotifications(filter);
                        });
                    });
                }
            }
            
            // Filter notifications
            function filterAllNotifications(filter) {
                const modalList = document.getElementById('allNotificationsList');
                if (!modalList) return;
                
                let filteredNotifications = allNotificationsData;
                
                if (filter === 'unread') {
                    filteredNotifications = allNotificationsData.filter(n => !n.is_read);
                } else if (filter === 'critical') {
                    filteredNotifications = allNotificationsData.filter(n => 
                        n.type === 'mou_expired' || 
                        (n.type === 'mou_expiring_soon' && n.mou_days_until_expiry !== undefined && n.mou_days_until_expiry <= 3)
                    );
                }
                
                // Re-render filtered notifications
                renderNotificationsInModal(filteredNotifications);
            }
            
            // Render notifications in modal
            function renderNotificationsInModal(notifications) {
                const modalList = document.getElementById('allNotificationsList');
                const countElement = document.getElementById('notificationsCount');
                
                if (!modalList) return;
                
                if (countElement) {
                    countElement.textContent = notifications.length;
                }
                
                if (notifications.length === 0) {
                    modalList.innerHTML = `
                        <div class="text-center py-12">
                            <span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-600 mb-4 block">notifications_off</span>
                            <p class="text-gray-500 dark:text-gray-400 text-lg">No notifications</p>
                        </div>
                    `;
                    return;
                }
                
                // Use existing rendering logic from loadAllNotificationsIntoModal
                // This will be handled by updating loadAllNotificationsIntoModal to store data
            }
            
            // Show all notifications modal
            function showAllNotificationsModal() {
                createAllNotificationsModal();
                const modal = document.getElementById('allNotificationsModal');
                if (modal) {
                    modal.classList.remove('hidden');
                    loadAllNotificationsIntoModal();
                }
            }
            
            // Close all notifications modal
            function closeAllNotificationsModal() {
                const modal = document.getElementById('allNotificationsModal');
                if (modal) {
                    modal.classList.add('hidden');
                }
            }
            
            // Load all notifications into the modal
            async function loadAllNotificationsIntoModal() {
                const modalList = document.getElementById('allNotificationsList');
                const countElement = document.getElementById('notificationsCount');
                
                if (!modalList) return;
                
                try {
                    const response = await fetch('api/notifications.php');
                    const data = await response.json();
                    
                    if (data.notifications && Array.isArray(data.notifications)) {
                        let allNotifications = data.notifications;
                        
                        // Sort by created_at (most recent first)
                        allNotifications.sort((a, b) => {
                            const dateA = new Date(a.created_at);
                            const dateB = new Date(b.created_at);
                            return dateB - dateA;
                        });
                        
                        // Store notifications for filtering
                        allNotificationsData = allNotifications;
                        
                        // Render notifications
                        renderNotificationsInModal(allNotifications);
                    } else {
                        allNotificationsData = [];
                        if (countElement) {
                            countElement.textContent = 0;
                        }
                        modalList.innerHTML = `
                            <div class="text-center py-12">
                                <span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-600 mb-4 block">notifications_off</span>
                                <p class="text-gray-500 dark:text-gray-400 text-lg">No notifications</p>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error loading notifications into modal:', error);
                    modalList.innerHTML = `
                        <div class="text-center py-12">
                            <p class="text-red-500">Error loading notifications. Please try again.</p>
                        </div>
                    `;
                }
            }
            
            // Render notifications in modal (updated with full rendering logic)
            function renderNotificationsInModal(notifications) {
                const modalList = document.getElementById('allNotificationsList');
                const countElement = document.getElementById('notificationsCount');
                
                if (!modalList) return;
                
                if (countElement) {
                    countElement.textContent = notifications.length;
                }
                
                if (notifications.length === 0) {
                    modalList.innerHTML = `
                        <div class="text-center py-12">
                            <span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-600 mb-4 block">notifications_off</span>
                            <p class="text-gray-500 dark:text-gray-400 text-lg">No notifications</p>
                        </div>
                    `;
                    return;
                }
                
                modalList.innerHTML = notifications.map(notif => {
                    const timeAgo = getTimeAgo(notif.created_at);
                    const icon = getNotificationIcon(notif.type);
                    const bgColor = getNotificationBgColor(notif.type);
                    const targetUrl = getNotificationUrl(notif);
                    const urlAttribute = targetUrl ? ` data-url="${encodeURIComponent(targetUrl)}"` : '';
                    const isMouNotification = notif.related_type === 'mou_moa';
                    const isConfirmed = notif.is_confirmed || false;
                    
                    // Add Renew/Renewed buttons for MOU notifications that aren't confirmed
                    let actionButtons = '';
                    if (isMouNotification && !isConfirmed) {
                        actionButtons = `
                            <div class="mt-3 flex gap-2">
                                <button onclick="event.stopPropagation(); if(typeof confirmMouRenewal === 'function') confirmMouRenewal(${notif.id}, 'renewed', ${notif.related_id})" 
                                        class="px-3 py-1.5 text-xs font-medium bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                    Renewed
                                </button>
                                <button onclick="event.stopPropagation(); if(typeof confirmMouRenewal === 'function') confirmMouRenewal(${notif.id}, 'not_renewed', ${notif.related_id})" 
                                        class="px-3 py-1.5 text-xs font-medium bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">cancel</span>
                                    Not Renewed
                                </button>
                            </div>
                        `;
                    } else if (isMouNotification && isConfirmed) {
                        const statusText = notif.mou_renewal_status === 'renewed' ? 'Renewed' : 'Not Renewed';
                        const statusColor = notif.mou_renewal_status === 'renewed' ? 'text-green-500' : 'text-red-500';
                        actionButtons = `
                            <div class="mt-2">
                                <p class="text-xs ${statusColor} font-medium flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">${notif.mou_renewal_status === 'renewed' ? 'check_circle' : 'cancel'}</span>
                                    Status: ${statusText}
                                </p>
                            </div>
                        `;
                    }
                    
                    const actionHint = targetUrl && !isMouNotification ? '<p class="text-xs text-primary mt-2 font-semibold flex items-center gap-1">Open related record<span class="material-symbols-outlined text-sm">arrow_outward</span></p>' : '';
                    
                    return `
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors ${notif.is_read ? 'opacity-60' : ''}" 
                             data-notification-id="${notif.id}"${urlAttribute}>
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full ${bgColor} flex items-center justify-center">
                                    <span class="material-symbols-outlined text-white text-lg">${icon}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(notif.title)}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">${escapeHtml(notif.message)}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">${timeAgo}</p>
                                    ${actionHint}
                                    ${actionButtons}
                                </div>
                                ${!notif.is_read ? '<div class="flex-shrink-0 w-2 h-2 bg-primary rounded-full mt-2"></div>' : ''}
                            </div>
                        </div>
                    `;
                }).join('');
                
                // Add click handlers for notifications in modal
                modalList.querySelectorAll('[data-notification-id]').forEach(item => {
                    item.addEventListener('click', async function(e) {
                        if (e.target.closest('button')) return;
                        
                        const notificationId = item.getAttribute('data-notification-id');
                        const url = item.getAttribute('data-url');
                        
                        try {
                            await fetch(`api/notifications.php?id=${notificationId}`, {
                                method: 'PUT',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ action: 'mark_read' })
                            });
                            
                            if (typeof updateNotificationBadge === 'function') {
                                await updateNotificationBadge();
                            }
                            
                            if (url) {
                                closeAllNotificationsModal();
                                window.location.href = decodeURIComponent(url);
                            } else {
                                await loadAllNotificationsIntoModal();
                            }
                        } catch (error) {
                            console.error('Error handling notification click:', error);
                        }
                    });
                });
            }
            
            }

            // View all notifications - open modal

            if (viewAllNotifications) {

                viewAllNotifications.addEventListener('click', function(e) {

                    e.preventDefault();

                    e.stopPropagation();

                    e.stopImmediatePropagation();

                    // Close dropdown first

                    if (notificationDropdown) {

                        notificationDropdown.classList.add('hidden');

                    }

                    showAllNotificationsModal();

                }, true); // Use capture phase to ensure it runs first

            }

            

            // Check for new notifications and create them

            async function checkNotifications() {

                try {

                    const response = await fetch('api/notifications.php?action=check');

                    const data = await response.json();

                    if (data.success) {

                        console.log('Notifications checked:', data);

                    }

                } catch (error) {

                    console.error('Error checking notifications:', error);

                }

            }

            

            // Load notifications from API

            async function loadNotifications() {

                try {

                    const response = await fetch('api/notifications.php');

                    const data = await response.json();

                    if (data.notifications) {
                        const previousNotifications = notifications || [];
                        notifications = data.notifications;

                        updateNotificationDisplay();

                        updateNotificationBadge();
                        
                        // Process notifications for bars and sounds
                        if (window.processNotificationsForBars) {
                            window.processNotificationsForBars(notifications, previousNotifications);
                        }
                        
                        // Play sound for new MOU/MOA notifications
                        const newNotifications = previousNotifications.length > 0 
                            ? notifications.filter(n => !previousNotifications.some(p => p.id === n.id))
                            : notifications;
                        
                        if (window.NotificationSound && window.NotificationSound.checkAndPlay) {
                            window.NotificationSound.checkAndPlay(newNotifications);
                        } else if (window.checkAndPlayMouNotificationSound) {
                            window.checkAndPlayMouNotificationSound(newNotifications);
                        }

                    }

                } catch (error) {

                    console.error('Error loading notifications:', error);

                }

            }

            

            // Get unread count

            async function updateNotificationBadge() {
                try {
                    // Get badge element dynamically to avoid scope issues
                    const badge = document.getElementById('notificationBadge');
                    
                    const enabled = window.areNotificationsEnabled ? await window.areNotificationsEnabled() : true;
                    if (!enabled) {
                        if (badge) {
                            badge.classList.add('hidden');
                        }
                        return;
                    }

                    const response = await fetch('api/notifications.php?action=count');
                    if (!response.ok) {
                        console.error('Failed to get notification count:', response.status, response.statusText);
                        return;
                    }
                    const data = await response.json();
                    const count = data.count || 0;
                    
                    if (badge) {
                        if (count > 0) {
                            badge.textContent = count > 99 ? '99+' : count;
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                    } else {
                        console.warn('Notification badge element not found');
                    }
                } catch (error) {
                    console.error('Error updating notification badge:', error);
                }
            }

            

            // Update notification display

            function updateNotificationDisplay() {

                if (!notificationList) return;

                

                if (notifications.length === 0) {

                    if (noNotifications) {

                        noNotifications.classList.remove('hidden');

                    }

                    notificationList.innerHTML = '';

                    return;

                }

                

                if (noNotifications) {

                    noNotifications.classList.add('hidden');

                }

                

                notificationList.innerHTML = notifications.map(notif => {
                    const timeAgo = getTimeAgo(notif.created_at);
                    const icon = getNotificationIcon(notif.type);
                    const bgColor = getNotificationBgColor(notif.type);
                    const targetUrl = getNotificationUrl(notif);
                    const urlAttribute = targetUrl ? ` data-url="${encodeURIComponent(targetUrl)}"` : '';
                    const isMouNotification = notif.related_type === 'mou_moa';
                    const isConfirmed = notif.is_confirmed || false;
                    
                    // Show confirmation buttons for MOU notifications that haven't been confirmed
                    let confirmationButtons = '';
                    if (isMouNotification && !isConfirmed) {
                        confirmationButtons = `
                            <div class="mt-3 flex gap-2">
                                <button onclick="event.stopPropagation(); if(typeof confirmMouRenewal === 'function') confirmMouRenewal(${notif.id}, 'renewed', ${notif.related_id})" 
                                        class="px-3 py-1.5 text-xs font-medium bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                    Renewed
                                </button>
                                <button onclick="event.stopPropagation(); if(typeof confirmMouRenewal === 'function') confirmMouRenewal(${notif.id}, 'not_renewed', ${notif.related_id})" 
                                        class="px-3 py-1.5 text-xs font-medium bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">cancel</span>
                                    Not Renewed
                                </button>
                            </div>
                        `;
                    } else if (isMouNotification && isConfirmed) {
                        const statusText = notif.mou_renewal_status === 'renewed' ? 'Renewed' : 'Not Renewed';
                        const statusColor = notif.mou_renewal_status === 'renewed' ? 'text-green-500' : 'text-red-500';
                        confirmationButtons = `
                            <div class="mt-2">
                                <p class="text-xs ${statusColor} font-medium flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">${notif.mou_renewal_status === 'renewed' ? 'check_circle' : 'cancel'}</span>
                                    Status: ${statusText}
                                </p>
                            </div>
                        `;
                    }
                    
                    const actionHint = targetUrl && !isMouNotification ? '<p class="text-xs text-primary mt-2 font-semibold flex items-center gap-1">Open related record<span class="material-symbols-outlined text-sm">arrow_outward</span></p>' : '';
                    const clickableClass = 'cursor-pointer';
                    
                    return `
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 ${clickableClass} focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/60 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-background-dark ${notif.is_read ? 'opacity-60' : ''}" 
                             role="button" tabindex="0" data-id="${notif.id}" data-notification-id="${notif.id}"${urlAttribute}>
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full ${bgColor} flex items-center justify-center">
                                    <span class="material-symbols-outlined text-white text-lg">${icon}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(notif.title)}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">${escapeHtml(notif.message)}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">${timeAgo}</p>
                                        ${actionHint}
                                    ${confirmationButtons}
                                </div>
                                ${!notif.is_read ? '<div class="flex-shrink-0 w-2 h-2 bg-primary rounded-full mt-2"></div>' : ''}
                            </div>
                        </div>
                    `;
                }).join('');

            }


            

            async function handleNotificationSelection(element) {

                const notificationId = Number(element.dataset.notificationId);

                if (!notificationId) return;

                

                await markNotificationAsRead(notificationId);

                

                const targetUrl = decodeUrlAttribute(element.dataset.url);

                if (targetUrl) {

                    if (notificationDropdown) {

                        notificationDropdown.classList.add('hidden');

                    }

                    window.location.href = targetUrl;

                }

            }

            

            // Mark notification as read

            window.markNotificationAsRead = async function(id) {

                try {

                    const response = await fetch(`api/notifications.php?id=${id}`, {

                        method: 'PUT'

                    });

                    const data = await response.json();

                    if (data.success) {

                        const notif = notifications.find(n => n.id === id);

                        if (notif) {

                            notif.is_read = true;

                            updateNotificationDisplay();

                            updateNotificationBadge();

                        }

                        return true;

                    }

                    return false;

                } catch (error) {

                    console.error('Error marking notification as read:', error);

                    return false;

                }

            };

            

            // Mark all as read

            if (markAllReadBtn) {

                markAllReadBtn.addEventListener('click', async () => {

                    try {

                        const response = await fetch('api/notifications.php', {

                            method: 'PUT',

                            headers: {

                                'Content-Type': 'application/json'

                            },

                            body: JSON.stringify({ action: 'mark_all_read' })

                        });

                        const data = await response.json();

                        if (data.success) {

                            notifications.forEach(n => n.is_read = true);

                            updateNotificationDisplay();

                            updateNotificationBadge();

                        }

                    } catch (error) {

                        console.error('Error marking all as read:', error);

                    }

                });

            }

            

            // Helper functions

            function escapeHtml(text) {

                const div = document.createElement('div');

                div.textContent = text;

                return div.innerHTML;

            }

            

            function getTimeAgo(dateString) {

                const date = new Date(dateString);

                const now = new Date();

                const diffInSeconds = Math.floor((now - date) / 1000);

                

                if (diffInSeconds < 60) return 'Just now';

                if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;

                if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;

                if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;

                return date.toLocaleDateString();

            }

            

            function getNotificationIcon(type) {

                const icons = {

                    'mou_expiring': 'schedule',

                    'mou_expired': 'warning',

                    'event_upcoming': 'event',

                    'event_today': 'today',

                    'system': 'info'

                };

                return icons[type] || 'notifications';

            }

            

            function getNotificationBgColor(type) {

                const colors = {

                    'mou_expiring': 'bg-yellow-500',

                    'mou_expired': 'bg-red-500',

                    'event_upcoming': 'bg-blue-500',

                    'event_today': 'bg-green-500',

                    'system': 'bg-gray-500'

                };

                return colors[type] || 'bg-gray-500';

            }

            

            function getNotificationUrl(notif) {

                if (!notif || !notif.related_type || !notif.related_id) {

                    return '';

                }

                

                const encodedId = encodeURIComponent(notif.related_id);

                

                if (notif.related_type === 'mou_moa') {

                    return `mou-moa.php?entry=${encodedId}`;

                }
                
                if (notif.related_type === 'schedule') {
                    return `scheduler.php`;
                }

                

                if (notif.related_type === 'event') {

                    return `events-activities.php?event=${encodedId}`;

                }

                

                return '';

            }

            

            function decodeUrlAttribute(value) {

                if (!value) return '';

                try {

                    return decodeURIComponent(value);

                } catch (error) {

                    console.warn('Unable to decode notification URL attribute:', error);

                    return value;

                }

            }

            

            // Show MOU notification modal

            window.showMouNotificationModal = function(notificationId) {

                const notif = notifications.find(n => n.id === notificationId);

                if (!notif || notif.related_type !== 'mou_moa') return;

                

                const modal = document.getElementById('mouNotificationModal');

                const modalContent = document.getElementById('mouModalContent');

                if (!modal || !modalContent) return;

                

                // Determine criticality

                const isExpired = notif.mou_is_expired || false;

                const daysUntilExpiry = notif.mou_days_until_expiry || 0;

                const isExpiringSoon = !isExpired && daysUntilExpiry <= 30;

                

                let criticalityLevel = 'Low';

                let criticalityColor = 'text-blue-500';

                let criticalityBg = 'bg-blue-50 dark:bg-blue-900/20';

                let criticalityIcon = 'info';

                

                if (isExpired) {

                    criticalityLevel = 'Critical';

                    criticalityColor = 'text-red-500';

                    criticalityBg = 'bg-red-50 dark:bg-red-900/20';

                    criticalityIcon = 'error';

                } else if (isExpiringSoon) {

                    criticalityLevel = 'High';

                    criticalityColor = 'text-yellow-500';

                    criticalityBg = 'bg-yellow-50 dark:bg-yellow-900/20';

                    criticalityIcon = 'warning';

                }

                

                const mouTitle = notif.mou_institution || notif.mou_partner || 'Unknown';

                const endDate = notif.mou_end_date ? new Date(notif.mou_end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'Not specified';

                const statusText = notif.mou_status || 'Unknown';

                

                modalContent.innerHTML = `

                    <div class="space-y-4">

                        <div class="${criticalityBg} p-4 rounded-lg border border-current ${criticalityColor}">

                            <div class="flex items-center gap-2">

                                <span class="material-symbols-outlined">${criticalityIcon}</span>

                                <p class="font-semibold">Priority Level: ${criticalityLevel}</p>

                            </div>

                            ${(isExpired ?
                                `<p class="text-sm mt-2">This MOU/MOA has expired and requires immediate attention.</p>` :
                                isExpiringSoon ?
                                `<p class="text-sm mt-2">This MOU/MOA will expire in ${daysUntilExpiry} day(s). Please take action soon.</p>` :
                                `<p class="text-sm mt-2">This MOU/MOA is still active but should be monitored.</p>`
                            )}

                        </div>

                        

                        <div class="space-y-2">

                            <div>

                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Institution/Partner</p>

                                <p class="text-base text-gray-900 dark:text-white">${escapeHtml(mouTitle)}</p>

                            </div>

                            <div>

                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">End Date</p>

                                <p class="text-base text-gray-900 dark:text-white">${endDate}</p>

                            </div>

                            <div>

                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</p>

                                <p class="text-base text-gray-900 dark:text-white">${escapeHtml(statusText)}</p>

                            </div>

                            ${(isExpired ?

                                `<div>

                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Days Since Expiration</p>

                                    <p class="text-base text-red-600 dark:text-red-400 font-semibold">${Math.abs(daysUntilExpiry)} day(s)</p>

                                </div>` :

                                `<div>

                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Days Until Expiration</p>

                                    <p class="text-base ${isExpiringSoon ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-900 dark:text-white'} font-semibold">${daysUntilExpiry} day(s)</p>

                                </div>`

                            )}

                        </div>

                        

                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">

                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Please confirm the renewal status:</p>

                            <div class="flex gap-2">

                                <button onclick="confirmMouRenewal(${notif.id}, 'renewed', ${notif.related_id}); closeMouModal();" 

                                        class="flex-1 px-4 py-2 text-sm font-medium bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors flex items-center justify-center gap-2">

                                    <span class="material-symbols-outlined text-sm">check_circle</span>

                                    Mark as Renewed

                                </button>

                                <button onclick="confirmMouRenewal(${notif.id}, 'not_renewed', ${notif.related_id}); closeMouModal();" 

                                        class="flex-1 px-4 py-2 text-sm font-medium bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors flex items-center justify-center gap-2">

                                    <span class="material-symbols-outlined text-sm">cancel</span>

                                    Not Renewed

                                </button>

                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Note: If marked as "Not Renewed", you will continue to receive notifications until it is renewed.</p>

                        </div>

                    </div>

                `;

                

                modal.classList.remove('hidden');

            };

            

            // Close MOU modal

            window.closeMouModal = function() {

                const modal = document.getElementById('mouNotificationModal');

                if (modal) {

                    modal.classList.add('hidden');

                }

            };

            

            // Setup modal close handlers

            document.addEventListener('DOMContentLoaded', () => {

                const closeBtn = document.getElementById('closeMouModal');

                const modal = document.getElementById('mouNotificationModal');

                

                if (closeBtn) {

                    closeBtn.addEventListener('click', closeMouModal);

                }

                

                if (modal) {

                    modal.addEventListener('click', (e) => {

                        if (e.target === modal) {

                            closeMouModal();

                        }

                    });

                }

                

                // Close on Escape key

                document.addEventListener('keydown', (e) => {

                    if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {

                        closeMouModal();

                    }

                });

            });

            

            // Confirm MOU renewal status

            window.confirmMouRenewal = async function(notificationId, renewalStatus, entryId) {
                // For "renewed": open the MOU/MOA renew flow (edit sign date + term) instead of immediately confirming.
                if (renewalStatus === 'renewed') {
                    if (typeof window.openMouRenewalFlow === 'function') {
                        window.openMouRenewalFlow(notificationId, entryId);
                    } else {
                        if (!entryId) {
                            alert('Error: missing MOU/MOA entry id for renewal.');
                            return;
                        }
                        window.location.href = `mou-moa.php?entry=${encodeURIComponent(entryId)}&renew=1&notif=${encodeURIComponent(notificationId)}`;
                    }
                    return;
                }

                try {

                    const response = await fetch('api/notifications.php', {

                        method: 'POST',

                        headers: {

                            'Content-Type': 'application/json'

                        },

                        body: JSON.stringify({

                            action: 'confirm_mou_renewal',

                            notification_id: notificationId,

                            renewal_status: renewalStatus

                        })

                    });

                    

                    const data = await response.json();

                    if (data.success) {

                        // Reload notifications to reflect the confirmation

                        await loadNotifications();

                        await updateNotificationBadge();

                        

                        if (renewalStatus === 'renewed') {

                            if (typeof showToast === 'function') {

                                showToast('MOU/MOA marked as renewed. Notification will be removed.', 'success');

                            } else {

                                alert('MOU/MOA marked as renewed. Notification will be removed.');

                            }

                        } else {

                            if (typeof showToast === 'function') {

                                showToast('MOU/MOA marked as not renewed. You will continue to receive notifications.', 'info');

                            } else {

                                alert('MOU/MOA marked as not renewed. You will continue to receive notifications.');

                            }

                        }

                    } else {

                        console.error('Failed to confirm MOU renewal:', data.error);

                        alert('Failed to confirm MOU renewal status. Please try again.');

                    }

                } catch (error) {

                    console.error('Error confirming MOU renewal:', error);

                    alert('An error occurred while confirming MOU renewal status. Please try again.');

                }

            };

            

            async function refreshNotificationIndicators() {

                try {

                    await checkNotifications();

                    await updateNotificationBadge();

                } catch (error) {

                    console.error('Error refreshing notification indicators:', error);

                }

            }

                // Initialize: Check for notifications and load them (run when init runs)
                if (typeof updateNotificationBadge === 'function') {
                    updateNotificationBadge();
                }
                if (typeof refreshNotificationIndicators === 'function') {
                    refreshNotificationIndicators();
                }
                setTimeout(function() {
                    if (typeof updateNotificationBadge === 'function') {
                        updateNotificationBadge();
                    }
                }, 300);
                setInterval(function() {
                    if (typeof refreshNotificationIndicators === 'function') {
                        refreshNotificationIndicators();
                    }
                }, 5 * 60 * 1000);
                setInterval(function() {
                    if (typeof updateNotificationBadge === 'function') {
                        updateNotificationBadge();
                    }
                }, 30000);
        })();
