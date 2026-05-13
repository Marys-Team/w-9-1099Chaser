<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_connected            = $this->core->is_connected();
$credentials             = $this->core->get_credentials();
$connection_error        = get_transient( 'w91099ch_connection_error' );
$connection_success      = get_transient( 'w91099ch_connection_success' );
$newsletter_subscribed   = (bool) get_option( 'w91099ch_newsletter_subscribed', false );
$w9_default_page_id      = absint( get_option( 'w91099ch_w9_default_page_id', 0 ) );
$w9_default_page_url     = $w9_default_page_id ? (string) get_permalink( $w9_default_page_id ) : '';
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 to-blue-50 mp-shell">
 	<!-- Header -->
 	<div class="relative bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 overflow-hidden mb-12 mp-hero">
 		<div class="absolute inset-0 opacity-10">
 			<div class="absolute inset-0" style="background-image: linear-gradient(to right, #4f46e5 1px, transparent 1px), linear-gradient(to bottom, #4f46e5 1px, transparent 1px); background-size: 50px 50px;"></div>
 		</div>
 
 		<div class="absolute top-1/4 left-1/4 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl animate-pulse"></div>
 		<div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
 
 		<div class="relative z-10">
 			<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
 				<div class="py-8 md:py-12">
 					<div class="grid grid-cols-1 md:grid-cols-3 items-center gap-4 mb-8">
 						<div class="flex items-center justify-center md:justify-start">
 							<div class="w-40 h-40 overflow-hidden rounded-full">
 								<img src="<?php echo esc_url( w91099ch_PLUGIN_URL . 'assets/logo/logo-removebg-preview.png' ); ?>" alt="Vendor Onboarding W9-1099 Chaser by Mypowerly" style="width: 100%; height: 100%; object-fit: cover; display: block; transform: scale(1.15); transform-origin: center;" />
 							</div>
 						</div>
 
 						<div class="flex justify-center">
 							<?php if ( $is_connected && ! empty( $credentials ) ) : ?>
 								<div class="inline-flex items-center gap-3 px-8 py-4 bg-emerald-400/25 backdrop-blur-xl rounded-full border border-emerald-300/90 shadow-2xl ring-1 ring-emerald-300/30" style="box-shadow: 0 20px 52px #03b87bff;" title="Connected with Mypowerly" aria-label="Connected with Mypowerly">
 									<i class="fas fa-circle-check" style="font-size: 18px; color: #a7f3d0;" aria-hidden="true"></i>
 									<span class="font-extrabold text-white" style="letter-spacing: 0.02em;">Live Connection</span>
 									<span class="text-emerald-50" style="opacity: 0.95;" aria-hidden="true">&bull;</span>
 									<span class="text-emerald-50" style="color: #ffffff; opacity: 1;">Connected with Mypowerly</span>
 									<i class="fas fa-circle-info" style="font-size: 14px; color: #d1fae5; opacity: 0.9;" aria-hidden="true"></i>
 								</div>
 							<?php else : ?>
 								<div class="inline-flex items-center gap-3 px-8 py-4 bg-red-400/25 backdrop-blur-xl rounded-full border border-red-300/90 shadow-2xl ring-1 ring-red-300/30" style="box-shadow: 0 20px 52px #ef4444ff;" title="Not connected to Mypowerly" aria-label="Not connected to Mypowerly">
 									<i class="fas fa-circle-xmark" style="font-size: 18px; color: #fecaca;" aria-hidden="true"></i>
 									<span class="font-extrabold text-white" style="letter-spacing: 0.02em;">Not Connected</span>
 									<span class="text-red-50" style="opacity: 0.95;" aria-hidden="true">&bull;</span>
 									<span class="text-red-50" style="color: #ffffff; opacity: 1;">Connect to Mypowerly</span>
 									<i class="fas fa-plug" style="font-size: 14px; color: #fee2e2; opacity: 0.9;" aria-hidden="true"></i>
 								</div>
 							<?php endif; ?>
 						</div>
 
 						<div class="flex flex-col items-center md:items-end gap-3 pr-2 md:pr-6" style="padding-right: 32px;">
							<div class="flex items-center justify-center md:justify-end gap-3 flex-wrap">
								<a href="https://1099automation.com" target="_blank" rel="noopener noreferrer" class="mp-btn-secondary mp-hero-action">
									<i class="fas fa-globe" aria-hidden="true"></i>
									Visit 1099automation.com
								</a>
								<a href="https://mypowerly.com" target="_blank" rel="noopener noreferrer" class="mp-btn-primary mp-hero-action">
									<i class="fas fa-up-right-from-square" aria-hidden="true"></i>
									Go to MyPowerly
								</a>
								<?php if ( $is_connected && ! empty( $credentials ) ) : ?>
									<button type="button" class="mp-btn-secondary mp-hero-action" id="disconnect-mypowerly">
										<i class="fas fa-right-from-bracket" aria-hidden="true"></i>
										Disconnect
									</button>
								<?php endif; ?>
							</div>
						</div>
					</div>
 
 					<div class="text-center max-w-4xl mx-auto mp-hero-inner">
 						<h1 class="text-5xl md:text-7xl font-bold mb-6 tracking-tight mp-page-title">
 							<span class="bg-clip-text text-transparent bg-gradient-to-r from-white via-blue-200 to-indigo-200">
 								Dashboard
 							</span>
 							<br>
 						</h1>
 
 						<p class="text-xl text-gray-300 mb-8 leading-relaxed max-w-2xl mx-auto">
 							Free W-9 form generator and settings.
 						</p>
 
 						<div class="w-full flex justify-center mt-6">
 							<div class="w-full max-w-md">
 								<?php w9_1099_chaser_render_header_support( $is_connected && ! empty( $credentials ) ? 'connected' : 'disconnected' ); ?>
 							</div>
 						</div>
 					</div>
 				</div>
 			</div>
 		</div>
 
 		<div class="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-gray-900 to-transparent"></div>
 	</div>

	<div class="max-w-screen-2xl mx-auto px-3 sm:px-4 lg:px-6 pb-12">
		<div id="w9-top-anchor"></div>

		<?php if ( $connection_error && ( ! $is_connected || empty( $credentials ) ) && ! $connection_success ) : ?>
			<!-- Error Notice -->
			<div class="mp-card mb-8 border-l-4 border-red-500">
				<div class="p-6">
					<div class="flex items-start gap-4">
						<div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center">
							<i class="fas fa-triangle-exclamation text-2xl text-red-600"></i>
						</div>
						<div class="flex-1">
							<h3 class="text-xl font-bold text-gray-800 mb-2">Connection Failed</h3>
							<div class="mb-6">
								<p class="text-gray-700"><span class="font-semibold">Error:</span> <?php echo esc_html( $connection_error ); ?></p>
							</div>

							<div class="flex flex-wrap gap-4">
								<button type="button" class="mp-btn-primary" onclick="window.location.href='<?php echo esc_js( esc_url( admin_url( 'options-general.php?page=w91099ch' ) ) ); ?>'">
									<i class="fas fa-rotate-right mr-2"></i>Try Again
								</button>
								<button type="button" class="mp-btn-secondary" onclick="window.location.reload()">
									<i class="fas fa-sync mr-2"></i>Reload Page
								</button>
								<a href="https://mypowerly.com/v1/support" target="_blank" rel="noopener noreferrer" class="mp-btn-secondary">
									<i class="fas fa-headset mr-2"></i>Contact Support
								</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<div class="mb-10" id="w9-form-section">
			<div class="text-center mb-10">
				<div class="flex items-center justify-center gap-4 flex-wrap mb-4">
					<h2 class="text-3xl md:text-4xl font-bold text-gray-800"> Free W-9 Form Generator</h2>
					<a id="w9-1099-chaser-open-widget-settings" href="<?php echo esc_url( admin_url( 'admin.php?page=w91099ch-widget' ) ); ?>" class="mp-btn-secondary flex items-center gap-3" style="margin-left: 12px;" title="<?php echo esc_attr( $is_connected ? __( 'Connected to Mypowerly — collect W-9 forms through a secure website using the W-9 Chaser Widget.', 'w9-1099-chaser' ) : __( 'Install W9 Chaser, connect to Mypowerly, and collect W-9 forms through a secure website.', 'w9-1099-chaser' ) ); ?>" aria-label="<?php echo esc_attr( $is_connected ? __( 'Connected to Mypowerly — collect W-9 forms through a secure website using the W-9 Chaser Widget.', 'w9-1099-chaser' ) : __( 'Install W9 Chaser, connect to Mypowerly, and collect W-9 forms through a secure website.', 'w9-1099-chaser' ) ); ?>">
						<i class="fas fa-comments"></i>
						W-9 Chaser Widget
					</a>
				</div>
				<p class="text-gray-600 text-lg max-w-3xl mx-auto">
					Fill out the W-9 form below and download unlimited completed PDF. Share it with anyone. Form fields are not saved in WordPress database for your safety and protection.
				</p>
			</div>

			<!-- W9 Shortcode Information -->
			<div class="mp-card p-6 mb-8" style="border-left: 4px solid #10b981;">
				<div class="flex items-start gap-4">
					<div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center flex-shrink-0">
						<i class="fas fa-code text-2xl text-green-600"></i>
					</div>
					<div class="flex-1">
						<h3 class="text-xl font-bold text-gray-800 mb-2">Use W-9 Form on Your Frontend Pages / either public or private</h3>
						<p class="text-gray-600 mb-4">Add this shortcode to any page or post to display the W-9 form for your visitors:</p>
						<?php if ( ! $newsletter_subscribed ) : ?>
							<div class="mb-4 p-4 rounded-lg border" style="background: #fff7ed; border-color: #fed7aa;">
								<div class="text-sm font-semibold" style="color: #9a3412;">First subscribe to the newsletter to unlock this.</div>
								<div class="text-xs mt-1" style="color: #9a3412; opacity: .9;">After subscribing, you can copy the shortcode and configure display options.</div>
							</div>
						<?php endif; ?>

						<div class="flex items-center gap-2 mb-4">
							<input id="w9-shortcode-copy" type="text" readonly value="[w91099ch_w9_form]" class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm font-mono bg-gray-50" />
							<button type="button" onclick="copyToClipboard('#w9-shortcode-copy')" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md text-sm font-medium transition-colors" <?php echo ! $newsletter_subscribed ? 'disabled="disabled" aria-disabled="true" style="opacity:.55;cursor:not-allowed;"' : ''; ?> >
								<i class="far fa-copy"></i> Copy
							</button>
						</div>

						<div class="bg-gray-50 p-5 rounded-xl border border-gray-200 mb-6">
							<h4 class="font-bold text-gray-800 mb-2">Choose how you want to use this form (3 modes):</h4>
							<p class="text-sm text-gray-600 mb-4">Create a page and paste the shortcode. Then choose one of the modes below.</p>
							<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
								<div class="bg-white p-4 rounded-lg border border-gray-200">
									<div class="mb-2">
										<h5 class="font-semibold text-gray-800">Public mode</h5>
									</div>
									<ul class="text-sm text-gray-700 space-y-1">
										<li>• Best for a public W-9 download page.</li>
										<li>• Add the shortcode: <span class="font-mono">[w91099ch_w9_form]</span></li>
										<li>• Publish the page (Visibility: Public).</li>
										<li>• Share the page URL with your visitors.</li>
									</ul>
									<div class="flex justify-end mt-3">
										<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page&w91099ch_w9_mode=public' ) ); ?>" class="mp-btn-primary" style="font-size: 12px; padding: 6px 12px;">
											<i class="fas fa-external-link-alt" style="margin-right: 6px;"></i>
											Go to page
										</a>
									</div>
								</div>

								<div class="bg-white p-4 rounded-lg border border-gray-200">
									<div class="mb-2">
										<h5 class="font-semibold text-gray-800">Private mode</h5>
									</div>
									<ul class="text-sm text-gray-700 space-y-1">
										<li>• Only logged-in admins/editors can view it.</li>
										<li>• Add the shortcode: <span class="font-mono">[w91099ch_w9_form]</span></li>
										<li>• In Page settings, set Visibility to <strong>Private</strong>.</li>
										<li>• Useful for internal use/testing.</li>
									</ul>
									<div class="flex justify-end mt-3">
										<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page&w91099ch_w9_mode=private' ) ); ?>" class="mp-btn-primary" style="font-size: 12px; padding: 6px 12px;">
											<i class="fas fa-external-link-alt" style="margin-right: 6px;"></i>
											Go to page
										</a>
									</div>
								</div>

								<div class="bg-white p-4 rounded-lg border border-gray-200">
									<div class="mb-2">
										<h5 class="font-semibold text-gray-800">Password protected mode</h5>
									</div>
									<ul class="text-sm text-gray-700 space-y-1">
										<li>• Visitors must enter a password to unlock the page.</li>
										<li>• Add the shortcode: <span class="font-mono">[w91099ch_w9_form]</span></li>
										<li>• In Page settings, set Visibility to <strong>Password protected</strong>.</li>
										<li>• After the password is entered, the W-9 form will show.</li>
									</ul>
									<div class="flex justify-end mt-3">
										<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page&w91099ch_w9_mode=protected' ) ); ?>" class="mp-btn-primary" style="font-size: 12px; padding: 6px 12px;">
											<i class="fas fa-external-link-alt" style="margin-right: 6px;"></i>
											Go to page
										</a>
									</div>
								</div>
							</div>
						</div>

					<!-- W9 Form Display Settings -->
					<div class="bg-orange-50 p-6 rounded-xl border border-orange-200">
						<h4 class="font-bold text-orange-800 mb-3 flex items-center gap-2">
							<i class="fas fa-desktop"></i>
							<?php echo esc_html__( 'Advanced Display Settings:', 'w9-1099-chaser' ); ?>
						</h4>

						<div class="space-y-4">
							<p class="text-sm text-orange-700">
								<?php echo esc_html__( 'You can now control where the W-9 form appears from the plugin settings.', 'w9-1099-chaser' ); ?>
							</p>

							<div class="flex flex-wrap gap-3">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=w91099ch-settings&w91099ch_settings_tab=w9-display' ) ); ?>" class="mp-btn-primary" style="font-size: 13px; padding: 8px 16px;<?php echo ! $newsletter_subscribed ? 'opacity:.55;cursor:not-allowed;pointer-events:none;' : ''; ?>" <?php echo ! $newsletter_subscribed ? 'aria-disabled="true" tabindex="-1"' : ''; ?> >
									<i class="fas fa-cog mr-2"></i>
									<?php echo esc_html__( 'Configure Display Options', 'w9-1099-chaser' ); ?>
								</a>
							</div>

							<div class="mt-4 pt-4 border-t border-orange-200">
								<ul class="text-xs text-orange-700 space-y-2">
									<li class="flex items-center gap-2">
										<i class="fas fa-check-circle text-orange-500"></i>
										<span><?php echo esc_html__( 'Auto-display on all frontend pages', 'w9-1099-chaser' ); ?></span>
									</li>
									<li class="flex items-center gap-2">
										<i class="fas fa-check-circle text-orange-500"></i>
										<span><?php echo esc_html__( 'Target specific pages only', 'w9-1099-chaser' ); ?></span>
									</li>
									<li class="flex items-center gap-2">
										<i class="fas fa-check-circle text-orange-500"></i>
										<span><?php echo esc_html__( 'Manual shortcode placement', 'w9-1099-chaser' ); ?></span>
									</li>
								</ul>
							</div>
						</div>
					</div>

					<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
						<!-- Download Stats -->
						<div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm col-span-1 md:col-span-2 lg:col-span-3">
							<h4 class="font-bold text-gray-800 mb-3 flex items-center justify-between gap-3">
								<span class="flex items-center gap-2">
									<i class="fas fa-chart-line text-blue-600"></i>
									<?php echo esc_html__( 'Form Download Statistics', 'w9-1099-chaser' ); ?>
								</span>
								<button
									type="button"
									id="w91099ch-reset-download-stats"
									class="mp-btn-secondary"
									style="font-size: 12px; padding: 6px 12px; border-color: #fecaca; color: #b91c1c;"
									data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( 'w91099ch_nonce' ) ); ?>"
								>
									<i class="fas fa-trash-alt" style="margin-right: 6px;"></i>
									<?php echo esc_html__( 'Reset', 'w9-1099-chaser' ); ?>
								</button>
							</h4>
							<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
								<div class="bg-blue-50 p-3 rounded-lg border border-blue-100 text-center">
									<div class="text-2xl font-bold text-blue-700"><span id="w91099ch-total-downloads-count"><?php echo esc_html( get_option( 'w91099ch_total_downloads', 0 ) ); ?></span></div>
									<div class="text-xs text-blue-600 uppercase tracking-wider font-semibold"><?php echo esc_html__( 'Total Downloads', 'w9-1099-chaser' ); ?></div>
								</div>
								<div class="bg-indigo-50 p-3 rounded-lg border border-indigo-100 text-center">
									<div class="text-2xl font-bold text-indigo-700"><span id="w91099ch-print-to-pdf-count"><?php echo esc_html( get_option( 'w91099ch_downloads_print_to_pdf', 0 ) ); ?></span></div>
									<div class="text-xs text-indigo-600 uppercase tracking-wider font-semibold"><?php echo esc_html__( 'Print to PDF', 'w9-1099-chaser' ); ?></div>
								</div>
								<div class="bg-purple-50 p-3 rounded-lg border border-purple-100 text-center">
									<div class="text-2xl font-bold text-purple-700"><span id="w91099ch-official-forms-count"><?php echo esc_html( get_option( 'w91099ch_downloads_govt_form', 0 ) ); ?></span></div>
									<div class="text-xs text-purple-600 uppercase tracking-wider font-semibold"><?php echo esc_html__( 'Official Forms', 'w9-1099-chaser' ); ?></div>
								</div>
							</div>
						</div>
					</div>

				</div>
			</div>
		</div>

		<div class="mp-card p-8">
			<form id="mypowerly-w9-form" class="mypowerly-w9-form">
				<?php wp_nonce_field( 'w91099ch_w9_form_submit', 'w91099ch_w9_nonce' ); ?>
				<div class="space-y-8">
					<!-- Section 1: Name and Business Information -->
					<div class="border-b border-gray-200 pb-8">
						<h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-3">
							<div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
								<i class="fas fa-user text-blue-600"></i>
							</div>
							1. Name and Business Information
						</h3>

						<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-2">
									Name (as shown on your tax return) <span class="text-red-500">*</span>
								</label>
								<input type="text" id="name" name="name" required
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
							</div>

							<div>
								<label class="block text-sm font-medium text-gray-700 mb-2">
									Business name/disregarded entity name
								</label>
								<input type="text" id="business_name" name="business_name"
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
							</div>
						</div>

						<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-2">
									Federal tax classification <span class="text-red-500">*</span>
								</label>
								<select id="federal_tax_classification" name="federal_tax_classification" required
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
									<option value="">Select One...</option>
									<option value="individual">Individual / sole proprietor</option>
									<option value="c_corp">C Corporation</option>
									<option value="s_corp">S Corporation</option>
									<option value="partnership">Partnership</option>
									<option value="trust">Trust/estate</option>
									<option value="llc">Limited liability company (LLC)</option>
									<option value="other">Other (see instructions)</option>
								</select>
							</div>

							<div id="llc_classification_container" style="display: none;">
								<label class="block text-sm font-medium text-gray-700 mb-2">
									LLC classification
								</label>
								<select id="llc_classification" name="llc_classification"
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
									<option value="">Select One...</option>
									<option value="c_corp">C Corporation</option>
									<option value="s_corp">S Corporation</option>
									<option value="partnership">Partnership</option>
								</select>
							</div>

							<div id="other-class-wrapper" style="display: none;">
								<label class="block text-sm font-medium text-gray-700 mb-2">
									Other classification (specify)
								</label>
								<input type="text" id="other_class" name="other_classification"
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
							</div>
						</div>

						<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-2">
									Exempt payee code (if any)
								</label>
								<input type="text" id="exempt_payee_code" name="exempt_payee_code" maxlength="2"
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
							</div>

							<div>
								<label class="block text-sm font-medium text-gray-700 mb-2">
									Exemption from FATCA reporting code (if any)
								</label>
								<input type="text" id="fatca_code" name="fatca_code" maxlength="2"
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
							</div>
						</div>
					</div>

					<!-- Section 2: Address Information -->
					<div class="border-b border-gray-200 pb-8">
						<h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-3">
							<div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
								<i class="fas fa-location-dot text-green-600"></i>
							</div>
							2. Address Information
						</h3>

						<div class="mb-6">
							<label class="block text-sm font-medium text-gray-700 mb-2">
								Address (number, street, and apt. or suite no.) <span class="text-red-500">*</span>
							</label>
							<input type="text" id="address" name="address" required
									class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
						</div>

						<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-2">
									City <span class="text-red-500">*</span>
								</label>
								<input type="text" id="city" name="city" required
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
							</div>

							<div>
								<label class="block text-sm font-medium text-gray-700 mb-2">
									State <span class="text-red-500">*</span>
								</label>
								<input type="text" id="state" name="state" required
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
							</div>

							<div>
								<label class="block text-sm font-medium text-gray-700 mb-2">
									ZIP code <span class="text-red-500">*</span>
								</label>
								<input type="text" id="zip" name="zip" required
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
							</div>
						</div>

						<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-2">
									Requester name and address (optional)
								</label>
								<input type="text" id="requester" name="requester"
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
							</div>

							<div>
								<label class="block text-sm font-medium text-gray-700 mb-2">
									Account number(s) (optional)
								</label>
								<input type="text" id="account_numbers" name="account_numbers"
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
							</div>
						</div>
					</div>

					<!-- Section 3: Taxpayer Identification Number -->
					<div class="border-b border-gray-200 pb-8">
						<h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-3">
							<div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
								<i class="fas fa-id-card text-purple-600"></i>
							</div>
							3. Taxpayer Identification Number (TIN)
						</h3>

						<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-2">
									TIN Type <span class="text-red-500">*</span>
								</label>
								<select id="tin_type" name="tin_type" required
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
									<option value="">Select One...</option>
									<option value="ssn">SSN</option>
									<option value="fein">FEIN</option>
									<option value="itin">ITIN</option>
									<option value="atn">ATIN</option>
								</select>
							</div>

							<div>
								<label class="block text-sm font-medium text-gray-700 mb-2">
									Taxpayer Identification Number <span class="text-red-500">*</span>
								</label>
								<input type="text" id="tin" name="tin" required
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
							</div>
						</div>
					</div>

					<!-- Section 4: Signature -->
					<div>
						<h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-3">
							<div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
								<i class="fas fa-signature text-orange-600"></i>
							</div>
							4. Certification and Signature
						</h3>

						<div class="mb-6 p-4 bg-gray-50 rounded-lg">
							<p class="text-sm text-gray-700 mb-3">Under penalties of perjury, I certify that:</p>
							<ol class="text-sm text-gray-600 list-decimal list-inside space-y-2">
								<li>The number shown on this form is my correct taxpayer identification number</li>
								<li>I am not subject to backup withholding</li>
								<li>I am a U.S. person (including a U.S. resident alien)</li>
							</ol>
						</div>

						<div class="space-y-6">
							<div>
								<label class="block text-sm font-medium text-gray-700 mb-2">
									Signature <span class="text-red-500">*</span>
								</label>
								<div class="signature-pad">
									<div class="signature-pad--body">
										<canvas id="signature-canvas" width="400" height="200"></canvas>
									</div>
									<div class="signature-actions mt-3">
										<button type="button" id="clear-signature" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
											Clear Signature
										</button>
									</div>
									<input type="hidden" id="signature_data" name="signature_data" required>
									<input type="hidden" id="certification_name" name="certification_name" required>
								</div>
								<p class="text-sm text-gray-500 mt-2">Draw your signature above</p>
							</div>
						</div>

						<div>
								Date <span class="text-red-500">*</span>
							</label>
							<input type="date" id="certification_date" name="certification_date" required
									class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
						</div>
					</div>
				</div>

				<!-- Submit / Privacy Section -->
				<div class="mt-8 pt-8 border-t border-gray-200 space-y-4">
					<?php if ( $is_connected ) : ?>
						<div class="p-4 bg-blue-50 rounded-lg border border-blue-200 flex items-start gap-3">
							<input type="checkbox" id="mypowerly-w9-privacy-consent" class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded" />
							<div class="flex-1 text-sm text-gray-700">
								<div class="flex items-center justify-between mb-1">
									<p class="font-semibold text-gray-900">Data storage &amp; privacy</p>
									<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
										<i class="fas fa-circle-check mr-1"></i> Connected to MyPowerly
									</span>
								</div>
								<p class="mb-3 text-gray-700">
									By clicking this button, I consent to sending my W-9 web form data securely to MyPowerly(API Provider) (I acknowledge that my W-9 information is <strong>not stored in WordPress</strong>. The form is completed securely in my browser and is only downloaded as a PDF file.)

									<span class="block text-red-500 font-bold mt-2"><strong>For security and privacy reasons, this plugin never transmits SSN or FEIN information to MyPowerly.</strong></span>
								</p>

								<button type="button" id="mypowerly-w9-sync" class="mp-btn-primary inline-flex items-center gap-2 opacity-60 cursor-not-allowed" disabled>
									<i class="fas fa-cloud-arrow-up"></i>
									<span>Send W-9 data to MyPowerly</span>
								</button>
								<p class="mt-2 text-xs text-gray-500">
									After confirming this notice, you can sync data using your MyPowerly connection.
								</p>
							</div>
						</div>
					<?php endif; ?>
					<div class="flex items-center justify-between gap-4">
						<div id="mypowerly-w9-status" class="mypowerly-w9-status" style="display: none;"></div>
						<div class="flex gap-3">
							<button type="submit" id="mypowerly-w9-download"
									class="mp-btn-primary flex items-center gap-3">
								<i class="fas fa-download"></i>
								Print To PDF
							</button>
							<button type="button" id="mypowerly-govt-form-download"
									class="mp-btn-secondary flex items-center gap-3">
								<i class="fas fa-file-pdf"></i>
								Download Official W9 form
							</button>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>

	<?php if ( ! $is_connected || empty( $credentials ) ) : ?>
		<div class="mp-card p-6 mb-10" id="mypowerly-connect-block" style="border-left: 4px solid var(--mp-primary);">
			<div class="flex flex-col lg:flex-row items-start lg:items-center gap-6">
				<div class="flex-1">
					<div class="flex items-start gap-4">
						<div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
							<i class="fas fa-cloud-arrow-up text-2xl" style="color: var(--mp-primary);"></i>
						</div>
						<div>
							<h3 class="text-xl font-bold text-gray-800 mb-1">W9-1099-Chaser: Save this form & unlock more Benefits</h3>
							<p class="text-gray-600">If you want to save this W-9 form data and get more benifitss, connect your site to Mypowerly.</p>
							<p class="text-red-500 font-bold mt-2"><strong>For security and privacy reasons, this plugin never transmits SSN or FEIN information to MyPowerly.</strong></p>
						</div>
					</div>

					<div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
						<div class="text-sm text-gray-700 mb-3">This W-9 form data is <strong>not stored in WordPress</strong> by default. If you want to <strong>securely send and store</strong> your data (and your profile, affiliates, users data) in Mypowerly, click <strong>Connect to Mypowerly</strong>.</div>
						<label class="flex items-start gap-3 cursor-pointer">
							<input type="checkbox" id="mypowerly-consent" class="mt-1" />
							<span class="text-sm text-gray-700">I understand that connecting will securely sync and store My WordPress data in Mypowerly to unlock additional features.</span>
						</label>

						<label class="flex items-start gap-3 cursor-pointer mt-3">
							<input type="checkbox" id="mypowerly-auto-sync-on-connect" class="mt-1" <?php checked( get_option( 'w91099ch_auto_sync_on_connect', false ) ); ?> />
							<span class="text-sm text-gray-700">Automatically sync all data to Mypowerly right after connecting.</span>
						</label>

						<div class="mt-4">
							<div class="text-sm font-semibold text-gray-800 mb-2">Discount Code (optional)</div>
							<div class="flex items-center gap-3">
								<input type="text" id="mypowerly-discount-code" class="mp-input" placeholder="Discount code" autocomplete="off" value="1AB79K37AAA7" disabled data-preapplied="1" />
								<button type="button" id="mypowerly-apply-discount" class="mp-btn-secondary" disabled>Apply</button>
							</div>
							<div id="mypowerly-applied-discount" class="mt-3">
								<span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-green-50 text-green-800 text-sm" style="border: 1px solid rgba(16, 185, 129, 0.35);">
									<i class="fas fa-circle-check" style="opacity:.9"></i>
									<span id="mypowerly-applied-discount-code">1AB79K37AAA7</span>
								</span>
							</div>
							<div id="mypowerly-discount-inline-message" class="text-xs text-green-700 mt-2">A discount code has already been applied</div>
							<div class="text-xs text-gray-500 mt-2">Discount Code can be entered upto 48 hours of connecting. Only a valid Discount code will be accepted</div>
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

<!-- Simple Government Form Button Handler -->
<script>
jQuery(document).ready(function($) {
	// Govt download handled by assets/js/w9-1099-chaser-w9-form.js (PDFLib browser-side fill)
	if (typeof window.w91099chConnectorW9 === 'undefined') {
		window.w91099chConnectorW9 = {
			ajaxurl: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
			nonce: '<?php echo wp_create_nonce( 'w91099ch_w9_form_nonce' ); ?>',
			enableSocialSharing: <?php echo wp_json_encode( get_option( 'w91099ch_enable_social_sharing', false ) ); ?>,
			enableSecureW9: <?php echo wp_json_encode( get_option( 'w91099ch_enable_secure_w9', false ) ); ?>,
		};
	}

	// Save auto-sync-on-connect preference when the checkbox is toggled.
	$('#mypowerly-auto-sync-on-connect').off('change.autoSyncSave').on('change.autoSyncSave', function() {
		var checked = this.checked;
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
});
</script>

<script>
function copyToClipboard(selector) {
	try {
		var el = document.querySelector(selector);
		if (!el) {
			return;
		}
		var text = (el.value || el.textContent || '').toString();
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text);
			return;
		}
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.position = 'fixed';
		ta.style.left = '-9999px';
		document.body.appendChild(ta);
		ta.select();
		document.execCommand('copy');
		document.body.removeChild(ta);
	} catch (e) {}
}
</script>

<script>
jQuery(document).ready(function($) {
	async function copyText(text) {
		if (!text) {
			return false;
		}
		try {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				await navigator.clipboard.writeText(text);
				return true;
			}
		} catch (e) {}
		try {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.setAttribute('readonly', '');
			ta.style.position = 'fixed';
			ta.style.left = '-9999px';
			document.body.appendChild(ta);
			ta.select();
			var ok = document.execCommand('copy');
			document.body.removeChild(ta);
			return ok;
		} catch (e2) {
			return false;
		}
	}

	function openGmailCompose(url) {
		var subject = 'W-9 Form Link';
		var body = 'Hi,%0D%0A%0D%0AHere is the link to the W-9 form:%0D%0A' + encodeURIComponent(url) + '%0D%0A%0D%0AThanks';
		var gmailUrl = 'https://mail.google.com/mail/?view=cm&fs=1&su=' + encodeURIComponent(subject) + '&body=' + body;
		window.open(gmailUrl, '_blank', 'noopener');
	}

	// W-9 Tools dropdown functionality
	$('#w91099ch-w9-tools-btn').on('click', function(e) {
		e.preventDefault();
		e.stopPropagation();
		$('#w91099ch-w9-tools-menu').toggleClass('hidden');
		$(this).attr('aria-expanded', $(this).attr('aria-expanded') === 'false' ? 'true' : 'false');
	});

	$(document).on('click', function(e) {
		if (!$(e.target).closest('#w91099ch-w9-tools').length) {
			$('#w91099ch-w9-tools-menu').addClass('hidden');
			$('#w91099ch-w9-tools-btn').attr('aria-expanded', 'false');
		}
	});

	$('#w91099ch-w9-tools-menu [data-action]').on('click', function(e) {
		e.preventDefault();
		e.stopPropagation();

		var action = $(this).data('action');
		var defaultUrl = $('#w91099ch-w9-tools').data('default-page-url');

		if (!defaultUrl) {
			alert('Please set a default page first.');
			return;
		}

		switch (action) {
			case 'copy':
				copyText(defaultUrl).then(function(success) {
					if (success) {
						alert('Link copied to clipboard!');
					} else {
						alert('Could not copy the link.');
					}
				});
				break;
			case 'email':
				openGmailCompose(defaultUrl);
				break;
			case 'qr':
				showQRCode(defaultUrl);
				break;
		}

		$('#w91099ch-w9-tools-menu').addClass('hidden');
		$('#w91099ch-w9-tools-btn').attr('aria-expanded', 'false');
	});

	function showQRCode(url) {
		if (!$('#w91099ch-qr-modal').length) {
			var modalHtml = '<div id="w91099ch-qr-modal" style="position: fixed; inset: 0; z-index: 999999; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center;">' +
				'<div style="position: relative; max-width: 420px; margin: 10vh auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.25);">' +
					'<div style="padding: 18px 18px 0 18px; display:flex; align-items:center; justify-content: space-between; gap: 12px;">' +
						'<div style="font-weight: 800; color: #111827; font-size: 16px;">QR code for default page</div>' +
						'<button type="button" id="w91099ch-qr-close" style="border: 0; background: transparent; font-size: 22px; line-height: 1; padding: 6px 10px; cursor: pointer; color: #6b7280;">&times;</button>' +
					'</div>' +
					'<div style="padding: 18px;">' +
						'<div style="display:flex; flex-direction: column; align-items: center; gap: 12px;">' +
							'<img id="w91099ch-qr-img" alt="QR" style="width: 220px; height: 220px; border: 1px solid #e5e7eb; border-radius: 12px;" src="https://chart.googleapis.com/chart?cht=qr&chs=220x220&chl=' + encodeURIComponent(url) + '" />' +
							'<div style="word-break: break-all; color: #374151; font-size: 12px; text-align: center;">' + url + '</div>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>';
			$('body').append(modalHtml);
		} else {
			$('#w91099ch-qr-img').attr('src', 'https://chart.googleapis.com/chart?cht=qr&chs=220x220&chl=' + encodeURIComponent(url));
			$('#w91099ch-qr-modal').show();
		}
	}

	$(document).on('click', '#w91099ch-qr-close, #w91099ch-qr-modal', function(e) {
		if (e.target.id === 'w91099ch-qr-close' || e.target.id === 'w91099ch-qr-modal') {
			$('#w91099ch-qr-modal').hide();
		}
	});

	$(document).on('keydown', function(e) {
		if (e.key === 'Escape') {
			$('#w91099ch-qr-modal').hide();
		}
	});
});
</script>

 </div>
