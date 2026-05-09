jQuery(document).ready(function($) {
    // Check if required objects and elements exist
    if (typeof w91099chConnector === 'undefined' || !w91099chConnector || !w91099chConnector.ajaxurl || !w91099chConnector.nonce) {
        console.error('w91099chConnector not available');
        return;
    }

    if (!$('#accounting-bookkeeping-plugin-select').length || !$('#accounting-bookkeeping-table-body').length || !$('#accounting-bookkeeping-stats').length) {
        console.error('Required accounting bookkeeping elements not found');
        return;
    }

    const PREVIEW_LIMIT = 25;

    function loadPreview(pluginSlug) {
        const $tableBody = $('#accounting-bookkeeping-table-body');
        const $stats = $('#accounting-bookkeeping-stats');

        $tableBody.html(
            '<tr><td colspan="7" class="py-8 text-center text-gray-500">' +
            '<i class="fas fa-spinner fa-spin text-3xl text-gray-300 mb-3"></i>' +
            '<p>Loading accounting data...</p>' +
            '</td></tr>'
        );

        $.ajax({
            url: w91099chConnector.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'w91099ch_get_accounting_bookkeeping_plugins_preview',
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
                        '<tr><td colspan="7" class="py-8 text-center text-red-500">' +
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
                    const pluginName = $('#accounting-bookkeeping-plugin-select option:selected').text().trim();
                    $stats.text(rows.length + ' records from ' + pluginName);
                } else {
                    const totalPlugins = $('#accounting-bookkeeping-plugin-select option').length - 1; // Exclude "All" option
                    if (totalPlugins > 0) {
                        $stats.text('Showing ' + rows.length + ' records from all ' + totalPlugins + ' accounting plugins');
                    } else {
                        $stats.text('No accounting plugins detected');
                    }
                }
            },
            error: function(xhr) {
                // Try to parse JSON error response first
                let errorMsg = 'Error loading accounting data';
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
                    '<tr><td colspan="7" class="py-8 text-center text-red-500">' +
                    '<i class="fas fa-exclamation-triangle text-3xl mb-3"></i>' +
                    '<p>' + escapeHtml(errorMsg) + '</p>' +
                    '</td></tr>'
                );
            }
        });
    }

    function displayTable(rows) {
        const $tableBody = $('#accounting-bookkeeping-table-body');

        if (!rows || rows.length === 0) {
            $tableBody.html(
                '<tr><td colspan="7" class="py-8 text-center text-gray-500">' +
                '<p>No accounting records found</p>' +
                '</td></tr>'
            );
            return;
        }

        let html = '';
        rows.forEach(function(row) {
            const name = row.name || 'N/A';
            const email = row.email || 'N/A';
            const amount = row.amount || '$0.00';
            const type = row.type || 'N/A';
            const status = row.status || 'Unknown';
            const sourcePlugin = row.source_plugin || 'Unknown';
            const created = row.created || 'N/A';

            // Status styling
            const statusLower = status.toLowerCase();
            let statusClass = 'bg-gray-100 text-gray-600';
            if (statusLower === 'active' || statusLower === 'completed' || statusLower === 'paid') {
                statusClass = 'bg-green-100 text-green-800';
            } else if (statusLower === 'pending' || statusLower === 'processing') {
                statusClass = 'bg-yellow-100 text-yellow-800';
            } else if (statusLower === 'overdue' || statusLower === 'cancelled' || statusLower === 'failed') {
                statusClass = 'bg-red-100 text-red-800';
            }

            html += '<tr>' +
                '<td class="whitespace-nowrap">' + escapeHtml(name) + '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(email) + '</td>' +
                '<td class="whitespace-nowrap font-medium">' + escapeHtml(amount) + '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(type) + '</td>' +
                '<td class="whitespace-nowrap">' +
                '<span class="px-3 py-1 ' + statusClass + ' text-xs font-semibold rounded-full">' + escapeHtml(status) + '</span>' +
                '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(sourcePlugin) + '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(created) + '</td>' +
                '</tr>';
        });

        $tableBody.html(html);
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Plugin change handler
    $('#accounting-bookkeeping-plugin-select').on('change', function() {
        const pluginSlug = $(this).val();
        loadPreview(pluginSlug);
    });

    // Refresh button handler
    const $refreshBtn = $('#refresh-accounting-bookkeeping-plugins');
    if ($refreshBtn.length) {
        $refreshBtn.on('click', function() {
            const $btn = $(this);
            const $icon = $btn.find('i');
            
            $btn.prop('disabled', true);
            $icon.removeClass('fa-arrow-rotate-right').addClass('fa-spinner fa-spin');
            
            $.ajax({
                url: w91099chConnector.ajaxurl,
                type: 'POST',
                data: {
                    action: 'w91099ch_refresh_accounting_bookkeeping_plugins',
                    nonce: w91099chConnector.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updatePluginList(response.data.plugins || {});
                        loadPreview($('#accounting-bookkeeping-plugin-select').val());
                        alert('✅ Accounting plugins refreshed successfully!');
                    } else {
                        alert('❌ Failed to refresh accounting plugins');
                    }
                },
                error: function() {
                    alert('❌ Error refreshing accounting plugins');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $icon.removeClass('fa-spinner fa-spin').addClass('fa-arrow-rotate-right');
                }
            });
        });
    }

    function updatePluginList(plugins) {
        const $select = $('#accounting-bookkeeping-plugin-select');
        if (!$select.length) return;

        if (!plugins || Object.keys(plugins).length === 0) {
            $select.html('<option value="">All Accounting Plugins</option>');
            return;
        }

        let html = '<option value="">All Accounting Plugins</option>';
        Object.keys(plugins).forEach(function(slug) {
            const plugin = plugins[slug];
            html += '<option value="' + escapeHtml(slug) + '">' +
                      escapeHtml(plugin.name) + ' (v' + escapeHtml(plugin.version) + ')</option>';
        });

        $select.html(html);
    }

    // Load initial data
    loadPreview($('#accounting-bookkeeping-plugin-select').val());
});
