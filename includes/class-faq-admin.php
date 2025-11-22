<?php
/**
 * FAQ Admin Handler
 * 
 * @package FAQ_Post_Create
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles admin functionality for FAQ posts
 */
class FAQ_Admin {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'add_faq_response_meta_box'));
        add_action('save_post', array(__CLASS__, 'save_faq_response_meta_box'));
        add_action('transition_post_status', array(__CLASS__, 'handle_faq_publish'), 10, 3);
        add_action('wp_after_insert_post', array(__CLASS__, 'handle_faq_save'), 10, 4);
        add_shortcode('FAQ_FORM', array('FAQ_Template_Handler', 'display_submission_form'));
    }
    
    /**
     * Add meta box for admin to enter FAQ response
     */
    public static function add_faq_response_meta_box() {
        add_meta_box(
            'faq-admin-response',
            'Admin Response',
            array(__CLASS__, 'faq_response_meta_box_callback'),
            'faq',
            'normal',
            'high'
        );
    }

    /**
     * Callback function for the FAQ response meta box
     */
    public static function faq_response_meta_box_callback($post) {
        // Add an nonce field so we can check for it later
        wp_nonce_field('faq_save_response_meta_box', 'faq_response_meta_box_nonce');

        // Get the current response value
        $response = get_post_meta($post->ID, '_faq_admin_response', true);

        // Display the form
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<td colspan="2">';
        echo '<textarea name="faq_admin_response" rows="10" style="width:100%;">' . esc_textarea($response) . '</textarea>';
        echo '<p class="description">Enter your response to this FAQ here. This will be displayed separately from the original question.</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        // Also display the original submission details
        $original_question = $post->post_excerpt ? $post->post_excerpt : get_post_meta($post->ID, '_faq_original_question', true);

        echo '<div style="background-color:#f0f0f0; padding:15px; margin-top:15px; border-radius:4px;">';
        echo '<h3>User Submission Details</h3>';
        echo '<p><strong>Date:</strong> ' . get_the_date() . '</p>';
        if ($original_question) {
            echo '<p><strong>Question:</strong> ' . esc_html($post->post_title) . '</p>';
        }
        echo '</div>';
    }
    
    /**
     * Save the FAQ response meta box data
     */
    public static function save_faq_response_meta_box($post_id) {
        // Check if our nonce is set.
        if (!isset($_POST['faq_response_meta_box_nonce'])) {
            return;
        }

        // Verify that the nonce is valid.
        if (!wp_verify_nonce($_POST['faq_response_meta_box_nonce'], 'faq_save_response_meta_box')) {
            return;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions.
        if (isset($_POST['post_type']) && 'faq' === $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id)) {
                return;
            }
        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        }

        // Sanitize user input.
        $response = isset($_POST['faq_admin_response']) ? sanitize_textarea_field($_POST['faq_admin_response']) : '';

        // Update the meta field in the database.
        update_post_meta($post_id, '_faq_admin_response', $response);
    }

    /**
     * Handle FAQ post save and check if we need to send notification
     */
    public static function handle_faq_save($post_id, $post, $update, $post_before) {
        // Only process FAQ posts
        if ($post->post_type !== 'faq') {
            return;
        }

        // Only process if this is an update (not a new post)
        if (!$update) {
            return;
        }

        // Check if post was just published and has admin response
        if ($post->post_status === 'publish' && $post_before->post_status !== 'publish') {
            // Use a small delay to ensure all meta data is saved
            add_action('shutdown', function() use ($post_id) {
                $admin_response = get_post_meta($post_id, '_faq_admin_response', true);
                $submitter_email = get_post_meta($post_id, '_faq_email', true);
                
                error_log('FAQ Post Creator: shutdown hook - Post ID: ' . $post_id);
                error_log('FAQ Post Creator: Admin response in shutdown: ' . (!empty($admin_response) ? 'YES' : 'NO'));
                error_log('FAQ Post Creator: Submitter email in shutdown: ' . (!empty($submitter_email) ? 'YES' : 'NO'));
                
                if (!empty($admin_response) && !empty($submitter_email)) {
                    error_log('FAQ Post Creator: Attempting to send submitter notification email from shutdown');
                    $result = self::send_submitter_notification_email($post_id);
                    error_log('FAQ Post Creator: Email send result from shutdown: ' . ($result ? 'SUCCESS' : 'FAILED'));
                }
            });
        }
    }

    /**
     * Handle FAQ post publishing and send notification to submitter
     */
    public static function handle_faq_publish($new_status, $old_status, $post) {
        // Only process FAQ posts
        if ($post->post_type !== 'faq') {
            return;
        }

        // Only send notification when transitioning to publish status
        if ($new_status === 'publish' && $old_status !== 'publish') {
            // Add debug logging
            error_log('FAQ Post Creator: Post transition detected - ID: ' . $post->ID . ', Old status: ' . $old_status . ', New status: ' . $new_status);
            
            // Check if admin response exists
            $admin_response = get_post_meta($post->ID, '_faq_admin_response', true);
            $submitter_email = get_post_meta($post->ID, '_faq_email', true);
            
            error_log('FAQ Post Creator: Admin response exists: ' . (!empty($admin_response) ? 'YES' : 'NO'));
            error_log('FAQ Post Creator: Submitter email exists: ' . (!empty($submitter_email) ? 'YES' : 'NO'));
            
            if (!empty($admin_response) && !empty($submitter_email)) {
                error_log('FAQ Post Creator: Attempting to send submitter notification email');
                $result = self::send_submitter_notification_email($post->ID);
                error_log('FAQ Post Creator: Email send result: ' . ($result ? 'SUCCESS' : 'FAILED'));
            } else {
                error_log('FAQ Post Creator: Cannot send email - missing admin response or submitter email');
            }
        }
    }

    /**
     * Send notification email to FAQ submitter when their question is answered
     */
    private static function send_submitter_notification_email($post_id) {
        // Get submitter details
        $submitter_email = get_post_meta($post_id, '_faq_email', true);
        $submitter_name = get_post_meta($post_id, '_faq_full_name', true);
        $original_question = get_post_meta($post_id, '_faq_original_question', true);
        $admin_response = get_post_meta($post_id, '_faq_admin_response', true);

        // Check if we have the required information
        if (empty($submitter_email) || empty($admin_response)) {
            return false;
        }

        // Get FAQ post details
        $faq_title = get_the_title($post_id);
        $faq_url = get_permalink($post_id);

        // Get site name
        $site_name = get_bloginfo('name');

        // Set up email subject
        $subject = sprintf('[%s] Your question has been answered: ', $site_name) . wp_trim_words($faq_title, 10, '...');

        // Set up HTML email message
        $message = sprintf(
            "<html><body>" .
            "<p>Hello %s,</p>" .
            "<p>Thank you for submitting your question to <strong>%s</strong>. Our team has reviewed your question and provided a response:</p>" .
            "<table border='0'>" .
            "<tr><td><strong>Your Question:</strong> %s</td></tr>" .
            "<tr><td><strong>Our Response:</strong> %s</td></tr>" .
            "<tr><td><strong>Response Date:</strong> %s</td></tr>" .
            "</table>" .
            "<p>You can view your question and our response here:</p>" .
            "<p><a href=\"%s\" style=\"display: inline-block; padding: 10px 15px; background-color: #0073aa; color: white; text-decoration: none; border-radius: 4px;\">View Your Question</a></p>" .
            "<p>If you have any further questions, please don't hesitate to reach out.</p>" .
            "<p>Best regards,<br />%s</p>" .
            "</body></html>",
            esc_html($submitter_name),
            esc_html($site_name),
            esc_html($original_question ?: $faq_title),
            nl2br(esc_html($admin_response)),
            date('F j, Y g:i A'),
            esc_url($faq_url),
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
        $sent = wp_mail($submitter_email, $subject, $message, $headers);

        // For debugging, you can temporarily enable this to log the result:
        // error_log('FAQ submitter notification email result: ' . ($sent ? 'SUCCESS' : 'FAILED'));
        // error_log('To: ' . $submitter_email . ' | Subject: ' . $subject);

        // Reset content type to avoid conflicts
        remove_filter('wp_mail_content_type', function() { return 'text/html'; });

        return $sent;
    }
}
