jQuery(document).ready(function($) {
    // Check if required objects and elements exist
    if (typeof w91099chConnector === 'undefined' || !w91099chConnector || !w91099chConnector.ajaxurl || !w91099chConnector.nonce) {
        console.error('w91099chConnector not available');
        return;
    }

    if (!$('#wallet-payout-plugin-select').length || !$('#wallet-payout-table-body').length || !$('#wallet-payout-stats').length) {
        console.error('Required wallet payout elements not found');
        return;
    }

    const PREVIEW_LIMIT = 25;

    function loadPreview(pluginSlug) {
        const $tableBody = $('#wallet-payout-table-body');
        const $stats = $('#wallet-payout-stats');

        $tableBody.html(
            '<tr><td colspan="5" class="py-8 text-center text-gray-500">' +
            '<i class="fas fa-spinner fa-spin text-3xl text-gray-300 mb-3"></i>' +
            '<p>Loading data...</p>' +
            '</td></tr>'
        );

        $.ajax({
            url: w91099chConnector.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'w91099ch_get_wallet_payout_entries_preview',
                nonce: w91099chConnector.nonce,
                plugin_slug: pluginSlug || '',
                limit: PREVIEW_LIMIT
            },
            success: function(response) {
                if (!response || response.success !== true) {
                    // Handle error response properly - response.data might be an object
                    let msg = 'Request failed';
                    if (response && response.data !== undefined && response.data !== null) {
                        if (typeof response.data === 'string') {
                            msg = response.data;
                        } else if (typeof response.data === 'object' && response.data.message) {
                            msg = response.data.message;
                        } else if (typeof response.data === 'object') {
                            msg = JSON.stringify(response.data);
                        }
                    }
                    $tableBody.html(
                        '<tr><td colspan="5" class="py-8 text-center text-red-500">' +
                        '<i class="fas fa-exclamation-triangle text-3xl mb-3"></i>' +
                        '<p>' + escapeHtml(msg) + '</p>' +
                        '</td></tr>'
                    );
                    $stats.text('');
                    return;
                }

                const rows = response.data && Array.isArray(response.data.rows)
                    ? response.data.rows
                    : [];

                displayTable(rows);

                if (pluginSlug && pluginSlug !== '') {
                    const pluginName = $('#wallet-payout-plugin-select option:selected').text().trim();
                    $stats.text('Showing ' + rows.length + ' transactions from ' + pluginName);
                } else {
                    $stats.text('Showing ' + rows.length + ' transactions from all wallet plugins');
                }
            },
            error: function(xhr) {
                // Try to parse JSON error response first
                let errorMsg = 'Error loading data';
                try {
                    if (xhr && xhr.responseText) {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.data && response.data.message) {
                            errorMsg = response.data.message;
                        } else if (response && response.data) {
                            errorMsg = typeof response.data === 'string' ? response.data : JSON.stringify(response.data);
                        }
                    }
                } catch (e) {
                    // If not JSON, use status text
                    errorMsg = (xhr && xhr.statusText) ? xhr.statusText : 'Network error';
                }
                
                console.error('AJAX error:', xhr.responseText);
                $tableBody.html(
                    '<tr><td colspan="5" class="py-8 text-center text-red-500">' +
                    '<i class="fas fa-exclamation-triangle text-3xl mb-3"></i>' +
                    '<p>' + escapeHtml(errorMsg) + '</p>' +
                    '</td></tr>'
                );
            }
        });
    }

    function displayTable(rows) {
        const $tableBody = $('#wallet-payout-table-body');

        if (!rows || rows.length === 0) {
            $tableBody.html(
                '<tr><td colspan="5" class="py-8 text-center text-gray-500">' +
                '<p>No transactions found</p>' +
                '</td></tr>'
            );
            return;
        }

        let html = '';
        rows.forEach(function(r) {
            const user = r.user_name || r.user_email || 'N/A';
            const email = r.user_email || 'N/A';
            const amount = r.amount ? '$' + parseFloat(r.amount).toFixed(2) : '$0.00';
            const plugin = $('#wallet-payout-plugin-select option:selected').text().trim() || 'Wallet Plugin';
            const status = (r.status || 'active').toString();
            const statusLower = status.toLowerCase();
            
            let statusClass = 'bg-gray-100 text-gray-600';
            if (statusLower === 'active' || statusLower === 'completed' || statusLower === 'success') {
                statusClass = 'bg-green-100 text-green-800';
            } else if (statusLower === 'pending') {
                statusClass = 'bg-yellow-100 text-yellow-800';
            } else if (statusLower === 'failed' || statusLower === 'cancelled') {
                statusClass = 'bg-red-100 text-red-800';
            }

            html += '<tr>' +
                '<td class="whitespace-nowrap">' + escapeHtml(user) + '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(email) + '</td>' +
                '<td class="whitespace-nowrap font-medium">' + escapeHtml(amount) + '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(plugin) + '</td>' +
                '<td class="whitespace-nowrap"><span class="px-3 py-1 ' + statusClass + ' text-xs font-semibold rounded-full">' + escapeHtml(status) + '</span></td>' +
                '</tr>';
        });

        $tableBody.html(html);
    }

    function escapeHtml(str) {
        return String(str === null || str === undefined ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    $('#wallet-payout-plugin-select').on('change', function() {
        const selectedValue = $(this).val();
        loadPreview(selectedValue);
    });

    // Load initial data when page loads
    if ($('#wallet-payout-plugin-select').length) {
        loadPreview($('#wallet-payout-plugin-select').val());
    }
});
