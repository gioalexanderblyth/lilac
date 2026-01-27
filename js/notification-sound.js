/**
 * Notification Sound System
 * Plays sound effects for expiring MOU/MOA notifications
 */

(function() {
    'use strict';
    
    // Audio element for notification sound
    let notificationAudio = null;
    let playedNotificationIds = new Set(); // Track which notifications have played sound
    let audioUnlocked = false; // Whether browser has allowed playback
    
    /**
     * Initialize the notification audio element
     */
    function initNotificationAudio() {
        if (!notificationAudio) {
            notificationAudio = new Audio('assets/audio/MOU-ExpiringSound.wav');
            notificationAudio.volume = 0.5; // Set volume to 50%
            notificationAudio.preload = 'auto';
            
            // Handle audio loading errors
            notificationAudio.addEventListener('error', function(e) {
                console.warn('Failed to load notification sound:', e);
            });
        }
    }
    
    /**
     * Attempt to unlock audio on first user interaction (required by browsers for autoplay)
     */
    function setupAudioUnlock() {
        if (typeof document === 'undefined') return;
        const unlock = () => {
            if (audioUnlocked) return;
            initNotificationAudio();
            if (!notificationAudio) return;
            // Try a muted play/pause to satisfy gesture requirement
            notificationAudio.muted = true;
            const p = notificationAudio.play();
            if (p && p.then) {
                p.then(() => {
                    notificationAudio.pause();
                    notificationAudio.currentTime = 0;
                    notificationAudio.muted = false;
                    audioUnlocked = true;
                    removeUnlockListeners();
                }).catch(() => {
                    // Still locked; keep listeners
                });
            }
        };
        const removeUnlockListeners = () => {
            ['pointerdown','click','keydown','touchstart'].forEach(evt => {
                document.removeEventListener(evt, unlock, true);
            });
        };
        ['pointerdown','click','keydown','touchstart'].forEach(evt => {
            document.addEventListener(evt, unlock, true);
        });
    }
    
    /**
     * Play notification sound for expiring MOU/MOA
     */
    function playNotificationSound() {
        try {
            // Check if user has disabled notification sounds
            const soundEnabled = localStorage.getItem('notification_sound_enabled') !== 'false';
            if (!soundEnabled) {
                return;
            }
            
            // Initialize audio if not already done
            initNotificationAudio();
            
            if (!notificationAudio) {
                console.warn('Notification audio not initialized');
                return;
            }
            
            if (!audioUnlocked) {
                console.warn('Notification sound blocked until user interacts with the page (click/tap).');
                return;
            }
            
            // Reset audio to beginning and play
            notificationAudio.currentTime = 0;
            const playPromise = notificationAudio.play();
            
            // Handle play promise (required for modern browsers)
            if (playPromise !== undefined) {
                playPromise
                    .then(() => {
                        // Sound played successfully
                        console.log('Notification sound played');
                    })
                    .catch(error => {
                        // Auto-play was prevented or audio failed
                        console.warn('Could not play notification sound:', error);
                        // User interaction may be required for autoplay
                    });
            }
        } catch (error) {
            console.warn('Error playing notification sound:', error);
        }
    }
    
    /**
     * Check for expiring MOU/MOA notifications and play sound
     * @param {Array} newNotifications - Array of notification objects
     */
    function checkAndPlayNotificationSound(newNotifications) {
        if (!newNotifications || !Array.isArray(newNotifications)) {
            return;
        }
        
        // Find expiring MOU/MOA notifications that haven't played sound yet
        const expiringNotifications = newNotifications.filter(notif => {
            const isExpiring = (notif.type === 'mou_expiring' || notif.type === 'mou_expired') && 
                             notif.related_type === 'mou_moa';
            const notPlayedYet = !playedNotificationIds.has(notif.id);
            return isExpiring && notPlayedYet;
        });
        
        // Play sound if there are expiring notifications
        if (expiringNotifications.length > 0) {
            playNotificationSound();
            
            // Mark these notifications as having played sound
            expiringNotifications.forEach(notif => {
                playedNotificationIds.add(notif.id);
            });
        }
    }
    
    /**
     * Check all notifications for expiring MOU/MOA and play sound on initial load
     * @param {Array} allNotifications - Array of all notification objects
     */
    function checkInitialExpiringNotifications(allNotifications) {
        if (!allNotifications || !Array.isArray(allNotifications)) {
            return;
        }
        
        // Find all unread expiring MOU/MOA notifications
        const expiringNotifications = allNotifications.filter(notif => {
            const isExpiring = (notif.type === 'mou_expiring' || notif.type === 'mou_expired') && 
                             notif.related_type === 'mou_moa';
            const isUnread = !notif.is_read;
            return isExpiring && isUnread;
        });
        
        // Play sound if there are unread expiring notifications (only once on page load)
        if (expiringNotifications.length > 0 && playedNotificationIds.size === 0) {
            // Small delay to ensure page is fully loaded
            setTimeout(() => {
                playNotificationSound();
                
                // Mark all as played to avoid duplicate sounds
                expiringNotifications.forEach(notif => {
                    playedNotificationIds.add(notif.id);
                });
            }, 500);
        }
    }
    
    /**
     * Enable or disable notification sounds
     * @param {boolean} enabled - Whether to enable sounds
     */
    function setNotificationSoundEnabled(enabled) {
        localStorage.setItem('notification_sound_enabled', enabled ? 'true' : 'false');
    }
    
    /**
     * Check if notification sounds are enabled
     * @returns {boolean}
     */
    function isNotificationSoundEnabled() {
        return localStorage.getItem('notification_sound_enabled') !== 'false';
    }
    
    // Initialize audio when script loads
    initNotificationAudio();
    setupAudioUnlock();
    
    // Export functions to global scope
    window.NotificationSound = {
        play: playNotificationSound,
        checkAndPlay: checkAndPlayNotificationSound,
        checkInitial: checkInitialExpiringNotifications,
        setEnabled: setNotificationSoundEnabled,
        isEnabled: isNotificationSoundEnabled,
        resetPlayedIds: function() {
            playedNotificationIds.clear();
        }
    };
})();
