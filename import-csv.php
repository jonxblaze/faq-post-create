<?php
/**
 * CSV Import Script for FAQ Post Creator Plugin
 * 
 * This script imports FAQs from a CSV file into the custom post type
 * used by the FAQ Post Creator plugin.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate a post slug limited to 10 words
 *
 * @param string $title The post title
 * @return string The limited slug
 */
function generate_limited_slug($title) {
    // Sanitize the title first
    $sanitized_title = sanitize_title($title);

    // Break the slug into words using hyphens as separators
    $words = explode('-', $sanitized_title);

    // Limit to 10 words maximum
    $limited_words = array_slice($words, 0, 10);

    // Remove empty elements that might occur with multiple consecutive hyphens
    $limited_words = array_filter($limited_words);

    // Rejoin the words with hyphens
    $limited_slug = implode('-', $limited_words);

    // Ensure the slug is not empty
    if (empty($limited_slug)) {
        $limited_slug = 'faq-' . time(); // Fallback to timestamp if slug is empty
    }

    // Make sure the slug is unique by checking against existing slugs
    $original_slug = $limited_slug;
    $counter = 1;

    while (slug_exists($limited_slug)) {
        $limited_slug = $original_slug . '-' . $counter;
        $counter++;
    }

    return $limited_slug;
}

/**
 * Check if a post slug exists for FAQ posts
 *
 * @param string $slug The slug to check
 * @return bool Whether the slug exists
 */
function slug_exists($slug) {
    // Use WordPress's built-in function to check for a post by name
    // post_exists function only checks for title, so we need to use a direct query
    global $wpdb;

    $post_id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = 'questions-answered' AND post_status IN ('publish', 'draft', 'pending', 'private')",
        $slug
    ));

    return !empty($post_id);
}

/**
 * Import FAQs from CSV file
 *
 * @param string $csv_file Path to the CSV file
 * @return array Results of the import process
 */
function import_faqs_from_csv($csv_file) {
    global $wpdb;
    
    if (!current_user_can('manage_options')) {
        return array('error' => 'Insufficient permissions to import FAQs.');
    }
    
    if (!file_exists($csv_file)) {
        return array('error' => 'CSV file does not exist: ' . $csv_file);
    }
    
    $handle = fopen($csv_file, 'r');
    if (!$handle) {
        return array('error' => 'Could not open CSV file for reading.');
    }
    
    // Read the header row
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return array('error' => 'Could not read header from CSV file.');
    }
    
    $imported_count = 0;
    $errors = array();
    $skipped = 0;
    
    // Skip the header row and process each data row
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) < count($header)) {
            $skipped++;
            continue; // Skip incomplete rows
        }
        
        // Map CSV columns to array with named keys
        $data = array();
        foreach ($header as $index => $column_name) {
            $data[$column_name] = isset($row[$index]) ? trim($row[$index]) : '';
        }
        
        // Validate required fields
        if (empty($data['post_title']) || empty($data['post_content'])) {
            $skipped++;
            continue;
        }
        
        // Parse the date from the CSV file
        $post_date = !empty($data['created']) ? date('Y-m-d H:i:s', strtotime($data['created'])) : current_time('mysql');
        
        // Use the CSV post_title as the FAQ question and title
        $question = $data['post_title']; // The original post_title from CSV will be used as the question

        // Create the Questions Answered post
        $post_id = wp_insert_post(array(
            'post_title' => $question, // Use question as title
            'post_content' => $data['post_content'],
            'post_excerpt' => $question, // Store question in excerpt
            'post_status' => 'publish', // Since these are existing FAQs, publish them
            'post_type' => 'questions-answered',
            'post_date' => $post_date,
            'post_date_gmt' => get_gmt_from_date($post_date),
            'post_author' => get_current_user_id(), // Assign to current admin user
        ));
        
        if (is_wp_error($post_id)) {
            $errors[] = "Failed to create post with title: " . $data['post_title'] . " - Error: " . $post_id->get_error_message();
            continue;
        }

        // Store the original question in the custom meta field
        update_post_meta($post_id, '_faq_original_question', $data['post_content']);

        // Store the admin response (since post_content is the answer, this would be the response)
        update_post_meta($post_id, '_faq_admin_response', $data['post_content']);

        // Store the email if available
        if (!empty($data['_faq_email'])) {
            update_post_meta($post_id, '_faq_email', $data['_faq_email']);

            // For the name, we'll use a generic name since it's not in the CSV
            update_post_meta($post_id, '_faq_full_name', 'Imported FAQ');
        } else {
            // Set a generic name if no email available
            update_post_meta($post_id, '_faq_full_name', 'Imported FAQ');
        }

        $imported_count++;
    }

    // Flush rewrite rules after import to ensure all new slugs are recognized
    if ($imported_count > 0) {
        global $wp_rewrite;
        $wp_rewrite->flush_rules(false);
    }

    fclose($handle);

    return array(
        'success' => true,
        'imported' => $imported_count,
        'skipped' => $skipped,
        'errors' => $errors
    );
}

/**
 * Admin page to handle CSV import
 */
// Hook into post saving to ensure FAQ slugs are limited
add_filter('wp_insert_post_data', 'limit_faq_slug_length', 10, 2);

function limit_faq_slug_length($data, $postarr) {
    // Only process Questions Answered posts
    if ($postarr['post_type'] !== 'questions-answered') {
        return $data;
    }

    // Only modify if this is a new post or if the slug is being set
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $data;
    }

    // Check if slug has more than 10 words
    if (!empty($data['post_name'])) {
        $slug_parts = explode('-', $data['post_name']);
        if (count($slug_parts) > 10) {
            // Generate a limited slug from the title
            $limited_slug = generate_limited_slug($data['post_title']);
            $data['post_name'] = $limited_slug;
        }
    } elseif (!empty($data['post_title'])) {
        // If no slug is set, we'll generate one anyway based on the title
        $data['post_name'] = generate_limited_slug($data['post_title']);
    }

    return $data;
}

// Hook into query parsing to handle long slugs before they cause a 404
add_action('parse_request', 'handle_long_faq_slugs');

function handle_long_faq_slugs($wp) {
    // Only process if it's a Questions Answered single request with a potentially long slug
    if (isset($wp->query_vars['questions-answered']) && !empty($wp->query_vars['questions-answered'])) {
        $faq_slug = $wp->query_vars['questions-answered'];
        $slug_parts = explode('-', $faq_slug);

        if (count($slug_parts) > 10) {
            // This is a long slug that should have been limited, try to find by title instead
            global $wpdb;

            // First, try to find the actual post with the truncated slug
            $shortened_slug = implode('-', array_slice($slug_parts, 0, 10));
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE post_type = 'questions-answered' AND post_status IN ('publish', 'draft', 'pending', 'private') AND post_name LIKE %s",
                $wpdb->esc_like($shortened_slug) . '%'
            ));

            // If the above doesn't work, try with the first few words
            if (!$post_id) {
                for ($i = 9; $i >= 5; $i--) {
                    $test_slug = implode('-', array_slice($slug_parts, 0, $i));
                    $post_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT ID FROM $wpdb->posts WHERE post_type = 'questions-answered' AND post_status IN ('publish', 'draft', 'pending', 'private') AND post_name LIKE %s",
                        $wpdb->esc_like($test_slug) . '%'
                    ));
                    if ($post_id) {
                        break;
                    }
                }
            }

            if ($post_id) {
                // Found the post with a shortened version of the slug
                $post = get_post($post_id);
                if ($post) {
                    // Set the correct query variable
                    $wp->query_vars['questions-answered'] = $post->post_name;
                    $wp->query_vars['name'] = $post->post_name;
                    // Remove other conflicting query vars
                    unset($wp->query_vars['pagename']);
                    $wp->query_vars['post_type'] = 'questions-answered';

                    // Redirect to the correct URL to prevent the long slug from being used again
                    if (!is_admin()) {
                        $correct_url = get_permalink($post_id);
                        if ($correct_url) {
                            wp_redirect($correct_url, 301);
                            exit;
                        }
                    }
                }
            } else {
                // If the long slug doesn't match any post, maybe it was truncated and we need to try harder
                // Try to find the post by looking at all Questions Answered posts and checking if the current slug
                // begins with the same first few words as any existing slug
                $faqs = $wpdb->get_results("SELECT ID, post_name FROM $wpdb->posts WHERE post_type = 'questions-answered' AND post_status IN ('publish', 'draft', 'pending', 'private')");

                foreach ($faqs as $faq) {
                    $existing_slug_parts = explode('-', $faq->post_name);
                    $request_slug_prefix = implode('-', array_slice($slug_parts, 0, count($existing_slug_parts)));

                    // Check if the beginning of the requested slug matches an existing post name
                    if ($request_slug_prefix === $faq->post_name) {
                        $wp->query_vars['questions-answered'] = $faq->post_name;
                        $wp->query_vars['name'] = $faq->post_name;
                        unset($wp->query_vars['pagename']);
                        $wp->query_vars['post_type'] = 'questions-answered';

                        // Redirect to the correct URL
                        if (!is_admin()) {
                            $correct_url = get_permalink($faq->ID);
                            if ($correct_url) {
                                wp_redirect($correct_url, 301);
                                exit;
                            }
                        }
                        break;
                    }
                }
            }
        }
    }

    return $wp;
}

// Handle 404s for FAQ posts with long slugs
add_action('template_redirect', 'handle_faq_404_with_long_slug', 1);

function handle_faq_404_with_long_slug() {
    global $wp_query;

    // Only proceed if we have a 404 error
    if (is_404() && !is_admin()) {
        // Get the requested URL path
        $request_uri = $_SERVER['REQUEST_URI'];
        $path_info = parse_url($request_uri, PHP_URL_PATH);
        $path_parts = array_filter(explode('/', trim($path_info, '/')));

        // Check if the request is for a Questions Answered under the questions-answered base
        $faq_base_index = array_search('questions-answered', $path_parts);
        if ($faq_base_index !== false && isset($path_parts[$faq_base_index + 1])) {
            $faq_slug = $path_parts[$faq_base_index + 1];
            $slug_parts = explode('-', $faq_slug);

            if (count($slug_parts) > 10) {
                // This might be a request for a FAQ with a long slug that was truncated
                global $wpdb;

                // Try to find the correct post as before
                $shortened_slug = implode('-', array_slice($slug_parts, 0, 10));
                $post_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM $wpdb->posts WHERE post_type = 'questions-answered' AND post_status IN ('publish', 'draft', 'pending', 'private') AND post_name LIKE %s",
                    $wpdb->esc_like($shortened_slug) . '%'
                ));

                // If the above doesn't work, try with the first few words
                if (!$post_id) {
                    for ($i = 9; $i >= 5; $i--) {
                        $test_slug = implode('-', array_slice($slug_parts, 0, $i));
                        $post_id = $wpdb->get_var($wpdb->prepare(
                            "SELECT ID FROM $wpdb->posts WHERE post_type = 'questions-answered' AND post_status IN ('publish', 'draft', 'pending', 'private') AND post_name LIKE %s",
                            $wpdb->esc_like($test_slug) . '%'
                        ));
                        if ($post_id) {
                            break;
                        }
                    }
                }

                if ($post_id) {
                    // Found the post - redirect to correct URL
                    $correct_url = get_permalink($post_id);
                    if ($correct_url) {
                        wp_redirect($correct_url, 301);
                        exit;
                    }
                }
            }
        }
    }
}

/**
 * Function to fix existing FAQ posts with long slugs
 */
function fix_faq_long_slugs() {
    // Query for Questions Answered posts with long slugs
    $args = array(
        'post_type' => 'questions-answered',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'draft', 'pending'),
        'no_found_rows' => 1,
    );

    $faqs = get_posts($args);

    $fixed_count = 0;

    foreach ($faqs as $faq) {
        // Get the current slug
        $current_slug = $faq->post_name;

        // Check if slug has more than 10 words
        $slug_parts = explode('-', $current_slug);
        if (count($slug_parts) > 10) {
            // Generate a new limited slug from the title
            $new_slug = generate_limited_slug($faq->post_title);

            // Update the post with the new slug
            wp_update_post(array(
                'ID' => $faq->ID,
                'post_name' => $new_slug
            ));

            $fixed_count++;
        }
    }

    // After fixing long slugs, flush the rewrite rules once
    if ($fixed_count > 0) {
        global $wp_rewrite;
        $wp_rewrite->flush_rules(false);
    }

    return $fixed_count;
}

function faq_csv_import_admin_page() {
    $result = null;
    
    if (isset($_POST['import_faqs']) && wp_verify_nonce($_POST['faq_import_nonce'], 'faq_import_action')) {
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $result = import_faqs_from_csv($_FILES['csv_file']['tmp_name']);
        } elseif (!empty($_POST['csv_path'])) {
            $result = import_faqs_from_csv(sanitize_text_field($_POST['csv_path']));
        }
    }
    
    ?>
    <div class="wrap">
        <h1>Import Questions Answered from CSV</h1>
        
        <?php if ($result): ?>
            <?php if (isset($result['error'])): ?>
                <div class="notice notice-error">
                    <p><strong>Error:</strong> <?php echo esc_html($result['error']); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-success">
                    <p><strong>Import completed!</strong></p>
                    <ul>
                        <li>Questions Answered imported: <?php echo intval($result['imported']); ?></li>
                        <li>Rows skipped: <?php echo intval($result['skipped']); ?></li>
                    </ul>
                    <?php if (!empty($result['errors'])): ?>
                        <p><strong>Errors encountered:</strong></p>
                        <ul>
                            <?php foreach ($result['errors'] as $error): ?>
                                <li><?php echo esc_html($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="card">
            <h2>Import Instructions</h2>
            <p>This tool will import Questions Answered from a CSV file into the Questions Answered custom post type. The CSV should have the following columns:</p>
            <ul>
                <li><strong>created</strong> - Date the question was created</li>
                <li><strong>post_title</strong> - The title of the question</li>
                <li><strong>post_content</strong> - The answer/response for the question</li>
                <li><strong>_faq_email</strong> - (Optional) The email associated with the question</li>
            </ul>
            <p>All columns except _faq_email are required. Rows with missing required fields will be skipped.</p>
        </div>
        
        <form method="post" enctype="multipart/form-data" action="">
            <?php wp_nonce_field('faq_import_action', 'faq_import_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Upload CSV File</th>
                    <td>
                        <input type="file" name="csv_file" accept=".csv" />
                        <p class="description">Upload a CSV file containing your Questions Answered.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Or Enter File Path</th>
                    <td>
                        <input type="text" name="csv_path" value="" class="regular-text" />
                        <p class="description">Enter the full path to your CSV file on the server (alternative to uploading).</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Import Questions Answered', 'primary', 'import_faqs'); ?>

        <?php if (isset($_POST['fix_long_slugs']) && wp_verify_nonce($_POST['faq_fix_nonce'], 'faq_fix_action')): ?>
            <?php
            $fixed_count = fix_faq_long_slugs();
            ?>
            <div class="notice notice-success">
                        <p>Fixed <?php echo intval($fixed_count); ?> Questions Answered posts with long slugs.</p>
            </div>
        <?php endif; ?>
    </form>

    <h2>Fix Existing Posts</h2>
    <form method="post" action="">
        <?php wp_nonce_field('faq_fix_action', 'faq_fix_nonce'); ?>
        <p><strong>Fix existing posts:</strong> If you have existing Questions Answered posts with very long slugs, use this tool to fix them.</p>
        <?php submit_button('Fix Long Slugs', 'secondary', 'fix_long_slugs'); ?>
    </form>

    <div class="card" style="margin-top: 20px;">
        <h3>Troubleshooting</h3>
        <p><strong>Having trouble with page not found errors?</strong> After importing or fixing slugs, you may need to refresh your permalink structure.</p>
        <p>To do this, go to <a href="<?php echo admin_url('options-permalink.php'); ?>">Settings > Permalinks</a> and click "Save Changes" (you don't need to change any settings).</p>
    </div>
    </div>
    <?php
}

/**
 * Add CSV import submenu to FAQ Settings menu
 */
function add_faq_csv_import_menu() {
    add_submenu_page(
        'faq-settings',
        'Import Questions Answered',
        'Import CSV',
        'manage_options',
        'faq-csv-import',
        'faq_csv_import_admin_page'
    );
}
add_action('admin_menu', 'add_faq_csv_import_menu', 10); // Lower priority to run after parent menu is created
