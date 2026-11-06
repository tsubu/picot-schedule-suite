<?php
/**
 * Plugin Name: Picot Schedule Suite
 * Plugin URI: https://picot.tokyo/aio/
 * Description: 時間割・週間スケジュール・営業日カレンダーを1つにまとめたスケジュール管理プラグイン。
 * Version: 1.1.0
 * Author: PICOT
 * Author URI: https://picot.tokyo/aio/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: picot-schedule-suite
 * Requires at least: 6.3
 * Requires PHP: 8.3
 * Tested up to: 7.2
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PSS_VERSION', '1.1.0');
define('PSS_PLUGIN_FILE', __FILE__);
define('PSS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PSS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PSS_MENU_SLUG', 'picot-schedule-suite');

require_once PSS_PLUGIN_DIR . 'includes/helpers.php';

/**
 * Standalone plugins that this suite replaces.
 *
 * @return string[]
 */
function pss_standalone_plugin_basenames()
{
    return array(
        'timetable-creator/timetable-creator.php',
        'weekly-business-schedule/weekly-business-schedule.php',
        'business-day-calendar/business-day-calendar.php',
    );
}

/**
 * Activation: deactivate overlapping standalones and set up data structures.
 */
function pss_activate()
{
    if (!function_exists('deactivate_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $to_deactivate = array();
    foreach (pss_standalone_plugin_basenames() as $basename) {
        if (is_plugin_active($basename)) {
            $to_deactivate[] = $basename;
        }
    }
    if (!empty($to_deactivate)) {
        deactivate_plugins($to_deactivate, true);
        set_transient('pss_deactivated_standalones', $to_deactivate, 60);
    }

    require_once PSS_PLUGIN_DIR . 'modules/timetable/includes/class-language-manager.php';
    require_once PSS_PLUGIN_DIR . 'modules/timetable/class-timetable-module.php';

    if (class_exists('PSS_Timetable_Module')) {
        $timetable = new PSS_Timetable_Module(false);
        $timetable->create_tables();
    }

    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'pss_activate');

/**
 * Deactivation.
 */
function pss_deactivate()
{
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'pss_deactivate');

/**
 * Bootstrap the suite after plugins_loaded so standalones are known.
 */
function pss_bootstrap()
{
    require_once PSS_PLUGIN_DIR . 'includes/class-suite.php';
    Picot_Schedule_Suite::instance();
}
add_action('plugins_loaded', 'pss_bootstrap', 5);
