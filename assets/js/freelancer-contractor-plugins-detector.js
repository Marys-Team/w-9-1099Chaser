jQuery(document).ready(function($) {
    // Check if required objects and elements exist
    if (typeof w91099chConnector === 'undefined' || !w91099chConnector || !w91099chConnector.ajaxurl || !w91099chConnector.nonce) {
        console.error('w91099chConnector not available');
        return;
    }

    if (!$('#freelancer-contractor-plugin-select').length || !$('#freelancer-contractor-table-body').length || !$('#freelancer-contractor-stats').length) {
        console.error('Required freelancer contractor elements not found');
        return;
    }

    const PREVIEW_LIMIT = 25;

    function loadPreview(pluginSlug) {
        const $tableBody = $('#freelancer-contractor-table-body');
        const $stats = $('#freelancer-contractor-stats');

        $tableBody.html(
            '<tr><td colspan="6" class="py-8 text-center text-gray-500">' +
            '<i class="fas fa-spinner fa-spin text-3xl text-gray-300 mb-3"></i>' +
            '<p>Loading data...</p>' +
            '</td></tr>'
        );

        $.ajax({
            url: w91099chConnector.ajaxurl,
            type: 'POST',
            data: {
                action: 'w91099ch_get_freelancer_contractor_entries_preview',
                nonce: w91099chConnector.nonce,
                plugin_slug: pluginSlug || '',
                limit: PREVIEW_LIMIT
            },
            success: function(response) {
                const rows = response && response.success && response.data && Array.isArray(response.data.rows)
                    ? response.data.rows
                    : [];

                displayTable(rows);

                if (pluginSlug) {
                    const pluginName = $('#freelancer-contractor-plugin-select option:selected').text().trim();
                    $stats.text('Showing ' + rows.length + ' records from ' + pluginName);
                } else {
                    $stats.text('Showing ' + rows.length + ' latest records');
                }
            },
            error: function(xhr) {
                console.error('AJAX error:', xhr.responseText);
                $tableBody.html(
                    '<tr><td colspan="6" class="py-8 text-center text-red-500">' +
                    '<i class="fas fa-exclamation-triangle text-3xl mb-3"></i>' +
                    '<p>Error loading data</p>' +
                    '</td></tr>'
                );
            }
        });
    }

    function displayTable(rows) {
        const $tableBody = $('#freelancer-contractor-table-body');

        if (!rows || rows.length === 0) {
            $tableBody.html(
                '<tr><td colspan="6" class="py-8 text-center text-gray-500">' +
                '<p>No records found</p>' +
                '</td></tr>'
            );
            return;
        }

        let html = '';
        rows.forEach(function(r) {
            const name = r.name || 'N/A';
            const email = r.email || 'N/A';
            const roleType = r.role_type || 'N/A';
            const status = (r.status || 'N/A').toString();
            const statusLower = status.toLowerCase();
            const statusClass = statusLower === 'active' || statusLower === 'approved' || statusLower === 'enabled'
                ? 'bg-green-100 text-green-800'
                : 'bg-gray-100 text-gray-600';
            const source = r.source_plugin || 'N/A';
            const created = r.created || '';

            html += '<tr>' +
                '<td class="whitespace-nowrap">' + escapeHtml(name) + '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(email) + '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(roleType) + '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(source) + '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(created) + '</td>' +
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

    $('#freelancer-contractor-plugin-select').on('change', function() {
        loadPreview($(this).val());
    });

    if ($('#freelancer-contractor-plugin-select').length) {
        loadPreview($('#freelancer-contractor-plugin-select').val());
    }
});
