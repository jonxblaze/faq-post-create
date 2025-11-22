<?php
/**
 * Plugin Name: FAQ Post Creator
 * Description: Allows non-logged-in users to submit questions that become draft FAQ posts for admin approval.
 * Version: 1.0.4
 * Author: Jon Blaze
 * Author URI: https://jbwebdev.com
 * Plugin URI:
 * Copyright: 2025 Jon Blaze

 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include the activation hook function
if (!function_exists('register_activation_hook')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

// Load the main plugin class
require_once plugin_dir_path(__FILE__) . 'class-faq-post-create.php';

// Activation and deactivation hooks
register_activation_hook(__FILE__, array('FAQ_Post_Create', 'activate'));
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

// Initialize the plugin
new FAQ_Post_Create();

// Add settings link to plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'faq_add_settings_link');

// Settings link function
function faq_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=faq-settings') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
