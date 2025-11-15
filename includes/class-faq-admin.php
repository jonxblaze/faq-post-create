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
        add_shortcode('faq_submission_form', array('FAQ_Template_Handler', 'display_submission_form'));
    }
    
    /**
     * Add meta box for admin to enter FAQ response
     */
    public static function add_faq_response_meta_box() {
        add_meta_box(
            'faq-admin-response',
            __('Admin Response', 'faq-post-create'),
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
        echo '<p class="description">' . __('Enter your response to this FAQ here. This will be displayed separately from the original question.', 'faq-post-create') . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        // Also display the original submission details
        $full_name = get_post_meta($post->ID, '_faq_full_name', true);
        $original_question = $post->post_excerpt ? $post->post_excerpt : get_post_meta($post->ID, '_faq_original_question', true);
        
        echo '<div style="background-color:#f0f0f0; padding:15px; margin-top:15px; border-radius:4px;">';
        echo '<h4>' . __('Original Submission Details', 'faq-post-create') . '</h4>';
        if ($full_name) {
            echo '<p><strong>' . __('Name:', 'faq-post-create') . '</strong> ' . esc_html($full_name) . '</p>';
        }
        echo '<p><strong>' . __('Date:', 'faq-post-create') . '</strong> ' . get_the_date() . '</p>';
        if ($original_question) {
            echo '<p><strong>' . __('Question Title:', 'faq-post-create') . '</strong> ' . esc_html($post->post_title) . '</p>';
            echo '<p><strong>' . __('Question:', 'faq-post-create') . '</strong> ' . wp_kses_post($original_question) . '</p>';
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
}