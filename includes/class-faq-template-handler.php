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
        // Register separate shortcodes for form and list
        add_shortcode('FAQ_FORM', array(__CLASS__, 'display_submission_form'));
        add_shortcode('FAQ_LIST', array(__CLASS__, 'display_faq_list'));
    }
    
    /**
     * Display the FAQ submission form (form only)
     */
    public static function display_submission_form($atts) {
        $atts = shortcode_atts(array(
            'title' => '', // Empty default - no title by default
        ), $atts);

        ob_start();
        ?>
        <div class="faq-submission-form">
            <?php if (!empty($atts['title'])): ?>
                <h2><?php echo esc_html($atts['title']); ?></h2>
            <?php endif; ?>

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
                    <label for="faq_full_name">Full Name:</label>
                    <input type="text" id="faq_full_name" name="faq_full_name" value="<?php echo isset($_POST['faq_full_name']) ? esc_attr($_POST['faq_full_name']) : ''; ?>" required />
                </p>
                <p class="faq-form-field">
                    <label for="faq_email">Email Address:</label>
                    <input type="email" id="faq_email" name="faq_email" value="<?php echo isset($_POST['faq_email']) ? esc_attr($_POST['faq_email']) : ''; ?>" required />
                </p>

                <!-- Honeypot field for bot detection - should remain empty -->
                <p class="faq-form-field honeypot">
                    <label for="faq_company">Company:</label>
                    <input type="text" id="faq_company" name="faq_company" value="" autocomplete="off" />
                </p>

                <p class="faq-form-field">
                    <label for="faq_question">Question:</label>
                    <textarea id="faq_question" name="faq_question" rows="5" required><?php echo isset($_POST['faq_question']) ? esc_textarea($_POST['faq_question']) : ''; ?></textarea>
                </p>

                <?php
                // Check if reCAPTCHA is enabled
                if (class_exists('FAQ_Settings')) {
                    $settings = FAQ_Settings::get_settings();
                    $recaptcha_enabled = !empty($settings['recaptcha_enabled']);
                    $recaptcha_site_key = $settings['recaptcha_site_key'];
                } else {
                    $recaptcha_enabled = false;
                    $recaptcha_site_key = '';
                }

                if ($recaptcha_enabled && !empty($recaptcha_site_key)): ?>
                    <div class="faq-form-field">
                        <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($recaptcha_site_key); ?>"></div>
                    </div>
                <?php endif; ?>

                <p class="faq-form-submit">
                    <input type="hidden" name="faq_nonce" value="<?php echo wp_create_nonce('faq_nonce'); ?>" />
                    <input type="hidden" name="faq_submit" value="1" />
                    <input type="submit" id="faq_submit_button" value="Submit Question" />
                </p>
            </form>

            <div id="faq-result"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Display the FAQ list (list only)
     */
    public static function display_faq_list($atts) {
        $atts = shortcode_atts(array(
            'title' => '', // Empty default - no title by default
            'posts_per_page' => 25,
            'display_all' => false, // New parameter to display all FAQs without pagination
        ), $atts);

        // Convert display_all to boolean if it's a string
        $display_all = filter_var($atts['display_all'], FILTER_VALIDATE_BOOLEAN);

        ob_start();
        ?>
        <div id="faqs" class="faq-all-listings">
            <?php if (!empty($atts['title'])): ?>
                <h2><?php echo esc_html($atts['title']); ?></h2>
            <?php endif; ?>
            <div id="faq-list-container">
                <?php 
                if ($display_all) {
                    echo self::get_all_faqs();
                } else {
                    echo self::get_paginated_faq_list($atts['posts_per_page'], 1);
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get all published FAQs without pagination
     */
    public static function get_all_faqs() {
        // Query for all published Questions Answered posts
        $faqs = get_posts(array(
            'post_type' => 'questions-answered',
            'post_status' => 'publish',
            'posts_per_page' => -1, // Get all posts
            'orderby' => 'post_date',
            'order' => 'ASC'
        ));

        if (empty($faqs)) {
            return '<p>No FAQs found.</p>';
        }

        $output = '<ol class="faq-list-ol">';
        foreach ($faqs as $faq) {
            $title = $faq->post_title;
            $truncated_title = self::truncate_title($title, 22);
            $faq_url = get_permalink($faq->ID);

            $output .= '<li><a href="' . esc_url($faq_url) . '" title="'. $title .'">' . esc_html($truncated_title) . '</a></li>';
        }
        $output .= '</ol>';

        return $output;
    }

    /**
     * Get a paginated list of published Questions Answered with truncated titles
     */
    public static function get_paginated_faq_list($posts_per_page = 25, $page = 1) {
        $offset = ($page - 1) * $posts_per_page;

        // Query for published Questions Answered posts with pagination
        $faqs = get_posts(array(
            'post_type' => 'questions-answered',
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'offset' => $offset,
            'orderby' => 'post_date',
            'order' => 'ASC' // Changed from DESC to ASC for oldest to newest
        ));

        // Count total published Questions Answered for pagination
        $total_faqs = wp_count_posts('questions-answered');
        $total_published = $total_faqs->publish;
        $total_pages = ceil($total_published / $posts_per_page);

        if (empty($faqs)) {
            return '<p>No FAQs found.</p>';
        }

        $output = '<ol class="faq-list-ol">';
        foreach ($faqs as $faq) {
            $title = $faq->post_title;
            $truncated_title = self::truncate_title($title, 22);
            $faq_url = get_permalink($faq->ID);

            $output .= '<li><a href="' . esc_url($faq_url) . '" title="'. $title .'">' . esc_html($truncated_title) . '</a></li>';
        }
        $output .= '</ol>';

        // Add pagination controls
        $output .= self::get_pagination_controls($page, $total_pages, $posts_per_page);

        return $output;
    }

    /**
     * Generate pagination controls
     */
    private static function get_pagination_controls($current_page, $total_pages, $posts_per_page) {
        if ($total_pages <= 1) {
            return '';
        }

        $output = '<div class="faq-pagination">';
        $output .= '<nav aria-label="FAQ pagination">';
        $output .= '<ul class="faq-pagination-list">';

        // Previous button
        if ($current_page > 1) {
            $output .= '<li><a href="#" class="faq-page-link" data-page="' . ($current_page - 1) . '" data-posts-per-page="' . $posts_per_page . '">Previous</a></li>';
        }

        // Page links
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);

        if ($start_page > 1) {
            $output .= '<li><a href="#" class="faq-page-link" data-page="1" data-posts-per-page="' . $posts_per_page . '">1</a></li>';
            if ($start_page > 2) {
                $output .= '<li><span class="pagination-ellipsis">...</span></li>';
            }
        }

        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $current_page) {
                $output .= '<li><span class="current-page">' . $i . '</span></li>';
            } else {
                $output .= '<li><a href="#" class="faq-page-link" data-page="' . $i . '" data-posts-per-page="' . $posts_per_page . '">' . $i . '</a></li>';
            }
        }

        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                $output .= '<li><span class="pagination-ellipsis">...</span></li>';
            }
            $output .= '<li><a href="#" class="faq-page-link" data-page="' . $total_pages . '" data-posts-per-page="' . $posts_per_page . '">' . $total_pages . '</a></li>';
        }

        // Next button
        if ($current_page < $total_pages) {
            $output .= '<li><a href="#" class="faq-page-link" data-page="' . ($current_page + 1) . '" data-posts-per-page="' . $posts_per_page . '">Next</a></li>';
        }

        $output .= '</ul>';
        $output .= '</nav>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Truncate title to specified number of words with ellipsis
     */
    private static function truncate_title($title, $max_words = 15) {
        $words = explode(' ', $title);
        if (count($words) > $max_words) {
            return implode(' ', array_slice($words, 0, $max_words)) . '...';
        }
        return $title;
    }
}
