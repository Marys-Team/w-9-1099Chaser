<?php
/**
 * Plugin Update Checker
 *
 * @package w91099ch
 * @since 1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_Update_Checker {

	private static $instance = null;
	private $current_version;
	private $plugin_slug        = 'w9-1099-chaser';
	private $transient_key      = 'w91099ch_update_check';
	private $transient_duration = 12 * HOUR_IN_SECONDS; // Check every 12 hours
	private $plugin_file;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->current_version = w91099ch_VERSION;
		$this->plugin_file     = plugin_basename( w91099ch_PLUGIN_FILE );
		add_action( 'wp_ajax_w91099ch_check_update', array( $this, 'ajax_check_update' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_update_scripts' ) );
	}

	/**
	 * Check for plugin updates
	 */
	public function check_for_updates( $force = false ) {
		if ( ! $force ) {
			$cached_result = get_transient( $this->transient_key );
			if ( false !== $cached_result ) {
				return $cached_result;
			}
		}

		if ( $force && function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}

		$update_data = get_site_transient( 'update_plugins' );
		$item        = null;
		if ( is_object( $update_data ) && isset( $update_data->response ) && is_array( $update_data->response ) ) {
			$item = $update_data->response[ $this->plugin_file ] ?? null;
		}

		if ( is_object( $item ) && isset( $item->new_version ) ) {
			$latest_version = sanitize_text_field( (string) $item->new_version );
			$result         = array(
				'error'            => false,
				'update_available' => true,
				'latest_version'   => $latest_version,
				'download_url'     => '',
				'changelog'        => '',
				'requires_wp'      => isset( $item->requires ) ? sanitize_text_field( (string) $item->requires ) : '',
				'tested_wp'        => isset( $item->tested ) ? sanitize_text_field( (string) $item->tested ) : '',
			);
		} else {
			$result = array(
				'error'            => false,
				'update_available' => false,
				'latest_version'   => $this->current_version,
				'download_url'     => '',
				'changelog'        => '',
				'requires_wp'      => '',
				'tested_wp'        => '',
			);
		}

		// Cache the result
		set_transient( $this->transient_key, $result, $this->transient_duration );

		return $result;
	}

	/**
	 * AJAX handler for update check
	 */
	public function ajax_check_update() {
		check_ajax_referer( 'w91099ch_update_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$force_param_raw = filter_input( INPUT_POST, 'force', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$force_raw       = is_string( $force_param_raw ) ? sanitize_text_field( wp_unslash( $force_param_raw ) ) : '';
		$force     = ( $force_raw === 'true' );
		$result    = $this->check_for_updates( $force );

		if ( $result['error'] ) {
			$msg = isset( $result['message'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $result['message'] ) ) : '';
			wp_send_json_error( $msg );
		} else {
			wp_send_json_success( $result );
		}
	}

	/**
	 * Enqueue update checker scripts
	 */
	public function enqueue_update_scripts( $hook ) {
		if ( ! is_string( $hook ) ) {
			return;
		}

		if ( strpos( $hook, 'w91099ch' ) === false && strpos( $hook, 'w9-1099-chaser' ) === false ) {
			return;
		}

		if ( ! (bool) get_option( 'w91099ch_admin_consent', false ) ) {
			return;
		}

		wp_enqueue_script( 'jquery' );

		wp_localize_script(
			'jquery',
			'w91099chUpdateChecker',
			array(
				'ajaxurl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'w91099ch_update_nonce' ),
				'current_version' => $this->current_version,
			)
		);

		wp_add_inline_script( 'jquery', $this->get_update_checker_js() );
		wp_add_inline_style( 'wp-admin', $this->get_update_checker_css() );
	}

	/**
	 * Get update checker JavaScript
	 */
	private function get_update_checker_js() {
		return '(function($) {
            if (typeof w91099chUpdateChecker === "undefined") {
                console.error("w91099chUpdateChecker not defined");
                return;
            }

            function escapeHtml(value) {
                var str = (value === undefined || value === null) ? "" : String(value);
                return str
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/\'/g, "&#039;");
            }
            
            $(document).ready(function() {
                if ($("#1099automation-check-updates").length) {
                    return;
                }

                var $wrap = $(".wrap").first();
                if (!$wrap.length) {
                    return;
                }

                var buttonHtml = "<button type=\"button\" class=\"button\" id=\"1099automation-check-updates\">Check for Updates</button>";
                $wrap.prepend("<div class=\"1099automation-update-check\" style=\"margin: 16px 0;\">" + buttonHtml + "</div>");

                $("#1099automation-check-updates").on("click", function() {
                    var $btn = $(this);
                    $btn.prop("disabled", true).text("Checking...");

                    $.ajax({
                        url: w91099chUpdateChecker.ajaxurl,
                        type: "POST",
                        data: {
                            action: "w91099ch_check_update",
                            nonce: w91099chUpdateChecker.nonce,
                            force: true
                        },
                        success: function(response) {
                            if (response && response.success && response.data) {
                                if (response.data.update_available) {
                                    showUpdateBanner(response.data);
                                } else {
                                    showUpToDateNotice(response.data);
                                }
                            } else {
                                showUpdateError("Update check failed.");
                            }
                        },
                        error: function(xhr, status, error) {
                            showUpdateError(error || "Update check failed.");
                        },
                        complete: function() {
                            $btn.prop("disabled", false).text("Check for Updates");
                        }
                    });
                });
                
                function showUpdateBanner(updateData) {
                    if ($(".1099automation-update-banner").length) return;

                    var latestVersion = (updateData && updateData.latest_version) ? escapeHtml(updateData.latest_version) : "";
                    
                    var bannerHtml = "<div class=\"1099automation-update-banner\">" +
                        "<div class=\"1099automation-update-content\">" +
                        "<div class=\"1099automation-update-icon\"><span class=\"dashicons dashicons-update\"></span></div>" +
                        "<div class=\"1099automation-update-text\">" +
                        "<strong>Update Available!</strong>" +
                        "<span>Version " + latestVersion + " is ready to install.</span>" +
                        "</div>" +
                        "<a href=\"" + w91099chUpdateChecker.ajaxurl.replace("admin-ajax.php", "plugins.php") + "\" class=\"1099automation-update-btn\">Update Now</a>" +
                        "<button class=\"1099automation-update-dismiss\" title=\"Dismiss\"><span class=\"dashicons dashicons-no-alt\"></span></button>" +
                        "</div>" +
                        "</div>";
                    
                    $(".wrap").first().prepend(bannerHtml);
                    
                    $(".1099automation-update-dismiss").on("click", function() {
                        $(".1099automation-update-banner").slideUp(300, function() { $(this).remove(); });
                    });
                    
                    console.log("Update banner displayed");
                }

                function showUpToDateNotice(updateData) {
                    if ($(".1099automation-update-banner").length) return;
                    if ($(".1099automation-update-uptodate").length) return;

                    var latest = (updateData && updateData.latest_version) ? updateData.latest_version : w91099chUpdateChecker.current_version;
                    latest = escapeHtml(latest);
                    var html = "<div class=\"notice notice-success 1099automation-update-uptodate\"><p><strong>Up to date.</strong> Version " + latest + " is installed.</p></div>";
                    $(".wrap").first().prepend(html);
                }

                function showUpdateError(message) {
                    if ($(".1099automation-update-banner").length) return;
                    if ($(".1099automation-update-error").length) return;

                    var safe = (message && typeof message === "string") ? message : "Update check failed.";
                    safe = escapeHtml(safe);
                    var html = "<div class=\"notice notice-error 1099automation-update-error\"><p><strong>Update check failed.</strong> " + safe + "</p></div>";
                    $(".wrap").first().prepend(html);
                }
            });
        })(jQuery);';
	}

	/**
	 * Get update checker CSS
	 */
	private function get_update_checker_css() {
		return "
        .1099automation-update-banner {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            padding: 0;
            margin: 20px 0;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(30, 58, 138, 0.3);
            border-left: 5px solid #fbbf24;
            animation: w91099chBannerSlideDown 0.5s ease-out;
            position: relative;
            overflow: hidden;
        }
        
        .1099automation-update-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 1px, transparent 1px);
            background-size: 30px 30px;
            opacity: 0.4;
        }
        
        .1099automation-update-content {
            display: flex;
            align-items: center;
            padding: 24px 30px;
            gap: 20px;
            position: relative;
            z-index: 2;
        }
        
        .1099automation-update-icon {
            font-size: 36px;
            animation: w91099chRotate 2s linear infinite;
            flex-shrink: 0;
        }
        
        .1099automation-update-icon .dashicons {
            width: 36px;
            height: 36px;
            font-size: 36px;
        }
        
        .1099automation-update-text {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .1099automation-update-text strong {
            font-size: 20px;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .1099automation-update-text span {
            font-size: 15px;
            opacity: 0.95;
            font-weight: 500;
        }
        
        .1099automation-update-btn {
            background: white;
            color: #1e3a8a;
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 15px;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            flex-shrink: 0;
        }
        
        .1099automation-update-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            color: #1e3a8a;
            background: #fbbf24;
        }
        
        .1099automation-update-dismiss {
            background: transparent;
            border: none;
            color: white;
            cursor: pointer;
            padding: 8px;
            opacity: 0.7;
            transition: opacity 0.3s;
            flex-shrink: 0;
        }
        
        .1099automation-update-dismiss:hover {
            opacity: 1;
        }
        
        .1099automation-update-dismiss .dashicons {
            width: 24px;
            height: 24px;
            font-size: 24px;
        }
        
        @keyframes w91099chBannerSlideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes w91099chRotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 782px) {
            .1099automation-update-content {
                padding: 20px;
                gap: 15px;
            }
            
            .1099automation-update-icon {
                font-size: 28px;
            }
            
            .1099automation-update-text strong {
                font-size: 18px;
            }
            
            .1099automation-update-text span {
                font-size: 14px;
            }
            
            .1099automation-update-btn {
                padding: 12px 24px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 600px) {
            .1099automation-update-content {
                flex-wrap: wrap;
                gap: 12px;
                padding: 16px;
            }
            
            .1099automation-update-btn {
                width: 100%;
                text-align: center;
            }
        }
        ";
	}

	/**
	 * Get current plugin version
	 */
	public function get_current_version() {
		return $this->current_version;
	}

	/**
	 * Clear update cache
	 */
	public function clear_update_cache() {
		delete_transient( $this->transient_key );
	}

	/**
	 * Force update check (for testing)
	 */
	public function force_update_check() {
		$this->clear_update_cache();
		return $this->check_for_updates( true );
	}
}
