<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    wp_die(__('Direct access to this file is not permitted.', 'faq-post-create'));
}