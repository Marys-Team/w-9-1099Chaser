(function() {
    "use strict";
    const w91099chDebugEnabled = true; // Force enable debug for troubleshooting

    const hasW91099chConnector = (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector);

    window.w91099chConsole = (window.w91099chConsole && typeof window.w91099chConsole.log === 'function')
        ? window.w91099chConsole
        : (w91099chDebugEnabled && typeof window.console !== 'undefined'
            ? window.console
            : { log: function() {}, error: function() {}, warn: function() {} });

    const w91099chConsole = window.w91099chConsole;
    if (typeof window.jQuery !== 'function') {
        // If jQuery isn't available, any use of "$" will fail.
        // Log clearly so you can see the root cause in console.
        // eslint-disable-next-line no-console
        w91099chConsole.error('w9-1099-chaser-admin.js: jQuery is not loaded.');
        return;
    }

    window.jQuery(function($) {
        let connecting = false;
        let workspaceFetchConfirmed = false;

        const escapeHtmlSafe = (typeof window.escapeHtml === 'function')
            ? window.escapeHtml
            : function(unsafe) {
                if (unsafe === null || unsafe === undefined) return '';
                return String(unsafe)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

        function ensureAdminConsent(onDone) {
            if (!hasW91099chConnector) {
                if (typeof onDone === 'function') onDone(false);
                return;
            }

            if (window.w91099chConnector.has_admin_consent) {
                if (typeof onDone === 'function') onDone(true);
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
                        if (typeof onDone === 'function') onDone(true);
                        return;
                    }
                    if (typeof onDone === 'function') onDone(false);
                },
                error: function() {
                    if (typeof onDone === 'function') onDone(false);
                }
            });
        }

        function normalizeWorkspacesResponseSafe(raw) {
            if (!raw) return [];
            if (Array.isArray(raw)) return raw;
            if (typeof raw === 'object' && Array.isArray(raw.results)) return raw.results;
            return [];
        }

        function getWorkspaceOptionMetaSafe(item) {
            if (!item || typeof item !== 'object') return { value: '', label: '' };
            const id = item.id ?? item.workspace_id ?? item.uuid ?? item.value;
            const label = item.name ?? item.workspace_name ?? item.title ?? item.label;
            return {
                value: String(id || '').trim(),
                label: String(label || id || '').trim()
            };
        }

        function loadWorkspaces() {
            const $workspaceSelect = $('#workspace-select');
            if (!$workspaceSelect.length || !hasW91099chConnector) {
                return;
            }

            if (!workspaceFetchConfirmed) {
                const ok = window.confirm('This will contact the external MyPowerly service (https://mypowerly.com) to load your workspace list. Do you want to continue?');
                if (!ok) {
                    return;
                }
                workspaceFetchConfirmed = true;
            }

            if (!window.w91099chConnector.has_admin_consent) {
                ensureAdminConsent(function(consentOk) {
                    if (!consentOk) {
                        $workspaceSelect.prop('disabled', true);
                        $workspaceSelect.html('<option value="">Consent could not be saved. Please refresh and try again.</option>');
                        return;
                    }
                    loadWorkspaces();
                });
                return;
            }

            $workspaceSelect.prop('disabled', true);
            $workspaceSelect.html('<option value="">Loading workspaces...</option>');

            $.ajax({
                url: window.w91099chConnector.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'w91099ch_get_workspaces',
                    nonce: window.w91099chConnector.nonce
                },
                success: function(response) {
                    if (!response || !response.success) {
                        const msg = (response && response.data) ? response.data : 'Failed to load workspaces';
                        $workspaceSelect.prop('disabled', true);
                        $workspaceSelect.html('<option value="">' + escapeHtmlSafe(String(msg)) + '</option>');
                        return;
                    }

                    const raw = (response.data && (response.data.workspaces ?? response.data)) ?? [];
                    const normalizer = (typeof window.normalizeWorkspacesResponse === 'function')
                        ? window.normalizeWorkspacesResponse
                        : normalizeWorkspacesResponseSafe;
                    const mapper = (typeof window.getWorkspaceOptionMeta === 'function')
                        ? window.getWorkspaceOptionMeta
                        : getWorkspaceOptionMetaSafe;

                    const options = normalizer(raw)
                        .map(mapper)
                        .filter(o => o && o.value && o.label);

                    if (!options.length) {
                        $workspaceSelect.prop('disabled', true);
                        $workspaceSelect.html('<option value="">No workspaces found</option>');
                        return;
                    }

                    $workspaceSelect.empty();
                    $workspaceSelect.append('<option value="">Select a workspace...</option>');
                    options.forEach(o => {
                        $workspaceSelect.append('<option value="' + escapeHtmlSafe(o.value) + '">' + escapeHtmlSafe(o.label) + '</option>');
                    });
                    $workspaceSelect.prop('disabled', false);

                    // Auto-select first workspace by default if nothing is currently selected
                    const currentVal = String($workspaceSelect.val() || '').trim();
                    if (!currentVal && options.length > 0) {
                        $workspaceSelect.val(options[0].value);
                    }
                },
                error: function(xhr) {
                    $workspaceSelect.prop('disabled', true);
                    $workspaceSelect.html('<option value="">Failed to load workspaces</option>');
                    // eslint-disable-next-line no-console
                    w91099chConsole.error('Failed to load workspaces:', xhr && xhr.responseText);
                }
            });
        }

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

            const emptyHtml = ''
                + '<div class="text-center py-8">'
                + '  <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">'
                + '    <i class="fa-solid fa-plug-circle-xmark text-2xl text-gray-400"></i>'
                + '  </div>'
                + '  <p class="text-gray-600 mb-2">No All plugins detected</p>'
                + '  <p class="text-sm text-gray-500">Install All plugins to start syncing data</p>'
                + '</div>';

            const buildPluginRowHtml = function(slug, plugin) {
                const name = plugin && plugin.name ? plugin.name : slug;
                const version = plugin && plugin.version ? plugin.version : '';
                const affiliateCount = (plugin && plugin.affiliate_count !== undefined && plugin.affiliate_count !== null)
                    ? plugin.affiliate_count
                    : 0;

                const versionHtml = version
                    ? ('<span>v' + escapeHtmlSafe(String(version)) + '</span><span class="w-1 h-1 bg-gray-300 rounded-full"></span>')
                    : '';

                const isAffiliateVendor = !!(plugin && plugin.detected);
                const tagHtml = isAffiliateVendor
                    ? '<span class="w-2 h-2 rounded-full bg-purple-500"></span>'
                    : '';

                const affiliateCountHtml = isAffiliateVendor
                    ? ('<span class="text-purple-700 font-semibold">' + escapeHtmlSafe(String(affiliateCount)) + ' affiliates/vendors</span>')
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
                    + '          <div class="font-semibold text-gray-800 text-sm">' + escapeHtmlSafe(String(name)) + '</div>'
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
            };

            if ($list.length) {
                if (!entries.length) {
                    $list.html(emptyHtml);
                } else {
                    $list.html(entries.map(([slug, plugin]) => buildPluginRowHtml(slug, plugin)).join(''));
                }
            } else if ($container.length) {
                $container.html('<div class="space-y-3" id="detected-plugins-list"></div>');
                updateDetectedPluginsUi(plugins, totalAffiliates);
            }

            const $select = $('#affiliate-plugin-select');
            if ($select.length) {
                const current = String($select.val() || '');

                let optionsHtml = '<option value="">All Affiliate Detected plugins</option>';
                entries
                    .filter(([_, plugin]) => !!(plugin && plugin.detected))
                    .forEach(([slug, plugin]) => {
                    const label = plugin && plugin.name ? plugin.name : slug;
                    const affiliateCount = (plugin && plugin.affiliate_count !== undefined && plugin.affiliate_count !== null)
                        ? plugin.affiliate_count
                        : 0;
                    optionsHtml += '<option value="' + escapeHtmlSafe(String(slug)) + '">' + escapeHtmlSafe(String(label)) + ' (' + escapeHtmlSafe(String(affiliateCount)) + ' affiliates/vendors)</option>';
                });

                $select.html(optionsHtml);

                const exists = current && entries.some(([slug]) => String(slug) === current);
                $select.val(exists ? current : '');
            }
        }

        if (typeof window.initEnhancedUI === 'function') {
            window.initEnhancedUI();
        }

        if (hasW91099chConnector && window.w91099chConnector.pending_credentials && typeof window.processCredentials === 'function') {
            window.processCredentials(window.w91099chConnector.pending_credentials);
        }

        if (typeof window.checkUrlCredentialProcessing === 'function') {
            window.checkUrlCredentialProcessing();
        }

        if (typeof window.loadCredentialsDisplay === 'function') {
            window.loadCredentialsDisplay();
        }

        if (hasW91099chConnector && window.w91099chConnector.is_connected) {
            if (typeof window.loadDetectedPlugins === 'function') {
                window.loadDetectedPlugins();
            }
            if (typeof window.setupPluginAffiliatesPreview === 'function') {
                window.setupPluginAffiliatesPreview();
            }
            if (typeof window.initializeAffiliateFunctionality === 'function') {
                window.initializeAffiliateFunctionality();
            }
            // Load workspaces only after an explicit user interaction with the workspace selector.
            // This prevents background/silent data transmission to the external service.
            $(document)
                .off('focus.w91099chConnectorWorkspaceLoad mousedown.w91099chConnectorWorkspaceLoad touchstart.w91099chConnectorWorkspaceLoad')
                .one('focus.w91099chConnectorWorkspaceLoad mousedown.w91099chConnectorWorkspaceLoad touchstart.w91099chConnectorWorkspaceLoad', '#workspace-select, .workspace-select', function() {
                    loadWorkspaces();
                });
        }

        $('#connect-mypowerly-admin').off('click.w91099chConnector').on('click.w91099chConnector', function() {
            if (!hasW91099chConnector || connecting) return;

            const startConnect = () => {
                connecting = true;

                const $button = $(this);
                const $progress = $('#connection-progress');
                const $logs = $('#progress-logs-content');

                $button.prop('disabled', true).text('Connecting...').addClass('loading');
                $progress.show();
                $logs.html('<div class="log-entry"> Preparing connection...</div>');

                $.ajax({
                    url: window.w91099chConnector.ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'w91099ch_initiate_connection',
                        nonce: window.w91099chConnector.nonce,
                        discount_code: String($('#mypowerly-discount-code').val() || '').replace(/\s+/g, '').slice(0, 32)
                    },
                    success: function(response) {
                        if (!response || !response.success) {
                            const msg = (response && response.data) ? response.data : 'Connection initialization failed';
                            $logs.append('<div class="log-entry"> ❌ ' + escapeHtmlSafe(String(msg)) + '</div>');
                            $button.prop('disabled', false).removeClass('loading');
                            connecting = false;
                            return;
                        }

                        $logs.append('<div class="log-entry"> Connection data prepared</div>');
                        $logs.append('<div class="log-entry"> Sending to MyPowerly...</div>');

                        fetch(response.data.api_url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(response.data.post_data)
                        })
                            .then(apiResponse => {
                                console.log('Raw API Response:', apiResponse);
                                console.log('Response status:', apiResponse.status);
                                console.log('Response redirected:', apiResponse.redirected);
                                
                                if (apiResponse.redirected) {
                                    $logs.append('<div class="log-entry"> Redirecting to MyPowerly...</div>');
                                    window.location.href = apiResponse.url;
                                    return null;
                                }
                                return apiResponse.json();
                            })
                            .then(apiData => {
                                console.log('Parsed API Data:', apiData);
                                if (!apiData) return;
                                
                                // Log the full response structure for debugging
                                $logs.append('<div class="log-entry"> 🔍 Full API Response: ' + JSON.stringify(apiData).substring(0, 200) + '...</div>');
                                
                                // Handle nested data structure
                                const payload = apiData && apiData.data ? apiData.data : apiData;
                                const status = (payload && payload.status) || apiData.status;
                                const loginUrl = (payload && payload.login_url) || apiData.login_url;
                                const registrationUrl = (payload && payload.registration_url) || apiData.registration_url;
                                const encryptedCredentials = (payload && payload.encrypted_credentials) || apiData.encrypted_credentials;
                                const authorizationCode = (payload && payload.authorization_code) || apiData.authorization_code;
                                const message = (payload && payload.message) || apiData.message;
                                
                                console.log('Extracted values:', { status, loginUrl, registrationUrl, message });
                                $logs.append('<div class="log-entry"> 📊 Status: ' + escapeHtmlSafe(String(status)) + '</div>');
                                
                                if (status === 'connected' && encryptedCredentials && typeof window.processCredentials === 'function') {
                                    $logs.append('<div class="log-entry"> Received credentials</div>');
                                    $logs.append('<div class="log-entry"> Processing...</div>');
                                    window.processCredentials(encryptedCredentials);
                                    return;
                                }

                                if (status === 'connected' && authorizationCode) {
                                    const baseUrl = (window.w91099chConnector && window.w91099chConnector.admin_page_url)
                                        ? window.w91099chConnector.admin_page_url
                                        : window.location.href;
                                    if (baseUrl) {
                                        const sep = baseUrl.indexOf('?') === -1 ? '?' : '&';
                                        const redirectUrl = baseUrl + sep + 'status=connected&authorization_code=' + encodeURIComponent(String(authorizationCode));
                                        $logs.append('<div class="log-entry"> Redirecting to finalize connection...</div>');
                                        window.location.href = redirectUrl;
                                        return;
                                    }
                                }

                                if (status === 'registration_required' && registrationUrl) {
                                    $logs.append('<div class="log-entry"> Redirecting to registration...</div>');
                                    console.log('Redirecting to registration URL:', registrationUrl);
                                    window.location.href = registrationUrl;
                                    return;
                                }

                                if (status === 'login_required' && loginUrl) {
                                    $logs.append('<div class="log-entry"> Login required - redirecting...</div>');
                                    $logs.append('<div class="log-entry"> 🔗 Login URL: ' + escapeHtmlSafe(String(loginUrl)) + '</div>');
                                    $logs.append('<div class="log-entry"> ' + escapeHtmlSafe(message || 'Please log in to continue') + '</div>');
                                    console.log('Redirecting to login URL:', loginUrl);
                                    setTimeout(() => {
                                        console.log('Executing redirect to:', loginUrl);
                                        window.location.href = loginUrl;
                                    }, 2000); // Increased timeout to 2 seconds
                                    return;
                                }

                                // Log unhandled response for debugging
                                $logs.append('<div class="log-entry"> ⚠️ Unhandled response status: ' + escapeHtmlSafe(String(status)) + '</div>');
                                if (message) {
                                    $logs.append('<div class="log-entry"> ' + escapeHtmlSafe(String(message)) + '</div>');
                                }
                                console.log('Unhandled response:', apiData);
                            })
                            .catch(err => {
                                console.error('Fetch error:', err);
                                $logs.append('<div class="log-entry"> ❌ Connection error: ' + escapeHtmlSafe(String(err.message || err)) + '</div>');
                            })
                            .finally(() => {
                                $button.prop('disabled', false).removeClass('loading');
                                connecting = false;
                            });
                    },
                    error: function(xhr) {
                        // eslint-disable-next-line no-console
                        w91099chConsole.error('Connection error:', xhr && xhr.responseText);
                        $button.prop('disabled', false).removeClass('loading');
                        connecting = false;
                    }
                });
            };

            if (!window.w91099chConnector.has_admin_consent) {
                if (!window.confirm('This will transmit selected site/profile/affiliate/team data to the external Mypowerly service. Do you consent to proceed?')) {
                    return;
                }
                ensureAdminConsent(function(ok) {
                    if (!ok) {
                        window.alert('Unable to save consent. Please refresh the page and try again.');
                        return;
                    }
                    startConnect();
                });
                return;
            }
            
            startConnect();
        });

        $(function() {
            // Enhanced Tooltip Functionality
            function initTooltips() {
                $('.icon-tooltip').each(function() {
                    const $tooltip = $(this);
                    const tooltipText = $tooltip.attr('data-tooltip');
                    
                    if (!tooltipText) return;
                    
                    // Add hover events with enhanced positioning
                    $tooltip
                        .off('mouseenter.iconTooltip mouseleave.iconTooltip')
                        .on('mouseenter.iconTooltip', function(e) {
                            const $tooltipEl = $(this);
                            const tooltipText = $tooltipEl.attr('data-tooltip');
                            
                            // Create tooltip element if it doesn't exist
                            let $tooltipDiv = $tooltipEl.find('.tooltip-popup');
                            if ($tooltipDiv.length === 0) {
                                $tooltipDiv = $('<div class="tooltip-popup">' + tooltipText + '</div>');
                                $tooltipEl.append($tooltipDiv);
                            }
                            
                            // Position tooltip
                            const tooltipPos = calculateTooltipPosition($tooltipEl, $tooltipDiv);
                            $tooltipDiv.css(tooltipPos).addClass('show');
                        })
                        .on('mouseleave.iconTooltip', function() {
                            $(this).find('.tooltip-popup').removeClass('show');
                        });
                });
            }
            
            function calculateTooltipPosition($element, $tooltip) {
                const elementRect = $element[0].getBoundingClientRect();
                const tooltipRect = $tooltip[0].getBoundingClientRect();
                const windowWidth = $(window).width();
                const windowHeight = $(window).height();
                const scrollTop = $(window).scrollTop();
                const scrollLeft = $(window).scrollLeft();
                
                let position = {
                    position: 'absolute',
                    zIndex: 10000
                };
                
                // Default: top
                position.top = elementRect.top - tooltipRect.height - 10;
                position.left = elementRect.left + (elementRect.width / 2) - (tooltipRect.width / 2);
                
                // Adjust if tooltip goes off screen
                if (position.top < scrollTop) {
                    // Show below instead
                    position.top = elementRect.bottom + 10;
                    $tooltip.addClass('tooltip-bottom').removeClass('tooltip-top');
                } else {
                    $tooltip.addClass('tooltip-top').removeClass('tooltip-bottom');
                }
                
                if (position.left < scrollLeft) {
                    position.left = scrollLeft + 10;
                    $tooltip.addClass('tooltip-left').removeClass('tooltip-right');
                } else if (position.left + tooltipRect.width > scrollLeft + windowWidth) {
                    position.left = scrollLeft + windowWidth - tooltipRect.width - 10;
                    $tooltip.addClass('tooltip-right').removeClass('tooltip-left');
                } else {
                    $tooltip.removeClass('tooltip-left tooltip-right');
                }
                
                return position;
            }
            
            // Initialize tooltips on page load
            initTooltips();

            function w91099chShowNewsletterModal() {
                const $modal = $('#w91099ch-newsletter-modal');
                if ($modal.length) {
                    $modal.removeClass('hidden').show();
                }
            }

            function w91099chHideNewsletterModal() {
                const $modal = $('#w91099ch-newsletter-modal');
                if ($modal.length) {
                    $modal.addClass('hidden').hide();
                }
            }

            function w91099chApplyW9DisplayLockState() {
                const $lockWrap = $('#w91099ch-w9-display-lock');
                if (!$lockWrap.length) return;

                const lockedAttr = String($lockWrap.attr('data-locked') || '0');
                const locked = lockedAttr === '1';

                const $inputs = $lockWrap
                    .find('input, select, textarea, button')
                    .filter(function() {
                        return $(this).closest('#w91099ch-newsletter-modal, #w91099ch-w9-tools-qr-modal').length === 0;
                    });
                if (locked) {
                    $inputs.prop('disabled', true);
                    $lockWrap.attr('aria-disabled', 'true');
                } else {
                    $inputs.prop('disabled', false);
                    $lockWrap.removeAttr('aria-disabled');
                }

                const $overlay = $('#w91099ch-w9-display-lock-overlay');
                if (locked) {
                    if ($overlay.length) {
                        $overlay.css('cursor', 'pointer');
                    }
                }
            }

            // Newsletter gate for W-9 Display Settings (locks until subscribed once)
            (function initW9DisplayNewsletterGate() {
                const $lockWrap = $('#w91099ch-w9-display-lock');
                if (!$lockWrap.length) return;

                w91099chApplyW9DisplayLockState();

                // Bind close handler on the modal container so it runs BEFORE the event bubbles
                // up to the locked wrapper click handler.
                $('#w91099ch-newsletter-modal')
                    .off('click.w91099chNewsletterClose')
                    .on('click.w91099chNewsletterClose', '[data-newsletter-close="1"]', function(e) {
                        if (e && typeof e.preventDefault === 'function') e.preventDefault();
                        if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
                        if (e && typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
                        w91099chHideNewsletterModal();
                    });

                // Extra safety: bind directly on elements (non-delegated), in case another handler
                // stops propagation before delegated handlers can run.
                $('#w91099ch-newsletter-modal [data-newsletter-close="1"]')
                    .off('click.w91099chNewsletterCloseDirect')
                    .on('click.w91099chNewsletterCloseDirect', function(e) {
                        if (e && typeof e.preventDefault === 'function') e.preventDefault();
                        if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
                        if (e && typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
                        w91099chHideNewsletterModal();
                    });

                $('#w91099ch-w9-display-lock-overlay')
                    .off('click.w91099chNewsletterOverlay')
                    .on('click.w91099chNewsletterOverlay', function(e) {
                        if (e && typeof e.preventDefault === 'function') e.preventDefault();
                        w91099chShowNewsletterModal();
                    });

                // If overlay is not present for any reason, fallback to capturing clicks in wrapper
                $lockWrap
                    .off('click.w91099chNewsletterGate')
                    .on('click.w91099chNewsletterGate', function(e) {
                        const locked = String($lockWrap.attr('data-locked') || '0') === '1';
                        if (!locked) return;
                        // Ignore clicks that occur inside the modal itself.
                        if ($(e.target).closest('#w91099ch-newsletter-modal').length) return;
                        if (e && typeof e.preventDefault === 'function') e.preventDefault();
                        if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
                        w91099chShowNewsletterModal();
                    });

                $('#w91099ch-newsletter-subscribe-btn')
                    .off('click.w91099chNewsletterSubscribe')
                    .on('click.w91099chNewsletterSubscribe', function() {
                        if (!hasW91099chConnector) return;

                        const $btn = $(this);
                        const email = String($('#w91099ch_newsletter_email').val() || '').trim();
                        const nonce = (window.w91099chConnector && window.w91099chConnector.newsletter_subscribe_nonce)
                            ? window.w91099chConnector.newsletter_subscribe_nonce
                            : '';

                        if (!nonce) {
                            window.alert('Security nonce missing. Please refresh and try again.');
                            return;
                        }

                        $btn.prop('disabled', true);

                        $.ajax({
                            url: window.w91099chConnector.ajaxurl,
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'w91099ch_newsletter_subscribe',
                                nonce: nonce,
                                email: email
                            },
                            success: function(resp) {
                                if (resp && resp.success) {
                                    // Unlock immediately in UI and persist via option (server already did)
                                    $lockWrap.attr('data-locked', '0');
                                    if (window.w91099chConnector) {
                                        window.w91099chConnector.newsletter_subscribed = true;
                                    }
                                    w91099chHideNewsletterModal();
                                    w91099chApplyW9DisplayLockState();

                                    $('#w91099ch-w9-display-lock-overlay').remove();
                                    return;
                                }
                                const msg = (resp && resp.data) ? resp.data : 'Subscription failed';
                                window.alert(String(msg));
                            },
                            error: function(xhr, status, error) {
                                const msg = (xhr && xhr.responseText) ? xhr.responseText : (error || 'Subscription failed');
                                window.alert(String(msg));
                            },
                            complete: function() {
                                $btn.prop('disabled', false);
                            }
                        });
                    });
            })();
            
            // Re-initialize tooltips after AJAX calls
            const originalUpdateDetectedPluginsUi = window.updateDetectedPluginsUi;
            if (typeof originalUpdateDetectedPluginsUi === 'function') {
                window.updateDetectedPluginsUi = function(plugins, totalAffiliates) {
                    const result = originalUpdateDetectedPluginsUi(plugins, totalAffiliates);
                    setTimeout(initTooltips, 100); // Re-init after DOM updates
                    return result;
                };
            }

            $('#disconnect-mypowerly').off('click.w91099chConnectorDisconnect').on('click.w91099chConnectorDisconnect', function() {
                if (!hasW91099chConnector) return;

                if (!window.confirm('Disconnect from MyPowerly?')) {
                    return;
                }

                const $button = $(this);
                $button.prop('disabled', true);

                $.ajax({
                    url: window.w91099chConnector.ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'w91099ch_disconnect',
                        nonce: window.w91099chConnector.disconnect_nonce
                    },
                    success: function(response) {
                        if (response && response.success) {
                            window.location.reload();

                            return;
                        }
                        const msg = (response && response.data) ? response.data : 'Disconnect failed';
                        $button.prop('disabled', false);
                        window.alert(String(msg));
                    },
                    error: function(xhr, status, error) {
                        $button.prop('disabled', false);
                        const msg = (xhr && xhr.responseText) ? xhr.responseText : (error || 'Disconnect failed');
                        window.alert(String(msg));
                    }
                });
            });

            $('#refresh-plugins').off('click.w91099chConnectorRefreshPlugins').on('click.w91099chConnectorRefreshPlugins', function(e) {
                if (e && typeof e.preventDefault === 'function') {
                    e.preventDefault();
                }
                if (e && typeof e.stopImmediatePropagation === 'function') {
                    e.stopImmediatePropagation();
                }
                if (!hasW91099chConnector) return;

                const $btn = $(this);
                const $icon = $btn.find('i');
                const originalIconClass = $icon.attr('class');

                $btn.prop('disabled', true);
                if ($icon.length) {
                    $icon.attr('class', 'fas fa-spinner fa-spin text-xs');
                }

                $.ajax({
                    url: window.w91099chConnector.ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'w91099ch_refresh_affiliate_plugins',
                        nonce: window.w91099chConnector.nonce
                    },
                    success: function(resp) {
                        if (resp && resp.success) {
                            const data = (resp && resp.data) ? resp.data : {};
                            updateDetectedPluginsUi(data.plugins || {}, data.total_affiliates);
                            return;
                        }
                        const msg = (resp && resp.data) ? resp.data : 'Refresh failed';
                        window.alert(String(msg));
                    },
                    error: function(xhr, status, error) {
                        const msg = (xhr && xhr.responseText) ? xhr.responseText : (error || 'Refresh failed');
                        window.alert(String(msg));
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                        if ($icon.length && originalIconClass) {
                            $icon.attr('class', originalIconClass);
                        }
                    }
                });
            });
        });
    });
})();
