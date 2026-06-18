jQuery(document).ready(function($) {
    var pluginSlug = 'w9-1099-chaser'; // Updated to match the actual slug if needed
    
    // Find the deactivation link for our plugin
    var $deactivateLink = $('#the-list').find('tr[data-slug="' + pluginSlug + '"] .deactivate a');
    
    if (!$deactivateLink.length) {
        // Fallback search if data-slug is different
        $deactivateLink = $('#the-list').find('a[href*="action=deactivate"][href*="plugin=w9-1099-chaser"]');
    }

    if ($deactivateLink.length) {
        $deactivateLink.on('click', function(e) {
            e.preventDefault();
            var deactivationUrl = $(this).attr('href');
            showDeactivationPopup(deactivationUrl);
        });
    }

    function showDeactivationPopup(deactivationUrl) {
        $('#w9-deactivation-modal').remove();

        var modalHtml = `
            <div id="w9-deactivation-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 999999; display: flex; align-items: center; justify-content: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <div style="background: white; border-radius: 8px; padding: 0; max-width: 600px; width: 95%; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); overflow: hidden;">
                    <div style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; gap: 12px; background: #f9fafb;">
                        <div style="width: 32px; height: 32px; background: #4b5563; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: white;">
                            <i class="dashicons dashicons-list-view" style="font-size: 20px; width: 20px; height: 20px;"></i>
                        </div>
                        <h2 style="margin: 0; font-size: 18px; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: 0.5px;">Quick Feedback</h2>
                    </div>
                    
                    <div style="padding: 32px 40px;">
                        <p style="margin: 0 0 24px; color: #4b5563; font-size: 16px; font-weight: 600;">If you have a moment, please share why you are deactivating W9-1099 Chaser:</p>
                        
                        <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 32px;">
                            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; color: #4b5563; font-size: 15px;">
                                <input type="radio" name="w9_deactivate_reason" value="no_longer_needed" style="width: 20px; height: 20px; border: 2px solid #d1d5db;">
                                I no longer need the plugin
                            </label>
                            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; color: #4b5563; font-size: 15px;">
                                <input type="radio" name="w9_deactivate_reason" value="found_better" style="width: 20px; height: 20px; border: 2px solid #d1d5db;">
                                I found a better plugin
                            </label>
                            <div id="w9-better-plugin-input" style="display: none; margin-left: 32px;">
                                <input type="text" id="w9-better-plugin-name" placeholder="Please share name with us" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 14px;">
                            </div>

                            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; color: #4b5563; font-size: 15px;">
                                <input type="radio" name="w9_deactivate_reason" value="couldnt_get_to_work" style="width: 20px; height: 20px; border: 2px solid #d1d5db;">
                                I couldn't get the plugin to work
                            </label>

                            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; color: #4b5563; font-size: 15px;">
                                <input type="radio" name="w9_deactivate_reason" value="temporary" style="width: 20px; height: 20px; border: 2px solid #d1d5db;">
                                It's a temporary deactivation
                            </label>

                            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; color: #4b5563; font-size: 15px;">
                                <input type="radio" name="w9_deactivate_reason" value="other" style="width: 20px; height: 20px; border: 2px solid #d1d5db;">
                                Other
                            </label>
                            <div id="w9-other-reason-input" style="display: none; margin-left: 32px;">
                                <input type="text" id="w9-other-reason-text" placeholder="Please share the reason" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 14px;">
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <button id="w9-submit-deactivate" style="background: #f3e8ff; color: #000; border: none; border-radius: 4px; padding: 10px 24px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                                Submit & Deactivate
                            </button>
                            <button id="w9-cancel-deactivate" style="background: #e5e7eb; color: #374151; border: none; border-radius: 4px; padding: 10px 24px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.2s;">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);

        $('input[name="w9_deactivate_reason"]').on('change', function() {
            var val = $(this).val();
            $('#w9-better-plugin-input').toggle(val === 'found_better');
            $('#w9-other-reason-input').toggle(val === 'other');
        });

        $('#w9-submit-deactivate').on('click', function() {
            var reason = $('input[name="w9_deactivate_reason"]:checked').val();
            if (!reason) {
                alert('Please select a reason.');
                return;
            }

            var details = '';
            if (reason === 'found_better') {
                details = $('#w9-better-plugin-name').val();
            } else if (reason === 'other') {
                details = $('#w9-other-reason-text').val();
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Processing...');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'w91099ch_submit_deactivation_feedback',
                    nonce: typeof w91099chAdmin !== 'undefined' ? w91099chAdmin.nonce : '',
                    reason: reason,
                    details: details
                },
                complete: function() {
                    window.location.href = deactivationUrl;
                }
            });
        });

        $('#w9-cancel-deactivate').on('click', function() {
            $('#w9-deactivation-modal').remove();
            $(document).off('keydown.w9modal');
        });

        // Close on escape key
        $(document).on('keydown.w9modal', function(e) {
            if (e.keyCode === 27) {
                $('#w9-deactivation-modal').remove();
                $(document).off('keydown.w9modal');
            }
        });
    }
});
