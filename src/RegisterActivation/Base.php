<?php

namespace MailOptin\Core\RegisterActivation;

class Base
{
    public static function run_install()
    {
        if (is_multisite() && apply_filters('mo_stop_multisite_install', true)) {
            if (!function_exists('deactivate_plugins')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            deactivate_plugins(plugin_basename(MAILOPTIN_SYSTEM_FILE_PATH));
            wp_die(self::multisite_deactivate_notice());
        }

        global $wpdb;

        if (is_multisite()) {
            foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs LIMIT 100") as $blog_id) {
                switch_to_blog($blog_id);
                self::mo_install();
                restore_current_blog();
            }

        } else {
            self::mo_install();
        }
    }

    /**
     * Notice shown when multisite activation fails.
     *
     * @return string
     */
    public static function multisite_deactivate_notice()
    {
        $data = '<div class="error"><p>';
        $data .= sprintf(
            __("%s cannot work on WordPress multisite. %s for multisite compatibility and usage.", 'mailoptin'),
            '<strong>' . __('MailOptin Lite', 'mailoptin') . '</strong> ',
            '<a href="https://mailoptin.io/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=multisite_install" target="_blank">' . __('Upgrade to PRO or Agency package', 'mailoptin') . '</a>'
        );
        $data .= '</p></div>';

        return $data;
    }

    /**
     * Run plugin install / activation action when new blog is created in multisite setup.
     *
     * @param int $blog_id
     * @param int $user_id
     */
    public static function multisite_new_blog_install($blog_id, $user_id)
    {
        if (is_plugin_active_for_network('mailoptin/mailoptin.php')) {
            switch_to_blog($blog_id);
            self::mo_install();
            restore_current_blog();
        }
    }

    /**
     * Perform plugin activation / installation.
     */
    public static function mo_install()
    {
        if (!current_user_can('activate_plugins') || get_option('mo_plugin_activated') == 'true') {
            return;
        }

        CreateDBTables::make();
        self::setting_settings();

        add_option('mo_install_date', current_time('mysql'));
        add_option('mo_plugin_activated', 'true');
    }

    /**
     * Default values for settings
     */
    public static function setting_settings()
    {
        add_option(MAILOPTIN_SETTINGS_DB_OPTION_NAME, array(
                'from_name' => get_bloginfo(),
                'from_email' => get_bloginfo('admin_email'),
                'reply_to' => get_bloginfo('admin_email')
            )
        );
    }
}