<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_connected      = $this->core->is_connected();
$credentials       = $this->core->get_credentials();
$credentials_valid = get_option( 'w91099ch_credentials_valid', false );
$last_checked      = get_option( 'w91099ch_last_checked', 0 );
$connected_at      = get_option( 'w91099ch_connected_at', '' );
$site_url          = get_option( 'w91099ch_site_url', '' );
$user_email        = get_option( 'w91099ch_user_email', '' );

$current_user     = wp_get_current_user();
$users_count_data = count_users();
$wp_users_total   = isset( $users_count_data['total_users'] ) ? (int) $users_count_data['total_users'] : 0;

$allowed_roles             = array( 'shop_manager', 'contributor', 'author', 'editor', 'administrator' );
$team_users_total_filtered = 0;
$role_counts               = isset( $users_count_data['avail_roles'] ) && is_array( $users_count_data['avail_roles'] ) ? $users_count_data['avail_roles'] : array();
foreach ( $allowed_roles as $role_slug ) {
	if ( isset( $role_counts[ $role_slug ] ) ) {
		$team_users_total_filtered += (int) $role_counts[ $role_slug ];
	}
}

$connection_error   = get_transient( 'w91099ch_connection_error' );
$connection_success = get_transient( 'w91099ch_connection_success' );

$profile_last_sync    = get_option( 'w91099ch_profile_last_sync', 0 );
$plugin_last_sync     = get_option( 'w91099ch_plugin_last_sync', 0 );
$affiliates_last_sync = get_option( 'w91099ch_affiliates_last_sync', 0 );
$affiliates_count     = get_option( 'w91099ch_affiliates_count', 0 );
$team_last_sync       = get_option( 'w91099ch_team_last_sync', 0 ); // New option for team sync

$profile_last_sync_formatted    = $profile_last_sync ? gmdate( 'Y-m-d H:i:s', (int) $profile_last_sync ) : 'Never';
$plugin_last_sync_formatted     = $plugin_last_sync ? gmdate( 'Y-m-d H:i:s', (int) $plugin_last_sync ) : 'Never';
$affiliates_last_sync_formatted = $affiliates_last_sync ? gmdate( 'Y-m-d H:i:s', (int) $affiliates_last_sync ) : 'Never';
$team_last_sync_formatted       = $team_last_sync ? gmdate( 'Y-m-d H:i:s', (int) $team_last_sync ) : 'Never'; // New team sync time

$affiliate_manager = new w91099ch_Affiliate_Manager();
$detected_plugins  = $affiliate_manager->detect_affiliate_plugins();
$total_affiliates  = $affiliate_manager->get_total_affiliates_count();
$newsletter_subscribed = (bool) get_option( 'w91099ch_newsletter_subscribed', false );

// Get admin email for display
$admin_email = get_option( 'admin_email' );
$enc_param   = filter_input( INPUT_GET, 'encrypted_credentials', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

?>

<?php if ( false ) : ?>
<xstyle>
	/* Mypowerly Color Palette */
	:root {
		--mp-primary: #1a56db;
		--mp-primary-dark: #1e429f;
		--mp-secondary: #7c3aed;
		--mp-accent: #f59e0b;
		--mp-success: #059669;
		--mp-error: #dc2626;
		--mp-warning: #d97706;
		--mp-gray-50: #f9fafb;
		--mp-gray-100: #f3f4f6;
		--mp-gray-200: #e5e7eb;
		--mp-gray-300: #d1d5db;
		--mp-gray-600: #4b5563;
		--mp-gray-700: #374151;
		--mp-gray-800: #1f2937;
		--mp-gray-900: #111827;
	}
	
	* {
		font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
	}
	
	body {
		background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
		min-height: 100vh;
	}
	
	.mp-page {
		max-width: 84rem;
		margin-left: auto;
		margin-right: auto;
	}

	.mp-section-title {
		font-size: 14px;
		font-weight: 800;
		letter-spacing: 0.02em;
		color: var(--mp-gray-800);
	}

	.mp-section-subtitle {
		font-size: 13px;
		color: var(--mp-gray-600);
		line-height: 1.4;
	}

	.mp-divider {
		height: 1px;
		background: linear-gradient(90deg, rgba(229,231,235,0) 0%, rgba(229,231,235,1) 50%, rgba(229,231,235,0) 100%);
	}
	
	/* Mypowerly Header Style */
	.mp-header {
		background: linear-gradient(135deg, var(--mp-primary) 0%, var(--mp-secondary) 100%);
		border-bottom: 1px solid rgba(255, 255, 255, 0.1);
		box-shadow: 0 4px 20px rgba(26, 86, 219, 0.15);
	}
	
	/* Mypowerly Card Design */
	.mp-card {
		background: white;
		border-radius: 16px;
		border: 1px solid var(--mp-gray-200);
		transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		box-shadow: 0 8px 30px rgba(17, 24, 39, 0.06);
	}

	.mp-metric-card {
		padding: 16px !important;
	}

	.mp-metric-card .mp-progress-bar {
		margin-bottom: 10px;
	}

	.mp-metric-card .mp-input {
		padding: 8px 12px;
	}

	.mp-metric-card .mp-scroll {
		max-height: 220px;
	}

	.mp-card:hover {
		box-shadow: 0 18px 55px rgba(17, 24, 39, 0.10);
		transform: translateY(-4px);
		border-color: var(--mp-gray-300);
	}

	.mp-card.mp-metric-card {
		position: relative;
		overflow: visible;
		z-index: 0;
	}

	.mp-card.mp-metric-card:hover {
		z-index: 30;
	}

	@keyframes mpWsPulse {
		0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.28); }
		70% { box-shadow: 0 0 0 10px rgba(220, 38, 38, 0); }
		100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
	}

	@keyframes mpWsShake {
		0%, 100% { transform: translateX(0); }
		20% { transform: translateX(-4px); }
		40% { transform: translateX(4px); }
		60% { transform: translateX(-3px); }
		80% { transform: translateX(3px); }
	}

	.mp-ws-attention {
		border-color: rgba(220, 38, 38, 0.70) !important;
		outline: 3px solid rgba(220, 38, 38, 0.22);
		animation: mpWsPulse 1.2s ease-out 0s 2, mpWsShake 0.5s ease-in-out 0s 1;
	}

	.mp-card-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 16px;
		padding-bottom: 10px;
		margin-bottom: 10px;
		border-bottom: 1px solid rgba(229, 231, 235, 0.8);
	}

	.mp-card-header h3,
	.mp-card-header h4,
	.mp-card-header h5 {
		margin: 0;
	}
	
	/* Mypowerly Button Style */
	.mp-btn-primary {
		background: linear-gradient(135deg, var(--mp-primary) 0%, var(--mp-primary-dark) 100%);
		color: white;
		font-weight: 600;
		padding: 10px 18px;
		border-radius: 12px;
		border: none;
		transition: transform 0.18s ease, box-shadow 0.22s ease, filter 0.22s ease, background 0.22s ease;
		box-shadow: 0 10px 26px rgba(26, 86, 219, 0.22);
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 10px;
		position: relative;
		overflow: hidden;
		text-decoration: none;
		user-select: none;
	}
	
	.mp-btn-primary:hover {
		transform: translateY(-2px);
		box-shadow: 0 14px 34px rgba(26, 86, 219, 0.30);
		background: linear-gradient(135deg, var(--mp-primary-dark) 0%, #1e3a8a 100%);
	}

	.mp-btn-primary::after {
		content: "";
		position: absolute;
		inset: 0;
		background: radial-gradient(1000px 160px at 20% 0%, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0) 60%);
		opacity: 0;
		transition: opacity 0.22s ease;
		pointer-events: none;
	}

	.mp-btn-primary:hover::after {
		opacity: 1;
	}

	.mp-btn-primary:active {
		transform: translateY(-1px);
		filter: brightness(0.98);
	}

	.mp-btn-primary:disabled {
		opacity: 0.62;
		cursor: not-allowed;
		transform: none;
		box-shadow: 0 6px 14px rgba(26, 86, 219, 0.16);
		filter: grayscale(0.1);
	}
	
	.mp-btn-secondary {
		background: white;
		color: var(--mp-gray-700);
		font-weight: 600;
		padding: 10px 18px;
		border-radius: 12px;
		border: 1px solid var(--mp-gray-300);
		transition: transform 0.18s ease, box-shadow 0.22s ease, border-color 0.22s ease, background 0.22s ease;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 10px;
		text-decoration: none;
		user-select: none;
	}
	
	.mp-btn-secondary:hover {
		background: var(--mp-gray-50);
		border-color: var(--mp-gray-400);
		transform: translateY(-1px);
		box-shadow: 0 10px 24px rgba(17, 24, 39, 0.08);
	}

	.mp-btn-secondary:active {
		transform: translateY(0px);
	}

	.mp-btn-secondary:disabled {
		opacity: 0.70;
		cursor: not-allowed;
		transform: none;
		box-shadow: none;
	}
	
	/* Mypowerly Status Badges */
	.mp-status-badge {
		display: inline-flex;
		align-items: center;
		padding: 6px 12px;
		border-radius: 20px;
		font-size: 12px;
		font-weight: 600;
		gap: 6px;
	}
	
	.mp-status-connected {
		background: rgba(5, 150, 105, 0.1);
		color: var(--mp-success);
		border: 1px solid rgba(5, 150, 105, 0.2);
	}
	
	.mp-status-disconnected {
		background: rgba(220, 38, 38, 0.1);
		color: var(--mp-error);
		border: 1px solid rgba(220, 38, 38, 0.2);
	}
	
	/* Mypowerly Progress Bars */
	.mp-progress-bar {
		height: 8px;
		background: var(--mp-gray-200);
		border-radius: 4px;
		overflow: hidden;
	}
	
	.mp-progress-fill {
		box-shadow: 0 6px 18px rgba(26, 86, 219, 0.16);
	}
	
	.mp-progress-fill {
		height: 100%;
		background: linear-gradient(90deg, var(--mp-primary) 0%, var(--mp-secondary) 100%);
		border-radius: 4px;
		transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
	}
	
	/* Animations */
	@keyframes mp-float {
		0%, 100% { transform: translateY(0px); }
		50% { transform: translateY(-8px); }
	}
	
	@keyframes mp-pulse {
		0%, 100% { opacity: 1; }
		50% { opacity: 0.7; }
	}
	
	.mp-animate-float {
		animation: mp-float 3s ease-in-out infinite;
	}
	
	.mp-animate-pulse {
		animation: mp-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
	}
	
	/* Glass Morphism */
	.mp-glass {
		background: rgba(255, 255, 255, 0.8);
		backdrop-filter: blur(10px);
		-webkit-backdrop-filter: blur(10px);
		border: 1px solid rgba(255, 255, 255, 0.2);
	}
	
	/* Loading Spinner */
	.mp-spinner {
		width: 24px;
		height: 24px;
		border: 3px solid rgba(26, 86, 219, 0.1);
		border-top-color: var(--mp-primary);
		border-radius: 50%;
		animation: spin 1s linear infinite;
	}
	
	@keyframes spin {
		to { transform: rotate(360deg); }
	}
	
	/* Table Styles */
	.mp-table {
		width: 100%;
		border-collapse: separate;
		border-spacing: 0;
		border-radius: 12px;
		overflow: hidden;
		background: white;
	}
	
	.mp-table thead th {
		position: sticky;
		top: 0;
		z-index: 10;
	}
	
	.mp-table th {
		background: linear-gradient(180deg, var(--mp-gray-50) 0%, var(--mp-gray-100) 100%);
		color: var(--mp-gray-700);
		font-weight: 600;
		font-size: 12px;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		padding: 10px 12px;
		border-bottom: 2px solid var(--mp-gray-200);
	}
	
	.mp-table tbody tr:nth-child(even) td {
		background: #fbfdff;
	}
	
	.mp-table tbody tr:hover td {
		background: #f8fafc;
	}
	
	.mp-table td {
		padding: 10px 12px;
		border-bottom: 1px solid var(--mp-gray-100);
		font-size: 13px;
		color: var(--mp-gray-800);
		transition: background 0.18s ease;
	}

	.mp-table td:first-child {
		border-left: 0;
	}

	.mp-table td:last-child {
		border-right: 0;
	}
	
	/* Form Controls */
	.mp-input {
		width: 100%;
		padding: 11px 14px;
		border: 1px solid var(--mp-gray-300);
		border-radius: 12px;
		font-size: 14px;
		transition: all 0.3s ease;
		background: white;
		box-shadow: 0 1px 0 rgba(17, 24, 39, 0.02);
	}

	.mp-input:hover {
		border-color: var(--mp-gray-400);
		background: var(--mp-gray-50);
	}

	.mp-input:disabled {
		opacity: 0.75;
		cursor: not-allowed;
		background: var(--mp-gray-100);
	}

	.mp-roles-table {
		width: 100%;
		border-collapse: separate;
		border-spacing: 0;
	}

	.mp-roles-table th {
		background: var(--mp-gray-50);
		color: var(--mp-gray-700);
		font-weight: 700;
		font-size: 11px;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		padding: 8px 10px;
		border-bottom: 2px solid var(--mp-gray-200);
		white-space: nowrap;
	}

	.mp-roles-table td {
		padding: 8px 10px;
		border-bottom: 1px solid var(--mp-gray-100);
		font-size: 13px;
		vertical-align: middle;
	}

	.mp-roles-table th:nth-child(2),
	.mp-roles-table td:nth-child(2) {
		text-align: center;
		width: 72px;
		white-space: nowrap;
	}

	.mp-roles-table th:nth-child(3),
	.mp-roles-table td:nth-child(3) {
		text-align: right;
		width: 110px;
		white-space: nowrap;
	}

	.mp-roles-table tbody tr:nth-child(even) td {
		background: #fbfdff;
	}

	.mp-roles-table tbody tr:hover td {
		background: #f8fafc;
	}

	.mp-roles-toggle {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 86px;
		padding: 6px 10px;
		border-radius: 999px;
		font-size: 12px;
		font-weight: 700;
		border: 1px solid rgba(209, 213, 219, 0.9);
		background: rgba(255, 255, 255, 0.9);
		color: var(--mp-gray-700);
		transition: all 0.15s ease;
		cursor: pointer;
	}

	.mp-roles-toggle:hover {
		border-color: rgba(156, 163, 175, 0.9);
		background: rgba(249, 250, 251, 1);
	}

	.mp-roles-toggle.is-on {
		border-color: rgba(5, 150, 105, 0.28);
		background: rgba(5, 150, 105, 0.12);
		color: var(--mp-success);
	}
	
	.mp-input::placeholder {
		color: #9ca3af;
	}
	
	.mp-input:focus {
		outline: none;
		border-color: var(--mp-primary);
		box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.1);
	}
	
	.mp-select {
		appearance: none;
		background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
		background-position: right 0.8rem center;
		background-repeat: no-repeat;
		background-size: 1.15em 1.15em;
		padding-right: 2.75rem;
		cursor: pointer;
	}

	/* Sync All Data Section Styles */
	.sync-step {
		transition: all 0.3s ease;
	}

	.mp-sync-all {
		padding: 0 !important;
		overflow: hidden;
		border-radius: 18px;
		border: 1px solid rgba(229, 231, 235, 0.9);
		box-shadow: 0 12px 42px rgba(17, 24, 39, 0.08);
	}

	.mp-sync-all-header {
		padding: 18px 20px;
		background: linear-gradient(135deg, var(--mp-primary) 0%, var(--mp-secondary) 100%);
		color: #ffffff;
		position: relative;
		overflow: hidden;
	}

	.mp-sync-all-header::after {
		content: "";
		position: absolute;
		inset: 0;
		background: radial-gradient(900px 240px at 18% 0%, rgba(255,255,255,0.20) 0%, rgba(255,255,255,0) 60%);
		pointer-events: none;
		opacity: 0.9;
	}

	.mp-sync-all-header > * {
		position: relative;
		z-index: 1;
	}

	.mp-sync-all-title {
		font-size: 18px;
		font-weight: 800;
		letter-spacing: -0.01em;
		margin: 0;
		color: #ffffff;
	}

	.mp-sync-all-subtitle {
		margin: 3px 0 0;
		font-size: 13px;
		color: rgba(255,255,255,0.85);
		line-height: 1.35;
	}

	.mp-sync-all-badges {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
		margin-top: 12px;
	}

	.mp-sync-all-badge {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		padding: 6px 10px;
		border-radius: 999px;
		font-size: 12px;
		font-weight: 700;
		background: rgba(255,255,255,0.14);
		border: 1px solid rgba(255,255,255,0.18);
		backdrop-filter: blur(6px);
		-webkit-backdrop-filter: blur(6px);
		color: rgba(255,255,255,0.92);
		white-space: nowrap;
	}

	.mp-sync-all-badge-dot {
		width: 8px;
		height: 8px;
		border-radius: 999px;
		background: rgba(255,255,255,0.95);
		box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
		opacity: 0.95;
	}

	.mp-sync-all-body {
		padding: 14px 20px 18px;
		display: grid;
		grid-template-columns: 1fr auto;
		gap: 14px;
		align-items: start;
		background: linear-gradient(180deg, rgba(249,250,251,0.85) 0%, rgba(255,255,255,1) 55%);
		border-bottom: 1px solid rgba(229, 231, 235, 0.75);
	}

	.mp-sync-all-consent {
		padding: 12px 12px;
		border-radius: 14px;
		border: 1px solid rgba(26, 86, 219, 0.22);
		background: rgba(26, 86, 219, 0.06);
		display: flex;
		gap: 10px;
		align-items: flex-start;
	}

	.mp-sync-all-consent input[type="checkbox"] {
		margin-top: 3px;
		accent-color: var(--mp-primary);
	}

	.mp-sync-all-actions {
		display: flex;
		flex-direction: column;
		gap: 10px;
		align-items: stretch;
		justify-content: center;
		min-width: 220px;
	}

	.mp-sync-all-actions .mp-btn-primary {
		width: 100%;
		justify-content: center;
		white-space: nowrap;
	}

	.mp-sync-all-actions-note {
		font-size: 12px;
		color: var(--mp-gray-600);
		line-height: 1.35;
		text-align: center;
	}

	.mp-sync-all-progress,
	.mp-sync-all-results,
	.mp-sync-all-error {
		padding: 16px 20px 18px;
	}

	@media (max-width: 900px) {
		.mp-sync-all-body {
			grid-template-columns: 1fr;
		}

		.mp-sync-all-actions {
			min-width: 0;
			width: 100%;
		}

		.mp-sync-all-actions-note {
			text-align: left;
		}
	}
	
	.step-status {
		display: inline-block;
		padding: 4px 12px;
		border-radius: 20px;
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}
	
	.step-pending {
		background: #f3f4f6;
		color: #6b7280;
	}
	
	.step-processing {
		background: #fef3c7;
		color: #92400e;
	}
	
	.step-success {
		background: #d1fae5;
		color: #065f46;
	}
	
	.step-error {
		background: #fee2e2;
		color: #991b1b;
	}

	/* Sync All Progress Animation */
	@keyframes pulse-sync {
		0%, 100% { opacity: 1; }
		50% { opacity: 0.5; }
	}
	
	.sync-all-processing {
		animation: pulse-sync 1s infinite;
	}

	/* Amount Column Styles */
	.amount-cell {
		font-weight: 600;
		text-align: right;
		white-space: nowrap;
	}
	
	.amount-positive {
		color: #059669;
	}
	
	.amount-zero {
		color: #6b7280;
	}
	
	.amount-tooltip {
		position: relative;
		cursor: help;
	}
	
	.amount-tooltip:hover::after {
		content: attr(data-tooltip);
		position: absolute;
		bottom: 100%;
		left: 50%;
		transform: translateX(-50%);
		background: rgba(0, 0, 0, 0.8);
		color: white;
		padding: 6px 10px;
		border-radius: 4px;
		font-size: 12px;
		white-space: nowrap;
		z-index: 1000;
		pointer-events: none;
	}

	/* Payout Summary Styles */
	.payout-summary {
		background: linear-gradient(135deg, #f8fafc 0%, #f0f4ff 100%);
		border: 1px solid #e5e7eb;
		border-radius: 12px;
		padding: 12px;
		margin-bottom: 12px;
	}
	
	.summary-row {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 8px;
	}
	
	.summary-label {
		color: #6b7280;
	}

	/* Team Data Section Styles */
	.team-member-card {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 10px;
		background: white;
		border-radius: 12px;
		border: 1px solid #e5e7eb;
		transition: all 0.3s ease;
	}
	
	.team-member-card:hover {
		border-color: #3b82f6;
		box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
	}
	
	.team-member-avatar {
		width: 40px;
		height: 40px;
		border-radius: 50%;
		background: linear-gradient(135deg, #6366f1, #8b5cf6);
		display: flex;
		align-items: center;
		justify-content: center;
		color: white;
		font-weight: bold;
		font-size: 16px;
	}
	
	.team-member-info {
		flex: 1;
	}
	
	.team-member-name {
		font-weight: 600;
		color: #111827;
		margin-bottom: 2px;
	}
	
	.team-member-role {
		font-size: 12px;
		color: #6b7280;
	}
	
	.team-member-email {
		font-size: 11px;
		color: #9ca3af;
		margin-top: 2px;
	}

	/* Responsive Grid Layout */
	@media (max-width: 768px) {
		.grid-cols-1 {
			grid-template-columns: 1fr !important;
		}
		
		.sync-step {
			margin-bottom: 12px;
		}
		
		.team-member-card {
			flex-direction: column;
			text-align: center;
			padding: 16px;
		}
		
		.team-member-info {
			text-align: center;
		}
	}

	@media (min-width: 769px) and (max-width: 1024px) {
		.grid-cols-2 {
			grid-template-columns: repeat(2, 1fr) !important;
		}
	}

	.mp-metric-card {
		--mp-card-accent: var(--mp-primary);
		--mp-card-accent-rgb: 26, 86, 219;
		--mp-card-accent-2: var(--mp-secondary);
		position: relative;
		overflow: hidden;
		border: 1px solid rgba(229, 231, 235, 0.9);
		box-shadow: 0 10px 40px rgba(17, 24, 39, 0.08);
		border-radius: 18px;
		transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
	}

	@media (min-width: 768px) {
		.mp-metric-card {
			min-height: 760px;
		}
	}

	.mp-metric-card::before {
		content: "";
		position: absolute;
		left: 0;
		right: 0;
		top: 0;
		height: 14px;
		border-radius: 18px 18px 0 0;
		pointer-events: none;
		background: linear-gradient(90deg, var(--mp-card-accent) 0%, var(--mp-card-accent-2) 100%);
		opacity: 1;
	}

	.mp-metric-card::after {
		content: "";
		position: absolute;
		inset: 0;
		border-radius: 18px;
		background: radial-gradient(900px 300px at 85% 0%, rgba(var(--mp-card-accent-rgb), 0.12) 0%, rgba(var(--mp-card-accent-rgb), 0.00) 55%);
		opacity: 1;
		pointer-events: none;
	}

	.mp-card-label {
		text-transform: uppercase;
		font-weight: 900;
		letter-spacing: 0.10em;
		color: var(--mp-gray-900);
		text-align: center;
		font-size: 18px;
		padding-top: 8px;
		margin-bottom: 14px;
	}

	.mp-card-label::after {
		content: "";
		display: block;
		height: 4px;
		width: 82px;
		margin: 10px auto 0;
		border-radius: 999px;
		background: linear-gradient(90deg, var(--mp-card-accent) 0%, var(--mp-card-accent-2) 100%);
		box-shadow: 0 10px 20px rgba(var(--mp-card-accent-rgb), 0.18);
	}

	.mp-metric-card .mp-card-icon {
		background: linear-gradient(135deg, rgba(var(--mp-card-accent-rgb), 0.20) 0%, rgba(var(--mp-card-accent-rgb), 0.10) 100%) !important;
		box-shadow: 0 14px 30px rgba(var(--mp-card-accent-rgb), 0.18);
	}

	.mp-metric-card .mp-card-icon i {
		color: var(--mp-card-accent) !important;
	}

	.mp-metric-card > * {
		position: relative;
		z-index: 1;
	}

	.mp-metric-card:hover {
		transform: translateY(-6px);
		border-color: rgba(var(--mp-card-accent-rgb), 0.35);
		box-shadow: 0 18px 60px rgba(17, 24, 39, 0.14);
	}

	.mp-metric-blue {
		--mp-card-accent: var(--mp-primary);
		--mp-card-accent-rgb: 26, 86, 219;
		--mp-card-accent-2: #60a5fa;
	}

	.mp-metric-purple {
		--mp-card-accent: #7c3aed;
		--mp-card-accent-rgb: 124, 58, 237;
		--mp-card-accent-2: #a78bfa;
	}

	.mp-metric-green {
		--mp-card-accent: #059669;
		--mp-card-accent-rgb: 5, 150, 105;
		--mp-card-accent-2: #34d399;
	}

	.mp-metric-orange {
		--mp-card-accent: #d97706;
		--mp-card-accent-rgb: 217, 119, 6;
		--mp-card-accent-2: #fbbf24;
	}

	.mp-shell {
		color-scheme: light;
	}

	.mp-shell .mp-card {
		border-radius: 18px;
	}

	.mp-shell .mp-card:hover {
		transform: translateY(-3px);
	}

	.mp-shell .mp-page-title {
		letter-spacing: -0.02em;
	}

	.mp-hero {
		border-bottom: 1px solid rgba(255, 255, 255, 0.06);
	}

	.mp-hero-inner {
		max-width: 68rem;
	}

	.mp-hero-actions {
		row-gap: 12px;
	}

	.mp-hero-action {
		position: relative;
		overflow: hidden;
	}

	.mp-hero-action::after {
		content: "";
		position: absolute;
		inset: 0;
		background: radial-gradient(1000px 160px at 20% 0%, rgba(255,255,255,0.14) 0%, rgba(255,255,255,0) 60%);
		opacity: 0;
		transition: opacity 0.25s ease;
		pointer-events: none;
	}

	.mp-hero-action:hover::after {
		opacity: 1;
	}

	.mp-team-card {
		border-radius: 18px;
		overflow: hidden;
		box-shadow: 0 10px 40px rgba(17, 24, 39, 0.08);
	}

	.mp-team-card .mp-team-chip {
		backdrop-filter: blur(10px);
		-webkit-backdrop-filter: blur(10px);
	}

	.mp-team-card .mp-team-stats {
		background: linear-gradient(180deg, rgba(255,255,255,0.88) 0%, rgba(255,255,255,0.72) 100%);
	}

	.mp-team-card .team-member-card {
		border-radius: 14px;
		border-color: rgba(229, 231, 235, 0.9);
		box-shadow: 0 8px 20px rgba(17, 24, 39, 0.06);
	}

	.mp-team-card .team-member-avatar {
		box-shadow: 0 10px 24px rgba(99, 102, 241, 0.20);
	}

	.mp-team-card .team-member-email {
		font-size: 12px;
		color: #6b7280;
	}

	.mp-team-card .mp-team-preview {
		background: linear-gradient(180deg, #f8fafc 0%, #f3f4f6 100%);
		border: 1px solid rgba(229, 231, 235, 0.9);
	}

	.mp-team-card .mp-role-box {
		background: radial-gradient(900px 250px at 20% 0%, rgba(217, 119, 6, 0.10) 0%, rgba(217, 119, 6, 0) 60%),
					linear-gradient(180deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.82) 100%);
	}

	.mp-team-card input[type="checkbox"] {
		box-shadow: 0 0 0 3px rgba(217, 119, 6, 0);
		transition: box-shadow 0.2s ease;
	}

	.mp-team-card input[type="checkbox"]:focus {
		box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.18);
	}

	.mp-btn-primary:focus,
	.mp-btn-secondary:focus {
		outline: none;
		box-shadow: 0 0 0 4px rgba(26, 86, 219, 0.18);
	}

	/* Team Creation Popup Styles */
	.mp-popup-overlay {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background: rgba(0, 0, 0, 0.6);
		backdrop-filter: blur(4px);
		-webkit-backdrop-filter: blur(4px);
		z-index: 9999;
		display: flex;
		align-items: center;
		justify-content: center;
		opacity: 0;
		visibility: hidden;
		transition: all 0.3s ease;
	}

	.mp-popup-overlay.active {
		opacity: 1;
		visibility: visible;
	}

	.mp-popup-modal {
		background: white;
		border-radius: 20px;
		width: 90%;
		max-width: 600px;
		max-height: 90vh;
		overflow-y: auto;
		box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
		transform: scale(0.9) translateY(20px);
		transition: all 0.3s ease;
		border: 1px solid var(--mp-gray-200);
	}

	.mp-popup-overlay.active .mp-popup-modal {
		transform: scale(1) translateY(0);
	}

	.mp-popup-header {
		background: linear-gradient(135deg, var(--mp-primary) 0%, var(--mp-secondary) 100%);
		color: white;
		padding: 24px;
		border-radius: 20px 20px 0 0;
		position: relative;
	}

	.mp-popup-close {
		position: absolute;
		top: 20px;
		right: 20px;
		background: rgba(255, 255, 255, 0.2);
		border: none;
		color: white;
		width: 32px;
		height: 32px;
		border-radius: 50%;
		cursor: pointer;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: all 0.2s ease;
		font-size: 16px;
	}

	.mp-popup-close:hover {
		background: rgba(255, 255, 255, 0.3);
		transform: scale(1.1);
	}

	.mp-popup-body {
		padding: 32px;
	}

	.mp-form-group {
		margin-bottom: 24px;
	}

	.mp-form-label {
		display: block;
		font-weight: 600;
		color: var(--mp-gray-800);
		margin-bottom: 8px;
		font-size: 14px;
	}

	.mp-form-label .required {
		color: var(--mp-error);
		margin-left: 4px;
	}

	.mp-form-input {
		width: 100%;
		padding: 12px 16px;
		border: 2px solid var(--mp-gray-200);
		border-radius: 12px;
		font-size: 14px;
		transition: all 0.3s ease;
		background: white;
	}

	.mp-form-input:focus {
		outline: none;
		border-color: var(--mp-primary);
		box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.1);
	}

	.mp-form-input.error {
		border-color: var(--mp-error);
		box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
	}

	.mp-form-error {
		color: var(--mp-error);
		font-size: 12px;
		margin-top: 4px;
		display: none;
	}

	.mp-form-error.show {
		display: block;
	}

	.mp-role-selection {
		background: var(--mp-gray-50);
		border-radius: 12px;
		padding: 20px;
		margin-bottom: 24px;
		border: 1px solid var(--mp-gray-200);
	}

	.mp-role-selection-title {
		font-weight: 600;
		color: var(--mp-gray-800);
		margin-bottom: 16px;
		font-size: 14px;
	}

	.mp-role-checkboxes {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
		gap: 12px;
	}

	.mp-role-checkbox {
		display: flex;
		align-items: center;
		padding: 12px;
		background: white;
		border: 2px solid var(--mp-gray-200);
		border-radius: 10px;
		cursor: pointer;
		transition: all 0.2s ease;
	}

	.mp-role-checkbox:hover {
		border-color: var(--mp-primary);
		background: rgba(26, 86, 219, 0.02);
	}

	.mp-role-checkbox.selected {
		border-color: var(--mp-primary);
		background: rgba(26, 86, 219, 0.1);
		box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.1);
	}

	.mp-role-checkbox input[type="checkbox"] {
		margin-right: 8px;
		width: 18px;
		height: 18px;
		accent-color: var(--mp-primary);
	}

	.mp-role-checkbox label {
		cursor: pointer;
		font-size: 14px;
		color: var(--mp-gray-700);
		font-weight: 500;
		flex: 1;
	}

	.mp-popup-footer {
		padding: 24px 32px;
		border-top: 1px solid var(--mp-gray-200);
		display: flex;
		gap: 12px;
		justify-content: flex-end;
		background: var(--mp-gray-50);
		border-radius: 0 0 20px 20px;
	}

	.mp-popup-actions {
		display: flex;
		gap: 12px;
	}

	.mp-btn-cancel {
		background: white;
		color: var(--mp-gray-700);
		border: 2px solid var(--mp-gray-300);
		padding: 12px 24px;
		border-radius: 12px;
		font-weight: 600;
		cursor: pointer;
		transition: all 0.2s ease;
		font-size: 14px;
	}

	.mp-btn-cancel:hover {
		background: var(--mp-gray-50);
		border-color: var(--mp-gray-400);
	}

	.mp-btn-create {
		background: linear-gradient(135deg, var(--mp-primary) 0%, var(--mp-primary-dark) 100%);
		color: white;
		border: none;
		padding: 12px 32px;
		border-radius: 12px;
		font-weight: 600;
		cursor: pointer;
		transition: all 0.2s ease;
		font-size: 14px;
		box-shadow: 0 4px 12px rgba(26, 86, 219, 0.25);
	}

	.mp-btn-create:hover {
		transform: translateY(-2px);
		box-shadow: 0 8px 20px rgba(26, 86, 219, 0.35);
	}

	.mp-btn-create:disabled {
		opacity: 0.6;
		cursor: not-allowed;
		transform: none;
		box-shadow: none;
	}

	.mp-popup-loading {
		display: none;
		text-align: center;
		padding: 40px;
	}

	.mp-popup-loading.show {
		display: block;
	}

	.mp-spinner-lg {
		width: 48px;
		height: 48px;
		border: 4px solid rgba(26, 86, 219, 0.1);
		border-top-color: var(--mp-primary);
		border-radius: 50%;
		animation: spin 1s linear infinite;
		margin: 0 auto 16px;
	}

	.mp-instruction-text {
		background: rgba(26, 86, 219, 0.05);
		border-left: 4px solid var(--mp-primary);
		padding: 16px;
		border-radius: 8px;
		margin-bottom: 24px;
		font-size: 14px;
		color: var(--mp-gray-700);
		line-height: 1.5;
	}

	.mp-help-tooltip {
		position: relative;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		flex: 0 0 auto;
	}

	.mp-help-icon:hover {
		border-color: rgba(26, 86, 219, 0.35);
		box-shadow: 0 10px 24px rgba(17, 24, 39, 0.10);
		transform: translateY(-1px);
	}

	.mp-help-icon:focus {
		outline: none;
		box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.18);
		border-color: rgba(26, 86, 219, 0.45);
	}

	.mp-metric-card .mp-help-icon:hover {
		border-color: rgba(var(--mp-card-accent-rgb), 0.35);
		box-shadow: 0 10px 24px rgba(17, 24, 39, 0.10);
		transform: translateY(-1px);
	}

	.mp-metric-card .mp-help-icon:focus {
		box-shadow: 0 0 0 3px rgba(var(--mp-card-accent-rgb), 0.18);
		border-color: rgba(var(--mp-card-accent-rgb), 0.45);
	}

	.mp-help-bubble {
		position: absolute;
		bottom: 44px;
		right: 0;
		width: 580px;
		max-width: min(580px, 90vw);
		background: #fff;
		border: 1px solid var(--mp-gray-200);
		border-radius: 12px;
		padding: 16px;
		box-shadow: 0 18px 55px rgba(17, 24, 39, 0.14);
		z-index: 99999;
		display: none;
	}

	.mp-help-tooltip:hover .mp-help-bubble,
	.mp-help-tooltip:focus-within .mp-help-bubble {
		display: block;
	}

	.mp-help-bubble .mp-help-title {
		font-weight: 800;
		color: var(--mp-gray-800);
		margin-bottom: 6px;
		font-size: 13px;
		letter-spacing: 0.01em;
	}

	.mp-help-bubble .mp-help-line {
		font-size: 13px;
		color: var(--mp-gray-700);
		line-height: 1.45;
		margin: 6px 0;
	}

	@media (max-width: 640px) {
		.mp-popup-modal {
			width: 95%;
			margin: 20px;
		}

		.mp-popup-body {
			padding: 24px 20px;
		}

		.mp-popup-footer {
			padding: 20px;
			flex-direction: column;
		}

		.mp-popup-actions {
			width: 100%;
		}

		.mp-btn-cancel,
		.mp-btn-create {
			flex: 1;
		}

		.mp-role-checkboxes {
			grid-template-columns: 1fr;
		}
	}
</xstyle>

<?php endif; ?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 to-blue-50 mp-shell">
	<!-- Header -->
	<?php if ( $is_connected && ! empty( $credentials ) ) : ?>
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
								<div class="inline-flex items-center gap-3 px-8 py-4 bg-emerald-400/25 backdrop-blur-xl rounded-full border border-emerald-300/90 shadow-2xl ring-1 ring-emerald-300/30" style="box-shadow: 0 20px 52px #03b87bff;" title="An account for you has been created in mypowerly. Only your profile data has been sent." aria-label="An account for you has been created in mypowerly. Only your profile data has been sent.">
                                    <i class="fas fa-circle-check" style="font-size: 18px; color: #a7f3d0;" aria-hidden="true"></i>
									<span class="font-extrabold text-white" style="letter-spacing: 0.02em;">Live Connection</span>
                                    <span class="text-emerald-50" style="opacity: 0.95;" aria-hidden="true">&bull;</span>
									<span class="text-emerald-50" style="color: #ffffff; opacity: 1;">Connected with Mypowerly</span>
                                    <i class="fas fa-circle-info" style="font-size: 14px; color: #d1fae5; opacity: 0.9;" aria-hidden="true"></i>
								</div>
							</div>

							<div class="flex flex-col items-center md:items-end gap-3 pr-2 md:pr-6" style="padding-right: 32px;">
								<div class="flex items-center justify-center md:justify-end gap-3 flex-wrap">
									<button type="button" class="mp-btn-secondary mp-hero-action" id="disconnect-mypowerly">
                                        <i class="fas fa-right-from-bracket" aria-hidden="true"></i>
										Disconnect
									</button>

									<a href="https://mypowerly.com" target="_blank" rel="noopener noreferrer" class="mp-btn-primary mp-hero-action">
                                        <i class="fas fa-up-right-from-square" aria-hidden="true"></i>
										Go to MyPowerly
									</a>
								</div>
							</div>
						</div>

						<div class="text-center max-w-4xl mx-auto mp-hero-inner">
							<h1 class="text-5xl md:text-7xl font-bold mb-6 tracking-tight mp-page-title">
								<span class="bg-clip-text text-transparent bg-gradient-to-r from-white via-blue-200 to-indigo-200">
									Vendor Onboarding W9-1099 Chaser by Mypowerly
								</span>
								<br>
							</h1>

							<p class="text-xl text-gray-300 mb-8 leading-relaxed max-w-2xl mx-auto">
								Securely connected WordPress to Mypowerly (API Provider).
								This plugin sends data only when an administrator clicks Connect/Sync.
							</p>

							<div class="flex items-center justify-center gap-10 mb-8">
								<div class="text-center">
									<div class="text-3xl font-bold text-white"><?php echo esc_html( $total_affiliates ); ?></div>
									<div class="text-sm text-gray-400">Affiliates/Vendors</div>
								</div>
								<div class="text-center">
									<div class="text-3xl font-bold text-white"><?php echo esc_html( (string) count( $detected_plugins ) ); ?></div>
									<div class="text-sm text-gray-400">Plugins</div>
								</div>
								<div class="text-center">
									<div class="text-3xl font-bold text-white"><?php echo esc_html( (string) $team_users_total_filtered ); ?></div>
									<div class="text-sm text-gray-400">Users</div>
								</div>
							</div>

							<div class="w-full flex justify-center mt-6">
								<div class="w-full max-w-md">
									<?php w9_1099_chaser_render_header_support( 'connected' ); ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-gray-900 to-transparent"></div>
		</div>
	<?php else : ?>
		<div class="mp-header mb-12" style="padding: 0; background: linear-gradient(135deg, var(--mp-primary) 0%, var(--mp-primary-dark) 100%);">
			<div class="mp-page" style="padding: 28px 26px;">
				<div style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 18px;">
					<div style="display: flex; gap: 14px; align-items: flex-start; min-width: 280px;">
						<div style="width: 54px; height: 54px; border-radius: 9999px; overflow: hidden; background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.18); display: flex; align-items: center; justify-content: center;">
							<img src="<?php echo esc_url( w91099ch_PLUGIN_URL . 'assets/logo/logo-removebg-preview.png' ); ?>" alt="Vendor Onboarding W9-1099 Chaser by Mypowerly" style="width: 100%; height: 100%; object-fit: cover; display: block; border-radius: 9999px; transform: scale(1.5); transform-origin: center;" />
						</div>
						<div>
							<div style="font-size: 28px; font-weight: 900; letter-spacing: -0.02em; color: #ffffff; line-height: 1.1;">Vendor Onboarding W9-1099 Chaser by Mypowerly</div>
							<div style="font-size: 13px; color: rgba(255,255,255,0.82); font-weight: 600; margin-top: 2px;">Professional Tax Form Management</div>
						</div>
					</div>

					<div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: flex-end; flex: 1 1 240px; min-width: 240px;">
						<a href="https://1099automation.com" target="_blank" rel="noopener noreferrer" class="mp-btn-secondary" style="white-space: nowrap;">
							Visit 1099automation.com
						</a>
						<?php w9_1099_chaser_render_header_support( 'disconnected' ); ?>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>

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

										<?php
										$w9_default_page_id  = absint( get_option( 'w91099ch_w9_default_page_id', 0 ) );
										$w9_default_page_url = $w9_default_page_id ? (string) get_permalink( $w9_default_page_id ) : '';
										?>
										<div class="relative" id="w91099ch-w9-tools" data-default-page-url="<?php echo esc_attr( $w9_default_page_url ); ?>">
											<button type="button" id="w91099ch-w9-tools-btn" class="px-3 py-1.5 rounded-lg font-semibold text-xs bg-white border border-gray-200 hover:bg-gray-50 transition-colors flex items-center gap-1" aria-haspopup="true" aria-expanded="false">
												<?php echo esc_html__( 'W-9 & 1099 Tools', 'w9-1099-chaser' ); ?>
												<i class="fas fa-chevron-down" style="font-size: 10px; opacity: .8;"></i>
											</button>
											<div id="w91099ch-w9-tools-menu" class="hidden absolute right-0 mt-2 w-56 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden" style="z-index: 50;">
												<button type="button" data-action="copy" class="w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors flex items-center gap-3">
													<i class="far fa-copy" style="width: 18px;"></i>
													<span><?php echo esc_html__( 'Copy link', 'w9-1099-chaser' ); ?></span>
												</button>
												<button type="button" data-action="email" class="w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors flex items-center gap-3">
													<i class="far fa-envelope" style="width: 18px;"></i>
													<span><?php echo esc_html__( 'Share to email', 'w9-1099-chaser' ); ?></span>
												</button>
												<button type="button" data-action="qr" class="w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors flex items-center gap-3">
													<i class="fas fa-qrcode" style="width: 18px;"></i>
													<span><?php echo esc_html__( 'QR code', 'w9-1099-chaser' ); ?></span>
												</button>
											</div>
										</div>
									</div>
									<div class="text-xs text-gray-600">All form plugins data</div>
								</div>

								<div class="sync-step p-3 bg-gray-50 rounded-xl" data-step="membership">
									<div class="flex items-center gap-3 mb-2">
										<div class="w-9 h-9 rounded-lg bg-pink-100 flex items-center justify-center">
											<i class="fas fa-id-card text-pink-600 icon-tooltip" data-tooltip="Sync all membership plugins"></i>
										</div>
										<div>
											<div class="font-semibold text-gray-800">Memberships</div>
											<div class="step-status step-pending">Pending</div>
										</div>
									</div>
									<div class="text-xs text-gray-600">All membership data</div>
								</div>

								<div class="sync-step p-3 bg-gray-50 rounded-xl" data-step="contractor">
									<div class="flex items-center gap-3 mb-2">
										<div class="w-9 h-9 rounded-lg bg-orange-100 flex items-center justify-center">
											<i class="fas fa-user-tie text-orange-600 icon-tooltip" data-tooltip="Sync contractor plugins"></i>
										</div>
										<div>
											<div class="font-semibold text-gray-800">Contractors</div>
											<div class="step-status step-pending">Pending</div>
										</div>
									</div>
									<div class="text-xs text-gray-600">Contractor data</div>
								</div>

								<div class="sync-step p-3 bg-gray-50 rounded-xl" data-step="freelancer">
									<div class="flex items-center gap-3 mb-2">
										<div class="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center">
											<i class="fas fa-user-clock text-emerald-600 icon-tooltip" data-tooltip="Sync freelancer plugins"></i>
										</div>
										<div>
											<div class="font-semibold text-gray-800">Freelancers</div>
											<div class="step-status step-pending">Pending</div>
										</div>
									</div>
									<div class="text-xs text-gray-600">Freelancer data</div>
								</div>

								<div class="sync-step p-3 bg-gray-50 rounded-xl" data-step="accounting">
									<div class="flex items-center gap-3 mb-2">
										<div class="w-9 h-9 rounded-lg bg-indigo-100 flex items-center justify-center">
											<i class="fas fa-calculator text-indigo-600 icon-tooltip" data-tooltip="Sync accounting plugins"></i>
										</div>
										<div>
											<div class="font-semibold text-gray-800">Accounting</div>
											<div class="step-status step-pending">Pending</div>
										</div>
									</div>
									<div class="text-xs text-gray-600">Accounting data</div>
								</div>

								<div class="sync-step p-3 bg-gray-50 rounded-xl" data-step="payout">
									<div class="flex items-center gap-3 mb-2">
										<div class="w-9 h-9 rounded-lg bg-sky-100 flex items-center justify-center">
											<i class="fas fa-wallet text-sky-600 icon-tooltip" data-tooltip="Sync wallet/payout data"></i>
										</div>
										<div>
											<div class="font-semibold text-gray-800">Wallet/Payout</div>
											<div class="step-status step-pending">Pending</div>
										</div>
									</div>
									<div class="text-xs text-gray-600">Wallet and payout data</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Results Section -->
					<div class="hidden mp-sync-all-results" id="sync-all-results">
						<div class="p-6 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl">
							<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
								<div class="flex items-center gap-4">
									<div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
										<i class="fas fa-circle-check text-2xl text-green-600"></i>
									</div>
									<div>
										<h4 class="text-xl font-bold text-gray-800 mb-1">Sync Complete!</h4>
										<p class="text-gray-600">All data synchronized successfully with Mypowerly. No further action is required.</p>
									</div>
								</div>
								<div class="text-right">
									<div class="text-sm text-gray-600 mb-1">Total Time</div>
									<div id="sync-all-duration" class="text-2xl font-bold text-gray-800">0s</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Error Section -->
					<div class="hidden mp-sync-all-error" id="sync-all-error">
						<div class="p-6 bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 rounded-xl">
							<div class="flex items-start gap-4">
								<div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
									<i class="fas fa-circle-xmark text-2xl text-red-600"></i>
								</div>
								<div>
									<h4 class="text-xl font-bold text-gray-800 mb-2">Sync Failed</h4>
									<p id="sync-all-error-message" class="text-gray-700"></p>
									<div class="mt-4">
										<button type="button" onclick="window.location.reload()" class="mp-btn-secondary">
											<i class="fas fa-rotate-right mr-2"></i>Try Again
										</button>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Sync Cards Grid - Three Cards Per Row -->
			<div class="mp-cards-grid grid grid-cols-1 md:grid-cols-3 gap-6 <?php echo ( ! $is_connected || empty( $credentials ) ) ? 'mp-cards-disabled' : ''; ?>">

				<!-- Card 1: User Profile -->
				<div class="mp-card mp-metric-card mp-metric-blue p-4 flex flex-col h-full">
					<div class="mp-card-label">User Profile</div>
					<div class="flex items-start justify-between gap-3 mb-3">
						<div class="flex items-start gap-3">
							<div class="w-12 h-12 rounded-xl mp-card-icon flex items-center justify-center">
								<i class="fa-solid fa-user text-xl icon-tooltip" data-tooltip="Current WordPress user profile information"></i>
							</div>
							<div>
								<h3 class="text-base font-bold text-gray-800 mb-1">User Profile</h3>
								<p class="text-sm text-gray-600">Current user and site information</p>
							</div>
						</div>

						<div class="mp-help-tooltip">
							<button type="button" class="mp-help-icon" aria-label="User Profile help">
								<i class="fas fa-circle-question"></i>
							</button>
							<div class="mp-help-bubble">
								<div class="mp-help-title">Complete Profile Data Sent to MyPowerly</div>
								<div class="mp-help-line"><strong>User Information:</strong> ID, username, email, first name, last name, nickname, display name, role, registered date, bio, avatar URL.</div>
								<div class="mp-help-line"><strong>User Preferences:</strong> Admin color scheme, syntax highlighting, keyboard shortcuts, toolbar settings, language preference.</div>
								<div class="mp-help-line"><strong>Contact Information:</strong> Website URL from user profile settings.</div>
								<div class="mp-help-line"><strong>Site Information:</strong> Site name, site URL, admin email address, language settings.</div>
								<div class="mp-help-line"><strong>Data Usage:</strong> All profile information is securely sent to MyPowerly for account management and W-9/1099 workflow processing.</div>
							</div>
						</div>
					</div>

					<div class="space-y-2 mb-3 py-3 border-y border-gray-100">
						<div class="flex justify-between items-center">
							<span class="text-gray-600">Username:</span>
							<strong class="text-gray-800 font-medium"><?php echo esc_html( wp_get_current_user()->user_login ); ?></strong>
						</div>
						<div class="flex justify-between items-center">
							<span class="text-gray-600">Role:</span>
							<strong class="text-gray-800 font-medium"><?php
								$user = wp_get_current_user();
								echo esc_html( ucfirst( $user->roles[0] ?? 'N/A' ) );
							?></strong>
						</div>
						<div class="flex justify-between items-center">
							<span class="text-gray-600">Site:</span>
							<strong class="text-gray-800 font-medium"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong>
						</div>
					</div>

					<!-- Profile Information to be Sent -->
					<div class="mb-4">
						<div class="flex items-center justify-between mb-3">
							<h5 class="font-semibold text-gray-800">Your Profile Information <span class="text-xs text-gray-600">(Editable)</span></h5>
							<button type="button" onclick="window.open('<?php echo esc_url( admin_url( 'profile.php' ) ); ?>', '_blank')" class="text-blue-600 hover:text-blue-800 p-1 hover:bg-blue-50 rounded" title="Edit Profile">
								<i class="fa-solid fa-pencil text-sm"></i>
							</button>
						</div>

						<div class="bg-white border border-gray-200 rounded-lg p-4 w-full">
							<div class="space-y-3 w-full">
								<div class="flex items-center justify-between py-2 border-b border-gray-100 w-full">
									<span class="text-sm text-gray-600 flex items-center flex-shrink-0">
										<i class="fa-solid fa-user text-gray-400 mr-2 w-4"></i>
										Username
									</span>
									<span class="text-sm font-medium text-gray-900 text-right ml-4"><?php echo esc_html( wp_get_current_user()->user_login ); ?></span>
								</div>
								<div class="flex items-center justify-between py-2 border-b border-gray-100 w-full">
									<span class="text-sm text-gray-600 flex items-center flex-shrink-0">
										<i class="fa-solid fa-envelope text-gray-400 mr-2 w-4"></i>
										Email
									</span>
									<span class="text-sm font-medium text-gray-900 text-right ml-4"><?php echo esc_html( wp_get_current_user()->user_email ); ?></span>
								</div>
								<div class="flex items-center justify-between py-2 border-b border-gray-100 w-full">
									<span class="text-sm text-gray-600 flex items-center flex-shrink-0">
										<i class="fa-solid fa-shield-alt text-gray-400 mr-2 w-4"></i>
										Role
									</span>
									<span class="text-sm font-medium text-gray-900 text-right ml-4"><?php
										$user = wp_get_current_user();
										echo esc_html( ucfirst( $user->roles[0] ?? 'N/A' ) );
									?></span>
								</div>
								<div class="flex items-center justify-between py-2 w-full">
									<span class="text-sm text-gray-600 flex items-center flex-shrink-0">
										<i class="fa-solid fa-globe text-gray-400 mr-2 w-4"></i>
										Website
									</span>
									<span class="text-sm font-medium text-gray-900 text-right ml-4"><?php
										$website = get_user_meta( get_current_user_id(), 'user_url', true );
										echo $website ? esc_html( $website ) : 'Not set';
									?></span>
								</div>
							</div>
						</div>
					</div>

					<div class="space-y-3 mt-auto">
						<div class="p-3 bg-blue-50 rounded-lg border border-blue-200 flex items-start gap-3">
							<input type="checkbox" id="mypowerly-consent-profile-sync" class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded" />
							<div class="flex-1 text-sm text-gray-700">
								<p class="font-semibold text-gray-900">Consent required</p>
								<p class="text-gray-700">I understand that clicking <strong>Sync Profile Now</strong> will send my profile information to the external Mypowerly service (<code>https://mypowerly.com</code>) for account management purposes.</p>
							</div>
						</div>

						<button type="button" class="mp-btn-primary w-full flex items-center justify-center gap-3 opacity-60 cursor-not-allowed" id="profile-sync" disabled>
							<i class="fa-solid fa-rotate mp-animate-pulse"></i>
							Sync Profile Now
						</button>

						<div class="hidden" id="profile-sync-progress-section">
							<div class="mb-3">
								<div class="mp-progress-bar">
									<div id="profile-sync-progress-fill" class="mp-progress-fill" style="width: 0%"></div>
								</div>
							</div>
							<div class="flex justify-between items-center text-sm">
								<span id="profile-sync-progress-text" class="font-medium text-blue-600">0%</span>
								<span id="current-profile-sync-step" class="text-gray-600">Initializing...</span>
							</div>
						</div>

						<div class="hidden" id="profile-sync-results">
							<div class="p-4 bg-green-50 rounded-xl border border-green-200">
								<div class="flex items-center justify-between">
									<div class="flex items-center gap-3">
										<i class="fas fa-circle-check text-green-600"></i>
										<span class="font-medium text-gray-800">Profile synced successfully!</span>
									</div>
									<span class="text-sm text-gray-600">Time: <span id="profile-sync-duration" class="font-medium">0s</span></span>
								</div>
							</div>
						</div>
					</div>
				</div>

					<!-- Card 2: Plugin Data Sync -->
					<div class="mp-card mp-metric-card mp-metric-purple p-4 flex flex-col h-full">
						<div class="mp-card-label">All plugins</div>
						<div class="flex items-start justify-between gap-3 mb-3">
							<div class="flex items-start gap-3">
								<div class="w-12 h-12 rounded-xl mp-card-icon flex items-center justify-center">
									<i class="fa-solid fa-plug text-xl icon-tooltip" data-tooltip="Manage affiliate and vendor plugins"></i>
								</div>
								<div>
									<h3 class="text-base font-bold text-gray-800 mb-1">All plugins</h3>
									<p class="text-sm text-gray-600">Sync detected </p>
								</div>
							</div>

							<div class="mp-help-tooltip">
								<button type="button" class="mp-help-icon" aria-label="All plugins help">
									<i class="fa-solid fa-circle-question"></i>
								</button>
								<div class="mp-help-bubble">
									<div class="mp-help-title">What this card does</div>
									<div class="mp-help-line"><strong>Refresh:</strong> Re-scans installed plugins and updates the detected list.</div>
									<div class="mp-help-line"><strong>Sync Plugins Info Now:</strong> Sends detected All plugins and IDs to Mypowerly.</div>
									<div class="mp-help-line"><strong>What is stored in WordPress:</strong> <strong>last sync time</strong> and some <strong>plugin IDs/mapping</strong> (to match the same plugins in MyPowerly).</div>
									<div class="mp-help-line"><strong>In Mypowerly (MyPowerly):</strong> Updates the plugins catalog so affiliate syncing and processing stays accurate.</div>
								</div>
							</div>
						</div>

						<div class="space-y-2 mb-3 py-3 border-y border-gray-100">
							<div class="flex justify-between items-center">
								<span class="text-gray-600">Last Sync:</span>
								<strong id="last-plugin-sync-time" class="text-gray-800 font-medium"><?php echo esc_html( $plugin_last_sync_formatted ); ?></strong>
							</div>
							<div class="flex justify-between items-center hidden">
								<span class="text-gray-600">Status:</span>
								<strong id="plugin-sync-status" class="text-green-600 font-semibold">Ready</strong>
							</div>
						</div>

						<!-- Detected Plugins Section -->
						<div class="mb-3">
							<div class="flex justify-between items-center mb-3">
								<h4 class="font-bold text-gray-800">Detected Plugins</h4>
								<button type="button" id="refresh-plugins" class="mp-btn-secondary flex items-center gap-2">
									<i class="fa-solid fa-arrow-rotate-right text-xs icon-tooltip" data-tooltip="Refresh plugin list"></i>
									Refresh
								</button>
							</div>

							<div class="text-xs text-gray-500 -mt-2 mb-3">
								If a plugin is not detected due to an issue or to exclude any plugin please go to Settings.
							</div>

							<div class="mb-3">
								<div class="flex items-center gap-4 text-sm">
									<span id="plugins-count" class="font-medium text-gray-700"><?php echo esc_html( (string) count( $detected_plugins ) ); ?> plugins detected</span>
									<span class="text-gray-400">|</span>
									<span id="total-affiliates-count" class="font-medium text-gray-700"><?php echo esc_html( (string) $total_affiliates ); ?> total affiliates/vendors</span>
								</div>
							</div>

							<div class="bg-gray-50 rounded-xl p-3 max-h-64 overflow-y-auto" id="detected-plugins-container">
								<?php if ( ! empty( $detected_plugins ) ) : ?>
									<div class="space-y-3" id="detected-plugins-list">
										<?php foreach ( $detected_plugins as $slug => $plugin ) : ?>
											<div class="p-2 bg-white rounded-lg border border-gray-200 hover:border-blue-300 transition-colors">
												<div class="flex justify-between items-center">
													<div class="flex-1">
														<div class="flex items-center gap-3">
															<div class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center">
																<i class="fa-solid fa-cube text-sm text-blue-600 icon-tooltip" data-tooltip="Detected affiliate plugin"></i>
															</div>
															<div>
																<div class="font-semibold text-gray-800 text-sm"><?php echo esc_html( $plugin['name'] ); ?></div>
																<div class="flex items-center gap-3 mt-1 text-xs text-gray-600">
																	<span>v<?php echo esc_html( $plugin['version'] ); ?></span>
																	<?php if ( ! empty( $plugin['detected'] ) ) : ?>
																		<span class="w-1 h-1 bg-gray-300 rounded-full"></span>
																		<span class="text-purple-700 font-semibold"><?php echo esc_html( $plugin['affiliate_count'] ); ?> affiliates/vendors</span>
																	<?php endif; ?>
																</div>
															</div>
														</div>
													</div>
													<div class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">
														ACTIVE
													</div>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<div class="text-center py-8">
										<div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
											<i class="fa-solid fa-plug-circle-xmark text-2xl text-gray-400 icon-tooltip" data-tooltip="No affiliate plugins detected"></i>
										</div>
										<p class="text-gray-600 mb-2">No All plugins detected</p>
										<p class="text-sm text-gray-500">Install All plugins to start syncing data</p>
									</div>
								<?php endif; ?>
							</div>
						</div>

						<div class="space-y-2 mt-auto">
							<div class="p-3 bg-blue-50 rounded-lg border border-blue-200 flex items-start gap-3">
								<input type="checkbox" id="mypowerly-consent-plugin-sync" class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded" />
								<div class="flex-1 text-sm text-gray-700">
									<p class="font-semibold text-gray-900">Consent required</p>
									<p class="text-gray-700">I understand that clicking <strong>Sync Plugins Info Now</strong> will send affiliate/vendor plugin data to the external Mypowerly service (<code>https://mypowerly.com</code>)No data is stored in WordPress..</p>
								</div>
							</div>

							<button type="button" class="mp-btn-primary w-full flex items-center justify-center gap-3 opacity-60 cursor-not-allowed" id="plugin-sync" disabled>
								<i class="fa-solid fa-arrows-rotate icon-tooltip" data-tooltip="Sync plugin information now"></i>
								Sync Plugins Info Now
							</button>

							<div class="hidden" id="plugin-sync-progress-section">
								<div class="mb-3">
									<div class="mp-progress-bar">
										<div id="plugin-sync-progress-fill" class="mp-progress-fill" style="width: 0%"></div>
									</div>
								</div>
								<div class="flex justify-between items-center text-sm">
									<span id="plugin-sync-progress-text" class="font-medium text-blue-600">0%</span>
									<span id="current-plugin-sync-step" class="text-gray-600">Initializing...</span>
								</div>
							</div>

							<div class="hidden" id="plugin-sync-results">
								<div class="p-4 bg-green-50 rounded-xl border border-green-200">
									<div class="flex items-center justify-between">
										<div class="flex items-center gap-3">
											<i class="fas fa-circle-check text-green-600"></i>
											<span id="plugin-sync-result-message" class="font-medium text-gray-800">Plugins synced successfully!</span>
										</div>
										<span class="text-sm text-gray-600">Time: <span id="plugin-sync-duration" class="font-medium">0s</span></span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Card 3: Affiliate Data Sync -->
					<div class="mp-card mp-metric-card mp-metric-green p-4 flex flex-col h-full">
						<div class="mp-card-label">Affiliates/Vendors Data</div>
						<div class="flex items-start justify-between gap-3 mb-3">
							<div class="flex items-start gap-3">
								<div class="w-12 h-12 rounded-xl mp-card-icon flex items-center justify-center">
									<i class="fas fa-users text-xl"></i>
								</div>
								<div>
									<h3 class="text-base font-bold text-gray-800 mb-1">Affiliates/Vendors Data Sync</h3>
									<p class="text-sm text-gray-600">Sync all affiliates/vendors, commissions, and payment data</p>
								</div>
							</div>

							<div class="mp-help-tooltip">
								<button type="button" class="mp-help-icon" aria-label="Affiliates/Vendors Data Sync help">
									<i class="fas fa-circle-question"></i>
								</button>
								<div class="mp-help-bubble" style="width: 480px;">
									<div class="mp-help-title">What this card does</div>
									<div class="mp-help-line"><strong>What you do:</strong> Select a Workspace, then click <strong>Sync All Affiliates/Vendors Data Now</strong>.</div>
									<div class="mp-help-line"><strong>In WordPress:</strong> Reads affiliate/vendor data from your affiliate plugins and sends it securely to Mypowerly.</div>
									<div class="mp-help-line"><strong>In Mypowerly (MyPowerly):</strong> Stores and processes vendors/affiliates and payout amounts for W-9/1099 workflows.</div>
									<div class="mp-help-line"><strong>What is stored in WordPress:</strong> Only <strong>last sync time</strong> and <strong>count</strong> (full affiliate records are not stored in WP DB).</div>
									<hr style="margin: 10px 0; border: none; border-top: 1px solid #e5e7eb;">
									<div class="mp-help-title">How to access your Vendor/Affiliate data in MyPowerly</div>
									<div class="mp-help-line"><strong>Step 1:</strong> Go to MyPowerly Settings.</div>
									<div class="mp-help-line"><strong>Step 2:</strong> Click on Workspace Management.</div>
									<div class="mp-help-line"><strong>Step 3:</strong> Then click on Manage App.</div>
									<div class="mp-help-line"><strong>Step 4:</strong> Click on Add App and enter the app name "Tax Form".</div>
									<div class="mp-help-line"><strong>Step 5:</strong> Open the Tax Form app.</div>
									<div class="mp-help-line"><strong>Step 6:</strong> Inside Tax Form, you will see W9 Chaser. Click on W9 Chaser.</div>
									<div class="mp-help-line"><strong>Step 7:</strong> You will see a list of Workspaces. Choose the workspace where you are sending the data.</div>
									<div class="mp-help-line"><strong>Step 8:</strong> Inside that workspace, you will find the Vendor Affiliates data.</div>
								</div>
							</div>
						</div>

						<div class="space-y-2 mb-3 py-3 border-y border-gray-100">
							<div class="flex justify-between items-center">
								<span class="text-gray-600">Last Sync:</span>
								<strong id="last-affiliates-sync-time" class="text-gray-800 font-medium"><?php echo esc_html( $affiliates_last_sync_formatted ); ?></strong>
							</div>
							<div class="flex justify-between items-center">
								<span class="text-gray-600">Total Affiliates/Vendors:</span>
								<strong id="affiliate-count" class="text-gray-800 font-medium"><?php echo esc_html( $total_affiliates ); ?></strong>
							</div>
							<div class="flex justify-between items-center hidden">
								<span class="text-gray-600">Status:</span>
								<strong id="affiliates-sync-status" class="text-green-600 font-semibold">Ready</strong>
							</div>
						</div>

						<!-- Plugin Selection -->
						<div class="mb-4">
							<div class="flex items-center justify-between mb-3">
								<label class="block text-sm font-medium text-gray-700">Select Plugin to View Affiliates/Vendors</label>
								<span class="text-xs text-gray-500">Filter by plugin</span>
							</div>
							<div class="relative mb-4">
								<select id="affiliate-plugin-select" class="mp-input mp-select">
									<option value="" selected>
										All Affiliate Detected plugins
									</option>
									<?php
									// Ensure SliceWP is in the detected plugins if it exists
									if ( class_exists( 'SliceWP' ) && ! isset( $detected_plugins['slicewp'] ) ) {
										$detected_plugins['slicewp'] = array(
											'name' => 'SliceWP',
											'affiliate_count' => 0, // Will be updated via AJAX
										);
									}

									foreach ( $detected_plugins as $slug => $plugin ) :
										$is_affiliate_vendor = ! empty( $plugin['detected'] );
										if ( ! $is_affiliate_vendor ) {
											continue;
										}
										?>
										<option value="<?php echo esc_attr( $slug ); ?>">
											<?php echo esc_html( $plugin['name'] ); ?> (<?php echo esc_html( $plugin['affiliate_count'] ); ?> affiliates/vendors)
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<!-- Affiliates Display -->
							<div class="bg-gray-50 rounded-xl p-4">
								<div class="flex justify-between items-center mb-4">
									<h5 class="font-bold text-gray-800">Affiliates/Vendors Preview</h5>
									<div id="affiliates-stats" class="text-sm text-gray-600">
										Total: <?php echo esc_html( (string) $total_affiliates ); ?> affiliates/vendors
									</div>
								</div>

								<!-- Add overflow-x-auto here for horizontal scrolling -->
								<div class="mp-scroll bg-white rounded-lg overflow-hidden border border-gray-200 max-h-64 overflow-y-auto overflow-x-auto">
									<table class="mp-table min-w-full">
										<thead>
											<tr>
												<th class="whitespace-nowrap">EXCLUDE</th>
												<th class="whitespace-nowrap">ID</th>
												<th class="whitespace-nowrap">Name</th>
												<th class="whitespace-nowrap">Email</th>
												<th class="whitespace-nowrap">Amount</th>
												<th class="whitespace-nowrap">Status</th>
											</tr>
										</thead>
										<tbody id="affiliates-table-body">
											<tr>
												<td colspan="6" class="py-8 text-center text-gray-500">
													<div class="flex flex-col items-center justify-center">
														<i class="fas fa-users text-3xl text-gray-300 mb-3"></i>
														<p>Select a plugin to view affiliates/vendors</p>
													</div>
												</td>
											</tr>
										</tbody>
									</table>
								</div>

								<div class="mt-4 text-center">
									<button type="button" id="load-more-affiliates" class="hidden mp-btn-secondary">
										<i class="fas fa-chevron-down mr-2"></i>
										Load More Affiliates/Vendors
									</button>
								</div>
							</div>
						</div>

						<div class="space-y-3 mt-auto">
							<div class="p-3 bg-blue-50 rounded-lg border border-blue-200 flex items-start gap-3">
								<input type="checkbox" id="mypowerly-consent-affiliates-sync" class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded" />
								<div class="flex-1 text-sm text-gray-700">
									<p class="font-semibold text-gray-900">Consent required</p>
									<p class="text-gray-700">I understand that clicking <strong>Sync All Affiliates/Vendors Data Now</strong> will send affiliate/vendor/payee data to the external Mypowerly service (<code>https://mypowerly.com</code>)No data is stored in WordPress..</p>
								</div>
							</div>

							<button type="button" class="mp-btn-primary w-full flex items-center justify-center gap-3 opacity-60 cursor-not-allowed" id="affiliates-sync" disabled>
								<i class="fas fa-rotate mp-animate-pulse"></i>
								Sync All Affiliates/Vendors Data Now
							</button>
							<div class="text-center">
								<p class="text-sm font-semibold text-green-600">
									<i class="fas fa-exclamation-circle mr-2"></i>
									Critical for W9/1099 processing
								</p>
							</div>

							<div class="hidden" id="affiliates-sync-progress-section">
								<div class="mb-3">
									<div class="mp-progress-bar">
										<div id="affiliates-sync-progress-fill" class="mp-progress-fill" style="width: 0%"></div>
									</div>
								</div>
								<div class="flex justify-between items-center text-sm">
									<span id="affiliates-sync-progress-text" class="font-medium text-green-600">0%</span>
									<span id="current-affiliates-sync-step" class="text-gray-600">Initializing...</span>
								</div>
							</div>

							<div class="hidden" id="affiliates-sync-results">
								<div class="p-4 bg-green-50 rounded-xl border border-green-200">
									<div class="flex items-center justify-between">
										<div class="flex items-center gap-3">
											<i class="fas fa-circle-check text-green-600"></i>
											<span class="font-medium text-gray-800">
												<span id="affiliates-synced">0</span>
												affiliates/vendors synced successfully!
											</span>
										</div>
										<span class="text-sm text-gray-600">Time: <span id="affiliates-sync-duration" class="font-medium">0s</span></span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- NEW CARD 4: Team/User Data Sync -->
					<div class="mp-card mp-metric-card mp-metric-orange p-4 flex flex-col h-full mp-team-card">
						<div class="mp-card-label">Team / Users</div>
						<div class="flex items-start justify-between gap-3 mb-3">
							<div class="flex items-start gap-3">
								<div class="w-12 h-12 rounded-xl mp-card-icon flex items-center justify-center">
									<i class="fas fa-user-group text-xl"></i>
								</div>
								<div>
									<h3 class="text-base font-bold text-gray-800 mb-1">Team/User Data Sync</h3>
									<p class="text-sm text-gray-600">Sync your WordPress team members and user accounts</p>
								</div>
							</div>

							<div class="mp-help-tooltip">
								<button type="button" class="mp-help-icon" aria-label="Team/Users Sync help">
									<i class="fas fa-circle-question"></i>
								</button>
								<div class="mp-help-bubble">
									<div class="mp-help-title">What this card does</div>
									<div class="mp-help-line"><strong>What you do:</strong> Select a Workspace, then click <strong>Sync Team/Users Data Now</strong>.</div>
									<div class="mp-help-line"><strong>In WordPress:</strong> Reads WordPress users (username, email, role).</div>
									<div class="mp-help-line"><strong>In Mypowerly (MyPowerly):</strong> Updates the team/members for the selected workspace so access and roles can be managed.</div>
									<div class="mp-help-line"><strong>What is stored in WordPress:</strong> Only <strong>last sync time</strong> (no separate copy of users is stored; WP already owns the user records).</div>
								</div>
							</div>
						</div>

						<div class="space-y-2 mb-3 py-3 border-y border-gray-100 mp-team-stats">
							<div class="flex justify-between items-center">
								<span class="text-gray-600">Last Sync:</span>
								<strong id="last-team-sync-time" class="text-gray-800 font-medium"><?php echo esc_html( $team_last_sync_formatted ); ?></strong>
							</div>
							<div class="flex justify-between items-center">
								<span class="text-gray-600">Total Users:</span>
								<strong id="team-user-count" class="text-gray-800 font-medium">
									<?php echo esc_html( (string) $team_users_total_filtered ); ?>
								</strong>
							</div>
							<div class="flex justify-between items-center hidden">
								<span class="text-gray-600">Status:</span>
								<strong id="team-sync-status" class="text-green-600 font-semibold">Ready</strong>
							</div>
						</div>

						<!-- Team Members Preview -->
						<div class="mb-4 hidden">
							<div class="flex justify-between items-center mb-3">
								<h4 class="font-bold text-gray-800">Team Members Preview</h4>
								<span class="text-xs text-gray-500">Administrators & Editors</span>
							</div>

							<div class="bg-gray-50 rounded-xl p-3 max-h-64 overflow-y-auto mp-team-preview">
								<?php
								// Get admin and editor users
								$team_args  = array(
									'role__in' => array( 'administrator', 'editor' ),
									'number'   => 5,
									'orderby'  => 'registered',
									'order'    => 'DESC',
								);
								$team_users = get_users( $team_args );

								if ( ! empty( $team_users ) ) :
									?>
									<div class="space-y-3">
										<?php
										foreach ( $team_users as $user ) :
											$initials = '';
											if ( ! empty( $user->first_name ) && ! empty( $user->last_name ) ) {
												$initials = strtoupper( substr( $user->first_name, 0, 1 ) . substr( $user->last_name, 0, 1 ) );
											} else {
												$initials = strtoupper( substr( $user->display_name, 0, 2 ) );
											}

											$role_names   = array(
												'administrator' => 'Administrator',
												'editor' => 'Editor',
												'author' => 'Author',
												'contributor' => 'Contributor',
												'subscriber' => 'Subscriber',
											);
											$role_display = isset( $role_names[ $user->roles[0] ] ) ? $role_names[ $user->roles[0] ] : $user->roles[0];
											?>
											<div class="team-member-card">
												<div class="team-member-avatar">
													<?php echo esc_html( $initials ); ?>
												</div>
												<div class="team-member-info">
													<div class="team-member-name">
														<?php echo esc_html( $user->display_name ); ?>
													</div>
													<div class="team-member-role" style="display:none;">
														<?php echo esc_html( $role_display ); ?>
													</div>
													<div class="team-member-email">
														<?php echo esc_html( $user->user_email ); ?>
													</div>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<div class="text-center py-8">
										<div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
											<i class="fas fa-users text-2xl text-gray-400"></i>
										</div>
										<p class="text-gray-600 mb-2">No team members found</p>
										<p class="text-sm text-gray-500">Add users to your WordPress site to sync team data</p>
									</div>
								<?php endif; ?>
							</div>
						</div>

						<div class="mb-4">
							<div id="all-users-display" class="bg-gray-50 rounded-xl p-4 mt-4">
								<div class="flex justify-between items-center mb-4">
									<h5 class="font-bold text-gray-800">All Users</h5>
									<div class="flex items-center gap-3">
										<div id="all-users-stats" class="text-sm text-gray-600">
											Total: <span id="all-users-total" class="font-medium"><?php echo esc_html( isset($users_count['total_users']) ? (string) $users_count['total_users'] : '0' ); ?></span>
										</div>
										<button type="button" id="view-all-users" class="mp-btn-secondary">
											<i class="fas fa-rotate-right"></i>
										</button>
									</div>
								</div>

								<div class="mp-scroll bg-white rounded-lg overflow-hidden border border-gray-200 max-h-64 overflow-y-auto overflow-x-auto">
									<table class="mp-table min-w-full">
										<thead>
											<tr>
												<th class="whitespace-nowrap">Username</th>
												<th class="whitespace-nowrap">Name</th>
												<th class="whitespace-nowrap">Email</th>
												<th class="whitespace-nowrap">Role</th>
											</tr>
										</thead>
										<tbody id="all-users-table-body">
											<tr>
												<td colspan="4" class="py-8 text-center text-gray-500">
													<div class="flex flex-col items-center justify-center">
														<i class="fas fa-eye text-3xl text-gray-300 mb-3"></i>
														<p>Loading users...</p>
													</div>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>

						<!-- User Role Selection -->
						<div class="mb-4" style="display:none;">
							<div class="p-3 bg-orange-50 rounded-xl border border-orange-100 mp-role-box">
								<div class="flex items-center gap-3 mb-3">
									<div class="w-9 h-9 rounded-lg bg-orange-100 flex items-center justify-center">
										<i class="fas fa-user-tag text-orange-600"></i>
									</div>
									<div>
										<h4 class="font-bold text-gray-800">User Roles to Sync</h4>
									</div>
								</div>

								<?php
								$wp_roles       = wp_roles()->roles;
								$selected_roles = array( 'administrator', 'editor' );
								$users_count    = count_users();
								$role_counts    = isset( $users_count['avail_roles'] ) && is_array( $users_count['avail_roles'] ) ? $users_count['avail_roles'] : array();
								?>

								<div class="overflow-x-auto">
									<table class="mp-roles-table">
										<thead>
											<tr>
												<th>Role</th>
												<th>Users</th>
												<th>Include</th>
											</tr>
										</thead>
										<tbody>
											<?php
											foreach ( $wp_roles as $role_key => $role_info ) :
												$is_selected = in_array( $role_key, $selected_roles, true );
												$count       = isset( $role_counts[ $role_key ] ) ? (int) $role_counts[ $role_key ] : 0;
												?>
												<tr>
													<td>
														<div class="font-semibold text-gray-800"><?php echo esc_html( $role_info['name'] ); ?></div>
														<div class="text-xs text-gray-500"><?php echo esc_html( $role_key ); ?></div>
													</td>
													<td class="text-gray-700"><?php echo esc_html( (string) $count ); ?></td>
													<td>
														<input type="checkbox"
																id="role-<?php echo esc_attr( $role_key ); ?>"
																name="sync_roles[]"
																value="<?php echo esc_attr( $role_key ); ?>"
																<?php checked( $is_selected ); ?>
																class="hidden">
														<button type="button" class="mp-roles-toggle <?php echo esc_attr( $is_selected ? 'is-on' : '' ); ?>" data-role="<?php echo esc_attr( $role_key ); ?>">
															<?php echo esc_html( $is_selected ? 'Included' : 'Excluded' ); ?>
														</button>
													</td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
								
								<div class="mt-2 text-xs text-gray-500 flex items-start gap-2">
									<i class="fas fa-info-circle text-orange-500 mt-0.5"></i>
									<span>Selected roles will be synced with Mypowerly for team management.</span>
								</div>
							</div>
						</div>

						<div class="space-y-3 mt-auto">
							<div class="p-3 bg-blue-50 rounded-lg border border-blue-200 flex items-start gap-3">
								<input type="checkbox" id="mypowerly-consent-team-sync" class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded" />
								<div class="flex-1 text-sm text-gray-700">
									<p class="font-semibold text-gray-900">Consent required</p>
									<p class="text-gray-700">I understand that clicking <strong>Sync Team/Users Data Now</strong> will send user/team data to the external Mypowerly service (<code>https://mypowerly.com</code>)No data is stored in WordPress..</p>
								</div>
							</div>

							<button type="button" class="mp-btn-primary w-full flex items-center justify-center gap-3 opacity-60 cursor-not-allowed" id="team-sync" disabled>
								<i class="fas fa-rotate"></i>
								Sync Team/Users Data Now
							</button>
							<div class="text-center">
								<p class="text-sm font-semibold text-green-600">
									<i class="fas fa-shield-alt mr-2"></i>
									Secure team collaboration
								</p>
							</div>

							<div class="hidden" id="team-sync-progress-section">
								<div class="mb-3">
									<div class="mp-progress-bar">
										<div id="team-sync-progress-fill" class="mp-progress-fill" style="width: 0%"></div>
									</div>
								</div>
								<div class="flex justify-between items-center text-sm">
									<span id="team-sync-progress-text" class="font-medium text-orange-600">0%</span>
									<span id="current-team-sync-step" class="text-gray-600">Initializing...</span>
								</div>
							</div>

							<div class="hidden" id="team-sync-results">
								<div class="p-4 bg-green-50 rounded-xl border border-green-200">
									<div class="flex items-center justify-between">
										<div class="flex items-center gap-3">
											<i class="fas fa-circle-check text-green-600"></i>
											<span class="font-medium text-gray-800">
												<span id="team-users-synced">0</span> team members synced successfully!
											</span>
										</div>
										<span class="text-sm text-gray-600">Time: <span id="team-sync-duration" class="font-medium">0s</span></span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Card 4: Form Plugin Detection -->
					<div class="mp-card mp-metric-card mp-metric-blue p-4 flex flex-col h-full">
						<div class="mp-card-label">Form Plugins</div>
						<div class="flex items-start justify-between gap-3 mb-3">
							<div class="flex items-start gap-3">
								<div class="w-12 h-12 rounded-xl mp-card-icon flex items-center justify-center">
									<i class="fas fa-file-lines text-xl"></i>
								</div>
								<div>
									<h3 class="text-base font-bold text-gray-800 mb-1">Form Plugin Detection</h3>
									<p class="text-sm text-gray-600">Detect and manage form plugins</p>
								</div>
							</div>

							<div class="mp-help-tooltip">
								<button type="button" class="mp-help-icon" aria-label="Form Plugin Detection help">
									<i class="fas fa-circle-question"></i>
								</button>
								<div class="mp-help-bubble">
									<div class="mp-help-title">What this card does</div>
									<div class="mp-help-line"><strong>Refresh:</strong> Re-scans installed form plugins.</div>
									<div class="mp-help-line"><strong>Select Plugin:</strong> View forms from specific plugin.</div>
									<div class="mp-help-line"><strong>Detected Plugins:</strong> Fluent Forms, WPForms, Gravity Forms.</div>
									<hr style="margin: 10px 0; border: none; border-top: 1px solid #e5e7eb;">
									<div class="mp-help-title">Data that will be sent</div>
									<div class="mp-help-line"><strong>Form Entries:</strong> Contact forms, survey responses, submission data</div>
									<div class="mp-help-line"><strong>User Data:</strong> Names, emails, phone numbers from forms</div>
									<div class="mp-help-line"><strong>Plugin Config:</strong> Form settings and field configurations</div>
								</div>
							</div>
						</div>

						<div class="space-y-2 mb-3 py-3 border-y border-gray-100">
							<div class="flex justify-between items-center">
								<span class="text-gray-600">Total Forms:</span>
								<strong id="total-forms-count" class="text-gray-800 font-medium">
									<?php
 // Ensure form plugin detector is loaded
 if (!class_exists('w91099ch_Form_Plugin_Detector')) {
		require_once w91099ch_PLUGIN_PATH . 'includes/form-plugin-detector-init.php';
 }

$form_detector         = new w91099ch_Form_Plugin_Detector();
									$detected_form_plugins = $form_detector->get_form_plugins_data();
									$total_forms           = array_sum( array_column( $detected_form_plugins, 'forms_count' ) );
									echo esc_html( $total_forms );
									?>
								</strong>
							</div>
						</div>

						<div class="mb-4" style="flex: 1;">
							<div class="flex items-center justify-between mb-3">
								<label class="block text-sm font-medium text-gray-700">Select Plugin to View Forms</label>
								<span class="text-xs text-gray-500">Filter by plugin</span>
							</div>
							<div class="relative mb-4">
								<select id="form-plugin-select" class="mp-input mp-select">
									<option value="" selected>All Form Plugins</option>
									<?php foreach ( $detected_form_plugins as $slug => $plugin ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>">
											<?php echo esc_html( $plugin['name'] ); ?> (<?php echo esc_html( $plugin['forms_count'] ); ?> forms)
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="bg-gray-50 rounded-xl p-4">
								<div class="flex justify-between items-center mb-4">
									<h5 class="font-bold text-gray-800">Forms Preview</h5>
									<div id="forms-stats" class="text-sm text-gray-600">
										Total: <?php echo esc_html( $total_forms ); ?> forms
									</div>
								</div>

								<div class="mp-scroll bg-white rounded-lg overflow-hidden border border-gray-200 max-h-64 overflow-y-auto overflow-x-auto">
									<table class="mp-table min-w-full">
										<thead>
											<tr>
												<th class="whitespace-nowrap" style="width: 100px; text-align: center;">Exclude</th>
												<th class="whitespace-nowrap">Form ID</th>
												<th class="whitespace-nowrap">Form Title</th>
												<th class="whitespace-nowrap" style="width: 110px; text-align: center;">Entries</th>
												<th class="whitespace-nowrap">Status</th>
											</tr>
										</thead>
										<tbody id="forms-table-body">
											<tr>
												<td colspan="5" class="py-8 text-center text-gray-500">
													<div class="flex flex-col items-center justify-center">
														<i class="fas fa-file-lines text-3xl text-gray-300 mb-3"></i>
														<p>Select a plugin to view records</p>
													</div>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>

						<div class="space-y-3 mt-auto">
							<div class="p-3 bg-blue-50 rounded-lg border border-blue-200 flex items-start gap-3">
								<input type="checkbox" id="form-plugins-consent" class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded" />
								<div class="flex-1 text-sm text-gray-700">
									<p class="font-semibold text-gray-900">Consent required</p>
									<p class="text-gray-700">I understand that clicking <strong>Sync Form Data</strong> will send form plugin data to the external Mypowerly service (<code>https://mypowerly.com</code>)No data is stored in WordPress..</p>
								</div>
							</div>

							<button type="button" id="sync-form-plugins-btn" class="mp-btn-primary w-full flex items-center justify-center gap-3 opacity-60 cursor-not-allowed" disabled>
								<i class="fas fa-rotate"></i>
								Sync Form Data
							</button>
							<div class="mt-2 text-xs text-gray-500 text-center">
								<span id="form-sync-status">Check consent to enable sync</span>
							</div>
						</div>
					</div>

					<!-- Card 5: Contractor / Service Plugins -->
					<div class="mp-card mp-metric-card mp-metric-purple p-4 flex flex-col h-full">
						<div class="mp-card-label">Membership & Subscription Plugins</div>
						<div class="flex items-start justify-between gap-3 mb-3">
							<div class="flex items-start gap-3">
								<div class="w-12 h-12 rounded-xl mp-card-icon flex items-center justify-center">
									<i class="fas fa-user-tie text-xl"></i>
								</div>
								<div>
									<h3 class="text-base font-bold text-gray-800 mb-1">Membership & Subscription Plugins</h3>
									<p class="text-sm text-gray-600">Detect and preview membership/subscription plugins</p>
								</div>
							</div>

							<div class="mp-help-tooltip">
								<button type="button" class="mp-help-icon" aria-label="Membership & Subscription Plugins help">
									<i class="fas fa-circle-question"></i>
								</button>
								<div class="mp-help-bubble">
									<div class="mp-help-title">What this card does</div>
									<div class="mp-help-line"><strong>Select Plugin:</strong> Preview contractor/member records.</div>
									<div class="mp-help-line"><strong>Data:</strong> Name, email, role/type, status, source plugin, created date.</div>
									<hr style="margin: 10px 0; border: none; border-top: 1px solid #e5e7eb;">
									<div class="mp-help-title">Data that will be sent</div>
									<div class="mp-help-line"><strong>Member Profiles:</strong> Names, emails, registration details</div>
									<div class="mp-help-line"><strong>Subscription Data:</strong> Membership levels, renewal dates, status</div>
									<div class="mp-help-line"><strong>Payment Info:</strong> Subscription amounts, billing cycles</div>
								</div>
							</div>
						</div>

						<?php
						$contractor_detector         = new w91099ch_Contractor_Plugin_Detector();
						$detected_contractor_plugins = $contractor_detector->get_contractor_plugins_data();

						if ( is_array( $detected_contractor_plugins ) && ! empty( $detected_contractor_plugins ) ) {
							foreach ( $detected_contractor_plugins as $slug => $plugin ) {
								$slug = is_string( $slug ) ? $slug : '';
								$name = ( is_array( $plugin ) && isset( $plugin['name'] ) && is_string( $plugin['name'] ) ) ? $plugin['name'] : '';
								if ( in_array( $slug, array( 'erp', 'wperp', 'wp-erp' ), true ) || preg_match( '/\berp\b/i', $name ) ) {
									unset( $detected_contractor_plugins[ $slug ] );
								}
							}
						}
						?>

						<div class="mb-4" style="flex: 1;">
							<div class="flex items-center justify-between mb-3">
								<label class="block text-sm font-medium text-gray-700">Select Plugin</label>
								<span class="text-xs text-gray-500">Filter by plugin</span>
							</div>
							<div class="relative mb-4">
								<select id="contractor-plugin-select" class="mp-input mp-select">
									<option value="" selected>All Membership & Subscription Plugins</option>
									<?php if ( ! empty( $detected_contractor_plugins ) ) : ?>
										<?php foreach ( $detected_contractor_plugins as $slug => $plugin ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>">
												<?php echo esc_html( $plugin['name'] ); ?>
											</option>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
							</div>

							<div class="bg-gray-50 rounded-xl p-4">
								<div class="flex justify-between items-center mb-4">
									<h5 class="font-bold text-gray-800">Membership / Subscription Preview</h5>
									<div id="contractor-stats" class="text-sm text-gray-600">
										<?php if ( ! empty( $detected_contractor_plugins ) ) : ?>
											Select a plugin to view records
										<?php else : ?>
											No membership/subscription plugins detected
										<?php endif; ?>
									</div>
								</div>

								<div class="mp-scroll bg-white rounded-lg overflow-hidden border border-gray-200 max-h-64 overflow-y-auto overflow-x-auto">
									<table class="mp-table min-w-full">
										<thead>
											<tr>
												<th class="whitespace-nowrap">Name</th>
												<th class="whitespace-nowrap">Email</th>
												<th class="whitespace-nowrap">Role / Type</th>
												<th class="whitespace-nowrap">Source Plugin</th>
												<th class="whitespace-nowrap">Created Date</th>
												<th class="whitespace-nowrap">Status</th>
											</tr>
										</thead>
										<tbody id="contractor-table-body">
											<tr>
												<td colspan="6" class="py-8 text-center text-gray-500">
													<div class="flex flex-col items-center justify-center">
														<i class="fas fa-user-tie text-3xl text-gray-300 mb-3"></i>
														<p>Select a plugin to view records</p>
													</div>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>

						<div class="space-y-3 mt-auto">
							<div class="p-3 bg-purple-50 rounded-lg border border-purple-200 flex items-start gap-3">
								<input type="checkbox" id="contractor-consent" class="mt-1 h-4 w-4 text-purple-600 border-gray-300 rounded" />
								<div class="flex-1 text-sm text-gray-700">
									<p class="font-semibold text-gray-900">Consent required</p>
									<p class="text-gray-700">I understand that clicking <strong>Sync Contractor Data</strong> will send membership/subscription plugin data to the external Mypowerly service (<code>https://mypowerly.com</code>)No data is stored in WordPress..</p>
								</div>
							</div>

							<button type="button" id="sync-contractor-btn" class="mp-btn-primary w-full flex items-center justify-center gap-3 opacity-60 cursor-not-allowed" disabled>
								<i class="fas fa-rotate"></i>
								Sync Contractor Data
							</button>
							<div class="mt-2 text-xs text-gray-500 text-center">
								<span id="contractor-sync-status">Check consent to enable sync</span>
							</div>
						</div>
					</div>

					<!-- Card 6: Freelancer / Contractor Management Plugins -->
					<div class="mp-card mp-metric-card mp-metric-blue p-4 flex flex-col h-full">
						<div class="mp-card-label">Freelancer / Contractor Management Plugins</div>
						<div class="flex items-start justify-between gap-3 mb-3">
							<div class="flex items-start gap-3">
								<div class="w-12 h-12 rounded-xl mp-card-icon flex items-center justify-center">
									<i class="fas fa-suitcase text-xl"></i>
								</div>
								<div>
									<h3 class="text-base font-bold text-gray-800 mb-1">Freelancer / Contractor Management Plugins</h3>
									<p class="text-sm text-gray-600">Detect and preview freelancer/contractor plugins</p>
								</div>
							</div>

							<div class="mp-help-tooltip">
								<button type="button" class="mp-help-icon" aria-label="Freelancer / Contractor Management Plugins help">
									<i class="fas fa-circle-question"></i>
								</button>
								<div class="mp-help-bubble">
									<div class="mp-help-title">What this card does</div>
									<div class="mp-help-line"><strong>Select Plugin:</strong> Preview contractor/service-provider records when available.</div>
									<div class="mp-help-line"><strong>Detection:</strong> Projectopia, HivePress, WP-Client, Zephyr PM, Simple Job Board + related plugins.</div>
									<hr style="margin: 10px 0; border: none; border-top: 1px solid #e5e7eb;">
									<div class="mp-help-title">Data that will be sent</div>
									<div class="mp-help-line"><strong>Contractor Profiles:</strong> Names, emails, skills, specialties</div>
									<div class="mp-help-line"><strong>Project Data:</strong> Active projects, completion status, timelines</div>
									<div class="mp-help-line"><strong>Service Info:</strong> Service types, rates, availability status</div>
								</div>
							</div>
						</div>

						<?php
						// Ensure freelancer contractor plugin detector is loaded
if (!class_exists('w91099ch_Freelancer_Contractor_Plugin_Detector')) {
		require_once w91099ch_PLUGIN_PATH . 'includes/freelancer-contractor-plugin-detector-init.php';
}

$fc_detector         = new w91099ch_Freelancer_Contractor_Plugin_Detector();
						$detected_fc_plugins = $fc_detector->get_freelancer_contractor_plugins_data();
						?>

						<div class="mb-4" style="flex: 1;">
							<div class="flex items-center justify-between mb-3">
								<label class="block text-sm font-medium text-gray-700">Select Plugin</label>
								<span class="text-xs text-gray-500">Filter by plugin</span>
							</div>
							<div class="relative mb-4">
								<select id="freelancer-contractor-plugin-select" class="mp-input mp-select">
									<option value="" selected>All Freelancer / Contractor Plugins</option>
									<?php if ( ! empty( $detected_fc_plugins ) ) : ?>
										<?php foreach ( $detected_fc_plugins as $slug => $plugin ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>">
												<?php echo esc_html( $plugin['name'] ); ?>
											</option>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
							</div>

							<div class="bg-gray-50 rounded-xl p-4">
								<div class="flex justify-between items-center mb-4">
									<h5 class="font-bold text-gray-800">Freelancer / Contractor Preview</h5>
									<div id="freelancer-contractor-stats" class="text-sm text-gray-600">
										<?php if ( ! empty( $detected_fc_plugins ) ) : ?>
											<?php echo esc_html( 'Select a plugin to view records' ); ?>
										<?php else : ?>
											<?php echo esc_html( 'No contractor plugins detected' ); ?>
										<?php endif; ?>
									</div>
								</div>

								<div class="mp-scroll bg-white rounded-lg overflow-hidden border border-gray-200 max-h-64 overflow-y-auto overflow-x-auto">
									<table class="mp-table min-w-full">
										<thead>
											<tr>
												<th class="whitespace-nowrap">Name</th>
												<th class="whitespace-nowrap">Email</th>
												<th class="whitespace-nowrap">Role / Type</th>
												<th class="whitespace-nowrap">Source Plugin</th>
												<th class="whitespace-nowrap">Created Date</th>
												<th class="whitespace-nowrap">Status</th>
											</tr>
										</thead>
										<tbody id="freelancer-contractor-table-body">
											<tr>
												<td colspan="6" class="py-8 text-center text-gray-500">
													<div class="flex flex-col items-center justify-center">
														<i class="fas fa-suitcase text-3xl text-gray-300 mb-3"></i>
														<p>Select a plugin to view records</p>
													</div>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>

						<div class="space-y-3 mt-auto">
							<div class="p-3 bg-blue-50 rounded-lg border border-blue-200 flex items-start gap-3">
								<input type="checkbox" id="freelancer-contractor-consent" class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded" />
								<div class="flex-1 text-sm text-gray-700">
									<p class="font-semibold text-gray-900">Consent required</p>
									<p class="text-gray-700">I understand that clicking <strong>Sync Freelancer Data</strong> will send freelancer/contractor plugin data to the external Mypowerly service (<code>https://mypowerly.com</code>)No data is stored in WordPress..</p>
								</div>
							</div>

							<button type="button" id="sync-freelancer-contractor-btn" class="mp-btn-primary w-full flex items-center justify-center gap-3 opacity-60 cursor-not-allowed" disabled>
								<i class="fas fa-rotate"></i>
								Sync Freelancer Data
							</button>
							<div class="mt-2 text-xs text-gray-500 text-center">
								<span id="freelancer-contractor-sync-status">Check consent to enable sync</span>
							</div>
						</div>
					</div>

					<div class="mp-card mp-metric-card mp-metric-green p-4 flex flex-col h-full">
						<div class="mp-card-label">Accounting &amp; Bookkeeping Plugins</div>
						<div class="flex items-start justify-between gap-3 mb-3">
							<div class="flex items-start gap-3">
								<div class="w-12 h-12 rounded-xl mp-card-icon flex items-center justify-center">
									<i class="fas fa-calculator text-xl"></i>
								</div>
								<div>
									<h3 class="text-base font-bold text-gray-800 mb-1">Accounting &amp; Bookkeeping Plugins</h3>
									<p class="text-sm text-gray-600">Detect and preview accounting/invoicing plugins</p>
								</div>
							</div>

							<div class="mp-help-tooltip">
								<button type="button" class="mp-help-icon" aria-label="Accounting &amp; Bookkeeping Plugins help">
									<i class="fas fa-circle-question"></i>
								</button>
								<div class="mp-help-bubble">
									<div class="mp-help-title">What this card does</div>
									<div class="mp-help-line"><strong>Select Plugin:</strong> View detected accounting/bookkeeping plugins.</div>
									<div class="mp-help-line"><strong>Detection:</strong> WP ERP, QuickBooks integrations, Sliced Invoices, Sprout Invoices, WooCommerce PDF Invoices &amp; Packing Slips + related plugins.</div>
									<hr style="margin: 10px 0; border: none; border-top: 1px solid #e5e7eb;">
									<div class="mp-help-title">Data that will be sent</div>
									<div class="mp-help-line"><strong>Financial Records:</strong> Transaction history, account balances</div>
									<div class="mp-help-line"><strong>Invoices:</strong> Invoice numbers, amounts, due dates, status</div>
									<div class="mp-help-line"><strong>Accounting Data:</strong> Expense records, tax information, reports</div>
								</div>
							</div>
						</div>
						<?php
						// Ensure accounting plugin detector is loaded
if (!class_exists('w91099ch_Accounting_Bookkeeping_Plugin_Detector')) {
		require_once w91099ch_PLUGIN_PATH . 'includes/accounting-bookkeeping-plugin-detector-init.php';
}

$ab_detector         = new w91099ch_Accounting_Bookkeeping_Plugin_Detector();
						$detected_ab_plugins = $ab_detector->get_accounting_bookkeeping_plugins_data();
						?>

						<div class="mb-4" style="flex: 1;">
							<div class="flex items-center justify-between mb-3">
								<label class="block text-sm font-medium text-gray-700">Select Plugin</label>
								<span class="text-xs text-gray-500">Filter by plugin</span>
							</div>
							<div class="relative mb-4">
								<select id="accounting-bookkeeping-plugin-select" class="mp-input mp-select">
									<option value="" selected>All Accounting / Bookkeeping Plugins</option>
									<?php if ( ! empty( $detected_ab_plugins ) ) : ?>
										<?php foreach ( $detected_ab_plugins as $slug => $plugin ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>">
												<?php echo esc_html( $plugin['name'] ); ?>
											</option>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
							</div>

							<div class="bg-gray-50 rounded-xl p-4">
								<div class="flex justify-between items-center mb-4">
									<h5 class="font-bold text-gray-800">Accounting / Bookkeeping Preview</h5>
									<div id="accounting-bookkeeping-stats" class="text-sm text-gray-600">
										<?php if ( ! empty( $detected_ab_plugins ) ) : ?>
											<?php 
											$total_plugins = count( $detected_ab_plugins );
											echo esc_html( "Detected {$total_plugins} accounting plugins" ); 
											?>
										<?php else : ?>
											<?php echo esc_html( 'No accounting/bookkeeping plugins detected' ); ?>
										<?php endif; ?>
									</div>
								</div>

								<div class="mp-scroll bg-white rounded-lg overflow-hidden border border-gray-200 max-h-64 overflow-y-auto overflow-x-auto">
									<table class="mp-table min-w-full">
										<thead>
											<tr>
												<th class="whitespace-nowrap">Name</th>
												<th class="whitespace-nowrap">Email</th>
												<th class="whitespace-nowrap">Amount</th>
												<th class="whitespace-nowrap">Type</th>
												<th class="whitespace-nowrap">Status</th>
												<th class="whitespace-nowrap">Source Plugin</th>
												<th class="whitespace-nowrap">Created</th>
											</tr>
										</thead>
										<tbody id="accounting-bookkeeping-table-body">
											<tr>
												<td colspan="7" class="py-8 text-center text-gray-500">
													<div class="flex flex-col items-center justify-center">
														<i class="fas fa-calculator text-3xl text-gray-300 mb-3"></i>
														<p>Loading accounting data...</p>
													</div>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>

						<div class="space-y-3 mt-auto">
							<div class="p-3 bg-green-50 rounded-lg border border-green-200 flex items-start gap-3">
								<input type="checkbox" id="accounting-bookkeeping-consent" class="mt-1 h-4 w-4 text-green-600 border-gray-300 rounded" />
								<div class="flex-1 text-sm text-gray-700">
									<p class="font-semibold text-gray-900">Consent required</p>
									<p class="text-gray-700">I understand that clicking <strong>Sync Accounting Data</strong> will send accounting/bookkeeping plugin data to the external Mypowerly service (<code>https://mypowerly.com</code>)No data is stored in WordPress..</p>
								</div>
							</div>

							<button type="button" id="sync-accounting-bookkeeping-btn" class="mp-btn-primary w-full flex items-center justify-center gap-3 opacity-60 cursor-not-allowed" disabled>
								<i class="fas fa-rotate"></i>
								Sync Accounting Data
							</button>
							<div class="mt-2 text-xs text-gray-500 text-center">
								<span id="accounting-bookkeeping-sync-status">Check consent to enable sync</span>
							</div>
						</div>
					</div>

					<!-- Card 9: Ecommerce Data -->
					<div class="mp-card mp-metric-card mp-metric-blue p-4 flex flex-col h-full">
						<div class="mp-card-label">Ecommerce Data</div>
						<div class="flex items-start justify-between gap-3 mb-3">
							<div class="flex items-start gap-3">
								<div class="w-12 h-12 rounded-xl mp-card-icon flex items-center justify-center">
									<i class="fas fa-shopping-cart text-xl"></i>
								</div>
								<div>
									<h3 class="text-base font-bold text-gray-800 mb-1">Ecommerce Data</h3>
									<p class="text-sm text-gray-600">Detect and preview ecommerce plugins</p>
								</div>
							</div>

							<div class="mp-help-tooltip">
								<button type="button" class="mp-help-icon" aria-label="Ecommerce Data help">
									<i class="fas fa-circle-question"></i>
								</button>
								<div class="mp-help-bubble">
									<div class="mp-help-title">What this card does</div>
									<div class="mp-help-line"><strong>Select Plugin:</strong> View detected ecommerce plugins.</div>
									<div class="mp-help-line"><strong>Detection:</strong> WooCommerce, Dokan, WCFM, Stripe, PayPal + related plugins.</div>
									<hr style="margin: 10px 0; border: none; border-top: 1px solid #e5e7eb;">
									<div class="mp-help-title">Data that will be sent</div>
									<div class="mp-help-line"><strong>Store Data:</strong> Store name, currency, settings</div>
									<div class="mp-help-line"><strong>Product Info:</strong> Product catalog, pricing, inventory</div>
									<div class="mp-help-line"><strong>Order Data:</strong> Order history, customer information, payment methods</div>
								</div>
							</div>
						</div>

						<?php
						// Ensure ecommerce plugin detector is loaded
						if (!class_exists('w91099ch_Ecommerce_Plugin_Detector')) {
							require_once w91099ch_PLUGIN_PATH . 'includes/ecommerce-plugin-detector-init.php';
						}

						$ecommerce_detector = new w91099ch_Ecommerce_Plugin_Detector();
						$detected_ecommerce_plugins = $ecommerce_detector->get_ecommerce_plugins_data();
						?>

						<div class="mb-4" style="flex: 1;">
							<div class="flex items-center justify-between mb-3">
								<label class="block text-sm font-medium text-gray-700">Select Plugin</label>
								<span class="text-xs text-gray-500">Filter by plugin</span>
							</div>
							<div class="relative mb-4">
								<select id="ecommerce-plugin-select" class="mp-input mp-select">
									<option value="" selected>All Ecommerce Plugins</option>
									<?php if ( ! empty( $detected_ecommerce_plugins ) ) : ?>
										<?php foreach ( $detected_ecommerce_plugins as $slug => $plugin ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>">
												<?php echo esc_html( $plugin['name'] ); ?>
											</option>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
							</div>

							<div class="bg-gray-50 rounded-xl p-4">
								<div class="flex justify-between items-center mb-4">
									<h5 class="font-bold text-gray-800">Ecommerce Preview</h5>
									<div id="ecommerce-stats" class="text-sm text-gray-600">
										<?php if ( ! empty( $detected_ecommerce_plugins ) ) : ?>
											<?php 
											$total_plugins = count( $detected_ecommerce_plugins );
											echo esc_html( "Detected {$total_plugins} ecommerce plugins" ); 
											?>
										<?php else : ?>
											<?php echo esc_html( 'No ecommerce plugins detected' ); ?>
										<?php endif; ?>
									</div>
								</div>

								<div class="mp-scroll bg-white rounded-lg overflow-hidden border border-gray-200 max-h-64 overflow-y-auto overflow-x-auto">
									<table class="mp-table min-w-full">
										<thead>
											<tr>
												<th class="whitespace-nowrap">Plugin Name</th>
												<th class="whitespace-nowrap">Version</th>
												<th class="whitespace-nowrap">Type</th>
												<th class="whitespace-nowrap">Status</th>
											</tr>
										</thead>
										<tbody id="ecommerce-table-body">
											<tr>
												<td colspan="4" class="py-8 text-center text-gray-500">
													<div class="flex flex-col items-center justify-center">
														<i class="fas fa-shopping-cart text-3xl text-gray-300 mb-3"></i>
														<p>Loading ecommerce data...</p>
													</div>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>

						<div class="space-y-3 mt-auto">
							<div class="p-3 bg-blue-50 rounded-lg border border-blue-200 flex items-start gap-3">
								<input type="checkbox" id="ecommerce-consent" class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded" />
								<div class="flex-1 text-sm text-gray-700">
									<p class="font-semibold text-gray-900">Consent required</p>
									<p class="text-gray-700">I understand that clicking <strong>Sync Ecommerce Data</strong> will send ecommerce plugin data to the external Mypowerly service (<code>https://mypowerly.com</code>). No data is stored in WordPress.</p>
								</div>
							</div>

							<button type="button" id="sync-ecommerce-btn" class="mp-btn-primary w-full flex items-center justify-center gap-3 opacity-60 cursor-not-allowed" disabled>
								<i class="fas fa-rotate"></i>
								Sync Ecommerce Data
							</button>
							<div class="mt-2 text-xs text-gray-500 text-center">
								<span id="ecommerce-sync-status">Check consent to enable sync</span>
							</div>
						</div>
					</div>

					<div class="mp-card mp-metric-card mp-metric-orange p-4 flex flex-col h-full">
						<div class="mp-card-label">Payout / Wallet Plugins</div>
						<div class="flex items-start justify-between gap-3 mb-3">
							<div class="flex items-start gap-3">
								<div class="w-12 h-12 rounded-xl mp-card-icon flex items-center justify-center">
									<i class="fas fa-wallet text-xl"></i>
								</div>
								<div>
									<h3 class="text-base font-bold text-gray-800 mb-1">Payout / Wallet Plugins</h3>
									<p class="text-sm text-gray-600">Detect and preview wallet/store-credit plugins</p>
								</div>
							</div>

							<div class="mp-help-tooltip">
								<button type="button" class="mp-help-icon" aria-label="Payout / Wallet Plugins help">
									<i class="fas fa-circle-question"></i>
								</button>
								<div class="mp-help-bubble">
									<div class="mp-help-title">What this card does</div>
									<div class="mp-help-line"><strong>Select Plugin:</strong> View detected wallet/payout plugins.</div>
									<div class="mp-help-line"><strong>Detection:</strong> TeraWallet, myCred, BP Wallet, WooCommerce Wallet, Store Credit plugins + related plugins.</div>
									<hr style="margin: 10px 0; border: none; border-top: 1px solid #e5e7eb;">
									<div class="mp-help-title">Data that will be sent</div>
									<div class="mp-help-line"><strong>Wallet Balances:</strong> Current balances, currency information</div>
									<div class="mp-help-line"><strong>Transaction History:</strong> Payments, transfers, refunds, timestamps</div>
									<div class="mp-help-line"><strong>Payment Config:</strong> Gateway settings, payout methods, limits</div>
								</div>
							</div>
						</div>

						<?php
						// Ensure wallet payout plugin detector is loaded
if (!class_exists('w91099ch_Wallet_Payout_Plugin_Detector')) {
		require_once w91099ch_PLUGIN_PATH . 'includes/wallet-payout-plugin-detector-init.php';
}

$wp_detector         = new w91099ch_Wallet_Payout_Plugin_Detector();
						$detected_wp_plugins = $wp_detector->get_wallet_payout_plugins_data();
						?>

						<div class="mb-4" style="flex: 1;">
							<div class="flex items-center justify-between mb-3">
								<label class="block text-sm font-medium text-gray-700">Select Plugin</label>
								<span class="text-xs text-gray-500">Filter by plugin</span>
							</div>
							<div class="relative mb-4">
								<select id="wallet-payout-plugin-select" class="mp-input mp-select">
									<option value="" selected>All Payout / Wallet Plugins</option>
									<?php if ( ! empty( $detected_wp_plugins ) ) : ?>
										<?php foreach ( $detected_wp_plugins as $slug => $plugin ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>">
												<?php echo esc_html( $plugin['name'] ); ?>
											</option>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
							</div>

							<div class="bg-gray-50 rounded-xl p-4">
								<div class="flex justify-between items-center mb-4">
									<h5 class="font-bold text-gray-800">Payout / Wallet Preview</h5>
									<div id="wallet-payout-stats" class="text-sm text-gray-600">
										<?php if ( ! empty( $detected_wp_plugins ) ) : ?>
											<?php echo esc_html( 'Select a plugin to view details' ); ?>
										<?php else : ?>
											<?php echo esc_html( 'No wallet/payout plugins detected' ); ?>
										<?php endif; ?>
									</div>
								</div>

								<div class="mp-scroll bg-white rounded-lg overflow-hidden border border-gray-200 max-h-64 overflow-y-auto overflow-x-auto">
									<table class="mp-table min-w-full">
										<thead>
											<tr>
												<th class="whitespace-nowrap">User</th>
												<th class="whitespace-nowrap">Email</th>
												<th class="whitespace-nowrap">Balance</th>
												<th class="whitespace-nowrap">Source Plugin</th>
												<th class="whitespace-nowrap">Status</th>
											</tr>
										</thead>
										<tbody id="wallet-payout-table-body">
											<tr>
												<td colspan="5" class="py-8 text-center text-gray-500">
													<div class="flex flex-col items-center justify-center">
														<i class="fas fa-wallet text-3xl text-gray-300 mb-3"></i>
														<p>Select a plugin to view records</p>
													</div>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>

						<div class="space-y-3 mt-auto">
							<div class="p-3 bg-orange-50 rounded-lg border border-orange-200 flex items-start gap-3">
								<input type="checkbox" id="wallet-payout-consent" class="mt-1 h-4 w-4 text-orange-600 border-gray-300 rounded" />
								<div class="flex-1 text-sm text-gray-700">
									<p class="font-semibold text-gray-900">Consent required</p>
									<p class="text-gray-700">I understand that clicking <strong>Sync Payout Data</strong> will send wallet/payout plugin data to the external Mypowerly service (<code>https://mypowerly.com</code>)No data is stored in WordPress..</p>
								</div>
							</div>

							<button type="button" id="sync-wallet-payout-btn" class="mp-btn-primary w-full flex items-center justify-center gap-3 opacity-60 cursor-not-allowed" disabled>
								<i class="fas fa-rotate"></i>
								Sync Payout Data
							</button>
							<div class="mt-2 text-xs text-gray-500 text-center">
								<span id="wallet-payout-sync-status">Check consent to enable sync</span>
							</div>
						</div>
					</div>
				</div>
			</div>

		<?php else : ?>
			<!-- Not Connected State -->

			<?php if ( ! empty( $enc_param ) ) : ?>
				<!-- Connection Progress -->
				<div class="mp-card p-8">
					<div class="p-8 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl text-white">
						<div class="flex items-center gap-4 mb-8">
							<div class="w-16 h-16 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
								<div class="mp-spinner"></div>
							</div>
							<div>
								<h3 class="text-2xl font-bold mb-2">Processing Connection</h3>
								<p class="text-blue-100 opacity-90">Establishing secure connection with Mypowerly</p>
							</div>
						</div>
						
						<div class="mb-8 bg-white/10 rounded-xl p-6 backdrop-blur-sm">
							<div class="space-y-4">
								<div class="flex items-center gap-4 p-3 bg-white/5 rounded-lg">
									<div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center">
										<i class="fas fa-envelope"></i>
									</div>
									<div class="flex-1">
										<div class="font-medium">Received credentials</div>
										<div class="text-sm opacity-80">From MyPowerly platform</div>
																	<div class="text-sm opacity-80">Secure local storage</div>
								</div>
								<span class="text-sm opacity-90"><?php echo esc_html( gmdate( 'H:i:s' ) ); ?></span>
							</div>
							
							<div class="flex items-center gap-4 p-3 bg-white/5 rounded-lg">
								<div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center">
									<i class="fas fa-unlock-keyhole"></i>
								</div>
								<div class="flex-1">
									<div class="font-medium">Decrypting credentials</div>
									<div class="text-sm opacity-80">Secure decryption process</div>
								</div>
								<span class="text-sm opacity-90"><?php echo esc_html( gmdate( 'H:i:s' ) ); ?></span>
							</div>
							
							<div class="flex items-center gap-4 p-3 bg-white/5 rounded-lg">
								<div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center">
									<i class="fas fa-save"></i>
								</div>
								<div class="flex-1">
									<div class="font-medium">Storing connection data</div>
									<div class="text-sm opacity-80">Secure local storage</div>
								</div>
								<span class="text-sm opacity-90"><?php echo esc_html( gmdate( 'H:i:s' ) ); ?></span>
							</div>
							
							<?php if ( $connection_error ) : ?>
								<div class="flex items-center gap-4 p-3 bg-red-500/20 rounded-lg border border-red-400/30">
									<div class="w-10 h-10 rounded-lg bg-red-500/30 flex items-center justify-center">
										<i class="fas fa-times"></i>
									</div>
									<div class="flex-1">
										<div class="font-medium">Connection Failed</div>
										<div class="text-sm"><?php echo esc_html( $connection_error ); ?></div>
									</div>
									<span class="text-sm"><?php echo esc_html( gmdate( 'H:i:s' ) ); ?></span>
								</div>
							<?php endif; ?>
						</div>
						
						<?php if ( $connection_error ) : ?>
							<div class="text-center p-6 bg-white/10 rounded-xl backdrop-blur-sm">
								<div class="w-16 h-16 rounded-full bg-red-500/20 flex items-center justify-center mx-auto mb-4">
										<i class="fas fa-triangle-exclamation text-2xl text-red-300"></i>
								</div>
								<h4 class="text-xl font-bold mb-3 text-red-100">Connection Failed</h4>
								<p class="mb-6 opacity-90">The connection could not be established. Please try again.</p>
								<div class="flex flex-wrap justify-center gap-4">
									<button type="button" onclick="window.location.href='<?php echo esc_js( esc_url( admin_url( 'options-general.php?page=w91099ch' ) ) ); ?>';" class="mp-btn-primary">
										<i class="fas fa-rotate-right mr-2"></i>Try Again
									</button>
									<button type="button" onclick="window.location.reload();" class="mp-btn-secondary">
										<i class="fas fa-sync mr-2"></i>Reload Page
									</button>
								</div>
							</div>
						<?php else : ?>
							<div class="text-center">
								<div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 rounded-full mb-4 backdrop-blur-sm">
									<div class="w-10 h-10 border-3 border-white border-t-transparent rounded-full animate-spin"></div>
								</div>
								<p class="opacity-90">Processing credentials securely... This may take a few moments.</p>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
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
		</div>

		<?php if ( ! $is_connected ) : ?>
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
});
</script>

<!-- Enhanced Footer -->
<footer class="mp-footer" style="display:none;">
	<div class="mp-footer-container">
		<div class="mp-footer-content">
			<!-- Logo Section -->
			<div class="mp-footer-brand">
				<div class="mp-footer-logo">
					<img src="<?php echo esc_url( w91099ch_PLUGIN_URL . 'assets/logo/logo-removebg-preview.png' ); ?>" alt="Vendor Onboarding W9-1099 Chaser by Mypowerly" />
				</div>
				<div class="mp-footer-brand-text">
					<h3>Vendor Onboarding W9-1099 Chaser by Mypowerly</h3>
					<p>Professional Tax Form Management</p>
				</div>
			</div>

			<!-- Links Section -->
			<div class="mp-footer-links">
				<div class="mp-footer-column">
					<h4>Platform</h4>
					<ul>
						<li><a href="https://1099automation.com" target="_blank" rel="noopener noreferrer">1099automation.com</a></li>
						<li><a href="https://mypowerly.com" target="_blank" rel="noopener noreferrer">MyPowerly.com</a></li>
					</ul>
				</div>
				<div class="mp-footer-column">
					<h4>Resources</h4>
					<ul>
						<li><a href="https://mypowerly.com/v1/support" target="_blank" rel="noopener noreferrer">Support</a></li>
						<li><a href="https://mypowerly.com/v1/docs" target="_blank" rel="noopener noreferrer">Documentation</a></li>
					</ul>
				</div>
				<div class="mp-footer-column">
					<h4>Legal</h4>
					<ul>
						<li><a href="https://1099automation.com/w9-1099-chaser/privacy-policy/" target="_blank" rel="noopener noreferrer">Plugin Privacy Policy</a></li>
						<li><a href="https://1099automation.com/w9-1099-chaser/terms/" target="_blank" rel="noopener noreferrer">Plugin Terms of Service</a></li>
						<li><a href="https://mypowerly.com/privacy" target="_blank" rel="noopener noreferrer">MyPowerly Privacy Policy</a></li>
						<li><a href="https://mypowerly.com/terms" target="_blank" rel="noopener noreferrer">MyPowerly Terms of Service</a></li>
					</ul>
				</div>
			</div>
		</div>

		<!-- Footer Bottom -->
		<div class="mp-footer-bottom">
			<div class="mp-footer-credits">
				<div class="mp-footer-credit-item">
					<i class="fas fa-globe"></i>
					<span>Powered by <a href="https://1099automation.com" target="_blank" rel="noopener noreferrer">1099automation.com</a></span>
				</div>
				<div class="mp-footer-credit-item">
					<i class="fas fa-code"></i>
					<span>Developed by <a href="https://mypowerly.com" target="_blank" rel="noopener noreferrer">MyPowerly.com</a></span>
				</div>
				<div class="mp-footer-credit-item">
					<i class="fas fa-shield-alt"></i>
					<span>Secure &amp; Compliant</span>
				</div>
			</div>
			<div class="mp-footer-copyright">
				<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Vendor Onboarding W9-1099 Chaser by Mypowerly. All rights reserved.</p>
			</div>
		</div>
	</div>
</footer>

<?php if ( true ) : ?>
<script>
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
		$toast.fadeIn(200);
		setTimeout(() => $toast.fadeOut(200), 3000);
	}

	(function initMypowerlyConsentGate() {
		const isConnected = !!(window.w91099chConnector && window.w91099chConnector.is_connected);
		if (isConnected) {
			return;
		}

		const $consent = $('#mypowerly-consent');
		const $cta = $('#connect-mypowerly-cta');
		if (!$consent.length || !$cta.length) {
			return;
		}

		const setEnabled = function() {
			$cta.prop('disabled', !$consent.prop('checked'));
		};
		setEnabled();

		$consent.off('change.mypowerly').on('change.mypowerly', setEnabled);
		$cta.off('click.mypowerly').on('click.mypowerly', function() {
			showMypowerlyToastAboveButton('Connecting to Mypowerly...', $cta);
			const $existingConnectBtn = $('#connect-mypowerly-admin');
			if ($existingConnectBtn.length) {
				$existingConnectBtn.trigger('click');
			}
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
				// Always enable locally when checked to avoid stale disabled block.
				apply();
				if ($(this).is(':checked') && typeof window.persistAdminConsentIfNeeded === 'function') {
					window.persistAdminConsentIfNeeded(function() {
						// Re-apply once persistence is returned.
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
	setupConsentGate('#form-plugins-consent', '#sync-form-plugins-btn');
	setupConsentGate('#wallet-payout-consent', '#sync-wallet-payout-btn');
	setupConsentGate('#freelancer-contractor-consent', '#sync-freelancer-contractor-btn');
	setupConsentGate('#accounting-bookkeeping-consent', '#sync-accounting-bookkeeping-btn');
	setupConsentGate('#contractor-consent', '#sync-contractor-btn');
	setupConsentGate('#ecommerce-consent', '#sync-ecommerce-btn', '#ecommerce-sync-status');

	$('#plugin-sync').off('click.pluginSync').on('click.pluginSync', function() {
		if (!$('#mypowerly-consent-plugin-sync').is(':checked')) {
			window.alert('Please check the consent checkbox to enable sending plugin data to the external service.');
			return;
		}
		if (!confirmSendToMypowerly('plugin data')) {
			return;
		}

		const $button = $(this);
		const $status = $('#plugin-sync-status');
		$button.prop('disabled', true);
		$status.text('Syncing...');

		$.ajax({
			url: window.w91099chConnector.ajaxurl,
			type: 'POST',
			data: {
				action: 'w91099ch_sync_plugin_data',
				nonce: window.w91099chConnector.nonce
			},
			success: function(response) {
				if (response && response.success) {
					const now = new Date();
					$('#last-plugin-sync-time').text(now.toLocaleString());
					$status.text('Ready');
					if (typeof loadDetectedPlugins === 'function') {
						loadDetectedPlugins();
					}
					window.alert('✅ Plugins synced successfully!');
				} else {
					const msg = safeExtractErrorMessage(response, 'Plugin sync failed');
					$status.text('Error');
					window.alert('❌ ' + msg);
				}
			},
			error: function(xhr, status, error) {
				const msg = safeExtractErrorMessage(xhr.responseText || error, 'Plugin sync error');
				$status.text('Error');
				window.alert('❌ ' + msg);
			},
			complete: function() {
				$button.prop('disabled', false);
			}
		});
	});

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
			$icon.removeClass('fa-rotate-right').addClass('fa-spinner fa-spin');
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
					$icon.removeClass('fa-spinner fa-spin').addClass('fa-rotate-right');
				}
			}
		});
	});

	$('#affiliates-sync').off('click.affiliatesSync').on('click.affiliatesSync', function() {
		if (!$('#mypowerly-consent-affiliates-sync').is(':checked')) {
			window.alert('Please check the consent checkbox to enable sending affiliate/vendor data to the external service.');
			return;
		}
		if (!confirmSendToMypowerly('affiliate/vendor data')) {
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
			: 'All Affiliate Detected Plugins';

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
					$currentStep.text('✅ Affiliates/Vendors synced successfully!');

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
\t// Sync All handled in assets/js/w9-1099-chaser-admin-page-inline.js
\t// Team/User Invite Members (Card 4)
	$('#team-sync').off('click.teamInvite').on('click.teamInvite', function() {
		if (!$('#mypowerly-consent-team-sync').is(':checked')) {
			window.alert('Please check the consent checkbox to enable sending team/user data to the external service.');
			return;
		}
		if (!confirmSendToMypowerly('team/user data')) {
			return;
		}

		// Require consent
		if (!window.confirm('Confirm: invite the currently displayed users to the workspace team?')) {
			return;
		}

		syncTeamData();
	});

	function syncTeamData() {
		window.w91099chConsole.log('Starting Team Invite...');
		
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
			window.w91099chConsole.error('Team Sync Error:', errorMessage);
			
			$button.prop('disabled', false);
			$progressSection.hide();
			
			alert('❌ Team invite failed: ' + errorMessage);
		}
	}

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
					const msg = (response && response.data) ? String(response.data) : 'Failed to load users';
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
	function loadAffiliates(pluginSlug) {
		const $tableBody = $('#affiliates-table-body');
		const $stats = $('#affiliates-stats');
		const $loadMore = $('#load-more-affiliates');
		const $affiliatesDisplay = $('#affiliates-display');
		
		// Reset scroll position
		if ($affiliatesDisplay.length) {
			$affiliatesDisplay.find('.affiliates-table-container').scrollTop(0);
		}
		
		$tableBody.html('<tr><td colspan="5" style="padding: 20px; text-align: center; color: #666;">Loading affiliates/vendors with payout data...</td></tr>');
		$loadMore.hide();
		
		// Reset payout summary
		$('#total-payouts-amount').text('$0.00');
		$('#affiliates-with-payouts').text('0');
		$('#avg-payout').text('$0.00');
		
		// If "All Detected Plugins" is selected (empty pluginSlug), use a different action
		const action = pluginSlug ? 'w91099ch_get_plugin_affiliates' : 'w91099ch_get_all_affiliates';
		const data = {
			action: action,
			nonce: window.w91099chConnector.nonce,
			limit: 20,
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
			data: data,
			success: function(response) {
				if (response.success) {
					if (response.data && response.data.total_count !== undefined) {
						$('#affiliate-count').text(response.data.total_count);
					}

					if (response.data.affiliates && response.data.affiliates.length > 0) {
						displayAffiliatesWithPayouts(response.data.affiliates, pluginSlug, (response.data.payout_summary || response.data.summary));
						
						// Update stats text based on selection
						if (pluginSlug) {
							$stats.text('Total: ' + response.data.total_count + ' affiliates/vendors • Showing first ' + Math.min(20, response.data.affiliates.length));
						} else {
							$stats.text('Total: ' + response.data.total_count + ' affiliates/vendors across all plugins • Showing first ' + Math.min(20, response.data.affiliates.length));
						}
						
						if (response.data.affiliates.length === 20 && response.data.total_count > 20) {
							$loadMore.show().data('offset', 20);
						} else {
							$loadMore.hide();
						}
					} else {
						// No affiliates found
						$tableBody.html('<tr><td colspan="5" style="padding: 20px; text-align: center; color: #666;">No affiliates/vendors found for this plugin.</td></tr>');
						$stats.text('Total: 0 affiliates/vendors');
						$loadMore.hide();
					}
				} else {
					$tableBody.html('<tr><td colspan="5" style="padding: 20px; text-align: center; color: #dc3545;">Failed to load affiliates/vendors: ' + response.data + '</td></tr>');
				}
			},
			error: function(xhr) {
				$tableBody.html('<tr><td colspan="5" style="padding: 20px; text-align: center; color: #dc3545;">Error loading affiliates/vendors: ' + xhr.statusText + '</td></tr>');
			}
		});
	}

	// Display affiliates with payout data
	function displayAffiliatesWithPayouts(affiliates, pluginSlug, payoutSummary) {
		const $tableBody = $('#affiliates-table-body');
		const $affiliatesTableContainer = $('.affiliates-table-container');
		
		if (!affiliates || affiliates.length === 0) {
			$tableBody.html('<tr><td colspan="5" style="padding: 20px; text-align: center; color: #666;">No affiliates/vendors found</td></tr>');
			return;
		}
		
		let html = '';
		let totalPayouts = 0;
		let affiliatesWithPayouts = 0;
		
		affiliates.forEach(affiliate => {
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
			
			html += `
				<tr style="border-bottom: 1px solid #dee2e6;">
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
		// Always start with 'All Affiliate Detected Plugins' selected
		const initialPlugin = '';
		setTimeout(() => {
			loadAffiliates(initialPlugin);
		}, 500);
		
		// Update the plugin select change handler
		$('#affiliate-plugin-select').on('change', function() {
			const pluginSlug = $(this).val();
			loadAffiliates(pluginSlug);
		});
	}

	// Initialize when page loads

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
		$(document).ready(function() {
			// Toggle dropdown
			$('#w91099ch-w9-tools-btn').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$('#w91099ch-w9-tools-menu').toggleClass('hidden');
				$(this).attr('aria-expanded', $(this).attr('aria-expanded') === 'false' ? 'true' : 'false');
			});

			// Close dropdown when clicking outside
			$(document).on('click', function(e) {
				if (!$(e.target).closest('#w91099ch-w9-tools').length) {
					$('#w91099ch-w9-tools-menu').addClass('hidden');
					$('#w91099ch-w9-tools-btn').attr('aria-expanded', 'false');
				}
			});

			// Handle dropdown actions
			$('#w91099ch-w9-tools-menu [data-action]').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				var action = $(this).data('action');
				var defaultUrl = $('#w91099ch-w9-tools').data('default-page-url');
				
				if (!defaultUrl) {
					alert('Please set a default page first.');
					return;
				}

				switch(action) {
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

				// Close dropdown
				$('#w91099ch-w9-tools-menu').addClass('hidden');
				$('#w91099ch-w9-tools-btn').attr('aria-expanded', 'false');
			});
		});

		// QR Code functionality
		function showQRCode(url) {
			// Create modal if it doesn't exist
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

		// Close QR modal
		$(document).on('click', '#w91099ch-qr-close, #w91099ch-qr-modal', function(e) {
			if (e.target.id === 'w91099ch-qr-close' || e.target.id === 'w91099ch-qr-modal') {
				$('#w91099ch-qr-modal').hide();
			}
		});

		// Close modal on escape key
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape') {
				$('#w91099ch-qr-modal').hide();
			}
		});
    document.execCommand('copy');
    $temp.remove();
}
</script>

<?php endif; ?>




