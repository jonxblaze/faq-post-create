<?php
/**
 * Plugin Name: FAQ Post Creator
 * Description: Allows non-logged-in users to submit questions that become draft FAQ posts for admin approval.
 * Version: 1.0
 * Author: Jon Blaze
 * Author URI: https://jbwebdev.com
 * Plugin URI:
 * Copyright: 2025 Jon Blaze
 * Text Domain: faq-post-create
 * Domain Path: /languages
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * @package FAQPostCreator
 * 
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include the activation hook function
if (!function_exists('register_activation_hook')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

class FAQPostCreator {

    public function __construct() {
        // Initialize the plugin
        add_action('init', array($this, 'register_custom_post_type'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('init', array($this, 'handle_form_submission'));
        add_shortcode('faq_submission_form', array($this, 'display_submission_form'));
        add_action('wp_ajax_faq_submit_question', array($this, 'ajax_submit_question'));
        add_action('wp_ajax_nopriv_faq_submit_question', array($this, 'ajax_submit_question'));

        // Initialize session if not already started
        add_action('init', array($this, 'start_session'), 1);

        // Flush rewrite rules on init to ensure proper URLs
        add_action('init', array($this, 'check_flush_rewrite_rules'), 20);
        
        // Hook into single template for faq post type
        add_filter('single_template', array($this, 'faq_single_template'));
        
        // Add meta box for admin response
        add_action('add_meta_boxes', array($this, 'add_faq_response_meta_box'));
        add_action('save_post', array($this, 'save_faq_response_meta_box'));
    }

    /**
     * Start session if not already started
     */
    public function start_session() {
        if (!session_id()) {
            session_start();
        }
    }

    /**
     * Check if we need to flush rewrite rules
     */
    public function check_flush_rewrite_rules() {
        $plugin_version = get_option('faq_post_creator_version');
        $current_version = '1.0';

        if (!$plugin_version || $plugin_version !== $current_version) {
            flush_rewrite_rules();
            update_option('faq_post_creator_version', $current_version);
        }
    }

    /**
     * Static method for plugin activation
     */
    public static function activate_plugin() {
        // Register the post type to ensure rewrite rules are properly set
        $instance = new self();
        $instance->register_custom_post_type();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set version option
        update_option('faq_post_creator_version', '1.0');
    }
    
    /**
     * Add meta box for admin to enter FAQ response
     */
    public function add_faq_response_meta_box() {
        add_meta_box(
            'faq-admin-response',
            __('Admin Response', 'faq-post-create'),
            array($this, 'faq_response_meta_box_callback'),
            'faq',
            'normal',
            'high'
        );
    }
    
    /**
     * Callback function for the FAQ response meta box
     */
    public function faq_response_meta_box_callback($post) {
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
        $email = get_post_meta($post->ID, '_faq_email', true);
        $original_question = $post->post_excerpt ? $post->post_excerpt : get_post_meta($post->ID, '_faq_original_question', true);
        
        echo '<div style="background-color:#f0f0f0; padding:15px; margin-top:15px; border-radius:4px;">';
        echo '<h4>' . __('Original Submission Details', 'faq-post-create') . '</h4>';
        if ($full_name) {
            echo '<p><strong>' . __('Name:', 'faq-post-create') . '</strong> ' . esc_html($full_name) . '</p>';
        }
        if ($original_question) {
            echo '<p><strong>' . __('Question:', 'faq-post-create') . '</strong> ' . wp_kses_post($original_question) . '</p>';
        }
        echo '</div>';
    }
    
    /**
     * Save the FAQ response meta box data
     */
    public function save_faq_response_meta_box($post_id) {
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
     * Load custom template for single FAQ posts
     */
    public function faq_single_template($template) {
        if (is_singular('faq')) {
            // Create the custom output instead of loading a separate template file
            $this->render_faq_template();
            exit;
        }
        return $template;
    }
    
    /**
     * Render the custom FAQ template
     */
    public function render_faq_template() {
        global $post;
        
        // Get the question details submitted by the user
        $full_name = get_post_meta($post->ID, '_faq_full_name', true);
        $email = get_post_meta($post->ID, '_faq_email', true);
        $original_question = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
        
        // Start output buffering to integrate with theme
        $theme_template = locate_template(array('single.php', 'index.php'));
        
        // Apply the theme's wrapper but with our custom content
        if ($theme_template) {
            // Store content in a variable and use WordPress functions to render within theme
            ob_start();
            
            // Get header of active theme
            get_header();
            
            ?>
            
            <div id="primary" class="content-area">
                <main id="main" class="site-main" role="main">
                    
                    <article id="post-<?php echo $post->ID; ?>" <?php post_class(); ?>>
                        <header class="entry-header">
                            <h1 class="entry-title"><?php echo esc_html($post->post_title); ?></h1>
                        </header>
                        
                        <div class="entry-content">
                            <!-- Display the original FAQ submission -->
                            <div class="faq-submission-section">
                                <h2><?php _e('Question Details', 'faq-post-create'); ?></h2>
                                
                                <div class="faq-submitter-info">
                                    <?php if ($full_name): ?>
                                        <p><strong><?php _e('Name:', 'faq-post-create'); ?></strong> <?php echo esc_html($full_name); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="faq-original-question">
                                    <h3><?php echo esc_html($post->post_title); ?></h3>
                                    <div class="question-content">
                                        <?php echo wp_kses_post($post->post_excerpt); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Admin response section with light background -->
                            <div class="faq-response-section">
                                <h3><?php _e('Response', 'faq-post-create'); ?></h3>
                                <?php 
                                // Use a custom meta field for admin response to ensure separation
                                $admin_response = get_post_meta($post->ID, '_faq_admin_response', true);
                                
                                // Only show admin response if there's content in the custom field
                                if (!empty(trim($admin_response))): ?>
                                    <div class="faq-admin-response">
                                        <?php echo wp_kses_post($admin_response); ?>
                                    </div>
                                <?php else: ?>
                                    <p class="no-response-yet">
                                        <?php _e('This question is awaiting a response from our team.', 'faq-post-create'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                    
                </main>
            </div>
            
            <style>
                .faq-submission-section {
                    margin-bottom: 2rem;
                    padding: 1.5rem;
                    border: 1px solid #eee;
                    border-radius: 4px;
                }
                
                .faq-submitter-info {
                    background-color: #f9f9f9;
                    padding: 1rem;
                    margin-bottom: 1rem;
                    border-radius: 4px;
                }
                
                .faq-original-question {
                    margin-top: 1rem;
                }
                
                .faq-original-question .question-content {
                    background-color: #f0f8ff;
                    padding: 1rem;
                    border-left: 3px solid #2196F3;
                    border-radius: 0 4px 4px 0;
                }
                
                .faq-response-section {
                    background-color: #f9f9f9;
                    padding: 1.5rem;
                    border-radius: 4px;
                    border: 1px solid #eee;
                    margin-top: 2rem;
                }
                
                .faq-response-section h2 {
                    margin-top: 0;
                    color: #333;
                }
                
                .faq-admin-response {
                    margin-top: 1rem;
                    padding: 1rem;
                    background-color: white;
                    border-radius: 4px;
                    border-left: 3px solid #4CAF50;
                }
                
                .no-response-yet {
                    font-style: italic;
                    color: #666;
                }
            </style>
            
            <?php
            
            $custom_content = ob_get_clean();
            
            // Set up postdata for the theme
            setup_postdata($post);
            
            // Output the content within the theme context
            echo $custom_content;
            
            // Get footer of active theme
            get_footer();
        } else {
            // Fallback if no theme template is found
            $this->render_simple_faq_page($post, $full_name, $email, $original_question);
        }
    }
    
    /**
     * Simple fallback FAQ page rendering
     */
    private function render_simple_faq_page($post, $full_name, $email, $original_question) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($post->post_title); ?> - FAQ</title>
            <?php wp_head(); ?>
        </head>
        <body <?php body_class(); ?>>
            <div id="page">
                <div id="content">
                    <article id="post-<?php echo $post->ID; ?>" <?php post_class(); ?>>
                        <header class="entry-header">
                            <h1 class="entry-title"><?php echo esc_html($post->post_title); ?></h1>
                        </header>
                        
                        <div class="entry-content">
                            <!-- Display the original FAQ submission -->
                            <div class="faq-submission-section">
                                <h2>Question Details</h2>
                                
                                <div class="faq-submitter-info">
                                    <?php if ($full_name): ?>
                                        <p><strong>Name:</strong> <?php echo esc_html($full_name); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($email): ?>
                                        <p><strong>Email:</strong> <?php echo esc_html($email); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="faq-original-question">
                                    <h3>Question:</h3>
                                    <div class="question-content">
                                        <?php echo wp_kses_post($original_question); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Admin response section with light background -->
                            <div class="faq-response-section">
                                <h2>Response</h2>
                                <?php 
                                $admin_response = $post->post_content;
                                if (!empty(trim($admin_response)) && $admin_response !== $original_question): ?>
                                    <div class="faq-admin-response">
                                        <?php echo wp_kses_post($admin_response); ?>
                                    </div>
                                <?php else: ?>
                                    <p class="no-response-yet">
                                        This question is awaiting a response from our team.
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
            
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                }
                
                .faq-submission-section {
                    margin-bottom: 2rem;
                    padding: 1.5rem;
                    border: 1px solid #eee;
                    border-radius: 4px;
                }
                
                .faq-submitter-info {
                    background-color: #f9f9f9;
                    padding: 1rem;
                    margin-bottom: 1rem;
                    border-radius: 4px;
                }
                
                .faq-original-question {
                    margin-top: 1rem;
                }
                
                .faq-original-question .question-content {
                    background-color: #f0f8ff;
                    padding: 1rem;
                    border-left: 3px solid #2196F3;
                    border-radius: 0 4px 4px 0;
                }
                
                .faq-response-section {
                    background-color: #f9f9f9;
                    padding: 1.5rem;
                    border-radius: 4px;
                    border: 1px solid #eee;
                    margin-top: 2rem;
                }
                
                .faq-response-section h2 {
                    margin-top: 0;
                    color: #333;
                }
                
                .faq-admin-response {
                    margin-top: 1rem;
                    padding: 1rem;
                    background-color: white;
                    border-radius: 4px;
                    border-left: 3px solid #4CAF50;
                }
                
                .no-response-yet {
                    font-style: italic;
                    color: #666;
                }
            </style>
            
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    /**
     * Register the custom post type for FAQs
     */
    public function register_custom_post_type() {
        $args = array(
            'labels' => array(
                'name' => __('FAQs', 'faq-post-create'),
                'singular_name' => __('FAQ', 'faq-post-create'),
                'add_new' => __('Add New', 'faq-post-create'),
                'add_new_item' => __('Add New FAQ', 'faq-post-create'),
                'edit_item' => __('Edit FAQ', 'faq-post-create'),
                'new_item' => __('New FAQ', 'faq-post-create'),
                'view_item' => __('View FAQ', 'faq-post-create'),
                'search_items' => __('Search FAQs', 'faq-post-create'),
                'not_found' => __('No FAQs found', 'faq-post-create'),
                'not_found_in_trash' => __('No FAQs found in Trash', 'faq-post-create'),
            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'faqs', 'with_front' => false),
            'capability_type' => 'post',
            'has_archive' => true,
            'supports' => array('title', 'custom-fields'), // Removed editor to use our custom meta box
            'menu_position' => 5,
            'menu_icon' => 'dashicons-editor-help',
            'can_export' => true,
            'show_in_rest' => true, // Enable Gutenberg support
        );

        register_post_type('faq', $args);
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'faq-submission-script',
            plugin_dir_url(__FILE__) . 'assets/faq-submission.js',
            array('jquery'),
            '1.0',
            true
        );

        wp_enqueue_style(
            'faq-submission-style',
            plugin_dir_url(__FILE__) . 'assets/faq-submission.css',
            array(),
            '1.0'
        );

        // Localize script for AJAX
        wp_localize_script('faq-submission-script', 'faq_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('faq_nonce')
        ));
    }

    /**
     * Handle form submission via AJAX
     */
    public function ajax_submit_question() {
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
        $error_message = '';

        // Bot detection: If the honeypot field has a value, it's likely a bot
        if (!empty($company)) {
            wp_send_json_error(array('message' => __('Submission rejected. Please try again without filling the company field.', 'faq-post-create')));
            return;
        }

        // Validation
        if (empty($title)) {
            $error_message = __('Title is required.', 'faq-post-create');
        }

        if (empty($question)) {
            $error_message = __('Question is required.', 'faq-post-create');
        }

        if (empty($full_name)) {
            $error_message = __('Full Name is required.', 'faq-post-create');
        }

        if (empty($email)) {
            $error_message = __('Email is required.', 'faq-post-create');
        } elseif (!is_email($email)) {
            $error_message = __('Please enter a valid email address.', 'faq-post-create');
        }

        if (!empty($error_message)) {
            wp_send_json_error(array('message' => $error_message));
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
    public function handle_form_submission() {
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
            $error_message = '';

            // Bot detection: If the honeypot field has a value, it's likely a bot
            if (!empty($company)) {
                $_SESSION['faq_error'] = __('Submission rejected. Please try again without filling the company field.', 'faq-post-create');
                return;
            }

            // Validation
            if (empty($title)) {
                $error_message = __('Title is required.', 'faq-post-create');
            }

            if (empty($question)) {
                $error_message = __('Question is required.', 'faq-post-create');
            }

            if (empty($full_name)) {
                $error_message = __('Full Name is required.', 'faq-post-create');
            }

            if (empty($email)) {
                $error_message = __('Email is required.', 'faq-post-create');
            } elseif (!is_email($email)) {
                $error_message = __('Please enter a valid email address.', 'faq-post-create');
            }

            if (!empty($error_message)) {
                // Set error message in session or redirect with error
                $_SESSION['faq_error'] = $error_message;
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

    /**
     * Display the FAQ submission form
     */
    public function display_submission_form($atts) {
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

// Initialize the plugin
new FAQPostCreator();

// Activation hook to flush rewrite rules
register_activation_hook(__FILE__, array('FAQPostCreator', 'activate_plugin'));

// Deactivation hook to flush rewrite rules
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');