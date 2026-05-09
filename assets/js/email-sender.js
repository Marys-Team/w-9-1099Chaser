/**
 * Open Gmail compose with email inputs
 */
function openGmailCompose(emails = '', subject = 'Document', body = 'Please find attached document.') {
    // Create Gmail compose URL
    const gmailUrl = `https://mail.google.com/mail/u/0/?fs=1&to=${encodeURIComponent(emails)}&su=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}&tf=cm`;
    
    // Open Gmail in new tab
    window.open(gmailUrl, '_blank');
}

/**
 * Show email input modal
 */
function showEmailInputModal() {
    // Create modal HTML
    const modalHTML = `
        <div id="email-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">
            <div style="background:white;padding:20px;border-radius:8px;width:400px;max-width:90%;">
                <h3 style="margin:0 0 15px 0;">Send Email</h3>
                <input type="email" id="email-to" placeholder="Enter email addresses (comma separated)" style="width:100%;padding:8px;margin-bottom:10px;border:1px solid #ddd;border-radius:4px;">
                <input type="text" id="email-subject" placeholder="Subject" value="Document" style="width:100%;padding:8px;margin-bottom:10px;border:1px solid #ddd;border-radius:4px;">
                <textarea id="email-body" placeholder="Email body" style="width:100%;padding:8px;margin-bottom:15px;border:1px solid #ddd;border-radius:4px;height:80px;">Please find attached document.</textarea>
                <div style="text-align:right;">
                    <button onclick="closeEmailModal()" style="margin-right:10px;padding:8px 15px;border:1px solid #ddd;background:white;border-radius:4px;cursor:pointer;">Cancel</button>
                    <button onclick="sendEmailFromModal()" style="padding:8px 15px;background:#007cba;color:white;border:none;border-radius:4px;cursor:pointer;">Open Gmail</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    document.getElementById('email-to').focus();
}

function closeEmailModal() {
    const modal = document.getElementById('email-modal');
    if (modal) modal.remove();
}

function sendEmailFromModal() {
    const emails = document.getElementById('email-to').value;
    const subject = document.getElementById('email-subject').value;
    const body = document.getElementById('email-body').value;
    
    closeEmailModal();
    openGmailCompose(emails, subject, body);
}

// Usage example
document.addEventListener('DOMContentLoaded', function() {
    const emailButton = document.getElementById('send-email-btn');
    if (emailButton) {
        emailButton.addEventListener('click', function() {
            showEmailInputModal();
        });
    }
});