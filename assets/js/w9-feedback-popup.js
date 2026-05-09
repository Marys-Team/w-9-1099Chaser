/**
 * Custom Feedback Popup for W9-1099 Chaser
 */
(function($) {
    'use strict';

    function ensureFeedbackTab() {
        if ($('#w91099ch-feedback-tab-container').length) return;

        const tabHtml = `
            <div id="w91099ch-feedback-tab-container">
                <button type="button" id="w91099ch-feedback-tab" aria-label="Feedback">
                    <span>Feedback</span>
                </button>
            </div>
            <style>
                #w91099ch-feedback-tab-container {
                    position: fixed !important;
                    right: -5px !important;
                    bottom: 30px !important;
                    z-index: 999998 !important;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                }
                #w91099ch-feedback-tab {
                    background: #2563eb !important;
                    color: #fff !important;
                    border: 0 !important;
                    border-radius: 10px 0 0 10px !important;
                    padding: 15px 12px !important;
                    font-weight: 700 !important;
                    letter-spacing: .6px !important;
                    cursor: pointer !important;
                    box-shadow: -2px 0 15px rgba(0,0,0,0.1) !important;
                    writing-mode: vertical-rl !important;
                    text-orientation: mixed !important;
                    display: flex !important;
                    align-items: center !important;
                    gap: 8px !important;
                    transition: all 0.3s ease !important;
                    outline: none !important;
                }
                #w91099ch-feedback-tab span {
                    font-size: 14px !important;
                    text-transform: uppercase !important;
                }
                #w91099ch-feedback-tab-container:hover {
                    right: 0 !important;
                }
                #w91099ch-feedback-tab-container:hover #w91099ch-feedback-tab {
                    background: #1d4ed8 !important;
                    padding-right: 20px !important;
                    box-shadow: -4px 0 20px rgba(0,0,0,0.2) !important;
                }
            </style>
        `;
        $('body').append(tabHtml);

        $('#w91099ch-feedback-tab').on('click', function(e) {
            if (e && typeof e.preventDefault === 'function') e.preventDefault();
            try {
                showFeedbackPopup();
            } catch (err) {
                window.open('https://docs.google.com/forms/d/e/1FAIpQLSfpKDl5tFerKl4Ag6fqFUrGTs4NuA9IS9w7f7Zi29LWBavNgQ/viewform', '_blank', 'noopener,noreferrer');
            }
        });
    }

    function showFeedbackPopup() {
        // Remove existing modal if any
        $('#w91099ch-feedback-modal').remove();

        const modalHtml = `
            <div id="w91099ch-feedback-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 999999; display: flex; align-items: center; justify-content: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <div style="background: white; border-radius: 16px; padding: 32px; max-width: 500px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); position: relative; animation: w9FadeIn 0.3s ease-out;">
                    <button id="w9-feedback-close" style="position: absolute; top: 16px; right: 16px; background: none; border: none; font-size: 24px; cursor: pointer; color: #9ca3af;">&times;</button>
                    
                    <div style="text-align: center; margin-bottom: 24px;">
                        <h2 style="margin: 0 0 8px; font-size: 22px; font-weight: 700; color: #111827;">Your Feedback Matters!</h2>
                        <p style="margin: 0; color: #6b7280; font-size: 15px;">How would you rate your experience with our plugin?</p>
                    </div>

                    <div id="w9-star-rating" style="display: flex; justify-content: center; gap: 8px; margin-bottom: 24px;">
                        ${[1, 2, 3, 4, 5].map(i => `
                            <span class="w9-star" data-rating="${i}" style="font-size: 32px; cursor: pointer; color: #d1d5db; transition: color 0.2s;">★</span>
                        `).join('')}
                    </div>

                    <div style="margin-bottom: 24px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151; font-size: 14px;">What made using this plugin easy for you?</label>
                        <textarea id="w9-feedback-message" style="width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 12px; font-size: 14px; min-height: 100px; resize: vertical; outline: none; transition: border-color 0.2s;" placeholder="What made using this plugin easy for you?"></textarea>
                    </div>

                    <div id="w9-feedback-status" style="margin-bottom: 16px; font-size: 14px; text-align: center; display: none;"></div>

                    <button id="w9-feedback-submit" style="width: 100%; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: white; border: none; border-radius: 10px; padding: 14px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s, opacity 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        Submit Feedback
                    </button>
                </div>
                <style>
                    @keyframes w9FadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
                    .w9-star.active { color: #f59e0b !important; }
                    .w9-star:hover { color: #fbbf24 !important; }
                    #w9-feedback-submit:hover { opacity: 0.95; transform: translateY(-1px); }
                    #w9-feedback-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
                    #w9-feedback-message:focus { border-color: #6366f1; ring: 2px solid #6366f1; }
                </style>
            </div>
        `;

        $('body').append(modalHtml);

        let currentRating = 0;

        // Star click handler
        $('.w9-star').on('click', function() {
            currentRating = $(this).data('rating');
            $('.w9-star').each(function() {
                $(this).toggleClass('active', $(this).data('rating') <= currentRating);
            });
        });

        // Close handlers
        $('#w9-feedback-close').on('click', function() {
             $('#w91099ch-feedback-modal').remove();
        });
        
        $('#w91099ch-feedback-modal').on('click', function(e) {
            if (e.target === this) $('#w91099ch-feedback-modal').remove();
        });

        // Submit handler
        $('#w9-feedback-submit').on('click', function() {
            if (currentRating === 0) {
                showStatus('Please select a star rating.', 'error');
                return;
            }

            const message = $('#w9-feedback-message').val();
            const $btn = $(this);

            $btn.prop('disabled', true).text('Sending...');
            
            $.ajax({
                url: w91099chFeedbackConfig.ajaxurl,
                method: 'POST',
                data: {
                    action: 'w91099ch_submit_feedback',
                    nonce: w91099chFeedbackConfig.nonce,
                    rating: currentRating,
                    message: message
                },
                success: function(response) {
                    if (response.success) {
                        showStatus(response.data.message, 'success');
                        setTimeout(() => $('#w91099ch-feedback-modal').remove(), 2000);
                    } else {
                        showStatus(response.data.message || 'An error occurred.', 'error');
                        $btn.prop('disabled', false).text('Submit Feedback');
                    }
                },
                error: function() {
                    showStatus('Network error. Please try again.', 'error');
                    $btn.prop('disabled', false).text('Submit Feedback');
                }
            });
        });

        function showStatus(msg, type) {
            const color = type === 'success' ? '#059669' : '#dc2626';
            $('#w9-feedback-status').text(msg).css('color', color).fadeIn();
        }
    }

    // Export to window
    window.showFeedbackPopup = showFeedbackPopup;

    $(function() {
        ensureFeedbackTab();
    });

})(jQuery);
