<?php

namespace MailOptin\Core\OptinForms;

use MailOptin\Core\Admin\Customizer\OptinForm\AbstractCustomizer;
use MailOptin\Core\Admin\Customizer\OptinForm\Customizer;
use MailOptin\Core\Admin\Customizer\OptinForm\CustomizerSettings;
use MailOptin\Core\PluginSettings\Settings;
use MailOptin\Core\RegisterScripts;
use MailOptin\Core\Repositories\OptinCampaignsRepository;

abstract class AbstractOptinForm extends AbstractCustomizer implements OptinFormInterface
{
    /** @var int optin campaign ID */
    protected $optin_campaign_id;

    /** @var string optin universal unique ID */
    protected $optin_campaign_uuid;

    /** @var string optin campaign type */
    protected $optin_campaign_type;

    /** @var string optin campaign class */
    protected $optin_campaign_class;

    /** @var string optin wrapper CSS ID */
    protected $optin_css_id;

    /**
     * ID of optin form.
     *
     * @param int $optin_campaign_id
     * @param \WP_Customize_Manager $wp_customize
     */
    public function __construct($optin_campaign_id = 0, $wp_customize = null)
    {
        // isn't included in if condition below because it is reused by front end optin output.
        $this->optin_campaign_id = $optin_campaign_id;
        $this->optin_campaign_uuid = OptinCampaignsRepository::get_optin_campaign_uuid($optin_campaign_id);
        $this->optin_campaign_type = OptinCampaignsRepository::get_optin_campaign_type($optin_campaign_id);
        $this->optin_campaign_class = OptinCampaignsRepository::get_optin_campaign_class($optin_campaign_id);
        $this->optin_css_id = "{$this->optin_campaign_uuid}_{$this->optin_campaign_type}";

        if (!empty($_REQUEST['mailoptin_optin_campaign_id'])) {
            add_filter('mo_optin_form_customizer_design_settings', [$this, 'customizer_design_settings'], 10, 2);
            add_filter('mo_optin_form_customizer_headline_settings', [$this, 'customizer_headline_settings'], 10, 2);
            add_filter('mo_optin_form_customizer_description_settings', [$this, 'customizer_description_settings'], 10, 2);
            add_filter('mo_optin_form_customizer_note_settings', [$this, 'customizer_note_settings'], 10, 2);
            add_filter('mo_optin_form_customizer_fields_settings', [$this, 'customizer_fields_settings'], 10, 2);
            add_filter('mo_optin_form_customizer_configuration_settings', [$this, 'customizer_configuration_settings'], 10, 2);
            add_filter('mo_optin_form_customizer_output_settings', [$this, 'customizer_output_settings'], 10, 2);

            add_filter('mo_optin_form_customizer_design_controls', [$this, 'customizer_design_controls'], 10, 4);
            add_filter('mo_optin_form_customizer_headline_controls', [$this, 'customizer_headline_controls'], 10, 4);
            add_filter('mo_optin_form_customizer_description_controls', [$this, 'customizer_description_controls'], 10,
                4);
            add_filter('mo_optin_form_customizer_note_controls', array($this, 'customizer_note_controls'), 10, 4);
            add_filter('mo_optin_form_customizer_fields_controls', [$this, 'customizer_fields_controls'], 10, 4);
            add_filter('mo_optin_form_customizer_configuration_controls', [$this, 'customizer_configuration_controls'], 10, 4);
            add_filter('mo_optin_form_customizer_output_controls', [$this, 'customizer_output_controls'], 10, 4);

            add_action('customize_preview_init', array($this, 'optin_form_customizer_javascript'));

            add_action('customize_preview_init', array(RegisterScripts::get_instance(), 'modal_scripts'));
        }

        parent::__construct($optin_campaign_id);
    }

    /**
     * Font with space are suppose to be enclose in double quote else, unquoted.
     *
     * @param $val
     * @param string $fallback fallback font
     *
     * @return mixed|string
     */
    public function _construct_font_family($val, $fallback = 'Helvetica, Arial, sans-serif;')
    {
        $font_family = $this->_replace_plus_with_space($val);

        return strpos($font_family, ' ') ? "'$font_family', $fallback" : $font_family . ", $fallback";
    }

    /**
     * Replace + with space.
     *
     * @param string $val
     *
     * @return mixed
     */
    public function _replace_plus_with_space($val)
    {
        return str_replace('+', ' ', $val);
    }

    /**
     * Replace space with +.
     *
     * @param string $val
     *
     * @return mixed
     */
    public function _replace_space_with_plus($val)
    {
        return str_replace(' ', '+', $val);
    }

    /**
     * If font is a web safe font, default to empty.
     *
     * @param string $font
     *
     * @return string
     */
    public function _remove_web_safe_font($font)
    {
        $web_safe_font = [
            'Helvetica',
            'Helvetica Neue',
            'Arial',
            'Times New Roman',
            'Lucida Sans',
            'Verdana',
            'Tahoma',
            'Cambria',
            'Trebuchet MS',
            'Segoe UI'
        ];

        $font = $this->_replace_plus_with_space($font);

        return in_array($font, $web_safe_font) ? '' : $font;
    }

    /**
     * Enqueue optin form customizer JavaScript.
     *
     * @return mixed
     */
    public function optin_form_customizer_javascript()
    {
        $optin_form_name = OptinCampaignsRepository::get_optin_campaign_name($this->optin_campaign_id);
        $optin_form_name = preg_replace('/\s+/', '-', $optin_form_name);

        wp_enqueue_script(
            "mailoptin-optin-form-customizer-{$optin_form_name}",
            MAILOPTIN_ASSETS_URL . 'js/admin/optin-customizer.js',
            array('customize-preview', 'jquery'),
            false,
            true
        );

        do_action('mailoptin_optin_customizer_javascript_enqueue', MAILOPTIN_ASSETS_URL, $optin_form_name);

        $optin_campaign_option_prefix = MO_OPTIN_CAMPAIGN_WP_OPTION_NAME;
        wp_add_inline_script(
            "customize-preview",
            "var mailoptin_optin_campaign_id = $this->optin_campaign_id;
            var mailoptin_optin_option_prefix  = '$optin_campaign_option_prefix'"
        );
    }

    /**
     * Return value of a optin form customizer settings.
     *
     * @param string $optin_form_setting
     *
     * @return string
     */
    public function get_customizer_value($optin_form_setting)
    {
        $default = isset($this->customizer_defaults[$optin_form_setting]) ? $this->customizer_defaults[$optin_form_setting] : '';

        return OptinCampaignsRepository::get_customizer_value($this->optin_campaign_id, $optin_form_setting, $default);
    }

    /**
     * Full HTML doctype markup preview of a optin form.
     *
     * @return string
     */
    public function get_preview_structure()
    {
        $title = __('MailOptin Optin Form', 'mailoptin');
        $optin_form_fonts = $this->get_optin_form_fonts();

        // set body padding-top to 0 if optin is bar.
        $body_padding_top = in_array($this->optin_campaign_type, ['bar']) ? 0 : '10%';
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title><?php echo $title; ?></title>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
            <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1"/>
            <?php do_action('mo_optin_form_customizer_preview_head'); ?>
        </head>

    <body style="background: #f3f3f3 !important;padding-top:<?php echo $body_padding_top; ?>;">
        <?php
        if (in_array($this->optin_campaign_type,
            ['sidebar', 'inpost'])) { // ensure sidebar and inpost optin has a max width for preview sake.
            echo '<div style="max-width:700px;margin: auto">';
        }

        echo $this->get_optin_form_structure();

        if (in_array($this->optin_campaign_type, ['sidebar', 'inpost'])) {
            echo '</div>';
        }

        if (is_customize_preview()) {
            echo '<style id="mo-customizer-preview-custom-css"></style>';
            // hide any element that might have been injected to footer by any plugin.
            echo '<div style="display:none">';
            wp_footer();
            echo '</div>';
            // script below is below wp_footer() because jquery must have been loaded by now.
            if (!empty($optin_form_fonts)) {
                echo "<script type='text/javascript'>jQuery(function(){WebFont.load({google: {families: [$optin_form_fonts]}})});</script>";
            }
        }
        echo '</body>';

        return ob_get_clean();
    }


    /**
     * Global optin CSS.
     *
     * @param string $optin_css_id
     * @param string $optin_campaign_uuid
     *
     * @return string
     */
    public function global_css($optin_css_id, $optin_campaign_uuid = '')
    {
        $global_css = "div#{$optin_css_id}_container div.mo-optin-powered-by{margin:5px auto 0;text-align:center;}";
        $global_css .= "div#{$optin_css_id}_container div.mo-optin-powered-by a {font-size:16px !important; text-decoration: none !important;box-shadow:none !important;border-bottom-width:0px !important;cursor:pointer !important;}";

        if ($this->optin_campaign_type == 'bar') {
            $global_css .= "div#$optin_campaign_uuid.mo-optin-form-bar-top {top: 0;position: absolute;}";
            $global_css .= "div#$optin_campaign_uuid.mo-optin-form-bar-bottom {bottom: 0;position: fixed;}";
            $global_css .= "div#$optin_campaign_uuid.mo-optin-form-bar-sticky {position: fixed;}";
            $global_css .= '.admin-bar .mo-optin-form-bar-top {top: 32px !important;}';
            $global_css .= '@media screen and (max-width: 782px) { .admin-bar .mo-optin-form-bar-top { top: 46px !important; } }';
            $global_css .= '@media screen and (max-width: 600px) { .admin-bar .mo-optin-form-bar-top.mo-optin-form-bar-sticky { top: 0 !important; } }';
        }

        if ($this->optin_campaign_type == 'slidein') {
            $global_css .= "div#$optin_campaign_uuid.mo-slidein-bottom_right {right: 10px;}";
            $global_css .= "div#$optin_campaign_uuid.mo-slidein-bottom_left {left: 10px;}";
        }

        return $global_css;
    }

    /**
     * Return scripts and styles needed or that belongs to an optin form.
     *
     * @return string
     */
    public function optin_script_and_styles()
    {
        $custom_css = $this->get_customizer_value('form_custom_css');
        $script_styles = '';
        $script_styles .= "<script type=\"text/javascript\">{$this->optin_js_config()}</script>";
        $script_styles .= '<style id="mo-optin-form-stylesheet" type="text/css">';
        $script_styles .= minify_css($this->optin_form_css() . $this->global_css($this->optin_css_id, $this->optin_campaign_uuid));
        $script_styles .= '</style>';

        if (!empty($custom_css)) {
            $script_styles .= '<style id="mo-optin-form-custom-css" type="text/css">';
            $script_styles .= minify_css($custom_css);
            $script_styles .= '</style>';
        }

        $script_styles .= $this->optin_script();

        return $script_styles;
    }

    /**
     * HTML and CSS structure of an optin form.
     */
    protected function _get_optin_form_structure()
    {
        $optin_css_id = $this->optin_css_id;
        $optin_campaign_uuid = $this->optin_campaign_uuid;
        $optin_form = '';
        $name_email_class_indicator = $this->get_customizer_value('hide_name_field') === false ? ' mo-has-name-email' : ' mo-has-email';

        if ($this->optin_campaign_type == 'lightbox') {
            $modalWrapperStyle = implode(';', [
                'display: none',
                'position: fixed',
                'zoom: 1',
                'text-align: center',
                'z-index: 99999999',
                'left: 0',
                'top: 0',
                'width: 100%',
                'height: 100%',
                'overflow: hidden', // fixes overflow scrolling bar being shown :D
                'background: rgba(0,0,0,0.7)'
            ]);

            $optin_form .= "<div id='$optin_campaign_uuid' class=\"moOptinForm mo-optin-form-{$this->optin_campaign_type} {$name_email_class_indicator}\" data-optin-type='{$this->optin_campaign_type}' style='$modalWrapperStyle'>";
        }

        if ($this->optin_campaign_type == 'bar') {
            $position = $this->get_customizer_value('bar_position');
            $position_class = ' mo-optin-form-bar-' . $position;
            $is_sticky = $position == 'top' && $this->get_customizer_value('bar_sticky') ? ' mo-optin-form-bar-sticky' : '';

            $bar_wrapper_style_properties = [
                'display: none',
                'left: 0',
                'right: 0',
                'width: 100%',
                'margin: 0',
                'background: 0 0',
                'z-index: 99999'
            ];

            $barWrapperStyle = implode(';', $bar_wrapper_style_properties);

            $optin_form .= "<div id='$optin_campaign_uuid' class=\"moOptinForm mo-optin-form-{$this->optin_campaign_type} {$name_email_class_indicator}{$position_class}{$is_sticky}\" data-optin-type='{$this->optin_campaign_type}' style='$barWrapperStyle'>";
        }

        if ($this->optin_campaign_type == 'slidein') {
            $position = $this->get_customizer_value('slidein_position');
            $position = empty($position) ? 'bottom_right' : $position;
            $position_class = ' mo-slidein-' . $position;
            $slidein_wrapper_style_properties = [
                'display: none',
                'position: fixed',
                'bottom: 10px',
                'width: auto',
                'margin: 0',
                'background: 0 0',
                'z-index: 99999'
            ];

            $slideinWrapperStyle = implode(';', $slidein_wrapper_style_properties);

            $optin_form .= "<div id='$optin_campaign_uuid' class=\"moOptinForm mo-optin-form-{$this->optin_campaign_type}{$position_class}\" data-optin-type='{$this->optin_campaign_type}' style='$slideinWrapperStyle'>";
        }

        if ($this->optin_campaign_type == 'sidebar') {
            $optin_form .= "<div id='$optin_campaign_uuid' class=\"moOptinForm mo-optin-form-{$this->optin_campaign_type} {$name_email_class_indicator}\" data-optin-type='{$this->optin_campaign_type}'>";
        }

        if ($this->optin_campaign_type == 'inpost') {
            $optin_form .= "<div id='$optin_campaign_uuid' class=\"moOptinForm mo-optin-form-{$this->optin_campaign_type} {$name_email_class_indicator}\" data-optin-type='{$this->optin_campaign_type}'>";
        }

        $optin_form .= "<div class='mo-optin-form-container' id='{$optin_css_id}_container' style='position:relative;margin: 0 auto;'>";
        $optin_form .= $this->optin_script_and_styles();
        $optin_form .= do_shortcode($this->optin_form());

        $optin_form .= "</div>";
        $optin_form .= "</div>";

        $output = apply_filters('mo_optin_form_attribution_start', '<!-- This site converts visitors into subscribers and customers with the MailOptin WordPress plugin v' . MAILOPTIN_VERSION_NUMBER . ' - https://mailoptin.io -->' . PHP_EOL);
        $output .= minify_html($optin_form);
        $output .= "<!-- / MailOptin WordPress plugin. -->" . PHP_EOL;

        return $output;
    }

    /**
     * Cache proxy to retrieve the optin form structure.
     *
     * @return string
     */
    public function get_optin_form_structure()
    {
        // retrieve uncached result if we are in customizer screen.
        if (apply_filters('mailoptin_disable_optin_form_cache', is_customize_preview())) {
            return $this->_get_optin_form_structure();
        }

        $cache_key = "mo_get_optin_form_structure_{$this->optin_campaign_id}";
        $optin_structure = get_transient($cache_key);

        if (empty($optin_structure) || false === $optin_structure) {
            $optin_structure = $this->_get_optin_form_structure();
            set_transient(
                $cache_key,
                $optin_structure,
                apply_filters('mailoptin_get_optin_form_structure_cache_expiration', HOUR_IN_SECONDS)
            );
        }

        return $optin_structure;
    }

    /**
     * Optin form (Google) fonts.
     *
     * @return string
     */
    public function get_optin_form_fonts()
    {
        // retrieve uncached result if we are in customizer screen.
        if (apply_filters('mailoptin_disable_optin_form_cache', is_customize_preview())) {
            return $this->_get_optin_form_fonts();
        }

        $cache_key = "mo_get_optin_form_fonts_{$this->optin_campaign_id}";
        $optin_structure = get_transient($cache_key);

        if (empty($optin_structure) || false === $optin_structure) {

            $optin_structure = $this->_get_optin_form_fonts();

            set_transient(
                $cache_key,
                $optin_structure,
                apply_filters('mailoptin_get_optin_form_fonts_cache_expiration', HOUR_IN_SECONDS)
            );
        }

        return $optin_structure;
    }

    /**
     * Cache proxy to retrieve the optin form (Google) fonts.
     *
     * @return string
     */
    protected function _get_optin_form_fonts()
    {
        $default_headline_font = $this->customizer_defaults['headline_font'];
        $default_description_font = $this->customizer_defaults['description_font'];
        $default_note_font = $this->customizer_defaults['note_font'];
        $default_submit_button_font = $this->customizer_defaults['submit_button_font'];

        $headline_font = apply_filters('mo_get_optin_form_headline_font',
            $this->_remove_web_safe_font(
                OptinCampaignsRepository::get_customizer_value($this->optin_campaign_id, 'headline_font', $default_headline_font)
            ),
            'headline_font',
            $default_headline_font,
            $this->optin_campaign_id
        );

        $description_font = apply_filters('mo_get_optin_form_description_font',
            $this->_remove_web_safe_font(
                OptinCampaignsRepository::get_customizer_value($this->optin_campaign_id, 'description_font', $default_description_font)
            ),
            'description_font',
            $default_headline_font,
            $this->optin_campaign_id
        );

        $note_font = apply_filters('mo_get_optin_form_note_font',
            $this->_remove_web_safe_font(
                OptinCampaignsRepository::get_customizer_value($this->optin_campaign_id, 'note_font', $default_note_font)
            ),
            'note_font',
            $default_headline_font,
            $this->optin_campaign_id
        );

        $submit_button_font = apply_filters('mo_get_optin_form_submit_button_font',
            $this->_remove_web_safe_font(
                OptinCampaignsRepository::get_customizer_value($this->optin_campaign_id, 'submit_button_font', $default_submit_button_font)
            ),
            'submit_button_font',
            $default_headline_font,
            $this->optin_campaign_id
        );

        // build the comma delimited webfonts
        $webfont = [];
        if (!empty($headline_font)) {
            $webfont[] = "'$headline_font'";
        }
        if (!empty($description_font)) {
            $webfont [] = "'$description_font'";
        }
        if (!empty($note_font)) {
            $webfont[] = "'$note_font'";
        }
        if (!empty($submit_button_font)) {
            $webfont[] = "'$submit_button_font'";
        }

        $delimiter = !empty($webfont) ? ',' : null;

        return implode(',', array_unique($webfont)) . $delimiter;
    }

    /**
     * Optin JS configuration.
     */
    public function optin_js_config()
    {
        $optin_campaign_id = $this->optin_campaign_id;

        $exit_cookie = $this->get_customizer_value('cookie');
        $success_cookie = $this->get_customizer_value('success_cookie');

        $global_exit_cookie = Settings::instance()->global_cookie();
        $global_success_cookie = Settings::instance()->global_success_cookie();

        $x_page_views_status = $this->get_customizer_value('x_page_views_status');
        $x_page_views_condition = $this->get_customizer_value('x_page_views_condition');
        $x_page_views_value = $this->get_customizer_value('x_page_views_value');

        $x_seconds_status = $this->get_customizer_value('x_seconds_status');
        $x_seconds_value = $this->get_customizer_value('x_seconds_value');

        $x_scroll_status = $this->get_customizer_value('x_scroll_status');
        $x_scroll_value = $this->get_customizer_value('x_scroll_value');

        $exit_intent_status = $this->get_customizer_value('exit_intent_status');

        $data = array();
        $data['optin_uuid'] = $this->optin_campaign_uuid;
        $data['optin_campaign_id'] = $optin_campaign_id;
        $data['optin_type'] = OptinCampaignsRepository::get_optin_campaign_type($optin_campaign_id);
        $data['post_id'] = $post_id = is_singular() || is_front_page() ? get_queried_object_id() : 0;
        // must be of integer type for js-cookie to work.
        // am not using empty() because if cookie is set to 0, it returns true i.e 0 is empty.
        $data['cookie'] = $exit_cookie != '' ? absint($exit_cookie) : 30;
        // defaults to value of exit cookie above
        $data['success_cookie'] = $success_cookie != '' ? absint($success_cookie) : $data['cookie'];
        $data['global_cookie'] = $global_exit_cookie != '' ? absint($global_exit_cookie) : 0;
        $data['global_success_cookie'] = $global_success_cookie != '' ? absint($global_success_cookie) : 0;
        $data['success_message'] = $this->get_customizer_value('success_message');

        /** x page view display rule */
        if ($x_page_views_status === true && $x_page_views_condition != '...' && !empty($x_page_views_value)) {
            $data['x_page_views_status'] = $x_page_views_status;
            $data['x_page_views_condition'] = $x_page_views_condition;
            $data['x_page_views_value'] = absint($x_page_views_value);
        }

        /** after x seconds display rule */
        if ($x_seconds_status === true && !empty($x_seconds_value)) {
            $data['x_seconds_status'] = $x_seconds_status;
            $data['x_seconds_value'] = absint($x_seconds_value);
        }

        /** after x scroll percentage display rule */
        if ($x_scroll_status === true && !empty($x_scroll_value)) {
            $data['x_scroll_status'] = $x_scroll_status;
            $data['x_scroll_value'] = absint($x_scroll_value);
        }

        /** exit intent display rule */
        if ($exit_intent_status === true && !empty($exit_intent_status)) {
            $data['exit_intent_status'] = $exit_intent_status;
        }

        $data['test_mode'] = OptinCampaignsRepository::is_test_mode($optin_campaign_id);
        $data['icon_close'] = $this->optin_campaign_type == 'lightbox' ? apply_filters('mo_optin_campaign_icon_close', true, $this->optin_campaign_class) : false;

        if ($this->optin_campaign_type == 'lightbox') {
            $data['body_close'] = apply_filters('mo_optin_campaign_body_close', true);
            $data['keyClose'] = apply_filters('mo_optin_campaign_key_close', true);
        }

        if ($this->optin_campaign_type == 'bar') {
            $data['bar_position'] = $this->get_customizer_value('bar_position');
        }

        if ($this->optin_campaign_type == 'slidein') {
            $data['slidein_position'] = $this->get_customizer_value('slidein_position');
        }

        $data['unexpected_error'] = apply_filters('mo_optin_campaign_unexpected_error', __('Unexpected error. Please try again.', 'mailoptin'));
        $data['email_missing_error'] = apply_filters('mo_optin_campaign_email_missing_error', 'Please enter a valid email.', 'mailoptin');
        $data['name_missing_error'] = apply_filters('mo_optin_campaign_name_missing_error', __('Please enter a valid name.', 'mailoptin'));
        $data['honeypot_error'] = apply_filters('mo_optin_campaign_honeypot_error', __('Your submission has been flagged as potential spam.', 'mailoptin'));

        $data = apply_filters('mo_optin_js_config', $data, $this);

        $json = json_encode($data);

        $script = "var $this->optin_campaign_uuid = {$this->optin_campaign_uuid}_{$this->optin_campaign_type} = $json;";

        return $script;
    }

    /**
     * Customizer settings for optin form design.
     *
     * Any optin theme that wish to modify any of these settings should implement the method(s)
     *
     * @param mixed $settings
     *
     * @return mixed
     */
    abstract public function customizer_design_settings($settings, $CustomizerSettingsInstance);

    /**
     * Customizer settings for optin form headline.
     *
     * Any optin theme that wish to modify any of these settings should implement the method(s)
     *
     * @param mixed $settings
     *
     * @param CustomizerSettings $CustomizerSettingsInstance
     *
     * @return mixed
     */
    abstract public function customizer_headline_settings($settings, $CustomizerSettingsInstance);

    /**
     * Customizer settings for optin form description.
     *
     * Any optin theme that wish to modify any of these settings should implement the method(s)
     *
     * @param mixed $settings
     *
     * @param CustomizerSettings $CustomizerSettingsInstance
     *
     * @return mixed
     */
    abstract public function customizer_description_settings($settings, $CustomizerSettingsInstance);

    /**
     * Customizer settings for optin form note.
     *
     * Any optin theme that wish to modify any of these settings should implement the method(s)
     *
     * @param mixed $settings
     *
     * @param CustomizerSettings $CustomizerSettingsInstance
     *
     * @return mixed
     */
    abstract public function customizer_note_settings($settings, $CustomizerSettingsInstance);

    /**
     * Customizer settings for optin form fields.
     *
     * @param mixed $settings
     *
     * @param CustomizerSettings $CustomizerSettingsInstance
     *
     * @return mixed
     */
    abstract public function customizer_fields_settings($settings, $CustomizerSettingsInstance);

    /**
     * Customizer settings for optin form configuration.
     *
     * @param mixed $settings
     *
     * @param CustomizerSettings $CustomizerSettingsInstance
     *
     * @return mixed
     */
    abstract public function customizer_configuration_settings($settings, $CustomizerSettingsInstance);

    /**
     * Customizer settings for optin form output.
     *
     * @param mixed $settings
     *
     * @param CustomizerSettings $CustomizerSettingsInstance
     *
     * @return mixed
     */
    abstract public function customizer_output_settings($settings, $CustomizerSettingsInstance);


    // --------------------------------------------- Optin form customizer controls. --------------------------------------- //

    /**
     * Customizer controls for optin form design settings.
     *
     * Any optin theme that wish to modify any of these controls should implement the method(s)
     *
     * @param array $controls
     * @param \WP_Customize_Manager $wp_customize
     * @param string $option_prefix
     * @param Customizer $customizerClassInstance
     *
     * @return mixed
     */
    abstract public function customizer_design_controls(
        $controls,
        $wp_customize,
        $option_prefix,
        $customizerClassInstance
    );

    /**
     * Customizer controls for optin form headline settings.
     *
     * Any optin theme that wish to modify any of these controls should implement the method(s)
     *
     * @param array $controls
     * @param \WP_Customize_Manager $wp_customize
     * @param string $option_prefix
     * @param Customizer $customizerClassInstance
     *
     * @return mixed
     */
    abstract public function customizer_headline_controls(
        $controls,
        $wp_customize,
        $option_prefix,
        $customizerClassInstance
    );

    /**
     * Customizer controls for optin form description settings.
     *
     * Any optin theme that wish to modify any of these controls should implement the method(s)
     *
     * @param array $controls
     * @param \WP_Customize_Manager $wp_customize
     * @param string $option_prefix
     * @param Customizer $customizerClassInstance
     *
     * @return mixed
     */
    abstract public function customizer_description_controls(
        $controls,
        $wp_customize,
        $option_prefix,
        $customizerClassInstance
    );

    /**
     * Customizer controls for optin form note settings.
     *
     * Any optin theme that wish to modify any of these controls should implement the method(s)
     *
     * @param array $controls
     * @param \WP_Customize_Manager $wp_customize
     * @param string $option_prefix
     * @param Customizer $customizerClassInstance
     *
     * @return mixed
     */
    abstract public function customizer_note_controls(
        $controls,
        $wp_customize,
        $option_prefix,
        $customizerClassInstance
    );

    /**
     * Customizer controls for optin form fields settings.
     *
     * @param array $controls
     * @param \WP_Customize_Manager $wp_customize
     * @param string $option_prefix
     * @param Customizer $customizerClassInstance
     *
     * @return mixed
     */
    abstract public function customizer_fields_controls(
        $controls,
        $wp_customize,
        $option_prefix,
        $customizerClassInstance
    );

    /**
     * Customizer controls for optin form configuration settings.
     *
     * @param array $controls
     * @param \WP_Customize_Manager $wp_customize
     * @param string $option_prefix
     * @param Customizer $customizerClassInstance
     *
     * @return mixed
     */
    abstract public function customizer_configuration_controls(
        $controls,
        $wp_customize,
        $option_prefix,
        $customizerClassInstance
    );

    /**
     * Customizer controls for optin form output settings.
     *
     * @param array $controls
     * @param \WP_Customize_Manager $wp_customize
     * @param string $option_prefix
     * @param Customizer $customizerClassInstance
     *
     * @return mixed
     */
    abstract public function customizer_output_controls(
        $controls,
        $wp_customize,
        $option_prefix,
        $customizerClassInstance
    );
}