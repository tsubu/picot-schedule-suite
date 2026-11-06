<?php
/**
 * Picot Schedule Suite bootstrap.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Picot_Schedule_Suite
{
    /** @var self|null */
    private static $instance = null;

    /** @var PSS_Timetable_Module|null */
    public $timetable = null;

    /** @var PSS_Weekly_Module|null */
    public $weekly = null;

    /** @var PSS_Calendar_Module|null */
    public $calendar = null;

    /**
     * @return self
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->load_modules();

        require_once PSS_PLUGIN_DIR . 'includes/class-blocks.php';
        new PSS_Blocks($this);
        add_action('admin_menu', array($this, 'register_menu'), 9);
        add_action('admin_menu', array($this, 'reorder_submenu'), 999);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_notices', array($this, 'maybe_show_migration_notice'));
        add_action('admin_notices', array($this, 'maybe_warn_standalone_conflict'));
        add_action('admin_post_pss_save_settings', array($this, 'handle_save_settings'));
    }

    /**
     * Save suite-wide settings from the dashboard.
     */
    public function handle_save_settings()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('権限がありません。', 'picot-schedule-suite'), 403);
        }
        check_admin_referer('pss_save_settings');

        update_option('pss_delete_data_on_uninstall', !empty($_POST['pss_delete_data_on_uninstall']), false);

        wp_safe_redirect(add_query_arg(
            array('page' => PSS_MENU_SLUG, 'pss-updated' => '1'),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Load feature modules.
     */
    private function load_modules()
    {
        require_once PSS_PLUGIN_DIR . 'includes/class-japan-holidays.php';
        require_once PSS_PLUGIN_DIR . 'modules/timetable/includes/class-language-manager.php';
        require_once PSS_PLUGIN_DIR . 'modules/timetable/class-timetable-module.php';
        require_once PSS_PLUGIN_DIR . 'modules/weekly/class-weekly-module.php';
        require_once PSS_PLUGIN_DIR . 'modules/calendar/class-calendar-module.php';

        if (class_exists('PSS_Timetable_Module') && !$this->is_standalone_active('timetable-creator/timetable-creator.php')) {
            $this->timetable = new PSS_Timetable_Module(true);
        }

        if (class_exists('PSS_Weekly_Module') && !$this->is_standalone_active('weekly-business-schedule/weekly-business-schedule.php')) {
            $this->weekly = new PSS_Weekly_Module();
        }

        if (class_exists('PSS_Calendar_Module') && !$this->is_standalone_active('business-day-calendar/business-day-calendar.php')) {
            $this->calendar = new PSS_Calendar_Module();
        }
    }

    /**
     * @param string $basename Plugin basename.
     * @return bool
     */
    private function is_standalone_active($basename)
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active($basename);
    }

    /**
     * Top-level admin menu and dashboard.
     */
    public function register_menu()
    {
        add_menu_page(
            __('スケジュール管理', 'picot-schedule-suite'),
            __('スケジュール', 'picot-schedule-suite'),
            'manage_options',
            PSS_MENU_SLUG,
            array($this, 'render_dashboard'),
            'dashicons-calendar-alt',
            30
        );

        add_submenu_page(
            PSS_MENU_SLUG,
            __('ダッシュボード', 'picot-schedule-suite'),
            __('ダッシュボード', 'picot-schedule-suite'),
            'manage_options',
            PSS_MENU_SLUG,
            array($this, 'render_dashboard')
        );
    }

    /**
     * Keep submenu order: Dashboard → Timetable → Weekly → Calendar.
     */
    public function reorder_submenu()
    {
        global $submenu;

        if (empty($submenu[PSS_MENU_SLUG]) || !is_array($submenu[PSS_MENU_SLUG])) {
            return;
        }

        $preferred = array(
            PSS_MENU_SLUG,
            'timetable-creator',
            'edit.php?post_type=bd_weekly',
            'edit.php?post_type=bd_calendar',
        );

        $by_slug = array();
        foreach ($submenu[PSS_MENU_SLUG] as $item) {
            if (!empty($item[2])) {
                $by_slug[$item[2]] = $item;
            }
        }

        $ordered = array();
        foreach ($preferred as $slug) {
            if (isset($by_slug[$slug])) {
                $ordered[] = $by_slug[$slug];
                unset($by_slug[$slug]);
            }
        }

        foreach ($by_slug as $item) {
            $ordered[] = $item;
        }

        $submenu[PSS_MENU_SLUG] = $ordered;
    }

    /**
     * Suite dashboard styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets($hook)
    {
        if ($hook !== 'toplevel_page_' . PSS_MENU_SLUG) {
            return;
        }

        wp_enqueue_style(
            'pss-admin-dashboard',
            PSS_PLUGIN_URL . 'assets/css/dashboard.css',
            array(),
            PSS_VERSION
        );
    }

    /**
     * Overview dashboard.
     */
    public function render_dashboard()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $cards = array(
            array(
                'title'       => __('時間割', 'picot-schedule-suite'),
                'description' => __('学校・塾向けの時間割を作成・表示します。', 'picot-schedule-suite'),
                'url'         => admin_url('admin.php?page=timetable-creator'),
                'icon'        => 'dashicons-welcome-learn-more',
                'shortcode'   => '[timetable id="ID"]',
                'enabled'     => (bool) $this->timetable,
            ),
            array(
                'title'       => __('週間スケジュール', 'picot-schedule-suite'),
                'description' => __('時間帯と記号で週間の空き状況を管理します。', 'picot-schedule-suite'),
                'url'         => admin_url('edit.php?post_type=bd_weekly'),
                'icon'        => 'dashicons-clock',
                'shortcode'   => '[weekly_schedule id="ID"]',
                'enabled'     => (bool) $this->weekly,
            ),
            array(
                'title'       => __('営業日カレンダー', 'picot-schedule-suite'),
                'description' => __('営業日・休業日をカレンダーで管理・表示します。', 'picot-schedule-suite'),
                'url'         => admin_url('edit.php?post_type=bd_calendar'),
                'icon'        => 'dashicons-calendar',
                'shortcode'   => '[business_calendar id="ID"]',
                'enabled'     => (bool) $this->calendar,
            ),
        );

        $modules = array(
            'timetable' => array('post_type' => 'timetable', 'shortcode' => 'timetable'),
            'weekly'    => array('post_type' => 'bd_weekly', 'shortcode' => 'weekly_schedule'),
            'calendar'  => array('post_type' => 'bd_calendar', 'shortcode' => 'business_calendar'),
        );
        foreach ($cards as $index => $card) {
            $key = array_keys($modules)[$index];
            $cards[$index]['count'] = $card['enabled'] ? $this->count_items($modules[$key]['post_type']) : null;
            $cards[$index]['new_url'] = $key === 'timetable'
                ? admin_url('admin.php?page=timetable-creator')
                : admin_url('post-new.php?post_type=' . $modules[$key]['post_type']);
        }

        $recent = $this->get_recent_items($modules, 8);

        $delete_on_uninstall = (bool) get_option('pss_delete_data_on_uninstall', false);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
        $settings_updated = isset($_GET['pss-updated']);

        include PSS_PLUGIN_DIR . 'includes/views/dashboard.php';
    }

    /**
     * Number of items (published, draft, private) of a post type.
     *
     * @param string $post_type Post type.
     * @return int
     */
    private function count_items($post_type)
    {
        $counts = wp_count_posts($post_type);
        $total = 0;
        foreach (array('publish', 'draft', 'private', 'pending', 'future') as $status) {
            $total += isset($counts->$status) ? (int) $counts->$status : 0;
        }
        return $total;
    }

    /**
     * Recently updated items across enabled modules.
     *
     * Timetable cells live in a custom table, so for timetables the newest
     * cell update counts as the item's modification time too.
     *
     * @param array $modules Module map (key => post_type, shortcode).
     * @param int   $limit   Max items.
     * @return array<int,array{title:string,type:string,modified:int,edit_url:string,shortcode:string}>
     */
    private function get_recent_items(array $modules, $limit)
    {
        global $wpdb;

        $type_labels = array(
            'timetable' => __('時間割', 'picot-schedule-suite'),
            'weekly'    => __('週間スケジュール', 'picot-schedule-suite'),
            'calendar'  => __('営業日カレンダー', 'picot-schedule-suite'),
        );

        $items = array();
        foreach ($modules as $key => $module) {
            if (empty($this->$key)) {
                continue;
            }

            $posts = get_posts(array(
                'post_type'   => $module['post_type'],
                'post_status' => array('publish', 'draft', 'private'),
                'numberposts' => $limit,
                'orderby'     => 'modified',
                'order'       => 'DESC',
            ));

            $cell_updates = array();
            if ($key === 'timetable' && $posts) {
                $ids = implode(',', array_map('absint', wp_list_pluck($posts, 'ID')));
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs are absint()-ed.
                $rows = $wpdb->get_results("SELECT timetable_id, MAX(updated_at) AS updated FROM {$wpdb->prefix}timetable_data WHERE timetable_id IN ($ids) GROUP BY timetable_id");
                foreach ((array) $rows as $row) {
                    $cell_updates[(int) $row->timetable_id] = $row->updated;
                }
            }

            foreach ($posts as $post) {
                $modified = (int) get_post_modified_time('U', true, $post);
                if (isset($cell_updates[$post->ID])) {
                    // updated_at はサーバー（MySQL）時刻なので、サイト時刻として解釈
                    $cell_time = date_create_immutable($cell_updates[$post->ID], wp_timezone());
                    if ($cell_time) {
                        $modified = max($modified, $cell_time->getTimestamp());
                    }
                }

                $items[] = array(
                    'title'     => $post->post_title !== '' ? $post->post_title : __('（無題）', 'picot-schedule-suite'),
                    'type'      => $type_labels[$key],
                    'modified'  => $modified,
                    'edit_url'  => $key === 'timetable'
                        ? admin_url('admin.php?page=timetable-creator&timetable_id=' . $post->ID)
                        : (string) get_edit_post_link($post->ID, 'raw'),
                    'shortcode' => sprintf('[%s id="%d"]', $module['shortcode'], $post->ID),
                );
            }
        }

        usort($items, static function ($a, $b) {
            return $b['modified'] <=> $a['modified'];
        });

        return array_slice($items, 0, $limit);
    }

    /**
     * Notice after activation when standalones were turned off.
     */
    public function maybe_show_migration_notice()
    {
        $deactivated = get_transient('pss_deactivated_standalones');
        if (!$deactivated) {
            return;
        }
        delete_transient('pss_deactivated_standalones');

        echo '<div class="notice notice-success is-dismissible"><p>';
        echo esc_html__('Picot Schedule Suite を有効化したため、重複する単体プラグイン（時間割 / 週間スケジュール / 営業日カレンダー）を無効化しました。データはそのまま引き継がれます。', 'picot-schedule-suite');
        echo '</p></div>';
    }

    /**
     * Warn if a standalone is still active alongside the suite.
     */
    public function maybe_warn_standalone_conflict()
    {
        $active = array();
        foreach (pss_standalone_plugin_basenames() as $basename) {
            if ($this->is_standalone_active($basename)) {
                $active[] = $basename;
            }
        }
        if (empty($active)) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('Picot Schedule Suite と単体プラグインが同時に有効です。機能の重複を避けるため、単体プラグインを無効化してください。', 'picot-schedule-suite');
        echo '</p></div>';
    }
}
