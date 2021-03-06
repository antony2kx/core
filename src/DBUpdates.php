<?php

namespace MailOptin\Core;


class DBUpdates
{
    public static $instance;

    const DB_VER = 1;

    public function init_options()
    {
        add_site_option('mo_db_ver', 0);
    }

    public function maybe_update()
    {
        $this->init_options();

        if (get_site_option('mo_db_ver') >= self::DB_VER) {
            return;
        }

        // update plugin
        $this->update();
    }

    public function update()
    {
        // no PHP timeout for running updates
        set_time_limit(0);

        // this is the current database schema version number
        $current_db_ver = get_site_option('mo_db_ver');

        // this is the target version that we need to reach
        $target_db_ver = self::DB_VER;

        // run update routines one by one until the current version number
        // reaches the target version number
        while ($current_db_ver < $target_db_ver) {
            // increment the current db_ver by one
            $current_db_ver++;

            // each db version will require a separate update function
            $update_method = "update_routine_{$current_db_ver}";

            if (method_exists($this, $update_method)) {
                call_user_func(array($this, $update_method));
            }

            // update the option in the database, so that this process can always
            // pick up where it left off
            update_site_option('mo_db_ver', $current_db_ver);
        }
    }

    public function update_routine_1()
    {
        global $wpdb;

        $table = $wpdb->prefix . Core::conversions_table_name;

        $sql = "ALTER TABLE $table CHANGE date_added date_added DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00';";

        $wpdb->query($sql);
    }

    /** Singleton poop */
    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}