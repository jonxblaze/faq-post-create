jQuery(document).ready(function($) {
    $('#faq-submission').on('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        var full_name = $('#faq_full_name').val().trim();
        var email = $('#faq_email').val().trim();
        var question = $('#faq_question').val().trim();
        var company = $('#faq_company').val().trim(); // Honeypot field

        // Validation - check in the new order: Full Name, Email, Question
        if (full_name === '') {
            $('#faq-result').html('<div class="faq-message error">Full Name is required.</div>');
            return;
        }

        if (email === '') {
            $('#faq-result').html('<div class="faq-message error">Email is required.</div>');
            return;
        }

        if (question === '') {
            $('#faq-result').html('<div class="faq-message error">Question is required.</div>');
            return;
        }

        // Check minimum word count (at least 5 words)
        var wordCount = question.trim().split(/\s+/).length;
        if (wordCount < 5) {
            $('#faq-result').html('<div class="faq-message error">Question must contain at least 5 words.</div>');
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
        $('#faq_submit_button').prop('disabled', true);

        // Add a loading spinner to the submit button
        var originalButtonText = $('#faq_submit_button').val();
        $('#faq_submit_button').val('Submitting your question...').prop('disabled', true);

        // Get reCAPTCHA response if reCAPTCHA is present
        var recaptchaResponse = typeof grecaptcha !== 'undefined' ? grecaptcha.getResponse() : '';

        // Validate reCAPTCHA if it's required
        if ($('.g-recaptcha').length > 0 && recaptchaResponse === '') {
            $('#faq-result').html('<div class="faq-message error">Please complete the reCAPTCHA verification.</div>');
            $('#faq_submit_button').val(originalButtonText).prop('disabled', false);
            return;
        }

        // Send AJAX request
        $.ajax({
            url: faq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'faq_submit_question',
                faq_question: question,
                faq_full_name: full_name,
                faq_email: email,
                faq_company: company, // Include honeypot field (should be empty)
                'g-recaptcha-response': recaptchaResponse,
                nonce: faq_ajax.nonce
            },
            success: function(response) {
                // Wait at least 3 seconds before showing the success message
                setTimeout(function() {
                    if (response.success) {
                        // Hide the form and show success message
                        $('#faq-submission').hide();
                        $('#faq-result').html('<div class="faq-message success">Your question was sent and will be answered shortly, thank you.</div>');
                    } else {
                        $('#faq-result').html('<div class="faq-message error">' + response.data.message + '</div>');
                        $('#faq_submit_button').val(originalButtonText).prop('disabled', false);
                    }
                }, 2000); // Wait 3 seconds minimum
            },
            error: function() {
                // Wait at least 3 seconds before showing the error message
                setTimeout(function() {
                    $('#faq-result').html('<div class="faq-message error">There was an error submitting your question. Please try again.</div>');
                    $('#faq_submit_button').val(originalButtonText).prop('disabled', false);
                }, 2000); // Wait 3 seconds minimum
            }
        });
    });
});

// Handle FAQ pagination clicks
jQuery(document).ready(function($) {
    $(document).on('click', '.faq-page-link', function(e) {
        e.preventDefault();

        var page = $(this).data('page');
        var posts_per_page = $(this).data('posts-per-page');

        // Show loading indicator
        $('#faq-list-container').html('<div class="faq-loading">Loading...</div>');

        // Send AJAX request for new page
        $.ajax({
            url: faq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'load_faq_page',
                page: page,
                posts_per_page: posts_per_page,
                nonce: faq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#faq-list-container').html(response.data.html);

                    // Force a reflow and then scroll to the bottom of the FAQ submission form
                    setTimeout(function() {
                        // Get the bottom position of the FAQ submission form
                        var formBottom = $('.faq-submission-form').offset().top + $('.faq-submission-form').outerHeight();
                        $('html, body').animate({
                            scrollTop: formBottom
                        }, 500);
                    }, 50);
                } else {
                    $('#faq-list-container').html('<div class="faq-message error">Error loading FAQs.</div>');
                }
            },
            error: function() {
                $('#faq-list-container').html('<div class="faq-message error">Error loading FAQs.</div>');
            }
        });
    });
});