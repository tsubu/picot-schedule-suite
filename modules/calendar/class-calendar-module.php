<?php
/**
 * Business day calendar module for Picot Schedule Suite.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PSS_Calendar_Module')) {
class PSS_Calendar_Module
{
    public function __construct()
    {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_post_data'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_filter('manage_bd_calendar_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_bd_calendar_posts_custom_column', array($this, 'render_custom_columns'), 10, 2);

        if (!shortcode_exists('business_calendar')) {
            add_shortcode('business_calendar', array($this, 'render_shortcode'));
        }
    }

    public function register_post_type()
    {
        $parent = defined('PSS_MENU_SLUG') ? PSS_MENU_SLUG : 'picot-schedule-suite';

        $labels = array(
            'name'               => __('営業日カレンダー', 'picot-schedule-suite'),
            'singular_name'      => __('営業日カレンダー', 'picot-schedule-suite'),
            'menu_name'          => __('営業日カレンダー', 'picot-schedule-suite'),
            'name_admin_bar'     => __('営業日カレンダー', 'picot-schedule-suite'),
            'add_new'            => __('新規追加', 'picot-schedule-suite'),
            'add_new_item'       => __('新しいカレンダーを追加', 'picot-schedule-suite'),
            'new_item'           => __('新しいカレンダー', 'picot-schedule-suite'),
            'edit_item'          => __('カレンダーを編集', 'picot-schedule-suite'),
            'view_item'          => __('カレンダーを表示', 'picot-schedule-suite'),
            'all_items'          => __('営業日カレンダー', 'picot-schedule-suite'),
            'search_items'       => __('カレンダーを検索', 'picot-schedule-suite'),
            'not_found'          => __('カレンダーが見つかりません', 'picot-schedule-suite'),
            'not_found_in_trash' => __('ゴミ箱にカレンダーはありません', 'picot-schedule-suite'),
        );

        register_post_type('bd_calendar', array(
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
            'menu_icon'          => 'dashicons-calendar',
            'supports'           => array('title'),
        ));
    }

    public function admin_enqueue_scripts($hook)
    {
        global $post;

        if (($hook === 'post-new.php' || $hook === 'post.php') && $post && $post->post_type === 'bd_calendar') {
            $base = plugin_dir_url(__FILE__);
            wp_enqueue_style('pss-calendar-admin', $base . 'assets/css/admin.css', array(), PSS_VERSION);
            wp_enqueue_script('pss-calendar-admin', $base . 'assets/js/admin.js', array('jquery'), PSS_VERSION, true);
            wp_localize_script('pss-calendar-admin', 'pssCalendarL10n', array(
                'weekdays'      => array_values(pss_weekday_labels()),
                'monthTitle'    => __('%1$d年 %2$d月', 'picot-schedule-suite'),
                'defaultOpen'   => __('営業日', 'picot-schedule-suite'),
                'newStatus'     => __('新規ステータス', 'picot-schedule-suite'),
                'confirmDelete' => __('このステータスを削除しますか？', 'picot-schedule-suite'),
                'selectWeekday' => __('曜日を選択してください', 'picot-schedule-suite'),
                'confirmBulk'   => __('現在表示されている期間の指定された曜日を一括更新しますか？', 'picot-schedule-suite'),
                'label'         => __('ラベル', 'picot-schedule-suite'),
                'hours'         => __('営業時間', 'picot-schedule-suite'),
                'background'    => __('背景', 'picot-schedule-suite'),
                'text'          => __('文字', 'picot-schedule-suite'),
                'remove'        => __('削除', 'picot-schedule-suite'),
                'noHolidayRule' => __('適用しない（祝日名のみ表示も可）', 'picot-schedule-suite'),
                'holidays'      => PSS_Japan_Holidays::for_years((int) wp_date('Y') - 1, (int) wp_date('Y') + 3),
            ));
        }
    }

    public function enqueue_frontend_scripts()
    {
        $this->register_frontend_assets();

        if (pss_current_content_has_shortcode('business_calendar')) {
            $this->enqueue_frontend_assets();
        }
    }

    /**
     * Register front-end assets (idempotent).
     */
    public function register_frontend_assets()
    {
        if (wp_style_is('pss-calendar-style', 'registered')) {
            return;
        }
        $base = plugin_dir_url(__FILE__);
        wp_register_style('pss-calendar-style', $base . 'assets/css/style.css', array(), PSS_VERSION);
        wp_register_script('pss-calendar-script', $base . 'assets/js/script.js', array('jquery'), PSS_VERSION, true);
    }

    /**
     * Enqueue front-end assets (only on pages that render the calendar).
     */
    public function enqueue_frontend_assets()
    {
        $this->register_frontend_assets();
        wp_enqueue_style('pss-calendar-style');
        wp_enqueue_script('pss-calendar-script');
    }

    public function add_meta_boxes()
    {
        add_meta_box(
            'bd_calendar_settings',
            __('カレンダー設定', 'picot-schedule-suite'),
            array($this, 'render_meta_box'),
            'bd_calendar',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post)
    {
        wp_nonce_field('bd_calendar_save_action', 'bd_calendar_nonce');

        $calendar_data = pss_decode_meta_array(get_post_meta($post->ID, '_bd_calendar_data', true));
        $config_data = pss_decode_meta_array(get_post_meta($post->ID, '_bd_calendar_config', true));

        if (empty($config_data)) {
            $config_data = array(
                'items' => array(
                    'open'   => array('label' => __('営業日', 'picot-schedule-suite'), 'color' => '#ffffff', 'text_color' => '#333333', 'default' => true),
                    'closed' => array('label' => __('休業日', 'picot-schedule-suite'), 'color' => '#ffcccc', 'text_color' => '#cc0000'),
                ),
            );
        }

        include plugin_dir_path(__FILE__) . 'templates/admin-editor.php';
    }

    public function save_post_data($post_id)
    {
        if (!isset($_POST['bd_calendar_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bd_calendar_nonce'])), 'bd_calendar_save_action')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['bd_calendar_data']) && is_string($_POST['bd_calendar_data'])) {
            $decoded = pss_get_json_array_from_post('bd_calendar_data');
            if ($decoded !== null) {
                update_post_meta($post_id, '_bd_calendar_data', pss_encode_json_meta($decoded));
            }
        }

        if (isset($_POST['bd_calendar_config']) && is_string($_POST['bd_calendar_config'])) {
            $decoded = pss_get_json_array_from_post('bd_calendar_config');
            if ($decoded !== null) {
                update_post_meta($post_id, '_bd_calendar_config', pss_encode_json_meta($decoded));
            }
        }
    }

    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'business_calendar');

        if (!$atts['id']) {
            return '';
        }

        $post_id = intval($atts['id']);
        $calendar_data = get_post_meta($post_id, '_bd_calendar_data', true);
        $config_data = get_post_meta($post_id, '_bd_calendar_config', true);

        if (!$calendar_data) {
            return pss_missing_data_notice(__('営業日カレンダーのデータが見つかりません。', 'picot-schedule-suite'), $post_id);
        }

        $calendar_data = pss_decode_meta_array($calendar_data);
        $config_data = pss_decode_meta_array($config_data);

        $this->enqueue_frontend_assets();

        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/calendar-display.php';
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
            echo '<code>[business_calendar id="' . esc_attr($post_id) . '"]</code>';
        }
    }
}
} // class_exists PSS_Calendar_Module
