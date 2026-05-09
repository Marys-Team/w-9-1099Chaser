<?php
/**
 * W-9 Form Admin Tab
 *
 * @package w91099ch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get the current tab
$w91099ch_tab_param_raw = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
$w91099ch_current_tab   = is_string( $w91099ch_tab_param_raw ) ? sanitize_text_field( wp_unslash( $w91099ch_tab_param_raw ) ) : 'dashboard';
$w91099ch_is_connected = ( isset( $this->core ) && is_object( $this->core ) && method_exists( $this->core, 'is_connected' ) )
	? (bool) $this->core->is_connected()
	: false;
?>

<div class="wrap">
	<!-- Tabs Navigation -->
	<h2 class="nav-tab-wrapper">
		<a href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'page' => 'w9-1099-chaser',
					'tab'  => 'dashboard',
				),
				admin_url( 'admin.php' )
			)
		);
		?>
		" class="nav-tab <?php echo esc_attr( $w91099ch_current_tab === 'dashboard' ? 'nav-tab-active' : '' ); ?>">
			<i class="fas fa-tachometer-alt"></i> Dashboard
		</a>
		<a href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'page' => 'w9-1099-chaser',
					'tab'  => 'w9-form',
				),
				admin_url( 'admin.php' )
			)
		);
		?>
		" class="nav-tab <?php echo esc_attr( $w91099ch_current_tab === 'w9-form' ? 'nav-tab-active' : '' ); ?>">
			<i class="fas fa-file-pdf"></i> W-9 Form
		</a>
	</h2>

	<!-- Tab Content -->
	<div class="tab-content">
		<?php if ( $w91099ch_current_tab === 'dashboard' ) : ?>
			<!-- Existing dashboard content will be loaded here -->
			<?php $this->render_dashboard_content(); ?>
		<?php elseif ( $w91099ch_current_tab === 'w9-form' ) : ?>
			<!-- W-9 Form Tab Content -->
			<div class="mp-card p-8">
				<div class="flex flex-col lg:flex-row gap-8">
					<!-- Left Column: Settings and Instructions -->
					<div class="w-full lg:w-1/2 space-y-6">
						<!-- Display Settings Section -->
						<div class="p-6 bg-white rounded-xl border border-gray-200">
							<h3 class="text-xl font-bold text-gray-800 mb-6">
								<i class="fas fa-cog text-blue-600 mr-2"></i> Display Settings
							</h3>
							
							<!-- Display Options -->
							<div class="space-y-4 mb-6">
								<label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
									<input type="radio" name="display_mode" value="all" class="mr-3">
									<div>
										<div class="font-semibold text-gray-800">Auto display on all pages</div>
										<div class="text-sm text-gray-600">Show W-9 form automatically on every page</div>
									</div>
								</label>
								
								<label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
									<input type="radio" name="display_mode" value="selected" class="mr-3">
									<div>
										<div class="font-semibold text-gray-800">Display on selected pages only</div>
										<div class="text-sm text-gray-600">Choose specific pages where W-9 form should appear</div>
									</div>
								</label>
								
								<label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
									<input type="radio" name="display_mode" value="shortcode" class="mr-3">
									<div>
										<div class="font-semibold text-gray-800">Shortcode only</div>
										<div class="text-sm text-gray-600">Display only using shortcode [w91099ch_w9_form]</div>
									</div>
								</label>
							</div>

							<!-- Position Settings (shown when "all" or "selected" is chosen) -->
							<div id="position-settings-section" style="display: none;">
								<h4 class="font-semibold text-gray-800 mb-3">Form Position</h4>
								<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
									<label class="flex flex-col items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
										<input type="radio" name="display_position" value="top" class="mb-2">
										<i class="fas fa-arrow-up text-blue-600 mb-1"></i>
										<span class="text-sm font-medium">Top</span>
									</label>
									
									<label class="flex flex-col items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
										<input type="radio" name="display_position" value="middle" class="mb-2">
										<i class="fas fa-grip-lines text-green-600 mb-1"></i>
										<span class="text-sm font-medium">Middle</span>
									</label>
									
									<label class="flex flex-col items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
										<input type="radio" name="display_position" value="bottom" class="mb-2">
										<i class="fas fa-arrow-down text-orange-600 mb-1"></i>
										<span class="text-sm font-medium">Bottom</span>
									</label>
									
									<label class="flex flex-col items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
										<input type="radio" name="display_position" value="floating" class="mb-2">
										<i class="fas fa-window-restore text-purple-600 mb-1"></i>
										<span class="text-sm font-medium">Floating</span>
									</label>
								</div>

								<!-- Floating Settings (shown when "floating" is chosen) -->
								<div id="floating-settings-section" style="display: none;" class="p-4 bg-purple-50 rounded-lg border border-purple-200">
									<h5 class="font-semibold text-gray-800 mb-3">Floating Widget Settings</h5>
									<div class="space-y-3">
										<div>
											<label class="block text-sm font-medium text-gray-700 mb-1">Widget Type</label>
											<select name="floating_widget_type" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
												<option value="icon-button">Icon Button</option>
												<option value="text-button">Text Button</option>
												<option value="badge">Badge</option>
											</select>
										</div>
										
										<div>
											<label class="block text-sm font-medium text-gray-700 mb-1">Button Text</label>
											<input type="text" name="floating_button_text" value="W-9 Form" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
										</div>
										
										<div>
											<label class="block text-sm font-medium text-gray-700 mb-1">Position on Screen</label>
											<select name="floating_screen_position" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
												<option value="bottom-right">Bottom Right</option>
												<option value="bottom-left">Bottom Left</option>
												<option value="top-right">Top Right</option>
												<option value="top-left">Top Left</option>
											</select>
										</div>
										
										<div>
											<label class="block text-sm font-medium text-gray-700 mb-1">Background Color</label>
											<input type="color" name="floating_bg_color" value="#3b82f6" class="h-10 w-20 border border-gray-300 rounded">
										</div>
									</div>
								</div>
							</div>

							<!-- Page Selection (shown when "selected" is chosen) -->
							<div id="page-selection-section" style="display: none;">
								<h4 class="font-semibold text-gray-800 mb-3">Select Pages</h4>
								<div class="mb-4">
									<div class="flex items-center justify-between mb-2">
										<span class="text-sm text-gray-600">Choose pages where W-9 form should appear:</span>
										<span id="selected-count" class="text-sm font-medium text-blue-600">0 pages selected</span>
									</div>
									<div class="border rounded-lg p-3 max-h-40 overflow-y-auto bg-gray-50">
										<div id="page-checkboxes">
											<!-- Page checkboxes will be populated by JavaScript -->
										</div>
									</div>
								</div>
								
								<!-- Selected Pages Display -->
								<div id="selected-pages-display" style="display: none;">
									<div class="flex items-center justify-between mb-2">
										<h4 class="font-semibold text-gray-800">Selected Pages</h4>
										<button type="button" id="show-all-pages" class="text-sm text-blue-600 hover:text-blue-800">Show all</button>
									</div>
									<div id="selected-pages-list" class="flex flex-wrap gap-2">
										<!-- Selected page tags will be shown here -->
									</div>
								</div>

								<!-- Widget Placement Settings -->
								<div id="widget-placement-section" style="display: none;" class="mt-6">
									<h4 class="font-semibold text-gray-800 mb-3">Page Settings (Position & Widget)</h4>
									<div id="page-position-settings" class="space-y-3">
										<!-- Individual page position settings will be shown here -->
									</div>
								</div>
							</div>

							<!-- Reward Section Visibility Toggle -->
							<div class="p-4 bg-purple-50 rounded-lg border border-purple-200 mb-6">
								<h4 class="font-semibold text-gray-800 mb-2">
									<i class="fas fa-eye text-purple-600 mr-2"></i> Reward Section Visibility
								</h4>
								<p class="text-sm text-gray-600 mb-3">Control whether the reward section (stars, email, comment) is shown by default in the popup.</p>
						<?php
						$reward_section_visible = get_option( 'w91099ch_reward_section_visible', 'false' );
						?>
						<label class="flex items-center gap-3 cursor-pointer">
							<input type="checkbox" id="w91099ch_reward_section_visible" name="w91099ch_reward_section_visible" value="true" <?php checked( (string) $reward_section_visible, 'true' ); ?> class="w-4 h-4 text-purple-600 rounded border-gray-300 focus:ring-purple-500">
							<span class="text-sm font-medium text-gray-800">Show reward section by default</span>
						</label>
								<p class="text-xs mt-2 text-gray-500">If CHECKED, the reward section (stars, email, comment) will be shown by default. If UNCHECKED, it will be hidden by default.</p>
							</div>

							<!-- Save Button -->
							<div class="mt-6 flex items-center justify-between">
								<button type="button" id="save-w9-settings" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
									<i class="fas fa-save mr-2"></i> Save Settings
								</button>
								<div id="settings-message" class="text-sm"></div>
							</div>
						</div>

						<!-- Shortcode Section -->
						<div class="p-6 bg-blue-50 rounded-xl border border-blue-100">
							<h3 class="text-lg font-semibold text-gray-800 mb-4">
								<i class="fas fa-code text-gray-600 mr-2"></i> Shortcode
							</h3>
							<p class="text-sm text-gray-600 mb-3">
								Add this shortcode to any page to display the W-9 form:
							</p>
							<div class="flex items-center gap-2 mb-4">
								<input type="text" id="w9-shortcode" readonly value="[w91099ch_w9_form]" class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm font-mono bg-gray-50">
								<button type="button" onclick="copyToClipboard('#w9-shortcode')" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md text-sm font-medium transition-colors">
									<i class="far fa-copy"></i> Copy
								</button>
							</div>
							<button type="button" id="create-w9-page" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium transition-colors">
								<i class="fas fa-plus-circle mr-2"></i> Create W-9 Page
							</button>
							<p class="text-sm text-gray-600 mt-4">
								You can also add the <strong>W-9 Form</strong> block from the Gutenberg block inserter while editing a page or post.
							</p>
						</div>
					</div>

					<!-- Right Column: Form Preview -->
					<div class="w-full lg:w-1/2">
						<div class="p-6 bg-white rounded-xl border border-gray-200">
							<h3 class="text-xl font-bold text-gray-800 mb-6">
								<i class="fas fa-file-signature text-green-600 mr-2"></i> W-9 Form Preview
							</h3>
							<?php echo do_shortcode( '[w91099ch_w9_form]' ); ?>
							<?php if ( ! $w91099ch_is_connected ) : ?>
								<div class="mp-card p-6 mt-8" id="mypowerly-connect-block" style="border-left: 4px solid var(--mp-primary);">
									<div class="flex flex-col lg:flex-row items-start lg:items-center gap-6">
										<div class="flex-1">
											<div class="flex items-start gap-4">
												<div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
													<i class="fas fa-cloud-upload-alt text-2xl" style="color: var(--mp-primary);"></i>
												</div>
												<div>
													<h3 class="text-xl font-bold text-gray-800 mb-1">Connect to MyPowerly (optional)</h3>
													<p class="text-gray-600">If you want to send this W-9 form data to MyPowerly for processing and storage, connect your site.</p>
													<p class="text-red-500 font-bold mt-2"><strong>For security and privacy reasons, this plugin never transmits SSN or FEIN information to MyPowerly.</strong></p>
												</div>
											</div>
											<div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
												<div class="text-sm text-gray-700 mb-3">This W-9 form data is <strong>not stored in WordPress</strong> by default. If you choose to connect, the plugin can <strong>securely send</strong> selected data (profile, affiliates, users) to MyPowerly when you initiate sync actions.</div>
												<label class="flex items-start gap-3 cursor-pointer">
													<input type="checkbox" id="mypowerly-consent" class="mt-1" />
													<span class="text-sm text-gray-700">I understand that connecting will securely sync and store selected WordPress data in MyPowerly as part of the connector.</span>
												</label>
												<div class="mt-4">
													<div class="text-sm font-semibold text-gray-800 mb-2">Connection code (optional)</div>
													<div class="flex items-center gap-3">
														<input type="text" id="mypowerly-discount-code" class="mp-input" placeholder="Optional code" autocomplete="off" />
														<button type="button" id="mypowerly-apply-discount" class="mp-btn-secondary">Apply</button>
													</div>
													<div id="mypowerly-applied-discount" class="mt-3 hidden">
														<span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 text-gray-800 text-sm" style="border: 1px solid #e5e7eb;">
															<i class="fas fa-tag" style="opacity:.7"></i>
															<span id="mypowerly-applied-discount-code"></span>
															<button type="button" id="mypowerly-remove-discount" class="text-gray-500" style="font-size: 16px; line-height: 1;">&times;</button>
														</span>
													</div>
													<div class="text-xs text-gray-500 mt-2">Optional code can be entered during connection.</div>
												</div>
											</div>
										</div>
										<div class="flex flex-col gap-3 w-full lg:w-auto">
											<button type="button" id="connect-mypowerly-cta" class="mp-btn-primary flex items-center justify-center gap-3" disabled>
												<i class="fas fa-plug"></i>
												Connect to Mypowerly
											</button>
											<div class="text-xs text-gray-500 text-center lg:text-left">You can still download the W-9 PDF without connecting.</div>
										</div>
									</div>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- JavaScript for Display Settings -->
<script>
jQuery(document).ready(function($) {
    // Load current settings
    var currentSettings = {
        display_mode: '<?php echo esc_js( get_option( 'w91099ch_w9_display_method', 'all' ) ); ?>',
        display_position: '<?php echo esc_js( get_option( 'w91099ch_w9_display_position', 'bottom' ) ); ?>',
        floating_settings: <?php echo json_encode( get_option( 'w91099ch_w9_floating_settings', array() ) ); ?>,
        selected_pages: <?php echo json_encode( get_option( 'w91099ch_w9_selected_pages', array() ) ); ?>,
        page_positions: <?php echo json_encode( get_option( 'w91099ch_w9_page_positions', array() ) ); ?>,
        reward_section_visible: '<?php echo esc_js( get_option( 'w91099ch_reward_section_visible', 'true' ) ); ?>'
    };
    
    var allPages = <?php echo json_encode( get_pages( array( 'number' => 100 ) ) ); ?>;
    var selectedPages = currentSettings.selected_pages || [];
    var pagePositions = currentSettings.page_positions || {};
    var floatingSettings = currentSettings.floating_settings || {};
    
    // Define available widgets
    var availableWidgets = [
        { id: 'default', name: 'Default Widget' },
        { id: 'daniel_widget', name: 'daniel widget' },
        { id: 'standard_widget', name: 'Standard Widget' },
        { id: 'compact_widget', name: 'Compact Widget' }
    ];

    // Initialize display mode and position
    console.log('W9 Debug: Initializing checkboxes with:', {
        reward_section_visible: currentSettings.reward_section_visible
    });
    
    $('input[name="display_mode"][value="' + currentSettings.display_mode + '"]').prop('checked', true);
    $('input[name="display_position"][value="' + currentSettings.display_position + '"]').prop('checked', true);
    $('#w91099ch_reward_section_visible').prop('checked', String(currentSettings.reward_section_visible) === 'true');
    
    togglePageSelection();
    togglePositionSettings();
    loadFloatingSettings();

    // Populate page checkboxes
    populatePageCheckboxes();
    updateSelectedPagesDisplay();
    updatePagePositionSettings();

    // Handle display mode change
    $('input[name="display_mode"]').on('change', function() {
        togglePageSelection();
        togglePositionSettings();
    });

    // Handle display position change
    $('input[name="display_position"]').on('change', function() {
        toggleFloatingSettings();
    });

    function togglePageSelection() {
        var displayMode = $('input[name="display_mode"]:checked').val();
        if (displayMode === 'selected') {
            $('#page-selection-section').show();
        } else {
            $('#page-selection-section').hide();
        }
    }

    function togglePositionSettings() {
        var displayMode = $('input[name="display_mode"]:checked').val();
        if (displayMode === 'all' || displayMode === 'selected') {
            $('#position-settings-section').show();
            toggleFloatingSettings();
        } else {
            $('#position-settings-section').hide();
            $('#floating-settings-section').hide();
        }
    }

    function toggleFloatingSettings() {
        var displayPosition = $('input[name="display_position"]:checked').val();
        if (displayPosition === 'floating') {
            $('#floating-settings-section').show();
        } else {
            $('#floating-settings-section').hide();
        }
    }

    function loadFloatingSettings() {
        if (floatingSettings.widget_type) {
            $('select[name="floating_widget_type"]').val(floatingSettings.widget_type);
        }
        if (floatingSettings.button_text) {
            $('input[name="floating_button_text"]').val(floatingSettings.button_text);
        }
        if (floatingSettings.screen_position) {
            $('select[name="floating_screen_position"]').val(floatingSettings.screen_position);
        }
        if (floatingSettings.bg_color) {
            $('input[name="floating_bg_color"]').val(floatingSettings.bg_color);
        }
    }

    function populatePageCheckboxes() {
        var container = $('#page-checkboxes');
        container.empty();
        
        allPages.forEach(function(page) {
            var isChecked = selectedPages.includes(page.ID);
            var checkbox = $('<label class="flex items-center p-2 hover:bg-gray-100 rounded cursor-pointer">' +
                '<input type="checkbox" value="' + page.ID + '" ' + (isChecked ? 'checked' : '') + ' class="mr-2 page-checkbox">' +
                '<span class="text-sm">' + page.post_title + '</span>' +
                '</label>');
            container.append(checkbox);
        });

        // Handle checkbox changes
        $('.page-checkbox').on('change', function() {
            var pageId = parseInt($(this).val());
            if ($(this).is(':checked')) {
                if (!selectedPages.includes(pageId)) {
                    selectedPages.push(pageId);
                }
            } else {
                selectedPages = selectedPages.filter(function(id) {
                    return id !== pageId;
                });
                // Remove position setting if page is deselected
                delete pagePositions[pageId];
            }
            updateSelectedCount();
            updateSelectedPagesDisplay();
            updatePagePositionSettings();
        });
    }

    function updateSelectedCount() {
        $('#selected-count').text(selectedPages.length + ' pages selected');
    }

    function updateSelectedPagesDisplay() {
        var display = $('#selected-pages-display');
        var list = $('#selected-pages-list');
        
        if (selectedPages.length === 0) {
            display.hide();
            return;
        }
        
        display.show();
        list.empty();
        
        selectedPages.forEach(function(pageId) {
            var page = allPages.find(function(p) { return p.ID === pageId; });
            if (page) {
                var tag = $('<span class="inline-flex items-center gap-1 px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">' +
                    page.post_title +
                    '<button type="button" class="ml-1 text-blue-600 hover:text-blue-800" data-page-id="' + pageId + '">&times;</button>' +
                    '</span>');
                list.append(tag);
            }
        });

        // Handle remove buttons
        list.find('button').on('click', function() {
            var pageId = parseInt($(this).data('page-id'));
            selectedPages = selectedPages.filter(function(id) { return id !== pageId; });
            delete pagePositions[pageId];
            $('.page-checkbox[value="' + pageId + '"]').prop('checked', false);
            updateSelectedCount();
            updateSelectedPagesDisplay();
            updatePagePositionSettings();
        });
    }

    function updatePagePositionSettings() {
        var container = $('#page-position-settings');
        var placementSection = $('#widget-placement-section');
        
        if (selectedPages.length === 0) {
            placementSection.hide();
            return;
        }
        
        placementSection.show();
        container.empty();
        
        selectedPages.forEach(function(pageId) {
            var page = allPages.find(function(p) { return p.ID === pageId; });
            if (page) {
                var currentPosition = pagePositions[pageId] ? pagePositions[pageId].position : 'top';
                var currentWidget = pagePositions[pageId] ? pagePositions[pageId].widget : 'default';
                
                // Build widget options HTML
                var widgetOptionsHtml = '';
                availableWidgets.forEach(function(widget) {
                    widgetOptionsHtml += '<option value="' + widget.id + '" ' + (currentWidget === widget.id ? 'selected' : '') + '>' + widget.name + '</option>';
                });
                
                var positionHtml = $('<div class="flex items-center gap-3 p-3 border rounded-lg">' +
                    '<span class="text-sm font-medium flex-1">' + page.post_title + '</span>' +
                    '<select class="page-position-select px-3 py-1 border rounded text-sm" data-page-id="' + pageId + '">' +
                    '<option value="top" ' + (currentPosition === 'top' ? 'selected' : '') + '>Top</option>' +
                    '<option value="bottom" ' + (currentPosition === 'bottom' ? 'selected' : '') + '>Bottom</option>' +
                    '</select>' +
                    '<select class="page-widget-select px-3 py-1 border rounded text-sm" data-page-id="' + pageId + '">' +
                    widgetOptionsHtml +
                    '</select>' +
                    '</div>');
                container.append(positionHtml);
            }
        });

        // Handle position changes
        $('.page-position-select').on('change', function() {
            var pageId = parseInt($(this).data('page-id'));
            var position = $(this).val();
            if (!pagePositions[pageId]) {
                pagePositions[pageId] = {};
            }
            pagePositions[pageId].position = position;
        });
        
        // Handle widget changes
        $('.page-widget-select').on('change', function() {
            var pageId = parseInt($(this).data('page-id'));
            var widget = $(this).val();
            if (!pagePositions[pageId]) {
                pagePositions[pageId] = {};
            }
            pagePositions[pageId].widget = widget;
        });
    }

    // Handle save settings
    $('#save-w9-settings').on('click', function() {
        var button = $(this);
        var messageDiv = $('#settings-message');
        
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Saving...');
        
        // Collect floating settings
        var floatingSettings = {};
        var displayPosition = $('input[name="display_position"]:checked').val();
        
        if (displayPosition === 'floating') {
            floatingSettings = {
                widget_type: $('select[name="floating_widget_type"]').val(),
                button_text: $('input[name="floating_button_text"]').val(),
                screen_position: $('select[name="floating_screen_position"]').val(),
                bg_color: $('input[name="floating_bg_color"]').val()
            };
        }
        
        const rewardSectionVisibleChecked = $('#w91099ch_reward_section_visible').is(':checked');
        
        console.log('W9 Debug: Saving settings...', {
            rewardSectionVisible: rewardSectionVisibleChecked
        });

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'w91099ch_save_w9_display_settings',
                nonce: '<?php echo wp_create_nonce( "w91099ch_w9_form_nonce" ); ?>',
                display_mode: $('input[name="display_mode"]:checked').val(),
                display_position: displayPosition,
                floating_settings: JSON.stringify(floatingSettings),
                selected_pages: selectedPages,
                page_positions: JSON.stringify(pagePositions),
                w91099ch_reward_section_visible: rewardSectionVisibleChecked ? 'true' : 'false'
            },
            success: function(response) {
                if (response.success) {
                    messageDiv.html('<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i> ' + response.data.message + '</span>');
                } else {
                    messageDiv.html('<span class="text-red-600"><i class="fas fa-exclamation-circle mr-1"></i> ' + response.data.message + '</span>');
                }
            },
            error: function() {
                messageDiv.html('<span class="text-red-600"><i class="fas fa-exclamation-circle mr-1"></i> An error occurred while saving settings.</span>');
            },
            complete: function() {
                button.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Save Settings');
                setTimeout(function() {
                    messageDiv.empty();
                }, 3000);
            }
        });
    });

    // Initialize selected count
    updateSelectedCount();
});

// Copy to clipboard function
function copyToClipboard(element) {
    var $temp = $('<input>');
    $('body').append($temp);
    $temp.val($(element).val()).select();
    document.execCommand('copy');
    $temp.remove();
}
</script>
