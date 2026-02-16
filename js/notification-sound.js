/**
 * Notification Sound System
 * Plays sound effects for expiring MOU/MOA notifications
 */

(function() {
    'use strict';
    
    // Audio element for notification sound
    let notificationAudio = null;
    let currentSoundFile = null; // Track the current sound file path
    // Track which notifications have already played sound (persisted via localStorage).
    // We use a logical key (e.g. by MOU entry) instead of raw notification ID so
    // re-created notifications for the same MOU don't keep re-triggering the sound.
    let playedNotificationKeys = new Set();
    let playedNotificationIds = new Set(); // IDs that have played sound (used by notification-bar.js)
    let audioUnlocked = false; // Track if audio has been unlocked by user interaction
    const STORAGE_KEY = 'mouNotificationSound:playedKeys';
    
    /**
     * Load played notification IDs from localStorage
     */
    function loadPlayedKeysFromStorage() {
        try {
            const raw = window.localStorage ? localStorage.getItem(STORAGE_KEY) : null;
            if (!raw) return;
            const arr = JSON.parse(raw);
            if (Array.isArray(arr)) {
                playedNotificationKeys = new Set(arr);
                console.log('[NotificationSound] Loaded played keys from storage:', playedNotificationKeys.size);
            }
        } catch (e) {
            console.warn('[NotificationSound] Failed to load played keys from storage', e);
        }
    }
    
    /**
     * Save played notification IDs to localStorage
     */
    function savePlayedKeysToStorage() {
        try {
            if (!window.localStorage) return;
            const arr = Array.from(playedNotificationKeys);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(arr));
        } catch (e) {
            console.warn('[NotificationSound] Failed to save played keys to storage', e);
        }
    }
    
    /**
     * Get the sound file path from localStorage or use default
     */
    function getSoundFilePath() {
        try {
            const savedSound = localStorage.getItem('notification_sound_file');
            return savedSound || 'assets/audio/MOU-ExpiringSound.wav';
        } catch (e) {
            return 'assets/audio/MOU-ExpiringSound.wav';
        }
    }

    /**
     * Initialize the notification audio element
     */
    function initNotificationAudio() {
        const soundFile = getSoundFilePath();
        
        if (!notificationAudio) {
            notificationAudio = new Audio(soundFile);
            currentSoundFile = soundFile;
            notificationAudio.volume = 0.5; // Set volume to 50%
            notificationAudio.preload = 'auto';
            
            // Handle audio loading success
            notificationAudio.addEventListener('canplaythrough', function() {
                console.log('[NotificationSound] Audio file loaded successfully');
            });
            
            // Handle audio loading errors
            notificationAudio.addEventListener('error', function(e) {
                console.error('[NotificationSound] Failed to load notification sound:', e);
                console.error('[NotificationSound] Audio error details:', {
                    code: notificationAudio.error?.code,
                    message: notificationAudio.error?.message,
                    src: notificationAudio.src
                });
            });
            
            console.log('[NotificationSound] Initializing audio from:', notificationAudio.src);
        } else {
            // If audio already exists but sound file changed, update it
            const newSoundFile = getSoundFilePath();
            if (currentSoundFile !== newSoundFile) {
                notificationAudio.src = newSoundFile;
                currentSoundFile = newSoundFile;
                notificationAudio.load(); // Reload the audio element
                console.log('[NotificationSound] Sound file updated to:', notificationAudio.src);
            }
        }
        
        // Load played keys from storage once when audio is initialized
        if (playedNotificationKeys.size === 0) {
            loadPlayedKeysFromStorage();
        }
    }

    /**
     * Build a stable key for a notification for sound purposes.
     * For MOU/MOA notifications, we key by MOU entry (so regenerated
     * notifications for the same entry don't repeatedly trigger sounds).
     */
    function getNotificationSoundKey(notif) {
        if (!notif) return null;
        if (notif.related_type === 'mou_moa' && notif.related_id) {
            return `mou_${notif.related_id}`;
        }
        // Fallback to ID-based key
        return `id_${notif.id}`;
    }
    
    /**
     * Unlock audio by playing and immediately pausing (requires user interaction)
     * This must be called after a user interaction event
     */
    function unlockAudio() {
        if (audioUnlocked) {
            return Promise.resolve();
        }
        
        // Initialize audio if not already done
        initNotificationAudio();
        
        if (!notificationAudio) {
            return Promise.reject(new Error('Audio not initialized'));
        }
        
        return new Promise((resolve, reject) => {
            try {
                // Reset audio to beginning
                notificationAudio.currentTime = 0;
                
                // Attempt to play and immediately pause to unlock audio
                const playPromise = notificationAudio.play();
                if (playPromise !== undefined) {
                    playPromise
                        .then(() => {
                            notificationAudio.pause();
                            notificationAudio.currentTime = 0;
                            audioUnlocked = true;
                            resolve();
                        })
                        .catch((error) => {
                            // If it's a NotAllowedError, that's expected before user interaction
                            if (error.name === 'NotAllowedError') {
                                reject(error);
                            } else {
                                // Other errors might still mean it's unlocked
                                audioUnlocked = true;
                                resolve();
                            }
                        });
                } else {
                    // If play() doesn't return a promise, assume it worked
                    notificationAudio.pause();
                    notificationAudio.currentTime = 0;
                    audioUnlocked = true;
                    resolve();
                }
            } catch (error) {
                reject(error);
            }
        });
    }
    
    /**
     * Set up user interaction listeners to unlock audio
     */
    function setupAudioUnlock() {
        const unlockEvents = ['click', 'touchstart', 'keydown', 'mousedown'];
        let unlockHandler = null;
        
        unlockHandler = function(event) {
            if (audioUnlocked) {
                return;
            }
            
            console.log('[NotificationSound] User interaction detected, unlocking audio synchronously...');
            
            // Initialize audio if not already done
            initNotificationAudio();
            
            if (!notificationAudio) {
                console.warn('[NotificationSound] Cannot unlock - audio not initialized');
                return;
            }
            
            // Try to unlock synchronously within the user interaction handler
            try {
                // Reset and attempt to play/pause to unlock
                notificationAudio.currentTime = 0;
                const playPromise = notificationAudio.play();
                
                if (playPromise !== undefined) {
                    playPromise
                        .then(() => {
                            // Successfully unlocked
                            notificationAudio.pause();
                            notificationAudio.currentTime = 0;
                            audioUnlocked = true;
                            console.log('[NotificationSound] Audio unlocked successfully!');
                            
                            // Remove all listeners
                            unlockEvents.forEach(evt => {
                                document.removeEventListener(evt, unlockHandler);
                            });
                        })
                        .catch((error) => {
                            // Failed to unlock
                            console.log('[NotificationSound] Audio unlock failed:', error.name, error.message);
                            // Keep listeners active to try again
                        });
                } else {
                    // Legacy browser - assume it worked
                    notificationAudio.pause();
                    notificationAudio.currentTime = 0;
                    audioUnlocked = true;
                    console.log('[NotificationSound] Audio unlocked (legacy browser)');
                    
                    // Remove all listeners
                    unlockEvents.forEach(evt => {
                        document.removeEventListener(evt, unlockHandler);
                    });
                }
            } catch (error) {
                console.warn('[NotificationSound] Error during unlock attempt:', error);
            }
        };
        
        unlockEvents.forEach(event => {
            document.addEventListener(event, unlockHandler, { passive: true, once: false });
        });
        
        console.log('[NotificationSound] Audio unlock listeners set up for events:', unlockEvents);
    }
    
    /**
     * Play notification sound for expiring MOU/MOA
     */
    function playNotificationSound() {
        try {
            // Check if user has disabled notification sounds
            const soundEnabled = localStorage.getItem('notification_sound_enabled') !== 'false';
            if (!soundEnabled) {
                console.log('[NotificationSound] Sounds are disabled by user');
                return;
            }
            
            // Initialize audio if not already done
            initNotificationAudio();
            
            if (!notificationAudio) {
                console.warn('[NotificationSound] Audio not initialized');
                return;
            }
            
            // Always try to play the sound - if audio is unlocked, it will work
            // If not unlocked, we'll try to unlock and play
            const attemptPlay = () => {
                try {
                    notificationAudio.currentTime = 0;
                    const playPromise = notificationAudio.play();
                    
                    if (playPromise !== undefined) {
                        playPromise
                            .then(() => {
                                // Sound played successfully
                                console.log('[NotificationSound] Sound played successfully');
                                audioUnlocked = true; // Mark as unlocked since play succeeded
                            })
                            .catch(error => {
                                // If it's a NotAllowedError, audio isn't unlocked yet
                                if (error.name === 'NotAllowedError') {
                                    console.log('[NotificationSound] Audio not unlocked yet, will unlock on next user interaction');
                                    audioUnlocked = false;
                                } else {
                                    console.warn('[NotificationSound] Could not play sound:', error);
                                    audioUnlocked = false;
                                }
                            });
                    } else {
                        // Older browsers - assume it worked
                        audioUnlocked = true;
                        console.log('[NotificationSound] Sound played (legacy browser)');
                    }
                } catch (error) {
                    console.warn('[NotificationSound] Error attempting to play:', error);
                }
            };
            
            // If audio is already unlocked, play directly
            if (audioUnlocked) {
                attemptPlay();
            } else {
                // Try to unlock first, then play
                unlockAudio()
                    .then(() => {
                        // Audio unlocked successfully, now play
                        attemptPlay();
                    })
                    .catch(() => {
                        // Unlock failed, but try to play anyway (might work in some browsers)
                        attemptPlay();
                    });
            }
        } catch (error) {
            console.warn('[NotificationSound] Error in playNotificationSound:', error);
        }
    }
    
    /**
     * Check for expiring MOU/MOA notifications and play sound
     * @param {Array} newNotifications - Array of notification objects
     */
    function checkAndPlayNotificationSound(newNotifications) {
        if (!newNotifications || !Array.isArray(newNotifications)) {
            console.log('[NotificationSound] checkAndPlay called with invalid notifications');
            return;
        }
        
        console.log('[NotificationSound] checkAndPlay called with', newNotifications.length, 'notifications');
        
        // Debug: Log the notification structure
        if (newNotifications.length > 0) {
            console.log('[NotificationSound] First notification structure:', JSON.stringify(newNotifications[0], null, 2));
        }
        
        // Find expiring MOU/MOA notifications that haven't played sound yet
        const expiringNotifications = newNotifications.filter(notif => {
            // Check if it's an expiring/expired MOU notification
            const isExpiringType = notif.type === 'mou_expiring' || notif.type === 'mou_expired';
            // Check related_type if it exists, otherwise assume it's MOU/MOA if type matches
            const isMouMoa = !notif.related_type || notif.related_type === 'mou_moa';
            const isExpiring = isExpiringType && isMouMoa;
            const key = getNotificationSoundKey(notif);
            const notPlayedYet = key && !playedNotificationKeys.has(key);
            
            console.log('[NotificationSound] Notification', notif.id, '- type:', notif.type, '- related_type:', notif.related_type, '- isExpiring:', isExpiring, '- notPlayedYet:', notPlayedYet);
            
            return isExpiring && notPlayedYet;
        });
        
        console.log('[NotificationSound] Found', expiringNotifications.length, 'expiring MOU/MOA notifications to play sound for');
        
        // Play sound if there are expiring notifications
        if (expiringNotifications.length > 0) {
            console.log('[NotificationSound] Playing sound for notifications:', expiringNotifications.map(n => n.id));
            playNotificationSound();
            
            // Mark these notifications as having played sound
            expiringNotifications.forEach(notif => {
                const key = getNotificationSoundKey(notif);
                if (key) {
                    playedNotificationKeys.add(key);
                }
                if (notif.id != null) {
                    playedNotificationIds.add(notif.id);
                }
            });
            // Persist to storage so reloading other pages doesn't replay the sound
            savePlayedKeysToStorage();
        } else {
            console.log('[NotificationSound] No new expiring notifications to play sound for');
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
        
        // Play sound if there are unread expiring notifications that haven't played yet
        const newToPlay = expiringNotifications.filter(notif => {
            const key = getNotificationSoundKey(notif);
            return key && !playedNotificationKeys.has(key);
        });
        
        if (newToPlay.length > 0) {
            // Small delay to ensure page is fully loaded
            setTimeout(() => {
                playNotificationSound();
                
                // Mark all as played to avoid duplicate sounds
                newToPlay.forEach(notif => {
                    const key = getNotificationSoundKey(notif);
                    if (key) {
                        playedNotificationKeys.add(key);
                    }
                    if (notif.id != null) {
                        playedNotificationIds.add(notif.id);
                    }
                });
                savePlayedKeysToStorage();
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
    
    /**
     * Check if a notification has already played sound
     * @param {number} notificationId - Notification ID
     * @returns {boolean}
     */
    function hasPlayedSound(notificationId) {
        return playedNotificationIds && playedNotificationIds.has(notificationId);
    }
    
    /**
     * Mark a notification as having played sound
     * @param {number} notificationId - Notification ID
     */
    function markAsPlayed(notificationId) {
        if (playedNotificationIds != null && notificationId != null) {
            playedNotificationIds.add(notificationId);
        }
    }
    
    /**
     * Set the notification sound file
     * @param {string} soundFile - Path to the sound file
     */
    function setSoundFile(soundFile) {
        try {
            // Save to localStorage
            localStorage.setItem('notification_sound_file', soundFile);
            
            // Update the audio element if it exists
            if (notificationAudio) {
                notificationAudio.src = soundFile;
                currentSoundFile = soundFile;
                notificationAudio.load(); // Reload the audio element
                console.log('[NotificationSound] Sound file changed to:', soundFile);
            } else {
                // If audio doesn't exist yet, it will use the new file when initialized
                currentSoundFile = soundFile;
                console.log('[NotificationSound] Sound file preference saved:', soundFile);
            }
        } catch (error) {
            console.error('[NotificationSound] Error setting sound file:', error);
        }
    }
    
    // Initialize audio when script loads
    initNotificationAudio();
    
    // Set up audio unlock listeners for user interaction
    setupAudioUnlock();
    
    // Export functions to global scope
    window.NotificationSound = {
        play: playNotificationSound,
        checkAndPlay: checkAndPlayNotificationSound,
        checkInitial: checkInitialExpiringNotifications,
        setEnabled: setNotificationSoundEnabled,
        isEnabled: isNotificationSoundEnabled,
        setSoundFile: setSoundFile,
        resetPlayedIds: function() {
            if (playedNotificationIds) playedNotificationIds.clear();
        },
        unlock: unlockAudio, // Expose unlock function for manual unlocking if needed
        _hasPlayedSound: hasPlayedSound, // Internal helper for checking if sound played
        _markAsPlayed: markAsPlayed // Internal helper for marking as played
    };
})();
