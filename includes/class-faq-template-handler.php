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
            'posts_per_page' => 25,
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

        <!-- Display all FAQ links with truncated titles outside the form -->
        <div class="faq-all-listings">
            <h3><?php _e('All FAQs', 'faq-post-create'); ?></h3>
            <div id="faq-list-container">
                <?php echo self::get_paginated_faq_list($atts['posts_per_page'], 1); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get a paginated list of published FAQs with truncated titles
     */
    public static function get_paginated_faq_list($posts_per_page = 25, $page = 1) {
        $offset = ($page - 1) * $posts_per_page;

        // Query for published FAQ posts with pagination
        $faqs = get_posts(array(
            'post_type' => 'faq',
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'offset' => $offset,
            'orderby' => 'post_date',
            'order' => 'DESC'
        ));

        // Count total published FAQs for pagination
        $total_faqs = wp_count_posts('faq');
        $total_published = $total_faqs->publish;
        $total_pages = ceil($total_published / $posts_per_page);

        if (empty($faqs)) {
            return '<p>' . __('No FAQs found.', 'faq-post-create') . '</p>';
        }

        $output = '<ul class="faq-list-ul">';
        foreach ($faqs as $faq) {
            $title = $faq->post_title;
            $truncated_title = self::truncate_title($title, 30);
            $faq_url = get_permalink($faq->ID);

            $output .= '<li><a href="' . esc_url($faq_url) . '">' . esc_html($truncated_title) . '</a></li>';
        }
        $output .= '</ul>';

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