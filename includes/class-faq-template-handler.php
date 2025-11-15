<?php
/**
 * FAQ Template Handler
 * 
 * @package FAQ_Post_Create
 * @subpackage Template
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles FAQ template rendering and display
 */
class FAQ_Template_Handler {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // Hooks are handled in FAQ_Post_Type class for single template
    }
    
    /**
     * Display the FAQ submission form
     */
    public static function display_submission_form($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Submit a Question', 'faq-post-create'),
        ), $atts);

        ob_start();
        ?>
        <div class="faq-submission-form">
            <h2><?php echo esc_html($atts['title']); ?></h2>
            
            <?php
            // Display success or error messages
            if (isset($_SESSION['faq_success'])) {
                echo '<div class="faq-message success">' . esc_html($_SESSION['faq_success']) . '</div>';
                unset($_SESSION['faq_success']);
            }
            
            if (isset($_SESSION['faq_error'])) {
                echo '<div class="faq-message error">' . esc_html($_SESSION['faq_error']) . '</div>';
                unset($_SESSION['faq_error']);
            }
            ?>
            
            <form id="faq-submission" method="post" action="">
                <p class="faq-form-field">
                    <label for="faq_full_name"><?php _e('Full Name:', 'faq-post-create'); ?></label><br>
                    <input type="text" id="faq_full_name" name="faq_full_name" value="<?php echo isset($_POST['faq_full_name']) ? esc_attr($_POST['faq_full_name']) : ''; ?>" required />
                </p>
                <p class="faq-form-field">
                    <label for="faq_email"><?php _e('Email Address:', 'faq-post-create'); ?></label><br>
                    <input type="email" id="faq_email" name="faq_email" value="<?php echo isset($_POST['faq_email']) ? esc_attr($_POST['faq_email']) : ''; ?>" required />
                </p>
                <p class="faq-form-field">
                    <label for="faq_title"><?php _e('Title:', 'faq-post-create'); ?></label><br>
                    <input type="text" id="faq_title" name="faq_title" value="<?php echo isset($_POST['faq_title']) ? esc_attr($_POST['faq_title']) : ''; ?>" required />
                </p>
                
                <!-- Honeypot field for bot detection - should remain empty -->
                <p class="faq-form-field honeypot">
                    <label for="faq_company"><?php _e('Company:', 'faq-post-create'); ?></label><br>
                    <input type="text" id="faq_company" name="faq_company" value="" autocomplete="off" />
                </p>
                
                <p class="faq-form-field">
                    <label for="faq_question"><?php _e('Question:', 'faq-post-create'); ?></label><br>
                    <textarea id="faq_question" name="faq_question" rows="5" required><?php echo isset($_POST['faq_question']) ? esc_textarea($_POST['faq_question']) : ''; ?></textarea>
                </p>

                <p class="faq-form-submit">
                    <input type="hidden" name="faq_nonce" value="<?php echo wp_create_nonce('faq_nonce'); ?>" />
                    <input type="hidden" name="faq_submit" value="1" />
                    <input type="submit" id="faq_submit_button" value="<?php _e('Submit Question', 'faq-post-create'); ?>" />
                </p>
            </form>

            <div id="faq-result"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}