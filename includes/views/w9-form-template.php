<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified W9 Form Template
 * This template provides a consistent W9 form display across all client pages
 */

// Get form configuration
$form_title = isset( $atts['title'] ) ? $atts['title'] : 'W-9 Form';
$show_client_tools = !isset( $atts['hide_tools'] ) || $atts['hide_tools'] !== 'true';

// Get default page URL for client-side dropdown
$w9_default_page_id = get_option( 'w91099ch_w9_default_page_id', 0 );
$w9_default_page_url = $w9_default_page_id ? get_permalink( $w9_default_page_id ) : '';

$w91099ch_mock_data_sync_modules = array(
	array(
		'id'       => 'website-content-media-assets',
		'label'    => 'Website Content',
		'icon'     => 'fa-photo-film',
		'accent'   => 'w91099ch-accent-blue',
		'title'    => 'Website Content & Media Assets Sync',
		'subtitle' => 'Sync pages, posts, media, comments and taxonomy data',
		'consent'  => 'I agree to sync Website Content & Media Assets data',
		'confirm'  => 'Are you sure you want to sync selected content data?',
		'groups'   => array(
			'Standard Pages & Posts' => array( 'Homepage', 'Privacy Policy', 'Blog Posts', 'Publishing Dates', 'Authors', 'SEO Metadata' ),
			'Media Library' => array( 'Images (original files)', 'Product Gallery Photos', 'PDFs / Downloads', 'Videos', 'Alt Text Data' ),
			'Comments & Reviews' => array( 'Reviewer Names', 'Emails', 'Star Ratings', 'Comment Text', 'Approval Status', 'Timestamps' ),
			'Categories & Tags' => array( 'Product Categories', 'Blog Categories', 'Tags', 'Hierarchical Structure', 'URL Slugs' ),
		),
	),
	array(
		'id'       => 'analytics-customer-relationship',
		'label'    => 'Analytics',
		'icon'     => 'fa-chart-line',
		'accent'   => 'w91099ch-accent-green',
		'title'    => 'Analytics & Customer Relationship Data Sync',
		'subtitle' => 'Sync sales, tax, customer and behavioral insights',
		'consent'  => 'I agree to sync Analytics & Customer Relationship data',
		'confirm'  => 'Are you sure you want to sync selected analytics data?',
		'groups'   => array(
			'Analytics Reports' => array( 'Gross Sales', 'Net Sales', 'Refund Totals', 'Total Orders', 'Product Quantity Sales' ),
			'Tax & Shipping Setup' => array( 'Tax Rates (State/Zip)', 'Shipping Zones', 'Shipping Class Rules', 'Flat Rate Pricing' ),
			'Customer Behavior' => array( 'Wishlist Data', 'Abandoned Carts', 'Cart Emails', 'Cart Items', 'Checkout Timestamps' ),
		),
	),
	array(
		'id'       => 'system-configuration-design',
		'label'    => 'System Config',
		'icon'     => 'fa-sliders',
		'accent'   => 'w91099ch-accent-orange',
		'title'    => 'System Configuration & Design Sync',
		'subtitle' => 'Sync system settings, plugins, theme and database structure',
		'consent'  => 'I agree to sync System Configuration & Design data',
		'confirm'  => 'Are you sure you want to sync selected configuration data?',
		'groups'   => array(
			'WordPress Settings' => array( 'Site Title', 'Tagline', 'Permalinks', 'Timezone', 'Reading/Writing Settings' ),
			'Plugin Settings' => array( 'SEO Configurations', 'Cache Settings', 'Form Plugin Config', 'Security Plugin Settings', 'JSON Export Settings' ),
			'Theme Customizer' => array( 'Layout Settings', 'Color Schemes', 'Typography', 'Widget Layouts' ),
			'Database Structure' => array( 'wp_posts', 'wp_options', 'wp_postmeta', 'Custom SQL Tables' ),
		),
	),
	array(
		'id'       => 'user-security-system-access',
		'label'    => 'Security Access',
		'icon'     => 'fa-shield-halved',
		'accent'   => 'w91099ch-accent-red',
		'title'    => 'User Security & System Access Sync',
		'subtitle' => 'Sync user accounts, roles and system activity logs',
		'consent'  => 'I agree to sync User Security & System Access data',
		'confirm'  => 'Are you sure you want to sync selected security data?',
		'groups'   => array(
			'User Accounts' => array( 'User Profiles', 'Roles (Admin, Shop Manager, Customer)', 'Account Meta Fields', 'Encrypted Password Hashes' ),
			'Activity Logs' => array( 'Login History', 'IP Tracking Logs', 'Admin Actions', 'File Modification Logs', 'Security Events' ),
		),
	),
	array(
		'id'       => 'payment-gateway-third-party-integrations',
		'label'    => 'Payments',
		'icon'     => 'fa-credit-card',
		'accent'   => 'w91099ch-accent-purple',
		'title'    => 'Payment Gateway & Third-Party Integrations Sync',
		'subtitle' => 'Sync API keys, webhooks and payment gateway logs',
		'consent'  => 'I agree to sync Payment & Integration data',
		'confirm'  => 'Are you sure you want to sync selected payment and integration data?',
		'groups'   => array(
			'API Keys' => array( 'ERP System Keys', 'Shipping Carrier Keys', 'External Tool Keys' ),
			'Webhooks' => array( 'Webhook URLs', 'Event Triggers', 'Delivery Endpoints' ),
			'Payment Logs' => array( 'Stripe Logs', 'PayPal Logs', 'Transaction Debug Logs' ),
		),
	),
);
?>

<!-- Main Form Container -->
<div class="w9-form-container max-w-8xl mx-auto">
	<div class="w9-form-card bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
		<!-- Form Header -->
		<div class="w9-form-header-bar bg-gradient-to-r from-blue-600 to-indigo-700 px-8 py-6">
			<div class="flex items-center justify-between">
				<div>
					<h2 class="text-2xl font-bold text-white flex items-center gap-3">
						<i class="fas fa-edit"></i>
						<?php echo esc_html( $form_title ); ?>
					</h2>
					<p class="text-blue-100 mt-1">Complete all required fields marked with *</p>
				</div>
				<div class="flex items-center gap-4">
					<div class="relative" id="w91099ch-client-tools" data-default-page-url="<?php echo esc_attr( $w9_default_page_url ); ?>">
						<button type="button" id="w91099ch-client-tools-btn" class="px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg font-semibold text-sm transition-colors flex items-center gap-2" aria-haspopup="true" aria-expanded="false">
							<i class="fas fa-tools"></i>
							<?php echo esc_html__( 'W-9 & 1099 Tools', 'w9-1099-chaser' ); ?>
							<i class="fas fa-chevron-down" style="font-size: 10px;"></i>
						</button>
						<div id="w91099ch-client-tools-menu" class="hidden absolute right-0 mt-2 w-56 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden z-50">
							<button type="button" data-action="copy" class="w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors flex items-center gap-3 text-gray-700">
								<i class="far fa-copy" style="width: 14px;"></i>
								<span><?php echo esc_html__( 'Share link', 'w9-1099-chaser' ); ?></span>
							</button>
							<button type="button" data-action="email" class="w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors flex items-center gap-3 text-gray-700">
								<i class="far fa-envelope" style="width: 14px;"></i>
								<span><?php echo esc_html__( 'Share to email', 'w9-1099-chaser' ); ?></span>
							</button>
							<button type="button" data-action="qr" class="w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors flex items-center gap-3 text-gray-700">
								<i class="fas fa-qrcode" style="width: 14px;"></i>
								<span><?php echo esc_html__( 'Share as QR code', 'w9-1099-chaser' ); ?></span>
							</button>
						</div>
					</div>
					<div class="w9-form-icon">
						<div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
							<i class="fas fa-file-pdf text-white text-xl"></i>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Form Body -->
		<div class="w9-form-body p-8">
			<form id="mypowerly-w9-form" class="mypowerly-w9-form" novalidate>
				<?php wp_nonce_field( 'w91099ch_w9_form_submit', 'w91099ch_w9_nonce' ); ?>

				<div class="space-y-8">
					<!-- Section 1: Name and Business Information -->
					<div class="w9-form-section bg-gray-50 rounded-xl p-6 border border-gray-200">
						<h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-3">
							<div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-md">
								<i class="fas fa-user text-white"></i>
							</div>
							<div>
								<span class="text-blue-600 font-semibold">Step 1:</span> Name and Business Information
							</div>
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
					<div class="w9-form-section bg-gray-50 rounded-xl p-6 border border-gray-200">
						<h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-3">
							<div class="w-10 h-10 rounded-lg bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center shadow-md">
								<i class="fas fa-location-dot text-white"></i>
							</div>
							<div>
								<span class="text-green-600 font-semibold">Step 2:</span> Address Information
							</div>
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
					<div class="w9-form-section bg-gray-50 rounded-xl p-6 border border-gray-200">
						<h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-3">
							<div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center shadow-md">
								<i class="fas fa-id-card text-white"></i>
							</div>
							<div>
								<span class="text-purple-600 font-semibold">Step 3:</span> Taxpayer Identification Number (TIN)
							</div>
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
					<div class="w9-form-section bg-gray-50 rounded-xl p-6 border border-gray-200">
						<h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-3">
							<div class="w-10 h-10 rounded-lg bg-gradient-to-br from-orange-500 to-orange-600 flex items-center justify-center shadow-md">
								<i class="fas fa-signature text-white"></i>
							</div>
							<div>
								<span class="text-orange-600 font-semibold">Step 4:</span> Certification and Signature
							</div>
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
										<canvas id="mypowerly-w9-signature-canvas" width="400" height="200" class="mypowerly-w9-signature-canvas border-2 border-gray-300 rounded-lg bg-white cursor-crosshair" style="touch-action: none; width: 100%; height: 200px;"></canvas>
									</div>
									<div class="signature-actions mt-3">
										<button type="button" id="mypowerly-w9-clear-signature" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium text-sm transition-colors">Clear Signature</button>
									</div>
									<input type="hidden" id="mypowerly-w9-signature-data" name="signature_data">
									<input type="hidden" id="certification_name" name="certification_name">
								</div>
								<p class="text-sm text-gray-500 mt-2">Draw your signature above</p>
							</div>

							<div>
								<label class="block text-sm font-medium text-gray-700 mb-2">
									Date <span class="text-red-500">*</span>
								</label>
								<input type="date" id="certification_date" name="certification_date" required
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
							</div>
						</div>
					</div>
				</div>

				<!-- Submit / Privacy Section -->
				<div class="w9-submit-section mt-8 pt-8 border-t border-gray-200">
					<div class="flex flex-col md:flex-row items-center justify-end gap-6">
						<div id="mypowerly-w9-status" class="w9-status-message w-full" style="display: none;"></div>
						<div class="w9-download-buttons w-full flex gap-4 flex-wrap justify-end">
							<button type="button" id="mypowerly-w9-download"
									class="w9-btn-primary bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800 text-white font-semibold px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 flex items-center gap-3">
								<i class="fas fa-download"></i>
								<span>Print To PDF</span>
							</button>
							<button type="button" id="mypowerly-govt-form-download"
									class="w9-btn-secondary bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white font-semibold px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 flex items-center gap-3">
								<i class="fas fa-file-pdf"></i>
								<span>Official W9 Form</span>
							</button>
						</div>
					</div>
				</div>

				<!-- Professional Branding Footer -->
				<div class="w9-branding-footer mt-12 pt-8 border-t border-gray-200">
					<div class="max-w-4xl mx-auto text-center">
						
						<div class="w9-footer-links flex items-center justify-center gap-8 flex-wrap mb-6">
							<a href="https://1099automation.com" target="_blank" rel="noopener noreferrer" 
							   class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 font-medium transition-colors">
								<i class="fas fa-globe"></i>
								<span>Official Website</span>
							</a>
							<a href="https://mail.google.com/mail/?view=cm&fs=1&to=1099automation@gmail.com,<?php echo rawurlencode( sanitize_email( get_option( 'admin_email' ) ) ); ?>&su=W-9%20Form%20Generator%20Support%20Request&body=Hi%20Support%20Team,%0D%0A%0D%0AI%20need%20help%20with%20the%20W-9%20Form%20Generator.%0D%0A%0D%0AWebsite:%20<?php echo rawurlencode( home_url( '/' ) ); ?>%0D%0A%0D%0APlease%20describe%20your%20issue%20below:%0D%0A%0D%0AThanks!" target="_blank" rel="noopener noreferrer"
							   class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-800 font-medium transition-colors">
								<i class="fas fa-envelope"></i>
								<span>Contact Support</span>
							</a>
							<a href="https://wordpress.org/plugins/w9-1099-chaser" target="_blank" rel="noopener noreferrer" 
							   class="inline-flex items-center gap-2 text-purple-600 hover:text-purple-800 font-medium transition-colors">
								<i class="fab fa-wordpress"></i>
								<span>WordPress Plugin</span>
							</a>
						</div>

						<div class="w9-footer-bottom border-t border-gray-200 pt-6">
							<p class="text-sm text-gray-500 mb-3">
								&copy; <?php echo esc_html(date('Y')); ?> Vendor Onboarding W9-1099 Chaser by Mypowerly. All rights reserved. | 
								Free W-9 Form Generator | Create unlimited professional tax forms
							</p>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>

	<div class="w91099ch-frontend-sync-modules" id="w91099ch-website-sync-modules">
		<div class="w91099ch-sync-modules-heading">
			<h2>Website Data Sync Modules</h2>
			<p>Select the data groups to include. These modules use mock sync behavior only and do not call external APIs.</p>
		</div>

		<div class="w91099ch-sync-card-grid">
			<?php foreach ( $w91099ch_mock_data_sync_modules as $module ) : ?>
				<div class="w91099ch-sync-card <?php echo esc_attr( $module['accent'] ); ?> w91099ch-mock-sync-card" data-module-id="<?php echo esc_attr( $module['id'] ); ?>" data-confirm-message="<?php echo esc_attr( $module['confirm'] ); ?>">
					<div class="w91099ch-sync-card-label"><?php echo esc_html( $module['label'] ); ?></div>
					<div class="w91099ch-sync-card-header">
						<div class="w91099ch-sync-card-icon">
							<i class="fas <?php echo esc_attr( $module['icon'] ); ?>" aria-hidden="true"></i>
						</div>
						<div>
							<h3><?php echo esc_html( $module['title'] ); ?></h3>
							<p><?php echo esc_html( $module['subtitle'] ); ?></p>
						</div>
					</div>

					<div class="w91099ch-mock-sync-groups">
						<?php foreach ( $module['groups'] as $group_label => $items ) : ?>
							<div class="w91099ch-mock-sync-group">
								<h4><?php echo esc_html( $group_label ); ?></h4>
								<div class="w91099ch-mock-sync-options">
									<?php foreach ( $items as $item ) : ?>
										<?php $item_id = 'w91099ch-frontend-' . $module['id'] . '-' . sanitize_title( $group_label ) . '-' . sanitize_title( $item ); ?>
										<label class="w91099ch-mock-sync-option">
											<input type="checkbox" class="w91099ch-mock-sync-item" id="<?php echo esc_attr( $item_id ); ?>" data-group="<?php echo esc_attr( $group_label ); ?>" data-item="<?php echo esc_attr( $item ); ?>" checked="checked" />
											<span><?php echo esc_html( $item ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="w91099ch-mock-payload-box" aria-live="polite">
						<div class="w91099ch-mock-payload-header">
							<h4>Selected JSON Payload</h4>
							<span class="w91099ch-mock-payload-count">0 selected</span>
						</div>
						<pre class="w91099ch-mock-payload-preview">{}</pre>
					</div>

					<div class="w91099ch-sync-card-footer">
						<div class="w91099ch-mock-consent-box">
							<input type="checkbox" class="w91099ch-mock-sync-consent" id="<?php echo esc_attr( 'w91099ch-frontend-consent-' . $module['id'] ); ?>" />
							<label for="<?php echo esc_attr( 'w91099ch-frontend-consent-' . $module['id'] ); ?>">
								<strong>Consent required</strong>
								<span><?php echo esc_html( $module['consent'] ); ?></span>
							</label>
						</div>

						<button type="button" class="w91099ch-mock-sync-button" disabled>
							<i class="fas fa-rotate" aria-hidden="true"></i>
							<span>Sync Selected Data</span>
						</button>

						<div class="w91099ch-mock-sync-status" aria-live="polite">Check consent to enable sync</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
