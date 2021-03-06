<?php
/**
 * Registers plugin scripts and CSS
 */

namespace MailOptin\Core;


class RegisterScripts
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'admin_css'));
        add_action('admin_enqueue_scripts', [$this, 'admin_js']);
        add_action('wp_enqueue_scripts', array($this, 'public_css'));
        add_action('wp_enqueue_scripts', array($this, 'public_js'));
    }

    /**
     * Admin JS
     */
    public function admin_js()
    {
        wp_enqueue_script('jquery');
        wp_enqueue_script('underscore');
        wp_enqueue_script('mailoptin-admin-script', MAILOPTIN_ASSETS_URL . 'js/admin/admin-script.js', array('jquery'), false, true);
        wp_enqueue_script('mailoptin-highcharts', MAILOPTIN_ASSETS_URL . 'js/admin/highcharts.js', array('jquery'), false, true);
        wp_enqueue_script('mailoptin-add-optin-campaign', MAILOPTIN_ASSETS_URL . 'js/admin/new-optin-campaign.js', array('jquery'), false, true);
        wp_enqueue_script('mailoptin-optin-type-selection', MAILOPTIN_ASSETS_URL . 'js/admin/optin-type-selection.js', array('jquery'), false, true);
        wp_enqueue_script('mailoptin-add-email-campaign', MAILOPTIN_ASSETS_URL . 'js/admin/new-email-campaign.js', array('jquery'), false, true);
        $this->global_js_variables('mailoptin-add-optin-campaign');
        do_action('mo_admin_js_enqueue');
    }

    /**
     * Enqueue public scripts and styles.
     */
    public function public_js()
    {
        wp_enqueue_script('jquery');
        $this->modal_scripts();
    }

    /**
     * Enqueue modal optin scripts.
     */
    public function modal_scripts()
    {
        // trailing "true" function argument not needed because we want it loaded before hidden optin markup display in footer.
        wp_enqueue_script('mo-google-webfont', 'https://ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js', false, MAILOPTIN_VERSION_NUMBER, true);

        if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) {
            wp_enqueue_style('mo-animate', MAILOPTIN_ASSETS_URL . 'css/animate.css', false, MAILOPTIN_VERSION_NUMBER);
            wp_enqueue_style('mo-mailoptin', MAILOPTIN_ASSETS_URL . 'css/mailoptin.css', false, MAILOPTIN_VERSION_NUMBER);
            wp_enqueue_script('mo-requirejs', MAILOPTIN_ASSETS_URL . 'js/src/require.js', [], MAILOPTIN_VERSION_NUMBER, true);
            wp_enqueue_script('mailoptin', MAILOPTIN_ASSETS_URL . 'js/src/main.js', ['jquery', 'mo-requirejs'], MAILOPTIN_VERSION_NUMBER, true);
        } else {
            wp_enqueue_style('mailoptin', MAILOPTIN_ASSETS_URL . 'css/mailoptin.min.css', false, MAILOPTIN_VERSION_NUMBER);
            wp_enqueue_script('mailoptin', MAILOPTIN_ASSETS_URL . 'js/mailoptin.min.js', ['jquery'], MAILOPTIN_VERSION_NUMBER, true);
        }

        $this->global_js_variables('mailoptin');
    }

    /**
     * Global JS variables by required by mailoptin.
     *
     * @param string $handle handle to cling to.
     */
    public function global_js_variables($handle)
    {
        wp_localize_script(
            $handle, 'mailoptin_globals',
            apply_filters('mo_mailoptin_js_globals', array(
                'admin_url' => admin_url(),
                'public_js' => MAILOPTIN_ASSETS_URL . 'js/src',
                'nonce' => wp_create_nonce('mailoptin-admin-nonce'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'is_customize_preview' => is_customize_preview() ? 'true' : 'false',
                'chosen_search_placeholder' => __('Type to search', 'mailoptin'),
                'js_confirm_text' => __('Are you sure you want to do this?', 'mailoptin'),
                'js_clear_stat_text' => __('Are you sure you want to do this? Clicking OK will delete all your optin analytics records.', 'mailoptin')
            ))
        );
    }

    /**
     * Admin CSS.
     */
    public function admin_css()
    {
        wp_enqueue_style('mailoptin-admin', MAILOPTIN_ASSETS_URL . 'css/admin/admin.css');
        wp_enqueue_style('mailoptin-font-awesome', MAILOPTIN_ASSETS_URL . 'css/font-awesome/css/font-awesome.min.css', array(), MAILOPTIN_VERSION_NUMBER);
    }

    /**
     * Front-end CSS
     */
    public function public_css()
    {
    }

    /**
     * @return RegisterScripts
     */
    public static function get_instance()
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new self();
        }

        return $instance;
    }

}