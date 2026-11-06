<?php
/**
 * Uninstall handler for Picot Schedule Suite.
 *
 * Schedule data (custom posts, post meta and the timetable tables) is shared
 * with the former standalone plugins, so it is only removed when the site
 * owner opted in from the suite dashboard. Suite-owned options are always
 * removed.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$pss_delete_data = (bool) get_option('pss_delete_data_on_uninstall', false);

if ($pss_delete_data) {
    global $wpdb;

    // Custom posts (and their meta) for all three modules.
    $pss_post_types = array('timetable', 'bd_weekly', 'bd_calendar');
    foreach ($pss_post_types as $pss_post_type) {
        $pss_ids = get_posts(array(
            'post_type'        => $pss_post_type,
            'post_status'      => 'any',
            'numberposts'      => -1,
            'fields'           => 'ids',
            'suppress_filters' => true,
        ));
        foreach ($pss_ids as $pss_id) {
            wp_delete_post($pss_id, true);
        }
    }

    // Timetable tables.
    $pss_tables = array(
        'timetable_data',
        'timetable_times',
        'timetable_contents',
        'timetable_time_master',
        'timetable_common_css',
        'timetable_individual_css',
    );
    foreach ($pss_tables as $pss_table) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$pss_table}");
    }

    // Per-user language preference of the timetable admin.
    delete_metadata('user', 0, 'timetable_creator_language', '', true);
}

delete_option('pss_timetable_db_version');
delete_option('pss_delete_data_on_uninstall');
delete_transient('pss_deactivated_standalones');
