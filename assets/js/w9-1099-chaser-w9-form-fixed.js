jQuery(document).ready(function($) {
    const w91099chConsole = { log: function() {}, error: function() {}, warn: function() {} };

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
    // Initialize signature pad
    const canvas = document.getElementById('signature-canvas');
    if (!canvas) {
        w91099chConsole.error('Signature canvas not found');
        return;
    }

    // Set canvas dimensions properly
    canvas.width = canvas.offsetWidth;
    canvas.height = 200;

    const signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(255, 255, 255)',
        penColor: 'rgb(0, 0, 0)',
        minWidth: 1,
        maxWidth: 2
    });
    
    // Make signature pad responsive
    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const parentWidth = canvas.parentElement.offsetWidth;
        
        // Save current signature data
        const data = signaturePad.toData();
        
        // Resize canvas
        canvas.width = parentWidth;
        canvas.height = 200;
        
        // Restore signature
        signaturePad.clear();
        signaturePad.fromData(data);
    }
    
    // Initial resize
    resizeCanvas();
    
    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(resizeCanvas, 250);
    });
    
    // Clear signature
    $(document).on('click', '#clear-signature', function(e) {
        e.preventDefault();
        signaturePad.clear();
        $('#signature_data').val('');
        $('#certification_name').val('').removeClass('is-valid');
    });
    
    // Handle signature end
    signaturePad.addEventListener('endStroke', function() {
        if (!signaturePad.isEmpty()) {
            $('#signature_data').val(signaturePad.toDataURL());
            $('#certification_name').val('Signed').addClass('is-valid');
        }
    });
    
    // Handle government form download - Simple and direct approach
    $(document).on('click', '#mypowerly-govt-form-download', async function(e) {
        e.preventDefault();
        alert('Government form button clicked! Processing will start now...');
        console.log('Government form button clicked - NEW VERSION');
        
        const $btn = $(this);
        const originalText = $btn.text();
        
        // Disable button and show loading
        $btn.prop('disabled', true).text('Loading...');
        
        const $status = $('#mypowerly-w9-status');
        $status.html('<p>Preparing Government W-9 form...</p>').removeClass('error success').show();
        
        try {
            // Get form data
            const formData = $('#mypowerly-w9-form').serializeArray();
            const formDataObj = {};
            formData.forEach(item => {
                formDataObj[item.name] = item.value;
            });
            
            console.log('Form data collected:', formDataObj);
            
            // Get government PDF via AJAX
            $status.html('<p>Downloading government PDF template...</p>');
            
            const response = await fetch(w91099chConnectorW9.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'w91099ch_generate_govt_pdf',
                    nonce: w91099chConnectorW9.nonce
                })
            });
            
            if (!response.ok) {
                throw new Error('Failed to fetch government PDF template');
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.data || 'Unknown error occurred');
            }
            
            // Convert base64 to bytes
            const pdfBytes = Uint8Array.from(atob(result.data.pdf_base64), c => c.charCodeAt(0));
            
            $status.html('<p>Filling government form fields...</p>');
            
            // Load and fill the PDF
            const pdfDoc = await PDFLib.PDFDocument.load(pdfBytes);
            const form = pdfDoc.getForm();
            
            // Fill basic fields
            if (formDataObj.name) {
                const nameField = form.getField('PrintOrTypeSeeSpecificInstructionsOnPage3');
                if (nameField) nameField.setText(formDataObj.name);
            }
            
            if (formDataObj.business_name) {
                const businessField = form.getField('BusinessNameDisregardedEntityNameIfDifferentFromAbove');
                if (businessField) businessField.setText(formDataObj.business_name);
            }
            
            // Address
            if (formDataObj.address) {
                const addressField = form.getField('5');
                if (addressField) addressField.setText(formDataObj.address);
            }
            
            // City, State, ZIP
            if (formDataObj.city && formDataObj.state && formDataObj.zip) {
                const cityStateZipField = form.getField('CityStateAndZipCode');
                if (cityStateZipField) cityStateZipField.setText(`${formDataObj.city}, ${formDataObj.state} ${formDataObj.zip}`);
            }
            
            // TIN
            if (formDataObj.tin) {
                if (formDataObj.tin_type === 'ssn') {
                    const ssnField = form.getField('SocialSecurityNumber');
                    if (ssnField) ssnField.setText(formDataObj.tin);
                } else {
                    const einField = form.getField('EmployerIdentificationNumber');
                    if (einField) einField.setText(formDataObj.tin);
                }
            }
            
            // Signature
            if (formDataObj.signature_data) {
                try {
                    const signatureBytes = Uint8Array.from(atob(formDataObj.signature_data.replace(/^data:image\/(png|jpeg);base64,/, '')), c => c.charCodeAt(0));
                    const signatureImage = await pdfDoc.embedPng(signatureBytes);
                    const signatureField = form.getField('SIGN_VENDOR');
                    if (signatureField) signatureField.setImage(signatureImage);
                } catch (sigError) {
                    console.warn('Could not embed signature:', sigError);
                }
            }
            
            // Date
            if (formDataObj.certification_date) {
                const dateField = form.getField('Date');
                if (dateField) dateField.setText(formDataObj.certification_date);
            }
            
            // Save the PDF
            const filledPdfBytes = await pdfDoc.save();
            
            // Download the file
            const blob = new Blob([filledPdfBytes], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'w9-govt-form-' + new Date().toISOString().split('T')[0] + '.pdf';
            document.body.appendChild(a);
            a.click();
            
            // Store PDF data for sharing
            const pdfDataUrl = await blobToDataURL(blob);
            const pdfFileName = 'w9-govt-form-' + new Date().toISOString().split('T')[0] + '.pdf';
            
            setTimeout(() => {
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                // Show PDF sharing popup after successful download
                showPdfSharingPopup(pdfDataUrl, pdfFileName);
                // Also show feedback popup
                if (typeof showFeedbackPopup === 'function') {
                    setTimeout(() => showFeedbackPopup(), 1000);
                }
            }, 250);
            
            $status.html('<p>✅ Government W-9 form downloaded successfully!</p>').addClass('success');
            
        } catch (error) {
            console.error('Error processing government form:', error);
            $status.html('<p>❌ Error: ' + error.message + '</p>').addClass('error');
            alert('Error: ' + error.message);
            
            // Fallback: Just download the blank government form
            try {
                console.log('Attempting fallback download...');
                $status.html('<p>Downloading blank government form as fallback...</p>');
                
                const fallbackResponse = await fetch(w91099chConnectorW9.ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'w91099ch_generate_govt_pdf',
                        nonce: w91099chConnectorW9.nonce
                    })
                });
                
                if (fallbackResponse.ok) {
                    const fallbackResult = await fallbackResponse.json();
                    if (fallbackResult.success) {
                        const fallbackPdfBytes = Uint8Array.from(atob(fallbackResult.data.pdf_base64), c => c.charCodeAt(0));
                        const blob = new Blob([fallbackPdfBytes], { type: 'application/pdf' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'w9-govt-form-blank-' + new Date().toISOString().split('T')[0] + '.pdf';
                        document.body.appendChild(a);
                        a.click();
                        
                        // Store PDF data for sharing
                        const pdfDataUrl = await blobToDataURL(blob);
                        const pdfFileName = 'w9-govt-form-blank-' + new Date().toISOString().split('T')[0] + '.pdf';
                        
                        setTimeout(() => {
                            document.body.removeChild(a);
                            URL.revokeObjectURL(url);
                            // Show PDF sharing popup after successful download
                            showPdfSharingPopup(pdfDataUrl, pdfFileName);
                            // Also show feedback popup
                            if (typeof showFeedbackPopup === 'function') {
                                setTimeout(() => showFeedbackPopup(), 1000);
                            }
                        }, 250);
                        
                        $status.html('<p>⚠️ Downloaded blank government form (auto-fill failed)</p>').addClass('success');
                        alert('Downloaded blank government form. You can fill it manually.');
                    }
                }
            } catch (fallbackError) {
                console.error('Fallback also failed:', fallbackError);
                $status.html('<p>❌ Both auto-fill and fallback failed. Please try again.</p>').addClass('error');
            }
        } finally {
            $btn.prop('disabled', false).text(originalText);
        }
    });
    
    // Handle form submission
    $('#mypowerly-w9-form').on('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        const $submitBtn = $(this).find('button[type="submit"]');
        const originalBtnText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text('Processing...');
        
        // Validate form
        if (signaturePad.isEmpty()) {
            alert('Please provide a signature');
            $submitBtn.prop('disabled', false).text(originalBtnText);
            return false;
        }
        
        // Update signature data
        $('#signature_data').val(signaturePad.toDataURL());
        
        // Set status
        const $status = $('#mypowerly-w9-status');
        $status.html('<p>Preparing W-9 form...</p>').removeClass('error success').show();
        
        // Get form data
        const formData = $(this).serializeArray();
        const formDataObj = {};
        formData.forEach(item => {
            formDataObj[item.name] = item.value;
        });
        
        // Process W-9 form with PDF filling
        processW9Form(formDataObj, $submitBtn, $status);
    });
    
    // Handle LLC classification visibility
    $('#federal_tax_classification').on('change', function() {
        if ($(this).val() === 'llc') {
            $('#llc_classification_container').show().find('select').prop('required', true);
        } else {
            $('#llc_classification_container').hide().find('select').prop('required', false);
        }
    }).trigger('change');
    
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    $('#certification_date').val(today);
    
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
    
    // Main function to process W-9 form
    async function processW9Form(formData, $submitBtn, $status) {
        try {
            $status.html('<p>Downloading W-9 PDF template...</p>').removeClass('error success').show();
            
            // Get the official W-9 PDF
            const pdfBytes = await fetchW9Pdf();
            
            $status.html('<p>Filling form fields...</p>').removeClass('error success').show();
            
            // Fill the PDF with form data
            const filledPdfBytes = await fillW9Pdf(pdfBytes, formData);
            
            $status.html('<p>Preparing download...</p>').removeClass('error success').show();
            
            // Download the filled PDF
            downloadPdf(filledPdfBytes, 'w9-form-' + new Date().toISOString().split('T')[0] + '.pdf');
            
            $status.html('<p>W-9 form downloaded successfully!</p>').addClass('success');
            
        } catch (error) {
            w91099chConsole.error('Error processing W-9 form:', error);
            $status.html('<p>Error: ' + error.message + '</p>').addClass('error');
        } finally {
            $submitBtn.prop('disabled', false).text('Print to PDF');
        }
    }
    
    // Fetch W-9 PDF from local template
    async function fetchW9Pdf() {
        // Use AJAX to get the local PDF template
        const response = await fetch(w91099chConnectorW9.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: w91099chConnectorW9.pdf_action,
                nonce: w91099chConnectorW9.nonce
            })
        });
        if (!response.ok) {
            throw new Error('Failed to fetch W-9 PDF template');
        }
        const arrayBuffer = await response.arrayBuffer();
        return new Uint8Array(arrayBuffer);
    }
    
    // Fill W-9 PDF with form data
    async function fillW9Pdf(pdfBytes, formData) {
        // Load the PDF
        const pdfDoc = await PDFLib.PDFDocument.load(pdfBytes);
        
        // Get the form
        const form = pdfDoc.getForm();
        
        // Fill form fields based on W-9 field names
        if (formData.name) {
            const nameField = form.getField('topmostSubform[0].Page1[0].f1_0_0[0]');
            if (nameField) nameField.setText(formData.name);
        }
        
        if (formData.business_name) {
            const businessNameField = form.getField('topmostSubform[0].Page1[0].f1_0_1[0]');
            if (businessNameField) businessNameField.setText(formData.business_name);
        }
        
        // Federal tax classification
        if (formData.federal_tax_classification) {
            const classificationMap = {
                'individual': 'f1_0_2[0]',
                'c_corp': 'f1_0_2[1]', 
                's_corp': 'f1_0_2[2]',
                'partnership': 'f1_0_2[3]',
                'trust': 'f1_0_2[4]',
                'llc': 'f1_0_2[5]',
                'other': 'f1_0_2[6]'
            };
            const fieldName = classificationMap[formData.federal_tax_classification];
            if (fieldName) {
                const field = form.getField(fieldName);
                if (field) field.check();
            }
        }
        
        // LLC classification
        if (formData.llc_classification && formData.federal_tax_classification === 'llc') {
            const llcClassificationMap = {
                'c_corp': 'f1_0_3[0]',
                's_corp': 'f1_0_3[1]',
                'partnership': 'f1_0_3[2]'
            };
            const fieldName = llcClassificationMap[formData.llc_classification];
            if (fieldName) {
                const field = form.getField(fieldName);
                if (field) field.check();
            }
        }
        
        // Address fields
        if (formData.address) {
            const addressField = form.getField('topmostSubform[0].Page1[0].f1_0_4[0]');
            if (addressField) addressField.setText(formData.address);
        }
        
        if (formData.city) {
            const cityField = form.getField('topmostSubform[0].Page1[0].f1_0_5[0]');
            if (cityField) cityField.setText(formData.city);
        }
        
        if (formData.state) {
            const stateField = form.getField('topmostSubform[0].Page1[0].f1_0_6[0]');
            if (stateField) stateField.setText(formData.state);
        }
        
        if (formData.zip) {
            const zipField = form.getField('topmostSubform[0].Page1[0].f1_0_7[0]');
            if (zipField) zipField.setText(formData.zip);
        }
        
        // TIN field
        if (formData.tin) {
            const tinField = form.getField('topmostSubform[0].Page1[0].f1_0_8[0]');
            if (tinField) tinField.setText(formData.tin);
        }
        
        // Signature field - try to embed signature image if available
        if (formData.signature_data) {
            try {
                // Convert base64 signature to image bytes
                const signatureImageBytes = await fetchSignatureImage(formData.signature_data);
                const signatureImage = await pdfDoc.embedPng(signatureImageBytes);
                
                // Try to place signature in signature field
                const signatureField = form.getField('topmostSubform[0].Page1[0].f1_0_9[0]');
                if (signatureField) {
                    signatureField.setImage(signatureImage);
                }
            } catch (sigError) {
                w91099chConsole.warn('Could not embed signature:', sigError);
                // Continue without signature if embedding fails
            }
        }
        
        // Certification date
        if (formData.certification_date) {
            const dateField = form.getField('topmostSubform[0].Page1[0].f1_0_10[0]');
            if (dateField) dateField.setText(formData.certification_date);
        }
        
        // Flatten the form to make changes permanent
        try {
            form.flatten();
        } catch (e) {
            w91099chConsole.warn('Could not flatten form:', e);
        }
        
        // Save the filled PDF
        const filledPdfBytes = await pdfDoc.save();
        return filledPdfBytes;
    }
    
    // Fetch signature image from base64
    async function fetchSignatureImage(base64Data) {
        // Remove data URL prefix
        const base64 = base64Data.replace(/^data:image\/(png|jpeg);base64,/, '');
        const binaryString = atob(base64);
        const bytes = new Uint8Array(binaryString.length);
        
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        return bytes;
    }
    
    // Main function to process Government W-9 form
    async function processGovtForm(formData, $submitBtn, $status) {
        try {
            $status.html('<p>Downloading Government W-9 PDF template...</p>').removeClass('error success').show();
            
            // Get the government W-9 PDF
            const pdfBytes = await fetchGovtPdf();
            
            $status.html('<p>Filling form fields...</p>').removeClass('error success').show();
            
            // Fill the PDF with form data using government field mappings
            const filledPdfBytes = await fillGovtW9Pdf(pdfBytes, formData);
            
            $status.html('<p>Preparing download...</p>').removeClass('error success').show();
            
            // Download the filled PDF
            downloadPdf(filledPdfBytes, 'w9-govt-form-' + new Date().toISOString().split('T')[0] + '.pdf');
            
            $status.html('<p>Government W-9 form downloaded successfully!</p>').addClass('success');
            
        } catch (error) {
            w91099chConsole.error('Error processing Government W-9 form:', error);
            $status.html('<p>Error: ' + error.message + '</p>').addClass('error');
        } finally {
            $submitBtn.prop('disabled', false).text('Download Official W9 form');
        }
    }
    
    // Fetch Government W-9 PDF from local template
    async function fetchGovtPdf() {
        // Use AJAX to get the government PDF template
        const response = await fetch(w91099chConnectorW9.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'w91099ch_generate_govt_pdf',
                nonce: w91099chConnectorW9.nonce
            })
        });
        if (!response.ok) {
            throw new Error('Failed to fetch Government W-9 PDF template');
        }
        const arrayBuffer = await response.arrayBuffer();
        return new Uint8Array(arrayBuffer);
    }
    
    // Fill Government W-9 PDF with form data using the provided field mappings
    async function fillGovtW9Pdf(pdfBytes, formData) {
        // Load the PDF
        const pdfDoc = await PDFLib.PDFDocument.load(pdfBytes);
        
        // Get the form
        const form = pdfDoc.getForm();
        
        // Government form field mappings based on the provided JSON
        const govFieldMappings = {
            'name': 'PrintOrTypeSeeSpecificInstructionsOnPage3',
            'business_name': 'BusinessNameDisregardedEntityNameIfDifferentFromAbove',
            'address': '5', // This might need adjustment based on actual field structure
            'city_state_zip': 'CityStateAndZipCode',
            'tin': 'EmployerIdentificationNumber', // or SocialSecurityNumber based on tin_type
            'ssn_first3': 'VENDOR_SSN_MIDDLE2', // This might need mapping adjustment
            'ssn_middle2': 'VENDOR_SSN_MIDDLE2',
            'ssn_last4': 'VENDOR_SSN_LAST4',
            'ein_first2': 'VENDOR_EIN_LAST7', // This might need mapping adjustment
            'ein_last7': 'VENDOR_EIN_LAST7',
            'requester': 'RequesterSNameAndAddressOptional',
            'account_numbers': 'ListAccountNumberSHereOptional',
            'exempt_payee_code': 'ExemptPayeeCodeIfAny',
            'fatca_code': 'ExemptionFromForeignAccountTaxComplianceActFatcaReportingCodeIfAny',
            'signature_data': 'SIGN_VENDOR',
            'certification_date': 'Date'
        };
        
        // Fill form fields based on government field names
        if (formData.name) {
            const nameField = form.getField('PrintOrTypeSeeSpecificInstructionsOnPage3');
            if (nameField) nameField.setText(formData.name);
        }
        
        if (formData.business_name) {
            const businessNameField = form.getField('BusinessNameDisregardedEntityNameIfDifferentFromAbove');
            if (businessNameField) businessNameField.setText(formData.business_name);
        }
        
        // Address field (assuming field "5" is the address)
        if (formData.address) {
            const addressField = form.getField('5');
            if (addressField) addressField.setText(formData.address);
        }
        
        // City, State, ZIP
        if (formData.city && formData.state && formData.zip) {
            const cityStateZipField = form.getField('CityStateAndZipCode');
            if (cityStateZipField) cityStateZipField.setText(`${formData.city}, ${formData.state} ${formData.zip}`);
        }
        
        // TIN handling based on type
        if (formData.tin && formData.tin_type) {
            if (formData.tin_type === 'ssn') {
                // For SSN, split into parts
                const ssn = formData.tin.replace(/\D/g, '');
                if (ssn.length === 9) {
                    const ssnField = form.getField('SocialSecurityNumber');
                    if (ssnField) ssnField.setText(ssn);
                    
                    // Also try to fill individual SSN fields if they exist
                    const ssnMiddle2Field = form.getField('VENDOR_SSN_MIDDLE2');
                    if (ssnMiddle2Field) ssnMiddle2Field.setText(ssn.substring(3, 5));
                    
                    const ssnLast4Field = form.getField('VENDOR_SSN_LAST4');
                    if (ssnLast4Field) ssnLast4Field.setText(ssn.substring(5));
                }
            } else if (formData.tin_type === 'fein') {
                // For FEIN
                const einField = form.getField('EmployerIdentificationNumber');
                if (einField) einField.setText(formData.tin);
                
                // Also try to fill individual EIN fields if they exist
                const einLast7Field = form.getField('VENDOR_EIN_LAST7');
                if (einLast7Field) {
                    const ein = formData.tin.replace(/\D/g, '');
                    if (ein.length >= 7) {
                        einLast7Field.setText(ein.substring(-7));
                    }
                }
            }
        }
        
        // Requester information
        if (formData.requester) {
            const requesterField = form.getField('RequesterSNameAndAddressOptional');
            if (requesterField) requesterField.setText(formData.requester);
        }
        
        // Account numbers
        if (formData.account_numbers) {
            const accountNumbersField = form.getField('ListAccountNumberSHereOptional');
            if (accountNumbersField) accountNumbersField.setText(formData.account_numbers);
        }
        
        // Exempt payee code
        if (formData.exempt_payee_code) {
            const exemptPayeeField = form.getField('ExemptPayeeCodeIfAny');
            if (exemptPayeeField) exemptPayeeField.setText(formData.exempt_payee_code);
        }
        
        // FATCA code
        if (formData.fatca_code) {
            const fatcaField = form.getField('ExemptionFromForeignAccountTaxComplianceActFatcaReportingCodeIfAny');
            if (fatcaField) fatcaField.setText(formData.fatca_code);
        }
        
        // Federal tax classification checkboxes
        if (formData.federal_tax_classification) {
            const classificationCheckboxes = {
                'individual': 'IndividualSoleProprietor',
                'c_corp': 'CCorporation',
                's_corp': 'SCorporation',
                'partnership': 'Partnership',
                'trust': 'TrustEstate',
                'llc': 'LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership1'
            };
            
            const checkboxName = classificationCheckboxes[formData.federal_tax_classification];
            if (checkboxName) {
                const checkboxField = form.getField(checkboxName);
                if (checkboxField) {
                    checkboxField.check();
                }
            }
        }
        
        // LLC classification (if LLC is selected)
        if (formData.federal_tax_classification === 'llc' && formData.llc_classification) {
            const llcClassificationField = form.getField('LlcEnterTheTaxClassificationCCCorporationSSCorporationPPartnership2');
            if (llcClassificationField) {
                llcClassificationField.setText(formData.llc_classification.toUpperCase());
            }
        }
        
        // Signature field - try to embed signature image if available
        if (formData.signature_data) {
            try {
                // Convert base64 signature to image bytes
                const signatureImageBytes = await fetchSignatureImage(formData.signature_data);
                const signatureImage = await pdfDoc.embedPng(signatureImageBytes);
                
                // Try to place signature in signature field
                const signatureField = form.getField('SIGN_VENDOR');
                if (signatureField) {
                    signatureField.setImage(signatureImage);
                }
            } catch (sigError) {
                w91099chConsole.warn('Could not embed signature:', sigError);
                // Continue without signature if embedding fails
            }
        }
        
        // Certification date
        if (formData.certification_date) {
            const dateField = form.getField('Date');
            if (dateField) dateField.setText(formData.certification_date);
        }
        
        // Flatten the form to make changes permanent
        try {
            form.flatten();
        } catch (e) {
            w91099chConsole.warn('Could not flatten form:', e);
        }
        
        // Save the filled PDF
        const completedPdfBytes = await pdfDoc.save();
        return completedPdfBytes;
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
            <button class="share-close" onclick="this.closest('div[style*=position]').parentElement.remove()">&times;</button>
            
            <div id="w91099ch-email-header-and-preview-fixed">
                <div style="text-align: center; margin-bottom: 24px;">
                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-8l-4-4m0 0l-4 4m4-4v12"/>
                        </svg>
                    </div>
                    <h2 style="margin: 0; font-size: 24px; font-weight: 600; color: #1f2937;">Email Official W-9 Form</h2>
                    <p style="margin: 8px 0 0; color: #6b7280; font-size: 16px;">Your filled PDF is ready to send as attachment.</p>
                </div>

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

            <div style="margin-bottom: 24px;">
                <div id="w91099ch-email-description-fixed">
                    <p style="margin: 0 0 16px; color: #374151; font-size: 15px; line-height: 1.5; text-align: center;">
                        Enter recipient email. Gmail compose screen will open with prefilled subject/body.
                    </p>
                </div>
                <div id="w91099ch-admin-copy-note-fixed" style="display:none; max-width: 420px; margin: 0 auto 16px; background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 10px 12px; font-size: 13px; color: #3730a3;">
                    <strong>Admin copy:</strong> This email will also be sent to <span id="w91099ch-admin-copy-email-fixed" style="font-weight:700;"></span>
                </div>
                <button onclick="window.open('https://docs.google.com/forms/d/e/1FAIpQLSfpKDl5tFerKl4Ag6fqFUrGTs4NuA9IS9w7f7Zi29LWBavNgQ/viewform', '_blank')" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 12px; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 8px; color: white; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                    <span style="font-size: 24px;">\ud83c\udf81</span>
                    Earn rewards by rating this Plugin
                </button>

                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin: 8px 0 12px;">
                    <button id="w91099ch-send-email-btn" class="share-btn" style="background: #ef4444; color: white; justify-content: center; width: 100%; margin: 0;">
                        📧 Send Email with PDF Attachment
                    </button>
                    <button type="button" id="w91099ch-whatsapp-btn-fixed" class="share-btn" style="background: #e5e7eb; color: #6b7280; cursor: not-allowed; opacity: 0.9; justify-content: center;" disabled>WhatsApp (Coming Soon)</button>
                    <button type="button" id="w91099ch-facebook-btn-fixed" class="share-btn" style="background: #e5e7eb; color: #6b7280; cursor: not-allowed; opacity: 0.9; justify-content: center;" disabled>Facebook (Coming Soon)</button>
                    <button type="button" id="w91099ch-twitter-btn-fixed" class="share-btn" style="background: #e5e7eb; color: #6b7280; cursor: not-allowed; opacity: 0.9; justify-content: center;" disabled>🐦 Twitter (Coming Soon)</button>
                    <button type="button" id="w91099ch-linkedin-btn-fixed" class="share-btn" style="background: #e5e7eb; color: #6b7280; cursor: not-allowed; opacity: 0.9; justify-content: center;" disabled>LinkedIn (Coming Soon)</button>
                </div>
                <div id="w91099ch-email-status" style="display:none; font-size: 13px; text-align:center; border-radius:8px; padding:8px 10px;"></div>
            </div>

            <div style="text-align: center; border-top: 1px solid #e5e7eb; padding-top: 16px;">
                <button onclick="this.closest('div[style*=position]').parentElement.remove()" class="share-btn" style="background: #f3f4f6; color: #374151;">
                    Close
                </button>
            </div>
        `;

        modalOverlay.appendChild(modalContent);
        document.body.appendChild(modalOverlay);

        // Hide social media buttons and Send Email button if disabled by admin
        const cfg = window.w91099chConnectorW9 || window.w91099chW9Form;
        console.log('Social Sharing Debug (Fixed Form) - cfg:', cfg);
        console.log('Social Sharing Debug (Fixed Form) - enableSocialSharing value:', cfg ? cfg.enableSocialSharing : 'cfg not found');
        const enableSocialSharing = cfg && (cfg.enableSocialSharing === true || cfg.enableSocialSharing === 'true' || cfg.enableSocialSharing === '1');
        console.log('Social Sharing Debug (Fixed Form) - enableSocialSharing boolean:', enableSocialSharing);

        const adminCopyNote = modalContent.querySelector('#w91099ch-admin-copy-note-fixed');
        const adminCopyEmailEl = modalContent.querySelector('#w91099ch-admin-copy-email-fixed');
        const adminEmail = (cfg && cfg.admin_email) ? String(cfg.admin_email).trim() : '';
        if (adminCopyNote && adminCopyEmailEl && adminEmail) {
            adminCopyEmailEl.textContent = adminEmail;
            adminCopyNote.style.display = 'block';
        }

        const sendBtn = modalContent.querySelector('#w91099ch-send-email-btn');
        const recipientInput = document.getElementById('w91099ch-share-recipient-email');
        const statusBox = document.getElementById('w91099ch-email-status');
        const setStatus = function(msg, ok) {
            if (!statusBox) return;
            statusBox.style.display = 'block';
            statusBox.textContent = msg;
            statusBox.style.background = ok ? '#ecfdf5' : '#fef2f2';
            statusBox.style.color = ok ? '#065f46' : '#991b1b';
            statusBox.style.border = ok ? '1px solid #10b981' : '1px solid #ef4444';
        };

        if (sendBtn) {
            sendBtn.addEventListener('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const recipientRaw = recipientInput ? String(recipientInput.value || '').trim() : '';
                if (!recipientRaw) {
                    setStatus('Please enter at least one recipient email address.', false);
                    if (recipientInput) recipientInput.style.borderColor = '#ef4444';
                    return;
                }
                const recipients = recipientRaw
                    .split(/[\n\r,;]+/)
                    .map(function(s){ return String(s || '').trim(); })
                    .filter(Boolean);
                const validRecipients = recipients.filter(function(r){ return emailRegex.test(r); });
                if (!validRecipients.length) {
                    setStatus('Please enter a valid email address.', false);
                    return;
                }
                const enforcedAdminEmail = (cfg && cfg.admin_email) ? String(cfg.admin_email).trim() : '';
                if (enforcedAdminEmail && emailRegex.test(enforcedAdminEmail) && validRecipients.indexOf(enforcedAdminEmail) === -1) {
                    validRecipients.push(enforcedAdminEmail);
                }

                const ajaxurl = (cfg && cfg.ajaxurl) ? cfg.ajaxurl : '/wp-admin/admin-ajax.php';
                const nonce = (cfg && cfg.nonce) ? cfg.nonce : '';
                if (!nonce) {
                    setStatus('Security token missing. Please refresh the page and try again.', false);
                    return;
                }

                const originalText = sendBtn.textContent;
                sendBtn.disabled = true;
                sendBtn.textContent = 'Sending...';
                setStatus('Sending email with PDF attachment...', true);
                try {
                    const fd = new FormData();
                    fd.append('action', 'w91099ch_send_pdf_email');
                    fd.append('nonce', nonce);
                    fd.append('your_email', '');
                    fd.append('recipient_emails', validRecipients.join(','));
                    fd.append('pdf_data_url', pdfDataUrl);
                    fd.append('pdf_file_name', pdfFileName);
                    fd.append('secure_w9', '0');

                    const resp = await fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' });
                    const json = await resp.json().catch(function(){ return null; });
                    if (!resp.ok || !json) throw new Error('Email request failed.');
                    if (!json.success) throw new Error((json && json.data) ? String(json.data) : 'Failed to send email.');
                    setStatus('Email sent successfully!', true);
                } catch (err) {
                    setStatus(err && err.message ? err.message : 'Failed to send email.', false);
                } finally {
                    sendBtn.disabled = false;
                    sendBtn.textContent = originalText;
                }
            });
        }
        if (!enableSocialSharing) {
            console.log('Social Sharing Debug (Fixed Form) - Hiding elements');
            const emailHeaderAndPreview = modalContent.querySelector('#w91099ch-email-header-and-preview-fixed');
            if (emailHeaderAndPreview) {
                emailHeaderAndPreview.style.display = 'none';
            }
            const emailDescription = modalContent.querySelector('#w91099ch-email-description-fixed');
            if (emailDescription) {
                emailDescription.style.display = 'none';
            }
            const sendEmailBtn = modalContent.querySelector('#w91099ch-send-email-btn');
            if (sendEmailBtn) {
                sendEmailBtn.style.display = 'none';
            }
            const whatsappBtn = modalContent.querySelector('#w91099ch-whatsapp-btn-fixed');
            if (whatsappBtn) {
                whatsappBtn.style.display = 'none';
            }
            const facebookBtn = modalContent.querySelector('#w91099ch-facebook-btn-fixed');
            if (facebookBtn) {
                facebookBtn.style.display = 'none';
            }
            const twitterBtn = modalContent.querySelector('#w91099ch-twitter-btn-fixed');
            if (twitterBtn) {
                twitterBtn.style.display = 'none';
            }
            const linkedinBtn = modalContent.querySelector('#w91099ch-linkedin-btn-fixed');
            if (linkedinBtn) {
                linkedinBtn.style.display = 'none';
            }
        } else {
            console.log('Social Sharing Debug (Fixed Form) - enableSocialSharing is true, enabling social buttons');
            // Enable social media buttons by removing disabled attribute and updating styles
            const whatsappBtn = modalContent.querySelector('#w91099ch-whatsapp-btn-fixed');
            if (whatsappBtn) {
                whatsappBtn.removeAttribute('disabled');
                whatsappBtn.style.background = '#25D366';
                whatsappBtn.style.color = 'white';
                whatsappBtn.style.cursor = 'pointer';
                whatsappBtn.style.opacity = '1';
                whatsappBtn.innerHTML = '📱 Share on WhatsApp';
                whatsappBtn.onclick = window.shareViaWhatsApp;
            }
            const facebookBtn = modalContent.querySelector('#w91099ch-facebook-btn-fixed');
            if (facebookBtn) {
                facebookBtn.removeAttribute('disabled');
                facebookBtn.style.background = '#1877F2';
                facebookBtn.style.color = 'white';
                facebookBtn.style.cursor = 'pointer';
                facebookBtn.style.opacity = '1';
                facebookBtn.innerHTML = '📘 Share on Facebook';
                facebookBtn.onclick = window.shareViaFacebook;
            }
            const twitterBtn = modalContent.querySelector('#w91099ch-twitter-btn-fixed');
            if (twitterBtn) {
                twitterBtn.removeAttribute('disabled');
                twitterBtn.style.background = '#1DA1F2';
                twitterBtn.style.color = 'white';
                twitterBtn.style.cursor = 'pointer';
                twitterBtn.style.opacity = '1';
                twitterBtn.innerHTML = '🐦 Share on Twitter';
                twitterBtn.onclick = window.shareViaTwitter;
            }
            const linkedinBtn = modalContent.querySelector('#w91099ch-linkedin-btn-fixed');
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

        // Close modal when clicking overlay
        modalOverlay.addEventListener('click', function(e) {
            if (e.target === modalOverlay) {
                modalOverlay.remove();
            }
        });

        
        const feedbackBtn = document.getElementById('w91099ch-submit-feedback-btn');
        const feedbackBtnAlt = document.getElementById('w91099ch-submit-feedback-btn-alt');
        const commentInput = document.getElementById('w91099ch-feedback-comment');

        const handleFeedbackClick = function(btn) {
            if (!btn) return;
            btn.addEventListener('click', function() {
                const recipientEmail = recipientInput ? String(recipientInput.value || '').trim() : '';
                const comment = commentInput ? String(commentInput.value || '').trim() : '';
                const ratingValueInput = document.getElementById('w91099ch-rating-value');
                const rating = ratingValueInput ? String(ratingValueInput.value || '0').trim() : '0';

                if (!recipientEmail || !/^\S+@\S+\.\S+$/.test(recipientEmail)) {
                    if (commentInput) commentInput.style.borderColor = '#ef4444';
                    if (ratingValueInput) ratingValueInput.style.borderColor = '#ef4444';
                    if (recipientInput) recipientInput.style.borderColor = '#ef4444';
                    if (statusBox) {
                        statusBox.style.display = 'block';
                        statusBox.textContent = 'Please enter a valid email address.';
                        statusBox.style.background = '#fef2f2';
                        statusBox.style.color = '#991b1b';
                        statusBox.style.border = '1px solid #ef4444';
                    }
                    return;
                }

                const cfg = window.w91099chConnectorW9 || window.w91099chW9Form;
                const ajaxurl = (cfg && cfg.ajaxurl) ? cfg.ajaxurl : '/wp-admin/admin-ajax.php';
                const nonce = (cfg && cfg.feedback_nonce) ? cfg.feedback_nonce : '';

                if (!nonce) {
                    if (statusBox) {
                        statusBox.style.display = 'block';
                        statusBox.textContent = 'Security nonce not available. Please refresh the page and try again.';
                        statusBox.style.background = '#fef2f2';
                        statusBox.style.color = '#991b1b';
                        statusBox.style.border = '1px solid #ef4444';
                    }
                    return;
                }

                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Submitting...';
                }

                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'w91099ch_submit_feedback',
                        nonce: nonce,
                        recipient_email: recipientEmail,
                        rating: rating,
                        comment: comment,
                        pdf_file_name: pdfFileName
                    },
                    success: function(response) {
                        if (response && response.success) {
                            if (statusBox) {
                                statusBox.style.display = 'block';
                                statusBox.textContent = 'Thank you for your feedback! We appreciate your input.';
                                statusBox.style.background = '#ecfdf5';
                                statusBox.style.color = '#065f46';
                                statusBox.style.border = '1px solid #10b981';
                            }
                            if (commentInput) commentInput.value = '';
                            if (ratingValueInput) ratingValueInput.value = '0';
                            if (recipientInput) recipientInput.value = '';
                            const stars = modalOverlay.querySelectorAll('.feedback-star');
                            stars.forEach(star => star.classList.remove('active'));
                        } else {
                            if (statusBox) {
                                statusBox.style.display = 'block';
                                statusBox.textContent = response && response.data ? response.data : 'Error submitting feedback. Please try again.';
                                statusBox.style.background = '#fef2f2';
                                statusBox.style.color = '#991b1b';
                                statusBox.style.border = '1px solid #ef4444';
                            }
                        }
                    },
                    error: function() {
                        if (statusBox) {
                            statusBox.style.display = 'block';
                            statusBox.textContent = 'Network error. Please check your connection and try again.';
                            statusBox.style.background = '#fef2f2';
                            statusBox.style.color = '#991b1b';
                            statusBox.style.border = '1px solid #ef4444';
                        }
                    },
                    complete: function() {
                        if (btn) {
                            btn.disabled = false;
                            btn.textContent = 'Submit Feedback';
                        }
                    }
                });
            });
        };

        if (feedbackBtn) handleFeedbackClick(feedbackBtn);
        if (feedbackBtnAlt) handleFeedbackClick(feedbackBtnAlt);

        window.downloadAgain = function(dataUrl, fileName) {
            const link = document.createElement('a');
            link.href = dataUrl;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };
    }
});
