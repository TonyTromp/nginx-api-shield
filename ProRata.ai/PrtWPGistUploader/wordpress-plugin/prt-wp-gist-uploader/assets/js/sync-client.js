/**
 * Sync Client JavaScript
 * Handles REST API polling and UI interactions
 */

(function($) {
    'use strict';
    
    let pollingInterval = null;
    let currentProcessId = null;
    let lastLogCount = 0;
    let syncInProgress = false;
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Check for stuck sync on page load
        checkSyncStatus();
        
        // Initialize filter toggles
        initFilterToggles();
        
        // Initialize sync button
        initSyncButton();
        
        // Initialize clear log button
        initClearLogButton();
        
        // Initialize reset sync button
        initResetSyncButton();
    });
    
    /**
     * Start polling for sync status and logs
     */
    function startPolling(processId) {
        currentProcessId = processId;
        lastLogCount = 0;
        
        // Poll every 5 seconds
        pollingInterval = setInterval(() => {
            pollSyncStatus();
            pollSyncLogs();
        }, 5000);
        
        // First poll immediately
        pollSyncStatus();
        pollSyncLogs();
    }
    
    /**
     * Stop polling
     */
    function stopPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }
    
    /**
     * Poll sync status
     */
    function pollSyncStatus() {
        if (typeof prtGistData === 'undefined') {
            return;
        }
        
        $.get(prtGistData.restUrl + '/sync-status', function(response) {
            if (response && response.status) {
                if (response.status === 'completed' || response.status === 'failed') {
                    stopPolling();
                    
                    if (response.status === 'completed') {
                        updateLastSyncTime();
                    }
                }
            }
        }).fail(function() {
            // Silently fail - we'll retry on next poll
        });
    }
    
    /**
     * Poll sync logs
     */
    function pollSyncLogs() {
        if (typeof prtGistData === 'undefined' || !currentProcessId) {
            return;
        }
        
        $.get(prtGistData.restUrl + '/sync-logs?process_id=' + currentProcessId, function(logs) {
            // Only add new logs
            if (logs && logs.length > lastLogCount) {
                for (let i = lastLogCount; i < logs.length; i++) {
                    addLogEntry(logs[i].type, logs[i].message, logs[i].timestamp);
                }
                lastLogCount = logs.length;
            }
        }).fail(function() {
            // Silently fail - we'll retry on next poll
        });
    }
    
    /**
     * Add log entry to the display
     */
    function addLogEntry(type, message, timestamp) {
        const logContainer = $('#sync-log');
        if (!logContainer.length) {
            return;
        }
        
        // Clear "waiting" message on first log
        if (logContainer.find('.log-entry').length === 1 && 
            logContainer.find('.log-entry').text().includes('Waiting for sync')) {
            logContainer.empty();
        }
        
        // Format timestamp
        let time;
        if (timestamp) {
            // If timestamp is Unix timestamp (number)
            if (typeof timestamp === 'number') {
                time = new Date(timestamp * 1000).toLocaleTimeString();
            } else {
                time = new Date(timestamp).toLocaleTimeString();
            }
        } else {
            time = new Date().toLocaleTimeString();
        }
        
        const logEntry = $('<div>')
            .addClass('log-entry')
            .addClass('log-' + type)
            .html('<span class="log-time">[' + time + ']</span> ' + escapeHtml(message));
        
        logContainer.append(logEntry);
        
        // Auto-scroll to bottom
        logContainer.scrollTop(logContainer[0].scrollHeight);
    }
    
    /**
     * Initialize filter toggles
     */
    function initFilterToggles() {
        $('input[name="filter_type"]').on('change', function() {
            const filterType = $(this).val();
            
            // Hide all filter inputs
            $('.filter-input').hide();
            
            // Show relevant filter input
            if (filterType === 'date') {
                $('#date-filter').show();
            } else if (filterType === 'tag') {
                $('#tag-filter').show();
            }
        });
    }
    
    /**
     * Initialize sync button
     */
    function initSyncButton() {
        $('#sync-now-btn').on('click', function(e) {
            e.preventDefault();
            
            if (syncInProgress) {
                return;
            }
            
            const button = $(this);
            const filterType = $('input[name="filter_type"]:checked').val();
            let filterValue = '';
            
            // Get filter value based on type
            if (filterType === 'date') {
                filterValue = $('#filter_date').val();
                if (!filterValue) {
                    alert('Please select a date');
                    return;
                }
            } else if (filterType === 'tag') {
                filterValue = $('#filter_tag').val();
                if (!filterValue) {
                    alert('Please select a tag');
                    return;
                }
            }
            
            // Start sync
            syncInProgress = true;
            button.prop('disabled', true);
            button.html('<span class="dashicons dashicons-update spin"></span> Syncing...');
            $('#sync-status-message').text('Starting sync process...');
            
            // Clear previous log entries for new sync
            addLogEntry('info', 'Initiating sync request...');
            
            // Send AJAX request to start background sync
            $.ajax({
                url: prtGistData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'prt_gist_sync_now',
                    nonce: prtGistData.nonce,
                    filter_type: filterType,
                    filter_value: filterValue
                },
                success: function(response) {
                    if (response.success) {
                        // Start polling for logs
                        startPolling(response.data.process_id);
                        
                        $('#sync-status-message')
                            .text('Sync completed successfully!')
                            .addClass('success-message');
                        
                        addLogEntry('success', 'Sync process completed (Process ID: ' + response.data.process_id + ')');
                        
                        // Show sync count in log
                        if (response.data.count) {
                            addLogEntry('success', response.data.count + ' post(s) synced successfully');
                        }
                        
                        // Update archive count if archive was created
                        if (response.data.archive_created) {
                            addLogEntry('success', 'Archive created successfully - ready for download!');
                            
                            // Increment archive count
                            var currentCount = parseInt($('#archive-count').text()) || 0;
                            $('#archive-count').text(currentCount + 1);
                        }
                        
                        // Update last sync time
                        updateLastSyncTime();
                        
                        // Re-enable sync button immediately
                        syncInProgress = false;
                        button.prop('disabled', false);
                        button.html('<span class="dashicons dashicons-yes"></span> Sync Complete!');
                        
                        // Reset button after 3 seconds
                        setTimeout(function() {
                            button.html('<span class="dashicons dashicons-update"></span> Sync Now');
                        }, 3000);
                    } else {
                        $('#sync-status-message')
                            .text('Failed to start sync: ' + (response.data.message || response.data))
                            .addClass('error-message');
                        addLogEntry('error', 'Failed to start sync: ' + (response.data.message || response.data));
                        
                        syncInProgress = false;
                        button.prop('disabled', false);
                        button.html('<span class="dashicons dashicons-update"></span> Sync Now');
                    }
                },
                error: function(xhr, status, error) {
                    $('#sync-status-message')
                        .text('Sync failed: ' + error)
                        .addClass('error-message');
                    addLogEntry('error', 'AJAX error: ' + error);
                    
                    syncInProgress = false;
                    button.prop('disabled', false);
                    button.html('<span class="dashicons dashicons-update"></span> Sync Now');
                },
                complete: function() {
                    // Clear status message after 10 seconds
                    setTimeout(function() {
                        $('#sync-status-message').text('').removeClass('success-message error-message');
                    }, 10000);
                }
            });
        });
    }
    
    /**
     * Initialize clear log button
     */
    function initClearLogButton() {
        $('#clear-log-btn').on('click', function() {
            $('#sync-log').html('<div class="log-entry log-info">Log cleared. Waiting for sync operation...</div>');
        });
    }
    
    /**
     * Initialize reset sync button
     */
    function initResetSyncButton() {
        $('#reset-sync-btn').on('click', function() {
            if (!confirm('Reset the sync process? This will clear any stuck sync jobs.')) {
                return;
            }
            
            const button = $(this);
            button.prop('disabled', true).text('Resetting...');
            
            $.ajax({
                url: prtGistData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'prt_gist_clear_stuck_sync',
                    nonce: prtGistData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        addLogEntry('success', 'Sync reset successfully');
                        syncInProgress = false;
                        $('#sync-now-btn')
                            .prop('disabled', false)
                            .html('<span class="dashicons dashicons-update"></span> Sync Now');
                        button.hide();
                        stopPolling();
                    } else {
                        addLogEntry('error', 'Failed to reset sync');
                        button.prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> Reset Sync');
                    }
                },
                error: function() {
                    addLogEntry('error', 'Error resetting sync');
                    button.prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> Reset Sync');
                }
            });
        });
    }
    
    /**
     * Check sync status on page load
     */
    function checkSyncStatus() {
        if (typeof prtGistData === 'undefined') {
            return;
        }
        
        $.ajax({
            url: prtGistData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'prt_gist_check_status',
                nonce: prtGistData.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    const status = response.data.status;
                    
                    if (status === 'running' || status === 'processing') {
                        // Check if sync has been running for more than 60 seconds
                        if (response.data.started_at) {
                            const startedTime = new Date(response.data.started_at).getTime();
                            const currentTime = new Date().getTime();
                            const elapsed = (currentTime - startedTime) / 1000;
                            
                            if (elapsed > 60) {
                                // Show reset button for stuck sync
                                addLogEntry('warning', 'Sync appears to be stuck (running for ' + Math.round(elapsed) + ' seconds)');
                                $('#reset-sync-btn').show();
                                $('#sync-now-btn')
                                    .prop('disabled', true)
                                    .html('<span class="dashicons dashicons-warning"></span> Sync Stuck');
                            } else {
                                // Resume showing running state and start polling
                                syncInProgress = true;
                                $('#sync-now-btn')
                                    .prop('disabled', true)
                                    .html('<span class="dashicons dashicons-update spin"></span> Sync Running...');
                                addLogEntry('info', 'Sync is currently running...');
                                
                                // Start polling for this process
                                if (response.data.process_id) {
                                    startPolling(response.data.process_id);
                                }
                            }
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Update last sync time display
     */
    function updateLastSyncTime() {
        const now = new Date();
        // Use the browser's local timezone
        const formatted = now.toLocaleString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
            timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone
        });
        $('#last-sync-time').text(formatted);
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    /**
     * Cleanup on page unload
     */
    $(window).on('beforeunload', function() {
        stopPolling();
    });
    
})(jQuery);
