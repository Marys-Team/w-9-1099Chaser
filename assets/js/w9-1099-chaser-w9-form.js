jQuery(document).ready(function($) {
    const w91099chDebugEnabled = !!(
        window.w91099chDebug ||
        (window.localStorage && localStorage.getItem('w91099chDebug') === '1') ||
        (window.w91099chConnectorW9 && window.w91099chConnectorW9.debug) ||
        (window.w91099chW9Form && window.w91099chW9Form.debug) ||
        true // Enable debug by default for TIN troubleshooting
    );

    window.w91099chConsole = (window.w91099chConsole && typeof window.w91099chConsole.log === 'function')
        ? window.w91099chConsole
        : (w91099chDebugEnabled && typeof window.console !== 'undefined'
            ? window.console
            : { log: function() {}, error: function() {}, warn: function() {} });

    const w91099chConsole = window.w91099chConsole;
    const footerBrandText = 'Created by W9 - 1099 Chaser';
    const footerBrandUrl = 'https://1099automation.com/';

    if (typeof window.copyToClipboardFallback !== 'function') {
        window.copyToClipboardFallback = function(text) {
            try {
                const t = String(text ?? '');
                const ta = document.createElement('textarea');
                ta.value = t;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                ta.style.left = '-9999px';
                ta.style.top = '0';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                const ok = document.execCommand('copy');
                document.body.removeChild(ta);
                return ok;
            } catch (_) {
                return false;
            }
        };
    }

    // Helper function to convert data URL to blob
    function dataURLtoBlob(dataURL) {
        const arr = dataURL.split(',');
        const mime = arr[0].match(/:(.*?);/)[1];
        const bstr = atob(arr[1]);
        let n = bstr.length;
        const u8arr = new Uint8Array(n);
        while(n--){
            u8arr[n] = bstr.charCodeAt(n);
        }
        return new Blob([u8arr], {type:mime});
    }

    // Helper function to create a FileList
    function createFileList(file) {
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        return dataTransfer.files;
    }

    async function w91099chCopyText(text) {
        const t = String(text ?? '');
        if (!t) {
            return false;
        }
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(t);
                return true;
            }
        } catch (_) {}

        if (typeof window.copyToClipboardFallback === 'function') {
            return !!window.copyToClipboardFallback(t);
        }

        try {
            const ta = document.createElement('textarea');
            ta.value = t;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            ta.style.top = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            const ok = document.execCommand('copy');
            document.body.removeChild(ta);
            return ok;
        } catch (_) {
            return false;
        }
    }

    if (typeof window.copyToClipboard !== 'function') {
        window.copyToClipboard = async function(elementId) {
            const text = $(elementId).val();
            return w91099chCopyText(text);
        };
    }
    // Check if required libraries are loaded
    w91099chConsole.log('=== Checking Library Dependencies ===');
    w91099chConsole.log('SignaturePad available:', typeof SignaturePad !== 'undefined');
    w91099chConsole.log('PDFLib available:', typeof PDFLib !== 'undefined');
    
    if (typeof PDFLib === 'undefined') {
        w91099chConsole.error('PDFLib is not loaded! PDF filling will not work.');
        alert('PDF library not loaded. Please refresh the page and try again.');
        return;
    }

    // Reset form
    $(document).on('click', '#mypowerly-w9-reset', function(e) {
        e.preventDefault();

        const formEl = document.getElementById('mypowerly-w9-form');
        if (formEl && typeof formEl.reset === 'function') {
            formEl.reset();
        }

        // Hide conditional fields for shortcode version
        $('#mypowerly-w9-llc-tax-class-wrapper').hide();
        $('#mypowerly-w9-other-class-wrapper').hide();

        // Clear status
        $('#mypowerly-w9-status').empty().removeClass('error success').hide();
    });

	function base64ToUint8Array(base64) {
		const binaryString = atob(String(base64 || ''));
		const bytes = new Uint8Array(binaryString.length);
		for (let i = 0; i < binaryString.length; i++) {
			bytes[i] = binaryString.charCodeAt(i);
		}
		return bytes;
	}

	function downloadPdfBytes(pdfBytes, filename, type = 'print_to_pdf') {
		const blob = new Blob([pdfBytes], { type: 'application/pdf' });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = filename;
		document.body.appendChild(a);
		a.click();
		
		// Track download with the specified type
		if (typeof window.trackW9Download === 'function') {
			window.trackW9Download(type);
		}
		
		// Store PDF data for sharing
		blobToDataURL(blob).then(pdfDataUrl => {
			setTimeout(() => {
				document.body.removeChild(a);
				URL.revokeObjectURL(url);
				// Show PDF sharing popup after successful download
				showPdfSharingPopup(pdfDataUrl, filename);
			}, 250);
		});
	}

	function addPdfLinkAnnotation(pageObj, x, y, width, height, url) {
		try {
			if (!pageObj || !pageObj.node || !url) return;
			const { PDFName, PDFArray, PDFString } = PDFLib;
			const linkDict = pageObj.doc.context.obj({
				Type: 'Annot',
				Subtype: 'Link',
				Rect: [x, y, x + width, y + height],
				Border: [0, 0, 0],
				A: {
					Type: 'Action',
					S: 'URI',
					URI: PDFString.of(String(url)),
				},
			});
			const linkRef = pageObj.doc.context.register(linkDict);

			const annotsKey = PDFName.of('Annots');
			const existing = pageObj.node.get(annotsKey);
			if (existing) {
				existing.push(linkRef);
			} else {
				pageObj.node.set(annotsKey, PDFArray.withContext(pageObj.doc.context));
				pageObj.node.get(annotsKey).push(linkRef);
			}
		} catch (e) {
			// Non-fatal if link annotations fail
		}
	}

	async function fetchGovtTemplateBase64() {
		// Debug logging for configuration
		w91099chConsole.log('=== Configuration Debug ===');
		w91099chConsole.log('w91099chConnectorW9:', window.w91099chConnectorW9);
		w91099chConsole.log('w91099chW9Form:', window.w91099chW9Form);
		
		// Enhanced configuration detection with fallbacks
		let cfg = window.w91099chConnectorW9 || window.w91099chW9Form;
		let ajaxurl = cfg && cfg.ajaxurl;
		let nonce = cfg && cfg.nonce;
		
		// Fallback: try to get missing pieces from the other config object
		if (!ajaxurl || !nonce) {
			const fallbackCfg = window.w91099chConnectorW9 === cfg ? window.w91099chW9Form : window.w91099chConnectorW9;
			if (fallbackCfg) {
				if (!ajaxurl) ajaxurl = fallbackCfg.ajaxurl;
				if (!nonce) nonce = fallbackCfg.nonce;
			}
		}
		
		// Final fallback: construct ajaxurl if missing
		if (!ajaxurl && typeof admin_url === 'function') {
			ajaxurl = admin_url('admin-ajax.php');
		}
		
		w91099chConsole.log('Selected config:', cfg);
		w91099chConsole.log('Final ajaxurl:', ajaxurl);
		w91099chConsole.log('Final nonce:', nonce ? 'present' : 'missing');
		
		if (!ajaxurl || !nonce) {
			w91099chConsole.error('Configuration missing:', {
				cfg: cfg,
				ajaxurl: ajaxurl,
				nonce: nonce ? 'present' : 'missing'
			});
			throw new Error('Configuration not loaded');
		}
		
		// Use the resolved values
		cfg = { ajaxurl, nonce };

		const resp = await fetch(cfg.ajaxurl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: new URLSearchParams({
				action: 'w91099ch_generate_govt_pdf',
				nonce: cfg.nonce,
			})
		});
		const json = await resp.json().catch(() => null);
		if (!resp.ok || !json || !json.success || !json.data || !json.data.pdf_base64) {
			const msg = (json && json.data) ? json.data : 'Failed to fetch government template';
			throw new Error(msg);
		}
		return json.data.pdf_base64;
	}

	function getFormDataObject() {
		const arr = $('#mypowerly-w9-form').serializeArray();
		const obj = {};
		arr.forEach(({ name, value }) => { obj[name] = value; });
		return obj;
	}

	async function fillGovtPdf(pdfBytes, formData) {
		const pdfDoc = await PDFLib.PDFDocument.load(pdfBytes);
		const form = pdfDoc.getForm();
		
		const onlyDigits = (v) => String(v || '').replace(/\D/g, '');
		const formatSSN = (digits) => {
			const d = onlyDigits(digits);
			if (d.length !== 9) return '';
			return d.substring(0, 3) + '-' + d.substring(3, 5) + '-' + d.substring(5);
		};
		const formatEIN = (digits) => {
			const d = onlyDigits(digits);
			if (d.length !== 9) return '';
			return d.substring(0, 2) + '-' + d.substring(2);
		};

		const safeSetText = (fieldName, val) => {
			if (val === undefined || val === null) return;
			const s = String(val).trim();
			try {
				const f = form.getTextField(fieldName);
				f.setText(s);
			} catch (e) {
				try {
					const f2 = form.getField(fieldName);
					if (f2 && typeof f2.setText === 'function') f2.setText(s);
				} catch (_) {}
			}
		};

		const safeCheck = (fieldName, checked) => {
			// CheckBox
			try {
				const cb = form.getCheckBox(fieldName);
				if (checked) cb.check(); else cb.uncheck();
				return;
			} catch (_) {}
			
			// RadioGroup (some PDFs use a radio group instead of checkboxes)
			try {
				const rg = form.getRadioGroup(fieldName);
				if (checked) {
					// Common on/off values
					try { rg.select('Yes'); return; } catch (_) {}
					try { rg.select('On'); return; } catch (_) {}
				}
				return;
			} catch (_) {}
			
			// Generic field fallback
			try {
				const f = form.getField(fieldName);
				if (!f) return;
				if (checked && typeof f.check === 'function') {
					f.check();
					return;
				}
				if (!checked && typeof f.uncheck === 'function') {
					f.uncheck();
					return;
				}
				if (checked && typeof f.select === 'function') {
					try { f.select('Yes'); return; } catch (_) {}
					try { f.select('On'); return; } catch (_) {}
				}
			} catch (_) {}
		};
		
		const setOnlyOne = (selectedField, allFields) => {
			allFields.forEach((f) => safeCheck(f, f === selectedField));
		};

		// Text fields
		safeSetText('PrintOrTypeSeeSpecificInstructionsOnPage3', formData.name);
		safeSetText('BusinessNameDisregardedEntityNameIfDifferentFromAbove', formData.business_name);
		safeSetText('5', formData.address);
		const cityStateZip = String(formData.city_state_zip || '').trim() || (
			(String(formData.city || '').trim() || String(formData.City || '').trim())
				? (
					String(formData.city || formData.City || '').trim() +
					(String(formData.state || formData.State || '').trim() ? (', ' + String(formData.state || formData.State || '').trim()) : '') +
					(String(formData.zip || formData.Zip || '').trim() ? (' ' + String(formData.zip || formData.Zip || '').trim()) : '')
				)
				: ''
		);
		safeSetText('CityStateAndZipCode', cityStateZip);
		safeSetText('RequesterSNameAndAddressOptional', formData.requester);
		safeSetText('ListAccountNumberSHereOptional', formData.account_numbers);
		safeSetText('ExemptPayeeCodeIfAny', formData.exempt_payee_code);
		safeSetText('ExemptionFromForeignAccountTaxComplianceActFatcaReportingCodeIfAny', formData.fatca_code);
		const llcTaxClassRaw = (
			formData.llc_tax_class ??
			formData.llc_classification ??
			formData.llcClassification ??
			formData.llc_tax_classification ??
			''
		);
		const normalizeLlcTaxClass = (v) => {
			const s = String(v || '').trim();
			if (!s) return '';
			// If already a valid single-letter (C/S/P)
			if (s.length === 1 && /[csp]/i.test(s)) return s.toUpperCase();
			const t = s.toLowerCase();
			// If value comes from secondary dropdown (admin): c_corp / s_corp / partnership
			if (t === 'c_corp' || t === 'c-corp' || t === 'c corporation' || t === 'ccorporation') return 'C';
			if (t === 's_corp' || t === 's-corp' || t === 's corporation' || t === 'scorporation') return 'S';
			if (t === 'partnership') return 'P';
			// If user selected individual here, IRS W-9 LLC letter is not applicable
			if (t === 'individual' || t.includes('individual') || t.includes('sole')) return '';
			return '';
		};
		const llcTaxClass = normalizeLlcTaxClass(llcTaxClassRaw);
		const classificationForLlc = String(formData.classification || formData.federal_tax_classification || '').trim().toLowerCase();
		if (classificationForLlc === 'llc') {
			safeSetText('LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership2', llcTaxClass);
			safeSetText('LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership', llcTaxClass);
		} else {
			// If the user filled it anyway (or template expects it), still try primary field
			safeSetText('LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership2', llcTaxClass);
		}
		const otherClassificationSpecify = (
			formData.other_classification ??
			formData.other_class ??
			formData.otherClassification ??
			formData.other ??
			''
		);
		if (String(formData.classification || formData.federal_tax_classification || '').trim().toLowerCase() === 'other') {
			safeSetText('OtherSeeInstructions2', otherClassificationSpecify);
			safeSetText('OtherSeeInstructions', otherClassificationSpecify);
			safeSetText('OtherSeeInstructionsText', otherClassificationSpecify);
		} else {
			safeSetText('OtherSeeInstructions2', otherClassificationSpecify);
		}
		safeSetText('Date', formData.certification_date);
		safeSetText('SIGN_VENDOR', formData.certification_name || (formData.signature_data ? 'Signed' : ''));

		// TIN splitting
		const tinDigits = onlyDigits(formData.tin);
		const tinType = String(formData.tin_type || '').trim().toLowerCase();
		
		// Debug logging for TIN type handling
		w91099chConsole.log('TIN Debug - Type:', tinType, 'Digits:', tinDigits, 'Length:', tinDigits.length);
		
		// Enhanced TIN type detection for ITIN/ATIN
		const isSsnLikeTin = (tinType === 'ssn' || tinType === 'itin' || tinType === 'atin' || tinType === 'atn' || tinType === 'ain');
		
		w91099chConsole.log('TIN Debug - isSsnLikeTin:', isSsnLikeTin);
		
		if (isSsnLikeTin && tinDigits.length === 9) {
			w91099chConsole.log('TIN Debug - Processing as SSN-like TIN');
			// Govt PDF has 3 SSN inputs: 123 / 45 / 6789
			safeSetText('SocialSecurityNumber', tinDigits.substring(0, 3));
			safeSetText('VENDOR_SSN_MIDDLE2', tinDigits.substring(3, 5));
			safeSetText('VENDOR_SSN_LAST4', tinDigits.substring(5));
			// Extra fallbacks (won't affect if fields don't exist)
			safeSetText('VENDOR_SSN_FIRST3', tinDigits.substring(0, 3));
			safeSetText('VENDOR_SSN_1', tinDigits.substring(0, 3));
			safeSetText('VENDOR_SSN_2', tinDigits.substring(3, 5));
			safeSetText('VENDOR_SSN_3', tinDigits.substring(5));
			// Also try formatted string in case template has a combined field somewhere
			safeSetText('SocialSecurityNumberFormatted', formatSSN(tinDigits));
		}
		if (tinType === 'fein' && tinDigits.length === 9) {
			// Govt PDF has 2 EIN inputs: 12 / 3456789
			safeSetText('EmployerIdentificationNumber', tinDigits.substring(0, 2));
			safeSetText('VENDOR_EIN_LAST7', tinDigits.substring(2));
			// Extra fallbacks
			safeSetText('VENDOR_EIN_FIRST2', tinDigits.substring(0, 2));
			safeSetText('VENDOR_EIN_1', tinDigits.substring(0, 2));
			safeSetText('VENDOR_EIN_2', tinDigits.substring(2));
			safeSetText('EmployerIdentificationNumberFormatted', formatEIN(tinDigits));
		}

		// Checkboxes
		const classificationRaw = (
			formData.classification ??
			formData.federal_tax_classification ??
			formData.tax_classification ??
			formData.fed_tax_classification ??
			formData.federal_classification ??
			''
		);
		const normalizeClassification = (v) => {
			const s = String(v || '').trim().toLowerCase();
			if (!s) return '';
			// Common select values
			if (s === 'individual' || s === 'sole_proprietor' || s === 'sole proprietor' || s === 'single_member_llc' || s === 'single-member llc') return 'individual';
			if (s === 'c_corp' || s === 'c-corp' || s === 'c corporation' || s === 'ccorporation' || s === 'c-corporation') return 'c_corp';
			if (s === 's_corp' || s === 's-corp' || s === 's corporation' || s === 'scorporation' || s === 's-corporation') return 's_corp';
			if (s === 'partnership') return 'partnership';
			if (s === 'trust_estate' || s === 'trust/estate' || s === 'trust' || s === 'estate' || s === 'trust estate') return 'trust_estate';
			if (s === 'llc' || s === 'limited liability company' || s === 'limited liability company (llc)') return 'llc';
			if (s === 'other' || s === 'other (see instructions)' || s === 'other see instructions') return 'other';
			// If label text comes through, try fuzzy matching
			if (s.includes('individual') || s.includes('sole proprietor') || s.includes('single-member')) return 'individual';
			if (s.includes('c corporation')) return 'c_corp';
			if (s.includes('s corporation')) return 's_corp';
			if (s.includes('partnership')) return 'partnership';
			if (s.includes('trust') || s.includes('estate')) return 'trust_estate';
			if (s.includes('llc')) return 'llc';
			if (s.includes('other')) return 'other';
			return '';
		};
		const c = normalizeClassification(classificationRaw);
		const classificationFields = [
			'IndividualSoleProprietor',
			'CCorporation',
			'SCorporation',
			'Partnership',
			'TrustEstate',
			'LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership1',
			'OtherSeeInstructions1',
		];
		const selected = (c === 'individual')
			? 'IndividualSoleProprietor'
			: (c === 'c_corp')
				? 'CCorporation'
				: (c === 's_corp')
					? 'SCorporation'
					: (c === 'partnership')
						? 'Partnership'
						: (c === 'trust_estate')
							? 'TrustEstate'
							: (c === 'llc')
								? 'LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership1'
								: (c === 'other')
									? 'OtherSeeInstructions1'
									: '';
		if (selected) {
			setOnlyOne(selected, classificationFields);
		} else {
			classificationFields.forEach((f) => safeCheck(f, false));
		}

		// Signature image (embed into govt PDF)
		if (formData.signature_data) {
			let signatureEmbedded = false;
			try {
				const signatureImageBytes = await fetchSignatureImage(formData.signature_data);
				let signatureImage = null;
				try {
					signatureImage = await pdfDoc.embedPng(signatureImageBytes);
				} catch (e) {
					signatureImage = await pdfDoc.embedJpg(signatureImageBytes);
				}

				// Try to set image into a dedicated field if the template supports it
				try {
					const sigField = form.getField('SIGN_VENDOR');
					if (sigField && typeof sigField.setImage === 'function') {
						sigField.setImage(signatureImage);
						signatureEmbedded = true;
					}
				} catch (_) {}

				// Fallback: draw image on first page near signature area (best-effort)
				if (!signatureEmbedded) {
					const pages = pdfDoc.getPages();
					if (pages && pages.length) {
						const page = pages[0];
						const { height } = page.getSize();

						let placedUsingDateField = false;
						try {
							// Place signature on the same line, just before the Date field
							const dateField = form.getField('Date');
							const acro = dateField && dateField.acroField ? dateField.acroField : null;
							const widgets = acro && typeof acro.getWidgets === 'function' ? acro.getWidgets() : null;
							const w0 = widgets && widgets.length ? widgets[0] : null;
							const rect = w0 && typeof w0.getRectangle === 'function' ? w0.getRectangle() : null;
							if (rect && typeof rect.x === 'number' && typeof rect.y === 'number') {
								const gap = 14;
								const leftEdge = 80;
								const availableW = Math.max(80, rect.x - leftEdge - gap);
								const maxW = Math.min(240, availableW);
								const maxH = 22;

								const imgW = (signatureImage && typeof signatureImage.width === 'number') ? signatureImage.width : maxW;
								const imgH = (signatureImage && typeof signatureImage.height === 'number') ? signatureImage.height : maxH;
								const scale = Math.min(maxW / imgW, maxH / imgH);
								const drawW = Math.max(60, imgW * scale);
								const drawH = Math.max(14, imgH * scale);

								const maxX = Math.max(leftEdge, rect.x - drawW - gap);
								const centeredX = leftEdge + Math.max(0, (availableW - drawW) / 2);
								const x = Math.max(leftEdge, Math.min(maxX, centeredX));
								// Align to Date field baseline but keep within the signature line height
								const y = Math.max(50, rect.y + 2);
								page.drawImage(signatureImage, { x, y, width: drawW, height: drawH });
								placedUsingDateField = true;
							}
						} catch (_) {}

						if (!placedUsingDateField) {
							page.drawImage(signatureImage, {
								// Secondary fallback (tuned for common IRS W-9 template)
								x: 135,
								y: Math.max(60, height - 260),
								width: 290,
								height: 40,
							});
						}
						signatureEmbedded = true;
					}
				}
			} catch (e) {
				// non-fatal
			}
		}

		try {
			const pages = pdfDoc.getPages();
			const linkFont = await pdfDoc.embedFont(PDFLib.StandardFonts.HelveticaBold);
			const footerSize = 9;
			const footerY = 18;
			for (const page of pages) {
				const { width } = page.getSize();
				const footerWidth = linkFont.widthOfTextAtSize(footerBrandText, footerSize);
				const footerX = Math.max(20, width - footerWidth - 32);
				page.drawText(footerBrandText, {
					x: footerX,
					y: footerY,
					size: footerSize,
					font: linkFont,
					color: PDFLib.rgb(0, 0, 0),
				});
				addPdfLinkAnnotation(page, footerX, footerY - 2, footerWidth, footerSize + 4, footerBrandUrl);
			}
		} catch (_) {}

		try { form.flatten(); } catch (_) {}
		return await pdfDoc.save();
	}

	async function downloadGovernmentW9() {
		const $btn = $('#mypowerly-govt-form-download');
		const originalText = $btn.text();
		$btn.prop('disabled', true).text('Loading...');
		setDownloadButtonsEnabled(false);
		try {
			if (typeof PDFLib === 'undefined') {
				throw new Error('PDF library not loaded');
			}
			const formData = getFormDataObject();
			const pdfBase64 = await fetchGovtTemplateBase64();
			const templateBytes = base64ToUint8Array(pdfBase64);
			const filledBytes = await fillGovtPdf(templateBytes, formData);
			downloadPdfBytes(filledBytes, 'w9-govt-form-' + new Date().toISOString().split('T')[0] + '.pdf', 'govt_form');
		} finally {
			$btn.prop('disabled', false).text(originalText);
			if (validateForm()) {
				setDownloadButtonsEnabled(true);
			}
			// Reset form state to prevent conflicts
			resetW9FormState();
		}
	}

	// Use event delegation with namespace to prevent multiple bindings
	$(document).off('click.w9govt').on('click.w9govt', '#mypowerly-govt-form-download', function(e) {
		e.preventDefault();
		
		w91099chConsole.log('Official W9 Form button clicked');
		
		// Check if PDF library is loaded
		if (typeof PDFLib === 'undefined') {
			w91099chConsole.error('PDF library not loaded');
			alert('Error: PDF library not loaded. Please refresh the page and try again.');
			return;
		}
		
		// Validate form before downloading govt form
		if (!validateForm()) {
			const msg = (validateForm.errors && validateForm.errors.length)
				? validateForm.errors[0]
				: 'Please fill in all required fields correctly.';
			$('#mypowerly-w9-status, #w9-status').html('<p>' + msg + '</p>').addClass('error').show();
			return;
		}
		
		setDownloadButtonsEnabled(false);
		
		downloadGovernmentW9().catch(err => {
			w91099chConsole.error('Govt W-9 download failed:', err);
			alert('Error: ' + (err && err.message ? err.message : 'Download failed'));
			// Re-enable buttons on error
			setDownloadButtonsEnabled(true);
		});
	});

    // Initialize signature pad (admin form only)
    const canvas = document.getElementById('signature-canvas');
    const signaturePad = (canvas && typeof SignaturePad !== 'undefined')
        ? new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(0, 0, 0)'
        })
        : null;
    
    if (signaturePad && canvas) {
        // Make signature pad responsive
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const parentWidth = canvas.parentElement.offsetWidth;

            canvas.width = parentWidth * ratio;
            canvas.height = 200 * ratio;
            canvas.getContext('2d').scale(ratio, ratio);

            // Redraw the signature if it exists
            if (!signaturePad.isEmpty()) {
                const data = signaturePad.toData();
                signaturePad.clear();
                signaturePad.fromData(data);
            }
        }

        // Initial resize
        resizeCanvas();

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(resizeCanvas, 250);
        });
    }
    
    // Clear signature
    $(document).on('click', '#clear-signature', function() {
        if (signaturePad) {
            signaturePad.clear();
        }
    });

    // Signature pad functionality for shortcode form
    let shortcodeSignaturePad = null;
    let isDrawing = false;
    
    function initSignaturePad() {
        const $canvas = $('#mypowerly-w9-signature-canvas');
        if (!$canvas.length) {
            w91099chConsole.log('Canvas element not found');
            return false;
        }
        
        const canvas = $canvas[0];
        const ctx = canvas.getContext('2d');
        
        if (!ctx) {
            w91099chConsole.error('Could not get canvas context');
            return false;
        }
        
        // Set canvas size with proper scaling for high DPI displays
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const rect = canvas.getBoundingClientRect();
        const displayWidth = Math.max(rect.width, canvas.parentElement ? canvas.parentElement.clientWidth : 0);
        const displayHeight = 200;

        if (!displayWidth || displayWidth < 20) {
            w91099chConsole.warn('Canvas width is not ready yet, retrying initialization...', {
                rectWidth: rect.width,
                parentWidth: canvas.parentElement ? canvas.parentElement.clientWidth : 0
            });
            return false;
        }
        
        // Set display size (CSS)
        canvas.style.width = displayWidth + 'px';
        canvas.style.height = displayHeight + 'px';
        
        // Set actual size in memory (scaled for DPI)
        canvas.width = Math.floor(displayWidth * ratio);
        canvas.height = Math.floor(displayHeight * ratio);
        
        // Reset + scale context to avoid cumulative scaling on re-init
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        
        // Configure drawing context
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        // Clear canvas to white background
        ctx.fillStyle = 'white';
        ctx.clearRect(0, 0, displayWidth, displayHeight);
        ctx.fillRect(0, 0, displayWidth, displayHeight);
        
        w91099chConsole.log('Signature pad initialized:', {
            canvasWidth: canvas.width,
            canvasHeight: canvas.height,
            displayWidth: displayWidth,
            displayHeight: displayHeight,
            ratio: ratio
        });
        
        // Remove existing event handlers to prevent duplicates
        $canvas.off('mousedown mousemove mouseup mouseout touchstart touchmove touchend');
        
        // Mouse events
        $canvas.on('mousedown', startDrawing);
        $canvas.on('mousemove', draw);
        $canvas.on('mouseup', stopDrawing);
        $canvas.on('mouseout', stopDrawing);
        
        // Touch events
        $canvas.on('touchstart', function(e) {
            e.preventDefault();
            const touch = e.originalEvent.touches[0];
            const mouseEvent = new MouseEvent('mousedown', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        });
        
        $canvas.on('touchmove', function(e) {
            e.preventDefault();
            const touch = e.originalEvent.touches[0];
            const mouseEvent = new MouseEvent('mousemove', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        });
        
        $canvas.on('touchend', function(e) {
            e.preventDefault();
            const mouseEvent = new MouseEvent('mouseup', {});
            canvas.dispatchEvent(mouseEvent);
        });
        
        shortcodeSignaturePad = { canvas, ctx };
        return true;
    }
    
    function startDrawing(e) {
        if (!shortcodeSignaturePad) return;
        isDrawing = true;
        const rect = shortcodeSignaturePad.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        w91099chConsole.log('Start drawing at:', { 
            x, y, 
            clientX: e.clientX, 
            clientY: e.clientY, 
            rectLeft: rect.left, 
            rectTop: rect.top,
            ratio: Math.max(window.devicePixelRatio || 1, 1)
        });
        
        shortcodeSignaturePad.ctx.beginPath();
        shortcodeSignaturePad.ctx.moveTo(x, y);
    }
    
    function draw(e) {
        if (!isDrawing || !shortcodeSignaturePad) return;
        const rect = shortcodeSignaturePad.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        shortcodeSignaturePad.ctx.lineTo(x, y);
        shortcodeSignaturePad.ctx.stroke();
    }
    
    function stopDrawing() {
        if (!isDrawing || !shortcodeSignaturePad) return;
        isDrawing = false;
        
        // Save signature data
        const dataURL = shortcodeSignaturePad.canvas.toDataURL();
        $('#mypowerly-w9-signature-data').val(dataURL);
        
        // Add visual feedback
        $('#mypowerly-w9-signature-canvas').addClass('border-blue-500').removeClass('border-gray-300');
        
        // Enable download buttons when signature is drawn
        $('#mypowerly-w9-download, #mypowerly-govt-form-download').prop('disabled', false);
        
        w91099chConsole.log('Signature completed and saved');
    }
    
    function setDownloadButtonsEnabled(enabled) {
        $('#mypowerly-w9-download, #mypowerly-govt-form-download').prop('disabled', !enabled);
    }
    
    // Global state reset function to fix issues after downloads
    function resetW9FormState() {
        w91099chConsole.log('Resetting W9 form state...');
        
        // Reset download buttons
        const hasSignature = $('#mypowerly-w9-signature-data').val() && $('#mypowerly-w9-signature-data').val().length > 0;
        setDownloadButtonsEnabled(hasSignature);
        
        // Reset tools dropdown
        $('#w91099ch-client-tools-menu').addClass('hidden');
        $('#w91099ch-client-tools-btn').attr('aria-expanded', 'false');
        
        // Reset any loading states
        $('#mypowerly-govt-form-download').prop('disabled', false).text('Official W9 Form');
        $('#mypowerly-w9-download').prop('disabled', false).text('Print To PDF');
        
        // Reinitialize signature pad if needed
        if (!shortcodeSignaturePad) {
            initializeSignaturePadWithRetry();
        }
        
        w91099chConsole.log('W9 form state reset complete');
    }

    function clearShortcodeSignature() {
        const $canvas = $('#mypowerly-w9-signature-canvas');
        if (!$canvas.length) return;
        
        w91099chConsole.log('Clearing signature pad');
        
        // Reinitialize the entire signature pad to ensure proper clearing
        if (!initSignaturePad() && shortcodeSignaturePad && shortcodeSignaturePad.ctx) {
            const fallbackRect = shortcodeSignaturePad.canvas.getBoundingClientRect();
            shortcodeSignaturePad.ctx.fillStyle = 'white';
            shortcodeSignaturePad.ctx.clearRect(0, 0, fallbackRect.width, 200);
            shortcodeSignaturePad.ctx.fillRect(0, 0, fallbackRect.width, 200);
        }
        
        // Reset visual feedback
        $canvas.removeClass('border-blue-500').addClass('border-gray-300');
        
        // Clear the stored signature data
        $('#mypowerly-w9-signature-data').val('');
        setDownloadButtonsEnabled(false);
        
        w91099chConsole.log('Signature pad cleared and reinitialized');
    }

    // Initialize shortcode signature pad with retry mechanism
    function initializeSignaturePadWithRetry(retries = 0) {
        const initialized = initSignaturePad();
        if (initialized) {
            w91099chConsole.log('Signature pad initialized successfully');
            return;
        }

        if (retries < 20) {
            // Retry after a short delay if canvas or size not ready yet
            setTimeout(function() {
                initializeSignaturePadWithRetry(retries + 1);
            }, 250);
        } else {
            w91099chConsole.error('Signature pad initialization failed after retries');
        }
    }
    
    // Start initialization
    initializeSignaturePadWithRetry();
    
    // Periodic state check to automatically fix issues
    setInterval(function() {
        // Check if tools dropdown is stuck open
        const $menu = $('#w91099ch-client-tools-menu');
        const $btn = $('#w91099ch-client-tools-btn');
        
        if ($menu.length && $btn.length && !$menu.hasClass('hidden') && $btn.attr('aria-expanded') === 'false') {
            w91099chConsole.log('Detected stuck tools dropdown - fixing...');
            $menu.addClass('hidden');
            $btn.attr('aria-expanded', 'false');
        }
        
        // Check if download buttons are in inconsistent state
        const hasSignature = $('#mypowerly-w9-signature-data').val() && $('#mypowerly-w9-signature-data').val().length > 0;
        const $downloadBtn = $('#mypowerly-govt-form-download');
        
        if ($downloadBtn.length && hasSignature && $downloadBtn.prop('disabled')) {
            w91099chConsole.log('Detected inconsistent download button state - fixing...');
            setDownloadButtonsEnabled(true);
        }
        
        // Check if signature pad needs reinitialization
        if (!shortcodeSignaturePad && $('#mypowerly-w9-signature-canvas').length) {
            w91099chConsole.log('Signature pad missing - reinitializing...');
            initializeSignaturePadWithRetry();
        }
    }, 5000); // Check every 5 seconds
    
    // Manual reset trigger (for debugging - can be activated by users)
    window.resetW9Form = function() {
        w91099chConsole.log('Manual W9 form reset triggered');
        resetW9FormState();
        alert('W9 form has been reset. Please try again.');
    };
    
    // Add keyboard shortcut for manual reset (Ctrl+Shift+R)
    $(document).on('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && e.key === 'R') {
            e.preventDefault();
            resetW9FormState();
            w91099chConsole.log('Manual reset triggered via keyboard shortcut');
        }
    });

    // Disable download buttons until signature is drawn
    setDownloadButtonsEnabled(false);

    // Clear shortcode signature button (with namespace to prevent multiple bindings)
    $(document).off('click.w9clear').on('click.w9clear', '#mypowerly-w9-clear-signature', clearShortcodeSignature);
    
    // Handle window resize for signature pad
    let resizeTimeout;
    $(window).on('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            if (shortcodeSignaturePad) {
                // Save current signature if exists
                const currentData = $('#mypowerly-w9-signature-data').val();
                
                // Reinitialize signature pad
                initSignaturePad();
                
                // Restore signature if it existed
                if (currentData) {
                    const img = new Image();
                    img.onload = function() {
                        shortcodeSignaturePad.ctx.drawImage(img, 0, 0);
                    };
                    img.src = currentData;
                }
                
                w91099chConsole.log('Signature pad resized and restored');
            }
        }, 250);
    });
    
    // Input restrictions + formatting
    function w91099chOnlyDigits(val) {
        return String(val || '').replace(/\D/g, '');
    }

    function w91099chGetTinType() {
        return String($('#tin_type').val() || $('input[name="tin_type"]:checked').val() || '').trim().toLowerCase();
    }

    function w91099chFormatTinForDisplay(digits, tinType) {
        const d = w91099chOnlyDigits(digits).slice(0, 9);

        // Treat SSN/ITIN/ATIN as SSN-like
        const isSsnLikeTin = (tinType === 'ssn' || tinType === 'itin' || tinType === 'atin' || tinType === 'atn' || tinType === 'ain');
        if (isSsnLikeTin) {
            if (d.length <= 3) return d;
            if (d.length <= 5) return d.slice(0, 3) + '-' + d.slice(3);
            return d.slice(0, 3) + '-' + d.slice(3, 5) + '-' + d.slice(5);
        }

        // FEIN/EIN formatting
        if (tinType === 'fein' || tinType === 'ein') {
            if (d.length <= 2) return d;
            return d.slice(0, 2) + '-' + d.slice(2);
        }

        // Default: digits only
        return d;
    }

    function w91099chApplyTinFormatting($input) {
        if (!$input || !$input.length) return;
        const tinType = w91099chGetTinType();
        const digits = w91099chOnlyDigits($input.val()).slice(0, 9);
        const formatted = w91099chFormatTinForDisplay(digits, tinType);
        $input.val(formatted);
    }

    $(document).on('input', '#tin, #mypowerly-w9-tin', function() {
        w91099chApplyTinFormatting($(this));
    });

    // Re-format when TIN type changes
    $(document).on('change', '#tin_type, input[name="tin_type"]', function() {
        w91099chApplyTinFormatting($('#mypowerly-w9-tin, #tin').first());
    });

    $(document).on('input', '#zip', function() {
        const digits = String($(this).val() || '').replace(/\D/g, '').slice(0, 9);
        $(this).val(digits);
    });

    $(document).on('input', '#state', function() {
        const letters = String($(this).val() || '').replace(/[^a-zA-Z]/g, '').toUpperCase().slice(0, 2);
        $(this).val(letters);
    });

    // Form validation
    function validateForm() {
        validateForm.errors = [];

        const $form = $('#mypowerly-w9-form');
        $form.find('.error').removeClass('error');
        $form.find('[aria-invalid="true"]').attr('aria-invalid', 'false');

        const required = [
            { selectors: '#mypowerly-w9-name, #name', label: 'Name' },
            { selectors: '#mypowerly-w9-classification, #classification, #federal_tax_classification', label: 'Federal tax classification' },
            { selectors: '#mypowerly-w9-address, #address', label: 'Address' },
            { selectors: '#mypowerly-w9-city-state-zip, #city', label: 'City' },
            { selectors: '#mypowerly-w9-city-state-zip, #state', label: 'State' },
            { selectors: '#mypowerly-w9-city-state-zip, #zip', label: 'ZIP code' },
            { selectors: '#mypowerly-w9-tin, #tin', label: 'Taxpayer Identification Number' },
            { selectors: '#mypowerly-w9-certification-date, #certification_date', label: 'Certification date' },
        ];

        let isValid = true;
        let firstInvalidField = null;

        const markError = ($el, message) => {
            isValid = false;
            if ($el && $el.length) {
                $el.addClass('error').attr('aria-invalid', 'true');
                if (!firstInvalidField) firstInvalidField = $el;
            }
            if (message) {
                validateForm.errors.push(message);
            }
        };

        const getFirstField = (selectors) => {
            const parts = String(selectors).split(',').map(s => s.trim()).filter(Boolean);
            for (const sel of parts) {
                const $el = $(sel);
                if ($el.length) return $el;
            }
            return $();
        };

        required.forEach(({ selectors, label }) => {
            const $field = getFirstField(selectors);
            if (!$field.length) return;

            const value = String($field.val() || '').trim();
            if (!value) {
                markError($field, label + ' is required');
            }
        });

        const hasCombinedCityStateZip = $('#mypowerly-w9-city-state-zip').length;
        if (hasCombinedCityStateZip) {
            const $csz = $('#mypowerly-w9-city-state-zip');
            const cityStateZip = String($csz.val() || '').trim();
            if (!cityStateZip) {
                markError($csz, 'City, state, and ZIP code is required');
            } else {
                const cszPattern = /^[^,]+,\s*[A-Za-z]{2}\s*\d{5}(-\d{4})?$/;
                if (!cszPattern.test(cityStateZip)) {
                    markError($csz, 'City/State/ZIP format should be like: City, ST 12345');
                }
            }
        } else {
            const $city = $('#city');
            const $state = $('#state');
            const $zip = $('#zip');

            if ($city.length && !String($city.val() || '').trim()) {
                markError($city, 'City is required');
            }

            if ($state.length) {
                const state = String($state.val() || '').trim();
                if (!state) {
                    markError($state, 'State is required');
                } else if (!/^[A-Za-z]{2}$/.test(state)) {
                    markError($state, 'State must be 2 letters (e.g. NY)');
                }
            }

            if ($zip.length) {
                const zipDigits = String($zip.val() || '').replace(/\D/g, '');
                if (!zipDigits) {
                    markError($zip, 'ZIP code is required');
                } else if (!(zipDigits.length === 5 || zipDigits.length === 9)) {
                    markError($zip, 'ZIP code must be 5 or 9 digits');
                }
            }
        }

        const $tin = getFirstField('#mypowerly-w9-tin, #tin');
        const tinDigits = String($tin.val() || '').replace(/\D/g, '');
        if (!$tin.length) {
            // no-op
        } else if (!tinDigits) {
            markError($tin, 'TIN is required');
        } else if (tinDigits.length !== 9) {
            markError($tin, 'TIN must be exactly 9 digits');
        }
        
        // Check for signature (either signature_data or signature_pad drawn)
        const hasSignature = $('#mypowerly-w9-signature-data, #signature_data').val() ? true : false;
        if (!hasSignature) {
            markError(null, 'Signature is required');
        }

        // Validate tin type (admin select OR shortcode radio)
        const tinType = String($('#tin_type').val() || $('input[name="tin_type"]:checked').val() || '').trim();
        if (!tinType) {
            const $tinTypeField = $('#tin_type').length ? $('#tin_type') : $('input[name="tin_type"]').first();
            markError($tinTypeField, 'TIN type is required');
        }

        if (firstInvalidField && firstInvalidField.length && typeof firstInvalidField.focus === 'function') {
            firstInvalidField.focus();
        }

        return isValid;
    }
    
    // Classification field visibility toggle
    function toggleClassificationFields() {
        const classification = $('#mypowerly-w9-classification').val() || $('#classification').val() || $('#federal_tax_classification').val();
        const $llcWrapper = $('#mypowerly-w9-llc-tax-class-wrapper, #llc-tax-class-wrapper, #llc_classification_container');
        const $otherWrapper = $('#mypowerly-w9-other-class-wrapper, #other-class-wrapper, #other-class-wrapper');

        if (classification === 'llc') {
            $llcWrapper.show();
            $otherWrapper.hide();
        } else if (classification === 'other') {
            $llcWrapper.hide();
            $otherWrapper.show();
        } else {
            $llcWrapper.hide();
            $otherWrapper.hide();
        }
    }

    // Listen to both admin and shortcode classification selects
    $('#mypowerly-w9-classification, #classification, #federal_tax_classification').on('change', toggleClassificationFields);
    toggleClassificationFields(); // Initial state

    // Download button click handler
    $('#mypowerly-w9-download, #w9-download').on('click', function(e) {
        const $btn = $(this);
        if ($btn.attr('type') === 'submit') {
            return;
        }

        // Validate form first
        if (!validateForm()) {
            const msg = (validateForm.errors && validateForm.errors.length)
                ? validateForm.errors[0]
                : 'Please fill in all required fields correctly.';
            $('#mypowerly-w9-status, #w9-status').html('<p>' + msg + '</p>').addClass('error').show();
            return;
        }

        setDownloadButtonsEnabled(false);

        const $form = $(this).closest('form');
        const $submitBtn = $(this);
        const $status = $('#mypowerly-w9-status, #w9-status');
        const originalBtnText = $submitBtn.text();

        // Collect form data
        const formData = {
            name: $('#mypowerly-w9-name, #name').val() || '',
            business_name: $('#mypowerly-w9-business-name, #business_name').val() || '',
            classification: $('#mypowerly-w9-classification, #classification, #federal_tax_classification').val() || '',
            llc_tax_class: $('#mypowerly-w9-llc-tax-class, #llc_tax_class, #llc_classification').val() || '',
            other_class: $('#mypowerly-w9-other-class, #other_class, #other_classification').val() || '',
            exempt_payee_code: $('#mypowerly-w9-exempt-payee, #exempt_payee_code').val() || '',
            fatca_code: $('#mypowerly-w9-fatca, #fatca_code').val() || '',
            address: $('#mypowerly-w9-address, #address').val() || '',
            city_state_zip: $('#mypowerly-w9-city-state-zip, #city_state_zip').val() || '',
            city: $('#city').val() || '',
            state: $('#state').val() || '',
            zip: $('#zip').val() || '',
            requester: $('#mypowerly-w9-requester, #requester').val() || '',
            account_numbers: $('#mypowerly-w9-account-numbers, #account_numbers').val() || '',
            tin_type: $('#tin_type').val() || $('input[name="tin_type"]:checked').val() || '',
            tin: $('#mypowerly-w9-tin, #tin').val() || '',
            certification_name: $('#mypowerly-w9-certification-name, #certification_name').val() || '',
            certification_date: $('#mypowerly-w9-certification-date, #certification_date').val() || '',
            signature_data: $('#mypowerly-w9-signature-data, #signature_data').val() || ''
        };

        processW9Form(formData, $submitBtn, $status, originalBtnText);
    });

    // Reset button click handler
    $('#mypowerly-w9-reset, #w9-reset').on('click', function() {
        const $form = $(this).closest('form');
        $form[0].reset();
        $('#mypowerly-w9-status, #w9-status').hide();
        $('.mypowerly-w9-input, .mypowerly-w9-signature-container').removeClass('error');
        toggleClassificationFields();
        clearShortcodeSignature();
    });

    // Sync W-9 data to MyPowerly/SignMary (admin form)
    (function initAdminW9Sync() {
        const $syncBtn = $('#mypowerly-w9-sync, #mypowerly-w9-sync-mypowerly');
        if (!$syncBtn.length) {
            return;
        }

        // Remove inline click handler from admin-page.php (it scrolls instead of syncing)
        $syncBtn.off('click');

        const $status = $('#mypowerly-w9-status');

        const setStatus = (html, type) => {
            $status.html(html).removeClass('error success').addClass(type || '').show();
        };

        const val = (selector) => String($(selector).val() || '').trim();

        const getRequiredMissing = (payload) => {
            const required = [
                'name_on_tax_return',
                'federal_tax_classification',
                'address',
                'city',
                'state',
                'zip_code',
                'tin_type',
                'signature_data',
                'date'
            ];

            const missing = [];
            required.forEach((k) => {
                if (!payload[k]) missing.push(k);
            });
            return missing;
        };

        $syncBtn.on('click', async function(e) {
            e.preventDefault();

            const data = {
                name_on_tax_return: val('#name'),
                business_name: val('#business_name'),
                federal_tax_classification: val('#federal_tax_classification'),
                llc_classification: val('#llc_classification'),
                exempt_payee_code: val('#exempt_payee_code'),
                exemption_from_fatca_code: val('#fatca_code'),
                address: val('#address'),
                city: val('#city'),
                state: val('#state'),
                zip_code: val('#zip'),
                requester_name_address: val('#requester'),
                account_numbers: val('#account_numbers'),
                tin_type: val('#tin_type'),
                signature_data: val('#signature_data'),
                certification_name: val('#certification_name'),
                date: val('#certification_date'),
            };

            const missing = getRequiredMissing(data);
            if (missing.length) {
                setStatus('<p>Missing required field(s): <strong>' + missing.join(', ') + '</strong></p>', 'error');
                return;
            }

            if (typeof w91099chW9Form === 'undefined' || !w91099chW9Form.ajaxurl || !w91099chW9Form.nonce) {
                setStatus('<p>Sync is not available (missing AJAX configuration).</p>', 'error');
                return;
            }

            if (!window.confirm('This action will transmit the entered W-9/payee data to the external MyPowerly service (https://mypowerly.com). Do you want to continue?')) {
                return;
            }

            const originalText = $syncBtn.text();
            $syncBtn.prop('disabled', true);
            setStatus('<p>Syncing W-9 data...</p>');

            try {
                const res = await fetch(w91099chW9Form.ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: new URLSearchParams({
                        action: 'w91099ch_sync_w9_payee',
                        nonce: w91099chW9Form.nonce,
                        ...Object.keys(data).reduce((acc, key) => {
                            acc['data[' + key + ']'] = data[key];
                            return acc;
                        }, {})
                    }).toString()
                });

                w91099chConsole.log('W-9 sync HTTP status:', res.status);
                let json = null;
                try {
                    json = await res.json();
                } catch (parseErr) {
                    w91099chConsole.error('W-9 sync response was not valid JSON:', parseErr);
                    throw parseErr;
                }
                w91099chConsole.log('W-9 sync response JSON:', json);
                if (!json || !json.success) {
                    const err = (json && json.data && json.data.message)
                        ? json.data.message
                        : (json && json.data ? json.data : 'Sync failed');

                    const missingFields = (json && json.data && json.data.missing_fields && Array.isArray(json.data.missing_fields))
                        ? json.data.missing_fields
                        : [];

                    if (missingFields.length) {
                        setStatus('<p>Missing required field(s): <strong>' + missingFields.join(', ') + '</strong></p>', 'error');
                    } else {
                        setStatus('<p>' + String(err) + '</p>', 'error');
                    }
                    return;
                }

                setStatus('<p>W-9 data synced successfully!</p>', 'success');
            } catch (error) {
                setStatus('<p>Error: ' + (error && error.message ? error.message : 'Unknown error') + '</p>', 'error');
            } finally {
                $syncBtn.prop('disabled', false);
                $syncBtn.text(originalText);
            }
        });
    })();
    
    // Handle signature end for admin form
    if (signaturePad) {
        signaturePad.addEventListener('endStroke', function() {
            if (!signaturePad.isEmpty()) {
                $('#signature_data').val(signaturePad.toDataURL());
                $('#certification_name').val('Signed').addClass('is-valid');
                setDownloadButtonsEnabled(true);
            }
        });
    }
    
    // Handle admin form submission (signature-based)
    $('#mypowerly-w9-form').on('submit', function(e) {
        // Only intercept submit flows when the submit button exists (admin form)
        const $submitBtn = $(this).find('button[type="submit"]');
        if (!$submitBtn.length) {
            return;
        }

        e.preventDefault();

        const originalBtnText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text('Processing...');

        if (!validateForm()) {
            const msg = (validateForm.errors && validateForm.errors.length)
                ? validateForm.errors[0]
                : 'Please fill in all required fields correctly.';
            $('#mypowerly-w9-status').html('<p>' + msg + '</p>').addClass('error').show();
            $submitBtn.prop('disabled', false).text(originalBtnText);
            return false;
        }

        if (signaturePad && !signaturePad.isEmpty()) {
            $('#signature_data').val(signaturePad.toDataURL());
        }

        const $status = $('#mypowerly-w9-status');
        $status.html('<p>Preparing W-9 form...</p>').removeClass('error success').show();

        const formData = $(this).serializeArray();
        const formDataObj = {};
        formData.forEach(item => {
            formDataObj[item.name] = item.value;
        });

        processW9Form(formDataObj, $submitBtn, $status, originalBtnText);
    });
    
    // Handle classification visibility
    $('#federal_tax_classification, #mypowerly-w9-classification').on('change', function() {
        const val = $(this).val();

        // Admin form
        if (val === 'llc') {
            $('#llc_classification_container').show().find('select').prop('required', true);
        } else {
            $('#llc_classification_container').hide().find('select').prop('required', false);
        }

        // Shortcode form
        if (val === 'llc') {
            $('#mypowerly-w9-llc-tax-class-wrapper').show();
        } else {
            $('#mypowerly-w9-llc-tax-class-wrapper').hide();
        }

        if (val === 'other') {
            $('#mypowerly-w9-other-class-wrapper, #other-class-wrapper').show();
        } else {
            $('#mypowerly-w9-other-class-wrapper, #other-class-wrapper').hide();
        }
    }).trigger('change');
    
    // Set default date to today (if field exists)
    const today = new Date().toISOString().split('T')[0];
    $('#certification_date').val(today);

    if (canvas) {
        // Add touch support for mobile devices
        function handleTouch(e) {
            if (e.target === canvas) {
                e.preventDefault();
            }
        }

        // Add touch event listeners
        if ('ontouchstart' in window) {
            document.body.addEventListener('touchstart', handleTouch, { passive: false });
            document.body.addEventListener('touchend', handleTouch, { passive: false });
            document.body.addEventListener('touchmove', handleTouch, { passive: false });
        }
    }
    
    // Main function to process W-9 form
    async function processW9Form(formData, $submitBtn, $status, originalBtnText) {
        w91099chConsole.log('=== Starting W-9 Form Processing ===');
        w91099chConsole.log('Form data received:', formData);

        try {
            $status.html('<p>Generating printable PDF...</p>').removeClass('error success').show();

            w91099chConsole.log('Step 1: Generating printable PDF...');
            const printablePdfBytes = await generatePrintableW9Pdf(formData);
            w91099chConsole.log('Printable PDF generated successfully, size:', printablePdfBytes.length, 'bytes');

            $status.html('<p>Preparing download...</p>').removeClass('error success').show();

            w91099chConsole.log('Step 2: Downloading printable PDF...');
            const filename = 'w9-form-' + new Date().toISOString().split('T')[0] + '.pdf';
            downloadPdf(printablePdfBytes, filename);
            w91099chConsole.log('PDF download initiated with filename:', filename);
            
            $status.html('<p>PDF downloaded successfully!</p>').addClass('success');
            w91099chConsole.log('=== W-9 Form Processing Complete ===');
            
        } catch (error) {
            w91099chConsole.error('=== ERROR PROCESSING W-9 FORM ===');
            w91099chConsole.error('Error details:', error);
            w91099chConsole.error('Error stack:', error.stack);
            $status.html('<p>Error: ' + error.message + '</p>').addClass('error');
        } finally {
            $submitBtn.prop('disabled', false).text(originalBtnText || $submitBtn.text());
            if (validateForm()) {
                setDownloadButtonsEnabled(true);
            }
        }
    }

    async function generatePrintableW9Pdf(formData) {
        const getLogoUrl = () => {
            const candidate = (typeof w91099chConnectorW9 !== 'undefined' && w91099chConnectorW9 && w91099chConnectorW9.logo_url)
                ? String(w91099chConnectorW9.logo_url)
                : ((typeof w91099chW9Form !== 'undefined' && w91099chW9Form && w91099chW9Form.logo_url)
                    ? String(w91099chW9Form.logo_url)
                    : '');
            w91099chConsole.log('W-9 Logo URL candidate:', candidate);
            return candidate;
        };

        const getLogoDataUrl = () => {
            const candidate = (typeof w91099chConnectorW9 !== 'undefined' && w91099chConnectorW9 && w91099chConnectorW9.logo_data)
                ? String(w91099chConnectorW9.logo_data)
                : ((typeof w91099chW9Form !== 'undefined' && w91099chW9Form && w91099chW9Form.logo_data)
                    ? String(w91099chW9Form.logo_data)
                    : '');
            w91099chConsole.log('W-9 Logo data URL present:', candidate ? 'yes' : 'no');
            return candidate;
        };

        const fetchBinary = async (url) => {
            const res = await fetch(url, { cache: 'force-cache' });
            w91099chConsole.log('W-9 Logo fetch status:', res.status, url);
            if (!res.ok) {
                throw new Error('Failed to fetch asset');
            }
            const buf = await res.arrayBuffer();
            return new Uint8Array(buf);
        };

        const dataUrlToBytes = async (dataUrl) => {
            const s = String(dataUrl || '');
            if (!s.startsWith('data:')) {
                throw new Error('Invalid data url');
            }
            const parts = s.split(',');
            if (parts.length < 2) {
                throw new Error('Invalid data url');
            }
            const b64 = parts.slice(1).join(',').replace(/\s+/g, '');
            const binStr = atob(b64);
            const out = new Uint8Array(binStr.length);
            for (let i = 0; i < binStr.length; i++) out[i] = binStr.charCodeAt(i);
            return out;
        };

        const normalizeFormData = (raw) => {
            const d = { ...(raw || {}) };
            if (!d.federal_tax_classification && d.classification) d.federal_tax_classification = d.classification;
            if (!d.llc_classification && d.llc_tax_class) d.llc_classification = d.llc_tax_class;
            if (!d.other_classification && d.other_class) d.other_classification = d.other_class;

            if ((!d.city || !d.state || !d.zip) && d.city_state_zip) {
                const parts = String(d.city_state_zip).trim();
                const m = parts.match(/^(.*?)(?:,)?\s+([A-Za-z]{2})\s+(\d{5}(?:-\d{4})?)$/);
                if (m) {
                    d.city = d.city || m[1].trim();
                    d.state = d.state || m[2].trim();
                    d.zip = d.zip || m[3].trim();
                }
            }

            if (!d.city_state_zip) {
                const csz = [d.city, d.state, d.zip].filter(Boolean).join(', ');
                if (csz) d.city_state_zip = csz;
            }

            if (!d.tin && d.taxpayer_identification_number) d.tin = d.taxpayer_identification_number;
            return d;
        };

        const classificationLabel = (value) => {
            const map = {
                individual: 'Individual/sole proprietor or single-member LLC',
                c_corp: 'C Corporation',
                s_corp: 'S Corporation',
                partnership: 'Partnership',
                trust: 'Trust/estate',
                trust_estate: 'Trust/estate',
                llc: 'Limited liability company (LLC)',
                other: 'Other',
            };
            return map[value] || value || '';
        };

        const data = normalizeFormData(formData);

        const pdfDoc = await PDFLib.PDFDocument.create();
        const pageWidth = 612;
        const pageHeight = 792;
        let page = pdfDoc.addPage([pageWidth, pageHeight]);

        const font = await pdfDoc.embedFont(PDFLib.StandardFonts.Helvetica);
        const fontBold = await pdfDoc.embedFont(PDFLib.StandardFonts.HelveticaBold);

        const marginX = 48;
        const marginBottom = 54;
        const contentWidth = pageWidth - (marginX * 2);
        const labelWidth = 190;
        const columnGap = 14;
        const fontSize = 11;
        const lineGap = 6;

        const safeText = (v) => {
            // Handle case where v might be an object with nested data
            let value = v;
            if (v && typeof v === 'object') {
                if (v.data !== undefined) {
                    value = v.data;
                } else if (v.value !== undefined) {
                    value = v.value;
                } else {
                    // Convert object to string representation
                    value = JSON.stringify(v);
                }
            }
            return String(value == null ? '' : value).trim();
        };

        const wrapText = (text, maxWidth, size, useFont) => {
            const t = safeText(text);
            if (!t) return [''];

            const words = t.split(/\s+/);
            const lines = [];
            let current = '';

            const w = (s) => useFont.widthOfTextAtSize(s, size);

            for (const word of words) {
                const test = current ? (current + ' ' + word) : word;
                if (w(test) <= maxWidth) {
                    current = test;
                } else {
                    if (current) lines.push(current);
                    if (w(word) <= maxWidth) {
                        current = word;
                    } else {
                        let chunk = '';
                        for (const ch of word) {
                            const testChunk = chunk + ch;
                            if (w(testChunk) <= maxWidth) {
                                chunk = testChunk;
                            } else {
                                if (chunk) lines.push(chunk);
                                chunk = ch;
                            }
                        }
                        current = chunk;
                    }
                }
            }

            if (current) lines.push(current);
            return lines;
        };

        const colors = {
            headerBgStart: PDFLib.rgb(0.12, 0.23, 0.54),   // #1e3a8a
            headerBgEnd: PDFLib.rgb(0.12, 0.25, 0.69),      // #1e40af
            headerText: PDFLib.rgb(1, 1, 1),
            subtleText: PDFLib.rgb(0.4, 0.4, 0.4),
            border: PDFLib.rgb(0.76, 0.78, 0.82),           // #c3c4c7
            rowAlt: PDFLib.rgb(0.98, 0.98, 0.99),
            sectionBg: PDFLib.rgb(0.93, 0.95, 0.98),        // #edf2fb
            black: PDFLib.rgb(0, 0, 0),
        };

        const headerH = 52;
        const sectionH = 22;
        const rowPaddingX = 10;
        const rowPaddingY = 7;
        const lineHeight = fontSize + 4;
        const valueX = marginX + labelWidth + columnGap;
        const valueMaxWidth = (marginX + contentWidth) - valueX - rowPaddingX;

        let embeddedLogo = null;
        try {
            let logoBytes = null;
            const logoDataUrl = getLogoDataUrl();
            if (logoDataUrl) {
                try {
                    logoBytes = await dataUrlToBytes(logoDataUrl);
                    w91099chConsole.log('Logo: loaded from base64 data');
                } catch (e) {
                    logoBytes = null;
                }
            }

            if (!logoBytes) {
                const logoUrl = getLogoUrl();
                if (logoUrl) {
                    logoBytes = await fetchBinary(logoUrl);
                    w91099chConsole.log('Logo: loaded from URL', logoUrl);
                }
            }

            if (logoBytes) {
                try {
                    embeddedLogo = await pdfDoc.embedPng(logoBytes);
                } catch (e) {
                    embeddedLogo = await pdfDoc.embedJpg(logoBytes);
                }
                w91099chConsole.log('Logo: embedded successfully');
            } else {
                w91099chConsole.warn('Logo: no bytes available (logoBytes is empty)');
            }
        } catch (e) {
            embeddedLogo = null;
            w91099chConsole.warn('Logo: embed failed', e);
        }

        const drawFooter = () => {
            const footerText = footerBrandText;
            const footerFontSize = 8;
            const footerY = 18;
            const textWidth = font.widthOfTextAtSize(footerText, footerFontSize);
            const x = pageWidth - marginX - textWidth;
            page.drawText(footerText, {
                x,
                y: footerY,
                size: footerFontSize,
                font,
                color: colors.subtleText,
            });

            addPdfLinkAnnotation(page, x, footerY - 2, textWidth, footerFontSize + 4, footerBrandUrl);
        };

        const drawHeader = () => {
            // Gradient header background
            for (let i = 0; i < headerH; i++) {
                const t = i / headerH;
                const startR = typeof colors.headerBgStart.r === 'number' ? colors.headerBgStart.r : 0.12;
                const startG = typeof colors.headerBgStart.g === 'number' ? colors.headerBgStart.g : 0.23;
                const startB = typeof colors.headerBgStart.b === 'number' ? colors.headerBgStart.b : 0.54;
                const endR = typeof colors.headerBgEnd.r === 'number' ? colors.headerBgEnd.r : 0.12;
                const endG = typeof colors.headerBgEnd.g === 'number' ? colors.headerBgEnd.g : 0.25;
                const endB = typeof colors.headerBgEnd.b === 'number' ? colors.headerBgEnd.b : 0.69;
                
                const r = startR * (1 - t) + endR * t;
                const g = startG * (1 - t) + endG * t;
                const b = startB * (1 - t) + endB * t;
                page.drawRectangle({
                    x: 0,
                    y: pageHeight - headerH + i,
                    width: pageWidth,
                    height: 1,
                    color: PDFLib.rgb(r, g, b),
                });
            }

            page.drawText('W-9 Information', {
                x: marginX,
                y: pageHeight - 34,
                size: 18,
                font: fontBold,
                color: colors.headerText,
            });

            if (embeddedLogo) {
                const targetW = 64;
                const targetH = 64;
                const logoX = pageWidth - marginX - targetW;
                const logoY = pageHeight - headerH + Math.floor((headerH - targetH) / 2);

                page.drawImage(embeddedLogo, {
                    x: logoX,
                    y: logoY,
                    width: targetW,
                    height: targetH,
                });

                addPdfLinkAnnotation(page, logoX, logoY, targetW, targetH, footerBrandUrl);
            }

            page.drawText('Generated: ' + new Date().toLocaleString(), {
                x: marginX,
                y: pageHeight - 48,
                size: 9,
                font,
                color: PDFLib.rgb(0.9, 0.93, 1),
            });

            drawFooter();
        };

        let cursorY;
        let rowIndex = 0;

        const newPage = () => {
            page = pdfDoc.addPage([pageWidth, pageHeight]);
            drawHeader();
            cursorY = pageHeight - headerH - 18;
        };

        const ensureSpace = (heightNeeded) => {
            if (cursorY - heightNeeded < marginBottom) {
                newPage();
            }
        };

        const drawSection = (title) => {
            ensureSpace(sectionH + 10);

            page.drawRectangle({
                x: marginX,
                y: cursorY - sectionH,
                width: contentWidth,
                height: sectionH,
                color: colors.sectionBg,
                borderColor: colors.border,
                borderWidth: 1,
            });

            page.drawText(safeText(title), {
                x: marginX + rowPaddingX,
                y: cursorY - 16,
                size: 11,
                font: fontBold,
                color: colors.black,
            });

            cursorY -= (sectionH + 8);
            rowIndex = 0;
        };

        const drawTableRow = (label, value) => {
            const l = safeText(label);
            const v = safeText(value) || '-';

            const valueLines = wrapText(v, valueMaxWidth, fontSize, font);
            const rowHeight = (valueLines.length * lineHeight) + (rowPaddingY * 2);

            ensureSpace(rowHeight);

            const yBottom = cursorY - rowHeight;
            const fill = (rowIndex % 2 === 0) ? colors.rowAlt : PDFLib.rgb(1, 1, 1);

            page.drawRectangle({
                x: marginX,
                y: yBottom,
                width: contentWidth,
                height: rowHeight,
                color: fill,
                borderColor: colors.border,
                borderWidth: 1,
            });

            page.drawLine({
                start: { x: valueX - (columnGap / 2), y: yBottom },
                end: { x: valueX - (columnGap / 2), y: yBottom + rowHeight },
                thickness: 1,
                color: colors.border,
            });

            page.drawText(l, {
                x: marginX + rowPaddingX,
                y: cursorY - rowPaddingY - fontSize,
                size: fontSize,
                font: fontBold,
                color: colors.black,
            });

            let textY = cursorY - rowPaddingY - fontSize;
            for (const line of valueLines) {
                page.drawText(line, {
                    x: valueX,
                    y: textY,
                    size: fontSize,
                    font,
                    color: colors.black,
                });
                textY -= lineHeight;
            }

            cursorY = yBottom;
            rowIndex += 1;
        };

        const drawSignatureRow = async () => {
            const label = 'Signature';
            const imgH = 62;
            const rowHeight = imgH + (rowPaddingY * 2);

            ensureSpace(rowHeight);

            const yBottom = cursorY - rowHeight;
            const fill = (rowIndex % 2 === 0) ? colors.rowAlt : PDFLib.rgb(1, 1, 1);

            page.drawRectangle({
                x: marginX,
                y: yBottom,
                width: contentWidth,
                height: rowHeight,
                color: fill,
                borderColor: colors.border,
                borderWidth: 1,
            });

            page.drawLine({
                start: { x: valueX - (columnGap / 2), y: yBottom },
                end: { x: valueX - (columnGap / 2), y: yBottom + rowHeight },
                thickness: 1,
                color: colors.border,
            });

            page.drawText(label, {
                x: marginX + rowPaddingX,
                y: cursorY - rowPaddingY - fontSize,
                size: fontSize,
                font: fontBold,
                color: colors.black,
            });

            try {
                const signatureImageBytes = await fetchSignatureImage(data.signature_data);
                const signatureImage = await pdfDoc.embedPng(signatureImageBytes);

                const maxW = (marginX + contentWidth) - valueX - rowPaddingX;
                const imgW = Math.min(260, maxW);
                const imgX = valueX;
                const imgY = yBottom + rowPaddingY;

                page.drawRectangle({
                    x: imgX,
                    y: imgY,
                    width: imgW,
                    height: imgH,
                    borderColor: colors.border,
                    borderWidth: 1,
                    color: PDFLib.rgb(1, 1, 1),
                });

                page.drawImage(signatureImage, {
                    x: imgX + 6,
                    y: imgY + 6,
                    width: imgW - 12,
                    height: imgH - 12,
                });
            } catch (e) {
                page.drawText('Signed (image could not be embedded)', {
                    x: valueX,
                    y: cursorY - rowPaddingY - fontSize,
                    size: fontSize,
                    font,
                    color: colors.subtleText,
                });
            }

            cursorY = yBottom;
            rowIndex += 1;
        };

        drawHeader();
        cursorY = pageHeight - headerH - 18;

        drawSection('Name & Business');
        drawTableRow('1. Name', data.name);
        drawTableRow('2. Business name', data.business_name);
        drawTableRow('3. Federal tax classification', classificationLabel(data.federal_tax_classification));
        if (data.federal_tax_classification === 'llc') drawTableRow('LLC tax classification', data.llc_classification);
        if (data.federal_tax_classification === 'other') drawTableRow('Other classification', data.other_classification);
        drawTableRow('Exempt payee code', data.exempt_payee_code);
        drawTableRow('FATCA code', data.fatca_code);

        drawSection('Address');
        drawTableRow('Address', data.address);
        drawTableRow('City, State, ZIP', data.city_state_zip);
        drawTableRow("Requester name/address", data.requester);
        drawTableRow('Account number(s)', data.account_numbers);

        drawSection('Taxpayer Identification');
        drawTableRow('TIN type', (data.tin_type || '').toUpperCase());
        drawTableRow('TIN', data.tin);

        drawSection('Certification');
        drawTableRow('Certification name', data.certification_name);
        drawTableRow('Certification date', data.certification_date);
        if (data.signature_data) {
            await drawSignatureRow();
        }

        return await pdfDoc.save();
    }

    async function fetchW9Pdf() {
        w91099chConsole.log('Fetching W-9 PDF via WP AJAX proxy (avoids CORS)...');
        const ajaxUrl = (typeof w91099chW9Form !== 'undefined' && w91099chW9Form.ajaxurl)
            ? w91099chW9Form.ajaxurl
            : ((typeof w91099chConnectorW9 !== 'undefined' && w91099chConnectorW9.ajaxurl)
                ? w91099chConnectorW9.ajaxurl
                : (typeof ajaxurl !== 'undefined' ? ajaxurl : null));
        if (!ajaxUrl) {
            throw new Error('AJAX URL not available');
        }

        const action = (typeof w91099chW9Form !== 'undefined' && w91099chW9Form.pdf_action)
            ? w91099chW9Form.pdf_action
            : ((typeof w91099chConnectorW9 !== 'undefined' && w91099chConnectorW9.pdf_action)
                ? w91099chConnectorW9.pdf_action
                : 'w91099ch_get_fw9_pdf');

        const nonce = (typeof w91099chW9Form !== 'undefined' && w91099chW9Form.nonce)
            ? w91099chW9Form.nonce
            : ((typeof w91099chConnectorW9 !== 'undefined' && w91099chConnectorW9.nonce)
                ? w91099chConnectorW9.nonce
                : '');

        const res = await fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams({
                action,
                nonce,
                form_data: ''
            }).toString()
        });

        if (!res.ok) {
            throw new Error('Failed to fetch W-9 PDF (proxy request failed)');
        }

        const contentType = (res.headers.get('content-type') || '').toLowerCase();

        // Endpoint: w91099ch_get_fw9_pdf returns raw PDF bytes
        if (contentType.includes('application/pdf')) {
            const arrayBuffer = await res.arrayBuffer();
            const byteArray = new Uint8Array(arrayBuffer);
            w91099chConsole.log('Proxy PDF bytes received (raw):', byteArray.length);
            return byteArray;
        }

        // Endpoint: w91099ch_generate_w9_pdf returns JSON with base64
        const json = await res.json();
        if (!json || !json.success || !json.data || !json.data.pdf_base64) {
            const msg = (json && json.data && json.data.message) ? json.data.message : 'Invalid proxy response';
            throw new Error(msg);
        }

        const byteCharacters = atob(json.data.pdf_base64);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        const byteArray = new Uint8Array(byteNumbers);
        w91099chConsole.log('Proxy PDF bytes decoded (base64):', byteArray.length);
        return byteArray;
    }
    
    // Fill W-9 PDF with form data
    async function fillW9Pdf(pdfBytes, formData) {
        w91099chConsole.log('=== Starting PDF Fill Process ===');
        w91099chConsole.log('PDF bytes received:', pdfBytes.length);

        const normalizeFormData = (raw) => {
            const d = { ...(raw || {}) };
            
            // Helper function to safely extract string values
            const safeString = (val) => {
                if (val && typeof val === 'object') {
                    if (val.data !== undefined) return String(val.data);
                    if (val.value !== undefined) return String(val.value);
                    return JSON.stringify(val);
                }
                return String(val || '');
            };
            
            // Support both shortcode field names and admin page field names
            if (!d.federal_tax_classification && d.classification) d.federal_tax_classification = d.classification;
            if (!d.llc_classification && d.llc_tax_class) d.llc_classification = d.llc_tax_class;
            if (!d.other_classification && d.other_class) d.other_classification = d.other_class;

            // Some versions use single input for city/state/zip
            if ((!d.city || !d.state || !d.zip) && d.city_state_zip) {
                const parts = safeString(d.city_state_zip).trim();
                // best-effort parse: "City, ST 12345" or "City ST 12345"
                const m = parts.match(/^(.*?)(?:,)?\s+([A-Za-z]{2})\s+(\d{5}(?:-\d{4})?)$/);
                if (m) {
                    d.city = d.city || m[1].trim();
                    d.state = d.state || m[2].trim();
                    d.zip = d.zip || m[3].trim();
                }
            }

            // Map tin key differences
            if (!d.tin && d.taxpayer_identification_number) d.tin = d.taxpayer_identification_number;

            return d;
        };

        const data = normalizeFormData(formData);

        // Load the PDF
        const pdfDoc = await PDFLib.PDFDocument.load(pdfBytes);
        w91099chConsole.log('PDF loaded successfully');

        // Add logo + link (top-right) if available
        try {
            const logoDataUrl = (window.w91099chW9Form && window.w91099chW9Form.logo_data)
                || (window.w91099chConnectorW9 && window.w91099chConnectorW9.logo_data)
                || '';
            const logoUrl = (window.w91099chW9Form && window.w91099chW9Form.logo_url)
                || (window.w91099chConnectorW9 && window.w91099chConnectorW9.logo_url)
                || '';

            const dataUrlToBytes = (dataUrl) => {
                const s = String(dataUrl || '');
                if (!s.startsWith('data:')) return null;
                const parts = s.split(',');
                if (parts.length < 2) return null;
                const b64 = parts.slice(1).join(',').replace(/\s+/g, '');
                const binStr = atob(b64);
                const out = new Uint8Array(binStr.length);
                for (let i = 0; i < binStr.length; i++) out[i] = binStr.charCodeAt(i);
                return out;
            };

            let logoBytes = null;
            if (logoDataUrl) {
                try {
                    logoBytes = dataUrlToBytes(logoDataUrl);
                    if (logoBytes && logoBytes.length) {
                        w91099chConsole.log('Logo (fill): loaded from base64 data');
                    } else {
                        logoBytes = null;
                    }
                } catch (e) {
                    logoBytes = null;
                }
            }

            if (!logoBytes && logoUrl) {
                const res = await fetch(logoUrl, { cache: 'force-cache' });
                if (res.ok) {
                    const buf = await res.arrayBuffer();
                    logoBytes = new Uint8Array(buf);
                    w91099chConsole.log('Logo (fill): loaded from URL', logoUrl);
                }
            }

            if (logoBytes && logoBytes.length) {
                const pages = pdfDoc.getPages();
                if (pages.length) {
                    const page = pages[0];
                    const { width, height } = page.getSize();

                    let logoImage = null;
                    try {
                        logoImage = await pdfDoc.embedPng(logoBytes);
                    } catch (e) {
                        logoImage = await pdfDoc.embedJpg(logoBytes);
                    }

                    if (logoImage) {
                        const logoWidth = 120;
                        const logoHeight = (logoImage.height / logoImage.width) * logoWidth;
                        const x = Math.max(20, width - logoWidth - 28);
                        const y = Math.max(20, height - logoHeight - 22);

                        page.drawImage(logoImage, {
                            x,
                            y,
                            width: logoWidth,
                            height: logoHeight,
                        });

                        const linkText = footerBrandText;
                        const linkFont = await pdfDoc.embedFont(PDFLib.StandardFonts.HelveticaBold);
                        page.drawText(linkText, {
                            x,
                            y: Math.max(10, y - 12),
                            size: 8,
                            font: linkFont,
                            color: PDFLib.rgb(0, 0, 0),
                            link: footerBrandUrl
                        });
                    }
                }
            } else {
                w91099chConsole.warn('Logo (fill): no bytes available');
            }
        } catch (e) {
            w91099chConsole.warn('Could not embed logo/link in PDF:', e);
        }

        // Get the form (W-9 PDF is an AcroForm PDF)
        const form = pdfDoc.getForm();

        // Debug: Log fields once (helps future tuning)
        try {
            const fields = form.getFields();
            w91099chConsole.log('Number of PDF fields found:', fields.length);
        } catch (e) {
            w91099chConsole.warn('Could not enumerate PDF fields:', e);
        }

        const safeGetField = (name) => {
            try {
                return form.getField(name);
            } catch (e) {
                return null;
            }
        };

        const safeCheck = (name) => {
            const f = safeGetField(name);
            if (!f) return false;
            try {
                if (typeof f.check === 'function') {
                    f.check();
                    return true;
                }
            } catch (e) {
                return false;
            }
            return false;
        };

        const tryNames = (names, fn) => {
            for (const n of names) {
                const ok = fn(n);
                if (ok) return true;
            }
            return false;
        };

        const safeSetText = (name, value) => {
            if (!value) return false;
            const f = safeGetField(name);
            if (!f) return false;
            try {
                // Handle case where value might be an object
                let textValue = value;
                if (value && typeof value === 'object') {
                    if (value.data !== undefined) {
                        textValue = value.data;
                    } else if (value.value !== undefined) {
                        textValue = value.value;
                    } else {
                        textValue = JSON.stringify(value);
                    }
                }
                // text fields support setText
                f.setText(String(textValue));
                return true;
            } catch (e) {
                return false;
            }
        };

        const tryFillUsingAcroForm = async () => {
            const normalizeTinDigits = (v) => {
                // Handle case where v might be an object with a data property
                let value = v;
                if (v && typeof v === 'object') {
                    if (v.data !== undefined) {
                        value = v.data;
                    } else if (v.value !== undefined) {
                        value = v.value;
                    } else {
                        // If it's an object but doesn't have data/value, convert to string
                        value = JSON.stringify(v);
                    }
                }
                return String(value || '').replace(/[^0-9]/g, '');
            };
            const tinDigits = normalizeTinDigits(data.tin);

            // 1) Try your custom template field names first (fw9_IREG_esign)
            const okCustom = {
                name: safeSetText('PrintOrTypeSeeSpecificInstructionsOnPage3', data.name),
                business_name: safeSetText('BusinessNameDisregardedEntityNameIfDifferentFromAbove', data.business_name),
                exempt_payee_code: safeSetText('ExemptPayeeCodeIfAny', data.exempt_payee_code),
                fatca_code: safeSetText('ExemptionFromForeignAccountTaxComplianceActFatcaReportingCodeIfAny', data.fatca_code),
                requester: safeSetText('RequesterSNameAndAddressOptional', data.requester),
                account_numbers: safeSetText('ListAccountNumberSHereOptional', data.account_numbers),
                city_state_zip: safeSetText('CityStateAndZipCode', (data.city_state_zip || [data.city, data.state, data.zip].filter(Boolean).join(', '))),
                date: safeSetText('Date', data.certification_date),
                sign_vendor: safeSetText('SIGN_VENDOR', data.certification_name || (data.signature_data ? 'Signed' : '')),
            };

            // Tax classification checkboxes (custom template)
            if (data.federal_tax_classification) {
                const customCheckboxMap = {
                    individual: ['IndividualSoleProprietor'],
                    c_corp: ['CCorporation'],
                    s_corp: ['SCorporation'],
                    partnership: ['Partnership'],
                    trust: ['TrustEstate'],
                    trust_estate: ['TrustEstate'],
                    other: ['OtherSeeInstructions1'],
                    llc: ['LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership1'],
                };

                if (customCheckboxMap[data.federal_tax_classification]) {
                    tryNames(customCheckboxMap[data.federal_tax_classification], safeCheck);
                }

                if (data.federal_tax_classification === 'other') {
                    safeSetText('OtherSeeInstructions2', data.other_classification || data.other_class);
                }

                if (data.federal_tax_classification === 'llc') {
                    safeSetText('LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership2', data.llc_classification || data.llc_tax_class);
                }
            }

            // SSN/FEIN (custom template)
            const tinType = String(data.tin_type || '').trim().toLowerCase();
            
            // Debug logging for TIN type handling (custom template)
            w91099chConsole.log('Custom TIN Debug - Type:', tinType, 'Digits:', tinDigits, 'Length:', tinDigits.length);
            
            // Enhanced TIN type detection for ITIN/ATIN (custom template)
            const isSsnLikeTin = (tinType === 'ssn' || tinType === 'itin' || tinType === 'atin' || tinType === 'atn' || tinType === 'ain');
            
            w91099chConsole.log('Custom TIN Debug - isSsnLikeTin:', isSsnLikeTin);
            
            if (isSsnLikeTin) {
                w91099chConsole.log('Custom TIN Debug - Processing as SSN-like TIN');
                // Try full SSN field if present
                safeSetText('SocialSecurityNumber', tinDigits);
                if (tinDigits.length >= 9) {
                    safeSetText('VENDOR_SSN_MIDDLE2', tinDigits.slice(3, 5));
                    safeSetText('VENDOR_SSN_LAST4', tinDigits.slice(-4));
                }
            } else if (tinType === 'fein') {
                w91099chConsole.log('Custom TIN Debug - Processing as FEIN');
                safeSetText('EmployerIdentificationNumber', tinDigits);
                if (tinDigits.length >= 9) {
                    safeSetText('VENDOR_FEIN_LAST7', tinDigits.slice(-7));
                }
            }

            // Address: your extracted list didn't include an explicit address field key.
            // We still try common candidates if present.
            safeSetText('Address', data.address);
            safeSetText('AddressNumberStreetAndAptOrSuiteNo', data.address);

            const customSucceeded = Boolean(okCustom.name || okCustom.city_state_zip || okCustom.date || okCustom.exempt_payee_code || okCustom.fatca_code);
            if (customSucceeded) {
                w91099chConsole.log('W-9 PDF fill: used custom template fields (fw9_IREG_esign)');
                return true;
            }

            w91099chConsole.log('W-9 PDF fill: custom template fields not found, falling back to standard field names');

            // 2) Fall back to standard W-9 field names
            const ok = {
                name: safeSetText('topmostSubform[0].Page1[0].f1_0_0[0]', data.name),
                business_name: safeSetText('topmostSubform[0].Page1[0].f1_0_1[0]', data.business_name),
                exempt_payee_code: safeSetText('topmostSubform[0].Page1[0].f1_0_4[0]', data.exempt_payee_code),
                fatca_code: safeSetText('topmostSubform[0].Page1[0].f1_0_5[0]', data.fatca_code),
                address: safeSetText('topmostSubform[0].Page1[0].f1_0_6[0]', data.address),
                city: safeSetText('topmostSubform[0].Page1[0].f1_0_7[0]', data.city),
                state: safeSetText('topmostSubform[0].Page1[0].f1_0_8[0]', data.state),
                zip: safeSetText('topmostSubform[0].Page1[0].f1_0_9[0]', data.zip),
                requester: safeSetText('topmostSubform[0].Page1[0].f1_0_10[0]', data.requester),
                account_numbers: safeSetText('topmostSubform[0].Page1[0].f1_0_11[0]', data.account_numbers),
                tin: safeSetText('topmostSubform[0].Page1[0].f1_0_12[0]', data.tin),
                date: safeSetText('topmostSubform[0].Page1[0].f1_0_13[0]', data.certification_date),
            };

            // Federal tax classification checkboxes
            // Note: Field names may vary slightly between revisions; we try both full + short names.
            const classMap = {
                individual: ['topmostSubform[0].Page1[0].c1_1[0]', 'c1_1[0]', 'f1_0_2[0]'],
                c_corp: ['topmostSubform[0].Page1[0].c1_1[1]', 'c1_1[1]', 'f1_0_2[1]'],
                s_corp: ['topmostSubform[0].Page1[0].c1_1[2]', 'c1_1[2]', 'f1_0_2[2]'],
                partnership: ['topmostSubform[0].Page1[0].c1_1[3]', 'c1_1[3]', 'f1_0_2[3]'],
                trust: ['topmostSubform[0].Page1[0].c1_1[4]', 'c1_1[4]', 'f1_0_2[4]'],
                trust_estate: ['topmostSubform[0].Page1[0].c1_1[4]', 'c1_1[4]', 'f1_0_2[4]'],
                other: ['topmostSubform[0].Page1[0].c1_1[6]', 'c1_1[6]', 'f1_0_2[6]'],
                llc: ['topmostSubform[0].Page1[0].c1_1[5]', 'c1_1[5]', 'f1_0_2[5]'],
            };

            if (data.federal_tax_classification && classMap[data.federal_tax_classification]) {
                tryNames(classMap[data.federal_tax_classification], safeCheck);
            }

            // TIN type checkbox/radio (SSN/FEIN) - different revisions use different widgets.
            // We try common candidates; if none exist, the number is still filled in the TIN field.
            if (data.tin_type) {
                const tinTypeMap = {
                    ssn: ['topmostSubform[0].Page1[0].c1_2[0]', 'c1_2[0]'],
                    fein: ['topmostSubform[0].Page1[0].c1_2[1]', 'c1_2[1]'],
                    itn: ['topmostSubform[0].Page1[0].c1_2[2]', 'c1_2[2]'],
                    atin: ['topmostSubform[0].Page1[0].c1_2[3]', 'c1_2[3]'],
                };
                if (tinTypeMap[data.tin_type]) {
                    tryNames(tinTypeMap[data.tin_type], safeCheck);
                }
            }

            // Signature: try AcroForm image field if present, else fallback overlay.
            let signatureEmbedded = false;
            if (data.signature_data) {
                const sigFieldNameCandidates = [
                    'topmostSubform[0].Page1[0].f1_0_14[0]',
                    'topmostSubform[0].Page1[0].f1_0_9[0]'
                ];

                for (const n of sigFieldNameCandidates) {
                    const f = safeGetField(n);
                    if (!f) continue;
                    try {
                        const signatureImageBytes = await fetchSignatureImage(data.signature_data);
                        const signatureImage = await pdfDoc.embedPng(signatureImageBytes);
                        if (typeof f.setImage === 'function') {
                            f.setImage(signatureImage);
                            signatureEmbedded = true;
                            break;
                        }
                    } catch (e) {
                        // ignore
                    }
                }

                if (!signatureEmbedded) {
                    // fallback: draw image on page near signature area (best-effort)
                    try {
                        const signatureImageBytes = await fetchSignatureImage(data.signature_data);
                        const signatureImage = await pdfDoc.embedPng(signatureImageBytes);
                        const pages = pdfDoc.getPages();
                        if (pages.length) {
                            const page = pages[0];
                            const { height } = page.getSize();
                            page.drawImage(signatureImage, {
                                x: 75,
                                y: height - 120,
                                width: 180,
                                height: 40,
                            });
                            signatureEmbedded = true;
                        }
                    } catch (e) {
                        w91099chConsole.warn('Could not embed signature:', e);
                    }
                }
            }

            const irsSucceeded = Object.values(ok).some(Boolean);
            return Boolean(irsSucceeded || signatureEmbedded);
        };

        let usedAcroForm = false;
        try {
            usedAcroForm = await tryFillUsingAcroForm();
        } catch (e) {
            usedAcroForm = false;
        }

        w91099chConsole.log('W-9 PDF fill: usedAcroForm =', usedAcroForm);

        if (!usedAcroForm) {
            w91099chConsole.warn('AcroForm fields not found/usable. Falling back to coordinate overlay.');
            const pages = pdfDoc.getPages();
            if (pages.length > 0) {
                const page = pages[0];
                const { height } = page.getSize();
                const font = await pdfDoc.embedFont(PDFLib.StandardFonts.Helvetica);

                if (data.name) {
                    page.drawText(String(data.name), { x: 75, y: height - 135, size: 11, font, color: PDFLib.rgb(0, 0, 0) });
                }
                if (data.business_name) {
                    page.drawText(String(data.business_name), { x: 75, y: height - 165, size: 11, font, color: PDFLib.rgb(0, 0, 0) });
                }
                if (data.address) {
                    page.drawText(String(data.address), { x: 75, y: height - 235, size: 11, font, color: PDFLib.rgb(0, 0, 0) });
                }
                if (data.city) {
                    page.drawText(String(data.city), { x: 75, y: height - 265, size: 11, font, color: PDFLib.rgb(0, 0, 0) });
                }
                if (data.state) {
                    page.drawText(String(data.state), { x: 240, y: height - 265, size: 11, font, color: PDFLib.rgb(0, 0, 0) });
                }
                if (data.zip) {
                    page.drawText(String(data.zip), { x: 340, y: height - 265, size: 11, font, color: PDFLib.rgb(0, 0, 0) });
                }
                if (data.tin) {
                    page.drawText(String(data.tin), { x: 415, y: height - 135, size: 11, font, color: PDFLib.rgb(0, 0, 0) });
                }
                if (data.certification_date) {
                    page.drawText(String(data.certification_date), { x: 445, y: height - 95, size: 11, font, color: PDFLib.rgb(0, 0, 0) });
                }
            }
        }

        // Footer branding for government PDF (same destination link as simple PDF)
        try {
            const pages = pdfDoc.getPages();
            const linkFont = await pdfDoc.embedFont(PDFLib.StandardFonts.HelveticaBold);
            const footerSize = 9;
            const footerY = 18;
            for (const page of pages) {
                const { width } = page.getSize();
                const footerWidth = linkFont.widthOfTextAtSize(footerBrandText, footerSize);
                const footerX = Math.max(20, width - footerWidth - 32);
                page.drawText(footerBrandText, {
                    x: footerX,
                    y: footerY,
                    size: footerSize,
                    font: linkFont,
                    color: PDFLib.rgb(0, 0, 0),
                });
                addPdfLinkAnnotation(page, footerX, footerY - 2, footerWidth, footerSize + 4, footerBrandUrl);
            }
        } catch (e) {
            w91099chConsole.warn('Could not add government PDF footer branding:', e);
        }

        // Flatten the form to make values permanent in the downloaded PDF
        try {
            form.flatten();
        } catch (e) {
            w91099chConsole.warn('Could not flatten form:', e);
        }

        return await pdfDoc.save();
    }
    
    // Fetch signature image from base64
    async function fetchSignatureImage(base64Data) {
        // Handle case where base64Data might be an object
        let base64String = base64Data;
        if (base64Data && typeof base64Data === 'object') {
            if (base64Data.data !== undefined) {
                base64String = base64Data.data;
            } else if (base64Data.value !== undefined) {
                base64String = base64Data.value;
            } else {
                throw new Error('Invalid signature data format');
            }
        }
        
        // Ensure it's a string
        base64String = String(base64String || '');
        
        // Remove data URL prefix
        const base64 = base64String.replace(/^data:image\/(png|jpeg);base64,/, '');
        const binaryString = atob(base64);
        const bytes = new Uint8Array(binaryString.length);
        
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        
        return bytes;
    }
    
    // Download PDF
    function downloadPdf(pdfBytes, filename) {
        const blob = new Blob([pdfBytes], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        
        // Track download for 'Print to PDF'
        if (typeof window.trackW9Download === 'function') {
            window.trackW9Download('print_to_pdf');
        }

        // Also show sharing popup (admin + client/shortcode) after download
        blobToDataURL(blob).then((pdfDataUrl) => {
            setTimeout(() => {
                try {
                    document.body.removeChild(a);
                } catch (_) {}
                URL.revokeObjectURL(url);
                showPdfSharingPopup(pdfDataUrl, filename);
            }, 250);
        }).catch(() => {
            setTimeout(() => {
                try {
                    document.body.removeChild(a);
                } catch (_) {}
                URL.revokeObjectURL(url);
            }, 100);
        });
    }

    // Professional Feedback Popup Function
    function showFeedbackPopup() {
        try {
            if (typeof window !== 'undefined' && typeof window.showFeedbackPopup === 'function' && window.showFeedbackPopup !== showFeedbackPopup) {
                window.showFeedbackPopup();
                return;
            }
        } catch (e) {}
        // Create modal overlay
        const modalOverlay = document.createElement('div');
        modalOverlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 20000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        `;

        // Create modal content
        const modalContent = document.createElement('div');
        modalContent.style.cssText = `
            background: white;
            border-radius: 12px;
            padding: 32px;
            max-width: 480px;
            width: 90%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            position: relative;
            animation: modalSlideIn 0.3s ease-out;
        `;

        // Add animation keyframes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes modalSlideIn {
                from {
                    opacity: 0;
                    transform: translateY(-20px) scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }
            .feedback-btn {
                transition: all 0.2s ease;
                border: none;
                padding: 10px 20px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                margin: 0 4px;
            }
            .feedback-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }
            .feedback-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            .feedback-secondary {
                background: #f3f4f6;
                color: #374151;
            }
            .feedback-close {
                position: absolute;
                top: 16px;
                right: 16px;
                background: none;
                border: none;
                font-size: 24px;
                color: #6b7280;
                cursor: pointer;
                padding: 4px;
                border-radius: 4px;
                transition: all 0.2s ease;
            }
            .feedback-close:hover {
                background: #f3f4f6;
                color: #374151;
            }
        `;
        document.head.appendChild(style);

        const isEarnRewardEnabled = String(w91099chW9Form.allowEarnRewardDownload) === '1';

        modalContent.innerHTML = `
            <button class="feedback-close" onclick="this.closest('div[style*=position]').parentElement.remove()">&times;</button>
            
            <div style="text-align: center; margin-bottom: 24px;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                </div>
                <h2 style="margin: 0; font-size: 24px; font-weight: 600; color: #1f2937;">How was your experience?</h2>
                <p style="margin: 8px 0 0; color: #6b7280; font-size: 16px;">Your W-9 form has been downloaded successfully!</p>
            </div>

            <div style="margin-bottom: 24px;">
                <p style="margin: 0 0 16px; color: #374151; font-size: 15px; line-height: 1.5;">
                    We'd love to hear your feedback about this W-9 form generator. Your experience helps us improve our service for everyone.
                </p>
                
                <div style="display: flex; gap: 8px; justify-content: center; margin-bottom: 20px;">
                    <button onclick="rateExperience('excellent', this)" style="font-size: 24px; background: none; border: none; cursor: pointer; padding: 8px; transition: all 0.2s; border-radius: 8px;" title="Excellent">😊</button>
                    <button onclick="rateExperience('good', this)" style="font-size: 24px; background: none; border: none; cursor: pointer; padding: 8px; transition: all 0.2s; border-radius: 8px;" title="Good">🙂</button>
                    <button onclick="rateExperience('average', this)" style="font-size: 24px; background: none; border: none; cursor: pointer; padding: 8px; transition: all 0.2s; border-radius: 8px;" title="Average">😐</button>
                    <button onclick="rateExperience('poor', this)" style="font-size: 24px; background: none; border: none; cursor: pointer; padding: 8px; transition: all 0.2s; border-radius: 8px;" title="Poor">😞</button>
                </div>

                <div id="feedbackMessage" style="display: none; margin-bottom: 16px;">
                    <textarea 
                        id="feedbackText" 
                        placeholder="Tell us more about your experience (optional)..."
                        style="width: 100%; min-height: 80px; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; resize: vertical; font-family: inherit;"
                    ></textarea>
                </div>
            </div>
            <div style="text-align: center; margin-top: 24px;">
                <button onclick="submitFeedback(event)" class="feedback-btn feedback-primary" style="margin-right: 8px;">
                    Submit Feedback
                </button>
                <button onclick="this.closest('div[style*=position]').parentElement.remove()" class="feedback-btn feedback-secondary">
                    Maybe Later
                </button>
            </div>
        `;

        modalOverlay.appendChild(modalContent);
        document.body.appendChild(modalOverlay);

        // Close modal when clicking overlay
        modalOverlay.addEventListener('click', function(e) {
            if (e.target === modalOverlay) {
                modalOverlay.remove();
            }
        });

        // Add rating functionality
        window.rateExperience = function(rating, buttonElement) {
            const feedbackMessage = document.getElementById('feedbackMessage');
            const feedbackText = document.getElementById('feedbackText');
            
            // Remove blue border from all emoji buttons
            const emojiButtons = modalOverlay.querySelectorAll('button[onclick*="rateExperience"]');
            emojiButtons.forEach(btn => {
                btn.style.border = 'none';
                btn.style.background = 'none';
            });
            
            // Add blue border to selected emoji button
            if (buttonElement) {
                buttonElement.style.border = '2px solid #3b82f6';
                buttonElement.style.background = 'rgba(59, 130, 246, 0.1)';
            }
            
            if (feedbackMessage) {
                feedbackMessage.style.display = 'block';
                feedbackText.focus();
                
                // Store rating for later use
                modalOverlay.setAttribute('data-rating', rating);
            }
        };

        // Add sharing functionality
        window.shareViaWhatsApp = function() {
            const text = encodeURIComponent(`Check out this free W-9 form generator! Create and download unlimited W-9 forms for free: ${window.location.href}`);
            window.open(`https://wa.me/?text=${text}`, '_blank');
        };

        window.shareViaFacebook = function() {
            const url = encodeURIComponent(window.location.href);
            window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, '_blank');
        };

        window.shareViaTwitter = function() {
            const text = encodeURIComponent(`Check out this free W-9 form generator! Create and download unlimited W-9 forms for free.`);
            const url = encodeURIComponent(window.location.href);
            window.open(`https://twitter.com/intent/tweet?text=${text}&url=${url}`, '_blank');
        };

        window.shareViaLinkedIn = function() {
            const url = encodeURIComponent(window.location.href);
            const title = encodeURIComponent('Free W-9 Form Generator');
            const summary = encodeURIComponent('Create and download unlimited W-9 forms for free with this easy-to-use tool.');
            window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${url}&title=${title}&summary=${summary}`, '_blank');
        };

        window.shareViaEmail = function() {
            const subject = encodeURIComponent('Check out this free W-9 form generator');
            const body = encodeURIComponent(`I just used this great free W-9 form generator and thought you might find it useful too! 

You can create and download unlimited W-9 forms here: ${window.location.href}

It's completely free and very easy to use.`);
            window.location.href = `mailto:?subject=${subject}&body=${body}`;
        };

        window.submitFeedback = function(event) {
            console.log('submitFeedback called');
            const rating = modalOverlay.getAttribute('data-rating') || 'No rating';
            const feedbackText = document.getElementById('feedbackText').value || 'No additional comments';
            const userAgent = navigator.userAgent;
            const currentUrl = window.location.href;
            const timestamp = new Date().toISOString();
            
            console.log('Feedback data:', { rating, feedbackText, userAgent, currentUrl, timestamp });
            
            // Show loading state
            const submitBtn = event.target;
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Submitting...';
            submitBtn.disabled = true;
            
            // Send feedback via AJAX
            console.log('Sending AJAX request to:', w91099chConnectorW9.ajaxurl);
            fetch(w91099chConnectorW9.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'w91099ch_submit_feedback',
                    nonce: (w91099chConnectorW9 && (w91099chConnectorW9.feedback_nonce || w91099chConnectorW9.nonce)) || '',
                    rating: rating,
                    feedback_text: feedbackText,
                    page_url: currentUrl,
                    user_agent: userAgent,
                    timestamp: timestamp
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const modalContent = modalOverlay.querySelector('div[style*="background: white"]');
                    if (modalContent) {
                        modalContent.innerHTML = `
                            <div style="text-align: center; padding: 40px 20px;">
                                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                        <path d="M20 6L9 17l-5-5"/>
                                    </svg>
                                </div>
                                <h3 style="margin: 16px 0 8px; color: #065f46; font-size: 20px;">Thank You!</h3>
                                <p style="margin: 0 0 16px; color: #374151; font-size: 15px; line-height: 1.5;">
                                    Your feedback has been submitted successfully. We appreciate your input!
                                </p>
                                <button onclick="this.closest('div[style*=position]').parentElement.remove()" class="feedback-btn feedback-primary">
                                    Close
                                </button>
                            </div>
                        `;
                    }
                } else {
                    // Show error message
                    alert('Error: ' + (data.data || 'Failed to submit feedback. Please try again.'));
                    // Reset button
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Feedback submission error:', error);
                alert('Error submitting feedback. Please try again.');
                // Reset button
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        };
    }

    if (typeof window.showFeedbackPopup !== 'function') {
        window.showFeedbackPopup = showFeedbackPopup;
    }
    
    // Convert blob to data URL for sharing
    function blobToDataURL(blob) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    }
    
    // Show PDF sharing popup
    function showPdfSharingPopup(pdfDataUrl, pdfFileName) {
        // Create modal overlay
        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'w91099ch-share-overlay';
        modalOverlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        `;

        // Create modal content
        const modalContent = document.createElement('div');
        modalContent.style.cssText = `
            background: white;
            border-radius: 12px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            position: relative;
            animation: modalSlideIn 0.3s ease-out;
        `;

        // Add animation keyframes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes modalSlideIn {
                from {
                    opacity: 0;
                    transform: translateY(-20px) scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }
            .share-btn {
                transition: all 0.2s ease;
                border: none;
                padding: 12px 20px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                margin: 4px;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                text-decoration: none;
            }
            .share-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }
            .share-close {
                position: absolute;
                top: 16px;
                right: 16px;
                background: none;
                border: none;
                font-size: 24px;
                color: #6b7280;
                cursor: pointer;
                padding: 4px;
                border-radius: 4px;
                transition: all 0.2s ease;
            }
            .share-close:hover {
                background: #f3f4f6;
                color: #374151;
            }
            .pdf-preview {
                background: #f8fafc;
                border: 2px dashed #cbd5e1;
                border-radius: 8px;
                padding: 20px;
                text-align: center;
                margin: 16px 0;
            }
        `;
        document.head.appendChild(style);

        modalContent.innerHTML = `
            <button class="share-close" onclick="closeSharePopup()">&times;</button>

            <div id="w91099ch-email-header-and-preview">
                <div id="w91099ch-popup-header" style="text-align: center; margin-bottom: 24px;">
                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-8l-4-4m0 0l-4 4m4-4v12"/>
                        </svg>
                    </div>
                    <h2 style="margin: 0; font-size: 24px; font-weight: 600; color: #1f2937;">Email Official W-9 Form</h2>
                    <p style="margin: 8px 0 0; color: #6b7280; font-size: 16px;">Your filled PDF is ready to send as attachment.</p>
                </div>

                <!-- Main Content Section (will be hidden on success) -->
                <div id="w91099ch-main-content">
                    <div class="pdf-preview">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" style="margin-bottom: 8px;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14,2 14,8 20,8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10,9 9,9 8,9"/>
                        </svg>
                        <div style="font-size: 14px; color: #64748b; font-weight: 500;">${pdfFileName}</div>
                        <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">Ready to email</div>
                    </div>
                </div>
            </div>

            <div id="w91099ch-email-wrapper" style="margin-bottom: 24px;">
                <div id="w91099ch-email-section">
                    <p style="margin: 0 0 8px; color: #374151; font-size: 15px; line-height: 1.5; text-align: center;">
                        Your Email <span style="color: #ef4444;">*</span>
                    </p>
                    <div style="display:flex; justify-content:center; margin: 0 0 16px;">
                        <input type="email" id="w91099ch-your-email" required placeholder="Enter your email address" style="width: 100%; max-width: 420px; padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px;">
                    </div>
                    <p style="margin: 0 0 8px; color: #374151; font-size: 15px; line-height: 1.5; text-align: center;">
                        Confirm Your Email <span style="color: #ef4444;">*</span>
                    </p>
                    <div style="display:flex; justify-content:center; margin: 0 0 16px;">
                        <input type="email" id="w91099ch-your-email-confirm" required placeholder="Confirm your email address" style="width: 100%; max-width: 420px; padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px;">
                    </div>
                    <div id="w91099ch-email-status" style="display:none; font-size: 13px; text-align:center; border-radius:8px; padding:8px 10px; margin-bottom: 16px;"></div>
                    <p style="margin: 0 0 16px; color: #374151; font-size: 15px; line-height: 1.5; text-align: center;">
                        Enter recipient emails. We'll send the PDF as an email attachment, and the store owner/admin will also receive a copy.
                    </p>
                    <div style="display:flex; justify-content:center; margin: 0 0 12px;">
                        <textarea id="w91099ch-share-recipient-email" rows="3" placeholder="Recipient emails (comma or new line separated)" style="width: 100%; max-width: 420px; padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; resize: vertical;"></textarea>
                    </div>
					<div id="w91099ch-admin-copy-note" style="display:none; max-width: 420px; margin: 0 auto 16px; background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 10px 12px; font-size: 13px; color: #3730a3;">
						<strong>Admin copy:</strong> This email will also be sent to <span id="w91099ch-admin-copy-email" style="font-weight:700;"></span>
					</div>
                </div>
                                <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                                    <p style="margin: 0; color: #92400e; font-size: 13px;">
                                        <i class="fas fa-star" style="color: #f59e0b; margin-right: 6px;"></i>
                                        <strong>Love using our plugin?</strong> Please take a moment to rate us and earn rewards!
                                    </p>
                                </div>
                <button onclick="if(typeof window.showFeedbackPopup === 'function') { window.showFeedbackPopup(); } else { window.open('https://docs.google.com/forms/d/e/1FAIpQLSfpKDl5tFerKl4Ag6fqFUrGTs4NuA9IS9w7f7Zi29LWBavNgQ/viewform', '_blank'); }" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 12px; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 8px; color: white; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                    <span style="font-size: 24px;">\ud83c\udf81</span>
                    Earn rewards by rating this Plugin
                </button>

                <!-- Secure W-9 Checkbox -->
                <label id="w91099ch-secure-w9-label" style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; margin-bottom: 16px; cursor: pointer;">
                    <input type="checkbox" id="w91099ch-secure-w9" style="width: 18px; height: 18px; cursor: pointer; accent-color: #0284c7;">
                    <span style="font-size: 14px; color: #0369a1; font-weight: 500;">
                        <i class="fas fa-shield-alt" style="margin-right: 6px;"></i>
                        Secure my W-9 in MyPowerly for future reuse
                    </span>
                </label>

                <div id="w91099ch-share-form-section">
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin: 8px 0 12px;">
                        <button id="w91099ch-send-email-btn" class="share-btn" style="background: #ef4444; color: white; justify-content: center; width: 100%; margin: 0;">
                            📧 Send Email with PDF Attachment
                        </button>
                        <button type="button" id="w91099ch-whatsapp-btn" class="share-btn" style="background: #e5e7eb; color: #6b7280; cursor: not-allowed; opacity: 0.9; justify-content: center;" disabled>
                            📱 WhatsApp (Coming Soon)
                        </button>
                        <button type="button" id="w91099ch-facebook-btn" class="share-btn" style="background: #e5e7eb; color: #6b7280; cursor: not-allowed; opacity: 0.9; justify-content: center;" disabled>
                            📘 Facebook (Coming Soon)
                        </button>
                        <button type="button" id="w91099ch-twitter-btn" class="share-btn" style="background: #e5e7eb; color: #6b7280; cursor: not-allowed; opacity: 0.9; justify-content: center;" disabled>
                            🐦 Twitter (Coming Soon)
                        </button>
                        <button type="button" id="w91099ch-linkedin-btn" class="share-btn" style="background: #e5e7eb; color: #6b7280; cursor: not-allowed; opacity: 0.9; justify-content: center;" disabled>
                            💼 LinkedIn (Coming Soon)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Success Message Container (hidden initially) -->
            <div id="w91099ch-success-container" style="display: none; text-align: center; padding: 24px;">
                <div id="w91099ch-success-content"></div>
            </div>

            <div style="text-align: center; border-top: 1px solid #e5e7eb; padding-top: 16px;">
                <button onclick="closeSharePopup()" class="share-btn" style="background: #f3f4f6; color: #374151;">
                    Close
                </button>
            </div>
        `;

        modalOverlay.appendChild(modalContent);
        document.body.appendChild(modalOverlay);

        // Hide social media buttons, Secure W-9 checkbox, and Send Email button if disabled by admin
        const cfg = window.w91099chConnectorW9 || window.w91099chW9Form;
        console.log('Social Sharing Debug - cfg:', cfg);
        console.log('Social Sharing Debug - enableSocialSharing value:', cfg ? cfg.enableSocialSharing : 'cfg not found');
        console.log('Social Sharing Debug - enableSocialSharing type:', cfg ? typeof cfg.enableSocialSharing : 'cfg not found');
        const enableSocialSharing = cfg && (cfg.enableSocialSharing === true || cfg.enableSocialSharing === 'true' || cfg.enableSocialSharing === '1');
        const enableSecureW9 = cfg && (cfg.enableSecureW9 === true || cfg.enableSecureW9 === 'true' || cfg.enableSecureW9 === '1');
        console.log('Social Sharing Debug - enableSocialSharing boolean:', enableSocialSharing);
        console.log('Secure W-9 Debug - enableSecureW9 boolean:', enableSecureW9);

        // Log all elements we're trying to find
        console.log('Social Sharing Debug - Looking for elements in modalContent');
        const secureW9Label = modalContent.querySelector('#w91099ch-secure-w9-label');
        console.log('Social Sharing Debug - secureW9Label found:', !!secureW9Label);
        const emailSection = modalContent.querySelector('#w91099ch-email-section');
        console.log('Social Sharing Debug - emailSection found:', !!emailSection);
        const emailTextarea = modalContent.querySelector('#w91099ch-share-recipient-email');
        console.log('Social Sharing Debug - emailTextarea found:', !!emailTextarea);
        const sendEmailBtn = modalContent.querySelector('#w91099ch-send-email-btn');
        console.log('Social Sharing Debug - sendEmailBtn found:', !!sendEmailBtn);
        const whatsappBtn = modalContent.querySelector('#w91099ch-whatsapp-btn');
        console.log('Social Sharing Debug - whatsappBtn found:', !!whatsappBtn);
        const facebookBtn = modalContent.querySelector('#w91099ch-facebook-btn');
        console.log('Social Sharing Debug - facebookBtn found:', !!facebookBtn);
        const twitterBtn = modalContent.querySelector('#w91099ch-twitter-btn');
        console.log('Social Sharing Debug - twitterBtn found:', !!twitterBtn);
        const linkedinBtn = modalContent.querySelector('#w91099ch-linkedin-btn');
        console.log('Social Sharing Debug - linkedinBtn found:', !!linkedinBtn);

		const adminCopyNote = modalContent.querySelector('#w91099ch-admin-copy-note');
		const adminCopyEmailEl = modalContent.querySelector('#w91099ch-admin-copy-email');
		const adminEmail = (cfg && cfg.admin_email) ? String(cfg.admin_email).trim() : '';
		if (adminCopyNote && adminCopyEmailEl && adminEmail) {
			adminCopyEmailEl.textContent = adminEmail;
			adminCopyNote.style.display = 'block';
		}

        if (!enableSocialSharing) {
            console.log('Social Sharing Debug - Hiding elements because enableSocialSharing is false');
            const emailHeaderAndPreview = modalContent.querySelector('#w91099ch-email-header-and-preview');
            if (emailHeaderAndPreview) {
                emailHeaderAndPreview.style.display = 'none';
            }
            if (emailSection) {
                emailSection.style.display = 'none';
            }
            if (emailTextarea) {
                emailTextarea.parentElement.style.display = 'none';
            }
            if (sendEmailBtn) {
                sendEmailBtn.style.display = 'none';
            }
            if (whatsappBtn) {
                whatsappBtn.style.display = 'none';
            }
            if (facebookBtn) {
                facebookBtn.style.display = 'none';
            }
            if (twitterBtn) {
                twitterBtn.style.display = 'none';
            }
            if (linkedinBtn) {
                linkedinBtn.style.display = 'none';
            }
        } else {
            console.log('Social Sharing Debug - enableSocialSharing is true, enabling social buttons');
            // Enable social media buttons by removing disabled attribute and updating styles
            if (whatsappBtn) {
                whatsappBtn.removeAttribute('disabled');
                whatsappBtn.style.background = '#25D366';
                whatsappBtn.style.color = 'white';
                whatsappBtn.style.cursor = 'pointer';
                whatsappBtn.style.opacity = '1';
                whatsappBtn.innerHTML = '📱 Share on WhatsApp';
                whatsappBtn.onclick = window.shareViaWhatsApp;
            }
            if (facebookBtn) {
                facebookBtn.removeAttribute('disabled');
                facebookBtn.style.background = '#1877F2';
                facebookBtn.style.color = 'white';
                facebookBtn.style.cursor = 'pointer';
                facebookBtn.style.opacity = '1';
                facebookBtn.innerHTML = '📘 Share on Facebook';
                facebookBtn.onclick = window.shareViaFacebook;
            }
            if (twitterBtn) {
                twitterBtn.removeAttribute('disabled');
                twitterBtn.style.background = '#1DA1F2';
                twitterBtn.style.color = 'white';
                twitterBtn.style.cursor = 'pointer';
                twitterBtn.style.opacity = '1';
                twitterBtn.innerHTML = '🐦 Share on Twitter';
                twitterBtn.onclick = window.shareViaTwitter;
            }
            if (linkedinBtn) {
                linkedinBtn.removeAttribute('disabled');
                linkedinBtn.style.background = '#0A66C2';
                linkedinBtn.style.color = 'white';
                linkedinBtn.style.cursor = 'pointer';
                linkedinBtn.style.opacity = '1';
                linkedinBtn.innerHTML = '💼 Share on LinkedIn';
                linkedinBtn.onclick = window.shareViaLinkedIn;
            }
        }

        // Hide Secure W-9 checkbox independently based on its own setting
        if (!enableSecureW9) {
            console.log('Secure W-9 Debug - Hiding secure W-9 checkbox because enableSecureW9 is false');
            if (secureW9Label) {
                secureW9Label.style.display = 'none';
            }
        }

        const triggerShareClose = function() {
            modalOverlay.remove();
        };

        // Add closeSharePopup function to window scope
        window.closeSharePopup = function() {
            triggerShareClose();
        };

        const closeBtn = modalContent.querySelector('.share-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                triggerShareClose();
            });
        }

        const closeButtons = modalContent.querySelectorAll('button');
        closeButtons.forEach(function(btn) {
            if (btn && String(btn.textContent || '').trim().toLowerCase() === 'close') {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    triggerShareClose();
                });
            }
        });

        // Close modal when clicking overlay
        modalOverlay.addEventListener('click', function(e) {
            if (e.target === modalOverlay) {
                triggerShareClose();
            }
        });

        const sendBtn = document.getElementById('w91099ch-send-email-btn');
        const recipientInput = document.getElementById('w91099ch-share-recipient-email');
        const yourEmailInput = document.getElementById('w91099ch-your-email');
        const yourEmailConfirmInput = document.getElementById('w91099ch-your-email-confirm');
        const statusBox = document.getElementById('w91099ch-email-status');

        const markInvalid = function(el) {
            if (!el) return;
            el.style.borderColor = '#ef4444';
            el.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.2)';
        };
        const clearInvalid = function(el) {
            if (!el) return;
            el.style.borderColor = '#d1d5db';
            el.style.boxShadow = 'none';
        };

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const emailsMatch = function() {
            const e1 = yourEmailInput ? String(yourEmailInput.value || '').trim() : '';
            const e2 = yourEmailConfirmInput ? String(yourEmailConfirmInput.value || '').trim() : '';
            if (!e1 || !e2) return false;
            if (!emailRegex.test(e1) || !emailRegex.test(e2)) return false;
            return e1.toLowerCase() === e2.toLowerCase();
        };

        // Reset border color when user starts typing
        if (yourEmailInput) {
            yourEmailInput.addEventListener('input', function() {
                clearInvalid(yourEmailInput);
                if (yourEmailConfirmInput && emailsMatch()) {
                    clearInvalid(yourEmailConfirmInput);
                    if (statusBox && statusBox.style.display === 'block') statusBox.style.display = 'none';
                }
            });
        }
        if (yourEmailConfirmInput) {
            yourEmailConfirmInput.addEventListener('input', function() {
                clearInvalid(yourEmailConfirmInput);
                if (yourEmailInput && emailsMatch()) {
                    clearInvalid(yourEmailInput);
                    if (statusBox && statusBox.style.display === 'block') statusBox.style.display = 'none';
                }
            });
        }
        if (recipientInput) {
            recipientInput.addEventListener('input', function() {
                clearInvalid(recipientInput);
            });
        }

        const setStatus = function(msg, ok) {
            if (!statusBox) return;
            statusBox.style.display = 'block';
            statusBox.textContent = msg;
            statusBox.style.background = ok ? '#ecfdf5' : '#fef2f2';
            statusBox.style.color = ok ? '#065f46' : '#991b1b';
            statusBox.style.border = ok ? '1px solid #10b981' : '1px solid #ef4444';
            statusBox.style.fontWeight = ok ? '500' : '700';
            statusBox.style.padding = '12px 14px';
            statusBox.style.borderRadius = '10px';
            statusBox.setAttribute('role', ok ? 'status' : 'alert');
            statusBox.setAttribute('aria-live', ok ? 'polite' : 'assertive');
            if (!ok && typeof statusBox.scrollIntoView === 'function') {
                statusBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        };

        if (sendBtn) {
            sendBtn.addEventListener('click', async function() {
                // Reset input borders
                clearInvalid(yourEmailInput);
                clearInvalid(yourEmailConfirmInput);
                clearInvalid(recipientInput);

                const yourEmail = yourEmailInput ? String(yourEmailInput.value || '').trim() : '';
                const yourEmailConfirm = yourEmailConfirmInput ? String(yourEmailConfirmInput.value || '').trim() : '';
                if (!yourEmail) {
                    setStatus('Please enter your email address.', false);
                    markInvalid(yourEmailInput);
                    if (yourEmailInput && typeof yourEmailInput.focus === 'function') yourEmailInput.focus();
                    return;
                }
                if (!emailRegex.test(yourEmail)) {
                    setStatus('Please enter a valid email address.', false);
                    markInvalid(yourEmailInput);
                    if (yourEmailInput && typeof yourEmailInput.focus === 'function') yourEmailInput.focus();
                    return;
                }

                if (!yourEmailConfirm) {
                    setStatus('Please confirm your email address.', false);
                    markInvalid(yourEmailConfirmInput);
                    if (yourEmailConfirmInput && typeof yourEmailConfirmInput.focus === 'function') yourEmailConfirmInput.focus();
                    return;
                }
                if (!emailRegex.test(yourEmailConfirm)) {
                    setStatus('Please enter a valid confirmation email address.', false);
                    markInvalid(yourEmailConfirmInput);
                    if (yourEmailConfirmInput && typeof yourEmailConfirmInput.focus === 'function') yourEmailConfirmInput.focus();
                    return;
                }
                if (yourEmailConfirm.toLowerCase() !== yourEmail.toLowerCase()) {
                    setStatus('Email does not match. Please make sure “Your Email” and “Confirm Your Email” are the same.', false);
                    markInvalid(yourEmailConfirmInput);
                    markInvalid(yourEmailInput);
                    if (yourEmailConfirmInput && typeof yourEmailConfirmInput.focus === 'function') yourEmailConfirmInput.focus();
                    return;
                }

                const recipientRaw = recipientInput ? String(recipientInput.value || '').trim() : '';
                if (!recipientRaw) {
                    setStatus('Please enter at least one recipient email address.', false);
                    markInvalid(recipientInput);
                    if (recipientInput && typeof recipientInput.focus === 'function') recipientInput.focus();
                    return;
                }

                const recipients = recipientRaw
                    .split(/[\n\r,;]+/)
                    .map(s => String(s || '').trim())
                    .filter(Boolean);

                const validRecipients = recipients.filter(r => emailRegex.test(r));
                const invalidRecipients = recipients.filter(r => !emailRegex.test(r));
                if (!validRecipients.length) {
                    setStatus('Please enter a valid email address.', false);
                    return;
                }
                if (invalidRecipients.length) {
                    setStatus('Some emails look invalid and will be skipped: ' + invalidRecipients.join(', '), false);
                }

                const cfg = window.w91099chConnectorW9 || window.w91099chW9Form;
                const ajaxurl = (cfg && cfg.ajaxurl) ? cfg.ajaxurl : '/wp-admin/admin-ajax.php';
                const nonce = (cfg && cfg.nonce) ? cfg.nonce : '';
                if (!nonce) {
                    setStatus('Security token missing. Please refresh the page and try again.', false);
                    return;
                }

				// Enforce sending a copy to the WordPress admin email (cannot be removed)
				const enforcedAdminEmail = (cfg && cfg.admin_email) ? String(cfg.admin_email).trim() : '';
				if (enforcedAdminEmail && emailRegex.test(enforcedAdminEmail) && !validRecipients.includes(enforcedAdminEmail)) {
					validRecipients.push(enforcedAdminEmail);
				}

                const originalText = sendBtn.textContent;
                sendBtn.disabled = true;
                sendBtn.textContent = 'Sending...';
                setStatus('Sending email with PDF attachment...', true);

                // Check secure W-9 checkbox before sending
                const secureW9Checkbox = document.getElementById('w91099ch-secure-w9');
                console.log('Secure W-9 Debug - Checkbox element found:', !!secureW9Checkbox);
                console.log('Secure W-9 Debug - Checkbox checked state:', secureW9Checkbox ? secureW9Checkbox.checked : 'N/A (checkbox not found)');
                const isSecureW9Checked = secureW9Checkbox && secureW9Checkbox.checked;
                console.log('Secure W-9 Debug - isSecureW9Checked value:', isSecureW9Checked);

                try {
                    const fd = new FormData();
                    fd.append('action', 'w91099ch_send_pdf_email');
                    fd.append('nonce', nonce);
                    fd.append('your_email', yourEmail);
                    fd.append('recipient_emails', validRecipients.join(','));
                    fd.append('pdf_data_url', pdfDataUrl);
                    fd.append('pdf_file_name', pdfFileName);
                    fd.append('secure_w9', isSecureW9Checked ? '1' : '0');

                    const resp = await fetch(ajaxurl, {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin'
                    });
                    const json = await resp.json().catch(() => null);
                    if (!resp.ok || !json) {
                        throw new Error('Email request failed.');
                    }
                    if (!json.success) {
                        const msg = (json && json.data) ? String(json.data) : 'Failed to send email.';
                        throw new Error(msg);
                    }

                    // Get UI elements for showing success message
                    const mainContent = document.getElementById('w91099ch-main-content');
                    const popupHeader = document.getElementById('w91099ch-popup-header');
                    const emailWrapper = document.getElementById('w91099ch-email-wrapper');
                    const successContainer = document.getElementById('w91099ch-success-container');
                    const successContent = document.getElementById('w91099ch-success-content');

                    if (isSecureW9Checked) {
                        // Checkbox checked - Show account creation message with login link
                        if (mainContent) mainContent.style.display = 'none';
                        if (popupHeader) popupHeader.style.display = 'none';
                        if (emailWrapper) emailWrapper.style.display = 'none';
                        if (successContainer) successContainer.style.display = 'block';
                        if (statusBox) statusBox.style.display = 'none';
                        
                        if (successContent) {
                            successContent.innerHTML = `
                                <div style="margin-bottom: 20px;">
                                    <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                                        <span style="font-size: 32px;">✅</span>
                                    </div>
                                    <h3 style="margin: 0 0 8px; font-size: 20px; font-weight: 600; color: #1e40af;">Email Sent Successfully!</h3>
                                    <p style="margin: 0 0 12px; color: #374151; font-size: 14px; line-height: 1.6;">
                                        An account for you has been created.<br>
                                        <a href="https://mypowerly.com/" target="_blank" style="color: #2563eb; font-weight: 600; text-decoration: underline;">Click here to login to MyPowerly</a>
                                    </p>
                                    ${adminEmail ? `<p style="margin: 0 0 12px; color: #374151; font-size: 13px; line-height: 1.6;">A copy was also sent to the store owner/admin: <strong>${adminEmail}</strong></p>` : ''}
                                </div>
                                <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                                    <p style="margin: 0; color: #92400e; font-size: 13px;">
                                        <i class="fas fa-star" style="color: #f59e0b; margin-right: 6px;"></i>
                                        <strong>Love using our plugin?</strong> Please take a moment to rate us and earn rewards!
                                    </p>
                                </div>
                                <button onclick="if(typeof window.showFeedbackPopup === 'function') { window.showFeedbackPopup(); } else { window.open('https://docs.google.com/forms/d/e/1FAIpQLSfpKDl5tFerKl4Ag6fqFUrGTs4NuA9IS9w7f7Zi29LWBavNgQ/viewform', '_blank'); }" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 8px; color: white; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                                    <span style="font-size: 24px;">🎁</span>
                                    Earn rewards by rating this Plugin
                                </button>
                            `;
                        }
                    } else {
                        // Checkbox unchecked - Show thank you message with rating button
                        if (mainContent) mainContent.style.display = 'none';
                        if (popupHeader) popupHeader.style.display = 'none';
                        if (emailWrapper) emailWrapper.style.display = 'none';
                        if (successContainer) successContainer.style.display = 'block';
                        if (statusBox) statusBox.style.display = 'none';
                        
                        if (successContent) {
                            successContent.innerHTML = `
                                <div style="margin-bottom: 20px;">
                                    <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                                        <span style="font-size: 32px;">🎉</span>
                                    </div>
                                    <h3 style="margin: 0 0 8px; font-size: 20px; font-weight: 600; color: #065f46;">Thank You!</h3>
                                    ${adminEmail ? `<p style="margin: 0 0 12px; color: #374151; font-size: 13px; line-height: 1.6;">Email sent successfully, and a copy was also sent to the store owner/admin: <strong>${adminEmail}</strong></p>` : `<p style="margin: 0 0 12px; color: #374151; font-size: 13px; line-height: 1.6;">Email sent successfully.</p>`}
                                    <p style="margin: 0; color: #374151; font-size: 14px; line-height: 1.6;">
                                        Your W-9 form has been sent successfully to email.
                                    </p>
                                </div>
                                <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                                    <p style="margin: 0; color: #92400e; font-size: 13px;">
                                        <i class="fas fa-star" style="color: #f59e0b; margin-right: 6px;"></i>
                                        <strong>Love using our plugin?</strong> Please take a moment to rate us and earn rewards!
                                    </p>
                                </div>
                                <button onclick="if(typeof window.showFeedbackPopup === 'function') { window.showFeedbackPopup(); } else { window.open('https://docs.google.com/forms/d/e/1FAIpQLSfpKDl5tFerKl4Ag6fqFUrGTs4NuA9IS9w7f7Zi29LWBavNgQ/viewform', '_blank'); }" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 8px; color: white; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                                    <span style="font-size: 24px;">🎁</span>
                                    Earn rewards by rating this Plugin
                                </button>
                            `;
                        }
                    }
                } catch (err) {
                    setStatus(err && err.message ? err.message : 'Failed to send email.', false);
                } finally {
                    sendBtn.disabled = false;
                    sendBtn.textContent = originalText;
                }
            });
        }
    }
    
    // Client-side W-9 & 1099 Tools dropdown functionality
    function initW9ToolsDropdown() {
        w91099chConsole.log('Initializing W9 tools dropdown...');
        
        const $btn = $('#w91099ch-client-tools-btn');
        const $menu = $('#w91099ch-client-tools-menu');


        if (!$btn.length || !$menu.length) {
            w91099chConsole.error('W9 tools elements missing from DOM');
            return;
        }

        // Add tooltip to the button
        $btn.attr('title', 'Help others create W-9 for free by sharing this link');
        // Function to get default page URL via AJAX (same as admin side)
        function getClientDefaultPageUrl() {
            let cfg = window.w91099chConnectorW9 || window.w91099chW9Form;
            let ajaxurl = cfg && cfg.ajaxurl ? cfg.ajaxurl : '/wp-admin/admin-ajax.php';
            let nonce = cfg && cfg.nonce ? cfg.nonce : '';

            return $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'w91099ch_get_default_page_url',
                    nonce: nonce
                }
            });
        }

        // Use direct binding on the button for better reliability
        $btn.off('click.w9tools').on('click.w9tools', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            w91099chConsole.log('Tools dropdown clicked');
            
            const isHidden = $menu.hasClass('hidden');
            
            if (isHidden) {
                $menu.removeClass('hidden');
                $btn.attr('aria-expanded', 'true');
                w91099chConsole.log('Tools dropdown opened');
            } else {
                $menu.addClass('hidden');
                $btn.attr('aria-expanded', 'false');
                w91099chConsole.log('Tools dropdown closed');
            }
        });

        // Close dropdown when clicking outside
        $(document).off('click.w9toolsclose').on('click.w9toolsclose', function(e) {
            if (!$(e.target).closest('#w91099ch-client-tools').length) {
                $menu.addClass('hidden');
                $btn.attr('aria-expanded', 'false');
            }
        });

        // Handle dropdown actions
        $menu.find('[data-action]').off('click.w9toolsaction').on('click.w9toolsaction', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var action = $(this).data('action');
            w91099chConsole.log('Action triggered:', action);
            
            getClientDefaultPageUrl().done(function(response) {
                if (response.success && response.data.url) {
                    var defaultUrl = response.data.url;

                    switch(action) {
                        case 'copy':
                            w91099chCopyText(defaultUrl).then(function(success) {
                                if (success) {
                                    alert('Success! Link copied to clipboard.');
                                } else {
                                    alert('Error: Could not copy the link.');
                                }
                            });
                            break;
                        
                        case 'email':
                            var subject = 'W-9 Form Link';
                            var body = 'Hi,%0D%0A%0D%0AHere is the link to the W-9 form:%0D%0A' + encodeURIComponent(defaultUrl) + '%0D%0A%0D%0AThanks';
                            var gmailUrl = 'https://mail.google.com/mail/?view=cm&fs=1&su=' + encodeURIComponent(subject) + '&body=' + body;
                            window.open(gmailUrl, '_blank', 'noopener');
                            break;
                        
                        case 'qr':
                            showQRCode(defaultUrl);
                            break;
                    }
                } else {
                    alert('Action Required: Please set a default page in W-9 Display Settings first.');
                }
            }).fail(function() {
                // Fallback to data attribute if AJAX fails
                var fallbackUrl = $('#w91099ch-client-tools').data('default-page-url');
                if (fallbackUrl) {
                    if (action === 'copy') {
                        w91099chCopyText(fallbackUrl).then(function(s){ alert(s ? 'Success! Link copied.' : 'Error.'); });
                    } else if (action === 'email') {
                        window.open('https://mail.google.com/mail/?view=cm&fs=1&su=W-9%20Form&body=' + encodeURIComponent(fallbackUrl), '_blank');
                    } else if (action === 'qr') {
                        showQRCode(fallbackUrl);
                    }
                } else {
                    alert('Error: Could not retrieve page URL.');
                }
            });

            // Close dropdown
            $menu.addClass('hidden');
            $btn.attr('aria-expanded', 'false');
        });
    }

    // Initialize tools dropdown with retry mechanism
    function initializeW9ToolsWithRetry(retries = 0) {
        const $btn = $('#w91099ch-client-tools-btn');
        if ($btn.length) {
            initW9ToolsDropdown();
            w91099chConsole.log('W9 tools dropdown initialized successfully');
        } else if (retries < 10) {
            // Retry up to 10 times (5 seconds total)
            setTimeout(() => initializeW9ToolsWithRetry(retries + 1), 500);
        } else {
            w91099chConsole.error('W9 tools button not found after retries');
        }
    }
    
    // Start initializations
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initializeSignaturePadWithRetry();
            initializeW9ToolsWithRetry();
        });
    } else {
        initializeSignaturePadWithRetry();
        initializeW9ToolsWithRetry();
    }

    // QR Code functionality for client-side
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
                            '<img id="w91099ch_qr_img" alt="QR" style="width: 220px; height: 220px; border: 1px solid #e5e7eb; border-radius: 12px;" src="https://quickchart.io/qr?size=220&text=' + encodeURIComponent(url) + '" />' +
                            '<div style="word-break: break-all; color: #374151; font-size: 12px; text-align: center;">' + url + '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
            $('body').append(modalHtml);
        } else {
            $('#w91099ch_qr_img').attr('src', 'https://quickchart.io/qr?size=220&text=' + encodeURIComponent(url));
            $('#w91099ch-qr-modal').show();
        }
    }

    // Close QR modal (works for both admin and client-side)
    $(document).on('click', '#w91099ch-qr-close, #w91099ch-qr-modal', function(e) {
        if (e.target.id === 'w91099ch-qr-close' || e.target.id === 'w91099ch-qr-modal') {
            $('#w91099ch-qr-modal').hide();
        }
    });

    // Close modal on escape key (works for both admin and client-side)
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#w91099ch-qr-modal').hide();
        }
    });

    /**
     * Track W-9 form downloads via AJAX
     */
    window.trackW9Download = function(type) {
        let cfg = window.w91099chConnectorW9 || window.w91099chW9Form;
        if (!cfg || !cfg.ajaxurl || !cfg.nonce) {
            if (typeof w91099chConsole !== 'undefined') {
                w91099chConsole.error('Tracking failed: Configuration missing');
            }
            return;
        }

        $.ajax({
            url: cfg.ajaxurl,
            method: 'POST',
            data: {
                action: 'w91099ch_track_download',
                nonce: cfg.nonce,
                download_type: type
            },
            success: function(response) {
                if (response.success && typeof w91099chConsole !== 'undefined') {
                    w91099chConsole.log('Download tracked successfully:', type, response.data);
                }
            },
            error: function(err) {
                if (typeof w91099chConsole !== 'undefined') {
                    w91099chConsole.error('Tracking request failed:', err);
                }
            }
        });
    };

    function w91099chEscapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function initMockDataSyncModules() {
        const $cards = $('.w91099ch-mock-sync-card');
        if (!$cards.length) {
            return;
        }

        function getPayload($card) {
            const moduleId = String($card.data('module-id') || '');
            const title = $.trim($card.find('h3').first().text());
            const payload = {
                module: moduleId,
                title: title,
                selected: {}
            };
            let selectedCount = 0;

            $card.find('.w91099ch-mock-sync-item:checked').each(function() {
                const group = String($(this).data('group') || '');
                const item = String($(this).data('item') || '');

                if (!group || !item) {
                    return;
                }

                if (!payload.selected[group]) {
                    payload.selected[group] = [];
                }

                payload.selected[group].push(item);
                selectedCount++;
            });

            return {
                payload: payload,
                count: selectedCount
            };
        }

        function updateCardState($card) {
            const hasConsent = $card.find('.w91099ch-mock-sync-consent').is(':checked');
            const $button = $card.find('.w91099ch-mock-sync-button');
            const $status = $card.find('.w91099ch-mock-sync-status');
            const payloadInfo = getPayload($card);

            $card.find('.w91099ch-mock-sync-option').each(function() {
                $(this).toggleClass('is-excluded', !$(this).find('.w91099ch-mock-sync-item').is(':checked'));
            });

            $card.find('.w91099ch-mock-payload-preview').text(JSON.stringify(payloadInfo.payload, null, 2));
            $card.find('.w91099ch-mock-payload-count').text(payloadInfo.count + ' selected');

            $button.prop('disabled', !hasConsent);

            if (!hasConsent) {
                $status.removeClass('is-success is-error is-loading').text('Check consent to enable sync');
            } else if (payloadInfo.count === 0) {
                $status.removeClass('is-success is-loading').addClass('is-error').text('Select at least one item to include in the mock payload');
            } else {
                $status.removeClass('is-success is-error is-loading').text('Ready for mock sync');
            }
        }

        function removeMockModal() {
            $('#w91099ch-mock-sync-modal').remove();
        }

        function showMockConfirmation($card, payloadInfo) {
            removeMockModal();

            const confirmMessage = String($card.data('confirm-message') || 'Are you sure you want to sync selected data?');
            const payloadJson = JSON.stringify(payloadInfo.payload, null, 2);
            const modalHtml = ''
                + '<div id="w91099ch-mock-sync-modal" class="w91099ch-mock-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="w91099ch-mock-modal-title">'
                + '  <div class="w91099ch-mock-modal">'
                + '    <div class="w91099ch-mock-modal-header">'
                + '      <div class="w91099ch-mock-modal-icon"><i class="fas fa-circle-question" aria-hidden="true"></i></div>'
                + '      <div>'
                + '        <h3 id="w91099ch-mock-modal-title">Confirm Mock Sync</h3>'
                + '        <p>' + w91099chEscapeHtml(confirmMessage) + '</p>'
                + '      </div>'
                + '    </div>'
                + '    <div class="w91099ch-mock-modal-body">'
                + '      <div class="w91099ch-mock-modal-note">No API call will be made. Only the selected checkboxes below are included.</div>'
                + '      <pre>' + w91099chEscapeHtml(payloadJson) + '</pre>'
                + '    </div>'
                + '    <div class="w91099ch-mock-modal-actions">'
                + '      <button type="button" class="w91099ch-mock-modal-cancel" id="w91099ch-mock-sync-cancel">Cancel</button>'
                + '      <button type="button" class="w91099ch-mock-modal-ok" id="w91099ch-mock-sync-ok"><i class="fas fa-check" aria-hidden="true"></i> OK</button>'
                + '    </div>'
                + '  </div>'
                + '</div>';

            $('body').append(modalHtml);

            $('#w91099ch-mock-sync-cancel').on('click', function() {
                removeMockModal();
                $card.find('.w91099ch-mock-sync-status').removeClass('is-success is-error is-loading').text('Mock sync cancelled');
                $card.find('.w91099ch-mock-sync-button').prop('disabled', false);
            });

            $('#w91099ch-mock-sync-ok').on('click', function() {
                const $button = $card.find('.w91099ch-mock-sync-button');
                const $status = $card.find('.w91099ch-mock-sync-status');

                removeMockModal();
                $button.prop('disabled', true);
                $status.removeClass('is-success is-error').addClass('is-loading').text('Finalizing mock sync...');

                window.setTimeout(function() {
                    $button.prop('disabled', false);

                    if (payloadInfo.count === 0) {
                        $status.removeClass('is-success is-loading').addClass('is-error').text('Mock failure: no selected data was included in the payload');
                        return;
                    }

                    $status.removeClass('is-error is-loading').addClass('is-success').text('Mock success: selected data payload prepared');
                    w91099chConsole.log('Mock sync payload:', payloadInfo.payload);
                }, 700);
            });
        }

        $cards.each(function() {
            updateCardState($(this));
        });

        $(document).off('change.w91099chMockDataSync').on('change.w91099chMockDataSync', '.w91099ch-mock-sync-card input[type="checkbox"]', function() {
            updateCardState($(this).closest('.w91099ch-mock-sync-card'));
        });

        $(document).off('click.w91099chMockDataSync').on('click.w91099chMockDataSync', '.w91099ch-mock-sync-button', function() {
            const $button = $(this);
            const $card = $button.closest('.w91099ch-mock-sync-card');
            const $status = $card.find('.w91099ch-mock-sync-status');

            if (!$card.find('.w91099ch-mock-sync-consent').is(':checked')) {
                updateCardState($card);
                return;
            }

            const payloadInfo = getPayload($card);

            $button.prop('disabled', true);
            $status.removeClass('is-success is-error').addClass('is-loading').text('Preparing selected mock payload...');

            window.setTimeout(function() {
                $status.removeClass('is-loading').text('Waiting for confirmation');
                showMockConfirmation($card, payloadInfo);
            }, 650);
        });

        $(document).off('click.w91099chMockDataSyncBackdrop').on('click.w91099chMockDataSyncBackdrop', '#w91099ch-mock-sync-modal', function(event) {
            if (event.target === this) {
                $('#w91099ch-mock-sync-cancel').trigger('click');
            }
        });
    }

    initMockDataSyncModules();
});
