(function() {
    'use strict';

    var config = window.w91099chPaymentLimitScannerConfig || {};
    var isEnabled = !!config.enabled;
    var limit = parseFloat(config.limit);
    var scanDelay = parseInt(config.scan_delay, 10);
    var rowClassName = 'w91099ch-limit-row-exceeded';
    var labelClassName = 'w91099ch-limit-warning-label';
    var styleElementId = 'w91099ch-limit-row-styles';
    var paymentHeaderKeywords = [
        'amount',
        'payment',
        'payout',
        'commission',
        'earning',
        'earnings',
        'paid',
        'due',
        'total',
        'balance',
        'revenue',
        'deposit',
        'withdrawal',
        'withdraw',
        'credit'
    ];
    var currencyCodes = [
        'USD',
        'EUR',
        'GBP',
        'CAD',
        'AUD',
        'NZD',
        'JPY',
        'INR',
        'PKR',
        'AED'
    ];
    var observer = null;
    var scheduledScanId = null;
    var idleScanId = null;

    if (!isEnabled || !isFinite(limit) || limit <= 0 || typeof document === 'undefined') {
        return;
    }

    if (!isFinite(scanDelay) || scanDelay < 50) {
        scanDelay = 150;
    }

    function ensureStyles() {
        if (document.getElementById(styleElementId)) {
            return;
        }

        var style = document.createElement('style');
        style.id = styleElementId;
        style.textContent =
            '.' + rowClassName + '{background:#fef2f2 !important;border-left:4px solid #dc2626 !important;}' +
            '.' + rowClassName + '> th,.' + rowClassName + '> td{background:transparent !important;}' +
            '.' + labelClassName + '{display:inline-flex;align-items:center;margin-left:8px;padding:2px 8px;border-radius:9999px;background:#dc2626;color:#fff;font-size:11px;font-weight:600;line-height:1.4;vertical-align:middle;white-space:nowrap;}';
        document.head.appendChild(style);
    }

    function normalizeText(value) {
        return String(value || '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function normalizeHeader(value) {
        return normalizeText(value).toLowerCase();
    }

    function parseAmount(rawValue) {
        if (rawValue === null || rawValue === undefined) {
            return null;
        }

        var normalized = String(rawValue)
            .replace(/\(([^)]+)\)/, '-$1')
            .replace(/[,\s]+/g, '')
            .replace(/^[^\d+-]+/, '')
            .replace(/[^\d.+-]+$/g, '');
        var amount = parseFloat(normalized);

        return isFinite(amount) ? amount : null;
    }

    function pushMatchesFromRegex(matches, text, regex, amountIndex) {
        var match;
        regex.lastIndex = 0;

        while ((match = regex.exec(text)) !== null) {
            var parsed = parseAmount(match[amountIndex]);
            if (parsed !== null) {
                matches.push(parsed);
            }
        }
    }

    function extractCurrencyAmounts(text) {
        var matches = [];
        var safeText = normalizeText(text);

        if (!safeText) {
            return matches;
        }

        pushMatchesFromRegex(
            matches,
            safeText,
            /(?:^|[^\w])[$€£¥₹]\s*([-+]?\d{1,3}(?:,\d{3})*(?:\.\d+)?|[-+]?\d+(?:\.\d+)?)(?![\w-])/g,
            1
        );
        pushMatchesFromRegex(
            matches,
            safeText,
            /(?:^|[^\w])([-+]?\d{1,3}(?:,\d{3})*(?:\.\d+)?|[-+]?\d+(?:\.\d+)?)(?:\s*)(USD|EUR|GBP|CAD|AUD|NZD|JPY|INR|PKR|AED)(?![A-Z])/gi,
            1
        );
        pushMatchesFromRegex(
            matches,
            safeText,
            /(?:^|[^\w])(USD|EUR|GBP|CAD|AUD|NZD|JPY|INR|PKR|AED)(?:\s*)([-+]?\d{1,3}(?:,\d{3})*(?:\.\d+)?|[-+]?\d+(?:\.\d+)?)(?![\w-])/gi,
            2
        );

        return matches;
    }

    function extractPlainAmount(text) {
        var safeText = normalizeText(text);

        if (!safeText || /%/.test(safeText) || /[/:]/.test(safeText)) {
            return null;
        }

        if (!/^[-+()$€£¥₹\d\s,.A-Z]+$/i.test(safeText)) {
            return null;
        }

        var cleaned = safeText.replace(new RegExp('\\b(?:' + currencyCodes.join('|') + ')\\b', 'gi'), '').trim();
        if (!/^[-+()$€£¥₹\d\s,.]+$/i.test(cleaned)) {
            return null;
        }

        return parseAmount(cleaned);
    }

    function getHeaderTexts(table) {
        var headerCells = table.querySelectorAll('thead th');
        if (!headerCells.length) {
            headerCells = table.querySelectorAll('tr:first-child th');
        }

        return Array.prototype.map.call(headerCells, function(cell) {
            return normalizeHeader(cell.textContent);
        });
    }

    function getPaymentColumnIndexes(table) {
        var headerTexts = getHeaderTexts(table);
        var indexes = [];

        headerTexts.forEach(function(headerText, index) {
            for (var i = 0; i < paymentHeaderKeywords.length; i += 1) {
                if (headerText.indexOf(paymentHeaderKeywords[i]) !== -1) {
                    indexes.push(index);
                    break;
                }
            }
        });

        return indexes;
    }

    function getMaxAmountFromCells(cells, tablePaymentIndexes) {
        var matches = [];

        Array.prototype.forEach.call(cells, function(cell, index) {
            var cellText = normalizeText(cell.textContent);
            if (!cellText) {
                return;
            }

            if (tablePaymentIndexes.indexOf(index) !== -1) {
                var explicitAmounts = extractCurrencyAmounts(cellText);
                if (explicitAmounts.length) {
                    matches = matches.concat(explicitAmounts);
                    return;
                }

                var plainAmount = extractPlainAmount(cellText);
                if (plainAmount !== null) {
                    matches.push(plainAmount);
                    return;
                }
            }

            matches = matches.concat(extractCurrencyAmounts(cellText));
        });

        if (!matches.length) {
            return null;
        }

        return Math.max.apply(Math, matches);
    }

    function getRowSignature(row) {
        var signatureSource = normalizeText(row.textContent)
            .replace(/\bLimit Exceeded\b/gi, '')
            .trim();

        return String(limit) + '|' + signatureSource;
    }

    function getLabelAnchorCell(row) {
        return row.querySelector('td, th');
    }

    function formatAmount(amount) {
        if (!isFinite(amount)) {
            return '';
        }

        return amount % 1 === 0 ? String(amount) : amount.toFixed(2);
    }

    function buildTooltipMessage(amount) {
        var template = normalizeText(config.tooltip_text);

        if (!template) {
            template = 'This row was flagged by the W9-1099 Chaser plugin because the detected payment amount ({amount}) is greater than or equal to your configured limit ({limit}).';
        }

        return template
            .replace(/\{amount\}/g, formatAmount(amount))
            .replace(/\{limit\}/g, formatAmount(limit))
            .replace(/\{plugin\}/g, config.plugin_name || 'W9-1099 Chaser');
    }

    function applyWarningState(row, amount) {
        var labelAnchor = getLabelAnchorCell(row);
        var label = row.querySelector('.' + labelClassName);
        var tooltip = buildTooltipMessage(amount);

        row.classList.add(rowClassName);
        row.dataset.w91099chLimitExceeded = '1';
        row.dataset.w91099chLimitAmount = String(amount);
        row.setAttribute('title', tooltip);
        row.setAttribute('aria-label', tooltip);

        if (!labelAnchor) {
            return;
        }

        if (!label) {
            label = document.createElement('span');
            label.className = labelClassName;
            labelAnchor.appendChild(label);
        }

        label.textContent = config.warning_label || 'Limit Exceeded';
        label.setAttribute('title', tooltip);
        label.setAttribute('aria-label', tooltip);
    }

    function clearWarningState(row) {
        var label = row.querySelector('.' + labelClassName);

        row.classList.remove(rowClassName);
        delete row.dataset.w91099chLimitExceeded;
        delete row.dataset.w91099chLimitAmount;
        row.removeAttribute('title');
        row.removeAttribute('aria-label');

        if (label && label.parentNode) {
            label.parentNode.removeChild(label);
        }
    }

    function processRow(row, tablePaymentIndexes) {
        if (!row || row.closest('thead, tfoot')) {
            return;
        }

        var cells = row.querySelectorAll('th, td');
        if (!cells.length) {
            return;
        }

        var signature = getRowSignature(row);
        if (row.dataset.w91099chLimitSignature === signature) {
            return;
        }

        var maxAmount = getMaxAmountFromCells(cells, tablePaymentIndexes);
        if (maxAmount !== null && maxAmount >= limit) {
            applyWarningState(row, maxAmount);
        } else {
            clearWarningState(row);
        }

        row.dataset.w91099chLimitSignature = signature;
    }

    function scanTable(table) {
        var paymentColumnIndexes = getPaymentColumnIndexes(table);
        var bodyRows = table.querySelectorAll('tbody tr');
        var rows = bodyRows.length ? bodyRows : table.querySelectorAll('tr');

        Array.prototype.forEach.call(rows, function(row) {
            processRow(row, paymentColumnIndexes);
        });
    }

    function performScan() {
        scheduledScanId = null;
        idleScanId = null;

        var tables = document.querySelectorAll((config.table_selector || 'table') + ':not(.w91099ch-limit-table-ignore)');
        Array.prototype.forEach.call(tables, scanTable);
    }

    function runWhenIdle() {
        if (typeof window.requestIdleCallback === 'function') {
            idleScanId = window.requestIdleCallback(performScan, { timeout: 400 });
        } else {
            idleScanId = window.setTimeout(performScan, 0);
        }
    }

    function scheduleScan() {
        if (scheduledScanId !== null) {
            window.clearTimeout(scheduledScanId);
        }

        if (idleScanId !== null) {
            if (typeof window.cancelIdleCallback === 'function') {
                window.cancelIdleCallback(idleScanId);
            } else {
                window.clearTimeout(idleScanId);
            }
            idleScanId = null;
        }

        scheduledScanId = window.setTimeout(runWhenIdle, scanDelay);
    }

    function shouldScheduleForMutation(mutations) {
        for (var i = 0; i < mutations.length; i += 1) {
            var mutation = mutations[i];

            if (mutation.type === 'characterData') {
                return true;
            }

            if (mutation.type === 'childList') {
                if (
                    (mutation.target && mutation.target.nodeType === 1 && mutation.target.closest && mutation.target.closest('table')) ||
                    Array.prototype.some.call(mutation.addedNodes, function(node) {
                        return node.nodeType === 1 && (node.matches('table, tr, td, th') || node.querySelector('table, tr, td, th'));
                    }) ||
                    Array.prototype.some.call(mutation.removedNodes, function(node) {
                        return node.nodeType === 1 && (node.matches('tr, td, th') || node.querySelector('tr, td, th'));
                    })
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    function attachObserver() {
        if (typeof window.MutationObserver !== 'function' || !document.body) {
            return;
        }

        observer = new window.MutationObserver(function(mutations) {
            if (shouldScheduleForMutation(mutations)) {
                scheduleScan();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }

    function bindEvents() {
        if (typeof window.jQuery === 'function') {
            window.jQuery(document).ajaxComplete(function() {
                scheduleScan();
            });
        }

        document.addEventListener('click', function(event) {
            var target = event.target;
            if (!target || !target.closest) {
                return;
            }

            if (target.closest('.tablenav a, .pagination-links a, .manage-column.sortable a, .manage-column.sorted a, .filter-links a, .subsubsub a, .bulkactions select, .tablenav-pages a')) {
                scheduleScan();
            }
        }, true);

        document.addEventListener('change', function(event) {
            var target = event.target;
            if (!target || !target.closest) {
                return;
            }

            if (target.closest('.tablenav, .search-form, form')) {
                scheduleScan();
            }
        }, true);
    }

    ensureStyles();
    bindEvents();
    attachObserver();
    scheduleScan();
    window.addEventListener('load', scheduleScan);
})();
