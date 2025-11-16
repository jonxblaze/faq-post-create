<?php
/**
 * FAQ Form Handler
 * 
 * @package FAQ_Post_Create
 * @subpackage Form
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles FAQ form submission and validation
 */
class FAQ_Form_Handler {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'start_session'), 1);
        add_action('init', array(__CLASS__, 'handle_form_submission'));
        add_action('wp_ajax_faq_submit_question', array(__CLASS__, 'ajax_submit_question'));
        add_action('wp_ajax_nopriv_faq_submit_question', array(__CLASS__, 'ajax_submit_question'));
        add_action('wp_ajax_load_faq_page', array(__CLASS__, 'ajax_load_faq_page'));
        add_action('wp_ajax_nopriv_load_faq_page', array(__CLASS__, 'ajax_load_faq_page'));
    }
    
    /**
     * Start session if not already started
     */
    public static function start_session() {
        if (!session_id()) {
            session_start();
        }
    }
    
    /**
     * Validate FAQ submission form data
     */
    private static function validate_faq_submission($question, $full_name, $email) {
        $errors = array();

        if (empty($question)) {
            $errors[] = __('Question is required.', 'faq-post-create');
        } else {
            // Check minimum word count (at least 5 words)
            $words = preg_split('/\s+/', trim($question), -1, PREG_SPLIT_NO_EMPTY);
            if (count($words) < 5) {
                $errors[] = __('Question must contain at least 5 words.', 'faq-post-create');
            }
        }

        if (empty($full_name)) {
            $errors[] = __('Full Name is required.', 'faq-post-create');
        }

        if (empty($email)) {
            $errors[] = __('Email is required.', 'faq-post-create');
        } elseif (!is_email($email)) {
            $errors[] = __('Please enter a valid email address.', 'faq-post-create');
        }

        return $errors;
    }
    
    /**
     * Handle form submission via AJAX
     */
    public static function ajax_submit_question() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'faq_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'faq-post-create')));
            return;
        }

        // Check reCAPTCHA if enabled
        $recaptcha_response = isset($_POST['g-recaptcha-response']) ? sanitize_text_field($_POST['g-recaptcha-response']) : '';
        if (!self::validate_recaptcha($recaptcha_response)) {
            wp_send_json_error(array('message' => __('Please complete the reCAPTCHA verification.', 'faq-post-create')));
            return;
        }

        // Rate limiting: Check if user submitted within the last 30 seconds
        $last_submission = isset($_SESSION['faq_last_submission']) ? $_SESSION['faq_last_submission'] : 0;
        $time_diff = time() - $last_submission;

        if ($time_diff < 30) {
            wp_send_json_error(array('message' => sprintf(__('Please wait %d seconds before submitting another question.', 'faq-post-create'), 30 - $time_diff)));
            return;
        }

        // Sanitize input
        $question = sanitize_textarea_field($_POST['faq_question']);
        $full_name = sanitize_text_field($_POST['faq_full_name']);
        $email = sanitize_email($_POST['faq_email']);
        $company = isset($_POST['faq_company']) ? sanitize_text_field($_POST['faq_company']) : '';

        // Bot detection: If the honeypot field has a value, it's likely a bot
        if (!empty($company)) {
            wp_send_json_error(array('message' => __('Submission rejected. Please try again without filling the company field.', 'faq-post-create')));
            return;
        }

        // Validate form data using shared validation method
        $errors = self::validate_faq_submission($question, $full_name, $email);

        if (!empty($errors)) {
            wp_send_json_error(array('message' => $errors[0])); // Return first error
        } else {
            // Use question as the post title
            $title = $question;

            // Create the FAQ post as draft - content is managed via custom meta field
            $post_id = wp_insert_post(array(
                'post_title' => $title,
                'post_content' => '',  // Content field not used for this post type
                'post_excerpt' => $question,  // Store original question in excerpt
                'post_status' => 'draft',
                'post_type' => 'faq',
                'post_author' => 0, // No author for anonymous submissions
            ));

            if ($post_id) {
                // Add custom fields as post meta
                update_post_meta($post_id, '_faq_full_name', $full_name);
                update_post_meta($post_id, '_faq_email', $email);
                update_post_meta($post_id, '_faq_original_question', $question); // Store question in custom field too

                // Send notification email to admin
                self::send_admin_notification_email($post_id, $title, $full_name, $email);

                // Set last submission time to prevent rapid submissions
                $_SESSION['faq_last_submission'] = time();

                wp_send_json_success(array(
                    'message' => __('Your question has been submitted successfully. It will be reviewed by an administrator.', 'faq-post-create')
                ));
            } else {
                wp_send_json_error(array('message' => __('There was an error submitting your question. Please try again.', 'faq-post-create')));
            }
        }
    }
    
    /**
     * Handle form submission for non-AJAX fallback
     */
    public static function handle_form_submission() {
        if (isset($_POST['faq_submit']) && wp_verify_nonce($_POST['faq_nonce'], 'faq_nonce')) {
            // Check reCAPTCHA if enabled
            $recaptcha_response = isset($_POST['g-recaptcha-response']) ? sanitize_text_field($_POST['g-recaptcha-response']) : '';
            if (!self::validate_recaptcha($recaptcha_response)) {
                $_SESSION['faq_error'] = __('Please complete the reCAPTCHA verification.', 'faq-post-create');
                return;
            }

            // Rate limiting: Check if user submitted within the last 30 seconds
            $last_submission = isset($_SESSION['faq_last_submission']) ? $_SESSION['faq_last_submission'] : 0;
            $time_diff = time() - $last_submission;

            if ($time_diff < 30) {
                $_SESSION['faq_error'] = sprintf(__('Please wait %d seconds before submitting another question.', 'faq-post-create'), 30 - $time_diff);
                return;
            }

            $question = sanitize_textarea_field($_POST['faq_question']);
            $full_name = sanitize_text_field($_POST['faq_full_name']);
            $email = sanitize_email($_POST['faq_email']);
            $company = isset($_POST['faq_company']) ? sanitize_text_field($_POST['faq_company']) : '';

            // Bot detection: If the honeypot field has a value, it's likely a bot
            if (!empty($company)) {
                $_SESSION['faq_error'] = __('Submission rejected. Please try again without filling the company field.', 'faq-post-create');
                return;
            }

            // Validate form data using shared validation method
            $errors = self::validate_faq_submission($question, $full_name, $email);

            if (!empty($errors)) {
                // Set error message in session or redirect with error
                $_SESSION['faq_error'] = $errors[0]; // Use first error
            } else {
                // Use question as the post title
                $title = $question;

                // Create the FAQ post as draft - content is managed via custom meta field
                $post_id = wp_insert_post(array(
                    'post_title' => $title,
                    'post_content' => '',  // Content field not used for this post type
                    'post_excerpt' => $question,  // Store original question in excerpt
                    'post_status' => 'draft',
                    'post_type' => 'faq',
                    'post_author' => 0, // No author for anonymous submissions
                ));

                if ($post_id) {
                    // Add custom fields as post meta
                    update_post_meta($post_id, '_faq_full_name', $full_name);
                    update_post_meta($post_id, '_faq_email', $email);
                    update_post_meta($post_id, '_faq_original_question', $question); // Store question in custom field too

                    // Send notification email to admin
                    self::send_admin_notification_email($post_id, $title, $full_name, $email);

                    $_SESSION['faq_success'] = __('Your question has been submitted successfully. It will be reviewed by an administrator.', 'faq-post-create');
                    $_SESSION['faq_last_submission'] = time();
                } else {
                    $_SESSION['faq_error'] = __('There was an error submitting your question. Please try again.', 'faq-post-create');
                }
            }
        }
    }

    /**
     * AJAX handler for loading FAQ pagination
     */
    public static function ajax_load_faq_page() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'faq_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'faq-post-create')));
            return;
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $posts_per_page = isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 25;

        // Sanitize inputs
        $page = max(1, $page);
        $posts_per_page = max(1, min(100, $posts_per_page)); // Limit posts per page to reasonable range

        // Include the template handler to access the method
        if (!class_exists('FAQ_Template_Handler')) {
            require_once plugin_dir_path(__FILE__) . 'class-faq-template-handler.php';
        }

        $faq_html = FAQ_Template_Handler::get_paginated_faq_list($posts_per_page, $page);

        wp_send_json_success(array(
            'html' => $faq_html,
            'page' => $page,
            'posts_per_page' => $posts_per_page
        ));
    }

    /**
     * Validate reCAPTCHA response
     */
    private static function validate_recaptcha($recaptcha_response) {
        // Get settings to check if reCAPTCHA is enabled
        if (!class_exists('FAQ_Settings')) {
            // Try to include the settings class if needed
            $settings_path = plugin_dir_path(__FILE__) . 'class-faq-settings.php';
            if (file_exists($settings_path)) {
                require_once $settings_path;
            }
        }

        if (!class_exists('FAQ_Settings')) {
            // If settings class is not available, allow submission to proceed
            return true;
        }

        $settings = FAQ_Settings::get_settings();
        if (empty($settings['recaptcha_enabled']) || empty($settings['recaptcha_secret_key'])) {
            // If reCAPTCHA is not enabled, consider it valid
            return true;
        }

        // Log the recaptcha response for debugging (temporarily)
        // error_log('reCAPTCHA response received: ' . ($recaptcha_response ? substr($recaptcha_response, 0, 20) . '...' : 'EMPTY'));

        if (empty($recaptcha_response)) {
            // Log that response is empty for debugging
            // error_log('reCAPTCHA validation failed: empty response');
            return false;
        }

        $recaptcha_secret_key = $settings['recaptcha_secret_key'];

        // Make request to Google reCAPTCHA API
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret' => $recaptcha_secret_key,
                'response' => $recaptcha_response,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            )
        ));

        if (is_wp_error($response)) {
            // Log API error for debugging
            // error_log('reCAPTCHA API error: ' . $response->get_error_message());
            // If there's an error contacting Google, we'll allow the submission to proceed
            // to avoid blocking legitimate users due to API issues
            return true;
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body);

        // Check if the response contains the success property
        $is_valid = $json && isset($json->success) && $json->success === true;

        // Log validation result for debugging
        // error_log('reCAPTCHA validation result: ' . ($is_valid ? 'SUCCESS' : 'FAILED'));
        // if ($json && isset($json->{'error-codes'})) {
        //     error_log('reCAPTCHA errors: ' . print_r($json->{'error-codes'}, true));
        // }

        return $is_valid;
    }

    /**
     * Send notification email to admin when a new FAQ is submitted
     */
    private static function send_admin_notification_email($post_id, $question_title, $submitter_name, $submitter_email) {
        // Get admin email address
        $admin_email = get_option('admin_email');

        // Get site name
        $site_name = get_bloginfo('name');

        // Set up email subject
        $subject = sprintf('[%s] New Question Submitted: ', $site_name) . wp_trim_words($question_title, 10, '...');

        // Set up HTML email message
        $message = sprintf(
            "<html><body>" .
            "<p>Hello,</p>" .
            "<p>A new FAQ question has been submitted on <strong>%s</strong> and requires your attention:</p>" .
            "<table border='0'>" .
            "<tr><td><strong>Question:</strong> %s</td></tr>" . 
            "<tr><td><strong>Submitted by:</strong> %s</td></tr>" .
            "<tr><td><strong>Submitter's email:</strong> %s</td></tr>" .
            "<tr><td><strong>Submission date:</strong> %s</td></tr>" .
            "</table>" .
            "<p>Please review and respond to this question when possible.</p>" .
            "<p><a href=\"%s\" style=\"display: inline-block; padding: 10px 15px; background-color: #0073aa; color: white; text-decoration: none; border-radius: 4px;\">Manage FAQ Post</a></p>" .
            "<p>Best regards,<br />%s</p>" .
            "</body></html>",
            esc_html($site_name),
            esc_html($question_title),
            esc_html($submitter_name),
            esc_html($submitter_email),
            date('F j, Y g:i A'),
            esc_url(admin_url('post.php?post=' . $post_id . '&action=edit')),
            esc_html($site_name)
        );

        // Set content type to HTML
        add_filter('wp_mail_content_type', function() { return 'text/html'; });

        // Get the site domain for proper "From" address
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        $from_email = 'wordpress@' . $site_domain;

        // Also add headers for proper email delivery
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $from_email . '>'
        );

        // Send the email
        $sent = wp_mail($admin_email, $subject, $message, $headers);

        // For debugging, you can temporarily enable this to log the result:
        // error_log('FAQ notification email result: ' . ($sent ? 'SUCCESS' : 'FAILED'));
        // error_log('To: ' . $admin_email . ' | Subject: ' . $subject);

        // Reset content type to avoid conflicts
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });

        return $sent;
    }
}