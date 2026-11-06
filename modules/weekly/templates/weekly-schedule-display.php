<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<?php

/**
 * Template for displaying the weekly schedule on the frontend.
 * 
 * Variables available:
 * - $data: Array containing 'time_slots' and 'schedule'.
 */

// データが無ければ何もしない
if (empty($data) || empty($data['time_slots'])) {
    return;
}

$days = pss_weekday_labels_by_key();

$week_start = isset($data['week_start']) ? $data['week_start'] : 'sun';
if ($week_start === 'mon') {
    // 日曜を末尾へ
    $pss_sunday = array('sun' => $days['sun']);
    unset($days['sun']);
    $days = $days + $pss_sunday;
}

// corner_labelキーが存在すればそれを使い、なければデフォルトを使う（空文字なら空文字として表示）
$corner_label = array_key_exists('corner_label', $data) ? $data['corner_label'] : __('時間帯 / 曜日', 'picot-schedule-suite');
?>
<div class="bd-weekly-schedule-wrapper">
    <table class="bd-weekly-table">
        <thead>
            <tr>
                <th class="bd-slot-header"><?php echo esc_html($corner_label); ?></th>
                <?php foreach ($days as $key => $label): ?>
                    <th class="bd-day-header bd-day-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['time_slots'] as $index => $slot): ?>
                <tr>
                    <td class="bd-slot-cell">
                        <div class="bd-slot-label"><?php echo esc_html(isset($slot['label']) ? $slot['label'] : ''); ?></div>
                        <?php if (!empty($slot['time'])): ?>
                            <div class="bd-slot-time"><?php echo esc_html($slot['time']); ?></div>
                        <?php endif; ?>
                    </td>
                    <?php foreach ($days as $day_key => $day_label): ?>
                        <?php
                        $symbol = '-';
                        if (isset($data['schedule'][$day_key]) && isset($data['schedule'][$day_key][$index])) {
                            $symbol = $data['schedule'][$day_key][$index];
                        }
                        ?>
                        <td class="bd-symbol-cell bd-symbol-<?php echo esc_attr($day_key); ?>">
                            <?php echo esc_html($symbol); ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>