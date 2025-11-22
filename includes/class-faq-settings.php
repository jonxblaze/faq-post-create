<?php
/**
 * FAQ Settings Handler
 *
 * @package FAQ_Post_Create
 * @subpackage Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles plugin settings and reCAPTCHA configuration
 */
class FAQ_Settings {

    /**
     * Settings page slug
     */
    const SETTINGS_PAGE = 'faq-settings';

    /**
     * Option group name
     */
    const OPTION_GROUP = 'faq_settings_group';

    /**
     * Option name for settings
     */
    const OPTION_NAME = 'faq_settings';

    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_settings_page'));
        add_action('admin_init', array(__CLASS__, 'init_settings'));
    }

    /**
     * Add settings page to admin menu
     */
    public static function add_settings_page() {
        add_options_page(
            'FAQ Settings',
            'FAQ Settings',
            'manage_options',
            self::SETTINGS_PAGE,
            array(__CLASS__, 'settings_page_html')
        );
    }

    /**
     * Initialize settings
     */
    public static function init_settings() {
        register_setting(self::OPTION_GROUP, self::OPTION_NAME, array(__CLASS__, 'sanitize_settings'));

        add_settings_section(
            'faq_recaptcha_section',
            'reCAPTCHA Settings',
            array(__CLASS__, 'recaptcha_section_callback'),
            self::SETTINGS_PAGE
        );

        add_settings_field(
            'recaptcha_enabled',
            'Enable reCAPTCHA',
            array(__CLASS__, 'recaptcha_enabled_callback'),
            self::SETTINGS_PAGE,
            'faq_recaptcha_section',
            array('label_for' => 'recaptcha_enabled')
        );

        add_settings_field(
            'recaptcha_site_key',
            '',
            array(__CLASS__, 'recaptcha_site_key_callback'),
            self::SETTINGS_PAGE,
            'faq_recaptcha_section'
        );

        add_settings_field(
            'recaptcha_secret_key',
            '',
            array(__CLASS__, 'recaptcha_secret_key_callback'),
            self::SETTINGS_PAGE,
            'faq_recaptcha_section'
        );
    }

    /**
     * Sanitize settings
     */
    public static function sanitize_settings($input) {
        $sanitized = array();

        // Sanitize reCAPTCHA enabled setting
        $sanitized['recaptcha_enabled'] = isset($input['recaptcha_enabled']) ? 1 : 0;

        // Sanitize reCAPTCHA keys only if they were changed
        if (isset($input['recaptcha_site_key']) && !empty(trim($input['recaptcha_site_key']))) {
            $sanitized['recaptcha_site_key'] = sanitize_text_field($input['recaptcha_site_key']);
        } else {
            $sanitized['recaptcha_site_key'] = '';
        }

        if (isset($input['recaptcha_secret_key']) && !empty(trim($input['recaptcha_secret_key']))) {
            $sanitized['recaptcha_secret_key'] = sanitize_text_field($input['recaptcha_secret_key']);
        } else {
            $sanitized['recaptcha_secret_key'] = '';
        }

        return $sanitized;
    }

    /**
     * reCAPTCHA section callback
     */
    public static function recaptcha_section_callback() {
        echo '<p>Configure reCAPTCHA settings to protect your FAQ submission form from spam.</p>';
    }

    /**
     * reCAPTCHA enabled callback
     */
    public static function recaptcha_enabled_callback() {
        $settings = self::get_settings();
        $enabled = isset($settings['recaptcha_enabled']) ? $settings['recaptcha_enabled'] : 0;
        ?>
        <input type="checkbox" name="<?php echo self::OPTION_NAME; ?>[recaptcha_enabled]" id="recaptcha_enabled" value="1" <?php checked(1, $enabled); ?> />
        <label for="recaptcha_enabled">Enable reCAPTCHA on the FAQ submission form</label>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Set up show/hide logic for reCAPTCHA fields
                function toggleRecaptchaFields() {
                    var isEnabled = $('#recaptcha_enabled').is(':checked');
                    if (isEnabled) {
                        $('.recaptcha-field-row').show();
                    } else {
                        $('.recaptcha-field-row').hide();
                    }
                }

                // Initial state
                toggleRecaptchaFields();

                // Toggle on checkbox change
                $('#recaptcha_enabled').on('change', function() {
                    toggleRecaptchaFields();
                });
            });
        </script>
        <?php
    }

    /**
     * reCAPTCHA site key callback
     */
    public static function recaptcha_site_key_callback() {
        $settings = self::get_settings();
        $site_key = isset($settings['recaptcha_site_key']) ? $settings['recaptcha_site_key'] : '';
        $row_class = 'recaptcha-field-row';
        ?>
        <style>
            .recaptcha-field-row input{
                width: 24rem;
            }
        </style>
        <tr class="<?php echo $row_class; ?>">
            <th scope="row">reCAPTCHA Site Key</th>
            <td>
                <input type="text" name="<?php echo self::OPTION_NAME; ?>[recaptcha_site_key]" id="recaptcha_site_key" value="<?php echo esc_attr($site_key); ?>" class="regular-text" />
                <p class="description">Enter your Google reCAPTCHA v2 site key.</p>
            </td>
        </tr>
        <?php
    }

    /**
     * reCAPTCHA secret key callback
     */
    public static function recaptcha_secret_key_callback() {
        $settings = self::get_settings();
        $secret_key = isset($settings['recaptcha_secret_key']) ? $settings['recaptcha_secret_key'] : '';
        $row_class = 'recaptcha-field-row';
        ?>
        <tr class="<?php echo $row_class; ?>">
            <th scope="row">reCAPTCHA Secret Key</th>
            <td>
                <input type="password" name="<?php echo self::OPTION_NAME; ?>[recaptcha_secret_key]" id="recaptcha_secret_key" value="<?php echo esc_attr($secret_key); ?>" class="regular-text" />
                <p class="description">Enter your Google reCAPTCHA v2 secret key.</p>
            </td>
        </tr>
        <?php
    }

    /**
     * Get settings
     */
    public static function get_settings() {
        $defaults = array(
            'recaptcha_enabled' => 0,
            'recaptcha_site_key' => '',
            'recaptcha_secret_key' => ''
        );

        $settings = get_option(self::OPTION_NAME, array());

        return wp_parse_args($settings, $defaults);
    }

    /**
     * Settings page HTML
     */
    public static function settings_page_html() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::SETTINGS_PAGE);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}