/**
 * Offline catalog (localStorage): items, customers, vendors per store.
 * Sale search filters items with available stock > 0; purchase shows all cached items.
 */
(function () {
    var PREFIX = 'inv_offline_';

    function safeParse(json) {
        try {
            return JSON.parse(json);
        } catch (e) {
            return null;
        }
    }

    function activeStoreID() {
        if (window.userSession && window.userSession.activeStoreID) {
            return String(window.userSession.activeStoreID);
        }
        var el = document.getElementById('storeSwitcher');
        if (el && el.value) {
            return String(el.value);
        }
        return '1';
    }

    function key(kind) {
        return PREFIX + kind + '_' + activeStoreID();
    }

    function metaKey() {
        return PREFIX + 'meta_' + activeStoreID();
    }

    function getList(kind) {
        var parsed = safeParse(localStorage.getItem(key(kind)));
        return Array.isArray(parsed) ? parsed : [];
    }

    function setList(kind, arr) {
        localStorage.setItem(key(kind), JSON.stringify(Array.isArray(arr) ? arr : []));
    }

    function getMeta() {
        var m = safeParse(localStorage.getItem(metaKey()));
        return m && typeof m === 'object' ? m : null;
    }

    function setMeta(meta) {
        localStorage.setItem(metaKey(), JSON.stringify(meta || {}));
    }

    function hasCatalog() {
        return getList('items').length > 0;
    }

    function hasCustomers() {
        return getList('customers').length > 0;
    }

    function hasVendors() {
        return getList('vendors').length > 0;
    }

    function emptyMessage(kind) {
        var labels = {
            items: 'Item list not loaded for offline use. Open the app once while online on this device.',
            customers: 'Customer list not loaded for offline use. Open the app once while online on this device.',
            vendors: 'Vendor list not loaded for offline use. Open the app once while online on this device.'
        };
        return labels[kind] || 'Offline data not loaded. Connect once while logged in.';
    }

    function normalizeQuery(q) {
        return String(q || '').trim().toLowerCase();
    }

    function itemAvailable(item) {
        var baseline = item && item.stock != null ? item.stock : 0;
        if (window.inventorySync && typeof window.inventorySync.getOfflineAvailableStock === 'function') {
            var avail = window.inventorySync.getOfflineAvailableStock(item.itemNumber, baseline);
            if (avail === null) {
                return Math.max(0, parseInt(baseline, 10) || 0);
            }
            return avail;
        }
        return Math.max(0, parseInt(baseline, 10) || 0);
    }

    /**
     * @param {string} query
     * @param {{ forSale?: boolean, limit?: number }} opts
     * forSale: true → only items with available stock > 0
     */
    function searchItems(query, opts) {
        opts = opts || {};
        var limit = opts.limit || 20;
        var forSale = !!opts.forSale;
        var q = normalizeQuery(query);
        if (q === '') {
            return [];
        }
        var items = getList('items');
        var prefix = [];
        var contains = [];
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            var num = String(it.itemNumber || '').toLowerCase();
            var name = String(it.itemName || '').toLowerCase();
            if (num.indexOf(q) === -1 && name.indexOf(q) === -1) {
                continue;
            }
            if (forSale && itemAvailable(it) <= 0) {
                continue;
            }
            if (num.indexOf(q) === 0) {
                prefix.push(it);
            } else {
                contains.push(it);
            }
        }
        return prefix.concat(contains).slice(0, limit);
    }

    /**
     * Resolve catalog item by itemNumber (preferred) or itemName (exact, then unique partial).
     * Users often type/search by name offline; the number field may briefly hold a name.
     */
    function getItem(itemNumberOrName) {
        var want = String(itemNumberOrName || '').trim();
        if (want === '') {
            return null;
        }
        var items = getList('items');
        var i;
        // 1) Exact item number
        for (i = 0; i < items.length; i++) {
            if (String(items[i].itemNumber) === want) {
                return items[i];
            }
        }
        // 2) Case-insensitive item number
        var wantLower = want.toLowerCase();
        for (i = 0; i < items.length; i++) {
            if (String(items[i].itemNumber || '').toLowerCase() === wantLower) {
                return items[i];
            }
        }
        // 3) Exact item name (case-insensitive)
        for (i = 0; i < items.length; i++) {
            if (String(items[i].itemName || '').toLowerCase() === wantLower) {
                return items[i];
            }
        }
        // 4) Unique partial name match
        var partial = [];
        for (i = 0; i < items.length; i++) {
            if (String(items[i].itemName || '').toLowerCase().indexOf(wantLower) !== -1) {
                partial.push(items[i]);
            }
        }
        if (partial.length === 1) {
            return partial[0];
        }
        return null;
    }

    function searchCustomers(query, limit) {
        limit = limit || 15;
        var q = normalizeQuery(query);
        if (q === '') {
            return [];
        }
        var list = getList('customers');
        var out = [];
        for (var i = 0; i < list.length && out.length < limit; i++) {
            var c = list[i];
            var id = String(c.customerID || '');
            var name = String(c.fullName || '').toLowerCase();
            if (id.indexOf(q) !== -1 || name.indexOf(q) !== -1) {
                out.push(c);
            }
        }
        return out;
    }

    function getCustomer(customerID) {
        var want = String(customerID || '').trim();
        if (want === '') {
            return null;
        }
        var list = getList('customers');
        for (var i = 0; i < list.length; i++) {
            if (String(list[i].customerID) === want) {
                return list[i];
            }
        }
        return null;
    }

    function searchVendors(query, limit) {
        limit = limit || 20;
        var q = normalizeQuery(query);
        var list = getList('vendors');
        if (q === '') {
            return list.slice(0, limit);
        }
        var out = [];
        for (var i = 0; i < list.length && out.length < limit; i++) {
            var v = list[i];
            var id = String(v.vendorID || '');
            var name = String(v.fullName || '').toLowerCase();
            if (id.indexOf(q) !== -1 || name.indexOf(q) !== -1) {
                out.push(v);
            }
        }
        return out;
    }

    function getVendorByName(fullName) {
        var want = String(fullName || '').trim().toLowerCase();
        if (want === '') {
            return null;
        }
        var list = getList('vendors');
        for (var i = 0; i < list.length; i++) {
            if (String(list[i].fullName || '').toLowerCase() === want) {
                return list[i];
            }
        }
        return null;
    }

    function renderItemSuggestionsHtml(items, listId) {
        if (!items.length) {
            return '';
        }
        listId = listId || 'itemNumberSuggestionsList';
        var html = '<ul class="list-unstyled suggestionsList" id="' + listId + '">';
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            var label = escapeHtml(it.itemNumber);
            if (it.itemName) {
                label += ' — ' + escapeHtml(it.itemName);
            }
            html += '<li data-item-number="' + escapeHtml(it.itemNumber) + '">' + label + '</li>';
        }
        html += '</ul>';
        return html;
    }

    function renderCustomerSuggestionsHtml(customers) {
        if (!customers.length) {
            return '';
        }
        var html = '<ul class="list-unstyled suggestionsList" id="saleDetailsCustomerIDSuggestionsList">';
        for (var i = 0; i < customers.length; i++) {
            var c = customers[i];
            html += '<li data-customer-id="' + escapeHtml(String(c.customerID)) + '">' +
                escapeHtml(c.fullName) + ' (ID: ' + escapeHtml(String(c.customerID)) + ')</li>';
        }
        html += '</ul>';
        return html;
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Detect which offline list a showSuggestions scriptPath targets.
     */
    function detectSuggestionKind(scriptPath) {
        var p = String(scriptPath || '');
        if (p.indexOf('showCustomerIDsForSaleTab') !== -1 || p.indexOf('showCustomerIDs') !== -1) {
            return 'customers';
        }
        if (p.indexOf('showItemNumberForSaleTab') !== -1 || p.indexOf('ForSaleTab') !== -1) {
            return 'items_sale';
        }
        if (p.indexOf('showItemNumberForPurchaseTab') !== -1 || p.indexOf('ForPurchaseTab') !== -1) {
            return 'items_purchase';
        }
        if (p.indexOf('showItemNumber') !== -1 || p.indexOf('showItemNames') !== -1) {
            return 'items_all';
        }
        if (p.indexOf('showVendor') !== -1 || p.indexOf('Vendor') !== -1) {
            return 'vendors';
        }
        return null;
    }

    function applyOfflineSuggestions(textBoxID, scriptPath, suggestionsDivID) {
        var kind = detectSuggestionKind(scriptPath);
        var q = $('#' + textBoxID).val();
        var $div = $('#' + suggestionsDivID);

        if (!kind) {
            $div.fadeOut().empty();
            return false;
        }

        if (kind === 'customers') {
            if (!hasCustomers()) {
                $div.fadeIn().html('<div class="alert alert-warning py-1 px-2 mb-0 small">' + emptyMessage('customers') + '</div>');
                return true;
            }
            var customers = searchCustomers(q);
            var cHtml = renderCustomerSuggestionsHtml(customers);
            if (cHtml) {
                $div.fadeIn().html(cHtml);
            } else {
                $div.fadeOut().empty();
            }
            return true;
        }

        if (kind === 'items_sale' || kind === 'items_purchase' || kind === 'items_all') {
            if (!hasCatalog()) {
                $div.fadeIn().html('<div class="alert alert-warning py-1 px-2 mb-0 small">' + emptyMessage('items') + '</div>');
                return true;
            }
            var forSale = kind === 'items_sale';
            var hits = searchItems(q, { forSale: forSale, limit: 20 });
            // Dynamic list id for multi-row sale/purchase
            var listId = $div.find('ul').attr('id') || (textBoxID + 'SuggestionsList');
            var iHtml = renderItemSuggestionsHtml(hits, listId);
            if (iHtml) {
                $div.fadeIn().html(iHtml);
            } else {
                $div.fadeOut().empty();
            }
            return true;
        }

        return false;
    }

    function fillVendorSelectFromCache() {
        var $select = $('#purchaseDetailsVendorName');
        if (!$select.length) {
            return;
        }
        var vendors = getList('vendors');
        if (!vendors.length) {
            return;
        }
        var current = $select.val();
        var opts = '<option value="">-- Select Vendor --</option>';
        for (var i = 0; i < vendors.length; i++) {
            var name = vendors[i].fullName || '';
            opts += '<option value="' + escapeHtml(name) + '">' + escapeHtml(name) + '</option>';
        }
        $select.html(opts);
        if (current) {
            $select.val(current);
        }
        if ($select.hasClass('chosenSelect') && typeof $select.trigger === 'function') {
            try {
                $select.trigger('chosen:updated');
            } catch (e) { /* chosen may not be ready */ }
        }
    }

    /**
     * Fetch catalog from server and store in localStorage.
     */
    function refreshFromServer() {
        if (!navigator.onLine) {
            return Promise.resolve({ success: false, message: 'Offline' });
        }
        return fetch('model/offline/exportOfflineCatalog.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('Catalog export failed: ' + res.status);
            }
            return res.json();
        }).then(function (data) {
            if (!data || !data.success) {
                throw new Error((data && data.message) || 'Catalog export failed');
            }
            // Keys use activeStoreID(); export may be for that store
            setList('items', data.items || []);
            setList('customers', data.customers || []);
            setList('vendors', data.vendors || []);
            setMeta({
                exportedAt: data.exportedAt || new Date().toISOString(),
                storeID: data.storeID,
                counts: data.counts || {
                    items: (data.items || []).length,
                    customers: (data.customers || []).length,
                    vendors: (data.vendors || []).length
                }
            });
            // Seed stock cache for offline availability math
            if (window.inventorySync && typeof window.inventorySync.recordKnownStock === 'function') {
                (data.items || []).forEach(function (it) {
                    window.inventorySync.recordKnownStock(it.itemNumber, it.stock);
                });
            }
            fillVendorSelectFromCache();
            return data;
        });
    }

    function statusText() {
        var meta = getMeta();
        if (!meta || !hasCatalog()) {
            return 'Offline catalog: not loaded';
        }
        var when = meta.exportedAt ? new Date(meta.exportedAt).toLocaleString() : 'unknown';
        var c = meta.counts || {};
        return 'Offline catalog: ' + (c.items || 0) + ' items, ' +
            (c.customers || 0) + ' customers, ' + (c.vendors || 0) + ' vendors · ' + when;
    }

    // Prefetch when online
    document.addEventListener('DOMContentLoaded', function () {
        if (navigator.onLine) {
            refreshFromServer().catch(function (err) {
                console.warn('Offline catalog refresh:', err.message || err);
            });
        } else {
            fillVendorSelectFromCache();
        }
    });

    window.addEventListener('online', function () {
        refreshFromServer().catch(function () {});
    });

    window.offlineCatalog = {
        refreshFromServer: refreshFromServer,
        getList: getList,
        getMeta: getMeta,
        hasCatalog: hasCatalog,
        hasCustomers: hasCustomers,
        hasVendors: hasVendors,
        emptyMessage: emptyMessage,
        searchItems: searchItems,
        getItem: getItem,
        searchCustomers: searchCustomers,
        getCustomer: getCustomer,
        searchVendors: searchVendors,
        getVendorByName: getVendorByName,
        applyOfflineSuggestions: applyOfflineSuggestions,
        fillVendorSelectFromCache: fillVendorSelectFromCache,
        itemAvailable: itemAvailable,
        statusText: statusText,
        detectSuggestionKind: detectSuggestionKind
    };
})();
