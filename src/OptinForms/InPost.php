<?php

namespace MailOptin\Core\OptinForms;

use MailOptin\Core\Admin\Customizer\OptinForm\OptinFormFactory;
use MailOptin\Core\Repositories\OptinCampaignsRepository as Repository;


class InPost
{
    public function __construct()
    {
        add_filter('the_content', [$this, 'insert_optin']);
    }

    public function insert_optin($content)
    {
        // needed to prevent the optin from showing on post excerpt (on homepage / post listing)
        if (is_front_page() || !is_singular()) return $content;

        /**
         * get_queried_object_id() could be the ID of a term, category, author etc depending on view context.
         * @see \WP_Query::get_queried_object() from ln 4106
         */
        $post_id = is_singular() ? get_queried_object_id() : 0;

        /** @var array $post_categories categories of current post */
        $post_categories = wp_get_post_categories($post_id);

        /** @var string $post_post_type post type of current post */
        $post_post_type = get_post_type($post_id);

        $optin_ids = get_transient('mo_get_optin_ids_inpost_display');

        if ($optin_ids === false) {
            $optin_ids = Repository::get_inpost_optin_ids();
            set_transient('mo_get_optin_ids_inpost_display', $optin_ids, HOUR_IN_SECONDS);
        }

        foreach ($optin_ids as $id) {

            do_action('mailoptin_before_inpost_optin_display_determinant', $id, $optin_ids, $post_id);

            $optin_position = Repository::get_customizer_value($id, 'inpost_form_optin_position');
            $optin_position = empty($optin_position) ? 'after_content' : $optin_position;

            $optin_uuid = Repository::get_optin_campaign_uuid($id);

            // if optin is not enabled, pass.
            if (!Repository::get_customizer_value($id, 'activate_optin')) {
                continue;
            }

            // if optin is disabled for logged users, continue
            if (Repository::get_customizer_value($id, 'hide_logged_in') && is_user_logged_in()) {
                continue;
            }

            if (Repository::get_customizer_value($id, 'load_optin_globally')) {

                $optinInstance = OptinFormFactory::make(intval($id));
                $optin_form_fonts = $optinInstance->get_optin_form_fonts();
                $optin_form = $optinInstance->get_optin_form_structure();
                if (!empty($optin_form_fonts)) {
                    $optin_form .= "<script type='text/javascript'>jQuery(function(){WebFont.load({google: {families: [$optin_form_fonts]}});});</script>";
                }
                $optin_form .= "<script type='text/javascript'>jQuery(function(){jQuery.MailOptin.track_impression('$optin_uuid')});</script>";

                if ('before_content' == $optin_position) {
                    $content = $optin_form . $content;
                } else {
                    $content .= $optin_form;
                }

            } else {
                $determinant = array();
                $posts_never_load = Repository::get_customizer_value($id, 'posts_never_load');
                $pages_never_load = Repository::get_customizer_value($id, 'pages_never_load');
                $cpt_never_load = Repository::get_customizer_value($id, 'cpt_never_load');
                $post_categories_load = Repository::get_customizer_value($id, 'post_categories_load');
                $exclusive_post_types_posts_load = Repository::get_customizer_value($id, 'exclusive_post_types_posts_load');
                $post_types_load = Repository::get_customizer_value($id, 'exclusive_post_types_load');

                // if current post should never contain optin, return false.
                if (!empty($posts_never_load) && in_array($post_id, $posts_never_load)) {
                    $determinant['posts_never_load'] = false;
                }

                // if current page should never contain optin, return false.
                if (!empty($pages_never_load) && is_page($post_id) && in_array($post_id, $pages_never_load)) {
                    $determinant['pages_never_load'] = false;
                }

                // if current CPT post should never contain optin, return false.
                if (!empty($cpt_never_load) && in_array($post_id, $cpt_never_load)) {
                    $determinant['cpt_never_load'] = false;
                }

                // if current post category contain a category that optin should load for, return true.
                // array_intersect() return array element that exist in both comparison arrays.
                if (!empty($post_categories_load)) {
                    $intersect = array_intersect($post_categories, $post_categories_load);
                    if (empty($intersect)) {
                        $determinant['post_categories_load'] = false;
                    }
                }

                // if current post isn't found in a set of all cpt posts to display optin for, return false.
                if (!empty($exclusive_post_types_posts_load) && !in_array($post_id, $exclusive_post_types_posts_load)) {
                    $determinant['exclusive_post_types_posts_load'] = false;
                }

                if (!empty($post_types_load) && !in_array($post_post_type, $post_types_load)) {
                    $determinant['post_types_load'] = false;
                }

                // result of the algorithmic calculation :D
                if (array_search(false, $determinant)) {
                    continue;
                } else {
                    $optinInstance = OptinFormFactory::make(intval($id));
                    $optin_form_fonts = $optinInstance->get_optin_form_fonts();
                    $optin_form = $optinInstance->get_optin_form_structure();
                    if (!empty($optin_form_fonts)) {
                        $optin_form .= "<script type='text/javascript'>jQuery(function(){WebFont.load({google: {families: [$optin_form_fonts]}});});</script>";
                    }

                    $optin_form .= "<script type='text/javascript'>jQuery(function(){jQuery.MailOptin.track_impression('$optin_uuid')});</script>";

                    if ('before_content' == $optin_position) {
                        $content = $optin_form . $content;
                    } else {
                        $content .= $optin_form;
                    }
                }

                do_action('mailoptin_after_inpost_optin_display_determinant', $id, $optin_ids, $post_id);
            }
        }

        return $content;
    }

    /**
     * @return InPost
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