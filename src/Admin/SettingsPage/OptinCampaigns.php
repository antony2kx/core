<?php
/**
 * Copyright (C) 2016  Agbonghama Collins <me@w3guy.com>
 */

namespace MailOptin\Core\Admin\SettingsPage;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

use W3Guy\Custom_Settings_Page_Api;

class OptinCampaigns extends AbstractSettingsPage
{
    /**
     * @var OptinCampaign_List
     */
    protected $optin_forms_instance;

    public function __construct()
    {
        add_action('admin_menu', array($this, 'register_settings_page'));

        add_filter('set-screen-option', array($this, 'set_screen'), 10, 3);
    }

    public function register_settings_page()
    {
        $hook = add_submenu_page(
            'mailoptin-settings',
            __('Optin Campaigns - MailOptin', 'mailoptin'),
            __('Optin Campaigns', 'mailoptin'),
            'manage_options',
            'mailoptin-optin-campaigns',
            array($this, 'settings_admin_page_callback')
        );


        add_action("load-$hook", array($this, 'screen_option'));

    }

    /**
     * We are rewriting the current page url to strip out the eg "&type=widget" from template settings page url
     * so the template tab will be active when any sub template menu such as solitary, manifold, widget is clicked/being viewed.
     *
     * @param string $current_url
     *
     * @return string
     */
    public function campaign_settings_page_url($current_url)
    {
        if ($_GET['page'] == 'mailoptin-optin-campaigns' && !empty($_GET['optin-type'])) {
            $current_url = substr($current_url, 0, strpos($current_url, '&optin-type'));
        } elseif ($_GET['page'] == 'mailoptin-optin-campaigns' && !empty($_GET['paged'])) {
            $current_url = substr($current_url, 0, strpos($current_url, '&paged'));
        } elseif ($_GET['page'] == 'mailoptin-optin-campaigns' && !empty($_GET['view'])) {
            $current_url = substr($current_url, 0, strpos($current_url, '&view'));
        }

        return $current_url;
    }


    /**
     * Sub-menu header for optin theme types.
     */
    public function optin_theme_sub_header()
    {
        if (!empty($_GET['page']) && $_GET['page'] == 'mailoptin-optin-campaigns') {
            $lightbox_url = add_query_arg('optin-type', 'lightbox', MAILOPTIN_OPTIN_CAMPAIGNS_SETTINGS_PAGE);
            $sidebar_url = add_query_arg('optin-type', 'sidebar', MAILOPTIN_OPTIN_CAMPAIGNS_SETTINGS_PAGE);
            $in_post = add_query_arg('optin-type', 'inpost', MAILOPTIN_OPTIN_CAMPAIGNS_SETTINGS_PAGE);
            $notification_bar = add_query_arg('optin-type', 'bar', MAILOPTIN_OPTIN_CAMPAIGNS_SETTINGS_PAGE);
            $slide_in = add_query_arg('optin-type', 'slidein', MAILOPTIN_OPTIN_CAMPAIGNS_SETTINGS_PAGE);

            $all_menu_active = isset($_GET['page']) && !isset($_GET['optin-type']) ? 'mailoptin-type-active' : null;
            $lightbox_menu_active = isset($_GET['optin-type']) && $_GET['page'] == 'mailoptin-optin-campaigns' && $_GET['optin-type'] == 'lightbox' ? 'mailoptin-type-active' : null;
            $sidebar_menu_active = isset($_GET['optin-type']) && $_GET['page'] == 'mailoptin-optin-campaigns' && $_GET['optin-type'] == 'sidebar' ? 'mailoptin-type-active' : null;
            $before_after_post_menu_active = isset($_GET['optin-type']) && $_GET['page'] == 'mailoptin-optin-campaigns' && $_GET['optin-type'] == 'inpost' ? 'mailoptin-type-active' : null;
            $notification_bar_menu_active = isset($_GET['optin-type']) && $_GET['page'] == 'mailoptin-optin-campaigns' && $_GET['optin-type'] == 'bar' ? 'mailoptin-type-active' : null;
            $slide_in_menu_active = isset($_GET['optin-type']) && $_GET['page'] == 'mailoptin-optin-campaigns' && $_GET['optin-type'] == 'slidein' ? 'mailoptin-type-active' : null;
            ?>
            <div id="mailoptin-sub-bar">
                <div class="mailoptin-new-toolbar mailoptin-clear">
                    <h4><?php _e('Filter by Optin Type', 'mailoptin'); ?></h4>
                    <ul class="mailoptin-design-options">
                        <li>
                            <a href="<?php echo MAILOPTIN_OPTIN_CAMPAIGNS_SETTINGS_PAGE; ?>" class="<?php echo $all_menu_active; ?>">
                                <?php _e('All', 'mailoptin'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $lightbox_url; ?>" class="<?php echo $lightbox_menu_active; ?>">
                                <?php _e('Lightbox', 'mailoptin'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $in_post; ?>" class="<?php echo $before_after_post_menu_active; ?>">
                                <?php _e('In-Post ', 'mailoptin'); ?>
                            </a>
                        </li>
                        <li>
                        <li>
                            <a href="<?php echo $sidebar_url; ?>" class="<?php echo $sidebar_menu_active; ?>">
                                <?php _e('Sidebar/Widget', 'mailoptin'); ?>
                            </a></li>
                        <li>
                            <a href="<?php echo $notification_bar; ?>" class="<?php echo $notification_bar_menu_active; ?>">
                                <?php _e('Notification-Bar', 'mailoptin'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $slide_in; ?>" class="<?php echo $slide_in_menu_active; ?>">
                                <?php _e('Slide-In', 'mailoptin'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        <?php }
    }


    /**
     * Save screen option.
     *
     * @param string $status
     * @param string $option
     * @param string $value
     *
     * @return mixed
     */
    public function set_screen($status, $option, $value)
    {
        return $value;
    }

    /**
     * Screen options
     */
    public function screen_option()
    {
        $option = 'per_page';
        $args = array(
            'label' => __('Optin Campaigns', 'mailoptin'),
            'default' => 8,
            'option' => 'optin_forms_per_page',
        );
        add_screen_option($option, $args);
        $this->optin_forms_instance = OptinCampaign_List::get_instance();
    }


    /**
     * Build the settings page structure. I.e tab, sidebar.
     */
    public function settings_admin_page_callback()
    {
        add_filter('wp_cspa_main_current_page_url', array($this, 'campaign_settings_page_url'), 10, 3);

        if (!empty($_GET['view']) && $_GET['view'] == 'add-new-optin') {
            AddOptinCampaign::get_instance()->settings_admin_page();
        } else {
            // Hook the OptinCampaign_List table to Custom_Settings_Page_Api main content filter.
            add_action('wp_cspa_main_content_area', array($this, 'wp_list_table'), 10, 2);
            add_action('wp_cspa_before_post_body_content', array($this, 'optin_theme_sub_header'), 10, 2);
            add_action('wp_cspa_before_closing_header', [$this, 'add_new_optin_form_button']);

            $instance = Custom_Settings_Page_Api::instance();
            $instance->option_name(MO_OPTIN_CAMPAIGN_WP_OPTION_NAME);
            $instance->page_header(__('Optin Campaigns', 'mailoptin'));
            $this->register_core_settings($instance, true);
            $instance->build(true);

        }
    }

    public function add_new_optin_form_button()
    {
        $url = add_query_arg('view', 'add-new-optin', MAILOPTIN_OPTIN_CAMPAIGNS_SETTINGS_PAGE);
        echo "<a class=\"add-new-h2\" href=\"$url\">" . __('Add New', 'mailoptin') . '</a>';
    }


    /**
     * Callback to output content of OptinCampaign_List table.
     *
     * @param string $content
     * @param string $option_name settings Custom_Settings_Page_Api option name.
     *
     * @return string
     */
    public function wp_list_table($content, $option_name)
    {
        if ($option_name != MO_OPTIN_CAMPAIGN_WP_OPTION_NAME) {
            return $content;
        }

        $this->optin_forms_instance->prepare_items();

        ob_start();
        $this->optin_forms_instance->display();
        return ob_get_clean();
    }

    /**
     * @return OptinCampaigns
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