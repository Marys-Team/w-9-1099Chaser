<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_Accounting_Bookkeeping_Plugin_Detector {

	public function get_accounting_bookkeeping_plugins_data() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$active      = (array) get_option( 'active_plugins', array() );

		$predefined = array(
			array(
				'slug'         => 'wp-erp',
				'name'         => 'WP ERP',
				'plugin_files' => array(
					'erp/erp.php',
					'wp-erp/wp-erp.php',
				),
				'name_regex'   => '/\bwp\s*erp\b/i',
			),
			array(
				'slug'         => 'quickbooks-online-integration',
				'name'         => 'QuickBooks Online Integration',
				'plugin_files' => array(),
				'name_regex'   => '/\bquickbooks\b|\bquick\s*books\b|\bintuit\b|\bmyworks\b/i',
			),
			array(
				'slug'         => 'sliced-invoices',
				'name'         => 'Sliced Invoices',
				'plugin_files' => array(
					'sliced-invoices/sliced-invoices.php',
				),
				'name_regex'   => '/\bsliced\s+invoices\b/i',
			),
			array(
				'slug'         => 'sprout-invoices',
				'name'         => 'Sprout Invoices',
				'plugin_files' => array(
					'sprout-invoices/sprout-invoices.php',
					'sprout-invoices-pro/sprout-invoices-pro.php',
				),
				'name_regex'   => '/\bsprout\s+invoices\b/i',
			),
			array(
				'slug'         => 'woocommerce-pdf-invoices-packing-slips',
				'name'         => 'WooCommerce PDF Invoices & Packing Slips',
				'plugin_files' => array(
					'woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packingslips.php',
					'woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packing-slips.php',
				),
				'name_regex'   => '/\bpdf\s+invoices?\b|\bpacking\s+slips?\b/i',
			),
		);

		$plugins = array();

		foreach ( $predefined as $def ) {
			$matched             = false;
			$matched_plugin_file = '';

			foreach ( $def['plugin_files'] as $plugin_file ) {
				if ( isset( $all_plugins[ $plugin_file ] ) ) {
					$matched             = true;
					$matched_plugin_file = $plugin_file;
					break;
				}
			}

			if ( ! $matched ) {
				foreach ( $all_plugins as $plugin_file => $plugin_data ) {
					$name = isset( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : '';
					if ( $name !== '' && preg_match( $def['name_regex'], $name ) ) {
						$matched             = true;
						$matched_plugin_file = (string) $plugin_file;
						break;
					}
				}
			}

			if ( ! $matched || $matched_plugin_file === '' ) {
				continue;
			}

			$plugin_data = isset( $all_plugins[ $matched_plugin_file ] ) ? $all_plugins[ $matched_plugin_file ] : array();

			$plugins[ $def['slug'] ] = array(
				'name'     => $def['name'],
				'slug'     => $def['slug'],
				'active'   => in_array( $matched_plugin_file, $active, true ),
				'version'  => isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '',
				'detected' => true,
				'source'   => 'predefined',
			);
		}

		$plugins = $this->detect_generic_plugins( $plugins, $all_plugins, $active );

		uasort(
			$plugins,
			function ( $a, $b ) {
				$an = isset( $a['name'] ) ? (string) $a['name'] : '';
				$bn = isset( $b['name'] ) ? (string) $b['name'] : '';
				return strcasecmp( $an, $bn );
			}
		);

		return $plugins;
	}

	public function get_plugins_preview( $plugin_slug, $limit ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 50;
		}

		$plugin_slug = is_string( $plugin_slug ) ? $plugin_slug : '';

		if ( $plugin_slug === '' ) {
			return $this->get_all_accounting_data( $limit );
		}

		return $this->get_accounting_data_for_plugin( $plugin_slug, $limit );
	}

	private function get_all_accounting_data( $limit ) {
		$plugins = $this->get_accounting_bookkeeping_plugins_data();
		if ( ! is_array( $plugins ) || empty( $plugins ) ) {
			return array();
		}

		$rows = array();
		foreach ( array_keys( $plugins ) as $slug ) {
			$slug = is_string( $slug ) ? $slug : '';
			if ( $slug === '' ) {
				continue;
			}
			$sub = $this->get_accounting_data_for_plugin( $slug, $limit );
			if ( is_array( $sub ) && ! empty( $sub ) ) {
				$rows = array_merge( $rows, $sub );
			}
		}

		$rows = $this->dedupe_rows( $rows );

		usort(
			$rows,
			function ( $a, $b ) {
				$ad = ( is_array( $a ) && isset( $a['created'] ) ) ? (string) $a['created'] : '';
				$bd = ( is_array( $b ) && isset( $b['created'] ) ) ? (string) $b['created'] : '';
				$at = $ad !== '' ? strtotime( $ad ) : 0;
				$bt = $bd !== '' ? strtotime( $bd ) : 0;
				if ( ! $at ) {
					$at = 0;
				}
				if ( ! $bt ) {
					$bt = 0;
				}
				return $bt <=> $at;
			}
		);

		if ( count( $rows ) > $limit ) {
			$rows = array_slice( $rows, 0, $limit );
		}

		return $rows;
	}

	private function get_accounting_data_for_plugin( $plugin_slug, $limit ) {
		$plugin_slug = is_string( $plugin_slug ) ? $plugin_slug : '';
		$plugins = $this->get_accounting_bookkeeping_plugins_data();
		
		$source_name = ( is_array( $plugins ) && isset( $plugins[ $plugin_slug ]['name'] ) ) ? (string) $plugins[ $plugin_slug ]['name'] : $this->pretty_plugin_name_from_slug( $plugin_slug );

		// Check if this is WP ERP (multiple possible slugs)
		$is_erp = in_array($plugin_slug, ['wp-erp', 'erp', 'wperp']) || 
				  (isset($plugins[$plugin_slug]) && strpos(strtolower($plugins[$plugin_slug]['name']), 'erp') !== false);
		
		if ($is_erp) {
			return $this->get_wp_erp_accounting_data( $limit, $source_name );
		}

		switch ( $plugin_slug ) {
			case 'sliced-invoices':
			case 'sliced_invoices':
				return $this->get_sliced_invoices_data( $limit, $source_name );
			case 'sprout-invoices':
			case 'sprout_invoices':
				return $this->get_sprout_invoices_data( $limit, $source_name );
			case 'woocommerce-pdf-invoices-packing-slips':
			case 'woocommerce_pdf_invoices':
				return $this->get_woocommerce_invoice_data( $limit, $source_name );
			case 'quickbooks-online-integration':
			case 'quickbooks':
				return $this->get_quickbooks_data( $limit, $source_name );
			default:
				return $this->get_generic_accounting_data( $limit, $source_name, $plugin_slug );
		}
	}

	private function get_wp_erp_accounting_data( $limit, $source_name ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 50;
		}

		$rows = array();
		global $wpdb;

		// Check for ERP invoices table
		$invoice_table = $wpdb->prefix . 'erp_ac_invoices';
		$transaction_table = $wpdb->prefix . 'erp_ac_transactions';
		$people_table = $wpdb->prefix . 'erp_peoples';

		// Check if ERP is active and tables exist
		$erp_active = true; // Trust user that plugin is active - focus on finding data
		$possible_plugins = array(
			'erp/erp.php',
			'wp-erp/wp-erp.php', 
			'erp-ac/erp-ac.php',
			'erp-accounting/erp-accounting.php'
		);
		
		foreach ($possible_plugins as $plugin_file) {
			if (is_plugin_active($plugin_file)) {
				break;
			}
		}
		
		// Also check if ERP functions exist (alternative detection)
		if (function_exists('erp_get_currency') && class_exists('WeDevs_ERP')) {
			// ERP detected via functions/classes
		}

		$invoice_cache_key = 'w91099ch_erp_invoice_exists';
		$transaction_cache_key = 'w91099ch_erp_transaction_exists';
		$people_cache_key = 'w91099ch_erp_people_exists';
		$invoice_exists = wp_cache_get( $invoice_cache_key );
		if ( false === $invoice_exists ) {
			$invoice_exists = $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $invoice_table ) . "'" );
			wp_cache_set( $invoice_cache_key, $invoice_exists, '', 300 );
		}
		$transaction_exists = wp_cache_get( $transaction_cache_key );
		if ( false === $transaction_exists ) {
			$transaction_exists = $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $transaction_table ) . "'" );
			wp_cache_set( $transaction_cache_key, $transaction_exists, '', 300 );
		}
		$people_exists = wp_cache_get( $people_cache_key );
		if ( false === $people_exists ) {
			$people_exists = $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $people_table ) . "'" );
			wp_cache_set( $people_cache_key, $people_exists, '', 300 );
		}

		if ( $invoice_exists ) {
			$total_cache_key = 'w91099ch_erp_total_invoices';
			$total_invoices = wp_cache_get( $total_cache_key );
			if ( false === $total_invoices ) {
				$total_invoices = $wpdb->get_var( "SELECT COUNT(*) FROM `" . esc_sql( $invoice_table ) . "`" );
				wp_cache_set( $total_cache_key, $total_invoices, '', 300 );
			}
			
			if ($total_invoices > 0) {
				$columns_cache = 'w91099ch_erp_invoice_columns';
				$columns = wp_cache_get( $columns_cache );
				if ( false === $columns ) {
					$columns = $wpdb->get_col( "SHOW COLUMNS FROM `" . esc_sql( $invoice_table ) . "`" );
					wp_cache_set( $columns_cache, $columns, '', 300 );
				}
				$sample_cache = 'w91099ch_erp_invoice_sample';
				$sample = wp_cache_get( $sample_cache );
				if ( false === $sample ) {
					$sample = $wpdb->get_row( "SELECT * FROM `" . esc_sql( $invoice_table ) . "` LIMIT 1" );
					wp_cache_set( $sample_cache, $sample, '', 300 );
				}
			}
			
			$invoices_cache_key = 'w91099ch_erp_invoices_' . $limit;
			$invoices = wp_cache_get( $invoices_cache_key );
			if ( false === $invoices ) {
				$invoices = $wpdb->get_results( $wpdb->prepare(
					"SELECT i.id, i.customer_id, i.user_id, i.total, i.due_date, i.status, i.created_at, p.first_name, p.last_name, p.email, u.display_name, u.user_email 
					FROM `$invoice_table` i 
					LEFT JOIN `$people_table` p ON i.customer_id = p.id 
					LEFT JOIN `$wpdb->users` u ON i.user_id = u.ID 
					ORDER BY i.created_at DESC LIMIT %d",
					$limit
				) );
				wp_cache_set( $invoices_cache_key, $invoices, '', 300 );
			}

			foreach ( $invoices as $invoice ) {
				$customer_name = 'N/A';
				$customer_email = 'N/A';
				
				// Try to get customer name from people table first
				if ( $invoice->first_name || $invoice->last_name ) {
					$customer_name = trim( $invoice->first_name . ' ' . $invoice->last_name );
				} elseif ( $invoice->display_name ) {
					$customer_name = $invoice->display_name;
				} elseif ( $invoice->user_id ) {
					$user = get_userdata( $invoice->user_id );
					if ( $user ) {
						$customer_name = $user->display_name;
					}
				}
				
				// Try to get customer email
				if ( $invoice->email ) {
					$customer_email = $invoice->email;
				} elseif ( $invoice->user_email ) {
					$customer_email = $invoice->user_email;
				} elseif ( $invoice->user_id ) {
					$user = get_userdata( $invoice->user_id );
					if ( $user ) {
						$customer_email = $user->user_email;
					}
				}

				$rows[] = array(
					'name'          => $customer_name,
					'email'         => $customer_email,
					'amount'        => '$' . number_format( $invoice->total ?? 0, 2 ),
					'status'        => ucfirst( $invoice->status ?? 'unknown' ),
					'source_plugin' => $source_name,
					'created'       => $invoice->created_at ?? '',
					'type'          => 'Invoice #' . ($invoice->id ?? 'N/A'),
				);
			}
		}

		if ( $transaction_exists ) {
			$transactions_cache_key = 'w91099ch_erp_transactions_' . $limit;
			$transactions = wp_cache_get( $transactions_cache_key );
			if ( false === $transactions ) {
				$transactions = $wpdb->get_results( $wpdb->prepare(
					"SELECT t.id, t.user_id, t.debit, t.credit, t.created_at, t.particulars, u.display_name, u.user_email 
					FROM `$transaction_table` t 
					LEFT JOIN `$wpdb->users` u ON t.user_id = u.ID 
					ORDER BY t.created_at DESC LIMIT %d",
					$limit
				) );
				wp_cache_set( $transactions_cache_key, $transactions, '', 300 );
			}

			foreach ( $transactions as $transaction ) {
				$user_name = 'N/A';
				$user_email = 'N/A';
				
				if ( $transaction->display_name ) {
					$user_name = $transaction->display_name;
					$user_email = $transaction->user_email;
				} elseif ( $transaction->user_id ) {
					$user = get_userdata( $transaction->user_id );
					if ( $user ) {
						$user_name = $user->display_name;
						$user_email = $user->user_email;
					}
				}

				$amount = $transaction->debit ?? $transaction->credit ?? 0;
				$transaction_type = $transaction->debit ? 'Debit' : 'Credit';
				
				$rows[] = array(
					'name'          => $user_name,
					'email'         => $user_email,
					'amount'        => '$' . number_format( $amount, 2 ),
					'status'        => 'Completed',
					'source_plugin' => $source_name,
					'created'       => $transaction->created_at ?? '',
					'type'          => $transaction_type . ' Transaction #' . ($transaction->id ?? 'N/A'),
				);
			}
		}

		// If no data found, check if there are any ERP-related posts
		if ( empty( $rows ) ) {
			$erp_posts = get_posts( array(
				'post_type'      => array( 'erp_invoice', 'erp_payment', 'erp_transaction' ),
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			) );

			foreach ( $erp_posts as $post ) {
				$author = get_userdata( $post->post_author );
				$amount = get_post_meta( $post->ID, '_amount', true ) ?: get_post_meta( $post->ID, '_total', true ) ?: '0';
				$status = get_post_meta( $post->ID, '_status', true ) ?: 'Published';

				$rows[] = array(
					'name'          => $author ? $author->display_name : $post->post_title,
					'email'         => $author ? $author->user_email : 'N/A',
					'amount'        => '$' . number_format( floatval( $amount ), 2 ),
					'status'        => ucfirst( $status ),
					'source_plugin' => $source_name,
					'created'       => $post->post_date,
					'type'          => ucfirst( get_post_type_object( $post->post_type )->labels->singular_name ),
				);
			}
		}

		// If still no data, create sample data with proper message
		if ( empty( $rows ) ) {
			// Try to get data from people table if it exists
			if ($people_exists) {
				$people_cache_key = 'w91099ch_erp_people_' . $limit;
				$people_data = wp_cache_get( $people_cache_key );
				if ( false === $people_data ) {
					$people_data = $wpdb->get_results( $wpdb->prepare(
						"SELECT * FROM `$people_table` LIMIT %d",
						$limit
					) );
					wp_cache_set( $people_cache_key, $people_data, '', 300 );
				}
				
				if (!empty($people_data)) {
					foreach ($people_data as $person) {
						$name = trim($person->first_name . ' ' . $person->last_name);
						if (empty($name)) $name = $person->company ?? '';
						if (empty($name)) $name = $person->name ?? '';
						if (empty($name)) $name = 'Person #' . $person->id;
						
						$email = $person->email ?? $person->email_address ?? 'N/A';
						$created = $person->created ?? $person->created_at ?? current_time('mysql');
						
						$row_data = array(
							'name'          => $name,
							'email'         => $email,
							'amount'        => '$0.00',
							'status'        => 'Customer',
							'source_plugin' => $source_name,
							'created'       => $created,
							'type'          => 'Customer',
						);
						
						$rows[] = $row_data;
					}
				}
			}
			
			// If still no data after checking people table
			if (empty($rows)) {
				$message = 'No ERP Data Found';
				if (!$invoice_exists && !$transaction_exists) {
					$message = 'ERP Accounting Tables Missing - Install ERP Accounting Module';
				} else {
					$total_records = 0;
					if ($invoice_exists) {
						$inv_count_cache = 'w91099ch_erp_inv_count';
						$inv_count = wp_cache_get( $inv_count_cache );
						if ( false === $inv_count ) {
							$inv_count = $wpdb->get_var( "SELECT COUNT(*) FROM `" . esc_sql( $invoice_table ) . "`" );
							wp_cache_set( $inv_count_cache, $inv_count, '', 300 );
						}
						$total_records += $inv_count;
					}
					if ($transaction_exists) {
						$trn_count_cache = 'w91099ch_erp_trn_count';
						$trn_count = wp_cache_get( $trn_count_cache );
						if ( false === $trn_count ) {
							$trn_count = $wpdb->get_var( "SELECT COUNT(*) FROM `" . esc_sql( $transaction_table ) . "`" );
							wp_cache_set( $trn_count_cache, $trn_count, '', 300 );
						}
						$total_records += $trn_count;
					}
					
					if ($total_records == 0) {
						$message = 'ERP Tables Empty - Create Some Invoices/Transactions';
					} else {
						$message = "ERP Data Issue - Found {$total_records} records but couldn't process";
					}
				}
				
				return array();
			}
		}

		return $rows;
	}

	private function create_sample_data( $source_name, $message ) {
		return array();
	}

	private function get_sliced_invoices_data( $limit, $source_name ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 50;
		}

		$rows = array();
		global $wpdb;

		// Check for Sliced Invoices
		$invoice_table = $wpdb->prefix . 'sliced_invoices';
		$si_table_cache = 'w91099ch_si_table_exists';
		$si_table_exists = wp_cache_get( $si_table_cache );
		if ( false === $si_table_exists ) {
			$si_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $invoice_table ) . "'" );
			wp_cache_set( $si_table_cache, $si_table_exists, '', 300 );
		}
		if ( $si_table_exists ) {
			$si_inv_cache = 'w91099ch_si_invoices_' . $limit;
			$invoices = wp_cache_get( $si_inv_cache );
			if ( false === $invoices ) {
				$invoices = $wpdb->get_results( $wpdb->prepare(
					"SELECT id, client_id, number, total, status, created FROM `$invoice_table` ORDER BY created DESC LIMIT %d",
					$limit
				) );
				wp_cache_set( $si_inv_cache, $invoices, '', 300 );
			}

			foreach ( $invoices as $invoice ) {
				$client = get_userdata( $invoice->client_id );
				$rows[] = array(
					'name'          => $client ? $client->display_name : 'Client ID ' . $invoice->client_id,
					'email'         => $client ? $client->user_email : 'N/A',
					'amount'        => '$' . number_format( $invoice->total ?? 0, 2 ),
					'status'        => ucfirst( $invoice->status ?? 'unknown' ),
					'source_plugin' => $source_name,
					'created'       => $invoice->created ?? '',
					'type'          => 'Invoice #' . ($invoice->number ?? $invoice->id),
				);
			}
		}

		return $rows;
	}

	private function get_sprout_invoices_data( $limit, $source_name ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 50;
		}

		$rows = array();
		global $wpdb;

		// Check for Sprout Invoices
		$invoice_table = $wpdb->prefix . 'sprout_invoices';
		$sp_table_cache = 'w91099ch_sp_table_exists';
		$sp_table_exists = wp_cache_get( $sp_table_cache );
		if ( false === $sp_table_exists ) {
			$sp_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $invoice_table ) . "'" );
			wp_cache_set( $sp_table_cache, $sp_table_exists, '', 300 );
		}
		if ( $sp_table_exists ) {
			$sp_inv_cache = 'w91099ch_sp_invoices_' . $limit;
			$invoices = wp_cache_get( $sp_inv_cache );
			if ( false === $invoices ) {
				$invoices = $wpdb->get_results( $wpdb->prepare(
					"SELECT id, user_id, total, status, post_date FROM `$invoice_table` ORDER BY post_date DESC LIMIT %d",
					$limit
				) );
				wp_cache_set( $sp_inv_cache, $invoices, '', 300 );
			}

			foreach ( $invoices as $invoice ) {
				$user = get_userdata( $invoice->user_id );
				$rows[] = array(
					'name'          => $user ? $user->display_name : 'User ID ' . $invoice->user_id,
					'email'         => $user ? $user->user_email : 'N/A',
					'amount'        => '$' . number_format( $invoice->total ?? 0, 2 ),
					'status'        => ucfirst( $invoice->status ?? 'unknown' ),
					'source_plugin' => $source_name,
					'created'       => $invoice->post_date ?? '',
					'type'          => 'Invoice #' . $invoice->id,
				);
			}
		}

		return $rows;
	}

	private function get_woocommerce_invoice_data( $limit, $source_name ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 50;
		}

		$rows = array();

		// Get WooCommerce orders with invoicing
		$args = array(
			'post_type'      => 'shop_order',
			'post_status'    => array( 'wc-completed', 'wc-processing' ),
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$orders = get_posts( $args );

		foreach ( $orders as $order ) {
			$wc_order = wc_get_order( $order->ID );
			if ( $wc_order ) {
				$rows[] = array(
					'name'          => $wc_order->get_formatted_billing_full_name(),
					'email'         => $wc_order->get_billing_email(),
					'amount'        => $wc_order->get_formatted_order_total(),
					'status'        => 'Completed',
					'source_plugin' => $source_name,
					'created'       => $order->post_date,
					'type'          => 'Order #' . $wc_order->get_order_number(),
				);
			}
		}

		return $rows;
	}

	private function get_quickbooks_data( $limit, $source_name ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 50;
		}

		$rows = array();

		// Check for QuickBooks data in user meta or options
		$quickbooks_data = get_option( 'quickbooks_data', array() );
		if ( ! empty( $quickbooks_data['customers'] ) ) {
			$customers = array_slice( $quickbooks_data['customers'], 0, $limit );
			foreach ( $customers as $customer ) {
				$rows[] = array(
					'name'          => $customer['name'] ?? 'N/A',
					'email'         => $customer['email'] ?? 'N/A',
					'amount'        => '$' . number_format( $customer['balance'] ?? 0, 2 ),
					'status'        => 'Active',
					'source_plugin' => $source_name,
					'created'       => $customer['created_at'] ?? '',
					'type'          => 'Customer',
				);
			}
		}

		return $rows;
	}

	private function get_generic_accounting_data( $limit, $source_name, $plugin_slug ) {
		$limit = (int) $limit;
		if ( $limit <= 0 ) {
			$limit = 50;
		}

		$rows = array();
		$plugin_slug = is_string( $plugin_slug ) ? $plugin_slug : '';

		error_log('W91099ch Debug: Generic method for plugin: ' . $plugin_slug . ' with source: ' . $source_name);

		// Method 1: Check for plugin-specific custom post types
		$post_types = get_post_types( array( 'public' => true ) );
		$accounting_post_types = array();

		foreach ( $post_types as $post_type ) {
			// Look for post types that match the plugin slug
			if ( $plugin_slug !== '' && strpos( $post_type, str_replace( '-', '_', $plugin_slug) ) !== false ) {
				$accounting_post_types[] = $post_type;
			}
			// Also look for general accounting post types
			elseif ( strpos( $post_type, 'invoice' ) !== false || 
					 strpos( $post_type, 'payment' ) !== false || 
					 strpos( $post_type, 'transaction' ) !== false ||
					 strpos( $post_type, 'estimate' ) !== false ) {
				$accounting_post_types[] = $post_type;
			}
		}

		error_log('W91099ch Debug: Found accounting post types: ' . print_r($accounting_post_types, true));

		if ( ! empty( $accounting_post_types ) ) {
			$args = array(
				'post_type'      => $accounting_post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			);

			$posts = get_posts( $args );
			error_log('W91099ch Debug: Found ' . count($posts) . ' posts');

			foreach ( $posts as $post ) {
				$author = get_userdata( $post->post_author );
				$amount = get_post_meta( $post->ID, '_amount', true ) ?: get_post_meta( $post->ID, '_total', true ) ?: '0';
				$status = get_post_meta( $post->ID, '_status', true ) ?: 'Published';

				// Check if this post is related to the specific plugin
				$post_plugin = get_post_meta( $post->ID, '_plugin', true ) ?: get_post_meta( $post->ID, '_source_plugin', true );
				
				// If no specific plugin metadata, include it only if we're showing all plugins
				$include_post = ( $plugin_slug === '' ) || ( $post_plugin && $post_plugin === $plugin_slug );
				
				if ( $include_post ) {
					$rows[] = array(
						'name'          => $author ? $author->display_name : $post->post_title,
						'email'         => $author ? $author->user_email : 'N/A',
						'amount'        => '$' . number_format( floatval( $amount ), 2 ),
						'status'        => ucfirst( $status ),
						'source_plugin' => $source_name,
						'created'       => $post->post_date,
						'type'          => ucfirst( get_post_type_object( $post->post_type )->labels->singular_name ),
					);
				}
			}
		}

		// Method 2: Check for plugin-specific user meta
		if ( empty( $rows ) || $plugin_slug === '' ) {
			$users = get_users( array( 'number' => $limit, 'orderby' => 'registered', 'order' => 'DESC' ) );
			
			foreach ( $users as $user ) {
				$accounting_meta = get_user_meta( $user->ID );
				$has_accounting_data = false;
				$amount = 0;
				$user_plugin = '';

				foreach ( $accounting_meta as $meta_key => $meta_value ) {
					// Look for accounting-related meta
					if ( strpos( $meta_key, 'invoice' ) !== false || 
						 strpos( $meta_key, 'payment' ) !== false || 
						 strpos( $meta_key, 'billing' ) !== false ||
						 strpos( $meta_key, 'accounting' ) !== false ) {
						$has_accounting_data = true;
						if ( strpos( $meta_key, 'amount' ) !== false || strpos( $meta_key, 'total' ) !== false ) {
							$amount = floatval( $meta_value[0] );
						}
						// Check for plugin-specific meta
						if ( strpos( $meta_key, $plugin_slug ) !== false ) {
							$user_plugin = $plugin_slug;
						}
					}
				}

				// Include user if they have accounting data and match the plugin (or if showing all)
				if ( $has_accounting_data && ( $plugin_slug === '' || $user_plugin === $plugin_slug ) ) {
					$rows[] = array(
						'name'          => $user->display_name,
						'email'         => $user->user_email,
						'amount'        => '$' . number_format( $amount, 2 ),
						'status'        => 'Active',
						'source_plugin' => $source_name,
						'created'       => $user->user_registered,
						'type'          => 'Customer',
					);
				}
			}
		}

		// Method 3: If still no data and specific plugin, create plugin-specific sample data
		if ( empty( $rows ) && $plugin_slug !== '' ) {
			return array();
		}

		error_log('W91099ch Debug: Generic method returning ' . count($rows) . ' rows');
		return $rows;
	}

	private function dedupe_rows( $rows ) {
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array();
		}

		$out  = array();
		$seen = array();
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$key = ( isset( $r['email'] ) && $r['email'] !== '' ) ? (string) $r['email'] : ( ( isset( $r['name'] ) && $r['name'] !== '' ) ? (string) $r['name'] : '' );
			if ( $key === '' ) {
				continue;
			}
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $r;
		}

		return $out;
	}

	private function pretty_plugin_name_from_slug( $slug ) {
		$slug = is_string( $slug ) ? $slug : '';
		if ( $slug === '' ) {
			return '';
		}
		$slug = str_replace( array( '-', '_' ), ' ', $slug );
		$slug = ucwords( $slug );
		return $slug;
	}

	private function detect_generic_plugins( $existing, $all_plugins, $active ) {
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$keywords = array(
			'accounting',
			'bookkeeping',
			'invoice',
			'invoices',
			'invoicing',
			'estimate',
			'estimates',
			'billing',
			'quickbooks',
			'intuit',
			'pdf invoice',
			'pdf invoices',
			'packing slip',
			'packing slips',
		);

		$used_slugs = array();
		foreach ( $existing as $k => $v ) {
			$used_slugs[ (string) $k ] = true;
		}

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$name = isset( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : '';
			$desc = isset( $plugin_data['Description'] ) ? (string) $plugin_data['Description'] : '';

			$hay      = strtolower( $name . ' ' . wp_strip_all_tags( $desc ) );
			$is_match = false;
			foreach ( $keywords as $kw ) {
				if ( strpos( $hay, $kw ) !== false ) {
					$is_match = true;
					break;
				}
			}

			if ( ! $is_match ) {
				continue;
			}

			$slug = $this->canonicalize_plugin_slug( $this->slug_from_plugin_file( $plugin_file ) );
			if ( $slug === '' || isset( $used_slugs[ $slug ] ) ) {
				continue;
			}

			if ( $this->is_blocked_generic_plugin( $slug, $name, $desc ) ) {
				continue;
			}

			$existing[ $slug ] = array(
				'name'     => $name !== '' ? $name : $slug,
				'slug'     => $slug,
				'active'   => in_array( $plugin_file, $active, true ),
				'version'  => isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '',
				'detected' => true,
				'source'   => 'generic',
			);

			$used_slugs[ $slug ] = true;
		}

		return $existing;
	}

	private function is_blocked_generic_plugin( $slug, $name, $desc ) {
		$slug = is_string( $slug ) ? strtolower( $slug ) : '';
		$name = is_string( $name ) ? strtolower( $name ) : '';
		$desc = is_string( $desc ) ? strtolower( $desc ) : '';
		$hay  = $slug . ' ' . $name . ' ' . $desc;

		$blocked_slugs = array(
			'woocommerce',
			'jetpack',
			'elementor',
			'wordfence',
			'litespeed-cache',
			'wp-rocket',
			'w3-total-cache',
			'rank-math',
			'yoast',
			'akismet',
			'updraftplus',
			'plugin-compatibility-checker',
		);

		if ( $slug !== '' && in_array( $slug, $blocked_slugs, true ) ) {
			return true;
		}

		$blocked_terms = array(
			'cache',
			'caching',
			'seo',
			'security',
			'backup',
			'antispam',
			'newsletter',
			'smtp',
			'analytics',
			'compatibility',
			'checker',
		);

		foreach ( $blocked_terms as $t ) {
			if ( strpos( $hay, $t ) !== false ) {
				return true;
			}
		}

		return false;
	}

	private function slug_from_plugin_file( $plugin_file ) {
		$plugin_file = is_string( $plugin_file ) ? $plugin_file : '';
		if ( $plugin_file === '' ) {
			return '';
		}

		$parts = explode( '/', $plugin_file );
		if ( ! is_array( $parts ) || empty( $parts ) ) {
			return '';
		}

		return strtolower( (string) $parts[0] );
	}

	private function canonicalize_plugin_slug( $slug ) {
		$slug = is_string( $slug ) ? strtolower( $slug ) : '';
		if ( $slug === '' ) {
			return '';
		}

		$aliases = array(
			'wp-erp'          => 'wp-erp',
			'sliced-invoices' => 'sliced-invoices',
			'sprout-invoices' => 'sprout-invoices',
		);

		return isset( $aliases[ $slug ] ) ? $aliases[ $slug ] : $slug;
	}
}
