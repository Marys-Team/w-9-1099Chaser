<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_Sync_Modules_Shortcode {

	private static $modules = array();

	public function init() {
		self::$modules = array(
			array(
				'id'      => 'website-content-media-assets',
				'label'   => 'Website Content',
				'icon'    => 'fa-photo-film',
				'accent'  => '#1a56db',
				'title'   => 'Website Content & Media Assets Sync',
				'subtitle'=> 'Sync pages, posts, media, comments and taxonomy data',
				'consent' => 'I agree to sync Website Content & Media Assets data',
				'confirm' => 'Are you sure you want to sync selected content data?',
				'groups'  => array(
					'Standard Pages & Posts' => array( 'Homepage', 'Privacy Policy', 'Blog Posts', 'Publishing Dates', 'Authors', 'SEO Metadata' ),
					'Media Library'          => array( 'Images (original files)', 'Product Gallery Photos', 'PDFs / Downloads', 'Videos', 'Alt Text Data' ),
					'Comments & Reviews'     => array( 'Reviewer Names', 'Emails', 'Star Ratings', 'Comment Text', 'Approval Status', 'Timestamps' ),
					'Categories & Tags'      => array( 'Product Categories', 'Blog Categories', 'Tags', 'Hierarchical Structure', 'URL Slugs' ),
				),
			),
			array(
				'id'      => 'analytics-customer-relationship',
				'label'   => 'Analytics',
				'icon'    => 'fa-chart-line',
				'accent'  => '#059669',
				'title'   => 'Analytics & Customer Relationship Data Sync',
				'subtitle'=> 'Sync sales, tax, customer and behavioral insights',
				'consent' => 'I agree to sync Analytics & Customer Relationship data',
				'confirm' => 'Are you sure you want to sync selected analytics data?',
				'groups'  => array(
					'Analytics Reports'    => array( 'Gross Sales', 'Net Sales', 'Refund Totals', 'Total Orders', 'Product Quantity Sales' ),
					'Tax & Shipping Setup' => array( 'Tax Rates (State/Zip)', 'Shipping Zones', 'Shipping Class Rules', 'Flat Rate Pricing' ),
					'Customer Behavior'    => array( 'Wishlist Data', 'Abandoned Carts', 'Cart Emails', 'Cart Items', 'Checkout Timestamps' ),
				),
			),
			array(
				'id'      => 'system-configuration-design',
				'label'   => 'System Config',
				'icon'    => 'fa-sliders',
				'accent'  => '#d97706',
				'title'   => 'System Configuration & Design Sync',
				'subtitle'=> 'Sync system settings, plugins, theme and database structure',
				'consent' => 'I agree to sync System Configuration & Design data',
				'confirm' => 'Are you sure you want to sync selected configuration data?',
				'groups'  => array(
					'WordPress Settings' => array( 'Site Title', 'Tagline', 'Permalinks', 'Timezone', 'Reading/Writing Settings' ),
					'Plugin Settings'    => array( 'SEO Configurations', 'Cache Settings', 'Form Plugin Config', 'Security Plugin Settings', 'JSON Export Settings' ),
					'Theme Customizer'   => array( 'Layout Settings', 'Color Schemes', 'Typography', 'Widget Layouts' ),
					'Database Structure' => array( 'wp_posts', 'wp_options', 'wp_postmeta', 'Custom SQL Tables' ),
				),
			),
			array(
				'id'      => 'user-security-system-access',
				'label'   => 'Security Access',
				'icon'    => 'fa-shield-halved',
				'accent'  => '#7c3aed',
				'title'   => 'User Security & System Access Sync',
				'subtitle'=> 'Sync user accounts, roles and system activity logs',
				'consent' => 'I agree to sync User Security & System Access data',
				'confirm' => 'Are you sure you want to sync selected security data?',
				'groups'  => array(
					'User Accounts' => array( 'User Profiles', 'Roles (Admin, Shop Manager, Customer)', 'Account Meta Fields', 'Encrypted Password Hashes' ),
					'Activity Logs' => array( 'Login History', 'IP Tracking Logs', 'Admin Actions', 'File Modification Logs', 'Security Events' ),
				),
			),
			array(
				'id'      => 'payment-gateway-third-party-integrations',
				'label'   => 'Payments',
				'icon'    => 'fa-credit-card',
				'accent'  => '#7c3aed',
				'title'   => 'Payment Gateway & Third-Party Integrations Sync',
				'subtitle'=> 'Sync API keys, webhooks and payment gateway logs',
				'consent' => 'I agree to sync Payment & Integration data',
				'confirm' => 'Are you sure you want to sync selected payment and integration data?',
				'groups'  => array(
					'API Keys'     => array( 'ERP System Keys', 'Shipping Carrier Keys', 'External Tool Keys' ),
					'Webhooks'     => array( 'Webhook URLs', 'Event Triggers', 'Delivery Endpoints' ),
					'Payment Logs' => array( 'Stripe Logs', 'PayPal Logs', 'Transaction Debug Logs' ),
				),
			),
		);

		add_shortcode( 'w91099ch_sync_modules', array( $this, 'render_shortcode' ) );
		add_action( 'init', array( $this, 'register_assets' ) );
	}

	public function register_assets() {
		wp_register_style(
			'w91099ch-sync-modules',
			w91099ch_PLUGIN_URL . 'assets/css/w9-1099-chaser-sync-modules.css',
			array(),
			w91099ch_VERSION
		);
		$sync_js_path = w91099ch_PLUGIN_PATH . 'assets/js/w9-1099-chaser-sync-modules.js';
		wp_register_script(
			'w91099ch-sync-modules',
			w91099ch_PLUGIN_URL . 'assets/js/w9-1099-chaser-sync-modules.js',
			array( 'jquery' ),
			file_exists( $sync_js_path ) ? filemtime( $sync_js_path ) : w91099ch_VERSION,
			true
		);
	}

	public function render_shortcode( $atts = array() ) {
		wp_enqueue_style( 'w91099ch-sync-modules' );
		wp_enqueue_script( 'w91099ch-sync-modules' );
		wp_localize_script(
			'w91099ch-sync-modules',
			'w91099chSyncModules',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'w91099ch_nonce' ),
			)
		);

		$sc_nonce = wp_create_nonce( 'w91099ch_nonce' );

		ob_start();
		?>
		<div class="w91099ch-sync-wrap">
			<div class="w91099ch-sync-header">
				<h2>Website Data Sync Modules</h2>
				<p>Select the data groups to include. These modules use mock sync behavior only and do not call external APIs.</p>
			</div>

			<div class="w91099ch-sync-grid">
				<?php foreach ( self::$modules as $module ) : ?>
					<div class="w91099ch-sync-card"
						data-module-id="<?php echo esc_attr( $module['id'] ); ?>"
						data-confirm-message="<?php echo esc_attr( $module['confirm'] ); ?>"
						data-nonce="<?php echo esc_attr( $sc_nonce ); ?>"
						style="--accent: <?php echo esc_attr( $module['accent'] ); ?>;">

						<div class="w91099ch-sync-card-label"><?php echo esc_html( $module['label'] ); ?></div>

						<div class="w91099ch-sync-card-head">
							<div class="w91099ch-sync-card-icon">
								<i class="fas <?php echo esc_attr( $module['icon'] ); ?>"></i>
							</div>
							<div>
								<h3><?php echo esc_html( $module['title'] ); ?></h3>
								<p><?php echo esc_html( $module['subtitle'] ); ?></p>
							</div>
						</div>

						<div class="w91099ch-sync-groups">
							<?php foreach ( $module['groups'] as $group_label => $items ) : ?>
								<div class="w91099ch-sync-group">
									<h4><?php echo esc_html( $group_label ); ?></h4>
									<div class="w91099ch-sync-options">
										<?php foreach ( $items as $item ) :
											$item_id = 'w91099ch-sc-' . $module['id'] . '-' . sanitize_title( $group_label ) . '-' . sanitize_title( $item );
										?>
											<label class="w91099ch-sync-option">
												<input type="checkbox"
													class="w91099ch-sync-item"
													id="<?php echo esc_attr( $item_id ); ?>"
													data-group="<?php echo esc_attr( $group_label ); ?>"
													data-item="<?php echo esc_attr( $item ); ?>"
													checked="checked" />
												<span><?php echo esc_html( $item ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<div class="w91099ch-sync-payload-box">
							<div class="w91099ch-sync-payload-header">
								<span>Selected JSON Payload</span>
								<span class="w91099ch-sync-payload-count">0 selected</span>
							</div>
							<pre class="w91099ch-sync-payload-pre">{}</pre>
						</div>

						<div class="w91099ch-sync-footer">
							<div class="w91099ch-sync-consent-wrap">
								<input type="checkbox"
									class="w91099ch-sync-consent"
									id="w91099ch-sc-consent-<?php echo esc_attr( $module['id'] ); ?>" />
								<label for="w91099ch-sc-consent-<?php echo esc_attr( $module['id'] ); ?>">
									<strong>Consent required</strong><br>
									<?php echo esc_html( $module['consent'] ); ?>
								</label>
							</div>

							<input type="hidden" class="w91099ch-sync-nonce" value="<?php echo esc_attr( $sc_nonce ); ?>" />
							<button type="button" class="w91099ch-sync-btn" disabled>
								<i class="fas fa-rotate"></i> Sync Selected Data
							</button>

							<div class="w91099ch-sync-status">Check consent to enable sync</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Confirmation Modal -->
			<div class="w91099ch-modal-overlay" id="w91099ch-sc-modal" style="display:none;" role="dialog" aria-modal="true">
				<div class="w91099ch-modal">
					<div class="w91099ch-modal-head">
						<i class="fas fa-circle-question"></i>
						<div>
							<h3>Confirm Mock Sync</h3>
							<p class="w91099ch-modal-confirm-msg"></p>
						</div>
					</div>
					<div class="w91099ch-modal-body">
						<div class="w91099ch-modal-note">No API call will be made. Only selected checkboxes are included.</div>
						<pre class="w91099ch-modal-payload"></pre>
					</div>
					<div class="w91099ch-modal-actions">
						<button type="button" class="w91099ch-btn-cancel" id="w91099ch-sc-cancel">Cancel</button>
						<button type="button" class="w91099ch-btn-ok" id="w91099ch-sc-ok"><i class="fas fa-check"></i> OK</button>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
