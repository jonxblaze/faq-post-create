jQuery(document).ready(function($) {
    $('#faq-submission').on('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        var full_name = $('#faq_full_name').val().trim();
        var email = $('#faq_email').val().trim();
        var title = $('#faq_title').val().trim();
        var question = $('#faq_question').val().trim();
        var company = $('#faq_company').val().trim(); // Honeypot field
        
        // Validation - check in the new order: Full Name, Email, Title, Question
        if (full_name === '') {
            $('#faq-result').html('<div class="faq-message error">Full Name is required.</div>');
            return;
        }
        
        if (email === '') {
            $('#faq-result').html('<div class="faq-message error">Email is required.</div>');
            return;
        }
        
        if (title === '') {
            $('#faq-result').html('<div class="faq-message error">Title is required.</div>');
            return;
        }
        
        if (question === '') {
            $('#faq-result').html('<div class="faq-message error">Question is required.</div>');
            return;
        }
        
        // Basic email validation
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            $('#faq-result').html('<div class="faq-message error">Please enter a valid email address.</div>');
            return;
        }
        
        // Check honeypot field - if it has any value, it's likely a bot
        if (company !== '') {
            $('#faq-result').html('<div class="faq-message error">Submission rejected. Please try again without filling the company field.</div>');
            return;
        }
        
        // Show loading message
        $('#faq-result').html('<div class="faq-message info">Submitting your question...</div>');
        $('#faq_submit_button').prop('disabled', true);
        
        // Send AJAX request
        $.ajax({
            url: faq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'faq_submit_question',
                faq_title: title,
                faq_question: question,
                faq_full_name: full_name,
                faq_email: email,
                faq_company: company, // Include honeypot field (should be empty)
                nonce: faq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#faq-result').html('<div class="faq-message success">' + response.data.message + '</div>');
                    $('#faq-submission')[0].reset(); // Reset form
                    // Clear the honeypot field again after reset
                    $('#faq_company').val('');
                } else {
                    $('#faq-result').html('<div class="faq-message error">' + response.data.message + '</div>');
                }
                $('#faq_submit_button').prop('disabled', false);
            },
            error: function() {
                $('#faq-result').html('<div class="faq-message error">There was an error submitting your question. Please try again.</div>');
                $('#faq_submit_button').prop('disabled', false);
            }
        });
    });
});