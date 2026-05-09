jQuery(document).ready(function($) {
    if (typeof w91099chConnector === 'undefined' || !w91099chConnector || !w91099chConnector.ajaxurl || !w91099chConnector.nonce) {
        console.error('w91099chConnector not available');
        return;
    }

    if (!$('#form-plugin-select').length || !$('#forms-table-body').length || !$('#forms-stats').length) {
        console.error('Required form elements not found');
        return;
    }

    let excludedFormIds = {};
    const ENTRIES_PREVIEW_LIMIT = 25;
    
    function loadExcludedForms() {
        return $.ajax({
            url: w91099chConnector.ajaxurl,
            type: 'POST',
            data: {
                action: 'w91099ch_get_excluded_forms',
                nonce: w91099chConnector.nonce
            }
        }).then(function(resp) {
            const ids = resp && resp.success && resp.data && Array.isArray(resp.data.excluded_ids)
                ? resp.data.excluded_ids
                : [];
            excludedFormIds = {};
            ids.forEach(function(id) {
                excludedFormIds[String(id)] = true;
            });
            return ids;
        }, function() {
            excludedFormIds = {};
            return [];
        });
    }

    function loadEntriesPreview(pluginSlug) {
        const $tableBody = $('#forms-table-body');
        const $stats = $('#forms-stats');

        $tableBody.html(
            '<tr><td colspan="8" class="py-8 text-center text-gray-500">' +
            '<i class="fas fa-spinner fa-spin text-3xl text-gray-300 mb-3"></i>' +
            '<p>Loading entries...</p>' +
            '</td></tr>'
        );

        $.ajax({
            url: w91099chConnector.ajaxurl,
            type: 'POST',
            data: {
                action: 'w91099ch_get_form_entries_preview',
                nonce: w91099chConnector.nonce,
                plugin_slug: pluginSlug || '',
                limit: ENTRIES_PREVIEW_LIMIT
            },
            success: function(response) {
                const entries = response && response.success && response.data && Array.isArray(response.data.entries)
                    ? response.data.entries
                    : [];

                displayEntriesPreviewTable(entries);

                if (pluginSlug) {
                    const pluginName = $('#form-plugin-select option:selected').text().split('(')[0].trim();
                    $stats.text('Showing ' + entries.length + ' entries from ' + pluginName);
                } else {
                    $stats.text('Showing ' + entries.length + ' latest entries');
                }
            },
            error: function(xhr) {
                console.error('AJAX error:', xhr.responseText);
                $tableBody.html(
                    '<tr><td colspan="8" class="py-8 text-center text-red-500">' +
                    '<i class="fas fa-exclamation-triangle text-3xl mb-3"></i>' +
                    '<p>Error loading entries</p>' +
                    '</td></tr>'
                );
            }
        });
    }

    function displayEntriesPreviewTable(entries) {
        const $tableBody = $('#forms-table-body');

        if (!entries || entries.length === 0) {
            $tableBody.html(
                '<tr><td colspan="8" class="py-8 text-center text-gray-500">' +
                '<p>No entries found</p>' +
                '</td></tr>'
            );
            return;
        }

        let html = '';
        entries.forEach(function(entry) {
            const pluginSlug = entry.plugin_slug || '';
            const entryId = entry.entry_id || entry.id || '';
            const formId = entry.form_id || '';
            const excludeId = pluginSlug + '_' + formId;
            const isExcluded = !!excludedFormIds[excludeId];
            const rowStyle = isExcluded ? ' style="opacity:0.55; background:#f8f9fa;"' : '';

            const status = (entry.status || '').toString();
            const statusLower = status.toLowerCase();
            const statusClass = statusLower === 'active' || statusLower === 'completed' || statusLower === 'publish' || statusLower === 'published'
                ? 'bg-green-100 text-green-800'
                : 'bg-gray-100 text-gray-600';
            const statusText = status ? status.toUpperCase() : 'N/A';

            const fieldsText = summarizeFields(entry.fields);

            const pluginName = entry.plugin_name || pluginSlug || '';
            const formTitle = entry.form_title || formId || '';
            const created = entry.date || '';

            html += '<tr' + rowStyle + '>' +
                '<td class="whitespace-nowrap text-center">' +
                '<input type="checkbox" class="form-exclude-checkbox h-4 w-4 text-blue-600 border-gray-300 rounded" data-form-id="' + escapeHtml(excludeId) + '"' +
                (isExcluded ? ' checked' : '') + ' />' +
                '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(entryId) + '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(entry.name || 'N/A') + '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(entry.email || 'N/A') + '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(pluginName) + '</td>' +
                '<td class="whitespace-nowrap" title="' + escapeHtml(fieldsText) + '">' + escapeHtml(formTitle) + '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(created) + '</td>' +
                '<td class="whitespace-nowrap">' +
                '<span class="px-3 py-1 ' + statusClass + ' text-xs font-semibold rounded-full">' + escapeHtml(statusText) + '</span>' +
                '</td>' +
                '</tr>';
        });

        $tableBody.html(html);
    }

    function summarizeFields(fields) {
        if (!fields) {
            return '';
        }
        if (typeof fields === 'string') {
            return fields;
        }
        if (Array.isArray(fields)) {
            return fields.join(' ');
        }
        if (typeof fields !== 'object') {
            return String(fields);
        }
        const parts = [];
        Object.keys(fields).forEach(function(k) {
            const v = fields[k];
            if (v === null || v === undefined) {
                return;
            }
            const vs = String(v);
            if (!vs) {
                return;
            }
            parts.push(k + ': ' + vs);
        });
        return parts.join(' | ');
    }

    function truncateText(text, maxLen) {
        const s = String(text || '');
        if (s.length <= maxLen) {
            return s;
        }
        return s.slice(0, maxLen - 3) + '...';
    }
    
    function saveExcludedForms() {
        const ids = Object.keys(excludedFormIds).filter(function(k) { return !!k; });
        $.ajax({
            url: w91099chConnector.ajaxurl,
            type: 'POST',
            data: {
                action: 'w91099ch_set_excluded_forms',
                nonce: w91099chConnector.nonce,
                excluded_ids: ids
            }
        });
    }
    
    const $refreshFormPluginsBtn = $('#refresh-form-plugins');
    if ($refreshFormPluginsBtn.length) {
    $refreshFormPluginsBtn.on('click', function() {
        const $btn = $(this);
        const $icon = $btn.find('i');
        
        $btn.prop('disabled', true);
        $icon.removeClass('fa-arrow-rotate-right').addClass('fa-spinner fa-spin');
        
        $.ajax({
            url: w91099chConnector.ajaxurl,
            type: 'POST',
            data: {
                action: 'w91099ch_refresh_form_plugins',
                nonce: w91099chConnector.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateFormPluginsList(response.data.plugins);
                    $('#total-forms-count').text(response.data.total_forms || 0);
                    loadForms($('#form-plugin-select').val());
                    alert('✅ Form plugins refreshed successfully!');
                } else {
                    alert('❌ Failed to refresh form plugins');
                }
            },
            error: function() {
                alert('❌ Error refreshing form plugins');
            },
            complete: function() {
                $btn.prop('disabled', false);
                $icon.removeClass('fa-spinner fa-spin').addClass('fa-arrow-rotate-right');
            }
        });
    });
    }
    
    $('#form-plugin-select').on('change', function() {
        const pluginSlug = $(this).val();
        loadForms(pluginSlug);
    });
    
    $(document).on('change', '.form-exclude-checkbox', function() {
        const $checkbox = $(this);
        const formId = $checkbox.data('form-id');
        const $row = $checkbox.closest('tr');
        
        if ($checkbox.is(':checked')) {
            excludedFormIds[String(formId)] = true;
            if ($row.length) {
                $row.css({ opacity: 0.55, background: '#f8f9fa' });
            }
        } else {
            delete excludedFormIds[String(formId)];
            if ($row.length) {
                $row.css({ opacity: '', background: '' });
            }
        }
        
        saveExcludedForms();
    });
    
    function loadForms(pluginSlug) {
        const $tableBody = $('#forms-table-body');
        const $stats = $('#forms-stats');
        
        $tableBody.html(
            '<tr><td colspan="5" class="py-8 text-center text-gray-500">' +
            '<i class="fas fa-spinner fa-spin text-3xl text-gray-300 mb-3"></i>' +
            '<p>Loading forms...</p>' +
            '</td></tr>'
        );
        
        $.ajax({
            url: w91099chConnector.ajaxurl,
            type: 'POST',
            data: {
                action: 'w91099ch_get_plugin_forms',
                nonce: w91099chConnector.nonce,
                plugin_slug: pluginSlug || ''
            },
            success: function(response) {
                if (response.success && response.data && response.data.forms) {
                    displayFormsTable(response.data.forms, pluginSlug);
                    if (pluginSlug) {
                        const pluginName = $('#form-plugin-select option:selected').text();
                        $stats.text('Showing ' + response.data.forms.length + ' forms from ' + pluginName);
                    } else {
                        $stats.text('Showing ' + response.data.forms.length + ' forms from all plugins');
                    }
                } else {
                    // Handle error response properly
                    let errorMsg = 'No forms found';
                    if (response && response.data !== undefined && response.data !== null) {
                        if (typeof response.data === 'string') {
                            errorMsg = response.data;
                        } else if (typeof response.data === 'object' && response.data.message) {
                            errorMsg = response.data.message;
                        } else if (typeof response.data === 'object') {
                            errorMsg = JSON.stringify(response.data);
                        }
                    }
                    console.error('Forms loading error:', errorMsg);
                    $tableBody.html(
                        '<tr><td colspan="5" class="py-8 text-center text-gray-500">' +
                        '<i class="fas fa-wpforms text-3xl text-gray-300 mb-3"></i>' +
                        '<p>' + escapeHtml(errorMsg) + '</p>' +
                        '</td></tr>'
                    );
                }
            },
            error: function(xhr) {
                console.error('AJAX error:', xhr.responseText);
                $tableBody.html(
                    '<tr><td colspan="5" class="py-8 text-center text-red-500">' +
                    '<i class="fas fa-exclamation-triangle text-3xl mb-3"></i>' +
                    '<p>Error loading forms</p>' +
                    '</td></tr>'
                );
            }
        });
    }
    
    function displayFormsTable(forms, pluginSlug) {
        const $tableBody = $('#forms-table-body');

        if (!forms || forms.length === 0) {
            $tableBody.html(
                '<tr><td colspan="5" class="py-8 text-center text-gray-500">' +
                '<p>No forms found</p>' +
                '</td></tr>'
            );
            return;
        }
        
        let html = '';
        
        forms.forEach(function(form) {
            const rowPluginSlug = pluginSlug ? pluginSlug : (form.plugin_slug || '');
            const formId = rowPluginSlug + '_' + form.id;
            const isExcluded = !!excludedFormIds[formId];
            const rowStyle = isExcluded ? ' style="opacity:0.55; background:#f8f9fa;"' : '';
            const statusClass = form.status === 'active' || form.status === 'publish' || form.status === 'published' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600';
            const statusText = form.status === 'active' || form.status === 'publish' || form.status === 'published' ? 'ACTIVE' : 'INACTIVE';
            
            html += '<tr' + rowStyle + '>' +
                '<td class="whitespace-nowrap text-center">' +
                '<input type="checkbox" class="form-exclude-checkbox h-4 w-4 text-blue-600 border-gray-300 rounded" data-form-id="' + escapeHtml(formId) + '"' +
                (isExcluded ? ' checked' : '') + ' />' +
                '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(form.id) + '</td>' +
                '<td class="whitespace-nowrap">' + escapeHtml(form.title) + '</td>' +
                '<td class="whitespace-nowrap text-center">' + escapeHtml(form.entries || 0) + '</td>' +
                '<td class="whitespace-nowrap">' +
                '<span class="px-3 py-1 ' + statusClass + ' text-xs font-semibold rounded-full">' + statusText + '</span>' +
                '</td>' +
                '</tr>';
        });
        
        $tableBody.html(html);
    }
    
    function updateFormPluginsList(plugins) {
        const $container = $('#detected-form-plugins-list');
        const $select = $('#form-plugin-select');

        if (!$container.length) {
            if (plugins && Object.keys(plugins).length) {
                let selectHtml = '<option value="">All Form Plugins</option>';
                Object.keys(plugins).forEach(function(slug) {
                    const plugin = plugins[slug];
                    selectHtml += '<option value="' + escapeHtml(slug) + '">' +
                                  escapeHtml(plugin.name) + ' (' + plugin.forms_count + ' forms)</option>';
                });
                $select.html(selectHtml);
            }
            return;
        }
        
        if (!plugins || Object.keys(plugins).length === 0) {
            $container.parent().html(
                '<div class="text-center py-8">' +
                '<div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">' +
                '<i class="fa-solid fa-wpforms text-2xl text-gray-400"></i>' +
                '</div>' +
                '<p class="text-gray-600 mb-2">No form plugins detected</p>' +
                '<p class="text-sm text-gray-500">Install form plugins to start</p>' +
                '</div>'
            );
            $select.html('<option value="">All Form Plugins</option>');
            return;
        }
        
        let html = '';
        let selectHtml = '<option value="">All Form Plugins</option>';
        
        Object.keys(plugins).forEach(function(slug) {
            const plugin = plugins[slug];
            html += 
                '<div class="p-2 bg-white rounded-lg border border-gray-200 hover:border-blue-300 transition-colors">' +
                '<div class="flex justify-between items-center">' +
                '<div class="flex-1">' +
                '<div class="flex items-center gap-3">' +
                '<div class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center">' +
                '<i class="fa-solid fa-wpforms text-sm text-blue-600"></i>' +
                '</div>' +
                '<div>' +
                '<div class="font-semibold text-gray-800 text-sm">' + escapeHtml(plugin.name) + '</div>' +
                '<div class="flex items-center gap-3 mt-1 text-xs text-gray-600">' +
                '<span>v' + escapeHtml(plugin.version) + '</span>' +
                '<span class="w-1 h-1 bg-gray-300 rounded-full"></span>' +
                '<span class="text-blue-700 font-semibold">' + plugin.forms_count + ' forms</span>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '<div class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">ACTIVE</div>' +
                '</div>' +
                '</div>';
            
            selectHtml += '<option value="' + escapeHtml(slug) + '">' + 
                          escapeHtml(plugin.name) + ' (' + plugin.forms_count + ' forms)</option>';
        });
        
        $container.html(html);
        $select.html(selectHtml);
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
    
    // Load excluded forms on page load
    loadExcludedForms().then(function() {
        loadForms($('#form-plugin-select').val());
    });
});
