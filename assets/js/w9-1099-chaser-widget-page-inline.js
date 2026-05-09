jQuery(document).ready(function($) {
    function w91099chWidgetHelpClose() {
        var $popover = $('#w91099ch-widget-help-popover');
        var $btn = $('#w91099ch-widget-help-btn');
        if ($popover.length) {
            $popover.addClass('hidden').attr('aria-hidden', 'true');
        }
        if ($btn.length) {
            $btn.attr('aria-expanded', 'false');
        }
    }

    function w91099chWidgetHelpToggle() {
        var $popover = $('#w91099ch-widget-help-popover');
        var $btn = $('#w91099ch-widget-help-btn');
        if (!$popover.length || !$btn.length) {
            return;
        }

        if ($popover.hasClass('hidden')) {
            $popover.removeClass('hidden').attr('aria-hidden', 'false');
            $btn.attr('aria-expanded', 'true');
        } else {
            w91099chWidgetHelpClose();
        }
    }

    function w91099chTogglePagesSelector() {
        var mode = $('input[name="w91099ch_widget_display_mode"]:checked').val();
        if (mode === 'selected') {
            $('#w9-1099-chaser-pages-selector').slideDown(300);
        } else {
            $('#w9-1099-chaser-pages-selector').slideUp(300);
        }
    }

    function w91099chChUpdateSelectedPagesCount() {
        var $select = $('.w91099ch-ch-page-checkbox');
        var $count = $('#w91099ch_pages_selected_count');
        var $label = $('#w91099ch_pages_dropdown_label');
        var $tags = $('#w91099ch_pages_selected_list');
        if (!$select.length || !$count.length) {
            return;
        }

        var selectedCount = $select.filter(':checked').length;
        $count.text(String(selectedCount));

        if ($label.length) {
            $label.text(selectedCount > 0 ? (selectedCount + ' page' + (selectedCount === 1 ? '' : 's') + ' selected') : 'Select pages');
        }

        if ($tags.length) {
            $tags.empty();
            if (selectedCount === 0) {
                $tags.append(
                    $('<span class="text-sm text-gray-600"></span>').text('No pages selected.')
                );
            } else {
                $select.filter(':checked').each(function() {
                    var $cb = $(this);
                    var title = String($cb.closest('label').find('span').first().text() || '').trim();
                    var $tag = $('<span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white border text-sm text-gray-800"></span>')
                        .css('border-color', 'var(--mp-gray-200)');
                    var $remove = $('<button type="button" class="text-gray-500 hover:text-gray-800"></button>')
                        .attr('aria-label', 'Remove')
                        .html('<i class="fas fa-times"></i>')
                        .on('click', function(e) {
                            e.preventDefault();
                            $cb.prop('checked', false).trigger('change');
                        });

                    $tag.append($('<span></span>').text(title));
                    $tag.append($remove);
                    $tags.append($tag);
                });
            }
        }
    }

    function w91099chChFilterPagesList(query) {
        var $items = $('.w91099ch-ch-page-checkbox');
        if (!$items.length) {
            return;
        }

        var q = String(query || '').toLowerCase().trim();
        $items.each(function() {
            var $cb = $(this);
            var title = String($cb.attr('data-title') || '').toLowerCase();
            var match = (q === '') || (title.indexOf(q) !== -1);
            var $row = $cb.closest('label');
            if (match) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    }

    w91099chTogglePagesSelector();
    w91099chChUpdateSelectedPagesCount();

    $('input[name="w91099ch_widget_display_mode"]').on('change', function() {
        w91099chTogglePagesSelector();
    });

    $(document).on('change', '.w91099ch-ch-page-checkbox', function() {
        w91099chChUpdateSelectedPagesCount();
    });

    $('#w91099ch_pages_search').on('input', function() {
        w91099chChFilterPagesList($(this).val());
    });

    $('#w91099ch_pages_select_all').on('click', function(e) {
        e.preventDefault();
        $('.w91099ch-ch-page-checkbox').each(function() {
            var $cb = $(this);
            var $row = $cb.closest('label');
            if ($row.is(':visible')) {
                $cb.prop('checked', true);
            }
        });
        w91099chChUpdateSelectedPagesCount();
    });

    $('#w91099ch_pages_clear').on('click', function(e) {
        e.preventDefault();
        $('.w91099ch-ch-page-checkbox').prop('checked', false);
        w91099chChUpdateSelectedPagesCount();
    });

    function w91099chChClosePagesDropdown() {
        var $panel = $('#w91099ch_pages_dropdown_panel');
        var $btn = $('#w91099ch_pages_dropdown_btn');
        if ($panel.length) {
            $panel.addClass('hidden');
        }
        if ($btn.length) {
            $btn.attr('aria-expanded', 'false');
        }
    }

    function w91099chChTogglePagesDropdown() {
        var $panel = $('#w91099ch_pages_dropdown_panel');
        var $btn = $('#w91099ch_pages_dropdown_btn');
        if (!$panel.length || !$btn.length) {
            return;
        }

        if ($panel.hasClass('hidden')) {
            $panel.removeClass('hidden');
            $btn.attr('aria-expanded', 'true');
            setTimeout(function() {
                $('#w91099ch_pages_search').trigger('focus');
            }, 0);
        } else {
            w91099chChClosePagesDropdown();
        }
    }

    $('#w91099ch_pages_dropdown_btn').on('click', function(e) {
        e.preventDefault();
        w91099chChTogglePagesDropdown();
    });

    $(document).on('click', function(e) {
        var $target = $(e.target);
        if ($target.closest('#w91099ch_pages_dropdown').length) {
            return;
        }
        w91099chChClosePagesDropdown();
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            w91099chChClosePagesDropdown();
        }
    });

    $('#w9-1099-chaser-generate-widget-code').on('click', function(e) {
        e.preventDefault();

        if (!window.confirm('This will contact the external MyPowerly service (https://mypowerly.com) and fetch your widget data. Do you want to continue?')) {
            return;
        }

        var $btn = $(this);
        var $btnContent = $btn.find('span');
        var $btnIcon = $btn.find('i');
        var originalText = $btnContent.text();
        
        // Show loading state
        $btn.prop('disabled', true).addClass('opacity-75 cursor-not-allowed');
        $btnIcon.removeClass('fa-bolt').addClass('fa-spinner fa-spin');
        $btnContent.text('Generating...');

        var ajaxUrl = (window.w91099chChaserWidgetPage && window.w91099chChaserWidgetPage.ajaxurl) ? window.w91099chChaserWidgetPage.ajaxurl : window.ajaxurl;
        var action = (window.w91099chChaserWidgetPage && window.w91099chChaserWidgetPage.action) ? window.w91099chChaserWidgetPage.action : null;
        var nonceName = (window.w91099chChaserWidgetPage && window.w91099chChaserWidgetPage.nonceName) ? window.w91099chChaserWidgetPage.nonceName : null;
        var nonceValue = (window.w91099chChaserWidgetPage && window.w91099chChaserWidgetPage.nonceValue) ? window.w91099chChaserWidgetPage.nonceValue : null;

        if (!ajaxUrl || !action || !nonceName || !nonceValue) {
            showNotification('Widget generator is not configured. Please reload the page and try again.', 'error');
            $btn.prop('disabled', false).removeClass('opacity-75 cursor-not-allowed');
            $btnIcon.removeClass('fa-spinner fa-spin').addClass('fa-bolt');
            $btnContent.text(originalText);
            return;
        }

        var payload = { action: action };
        payload[nonceName] = nonceValue;

        var $debug = $('#w91099ch_widget_api_debug');
        if ($debug.length) {
            $debug.text('Loading...');
        }

        $.post(ajaxUrl, payload).done(function(resp) {
            if ($debug.length) {
                try {
                    $debug.text(JSON.stringify(resp, null, 2));
                } catch (e) {
                    $debug.text(String(resp));
                }
            }
            if (resp && resp.success && resp.data && resp.data.code) {
                $('#w91099ch_widget_code').val(resp.data.code).addClass('border-green-500 bg-green-50');
                
                // Update the script display box with the actual embed script
                var scriptDisplay = $('#w91099ch_widget_script_display');
                if (scriptDisplay.length) {
                    scriptDisplay.val(resp.data.code);
                }

                showNotification('Widget code generated successfully. Click "Save Settings" to apply it.', 'success');
                
                // Show success feedback
                setTimeout(function() {
                    $('#w91099ch_widget_code').removeClass('border-green-500 bg-green-50');
                }, 2000);
                
                // Scroll to code area
                $('#w91099ch_widget_code')[0].scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            } else {
                // Show error notification
                var msg = (resp && resp.data && resp.data.message) ? String(resp.data.message) : 'Error generating widget code. Please try again.';
                showNotification(msg, 'error');
            }
        }).fail(function() {
            if ($debug.length) {
                $debug.text('Request failed (network/server error).');
            }
            showNotification('Network error. Please check your connection and try again.', 'error');
        }).always(function() {
            // Restore button state
            $btn.prop('disabled', false).removeClass('opacity-75 cursor-not-allowed');
            $btnIcon.removeClass('fa-spinner fa-spin').addClass('fa-bolt');
            $btnContent.text(originalText);
        });
    });

    // Enhanced form submission feedback
    $('form').on('submit', function() {
        var $submitBtn = $(this).find('button[type="submit"]');
        var $btnIcon = $submitBtn.find('i');
        var $btnContent = $submitBtn.find('span');
        var originalText = $btnContent.text();
        
        // Show saving state
        $submitBtn.prop('disabled', true).addClass('opacity-75 cursor-not-allowed');
        $btnIcon.removeClass('fa-save').addClass('fa-spinner fa-spin');
        $btnContent.text('Saving...');
    });

    // Notification helper function
    function showNotification(message, type) {
        var notification = $('<div class="fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg border-l-4 max-w-sm transform transition-all duration-300 translate-x-full">')
            .addClass(type === 'error' ? 'bg-red-50 border-red-500 text-red-800' : 'bg-green-50 border-green-500 text-green-800');

        var row = $('<div class="flex items-start gap-3"></div>');
        var iconClass = type === 'error' ? 'fa-exclamation-triangle' : 'fa-check-circle';
        var icon = $('<i class="fas mt-0.5"></i>').addClass(iconClass);
        var msg = $('<div></div>').text(String(message || ''));
        row.append(icon).append(msg);
        notification.append(row);
        
        $('body').append(notification);
        
        // Slide in
        setTimeout(function() {
            notification.removeClass('translate-x-full');
        }, 100);
        
        // Auto remove after 5 seconds
        setTimeout(function() {
            notification.addClass('translate-x-full');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 5000);
    }

    // Add hover effects to radio labels
    $('.mp-radio').each(function() {
        var $radio = $(this);
        var $label = $radio.closest('label');
        
        $radio.on('change', function() {
            // Remove active state from all labels in the same group
            $label.siblings('label').removeClass('ring-2 ring-blue-500 bg-blue-50');
            // Add active state to current label
            $label.addClass('ring-2 ring-blue-500 bg-blue-50');
        });
        
        // Set initial active state
        if ($radio.is(':checked')) {
            $label.addClass('ring-2 ring-blue-500 bg-blue-50');
        }
    });

    // Enhanced textarea focus effect
    $('#w91099ch_widget_code').on('focus', function() {
        $(this).parent().find('label').addClass('text-blue-700 font-semibold');
    }).on('blur', function() {
        $(this).parent().find('label').removeClass('text-blue-700 font-semibold');
    });

    // Copy script functionality
    $('#w91099ch_copy_script').on('click', function(e) {
        e.preventDefault();
        var scriptInput = $('#w91099ch_widget_script_display');
        var scriptText = scriptInput.val();
        
        if (!scriptText) {
            showNotification('No script to copy. Please generate widget code first.', 'error');
            return;
        }
        
        // Try to copy to clipboard
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(scriptText).then(function() {
                showNotification('Script copied to clipboard!', 'success');
                // Visual feedback
                scriptInput.addClass('border-green-500 bg-green-50');
                setTimeout(function() {
                    scriptInput.removeClass('border-green-500 bg-green-50');
                }, 1000);
            }).catch(function() {
                fallbackCopyScript(scriptInput, scriptText);
            });
        } else {
            fallbackCopyScript(scriptInput, scriptText);
        }
    });
    
    // Fallback copy method
    function fallbackCopyScript(scriptInput, scriptText) {
        try {
            scriptInput.select();
            scriptInput[0].setSelectionRange(0, 99999); // For mobile devices
            document.execCommand('copy');
            showNotification('Script copied to clipboard!', 'success');
            // Visual feedback
            scriptInput.addClass('border-green-500 bg-green-50');
            setTimeout(function() {
                scriptInput.removeClass('border-green-500 bg-green-50');
            }, 1000);
        } catch (err) {
            showNotification('Could not copy script. Please select and copy manually.', 'error');
            scriptInput.select();
        }
    }

    // Initialize script display with existing code
    var existingCode = $('#w91099ch_widget_code').val();
    if (existingCode) {
        $('#w91099ch_widget_script_display').val(existingCode);
    }

    $('#w91099ch-widget-help-btn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        w91099chWidgetHelpToggle();
    });

    $(document).on('click', function(e) {
        var $t = $(e.target);
        if ($t.closest('#w91099ch-widget-help-area').length) {
            return;
        }
        w91099chWidgetHelpClose();
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            w91099chWidgetHelpClose();
        }
    });
});
