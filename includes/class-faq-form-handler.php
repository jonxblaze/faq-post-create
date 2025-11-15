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
    private static function validate_faq_submission($title, $question, $full_name, $email) {
        $errors = array();
        
        if (empty($title)) {
            $errors[] = __('Title is required.', 'faq-post-create');
        }

        if (empty($question)) {
            $errors[] = __('Question is required.', 'faq-post-create');
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

        // Rate limiting: Check if user submitted within the last 30 seconds
        $last_submission = isset($_SESSION['faq_last_submission']) ? $_SESSION['faq_last_submission'] : 0;
        $time_diff = time() - $last_submission;
        
        if ($time_diff < 30) {
            wp_send_json_error(array('message' => sprintf(__('Please wait %d seconds before submitting another question.', 'faq-post-create'), 30 - $time_diff)));
            return;
        }

        // Sanitize input
        $title = sanitize_text_field($_POST['faq_title']);
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
        $errors = self::validate_faq_submission($title, $question, $full_name, $email);
        
        if (!empty($errors)) {
            wp_send_json_error(array('message' => $errors[0])); // Return first error
        } else {
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
            // Rate limiting: Check if user submitted within the last 30 seconds
            $last_submission = isset($_SESSION['faq_last_submission']) ? $_SESSION['faq_last_submission'] : 0;
            $time_diff = time() - $last_submission;

            if ($time_diff < 30) {
                $_SESSION['faq_error'] = sprintf(__('Please wait %d seconds before submitting another question.', 'faq-post-create'), 30 - $time_diff);
                return;
            }

            $title = sanitize_text_field($_POST['faq_title']);
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
            $errors = self::validate_faq_submission($title, $question, $full_name, $email);
            
            if (!empty($errors)) {
                // Set error message in session or redirect with error
                $_SESSION['faq_error'] = $errors[0]; // Use first error
            } else {
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
                    
                    $_SESSION['faq_success'] = __('Your question has been submitted successfully. It will be reviewed by an administrator.', 'faq-post-create');
                    $_SESSION['faq_last_submission'] = time();
                } else {
                    $_SESSION['faq_error'] = __('There was an error submitting your question. Please try again.', 'faq-post-create');
                }
            }
        }
    }
}