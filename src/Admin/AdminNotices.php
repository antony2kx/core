<?php
/**
 * Copyright (C) 2016  Agbonghama Collins <me@w3guy.com>
 */

namespace MailOptin\Core\Admin;

use MailOptin\Core\Repositories\EmailCampaignRepository;
use MailOptin\Core\Repositories\OptinCampaignsRepository;
use MailOptin\Core\Repositories\OptinConversionsRepository;
use PAnD as PAnD;

class AdminNotices
{
    public function __construct()
    {
        add_action('admin_notices', array($this, 'template_class_not_found'));
        add_action('admin_notices', array($this, 'optin_class_not_found'));
        add_action('admin_notices', array($this, 'failed_campaign_retried'));
        add_action('admin_notices', array($this, 'optin_campaign_count_limit_exceeded'));
        add_action('admin_notices', array($this, 'email_campaign_count_limit_exceeded'));

        add_action('admin_notices', array($this, 'optin_conversion_limit_exceeded'));

        add_filter('removable_query_args', array($this, 'removable_query_args'));
    }

    public function removable_query_args($args)
    {
        $args[] = 'email-campaign-error';

        return $args;
    }

    /**
     * Template class not found - admin notice.
     */
    public function template_class_not_found()
    {
        if (!is_super_admin(get_current_user_id()))
            return;

        if (isset($_GET['email-campaign-error']) && $_GET['email-campaign-error'] == 'class-not-found') : ?>
            <div id="message" class="updated notice is-dismissible">
                <p>
                    <?php
                    _e('There was an error fetching email campaign template dependency.', 'mailoptin');
                    ?>
                </p>
            </div>
        <?php endif;
    }

    /**
     * Optin template class not found - admin notice.
     */
    public function optin_class_not_found()
    {
        if (!is_super_admin(get_current_user_id()))
            return;

        if (isset($_GET['optin-error']) && $_GET['optin-error'] == 'class-not-found') : ?>
            <div id="message" class="updated notice is-dismissible">
                <p>
                    <?php _e('There was an error fetching optin dependency. Try again or select another template.', 'mailoptin'); ?>
                </p>
            </div>
        <?php endif;
    }

    /**
     * Template class not found - admin notice.
     */
    public function failed_campaign_retried()
    {
        if (!is_super_admin(get_current_user_id())) return;

        if (isset($_GET['failed-campaign']) && $_GET['failed-campaign'] == 'retried') : ?>
            <div id="message" class="updated notice is-dismissible">
                <p>
                    <?php _e('Failed email campaigned retried.', 'mailoptin'); ?>
                </p>
            </div>
        <?php endif;
    }


    /**
     * Display notice when limit of created optin campaign is exceeded
     */
    public function optin_campaign_count_limit_exceeded()
    {
        if (defined('MAILOPTIN_DETACH_LIBSODIUM')) return;

        if (!PAnD::is_admin_notice_active('optin-campaign-count-limit-exceeded-3')) {
            return;
        }

        if (OptinCampaignsRepository::campaign_count() < MO_LITE_OPTIN_CAMPAIGN_LIMIT) return;

        if (\MailOptin\Core\current_url_with_query_string() != MAILOPTIN_OPTIN_CAMPAIGNS_SETTINGS_PAGE) return;

        $upgrade_url = 'https://mailoptin.io/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=optin_campaign_limit';
        $notice = sprintf(
            __('%sYou have exceeded the maximum of %d optin campaign that you can create%s. Upgrade now to %s to remove this limit with ton of conversion boosting features.', 'mailoptin'),
            '<strong>', MO_LITE_OPTIN_CAMPAIGN_LIMIT, '</strong>', '<a href="' . $upgrade_url . '" target="_blank"><strong>MailOptin premium</strong></a>'
        );

        echo '<div data-dismissible="optin-campaign-count-limit-exceeded-3" class="updated notice notice-success is-dismissible">';
        echo "<p>$notice</strong></p>";
        echo '</div>';
    }

    /**
     * Display notice when limit of created email campaign is exceeded
     */
    public function email_campaign_count_limit_exceeded()
    {
        if (defined('MAILOPTIN_DETACH_LIBSODIUM')) return;

        if (!PAnD::is_admin_notice_active('email-campaign-count-limit-exceeded-3')) {
            return;
        }

        if (EmailCampaignRepository::campaign_count() < 1) return;

        if (\MailOptin\Core\current_url_with_query_string() != MAILOPTIN_EMAIL_CAMPAIGNS_SETTINGS_PAGE) return;

        $upgrade_url = 'https://mailoptin.io/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=email_campaign_limit';
        $notice = sprintf(__('Upgrade to %s now to create multiple email campaigns with advance targeting and availability of your email list subscribers as recipients.', 'mailoptin'),
            '<a href="' . $upgrade_url . '" target="_blank">MailOptin premium</a>'
        );
        echo '<div data-dismissible="email-campaign-count-limit-exceeded-3" class="updated notice notice-success is-dismissible">';
        echo "<p>$notice</p>";
        echo '</div>';
    }

    public function optin_conversion_limit_exceeded()
    {
        if (defined('MAILOPTIN_DETACH_LIBSODIUM')) return;

        if (!PAnD::is_admin_notice_active('optin-conversion-limit-exceeded-1')) {
            return;
        }

        if (OptinConversionsRepository::month_conversion_count() < MO_LITE_OPTIN_CONVERSION_LIMIT) return;

        $upgrade_url = 'https://mailoptin.io/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=optin_conversion_limit';
        $notice = sprintf(
            __('The monthly limit of %2$d optin subscribers has been exceeded. %3$sUpgrade to %1$s to capture unlimited subscribers and stop losing subscribers and customers.%4$s ', 'mailoptin'),
            '<a href="' . $upgrade_url . '" target="_blank">MailOptin premium</a>',
            MO_LITE_OPTIN_CONVERSION_LIMIT,
            '<strong>',
            '</strong>'
        );

        echo '<div data-dismissible="optin-conversion-limit-exceeded-1" class="updated notice notice-success">';
        echo "<p>$notice</p>";
        echo '</div>';
    }

    /**
     * @return AdminNotices
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