<?php
/**
 * Weekly schedule module for Picot Schedule Suite.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PSS_Weekly_Module')) {
class PSS_Weekly_Module
{
    public function __construct()
    {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_post_data'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_filter('manage_bd_weekly_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_bd_weekly_posts_custom_column', array($this, 'render_custom_columns'), 10, 2);

        if (!shortcode_exists('weekly_schedule')) {
            add_shortcode('weekly_schedule', array($this, 'render_shortcode'));
        }
    }

    public function register_post_type()
    {
        $parent = defined('PSS_MENU_SLUG') ? PSS_MENU_SLUG : 'picot-schedule-suite';

        $labels = array(
            'name'               => __('週間スケジュール', 'picot-schedule-suite'),
            'singular_name'      => __('週間スケジュール', 'picot-schedule-suite'),
            'menu_name'          => __('週間スケジュール', 'picot-schedule-suite'),
            'name_admin_bar'     => __('週間スケジュール', 'picot-schedule-suite'),
            'add_new'            => __('新規追加', 'picot-schedule-suite'),
            'add_new_item'       => __('新しいスケジュールを追加', 'picot-schedule-suite'),
            'new_item'           => __('新しいスケジュール', 'picot-schedule-suite'),
            'edit_item'          => __('スケジュールを編集', 'picot-schedule-suite'),
            'view_item'          => __('スケジュールを表示', 'picot-schedule-suite'),
            'all_items'          => __('週間スケジュール', 'picot-schedule-suite'),
            'search_items'       => __('スケジュールを検索', 'picot-schedule-suite'),
            'not_found'          => __('スケジュールが見つかりません', 'picot-schedule-suite'),
            'not_found_in_trash' => __('ゴミ箱にスケジュールはありません', 'picot-schedule-suite'),
        );

        register_post_type('bd_weekly', array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => $parent,
            'query_var'          => false,
            'rewrite'            => false,
            'show_in_rest'       => false,
            'exclude_from_search' => true,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_icon'          => 'dashicons-clock',
            'supports'           => array('title'),
        ));
    }

    public function admin_enqueue_scripts($hook)
    {
        global $post;

        if (($hook === 'post-new.php' || $hook === 'post.php') && $post && $post->post_type === 'bd_weekly') {
            $base = plugin_dir_url(__FILE__);
            wp_enqueue_style('pss-weekly-admin', $base . 'assets/css/admin.css', array(), PSS_VERSION);
            wp_enqueue_script('pss-weekly-admin', $base . 'assets/js/admin.js', array('jquery'), PSS_VERSION, true);
            wp_localize_script('pss-weekly-admin', 'pssWeeklyL10n', array(
                'weekdays'      => pss_weekday_labels_by_key(),
                'cornerLabel'   => __('時間帯 / 曜日', 'picot-schedule-suite'),
                'slotLabel'     => __('ラベル (例: 午前)', 'picot-schedule-suite'),
                'slotTime'      => __('時間 (例: 9:00-12:00)', 'picot-schedule-suite'),
                'remove'        => __('削除', 'picot-schedule-suite'),
                'confirmDelete' => __('この時間帯を削除しますか？', 'picot-schedule-suite'),
                'untitled'      => __('（名称未設定）', 'picot-schedule-suite'),
            ));
        }
    }

    public function enqueue_frontend_scripts()
    {
        if (pss_current_content_has_shortcode('weekly_schedule')) {
            $this->enqueue_frontend_assets();
        }
    }

    /**
     * Enqueue front-end styles (only on pages that render a weekly schedule).
     */
    public function enqueue_frontend_assets()
    {
        $this->register_frontend_assets();
        wp_enqueue_style('pss-weekly-style');
    }

    /**
     * Register front-end styles (idempotent; also used by the block).
     */
    public function register_frontend_assets()
    {
        if (!wp_style_is('pss-weekly-style', 'registered')) {
            wp_register_style('pss-weekly-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), PSS_VERSION);
        }
    }

    public function add_meta_boxes()
    {
        add_meta_box(
            'bd_weekly_settings',
            __('週間スケジュール設定', 'picot-schedule-suite'),
            array($this, 'render_meta_box'),
            'bd_weekly',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post)
    {
        wp_nonce_field('bd_weekly_save_action', 'bd_weekly_nonce');

        $weekly_data = pss_decode_meta_array(get_post_meta($post->ID, '_bd_weekly_schedule', true));
        if (empty($weekly_data)) {
            $weekly_data = array('time_slots' => array(), 'schedule' => array());
        }

        include plugin_dir_path(__FILE__) . 'templates/admin-editor.php';
    }

    public function save_post_data($post_id)
    {
        if (!isset($_POST['bd_weekly_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['bd_weekly_nonce'])), 'bd_weekly_save_action')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['bd_weekly_schedule']) && is_string($_POST['bd_weekly_schedule'])) {
            $decoded = pss_get_json_array_from_post('bd_weekly_schedule');
            if ($decoded !== null) {
                update_post_meta($post_id, '_bd_weekly_schedule', pss_encode_json_meta($decoded));
            }
        }
    }

    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'weekly_schedule');

        if (!$atts['id']) {
            return '';
        }

        $post_id = intval($atts['id']);
        $weekly_data = get_post_meta($post_id, '_bd_weekly_schedule', true);

        if (!$weekly_data) {
            return pss_missing_data_notice(__('週間スケジュールのデータが見つかりません。', 'picot-schedule-suite'), $post_id);
        }

        $data = pss_decode_meta_array($weekly_data);

        $this->enqueue_frontend_assets();

        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/weekly-schedule-display.php';
        return ob_get_clean();
    }

    public function add_custom_columns($columns)
    {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['shortcode'] = __('ショートコード', 'picot-schedule-suite');
            }
        }
        return $new_columns;
    }

    public function render_custom_columns($column, $post_id)
    {
        if ($column === 'shortcode') {
            echo '<code>[weekly_schedule id="' . esc_attr($post_id) . '"]</code>';
        }
    }
}
} // class_exists PSS_Weekly_Module
