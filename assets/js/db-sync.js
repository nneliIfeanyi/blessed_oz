(function () {
    const STORAGE_KEY = 'inventory_sync_outbox';
    const LAST_SYNC_KEY = 'inventory_sync_last_attempt';
    const STOCK_CACHE_KEY = 'inventory_sync_stock_cache';
    const DB_NAME = 'inventory_sync_db';
    const DB_VERSION = 1;

    function safeParse(json) {
        try {
            return JSON.parse(json);
        } catch (e) {
            return null;
        }
    }

    function getOutbox() {
        const raw = localStorage.getItem(STORAGE_KEY);
        const parsed = safeParse(raw);
        return Array.isArray(parsed) ? parsed : [];
    }

    function saveOutbox(items) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    }

    function getLastSyncTime() {
        return localStorage.getItem(LAST_SYNC_KEY) || null;
    }

    function setLastSyncTime(isoString) {
        const value = isoString || new Date().toISOString();
        localStorage.setItem(LAST_SYNC_KEY, value);
        return value;
    }

    function formatLastSyncDisplay(isoString) {
        if (!isoString) {
            return 'Never';
        }
        try {
            const d = new Date(isoString);
            if (isNaN(d.getTime())) {
                return 'Never';
            }
            return d.toLocaleString();
        } catch (e) {
            return 'Never';
        }
    }

    function showUpgradeModal() {
        if (typeof bootbox !== 'undefined') {
            bootbox.alert({
                title: 'Pro Feature',
                message: 'Offline transactions are available only with a Pro subscription. Upgrade now to enable offline mode and automatic sync.',
                callback: function () {
                    window.location.href = 'upgrade.php';
                }
            });
        } else {
            alert('Offline mode is a Pro-only feature. Please upgrade to continue.');
            window.location.href = 'upgrade.php';
        }
    }

    function addToOutbox(type, payload) {
        // Check Pro subscription before allowing offline queueing
        if (window.userSession && !window.userSession.isProActive) {
            showUpgradeModal();
            return null;
        }

        const outbox = getOutbox();
        const entry = {
            id: (Date.now() + Math.random()).toString(36),
            type: type,
            payload: payload,
            createdAt: new Date().toISOString(),
            synced: false,
            clientReferenceId: generateClientReferenceId()
        };

        outbox.push(entry);
        saveOutbox(outbox);
        setSyncStatusUI();
        return entry;
    }

    function generateClientReferenceId() {
        return 'CRF-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    }

    function getPendingCount() {
        return getOutbox().filter(item => !item.synced).length;
    }

    function getStockCache() {
        const parsed = safeParse(localStorage.getItem(STOCK_CACHE_KEY));
        return parsed && typeof parsed === 'object' ? parsed : {};
    }

    function saveStockCache(cache) {
        localStorage.setItem(STOCK_CACHE_KEY, JSON.stringify(cache));
    }

    /**
     * Remember last known server stock for an item (call when stock is loaded while online).
     */
    function recordKnownStock(itemNumber, stock) {
        const key = String(itemNumber || '').trim();
        if (key === '') {
            return;
        }
        const n = parseInt(stock, 10);
        if (isNaN(n) || n < 0) {
            return;
        }
        const cache = getStockCache();
        cache[key] = n;
        saveStockCache(cache);
    }

    /**
     * Net stock change from pending (unsynced) offline purchases (+) and sales (-).
     */
    function getPendingStockDelta(itemNumber) {
        const key = String(itemNumber || '').trim();
        if (key === '') {
            return 0;
        }
        let delta = 0;
        getOutbox().filter(function (item) { return !item.synced; }).forEach(function (entry) {
            const items = entry.payload && Array.isArray(entry.payload.items) ? entry.payload.items : [];
            items.forEach(function (it) {
                if (String(it.itemNumber || '').trim() !== key) {
                    return;
                }
                const q = parseInt(it.quantity, 10) || 0;
                if (entry.type === 'purchase') {
                    delta += q;
                } else if (entry.type === 'sale') {
                    delta -= q;
                }
            });
        });
        return delta;
    }

    /**
     * Offline available stock = last known server stock (form or cache) + pending purchase qty − pending sale qty.
     * baselineStock: optional value from the form row (preferred when present).
     * Returns null if we have no baseline knowledge and no offline purchase to raise stock.
     */
    function getOfflineAvailableStock(itemNumber, baselineStock) {
        const key = String(itemNumber || '').trim();
        if (key === '') {
            return null;
        }

        let base = null;
        if (baselineStock !== undefined && baselineStock !== null && String(baselineStock).trim() !== '') {
            const parsed = parseInt(baselineStock, 10);
            if (!isNaN(parsed) && parsed >= 0) {
                base = parsed;
            }
        }
        if (base === null) {
            const cache = getStockCache();
            if (Object.prototype.hasOwnProperty.call(cache, key)) {
                base = parseInt(cache[key], 10);
                if (isNaN(base) || base < 0) {
                    base = null;
                }
            }
        }

        const delta = getPendingStockDelta(key);

        // No server baseline: only offline purchases can create sellable stock offline
        if (base === null) {
            if (delta <= 0) {
                return null; // unknown — cannot validate against live DB
            }
            return delta;
        }

        return Math.max(0, base + delta);
    }

    /**
     * Navbar / status badge rules:
     * - Offline mode          → "Offline" (danger)
     * - Online + pending items → "Pending (N)" (warning)
     * - Online + nothing pending → "Synced" (success)
     */
    function setSyncStatusUI() {
        const badge = document.getElementById('syncStatusBadge');
        if (!badge) {
            return;
        }

        const count = getPendingCount();
        const isOnline = typeof navigator !== 'undefined' ? navigator.onLine : true;

        if (!isOnline) {
            badge.textContent = 'Offline';
            badge.className = 'badge badge-danger';
            badge.style.display = 'inline-block';
            badge.title = count > 0
                ? count + ' transaction(s) waiting to sync when connection returns'
                : 'You are offline';
            return;
        }

        if (count > 0) {
            badge.textContent = 'Pending (' + count + ')';
            badge.className = 'badge badge-warning';
            badge.style.display = 'inline-block';
            badge.title = count + ' transaction(s) waiting to sync';
        } else {
            badge.textContent = 'Synced';
            badge.className = 'badge badge-success';
            badge.style.display = 'inline-block';
            badge.title = 'All transactions synced';
        }

        // Update dashboard last-sync label if present
        const lastSyncEl = document.getElementById('lastSyncTime');
        if (lastSyncEl) {
            lastSyncEl.textContent = formatLastSyncDisplay(getLastSyncTime());
        }
    }

    function updateLastSyncUI() {
        const lastSyncEl = document.getElementById('lastSyncTime');
        if (lastSyncEl) {
            lastSyncEl.textContent = formatLastSyncDisplay(getLastSyncTime());
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        setSyncStatusUI();
        updateLastSyncUI();
    });

    /**
     * Sync pending outbox items.
     * Returns a result object (always truthy as object) for clear UI feedback:
     *   { status: 'offline'|'up_to_date'|'synced'|'partial'|'error',
     *     pendingBefore, syncedCount, failedCount, message }
     */
    async function syncPendingTransactions() {
        setLastSyncTime();
        updateLastSyncUI();

        if (!navigator.onLine) {
            console.log('Offline: skipping sync');
            setSyncStatusUI();
            return {
                status: 'offline',
                pendingBefore: getPendingCount(),
                syncedCount: 0,
                failedCount: 0,
                message: 'You are offline. Connect to the internet to sync.'
            };
        }

        const outbox = getOutbox();
        const pending = outbox.filter(item => !item.synced);
        const pendingBefore = pending.length;

        if (pendingBefore === 0) {
            setSyncStatusUI();
            return {
                status: 'up_to_date',
                pendingBefore: 0,
                syncedCount: 0,
                failedCount: 0,
                message: 'No queued transactions. Everything is up to date.'
            };
        }

        try {
            const response = await fetch('model/sync/syncOutbox.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ transactions: pending })
            });

            if (!response.ok) {
                let errorMsg = 'Sync failed with status ' + response.status;
                try {
                    const errorText = await response.text();
                    if (errorText) {
                        console.error('Server response: ' + errorText);
                        errorMsg += ' - ' + errorText.substring(0, 200);
                    }
                } catch (e) {
                    // Could not read response
                }
                console.error(errorMsg);
                setSyncStatusUI();
                return {
                    status: 'error',
                    pendingBefore: pendingBefore,
                    syncedCount: 0,
                    failedCount: pendingBefore,
                    message: errorMsg
                };
            }

            const result = await response.json();

            if (result.success) {
                const syncedIds = result.synced_ids || [];
                const failedCount = typeof result.failed_count === 'number'
                    ? result.failed_count
                    : 0;
                // Keep only items that were NOT successfully synced
                const remaining = outbox.filter(item => {
                    if (syncedIds.includes(item.id)) {
                        return false; // drop successfully synced
                    }
                    return true;
                });
                saveOutbox(remaining);
                setSyncStatusUI();

                const syncedCount = syncedIds.length;
                if (syncedCount === 0 && failedCount === 0 && pendingBefore > 0) {
                    // Server accepted request but synced nothing unexpected
                    return {
                        status: 'up_to_date',
                        pendingBefore: pendingBefore,
                        syncedCount: 0,
                        failedCount: 0,
                        message: 'No queued transactions. Everything is up to date.'
                    };
                }
                if (failedCount > 0 && syncedCount > 0) {
                    return {
                        status: 'partial',
                        pendingBefore: pendingBefore,
                        syncedCount: syncedCount,
                        failedCount: failedCount,
                        message: 'Synced ' + syncedCount + ' transaction(s); ' + failedCount + ' failed. Check the dashboard for details.'
                    };
                }
                if (failedCount > 0 && syncedCount === 0) {
                    return {
                        status: 'error',
                        pendingBefore: pendingBefore,
                        syncedCount: 0,
                        failedCount: failedCount,
                        message: 'Sync failed for ' + failedCount + ' transaction(s). Check the dashboard for details.'
                    };
                }
                return {
                    status: 'synced',
                    pendingBefore: pendingBefore,
                    syncedCount: syncedCount,
                    failedCount: 0,
                    message: 'Successfully synced ' + syncedCount + ' transaction(s). Everything is up to date.'
                };
            } else {
                const msg = result.message || 'Unknown error';
                console.error('Sync error: ' + msg);
                setSyncStatusUI();
                return {
                    status: 'error',
                    pendingBefore: pendingBefore,
                    syncedCount: 0,
                    failedCount: pendingBefore,
                    message: 'Sync error: ' + msg
                };
            }
        } catch (error) {
            console.error('Sync exception: ' + error.message);
            setSyncStatusUI();
            return {
                status: 'error',
                pendingBefore: pendingBefore,
                syncedCount: 0,
                failedCount: pendingBefore,
                message: 'Sync exception: ' + error.message
            };
        }
    }

    // Auto-sync when connection returns; always refresh badge on online/offline
    window.addEventListener('online', function () {
        setSyncStatusUI();
        syncPendingTransactions();
    });

    window.addEventListener('offline', function () {
        setSyncStatusUI();
    });

    window.inventorySync = {
        addToOutbox,
        getPendingCount,
        setSyncStatusUI,
        getOutbox,
        saveOutbox,
        syncPendingTransactions,
        getLastSyncTime,
        setLastSyncTime,
        formatLastSyncDisplay,
        updateLastSyncUI,
        recordKnownStock,
        getPendingStockDelta,
        getOfflineAvailableStock
    };
})();
