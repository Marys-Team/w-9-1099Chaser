<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$w91099ch_widget_page_url = admin_url( 'admin.php?page=w91099ch-widget' );

?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 to-blue-50 mp-shell">
	<div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-10 py-8 pb-12">
		<div class="mb-10">
			<div class="mp-card p-6" style="background: linear-gradient(135deg, rgba(26, 86, 219, 0.08) 0%, rgba(124, 58, 237, 0.06) 100%);">
				<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
					<div class="flex items-center gap-4">
						<div class="w-14 h-14 rounded-2xl overflow-hidden bg-white border" style="border-color: var(--mp-gray-200);">
							<img src="<?php echo esc_url( w91099ch_PLUGIN_URL . 'assets/logo/logo-removebg-preview.png' ); ?>" alt="Vendor Onboarding W9-1099 Chaser by Mypowerly" style="width: 100%; height: 100%; object-fit: cover; display: block; transform: scale(1.25); transform-origin: center;" />
						</div>
						<div>
							<h1 class="text-2xl font-bold text-gray-800 mb-1"><?php echo esc_html( $page_title ); ?></h1>
							<div class="text-sm" style="color: var(--mp-gray-600); line-height: 1.45;"><?php echo esc_html__( 'Self-service W-9 form collection—powered by chat, AI, and automation.', 'w9-1099-chaser' ); ?></div>
							<div class="text-sm" style="color: var(--mp-gray-600); line-height: 1.45;"><?php echo esc_html__( 'Generate widget code and control where it appears on your site.', 'w9-1099-chaser' ); ?></div>
						</div>
					</div>
					<div class="flex flex-wrap items-center gap-3 relative" id="w91099ch-widget-help-area">
						<button type="button" class="w91099ch-widget-help-btn" id="w91099ch-widget-help-btn" aria-label="Widget help" aria-haspopup="dialog" aria-expanded="false">
							<i class="fas fa-circle-question"></i>
						</button>

						<div class="w91099ch-widget-help-popover hidden" id="w91099ch-widget-help-popover" role="dialog" aria-hidden="true">
							<div class="w91099ch-widget-help-title"><?php echo esc_html__( 'MyPowerly W-9 Collection Widget', 'w9-1099-chaser' ); ?></div>
							<div class="w91099ch-widget-help-body">
								<div class="w91099ch-widget-help-line"><?php echo esc_html__( 'For full widget functionality, configure your widget in MyPowerly first.', 'w9-1099-chaser' ); ?></div>
								<div class="w91099ch-widget-help-line"><?php echo esc_html__( 'The widget can include Live Chat, AI Chat, and a My Services section (depending on your MyPowerly settings).', 'w9-1099-chaser' ); ?></div>
							</div>
						</div>

						<a href="<?php echo esc_url( admin_url( 'admin.php?page=w91099ch' ) ); ?>" class="mp-btn-secondary flex items-center gap-3">
							<i class="fas fa-arrow-left"></i>
							<span><?php echo esc_html__( 'Back to Dashboard', 'w9-1099-chaser' ); ?></span>
						</a>
						<a href="<?php echo esc_url( $w91099ch_widget_page_url ); ?>" class="mp-btn-secondary flex items-center gap-3">
							<i class="fas fa-rotate-right"></i>
							<span><?php echo esc_html__( 'Refresh', 'w9-1099-chaser' ); ?></span>
						</a>
					</div>
				</div>
			</div>
		</div>
		<?php if ( ! empty( $message ) ) : ?>
			<div class="mb-8">
				<div class="mp-card p-6 <?php echo esc_attr( $message_class === 'success' ? 'border-l-4 border-green-500 bg-green-50/50' : 'border-l-4 border-red-500 bg-red-50/50' ); ?>">
					<div class="flex items-start gap-4">
						<div class="w-12 h-12 rounded-xl <?php echo esc_attr( $message_class === 'success' ? 'bg-green-100' : 'bg-red-100' ); ?> flex items-center justify-center flex-shrink-0">
							<i class="fas <?php echo esc_attr( $message_class === 'success' ? 'fa-circle-check text-green-600' : 'fa-triangle-exclamation text-red-600' ); ?> text-xl"></i>
						</div>
						<div class="flex-1">
							<h3 class="text-lg font-bold text-gray-800 mb-1">
								<?php
									$w91099ch_message_heading = ( $message_class === 'success' )
										? __( 'Success!', 'w9-1099-chaser' )
										: __( 'Error', 'w9-1099-chaser' );
									echo esc_html( $w91099ch_message_heading );
								?>
							</h3>
							<div class="text-gray-700">
								<?php echo esc_html( $message ); ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( empty( $is_connected ) ) : ?>
			<div class="mp-card p-8">
				<div class="mp-card-header">
					<div>
						<h3 class="text-2xl font-bold text-gray-800 mb-2 flex items-center gap-3">
							<div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center">
								<i class="fas fa-plug text-white text-lg"></i>
							</div>
							<?php echo esc_html__( 'Connect to MyPowerly to access Widgets', 'w9-1099-chaser' ); ?>
						</h3>
						<p class="mp-section-subtitle">
							<?php echo esc_html__( 'Widget settings and code generation require an active connection.', 'w9-1099-chaser' ); ?>
						</p>
					</div>
				</div>

				<div class="p-6 bg-blue-50 rounded-xl border border-blue-200">
					<div class="flex items-start gap-3">
						<i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
						<div class="text-sm text-gray-700">
							<p class="font-semibold text-gray-900 mb-2"><?php echo esc_html__( 'How to enable the Widget', 'w9-1099-chaser' ); ?></p>
							<p class="mb-3"><?php echo wp_kses_post( __( '1) Go to the Vendor Onboarding W9-1099 Chaser by Mypowerly dashboard and click <strong>Connect to MyPowerly</strong>.', 'w9-1099-chaser' ) ); ?></p>
							<p class="mb-3"><?php echo esc_html__( '2) Complete the authorization in MyPowerly.', 'w9-1099-chaser' ); ?></p>
							<p class="mb-0"><?php echo esc_html__( '3) In MyPowerly settings, configure your widget and generate the embed code.', 'w9-1099-chaser' ); ?></p>
						</div>
					</div>
				</div>

				<div class="mt-6 flex flex-wrap items-center gap-3">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=w91099ch' ) ); ?>" class="mp-btn-primary flex items-center gap-3">
						<i class="fas fa-arrow-right"></i>
						<span><?php echo esc_html__( 'Go to Dashboard & Connect', 'w9-1099-chaser' ); ?></span>
					</a>
				</div>
			</div>
		<?php else : ?>

			<div class="mp-card p-8">
				<form method="post">
					<?php wp_nonce_field( w91099ch_Widget_Manager::NONCE_SAVE_ACTION, w91099ch_Widget_Manager::NONCE_SAVE_NAME ); ?>

					<div class="space-y-10">
						<div class="p-6 bg-blue-50 rounded-xl border border-blue-200">
							<div class="flex items-start gap-3">
								<i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
								<div class="text-sm text-gray-700">
									<p class="font-semibold text-gray-900 mb-2"><?php echo esc_html__( 'Widget Instructions', 'w9-1099-chaser' ); ?></p>
									<p class="mb-4"><?php echo esc_html__( 'Set up your forms and widget configuration in MyPowerly first, then generate and save your embed code here.', 'w9-1099-chaser' ); ?></p>

									<p class="font-semibold text-gray-900 mb-2"><?php echo esc_html__( 'Step 1: Set up your domain and widget app in MyPowerly', 'w9-1099-chaser' ); ?></p>
									<ol class="list-decimal list-inside space-y-1 mb-4">
										<li><?php echo esc_html__( 'Log in to MyPowerly, then go to Workspace Management.', 'w9-1099-chaser' ); ?></li>
										<li><?php echo esc_html__( 'Select your workspace, then click Apps.', 'w9-1099-chaser' ); ?></li>
										<li><?php echo esc_html__( 'Click Add App, choose Widgets, then in Widgets choose MyPowerly Integration, then enter your domain (or register a new domain).', 'w9-1099-chaser' ); ?></li>
									</ol>

									<p class="font-semibold text-gray-900 mb-2"><?php echo esc_html__( 'Step 2: Configure forms in Wagtail', 'w9-1099-chaser' ); ?></p>
									<ol class="list-decimal list-inside space-y-1 mb-4">
										<li><?php echo esc_html__( 'Log in or sign up to Wagtail from your MyPowerly workspace.', 'w9-1099-chaser' ); ?></li>
										<li><?php echo esc_html__( 'Click Add Form to create your W-9, 1099, ACH, or POA forms.', 'w9-1099-chaser' ); ?></li>
										<li><?php echo esc_html__( 'After you sign up and create forms in Wagtail, the forms you create will show in your widget.', 'w9-1099-chaser' ); ?></li>
									</ol>

									<p class="font-semibold text-gray-900 mb-2"><?php echo esc_html__( 'Step 3: Customize Widget Settings', 'w9-1099-chaser' ); ?></p>
									<ol class="list-decimal list-inside space-y-1 mb-0">
										<li><?php echo esc_html__( 'Choose which forms are visible.', 'w9-1099-chaser' ); ?></li>
										<li><?php echo esc_html__( 'Enable or disable AI Chat and Live Chat.', 'w9-1099-chaser' ); ?></li>
										<li><?php echo esc_html__( 'Customize the widget appearance and behavior.', 'w9-1099-chaser' ); ?></li>
									</ol>
								</div>
							</div>
						</div>

						<!-- Widget Code Section -->
						<div class="mp-card-header">
							<div>
								<h3 class="text-2xl font-bold text-gray-800 mb-2 flex items-center gap-3">
									<div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center">
										<i class="fas fa-code text-white text-lg"></i>
									</div>
									<?php echo esc_html__( 'Widget Code', 'w9-1099-chaser' ); ?>
								</h3>
								<p class="mp-section-subtitle">
									<?php echo esc_html__( 'Generate optimized widget code or paste your custom HTML', 'w9-1099-chaser' ); ?>
								</p>
							</div>
						</div>

						<div class="space-y-6">
							<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 p-6 bg-gradient-to-r from-purple-50 to-indigo-50 rounded-xl border border-purple-200">
								<div class="flex-1">
									<h4 class="font-semibold text-gray-800 mb-2"><?php echo esc_html__( 'Smart Code Generation', 'w9-1099-chaser' ); ?></h4>
									<p class="text-sm text-gray-600">
										<?php echo esc_html__( 'Create production-ready widget code with one click. Includes responsive design and optimal performance.', 'w9-1099-chaser' ); ?>
									</p>
								</div>
								<button type="button" id="w9-1099-chaser-generate-widget-code" class="mp-btn-primary flex items-center gap-3 whitespace-nowrap">
									<i class="fas fa-bolt"></i>
									<span><?php echo esc_html__( 'Generate Widget Code', 'w9-1099-chaser' ); ?></span>
								</button>
							</div>

							<div>
								<label class="block text-sm font-semibold text-gray-700 mb-3">
									<i class="fas fa-code mr-2 text-purple-600"></i>
									<?php echo esc_html__( 'Widget HTML Code', 'w9-1099-chaser' ); ?>
								</label>
								<textarea name="w91099ch_widget_code" id="w91099ch_widget_code" rows="12" class="mp-input" placeholder="<?php echo esc_attr__( 'Your widget code will appear here...', 'w9-1099-chaser' ); ?>" readonly="readonly" spellcheck="false" autocapitalize="off" autocomplete="off" autocorrect="off"><?php echo esc_textarea( $code ); ?></textarea>

								<!-- Widget Script Display Box -->
								<div class="mt-4 p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg border border-green-200">
									<div class="flex items-start gap-3">
										<i class="fas fa-search text-green-600 mt-0.5"></i>
										<div class="text-sm w-full">
											<p class="font-medium text-green-800 mb-2">Widget Script (Search Box)</p>
											<div class="relative">
												<input type="text" id="w91099ch_widget_script_display" class="mp-input bg-white" placeholder="Widget script will appear here..." readonly="readonly" style="font-family: monospace; font-size: 12px;" />
												<button type="button" id="w91099ch_copy_script" class="absolute right-2 top-1/2 transform -translate-y-1/2 px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 transition-colors">
													<i class="fas fa-copy mr-1"></i>Copy
												</button>
											</div>
											<p class="text-green-700 mt-2 text-xs">This is the actual script tag that will be embedded on your site.</p>
										</div>
									</div>
								</div>

								<div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
									<div class="flex items-start gap-3">
										<i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
										<div class="text-sm">
											<p class="font-medium text-blue-800 mb-1">Shortcode Usage</p>
											<p class="text-blue-700">
												Set <strong>Widget Display Mode</strong> to <strong>Shortcode only</strong>, then place this widget anywhere with:
												<code class="mp-code ml-1">[<?php echo esc_html( w91099ch_Widget_Manager::SHORTCODE ); ?>]</code>
											</p>
											<p class="text-blue-700 mt-2">
												<code class="mp-code">[<?php echo esc_html( w91099ch_Widget_Manager::SHORTCODE ); ?>]</code>
											</p>
											<p class="text-blue-700 mt-2">
												If display mode is set to <strong>Auto</strong> or <strong>Selected pages</strong>, you do not need to add the shortcode.
											</p>
										</div>
									</div>
								</div>
							</div>
						</div>

						<!-- Display Options Section -->
						<div class="mp-card-header">
							<div>
								<h3 class="text-2xl font-bold text-gray-800 mb-2 flex items-center gap-3">
									<div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center">
										<i class="fas fa-eye text-white text-lg"></i>
									</div>
									Display Options
								</h3>
								<p class="mp-section-subtitle">
									Control where and how your widget appears on the site
								</p>
							</div>
						</div>

						<div class="space-y-4">
							<label class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50/50 cursor-pointer transition-all duration-200">
								<input type="radio" name="w91099ch_widget_display_mode" value="auto" <?php checked( 'auto', $display_mode ); ?> class="mp-radio mt-1">
								<div class="flex-1">
									<div class="font-semibold text-gray-800 mb-1">
										<i class="fas fa-globe mr-2 text-blue-600"></i>
										Auto display on all pages
									</div>
									<div class="text-sm text-gray-600">Widget automatically appears on every frontend page of your site.</div>
								</div>
							</label>

							<label class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50/50 cursor-pointer transition-all duration-200">
								<input type="radio" name="w91099ch_widget_display_mode" value="selected" <?php checked( 'selected', $display_mode ); ?> class="mp-radio mt-1">
								<div class="flex-1">
									<div class="font-semibold text-gray-800 mb-1">
										<i class="fas fa-list-check mr-2 text-blue-600"></i>
										Display on selected pages only
									</div>
									<div class="text-sm text-gray-600">Widget appears only on the specific pages you choose below.</div>

									<div id="w9-1099-chaser-pages-selector" style="<?php echo esc_attr( ( $display_mode !== 'selected' ) ? 'display:none;' : '' ); ?>" class="mt-6">
										<div class="p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200">
											<label class="block text-sm font-semibold text-gray-700 mb-3">
												<i class="fas fa-file-lines mr-2 text-blue-600"></i>
												Select Pages
											</label>

											<div class="relative" id="w91099ch_pages_dropdown">
												<button type="button" id="w91099ch_pages_dropdown_btn" class="mp-input mp-select w-full flex items-center justify-between" aria-haspopup="listbox" aria-expanded="false">
													<span id="w91099ch_pages_dropdown_label" class="text-gray-700">Select pages</span>
													<span class="flex items-center gap-2">
														<span id="w91099ch_pages_selected_count" class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded bg-white border text-sm" style="border-color: var(--mp-gray-200);">0</span>
														<i class="fas fa-chevron-down text-gray-500"></i>
													</span>
												</button>

												<div id="w91099ch_pages_dropdown_panel" class="hidden absolute z-40 mt-2 w-full bg-white rounded-xl border shadow-lg" style="border-color: var(--mp-gray-200);">
													<div class="p-4 border-b" style="border-color: var(--mp-gray-200);">
														<input type="text" id="w91099ch_pages_search" class="mp-input" placeholder="Search pages..." autocomplete="off" />
														<div class="mt-3 flex flex-wrap items-center gap-2">
															<button type="button" id="w91099ch_pages_select_all" class="mp-btn-secondary flex items-center gap-2">
																<i class="fas fa-check-double"></i>
																<span>Select all</span>
															</button>
															<button type="button" id="w91099ch_pages_clear" class="mp-btn-secondary flex items-center gap-2">
																<i class="fas fa-eraser"></i>
																<span>Clear</span>
															</button>
														</div>
													</div>

													<div class="max-h-72 overflow-auto p-2">
														<?php foreach ( $pages as $w91099ch_page ) : ?>
															<?php
																$w91099ch_pid        = (int) $w91099ch_page->ID;
																$w91099ch_ptitle     = isset( $w91099ch_page->post_title ) ? (string) $w91099ch_page->post_title : '';
																$w91099ch_is_checked = in_array( $w91099ch_pid, $selected_pages, true );
															?>
															<label class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 cursor-pointer">
																<input type="checkbox" class="w91099ch-ch-page-checkbox" data-title="<?php echo esc_attr( strtolower( $w91099ch_ptitle ) ); ?>" name="w91099ch_widget_selected_pages[]" value="<?php echo esc_attr( $w91099ch_pid ); ?>" <?php checked( $w91099ch_is_checked, true ); ?> />
																<span class="text-sm text-gray-800"><?php echo esc_html( $w91099ch_ptitle ); ?></span>
															</label>
														<?php endforeach; ?>
													</div>
												</div>
											</div>

											<div id="w91099ch_pages_selected_list" class="mt-3 flex flex-wrap gap-2"></div>
										</div>
									</div>
								</div>
							</label>

							<label class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50/50 cursor-pointer transition-all duration-200">
								<input type="radio" name="w91099ch_widget_display_mode" value="shortcode" <?php checked( 'shortcode', $display_mode ); ?> class="mp-radio mt-1">
								<div class="flex-1">
									<div class="font-semibold text-gray-800 mb-1">
										<i class="fas fa-hashtag mr-2 text-blue-600"></i>
										Shortcode only
									</div>
									<div class="text-sm text-gray-600">Widget appears only where you manually place the shortcode.</div>
								</div>
							</label>

						</div>

						<!-- Widget Position Section -->
						<div class="mp-card-header">
							<div>
								<h3 class="text-2xl font-bold text-gray-800 mb-2 flex items-center gap-3">
									<div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center">
										<i class="fas fa-location-dot text-white text-lg"></i>
									</div>
									Widget Position
								</h3>
								<p class="mp-section-subtitle">
									Choose the optimal placement for your widget
								</p>
							</div>
						</div>

						<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
							<label class="flex items-center gap-4 p-6 rounded-xl border border-gray-200 hover:border-green-300 hover:bg-green-50/50 cursor-pointer transition-all duration-200">
								<input type="radio" name="w91099ch_widget_position" value="bottom-right" <?php checked( 'bottom-right', $position ); ?> class="mp-radio">
								<div class="flex-1">
									<div class="font-semibold text-gray-800 mb-1">
										<i class="fas fa-arrow-down-right mr-2 text-green-600"></i>
										Bottom Right
									</div>
									<div class="text-sm text-gray-600">Traditional placement, works well for most sites</div>
								</div>
							</label>

							<label class="flex items-center gap-4 p-6 rounded-xl border border-gray-200 hover:border-green-300 hover:bg-green-50/50 cursor-pointer transition-all duration-200">
								<input type="radio" name="w91099ch_widget_position" value="bottom-left" <?php checked( 'bottom-left', $position ); ?> class="mp-radio">
								<div class="flex-1">
									<div class="font-semibold text-gray-800 mb-1">
										<i class="fas fa-arrow-down-left mr-2 text-green-600"></i>
										Bottom Left
									</div>
									<div class="text-sm text-gray-600">Alternative placement, avoids right-side conflicts</div>
								</div>
							</label>
						</div>

						<!-- Save Section -->
						<div class="pt-8 flex items-center justify-between -mx-8 px-8 py-6 rounded-b-xl">
							<div class="text-sm text-gray-600">
							</div>
							<button type="submit" class="mp-btn-primary flex items-center gap-3">
								<i class="fas fa-save"></i>
								<span>Save Settings</span>
							</button>
						</div>
					</div>
				</form>
			</div>

		</div>
	</div>
</div>

		<?php endif; ?>

