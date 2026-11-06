<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">

    <!-- Weekly Data (Hidden) -->
    <textarea name="bd_weekly_schedule" id="bd_weekly_schedule" style="display:none;"><?php echo esc_textarea(pss_json_for_editor($weekly_data)); ?></textarea>

    <div class="bd-section">
        <h3><?php echo esc_html__('時間帯設定', 'picot-schedule-suite'); ?></h3>
        <p><?php echo esc_html__('スケジュール表の行となる時間帯を設定します。', 'picot-schedule-suite'); ?></p>
        <div id="bd-time-slots-list">
            <!-- JS will render -->
        </div>
        <button type="button" class="button" id="bd-add-time-slot-btn"><?php echo esc_html__('時間帯を追加', 'picot-schedule-suite'); ?></button>
    </div>

    <hr>

    <h3><?php echo esc_html__('週間スケジュール表', 'picot-schedule-suite'); ?></h3>
    <p><?php echo esc_html__('各曜日・時間帯の記号（○, △, ×, - など）を設定してください。', 'picot-schedule-suite'); ?></p>

    <div style="margin-bottom: 10px;">
        <label style="margin-right: 15px;">
            <input type="radio" name="bd_week_start" value="sun" checked> <?php echo esc_html__('日曜始まり', 'picot-schedule-suite'); ?>
        </label>
        <label>
            <input type="radio" name="bd_week_start" value="mon"> <?php echo esc_html__('月曜始まり', 'picot-schedule-suite'); ?>
        </label>
    </div>

    <table class="bd-weekly-table-editor widefat">
        <thead>
            <tr>
                <th><input type="text" id="bd-corner-label" class="regular-text" style="width:100%; text-align:center;"></th>
                <?php foreach (pss_weekday_labels() as $pss_day_label) : ?>
                    <th><?php echo esc_html($pss_day_label); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody id="bd-weekly-tbody">
            <!-- JS will render -->
        </tbody>
    </table>
</div>