<?php
// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class w91099ch_Admin {

	/**
	 * Default W-9 page URL for admin bar functionality
	 * @var string
	 */
	private $w9_default_page_url = '';

	/**
	 * Handle W-9 form submission and PDF generation (AJAX)
	 */
	public function handle_w9_form_submission() {
		if ( ! check_ajax_referer( 'w91099ch_w9_form_nonce', 'nonce', false ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		try {
			$pdf_content     = '';
			$template_source = 'local';
			$err_msg         = '';

			$cache_key = 'w91099ch_w9_pdf_template';
			$cached    = get_transient( $cache_key );
			if ( is_string( $cached ) && '' !== $cached ) {
				$pdf_content = $cached;
			}

			if ( ! $pdf_content ) {
				// Use local PDF template instead of downloading from external source
				$local_pdf_path = w91099ch_PLUGIN_PATH . 'assets/pdf/fw9_IREG_esign.pdf';
				if ( file_exists( $local_pdf_path ) && is_readable( $local_pdf_path ) ) {
					$pdf_content = '';
					if ( ! function_exists( 'WP_Filesystem' ) ) {
						require_once ABSPATH . 'wp-admin/includes/file.php';
					}
					$fs_ready = function_exists( 'WP_Filesystem' ) ? WP_Filesystem() : false;
					if ( $fs_ready ) {
						global $wp_filesystem;
						if ( isset( $wp_filesystem ) && is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'get_contents' ) ) {
							$pdf_content = $wp_filesystem->get_contents( $local_pdf_path );
						}
					}
					if ( $pdf_content ) {
						set_transient( $cache_key, $pdf_content, DAY_IN_SECONDS );
					}
				}
			}

			if ( ! $pdf_content ) {
				$local_pdf_path = w91099ch_PLUGIN_PATH . 'assets/pdf/fw9_IREG_esign.pdf';
				if ( file_exists( $local_pdf_path ) && is_readable( $local_pdf_path ) ) {
					$pdf_content = '';
					if ( ! function_exists( 'WP_Filesystem' ) ) {
						require_once ABSPATH . 'wp-admin/includes/file.php';
					}
					$fs_ready = function_exists( 'WP_Filesystem' ) ? WP_Filesystem() : false;
					if ( $fs_ready ) {
						global $wp_filesystem;
						if ( isset( $wp_filesystem ) && is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'get_contents' ) ) {
							$pdf_content = $wp_filesystem->get_contents( $local_pdf_path );
						}
					}
					if ( $pdf_content ) {
						$template_source = 'local';
					}
				}
			}

			if ( empty( $pdf_content ) ) {
				if ( '' !== $err_msg ) {
					throw new Exception(
						esc_html__(
							'Could not retrieve W-9 form: ',
							'w9-1099-chaser'
						) . $err_msg
					);
				}
				throw new Exception( esc_html__( 'Could not retrieve W-9 form template', 'w9-1099-chaser' ) );
			}

			// Return original PDF (as before)
			wp_send_json_success(
				array(
					'pdf_base64'      => base64_encode( $pdf_content ),
					'template_source' => $template_source,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				esc_html__(
					'An error occurred: ',
					'w9-1099-chaser'
				) . sanitize_text_field( $e->getMessage() )
			);
		}
	}

	/**
	 * Handle government W-9 form template retrieval (AJAX)
	 */
	public function handle_govt_form_submission() {
		if ( ! check_ajax_referer( 'w91099ch_w9_form_nonce', 'nonce', false ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		try {
			$pdf_content     = '';
			$local_pdf_path  = '';
			$candidates      = array(
				w91099ch_PLUGIN_PATH . 'assets/pdf/w9-govt-form.pdf',
				w91099ch_PLUGIN_PATH . 'assets/pdf/fw9_IREG_esign.pdf',
			);

			foreach ( $candidates as $candidate ) {
				if ( file_exists( $candidate ) && is_readable( $candidate ) ) {
					$local_pdf_path = $candidate;
					break;
				}
			}

			if ( $local_pdf_path && function_exists( 'file_get_contents' ) ) {
				$direct_bytes = @file_get_contents( $local_pdf_path );
				if ( is_string( $direct_bytes ) && '' !== $direct_bytes ) {
					$pdf_content = $direct_bytes;
				}
			}

			if ( ! $pdf_content && $local_pdf_path ) {
				if ( ! function_exists( 'WP_Filesystem' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}
				$fs_ready = function_exists( 'WP_Filesystem' ) ? WP_Filesystem() : false;
				if ( $fs_ready ) {
					global $wp_filesystem;
					if ( isset( $wp_filesystem ) && is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'get_contents' ) ) {
						$pdf_content = $wp_filesystem->get_contents( $local_pdf_path );
					}
				}
			}

			if ( empty( $pdf_content ) ) {
				throw new Exception( esc_html__( 'Could not retrieve government W-9 form template', 'w9-1099-chaser' ) );
			}

			wp_send_json_success(
				array(
					'pdf_base64'      => base64_encode( $pdf_content ),
					'template_source' => 'government_template',
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error(
				esc_html__(
					'An error occurred: ',
					'w9-1099-chaser'
				) . sanitize_text_field( $e->getMessage() )
			);
		}
	}
	
	/**
	 * Fill the real government PDF using pdftk
	 */
	private function fill_real_government_pdf( $form_data ) {
		// Do not assume the web server runtime matches the interactive shell runtime.
		// Try multiple candidates (container/chroot/PATH differences).
		$pdftk_candidates = array(
			'/usr/bin/pdftk',
			'/usr/local/bin/pdftk',
			'/bin/pdftk',
			'/snap/bin/pdftk',
			'pdftk',
		);
		
		// Get the real government PDF template
		$pdf_template = w91099ch_PLUGIN_PATH . 'assets/pdf/w9-govt-form.pdf';
		if ( ! file_exists( $pdf_template ) ) {
			throw new Exception( 'Government W-9 PDF template not found: ' . $pdf_template );
		}
		
		// Create temporary directory in WordPress uploads
		$upload_dir = wp_upload_dir();
		$temp_dir = $upload_dir['basedir'] . '/w9-temp/';
		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}
		
		// Create FDF content with all the fields from your specification
		$fdf_content = $this->create_fdf_content( $form_data );
		
		// Save FDF to file
		$fdf_file = $temp_dir . 'w9_form_' . uniqid() . '.fdf';
		file_put_contents( $fdf_file, $fdf_content );
		error_log('FDF file created: ' . $fdf_file);
		
		// Create output file path
		$output_pdf = $temp_dir . 'w9_filled_' . uniqid() . '.pdf';

		$attempt_logs = array();
		$cmd_result = array( 'exit_code' => null, 'output' => '' );
		foreach ( $pdftk_candidates as $pdftk_bin ) {
			$command = sprintf(
				'%s %s fill_form %s output %s flatten 2>&1',
				escapeshellcmd( $pdftk_bin ),
				escapeshellarg( $pdf_template ),
				escapeshellarg( $fdf_file ),
				escapeshellarg( $output_pdf )
			);
			error_log('Executing pdftk command: ' . $command);
			$cmd_result = $this->run_command_capture( $command );
			error_log('pdftk exit code: ' . (string) $cmd_result['exit_code'] );
			error_log('pdftk output: ' . (string) $cmd_result['output'] );
			$attempt_logs[] = $pdftk_bin . ': ' . trim( (string) $cmd_result['output'] );
			if ( file_exists( $output_pdf ) && filesize( $output_pdf ) > 0 ) {
				break;
			}
		}
		
		// Clean up FDF file
		unlink( $fdf_file );
		
		// Check if output file was created
		if ( ! file_exists( $output_pdf ) ) {
			throw new Exception( 'pdftk failed to create output file. Attempts: ' . implode( ' | ', $attempt_logs ) );
		}
		
		// Read the filled PDF
		$pdf_content = file_get_contents( $output_pdf );
		
		// Clean up output file
		unlink( $output_pdf );
		
		if ( empty( $pdf_content ) ) {
			throw new Exception( 'Generated PDF is empty' );
		}
		
		error_log('Successfully filled government PDF, size: ' . strlen($pdf_content));
		return $pdf_content;
	}
	
	private function resolve_pdftk_binary() {
		// Some PHP environments have open_basedir restrictions that can make file_exists/is_executable
		// return false for system paths even though the binary is runnable.
		error_log( 'Resolving pdftk binary...' );
		$candidates = array(
			'/usr/bin/pdftk',
			'/usr/local/bin/pdftk',
			'/bin/pdftk',
			'/snap/bin/pdftk',
			'pdftk',
		);
		
		foreach ( $candidates as $bin ) {
			$cmd = escapeshellcmd( $bin ) . ' --version 2>&1';
			$res = $this->run_command_capture( $cmd, true );
			$out = is_string( $res['output'] ) ? $res['output'] : '';
			error_log( 'pdftk probe [' . $bin . '] exit=' . (string) $res['exit_code'] . ' out=' . substr( preg_replace( '/\s+/', ' ', trim( $out ) ), 0, 200 ) );
			if ( stripos( $out, 'pdftk' ) !== false ) {
				return $bin;
			}
		}
		
		return '';
	}
	
	private function run_command_capture( $command, $suppress_throw = false ) {
		$disabled = (string) ini_get( 'disable_functions' );
		$disabled_list = array_filter( array_map( 'trim', explode( ',', $disabled ) ) );
		
		$can_shell_exec = function_exists( 'shell_exec' ) && ! in_array( 'shell_exec', $disabled_list, true );
		$can_proc_open = function_exists( 'proc_open' ) && ! in_array( 'proc_open', $disabled_list, true );
		error_log( 'run_command_capture: can_proc_open=' . ( $can_proc_open ? '1' : '0' ) . ' can_shell_exec=' . ( $can_shell_exec ? '1' : '0' ) );
		
		if ( $can_proc_open ) {
			$descriptorspec = array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			);
			$process = @proc_open( $command, $descriptorspec, $pipes );
			if ( is_resource( $process ) ) {
				fclose( $pipes[0] );
				$stdout = stream_get_contents( $pipes[1] );
				$stderr = stream_get_contents( $pipes[2] );
				fclose( $pipes[1] );
				fclose( $pipes[2] );
				$exit_code = proc_close( $process );
				return array(
					'output' => (string) $stdout . (string) $stderr,
					'exit_code' => (int) $exit_code,
				);
			}
		}
		
		if ( $can_shell_exec ) {
			$out = shell_exec( $command );
			$out = is_string( $out ) ? $out : '';
			// shell_exec doesn't reliably provide an exit code.
			return array(
				'output' => $out,
				'exit_code' => -1,
			);
		}
		
		if ( $suppress_throw ) {
			return array(
				'output' => '',
				'exit_code' => 127,
			);
		}
		
		throw new Exception( 'Server is not allowed to execute shell commands (shell_exec/proc_open disabled). pdftk cannot run.' );
	}
	
	/**
	 * Create FDF content for the real government PDF
	 */
	private function create_fdf_content( $form_data ) {
		// pdftk is picky about FDF syntax. This structure mirrors `pdftk <pdf> generate_fdf` output.
		$fdf = "%FDF-1.2\n%\xe2\xe3\xcf\xd3\n1 0 obj \n<<\n/FDF \n<<\n/Fields [\n";
		
		$checkbox_fields = array(
			'IndividualSoleProprietor',
			'CCorporation',
			'SCorporation',
			'Partnership',
			'TrustEstate',
			'LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership1',
			'OtherSeeInstructions1',
			'3bIfOnLine3aYouCheckedPartnershipOrTrustEstateOrCheckedLlcAndEnteredPAsItsTaxClassificationAndYouAreProvidingThisFormToAPartnershipTrustOrEstateInWhichYouHaveAnOwnershipInterestCheckThisBoxIfYouHaveAnyForeignPartnersOwnersOrBeneficiariesSeeInstructions',
		);
		
		// Always include every known field (even if empty), matching the template field list.
		$fields = array(
			'PrintOrTypeSeeSpecificInstructionsOnPage3' => $form_data['PrintOrTypeSeeSpecificInstructionsOnPage3'] ?? '',
			'BusinessNameDisregardedEntityNameIfDifferentFromAbove' => $form_data['BusinessNameDisregardedEntityNameIfDifferentFromAbove'] ?? '',
			'5' => $form_data['5'] ?? '',
			'CityStateAndZipCode' => $form_data['CityStateAndZipCode'] ?? '',
			'RequesterSNameAndAddressOptional' => $form_data['RequesterSNameAndAddressOptional'] ?? '',
			'ListAccountNumberSHereOptional' => $form_data['ListAccountNumberSHereOptional'] ?? '',
			'SocialSecurityNumber' => $form_data['SocialSecurityNumber'] ?? '',
			'VENDOR_SSN_MIDDLE2' => $form_data['VENDOR_SSN_MIDDLE2'] ?? '',
			'VENDOR_SSN_LAST4' => $form_data['VENDOR_SSN_LAST4'] ?? '',
			'EmployerIdentificationNumber' => $form_data['EmployerIdentificationNumber'] ?? '',
			'VENDOR_EIN_LAST7' => $form_data['VENDOR_EIN_LAST7'] ?? '',
			'ExemptPayeeCodeIfAny' => $form_data['ExemptPayeeCodeIfAny'] ?? '',
			'ExemptionFromForeignAccountTaxComplianceActFatcaReportingCodeIfAny' => $form_data['ExemptionFromForeignAccountTaxComplianceActFatcaReportingCodeIfAny'] ?? '',
			'LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership2' => $form_data['LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership2'] ?? '',
			'OtherSeeInstructions2' => $form_data['OtherSeeInstructions2'] ?? '',
			'Date' => $form_data['Date'] ?? '',
			'SIGN_VENDOR' => $form_data['SIGN_VENDOR'] ?? '',
			'IndividualSoleProprietor' => $form_data['IndividualSoleProprietor'] ?? 'Off',
			'CCorporation' => $form_data['CCorporation'] ?? 'Off',
			'SCorporation' => $form_data['SCorporation'] ?? 'Off',
			'Partnership' => $form_data['Partnership'] ?? 'Off',
			'TrustEstate' => $form_data['TrustEstate'] ?? 'Off',
			'LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership1' => $form_data['LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership1'] ?? 'Off',
			'OtherSeeInstructions1' => $form_data['OtherSeeInstructions1'] ?? 'Off',
			'3bIfOnLine3aYouCheckedPartnershipOrTrustEstateOrCheckedLlcAndEnteredPAsItsTaxClassificationAndYouAreProvidingThisFormToAPartnershipTrustOrEstateInWhichYouHaveAnOwnershipInterestCheckThisBoxIfYouHaveAnyForeignPartnersOwnersOrBeneficiariesSeeInstructions' => $form_data['3bIfOnLine3aYouCheckedPartnershipOrTrustEstateOrCheckedLlcAndEnteredPAsItsTaxClassificationAndYouAreProvidingThisFormToAPartnershipTrustOrEstateInWhichYouHaveAnOwnershipInterestCheckThisBoxIfYouHaveAnyForeignPartnersOwnersOrBeneficiariesSeeInstructions'] ?? 'Off',
		);
		
		foreach ( $fields as $field_name => $field_value ) {
			$fdf .= "<<\n";
			if ( in_array( $field_name, $checkbox_fields, true ) ) {
				// For checkbox/button fields, pdftk expects a *name object* value, e.g. `/V /Yes` or `/V /Off`.
				$normalized = ( $field_value === 'Yes' ) ? 'Yes' : 'Off';
				$fdf .= "/V /{$normalized}\n";
			} else {
				$escaped_value = str_replace( array( '\\', '(', ')', "\n", "\r" ), array( '\\\\', '\\(', '\\)', '\\n', '\\r' ), (string) $field_value );
				$fdf .= "/V ({$escaped_value})\n";
			}
			$fdf .= "/T ({$field_name})\n>> \n";
		}
		
		$fdf .= "]\n>>\n>>\nendobj \ntrailer\n\n<<\n/Root 1 0 R\n>>\n%%EOF\n";
		
		return $fdf;
	}
	
	/**
	 * Generate filled W-9 PDF using JavaScript (TCPDF removed)
	 * This method is deprecated and no longer functional since TCPDF was removed
	 */
	private function generate_filled_w9_pdf( $form_data ) {
		// TCPDF library has been removed to reduce plugin size
		// PDF generation is now handled client-side using pdf-lib.js
		throw new Exception( 'Server-side PDF generation is no longer available. Please use the client-side PDF generation.' );
	}
	
	/**
	 * Draw the W-9 form content with filled data (TCPDF removed)
	 * This method is deprecated and no longer functional since TCPDF was removed
	 */
	private function draw_w9_form_content( $pdf, $form_data ) {
		// TCPDF library has been removed to reduce plugin size
		// PDF generation is now handled client-side using pdf-lib.js
		throw new Exception( 'Server-side PDF generation is no longer available. Please use the client-side PDF generation.' );
	}
	
	/**
	 * Try to generate PDF using pdftk
	 */
	private function try_pdftk_generation( $form_data ) {
		try {
			// Generate XFDF file from form data
			$fdf_content = $this->generate_fdf_content( $form_data );
			error_log('Generated XFDF content length: ' . strlen($fdf_content));
			
			// Use WordPress uploads directory for temporary files
			$upload_dir = wp_upload_dir();
			$temp_dir = $upload_dir['basedir'] . '/w9-temp/';
			if ( ! file_exists( $temp_dir ) ) {
				wp_mkdir_p( $temp_dir );
			}
			
			// Save XFDF to temporary file
			$fdf_file = $temp_dir . 'w9_form_' . uniqid() . '.xfdf';
			file_put_contents( $fdf_file, $fdf_content );
			error_log('XFDF file saved to: ' . $fdf_file);
			
			// Get PDF template path
			$pdf_template = w91099ch_PLUGIN_PATH . 'assets/pdf/w9-govt-form.pdf';
			if ( ! file_exists( $pdf_template ) ) {
				error_log('PDF template not found: ' . $pdf_template);
				return null;
			}
			
			// Generate output PDF path
			$output_pdf = $temp_dir . 'w9_filled_' . uniqid() . '.pdf';
			
			// Execute pdftk command
			$command = sprintf(
				'pdftk "%s" fill_form "%s" output "%s" flatten 2>&1',
				escapeshellarg( $pdf_template ),
				escapeshellarg( $fdf_file ),
				escapeshellarg( $output_pdf )
			);
			
			error_log('Executing pdftk command: ' . $command);
			$shell_output = shell_exec( $command );
			error_log('pdftk output: ' . $shell_output);
			
			// Clean up XFDF file
			unlink( $fdf_file );
			
			// Check if output file was created and has content
			if ( file_exists( $output_pdf ) && filesize( $output_pdf ) > 0 ) {
				$pdf_content = file_get_contents( $output_pdf );
				unlink( $output_pdf );
				error_log('pdftk generation successful, PDF size: ' . strlen($pdf_content));
				return $pdf_content;
			} else {
				error_log('pdftk generation failed');
				return null;
			}
			
		} catch ( Exception $e ) {
			error_log('pdftk generation exception: ' . $e->getMessage());
			return null;
		}
	}
	
	/**
	 * Generate simple PDF with form data as fallback
	 */
	private function generate_simple_pdf_with_data( $form_data ) {
		// Create a simple PDF with the form data
		$pdf_content = '%PDF-1.4
1 0 obj
<<
/Type /Catalog
/Pages 2 0 R
>>
endobj

2 0 obj
<<
/Type /Pages
/Kids [3 0 R]
/Count 1
>>
endobj

3 0 obj
<<
/Type /Page
/Parent 2 0 R
/MediaBox [0 0 612 792]
/Contents 4 0 R
/Resources <<
/Font <<
/F1 5 0 R
>>
>>
>>
endobj

4 0 obj
<<
/Length 200
>>
stream
BT
/F1 12 Tf
72 720 Td
(W-9 Form Data) Tj
0 -20 Td
(Name: ' . ($form_data['PrintOrTypeSeeSpecificInstructionsOnPage3'] ?? 'N/A') . ') Tj
0 -20 Td
(Business: ' . ($form_data['BusinessNameDisregardedEntityNameIfDifferentFromAbove'] ?? 'N/A') . ') Tj
0 -20 Td
(Address: ' . ($form_data['5'] ?? 'N/A') . ') Tj
0 -20 Td
(Location: ' . ($form_data['CityStateAndZipCode'] ?? 'N/A') . ') Tj
0 -20 Td
(TIN: ' . ($form_data['SocialSecurityNumber'] ?? $form_data['EmployerIdentificationNumber'] ?? 'N/A') . ') Tj
0 -20 Td
(Date: ' . ($form_data['Date'] ?? 'N/A') . ') Tj
ET
endstream
endobj

5 0 obj
<<
/Type /Font
/Subtype /Type1
/BaseFont /Helvetica
>>
endobj

xref
0 6
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
0000000254 00000 n 
0000000489 00000 n 
trailer
<<
/Size 6
/Root 1 0 R
>>
startxref
556
%%EOF';
		
		error_log('Generated simple PDF with form data as fallback');
		return $pdf_content;
	}
	
	/**
	 * Get form data from POST request and map to PDF fields
	 */
	private function get_form_data() {
		$classification = $_POST['federal_tax_classification'] ?? '';
		$tin_type = strtolower( sanitize_text_field( $_POST['tin_type'] ?? '' ) );
		$tin_is_ssn_like = in_array( $tin_type, array( 'ssn', 'itin', 'atin', 'atn', 'ain' ), true );
		
		return array(
			// Basic information fields
			'PrintOrTypeSeeSpecificInstructionsOnPage3' => sanitize_text_field( $_POST['name'] ?? '' ),
			'BusinessNameDisregardedEntityNameIfDifferentFromAbove' => sanitize_text_field( $_POST['business_name'] ?? '' ),
			'5' => sanitize_text_field( $_POST['address'] ?? '' ),
			'CityStateAndZipCode' => sanitize_text_field( ($_POST['city'] ?? '') . ', ' . ($_POST['state'] ?? '') . ' ' . ($_POST['zip'] ?? '') ),
			
			// Requester and account information
			'RequesterSNameAndAddressOptional' => sanitize_text_field( $_POST['requester'] ?? '' ),
			'ListAccountNumberSHereOptional' => sanitize_text_field( $_POST['account_numbers'] ?? '' ),
			
			// TIN fields with proper splitting
			'SocialSecurityNumber' => sanitize_text_field( $tin_is_ssn_like ? ( $_POST['tin'] ?? '' ) : '' ),
			'VENDOR_SSN_MIDDLE2' => sanitize_text_field( $tin_is_ssn_like ? substr( (string) ( $_POST['tin'] ?? '' ), 3, 2 ) : '' ),
			'VENDOR_SSN_LAST4' => sanitize_text_field( $tin_is_ssn_like ? substr( (string) ( $_POST['tin'] ?? '' ), 5, 4 ) : '' ),
			'EmployerIdentificationNumber' => sanitize_text_field( (($_POST['tin_type'] ?? '') === 'fein') ? ($_POST['tin'] ?? '') : '' ),
			'VENDOR_EIN_LAST7' => sanitize_text_field( (($_POST['tin_type'] ?? '') === 'fein') ? substr($_POST['tin'] ?? '', -7) : '' ),
			
			// Federal tax classification checkboxes
			'IndividualSoleProprietor' => ($classification === 'individual') ? 'Yes' : 'Off',
			'CCorporation' => ($classification === 'c_corp') ? 'Yes' : 'Off',
			'SCorporation' => ($classification === 's_corp') ? 'Yes' : 'Off',
			'Partnership' => ($classification === 'partnership') ? 'Yes' : 'Off',
			'TrustEstate' => ($classification === 'trust') ? 'Yes' : 'Off',
			'LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership1' => ($classification === 'llc') ? 'Yes' : 'Off',
			
			// Additional fields
			'ExemptPayeeCodeIfAny' => sanitize_text_field( $_POST['exempt_payee_code'] ?? '' ),
			'ExemptionFromForeignAccountTaxComplianceActFatcaReportingCodeIfAny' => sanitize_text_field( $_POST['fatca_code'] ?? '' ),
			'LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership2' => sanitize_text_field( $_POST['llc_classification'] ?? '' ),
			'OtherSeeInstructions2' => sanitize_text_field( $_POST['other_description'] ?? '' ),
			'OtherSeeInstructions1' => ($classification === 'other') ? 'Yes' : 'Off',
			
			// Certification fields
			'Date' => sanitize_text_field( $_POST['certification_date'] ?? '' ),
			'SIGN_VENDOR' => sanitize_text_field( $_POST['certification_name'] ?? 'Signed' ),
			
			// Foreign partners checkbox (default to Off)
			'3bIfOnLine3aYouCheckedPartnershipOrTrustEstateOrCheckedLlcAndEnteredPAsItsTaxClassificationAndYouAreProvidingThisFormToAPartnershipTrustOrEstateInWhichYouHaveAnOwnershipInterestCheckThisBoxIfYouHaveAnyForeignPartnersOwnersOrBeneficiariesSeeInstructions' => 'Off',
		);
	}
	
	/**
	 * Generate XFDF content from form data (more reliable than FDF)
	 */
	private function generate_fdf_content( $form_data ) {
		$xfdf_content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xfdf_content .= '<xfdf xmlns="http://ns.adobe.com/xfdf/" xml:space="preserve">' . "\n";
		$xfdf_content .= '<fields>' . "\n";
		
		// Add text fields
		foreach ( $form_data as $field_name => $field_value ) {
			if ( ! empty( $field_value ) ) {
				$xfdf_content .= $this->xfdf_field( $field_name, $field_value );
			}
		}
		
		// Add checkbox fields based on federal tax classification
		$classification = $_POST['federal_tax_classification'] ?? '';
		$checkbox_fields = array(
			'IndividualSoleProprietor' => ($classification === 'individual'),
			'CCorporation' => ($classification === 'c_corp'),
			'SCorporation' => ($classification === 's_corp'),
			'Partnership' => ($classification === 'partnership'),
			'TrustEstate' => ($classification === 'trust'),
			'LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership1' => ($classification === 'llc'),
		);
		
		foreach ( $checkbox_fields as $field_name => $is_checked ) {
			$value = $is_checked ? 'Yes' : 'Off';
			$xfdf_content .= $this->xfdf_field( $field_name, $value );
		}
		
		$xfdf_content .= '</fields>' . "\n";
		$xfdf_content .= '</xfdf>';
		
		return $xfdf_content;
	}
	
	/**
	 * Generate XFDF field definition
	 */
	private function xfdf_field( $name, $value ) {
		// Escape special characters for XML
		$escaped_value = htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
		$escaped_name = htmlspecialchars( $name, ENT_QUOTES, 'UTF-8' );
		return '<field name="' . $escaped_name . '"><value>' . $escaped_value . '</value></field>' . "\n";
	}

	/**
	 * Add the AJAX handler to WordPress
	 */
	public function add_ajax_handlers() {
		if ( ! has_action( 'wp_ajax_w91099ch_generate_w9_pdf', array( $this, 'handle_w9_form_submission' ) ) ) {
			add_action( 'wp_ajax_w91099ch_generate_w9_pdf', array( $this, 'handle_w9_form_submission' ) );
		}
		if ( ! has_action( 'wp_ajax_w91099ch_generate_govt_pdf', array( $this, 'handle_govt_form_submission' ) ) ) {
			add_action( 'wp_ajax_w91099ch_generate_govt_pdf', array( $this, 'handle_govt_form_submission' ) );
		}
	}

	private $core;
	private $w91099ch_w9_mode_prefill = '';

	public function __construct( $core ) {
		$this->core = $core;
		add_action( 'current_screen', array( $this, 'maybe_load_support_ui' ) );
	}

	public function maybe_load_support_ui() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! isset( $screen->id ) ) {
			return;
		}

		$screen_id = (string) $screen->id;
		if ( strpos( $screen_id, 'w91099ch' ) === false && strpos( $screen_id, 'w9-1099-chaser' ) === false ) {
			return;
		}

		if ( defined( 'w91099ch_PLUGIN_PATH' ) ) {
			$support_file = trailingslashit( w91099ch_PLUGIN_PATH ) . 'admin/support-direct.php';
			if ( file_exists( $support_file ) ) {
				require_once $support_file;
			}
		}
	}

	public function init() {
		// Admin hooks (these only fire in admin)
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_menu', array( $this, 'hide_legacy_submenus' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
		add_action( 'wp_ajax_w91099ch_submit_deactivation_feedback', array( $this, 'ajax_submit_deactivation_feedback' ) );
		add_action( 'load-post-new.php', array( $this, 'maybe_setup_w9_page_prefill' ) );
		add_action( 'load-edit.php', array( $this, 'maybe_setup_w9_pages_list_guide' ) );

		// Admin bar hook (shows on all admin pages)
		add_action( 'wp_before_admin_bar_render', array( $this, 'add_admin_bar_tools' ) );

		add_action( 'admin_post_w91099ch_accept_consent', array( $this, 'handle_accept_consent' ) );
		add_action( 'admin_post_w91099ch_revoke_consent', array( $this, 'handle_revoke_consent' ) );

		add_action( 'wp_ajax_w91099ch_set_admin_consent', array( $this, 'ajax_set_admin_consent' ) );

		// AJAX
		add_action( 'wp_ajax_w91099ch_submit_feedback', array( $this, 'ajax_submit_feedback' ) );
		add_action( 'wp_ajax_w91099ch_get_decrypted_credentials', array( $this, 'ajax_get_decrypted_credentials' ) );
		add_action( 'wp_ajax_w91099ch_create_w9_page', array( $this, 'ajax_create_w9_page' ) );
		add_action( 'wp_ajax_w91099ch_get_all_users', array( $this, 'ajax_get_all_users' ) );
		add_action( 'wp_ajax_w91099ch_get_default_page_url', array( $this, 'ajax_get_default_page_url' ) );
		add_action( 'wp_ajax_w91099ch_newsletter_subscribe', array( $this, 'ajax_newsletter_subscribe' ) );
		$this->add_ajax_handlers();

		// Initialize update checker for admin
		if ( class_exists( 'w91099ch_Update_Checker' ) ) {
			w91099ch_Update_Checker::get_instance();
		}
	}

	public function maybe_setup_w9_pages_list_guide() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! isset( $screen->id ) || 'edit-page' !== (string) $screen->id ) {
			return;
		}

		$mode_raw = filter_input( INPUT_GET, 'w91099ch_w9_mode', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$mode     = is_string( $mode_raw ) ? sanitize_key( wp_unslash( $mode_raw ) ) : '';
		if ( ! in_array( $mode, array( 'public', 'private', 'protected' ), true ) ) {
			return;
		}

		$this->w91099ch_w9_mode_prefill = $mode;
		add_action( 'admin_notices', array( $this, 'w91099ch_w9_pages_list_mode_admin_notice' ) );
	}

	public function maybe_setup_w9_page_prefill() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! isset( $screen->base ) || 'post' !== (string) $screen->base ) {
			return;
		}

		$post_type_raw = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$post_type     = is_string( $post_type_raw ) ? sanitize_key( wp_unslash( $post_type_raw ) ) : '';
		if ( 'page' !== $post_type ) {
			return;
		}

		$mode_raw = filter_input( INPUT_GET, 'w91099ch_w9_mode', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$mode     = is_string( $mode_raw ) ? sanitize_key( wp_unslash( $mode_raw ) ) : '';
		if ( ! in_array( $mode, array( 'public', 'private', 'protected' ), true ) ) {
			return;
		}

		$this->w91099ch_w9_mode_prefill = $mode;
		add_filter( 'default_title', array( $this, 'w91099ch_default_w9_page_title' ), 10, 2 );
		add_filter( 'default_content', array( $this, 'w91099ch_default_w9_page_content' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'w91099ch_w9_page_mode_admin_notice' ) );
	}

	public function w91099ch_default_w9_page_title( $title, $post ) {
		if ( ! $post || ! isset( $post->post_type ) || 'page' !== (string) $post->post_type ) {
			return $title;
		}
		if ( '' === $this->w91099ch_w9_mode_prefill ) {
			return $title;
		}
		if ( is_string( $title ) && '' !== trim( $title ) ) {
			return $title;
		}

		$label = 'Public';
		if ( 'private' === $this->w91099ch_w9_mode_prefill ) {
			$label = 'Private';
		} elseif ( 'protected' === $this->w91099ch_w9_mode_prefill ) {
			$label = 'Password Protected';
		}

		return 'W-9 Form (' . $label . ')';
	}

	public function w91099ch_default_w9_page_content( $content, $post ) {
		if ( ! $post || ! isset( $post->post_type ) || 'page' !== (string) $post->post_type ) {
			return $content;
		}
		if ( '' === $this->w91099ch_w9_mode_prefill ) {
			return $content;
		}
		if ( is_string( $content ) && '' !== trim( $content ) ) {
			return $content;
		}

		return '[w91099ch_w9_form]';
	}

	public function w91099ch_w9_page_mode_admin_notice() {
		if ( '' === $this->w91099ch_w9_mode_prefill ) {
			return;
		}

		$mode_label = 'Public';
		$mode_note  = 'Publish the page (Visibility: Public).';
		if ( 'private' === $this->w91099ch_w9_mode_prefill ) {
			$mode_label = 'Private';
			$mode_note  = 'Set Visibility to Private (only logged-in admins/editors can view it).';
		} elseif ( 'protected' === $this->w91099ch_w9_mode_prefill ) {
			$mode_label = 'Password protected';
			$mode_note  = 'Set Visibility to Password protected and choose a password.';
		}

		echo '<div class="notice notice-info is-dismissible"><p><strong>W-9 Page Mode:</strong> ' . esc_html( $mode_label ) . '. The shortcode has been pre-filled. ' . esc_html( $mode_note ) . '</p></div>';
	}

	public function w91099ch_w9_pages_list_mode_admin_notice() {
		if ( '' === $this->w91099ch_w9_mode_prefill ) {
			return;
		}

		$mode_label = 'Public';
		$step_1     = 'Click “Add New Page” (top) or open an existing page.';
		$step_2     = 'Paste the shortcode into the page content: [w91099ch_w9_form]';
		$step_3     = 'Publish the page (Visibility: Public).';
		if ( 'private' === $this->w91099ch_w9_mode_prefill ) {
			$mode_label = 'Private';
			$step_3     = 'Set Visibility to Private (Quick Edit or inside the editor), then Update/Publish.';
		} elseif ( 'protected' === $this->w91099ch_w9_mode_prefill ) {
			$mode_label = 'Password protected';
			$step_3     = 'Set Visibility to Password protected and set a password (Quick Edit or inside the editor), then Update/Publish.';
		}

		$new_page_url = admin_url( 'post-new.php?post_type=page&w91099ch_w9_mode=' . $this->w91099ch_w9_mode_prefill );
		$new_page_url = esc_url( $new_page_url );

		echo '<div class="notice notice-info is-dismissible">';
		echo '<p><strong>W-9 Page Setup (' . esc_html( $mode_label ) . '):</strong></p>';
		echo '<ol style="margin: 0.5em 0 0.75em 1.25em;">';
		echo '<li>' . esc_html( $step_1 ) . '</li>';
		echo '<li>' . esc_html( $step_2 ) . '</li>';
		echo '<li>' . esc_html( $step_3 ) . '</li>';
		echo '</ol>';
		echo '<p><a class="button button-primary" href="' . $new_page_url . '">Create New W-9 Page (Prefilled)</a></p>';
		echo '</div>';
	}

	public function ajax_newsletter_subscribe() {
		$nonce_raw = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$nonce     = is_string( $nonce_raw ) ? sanitize_text_field( wp_unslash( $nonce_raw ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'w91099ch_newsletter_subscribe' ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$email_raw = filter_input( INPUT_POST, 'email', FILTER_SANITIZE_EMAIL );
		$email     = is_string( $email_raw ) ? sanitize_email( wp_unslash( $email_raw ) ) : '';
		if ( '' === $email ) {
			wp_send_json_error( esc_html__( 'Please enter an email address', 'w9-1099-chaser' ) );
		}
		if ( ! is_email( $email ) ) {
			wp_send_json_error( esc_html__( 'Invalid email address', 'w9-1099-chaser' ) );
		}

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$site_url  = home_url();
		$user      = wp_get_current_user();
		$user_info = ( $user && $user instanceof WP_User ) ? sprintf( '%1$s (ID %2$d)', (string) $user->user_login, (int) $user->ID ) : '';

		$subject = sprintf( '[%s] Newsletter subscription unlock', $site_name );
		$body    = "Newsletter subscription requested to unlock W-9 Display Settings.\n\n";
		$body   .= 'Email: ' . $email . "\n";
		if ( '' !== $user_info ) {
			$body .= 'User: ' . $user_info . "\n";
		}
		$body .= 'Site: ' . $site_url . "\n";
		$body .= 'Time (UTC): ' . gmdate( 'Y-m-d H:i:s' ) . "\n";

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$headers[] = 'Reply-To: ' . $email;

		$sent = wp_mail( '1099automation@gmail.com', $subject, $body, $headers );
		if ( ! $sent ) {
			error_log( 'w91099ch: Newsletter subscribe wp_mail failed for email: ' . $email );
		}

		update_option( 'w91099ch_newsletter_subscribed', true );
		update_option( 'w91099ch_newsletter_email', $email );
		wp_send_json_success(
			array(
				'subscribed' => true,
				'mail_sent'  => (bool) $sent,
			)
		);
	}

	public function ajax_create_w9_page() {
		if ( ! check_ajax_referer( 'w91099ch_create_w9_page', 'nonce', false ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$page_id = absint( get_option( 'w91099ch_w9_page_id', 0 ) );
		if ( $page_id > 0 ) {
			$post = get_post( $page_id );
			if ( $post && (string) $post->post_type === 'page' ) {
				$edit_link = get_edit_post_link( $page_id, 'raw' );
				if ( $edit_link ) {
					wp_send_json_success( array( 'edit_link' => esc_url_raw( $edit_link ) ) );
				}
			}
		}

		$title   = esc_html__( 'W-9 Form', 'w9-1099-chaser' );
		$content = '[w91099ch_w9_form]';

		$new_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => $title,
				'post_content' => $content,
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( esc_html__( 'Failed to create page', 'w9-1099-chaser' ) );
		}

		$page_id = absint( $new_id );
		if ( $page_id <= 0 ) {
			wp_send_json_error( esc_html__( 'Failed to create page', 'w9-1099-chaser' ) );
		}

		update_option( 'w91099ch_w9_page_id', $page_id );

		$edit_link = get_edit_post_link( $page_id, 'raw' );
		if ( ! $edit_link ) {
			wp_send_json_error( esc_html__( 'Failed to create page', 'w9-1099-chaser' ) );
		}

		wp_send_json_success( array( 'edit_link' => esc_url_raw( $edit_link ) ) );
	}

	public function ajax_get_all_users() {
		if ( ! check_ajax_referer( 'w91099ch_nonce', 'nonce', false ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$limit_raw  = filter_input( INPUT_POST, 'limit', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$limit      = is_string( $limit_raw ) ? absint( wp_unslash( $limit_raw ) ) : 20;
		$offset_raw = filter_input( INPUT_POST, 'offset', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$offset     = is_string( $offset_raw ) ? absint( wp_unslash( $offset_raw ) ) : 0;

		if ( $limit <= 0 ) {
			$limit = 20;
		}
		if ( $offset < 0 ) {
			$offset = 0;
		}

		$allowed_roles = array( 'administrator', 'shop_manager', 'contributor', 'author', 'editor' );

		try {
			$query = new WP_User_Query(
				array(
					'number'      => $limit,
					'offset'      => $offset,
					'orderby'     => 'registered',
					'order'       => 'DESC',
					'fields'      => array( 'ID', 'user_login', 'user_email', 'display_name' ),
					'role__in'    => $allowed_roles,
					'count_total' => true,
				)
			);

			$users = $query->get_results();

			$formatted = array();
			foreach ( $users as $user ) {
				$wp_user = get_userdata( $user->ID );
				$role    = '';
				if ( $wp_user && ! empty( $wp_user->roles ) && is_array( $wp_user->roles ) ) {
					$role = (string) reset( $wp_user->roles );
				}

				if ( $role && ! in_array( $role, $allowed_roles, true ) ) {
					continue;
				}

				$formatted[] = array(
					'id'           => $user->ID,
					'username'     => sanitize_text_field( (string) $user->user_login ),
					'display_name' => sanitize_text_field( (string) $user->display_name ),
					'email'        => sanitize_email( (string) $user->user_email ),
					'role'         => sanitize_key( (string) $role ),
				);
			}

			$total = (int) $query->get_total();
			if ( $total <= 0 ) {
				$total = count( $formatted );
			}

			wp_send_json_success(
				array(
					'users' => $formatted,
					'total' => $total,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				esc_html__( 'Failed to load users: ', 'w9-1099-chaser' ) . sanitize_text_field( $e->getMessage() )
			);
		}
	}

	/**
	 * AJAX handler for submitting feedback
	 */
	public function ajax_submit_feedback() {
		check_ajax_referer( 'w91099ch_feedback_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) ) );
		}

		$rating = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$website_url = home_url();
		$plugin_name = 'W9-1099 Chaser';

		if ( $rating < 1 || $rating > 5 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please provide a star rating.', 'w9-1099-chaser' ) ) );
		}

		$to      = '1099automation@gmail.com';
		$subject = sprintf( 'Experience Feedback: %s (%d Stars)', $plugin_name, $rating );
		
		$admin_email = sanitize_email( (string) get_option( 'admin_email' ) );
		$site_name   = get_bloginfo( 'name' );
		$wp_version  = get_bloginfo( 'version' );

		// Professional HTML Email Format for Experience Feedback
		$html_body = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
		$html_body .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e1e1; border-radius: 8px; background-color: #f9f9f9;">';
		$html_body .= '<h2 style="color: #2563eb; border-bottom: 2px solid #2563eb; padding-bottom: 10px;">New Experience Feedback</h2>';
		$html_body .= '<p>You have received new feedback for <strong>' . esc_html( $plugin_name ) . '</strong>. Here are the details:</p>';
		
		$html_body .= '<div style="background: #fff; padding: 15px; border-radius: 6px; border: 1px solid #ddd; margin-bottom: 20px; text-align: center;">';
		$html_body .= '<div style="font-size: 14px; color: #666; margin-bottom: 5px;">Rating Given</div>';
		$stars_html = str_repeat( '<span style="color: #f59e0b; font-size: 24px;">★</span>', $rating ) . str_repeat( '<span style="color: #d1d5db; font-size: 24px;">★</span>', 5 - $rating );
		$html_body .= '<div>' . $stars_html . ' (' . $rating . '/5)</div>';
		$html_body .= '</div>';

		$html_body .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
		if ( ! empty( $message ) ) {
			$html_body .= '<tr><td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; vertical-align: top; width: 30%;">Message:</td><td style="padding: 10px; border-bottom: 1px solid #eee;">' . nl2br( esc_html( $message ) ) . '</td></tr>';
		}
		$html_body .= '<tr><td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">Site Name:</td><td style="padding: 10px; border-bottom: 1px solid #eee;">' . esc_html( $site_name ) . '</td></tr>';
		$html_body .= '<tr><td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">Site URL:</td><td style="padding: 10px; border-bottom: 1px solid #eee;"><a href="' . esc_url( $website_url ) . '">' . esc_html( $website_url ) . '</a></td></tr>';
		$html_body .= '<tr><td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">Admin Email:</td><td style="padding: 10px; border-bottom: 1px solid #eee;">' . esc_html( $admin_email ) . '</td></tr>';
		$html_body .= '<tr><td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">WP Version:</td><td style="padding: 10px; border-bottom: 1px solid #eee;">' . esc_html( $wp_version ) . '</td></tr>';
		$html_body .= '<tr><td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">Date:</td><td style="padding: 10px; border-bottom: 1px solid #eee;">' . esc_html( current_time( 'mysql' ) ) . '</td></tr>';
		$html_body .= '</table>';
		
		$html_body .= '<p style="font-size: 12px; color: #777; text-align: center; margin-top: 30px;">This feedback was submitted via the in-plugin feedback tab.</p>';
		$html_body .= '</div></body></html>';

		$from_name   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$sent = false;
		$err_msg = '';
		try {
			if ( ! class_exists( '\\PHPMailer\\PHPMailer\\PHPMailer' ) ) {
				require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
				require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
				require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
			}

			$mailer = new \PHPMailer\PHPMailer\PHPMailer( true );
			$mailer->isMail();
			$mailer->CharSet = 'UTF-8';
			$mailer->Subject = $subject;
			$mailer->Body    = $html_body;
			$mailer->isHTML( true );
			$mailer->addAddress( $to );
			if ( $admin_email ) {
				$mailer->setFrom( $admin_email, $from_name, false );
				$mailer->addReplyTo( $admin_email, $from_name );
			}
			$sent = (bool) $mailer->send();
		} catch ( Exception $e ) {
			$err_msg = $e->getMessage();
		} catch ( \PHPMailer\PHPMailer\Exception $e ) {
			$err_msg = $e->getMessage();
		} catch ( \Throwable $e ) {
			$err_msg = $e->getMessage();
		}

		if ( $sent ) {
			wp_send_json_success( array( 'message' => esc_html__( 'Thank you for your feedback!', 'w9-1099-chaser' ) ) );
		} else {
			error_log( 'w91099ch: Feedback email failed. Site: ' . $website_url . ' | Rating: ' . (string) $rating . ( $err_msg ? ( ' | Error: ' . $err_msg ) : '' ) );

			$response = array( 'message' => esc_html__( 'Failed to send feedback. Please try again later.', 'w9-1099-chaser' ) );
			if ( $err_msg ) {
				$err_lower = strtolower( $err_msg );
				if (
					false !== strpos( $err_lower, 'gmail.googleapis.com' ) ||
					false !== strpos( $err_lower, 'googleapis.com' ) ||
					false !== strpos( $err_lower, 'unauthenticated' ) ||
					false !== strpos( $err_lower, 'login required' ) ||
					false !== strpos( $err_lower, 'oauth 2' )
				) {
					$response['message'] = esc_html__( 'Email service is not authenticated (WP Mail SMTP / Gmail). Please reconnect your mailer in WP Mail SMTP settings and try again.', 'w9-1099-chaser' );
				}
			}
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $err_msg ) {
				$response['debug'] = sanitize_text_field( $err_msg );
			}
			wp_send_json_error( $response );
		}
	}

	public function ajax_save_w9_display_settings() {
		if ( ! check_ajax_referer( 'w91099ch_w9_form_nonce', 'nonce', false ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$w9_form_enabled_raw = filter_input( INPUT_POST, 'w9_form_enabled', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( null === $w9_form_enabled_raw ) {
			$w9_form_enabled_raw = filter_input( INPUT_POST, 'w91099ch_w9_form_enabled', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}
		if ( null !== $w9_form_enabled_raw ) {
			update_option( 'w91099ch_w9_form_enabled', ( '1' === $w9_form_enabled_raw ) );
		}

		$display_method = filter_input( INPUT_POST, 'display_method', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! in_array( $display_method, array( 'all', 'selected', 'shortcode' ), true ) ) {
			wp_send_json_error( esc_html__( 'Invalid display method', 'w9-1099-chaser' ) );
		}

		// Save display method
		update_option( 'w91099ch_w9_display_method', $display_method );

		// Save display position
		$display_position = filter_input( INPUT_POST, 'display_position', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( in_array( $display_position, array( 'top', 'middle', 'bottom', 'floating' ), true ) ) {
			update_option( 'w91099ch_w9_display_position', $display_position );
		} else {
			update_option( 'w91099ch_w9_display_position', 'bottom' );
		}

		// Save floating settings if applicable
		if ( $display_position === 'floating' ) {
			$floating_settings_raw = filter_input( INPUT_POST, 'floating_settings', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			if ( is_array( $floating_settings_raw ) ) {
				$floating_settings = array(
					'widget_type'     => sanitize_text_field( $floating_settings_raw['widget_type'] ?? 'icon-button' ),
					'button_text'     => sanitize_text_field( $floating_settings_raw['button_text'] ?? 'W-9 Form' ),
					'screen_position' => sanitize_text_field( $floating_settings_raw['screen_position'] ?? 'bottom-right' ),
					'bg_color'        => sanitize_hex_color( $floating_settings_raw['bg_color'] ?? '#3b82f6' ),
				);
				update_option( 'w91099ch_w9_floating_settings', $floating_settings );
			}
		} else {
			update_option( 'w91099ch_w9_floating_settings', array() );
		}

		// Save selected pages if applicable
		if ( $display_method === 'selected' ) {
			$selected_pages = filter_input( INPUT_POST, 'selected_pages', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			if ( is_array( $selected_pages ) ) {
				$selected_pages = array_map( 'strval', array_map( 'absint', $selected_pages ) );
				update_option( 'w91099ch_w9_selected_pages', $selected_pages );
			} else {
				update_option( 'w91099ch_w9_selected_pages', array() );
			}

			// Save page positions
			$page_positions = filter_input( INPUT_POST, 'page_positions', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			if ( is_array( $page_positions ) ) {
				update_option( 'w91099ch_w9_page_positions', $page_positions );
			} else {
				update_option( 'w91099ch_w9_page_positions', array() );
			}
		} else {
			// Clear selected pages and positions if not using selected mode
			update_option( 'w91099ch_w9_selected_pages', array() );
			update_option( 'w91099ch_w9_page_positions', array() );
		}

		wp_send_json_success( array( 'message' => esc_html__( 'Display settings saved successfully!', 'w9-1099-chaser' ) ) );
	}

	/**
	 * AJAX handler to get default W-9 page URL
	 */
	public function ajax_get_default_page_url() {
		if ( ! check_ajax_referer( 'w91099ch_nonce', 'nonce', false ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$default_page_id = absint( get_option( 'w91099ch_w9_default_page_id', 0 ) );
		
		if ( $default_page_id > 0 ) {
			$url = get_permalink( $default_page_id );
			if ( $url ) {
				wp_send_json_success( array( 'url' => esc_url_raw( $url ) ) );
			}
		}

		wp_send_json_error( array( 'message' => esc_html__( 'No default page set', 'w9-1099-chaser' ) ) );
	}

	/**
	 * Add W-9 & 1099 Tools to WordPress admin bar
	 */
	public function add_admin_bar_tools() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wp_admin_bar;

		// Get default page URL
		$default_page_id = absint( get_option( 'w91099ch_w9_default_page_id', 0 ) );
		$default_page_url = $default_page_id > 0 ? get_permalink( $default_page_id ) : '';

		// Add parent menu item
		$wp_admin_bar->add_node(
			array(
				'id'    => 'w91099ch-tools',
				'title' => '<span class="ab-icon dashicons dashicons-admin-links"></span><span class="ab-label">' . esc_html__( 'W-9 & 1099 Tools', 'w9-1099-chaser' ) . '</span>',
				'href'  => '#',
				'meta'  => array(
					'title' => esc_html__( 'W-9 & 1099 Tools', 'w9-1099-chaser' ),
					'html'  => '<span data-default-page-url="' . esc_attr( $default_page_url ) . '"></span>',
				),
			)
		);

		// Add Copy Link submenu
		$wp_admin_bar->add_node(
			array(
				'parent' => 'w91099ch-tools',
				'id'     => 'w91099ch-copy-link',
				'title'  => '<span class="dashicons dashicons-admin-links" style="float:left;margin-right:5px;"></span>' . esc_html__( 'Copy link', 'w9-1099-chaser' ),
				'href'   => '#',
				'meta'   => array(
					'title' => esc_html__( 'Copy W-9 form link to clipboard', 'w9-1099-chaser' ),
					'class' => 'w91099ch-admin-bar-copy-link',
				),
			)
		);

		// Add Share via Email submenu
		$wp_admin_bar->add_node(
			array(
				'parent' => 'w91099ch-tools',
				'id'     => 'w91099ch-share-email',
				'title'  => '<span class="dashicons dashicons-email" style="float:left;margin-right:5px;"></span>' . esc_html__( 'Share to email', 'w9-1099-chaser' ),
				'href'   => '#',
				'meta'   => array(
					'title' => esc_html__( 'Share W-9 form link via email', 'w9-1099-chaser' ),
					'class' => 'w91099ch-admin-bar-share-email',
				),
			)
		);

		// Add QR Code submenu
		$wp_admin_bar->add_node(
			array(
				'parent' => 'w91099ch-tools',
				'id'     => 'w91099ch-qr-code',
				'title'  => '<span class="dashicons dashicons-smartphone" style="float:left;margin-right:5px;"></span>' . esc_html__( 'QR code', 'w9-1099-chaser' ),
				'href'   => '#',
				'meta'   => array(
					'title' => esc_html__( 'Generate QR code for W-9 form', 'w9-1099-chaser' ),
					'class' => 'w91099ch-admin-bar-qr-code',
				),
			)
		);

		// Add JavaScript for functionality
		?>
		<script>
			(function($) {
				// Get default page URL dynamically via AJAX
				function getDefaultPageUrl() {
					return $.ajax({
						url: ajaxurl,
						method: 'POST',
						data: {
							action: 'w91099ch_get_default_page_url',
							nonce: '<?php echo wp_create_nonce( 'w91099ch_nonce' ); ?>'
						}
					});
				}
				
				// Copy link functionality
				$(document).on("click", "#wp-admin-bar-w91099ch-copy-link a, .w91099ch-copy-link a", function(e) {
					e.preventDefault();
					e.stopPropagation();
					
					getDefaultPageUrl().done(function(response) {
						if (response.success && response.data.url) {
							var defaultUrl = response.data.url;
							
							if (navigator.clipboard && navigator.clipboard.writeText) {
								navigator.clipboard.writeText(defaultUrl).then(function() {
									alert("<?php echo esc_js( __( 'Success! Link copied to clipboard.', 'w9-1099-chaser' ) ); ?>");
								}).catch(function() {
									alert("<?php echo esc_js( __( 'Error: Could not copy the link.', 'w9-1099-chaser' ) ); ?>");
								});
							} else {
								// Fallback method
								var textArea = document.createElement("textarea");
								textArea.value = defaultUrl;
								textArea.style.position = "fixed";
								textArea.style.left = "-9999px";
								document.body.appendChild(textArea);
								textArea.focus();
								textArea.select();
								try {
									document.execCommand("copy");
									alert("<?php echo esc_js( __( 'Success! Link copied to clipboard.', 'w9-1099-chaser' ) ); ?>");
								} catch (err) {
									alert("<?php echo esc_js( __( 'Error: Could not copy the link.', 'w9-1099-chaser' ) ); ?>");
								}
								document.body.removeChild(textArea);
							}
						} else {
							alert("<?php echo esc_js( __( 'Action Required: Please set a default page in W-9 Display Settings first.', 'w9-1099-chaser' ) ); ?>");
						}
					}).fail(function() {
						alert("<?php echo esc_js( __( 'Network Error: Could not retrieve page URL.', 'w9-1099-chaser' ) ); ?>");
					});
				});
				
				// Share to email functionality
				$(document).on("click", "#wp-admin-bar-w91099ch-share-email a, .w91099ch-share-email a", function(e) {
					e.preventDefault();
					e.stopPropagation();
					
					getDefaultPageUrl().done(function(response) {
						if (response.success && response.data.url) {
							var defaultUrl = response.data.url;
							var subject = "<?php echo esc_js( __( 'W-9 Form Link', 'w9-1099-chaser' ) ); ?>";
							var body = "<?php echo esc_js( __( 'Hi,%0D%0A%0D%0AHere is the link to the W-9 form:%0D%0A', 'w9-1099-chaser' ) ); ?>" + encodeURIComponent(defaultUrl) + "<?php echo esc_js( __( '%0D%0A%0D%0AThanks', 'w9-1099-chaser' ) ); ?>";
							var gmailUrl = "https://mail.google.com/mail/?view=cm&fs=1&su=" + encodeURIComponent(subject) + "&body=" + body;
							window.open(gmailUrl, "_blank", "noopener");
						} else {
							alert("<?php echo esc_js( __( 'Action Required: Please set a default page in W-9 Display Settings first.', 'w9-1099-chaser' ) ); ?>");
						}
					}).fail(function() {
						alert("<?php echo esc_js( __( 'Network Error: Could not retrieve page URL.', 'w9-1099-chaser' ) ); ?>");
					});
				});
				
				// QR code functionality
				$(document).on("click", "#wp-admin-bar-w91099ch-qr-code a, .w91099ch-qr-code a", function(e) {
					e.preventDefault();
					e.stopPropagation();
					
					getDefaultPageUrl().done(function(response) {
						if (response.success && response.data.url) {
							var defaultUrl = response.data.url;
							
							// Create modal if it doesn't exist
							if (!$("#w91099ch-qr-modal").length) {
								var modalHtml = '<div id="w91099ch-qr-modal" style="position: fixed; inset: 0; z-index: 999999; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center;">' +
									'<div style="position: relative; max-width: 420px; margin: 10vh auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.25);">' +
										'<div style="padding: 18px 18px 0 18px; display:flex; align-items:center; justify-content: space-between; gap: 12px;">' +
											'<div style="font-weight: 800; color: #111827; font-size: 16px;"><?php echo esc_js( __( 'QR Code for W-9 Form', 'w9-1099-chaser' ) ); ?></div>' +
											'<button type="button" id="w91099ch-qr-close" style="border: 0; background: transparent; font-size: 22px; line-height: 1; padding: 6px 10px; cursor: pointer; color: #6b7280;">&times;</button>' +
										'</div>' +
										'<div style="padding: 18px;">' +
											'<div style="display:flex; flex-direction: column; align-items: center; gap: 12px;">' +
												'<img id="w91099ch_qr_img" alt="QR" style="width: 220px; height: 220px; border: 1px solid #e5e7eb; border-radius: 12px;" src="https://quickchart.io/qr?size=220&text=' + encodeURIComponent(defaultUrl) + '" />' +
												'<div style="word-break: break-all; color: #374151; font-size: 12px; text-align: center;">' + defaultUrl + '</div>' +
											'</div>' +
										'</div>' +
									'</div>' +
								'</div>';
								$("body").append(modalHtml);
							} else {
								$("#w91099ch_qr_img").attr("src", "https://quickchart.io/qr?size=220&text=" + encodeURIComponent(defaultUrl));
								$("#w91099ch-qr-modal").show();
							}
						} else {
							alert("<?php echo esc_js( __( 'Action Required: Please set a default page in W-9 Display Settings first.', 'w9-1099-chaser' ) ); ?>");
						}
					}).fail(function() {
						alert("<?php echo esc_js( __( 'Network Error: Could not retrieve page URL.', 'w9-1099-chaser' ) ); ?>");
					});
				});
				
				// Close QR modal
				$(document).on("click", "#w91099ch-qr-close, #w91099ch-qr-modal", function(e) {
					if (e.target.id === "w91099ch-qr-close" || e.target.id === "w91099ch-qr-modal") {
						$("#w91099ch-qr-modal").hide();
					}
				});
				
				// Close modal on escape key
				$(document).on("keydown", function(e) {
					if (e.key === "Escape") {
						$("#w91099ch-qr-modal").hide();
					}
				});
			})(jQuery);
		</script>
		<?php
	}

	public function add_admin_menu() {
		// Main menu item
		add_menu_page(
			esc_html__( 'Vendor Onboarding W9-1099 Chaser', 'w9-1099-chaser' ),
			esc_html__( 'Vendor Onboarding W9-1099 Chaser', 'w9-1099-chaser' ),
			'manage_options',
			'w91099ch',
			array( $this, 'render_advanced_features_page' ),
			'dashicons-admin-links',
			30
		);

		// Advanced Features submenu
		add_submenu_page(
			'w91099ch',
			esc_html__( 'Advanced Features', 'w9-1099-chaser' ),
			esc_html__( 'Advanced Features', 'w9-1099-chaser' ),
			'manage_options',
			'w91099ch',
			array( $this, 'render_advanced_features_page' )
		);

		// Dashboard submenu
		add_submenu_page(
			'w91099ch',
			esc_html__( 'Dashboard', 'w9-1099-chaser' ),
			esc_html__( 'Dashboard', 'w9-1099-chaser' ),
			'manage_options',
			'w91099ch-dashboard',
			array( $this, 'render_dashboard_page' )
		);

		// Legacy dashboard slug (backward compatibility)
		add_submenu_page(
			'w91099ch',
			esc_html__( 'Dashboard', 'w9-1099-chaser' ),
			esc_html__( 'Dashboard (Legacy)', 'w9-1099-chaser' ),
			'manage_options',
			'w9-1099-chaser',
			array( $this, 'render_dashboard_page' )
		);

		// Settings submenu
		add_submenu_page(
			'w91099ch',
			esc_html__( 'Settings', 'w9-1099-chaser' ),
			esc_html__( 'Settings', 'w9-1099-chaser' ),
			'manage_options',
			'w91099ch-settings',
			array( $this, 'render_settings_page' )
		);

		// Features submenu
		add_submenu_page(
			'w91099ch',
			esc_html__( 'Upgrade', 'w9-1099-chaser' ),
			esc_html__( 'Upgrade', 'w9-1099-chaser' ),
			'manage_options',
			'w91099ch-features',
			array( $this, 'render_features_page' )
		);

		// Legacy settings slug (backward compatibility)
		add_submenu_page(
			'w91099ch',
			esc_html__( 'Settings', 'w9-1099-chaser' ),
			esc_html__( 'Settings (Legacy)', 'w9-1099-chaser' ),
			'manage_options',
			'w9-1099-chaser-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function hide_legacy_submenus() {
		remove_submenu_page( 'w91099ch', 'w9-1099-chaser' );
		remove_submenu_page( 'w91099ch', 'w9-1099-chaser-settings' );
		remove_submenu_page( 'w91099ch', 'w9-1099-chaser-widget' );
	}

	public function render_advanced_features_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'w9-1099-chaser' ) );
		}

		$connection_error   = get_transient( 'w91099ch_connection_error' );
		$connection_success = get_transient( 'w91099ch_connection_success' );

		include_once w91099ch_PLUGIN_PATH . 'admin/views/advanced-features-page.php';

		if ( $connection_error ) {
			delete_transient( 'w91099ch_connection_error' );
		}
		if ( $connection_success ) {
			delete_transient( 'w91099ch_connection_success' );
		}
	}

	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'w9-1099-chaser' ) );
		}

		$connection_error   = get_transient( 'w91099ch_connection_error' );
		$connection_success = get_transient( 'w91099ch_connection_success' );

		include_once w91099ch_PLUGIN_PATH . 'admin/views/dashboard-page.php';

		if ( $connection_error ) {
			delete_transient( 'w91099ch_connection_error' );
		}
		if ( $connection_success ) {
			delete_transient( 'w91099ch_connection_success' );
		}
	}

	/**
	 * Handle deactivation feedback AJAX
	 */
	public function ajax_submit_deactivation_feedback() {
		check_ajax_referer( 'w91099ch_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden' );
		}

		$reason  = isset( $_POST['reason'] ) ? sanitize_text_field( $_POST['reason'] ) : 'unknown';
		$details = isset( $_POST['details'] ) ? sanitize_text_field( $_POST['details'] ) : '';
		
		$site_url    = get_site_url();
		$site_name   = get_bloginfo( 'name' );
		$admin_email = get_option( 'admin_email' );
		$wp_version  = get_bloginfo( 'version' );

		$to      = '1099automation@gmail.com';
		$subject = 'Plugin Deactivation Feedback: W9-1099 Chaser';
		
		// Professional HTML Email Format
		$message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
		$message .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e1e1; border-radius: 8px; background-color: #f9f9f9;">';
		$message .= '<h2 style="color: #4f46e5; border-bottom: 2px solid #4f46e5; padding-bottom: 10px;">Deactivation Feedback</h2>';
		$message .= '<p>A user has just deactivated <strong>W9-1099 Chaser</strong> on their website. Here are the details:</p>';
		
		$message .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
		$message .= '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold; width: 30%;">Reason:</td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html( str_replace( '_', ' ', ucfirst( $reason ) ) ) . '</td></tr>';
		
		if ( ! empty( $details ) ) {
			$label = ( $reason === 'found_better' ) ? 'Alternative Plugin:' : 'Additional Details:';
			$message .= '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">' . esc_html( $label ) . '</td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html( $details ) . '</td></tr>';
		}
		
		$message .= '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">Site Name:</td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html( $site_name ) . '</td></tr>';
		$message .= '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">Site URL:</td><td style="padding: 8px; border-bottom: 1px solid #ddd;"><a href="' . esc_url( $site_url ) . '">' . esc_html( $site_url ) . '</a></td></tr>';
		$message .= '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">Admin Email:</td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html( $admin_email ) . '</td></tr>';
		$message .= '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">WP Version:</td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html( $wp_version ) . '</td></tr>';
		$message .= '<tr><td style="padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;">Date:</td><td style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html( current_time( 'mysql' ) ) . '</td></tr>';
		$message .= '</table>';
		
		$message .= '<p style="font-size: 12px; color: #777; text-align: center; margin-top: 30px;">This is an automated notification from the W9-1099 Chaser plugin.</p>';
		$message .= '</div></body></html>';

		$headers = array( 
			'Content-Type: text/html; charset=UTF-8',
			'From: W9-1099 Chaser <' . $admin_email . '>'
		);

		wp_mail( $to, $subject, $message, $headers );

		wp_send_json_success();
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'w9-1099-chaser' ) );
		}

		$message       = '';
		$message_class = '';

		$manual_plugins = get_option( 'w91099ch_manual_plugins', array() );
		if ( ! is_array( $manual_plugins ) ) {
			$manual_plugins = array();
		}

		$master_webhook_url    = get_option( 'w91099ch_master_webhook_url', '' );
		$master_webhook_secret = get_option( 'w91099ch_master_webhook_secret', '' );
		$payment_limit_enabled = (bool) get_option( 'w91099ch_payment_limit_enabled', false );
		$payment_limit_amount  = (string) get_option( 'w91099ch_payment_limit_amount', '' );
		$payment_limit_period  = (string) get_option( 'w91099ch_payment_limit_period', 'month' );
		$payment_limit_action  = (string) get_option( 'w91099ch_payment_limit_action', 'block' );
		$warn_sales_tax_nexus_on_signup = (bool) get_option( 'w91099ch_warn_sales_tax_nexus_on_signup', false );
		$sales_tax_nexus_affiliate_enabled = (bool) get_option( 'w91099ch_sales_tax_nexus_affiliate_enabled', false );
		$sales_tax_nexus_click_through_enabled = (bool) get_option( 'w91099ch_sales_tax_nexus_click_through_enabled', false );
		$sales_tax_nexus_agency_enabled = (bool) get_option( 'w91099ch_sales_tax_nexus_agency_enabled', false );
		$newsletter_subscribed = (bool) get_option( 'w91099ch_newsletter_subscribed', false );
		$mypowerly_connected = false;
		if ( isset( $this->core ) && is_object( $this->core ) && method_exists( $this->core, 'is_connected' ) ) {
			$mypowerly_connected = (bool) $this->core->is_connected();
		} else {
			$mypowerly_connected = (bool) get_option( 'w91099ch_connected', false );
		}
		$w9_form_enabled = (bool) get_option( 'w91099ch_w9_form_enabled', false );
		$active_settings_tab   = 'plugin';
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			$settings_tab_raw = filter_input( INPUT_POST, 'w91099ch_settings_tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		} else {
			$settings_tab_raw = filter_input( INPUT_GET, 'w91099ch_settings_tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}
		$settings_tab          = is_string( $settings_tab_raw ) ? sanitize_key( wp_unslash( $settings_tab_raw ) ) : '';
		if ( in_array( $settings_tab, array( 'plugin', 'w9-display', 'payment-limits', 'ecommerce-data' ), true ) ) {
			$active_settings_tab = $settings_tab;
		}

		$settings_nonce_raw = filter_input( INPUT_POST, 'w91099ch_settings_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && is_string( $settings_nonce_raw ) && '' !== $settings_nonce_raw ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $settings_nonce_raw ) ), 'w91099ch_save_settings' ) ) {
				$message       = esc_html__( 'Security check failed.', 'w9-1099-chaser' );
				$message_class = 'error';
			} else {
				$action_raw = filter_input( INPUT_POST, 'settings_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				$action     = is_string( $action_raw ) ? sanitize_text_field( wp_unslash( $action_raw ) ) : 'save';

				if ( $action === 'add_manual_plugin' ) {
					$manual_name_raw = filter_input( INPUT_POST, 'manual_plugin_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
					$manual_name     = is_string( $manual_name_raw ) ? sanitize_text_field( wp_unslash( $manual_name_raw ) ) : '';
					$manual_slug_raw = filter_input( INPUT_POST, 'manual_plugin_slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
					$manual_slug     = is_string( $manual_slug_raw ) ? sanitize_text_field( wp_unslash( $manual_slug_raw ) ) : '';

					if ( ! $manual_name ) {
						$message       = esc_html__( 'Please enter a plugin name.', 'w9-1099-chaser' );
						$message_class = 'error';
					} else {
						$slug = $manual_slug ? sanitize_title( $manual_slug ) : sanitize_title( $manual_name );
						if ( ! $slug ) {
							$message       = esc_html__( 'Invalid plugin slug.', 'w9-1099-chaser' );
							$message_class = 'error';
						} else {
							$manual_plugins[] = array(
								'slug' => $slug,
								'name' => $manual_name,
							);

							$deduped = array();
							foreach ( $manual_plugins as $item ) {
								if ( ! is_array( $item ) ) {
									continue;
								}
								$s = sanitize_title( (string) ( $item['slug'] ?? '' ) );
								$n = sanitize_text_field( (string) ( $item['name'] ?? '' ) );
								if ( ! $s || ! $n ) {
									continue;
								}
								$deduped[ $s ] = array(
									'slug' => $s,
									'name' => $n,
								);
							}
							$manual_plugins = array_values( $deduped );
							update_option( 'w91099ch_manual_plugins', $manual_plugins );

							$message       = esc_html__( 'Manual plugin added.', 'w9-1099-chaser' );
							$message_class = 'success';
						}
					}
				} elseif ( $action === 'remove_manual_plugin' ) {
					$remove_slug_raw = filter_input( INPUT_POST, 'remove_manual_slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
					$remove_slug     = is_string( $remove_slug_raw ) ? sanitize_title( wp_unslash( $remove_slug_raw ) ) : '';
					if ( $remove_slug ) {
						$manual_plugins = array_values(
							array_filter(
								$manual_plugins,
								function ( $item ) use ( $remove_slug ) {
									if ( ! is_array( $item ) ) {
										return false;
									}
									return sanitize_title( (string) ( $item['slug'] ?? '' ) ) !== $remove_slug;
								}
							)
						);
						update_option( 'w91099ch_manual_plugins', $manual_plugins );

						$message       = esc_html__( 'Manual plugin removed.', 'w9-1099-chaser' );
						$message_class = 'success';
					}
				} else {
					$ecommerce_present_raw = filter_input( INPUT_POST, 'w91099ch_ecommerce_data_settings_present', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
					if ( '1' === $ecommerce_present_raw ) {
						$plugin_keys = array( 'woocommerce', 'dokan', 'wcfm', 'stripe', 'paypal' );
						$allowed_fields_map = array(
							'woocommerce' => array( 'orders', 'customers', 'products', 'payments', 'refunds', 'coupons', 'subscriptions', 'payouts', 'vendors' ),
							'dokan'       => array( 'orders', 'customers', 'products', 'payouts', 'vendors', 'refunds' ),
							'wcfm'        => array( 'orders', 'customers', 'products', 'payouts', 'vendors', 'refunds' ),
							'stripe'      => array( 'payments', 'refunds', 'payouts' ),
							'paypal'      => array( 'payments', 'refunds', 'payouts' ),
						);

						$existing = get_option( 'w91099ch_ecommerce_data_settings', array() );
						if ( ! is_array( $existing ) ) {
							$existing = array();
						}

						$next = array();
						foreach ( $plugin_keys as $pkey ) {
							$pkey = sanitize_key( (string) $pkey );
							if ( '' === $pkey ) {
								continue;
							}
							$enabled_raw = filter_input( INPUT_POST, 'w91099ch_ecom_enabled_' . $pkey, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
							$enabled     = ( '1' === $enabled_raw );
							$fields_in   = filter_input( INPUT_POST, 'w91099ch_ecom_fields_' . $pkey, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
							$fields_in   = is_array( $fields_in ) ? array_map( 'sanitize_key', $fields_in ) : array();
							$allowed     = isset( $allowed_fields_map[ $pkey ] ) ? (array) $allowed_fields_map[ $pkey ] : array();
							$fields_out  = array();
							foreach ( $allowed as $fname ) {
								$fname = sanitize_key( (string) $fname );
								$fields_out[ $fname ] = in_array( $fname, $fields_in, true );
							}

							$next[ $pkey ] = array(
								'enabled' => $enabled,
								'fields'  => $fields_out,
							);
						}

						if ( $next !== $existing ) {
							update_option( 'w91099ch_ecommerce_data_settings', $next );
						}
					}

					if ( isset( $_POST['hidden_plugins'] ) ) {
						$hidden_raw = wp_unslash( $_POST['hidden_plugins'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitized immediately below.
						$hidden     = is_array( $hidden_raw ) ? array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $hidden_raw ) ) ) ) : array();
						update_option( 'w91099ch_hidden_plugins', $hidden );
					}

					$payment_limit_enabled_raw = filter_input( INPUT_POST, 'w91099ch_payment_limit_enabled_present', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
					if ( '1' === $payment_limit_enabled_raw ) {
						if ( $mypowerly_connected ) {
							$enabled_raw = filter_input( INPUT_POST, 'w91099ch_payment_limit_enabled', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
							$payment_limit_enabled = ( '1' === $enabled_raw );
							update_option( 'w91099ch_payment_limit_enabled', (bool) $payment_limit_enabled );

							$sales_tax_nexus_present_raw = filter_input( INPUT_POST, 'w91099ch_sales_tax_nexus_warning_present', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
							if ( '1' === $sales_tax_nexus_present_raw ) {
								$sales_tax_affiliate_raw = filter_input( INPUT_POST, 'w91099ch_sales_tax_nexus_affiliate_enabled', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
								$sales_tax_click_through_raw = filter_input( INPUT_POST, 'w91099ch_sales_tax_nexus_click_through_enabled', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
								$sales_tax_agency_raw = filter_input( INPUT_POST, 'w91099ch_sales_tax_nexus_agency_enabled', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

								$sales_tax_nexus_affiliate_enabled = ( '1' === $sales_tax_affiliate_raw );
								$sales_tax_nexus_click_through_enabled = ( '1' === $sales_tax_click_through_raw );
								$sales_tax_nexus_agency_enabled = ( '1' === $sales_tax_agency_raw );

								update_option( 'w91099ch_sales_tax_nexus_affiliate_enabled', (bool) $sales_tax_nexus_affiliate_enabled );
								update_option( 'w91099ch_sales_tax_nexus_click_through_enabled', (bool) $sales_tax_nexus_click_through_enabled );
								update_option( 'w91099ch_sales_tax_nexus_agency_enabled', (bool) $sales_tax_nexus_agency_enabled );

								$warn_sales_tax_nexus_on_signup = ( $sales_tax_nexus_affiliate_enabled || $sales_tax_nexus_click_through_enabled || $sales_tax_nexus_agency_enabled );
								update_option( 'w91099ch_warn_sales_tax_nexus_on_signup', (bool) $warn_sales_tax_nexus_on_signup );
							}

							$amount_raw = filter_input( INPUT_POST, 'w91099ch_payment_limit_amount', FILTER_UNSAFE_RAW );
							$amount     = is_string( $amount_raw ) ? trim( wp_unslash( $amount_raw ) ) : '';
							$amount     = preg_replace( '/[^0-9.]/', '', (string) $amount );
							$amount_val = is_numeric( $amount ) ? (float) $amount : 0.0;
							if ( $amount_val < 0 ) {
								$amount_val = 0.0;
							}
							update_option( 'w91099ch_payment_limit_amount', (string) $amount_val );

							$period_raw = filter_input( INPUT_POST, 'w91099ch_payment_limit_period', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
							if ( null !== $period_raw ) {
								$period = is_string( $period_raw ) ? sanitize_key( wp_unslash( $period_raw ) ) : 'month';
								if ( ! in_array( $period, array( 'day', 'week', 'month' ), true ) ) {
									$period = 'month';
								}
								update_option( 'w91099ch_payment_limit_period', $period );
							}

							$action_raw = filter_input( INPUT_POST, 'w91099ch_payment_limit_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
							if ( null !== $action_raw ) {
								$action = is_string( $action_raw ) ? sanitize_key( wp_unslash( $action_raw ) ) : 'block';
								if ( ! in_array( $action, array( 'block', 'warn' ), true ) ) {
									$action = 'block';
								}
								update_option( 'w91099ch_payment_limit_action', $action );
							}
						}
					}

					$master_webhook_url_raw = filter_input( INPUT_POST, 'w91099ch_master_webhook_url', FILTER_UNSAFE_RAW );
					$master_webhook_secret_raw = filter_input( INPUT_POST, 'w91099ch_master_webhook_secret', FILTER_UNSAFE_RAW );
					$webhook_fields_submitted = ( null !== $master_webhook_url_raw || null !== $master_webhook_secret_raw );

					// Only update manual webhook options when webhook fields are present in this request.
					// Prevents clearing manual webhook values on non-webhook settings saves.
					if ( $webhook_fields_submitted ) {
						$master_webhook_url = is_string( $master_webhook_url_raw ) ? wp_unslash( $master_webhook_url_raw ) : '';
						$master_webhook_secret = is_string( $master_webhook_secret_raw ) ? wp_unslash( $master_webhook_secret_raw ) : '';

						if ( class_exists( 'w91099ch_Webhook_Dispatcher' ) ) {
							$master_webhook_url    = w91099ch_Webhook_Dispatcher::sanitize_webhook_url( $master_webhook_url );
							$master_webhook_secret = w91099ch_Webhook_Dispatcher::sanitize_webhook_secret( $master_webhook_secret );
						} else {
							$master_webhook_url    = esc_url_raw( (string) $master_webhook_url );
							$master_webhook_secret = sanitize_text_field( (string) $master_webhook_secret );
						}

						update_option( 'w91099ch_master_webhook_url', $master_webhook_url );
						update_option( 'w91099ch_master_webhook_secret', $master_webhook_secret );
						delete_option( 'w91099ch_user_webhook_url' );
						delete_option( 'w91099ch_user_webhook_secret' );
					}

					$display_method_raw = filter_input( INPUT_POST, 'w91099ch_w9_display_method', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
					$default_page_raw   = filter_input( INPUT_POST, 'w91099ch_w9_default_page_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
					$social_sharing_present = filter_input( INPUT_POST, 'w91099ch_enable_social_sharing_present', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
					$secure_w9_present   = filter_input( INPUT_POST, 'w91099ch_enable_secure_w9_present', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

					$w9_fields_submitted = ( null !== $display_method_raw || null !== $default_page_raw || isset( $_POST['w91099ch_w9_selected_pages'] ) || '1' === $social_sharing_present || '1' === $secure_w9_present );
					if ( $w9_fields_submitted && ! $newsletter_subscribed ) {
						$message       = esc_html__( 'Please subscribe to the newsletter to unlock W-9 Display Settings.', 'w9-1099-chaser' );
						$message_class = 'error';
					} else {
						if ( null !== $display_method_raw ) {
							update_option( 'w91099ch_w9_display_method', sanitize_text_field( $display_method_raw ) );
						}

						if ( isset( $_POST['w91099ch_w9_selected_pages'] ) ) {
							$selected_pages = array_map( 'strval', array_map( 'absint', (array) $_POST['w91099ch_w9_selected_pages'] ) );
							update_option( 'w91099ch_w9_selected_pages', $selected_pages );
						}

						if ( null !== $default_page_raw ) {
							$default_page_id = absint( $default_page_raw );
							update_option( 'w91099ch_w9_default_page_id', $default_page_id );
							error_log('W9 Debug: Raw input for default_page_id = ' . var_export($default_page_raw, true));
							error_log('W9 Debug: Setting saved as default_page_id = ' . get_option('w91099ch_w9_default_page_id'));
						}

						if ( '1' === $social_sharing_present ) {
							$enable_social_sharing_raw = filter_input( INPUT_POST, 'w91099ch_enable_social_sharing', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
							$enable_social_sharing = ( '1' === $enable_social_sharing_raw );
							update_option( 'w91099ch_enable_social_sharing', $enable_social_sharing );
							$verify_saved = get_option( 'w91099ch_enable_social_sharing' );
							error_log('W9 Debug: Social sharing saved - checkbox was rendered, value: ' . var_export($enable_social_sharing, true) . ', verified: ' . var_export($verify_saved, true));
						}

						if ( '1' === $secure_w9_present ) {
							$enable_secure_w9_raw = filter_input( INPUT_POST, 'w91099ch_enable_secure_w9', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
							$enable_secure_w9 = ( '1' === $enable_secure_w9_raw );
							update_option( 'w91099ch_enable_secure_w9', $enable_secure_w9 );
							error_log('W9 Debug: Secure W-9 saved - checkbox was rendered, value: ' . var_export($enable_secure_w9, true));
						}

						$w9_form_enabled_present_raw = filter_input( INPUT_POST, 'w91099ch_w9_form_enabled_present', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
						if ( '1' === $w9_form_enabled_present_raw ) {
							$w9_form_enabled = isset( $_POST['w91099ch_w9_form_enabled'] ) && '1' === (string) wp_unslash( $_POST['w91099ch_w9_form_enabled'] );
							update_option( 'w91099ch_w9_form_enabled', (bool) $w9_form_enabled );
						}
					}

					if ( '' === $message ) {
						$message       = esc_html__( 'Settings saved.', 'w9-1099-chaser' );
						$message_class = 'success';
					}
				}
			}
		}

		$hidden_plugins = get_option( 'w91099ch_hidden_plugins', array() );
		if ( ! is_array( $hidden_plugins ) ) {
			$hidden_plugins = array();
		}

		$affiliate_manager = new w91099ch_Affiliate_Manager();
		$plugins           = $affiliate_manager->detect_affiliate_plugins( true );
		$is_plugin_tab     = ( 'plugin' === $active_settings_tab );
		$is_payment_limits_tab = ( 'payment-limits' === $active_settings_tab );
		?>
		<div class="w9-1099-chaser-settings-shell">
			<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-12">
				<div class="mb-8">
					<div class="mp-card p-6" style="background: linear-gradient(135deg, rgba(26, 86, 219, 0.08) 0%, rgba(124, 58, 237, 0.06) 100%);">
						<div class="flex items-start gap-4">
							<div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
								<i class="fas fa-sliders-h text-xl" style="color: var(--mp-primary);"></i>
							</div>
							<div class="flex-1">
								<h1 class="text-2xl font-bold text-gray-800 mb-1"><?php echo esc_html__( 'Settings', 'w9-1099-chaser' ); ?></h1>
							</div>
						</div>
					</div>
				</div>

				<div class="mb-6">
					<div class="flex gap-2 border-b" style="border-color: var(--mp-gray-200);">
						<button type="button" onclick="document.getElementById('tab-input-plugin').click()" class="px-6 py-3 font-semibold transition-all <?php echo $active_settings_tab === 'plugin' ? 'border-b-2 text-blue-600' : 'text-gray-600 hover:text-gray-800'; ?>" style="<?php echo $active_settings_tab === 'plugin' ? 'border-color: var(--mp-primary);' : ''; ?>">
							<i class="fas fa-plug mr-2"></i><?php echo esc_html__( 'Plugin Detection Settings', 'w9-1099-chaser' ); ?>
						</button>
						<button type="button" onclick="document.getElementById('tab-input-w9-display').click()" class="px-6 py-3 font-semibold transition-all <?php echo $active_settings_tab === 'w9-display' ? 'border-b-2 text-blue-600' : 'text-gray-600 hover:text-gray-800'; ?>" style="<?php echo $active_settings_tab === 'w9-display' ? 'border-color: var(--mp-primary);' : ''; ?>">
							<i class="fas fa-desktop mr-2"></i><?php echo esc_html__( 'W-9 Display Settings', 'w9-1099-chaser' ); ?>
						</button>
						<button type="button" onclick="document.getElementById('tab-input-payment-limits').click()" class="px-6 py-3 font-semibold transition-all <?php echo $active_settings_tab === 'payment-limits' ? 'border-b-2 text-blue-600' : 'text-gray-600 hover:text-gray-800'; ?>" style="<?php echo $active_settings_tab === 'payment-limits' ? 'border-color: var(--mp-primary);' : ''; ?>">
							<i class="fas fa-gauge-high mr-2"></i><?php echo esc_html__( 'Alarms & Alerts Settings', 'w9-1099-chaser' ); ?>
						</button>
						<button type="button" onclick="document.getElementById('tab-input-ecommerce-data').click()" class="px-6 py-3 font-semibold transition-all <?php echo $active_settings_tab === 'ecommerce-data' ? 'border-b-2 text-blue-600' : 'text-gray-600 hover:text-gray-800'; ?>" style="<?php echo $active_settings_tab === 'ecommerce-data' ? 'border-color: var(--mp-primary);' : ''; ?>">
							<i class="fas fa-cart-shopping mr-2"></i><?php echo esc_html__( 'Ecommerce Data Settings', 'w9-1099-chaser' ); ?>
						</button>
					</div>
				</div>

				<?php if ( ! empty( $message ) && 'success' !== $message_class ) : ?>
					<div class="mb-8">
						<div class="mp-card p-6 <?php echo esc_attr( $message_class === 'success' ? 'border-l-4 border-green-500 bg-green-50/50' : 'border-l-4 border-red-500 bg-red-50/50' ); ?>">
							<div class="flex items-start gap-4">
								<div class="w-12 h-12 rounded-xl <?php echo esc_attr( $message_class === 'success' ? 'bg-green-100' : 'bg-red-100' ); ?> flex items-center justify-center flex-shrink-0">
									<i class="fas <?php echo esc_attr( $message_class === 'success' ? 'fa-circle-check text-green-600' : 'fa-triangle-exclamation text-red-600' ); ?> text-xl"></i>
								</div>
								<div class="flex-1">
									<h3 class="text-lg font-bold text-gray-800 mb-1">
										<?php
											$message_heading = ( $message_class === 'success' )
												? __( 'Success!', 'w9-1099-chaser' )
												: __( 'Error', 'w9-1099-chaser' );
											echo esc_html( $message_heading );
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

				<div class="mp-card p-8">
					<form method="post">
						<?php wp_nonce_field( 'w91099ch_save_settings', 'w91099ch_settings_nonce' ); ?>
						<input type="radio" name="w91099ch_settings_tab" id="tab-input-plugin" value="plugin" <?php checked( $active_settings_tab === 'plugin' ); ?> style="display:none;" onchange="this.form.submit()" />
						<input type="radio" name="w91099ch_settings_tab" id="tab-input-w9-display" value="w9-display" <?php checked( $active_settings_tab === 'w9-display' ); ?> style="display:none;" onchange="this.form.submit()" />
						<input type="radio" name="w91099ch_settings_tab" id="tab-input-payment-limits" value="payment-limits" <?php checked( $active_settings_tab === 'payment-limits' ); ?> style="display:none;" onchange="this.form.submit()" />
						<input type="radio" name="w91099ch_settings_tab" id="tab-input-ecommerce-data" value="ecommerce-data" <?php checked( $active_settings_tab === 'ecommerce-data' ); ?> style="display:none;" onchange="this.form.submit()" />

						<?php if ( $active_settings_tab === 'payment-limits' ) : ?>
						<div class="space-y-10">
							<div>
								<div class="mp-card-header">
									<div>
										<h2 class="text-xl font-bold text-gray-800 mb-1 flex items-center gap-3">
											<div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center">
												<i class="fas fa-gauge-high text-white"></i>
											</div>
											<?php echo esc_html__( 'Alarms & Alerts', 'w9-1099-chaser' ); ?>
										</h2>
										<p class="text-sm" style="color: var(--mp-gray-600);">
											<?php echo esc_html__( 'Set a global outgoing payment/payout limit across all detected integrations. When enabled, webhook events that contain payout amounts will be tracked and optionally blocked if the limit is exceeded.', 'w9-1099-chaser' ); ?>
										</p>
									</div>
								</div>

								<?php $alarms_disabled = ! $mypowerly_connected; ?>
								<?php $alarms_disabled_attr = $alarms_disabled ? 'disabled="disabled"' : ''; ?>
								<?php if ( $alarms_disabled ) : ?>
									<div class="mp-card p-6" style="background: linear-gradient(135deg, rgba(26, 86, 219, 0.06) 0%, rgba(124, 58, 237, 0.05) 100%);">
										<div class="mp-card-header">
											<div>
												<h3 class="text-xl font-bold text-gray-800 mb-2 flex items-center gap-3">
													<div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center">
														<i class="fas fa-plug text-white text-lg"></i>
													</div>
													<?php echo esc_html__( 'Connect to MyPowerly to enable Alarms & Alerts', 'w9-1099-chaser' ); ?>
												</h3>
												<p class="mp-section-subtitle">
													<?php echo esc_html__( 'Payout limits and nexus alerts require an active MyPowerly connection.', 'w9-1099-chaser' ); ?>
												</p>
											</div>
										</div>

										<div class="p-5 bg-blue-50 rounded-xl border border-blue-200">
											<div class="flex items-start gap-3">
												<i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
												<div class="text-sm text-gray-700">
													<p class="font-semibold text-gray-900 mb-2"><?php echo esc_html__( 'How to enable Alarms & Alerts', 'w9-1099-chaser' ); ?></p>
													<p class="mb-3"><?php echo wp_kses_post( __( '1) Go to the plugin Dashboard and click <strong>Connect to MyPowerly</strong>.', 'w9-1099-chaser' ) ); ?></p>
													<p class="mb-3"><?php echo esc_html__( '2) Complete the authorization in MyPowerly.', 'w9-1099-chaser' ); ?></p>
													<p class="mb-0"><?php echo esc_html__( '3) Come back here to enable payout limits and nexus alerts.', 'w9-1099-chaser' ); ?></p>
												</div>
											</div>
										</div>

										<div class="mt-5 flex flex-wrap items-center gap-3">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=w91099ch' ) ); ?>" class="mp-btn-primary flex items-center gap-3">
												<i class="fas fa-arrow-right"></i>
												<span><?php echo esc_html__( 'Go to Dashboard & Connect', 'w9-1099-chaser' ); ?></span>
											</a>
										</div>
									</div>
								<?php endif; ?>
								<div class="bg-gray-50 p-6 rounded-xl border border-gray-200 space-y-5">
									<?php $alarms_disabled_attr = $alarms_disabled ? 'disabled="disabled"' : ''; ?>
									<input type="hidden" name="w91099ch_payment_limit_enabled_present" value="1" />
									<label class="flex items-start gap-3 cursor-pointer">
										<input type="checkbox" name="w91099ch_payment_limit_enabled" value="1" <?php checked( $payment_limit_enabled ); ?> class="mt-1" <?php echo $alarms_disabled_attr; ?> />
										<div>
											<div class="font-bold text-gray-800"><?php echo esc_html__( 'Activate payout limits', 'w9-1099-chaser' ); ?></div>
											<div class="text-sm text-gray-600"><?php echo esc_html__( 'If enabled, outgoing payout/payment you will be alerted against your limit.', 'w9-1099-chaser' ); ?></div>
										</div>
									</label>

									<div class="grid grid-cols-1 md:grid-cols-1 gap-4">
										<div>
											<label for="w91099ch_payment_limit_amount" class="block text-sm font-semibold text-gray-700 mb-2"><?php echo esc_html__( 'Payout Limit amount', 'w9-1099-chaser' ); ?></label>
											<input id="w91099ch_payment_limit_amount" name="w91099ch_payment_limit_amount" type="text" class="mp-input" value="<?php echo esc_attr( (string) $payment_limit_amount ); ?>" placeholder="<?php echo esc_attr__( 'e.g. 5000', 'w9-1099-chaser' ); ?>" <?php echo $alarms_disabled_attr; ?> />
										</div>
									</div>

									<div class="text-base font-bold text-gray-800">
										<?php echo esc_html__( 'Alerts Sales Tax Nexus Libilites', 'w9-1099-chaser' ); ?>
									</div>
									<input type="hidden" name="w91099ch_sales_tax_nexus_warning_present" value="1" />
									<div class="space-y-3">
										<label class="flex items-start gap-3 cursor-pointer">
											<input type="checkbox" name="w91099ch_sales_tax_nexus_affiliate_enabled" value="1" <?php checked( $sales_tax_nexus_affiliate_enabled ); ?> class="mt-1" <?php echo $alarms_disabled_attr; ?> />
											<div>
												<div class="font-bold text-gray-800"><?php echo esc_html__( 'Affiliate Nexus', 'w9-1099-chaser' ); ?></div>
												<div class="text-sm text-gray-600"><?php echo esc_html__( 'If enabled, you will see an alert when a new affiliate/vendor signs up in certain states that may create sales-tax nexus.', 'w9-1099-chaser' ); ?></div>
											</div>
										</label>
										<label class="flex items-start gap-3 cursor-pointer">
											<input type="checkbox" name="w91099ch_sales_tax_nexus_click_through_enabled" value="1" <?php checked( $sales_tax_nexus_click_through_enabled ); ?> class="mt-1" <?php echo $alarms_disabled_attr; ?> />
											<div>
												<div class="font-bold text-gray-800"><?php echo esc_html__( 'Click Through Nexus', 'w9-1099-chaser' ); ?></div>
												<div class="text-sm text-gray-600"><?php echo esc_html__( 'If enabled, you will see an alert when a new affiliate/vendor signs up in certain states that may create sales-tax nexus.', 'w9-1099-chaser' ); ?></div>
											</div>
										</label>
										<label class="flex items-start gap-3 cursor-pointer">
											<input type="checkbox" name="w91099ch_sales_tax_nexus_agency_enabled" value="1" <?php checked( $sales_tax_nexus_agency_enabled ); ?> class="mt-1" <?php echo $alarms_disabled_attr; ?> />
											<div>
												<div class="font-bold text-gray-800"><?php echo esc_html__( 'Agency Nexus', 'w9-1099-chaser' ); ?></div>
												<div class="text-sm text-gray-600"><?php echo esc_html__( 'If enabled, you will see an alert when a new affiliate/vendor signs up in certain states that may create sales-tax nexus.', 'w9-1099-chaser' ); ?></div>
											</div>
										</label>
									</div>

									<div class="pt-2 flex justify-end">
										<button type="submit" class="mp-btn-primary" style="min-width: 200px;">
											<i class="fas fa-save"></i>
											<?php echo esc_html__( 'Save Settings', 'w9-1099-chaser' ); ?>
										</button>
									</div>
								</div>
							</div>
						</div>
						<?php endif; ?>

						<?php if ( $active_settings_tab === 'w9-display' ) : ?>
						<div class="space-y-10">
							<div>
								<div class="mp-card-header">
									<div>
										<h2 class="text-xl font-bold text-gray-800 mb-1 flex items-center gap-3">
											<div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-600 flex items-center justify-center">
												<i class="fas fa-link text-white"></i>
											</div>
											<?php echo esc_html__( 'W-9 Form Display Options', 'w9-1099-chaser' ); ?>
										</h2>
										<p class="text-sm" style="color: var(--mp-gray-600);">
											<?php echo esc_html__( 'Choose how and where you want to display the W-9 form on your website.', 'w9-1099-chaser' ); ?>
										</p>
									</div>
								</div>

								<?php $w9_display_locked = ! $newsletter_subscribed; ?>
								<div id="w91099ch-w9-display-lock" data-locked="<?php echo esc_attr( $w9_display_locked ? '1' : '0' ); ?>" style="position: relative;">
									<?php if ( $w9_display_locked ) : ?>
										<div id="w91099ch-w9-display-lock-overlay" style="position:absolute; inset:0; z-index: 50; border-radius: 18px;"></div>
									<?php endif; ?>

								<div id="w91099ch-newsletter-modal" class="hidden" style="position: fixed; inset: 0; z-index: 10000;">
									<div data-newsletter-close="1" style="position: absolute; inset: 0; background: rgba(0,0,0,.55);"></div>
									<div style="position: relative; max-width: 520px; margin: 10vh auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.25);">
										<div style="padding: 18px 18px 0 18px; display:flex; align-items:center; justify-content: space-between; gap: 12px;">
											<div style="font-weight: 800; color: #111827; font-size: 16px;">
												<?php echo esc_html__( 'Subscribe to unlock W-9 Display Settings', 'w9-1099-chaser' ); ?>
											</div>
											<button type="button" data-newsletter-close="1" style="border: 0; background: transparent; font-size: 22px; line-height: 1; padding: 6px 10px; cursor: pointer; color: #6b7280;">
												&times;
											</button>
										</div>
										<div style="padding: 18px;">
											<div class="p-5 bg-blue-50 rounded-xl border border-blue-200">
												<div class="flex items-start gap-3">
													<i class="fas fa-envelope text-blue-600 mt-0.5"></i>
													<div class="text-sm text-gray-700">
														<div class="font-semibold text-gray-900 mb-2"><?php echo esc_html__( 'One-time subscription required', 'w9-1099-chaser' ); ?></div>
														<div><?php echo esc_html__( 'Please subscribe to our newsletter once to enable and use W-9 Display Settings.', 'w9-1099-chaser' ); ?></div>
													</div>
												</div>
											</div>

										<div style="margin-top: 14px;">
											<label for="w91099ch_newsletter_email" class="block text-sm font-semibold text-gray-700 mb-2"><?php echo esc_html__( 'Email', 'w9-1099-chaser' ); ?></label>
											<input id="w91099ch_newsletter_email" type="email" class="mp-input" value="" placeholder="<?php echo esc_attr__( 'you@example.com', 'w9-1099-chaser' ); ?>" />
											<div class="text-xs" style="color: var(--mp-gray-600); margin-top: 6px;"><?php echo esc_html__( 'This unlocks the feature. No functionality changes otherwise.', 'w9-1099-chaser' ); ?></div>
										</div>

										<div style="display:flex; justify-content:flex-end; gap: 10px; margin-top: 16px;">
											<button type="button" data-newsletter-close="1" class="mp-btn-secondary"><?php echo esc_html__( 'Not now', 'w9-1099-chaser' ); ?></button>
											<button type="button" id="w91099ch-newsletter-subscribe-btn" class="mp-btn-primary">
												<i class="fas fa-lock-open"></i>
												<?php echo esc_html__( 'Subscribe & Unlock', 'w9-1099-chaser' ); ?>
											</button>
										</div>
									</div>
								</div>
								</div>

								<div class="space-y-6">
									<div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
										<div class="flex items-start gap-3 mb-4">
											<input type="hidden" name="w91099ch_w9_form_enabled_present" value="1" />
											<input type="hidden" name="w91099ch_w9_form_enabled" value="0" />
											<label class="mp-toggle mt-1">
												<input type="checkbox" name="w91099ch_w9_form_enabled" value="1" <?php checked( $w9_form_enabled ); ?> />
												<span class="mp-toggle-slider"></span>
											</label>
											<div>
												<h3 class="text-lg font-bold text-gray-800 m-0"><?php echo esc_html__( 'Enable W-9 form', 'w9-1099-chaser' ); ?></h3>
												<p class="text-sm text-gray-600 mt-1">
													<?php echo esc_html__( 'If disabled, the W-9 form will not be visible anywhere on your website (Shortcode, Auto-display, or Selected pages).', 'w9-1099-chaser' ); ?>
												</p>
											</div>
										</div>
									</div>

									<div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
										<h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo esc_html__( 'Display Method', 'w9-1099-chaser' ); ?></h3>
										
										<?php
										$display_method = get_option( 'w91099ch_w9_display_method', 'all' );
										?>
										
										<div class="space-y-4">
											<label class="flex items-start gap-3 cursor-pointer p-3 rounded-lg hover:bg-white transition-colors">
												<input type="radio" name="w91099ch_w9_display_method" value="all" <?php checked( $display_method, 'all' ); ?> class="mt-1" onchange="toggleSelectedPages(this.value)">
												<div>
													<span class="font-bold text-gray-800 block"><?php echo esc_html__( 'Auto-display on All Pages', 'w9-1099-chaser' ); ?></span>
													<span class="text-sm text-gray-600"><?php echo esc_html__( 'Automatically appends the W-9 form to the bottom of all frontend pages.', 'w9-1099-chaser' ); ?></span>
												</div>
											</label>

											<label class="flex items-start gap-3 cursor-pointer p-3 rounded-lg hover:bg-white transition-colors">
												<input type="radio" name="w91099ch_w9_display_method" value="selected" <?php checked( $display_method, 'selected' ); ?> class="mt-1" onchange="toggleSelectedPages(this.value)">
												<div>
													<span class="font-bold text-gray-800 block"><?php echo esc_html__( 'Auto-display on Selected Pages', 'w9-1099-chaser' ); ?></span>
													<span class="text-sm text-gray-600"><?php echo esc_html__( 'Automatically appends the W-9 form to specific pages chosen below.', 'w9-1099-chaser' ); ?></span>
												</div>
												
											</label>
                                             <div id="selected-pages-container" class="mt-5" style="<?php echo $display_method === 'selected' ? '' : 'display: none;'; ?>">
										<h3 class="text-base font-bold text-gray-800 mb-2"><?php echo esc_html__( 'Select Pages', 'w9-1099-chaser' ); ?></h3>
										<p class="text-sm mb-3" style="color: var(--mp-gray-600);">
											<?php echo esc_html__( 'Select the pages where you want the W-9 form to be automatically displayed.', 'w9-1099-chaser' ); ?>
										</p>
										<?php
										$selected_pages = get_option( 'w91099ch_w9_selected_pages', array() );
										$pages = get_pages();
										?>
										<div class="max-h-60 overflow-y-auto border rounded-lg bg-white p-4">
											<?php if ( $pages ) : ?>
												<?php foreach ( $pages as $page ) : ?>
													<?php $is_checked = in_array( (string) $page->ID, $selected_pages, true ); ?>
													<label class="flex items-center gap-3 mb-2 hover:bg-gray-50 p-1 rounded transition-colors">
														<input type="checkbox" name="w91099ch_w9_selected_pages[]" value="<?php echo esc_attr( $page->ID ); ?>" <?php checked( $is_checked ); ?>>
														<span class="text-gray-800"><?php echo esc_html( $page->post_title ); ?></span>
														<span class="text-xs text-gray-400 font-mono">(ID: <?php echo (int) $page->ID; ?>)</span>
													</label>
												<?php endforeach; ?>
											<?php else : ?>
												<p class="text-sm text-gray-500"><?php echo esc_html__( 'No pages found.', 'w9-1099-chaser' ); ?></p>
											<?php endif; ?>
										</div>
									</div>
											<label class="flex items-start gap-3 cursor-pointer p-3 rounded-lg hover:bg-white transition-colors">
												<input type="radio" name="w91099ch_w9_display_method" value="shortcode" <?php checked( $display_method, 'shortcode' ); ?> class="mt-1" onchange="toggleSelectedPages(this.value)">
												<div>
													<span class="font-bold text-gray-800 block"><?php echo esc_html__( 'Shortcode Only (Manual)', 'w9-1099-chaser' ); ?></span>
													<span class="text-sm text-gray-600"><?php echo esc_html__( 'Manual control. Use the shortcode [w91099ch_w9_form] anywhere in your content.', 'w9-1099-chaser' ); ?></span>
												</div>
											</label>
										</div>
									</div>

									

									<div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
										<h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo esc_html__( 'Set as default page', 'w9-1099-chaser' ); ?></h3>
										<p class="text-sm mb-4" style="color: var(--mp-gray-600);">
											<?php echo esc_html__( 'Choose one page as the default page for the W-9 form.', 'w9-1099-chaser' ); ?>
										</p>
										<?php
										$default_page_id = absint( get_option( 'w91099ch_w9_default_page_id', 0 ) );
										$pages = get_pages();
										?>
										<select name="w91099ch_w9_default_page_id" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-white">
											<option value="0" <?php selected( $default_page_id, 0 ); ?>><?php echo esc_html__( 'Select a page', 'w9-1099-chaser' ); ?></option>
											<?php if ( $pages ) : ?>
												<?php foreach ( $pages as $page ) : ?>
													<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $default_page_id, (int) $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
												<?php endforeach; ?>
											<?php endif; ?>
										</select>
									</div>

									<div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
										<h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo esc_html__( 'Social Media & Sharing Settings', 'w9-1099-chaser' ); ?></h3>
										<p class="text-sm mb-4" style="color: var(--mp-gray-600);">
											<?php echo esc_html__( 'Control the visibility of social media sharing buttons and related features in the PDF sharing popup.', 'w9-1099-chaser' ); ?>
										</p>
										<?php
										$enable_social_sharing = get_option( 'w91099ch_enable_social_sharing', false );
										error_log('W9 Debug: Rendering checkbox - value from DB: ' . var_export($enable_social_sharing, true));
										?>
										<input type="hidden" name="w91099ch_enable_social_sharing_present" value="1" />
										<label class="flex items-start gap-3 cursor-pointer p-3 rounded-lg hover:bg-white transition-colors">
											<input type="checkbox" name="w91099ch_enable_social_sharing" value="1" <?php checked( $enable_social_sharing, true ); ?> class="mt-1">
											<div>
												<span class="font-bold text-gray-800 block"><?php echo esc_html__( 'Enable Social Media Sharing', 'w9-1099-chaser' ); ?></span>
												<span class="text-sm text-gray-600"><?php echo esc_html__( 'I agree that after a W-9 PDF is created by visitors (contractors or affiliates) on my website, they can instantly send me a copy by automatically attaching the generated PDF through the email system. I understand that if I do not enable this option, users will need to download the PDF and send it manually using their own email.', 'w9-1099-chaser' ); ?></span>
											</div>
										</label>

										<?php
										$enable_secure_w9 = get_option( 'w91099ch_enable_secure_w9', false );
										?>
										<input type="hidden" name="w91099ch_enable_secure_w9_present" value="1" />
										<label class="flex items-start gap-3 cursor-pointer p-3 rounded-lg hover:bg-white transition-colors">
											<input type="checkbox" name="w91099ch_enable_secure_w9" value="1" <?php checked( $enable_secure_w9, true ); ?> class="mt-1">
											<div>
												<span class="font-bold text-gray-800 block"><?php echo esc_html__( 'Enable "Secure my W-9" Checkbox', 'w9-1099-chaser' ); ?></span>
												<span class="text-sm text-gray-600"><?php echo esc_html__( 'I agree to allow visitors to securely save the generated PDF in MyPowerly. I understand that a copy of the W-9 will be sent to MyPowerly using my email system, and that MyPowerly offers affiliate commissions and rewards for enabling this feature, which I choose to participate in.', 'w9-1099-chaser' ); ?></span>
											</div>
										</label>
									</div>

							<div class="pt-2 flex justify-end">
								<input type="hidden" name="settings_action" value="save" />
								<button type="submit" class="mp-btn-primary">
									<i class="fas fa-save"></i>
									<?php echo esc_html__( 'Save Display Settings', 'w9-1099-chaser' ); ?>
								</button>
							</div>
						</div>
						<?php endif; ?>

						<?php if ( $active_settings_tab === 'plugin' ) : ?>
						<div class="space-y-10">
							<div>
								<div class="mp-card-header">
									<div>
										<h2 class="text-xl font-bold text-gray-800 mb-1 flex items-center gap-3">
											<div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center">
												<i class="fas fa-plug text-white"></i>
											</div>
											<?php echo esc_html__( 'Detected Plugins', 'w9-1099-chaser' ); ?>
										</h2>
										<p class="text-sm" style="color: var(--mp-gray-600);">
											<?php echo esc_html__( 'Turn on “Hide” for plugins you want to remove from the dashboard and filters.', 'w9-1099-chaser' ); ?>
										</p>
									</div>
								</div>

								<div class="overflow-hidden rounded-xl border" style="border-color: var(--mp-gray-200);">
									<table class="min-w-full divide-y" style="border-color: var(--mp-gray-200);">
										<thead class="bg-gray-50">
											<tr>
												<th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--mp-gray-700); width: 210px;"><?php echo esc_html__( 'Hide', 'w9-1099-chaser' ); ?></th>
												<th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--mp-gray-700);"><?php echo esc_html__( 'Plugin', 'w9-1099-chaser' ); ?></th>
												<th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--mp-gray-700); width: 140px;"><?php echo esc_html__( 'Affiliates', 'w9-1099-chaser' ); ?></th>
												<th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--mp-gray-700); width: 140px;"><?php echo esc_html__( 'Status', 'w9-1099-chaser' ); ?></th>
											</tr>
										</thead>
										<tbody class="bg-white divide-y" style="border-color: var(--mp-gray-200);">
											<?php if ( ! empty( $plugins ) ) : ?>
												<?php
												foreach ( $plugins as $slug => $plugin ) :
													$is_hidden = in_array( (string) $slug, $hidden_plugins, true );
													$name      = isset( $plugin['name'] ) ? $plugin['name'] : $slug;
													$count     = isset( $plugin['affiliate_count'] ) ? $plugin['affiliate_count'] : 0;
													$toggle_id = 'hide-plugin-' . sanitize_title( (string) $slug );
													?>
													<tr>
														<td class="px-4 py-3">
															<label class="mp-toggle" for="<?php echo esc_attr( $toggle_id ); ?>">
																<input id="<?php echo esc_attr( $toggle_id ); ?>" type="checkbox" name="hidden_plugins[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $is_hidden ); ?> />
																<span class="mp-toggle-track" aria-hidden="true"><span class="mp-toggle-thumb"></span></span>
																<span class="text-sm font-semibold" style="color: var(--mp-gray-700);">
																	<?php echo esc_html__( 'Hide', 'w9-1099-chaser' ); ?>
																</span>
															</label>
														</td>
														<td class="px-4 py-3">
															<div class="font-semibold" style="color: var(--mp-gray-900);">
																<?php echo esc_html( $name ); ?>
															</div>
															<div class="text-xs mt-1" style="color: var(--mp-gray-600);">
																<?php echo esc_html__( 'Slug:', 'w9-1099-chaser' ); ?> <code><?php echo esc_html( $slug ); ?></code>
															</div>
														</td>
														<td class="px-4 py-3"><?php echo esc_html( (string) $count ); ?></td>
														<td class="px-4 py-3">
															<?php if ( $is_hidden ) : ?>
																<span class="mp-badge mp-badge-hidden"><?php echo esc_html__( 'Hidden', 'w9-1099-chaser' ); ?></span>
															<?php else : ?>
																<span class="mp-badge mp-badge-visible"><?php echo esc_html__( 'Visible', 'w9-1099-chaser' ); ?></span>
															<?php endif; ?>
														</td>
													</tr>
												<?php endforeach; ?>
											<?php else : ?>
												<tr>
													<td colspan="4" class="px-4 py-6 text-sm" style="color: var(--mp-gray-700);">
														<?php echo esc_html__( 'No detected plugins found.', 'w9-1099-chaser' ); ?>
													</td>
												</tr>
											<?php endif; ?>
										</tbody>
									</table>
								</div>
							</div>

							<div class="mp-divider"></div>

							<div>
								<div class="mp-card-header">
									<div>
										<h2 class="text-xl font-bold text-gray-800 mb-1 flex items-center gap-3">
											<div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center">
												<i class="fas fa-plus text-white"></i>
											</div>
											<?php echo esc_html__( 'Add Plugin Manually', 'w9-1099-chaser' ); ?>
										</h2>
										<p class="text-sm" style="color: var(--mp-gray-600);">
											<?php
											echo esc_html__(
												'If a plugin does not appear due to a server issue or detection issue, you can add it manually here. It will show across the dashboard and can also be hidden using the toggles below.',
												'w9-1099-chaser'
											);
											?>
										</p>
									</div>
								</div>

								<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
									<div>
										<label for="manual_plugin_name" class="block text-sm font-semibold text-gray-700 mb-2"><?php echo esc_html__( 'Plugin Name', 'w9-1099-chaser' ); ?></label>
										<input id="manual_plugin_name" name="manual_plugin_name" type="text" class="mp-input" placeholder="<?php echo esc_attr__( 'e.g. SliceWP', 'w9-1099-chaser' ); ?>" />
									</div>
									<div>
										<label for="manual_plugin_slug" class="block text-sm font-semibold text-gray-700 mb-2"><?php echo esc_html__( 'Plugin Slug (optional)', 'w9-1099-chaser' ); ?><span class="mp-help-tooltip" tabindex="0" data-tooltip="<?php echo esc_attr__( 'Where to find it: WP Admin → Plugins → Installed Plugins → find the plugin → copy its slug (folder name).', 'w9-1099-chaser' ); ?>"><i class="fas fa-info-circle" aria-hidden="true"></i></span></label>
										<input id="manual_plugin_slug" name="manual_plugin_slug" type="text" class="mp-input" placeholder="<?php echo esc_attr__( 'e.g. slicewp', 'w9-1099-chaser' ); ?>" />
									</div>
									<div class="flex items-end">
										<input type="hidden" name="settings_action" value="add_manual_plugin" />
										<button type="submit" class="mp-btn-primary w-full md:w-auto">
											<i class="fas fa-plus"></i>
											<?php echo esc_html__( 'Add', 'w9-1099-chaser' ); ?>
										</button>
									</div>
								</div>

								<?php if ( ! empty( $manual_plugins ) ) : ?>
									<div class="mt-6 overflow-hidden rounded-xl border" style="border-color: var(--mp-gray-200);">
										<table class="min-w-full divide-y" style="border-color: var(--mp-gray-200);">
											<thead class="bg-gray-50">
												<tr>
													<th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--mp-gray-700);"><?php echo esc_html__( 'Manual Plugins', 'w9-1099-chaser' ); ?></th>
													<th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--mp-gray-700); width: 220px;"><?php echo esc_html__( 'Slug', 'w9-1099-chaser' ); ?></th>
													<th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--mp-gray-700); width: 170px;"><?php echo esc_html__( 'Action', 'w9-1099-chaser' ); ?></th>
												</tr>
											</thead>
											<tbody class="bg-white divide-y" style="border-color: var(--mp-gray-200);">
												<?php
												foreach ( $manual_plugins as $mp ) :
													if ( ! is_array( $mp ) ) {
														continue;
													}
													$mp_slug = sanitize_title( (string) ( $mp['slug'] ?? '' ) );
													$mp_name = sanitize_text_field( (string) ( $mp['name'] ?? '' ) );
													if ( ! $mp_slug || ! $mp_name ) {
														continue;
													}
													?>
													<tr>
														<td class="px-4 py-3"><strong><?php echo esc_html( $mp_name ); ?></strong></td>
														<td class="px-4 py-3"><code><?php echo esc_html( $mp_slug ); ?></code></td>
														<td class="px-4 py-3">
															<button type="submit" name="settings_action" value="remove_manual_plugin" class="mp-btn-secondary">
																<i class="fas fa-trash"></i>
																<?php echo esc_html__( 'Remove', 'w9-1099-chaser' ); ?>
															</button>
															<input type="hidden" name="remove_manual_slug" value="<?php echo esc_attr( $mp_slug ); ?>" />
														</td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								<?php endif; ?>
							</div>

							<div class="pt-2 flex justify-end">
								<input type="hidden" name="settings_action" value="save" />
								<button type="submit" class="mp-btn-primary">
									<i class="fas fa-save"></i>
									<?php echo esc_html__( 'Save Settings', 'w9-1099-chaser' ); ?>
								</button>
							</div>
						</div>
						<?php endif; ?>

						<?php if ( $active_settings_tab === 'ecommerce-data' ) : ?>
							<?php
							$ecom_settings = get_option( 'w91099ch_ecommerce_data_settings', array() );
							if ( ! is_array( $ecom_settings ) ) {
								$ecom_settings = array();
							}
							$ecom_plugins = array(
								'woocommerce' => array(
									'label'  => 'WooCommerce',
									'icon'   => 'fa-cart-shopping',
									'fields' => array(
										'orders'        => 'Orders',
										'customers'     => 'Customers',
										'products'      => 'Products',
										'payments'      => 'Payments',
										'refunds'       => 'Refunds',
										'coupons'       => 'Coupons',
										'subscriptions' => 'Subscriptions',
										'payouts'       => 'Payouts',
										'vendors'       => 'Vendors',
									),
								),
								'dokan'       => array(
									'label'  => 'Dokan',
									'icon'   => 'fa-store',
									'fields' => array(
										'vendors'   => 'Vendors',
										'orders'    => 'Orders',
										'customers' => 'Customers',
										'products'  => 'Products',
										'payouts'   => 'Payouts',
										'refunds'   => 'Refunds',
									),
								),
								'wcfm'        => array(
									'label'  => 'WCFM',
									'icon'   => 'fa-people-group',
									'fields' => array(
										'vendors'   => 'Vendors',
										'orders'    => 'Orders',
										'customers' => 'Customers',
										'products'  => 'Products',
										'payouts'   => 'Payouts',
										'refunds'   => 'Refunds',
									),
								),
								'stripe'      => array(
									'label'  => 'Stripe',
									'icon'   => 'fa-credit-card',
									'fields' => array(
										'payments' => 'Payments',
										'refunds'  => 'Refunds',
										'payouts'  => 'Payouts',
									),
								),
								'paypal'      => array(
									'label'  => 'PayPal',
									'icon'   => 'fa-wallet',
									'fields' => array(
										'payments' => 'Payments',
										'refunds'  => 'Refunds',
										'payouts'  => 'Payouts',
									),
								),
							);
							?>
							<div class="space-y-10">
								<div>
									<div class="mp-card-header">
										<div>
											<h2 class="text-xl font-bold text-gray-800 mb-1 flex items-center gap-3">
												<div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center">
													<i class="fas fa-cart-shopping text-white"></i>
												</div>
												<?php echo esc_html__( 'Ecommerce Data Settings', 'w9-1099-chaser' ); ?>
											</h2>
											<p class="text-sm" style="color: var(--mp-gray-600);">
												<?php echo esc_html__( 'Choose what ecommerce data to include when syncing ecommerce events to the webhook.', 'w9-1099-chaser' ); ?>
										</p>
									</div>
								</div>

								<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
									<div class="lg:col-span-2 space-y-4">
										<input type="hidden" name="w91099ch_ecommerce_data_settings_present" value="1" />
										<?php foreach ( $ecom_plugins as $pkey => $meta ) : ?>
											<?php
												$curr = isset( $ecom_settings[ $pkey ] ) && is_array( $ecom_settings[ $pkey ] ) ? $ecom_settings[ $pkey ] : array();
												$enabled = isset( $curr['enabled'] ) ? (bool) $curr['enabled'] : false;
												$fields  = ( isset( $curr['fields'] ) && is_array( $curr['fields'] ) ) ? $curr['fields'] : array();
											?>
											<details class="mp-card p-5" <?php echo $enabled ? 'open' : ''; ?> style="border: 1px solid var(--mp-gray-200);">
												<summary class="flex items-center justify-between gap-4" style="cursor: pointer; list-style: none;">
													<div class="flex items-center gap-3">
														<div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center">
															<i class="fas <?php echo esc_attr( (string) $meta['icon'] ); ?>" style="color: var(--mp-primary);"></i>
														</div>
														<div>
															<div class="font-extrabold text-gray-900" style="font-size: 15px;">
																<?php echo esc_html( (string) $meta['label'] ); ?>
															</div>
															<div class="text-sm" style="color: var(--mp-gray-600);">
																<?php echo esc_html__( 'Select what to sync', 'w9-1099-chaser' ); ?>
															</div>
														</div>
													</div>
													<label class="flex items-center gap-2" style="margin: 0;">
														<input type="checkbox" name="w91099ch_ecom_enabled_<?php echo esc_attr( $pkey ); ?>" value="1" <?php checked( $enabled ); ?> />
														<span class="text-sm font-semibold text-gray-800"><?php echo esc_html__( 'Enable', 'w9-1099-chaser' ); ?></span>
													</label>
												</summary>
												<div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
													<?php foreach ( (array) $meta['fields'] as $fname => $flabel ) : ?>
														<?php $is_checked = isset( $fields[ $fname ] ) ? (bool) $fields[ $fname ] : false; ?>
														<label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 bg-gray-50 cursor-pointer">
															<input type="checkbox" class="mt-1" name="w91099ch_ecom_fields_<?php echo esc_attr( $pkey ); ?>[]" value="<?php echo esc_attr( $fname ); ?>" <?php checked( $is_checked ); ?> />
															<div>
																<div class="font-bold text-gray-800"><?php echo esc_html( (string) $flabel ); ?></div>
																<div class="text-xs text-gray-600"><?php echo esc_html__( 'Include in ecommerce sync', 'w9-1099-chaser' ); ?></div>
															</div>
														</label>
													<?php endforeach; ?>
												</div>
											</details>
										<?php endforeach; ?>
									</div>

									<div class="lg:col-span-1">
										<div class="mp-card p-6" style="border: 1px solid var(--mp-gray-200);">
											<div class="text-sm text-gray-700 mb-4">
												<?php echo esc_html__( 'Save your ecommerce sync preferences. These settings control what data is included when you click “Sync Ecommerce Data”.', 'w9-1099-chaser' ); ?>
											</div>
										</div>
									</div>
								</div>

								<div class="pt-2 flex justify-end">
									<input type="hidden" name="settings_action" value="save" />
									<button type="submit" class="mp-btn-primary" style="min-width: 200px;">
										<i class="fas fa-save"></i>
										<?php echo esc_html__( 'Save Settings', 'w9-1099-chaser' ); ?>
									</button>
								</div>
							</div>
						</div>
						</div>
					<?php endif; ?>
					</form>
				</div>
			</div>
		</div>

		<script>
			(function() {
				function toggleSelectedPages(method) {
					var container = document.getElementById('selected-pages-container');
					if (!container) return;
					container.style.display = (method === 'selected') ? '' : 'none';
				}

				window.toggleSelectedPages = toggleSelectedPages;

				document.addEventListener('DOMContentLoaded', function() {
					var checked = document.querySelector('input[name="w91099ch_w9_display_method"]:checked');
					if (checked) {
						toggleSelectedPages(checked.value);
					}

					var radios = document.querySelectorAll('input[name="w91099ch_w9_display_method"]');
					radios.forEach(function(radio) {
						radio.addEventListener('change', function() {
							toggleSelectedPages(this.value);
						});
					});
				});
			})();
		</script>

		<?php
	}

	public function render_features_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'w9-1099-chaser' ) );
		}

		include_once w91099ch_PLUGIN_PATH . 'admin/views/features-page.php';
	}

	public function enqueue_scripts( $hook ) {
		if ( 'plugins.php' === $hook ) {
			wp_enqueue_script( 'w9-deactivation-feedback', w91099ch_PLUGIN_URL . 'assets/js/w9-deactivation-feedback.js', array( 'jquery' ), w91099ch_VERSION, true );
			wp_localize_script( 'w9-deactivation-feedback', 'w91099chAdmin', array(
				'nonce' => wp_create_nonce( 'w91099ch_admin_nonce' ),
			) );
		}

		$payment_limit_enabled = (bool) get_option( 'w91099ch_payment_limit_enabled', false );
		$payment_limit_amount  = (float) get_option( 'w91099ch_payment_limit_amount', 0 );
		$payment_limit_period  = (string) get_option( 'w91099ch_payment_limit_period', 'month' );
		$payment_limit_action  = (string) get_option( 'w91099ch_payment_limit_action', 'block' );

		if ( $payment_limit_enabled && $payment_limit_amount > 0 ) {
			$period = strtolower( trim( $payment_limit_period ) );
			if ( ! in_array( $period, array( 'day', 'week', 'month' ), true ) ) {
				$period = 'month';
			}
			$now = function_exists( 'current_time' ) ? (int) current_time( 'timestamp', true ) : time();
			if ( 'day' === $period ) {
				$period_key = 'day:' . gmdate( 'Y-m-d', $now );
			} elseif ( 'week' === $period ) {
				$period_key = 'week:' . gmdate( 'o-W', $now );
			} else {
				$period_key = 'month:' . gmdate( 'Y-m', $now );
			}

			$stored = get_option( 'w91099ch_payment_limit_totals', array() );
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
			$current_total = isset( $stored[ $period_key ] ) && is_numeric( $stored[ $period_key ] ) ? (float) $stored[ $period_key ] : 0.0;
			$remaining     = max( 0.0, (float) $payment_limit_amount - $current_total );

			wp_register_style( 'w91099ch-payment-limit-admin-inline', false, array(), '1.0.0' );
			wp_enqueue_style( 'w91099ch-payment-limit-admin-inline' );
			wp_add_inline_style(
				'w91099ch-payment-limit-admin-inline',
				'.w91099ch-limit-exceeded{color:#dc2626 !important;font-weight:700 !important;cursor:help !important;text-decoration:underline dotted !important;}'
			);

			wp_register_script( 'w91099ch-payment-limit-admin-inline', false, array( 'jquery' ), '1.0.0', true );
			wp_enqueue_script( 'w91099ch-payment-limit-admin-inline' );
			wp_localize_script(
				'w91099ch-payment-limit-admin-inline',
				'w91099chPaymentLimitUi',
				array(
					'enabled'        => true,
					'limit_amount'   => (float) $payment_limit_amount,
					'period'         => (string) $period,
					'period_key'     => (string) $period_key,
					'current_total'  => (float) $current_total,
					'remaining'      => (float) $remaining,
					'tooltip'        => (string) __( 'This payout amount exceeds your remaining global payout limit configured in W9-1099 Chaser → Settings → Alarms & Alerts Settings.', 'w9-1099-chaser' ),
					'tooltip_prefix' => (string) __( 'Limit exceeded:', 'w9-1099-chaser' ),
				)
			);
			wp_add_inline_script(
				'w91099ch-payment-limit-admin-inline',
				"(function($){\n" .
				"  'use strict';\n" .
				"  var cfg = window.w91099chPaymentLimitUi || {};\n" .
				"  if (!cfg.enabled) { return; }\n" .
				"  cfg.remaining = (cfg.remaining === undefined || cfg.remaining === null || cfg.remaining === '') ? 0 : parseFloat(cfg.remaining);\n" .
				"  if (!isFinite(cfg.remaining)) { cfg.remaining = 0; }\n" .
				"  function isPendingText(text){\n" .
				"    var s = String(text || '').toLowerCase();\n" .
				"    if (!s) return false;\n" .
				"    return (\n" .
				"      s.indexOf('pending') !== -1 ||\n" .
				"      s.indexOf('unpaid') !== -1 ||\n" .
				"      s.indexOf('not paid') !== -1 ||\n" .
				"      s.indexOf('due') !== -1 ||\n" .
				"      s.indexOf('ready') !== -1 ||\n" .
				"      s.indexOf('payable') !== -1 ||\n" .
				"      s.indexOf('processing') !== -1 ||\n" .
				"      s.indexOf('on-hold') !== -1 ||\n" .
				"      s.indexOf('on hold') !== -1\n" .
				"    );\n" .
				"  }\n" .
				"  function parseAmount(text){\n" .
				"    var t = String(text || '').trim();\n" .
				"    if (!t) return 0;\n" .
				"    t = t.replace(/[,]/g,'');\n" .
				"    var m = t.match(/-?\$?[\s,]*([0-9,]+(?:\.[0-9]+)?)/);\n" .
				"    if (!m) return 0;\n" .
				"    var n = parseFloat(m[1].replace(/,/g, ''));\n" .
				"    return isFinite(n) ? n : 0;\n" .
				"  }\n" .
				"  function isKeyword(text){\n" .
				"    var s = String(text || '').toLowerCase();\n" .
				"    return (\n" .
				"      s.indexOf('amount') !== -1 ||\n" .
				"      s.indexOf('payout') !== -1 ||\n" .
				"      s.indexOf('earnings') !== -1 ||\n" .
				"      s.indexOf('commission') !== -1 ||\n" .
				"      s.indexOf('total') !== -1 ||\n" .
				"      s.indexOf('payment') !== -1 ||\n" .
				"      s.indexOf('balance') !== -1\n" .
				"    );\n" .
				"  }\n" .
				"  function markExceeded($el, amount){\n" .
				"    if (!$el || !$el.length) return;\n" .
				"    var $tr = $el.closest('tr');\n" .
				"    var $target = $tr.length ? $tr : $el;\n" .
				"    \n" .
				"    if ($target.hasClass('w91099ch-limit-exceeded-row')) return;\n" .
				"    \n" .
				"    $target.addClass('w91099ch-limit-exceeded-row');\n" .
				"    $target.css({\n" .
				"      'background-color': '#fee2e2',\n" .
				"      'border-left': '4px solid #ef4444'\n" .
				"    });\n" .
				"    \n" .
				"    if ($tr.length) {\n" .
				"      var $lastTd = $tr.find('td,th').last();\n" .
				"      if ($lastTd.length && $lastTd.find('.w91099ch-limit-label').length === 0) {\n" .
				"        $lastTd.append('<span class=\"w91099ch-limit-label\" style=\"display:inline-block; margin-left:8px; padding:2px 6px; background:#ef4444; color:#fff; border-radius:4px; font-size:10px; font-weight:bold; vertical-align:middle;\">Limit Exceeded</span>');\n" .
				"      }\n" .
				"    }\n" .
				"    \n" .
				"    var title = (cfg.tooltip_prefix ? (cfg.tooltip_prefix + ' ') : '') + (cfg.tooltip || '');\n" .
				"    try {\n" .
				"      var extra = ' Limit: ' + cfg.limit_amount + ' | This: ' + amount;\n" .
				"      title = title + extra;\n" .
				"    } catch(e) {}\n" .
				"    $el.attr('title', title);\n" .
				"  }\n" .
				"  function scanTables(){\n" .
				"    $('table').each(function(){\n" .
				"      var $table = $(this);\n" .
				"      var idxs = [];\n" .
				"      var dueIdxs = [];\n" .
				"      var statusIdxs = [];\n" .
				"      $table.find('thead th').each(function(i){\n" .
				"        var txt = $(this).text();\n" .
				"        if (isKeyword(txt)) { idxs.push(i); }\n" .
				"        var ht = String(txt || '').toLowerCase();\n" .
				"        if (ht.indexOf('pending') !== -1 || ht.indexOf('unpaid') !== -1 || ht.indexOf('outstanding') !== -1 || ht.indexOf('due') !== -1 || ht.indexOf('payable') !== -1) {\n" .
				"          dueIdxs.push(i);\n" .
				"        }\n" .
				"        var norm = String(txt || '').toLowerCase();\n" .
				"        if (norm.indexOf('status') !== -1 || norm.indexOf('state') !== -1) { statusIdxs.push(i); }\n" .
				"      });\n" .
				"      if (!idxs.length && !dueIdxs.length) return;\n" .
				"      $table.find('tbody tr').each(function(){\n" .
				"        var $tr = $(this);\n" .
				"        if ($tr.hasClass('w91099ch-limit-exceeded-row')) return;\n" .
				"        \n" .
				"        var isPendingRow = true;\n" .
				"        if (statusIdxs.length) {\n" .
				"          isPendingRow = false;\n" .
				"          statusIdxs.forEach(function(si){\n" .
				"            var $st = $tr.children('td,th').eq(si);\n" .
				"            if ($st.length && isPendingText($st.text())) { isPendingRow = true; }\n" .
				"          });\n" .
				"        }\n" .
				"        \n" .
				"        if (dueIdxs.length) {\n" .
				"          dueIdxs.forEach(function(di){\n" .
				"            var $due = $tr.children('td,th').eq(di);\n" .
				"            if (!$due.length) return;\n" .
				"            var dueAmt = parseAmount($due.text());\n" .
				"            if (dueAmt >= cfg.limit_amount) { markExceeded($due, dueAmt); }\n" .
				"          });\n" .
				"        }\n" .
				"        \n" .
				"        if (!isPendingRow) {\n" .
				"          // Even if not pending, check for amounts if they are definitively large\n" .
				"          idxs.forEach(function(i){\n" .
				"            var $td = $tr.children('td,th').eq(i);\n" .
				"            if (!$td.length) return;\n" .
				"            var amount = parseAmount($td.text());\n" .
				"            if (amount >= cfg.limit_amount) { markExceeded($td, amount); }\n" .
				"          });\n" .
				"          return;\n" .
				"        }\n" .
				"        \n" .
				"        idxs.forEach(function(i){\n" .
				"          var $td = $tr.children('td,th').eq(i);\n" .
				"          if (!$td.length) return;\n" .
				"          var amount = parseAmount($td.text());\n" .
				"          if (amount >= cfg.limit_amount) { markExceeded($td, amount); }\n" .
				"        });\n" .
				"      });\n" .
				"    });\n" .
				"  }\n" .
				"  function scanStandalone(){\n" .
				"    var selectors = [\n" .
				"      '.amount',\n" .
				"      '.pending-amount',\n" .
				"      '.payout-amount',\n" .
				"      '.order-total',\n" .
				"      '.total',\n" .
				"      '[class*=\\\"amount\\\"]',\n" .
				"      '[class*=\\\"payout\\\"]',\n" .
				"      '[class*=\\\"withdraw\\\"]',\n" .
				"      '[class*=\\\"transfer\\\"]',\n" .
				"      '[class*=\\\"payment\\\"]',\n" .
				"      '[id*=\\\"amount\\\"]',\n" .
				"      '[id*=\\\"payout\\\"]',\n" .
				"      '[id*=\\\"payment\\\"]'\n" .
				"    ];\n" .
				"    $(selectors.join(',')).each(function(){\n" .
				"      var $el = $(this);\n" .
				"      if ($el.closest('table').length) return;\n" .
				"      var amount = 0;\n" .
				"      if ($el.is('input,textarea,select')) {\n" .
				"        amount = parseAmount($el.val());\n" .
				"      } else {\n" .
				"        amount = parseAmount($el.text());\n" .
				"      }\n" .
				"      if (amount >= cfg.limit_amount) { markExceeded($el, amount); }\n" .
				"    });\n" .
				"  }\n" .
				"  function scanFormFields(){\n" .
				"    var fields = $('input[name], select[name], textarea[name]').filter(function(){\n" .
				"      var n = String($(this).attr('name') || '').toLowerCase();\n" .
				"      var id = String($(this).attr('id') || '').toLowerCase();\n" .
				"      return isKeyword(n) || isKeyword(id);\n" .
				"    });\n" .
				"    fields.each(function(){\n" .
				"      var $f = $(this);\n" .
				"      var amount = parseAmount($f.val());\n" .
				"      if (amount >= cfg.limit_amount) { markExceeded($f, amount); }\n" .
				"    });\n" .
				"  }\n" .
				"  function scanButtons(){\n" .
				"    $('button, a, input[type=submit], input[type=button]').each(function(){\n" .
				"      var $b = $(this);\n" .
				"      var txt = ($b.is('input') ? $b.val() : $b.text()) || '';\n" .
				"      var aria = String($b.attr('aria-label') || '');\n" .
				"      var title = String($b.attr('title') || '');\n" .
				"      var ctx = txt + ' ' + aria + ' ' + title;\n" .
				"      if (!isKeyword(ctx) && !isPendingText(ctx)) return;\n" .
				"      if (!isPendingText(ctx) && ctx.toLowerCase().indexOf('pay') === -1 && ctx.toLowerCase().indexOf('payout') === -1) { return; }\n" .
				"      var amount = parseAmount(ctx);\n" .
				"      if (amount >= cfg.limit_amount) { markExceeded($b, amount); }\n" .
				"    });\n" .
				"  }\n" .
				"  function run(){\n" .
				"    scanTables();\n" .
				"    scanStandalone();\n" .
				"    scanFormFields();\n" .
				"    scanButtons();\n" .
				"  }\n" .
				"  // Initial run\n" .
				"  $(run);\n" .
				"  // Handle AJAX and dynamic content (Production-ready robust observer)\n" .
				"  var debounceTimeout;\n" .
				"  var observer = new MutationObserver(function(mutations) {\n" .
				"    clearTimeout(debounceTimeout);\n" .
				"    debounceTimeout = setTimeout(function() {\n" .
				"      run();\n" .
				"    }, 500);\n" .
				"  });\n" .
				"  if (document.body) {\n" .
				"    observer.observe(document.body, {\n" .
				"      childList: true,\n" .
				"      subtree: true\n" .
				"    });\n" .
				"  }\n" .
				"  // Fallback timers for insurance\n" .
				"  setTimeout(run, 700);\n" .
				"  setTimeout(run, 1800);\n" .
				"  setTimeout(run, 5000);\n" .
				"})(jQuery));",
				'after'
			);
		}

		if (
			$hook === 'toplevel_page_w9-1099-chaser' || $hook === 'toplevel_page_w91099ch' ||
			strpos( (string) $hook, 'w91099ch' ) !== false ||
			strpos( (string) $hook, 'w9-1099-chaser' ) !== false
		) {
			$fontawesome_css_path = w91099ch_PLUGIN_PATH . 'assets/vendor/fontawesome/css/all.min.css';
			$fontawesome_css_ver  = file_exists( $fontawesome_css_path ) ? filemtime( $fontawesome_css_path ) : '6.5.1';

			wp_enqueue_style( 'w9-1099-chaser-admin', w91099ch_PLUGIN_URL . 'assets/css/w9-1099-chaser-admin.css', array(), '1.0.0' );

			// Vendor CSS (locally bundled to comply with .org)
			wp_enqueue_style( 'w9-1099-chaser-tailwind', w91099ch_PLUGIN_URL . 'assets/css/vendor/tailwind-2.2.19.min.css', array(), '2.2.19' );
			wp_enqueue_style( 'w9-1099-chaser-fontawesome', w91099ch_PLUGIN_URL . 'assets/vendor/fontawesome/css/all.min.css', array(), $fontawesome_css_ver );
			wp_enqueue_style( 'w9-1099-chaser-inter', w91099ch_PLUGIN_URL . 'assets/css/vendor/inter.css', array(), '1.0.0' );

			// Settings Forms CSS - Always load for all plugin pages
			wp_enqueue_style( 'w9-1099-chaser-settings-forms', w91099ch_PLUGIN_URL . 'assets/css/w9-1099-chaser-settings-forms.css', array( 'w9-1099-chaser-tailwind', 'w9-1099-chaser-fontawesome', 'w9-1099-chaser-admin' ), '1.0.0' );

			// Settings-specific inline CSS
			if ( $hook === 'w9-1099-chaser_page_w9-1099-chaser-settings' || $hook === 'w91099ch_page_w91099ch-settings' || strpos( (string) $hook, 'w91099ch-settings' ) !== false ) {
				wp_register_style( 'w9-1099-chaser-settings-inline', false, array( 'w9-1099-chaser-settings-forms' ), '1.0.0' );
				wp_enqueue_style( 'w9-1099-chaser-settings-inline' );
				wp_add_inline_style( 'w9-1099-chaser-settings-inline', $this->get_settings_inline_css() );
			}
		}

		if (
			strpos( (string) $hook, 'w91099ch' ) !== false ||
			strpos( (string) $hook, 'w9-1099-chaser' ) !== false
		) {
			$admin_inline_css_path = w91099ch_PLUGIN_PATH . 'assets/css/w9-1099-chaser-admin-page-inline.css';
			$admin_inline_js_path  = w91099ch_PLUGIN_PATH . 'assets/js/w9-1099-chaser-admin-page-inline.js';
			$admin_inline_css_ver  = file_exists( $admin_inline_css_path ) ? filemtime( $admin_inline_css_path ) : '1.0.0';
			$admin_inline_js_ver   = file_exists( $admin_inline_js_path ) ? filemtime( $admin_inline_js_path ) : '1.0.0';

			wp_enqueue_style(
				'w9-1099-chaser-admin-page-inline',
				w91099ch_PLUGIN_URL . 'assets/css/w9-1099-chaser-admin-page-inline.css',
				array( 'w9-1099-chaser-tailwind', 'w9-1099-chaser-fontawesome', 'w9-1099-chaser-inter' ),
				$admin_inline_css_ver
			);

			if (
				strpos( (string) $hook, 'w91099ch' ) !== false ||
				strpos( (string) $hook, 'w9-1099-chaser' ) !== false
			) {
				wp_enqueue_script(
					'w9-1099-chaser-admin-page-inline',
					w91099ch_PLUGIN_URL . 'assets/js/w9-1099-chaser-admin-page-inline.js',
					array( 'jquery' ),
					$admin_inline_js_ver,
					true
				);

				wp_add_inline_script(
					'w9-1099-chaser-admin-page-inline',
					$this->get_w9_admin_tab_inline_js(),
					'after'
				);

				wp_add_inline_script(
					'w9-1099-chaser-admin-page-inline',
					"(function(){\n" .
					"  if (window.__w91099chSupportFloatsInit) { return; }\n" .
					"  window.__w91099chSupportFloatsInit = true;\n" .
					"  function encodeQS(obj){\n" .
					"    var p=[];\n" .
					"    for (var k in obj){ if (!Object.prototype.hasOwnProperty.call(obj,k)) continue; p.push(encodeURIComponent(k)+'='+encodeURIComponent(obj[k]||'')); }\n" .
					"    return p.join('&');\n" .
					"  }\n" .
					"  function openGmailCompose(to, subject, body){\n" .
					"    var base='https://mail.google.com/mail/?view=cm&fs=1';\n" .
					"    var url=base + '&' + encodeQS({to: to, su: subject, body: body});\n" .
					"    try {\n" .
					"      window.open(url,'_blank','noopener,noreferrer');\n" .
					"    } catch (e) {}\n" .
					"  }\n" .
					"  function ensureStyle(){\n" .
					"    if (document.getElementById('w91099ch-support-floats-style')) return;\n" .
					"    var s=document.createElement('style');\n" .
					"    s.id='w91099ch-support-floats-style';\n" .
					"    s.textContent=\"\\n" .
					"#w91099ch-support-float-right,#w91099ch-support-float-left{position:fixed;bottom:22px;z-index:99999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif}\\n" .
					"#w91099ch-support-float-right{right:18px}\\n" .
					"#w91099ch-support-float-left{left:calc(160px + 22px)}\\n" .
					"body.folded #w91099ch-support-float-left{left:calc(36px + 22px)}\\n" .
					"@media (max-width:782px){#w91099ch-support-float-left{left:18px}}\\n" .
					".w91099ch-support-btn{position:relative;display:inline-flex;align-items:center;gap:12px;border:0;border-radius:16px;padding:12px 14px;font-size:13px;font-weight:800;letter-spacing:.1px;cursor:pointer;box-shadow:0 14px 30px rgba(0,0,0,.18);transition:transform .18s ease,box-shadow .18s ease,filter .18s ease;outline:none;min-width:190px}\\n" .
					".w91099ch-support-btn:hover{transform:translateY(-2px);box-shadow:0 16px 34px rgba(0,0,0,.22);filter:brightness(1.02)}\\n" .
					".w91099ch-support-btn:active{transform:translateY(0)}\\n" .
					".w91099ch-support-btn:focus{box-shadow:0 0 0 3px rgba(99,102,241,.28),0 14px 30px rgba(0,0,0,.18)}\\n" .
					".w91099ch-support-btn-feedback{background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%);color:#fff}\\n" .
					".w91099ch-support-btn-help{background:linear-gradient(135deg,#0f172a 0%,#111827 100%);color:#fff}\\n" .
					".w91099ch-support-pill{width:34px;height:34px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,.18);backdrop-filter:saturate(140%) blur(6px)}\\n" .
					".w91099ch-support-text{display:flex;flex-direction:column;align-items:flex-start;line-height:1.1}\\n" .
					".w91099ch-support-title{font-size:13px;font-weight:900;color:#fff}\\n" .
					".w91099ch-support-subtitle{margin-top:4px;font-size:11px;font-weight:700;color:rgba(255,255,255,.78)}\\n" .
					".w91099ch-support-badge{position:absolute;top:-10px;right:-10px;background:#22c55e;color:#062a16;font-size:10px;font-weight:900;padding:4px 8px;border-radius:999px;box-shadow:0 10px 18px rgba(0,0,0,.18)}\\n" .
					".w91099ch-support-btn-help .w91099ch-support-badge{background:#60a5fa;color:#0b1b3a}\\n" .
					"@keyframes w91099chRing{0%{opacity:.55;transform:scale(.92)}60%{opacity:0;transform:scale(1.18)}100%{opacity:0;transform:scale(1.18)}}\\n" .
					".w91099ch-support-btn::after{content:'';position:absolute;inset:-6px;border-radius:20px;border:2px solid rgba(255,255,255,.35);opacity:0;pointer-events:none}\\n" .
					".w91099ch-support-btn.w91099ch-attn::after{opacity:1;animation:w91099chRing 1.8s ease-out infinite}\\n" .
					"@keyframes w91099chFloatPulse{0%{transform:translateY(0);box-shadow:0 14px 30px rgba(0,0,0,.18)}50%{transform:translateY(-2px);box-shadow:0 18px 38px rgba(0,0,0,.24)}100%{transform:translateY(0);box-shadow:0 14px 30px rgba(0,0,0,.18)}}\\n" .
					".w91099ch-support-btn{animation:w91099chFloatPulse 2.4s ease-in-out infinite}\\n" .
					"@media (prefers-reduced-motion: reduce){.w91099ch-support-btn{animation:none}}\\n" .
					"\\n\";\n" .
					"    document.head.appendChild(s);\n" .
					"  }\n" .
					"  function ensureButtons(){\n" .
					"    ensureStyle();\n" .
					"    if (!document.getElementById('w91099ch-support-float-left')){\n" .
					"      var wrapL=document.createElement('div');\n" .
					"      wrapL.id='w91099ch-support-float-left';\n" .
					"      var btnL=document.createElement('button');\n" .
					"      btnL.type='button';\n" .
					"      btnL.className='w91099ch-support-btn w91099ch-support-btn-help';\n" .
					"      btnL.classList.add('w91099ch-attn');\n" .
					"      btnL.innerHTML='<span class=\"w91099ch-support-badge\">Support</span><span class=\"w91099ch-support-pill\">💬</span><span class=\"w91099ch-support-text\"><span class=\"w91099ch-support-title\">Need Help?</span><span class=\"w91099ch-support-subtitle\">Email 1099automation@gmail.com</span></span>';\n" .
					"      btnL.addEventListener('click',function(){\n" .
					"        var subject='Vendor Onboarding W9-1099 Chaser by Mypowerly Support';\n" .
					"        var body='Hi Support Team, I need help with... Thanks!\\n\\nWebsite: ' + String(window.location.origin || '') + (window.location.href ? ('\\nPage: ' + String(window.location.href)) : '');\n" .
					"        var adminEmail='" . esc_js( sanitize_email( get_option( 'admin_email' ) ) ) . "';\n" .
					"        var to='1099automation@gmail.com' + (adminEmail ? (',' + adminEmail) : '');\n" .
					"        openGmailCompose(to, subject, body);\n" .
					"      });\n" .
					"      wrapL.appendChild(btnL);\n" .
					"      document.body.appendChild(wrapL);\n" .
					"    }\n" .
					"  }\n" .
					"  if (document.readyState==='loading'){\n" .
					"    document.addEventListener('DOMContentLoaded', ensureButtons);\n" .
					"  } else {\n" .
					"    ensureButtons();\n" .
					"  }\n" .
					"})();",
					'after'
				);
			}
		}

		if (
			strpos( (string) $hook, 'w91099ch' ) !== false ||
			strpos( (string) $hook, 'w9-1099-chaser' ) !== false
		) {
			$w9_css_path = w91099ch_PLUGIN_PATH . 'assets/css/w9-1099-chaser-w9-form.css';
			$w9_js_path  = w91099ch_PLUGIN_PATH . 'assets/js/w9-1099-chaser-w9-form.js';
			$feedback_js_path = w91099ch_PLUGIN_PATH . 'assets/js/w9-feedback-popup.js';
			$w9_css_ver  = file_exists( $w9_css_path ) ? filemtime( $w9_css_path ) : '1.0.0';
			$w9_js_ver   = file_exists( $w9_js_path ) ? filemtime( $w9_js_path ) : '1.0.0';
			$feedback_js_ver = file_exists( $feedback_js_path ) ? filemtime( $feedback_js_path ) : '1.0.0';

			wp_enqueue_style( 'w9-1099-chaser-w9-form', w91099ch_PLUGIN_URL . 'assets/css/w9-1099-chaser-w9-form.css', array(), $w9_css_ver );

			$pdf_lib_file = file_exists( w91099ch_PLUGIN_PATH . 'assets/js/vendor/pdf-lib.js' )
				? 'pdf-lib.js'
				: 'pdf-lib.min.js';

			$signature_pad_file = file_exists( w91099ch_PLUGIN_PATH . 'assets/js/vendor/signature_pad.umd.js' )
				? 'signature_pad.umd.js'
				: 'signature_pad.umd.min.js';

			wp_enqueue_script(
				'w9-1099-chaser-pdf-lib',
				w91099ch_PLUGIN_URL . 'assets/js/vendor/' . $pdf_lib_file,
				array(),
				'1.17.1',
				true
			);

			wp_enqueue_script(
				'signature-pad',
				w91099ch_PLUGIN_URL . 'assets/js/vendor/' . $signature_pad_file,
				array(),
				'5.1.3',
				true
			);

			wp_enqueue_script(
				'w9-feedback-popup',
				w91099ch_PLUGIN_URL . 'assets/js/w9-feedback-popup.js',
				array( 'jquery' ),
				$feedback_js_ver,
				true
			);
			wp_localize_script(
				'w9-feedback-popup',
				'w91099chFeedbackConfig',
				array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'w91099ch_feedback_nonce' ),
				)
			);

			wp_enqueue_script(
				'w9-1099-chaser-w9-form',
				w91099ch_PLUGIN_URL . 'assets/js/w9-1099-chaser-w9-form.js',
				array( 'jquery', 'w9-1099-chaser-pdf-lib', 'signature-pad', 'w9-feedback-popup' ),
				$w9_js_ver,
				true
			);

			wp_add_inline_style(
				'w9-1099-chaser-w9-form',
				'
                .signature-pad {
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    background: #fff;
                    cursor: crosshair;
                    margin: 10px 0;
                }
                .signature-actions {
                    margin: 10px 0;
                }
                .signature-actions button {
                    margin-right: 10px;
                }'
			);

			$logo_path     = w91099ch_PLUGIN_PATH . 'assets/logo/logo for plugin.png';
			$logo_data_url = '';
			if ( file_exists( $logo_path ) && is_readable( $logo_path ) ) {
				$logo_bytes = '';
				if ( ! function_exists( 'WP_Filesystem' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}
				$fs_ready = function_exists( 'WP_Filesystem' ) ? WP_Filesystem() : false;
				if ( $fs_ready ) {
					global $wp_filesystem;
					if ( isset( $wp_filesystem ) && is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'get_contents' ) ) {
						$logo_bytes = $wp_filesystem->get_contents( $logo_path );
					}
				}
				if ( ! $logo_bytes && function_exists( 'file_get_contents' ) ) {
					$direct_bytes = @file_get_contents( $logo_path );
					if ( is_string( $direct_bytes ) && '' !== $direct_bytes ) {
						$logo_bytes = $direct_bytes;
					}
				}
				if ( $logo_bytes ) {
					$logo_data_url = 'data:image/png;base64,' . base64_encode( $logo_bytes );
				}
			}

			wp_localize_script(
				'w9-1099-chaser-w9-form',
				'w91099chW9Form',
				array(
					'ajaxurl'    => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'w91099ch_w9_form_nonce' ),
					'pdf_action' => 'w91099ch_get_fw9_pdf',
					'logo_url'   => w91099ch_PLUGIN_URL . 'assets/logo/logo%20for%20plugin.png',
					'logo_data'  => $logo_data_url,
					'allowEarnRewardDownload' => get_option( 'w91099ch_allow_earn_reward_download', 'yes' ),
					'messages'   => array(
						'error'       => esc_html__( 'An error occurred. Please try again.', 'w9-1099-chaser' ),
						'downloading' => esc_html__( 'Downloading W-9 form...', 'w9-1099-chaser' ),
						'success'     => esc_html__( 'W-9 form downloaded successfully!', 'w9-1099-chaser' ),
					),
				)
			);
		}

		$admin_js_path = w91099ch_PLUGIN_PATH . 'assets/js/w9-1099-chaser-admin.js';
		$admin_js_ver  = file_exists( $admin_js_path ) ? filemtime( $admin_js_path ) : '1.0.0';
		wp_enqueue_script( 'w9-1099-chaser-admin', w91099ch_PLUGIN_URL . 'assets/js/w9-1099-chaser-admin.js', array( 'jquery' ), $admin_js_ver, true );

		$payment_limit_scanner_js_path = w91099ch_PLUGIN_PATH . 'assets/js/payment-limit-table-scanner.js';
		$payment_limit_scanner_js_ver  = file_exists( $payment_limit_scanner_js_path ) ? filemtime( $payment_limit_scanner_js_path ) : '1.0.0';
		wp_enqueue_script(
			'w91099ch-payment-limit-table-scanner',
			w91099ch_PLUGIN_URL . 'assets/js/payment-limit-table-scanner.js',
			array(),
			$payment_limit_scanner_js_ver,
			true
		);
		wp_localize_script(
			'w91099ch-payment-limit-table-scanner',
			'w91099chPaymentLimitScannerConfig',
				array(
					'enabled'       => (bool) get_option( 'w91099ch_payment_limit_enabled', false ),
					'limit'         => (float) get_option( 'w91099ch_payment_limit_amount', 200 ),
					'warning_label' => (string) __( 'Limit Exceeded', 'w9-1099-chaser' ),
					'plugin_name'   => 'W9-1099 Chaser',
					'tooltip_text'  => (string) __( 'This row was flagged by the {plugin} plugin because the detected payment amount ({amount}) is greater than or equal to your configured payment limit ({limit}).', 'w9-1099-chaser' ),
					'table_selector' => 'table',
					'scan_delay'    => 180,
				)
			);

		// Enqueue form plugins detector JS
		if (
			strpos( (string) $hook, 'w91099ch' ) !== false ||
			strpos( (string) $hook, 'w9-1099-chaser' ) !== false
		) {
			$form_js_path = w91099ch_PLUGIN_PATH . 'assets/js/form-plugins-detector.js';
			$form_js_ver  = file_exists( $form_js_path ) ? filemtime( $form_js_path ) : '1.0.0';
			wp_enqueue_script( 'w9-1099-chaser-form-plugins', w91099ch_PLUGIN_URL . 'assets/js/form-plugins-detector.js', array( 'jquery', 'w9-1099-chaser-admin' ), $form_js_ver, true );
		}

		// Enqueue contractor/service plugins detector JS
		if (
			strpos( (string) $hook, 'w91099ch' ) !== false ||
			strpos( (string) $hook, 'w9-1099-chaser' ) !== false
		) {
			$contractor_js_path = w91099ch_PLUGIN_PATH . 'assets/js/contractor-plugins-detector.js';
			$contractor_js_ver  = file_exists( $contractor_js_path ) ? filemtime( $contractor_js_path ) : '1.0.0';
			wp_enqueue_script( 'w9-1099-chaser-contractor-plugins', w91099ch_PLUGIN_URL . 'assets/js/contractor-plugins-detector.js', array( 'jquery', 'w9-1099-chaser-admin' ), $contractor_js_ver, true );
		}

		// Enqueue freelancer/contractor management plugins detector JS
		if (
			strpos( (string) $hook, 'w91099ch' ) !== false ||
			strpos( (string) $hook, 'w9-1099-chaser' ) !== false
		) {
			$fc_js_path = w91099ch_PLUGIN_PATH . 'assets/js/freelancer-contractor-plugins-detector.js';
			$fc_js_ver  = file_exists( $fc_js_path ) ? filemtime( $fc_js_path ) : '1.0.0';
			wp_enqueue_script( 'w9-1099-chaser-freelancer-contractor-plugins', w91099ch_PLUGIN_URL . 'assets/js/freelancer-contractor-plugins-detector.js', array( 'jquery', 'w9-1099-chaser-admin' ), $fc_js_ver, true );
		}

		// Enqueue accounting/bookkeeping plugins detector JS
		if (
			strpos( (string) $hook, 'w91099ch' ) !== false ||
			strpos( (string) $hook, 'w9-1099-chaser' ) !== false
		) {
			$ab_js_path = w91099ch_PLUGIN_PATH . 'assets/js/accounting-bookkeeping-plugins-detector.js';
			$ab_js_ver  = file_exists( $ab_js_path ) ? filemtime( $ab_js_path ) : '1.0.0';
			wp_enqueue_script( 'w9-1099-chaser-accounting-bookkeeping-plugins', w91099ch_PLUGIN_URL . 'assets/js/accounting-bookkeeping-plugins-detector.js', array( 'jquery', 'w9-1099-chaser-admin' ), $ab_js_ver, true );
		}

		// Enqueue wallet/payout plugins detector JS
		if (
			strpos( (string) $hook, 'w91099ch' ) !== false ||
			strpos( (string) $hook, 'w9-1099-chaser' ) !== false
		) {
			$wp_js_path = w91099ch_PLUGIN_PATH . 'assets/js/wallet-payout-plugins-detector.js';
			$wp_js_ver  = file_exists( $wp_js_path ) ? filemtime( $wp_js_path ) : '1.0.0';
			wp_enqueue_script( 'w9-1099-chaser-wallet-payout-plugins', w91099ch_PLUGIN_URL . 'assets/js/wallet-payout-plugins-detector.js', array( 'jquery', 'w9-1099-chaser-admin' ), $wp_js_ver, true );
			
			// Legacy wallet sync binder intentionally not enqueued.
			// Wallet/Payout sync is handled by assets/js/w9-1099-chaser-admin-page-inline.js
			// to keep a single source of truth and avoid duplicate click handlers.
			
			// Enqueue team sync JS
			$team_sync_js_path = w91099ch_PLUGIN_PATH . 'assets/js/team-sync.js';
			$team_sync_js_ver  = file_exists( $team_sync_js_path ) ? filemtime( $team_sync_js_path ) : '1.0.0';
			wp_enqueue_script( 'w9-1099-chaser-team-sync', w91099ch_PLUGIN_URL . 'assets/js/team-sync.js', array( 'jquery', 'w9-1099-chaser-admin' ), $team_sync_js_ver, true );
		}

		$affiliate_manager = new w91099ch_Affiliate_Manager();
		$detected_plugins  = $affiliate_manager->detect_affiliate_plugins();
		$total_affiliates  = $affiliate_manager->get_total_affiliates_count();

		$profile_last_sync    = get_option( 'w91099ch_profile_last_sync', 0 );
		$plugin_last_sync     = get_option( 'w91099ch_plugin_last_sync', 0 );
		$affiliates_last_sync = get_option( 'w91099ch_affiliates_last_sync', 0 );
		$affiliates_count     = get_option( 'w91099ch_affiliates_count', 0 );

		$current_user    = wp_get_current_user();
		$connected_email = (string) get_option( 'w91099ch_user_email', '' );
		$promo_email     = $connected_email !== '' ? $connected_email : ( ( $current_user && $current_user->user_email ) ? (string) $current_user->user_email : '' );
		$newsletter_subscribed = (bool) get_option( 'w91099ch_newsletter_subscribed', false );

		wp_localize_script(
			'w9-1099-chaser-admin',
			'w91099chConnector',
			array(
				'ajaxurl'             => admin_url( 'admin-ajax.php' ),
				'nonce'               => wp_create_nonce( 'w91099ch_nonce' ),
				'disconnect_nonce'    => wp_create_nonce( 'w91099ch_disconnect_nonce' ),
				'consent_nonce'       => wp_create_nonce( 'w91099ch_admin_consent' ),
				'has_admin_consent'   => $this->core->has_admin_consent(),
				'pending_credentials' => $this->core->get_pending_credentials(),
				'is_connected'        => $this->core->is_connected(),
				'admin_page_url'      => admin_url( 'admin.php?page=w91099ch' ),
				'detected_plugins'    => $detected_plugins,
				'total_affiliates'    => $total_affiliates,
				'user_email'          => $promo_email,
				'promo_plugin_id'     => 101,
				'sync_nonce'          => wp_create_nonce( 'w91099ch_sync_nonce' ),
				'last_sync_times'     => array(
					'profile'    => $profile_last_sync ? gmdate( 'Y-m-d H:i:s', (int) $profile_last_sync ) : 'Never',
					'plugin'     => $plugin_last_sync ? gmdate( 'Y-m-d H:i:s', (int) $plugin_last_sync ) : 'Never',
					'affiliates' => $affiliates_last_sync ? gmdate( 'Y-m-d H:i:s', (int) $affiliates_last_sync ) : 'Never',
				),
				'affiliates_count'    => $affiliates_count,
				'wallet_payout_nonce' => wp_create_nonce( 'w91099ch_nonce' ),
				'allowEarnRewardDownload' => get_option( 'w91099ch_allow_earn_reward_download', 'yes' ),
				'payment_limit_enabled' => get_option( 'w91099ch_payment_limit_enabled', false ),
				'payment_limit_amount' => get_option( 'w91099ch_payment_limit_amount', '0' ),
				'newsletter_subscribed' => $newsletter_subscribed,
				'newsletter_subscribe_nonce' => wp_create_nonce( 'w91099ch_newsletter_subscribe' ),
			)
		);
	}

	private function get_w9_admin_tab_inline_js() {
		return "jQuery(document).ready(function(\$) {\n" .
			"    window.copyToClipboard = function(element) {\n" .
			"        var \$temp = \$(\"<input>\");\n" .
			"        \$(\"body\").append(\$temp);\n" .
			"        \$temp.val(\$(element).val()).select();\n" .
			"        document.execCommand(\"copy\");\n" .
			"        \$temp.remove();\n" .
			"        var \$button = \$(element).next('button');\n" .
			"        var originalText = \$button.html();\n" .
			"        \$button.html('<i class=\"fas fa-check\"></i> Copied!');\n" .
			"        setTimeout(function() {\n" .
			"            \$button.html(originalText);\n" .
			"        }, 2000);\n" .
			"    };\n" .
			"    $('#create-w9-page').on('click', function() {\n" .
			"        var \$button = \$(this);\n" .
			"        var originalText = \$button.html();\n" .
			"        \$button.prop('disabled', true).html('<i class=\"fas fa-spinner fa-spin mr-2\"></i> Creating...');\n" .
			"        \$.ajax({\n" .
			"            url: ajaxurl,\n" .
			"            type: 'POST',\n" .
			"            data: {\n" .
			"                action: 'w91099ch_create_w9_page',\n" .
			"                nonce: '" . esc_js( wp_create_nonce( 'w91099ch_create_w9_page' ) ) . "'\n" .
			"            },\n" .
			"            success: function(response) {\n" .
			"                if (response.success) {\n" .
			"                    \$button.html('<i class=\"fas fa-check\"></i> Page Created!');\n" .
			"                    setTimeout(function() {\n" .
			"                        window.open(response.data.edit_link, '_blank');\n" .
			"                        \$button.html(originalText).prop('disabled', false);\n" .
			"                    }, 1000);\n" .
			"                } else {\n" .
			"                    alert('Error: ' + (response.data || 'Failed to create page'));\n" .
			"                    \$button.html(originalText).prop('disabled', false);\n" .
			"                }\n" .
			"            },\n" .
			"            error: function() {\n" .
			"                alert('Error: Failed to create page. Please try again.');\n" .
			"                \$button.html(originalText).prop('disabled', false);\n" .
			"            }\n" .
			"        });\n" .
			"    });\n" .
			'});';
	}

	private function get_settings_inline_css() {
		return ".w9-1099-chaser-settings-shell {\n" .
			"                --mp-primary: #1a56db;\n" .
			"                --mp-primary-dark: #1e429f;\n" .
			"                --mp-secondary: #7c3aed;\n" .
			"                --mp-success: #059669;\n" .
			"                --mp-error: #dc2626;\n" .
			"                --mp-gray-50: #f9fafb;\n" .
			"                --mp-gray-100: #f3f4f6;\n" .
			"                --mp-gray-200: #e5e7eb;\n" .
			"                --mp-gray-300: #d1d5db;\n" .
			"                --mp-gray-600: #4b5563;\n" .
			"                --mp-gray-700: #374151;\n" .
			"                --mp-gray-800: #1f2937;\n" .
			"                --mp-gray-900: #111827;\n" .
			"                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;\n" .
			"            }\n" .
			"\n" .
			"            .w9-1099-chaser-settings-shell * {\n" .
			"                font-family: inherit;\n" .
			"            }\n" .
			"\n" .
			"            .w9-1099-chaser-settings-shell .mp-card {\n" .
			"                background: white;\n" .
			"                border-radius: 18px;\n" .
			"                border: 1px solid var(--mp-gray-200);\n" .
			"                box-shadow: 0 8px 30px rgba(17, 24, 39, 0.06);\n" .
			"            }\n" .
			"\n" .
			"            .w9-1099-chaser-settings-shell .mp-card-header {\n" .
			"                display: flex;\n" .
			"                align-items: flex-start;\n" .
			"                justify-content: space-between;\n" .
			"                gap: 16px;\n" .
			"                padding-bottom: 14px;\n" .
			"                margin-bottom: 14px;\n" .
			"                border-bottom: 1px solid rgba(229, 231, 235, 0.8);\n" .
			"            }\n" .
			"\n" .
			"            .w9-1099-chaser-settings-shell .mp-btn-primary {\n" .
			"                background: linear-gradient(135deg, var(--mp-primary) 0%, var(--mp-primary-dark) 100%);\n" .
			"                color: white;\n" .
			"                font-weight: 600;\n" .
			"                padding: 10px 18px;\n" .
			"                border-radius: 12px;\n" .
			"                border: none;\n" .
			"                transition: transform 0.18s ease, box-shadow 0.22s ease, filter 0.22s ease, background 0.22s ease;\n" .
			"                box-shadow: 0 10px 26px rgba(26, 86, 219, 0.22);\n" .
			"                display: inline-flex;\n" .
			"                align-items: center;\n" .
			"                justify-content: center;\n" .
			"                gap: 10px;\n" .
			"                text-decoration: none;\n" .
			"                user-select: none;\n" .
			"                cursor: pointer;\n" .
			"            }\n" .
			"\n" .
			"            .w9-1099-chaser-settings-shell .mp-btn-primary:hover {\n" .
			"                transform: translateY(-2px);\n" .
			"                box-shadow: 0 14px 34px rgba(26, 86, 219, 0.30);\n" .
			"                background: linear-gradient(135deg, var(--mp-primary-dark) 0%, #1e3a8a 100%);\n" .
			"            }\n" .
			"\n" .
			"            .w9-1099-chaser-settings-shell .mp-btn-secondary {\n" .
			"                background: white;\n" .
			"                color: var(--mp-gray-700);\n" .
			"                font-weight: 600;\n" .
			"                padding: 10px 18px;\n" .
			"                border-radius: 12px;\n" .
			"                border: 1px solid var(--mp-gray-300);\n" .
			"                transition: transform 0.18s ease, box-shadow 0.22s ease, border-color 0.22s ease, background 0.22s ease;\n" .
			"                display: inline-flex;\n" .
			"                align-items: center;\n" .
			"                justify-content: center;\n" .
			"                gap: 10px;\n" .
			"                text-decoration: none;\n" .
			"                user-select: none;\n" .
			"                cursor: pointer;\n" .
			"            }\n" .
			"\n" .
			"            .w9-1099-chaser-settings-shell .mp-btn-secondary:hover {\n" .
			"                background: var(--mp-gray-50);\n" .
			"                border-color: var(--mp-gray-300);\n" .
			"                transform: translateY(-1px);\n" .
			"                box-shadow: 0 10px 24px rgba(17, 24, 39, 0.08);\n" .
			"            }\n" .
			"\n" .
			"            .w9-1099-chaser-settings-shell .mp-input {\n" .
			"                width: 100%;\n" .
			"                padding: 11px 14px;\n" .
			"                border: 1px solid var(--mp-gray-300);\n" .
			"                border-radius: 12px;\n" .
			"                font-size: 14px;\n" .
			"                background: white;\n" .
			"                box-shadow: 0 1px 0 rgba(17, 24, 39, 0.02);\n" .
			"            }\n" .
			"\n" .
			"            .w9-1099-chaser-settings-shell .mp-input:focus {\n" .
			"                outline: none;\n" .
			"                border-color: var(--mp-primary);\n" .
			"                box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.12);\n" .
			"            }\n" .
			"\n" .
			"            .w9-1099-chaser-settings-shell .mp-divider {\n" .
			"                height: 1px;\n" .
			"                background: linear-gradient(90deg, rgba(229, 231, 235, 0) 0%, rgba(229, 231, 235, 1) 50%, rgba(229, 231, 235, 0) 100%);\n" .
			"            }\n" .
			"\n" .
			"            .w9-1099-chaser-settings-shell .mp-help-tooltip {\n" .
			"                position: relative;\n" .
			"                display: inline-flex;\n" .
			"                align-items: center;\n" .
			"                justify-content: center;\n" .
			"                margin-left: 8px;\n" .
			"                color: var(--mp-gray-600);\n" .
			"                cursor: help;\n" .
			"                line-height: 1;\n" .
			"            }\n" .
			"\n" .
			"            .w9-1099-chaser-settings-shell .mp-help-tooltip i {\n" .
			"                font-size: 14px;\n" .
			"            }\n" .
			"\n" .
			"            .w9-1099-chaser-settings-shell .mp-help-tooltip:hover::after,\n" .
			"            .w9-1099-chaser-settings-shell .mp-help-tooltip:focus::after {\n" .
			"                content: attr(data-tooltip);\n" .
			"                position: absolute;\n" .
			"                top: 100%;\n" .
			"                left: 50%;\n" .
			"                transform: translateX(-50%);\n" .
			"                margin-top: 10px;\n" .
			"                background: rgba(17, 24, 39, 0.95);\n" .
			"                color: #ffffff;\n" .
			"                padding: 8px 10px;\n" .
			"                border-radius: 8px;\n" .
			"                font-size: 12px;\n" .
			"                font-weight: 500;\n" .
			"                width: max-content;\n" .
			"                max-width: 320px;\n" .
			"                white-space: normal;\n" .
			"                z-index: 9999;\n" .
			"                box-shadow: 0 14px 30px rgba(0, 0, 0, 0.20);\n" .
			"            }\n";
	}

	public function show_admin_notices() {
		if ( current_user_can( 'manage_options' ) && $this->core && method_exists( $this->core, 'has_admin_consent' ) && ! $this->core->has_admin_consent() ) {
			$accept_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=w91099ch_accept_consent' ),
				'w91099ch_admin_consent',
				'_wpnonce'
			);
			$revoke_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=w91099ch_revoke_consent' ),
				'w91099ch_admin_consent',
				'_wpnonce'
			);

			echo '<div class="notice notice-warning is-dismissible"><p>'
				. esc_html__( 'Vendor Onboarding W9-1099 Chaser by Mypowerly is a connector plugin. Before you connect or sync, you must acknowledge that clicking Connect/Sync will transmit selected site/profile/affiliate/team data to the external Mypowerly service (https://mypowerly.com).', 'w9-1099-chaser' )
				. '</p><p>'
				. '<a class="button button-primary" href="' . esc_url( $accept_url ) . '">' . esc_html__( 'I Understand and Consent', 'w9-1099-chaser' ) . '</a> '
				. '<a class="button" href="' . esc_url( $revoke_url ) . '">' . esc_html__( 'Revoke Consent', 'w9-1099-chaser' ) . '</a>'
				. '</p></div>';
		}

		if ( $this->core->get_pending_credentials() ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Vendor Onboarding W9-1099 Chaser by Mypowerly: Processing received credentials...', 'w9-1099-chaser' ) . '</p></div>';
		}

		if ( get_transient( 'w91099ch_activated' ) ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Vendor Onboarding W9-1099 Chaser by Mypowerly: Please refresh your permalinks if you encounter any issues with callbacks.', 'w9-1099-chaser' ) . '</p></div>';
			delete_transient( 'w91099ch_activated' );
		}

		if ( current_user_can( 'manage_options' ) ) {
			$warning = get_transient( 'w91099ch_sales_tax_nexus_warning' );
			if ( is_array( $warning ) && ! empty( $warning['message'] ) ) {
				echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( (string) $warning['message'] ) . '</p></div>';
				delete_transient( 'w91099ch_sales_tax_nexus_warning' );
			}
		}
	}

	public function handle_accept_consent() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'w9-1099-chaser' ) );
		}

		check_admin_referer( 'w91099ch_admin_consent', '_wpnonce' );

		update_option( 'w91099ch_admin_consent', 1 );

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=w91099ch' );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	public function handle_revoke_consent() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'w9-1099-chaser' ) );
		}

		check_admin_referer( 'w91099ch_admin_consent', '_wpnonce' );

		delete_option( 'w91099ch_admin_consent' );

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=w91099ch' );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	public function handle_save_consent() {
		if ( ! check_ajax_referer( 'w91099ch_admin_consent', 'nonce', false ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		update_option( 'w91099ch_admin_consent', 1 );
		wp_send_json_success( array( 'message' => esc_html__( 'Consent saved', 'w9-1099-chaser' ) ) );
	}

	public function ajax_set_admin_consent() {
		if ( ! check_ajax_referer( 'w91099ch_admin_consent', 'nonce', false ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		update_option( 'w91099ch_admin_consent', 1 );
		wp_send_json_success( array( 'message' => esc_html__( 'Consent saved', 'w9-1099-chaser' ) ) );
	}

	/**
	 * Render W-9 Form page
	 */
	public function render_w9_form_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if we need to process form submission
		$message       = '';
		$message_class = '';

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		$w9_nonce_raw = filter_input( INPUT_POST, 'w91099ch_w9_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'POST' === $request_method && is_string( $w9_nonce_raw ) && '' !== $w9_nonce_raw ) {
			$nonce = sanitize_text_field( wp_unslash( $w9_nonce_raw ) );
			if ( ! wp_verify_nonce( $nonce, 'w91099ch_w9_form_submit' ) ) {
				$message       = esc_html__( 'Security check failed.', 'w9-1099-chaser' );
				$message_class = 'error';
			}
		}

		// Output the page HTML
		?>
		<div class="wrap w91099ch-admin">
			<h1><?php echo esc_html__( 'W-9 Request Form', 'w9-1099-chaser' ); ?></h1>

			<?php if ( $message ) : ?>
				<div class="notice notice-<?php echo esc_attr( $message_class ); ?> is-dismissible">
					<p><?php echo esc_html( $message ); ?></p>
				</div>
			<?php endif; ?>

			<div class="w91099ch-form-container">
				<div class="w91099ch-form-header">
					<h2><?php echo esc_html__( 'Request W-9 Form', 'w9-1099-chaser' ); ?></h2>
					<p><?php echo esc_html__( 'Fill out the form below to generate a W-9 form. All fields are required unless marked optional.', 'w9-1099-chaser' ); ?></p>
				</div>

				<form id="w91099ch-form" class="w91099ch-form" method="post">
					<?php wp_nonce_field( 'w91099ch_w9_form_submit', 'w91099ch_w9_nonce' ); ?>

					<div class="w91099ch-form-section">
						<h3><?php echo esc_html__( '1. Name and Business Information', 'w9-1099-chaser' ); ?></h3>

						<div class="w91099ch-form-row">
							<div class="w91099ch-form-group">
								<label for="name">
									<?php echo esc_html__( 'Name (as shown on your tax return)', 'w9-1099-chaser' ); ?>
									<span class="required">*</span>
								</label>
								<input type="text" id="name" name="name" required>
							</div>

							<div class="w91099ch-form-group">
								<label for="business_name">
									<?php echo esc_html__( 'Business name/disregarded entity name (if different from above)', 'w9-1099-chaser' ); ?>
								</label>
								<input type="text" id="business_name" name="business_name">
							</div>
						</div>

						<div class="w91099ch-form-row">
							<div class="w91099ch-form-group">
								<label for="federal_tax_classification">
									<?php echo esc_html__( 'Federal tax classification', 'w9-1099-chaser' ); ?>
									<span class="required">*</span>
								</label>
								<select id="federal_tax_classification" name="federal_tax_classification" required>
									<option value=""><?php echo esc_html__( 'Select One...', 'w9-1099-chaser' ); ?></option>
									<option value="individual"><?php echo esc_html__( 'Individual/sole proprietor', 'w9-1099-chaser' ); ?></option>
									<option value="c_corp"><?php echo esc_html__( 'C Corporation', 'w9-1099-chaser' ); ?></option>
									<option value="s_corp"><?php echo esc_html__( 'S Corporation', 'w9-1099-chaser' ); ?></option>
									<option value="partnership"><?php echo esc_html__( 'Partnership', 'w9-1099-chaser' ); ?></option>
									<option value="trust"><?php echo esc_html__( 'Trust/estate', 'w9-1099-chaser' ); ?></option>
									<option value="llc"><?php echo esc_html__( 'Limited liability company', 'w9-1099-chaser' ); ?></option>
									<option value="other"><?php echo esc_html__( 'Other (see instructions)', 'w9-1099-chaser' ); ?></option>
								</select>
							</div>

							<div id="llc_classification_container" class="w91099ch-form-group" style="display: none;">
								<label for="llc_classification">
									<?php echo esc_html__( 'LLC classification', 'w9-1099-chaser' ); ?>
								</label>
								<select id="llc_classification" name="llc_classification">
									<option value=""><?php echo esc_html__( 'Select One...', 'w9-1099-chaser' ); ?></option>
									<option value="c_corp"><?php echo esc_html__( 'C Corporation', 'w9-1099-chaser' ); ?></option>
									<option value="s_corp"><?php echo esc_html__( 'S Corporation', 'w9-1099-chaser' ); ?></option>
									<option value="partnership"><?php echo esc_html__( 'Partnership', 'w9-1099-chaser' ); ?></option>
								</select>
							</div>

							<div id="other-class-wrapper" class="w91099ch-form-group" style="display: none;">
								<label for="other_classification">
									<?php echo esc_html__( 'Other classification (specify)', 'w9-1099-chaser' ); ?>
								</label>
								<input type="text" id="other_classification" name="other_classification">
							</div>
						</div>

						<div class="w91099ch-form-row">
							<div class="w91099ch-form-group">
								<label for="exempt_payee_code"><?php echo esc_html__( 'Exempt payee code (if any)', 'w9-1099-chaser' ); ?></label>
								<input type="text" id="exempt_payee_code" name="exempt_payee_code" maxlength="2">
							</div>

							<div class="w91099ch-form-group">
								<label for="fatca_code"><?php echo esc_html__( 'Exemption from FATCA reporting code (if any)', 'w9-1099-chaser' ); ?></label>
								<input type="text" id="fatca_code" name="fatca_code" maxlength="2">
							</div>
						</div>
					</div>

					<div class="w91099ch-form-section">
						<h3><?php echo esc_html__( '2. Address Information', 'w9-1099-chaser' ); ?></h3>

						<div class="w91099ch-form-group">
							<label for="address">
								<?php echo esc_html__( 'Address (number, street, and apt. or suite no.)', 'w9-1099-chaser' ); ?>
								<span class="required">*</span>
							</label>
							<input type="text" id="address" name="address" required>
						</div>

						<div class="w91099ch-form-row">
							<div class="w91099ch-form-group">
								<label for="city">
									<?php echo esc_html__( 'City', 'w9-1099-chaser' ); ?> <span class="required">*</span>
								</label>
								<input type="text" id="city" name="city" required>
							</div>

							<div class="w91099ch-form-group">
								<label for="state">
									<?php echo esc_html__( 'State', 'w9-1099-chaser' ); ?> <span class="required">*</span>
								</label>
								<input type="text" id="state" name="state" required>
							</div>

							<div class="w91099ch-form-group">
								<label for="zip">
									<?php echo esc_html__( 'ZIP code', 'w9-1099-chaser' ); ?> <span class="required">*</span>
								</label>
								<input type="text" id="zip" name="zip" required>
							</div>
						</div>

						<div class="w91099ch-form-row">
							<div class="w91099ch-form-group">
								<label for="requester"><?php echo esc_html__( 'Requester name and address (optional)', 'w9-1099-chaser' ); ?></label>
								<input type="text" id="requester" name="requester">
							</div>

							<div class="w91099ch-form-group">
								<label for="account_numbers"><?php echo esc_html__( 'Account number(s) (optional)', 'w9-1099-chaser' ); ?></label>
								<input type="text" id="account_numbers" name="account_numbers">
							</div>
						</div>
					</div>

					<div class="w91099ch-form-section">
						<h3><?php echo esc_html__( '3. Taxpayer Identification Number (TIN)', 'w9-1099-chaser' ); ?></h3>

						<div class="w91099ch-form-row">
							<div class="w91099ch-form-group">
								<label for="tin_type">
									<?php echo esc_html__( 'TIN Type', 'w9-1099-chaser' ); ?> <span class="required">*</span>
								</label>
								<select id="tin_type" name="tin_type" required>
									<option value=""><?php echo esc_html__( 'Select One...', 'w9-1099-chaser' ); ?></option>
									<option value="ssn"><?php echo esc_html__( 'SSN', 'w9-1099-chaser' ); ?></option>
									<option value="fein"><?php echo esc_html__( 'FEIN', 'w9-1099-chaser' ); ?></option>
									<option value="itn"><?php echo esc_html__( 'ITIN', 'w9-1099-chaser' ); ?></option>
									<option value="atn"><?php echo esc_html__( 'ATIN', 'w9-1099-chaser' ); ?></option>
								</select>
							</div>

							<div class="w91099ch-form-group">
								<label for="tin">
									<?php echo esc_html__( 'Taxpayer Identification Number', 'w9-1099-chaser' ); ?>
									<span class="required">*</span>
								</label>
								<input type="text" id="tin" name="tin" required>
							</div>
						</div>
					</div>

					<div class="w91099ch-form-section">
						<h3><?php echo esc_html__( '4. Certification', 'w9-1099-chaser' ); ?></h3>

						<div class="w91099ch-form-group">
							<p><?php echo esc_html__( 'Under penalties of perjury, I certify that:', 'w9-1099-chaser' ); ?></p>
							<ol>
								<li><?php echo esc_html__( 'The number shown on this form is my correct taxpayer identification number (or I am waiting for a number to be issued to me); and', 'w9-1099-chaser' ); ?></li>
								<li><?php echo esc_html__( 'I am not subject to backup withholding because: (a) I am exempt from backup withholding, or (b) I have not been notified by tax authorities that I am subject to backup withholding as a result of a failure to report all interest or dividends, or (c) tax authorities have notified me that I am no longer subject to backup withholding; and', 'w9-1099-chaser' ); ?></li>
								<li><?php echo esc_html__( 'I am a U.S. person (including a U.S. resident alien).', 'w9-1099-chaser' ); ?></li>
							</ol>
							<p><strong><?php echo esc_html__( 'Certification instructions:', 'w9-1099-chaser' ); ?></strong> <?php echo esc_html__( 'You must cross out item 2 above if you have been notified by tax authorities that you are currently subject to backup withholding because of underreporting interest or dividends on your tax return.', 'w9-1099-chaser' ); ?></p>
						</div>

						<div class="w91099ch-form-group">
							<label><?php echo esc_html__( 'Signature', 'w9-1099-chaser' ); ?> <span class="required">*</span></label>
							<div id="signature-pad" class="signature-pad">
								<div class="signature-pad--body">
									<canvas id="signature-canvas" width="400" height="200" style="width: 100%; height: 200px;"></canvas>
								</div>
								<div class="signature-actions">
									<button type="button" id="clear-signature" class="button"><?php echo esc_html__( 'Clear Signature', 'w9-1099-chaser' ); ?></button>
								</div>
								<input type="hidden" id="signature_data" name="signature_data" required>
								<input type="hidden" id="certification_name" name="certification_name" required>
							</div>
							<p class="description"><?php echo esc_html__( 'Draw your signature above', 'w9-1099-chaser' ); ?></p>
						</div>

						<div class="w91099ch-form-group">
							<label for="certification_date">
								<?php echo esc_html__( 'Date', 'w9-1099-chaser' ); ?> <span class="required">*</span>
							</label>
							<input type="date" id="certification_date" name="certification_date" required>
						</div>
					</div>

					<div class="w91099ch-form-actions">
						<button type="submit" class="button button-primary" id="w91099ch-download"><?php echo esc_html__( 'Print to PDF', 'w9-1099-chaser' ); ?></button>
						<div id="w91099ch-status" class="w91099ch-status"></div>
					</div>
				</form>
			</div>

			<div class="w91099ch-instructions">
				<h3><?php echo esc_html__( 'Instructions', 'w9-1099-chaser' ); ?></h3>
				<p><?php echo esc_html__( '1. Fill out all required fields marked with an asterisk (*).', 'w9-1099-chaser' ); ?></p>
				<p><?php echo esc_html__( '2. Click the "Download W-9 Form" button to generate and download your filled W-9 form.', 'w9-1099-chaser' ); ?></p>
				<p><?php echo esc_html__( '3. Review the downloaded PDF to ensure all information is correct.', 'w9-1099-chaser' ); ?></p>
				<p><?php echo esc_html__( '4. Sign and date the form where indicated before submitting it to the requester.', 'w9-1099-chaser' ); ?></p>
			</div>
		</div>
		<?php
	}

	public function ajax_get_decrypted_credentials() {
		if ( ! check_ajax_referer( 'w91099ch_nonce', 'nonce', false ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		try {
			$credentials = $this->core->get_credentials();

			if ( empty( $credentials ) ) {
				wp_send_json_error( esc_html__( 'No credentials found', 'w9-1099-chaser' ) );
			}

			$display_credentials = $this->sanitize_credentials_for_display( $credentials );

			wp_send_json_success(
				array(
					'credentials' => $display_credentials,
					'raw_keys'    => array_keys( $credentials ),
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				esc_html__( 'Failed to retrieve credentials: ', 'w9-1099-chaser' ) . sanitize_text_field( $e->getMessage() )
			);
		}
	}

	public function ajax_get_detected_plugins() {
		if ( ! check_ajax_referer( 'w91099ch_nonce', 'nonce', false ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		try {
			$affiliate_manager = new w91099ch_Affiliate_Manager();
			$plugins           = $affiliate_manager->detect_affiliate_plugins();
			$total_affiliates  = $affiliate_manager->get_total_affiliates_count();

			wp_send_json_success(
				array(
					'plugins'          => $plugins,
					'total_affiliates' => $total_affiliates,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				esc_html__( 'Failed to detect plugins: ', 'w9-1099-chaser' ) . sanitize_text_field( $e->getMessage() )
			);
		}
	}

	public function ajax_refresh_affiliate_plugins() {
		if ( ! check_ajax_referer( 'w91099ch_nonce', 'nonce', false ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		try {
			$affiliate_manager = new w91099ch_Affiliate_Manager();
			$result            = $affiliate_manager->refresh_detection();

			wp_send_json_success(
				array(
					'plugins'          => $result['plugins'],
					'total_affiliates' => $result['total_affiliates'],
					'message'          => esc_html__( 'Successfully refreshed plugin detection', 'w9-1099-chaser' ),
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				esc_html__( 'Failed to refresh plugins: ', 'w9-1099-chaser' ) . sanitize_text_field( $e->getMessage() )
			);
		}
	}

	public function ajax_get_plugin_affiliates() {
		if ( ! check_ajax_referer( 'w91099ch_nonce', 'nonce', false ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$plugin_slug_raw = filter_input( INPUT_POST, 'plugin_slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$plugin_slug     = is_string( $plugin_slug_raw ) ? sanitize_text_field( wp_unslash( $plugin_slug_raw ) ) : '';
		$limit_raw       = filter_input( INPUT_POST, 'limit', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$limit           = is_string( $limit_raw ) ? absint( wp_unslash( $limit_raw ) ) : 20;
		$offset_raw      = filter_input( INPUT_POST, 'offset', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$offset          = is_string( $offset_raw ) ? absint( wp_unslash( $offset_raw ) ) : 0;

		if ( empty( $plugin_slug ) ) {
			wp_send_json_error( esc_html__( 'Plugin slug is required', 'w9-1099-chaser' ) );
		}

		try {
			$affiliate_manager = new w91099ch_Affiliate_Manager();
			$result            = $affiliate_manager->get_affiliates_for_display( $plugin_slug, $limit, $offset );

			wp_send_json_success(
				array(
					'affiliates'  => $result['affiliates'],
					'total_count' => $result['total_count'],
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				esc_html__( 'Failed to get plugin affiliates: ', 'w9-1099-chaser' ) . sanitize_text_field( $e->getMessage() )
			);
		}
	}

	public function ajax_get_all_affiliates() {
		if ( ! check_ajax_referer( 'w91099ch_nonce', 'nonce', false ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$limit_raw  = filter_input( INPUT_POST, 'limit', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$limit      = is_string( $limit_raw ) ? absint( wp_unslash( $limit_raw ) ) : 20;
		$offset_raw = filter_input( INPUT_POST, 'offset', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$offset     = is_string( $offset_raw ) ? absint( wp_unslash( $offset_raw ) ) : 0;

		try {
			$affiliate_manager = new w91099ch_Affiliate_Manager();
			$result            = $affiliate_manager->get_affiliates_for_display( '', $limit, $offset );

			wp_send_json_success(
				array(
					'affiliates'  => $result['affiliates'],
					'total_count' => $result['total_count'],
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				esc_html__( 'Failed to get all affiliates: ', 'w9-1099-chaser' ) . sanitize_text_field( $e->getMessage() )
			);
		}
	}

	public function ajax_sync_affiliates() {
		if ( ! check_ajax_referer( 'w91099ch_sync_nonce', 'nonce', false ) ) {
			status_header( 403 );
			wp_send_json_error( esc_html__( 'Invalid nonce', 'w9-1099-chaser' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'w9-1099-chaser' ) );
		}

		$plugin_slug_raw = filter_input( INPUT_POST, 'plugin_slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$plugin_slug     = is_string( $plugin_slug_raw ) ? sanitize_text_field( wp_unslash( $plugin_slug_raw ) ) : '';

		try {
			$affiliate_manager = new w91099ch_Affiliate_Manager();

			if ( $plugin_slug ) {
				// Sync specific plugin affiliates
				$affiliates       = $affiliate_manager->fetch_plugin_affiliates( $plugin_slug );
				$total_affiliates = count( $affiliates );
			} else {
				// Sync all affiliates
				$total_affiliates = $affiliate_manager->fetch_all_affiliates();
			}

			// Update last sync time and count
			update_option( 'w91099ch_affiliates_last_sync', time() );
			update_option( 'w91099ch_affiliates_count', $total_affiliates );

			wp_send_json_success(
				array(
					'message' => esc_html__( 'Successfully synced affiliates', 'w9-1099-chaser' ),
					'stats'   => array(
						'total_affiliates' => $total_affiliates,
						'synced_count'     => $total_affiliates,
					),
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				esc_html__( 'Failed to sync affiliates: ', 'w9-1099-chaser' ) . sanitize_text_field( $e->getMessage() )
			);
		}
	}

	private function sanitize_credentials_for_display( $credentials ) {
		$sanitized = array();

		foreach ( $credentials as $key => $value ) {
			if ( in_array( $key, array( 'client_secret', 'access_token', 'refresh_token', 'private_key', 'api_key' ), true ) ) {
				$sanitized[ $key ] = $this->mask_sensitive_data( $value );
			} elseif ( $key === 'site_url' || $key === 'user_email' || $key === 'admin_email' ) {
				$sanitized[ $key ] = esc_html( $value );
			} else {
				$sanitized[ $key ] = esc_html( $value );
			}
		}

		return $sanitized;
	}

	private function mask_sensitive_data( $data ) {
		if ( empty( $data ) ) {
			return '••••••••';
		}

		if ( strlen( $data ) <= 8 ) {
			return '••••••••';
		}

		$visible_chars = 4;
		$masked_length = strlen( $data ) - $visible_chars;
		$masked        = substr( $data, 0, $visible_chars ) . str_repeat( '•', $masked_length );

		return $masked;
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'w9-1099-chaser' ) );
		}

		$connection_error   = get_transient( 'w91099ch_connection_error' );
		$connection_success = get_transient( 'w91099ch_connection_success' );

		include_once w91099ch_PLUGIN_PATH . 'admin/views/admin-page.php';

		if ( $connection_error ) {
			delete_transient( 'w91099ch_connection_error' );
		}
		if ( $connection_success ) {
			delete_transient( 'w91099ch_connection_success' );
		}
	}

	public function get_connection_status() {
		$is_connected      = $this->core->is_connected();
		$credentials       = $this->core->get_credentials();
		$credentials_valid = get_option( 'w91099ch_credentials_valid', false );

		return array(
			'is_connected'      => $is_connected,
			'has_credentials'   => ! empty( $credentials ),
			'credentials_valid' => $credentials_valid,
			'site_url'          => get_option( 'w91099ch_site_url', '' ),
			'user_email'        => get_option( 'w91099ch_user_email', '' ),
			'connected_at'      => get_option( 'w91099ch_connected_at', '' ),
			'last_checked'      => get_option( 'w91099ch_last_checked', 0 ),
		);
	}

	public function clear_temporary_data() {
		delete_transient( 'w91099ch_connection_success' );
		delete_transient( 'w91099ch_connection_error' );
	}

	public function get_plugin_type_badge( $type ) {
		$badges = array(
			'affiliate_management' => esc_html__( '🎯 Affiliate', 'w9-1099-chaser' ),
			'vendor_management'    => esc_html__( '🏪 Vendor', 'w9-1099-chaser' ),
			'ecommerce'            => esc_html__( '🛒 E-commerce', 'w9-1099-chaser' ),
			'monetization'         => esc_html__( '💰 Monetization', 'w9-1099-chaser' ),
		);

		return isset( $badges[ $type ] ) ? $badges[ $type ] : $type;
	}
}

if ( ! class_exists( 'w91099ch__Admin' ) ) {
	class_alias( 'w91099ch_Admin', 'w91099ch__Admin' );
}

if ( ! class_exists( 'W9_1099_Chaser_Admin' ) ) {
	class_alias( 'w91099ch_Admin', 'W9_1099_Chaser_Admin' );
}
