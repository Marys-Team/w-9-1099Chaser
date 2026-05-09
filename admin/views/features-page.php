<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'w9-1099-chaser' ) );
}

$w91099ch_upgrade_url = 'https://mypowerly.com';

$w91099ch_premium_features = array(
	array(
		'icon'  => 'fa-file-signature',
		'title' => __( 'W-9 Integrated Request & eSignature System', 'w9-1099-chaser' ),
		'desc'  => __( 'Send W-9 requests digitally—individually or in bulk—with built-in eSignatures. Collect completed W-9 forms instantly without paperwork or manual follow-ups.', 'w9-1099-chaser' ),
	),
	array(
		'icon'  => 'fa-bell',
		'title' => __( 'Automated W-9 Chasing & Reminder System', 'w9-1099-chaser' ),
		'desc'  => __( 'Never chase contractors manually again. Automated reminders follow up with recipients until W-9 forms are completed—saving time and reducing delays.', 'w9-1099-chaser' ),
	),
	array(
		'icon'  => 'fa-paper-plane',
		'title' => __( 'Multi-Channel Reminder Delivery', 'w9-1099-chaser' ),
		'desc'  => __( 'Reach recipients on the channels they respond to best. Send automated reminders via Email, SMS, WhatsApp, and Auto Phone Dialer (VOIP/IVR).', 'w9-1099-chaser' ),
	),
	array(
		'icon'  => 'fa-file-invoice-dollar',
		'title' => __( '1099 Integrated eFiling System (All Forms Included)', 'w9-1099-chaser' ),
		'desc'  => __( 'Prepare, manage, and eFile all 1099 forms from one centralized system—designed for accuracy, compliance, and fast submission.', 'w9-1099-chaser' ),
	),
	array(
		'icon'  => 'fa-building-columns',
		'title' => __( 'State & Federal 1099 eFiling', 'w9-1099-chaser' ),
		'desc'  => __( 'File 1099 forms with participating states directly from platform—no third-party tools required.', 'w9-1099-chaser' ),
	),
	array(
		'icon'  => 'fa-coins',
		'title' => __( '1099-DA (Crypto & Digital Assets Reporting)', 'w9-1099-chaser' ),
		'desc'  => __( 'Stay compliant with upcoming crypto regulations. Track and manage 1099-DA forms for digital asset and cryptocurrency reporting.', 'w9-1099-chaser' ),
	),
);
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 to-blue-50 mp-shell">
	<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-12">
		<div class="mp-card p-6 mb-8" style="background: linear-gradient(135deg, var(--mp-primary, #1a56db) 0%, var(--mp-secondary, #7c3aed) 100%); box-shadow: 0 18px 55px rgba(26, 86, 219, 0.22); border: 1px solid rgba(255,255,255,0.16); position: relative; overflow: hidden;">
			<div aria-hidden="true" style="position:absolute; inset:-2px; background: radial-gradient(900px 340px at 85% -10%, rgba(255,255,255,0.22) 0%, rgba(255,255,255,0.00) 55%); pointer-events:none;"></div>
			<div class="flex items-start justify-between gap-6" style="position: relative;">
				<div class="flex items-start gap-4">
					<div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0" style="background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.22);">
						<i class="fas fa-gem" aria-hidden="true" style="color: rgba(255,255,255,0.95);"></i>
					</div>
					<div class="flex-1">
						<div class="flex items-center gap-3 mb-1">
							<div class="text-2xl font-extrabold" style="color: rgba(255,255,255,0.98); line-height: 1.15;">
								<?php echo esc_html__( 'MyPowerly', 'w9-1099-chaser' ); ?>
							</div>
							<span class="inline-flex items-center" style="font-size: 11px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; padding: 3px 10px; border-radius: 999px; background: rgba(255,255,255,0.16); border: 1px solid rgba(255,255,255,0.22); color: rgba(255,255,255,0.92);">
								<?php echo esc_html__( 'Account', 'w9-1099-chaser' ); ?>
							</span>
						</div>
						<div class="text-sm" style="color: rgba(255,255,255,0.86); max-width: 56ch;">
							<?php echo esc_html__( 'Manage advanced automation, chasing, and filing workflows in MyPowerly.', 'w9-1099-chaser' ); ?>
						</div>
					</div>
				</div>

				<div class="flex items-center gap-3">
					<a href="<?php echo esc_url( $w91099ch_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer" class="mp-btn-primary" style="background: rgba(255,255,255,0.96); color: var(--mp-primary, #1a56db); border: 1px solid rgba(255,255,255,0.55); box-shadow: 0 14px 34px rgba(17, 24, 39, 0.10);">
						<?php echo esc_html__( 'Open MyPowerly', 'w9-1099-chaser' ); ?>
					</a>
				</div>
			</div>
		</div>

		<div class="mp-card" style="border: 1px solid rgba(26, 86, 219, 0.18); overflow: hidden;">
			<div class="p-6" style="border-bottom: 1px solid rgba(229, 231, 235, 0.9);">
				<h2 class="text-xl font-bold text-gray-900 mb-1"><?php echo esc_html__( 'MyPowerly Features', 'w9-1099-chaser' ); ?></h2>
				<div class="text-sm" style="color: var(--mp-gray-600);">
					<?php echo esc_html__( 'These options are managed in your MyPowerly account.', 'w9-1099-chaser' ); ?>
				</div>
			</div>

			<div class="p-6 space-y-4">
				<?php foreach ( $w91099ch_premium_features as $w91099ch_feature ) : ?>
					<div class="group p-4 rounded-2xl transition-all duration-300" style="border: 1px solid rgba(209, 213, 219, 0.9); background: rgba(249, 250, 251, 0.72);">
						<div class="flex items-start gap-4">
							<div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0" style="background: linear-gradient(135deg, var(--mp-primary, #1a56db) 0%, var(--mp-secondary, #7c3aed) 100%); box-shadow: 0 14px 34px rgba(26, 86, 219, 0.18);">
								<i class="fas <?php echo esc_attr( $w91099ch_feature['icon'] ); ?>" aria-hidden="true" style="color: rgba(255,255,255,0.96);"></i>
							</div>
							<div class="flex-1">
								<div class="font-extrabold text-gray-900 text-sm mb-1" style="letter-spacing: 0.01em;">
									<?php echo esc_html( $w91099ch_feature['title'] ); ?>
								</div>
								<div class="text-sm" style="color: var(--mp-gray-600); line-height: 1.45;">
									<?php echo esc_html( $w91099ch_feature['desc'] ); ?>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="p-5 flex justify-end" style="border-top: 1px solid rgba(229, 231, 235, 0.9); background: rgba(255,255,255,0.85);">
				<a href="<?php echo esc_url( $w91099ch_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer" class="mp-btn-primary" style="padding: 10px 16px; border-radius: 12px; box-shadow: 0 14px 34px rgba(26, 86, 219, 0.20);">
					<?php echo esc_html__( 'Open MyPowerly', 'w9-1099-chaser' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>
