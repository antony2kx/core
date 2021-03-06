<?php
/**
 * Copyright (C) 2016  Agbonghama Collins <me@w3guy.com>
 */

namespace MailOptin\Core\Admin\SettingsPage;

use MailOptin\Core\Core;
use MailOptin\Core\Repositories\EmailCampaignRepository as ER;
use MailOptin\Core\Repositories\EmailCampaignRepository;
use MailOptin\Core\Triggers\NewPublishPost;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Campaign_Log_List extends \WP_List_Table
{
    private $table;

    /** @var \wpdb */
    private $wpdb;

    /**
     * Class constructor
     */
    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $this->wpdb->prefix . Core::campaign_log_table_name;
        parent::__construct(array(
            'singular' => __('campaign_log', 'mailoptin'), //singular name of the listed records
            'plural' => __('campaign_logs', 'mailoptin'), //plural name of the listed records
            'ajax' => false //does this table support ajax?
        ));
    }

    /**
     * Retrieve campaign log data from the database
     *
     * @param int $per_page
     * @param int $current_page
     *
     * @return mixed
     */
    public function get_campaign_log($per_page, $current_page = 1)
    {
        $offset = ($current_page - 1) * $per_page;
        $sql = "SELECT * FROM {$this->table}";
        $sql .= " ORDER BY id DESC";
        $sql .= " LIMIT $per_page";
        if ($current_page > 1) {
            $sql .= "  OFFSET $offset";
        }

        $result = $this->wpdb->get_results($sql, 'ARRAY_A');

        return $result;
    }

    /**
     * Retry failed email campaign.
     *
     * @param int $campaign_log_id
     */
    public function retry_failed_email_campaign($campaign_log_id)
    {
        $email_campaign_id = $this->get_email_campaign_id_from_campaign_log($campaign_log_id);
        $campaign_type = ER::get_email_campaign_type($email_campaign_id);

        if ($campaign_type == ER::NEW_PUBLISH_POST) {
            NewPublishPost::get_instance()->send_campaign($email_campaign_id, $campaign_log_id);
        }

        wp_redirect(add_query_arg('failed-campaign', 'retried', MAILOPTIN_CAMPAIGN_LOG_SETTINGS_PAGE));
        exit;
    }

    /**
     * Delete a campaign log record.
     *
     * @param int $id campaign_log ID
     *
     * @return false|int
     */
    public function delete_a_campaign_log($id)
    {
        return $this->wpdb->delete(
            $this->table,
            array('id' => $id),
            array('%d')
        );
    }

    /**
     * Can email campaign be retried?
     *
     * @param int $campaign_log_id
     *
     * @return null|string
     */
    public function is_retry($campaign_log_id)
    {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT status FROM $this->table WHERE id = %d",
                $campaign_log_id
            )
        );

        return in_array($result, ['failed', 'queued', 'draft']);
    }

    /**
     * Get email campaign ID from campaign log ID
     *
     * @param int $campaign_log_id
     *
     * @return null|int
     */
    public function get_email_campaign_id_from_campaign_log($campaign_log_id)
    {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT email_campaign_id FROM $this->table WHERE id = %d",
                $campaign_log_id
            )
        );

        // cast to integer if result isn't null.
        return !is_null($result) ? absint($result) : $result;
    }

    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public function record_count()
    {
        $sql = "SELECT COUNT(*) FROM $this->table";

        return $this->wpdb->get_var($sql);
    }

    /** Text displayed when no campaign log is available */
    public function no_items()
    {
        _e('No email campaign has been sent yet.', 'mailoptin');
    }


    /**
     *  Associative array of columns
     *
     * @return array
     */
    public function get_columns()
    {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'subject' => __('Subject', 'mailoptin'),
            'content_html' => __('HTML', 'mailoptin'),
            'content_text' => __('Plain Text', 'mailoptin'),
            'status' => __('Status', 'mailoptin'),
            'email_campaign' => __('Campaign Name', 'mailoptin'),
            'date_time' => __('Date & Time', 'mailoptin'),
        );

        return $columns;
    }

    /**
     * Render the bulk edit checkbox
     *
     * @param array $item
     *
     * @return string
     */
    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id']
        );
    }

    /**
     * Column for subject
     *
     * @param array $item an array of DB data
     *
     * @return string
     */
    function column_subject($item)
    {
        $delete_nonce = wp_create_nonce('mo_delete_campaign_log');
        $retry_nonce = wp_create_nonce('mo_retry_failed_campaign');
        $name = '<strong>' . $item['title'] . '</strong>';
        $campaign_log_id = absint($item['id']);

        $actions = array(
            'delete' => sprintf(
                '<a href="?page=%s&action=%s&campaign-log-id=%s&_wpnonce=%s">%s</a>',
                esc_attr($_REQUEST['page']), 'delete', $campaign_log_id, $delete_nonce, __('Delete', 'mailoptin')
            ),
        );

        if ($this->is_retry($campaign_log_id)) {
            $actions['retry'] = sprintf(
                '<a href="?page=%s&action=%s&campaign-log-id=%s&_wpnonce=%s">%s</a>',
                esc_attr($_REQUEST['page']),
                'retry',
                $campaign_log_id,
                $retry_nonce,
                __('Retry', 'mailoptin')
            );
        }

        return $name . $this->row_actions($actions);
    }

    /**
     * Column for HTML preview
     *
     * @param array $item
     *
     * @return mixed
     */
    public function column_content_html($item)
    {
        $campaign_id = intval($item['id']);
        $preview_url = add_query_arg(
            ['mailoptin' => 'preview-campaign', 'type' => 'html', 'id' => $campaign_id],
            home_url()
        );

        return "<a class=\"campaign-preview\" href=\"$preview_url\"><span class=\"dashicons dashicons-visibility\"></span></a>";
    }

    /**
     * Column for plain text preview
     *
     * @param array $item
     *
     * @return mixed
     */
    public function column_content_text($item)
    {
        $campaign_id = intval($item['id']);
        $preview_url = add_query_arg(
            ['mailoptin' => 'preview-campaign', 'type' => 'text', 'id' => $campaign_id],
            home_url()
        );

        return "<a class=\"campaign-preview\" href=\"$preview_url\"><span class=\"dashicons dashicons-visibility\"></span></a>";
    }

    /**
     * Column for email campaign
     *
     * @param array $item
     *
     * @return mixed
     */
    public function column_email_campaign($item)
    {
        $email_campaign_id = absint($item['email_campaign_id']);
        $email_campaign_title = EmailCampaignRepository::get_email_campaign_name($email_campaign_id);

        $customize_url = Email_Campaign_List::_campaign_customize_url($email_campaign_id);
        return "<a href=\"$customize_url\">$email_campaign_title</span></a>";
    }

    /**
     * Column for campaign status
     *
     * @param array $item
     *
     * @return mixed
     */
    public function column_status($item)
    {
        $campaign_log_id = absint($item['id']);
        $email_campaign_id = absint($item['email_campaign_id']);
        $email_campaign_title = EmailCampaignRepository::get_email_campaign_name($email_campaign_id);
        $error_log_filename = md5($email_campaign_title . $campaign_log_id);

        $preview_url = add_query_arg(
            ['mailoptin' => 'preview-campaign-error-log', 'id' => $error_log_filename],
            admin_url()
        );

        $log_file = MAILOPTIN_CAMPAIGN_ERROR_LOG . "{$error_log_filename}.log";

        $err_log = '';
        // only display the link to error log if error content isn't empty.
        if (file_exists($log_file) && file_get_contents($log_file) != '') {
            $err_log .= "<div><a class=\"campaign-preview\" href=\"$preview_url\">" . __('Error logs', 'mailoptin') . "</span></a></div>";
        }

        return $item['status'] . $err_log;
    }

    /**
     * Column for campaign status
     *
     * @param array $item
     *
     * @return mixed
     */
    public function column_date_time($item)
    {
        return $item['status_time'];
    }

    /**
     * Returns an associative array containing the bulk action
     *
     * @return array
     */
    public function get_bulk_actions()
    {
        $actions = array(
            'bulk-delete' => 'Delete',
        );

        return $actions;
    }

    /**
     * Handles data query and filter, sorting, and pagination.
     */
    public function prepare_items()
    {
        $this->_column_headers = $this->get_column_info();
        /** Process bulk action */
        $this->process_bulk_action();
        $per_page = $this->get_items_per_page('campaign_log_per_page', 10);
        $current_page = $this->get_pagenum();
        $total_items = self::record_count();
        $this->set_pagination_args(array(
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page' => $per_page //WE have to determine how many items to show on a page
        ));

        $this->items = $this->get_campaign_log($per_page, $current_page);
    }


    /**
     * Process bulk action.
     */
    public function process_bulk_action()
    {
        // bail if user is not an admin or without admin privileges.
        if (!current_user_can('administrator')) {
            return;
        }

        //Detect when a bulk action is being triggered...
        if ('delete' === $this->current_action()) {
            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr($_REQUEST['_wpnonce']);
            if (!wp_verify_nonce($nonce, 'mo_delete_campaign_log')) {
                die('Go get a life script kiddies');
            } else {
                self::delete_a_campaign_log(absint($_GET['campaign-log-id']));
                // esc_url_raw() is used to prevent converting ampersand in url to "#038;"
                // add_query_arg() return the current url
                wp_redirect(esc_url_raw(admin_url('admin.php?page=mailoptin-campaign-log')));
                exit;
            }
        }

        //Detect when a bulk action is being triggered...
        if ('retry' === $this->current_action()) {
            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr($_REQUEST['_wpnonce']);
            if (!wp_verify_nonce($nonce, 'mo_retry_failed_campaign')) {
                wp_die('Go get a life script kiddies');
            } else {
                self::retry_failed_email_campaign(absint($_GET['campaign-log-id']));
                // esc_url_raw() is used to prevent converting ampersand in url to "#038;"
                // add_query_arg() return the current url
                wp_redirect(esc_url_raw(MAILOPTIN_CAMPAIGN_LOG_SETTINGS_PAGE));
                exit;
            }
        }
        // If the delete bulk action is triggered
        if ('bulk-delete' === $this->current_action()) {
            $delete_ids = array_map('absint', $_POST['bulk-delete']);
            // loop over the array of record IDs and delete them
            foreach ($delete_ids as $id) {
                self::delete_a_campaign_log($id);
            }
            wp_redirect(esc_url_raw(add_query_arg()));
            exit;
        }
    }


    /**
     * @return Campaign_Log_List
     */
    public static function get_instance()
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new self($GLOBALS['wpdb']);
        }

        return $instance;
    }
}