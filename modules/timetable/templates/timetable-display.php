<?php
// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

$timetable_creator_timetable_id = intval($atts['id']);
$timetable_creator_timetable = get_post($timetable_creator_timetable_id);

if (!$timetable_creator_timetable || $timetable_creator_timetable->post_type !== 'timetable') {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pss_missing_data_notice().
    echo pss_missing_data_notice(pss_timetable_text('timetable_not_found', '指定された時間割が見つかりません。'), $timetable_creator_timetable_id);
    return;
}

// 時間割データの取得
global $wpdb;
$timetable_creator_timetable_data_list = array();
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$timetable_creator_results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}timetable_data WHERE timetable_id = %d",
    $timetable_creator_timetable_id
));

foreach ($timetable_creator_results as $timetable_creator_row) {
    $timetable_creator_timetable_data_list[$timetable_creator_row->day_of_week][$timetable_creator_row->time_slot] = array(
        'content_id' => $timetable_creator_row->content,
        'is_observable' => $timetable_creator_row->is_observable,
        'comment' => $timetable_creator_row->comment
    );
}

// 時間設定の取得
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$timetable_creator_time_slots = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}timetable_times WHERE timetable_id = %d ORDER BY sort_order",
    $timetable_creator_timetable_id
));

// コンテンツ一覧の取得
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$timetable_creator_contents_list = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}timetable_contents WHERE is_active = 1");
$timetable_creator_contents_by_id = array();
foreach ($timetable_creator_contents_list as $timetable_creator_content) {
    $timetable_creator_contents_by_id[$timetable_creator_content->id] = $timetable_creator_content;
}

// 表示する曜日を取得
$timetable_creator_selected_days = get_post_meta($timetable_creator_timetable_id, '_timetable_days', true);
if (!is_array($timetable_creator_selected_days)) {
    $timetable_creator_selected_days = array(1, 2, 3, 4, 5); // デフォルトは平日
}

$timetable_creator_all_days_list = array(
    0 => pss_timetable_text('sun', '日'),
    1 => pss_timetable_text('mon', '月'),
    2 => pss_timetable_text('tue', '火'),
    3 => pss_timetable_text('wed', '水'),
    4 => pss_timetable_text('thu', '木'),
    5 => pss_timetable_text('fri', '金'),
    6 => pss_timetable_text('sat', '土')
);
?>

<div class="timetable-display">
    <h2 class="timetable-title"><?php echo esc_html($timetable_creator_timetable->post_title); ?></h2>

    <?php if ($timetable_creator_timetable->post_content): ?>
        <div class="timetable-description">
            <?php echo wp_kses_post($timetable_creator_timetable->post_content); ?>
        </div>
    <?php endif; ?>

    <div class="timetable-container">
        <table class="timetable-table">
            <thead>
                <tr>
                    <th class="time-header"><?php echo esc_html(pss_timetable_text('time', '時間')); ?></th>
                    <?php foreach ($timetable_creator_all_days_list as $timetable_creator_day_num => $timetable_creator_day_name): ?>
                        <?php if (in_array($timetable_creator_day_num, $timetable_creator_selected_days)): ?>
                            <th class="day-header"><?php echo esc_html($timetable_creator_day_name); ?></th>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($timetable_creator_time_slots as $timetable_creator_slot): ?>
                    <tr>
                        <td class="time-cell">
                            <div class="time-slot">
                                <div class="time-label"><?php echo esc_html($timetable_creator_slot->time_slot); ?></div>
                                <div class="time-range">
                                    <?php echo esc_html($timetable_creator_slot->start_time); ?> - <?php echo esc_html($timetable_creator_slot->end_time); ?>
                                </div>
                            </div>
                        </td>
                        <?php foreach ($timetable_creator_all_days_list as $timetable_creator_day_num => $timetable_creator_day_name): ?>
                            <?php if (in_array($timetable_creator_day_num, $timetable_creator_selected_days)): ?>
                                <?php
                                $timetable_creator_cell_data = isset($timetable_creator_timetable_data_list[$timetable_creator_day_num][$timetable_creator_slot->time_slot]) ? $timetable_creator_timetable_data_list[$timetable_creator_day_num][$timetable_creator_slot->time_slot] : array();
                                $timetable_creator_content_name = '';
                                $timetable_creator_is_observable = false;
                                $timetable_creator_comment = '';

                                if (!empty($timetable_creator_cell_data['content_id']) && isset($timetable_creator_contents_by_id[$timetable_creator_cell_data['content_id']])) {
                                    $timetable_creator_content_name = $timetable_creator_contents_by_id[$timetable_creator_cell_data['content_id']]->name;
                                    $timetable_creator_is_observable = $timetable_creator_cell_data['is_observable'];
                                    $timetable_creator_comment = $timetable_creator_cell_data['comment'];
                                }
                                ?>
                                <td class="timetable-cell <?php echo $timetable_creator_is_observable ? 'observable' : ''; ?>">
                                    <?php if ($timetable_creator_content_name): ?>
                                        <div class="cell-content">
                                            <div class="content-name"><?php echo esc_html($timetable_creator_content_name); ?></div>
                                            <?php if ($timetable_creator_is_observable): ?>
                                                <div class="observable-badge"><?php echo esc_html(pss_timetable_text('observable', '見学可能')); ?></div>
                                            <?php endif; ?>
                                            <?php if ($timetable_creator_comment): ?>
                                                <div class="cell-comment"><?php echo nl2br(esc_html($timetable_creator_comment)); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-cell">-</div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (empty($timetable_creator_time_slots)): ?>
        <div class="timetable-empty">
            <p><?php echo esc_html(pss_timetable_text('no_time_settings', 'この時間割には時間設定が登録されていません。')); ?></p>
        </div>
    <?php endif; ?>
</div>