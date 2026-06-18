jQuery(document).ready(function($) {
                            $(document).on('click', '.mp-roles-toggle', function() {
                                const $btn = $(this);
                                const role = String($btn.data('role') || '');
                                if (!role) return;
                                const $cb = $('#role-' + role);
                                if (!$cb.length) return;

                                const next = !$cb.prop('checked');
                                $cb.prop('checked', next).trigger('change');
                                $btn.toggleClass('is-on', next);
                                $btn.text(next ? 'Included' : 'Excluded');
                            });
                        });

jQuery(document).ready(function($) {
    const w91099chDebugEnabled = !!(
        window.w91099chDebug ||
        (window.w91099chConnector && window.w91099chConnector.debug)
    );

    window.w91099chConsole = (window.w91099chConsole && typeof window.w91099chConsole.log === 'function')
        ? window.w91099chConsole
        : (w91099chDebugEnabled && typeof window.console !== 'undefined'
            ? window.console
            : { log: function() {}, error: function() {}, warn: function() {} });

    window.w91099chConnectorConsole = window.w91099chConsole;

    function persistAdminConsentIfNeeded(done) {
        if (!window.w91099chConnector || !window.w91099chConnector.ajaxurl) {
            if (typeof done === 'function') done(false);
            return;
        }
        if (window.w91099chConnector.has_admin_consent) {
            if (typeof done === 'function') done(true);
            return;
        }
        $.ajax({
            url: window.w91099chConnector.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'w91099ch_set_admin_consent',
                nonce: window.w91099chConnector.consent_nonce
            },
            success: function(resp) {
                if (resp && resp.success) {
                    window.w91099chConnector.has_admin_consent = true;
                    if (typeof done === 'function') done(true);
                    return;
                }
                if (typeof done === 'function') done(false);
            },
            error: function() {
                if (typeof done === 'function') done(false);
            }
        });
    }

    function updateAffiliatesSyncButtonCount(pluginSlug, count) {
        const $btn = $('#affiliates-sync');
        if (!$btn.length) {
            return;
        }

        const baseLabel = $btn.data('base-label')
            ? String($btn.data('base-label'))
            : 'Sync All Affiliates/Vendors Data Now';

        if (!$btn.data('base-label')) {
            $btn.data('base-label', baseLabel);
        }

        const selectedSlug = String(pluginSlug || '').trim();
        const hasPlugin = !!selectedSlug;
        const safeCount = parseInt(String(count), 10);
        const showCount = isFinite(safeCount) ? safeCount : 0;

        const noun = (showCount === 1) ? 'Affiliate' : 'Affiliates';

        const label = hasPlugin
            ? ('Sync ' + showCount + ' ' + noun + ' Data Now')
            : baseLabel;

        $btn.html('<i class="fas fa-sync-alt mp-animate-pulse"></i> ' + label);
    }

    function updateCardsDisabledState(isConnected) {
        const $cardsGrid = $('.mp-cards-grid').not('.w91099ch-sync-modules-grid');
        if (!$cardsGrid.length) {
            return;
        }

        if (isConnected) {
            $cardsGrid.removeClass('mp-cards-disabled');
        } else {
            $cardsGrid.addClass('mp-cards-disabled');
        }
    }

    /**
     * Check if user is connected to Mypowerly
     * Shows a styled alert if not connected
     * @returns {boolean} true if connected, false otherwise
     */
    function checkMypowerlyConnection() {
        const isConnected = !!(window.w91099chConnector && window.w91099chConnector.is_connected);
        
        if (!isConnected) {
            // Check if we're on the Advanced Features page
            const isAdvancedFeaturesPage = window.location.href.indexOf('page=w91099ch-advanced-features') !== -1;
            
            // Create a styled modal alert with clear instructions
            const alertHtml = ''
                + '<div id="mp-connection-alert-overlay" style="'
                +   'position:fixed;top:0;left:0;right:0;bottom:0;'
                +   'background:rgba(0,0,0,0.7);backdrop-filter:blur(6px);'
                +   'z-index:99999;display:flex;align-items:center;justify-content:center;'
                +   'animation:fadeIn 0.2s ease;">'
                + '<div style="'
                +   'background:#fff;border-radius:20px;padding:0;max-width:560px;width:90%;'
                +   'box-shadow:0 25px 50px rgba(0,0,0,0.3);overflow:hidden;'
                +   'animation:slideUp 0.3s ease;">'
                
                // Header with gradient
                + '<div style="'
                +   'background:linear-gradient(135deg,#dc2626,#ef4444);'
                +   'padding:28px 32px;text-align:center;position:relative;">'
                + '<div style="'
                +   'width:72px;height:72px;margin:0 auto 16px;'
                +   'background:rgba(255,255,255,0.2);backdrop-filter:blur(10px);'
                +   'border-radius:50%;display:flex;align-items:center;justify-content:center;'
                +   'border:3px solid rgba(255,255,255,0.3);">'
                + '<i class="fas fa-plug-circle-xmark" style="font-size:36px;color:#fff;"></i>'
                + '</div>'
                + '<h3 style="margin:0;font-size:26px;font-weight:800;color:#fff;letter-spacing:-0.02em;">Connection Required</h3>'
                + '</div>'
                
                // Body with instructions
                + '<div style="padding:32px;">'
                + '<div style="'
                +   'background:linear-gradient(135deg,#fef3c7,#fde68a);'
                +   'border-left:4px solid #f59e0b;padding:20px;border-radius:12px;margin-bottom:24px;">'
                + '<p style="margin:0 0 12px;font-size:16px;font-weight:700;color:#92400e;">'
                + '<i class="fas fa-exclamation-triangle" style="margin-right:8px;"></i>Feature Not Available'
                + '</p>'
                + '<p style="margin:0;font-size:14px;color:#78350f;line-height:1.6;">'
                + 'This feature requires an active connection to <strong>Mypowerly</strong>. '
                + 'Please connect your site to unlock all sync and data management features.'
                + '</p>'
                + '</div>'
                
                // Instructions box - different for Advanced Features page
                + '<div style="'
                +   'background:#f8fafc;border:2px solid #e2e8f0;'
                +   'padding:20px;border-radius:12px;margin-bottom:24px;">'
                + '<p style="margin:0 0 16px;font-size:15px;font-weight:700;color:#1e293b;">'
                + '<i class="fas fa-list-check" style="margin-right:8px;color:#4f46e5;"></i>How to Connect:'
                + '</p>'
                + (isAdvancedFeaturesPage 
                    ? '<ol style="margin:0;padding-left:20px;font-size:14px;color:#475569;line-height:1.8;">'
                    + '<li style="margin-bottom:8px;">Click <strong>"Go to Dashboard and Connect"</strong> button below</li>'
                    + '<li style="margin-bottom:8px;">You will be redirected to the main Dashboard</li>'
                    + '<li style="margin-bottom:8px;">Check the consent checkbox to agree to sync your data</li>'
                    + '<li style="margin-bottom:0;">Click <strong>"Connect to Mypowerly"</strong> to establish connection</li>'
                    + '</ol>'
                    : '<ol style="margin:0;padding-left:20px;font-size:14px;color:#475569;line-height:1.8;">'
                    + '<li style="margin-bottom:8px;">Scroll down to the <strong>"Connect to Mypowerly"</strong> section</li>'
                    + '<li style="margin-bottom:8px;">Check the consent checkbox to agree to sync your data</li>'
                    + '<li style="margin-bottom:8px;">Click the <strong>"Connect to Mypowerly"</strong> button</li>'
                    + '<li style="margin-bottom:0;">Once connected, all features will be unlocked</li>'
                    + '</ol>')
                + '</div>'
                
                // Benefits box
                + '<div style="'
                +   'background:linear-gradient(135deg,#eff6ff,#dbeafe);'
                +   'border:1px solid #bfdbfe;padding:16px;border-radius:10px;margin-bottom:24px;">'
                + '<p style="margin:0 0 10px;font-size:13px;font-weight:700;color:#1e40af;">'
                + '<i class="fas fa-sparkles" style="margin-right:6px;"></i>What You\'ll Get:'
                + '</p>'
                + '<ul style="margin:0;padding-left:20px;font-size:13px;color:#1e40af;line-height:1.6;">'
                + '<li>Sync profile, plugins, affiliates, and team data</li>'
                + '<li>Manage W-9/1099 forms and vendor information</li>'
                + '<li>Access advanced reporting and analytics</li>'
                + '</ul>'
                + '</div>'
                
                // Action buttons - different for Advanced Features page
                + '<div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">'
                + '<button id="mp-alert-close" style="'
                +   'padding:14px 28px;background:#fff;color:#64748b;'
                +   'border:2px solid #e2e8f0;border-radius:12px;'
                +   'font-size:15px;font-weight:600;cursor:pointer;'
                +   'transition:all 0.2s ease;"'
                +   'onmouseover="this.style.background=\'#f8fafc\';this.style.borderColor=\'#cbd5e1\';"'
                +   'onmouseout="this.style.background=\'#fff\';this.style.borderColor=\'#e2e8f0\';">'
                + '<i class="fas fa-times" style="margin-right:8px;"></i>Cancel</button>'
                + '<button id="mp-alert-connect" style="'
                +   'padding:14px 32px;background:linear-gradient(135deg,#4f46e5,#7c3aed);'
                +   'color:#fff;border:none;border-radius:12px;'
                +   'font-size:15px;font-weight:700;cursor:pointer;'
                +   'box-shadow:0 4px 14px rgba(79,70,229,0.4);'
                +   'transition:all 0.2s ease;"'
                +   'onmouseover="this.style.transform=\'translateY(-2px)\';this.style.boxShadow=\'0 6px 20px rgba(79,70,229,0.5)\';"'
                +   'onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 4px 14px rgba(79,70,229,0.4)\';">'
                + '<i class="fas fa-' + (isAdvancedFeaturesPage ? 'arrow-right' : 'plug') + '" style="margin-right:8px;"></i>'
                + (isAdvancedFeaturesPage ? 'Go to Dashboard and Connect' : 'Go to Connection Section')
                + '</button>'
                + '</div>'
                + '</div>'
                + '</div>'
                + '</div>';
            
            // Add CSS animations
            const styleTag = '<style>'
                + '@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }'
                + '@keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }'
                + '</style>';
            
            $('body').append(styleTag + alertHtml);
            
            // Close button handler
            $('#mp-alert-close').on('click', function() {
                $('#mp-connection-alert-overlay').fadeOut(200, function() {
                    $(this).remove();
                });
            });
            
            // Connect button handler
            $('#mp-alert-connect').on('click', function() {
                $('#mp-connection-alert-overlay').fadeOut(200, function() {
                    $(this).remove();
                });
                
                if (isAdvancedFeaturesPage) {
                    // Redirect to Dashboard page with scroll parameter
                    const dashboardUrl = window.location.href.replace('page=w91099ch-advanced-features', 'page=w91099ch') + '&scroll_to_connect=1';
                    window.location.href = dashboardUrl;
                } else {
                    // Scroll to connect block if it exists on current page
                    const $connectBlock = $('#mypowerly-connect-block');
                    if ($connectBlock.length) {
                        $('html, body').animate({
                            scrollTop: $connectBlock.offset().top - 80
                        }, 600, 'swing');
                        
                        // Highlight the connect block with pulsing effect
                        $connectBlock.css({
                            'box-shadow': '0 0 0 4px rgba(79, 70, 229, 0.4)',
                            'transition': 'box-shadow 0.3s ease',
                            'border-left-color': '#4f46e5'
                        });
                        
                        // Pulse effect
                        let pulseCount = 0;
                        const pulseInterval = setInterval(function() {
                            pulseCount++;
                            if (pulseCount % 2 === 0) {
                                $connectBlock.css('box-shadow', '0 0 0 4px rgba(79, 70, 229, 0.4)');
                            } else {
                                $connectBlock.css('box-shadow', '0 0 0 8px rgba(79, 70, 229, 0.2)');
                            }
                            
                            if (pulseCount >= 6) {
                                clearInterval(pulseInterval);
                                setTimeout(function() {
                                    $connectBlock.css({
                                        'box-shadow': '',
                                        'border-left-color': ''
                                    });
                                }, 500);
                            }
                        }, 300);
                    } else {
                        // If connect block not found, scroll to top
                        $('html, body').animate({ scrollTop: 0 }, 600);
                        alert('Please scroll down to find the "Connect to Mypowerly" section.');
                    }
                }
            });
            
            // Close on overlay click
            $('#mp-connection-alert-overlay').on('click', function(e) {
                if (e.target.id === 'mp-connection-alert-overlay') {
                    $(this).fadeOut(200, function() {
                        $(this).remove();
                    });
                }
            });
            
            // Close on ESC key
            $(document).on('keydown.mpAlert', function(e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    $('#mp-connection-alert-overlay').fadeOut(200, function() {
                        $(this).remove();
                    });
                    $(document).off('keydown.mpAlert');
                }
            });
            
            return false;
        }
        
        return true;
    }

    window.persistAdminConsentIfNeeded = persistAdminConsentIfNeeded;
    window.updateAffiliatesSyncButtonCount = updateAffiliatesSyncButtonCount;
    window.updateCardsDisabledState = updateCardsDisabledState;

    // ── Handle scroll to connect section from Advanced Features page ──
    (function handleScrollToConnect() {
        // Check if URL has scroll_to_connect parameter
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('scroll_to_connect') === '1') {
            // Wait for page to fully load
            $(window).on('load', function() {
                setTimeout(function() {
                    const $connectBlock = $('#mypowerly-connect-block');
                    if ($connectBlock.length) {
                        // Scroll to connect block
                        $('html, body').animate({
                            scrollTop: $connectBlock.offset().top - 80
                        }, 800, 'swing');
                        
                        // Highlight with pulsing effect
                        $connectBlock.css({
                            'box-shadow': '0 0 0 4px rgba(79, 70, 229, 0.4)',
                            'transition': 'box-shadow 0.3s ease',
                            'border-left-color': '#4f46e5',
                            'border-left-width': '6px'
                        });
                        
                        // Enhanced pulse effect
                        let pulseCount = 0;
                        const pulseInterval = setInterval(function() {
                            pulseCount++;
                            if (pulseCount % 2 === 0) {
                                $connectBlock.css('box-shadow', '0 0 0 4px rgba(79, 70, 229, 0.4)');
                            } else {
                                $connectBlock.css('box-shadow', '0 0 0 10px rgba(79, 70, 229, 0.15)');
                            }
                            
                            if (pulseCount >= 8) {
                                clearInterval(pulseInterval);
                                setTimeout(function() {
                                    $connectBlock.css({
                                        'box-shadow': '',
                                        'border-left-color': '',
                                        'border-left-width': ''
                                    });
                                }, 500);
                            }
                        }, 350);
                        
                        // Remove the parameter from URL without reloading
                        if (window.history && window.history.replaceState) {
                            const newUrl = window.location.href.replace(/[&?]scroll_to_connect=1/, '');
                            window.history.replaceState({}, document.title, newUrl);
                        }
                    }
                }, 500);
            });
        }
    })();

    // ── Auto-sync on connect ──────────────────────────────────────────────────
    // If the user had "auto sync after connection" checked and we just landed
    // back here after a successful connection, fire sync-all automatically.
    (function maybeAutoSyncAfterConnect() {
        if (!window.w91099chConnector || !window.w91099chConnector.pending_auto_sync) return;
        if (!window.w91099chConnector.is_connected) return;
        if (!window.w91099chConnector.ajaxurl || !window.w91099chConnector.nonce) return;

        // ── Build the overlay modal HTML ──────────────────────────────────────
        var overlayHtml =
            '<div id="mp-auto-sync-overlay" style="'
            +   'position:fixed;top:0;left:0;width:100%;height:100%;'
            +   'background:rgba(15,23,42,0.55);z-index:99999;'
            +   'display:flex;align-items:center;justify-content:center;'
            +   'backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px);">'
            + '<div id="mp-auto-sync-modal" style="'
            +   'background:#fff;border-radius:16px;padding:0;width:100%;max-width:560px;'
            +   'margin:20px;box-shadow:0 25px 60px rgba(0,0,0,0.25);overflow:hidden;">'

            // ── Header ──
            + '<div style="'
            +   'background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%);'
            +   'padding:24px 28px;display:flex;align-items:center;gap:14px;">'
            + '<div style="'
            +   'width:44px;height:44px;border-radius:10px;'
            +   'background:rgba(255,255,255,0.2);'
            +   'display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
            + '<i class="fas fa-rotate" id="mp-auto-sync-icon" style="color:#fff;font-size:20px;"></i>'
            + '</div>'
            + '<div>'
            + '<h3 style="margin:0;color:#fff;font-size:18px;font-weight:700;line-height:1.2;">Syncing All Data to Mypowerly</h3>'
            + '<p style="margin:4px 0 0;color:rgba(255,255,255,0.8);font-size:13px;">Connected successfully — sending your data now&hellip;</p>'
            + '</div>'
            + '</div>'

            // ── Body: progress state ──
            + '<div id="mp-auto-sync-body-progress" style="padding:28px;">'

            // Badge row
            + '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:20px;">'
            + ['Plugins','Affiliates/Vendors','Team/Users','Forms','Memberships','Contractors','Freelancers','Accounting','Wallet/Payout','Ecommerce'].map(function(label) {
                return '<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;'
                    + 'border-radius:20px;background:#f1f5f9;font-size:11px;font-weight:600;color:#475569;">'
                    + '<span style="width:6px;height:6px;border-radius:50%;background:#94a3b8;display:inline-block;"></span>'
                    + label + '</span>';
            }).join('')
            + '</div>'

            // Progress bar
            + '<div style="margin-bottom:10px;">'
            + '<div style="display:flex;justify-content:space-between;margin-bottom:6px;">'
            + '<span id="mp-auto-sync-step" style="font-size:13px;color:#64748b;">Preparing&hellip;</span>'
            + '<span id="mp-auto-sync-pct" style="font-size:13px;font-weight:700;color:#4f46e5;">0%</span>'
            + '</div>'
            + '<div style="height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">'
            + '<div id="mp-auto-sync-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#4f46e5,#7c3aed);border-radius:4px;transition:width 0.4s ease;"></div>'
            + '</div>'
            + '</div>'

            + '<p style="margin:14px 0 0;font-size:12px;color:#94a3b8;text-align:center;">Please wait — this usually takes a few seconds.</p>'
            + '</div>'

            // ── Body: success state (hidden initially) ──
            + '<div id="mp-auto-sync-body-success" style="display:none;padding:28px;">'
            + '<div style="'
            +   'padding:20px 24px;background:linear-gradient(135deg,#f0fdf4,#ecfdf5);'
            +   'border:1px solid #bbf7d0;border-radius:12px;'
            +   'display:flex;justify-content:space-between;align-items:center;gap:16px;">'
            + '<div style="display:flex;align-items:center;gap:14px;">'
            + '<div style="width:44px;height:44px;border-radius:10px;background:#dcfce7;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
            + '<i class="fas fa-circle-check" style="color:#16a34a;font-size:22px;"></i>'
            + '</div>'
            + '<div>'
            + '<h4 style="margin:0 0 4px;font-size:17px;font-weight:700;color:#1e293b;">Sync Complete!</h4>'
            + '<p style="margin:0;font-size:13px;color:#475569;">All data synchronized successfully with Mypowerly. No further action is required.</p>'
            + '</div>'
            + '</div>'
            + '<div style="text-align:right;flex-shrink:0;">'
            + '<div style="font-size:11px;color:#64748b;margin-bottom:2px;">Total Time</div>'
            + '<div id="mp-auto-sync-duration" style="font-size:22px;font-weight:700;color:#1e293b;">0s</div>'
            + '</div>'
            + '</div>'
            + '<div style="margin-top:20px;text-align:center;display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">'
            + '<a href="https://mypowerly.com" target="_blank" rel="noopener noreferrer" style="'
            +   'padding:10px 24px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;'
            +   'border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;'
            +   'display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 12px rgba(16,185,129,0.25);'
            +   'transition:all 0.2s ease;"'
            +   'onmouseover="this.style.transform=\'translateY(-2px)\';this.style.boxShadow=\'0 6px 16px rgba(16,185,129,0.35)\';"'
            +   'onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 4px 12px rgba(16,185,129,0.25)\';">'
            + '<i class="fas fa-external-link-alt"></i>'
            + 'Go to Mypowerly & See Your Data</a>'
            + '<button id="mp-auto-sync-close-btn" style="'
            +   'padding:10px 28px;background:#4f46e5;color:#fff;border:none;'
            +   'border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;'
            +   'transition:all 0.2s ease;"'
            +   'onmouseover="this.style.background=\'#4338ca\';"'
            +   'onmouseout="this.style.background=\'#4f46e5\';">'
            + 'Continue</button>'
            + '</div>'
            + '</div>'

            // ── Body: error state (hidden initially) ──
            + '<div id="mp-auto-sync-body-error" style="display:none;padding:28px;">'
            + '<div style="'
            +   'padding:20px 24px;background:linear-gradient(135deg,#fff1f2,#fef2f2);'
            +   'border:1px solid #fecaca;border-radius:12px;'
            +   'display:flex;align-items:flex-start;gap:14px;">'
            + '<div style="width:44px;height:44px;border-radius:10px;background:#fee2e2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
            + '<i class="fas fa-circle-xmark" style="color:#dc2626;font-size:22px;"></i>'
            + '</div>'
            + '<div>'
            + '<h4 style="margin:0 0 6px;font-size:17px;font-weight:700;color:#1e293b;">Sync Failed</h4>'
            + '<p id="mp-auto-sync-error-msg" style="margin:0;font-size:13px;color:#475569;"></p>'
            + '<button onclick="window.location.reload()" style="'
            +   'margin-top:14px;padding:8px 20px;background:#fff;color:#dc2626;'
            +   'border:1px solid #fca5a5;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">'
            + '<i class="fas fa-rotate-right" style="margin-right:6px;"></i>Try Again</button>'
            + '</div>'
            + '</div>'
            + '</div>'

            + '</div>'  // modal
            + '</div>'; // overlay

        $('body').append(overlayHtml);

        // Animate the spinner icon
        var $icon = $('#mp-auto-sync-icon');
        var spinAngle = 0;
        var spinTimer = setInterval(function() {
            spinAngle = (spinAngle + 15) % 360;
            $icon.css('transform', 'rotate(' + spinAngle + 'deg)');
        }, 40);

        function setProgress(pct, stepText) {
            $('#mp-auto-sync-bar').css('width', pct + '%');
            $('#mp-auto-sync-pct').text(Math.round(pct) + '%');
            if (stepText) { $('#mp-auto-sync-step').text(stepText); }
        }

        var startTime = Date.now();
        setProgress(10, 'Preparing consolidated payload\u2026');

        // Animate progress to 80% while waiting for the AJAX response
        var fakeProgress = 10;
        var fakeTimer = setInterval(function() {
            if (fakeProgress < 78) {
                fakeProgress += 2;
                setProgress(fakeProgress, 'Sending data to Mypowerly\u2026');
            }
        }, 300);

        $.ajax({
            url: window.w91099chConnector.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'w91099ch_sync_all_webhook',
                nonce: window.w91099chConnector.nonce
            },
            success: function(response) {
                clearInterval(fakeTimer);
                clearInterval(spinTimer);

                if (response && response.success) {
                    setProgress(100, 'All data synced!');
                    var duration = ((Date.now() - startTime) / 1000).toFixed(1) + 's';
                    $('#mp-auto-sync-duration').text(duration);

                    // Update the existing sync-all UI on the page if it's present
                    var now = new Date();
                    var timeStr = now.toLocaleString();
                    $('#last-plugin-sync-time').text(timeStr);
                    $('#last-affiliates-sync-time').text(timeStr);
                    $('#last-team-sync-time').text(timeStr);
                    $('#plugin-sync-status, #affiliates-sync-status, #team-sync-status').text('Synced via Auto-Sync');
                    $('#form-sync-status, #contractor-sync-status, #freelancer-contractor-sync-status').text('Synced via Auto-Sync');
                    $('#accounting-bookkeeping-sync-status, #wallet-payout-sync-status, #ecommerce-sync-status').text('Synced via Auto-Sync');

                    // Show success state after a brief pause
                    setTimeout(function() {
                        $('#mp-auto-sync-body-progress').hide();
                        $('#mp-auto-sync-body-success').show();
                        // Update header
                        $('#mp-auto-sync-icon').css('transform', '').removeClass().addClass('fas fa-circle-check').css('color', '#fff');
                    }, 400);
                } else {
                    var msg = (response && response.data)
                        ? (typeof response.data === 'string' ? response.data : (response.data.message || JSON.stringify(response.data)))
                        : 'Sync failed. Please retry.';
                    $('#mp-auto-sync-error-msg').text(msg);
                    $('#mp-auto-sync-body-progress').hide();
                    $('#mp-auto-sync-body-error').show();
                }
            },
            error: function(xhr) {
                clearInterval(fakeTimer);
                clearInterval(spinTimer);
                var msg = 'Connection error. Please try again.';
                if (xhr && xhr.responseText) {
                    try { msg = JSON.parse(xhr.responseText).data || msg; } catch(e) {}
                }
                $('#mp-auto-sync-error-msg').text(msg);
                $('#mp-auto-sync-body-progress').hide();
                $('#mp-auto-sync-body-error').show();
            }
        });

        // Close button
        $(document).on('click', '#mp-auto-sync-close-btn', function() {
            $('#mp-auto-sync-overlay').fadeOut(200, function() { $(this).remove(); });
        });
    }());
    // Connect button (when not connected to MyPowerly)
    $('#mypowerly-w9-connect').on('click', function(e) {
        e.preventDefault();
        var $connectBtn = $('#connect-mypowerly-cta');
        if ($connectBtn.length) {
            try {
                $('html, body').animate({ scrollTop: $connectBtn.offset().top - 120 }, 600);
                setTimeout(function() {
                    $connectBtn.addClass('mp-connect-highlight').trigger('focus');
                    setTimeout(function() {
                        $connectBtn.removeClass('mp-connect-highlight');
                    }, 2400);
                }, 650);
            } catch (err) {
                window.location.href = (window.w91099chConnector && window.w91099chConnector.admin_page_url) ? window.w91099chConnector.admin_page_url : 'admin.php?page=w91099ch';
            }
        } else {
            window.location.href = (window.w91099chConnector && window.w91099chConnector.admin_page_url) ? window.w91099chConnector.admin_page_url : 'admin.php?page=w91099ch';
        }
    });

    // Sync button (when already connected)
    var $consent = $('#mypowerly-w9-privacy-consent');
    var $syncBtn = $('#mypowerly-w9-sync');

    function updateAutoSyncStateBadge() {
        const $toggle = $('#w91099ch-enable-auto-sync');
        const $badge = $('#w91099ch-auto-sync-state');
        if (!$toggle.length || !$badge.length) return;
        const enabled = !!$toggle.prop('checked');
        $badge.toggleClass('is-on', enabled);
        $badge.text(enabled ? 'ON' : 'OFF');
        $badge.attr('data-enabled', enabled ? '1' : '0');
    }

    updateAutoSyncStateBadge();
    $(document).on('change', '#w91099ch-enable-auto-sync', updateAutoSyncStateBadge);

    (function() {
        const $toggle = $('#w91099ch-enable-auto-sync');
        if (!$toggle.length) return;

        $toggle.off('change.w91099chAutoSync').on('change.w91099chAutoSync', function() {
            const enabled = $(this).is(':checked');
            updateAutoSyncStateBadge();

            const ajaxUrl = (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector && window.w91099chConnector.ajaxurl)
                ? window.w91099chConnector.ajaxurl
                : (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '');
            const nonce = (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector && window.w91099chConnector.nonce)
                ? window.w91099chConnector.nonce
                : '';

            if (!ajaxUrl || !nonce) {
                alert('Unable to save auto-sync setting. Please reload the page.');
                $(this).prop('checked', !enabled);
                updateAutoSyncStateBadge();
                return;
            }

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'w91099ch_save_auto_sync_setting',
                    nonce: nonce,
                    enabled: enabled ? 1 : 0
                },
                success: function(response) {
                    if (response && response.success) {
                        showMypowerlyToastAboveButton(
                            enabled ? 'Auto-sync enabled.' : 'Auto-sync disabled.',
                            $toggle.closest('.bg-gradient-to-r')
                        );
                    } else {
                        const msg = (response && response.data) ? String(response.data) : 'Failed to save setting.';
                        showMypowerlyToastAboveButton('Error: ' + msg, $toggle.closest('.bg-gradient-to-r'));
                        $toggle.prop('checked', !enabled);
                        updateAutoSyncStateBadge();
                    }
                },
                error: function() {
                    showMypowerlyToastAboveButton('Connection error. Please try again.', $toggle.closest('.bg-gradient-to-r'));
                    $toggle.prop('checked', !enabled);
                    updateAutoSyncStateBadge();
                }
            });
        });
    })();

    function toggleSyncButton() {
        if (!$syncBtn.length) return;
        if ($consent.is(':checked')) {
            $syncBtn.prop('disabled', false).removeClass('opacity-60 cursor-not-allowed');
        } else {
            $syncBtn.prop('disabled', true).addClass('opacity-60 cursor-not-allowed');
        }
    }

    if ($syncBtn.length) {
        toggleSyncButton();
        $consent.on('change', function() {
            if ($consent.is(':checked')) {
                persistAdminConsentIfNeeded(function() {
                    toggleSyncButton();
                });
                return;
            }
            toggleSyncButton();
        });

        $syncBtn.on('click', function(e) {
            e.preventDefault();
            // Scroll to a relevant sync action (e.g. profile sync)
            var $target = $('#plugin-sync');
            if ($target.length) {
                $('html, body').animate({ scrollTop: $target.offset().top - 100 }, 500);
                $target.focus();
            } else {
                window.location.href = (window.w91099chConnector && window.w91099chConnector.admin_page_url) ? window.w91099chConnector.admin_page_url : 'admin.php?page=w91099ch';
            }
        });
    }
});

jQuery(document).ready(function($) {

function normalizeDigits(value) {
    return String(value || '').replace(/\D/g, '');
}

function formatTinByType(tinType, digits) {
    const d = normalizeDigits(digits);
    if (tinType === 'fein') {
        const p1 = d.slice(0, 2);
        const p2 = d.slice(2, 9);
        return p2.length ? (p1 + '-' + p2) : p1;
    }
    const p1 = d.slice(0, 3);
    const p2 = d.slice(3, 5);
    const p3 = d.slice(5, 9);
    if (p3.length) return p1 + '-' + p2 + '-' + p3;
    if (p2.length) return p1 + '-' + p2;
    return p1;
}

function getTinUiConfig(tinType) {
    const type = String(tinType || '').toLowerCase();
    if (type === 'fein') {
        return {
            label: 'FEIN',
            placeholder: '12-3456789',
            maxDigits: 9,
            minDigits: 9,
            
        };
    }
    if (type === 'ssn') {
        return {
            label: 'SSN',
            placeholder: '123-45-6789',
            maxDigits: 9,
            minDigits: 9,
          
        };
    }
    if (type === 'itin') {
        return {
            label: 'ITIN',
            placeholder: '9XX-XX-XXXX',
            maxDigits: 9,
            minDigits: 9,
           
        };
    }
    if (type === 'atn') {
        return {
            label: 'ATIN',
            placeholder: '9XX-93-XXXX',
            maxDigits: 9,
            minDigits: 9,
        
        };
    }
    return {
        label: 'TIN',
        placeholder: 'Enter TIN',
        maxDigits: 9,
        minDigits: 9,
      
    };
}

function ensureFieldMessage($field, messageId) {
    const id = String(messageId || '');
    if (!id) return null;
    let $msg = $('#' + id);
    if ($msg.length) return $msg;
    $msg = $('<div/>', { id: id })
        .addClass('text-xs mt-1')
        .css({ display: 'none' });
    $field.after($msg);
    return $msg;
}

function setFieldError($field, $msg, message) {
    $field.addClass('border-red-500').removeClass('border-gray-300');
    if ($msg && $msg.length) {
        $msg.text(String(message || '')).css({ display: 'block', color: '#dc2626' });
    }
}

function clearFieldError($field, $msg) {
    $field.removeClass('border-red-500').addClass('border-gray-300');
    if ($msg && $msg.length) {
        $msg.text('').css({ display: 'none' });
    }
}

function validateTin(showErrors) {
    const $type = $('#tin_type');
    const $tin = $('#tin');
    if (!$type.length || !$tin.length) return true;

    const typeVal = String($type.val() || '');
    const digits = normalizeDigits($tin.val());
    const cfg = getTinUiConfig(typeVal);
    const $msg = ensureFieldMessage($tin, 'tin-error');

    clearFieldError($type, ensureFieldMessage($type, 'tin-type-error'));
    clearFieldError($tin, $msg);

    if (!typeVal) {
        if (showErrors) {
            setFieldError($type, ensureFieldMessage($type, 'tin-type-error'), 'Please select a TIN Type.');
        }
        return false;
    }

    if (!digits) {
        if (showErrors) {
            setFieldError($tin, $msg, 'Please enter your ' + cfg.label + '.');
        }
        return false;
    }

    if (digits.length !== cfg.minDigits) {
        if (showErrors) {
            setFieldError($tin, $msg, cfg.label + ' must be ' + cfg.minDigits + ' digits. ' + cfg.example);
        }
        return false;
    }

    if ((typeVal === 'itin' || typeVal === 'atn') && digits.charAt(0) !== '9') {
        if (showErrors) {
            setFieldError($tin, $msg, cfg.label + ' should start with 9. ' + cfg.example);
        }
        return false;
    }

    if (typeVal === 'fein' && digits.slice(0, 2) === '00') {
        if (showErrors) {
            setFieldError($tin, $msg, 'FEIN prefix cannot be 00. ' + cfg.example);
        }
        return false;
    }

    clearFieldError($tin, $msg);
    return true;
}

function applyTinUi() {
    const $type = $('#tin_type');
    const $tin = $('#tin');
    if (!$type.length || !$tin.length) return;

    const typeVal = String($type.val() || '');
    const cfg = getTinUiConfig(typeVal);

    $tin.attr('placeholder', cfg.placeholder);
    $tin.attr('inputmode', 'numeric');
    $tin.attr('autocomplete', 'off');
    $tin.attr('maxlength', typeVal === 'fein' ? 10 : 11);

    const $helper = ensureFieldMessage($tin, 'tin-helper');
    if ($helper && $helper.length) {
        $helper.text(cfg.example).css({ display: 'block', color: '#6b7280' });
    }

    const formatted = formatTinByType(typeVal, $tin.val());
    if (String($tin.val() || '') !== formatted) {
        $tin.val(formatted);
    }
}

function applyBasePlaceholders() {
    const setPh = function(id, ph) {
        const $el = $('#' + id);
        if ($el.length && !$el.attr('placeholder')) {
            $el.attr('placeholder', ph);
        }
    };
    setPh('name', 'Full legal name (e.g., John A. Smith)');
    setPh('business_name', 'Business/DBA name (optional)');
    setPh('address', '123 Main St, Apt 4B');
    setPh('city', 'City');
    setPh('state', 'State (e.g., CA)');
    setPh('zip', 'ZIP (e.g., 12345 or 12345-6789)');
    setPh('requester', 'Requester name and address');
    setPh('account_numbers', 'Account number(s)');
    setPh('exempt_payee_code', 'e.g., 01');
    setPh('fatca_code', 'e.g., A');
}

function validateSelectField(id, label, showErrors) {
    const $field = $('#' + id);
    if (!$field.length) return true;
    const $msg = ensureFieldMessage($field, id + '-error');
    clearFieldError($field, $msg);
    const val = String($field.val() || '').trim();
    if (!val) {
        if (showErrors) {
            setFieldError($field, $msg, 'Please select ' + label + '.');
        }
        return false;
    }
    return true;
}

function validateRequiredTextField(id, label, showErrors) {
    const $field = $('#' + id);
    if (!$field.length) return true;
    const $msg = ensureFieldMessage($field, id + '-error');
    clearFieldError($field, $msg);
    const val = String($field.val() || '').trim();
    if (!val) {
        if (showErrors) {
            setFieldError($field, $msg, 'Please enter ' + label + '.');
        }
        return false;
    }
    return true;
}

function validateStateZip(showErrors) {
    let ok = true;
    const $state = $('#state');
    const $zip = $('#zip');

    if ($state.length) {
        const $msg = ensureFieldMessage($state, 'state-error');
        clearFieldError($state, $msg);
        const val = String($state.val() || '').trim();
        if (!val) {
            if (showErrors) setFieldError($state, $msg, 'Please enter State.');
            ok = false;
        } else if (!/^[a-zA-Z]{2}$/.test(val)) {
            if (showErrors) setFieldError($state, $msg, 'State must be 2 letters (e.g., CA).');
            ok = false;
        } else {
            $state.val(val.toUpperCase());
        }
    }

    if ($zip.length) {
        const $msg = ensureFieldMessage($zip, 'zip-error');
        clearFieldError($zip, $msg);
        const raw = String($zip.val() || '');
        const digits = raw.replace(/[^0-9]/g, '');
        let formatted = raw;
        if (digits.length > 5) {
            formatted = digits.slice(0, 5) + '-' + digits.slice(5, 9);
        } else {
            formatted = digits.slice(0, 5);
        }
        $zip.val(formatted);
        if (digits.length !== 5 && digits.length !== 9) {
            if (showErrors) setFieldError($zip, $msg, 'ZIP must be 5 digits or 9 digits (ZIP+4).');
            ok = false;
        }
    }

    return ok;
}

function validateSignature(showErrors) {
    const $sig = $('#signature_data');
    if (!$sig.length) return true;
    const $msg = ensureFieldMessage($sig, 'signature_data-error');
    clearFieldError($sig, $msg);
    const val = String($sig.val() || '').trim();
    if (!val) {
        if (showErrors) {
            setFieldError($sig, $msg, 'Please add your signature.');
        }
        return false;
    }
    return true;
}

function attachW9Validation() {
    const $form = $('#mypowerly-w9-form');
    if (!$form.length) return;

    applyBasePlaceholders();
    applyTinUi();

    $('#federal_tax_classification').off('change.w9Tax').on('change.w9Tax', function() {
        const val = String($(this).val() || '');
        const $llc = $('#llc_classification_container');
        if ($llc.length) {
            if (val === 'llc') {
                $llc.show();
            } else {
                $llc.hide();
                $('#llc_classification').val('');
                clearFieldError($('#llc_classification'), ensureFieldMessage($('#llc_classification'), 'llc_classification-error'));
            }
        }
        validateSelectField('federal_tax_classification', 'Federal tax classification', false);
    });

    $('#tin_type').off('change.tinUi').on('change.tinUi', function() {
        $('#tin').val('');
        applyTinUi();
        validateTin(false);
    });

    const applyTinMaskToField = function($field) {
        const typeVal = String($('#tin_type').val() || '');
        const digits = normalizeDigits($field.val());
        const cfg = getTinUiConfig(typeVal);
        const clipped = digits.slice(0, cfg.maxDigits);
        const formatted = formatTinByType(typeVal, clipped);
        if (String($field.val() || '') !== formatted) {
            $field.val(formatted);
        }
        validateTin(false);
    };

    // Delegated handlers so masking keeps working even if other scripts rebind/replace the input
    $(document)
        .off('input.tinUi keyup.tinUi', '#tin')
        .on('input.tinUi keyup.tinUi', '#tin', function() {
            applyTinMaskToField($(this));
        })
        .off('paste.tinUi', '#tin')
        .on('paste.tinUi', '#tin', function() {
            const $field = $(this);
            setTimeout(function() {
                applyTinMaskToField($field);
            }, 0);
        });

    $('#tin').off('blur.tinUi').on('blur.tinUi', function() {
        applyTinMaskToField($(this));
        validateTin(true);
    });

    $('#zip').off('blur.zipUi').on('blur.zipUi', function() {
        validateStateZip(true);
    });

    $('#state').off('blur.stateUi').on('blur.stateUi', function() {
        validateStateZip(true);
    });

    $form.off('submit.w9Validation').on('submit.w9Validation', function(e) {
        let ok = true;

        ok = validateRequiredTextField('name', 'Name', true) && ok;
        ok = validateSelectField('federal_tax_classification', 'Federal tax classification', true) && ok;
        if (String($('#federal_tax_classification').val() || '') === 'llc') {
            ok = validateSelectField('llc_classification', 'LLC classification', true) && ok;
        }
        ok = validateRequiredTextField('address', 'Address', true) && ok;
        ok = validateRequiredTextField('city', 'City', true) && ok;
        ok = validateStateZip(true) && ok;
        ok = validateTin(true) && ok;
        ok = validateSignature(true) && ok;

        const $date = $('#certification_date');
        if ($date.length) {
            const $msg = ensureFieldMessage($date, 'certification_date-error');
            clearFieldError($date, $msg);
            if (!String($date.val() || '').trim()) {
                setFieldError($date, $msg, 'Please select the certification date.');
                ok = false;
            }
        }

        if (!ok) {
            e.preventDefault();
            const $firstInvalid = $('.border-red-500').first();
            if ($firstInvalid.length) {
                try { $firstInvalid.focus(); } catch (err) {}
            }
            const $status = $('#mypowerly-w9-status');
            if ($status.length) {
                $status.text('Please fix the highlighted fields.').css({ display: 'block', color: '#dc2626', fontWeight: 600 });
            }
            return false;
        }

        const $status = $('#mypowerly-w9-status');
        if ($status.length) {
            $status.text('').css({ display: 'none' });
        }
        return true;
    });
}

attachW9Validation();

});

jQuery(document).ready(function($) {
    $(document).on('click', '#w91099ch-reset-download-stats', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const ajaxUrl = String($btn.data('ajaxurl') || (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '') || '');
        const nonce = String($btn.data('nonce') || '');
        if (!ajaxUrl || !nonce) {
            alert('Unable to reset statistics. Please reload the page and try again.');
            return;
        }

        const ok = window.confirm('Reset download statistics? This will set all counters back to 0.');
        if (!ok) {
            return;
        }

        const prevHtml = $btn.html();
        $btn.prop('disabled', true).addClass('opacity-60 cursor-not-allowed');
        $btn.html('<i class="fas fa-spinner fa-spin" style="margin-right: 6px;"></i>Resetting...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'w91099ch_reset_download_stats',
                nonce: nonce
            }
        })
            .done(function(resp) {
                if (resp && resp.success && resp.data) {
                    $('#w91099ch-total-downloads-count').text(String(resp.data.total_downloads ?? 0));
                    $('#w91099ch-print-to-pdf-count').text(String(resp.data.print_to_pdf ?? 0));
                    $('#w91099ch-official-forms-count').text(String(resp.data.official_forms ?? 0));
                    $btn.html('<i class="fas fa-check" style="margin-right: 6px;"></i>Reset');
                    setTimeout(function() {
                        $btn.html(prevHtml);
                    }, 900);
                    return;
                }
                const msg = (resp && resp.data) ? String(resp.data) : 'Reset failed.';
                alert(msg);
                $btn.html(prevHtml);
            })
            .fail(function() {
                alert('Network error. Please try again.');
                $btn.html(prevHtml);
            })
            .always(function() {
                $btn.prop('disabled', false).removeClass('opacity-60 cursor-not-allowed');
            });
    });
});

// Add this JavaScript at the end of the file
jQuery(document).ready(function($) {
    function showMypowerlyToastAboveButton(message, $button) {
        const toastId = 'mypowerly-toast';
        let $toast = $('#' + toastId);
        if (!$toast.length) {
            $toast = $('<div/>', { id: toastId })
                .css({
                    position: 'absolute',
                    background: 'var(--mp-success)',
                    color: '#fff',
                    padding: '10px 14px',
                    borderRadius: '12px',
                    boxShadow: '0 12px 28px rgba(5, 150, 105, 0.28)',
                    zIndex: 100000,
                    fontSize: '13px',
                    fontWeight: 700,
                    display: 'none',
                    maxWidth: '340px',
                    whiteSpace: 'nowrap'
                })
                .appendTo('body');
        }

        $toast.stop(true, true);
        $toast.text(String(message || ''));

        if ($button && $button.length) {
            const offset = $button.offset();
            const toastTop = Math.max(0, offset.top - 50);
            $toast.css({
                top: toastTop + 'px',
                left: offset.left + 'px'
            });
        }

        $toast.fadeIn(150).delay(1600).fadeOut(250);
    }

    // UI Restructure: Dashboard first (when connected), then W-9 form. No Optional/Advanced wrapper.
    (function restructureAdminUi() {
        const storageKey = 'w91099ch_save_data_optin';
        const isConnected = (function() {
            if (window.w91099chConnector && typeof window.w91099chConnector.is_connected !== 'undefined') {
                return !!window.w91099chConnector.is_connected;
            }
            return $('#disconnect-mypowerly').length > 0;
        })();

        // Update cards disabled state based on connection status
        updateCardsDisabledState(isConnected);

        const $headerSection = $('.min-h-screen').children('div.relative').first();

        const $w9 = $('#w9-form-section').length
            ? $('#w9-form-section')
            : $('#mypowerly-w9-form').closest('.mb-10');

        if (!$w9.length) {
            return;
        }

        if (!$w9.attr('id')) {
            $w9.attr('id', 'w9-form-section');
        }

        const $mainContainer = $w9.closest('div.max-w-7xl, div.max-w-screen-2xl, div.max-w-full');
        if (!$mainContainer.length) {
            return;
        }

        const $dashboard = $('#data-sync-dashboard-section');

        // Remove the Optional/Advanced wrapper if it exists, and restore its contents back into the main container.
        if ($('#optional-advanced-block').length) {
            const $optionalContent = $('#optional-advanced-content');
            if ($optionalContent.length) {
                const $moved = $optionalContent.children().detach();
                $('#optional-advanced-block').remove();
                if ($moved.length) {
                    const $anchor = $('#w9-top-anchor');
                    if ($anchor.length) {
                        $moved.insertAfter($anchor);
                    } else {
                        $mainContainer.prepend($moved);
                    }
                }
            } else {
                $('#optional-advanced-block').remove();
            }
        }

        const $anchor = $('#w9-top-anchor');
        if (isConnected) {
            if ($anchor.length) {
                if ($dashboard.length) {
                    $dashboard.insertAfter($anchor);
                    $w9.insertAfter($dashboard);
                } else {
                    $w9.insertAfter($anchor);
                }
            } else {
                if ($dashboard.length) {
                    $mainContainer.prepend($dashboard);
                    $dashboard.after($w9);
                } else {
                    $mainContainer.prepend($w9);
                }
            }
        } else {
            if ($anchor.length) {
                $w9.insertAfter($anchor);
            } else {
                $mainContainer.prepend($w9);
            }
        }

        if (!isConnected && !$('#mypowerly-connect-block').length) {
            const connectBlockHtml = ''
                + '<div class="mp-card p-6 mb-10" id="mypowerly-connect-block" style="border-left: 4px solid var(--mp-primary);">'
                + '  <div class="flex flex-col lg:flex-row items-start lg:items-center gap-6">'
                + '    <div class="flex-1">'
                + '      <div class="flex items-start gap-4">'
                + '        <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">'
                + '          <i class="fas fa-cloud-upload-alt text-2xl" style="color: var(--mp-primary);"></i>'
                + '        </div>'
                + '        <div>'
                + '          <h3 class="text-xl font-bold text-gray-800 mb-1">Save this form & unlock more Benefits</h3>'
                + '          <p class="text-gray-600">If you want to save this W-9 form data and get more benifitss, connect your site to Mypowerly.</p>'
                + '          <p class="text-red-500 font-bold mt-2"><strong>For security and privacy reasons, this plugin never transmits SSN or FEIN information to MyPowerly.</strong></p>'
                + '        </div>'
                + '      </div>'
                + '      <div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">'
                + '        <div class="text-sm text-gray-700 mb-3">This W-9 form data is <strong>not stored in WordPress</strong> by default. If you want to <strong>securely send and store</strong> your data (and your profile, affiliates, users data) in Mypowerly, click <strong>Connect to Mypowerly</strong>.</div>'
                + '        <label class="flex items-start gap-3 cursor-pointer">'
                + '          <input type="checkbox" id="mypowerly-consent" class="mt-1" />'
                + '          <span class="text-sm text-gray-700">I understand that connecting will securely sync and store My WordPress data in Mypowerly to unlock additional features.</span>'
                + '        </label>'
                + '        <label class="flex items-start gap-3 cursor-pointer mt-3">'
                + '          <input type="checkbox" id="mypowerly-auto-sync-on-connect" class="mt-1" />'
                + '          <span class="text-sm text-gray-700">Automatically sync all data to Mypowerly right after connecting.</span>'
                + '        </label>'
                + '        <div class="mt-4">'
                + '          <div class="text-sm font-semibold text-gray-800 mb-2">Discount Code (optional)</div>'
                + '          <div class="flex items-center gap-3">'
                + '            <input type="text" id="mypowerly-discount-code" class="mp-input" placeholder="Discount code" autocomplete="off" />'
                + '            <button type="button" id="mypowerly-apply-discount" class="mp-btn-secondary">Apply</button>'
                + '          </div>'
                + '          <div id="mypowerly-applied-discount" class="mt-3 hidden">'
                + '            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 text-gray-800 text-sm" style="border: 1px solid #e5e7eb;">'
                + '              <i class="fas fa-tag" style="opacity:.7"></i>'
                + '              <span id="mypowerly-applied-discount-code"></span>'
                + '              <button type="button" id="mypowerly-remove-discount" class="text-gray-500" style="font-size: 16px; line-height: 1;">&times;</button>'
                + '            </span>'
                + '          </div>'
                + '          <div class="text-xs text-gray-500 mt-2">Discount Code can be entered upto 48 hours of connecting. Only a valid Discount code will be accepted</div>'
                + '        </div>'
                + '      </div>'
                + '    </div>'
                + '    <div class="flex flex-col gap-3 w-full lg:w-auto">'
                + '      <button type="button" id="connect-mypowerly-cta" class="mp-btn-primary flex items-center justify-center gap-3" disabled>'
                + '        <i class="fas fa-plug"></i>'
                + '        Connect to Mypowerly'
                + '      </button>'
                + '      <div class="text-xs text-gray-500 text-center lg:text-left">You can still download the W-9 PDF without connecting.</div>'
                + '    </div>'
                + '  </div>'
                + '</div>';

            $w9.after(connectBlockHtml);
        }

        const $connectBlock = $('#mypowerly-connect-block');

        $('#w9-disconnected-help').remove();
        $(document).off('click.scrollToMypowerly');

        if ($connectBlock.length) {
            if (isConnected) {
                $connectBlock.hide();
            } else {
                $connectBlock.show();
            }
        }

        // Remove legacy save-toggle block (UI only)
        $('#save-data-toggle-block').remove();

        if (!isConnected) {
            if ($('#mypowerly-consent').length) {
                $('#mypowerly-consent').prop('checked', false);
                // Allow the button to be clickable for validation
                $('#connect-mypowerly-cta').prop('disabled', false);
                if (window.localStorage) {
                    localStorage.setItem(storageKey, '0');
                }
            }

            // Initialize auto-sync checkbox from server setting and wire up save-on-change
            // Default to false (unchecked) if not explicitly set to true
            var autoSyncInitial = !!(window.w91099chConnector && window.w91099chConnector.auto_sync_on_connect === true);
            $('#mypowerly-auto-sync-on-connect').prop('checked', autoSyncInitial);

            $('#mypowerly-auto-sync-on-connect').off('change.autoSync').on('change.autoSync', function() {
                var checked = !!this.checked;
                if (!window.w91099chConnector || !window.w91099chConnector.ajaxurl) return;
                $.ajax({
                    url: window.w91099chConnector.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'w91099ch_save_auto_sync_on_connect',
                        nonce: window.w91099chConnector.nonce,
                        enabled: checked ? '1' : '0'
                    }
                });
            });
        }

        const handleOptInChange = function(checked) {
            if (window.localStorage) {
                localStorage.setItem(storageKey, checked ? '1' : '0');
            }
            if (!isConnected) {
                // Remove error message if user checks the box
                if (checked) {
                    $('#mypowerly-consent-error').remove();
                }
                // Comment out or remove the disabling logic to allow clicking the button for validation
                // $('#connect-mypowerly-cta').prop('disabled', !checked);
            }
        };

        $('#mypowerly-consent').off('change.mypowerly').on('change.mypowerly', function() {
            handleOptInChange(!!this.checked);
        });

        $('#connect-mypowerly-cta').off('click.mypowerly').on('click.mypowerly', function() {
            // Check if global admin consent is missing
            const hasAdminConsent = (window.w91099chConnector && window.w91099chConnector.has_admin_consent);
            
            if (!hasAdminConsent) {
                // Find the notice with the consent button
                let $notice = $('.notice:contains("transmit selected site/profile/affiliate/team data")');
                
                // If notice is hidden/closed but still exists in DOM, or if it was removed by WP dismiss
                if ($notice.length && ($notice.is(':hidden') || !$notice.is(':visible'))) {
                    $notice.show().removeClass('is-dismissible'); // Force show and prevent easy dismissal
                }

                // If notice doesn't exist at all (removed from DOM), we might need to recreate it or just alert
                if (!$notice.length) {
                    // This is a fallback in case the notice was completely removed from DOM by WP dismiss
                    location.reload(); // Simplest way to bring back the notice if it was destroyed
                    return;
                }
                
                if ($notice.length) {
                    // Scroll to the notice at the top
                    $('html, body').animate({
                        scrollTop: $notice.offset().top - 50
                    }, 800);
                    
                    // Visual alert: Shake and highlight the notice
                    $notice.css({
                        'border-left-color': '#dc2626',
                        'background-color': '#fef2f2',
                        'transition': 'all 0.3s ease'
                    });
                    
                    // Add shake effect
                    $notice.addClass('mp-animate-shake');
                    setTimeout(function() {
                        $notice.removeClass('mp-animate-shake');
                    }, 1000);
                    
                    // Show a temporary tooltip or toast near the button to explain why
                    showMypowerlyToastAboveButton('Please give consent at the top first.', $('#connect-mypowerly-cta'));
                    
                    return;
                }
            }

            const $consentCb = $('#mypowerly-consent');
            if ($consentCb.length && !$consentCb.is(':checked')) {
                // Remove any existing error message
                $('#mypowerly-consent-error').remove();
                
                // Add red error message
                $consentCb.closest('label').after('<div id="mypowerly-consent-error" class="text-red-600 text-sm font-bold mt-2 mp-animate-shake"><i class="fas fa-exclamation-circle mr-1"></i> Please check the consent checkbox to continue.</div>');
                
                // Scroll to consent checkbox
                $('html, body').animate({
                    scrollTop: $consentCb.offset().top - 150
                }, 500);
                
                // Highlight the checkbox area
                $consentCb.closest('.bg-gray-50').css('border-color', '#dc2626').css('background-color', '#fef2f2');
                setTimeout(function() {
                    $consentCb.closest('.bg-gray-50').css('border-color', '').css('background-color', '');
                }, 3000);
                
                return;
            }

            showMypowerlyToastAboveButton('Connecting to Mypowerly...', $('#connect-mypowerly-cta'));
            const $existingConnectBtn = $('#connect-mypowerly-admin');
            if ($existingConnectBtn.length) {
                $existingConnectBtn.trigger('click');
                return;
            }

            if (!window.w91099chConnector || !window.w91099chConnector.ajaxurl || !window.w91099chConnector.nonce) {
                showMypowerlyToastAboveButton('Unable to start connection. Please refresh and try again.', $('#connect-mypowerly-cta'));
                return;
            }

            const discountCode = String($('#mypowerly-discount-code').val() || '').replace(/\s+/g, '').slice(0, 32);

            $.ajax({
                url: window.w91099chConnector.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'w91099ch_initiate_connection',
                    nonce: window.w91099chConnector.nonce,
                    discount_code: discountCode
                },
                success: function(response) {
                    if (!response || !response.success || !response.data || !response.data.api_url || !response.data.post_data) {
                        const msg = (response && response.data) ? response.data : 'Connection initialization failed';
                        showMypowerlyToastAboveButton(String(msg), $('#connect-mypowerly-cta'));
                        return;
                    }

                    fetch(response.data.api_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(response.data.post_data)
                    })
                        .then(apiResponse => {
                            if (apiResponse.redirected) {
                                window.location.href = apiResponse.url;
                                return null;
                            }
                            return apiResponse.json();
                        })
                        .then(apiData => {
                            if (!apiData) return;

                            const payload = apiData && apiData.data ? apiData.data : apiData;
                            const status = payload && payload.status ? payload.status : apiData.status;
                            const encryptedCredentials = (payload && payload.encrypted_credentials) || apiData.encrypted_credentials;
                            const registrationUrl = (payload && payload.registration_url) || apiData.registration_url;
                            const loginUrl = (payload && payload.login_url) || apiData.login_url;
                            const authorizationCode = (payload && payload.authorization_code) || apiData.authorization_code;

                            if (status === 'connected' && encryptedCredentials && typeof window.processCredentials === 'function') {
                                window.processCredentials(encryptedCredentials);
                                return;
                            }

                            if (status === 'connected' && authorizationCode) {
                                const baseUrl = (window.w91099chConnector && window.w91099chConnector.admin_page_url)
                                    ? window.w91099chConnector.admin_page_url
                                    : window.location.href;
                                if (baseUrl) {
                                    const sep = baseUrl.indexOf('?') === -1 ? '?' : '&';
                                    window.location.href = baseUrl + sep + 'status=connected&authorization_code=' + encodeURIComponent(String(authorizationCode));
                                }
                                return;
                            }

                            if (status === 'registration_required' && registrationUrl) {
                                window.location.href = registrationUrl;
                                return;
                            }

                            if (status === 'login_required' && loginUrl) {
                                setTimeout(() => {
                                    window.location.href = loginUrl;
                                }, 500);
                                return;
                            }
                        })
                        .catch(() => {
                            showMypowerlyToastAboveButton('Connection request failed. Please try again.', $('#connect-mypowerly-cta'));
                        });
                },
                error: function() {
                    showMypowerlyToastAboveButton('Connection error. Please try again.', $('#connect-mypowerly-cta'));
                }
            });
        });

        $(document).off('input.mypowerlyDiscount').on('input.mypowerlyDiscount', '#mypowerly-discount-code', function() {
            const v = String($(this).val() || '');
            const clean = v.replace(/\s+/g, '');
            if (v !== clean) {
                $(this).val(clean);
            }
        });

        const promoStorageKey = 'wp_1099_chaser_applied_promo';

        function setPromoUi(promo) {
            const $wrap = $('#mypowerly-applied-discount');
            const $code = $('#mypowerly-applied-discount-code');
            if (!$wrap.length || !$code.length) return;
            if (promo && promo.valid && promo.code) {
                $code.text(String(promo.code));
                $wrap.removeClass('hidden');
            } else {
                $code.text('');
                $wrap.addClass('hidden');
            }
        }

        function getDefaultPromoAmount() {
            const v = (window.w91099chConnector && window.w91099chConnector.promo_amount !== undefined)
                ? window.w91099chConnector.promo_amount
                : 100;
            const n = parseFloat(String(v));
            return Number.isFinite(n) ? n : 100;
        }

        function loadStoredPromo() {
            try {
                const raw = window.localStorage ? localStorage.getItem(promoStorageKey) : '';
                if (!raw) return null;
                const parsed = JSON.parse(raw);
                if (parsed && parsed.valid && parsed.code) {
                    return parsed;
                }
            } catch (e) {
            }
            return null;
        }

        function storePromo(promo) {
            try {
                if (window.localStorage) {
                    if (promo) {
                        localStorage.setItem(promoStorageKey, JSON.stringify(promo));
                    } else {
                        localStorage.removeItem(promoStorageKey);
                    }
                }
            } catch (e) {
            }
        }

        function ensurePromoInlineMessageEl() {
            if ($('#mypowerly-discount-inline-message').length) return;
            const $input = $('#mypowerly-discount-code');
            if (!$input.length) return;
            const $row = $input.closest('div');
            if (!$row.length) return;
            $row.after('<div id="mypowerly-discount-inline-message" class="text-xs text-gray-600 mt-2"></div>');
        }

        function setPromoInlineMessage(message, tone) {
            ensurePromoInlineMessageEl();
            const $el = $('#mypowerly-discount-inline-message');
            if (!$el.length) return;
            const t = String(tone || 'info');
            const cls = t === 'error' ? 'text-xs text-red-600 mt-2' : (t === 'success' ? 'text-xs text-green-700 mt-2' : 'text-xs text-gray-600 mt-2');
            $el.attr('class', cls).html(String(message || ''));
        }

        function clearPromoInlineMessage() {
            const $el = $('#mypowerly-discount-inline-message');
            if ($el.length) {
                $el.html('');
            }
        }

        const storedPromo = loadStoredPromo();
        if (storedPromo) {
            setPromoUi(storedPromo);
        }

        const $discountInput = $('#mypowerly-discount-code');
        const isPreapplied = $discountInput.length && String($discountInput.data('preapplied') || '') === '1';
        if (isPreapplied) {
            const pre = { valid: true, code: String($discountInput.val() || '1AB79K37AAA7') };
            setPromoUi(pre);
            setPromoInlineMessage('A discount code has already been applied', 'success');
            $('#mypowerly-discount-code').prop('disabled', true);
            $('#mypowerly-apply-discount').prop('disabled', true);
        }

        ensurePromoInlineMessageEl();

        $(document).off('click.mypowerlyApplyDiscount').on('click.mypowerlyApplyDiscount', '#mypowerly-apply-discount', function() {
            const $discountInput = $('#mypowerly-discount-code');
            const isPreapplied = $discountInput.length && String($discountInput.data('preapplied') || '') === '1';
            if (isPreapplied) {
                setPromoInlineMessage('A discount code has already been applied', 'success');
                return;
            }
            const code = String($('#mypowerly-discount-code').val() || '').trim();
            if (!code) {
                setPromoInlineMessage('Please enter a discount code.', 'error');
                return;
            }
            if (!window.w91099chConnector || !window.w91099chConnector.ajaxurl || !window.w91099chConnector.nonce) {
                setPromoInlineMessage('Missing AJAX URL/nonce. Please reload the admin page.', 'error');
                return;
            }

            const email = (window.w91099chConnector && window.w91099chConnector.user_email) ? String(window.w91099chConnector.user_email) : '';
            const pluginId = (window.w91099chConnector && window.w91099chConnector.promo_plugin_id !== undefined)
                ? parseInt(window.w91099chConnector.promo_plugin_id, 10)
                : NaN;

            if (!email) {
                setPromoInlineMessage('Missing email. Please connect to Mypowerly or reload the admin page.', 'error');
                return;
            }
            if (!Number.isFinite(pluginId) || pluginId <= 0) {
                setPromoInlineMessage('Missing plugin ID. Please reload the admin page.', 'error');
                return;
            }

            const payload = { code: code, email: email, plugin_id: pluginId };
            const consentText = 'This action will send the following data to Mypowerly (https://mypowerly.com) to redeem your discount code:\n\n'
                + JSON.stringify(payload, null, 2)
                + '\n\nDo you want to continue?';

            clearPromoInlineMessage();

            const $btn = $('#mypowerly-apply-discount');
            $btn.prop('disabled', true);

            $.ajax({
                url: window.w91099chConnector.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'w91099ch_validate_promo_code',
                    nonce: window.w91099chConnector.nonce,
                    code: code,
                    email: email,
                    plugin_id: pluginId,
                    confirm_send: 1
                }
            }).done(function(resp) {
                if (!resp || !resp.success || !resp.data) {
                    setPromoInlineMessage(safeExtractErrorMessage(resp, 'Failed to apply code. Please try again.'), 'error');
                    return;
                }

                const data = resp.data;
                if (data.valid) {
                    const promo = {
                        valid: true,
                        code: String(data.code || code)
                    };
                    if (data.discount_type !== undefined) promo.discount_type = data.discount_type;
                    if (data.discount_value !== undefined) promo.discount_value = data.discount_value;
                    if (data.campaign_name !== undefined) promo.campaign_name = data.campaign_name;

                    storePromo(promo);
                    setPromoUi(promo);

                    let msg = 'Discount applied successfully.';
                    const dt = String(data.discount_type || '');
                    const dv = String(data.discount_value || '');
                    if (dt && dv) {
                        msg = dt === 'percentage'
                            ? ('Discount applied: ' + dv + '% off')
                            : ('Discount applied: ' + dv);
                    }
                    showMypowerlyToastAboveButton(msg, $('#mypowerly-apply-discount'));
                    setPromoInlineMessage('Discount code applied successfully.', 'success');
                } else {
                    storePromo(null);
                    setPromoUi(null);
                    const errMsg = String(data.error || 'Invalid code');
                    setPromoInlineMessage(
                        errMsg + ' <a href="https://mypowerly.com" target="_blank" rel="noopener noreferrer" class="underline">Go to MyPowerly to get a discount code</a>.',
                        'error'
                    );
                }
            }).fail(function(xhr) {
                let msg = 'Validation failed';
                if (xhr && xhr.responseJSON) {
                    msg = safeExtractErrorMessage(xhr.responseJSON, msg);
                } else if (xhr && xhr.responseText) {
                    try {
                        msg = safeExtractErrorMessage(JSON.parse(xhr.responseText), msg);
                    } catch (e) {
                        msg = safeExtractErrorMessage(xhr.responseText, msg);
                    }
                }
                setPromoInlineMessage(String(msg) + ' <a href="https://mypowerly.com" target="_blank" rel="noopener noreferrer" class="underline">Go to MyPowerly</a>.', 'error');
            }).always(function() {
                $btn.prop('disabled', false);
            });
        });

        $(document).off('click.mypowerlyRemoveDiscount').on('click.mypowerlyRemoveDiscount', '#mypowerly-remove-discount', function() {
            storePromo(null);
            setPromoUi(null);
            $('#mypowerly-discount-code').val('');
        });
    })();

    // Helper function to safely extract error message from response
    function safeExtractErrorMessage(response, defaultMessage) {
        defaultMessage = defaultMessage || 'Unknown error';
        
        if (!response) {
            return defaultMessage;
        }
        
        if (typeof response === 'string') {
            // Fix: Ensure the response is a string before calling replace()
            try {
                return String(response);
            } catch (e) {
                return defaultMessage;
            }
        }
        
        if (typeof response === 'object') {
            // Try different common error message properties
            if (response.data) {
                if (typeof response.data === 'string') {
                    return response.data;
                } else if (typeof response.data === 'object') {
                    return response.data.message || response.data.error || response.data.detail || JSON.stringify(response.data);
                } else {
                    // Handle case where response.data is not a string or object
                    try {
                        return String(response.data);
                    } catch (e) {
                        return defaultMessage;
                    }
                }
            }
            
            // Try other common properties
            try {
                return response.message || response.error || response.detail || JSON.stringify(response);
            } catch (e) {
                return defaultMessage;
            }
        }
        
        try {
            return String(response);
        } catch (e) {
            return defaultMessage;
        }
    }

    function confirmSendToMypowerly(actionLabel) {
        const label = String(actionLabel || 'this data');
        return window.confirm('Are you sure you want to send ' + label + ' to Mypowerly?');
    }

    function buildWebhookStatusText(webhookStatus) {
        if (!webhookStatus) return '';

        // Sync-all returns a map keyed by event_type, e.g.
        // { plugin_data_synced: {attempted:1,sent:0,errors:[...]}, affiliates_synced: {...}, ... }
        if (typeof webhookStatus === 'object' && webhookStatus !== null && webhookStatus.attempted === undefined) {
            let attemptedTotal = 0;
            let sentTotal = 0;
            let errorsTotal = [];

            Object.keys(webhookStatus).forEach(function(key) {
                const item = webhookStatus[key];
                if (!item || typeof item !== 'object') return;

                attemptedTotal += Number(item.attempted || 0);
                sentTotal += Number(item.sent || 0);

                const itemErrors = Array.isArray(item.errors) ? item.errors : [];
                if (itemErrors.length) {
                    errorsTotal = errorsTotal.concat(itemErrors);
                }
            });

            if (attemptedTotal === 0) return 'Webhook: not configured';
            if (sentTotal === attemptedTotal && !errorsTotal.length) return 'Webhook: sent (' + sentTotal + '/' + attemptedTotal + ')';
            let msg = 'Webhook: ' + sentTotal + '/' + attemptedTotal + ' sent';
            if (errorsTotal.length && errorsTotal[0] && errorsTotal[0].error) {
                msg += ' - ' + String(errorsTotal[0].error);
            }
            return msg;
        }

        const attempted = Number(webhookStatus.attempted || 0);
        const sent = Number(webhookStatus.sent || 0);
        const errors = Array.isArray(webhookStatus.errors) ? webhookStatus.errors : [];
        if (attempted === 0) return 'Webhook: not configured';
        if (sent === attempted && !errors.length) return 'Webhook: sent (' + sent + '/' + attempted + ')';
        let msg = 'Webhook: ' + sent + '/' + attempted + ' sent';
        if (errors.length && errors[0] && errors[0].error) {
            msg += ' - ' + String(errors[0].error);
        }
        return msg;
    }

    function setupConsentGate(checkboxSelector, buttonSelector, statusSelector) {
        const $cb = $(checkboxSelector);
        const $btn = $(buttonSelector);
        const $status = statusSelector ? $(statusSelector) : null;
        if (!$btn.length) return;

        const apply = function() {
            const ok = $cb.length && $cb.is(':checked');
            $btn.prop('disabled', !ok);
            $btn.toggleClass('opacity-60 cursor-not-allowed', !ok);
            if ($status && $status.length) {
                $status.text(ok ? 'Ready to sync' : 'Check consent to enable sync');
            }
        };

        apply();

        if ($cb.length) {
            $cb.off('change.mypowerlyConsent').on('change.mypowerlyConsent', function() {
                // Apply immediately so the button enables/disables right away.
                apply();
                if ($(this).is(':checked') && typeof window.persistAdminConsentIfNeeded === 'function') {
                    window.persistAdminConsentIfNeeded(function() {
                        apply();
                    });
                }
            });
        }
    }

    setupConsentGate('#mypowerly-consent-profile-sync', '#profile-sync');
    setupConsentGate('#mypowerly-consent-plugin-sync', '#plugin-sync');
    setupConsentGate('#mypowerly-consent-affiliates-sync', '#affiliates-sync');
    setupConsentGate('#mypowerly-consent-team-sync', '#team-sync');
    setupConsentGate('#mypowerly-consent-sync-all', '#sync-all-data');
    setupConsentGate('#form-plugins-consent', '#sync-form-plugins-btn', '#form-sync-status');
    setupConsentGate('#contractor-consent', '#sync-contractor-btn', '#contractor-sync-status');
    setupConsentGate('#freelancer-contractor-consent', '#sync-freelancer-contractor-btn', '#freelancer-contractor-sync-status');
    setupConsentGate('#accounting-bookkeeping-consent', '#sync-accounting-bookkeeping-btn', '#accounting-bookkeeping-sync-status');
    setupConsentGate('#wallet-payout-consent', '#sync-wallet-payout-btn', '#wallet-payout-sync-status');
    setupConsentGate('#ecommerce-consent', '#sync-ecommerce-btn', '#ecommerce-sync-status');

    $(document).off('click.profileSync').on('click.profileSync', '#profile-sync', function() {
        if (!$('#mypowerly-consent-profile-sync').is(':checked')) {
            window.alert('Please check the consent checkbox to enable sending profile data to the external service.');
            return;
        }

        const $button = $('#profile-sync');
        const $progressSection = $('#profile-sync-progress-section');
        const $resultsSection = $('#profile-sync-results');
        const $progressFill = $('#profile-sync-progress-fill');
        const $progressText = $('#profile-sync-progress-text');
        const $currentStep = $('#current-profile-sync-step');
        const $duration = $('#profile-sync-duration');

        const startTime = Date.now();
        $resultsSection.hide();
        $progressSection.show();
        $button.prop('disabled', true);
        $progressFill.css('width', '10%');
        $progressText.text('10%');
        $currentStep.text('Syncing profile...');

        const ajaxUrl = (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector && window.w91099chConnector.ajaxurl)
            ? window.w91099chConnector.ajaxurl
            : (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '');

        const ajaxNonce = (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector && window.w91099chConnector.nonce)
            ? window.w91099chConnector.nonce
            : '';

        if (!ajaxUrl) {
            $button.prop('disabled', false);
            $progressSection.hide();
            alert('❌ AJAX URL is missing. Please reload the admin page.');
            return;
        }

        if (!ajaxNonce) {
            $button.prop('disabled', false);
            $progressSection.hide();
            alert('❌ Security nonce is missing. Please reload the admin page.');
            return;
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'w91099ch_sync_profile',
                nonce: ajaxNonce,
                client_type: 'individual'
            },
            success: function(response) {
                if (response && response.success) {
                    $progressFill.css('width', '100%');
                    $progressText.text('100%');

                    const duration = ((Date.now() - startTime) / 1000).toFixed(1) + 's';
                    $duration.text(duration);

                    const webhookInfo = buildWebhookStatusText(response.data && response.data.webhook_status);
                    const message = ((response.data && response.data.message) ? String(response.data.message) : '✅ Profile synced successfully!') + (webhookInfo ? ' | ' + webhookInfo : '');
                    $currentStep.text(message);

                    setTimeout(function() {
                        $progressSection.hide();
                        $resultsSection.show();
                        $button.prop('disabled', false);
                    }, 300);
                } else {
                    const msg = safeExtractErrorMessage(response, 'Profile sync failed');
                    $button.prop('disabled', false);
                    $progressSection.hide();
                    alert('❌ ' + msg);
                }
            },
            error: function(xhr, status, error) {
                $button.prop('disabled', false);
                $progressSection.hide();
                const msg = safeExtractErrorMessage(xhr.responseText || error, 'Profile sync connection error');
                alert('❌ ' + msg);
            }
        });
    });

    $(document).off('click.pluginSync').on('click.pluginSync', '#plugin-sync', function() {
        if (!$('#mypowerly-consent-plugin-sync').is(':checked')) {
            window.alert('Please check the consent checkbox to enable sending plugin data to the external service.');
            return;
        }
        const $button = $('#plugin-sync');
        const $progressSection = $('#plugin-sync-progress-section');
        const $resultsSection = $('#plugin-sync-results');
        const $progressFill = $('#plugin-sync-progress-fill');
        const $progressText = $('#plugin-sync-progress-text');
        const $currentStep = $('#current-plugin-sync-step');
        const $duration = $('#plugin-sync-duration');

        const startTime = Date.now();
        $resultsSection.hide();
        $progressSection.show();
        $button.prop('disabled', true);
        $progressFill.css('width', '10%');
        $progressText.text('10%');
        $currentStep.text('Syncing plugins...');

        const ajaxUrl = (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector && window.w91099chConnector.ajaxurl)
            ? window.w91099chConnector.ajaxurl
            : (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '');

        const ajaxNonce = (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector && window.w91099chConnector.nonce)
            ? window.w91099chConnector.nonce
            : '';

        if (!ajaxUrl) {
            $button.prop('disabled', false);
            $progressSection.hide();
            alert('❌ AJAX URL is missing. Please reload the admin page.');
            return;
        }

        if (!ajaxNonce) {
            $button.prop('disabled', false);
            $progressSection.hide();
            alert('❌ Security nonce is missing. Please reload the admin page.');
            return;
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'w91099ch_sync_plugin_data',
                nonce: ajaxNonce
            },
            success: function(response) {
                if (response && response.success) {
                    $progressFill.css('width', '100%');
                    $progressText.text('100%');
                    const duration = ((Date.now() - startTime) / 1000).toFixed(1) + 's';
                    $duration.text(duration);

                    const now = new Date();
                    $('#last-plugin-sync-time').text(now.toLocaleString());
                    const webhookMessage = buildWebhookStatusText(response.data && response.data.webhook_status);
                    $('#plugin-sync-status').text(webhookMessage || 'Ready');
                    const successMessage = '✅ Plugins synced successfully!' + (webhookMessage ? ' | ' + webhookMessage : '');
                    $currentStep.text(successMessage);
                    $('#plugin-sync-result-message').text(successMessage);

                    if (response.data && response.data.stats) {
                        if (response.data.stats.plugins_count !== undefined) {
                            $('#plugins-count').text(String(response.data.stats.plugins_count) + ' plugins detected');
                        }
                        if (response.data.stats.total_affiliates !== undefined) {
                            $('#total-affiliates-count').text(String(response.data.stats.total_affiliates) + ' total affiliates/vendors');
                        }
                    }

                    setTimeout(function() {
                        $progressSection.hide();
                        $resultsSection.show();
                        $button.prop('disabled', false);
                    }, 300);
                } else {
                    const msg = safeExtractErrorMessage(response, 'Plugin sync failed');
                    $button.prop('disabled', false);
                    $progressSection.hide();
                    alert('❌ ' + msg);
                }
            },
            error: function(xhr, status, error) {
                $button.prop('disabled', false);
                $progressSection.hide();
                const msg = safeExtractErrorMessage(xhr.responseText || error, 'Plugin sync connection error');
                alert('❌ ' + msg);
            }
        });
    });

    function updateDetectedPluginsUi(plugins, totalAffiliates) {
        const entries = (plugins && typeof plugins === 'object') ? Object.entries(plugins) : [];

        const $list = $('#detected-plugins-list');
        const $container = $('#detected-plugins-container');

        if ($('#plugins-count').length) {
            $('#plugins-count').text(String(entries.length) + ' plugins detected');
        }
        if ($('#total-affiliates-count').length && totalAffiliates !== undefined && totalAffiliates !== null) {
            $('#total-affiliates-count').text(String(totalAffiliates) + ' total affiliates/vendors');
        }

        if ($list.length) {
            if (!entries.length) {
                $list.html(
                    '<div class="text-center py-8">'
                    + '<div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">'
                    + '<i class="fa-solid fa-plug-circle-xmark text-2xl text-gray-400"></i>'
                    + '</div>'
                    + '<p class="text-gray-600 mb-2">No All plugins detected</p>'
                    + '<p class="text-sm text-gray-500">Install All plugins to start syncing data</p>'
                    + '</div>'
                );
            } else {
                const html = entries.map(([slug, plugin]) => {
                    const name = plugin && plugin.name ? plugin.name : slug;
                    const version = plugin && plugin.version ? plugin.version : '';
                    const affiliateCount = (plugin && plugin.affiliate_count !== undefined && plugin.affiliate_count !== null)
                        ? plugin.affiliate_count
                        : 0;
                    const versionHtml = version ? ('<span>v' + escapeHtml(version) + '</span><span class="w-1 h-1 bg-gray-300 rounded-full"></span>') : '';

                    const isAffiliateVendor = !!(plugin && plugin.detected);
                    const tagHtml = isAffiliateVendor
                        ? '<span class="w-2 h-2 rounded-full bg-purple-500"></span>'
                        : '';

                    const affiliateCountHtml = isAffiliateVendor
                        ? ('<span class="text-purple-700 font-semibold">' + escapeHtml(String(affiliateCount)) + ' affiliates/vendors</span>')
                        : '';

                    return ''
                        + '<div class="p-2 bg-white rounded-lg border border-gray-200 hover:border-blue-300 transition-colors">'
                        + '  <div class="flex justify-between items-center">'
                        + '    <div class="flex-1">'
                        + '      <div class="flex items-center gap-3">'
                        + '        <div class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center">'
                        + '          <i class="fa-solid fa-cube text-sm text-blue-600"></i>'
                        + '        </div>'
                        + '        <div>'
                        + '          <div class="font-semibold text-gray-800 text-sm">' + escapeHtml(name) + '</div>'
                        + '          <div class="flex items-center gap-3 mt-1 text-xs text-gray-600">'
                        +              versionHtml
                        +              affiliateCountHtml
                        + '          </div>'
                        + '        </div>'
                        + '      </div>'
                        + '    </div>'
                        + '    <div class="flex items-center gap-2">'
                        +        tagHtml
                        + '      <div class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">ACTIVE</div>'
                        + '    </div>'
                        + '  </div>'
                        + '</div>';
                }).join('');

                $list.html(html);
            }
        } else if ($container.length) {
            $container.html('<div id="detected-plugins-list"></div>');
            updateDetectedPluginsUi(plugins, totalAffiliates);
        }

        const $select = $('#affiliate-plugin-select');
        if ($select.length) {
            const current = String($select.val() || '');

            let optionsHtml = '<option value="">All affiliate detected plugins</option>';
            entries
                .filter(([_, plugin]) => !!(plugin && plugin.detected))
                .forEach(([slug, plugin]) => {
                const name = plugin && plugin.name ? plugin.name : slug;
                const affiliateCount = (plugin && plugin.affiliate_count !== undefined && plugin.affiliate_count !== null)
                    ? plugin.affiliate_count
                    : 0;
                optionsHtml += '<option value="' + escapeHtml(slug) + '">' + escapeHtml(name) + ' (' + escapeHtml(String(affiliateCount)) + ' affiliates/vendors)</option>';
            });

            $select.html(optionsHtml);

            const exists = current && entries.some(([slug]) => String(slug) === current);
            $select.val(exists ? current : '');
        }
    }

    $('#refresh-plugins').off('click.refreshPlugins').on('click.refreshPlugins', function() {
        const $btn = $(this);
        const $icon = $btn.find('i').first();

        const ajaxUrl = (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector && window.w91099chConnector.ajaxurl)
            ? window.w91099chConnector.ajaxurl
            : (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '');

        const ajaxNonce = (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector && window.w91099chConnector.nonce)
            ? window.w91099chConnector.nonce
            : '';

        if (!ajaxUrl || !ajaxNonce) {
            alert('❌ Missing AJAX URL/nonce. Please reload the admin page.');
            return;
        }

        $btn.prop('disabled', true);
        if ($icon.length) {
            $icon.removeClass('fa-redo').addClass('fa-spinner fa-spin');
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'w91099ch_refresh_affiliate_plugins',
                nonce: ajaxNonce
            },
            success: function(response) {
                if (response && response.success && response.data) {
                    updateDetectedPluginsUi(response.data.plugins || {}, response.data.total_affiliates);
                } else {
                    const msg = safeExtractErrorMessage(response, 'Failed to refresh plugins');
                    alert('❌ ' + msg);
                }
            },
            error: function(xhr, status, error) {
                const msg = safeExtractErrorMessage(xhr && xhr.responseText ? xhr.responseText : error, 'Failed to refresh plugins');
                alert('❌ ' + msg);
            },
            complete: function() {
                $btn.prop('disabled', false);
                if ($icon.length) {
                    $icon.removeClass('fa-spinner fa-spin').addClass('fa-redo');
                }
            }
        });
    });

    $(document).off('click.affiliatesSync').on('click.affiliatesSync', '#affiliates-sync', function() {
        if (!$('#mypowerly-consent-affiliates-sync').is(':checked')) {
            window.alert('Please check the consent checkbox to enable sending affiliate/vendor data to the external service.');
            return;
        }
        const $button = $('#affiliates-sync');
        const $progressSection = $('#affiliates-sync-progress-section');
        const $resultsSection = $('#affiliates-sync-results');
        const $progressFill = $('#affiliates-sync-progress-fill');
        const $progressText = $('#affiliates-sync-progress-text');
        const $currentStep = $('#current-affiliates-sync-step');
        const $duration = $('#affiliates-sync-duration');
        const $syncedCount = $('#affiliates-synced');

        const selectedPluginSlug = String(($('#affiliate-plugin-select').val() || '')).trim();
        const selectedPluginLabel = selectedPluginSlug
            ? String(($('#affiliate-plugin-select option:selected').text() || '')).trim()
            : 'All Affiliate Detected plugins';

        const visibleAffiliateCount = parseInt(String(($('#affiliate-count').text() || '')).replace(/[^0-9]/g, ''), 10);
        if (selectedPluginSlug && (!isFinite(visibleAffiliateCount) || visibleAffiliateCount <= 0)) {
            $resultsSection.hide();
            $progressSection.show();
            $button.prop('disabled', true);
            $progressFill.css('width', '100%');
            $progressText.text('100%');
            $currentStep.text('ℹ️ No affiliates/vendors found for ' + selectedPluginLabel + '. Nothing to sync.');
            $duration.text('0s');
            $syncedCount.text('0');
            $('#affiliates-sync-status').text('Ready');

            setTimeout(function() {
                $progressSection.hide();
                $resultsSection.show();
                $button.prop('disabled', false);
            }, 200);
            return;
        }

        const startTime = Date.now();
        $resultsSection.hide();
        $progressSection.show();
        $button.prop('disabled', true);
        $progressFill.css('width', '10%');
        $progressText.text('10%');
        $currentStep.text('Syncing affiliates/vendors (' + selectedPluginLabel + ')...');

        const ajaxUrl = (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector && window.w91099chConnector.ajaxurl)
            ? window.w91099chConnector.ajaxurl
            : (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '');

        const ajaxNonce = (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector && window.w91099chConnector.nonce)
            ? window.w91099chConnector.nonce
            : '';

        if (!ajaxUrl) {
            $button.prop('disabled', false);
            $progressSection.hide();
            alert('❌ AJAX URL is missing. Please reload the admin page.');
            return;
        }

        if (!ajaxNonce) {
            $button.prop('disabled', false);
            $progressSection.hide();
            alert('❌ Security nonce is missing. Please reload the admin page.');
            return;
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'w91099ch_sync_affiliates',
                nonce: ajaxNonce,
                plugin_slug: selectedPluginSlug
            },
            success: function(response) {
                if (response && response.success) {
                    $progressFill.css('width', '100%');
                    $progressText.text('100%');
                    const excludedCount = (response.data && response.data.stats && response.data.stats.excluded !== undefined)
                        ? parseInt(response.data.stats.excluded, 10)
                        : 0;
                    const affiliatesWebhookInfo = buildWebhookStatusText(response.data && response.data.webhook_status);
                    const affiliatesMsg = (excludedCount > 0
                        ? ('✅ Affiliates/Vendors synced! (' + excludedCount + ' excluded)')
                        : '✅ Affiliates/Vendors synced successfully!')
                        + (affiliatesWebhookInfo ? ' | ' + affiliatesWebhookInfo : '');
                    $currentStep.text(affiliatesMsg);

                    const duration = ((Date.now() - startTime) / 1000).toFixed(1) + 's';
                    $duration.text(duration);

                    const count = (response.data && response.data.stats && response.data.stats.successful !== undefined)
                        ? response.data.stats.successful
                        : ((response.data && response.data.stats && response.data.stats.total_affiliates !== undefined)
                            ? response.data.stats.total_affiliates
                            : 0);
                    $syncedCount.text(String(count));

                    const now = new Date();
                    $('#last-affiliates-sync-time').text(now.toLocaleString());
                    $('#affiliates-sync-status').text('Ready');

                    setTimeout(function() {
                        $progressSection.hide();
                        $resultsSection.show();
                        $button.prop('disabled', false);
                    }, 300);
                } else {
                    const msg = safeExtractErrorMessage(response, 'Affiliates sync failed');
                    $button.prop('disabled', false);
                    $progressSection.hide();
                    $currentStep.text('❌ ' + msg);
                    $('#affiliates-sync-status').text('Error');
                }
            },
            error: function(xhr, status, error) {
                $button.prop('disabled', false);
                $progressSection.hide();
                const msg = safeExtractErrorMessage(xhr.responseText || error, 'Affiliates sync connection error');
                $currentStep.text('❌ ' + msg);
                $('#affiliates-sync-status').text('Error');
            }
        });
    });
    // Sync All Data Functionality - consolidated single payload
    $(document).off('click.syncAllData').on('click.syncAllData', '#sync-all-data', function() {
        if (!$('#mypowerly-consent-sync-all').is(':checked')) {
            window.alert('Please check the consent checkbox to enable sending data to the external service.');
            return;
        }
        syncAllData();
    });

    function syncAllData() {
        window.w91099chConnectorConsole.log('Starting Sync All Data...');

        const $button = $('#sync-all-data');
        const $progressSection = $('#sync-all-progress-section');
        const $resultsSection = $('#sync-all-results');
        const $errorSection = $('#sync-all-error');
        const $progressFill = $('#sync-all-progress-fill');
        const $progressText = $('#sync-all-progress-text');
        const $currentStep = $('#current-sync-all-step');
        const $duration = $('#sync-all-duration');

        $resultsSection.hide();
        $errorSection.hide();
        $progressSection.show();
        $button.prop('disabled', true);

        $('.sync-step .step-status').each(function() {
            $(this).text('Pending').removeClass('step-success step-error step-processing').addClass('step-pending');
        });

        const startTime = Date.now();

        function updateProgress(percent, step) {
            $progressFill.css('width', percent + '%');
            $progressText.text(Math.round(percent) + '%');
            $currentStep.text(step);
        }

        function updateStepStatus(step, status) {
            const $step = $('.sync-step[data-step="' + step + '"] .step-status');
            $step.removeClass('step-pending step-processing step-success step-error').addClass('step-' + status);

            switch (status) {
                case 'processing':
                    $step.text('Processing...');
                    break;
                case 'success':
                    $step.text('Complete');
                    break;
                case 'error':
                    $step.text('Failed');
                    break;
                default:
                    $step.text('Pending');
            }
        }

        function handleSyncAllError(errorMessage, step) {
            window.w91099chConnectorConsole.error('Sync All Error:', errorMessage);

            if (step) {
                updateStepStatus(step, 'error');
            }

            $button.prop('disabled', false);
            $progressSection.hide();

            let safeErrorMessage = 'Unknown error';
            if (typeof errorMessage === 'string') {
                safeErrorMessage = errorMessage;
            } else if (typeof errorMessage === 'object') {
                safeErrorMessage = errorMessage.message || errorMessage.error || JSON.stringify(errorMessage);
            } else {
                safeErrorMessage = String(errorMessage);
            }

            $('#sync-all-error-message').text(safeErrorMessage);
            $errorSection.slideDown();
            alert('Sync All failed: ' + safeErrorMessage);
        }

        function completeSyncAll(webhookInfo) {
            const duration = ((Date.now() - startTime) / 1000).toFixed(1) + 's';
            $duration.text(duration);

            $progressSection.hide();
            $resultsSection.slideDown();
            $button.prop('disabled', false);

            const now = new Date();
            const timeString = now.toLocaleString();

            $('#last-plugin-sync-time').text(timeString);
            $('#last-affiliates-sync-time').text(timeString);
            $('#last-team-sync-time').text(timeString);

            $('#plugin-sync-status').text(webhookInfo || 'Ready');
            $('#affiliates-sync-status').text(webhookInfo || 'Ready');
            $('#team-sync-status').text(webhookInfo || 'Ready');
            $('#form-sync-status').text('Synced via Sync All');
            $('#contractor-sync-status').text('Synced via Sync All');
            $('#freelancer-contractor-sync-status').text('Synced via Sync All');
            $('#accounting-bookkeeping-sync-status').text('Synced via Sync All');
            $('#wallet-payout-sync-status').text('Synced via Sync All');

            setTimeout(() => {
                if (typeof loadDetectedPlugins === 'function') {
                    loadDetectedPlugins();
                }
                const currentPlugin = $('#affiliate-plugin-select').val();
                if (currentPlugin && typeof loadAffiliates === 'function') {
                    loadAffiliates(currentPlugin);
                }
                refreshTeamUserCount();
            }, 500);

            alert('✅ All data synced! ' + (webhookInfo || 'Webhook: not configured'));
        }

        function refreshTeamUserCount() {
            $.ajax({
                url: window.w91099chConnector.ajaxurl,
                type: 'POST',
                data: {
                    action: 'w91099ch_get_user_count',
                    nonce: window.w91099chConnector.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#team-user-count').text(response.data.total_users);
                    }
                }
            });
        }

        updateProgress(5, 'Initializing sync...');

        updateProgress(10, 'Preparing consolidated payload...');
        ['plugin','affiliate','team','forms','membership','contractor','freelancer','accounting','payout','website-content','analytics','system-config','security-access','payments'].forEach(function(stepKey) {
            updateStepStatus(stepKey, 'processing');
        });

        $.ajax({
            url: window.w91099chConnector.ajaxurl,
            type: 'POST',
            data: {
                action: 'w91099ch_sync_all_webhook',
                nonce: window.w91099chConnector.nonce
            },
            success: function(webhookResponse) {
                if (webhookResponse && webhookResponse.success) {
                    ['plugin','affiliate','team','forms','membership','contractor','freelancer','accounting','payout','website-content','analytics','system-config','security-access','payments'].forEach(function(stepKey) {
                        updateStepStatus(stepKey, 'success');
                    });
                    const syncAllWebhookInfo = buildWebhookStatusText(webhookResponse.data && webhookResponse.data.webhook_status);
                    updateProgress(100, 'All data synced! ' + (syncAllWebhookInfo || 'Webhook: not configured'));
                    completeSyncAll(syncAllWebhookInfo);
                } else {
                    let msg = 'Webhook failed';
                    if (webhookResponse && webhookResponse.data) {
                        if (typeof webhookResponse.data === 'string') {
                            msg = webhookResponse.data;
                        } else if (typeof webhookResponse.data === 'object') {
                            msg = webhookResponse.data.message || webhookResponse.data.error || JSON.stringify(webhookResponse.data);
                        } else {
                            msg = String(webhookResponse.data);
                        }
                    }
                    handleSyncAllError('Sync-all webhook failed: ' + msg, 'plugin');
                }
            },
            error: function(xhr, status, error) {
                let msg = 'Connection error';
                if (xhr && xhr.responseText) {
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        msg = errorResponse.data || errorResponse.message || xhr.responseText;
                    } catch (e) {
                        msg = xhr.responseText;
                    }
                } else if (error) {
                    msg = String(error);
                }
                handleSyncAllError('Sync-all webhook error: ' + msg, 'plugin');
            }
        });
    }

    // Team/User Invite Members (Card 4) - handler in advanced-features-page.php inline script

    function syncTeamData() {
        window.w91099chConnectorConsole.log('Starting Team Invite...');
        
        const $button = $('#team-sync');
        const $progressSection = $('#team-sync-progress-section');
        const $resultsSection = $('#team-sync-results');
        const $progressFill = $('#team-sync-progress-fill');
        const $progressText = $('#team-sync-progress-text');
        const $currentStep = $('#current-team-sync-step');
        const $duration = $('#team-sync-duration');
        const $syncedCount = $('#team-users-synced');
        
        // Reset UI
        $resultsSection.hide();
        $progressSection.show();
        $button.prop('disabled', true);
        
        const startTime = Date.now();
        
        // Update progress function
        function updateTeamProgress(percent, step) {
            $progressFill.css('width', percent + '%');
            $progressText.text(Math.round(percent) + '%');
            $currentStep.text(step);
        }
        
        // Get selected roles (optional filter for invitation)
        const selectedRoles = [];
        $('input[name="sync_roles[]"]:checked').each(function() {
            selectedRoles.push($(this).val());
        });

        updateTeamProgress(10, 'Preparing team invites...');

        inviteTeamMembers({ roles: selectedRoles })
            .then(function(result) {
                updateTeamProgress(100, '✅ Team invitations sent successfully!');

                setTimeout(() => {
                    $progressSection.hide();
                    $resultsSection.show();

                    const duration = ((Date.now() - startTime) / 1000).toFixed(1) + 's';
                    $duration.text(duration);
                    $syncedCount.text((result && result.invited !== undefined) ? result.invited : 0);

                    const now = new Date();
                    $('#last-team-sync-time').text(now.toLocaleString());

                    $button.prop('disabled', false);
                    refreshTeamUserCount();

                    alert('✅ Team invite completed!');
                }, 300);
            })
            .catch(function(err) {
                handleTeamSyncError((err && err.message) ? err.message : String(err));
            });
        
        function handleTeamSyncError(errorMessage) {
            window.w91099chConnectorConsole.error('Team Sync Error:', errorMessage);
            
            $button.prop('disabled', false);
            $progressSection.hide();
            
            alert('❌ Team invite failed: ' + errorMessage);
        }
    }

    // Forms / Membership / Freelancer / Accounting / Wallet sync buttons
    (function bindCardSyncButtons() {
        const ajaxUrl = (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector && window.w91099chConnector.ajaxurl)
            ? window.w91099chConnector.ajaxurl
            : (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '');
        const nonce = (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector && window.w91099chConnector.nonce)
            ? window.w91099chConnector.nonce
            : '';

        const safeMsg = function(response, fallback) {
            if (typeof safeExtractErrorMessage === 'function') {
                return safeExtractErrorMessage(response, fallback);
            }
            return fallback || 'Unknown error';
        };

        function gateConsent($consent, $btn, $status) {
            if (!$consent.length || !$btn.length) return;
            const apply = function() {
                const ok = $consent.is(':checked');
                $btn.prop('disabled', !ok);
                $btn.toggleClass('opacity-60 cursor-not-allowed', !ok);
                if ($status && $status.length) {
                    $status.text(ok ? 'Ready to sync' : 'Check consent to enable sync');
                }
            };
            apply();
            $consent.off('change.cardConsent').on('change.cardConsent', apply);
        }

        function bindSync($btn, $status, action, confirmLabel, busyLabel, doneLabelFn) {
            if (!$btn.length) return;

            // Store original button label for restore
            $btn.data('original-label', $btn.html());

            $btn.off('click.cardSync').on('click.cardSync', function() {
                if ($btn.prop('disabled')) return;

                const btnId    = $btn.attr('id') || '';
                const resultId = btnId.replace('-btn', '-result');
                const $result  = $('#' + resultId);

                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Syncing...');
                if ($status && $status.length) {
                    $status.text(busyLabel);
                }
                if ($result.length) {
                    $result.hide();
                }

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: action,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response && response.success) {
                            const webhookInfo = buildWebhookStatusText(response.data && response.data.webhook_status);
                            const doneLabel = doneLabelFn(response) + (webhookInfo ? ' | ' + webhookInfo : '');
                            if ($status && $status.length) {
                                $status.text('Ready');
                            }
                            if ($result.length) {
                                $result.find('.card-sync-result-msg').text(doneLabel);
                                $result.show();
                            }
                        } else {
                            const msg = safeMsg(response, 'Sync failed');
                            if ($status && $status.length) {
                                $status.text('Sync failed: ' + msg);
                            }
                            alert('❌ Sync failed: ' + msg);
                        }
                    },
                    error: function(xhr) {
                        const msg = safeMsg(xhr && (xhr.responseText || xhr.statusText), 'Sync error');
                        if ($status && $status.length) {
                            $status.text('Sync error: ' + msg);
                        }
                        alert('❌ Sync error: ' + msg);
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html($btn.data('original-label') || $btn.html());
                    }
                });
            });
        }

        const $formConsent = $('#form-plugins-consent');
        const $formBtn = $('#sync-form-plugins-btn');
        const $formStatus = $('#form-sync-status');
        gateConsent($formConsent, $formBtn, $formStatus);
        bindSync(
            $formBtn,
            $formStatus,
            'w91099ch_sync_form_plugins',
            'form plugin data',
            'Syncing form data...',
            function(resp) {
                const count = resp && resp.data ? (resp.data.synced_count || 0) : 0;
                const forms = resp && resp.data ? (resp.data.total_forms || 0) : 0;
                return 'Sync completed! ' + count + ' plugins, ' + forms + ' forms.';
            }
        );

        const $contractorConsent = $('#contractor-consent');
        const $contractorBtn = $('#sync-contractor-btn');
        const $contractorStatus = $('#contractor-sync-status');
        gateConsent($contractorConsent, $contractorBtn, $contractorStatus);
        bindSync(
            $contractorBtn,
            $contractorStatus,
            'w91099ch_sync_contractor_plugins',
            'membership/subscription plugin data',
            'Syncing contractor data...',
            function(resp) {
                const count = resp && resp.data ? (resp.data.synced_count || 0) : 0;
                const members = resp && resp.data ? (resp.data.total_members || 0) : 0;
                return 'Sync completed! ' + count + ' plugins, ' + members + ' members.';
            }
        );

        const $freelancerConsent = $('#freelancer-contractor-consent');
        const $freelancerBtn = $('#sync-freelancer-contractor-btn');
        const $freelancerStatus = $('#freelancer-contractor-sync-status');
        gateConsent($freelancerConsent, $freelancerBtn, $freelancerStatus);
        bindSync(
            $freelancerBtn,
            $freelancerStatus,
            'w91099ch_sync_freelancer_contractor_plugins',
            'freelancer/contractor plugin data',
            'Syncing freelancer data...',
            function(resp) {
                const count = resp && resp.data ? (resp.data.synced_count || 0) : 0;
                const contractors = resp && resp.data ? (resp.data.total_contractors || 0) : 0;
                return 'Sync completed! ' + count + ' plugins, ' + contractors + ' contractors.';
            }
        );

        const $accountingConsent = $('#accounting-bookkeeping-consent');
        const $accountingBtn = $('#sync-accounting-bookkeeping-btn');
        const $accountingStatus = $('#accounting-bookkeeping-sync-status');
        gateConsent($accountingConsent, $accountingBtn, $accountingStatus);
        bindSync(
            $accountingBtn,
            $accountingStatus,
            'w91099ch_sync_accounting_bookkeeping_plugins',
            'accounting/bookkeeping plugin data',
            'Syncing accounting data...',
            function(resp) {
                const count = resp && resp.data ? (resp.data.synced_count || 0) : 0;
                const total = resp && resp.data ? (resp.data.total_plugins || 0) : 0;
                return 'Sync completed! ' + count + ' plugins, ' + total + ' active.';
            }
        );

        const $ecommerceConsent = $('#ecommerce-consent');
        const $ecommerceBtn = $('#sync-ecommerce-btn');
        const $ecommerceStatus = $('#ecommerce-sync-status');
        gateConsent($ecommerceConsent, $ecommerceBtn, $ecommerceStatus);
        bindSync(
            $ecommerceBtn,
            $ecommerceStatus,
            'w91099ch_sync_ecommerce_plugins',
            'ecommerce plugin data',
            'Syncing ecommerce data...',
            function(resp) {
                const count = resp && resp.data ? (resp.data.synced_count || 0) : 0;
                const total = resp && resp.data ? (resp.data.total_plugins || 0) : 0;
                return 'Sync completed! ' + count + ' plugins, ' + total + ' active.';
            }
        );

        const $walletConsent = $('#wallet-payout-consent');
        const $walletBtn = $('#sync-wallet-payout-btn');
        const $walletStatus = $('#wallet-payout-sync-status');
        gateConsent($walletConsent, $walletBtn, $walletStatus);
        bindSync(
            $walletBtn,
            $walletStatus,
            'w91099ch_sync_wallet_payout_plugins',
            'wallet/payout plugin data',
            'Syncing payout data...',
            function(resp) {
                const count = resp && resp.data ? (resp.data.synced_count || 0) : 0;
                const wallets = resp && resp.data ? (resp.data.total_wallets || 0) : 0;
                return 'Sync completed! ' + count + ' plugins, ' + wallets + ' wallets.';
            }
        );
    })();

    function inviteTeamMembers(options) {
        const opts = options || {};
        const rolesFilter = Array.isArray(opts.roles) ? opts.roles.map(String) : [];

        return new Promise(function(resolve, reject) {
            const users = [];

            function sendInviteRequest() {
                if (!users.length) {
                    reject(new Error('No users available to invite.'));
                    return;
                }

                $.ajax({
                    url: window.w91099chConnector.ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'w91099ch_invite_team_members',
                        nonce: window.w91099chConnector.nonce,
                        users: JSON.stringify(users)
                    },
                    success: function(resp) {
                        if (!resp || !resp.success) {
                            const msg = (resp && resp.data && resp.data.message) ? resp.data.message : ((resp && resp.data) ? String(resp.data) : 'Invite failed');
                            reject(new Error(msg));
                            return;
                        }
                        resolve(resp.data);
                    },
                    error: function(xhr) {
                        let msg = (xhr && xhr.responseText) ? xhr.responseText : 'Request failed';
                        try {
                            const parsed = JSON.parse(msg);
                            if (parsed && (parsed.data || parsed.message)) {
                                msg = parsed.data || parsed.message;
                            }
                        } catch (e) {
                        }
                        reject(new Error(String(msg)));
                    }
                });
            }

            function fetchUsersPage(offset) {
                const safeOffset = parseInt(offset, 10) || 0;

                $.ajax({
                    url: window.w91099chConnector.ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'w91099ch_get_all_users',
                        nonce: window.w91099chConnector.nonce,
                        limit: USERS_PAGE_LIMIT,
                        offset: safeOffset
                    },
                    success: function(response) {
                        if (!response || !response.success || !response.data) {
                            const msg = (response && response.data) ? String(response.data) : 'Failed to load users';
                            reject(new Error(msg));
                            return;
                        }

                        const pageUsers = response.data.users || [];
                        const total = (response.data.total !== undefined) ? parseInt(response.data.total, 10) : 0;

                        pageUsers.forEach(function(u) {
                            const email = String((u && (u.email || u.user_email)) ? (u.email || u.user_email) : '').trim();
                            const role = String((u && u.role) ? u.role : '').trim();
                            if (!email) return;
                            users.push({ email: email, role: role || 'VIEWER' });
                        });

                        const nextOffset = safeOffset + pageUsers.length;
                        if (pageUsers.length === USERS_PAGE_LIMIT && total && nextOffset < total) {
                            fetchUsersPage(nextOffset);
                            return;
                        }

                        sendInviteRequest();
                    },
                    error: function(xhr) {
                        let msg = (xhr && xhr.responseText) ? xhr.responseText : 'Request failed';
                        try {
                            const parsed = JSON.parse(msg);
                            if (parsed && (parsed.data || parsed.message)) {
                                msg = parsed.data || parsed.message;
                            }
                        } catch (e) {
                        }
                        reject(new Error(String(msg)));
                    }
                });
            }

            fetchUsersPage(0);
        });
    }
    
    const USERS_PAGE_LIMIT = 20;

    function renderAllUsersTable(users, opts) {
        const options = opts || {};
        const append = !!options.append;
        const $tbody = $('#all-users-table-body');
        if (!$tbody.length) return;

        if (!append) {
            $tbody.empty();
        }

        if (!users || !users.length) {
            if (!append) {
                $tbody.html('<tr><td colspan="4" class="py-8 text-center text-gray-500">No users found.</td></tr>');
            }
            return;
        }

        let rowsHtml = '';
        users.forEach(function(u) {
            const username = escapeHtml((u && (u.username || u.user_login)) ? (u.username || u.user_login) : '');
            const name = escapeHtml((u && u.display_name) ? u.display_name : '');
            const email = escapeHtml((u && (u.email || u.user_email)) ? (u.email || u.user_email) : '');
            const role = escapeHtml((u && u.role) ? u.role : '');

            rowsHtml += '<tr>'
                + '<td class="whitespace-nowrap">' + (username || '-') + '</td>'
                + '<td class="whitespace-nowrap">' + (name || '-') + '</td>'
                + '<td class="whitespace-nowrap">' + (email || '-') + '</td>'
                + '<td class="whitespace-nowrap">' + (role || '-') + '</td>'
                + '</tr>';
        });

        $tbody.append(rowsHtml);
    }

    function setAllUsersLoading() {
        const $tbody = $('#all-users-table-body');
        if (!$tbody.length) return;
        $tbody.html('<tr><td colspan="4" class="py-8 text-center text-gray-500">Loading users...</td></tr>');
    }

    function setAllUsersError(msg) {
        const $tbody = $('#all-users-table-body');
        if (!$tbody.length) return;
        $tbody.html('<tr><td colspan="4" class="py-8 text-center text-red-600">' + escapeHtml(msg || 'Failed to load users') + '</td></tr>');
    }

    function loadAllUsersPage(offset, append) {
        const safeOffset = parseInt(offset, 10) || 0;
        const $total = $('#all-users-total');

        if (!append) {
            setAllUsersLoading();
        }

        $.ajax({
            url: window.w91099chConnector.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'w91099ch_get_all_users',
                nonce: window.w91099chConnector.nonce,
                limit: USERS_PAGE_LIMIT,
                offset: safeOffset
            },
            success: function(response) {
                if (!response || !response.success || !response.data) {
                    let msg = 'Failed to load users';
                    if (response && response.data !== undefined && response.data !== null) {
                        if (typeof response.data === 'string') {
                            msg = response.data;
                        } else if (typeof response.data === 'object' && response.data.message) {
                            msg = response.data.message;
                        } else if (typeof response.data === 'object') {
                            msg = JSON.stringify(response.data);
                        }
                    }
                    setAllUsersError(msg);
                    return;
                }

                const users = response.data.users || [];
                const total = (response.data.total !== undefined) ? parseInt(response.data.total, 10) : 0;

                if ($total.length && !Number.isNaN(total)) {
                    $total.text(String(total));
                }

                renderAllUsersTable(users, { append: !!append });

                const nextOffset = safeOffset + users.length;
                if (users.length === USERS_PAGE_LIMIT && total && nextOffset < total) {
                    setTimeout(function() {
                        loadAllUsersPage(nextOffset, true);
                    }, 0);
                }
            },
            error: function(xhr) {
                const msg = (xhr && xhr.statusText) ? xhr.statusText : 'Error loading users';
                setAllUsersError(msg);
            }
        });
    }

    $('#view-all-users').off('click.allUsers').on('click.allUsers', function() {
        const $display = $('#all-users-display');
        if (!$display.length) return;
        $display.removeClass('hidden');
        $display.data('loaded', 1);
        loadAllUsersPage(0, false);
    });

    (function() {
        const $display = $('#all-users-display');
        if (!$display.length) return;
        $display.removeClass('hidden');
        if (!$display.data('loaded')) {
            $display.data('loaded', 1);
            loadAllUsersPage(0, false);
        }
    })();
    
    // Helper function to escape HTML
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Load affiliates with payout data
    let excludedAffiliateIds = {};

    let currentAffiliatePluginSlug = '';
    let currentAffiliatesTotalCount = 0;
    let currentAffiliatesList = [];

    function safeParseInt(value, fallback) {
        const n = parseInt(String(value), 10);
        return Number.isFinite(n) ? n : (fallback || 0);
    }

    function getExcludedCountForAffiliates(list) {
        if (!Array.isArray(list) || !list.length) return 0;
        let excluded = 0;
        list.forEach(function(affiliate) {
            const id = String((affiliate && (affiliate.id || affiliate.affiliate_id)) || '').trim();
            if (id && excludedAffiliateIds && excludedAffiliateIds[id]) {
                excluded++;
            }
        });
        return excluded;
    }

    function refreshAffiliatesSyncButtonCount() {
        const total = safeParseInt(currentAffiliatesTotalCount, Array.isArray(currentAffiliatesList) ? currentAffiliatesList.length : 0);
        const excluded = getExcludedCountForAffiliates(currentAffiliatesList);
        const included = Math.max(0, total - excluded);
        updateAffiliatesSyncButtonCount(currentAffiliatePluginSlug, included);
    }

    function normalizeExcludedIds(ids) {
        const out = {};
        if (!ids) return out;
        if (Array.isArray(ids)) {
            ids.forEach(function(v) {
                const key = String(v || '').trim();
                if (key) out[key] = true;
            });
            return out;
        }
        if (typeof ids === 'object') {
            Object.keys(ids).forEach(function(k) {
                const key = String(k || '').trim();
                if (key) out[key] = true;
            });
        }
        return out;
    }

    function excludedIdsToArray() {
        return Object.keys(excludedAffiliateIds || {}).filter(function(k) { return !!k; });
    }

    function loadExcludedAffiliates() {
        if (typeof window.w91099chConnector === 'undefined' || !window.w91099chConnector || !window.w91099chConnector.ajaxurl) {
            excludedAffiliateIds = {};
            return $.Deferred().resolve([]).promise();
        }

        return $.ajax({
            url: window.w91099chConnector.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'w91099ch_get_excluded_affiliates',
                nonce: window.w91099chConnector.nonce
            }
        }).then(function(resp) {
            const ids = resp && resp.success && resp.data && Array.isArray(resp.data.excluded_ids)
                ? resp.data.excluded_ids
                : [];
            excludedAffiliateIds = normalizeExcludedIds(ids);
            return ids;
        }, function() {
            excludedAffiliateIds = {};
            return [];
        });
    }

    function saveExcludedAffiliates() {
        if (typeof window.w91099chConnector === 'undefined' || !window.w91099chConnector || !window.w91099chConnector.ajaxurl) {
            return;
        }

        $.ajax({
            url: window.w91099chConnector.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'w91099ch_set_excluded_affiliates',
                nonce: window.w91099chConnector.nonce,
                excluded_ids: excludedIdsToArray()
            }
        });
    }

    function loadAffiliates(pluginSlug) {
        const $tableBody = $('#affiliates-table-body');
        const $stats = $('#affiliates-stats');
        const $loadMore = $('#load-more-affiliates');
        const $affiliatesDisplay = $('#affiliates-display');

        const showUiError = function(msg) {
            const safeMsg = escapeHtml(String(msg || 'Request failed. Please retry.'));
            $tableBody.html('<tr><td colspan="6" style="padding: 20px; text-align: center; color: #dc3545;">' + safeMsg + '</td></tr>');
            $loadMore.hide();
        };

        try {
            // Reset scroll position
            if ($affiliatesDisplay.length) {
                $affiliatesDisplay.find('.affiliates-table-container').scrollTop(0);
            } else {
                $('.mp-scroll, .affiliates-table-container').first().scrollTop(0);
            }

            $tableBody.html('<tr><td colspan="6" style="padding: 20px; text-align: center; color: #666;">Loading affiliates with payout data...</td></tr>');
            $loadMore.hide();

            // Reset payout summary
            $('#total-payouts-amount').text('$0.00');
            $('#affiliates-with-payouts').text('0');
            $('#avg-payout').text('$0.00');

            if (typeof window.w91099chConnector === 'undefined' || !window.w91099chConnector || !window.w91099chConnector.ajaxurl || !window.w91099chConnector.nonce) {
                showUiError('Missing AJAX configuration (ajaxurl/nonce). Please reload the admin page.');
                return;
            }

            // If "All Detected Plugins" is selected (empty pluginSlug), use a different action
            const action = pluginSlug ? 'w91099ch_get_plugin_affiliates' : 'w91099ch_get_all_affiliates';
            const data = {
                action: action,
                nonce: window.w91099chConnector.nonce,
                limit: 10000,
                offset: 0,
                include_payouts: true // New parameter to include payout data
            };

            // Add plugin_slug only if a specific plugin is selected
            if (pluginSlug) {
                data.plugin_slug = pluginSlug;
            }

            $.ajax({
                url: window.w91099chConnector.ajaxurl,
                type: 'POST',
                dataType: 'json',
                timeout: 20000,
                data: data,
                success: function(response) {
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch (e) {
                        response = null;
                    }
                }

                if (response && response.success) {
                    const affiliatesList = (response.data && Array.isArray(response.data.affiliates)) ? response.data.affiliates : [];
                    const totalCount = (response.data && response.data.total_count !== undefined)
                        ? safeParseInt(response.data.total_count, affiliatesList.length)
                        : affiliatesList.length;

                    currentAffiliatePluginSlug = String(pluginSlug || '').trim();
                    currentAffiliatesTotalCount = totalCount;
                    currentAffiliatesList = affiliatesList;

                    const excludedInThisView = getExcludedCountForAffiliates(affiliatesList);
                    const includedCount = Math.max(0, totalCount - excludedInThisView);

                    if ($('#affiliate-count').length) {
                        $('#affiliate-count').text(String(includedCount));
                    }
                    updateAffiliatesSyncButtonCount(pluginSlug, includedCount);

                    if (affiliatesList && affiliatesList.length > 0) {
                        displayAffiliatesWithPayouts(affiliatesList, pluginSlug, (response.data.payout_summary || response.data.summary));
                        
                        // Update stats text based on selection
                        if (pluginSlug) {
                            $stats.text('Total: ' + includedCount + ' affiliates/vendors');
                        } else {
                            $stats.text('Total: ' + includedCount + ' affiliates/vendors across all plugins');
                        }
                        
                        $loadMore.hide();
                    } else {
                        // No affiliates found
                        $tableBody.html('<tr><td colspan="6" style="padding: 20px; text-align: center; color: #666;">No affiliates/vendors found for this plugin.</td></tr>');
                        $stats.text('Total: 0 affiliates/vendors');
                        $loadMore.hide();
                        updateAffiliatesSyncButtonCount(pluginSlug, 0);
                    }
                } else {
                    // Handle error response properly - response.data might be an object
                    let msg = 'Request failed. Please retry.';
                    if (response && response.data !== undefined && response.data !== null) {
                        if (typeof response.data === 'string') {
                            msg = response.data;
                        } else if (typeof response.data === 'object' && response.data.message) {
                            msg = response.data.message;
                        } else if (typeof response.data === 'object') {
                            msg = JSON.stringify(response.data);
                        }
                    }
                    $tableBody.html('<tr><td colspan="6" style="padding: 20px; text-align: center; color: #dc3545;">Failed to load affiliates/vendors: ' + escapeHtml(msg) + '</td></tr>');
                }
                },
                error: function(xhr, textStatus) {
                    const status = (textStatus === 'timeout') ? 'Request timed out' : (textStatus || (xhr && xhr.statusText) || 'Request failed');
                    showUiError('Error loading affiliates/vendors: ' + status);
                }
            });
        } catch (err) {
            showUiError('Error loading affiliates/vendors: ' + (err && err.message ? err.message : String(err)));
        }
    }

    // Display affiliates with payout data
    function displayAffiliatesWithPayouts(affiliates, pluginSlug, payoutSummary) {
        const $tableBody = $('#affiliates-table-body');
        const $affiliatesTableContainer = $('.affiliates-table-container');
        
        if (!affiliates || affiliates.length === 0) {
            $tableBody.html('<tr><td colspan="6" style="padding: 20px; text-align: center; color: #666;">No affiliates/vendors found</td></tr>');
            return;
        }
        
        let html = '';
        let totalPayouts = 0;
        let affiliatesWithPayouts = 0;
        
        affiliates.forEach(affiliate => {
            const isExcluded = !!excludedAffiliateIds[String(affiliate.id || '')];
            // Get payout amount from affiliate data
            const payoutAmount = affiliate.payout_amount || affiliate.total_payouts || affiliate.commission || affiliate.amount || 0;
            const formattedAmount = formatCurrency(payoutAmount);
            const amountClass = payoutAmount > 0 ? 'amount-positive' : 'amount-zero';
            const tooltipText = getPayoutTooltip(affiliate);
            
            // Update totals
            totalPayouts += parseFloat(payoutAmount);
            if (payoutAmount > 0) {
                affiliatesWithPayouts++;
            }
            
            // Highlight row if payout exceeds the limit
            const limitEnabled = window.w91099chConnector && (window.w91099chConnector.payment_limit_enabled === true || window.w91099chConnector.payment_limit_enabled === '1');
            const limitAmount = window.w91099chConnector ? parseFloat(window.w91099chConnector.payment_limit_amount) : 0;
            
            // Parse payout amount correctly (handling potential strings with commas)
            let parsedPayout = 0;
            if (typeof payoutAmount === 'number') {
                parsedPayout = payoutAmount;
            } else if (typeof payoutAmount === 'string') {
                parsedPayout = parseFloat(payoutAmount.replace(/[^0-9.-]+/g, ''));
            }
            if (isNaN(parsedPayout)) parsedPayout = 0;

            const isExceeded = limitEnabled && limitAmount > 0 && parsedPayout >= limitAmount;
            const rowStyle = isExceeded ? 'background-color: #fee2e2 !important; border-left: 4px solid #ef4444;' : (isExcluded ? ' opacity:0.55; background:#f8f9fa;' : '');

            html += `
                <tr style="border-bottom: 1px solid #dee2e6;${rowStyle}">
                    <td style="padding: 8px; text-align: center;">
                        <input type="checkbox" class="affiliate-exclude-toggle" data-affiliate-id="${escapeHtml(affiliate.id || '')}" ${excludedAffiliateIds[String(affiliate.id || '')] ? 'checked' : ''} />
                    </td>
                    <td style="padding: 8px; font-family: monospace; font-size: 11px;">${escapeHtml(affiliate.id || 'N/A')}</td>
                    <td style="padding: 8px;">${escapeHtml(affiliate.name || 'N/A')}</td>
                    <td style="padding: 8px;">${escapeHtml(affiliate.email || 'N/A')}</td>
                    <td style="padding: 8px;" class="amount-cell ${amountClass} amount-tooltip" data-tooltip="${tooltipText}">
                        ${formattedAmount}
                    </td>
                    <td style="padding: 8px;">
                        <span class="status-badge" style="padding: 2px 6px; background: ${affiliate.status === 'active' ? '#28a745' : '#6c757d'}; color: white; border-radius: 10px; font-size: 10px; font-weight: 600;">
                            ${(affiliate.status || 'active').toUpperCase()}
                        </span>
                    </td>
                </tr>
            `;
        });
        
        $tableBody.html(html);
        
        // Update payout summary
        const avgPayout = affiliatesWithPayouts > 0 ? (totalPayouts / affiliatesWithPayouts) : 0;
        
        $('#total-payouts-amount').text('$' + formatNumber(totalPayouts));
        $('#affiliates-with-payouts').text(affiliatesWithPayouts);
        $('#avg-payout').text('$' + formatNumber(avgPayout));
        
        // If payoutSummary is provided, use it
        if (payoutSummary) {
            $('#total-payouts-amount').text('$' + formatNumber(payoutSummary.total_payouts || totalPayouts));
            $('#affiliates-with-payouts').text(payoutSummary.affiliates_with_payouts || affiliatesWithPayouts);
            $('#avg-payout').text('$' + formatNumber(payoutSummary.avg_payout || avgPayout));
        }
        
        // Set fixed height for table container to prevent card resizing
        if ($affiliatesTableContainer.length) {
            const maxHeight = Math.min(affiliates.length * 40 + 50, 300); // Max 300px or based on content
            $affiliatesTableContainer.css('max-height', maxHeight + 'px');
        }
    }

    // Helper functions
    function formatCurrency(amount) {
        if (typeof amount === 'string') {
            amount = parseFloat(amount.replace(/[^0-9.-]+/g, ''));
        } else if (typeof amount === 'object' && amount !== null) {
            // Handle case where amount might be an object
            amount = parseFloat(String(amount).replace(/[^0-9.-]+/g, ''));
        }
        if (isNaN(amount)) amount = 0;
        return '$' + formatNumber(amount);
    }
    
    function formatNumber(num) {
        if (typeof num !== 'number') {
            num = parseFloat(String(num).replace(/[^0-9.-]+/g, ''));
        }
        if (isNaN(num)) num = 0;
        return num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
    
    function getPayoutTooltip(affiliate) {
        const amount = affiliate.payout_amount || affiliate.total_payouts || affiliate.commission || affiliate.amount || 0;
        const lastPayout = affiliate.last_payout_date || 'N/A';
        const transactionCount = affiliate.transaction_count || 'N/A';
        const payoutType = affiliate.payout_type || 'Commission';
        
        return `Total: $${formatNumber(amount)}\nLast Payout: ${lastPayout}\nTransactions: ${transactionCount}\nType: ${payoutType}`;
    }
    
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Initialize affiliate functionality with payout data
    function initializeAffiliateFunctionality() {
        // Always start with 'All Detected Plugins' selected
        const initialPlugin = '';
        setTimeout(() => {
            loadExcludedAffiliates().always(function() {
                loadAffiliates(initialPlugin);
            });
        }, 500);
        
        // Update the plugin select change handler
        $('#affiliate-plugin-select').on('change', function() {
            const pluginSlug = $(this).val();
            loadAffiliates(pluginSlug);
        });
    }

    // Initialize when page loads
    if (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector.is_connected) {
        initializeAffiliateFunctionality();
    }

    $(document).off('change.affiliateExclude').on('change.affiliateExclude', '.affiliate-exclude-toggle', function() {
        const id = String($(this).data('affiliate-id') || '').trim();
        if (!id) return;

        const $row = $(this).closest('tr');

        if (this.checked) {
            excludedAffiliateIds[id] = true;
            if ($row.length) {
                $row.css({ opacity: 0.55, background: '#f8f9fa' });
            }
        } else {
            delete excludedAffiliateIds[id];
            if ($row.length) {
                $row.css({ opacity: '', background: '' });
            }
        }

        saveExcludedAffiliates();
        refreshAffiliatesSyncButtonCount();
    });

    // Form Plugin Sync Button Handler
    $('#sync-form-plugins-btn').off('click.formSync').on('click.formSync', function() {
        // Check if connected to Mypowerly
        if (!checkMypowerlyConnection()) {
            return;
        }
        
        if (!$('#form-plugins-consent').is(':checked')) {
            window.alert('Please check the consent checkbox to enable sending form plugin data to the external service.');
            return;
        }
        if (!confirmSendToMypowerly('form plugin data')) {
            return;
        }
        window.alert('Form plugin sync functionality will be implemented in a future update.');
    });

    // Contractor Sync Button Handler
    $('#sync-contractor-btn').off('click.contractorSync').on('click.contractorSync', function() {
        // Check if connected to Mypowerly
        if (!checkMypowerlyConnection()) {
            return;
        }
        
        if (!$('#contractor-consent').is(':checked')) {
            window.alert('Please check the consent checkbox to enable sending membership/subscription plugin data to the external service.');
            return;
        }
        if (!confirmSendToMypowerly('membership/subscription plugin data')) {
            return;
        }
        window.alert('Membership/subscription plugin sync functionality will be implemented in a future update.');
    });

    // Freelancer/Contractor Sync Button Handler
    $('#sync-freelancer-contractor-btn').off('click.freelancerSync').on('click.freelancerSync', function() {
        // Check if connected to Mypowerly
        if (!checkMypowerlyConnection()) {
            return;
        }
        
        if (!$('#freelancer-contractor-consent').is(':checked')) {
            window.alert('Please check the consent checkbox to enable sending freelancer/contractor plugin data to the external service.');
            return;
        }
        if (!confirmSendToMypowerly('freelancer/contractor plugin data')) {
            return;
        }
        window.alert('Freelancer/contractor plugin sync functionality will be implemented in a future update.');
    });

    // Accounting/Bookkeeping Sync Button Handler
    $('#sync-accounting-bookkeeping-btn').off('click.accountingSync').on('click.accountingSync', function() {
        // Check if connected to Mypowerly
        if (!checkMypowerlyConnection()) {
            return;
        }
        
        if (!$('#accounting-bookkeeping-consent').is(':checked')) {
            window.alert('Please check the consent checkbox to enable sending accounting/bookkeeping plugin data to the external service.');
            return;
        }
        if (!confirmSendToMypowerly('accounting/bookkeeping plugin data')) {
            return;
        }
        window.alert('Accounting/bookkeeping plugin sync functionality will be implemented in a future update.');
    });

    // Wallet/Payout Sync Button Handler
    $('#sync-wallet-payout-btn').off('click.walletSync').on('click.walletSync', function() {
        // Check if connected to Mypowerly
        if (!checkMypowerlyConnection()) {
            return;
        }
        
        if (!$('#wallet-payout-consent').is(':checked')) {
            window.alert('Please check the consent checkbox to enable sending wallet/payout plugin data to the external service.');
            return;
        }
        if (!confirmSendToMypowerly('wallet/payout plugin data')) {
            return;
        }
        window.alert('Wallet/payout plugin sync functionality will be implemented in a future update.');
    });

    // Ecommerce Sync Button Handler - handled by bindCardSyncButtons() above

    function initMockDataSyncModules() {
        const $cards = $('.w91099ch-mock-sync-card');
        if (!$cards.length) {
            return;
        }

        function getPayload($card) {
            const moduleId = String($card.data('module-id') || '');
            const title = $.trim($card.find('h3').first().text());
            const payload = {
                module: moduleId,
                title: title,
                selected: {}
            };
            let selectedCount = 0;

            $card.find('.w91099ch-mock-sync-item:checked').each(function() {
                const group = String($(this).data('group') || '');
                const item = String($(this).data('item') || '');

                if (!group || !item) {
                    return;
                }

                if (!payload.selected[group]) {
                    payload.selected[group] = [];
                }

                payload.selected[group].push(item);
                selectedCount++;
            });

            return {
                payload: payload,
                count: selectedCount
            };
        }

        function updateCardState($card) {
            const hasConsent = $card.find('.w91099ch-mock-sync-consent').is(':checked');
            const $button = $card.find('.w91099ch-mock-sync-button');
            const $status = $card.find('.w91099ch-mock-sync-status');
            const payloadInfo = getPayload($card);

            $card.find('.w91099ch-mock-sync-option').each(function() {
                $(this).toggleClass('is-excluded', !$(this).find('.w91099ch-mock-sync-item').is(':checked'));
            });

            $card.find('.w91099ch-mock-payload-preview').text(JSON.stringify(payloadInfo.payload, null, 2));
            $card.find('.w91099ch-mock-payload-count').text(payloadInfo.count + ' selected');

            $button.prop('disabled', !hasConsent);
            $button.toggleClass('opacity-60 cursor-not-allowed', !hasConsent);

            if (!hasConsent) {
                $status.removeClass('is-success is-error is-loading').text('Check consent to enable sync');
            } else if (payloadInfo.count === 0) {
                $status.removeClass('is-success is-loading').addClass('is-error').text('Select at least one item to include in the mock payload');
            } else {
                $status.removeClass('is-success is-error is-loading').text('Ready for mock sync');
            }
        }

        function removeMockModal() {
            $('#w91099ch-mock-sync-modal').remove();
        }

        function showMockConfirmation($card, payloadInfo) {
            removeMockModal();

            const confirmMessage = String($card.data('confirm-message') || 'Are you sure you want to sync selected data?');
            const payloadJson = JSON.stringify(payloadInfo.payload, null, 2);
            const modalHtml = ''
                + '<div id="w91099ch-mock-sync-modal" class="w91099ch-mock-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="w91099ch-mock-modal-title">'
                + '  <div class="w91099ch-mock-modal">'
                + '    <div class="w91099ch-mock-modal-header">'
                + '      <div class="w91099ch-mock-modal-icon"><i class="fas fa-circle-question" aria-hidden="true"></i></div>'
                + '      <div>'
                + '        <h3 id="w91099ch-mock-modal-title">Confirm Mock Sync</h3>'
                + '        <p>' + escapeHtml(confirmMessage) + '</p>'
                + '      </div>'
                + '    </div>'
                + '    <div class="w91099ch-mock-modal-body">'
                + '      <div class="w91099ch-mock-modal-note">No API call will be made. Only the selected checkboxes below are included.</div>'
                + '      <pre>' + escapeHtml(payloadJson) + '</pre>'
                + '    </div>'
                + '    <div class="w91099ch-mock-modal-actions">'
                + '      <button type="button" class="mp-btn-secondary" id="w91099ch-mock-sync-cancel">Cancel</button>'
                + '      <button type="button" class="mp-btn-primary" id="w91099ch-mock-sync-ok"><i class="fas fa-check" aria-hidden="true"></i>OK</button>'
                + '    </div>'
                + '  </div>'
                + '</div>';

            $('body').append(modalHtml);

            $('#w91099ch-mock-sync-cancel').on('click', function() {
                removeMockModal();
                $card.find('.w91099ch-mock-sync-status').removeClass('is-success is-error is-loading').text('Mock sync cancelled');
                $card.find('.w91099ch-mock-sync-button').prop('disabled', false).removeClass('opacity-60 cursor-not-allowed');
            });

            $('#w91099ch-mock-sync-ok').on('click', function() {
                const $button = $card.find('.w91099ch-mock-sync-button');
                const $status = $card.find('.w91099ch-mock-sync-status');

                removeMockModal();
                $button.prop('disabled', true).addClass('opacity-60 cursor-not-allowed');
                $status.removeClass('is-success is-error').addClass('is-loading').text('Finalizing mock sync...');

                window.setTimeout(function() {
                    $button.prop('disabled', false).removeClass('opacity-60 cursor-not-allowed');

                    if (payloadInfo.count === 0) {
                        $status.removeClass('is-success is-loading').addClass('is-error').text('Mock failure: no selected data was included in the payload');
                        return;
                    }

                    $status.removeClass('is-error is-loading').addClass('is-success').text('Mock success: selected data payload prepared');

                    if (window.w91099chConsole && typeof window.w91099chConsole.log === 'function') {
                        window.w91099chConsole.log('Mock sync payload:', payloadInfo.payload);
                    }
                }, 700);
            });
        }

        $cards.each(function() {
            updateCardState($(this));
        });

        $(document).off('change.mockDataSync').on('change.mockDataSync', '.w91099ch-mock-sync-card input[type="checkbox"]', function() {
            updateCardState($(this).closest('.w91099ch-mock-sync-card'));
        });

        $(document).off('click.mockDataSync').on('click.mockDataSync', '.w91099ch-mock-sync-button', function() {
            const $button = $(this);
            const $card = $button.closest('.w91099ch-mock-sync-card');
            const $status = $card.find('.w91099ch-mock-sync-status');

            if (!$card.find('.w91099ch-mock-sync-consent').is(':checked')) {
                updateCardState($card);
                return;
            }

            const payloadInfo = getPayload($card);

            $button.prop('disabled', true).addClass('opacity-60 cursor-not-allowed');
            $status.removeClass('is-success is-error').addClass('is-loading').text('Preparing selected mock payload...');

            window.setTimeout(function() {
                $status.removeClass('is-loading').text('Waiting for confirmation');
                showMockConfirmation($card, payloadInfo);
            }, 650);
        });

        $(document).off('click.mockDataSyncBackdrop').on('click.mockDataSyncBackdrop', '#w91099ch-mock-sync-modal', function(event) {
            if (event.target === this) {
                $('#w91099ch-mock-sync-cancel').trigger('click');
            }
        });
    }

    initMockDataSyncModules();

    // Load ecommerce plugins data on page load
    function loadEcommercePlugins() {
        const $tableBody = $('#ecommerce-table-body');
        const $stats = $('#ecommerce-stats');
        
        if (!$tableBody.length) return;
        
        $tableBody.html('<tr><td colspan="4" style="padding: 20px; text-align: center; color: #666;">Loading ecommerce plugins...</td></tr>');
        
        const ajaxUrl = (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector && window.w91099chConnector.ajaxurl)
            ? window.w91099chConnector.ajaxurl
            : (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '');
        
        const ajaxNonce = (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector && window.w91099chConnector.nonce)
            ? window.w91099chConnector.nonce
            : '';
        
        if (!ajaxUrl || !ajaxNonce) {
            $tableBody.html('<tr><td colspan="4" style="padding: 20px; text-align: center; color: #dc3545;">Missing AJAX configuration. Please reload the page.</td></tr>');
            return;
        }
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'w91099ch_get_ecommerce_plugins',
                nonce: ajaxNonce
            },
            success: function(response) {
                if (response && response.success && response.data && response.data.plugins) {
                    displayEcommercePlugins(response.data.plugins);
                    if ($stats.length) {
                        const count = Object.keys(response.data.plugins).length;
                        $stats.text('Detected ' + count + ' ecommerce plugin' + (count !== 1 ? 's' : ''));
                    }
                } else {
                    $tableBody.html('<tr><td colspan="4" style="padding: 20px; text-align: center; color: #666;">No ecommerce plugins detected</td></tr>');
                    if ($stats.length) {
                        $stats.text('No ecommerce plugins detected');
                    }
                }
            },
            error: function(xhr, status, error) {
                const msg = (xhr && xhr.responseText) ? xhr.responseText : (error || 'Failed to load ecommerce plugins');
                $tableBody.html('<tr><td colspan="4" style="padding: 20px; text-align: center; color: #dc3545;">Error: ' + escapeHtml(msg) + '</td></tr>');
            }
        });
    }
    
    function displayEcommercePlugins(plugins) {
        const $tableBody = $('#ecommerce-table-body');
        if (!$tableBody.length) return;
        
        if (!plugins || Object.keys(plugins).length === 0) {
            $tableBody.html('<tr><td colspan="4" style="padding: 20px; text-align: center; color: #666;">No ecommerce plugins detected</td></tr>');
            return;
        }
        
        let html = '';
        Object.keys(plugins).forEach(function(slug) {
            const plugin = plugins[slug];
            const name = escapeHtml(plugin.name || slug);
            const version = escapeHtml(plugin.version || 'N/A');
            const type = getEcommercePluginType(slug);
            const isActive = plugin.active ? true : false;
            const statusClass = isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
            const statusText = isActive ? 'ACTIVE' : 'INACTIVE';
            
            html += '<tr style="border-bottom: 1px solid #dee2e6;">'
                + '<td style="padding: 8px;">' + name + '</td>'
                + '<td style="padding: 8px;">' + version + '</td>'
                + '<td style="padding: 8px;">' + type + '</td>'
                + '<td style="padding: 8px;">'
                + '<span class="status-badge ' + statusClass + '" style="padding: 2px 6px; border-radius: 10px; font-size: 10px; font-weight: 600;">'
                + statusText
                + '</span>'
                + '</td>'
                + '</tr>';
        });
        
        $tableBody.html(html);
    }
    
    function getEcommercePluginType(slug) {
        const types = {
            'woocommerce': 'Store Platform',
            'dokan': 'Marketplace',
            'wcfm': 'Marketplace',
            'stripe': 'Payment Gateway',
            'paypal': 'Payment Gateway'
        };
        return types[slug] || 'Ecommerce';
    }
    
    // Initialize ecommerce plugins on page load
    if (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector.is_connected) {
        setTimeout(function() {
            loadEcommercePlugins();
        }, 800);
    }

});
