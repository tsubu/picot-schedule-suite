<?php
/**
 * Timetable module for Picot Schedule Suite.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PSS_Timetable_Module')) {
class PSS_Timetable_Module
{
    /** Bump when the table schema in create_tables() changes. */
    const DB_VERSION = '1.0.0';

    /** Option that stores the installed schema version. */
    const DB_VERSION_OPTION = 'pss_timetable_db_version';

    /**
     * @param bool $register_hooks Whether to wire WordPress hooks (false during activation setup).
     */
    public function __construct($register_hooks = true)
    {
        global $pss_timetable_module;
        $pss_timetable_module = $this;

        $this->init_language_manager();

        if (!$register_hooks) {
            return;
        }

        add_action('init', array($this, 'init'));
        add_action('init', array($this, 'register_frontend_assets'));
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
    }

    public function init()
    {
        // カスタム投稿タイプの登録
        $this->register_post_types();
        // スキーマが古い・未作成のときだけテーブルを作成（毎リクエストの dbDelta を避ける）
        $this->maybe_upgrade_tables();
    }

    /**
     * Run create_tables() only when the stored schema version differs.
     */
    public function maybe_upgrade_tables()
    {
        if (get_option(self::DB_VERSION_OPTION) === self::DB_VERSION) {
            return;
        }
        $this->create_tables();
    }

    /**
     * 言語管理の初期化
     */
    public function init_language_manager()
    {
        global $pss_timetable_language_manager;

        if (!isset($pss_timetable_language_manager)) {
            $pss_timetable_language_manager = new PSS_Timetable_Language_Manager();
            $pss_timetable_language_manager->handle_language_switch();
        }
    }

    public function register_post_types()
    {
        // 時間割投稿タイプ
        register_post_type('timetable', array(
            'labels' => array(
                'name' => '時間割',
                'singular_name' => '時間割',
                'add_new' => '新規追加',
                'add_new_item' => '新しい時間割を追加',
                'edit_item' => '時間割を編集',
                'new_item' => '新しい時間割',
                'view_item' => '時間割を表示',
                'search_items' => '時間割を検索',
                'not_found' => '時間割が見つかりませんでした',
                'not_found_in_trash' => 'ゴミ箱に時間割はありません'
            ),
            // データは独自テーブルにあり単体表示では中身が出ないため、フロントには公開しない（表示はショートコードのみ）
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_rest' => false,
            'exclude_from_search' => true,
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
            'supports' => array('title'),
        ));
    }

    public function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // 時間割データテーブル
        $table_name = $wpdb->prefix . 'timetable_data';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            timetable_id bigint(20) NOT NULL,
            day_of_week tinyint(1) NOT NULL,
            time_slot varchar(20) NOT NULL,
            content text,
            is_observable tinyint(1) DEFAULT 0,
            comment text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY timetable_id (timetable_id),
            KEY day_time (day_of_week, time_slot)
        ) $charset_collate;";

        // 時間設定テーブル
        $time_table = $wpdb->prefix . 'timetable_times';
        $time_sql = "CREATE TABLE $time_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            timetable_id bigint(20) NOT NULL,
            time_slot varchar(20) NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY timetable_id (timetable_id)
        ) $charset_collate;";

        // コンテンツマスターテーブル
        $content_table = $wpdb->prefix . 'timetable_contents';
        $content_sql = "CREATE TABLE $content_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // 時間マスターテーブル
        $time_master_table = $wpdb->prefix . 'timetable_time_master';
        $time_master_sql = "CREATE TABLE $time_master_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time_slot varchar(50) NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            sort_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // 共通CSSテーブル
        $common_css_table = $wpdb->prefix . 'timetable_common_css';
        $common_css_sql = "CREATE TABLE $common_css_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            css_code longtext NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // 個別CSSテーブル
        $individual_css_table = $wpdb->prefix . 'timetable_individual_css';
        $individual_css_sql = "CREATE TABLE $individual_css_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            timetable_id bigint(20) NOT NULL,
            css_code longtext NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY timetable_id (timetable_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($time_sql);
        dbDelta($content_sql);
        dbDelta($time_master_sql);
        dbDelta($common_css_sql);
        dbDelta($individual_css_sql);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    public function add_admin_menu()
    {
        $parent = defined('PSS_MENU_SLUG') ? PSS_MENU_SLUG : 'picot-schedule-suite';
        add_submenu_page(
            $parent,
            pss_timetable_text('menu_page_title'),
            pss_timetable_text('menu_title', '時間割'),
            'manage_options',
            'timetable-creator',
            array($this, 'admin_page')
        );
    }

    public function admin_page()
    {
        include plugin_dir_path(__FILE__) . 'admin/admin-page.php';
    }

    public function enqueue_scripts()
    {
        if (pss_current_content_has_shortcode('timetable')) {
            $this->enqueue_frontend_assets();
        }
    }

    /**
     * Enqueue front-end assets (only on pages that render a timetable).
     */
    public function register_frontend_assets()
    {
        if (!wp_style_is('pss-timetable', 'registered')) {
            wp_register_style('pss-timetable', plugin_dir_url(__FILE__) . 'assets/css/timetable-creator.css', array(), PSS_VERSION);
            wp_register_script('pss-timetable', plugin_dir_url(__FILE__) . 'assets/js/timetable-creator.js', array('jquery'), PSS_VERSION, true);
        }
    }

    public function enqueue_frontend_assets()
    {
        $this->register_frontend_assets();
        wp_enqueue_style('pss-timetable');
        wp_enqueue_script('pss-timetable');
    }

    public function admin_enqueue_scripts($hook)
    {
        if (strpos($hook, 'timetable-creator') !== false) {
            wp_enqueue_style('pss-timetable-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css', array(), PSS_VERSION);
            wp_enqueue_script('pss-timetable-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js', array('jquery'), PSS_VERSION, true);

            // 多言語テキストをJavaScriptに渡す
            global $pss_timetable_language_manager;
            if (isset($pss_timetable_language_manager)) {
                $translations = $pss_timetable_language_manager->translations;
                // JavaScriptで使用する追加のテキスト
                $translations['error_select_time_setting'] = $pss_timetable_language_manager->get_text('error_select_time_setting', 'Please select a time setting.');
                wp_localize_script('pss-timetable-admin', 'tcTexts', $translations);
            }
        }
    }

    public function activate()
    {
        $this->create_tables();
        flush_rewrite_rules();
    }

    public function deactivate()
    {
        flush_rewrite_rules();
    }

    // フォーム処理の統合
    public function handle_form_submissions()
    {
        if (!isset($_POST['action']) || !current_user_can('manage_options')) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['action']));

        // このプラグインのアクション以外（例: 投稿保存の editpost）は無視
        $timetable_actions = array(
            'create_timetable', 'update_timetable', 'delete_timetable',
            'add_content', 'update_content', 'delete_content',
            'update_time_slots', 'add_time_master', 'update_time_master', 'delete_time_master',
            'apply_time_master', 'update_days', 'copy_timetable', 'rename_timetable',
            'update_common_css', 'update_individual_css',
        );
        if (!in_array($action, $timetable_actions, true)) {
            return;
        }

        // CSRF対策
        $timetable_creator_nonce = isset($_POST['timetable_creator_nonce']) ? sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])) : '';
        if (!wp_verify_nonce($timetable_creator_nonce, 'timetable_creator_action')) {
            wp_die('セキュリティチェックに失敗しました。');
        }

        switch ($action) {
            case 'create_timetable':
                $this->create_timetable();
                break;
            case 'update_timetable':
                $this->update_timetable();
                break;
            case 'delete_timetable':
                $this->delete_timetable();
                break;
            case 'add_content':
                $this->add_content();
                break;
            case 'update_content':
                $this->update_content();
                break;
            case 'delete_content':
                $this->delete_content();
                break;
            case 'update_time_slots':
                $this->update_time_slots();
                break;
            case 'add_time_master':
                $this->add_time_master();
                break;
            case 'update_time_master':
                $this->update_time_master();
                break;
            case 'delete_time_master':
                $this->delete_time_master();
                break;
            case 'apply_time_master':
                $this->apply_time_master();
                break;
            case 'update_days':
                $this->update_days();
                break;
            case 'copy_timetable':
                $this->copy_timetable();
                break;
            case 'rename_timetable':
                $this->rename_timetable();
                break;
            case 'update_common_css':
                $this->update_common_css();
                break;
            case 'update_individual_css':
                $this->update_individual_css();
                break;
        }
    }

    // 時間割データの取得
    public function get_timetable_data($timetable_id)
    {
        global $wpdb;
        $data = array();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}timetable_data WHERE timetable_id = %d",
            $timetable_id
        ));

        foreach ($results as $row) {
            $data[$row->day_of_week][$row->time_slot] = array(
                'content_id' => $row->content,
                'is_observable' => $row->is_observable,
                'comment' => $row->comment
            );
        }

        return $data;
    }

    // 時間割の作成
    public function create_timetable()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $title = isset($_POST['timetable_title']) ? sanitize_text_field(wp_unslash($_POST['timetable_title'])) : '';
        $description = isset($_POST['timetable_description']) ? sanitize_textarea_field(wp_unslash($_POST['timetable_description'])) : '';

        // 重複チェック
        $existing_timetable = new WP_Query(array(
            'post_type' => 'timetable',
            'title'     => $title,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ));
        if ($existing_timetable->have_posts()) {
            wp_safe_redirect(admin_url('admin.php?page=timetable-creator&error=duplicate_title'));
            exit;
        }

        $post_data = array(
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => 'publish',
            'post_type' => 'timetable'
        );

        $post_id = wp_insert_post($post_data);

        if ($post_id) {
            wp_safe_redirect(admin_url('admin.php?page=timetable-creator&timetable_id=' . $post_id . '&created=1'));
            exit;
        } else {
            wp_safe_redirect(admin_url('admin.php?page=timetable-creator&error=creation_failed'));
            exit;
        }
    }

    public function update_time_master_data()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $master_id = intval(wp_unslash($_POST['master_id'] ?? 0));
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $content = isset($_POST['content']) ? (array) wp_unslash($_POST['content']) : array();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $observable = isset($_POST['observable']) ? (array) wp_unslash($_POST['observable']) : array();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $comment = isset($_POST['comment']) ? (array) wp_unslash($_POST['comment']) : array();
    }

    // 時間割の更新
    public function update_timetable()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $timetable_id = intval(wp_unslash($_POST['timetable_id'] ?? 0));
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $content = isset($_POST['content']) ? (array) wp_unslash($_POST['content']) : array();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $observable = isset($_POST['observable']) ? (array) wp_unslash($_POST['observable']) : array();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $comment = isset($_POST['comment']) ? (array) wp_unslash($_POST['comment']) : array();

        // サニタイズ
        $sanitized_content = array();
        foreach ($content as $day => $times) {
            foreach ($times as $time => $val) {
                $sanitized_content[intval($day)][sanitize_text_field($time)] = intval($val);
            }
        }

        $sanitized_observable = array();
        foreach ($observable as $day => $times) {
            foreach ($times as $time => $val) {
                $sanitized_observable[intval($day)][sanitize_text_field($time)] = 1;
            }
        }

        $sanitized_comment = array();
        foreach ($comment as $day => $times) {
            foreach ($times as $time => $val) {
                $sanitized_comment[intval($day)][sanitize_text_field($time)] = sanitize_textarea_field($val);
            }
        }

        global $wpdb;

        // 既存データを削除
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $wpdb->prefix . 'timetable_data',
            array('timetable_id' => $timetable_id),
            array('%d')
        );

        // 新しいデータを挿入
        foreach ($sanitized_content as $day => $times) {
            foreach ($times as $time => $content_id) {
                if (!empty($content_id)) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->insert(
                        $wpdb->prefix . 'timetable_data',
                        array(
                            'timetable_id' => $timetable_id,
                            'day_of_week' => $day,
                            'time_slot' => $time,
                            'content' => $content_id,
                            'is_observable' => isset($sanitized_observable[$day][$time]) ? 1 : 0,
                            'comment' => isset($sanitized_comment[$day][$time]) ? $sanitized_comment[$day][$time] : ''
                        ),
                        array('%d', '%d', '%s', '%s', '%d', '%s')
                    );
                }
            }
        }

        // 時間設定の保存
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $time_slots = wp_unslash($_POST['time_slots'] ?? array());
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $start_times = wp_unslash($_POST['start_times'] ?? array());
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $end_times = wp_unslash($_POST['end_times'] ?? array());

        if (!empty($time_slots)) {
            // 既存の時間設定を削除
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete(
                $wpdb->prefix . 'timetable_times',
                array('timetable_id' => $timetable_id),
                array('%d')
            );

            // 新しい時間設定を挿入
            foreach ($time_slots as $index => $time_slot) {
                if (!empty($time_slot) && !empty($start_times[$index]) && !empty($end_times[$index])) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $result = $wpdb->insert(
                        $wpdb->prefix . 'timetable_times',
                        array(
                            'timetable_id' => $timetable_id,
                            'time_slot' => sanitize_text_field($time_slot),
                            'start_time' => sanitize_text_field($start_times[$index]),
                            'end_time' => sanitize_text_field($end_times[$index]),
                            'sort_order' => $index
                        ),
                        array('%d', '%s', '%s', '%s', '%d')
                    );
                }
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=timetable-creator&timetable_id=' . $timetable_id . '&updated=1'));
        exit;
    }

    // 時間割の削除
    public function delete_timetable()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $timetable_id = intval(wp_unslash($_POST['timetable_id'] ?? 0));

        // 投稿を削除
        wp_delete_post($timetable_id, true);

        // 関連データを削除
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($wpdb->prefix . 'timetable_data', array('timetable_id' => $timetable_id), array('%d'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($wpdb->prefix . 'timetable_times', array('timetable_id' => $timetable_id), array('%d'));

        wp_safe_redirect(admin_url('admin.php?page=timetable-creator&deleted=1'));
        exit;
    }

    // 曜日の更新
    public function update_days()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $timetable_id = intval(wp_unslash($_POST['timetable_id'] ?? 0));
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $selected_days = wp_unslash($_POST['timetable_days'] ?? array());

        // 選択された曜日を数値の配列に変換
        $days_array = array();
        foreach ($selected_days as $day) {
            $day_num = intval($day);
            if ($day_num >= 0 && $day_num <= 6) {
                $days_array[] = $day_num;
            }
        }

        // 曜日設定を保存
        update_post_meta($timetable_id, '_timetable_days', $days_array);

        // リダイレクト先を決定
        $redirect_url = 'admin.php?page=timetable-creator&timetable_id=' . $timetable_id . '&tab=days&days_updated=1';
        wp_safe_redirect(admin_url($redirect_url));
        exit;
    }

    // コンテンツの追加
    public function add_content()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $name = isset($_POST['content_name']) ? sanitize_text_field(wp_unslash($_POST['content_name'])) : '';
        $description = isset($_POST['content_description']) ? sanitize_textarea_field(wp_unslash($_POST['content_description'])) : '';
        $is_active = isset($_POST['content_active']) ? 1 : 0;

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert(
            $wpdb->prefix . 'timetable_contents',
            array(
                'name' => $name,
                'description' => $description,
                'is_active' => $is_active
            ),
            array('%s', '%s', '%d')
        );

        // 現在選択されている時間割IDを取得
        $timetable_id = isset($_POST['timetable_id']) ? intval(wp_unslash($_POST['timetable_id'])) : 0;
        $redirect_url = 'admin.php?page=timetable-creator&tab=content&added=1';
        if ($timetable_id) {
            $redirect_url .= '&timetable_id=' . $timetable_id;
        }
        wp_safe_redirect(admin_url($redirect_url));
        exit;
    }

    // コンテンツの更新
    public function update_content()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $content_id = intval(wp_unslash($_POST['content_id'] ?? 0));
        $name = isset($_POST['content_name']) ? sanitize_text_field(wp_unslash($_POST['content_name'])) : '';
        $description = isset($_POST['content_description']) ? sanitize_textarea_field(wp_unslash($_POST['content_description'])) : '';
        $is_active = isset($_POST['content_active']) ? 1 : 0;

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->prefix . 'timetable_contents',
            array(
                'name' => $name,
                'description' => $description,
                'is_active' => $is_active
            ),
            array('id' => $content_id),
            array('%s', '%s', '%d'),
            array('%d')
        );

        // 現在選択されている時間割IDを取得
        $timetable_id = isset($_POST['timetable_id']) ? intval(wp_unslash($_POST['timetable_id'])) : 0;
        $redirect_url = 'admin.php?page=timetable-creator&tab=content&updated=1';
        if ($timetable_id) {
            $redirect_url .= '&timetable_id=' . $timetable_id;
        }
        wp_safe_redirect(admin_url($redirect_url));
        exit;
    }

    // コンテンツの削除
    public function delete_content()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $content_id = intval(wp_unslash($_POST['content_id'] ?? 0));

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $wpdb->prefix . 'timetable_contents',
            array('id' => $content_id),
            array('%d')
        );

        // 現在選択されている時間割IDを取得
        $timetable_id = isset($_POST['timetable_id']) ? intval(wp_unslash($_POST['timetable_id'])) : 0;
        $redirect_url = 'admin.php?page=timetable-creator&tab=content&deleted=1';
        if ($timetable_id) {
            $redirect_url .= '&timetable_id=' . $timetable_id;
        }
        wp_safe_redirect(admin_url($redirect_url));
        exit;
    }

    // 時間割のコピー
    public function copy_timetable()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $source_timetable_id = intval(wp_unslash($_POST['source_timetable_id'] ?? 0));
        $new_timetable_name = isset($_POST['new_timetable_name']) ? sanitize_text_field(wp_unslash($_POST['new_timetable_name'])) : '';
        $copy_description = isset($_POST['copy_description']) ? sanitize_textarea_field(wp_unslash($_POST['copy_description'])) : '';

        // 入力値の検証
        if (empty($source_timetable_id) || empty($new_timetable_name)) {
            wp_safe_redirect(admin_url('admin.php?page=timetable-creator&tab=copy&error=missing_fields'));
            exit;
        }

        // 元の時間割を取得
        $source_timetable = get_post($source_timetable_id);
        if (!$source_timetable || $source_timetable->post_type !== 'timetable') {
            wp_safe_redirect(admin_url('admin.php?page=timetable-creator&tab=copy&error=invalid_source'));
            exit;
        }

        // 同じ名前の時間割が既に存在するかチェック
        $existing_timetable = new WP_Query(array(
            'post_type' => 'timetable',
            'title'     => $new_timetable_name,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ));
        if ($existing_timetable->have_posts()) {
            wp_safe_redirect(admin_url('admin.php?page=timetable-creator&tab=copy&error=duplicate_title'));
            exit;
        }

        global $wpdb;

        // 新しい時間割を作成
        $new_timetable_id = wp_insert_post(array(
            'post_title' => $new_timetable_name,
            'post_content' => $copy_description,
            'post_type' => 'timetable',
            'post_status' => 'publish'
        ));

        if (is_wp_error($new_timetable_id)) {
            wp_safe_redirect(admin_url('admin.php?page=timetable-creator&tab=copy&error=creation_failed'));
            exit;
        }

        // 時間設定をコピー
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $time_slots = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}timetable_times WHERE timetable_id = %d ORDER BY sort_order",
            $source_timetable_id
        ));

        foreach ($time_slots as $slot) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert(
                $wpdb->prefix . 'timetable_times',
                array(
                    'timetable_id' => $new_timetable_id,
                    'time_slot' => $slot->time_slot,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'sort_order' => $slot->sort_order
                ),
                array('%d', '%s', '%s', '%s', '%d')
            );
        }

        // 時間割データをコピー
        $timetable_data = $this->get_timetable_data($source_timetable_id);
        if (!empty($timetable_data)) {
            update_post_meta($new_timetable_id, '_timetable_data', $timetable_data);
        }

        wp_safe_redirect(admin_url('admin.php?page=timetable-creator&tab=copy&copied=1&new_id=' . $new_timetable_id));
        exit;
    }

    // 時間割の名称変更
    public function rename_timetable()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $timetable_id = intval(wp_unslash($_POST['timetable_id'] ?? 0));
        $new_name = isset($_POST['new_timetable_name']) ? sanitize_text_field(wp_unslash($_POST['new_timetable_name'])) : '';

        // 入力値の検証
        if (empty($timetable_id) || empty($new_name)) {
            wp_safe_redirect(admin_url('admin.php?page=timetable-creator&timetable_id=' . $timetable_id . '&tab=rename&error=missing_fields'));
            exit;
        }

        // 時間割を取得
        $timetable = get_post($timetable_id);
        if (!$timetable || $timetable->post_type !== 'timetable') {
            wp_safe_redirect(admin_url('admin.php?page=timetable-creator&timetable_id=' . $timetable_id . '&tab=rename&error=invalid_timetable'));
            exit;
        }

        // 同じ名前の時間割が既に存在するかチェック（自分以外）
        $existing_timetable = new WP_Query(array(
            'post_type' => 'timetable',
            'title'     => $new_name,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ));
        if ($existing_timetable->have_posts() && $existing_timetable->posts[0]->ID !== $timetable_id) {
            wp_safe_redirect(admin_url('admin.php?page=timetable-creator&timetable_id=' . $timetable_id . '&tab=rename&error=duplicate_title'));
            exit;
        }

        // 時間割の名称を更新
        $result = wp_update_post(array(
            'ID' => $timetable_id,
            'post_title' => $new_name
        ));

        if (is_wp_error($result)) {
            wp_safe_redirect(admin_url('admin.php?page=timetable-creator&timetable_id=' . $timetable_id . '&tab=rename&error=rename_failed'));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=timetable-creator&timetable_id=' . $timetable_id . '&tab=rename&renamed=1'));
        exit;
    }

    // 時間設定の更新
    public function save_time_master_settings()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $master_id = intval(wp_unslash($_POST['master_id'] ?? 0));
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $time_slots = wp_unslash($_POST['time_slots'] ?? array());
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $start_times = wp_unslash($_POST['start_times'] ?? array());
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $end_times = wp_unslash($_POST['end_times'] ?? array());
        // 既存の時間設定を削除
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $wpdb->prefix . 'timetable_times',
            array('timetable_id' => $timetable_id),
            array('%d')
        );
    }

    public function update_time_slots()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $timetable_id = isset($_POST['timetable_id']) ? intval(wp_unslash($_POST['timetable_id'])) : 0;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $time_slots = wp_unslash($_POST['time_slot'] ?? array());
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $time_ranges = wp_unslash($_POST['time_range'] ?? array());

        global $wpdb;

        // 既存の時間設定を削除
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $wpdb->prefix . 'timetable_times',
            array('timetable_id' => $timetable_id),
            array('%d')
        );

        // 新しい時間設定を挿入
        foreach ($time_slots as $index => $time_slot) {
            if (!empty($time_slot) && !empty($time_ranges[$index])) {
                // 時間範囲を解析（例: 09:00〜09:50）
                $time_range = sanitize_text_field($time_ranges[$index]);
                if (preg_match('/^(\d{1,2}:\d{2})〜(\d{1,2}:\d{2})$/', $time_range, $matches)) {
                    $start_time = $matches[1];
                    $end_time = $matches[2];

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->insert(
                        $wpdb->prefix . 'timetable_times',
                        array(
                            'timetable_id' => $timetable_id,
                            'time_slot' => sanitize_text_field($time_slot),
                            'start_time' => $start_time,
                            'end_time' => $end_time,
                            'sort_order' => $index
                        ),
                        array('%d', '%s', '%s', '%s', '%d')
                    );
                }
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=timetable-creator&timetable_id=' . $timetable_id . '&tab=time&updated=1'));
        exit;
    }

    // 時間マスターの追加
    public function add_time_master()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $name = isset($_POST['master_name']) ? sanitize_text_field(wp_unslash($_POST['master_name'])) : '';
        $description = isset($_POST['master_description']) ? sanitize_textarea_field(wp_unslash($_POST['master_description'])) : '';
        $is_active = isset($_POST['master_active']) ? 1 : 0;
        $start_time = isset($_POST['start_time']) ? sanitize_text_field(wp_unslash($_POST['start_time'])) : '';
        $end_time = isset($_POST['end_time']) ? sanitize_text_field(wp_unslash($_POST['end_time'])) : '';
        $time_active = isset($_POST['time_active']) ? 1 : 0;
        $time_slot = isset($_POST['time_slot']) ? sanitize_text_field(wp_unslash($_POST['time_slot'])) : '';

        // 現在選択されている時間割IDを取得
        $timetable_id = isset($_POST['timetable_id']) ? intval(wp_unslash($_POST['timetable_id'])) : 0;

        // 時間形式の検証
        if (
            !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $start_time) ||
            !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $end_time)
        ) {
            $redirect_url = 'admin.php?page=timetable-creator&tab=time&error=invalid_time_format';
            if ($timetable_id) {
                $redirect_url .= '&timetable_id=' . $timetable_id;
            }
            wp_safe_redirect(admin_url($redirect_url));
            exit;
        }

        // 開始時刻が終了時刻より後の場合はエラー
        if (strtotime($start_time) >= strtotime($end_time)) {
            $redirect_url = 'admin.php?page=timetable-creator&tab=time&error=invalid_time_order';
            if ($timetable_id) {
                $redirect_url .= '&timetable_id=' . $timetable_id;
            }
            wp_safe_redirect(admin_url($redirect_url));
            exit;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->insert(
            $wpdb->prefix . 'timetable_time_master',
            array(
                'time_slot' => $time_slot,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'is_active' => $time_active,
                'sort_order' => 0
            ),
            array('%s', '%s', '%s', '%d', '%d')
        );

        // 現在選択されている時間割IDを取得
        $timetable_id = isset($_POST['timetable_id']) ? intval(wp_unslash($_POST['timetable_id'])) : 0;

        if ($result) {
            $redirect_url = 'admin.php?page=timetable-creator&tab=time&added=1';
            if ($timetable_id) {
                $redirect_url .= '&timetable_id=' . $timetable_id;
            }
            wp_safe_redirect(admin_url($redirect_url));
        } else {
            $redirect_url = 'admin.php?page=timetable-creator&tab=time&error=creation_failed';
            if ($timetable_id) {
                $redirect_url .= '&timetable_id=' . $timetable_id;
            }
            wp_safe_redirect(admin_url($redirect_url));
        }
        exit;
    }

    // 時間マスターの更新
    public function update_time_master()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $time_master_id = isset($_POST['time_master_id']) ? intval(wp_unslash($_POST['time_master_id'])) : 0;
        $time_slot = isset($_POST['time_slot']) ? sanitize_text_field(wp_unslash($_POST['time_slot'])) : '';
        $start_time = isset($_POST['start_time']) ? sanitize_text_field(wp_unslash($_POST['start_time'])) : '';
        $end_time = isset($_POST['end_time']) ? sanitize_text_field(wp_unslash($_POST['end_time'])) : '';
        $is_active = isset($_POST['time_active']) ? 1 : 0;

        // 現在選択されている時間割IDを取得
        $timetable_id = isset($_POST['timetable_id']) ? intval(wp_unslash($_POST['timetable_id'])) : 0;

        // 時間形式の検証（時間フィールドが空でない場合のみ）
        if (!empty($start_time) && !empty($end_time)) {
            // 時間形式の正規化（HH:MM形式に統一）
            $start_time = trim($start_time);
            $end_time = trim($end_time);

            // 時間形式の検証
            if (
                !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $start_time) ||
                !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $end_time)
            ) {
                $redirect_url = 'admin.php?page=timetable-creator&tab=time&error=invalid_time_format';
                if ($timetable_id) {
                    $redirect_url .= '&timetable_id=' . $timetable_id;
                }
                wp_safe_redirect(admin_url($redirect_url));
                exit;
            }

            // 開始時刻が終了時刻より後の場合はエラー
            if (strtotime($start_time) >= strtotime($end_time)) {
                $redirect_url = 'admin.php?page=timetable-creator&tab=time&error=invalid_time_order';
                if ($timetable_id) {
                    $redirect_url .= '&timetable_id=' . $timetable_id;
                }
                wp_safe_redirect(admin_url($redirect_url));
                exit;
            }
        } else {
            // 時間フィールドが空の場合は、既存の値を取得して使用
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $existing_time_master = $wpdb->get_row($wpdb->prepare(
                "SELECT start_time, end_time FROM {$wpdb->prefix}timetable_time_master WHERE id = %d",
                $time_master_id
            ));

            if ($existing_time_master) {
                $start_time = $existing_time_master->start_time;
                $end_time = $existing_time_master->end_time;
            }
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $wpdb->prefix . 'timetable_time_master',
            array(
                'time_slot' => $time_slot,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'is_active' => $is_active
            ),
            array('id' => $time_master_id),
            array('%s', '%s', '%s', '%d'),
            array('%d')
        );

        // 現在選択されている時間割IDを取得
        $timetable_id = isset($_POST['timetable_id']) ? intval(wp_unslash($_POST['timetable_id'])) : 0;

        if ($result !== false) {
            // 同じ時間帯名を持つ全ての時間割の時間設定を更新
            $this->update_all_timetable_times($time_master_id, $time_slot, $start_time, $end_time);
            $redirect_url = 'admin.php?page=timetable-creator&tab=time&updated=1';
            if ($timetable_id) {
                $redirect_url .= '&timetable_id=' . $timetable_id;
            }
            wp_safe_redirect(admin_url($redirect_url));
        } else {
            $redirect_url = 'admin.php?page=timetable-creator&tab=time&error=update_failed';
            if ($timetable_id) {
                $redirect_url .= '&timetable_id=' . $timetable_id;
            }
            wp_safe_redirect(admin_url($redirect_url));
        }
        exit;
    }

    // 時間マスターの削除
    public function delete_time_master()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $master_id = intval(wp_unslash($_POST['master_id'] ?? 0));

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $wpdb->prefix . 'timetable_time_master',
            array('id' => $master_id),
            array('%d')
        );

        // 現在選択されている時間割IDを取得
        $timetable_id = isset($_POST['timetable_id']) ? intval(wp_unslash($_POST['timetable_id'])) : 0;
        $redirect_url = 'admin.php?page=timetable-creator&tab=time&deleted=1';
        if ($timetable_id) {
            $redirect_url .= '&timetable_id=' . $timetable_id;
        }
        wp_safe_redirect(admin_url($redirect_url));
        exit;
    }

    // 全ての時間割の時間設定を更新
    private function update_all_timetable_times($time_master_id, $time_slot, $start_time, $end_time)
    {
        global $wpdb;

        // 時間マスターから時間帯名を取得
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $master_data = $wpdb->get_row($wpdb->prepare(
            "SELECT time_slot FROM {$wpdb->prefix}timetable_time_master WHERE id = %d",
            $time_master_id
        ));

        if ($master_data) {
            $old_time_slot = $master_data->time_slot;

            // 全ての時間割で同じ時間帯名の設定を更新
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $wpdb->prefix . 'timetable_times',
                array(
                    'start_time' => $start_time,
                    'end_time' => $end_time
                ),
                array('time_slot' => $old_time_slot),
                array('%s', '%s'),
                array('%s')
            );
        }
    }

    // 時間マスターから時間設定を取得
    public function get_time_master_data()
    {
        global $wpdb;

        // テーブル存在確認
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}timetable_time_master'");

        if (!$table_exists) {
            return array();
        }

        // SQLクエリの実行
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}timetable_time_master WHERE is_active = %d ORDER BY start_time, time_slot",
            1
        ));

        return $results;
    }

    // 時間マスターを時間割に適用
    public function apply_time_master()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        $timetable_id = intval(wp_unslash($_POST['timetable_id'] ?? 0));
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $selected_time_masters = wp_unslash($_POST['selected_time_master'] ?? array());

        if (empty($selected_time_masters)) {
            wp_safe_redirect(admin_url('admin.php?page=timetable-creator&timetable_id=' . $timetable_id . '&tab=time&error=no-selection'));
            exit;
        }

        global $wpdb;

        // 既存の時間設定を削除
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $wpdb->prefix . 'timetable_times',
            array('timetable_id' => $timetable_id),
            array('%d')
        );

        // 選択された時間マスターを適用
        foreach ($selected_time_masters as $index => $time_master_id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $time_master = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}timetable_time_master WHERE id = %d",
                $time_master_id
            ));

            if ($time_master) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->insert(
                    $wpdb->prefix . 'timetable_times',
                    array(
                        'timetable_id' => $timetable_id,
                        'time_slot' => $time_master->time_slot,
                        'start_time' => $time_master->start_time,
                        'end_time' => $time_master->end_time,
                        'sort_order' => $index
                    ),
                    array('%d', '%s', '%s', '%s', '%d')
                );
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=timetable-creator&timetable_id=' . $timetable_id . '&tab=edit&applied=1'));
        exit;
    }



    /**
     * 共通CSSの更新
     */
    public function update_common_css()
    {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }

        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        global $wpdb;
        // wp_kses_postはCSSの親子セレクタ(>)などをエンコードしてしまうため、wp_strip_all_tagsを使用
        $css_code = isset($_POST['css_code']) ? wp_strip_all_tags(wp_unslash($_POST['css_code'])) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // 既存のCSSを確認
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}timetable_common_css LIMIT 1");

        if ($existing) {
            // 更新
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->update(
                $wpdb->prefix . 'timetable_common_css',
                array(
                    'css_code' => $css_code,
                    'is_active' => $is_active,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing->id),
                array('%s', '%d', '%s'),
                array('%d')
            );
        } else {
            // 新規作成
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->insert(
                $wpdb->prefix . 'timetable_common_css',
                array(
                    'css_code' => $css_code,
                    'is_active' => $is_active,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%d', '%s', '%s')
            );
        }

        if ($result !== false) {
            wp_safe_redirect(add_query_arg('message', 'css_updated', wp_get_referer()));
        } else {
            wp_safe_redirect(add_query_arg('error', 'css_update_failed', wp_get_referer()));
        }
        exit;
    }

    /**
     * 個別CSSの更新
     */
    public function update_individual_css()
    {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }

        if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_creator_action')) {
            wp_die('Security check failed');
        }

        global $wpdb;
        $timetable_id = isset($_POST['timetable_id']) ? intval(wp_unslash($_POST['timetable_id'])) : 0;
        // wp_kses_postはCSSの親子セレクタ(>)などをエンコードしてしまうため、wp_strip_all_tagsを使用
        $css_code = isset($_POST['css_code']) ? wp_strip_all_tags(wp_unslash($_POST['css_code'])) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // 既存のCSSを確認
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}timetable_individual_css WHERE timetable_id = %d",
            $timetable_id
        ));

        if ($existing) {
            // 更新
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->update(
                $wpdb->prefix . 'timetable_individual_css',
                array(
                    'css_code' => $css_code,
                    'is_active' => $is_active,
                    'updated_at' => current_time('mysql')
                ),
                array('timetable_id' => $timetable_id),
                array('%s', '%d', '%s'),
                array('%d')
            );
        } else {
            // 新規作成
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->insert(
                $wpdb->prefix . 'timetable_individual_css',
                array(
                    'timetable_id' => $timetable_id,
                    'css_code' => $css_code,
                    'is_active' => $is_active,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%d', '%s', '%d', '%s', '%s')
            );
        }

        if ($result !== false) {
            wp_safe_redirect(add_query_arg('message', 'css_updated', wp_get_referer()));
        } else {
            wp_safe_redirect(add_query_arg('error', 'css_update_failed', wp_get_referer()));
        }
        exit;
    }

    /**
     * 共通CSSの取得
     */
    public function get_common_css()
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $css = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}timetable_common_css LIMIT 1");
        return $css;
    }

    /**
     * 個別CSSの取得
     */
    public function get_individual_css($timetable_id)
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $css = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}timetable_individual_css WHERE timetable_id = %d",
            $timetable_id
        ));
        return $css;
    }
}
} // class_exists PSS_Timetable_Module

if (!function_exists('pss_timetable_shortcode')) {
function pss_timetable_shortcode($atts)
{
    $timetable_creator_atts = shortcode_atts(array(
        'id' => 0,
    ), $atts);

    if (!$timetable_creator_atts['id']) {
        return pss_missing_data_notice(pss_timetable_text('specify_timetable_id', '時間割IDを指定してください。'));
    }

    // CSSの出力
    $timetable_creator_css_output = '';

    // 共通CSSの出力（XSS対策は pss_prepare_css_for_output で実施）
    global $pss_timetable_module;
    if (!($pss_timetable_module instanceof PSS_Timetable_Module)) {
        return '';
    }
    $pss_timetable_module->enqueue_frontend_assets();

    $timetable_creator_common_css = $pss_timetable_module->get_common_css();
    if ($timetable_creator_common_css && $timetable_creator_common_css->is_active && !empty($timetable_creator_common_css->css_code)) {
        $timetable_creator_css_output .= pss_style_block($timetable_creator_common_css->css_code);
    }

    // 個別CSSの出力（XSS対策: 同上）
    $timetable_creator_individual_css = $pss_timetable_module->get_individual_css($timetable_creator_atts['id']);
    if ($timetable_creator_individual_css && $timetable_creator_individual_css->is_active && !empty($timetable_creator_individual_css->css_code)) {
        $timetable_creator_css_output .= pss_style_block($timetable_creator_individual_css->css_code);
    }

    ob_start();
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $timetable_creator_css_output;
    include plugin_dir_path(__FILE__) . 'templates/timetable-display.php';
    return ob_get_clean();
}
}

if (!shortcode_exists('timetable')) {
    add_shortcode('timetable', 'pss_timetable_shortcode');
}
