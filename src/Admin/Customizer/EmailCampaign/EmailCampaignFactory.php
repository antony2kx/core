<?php

namespace MailOptin\Core\Admin\Customizer\EmailCampaign;

use MailOptin\Core\EmailCampaignTemplates\AbstractEmailCampaign;
use MailOptin\Core\Repositories\EmailCampaignRepository;

/**
 * Create an email campaign class instance from its ID.
 *
 * @package MailOptin\Core\Admin\Customizer\EmailCampaign
 */
class EmailCampaignFactory
{
    /**
     * @param int $optin_campaign_id
     * @param null|\WP_Customize_Manager $wp_customize
     *
     * @return false|AbstractEmailCampaign
     */
    public static function make($email_campaign_id, $wp_customize = null)
    {
        $db_email_template_class = EmailCampaignRepository::get_template_class($email_campaign_id);
        $email_campaign_type = EmailCampaignRepository::get_email_campaign_type($email_campaign_id);

        // convert e.g new_publish_post to NewPublishPost
        $email_campaign_type_namespace = str_replace(' ', '', ucwords(str_replace('_', ' ', $email_campaign_type)));

        do_action('mailoptin_email_template_before_forge', $email_campaign_id, $db_email_template_class);

        // first $template_class is the template class namespace.
        $email_campaign_class = apply_filters(
            'mailoptin_register_template_class',
            "\\MailOptin\\Core\\EmailCampaignTemplates\\$email_campaign_type_namespace\\$db_email_template_class",
            $email_campaign_id,
            $db_email_template_class
        );

        if (!class_exists($email_campaign_class)) {
            return false;
        }

        return new $email_campaign_class($email_campaign_id, $wp_customize);
    }

}