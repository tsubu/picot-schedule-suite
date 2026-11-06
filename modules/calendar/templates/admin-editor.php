<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap bd-calendar-admin-wrapper">
    <!-- Config Data (Hidden) -->
    <textarea name="bd_calendar_config" id="bd_calendar_config" style="display:none;"><?php echo esc_textarea(pss_json_for_editor($config_data)); ?></textarea>
    <!-- Calendar Data (Hidden) -->
    <textarea name="bd_calendar_data" id="bd_calendar_data" style="display:none;"><?php echo esc_textarea(pss_json_for_editor($calendar_data)); ?></textarea>

    <h2 class="nav-tab-wrapper bd-nav-tabs">
        <a href="#bd-tab-calendar" class="nav-tab nav-tab-active"><?php echo esc_html__('カレンダー管理', 'picot-schedule-suite'); ?></a>
        <a href="#bd-tab-css" class="nav-tab"><?php echo esc_html__('カスタムCSS', 'picot-schedule-suite'); ?></a>
    </h2>

    <div id="bd-tab-calendar" class="bd-tab-content active">

        <!-- Legend Configuration -->
        <div class="bd-section">
            <h3><?php echo esc_html__('営業日設定（ステータス）', 'picot-schedule-suite'); ?></h3>
            <p><?php echo esc_html__('カレンダーで使用するステータス（営業、休業など）を設定します。', 'picot-schedule-suite'); ?></p>
            <div id="bd-status-list">
                <!-- JS will render input fields here -->
            </div>
            <button type="button" class="button" id="bd-add-status-btn"><?php echo esc_html__('ステータスを追加', 'picot-schedule-suite'); ?></button>

            <hr>

            <h4><?php echo esc_html__('その他の設定', 'picot-schedule-suite'); ?></h4>
            <p>
                <label for="bd-config-display-end"><?php echo esc_html__('表示終了月 (YYYY-MM):', 'picot-schedule-suite'); ?></label>
                <input type="month" id="bd-config-display-end" class="regular-text" style="width: auto;">
                <span class="description"><?php echo esc_html__('設定すると、現在の月からこの月までを表示します。(未設定の場合はデフォルトで6ヶ月分)', 'picot-schedule-suite'); ?></span>
            </p>
            <p>
                <label for="bd-config-week-start"><?php echo esc_html__('週の始まり:', 'picot-schedule-suite'); ?></label>
                <select id="bd-config-week-start">
                    <option value="sun"><?php echo esc_html__('日曜始まり', 'picot-schedule-suite'); ?></option>
                    <option value="mon"><?php echo esc_html__('月曜始まり', 'picot-schedule-suite'); ?></option>
                </select>
            </p>
            <p>
                <label for="bd-config-holiday-status"><?php echo esc_html__('日本の祝日に適用するステータス:', 'picot-schedule-suite'); ?></label>
                <select id="bd-config-holiday-status"></select>
                <span class="description"><?php echo esc_html__('個別に設定した日はそちらが優先されます。祝日は自動計算されます（2007〜2099年）。', 'picot-schedule-suite'); ?></span>
            </p>
            <p>
                <label>
                    <input type="checkbox" id="bd-config-show-holiday-names">
                    <?php echo esc_html__('カレンダーに祝日名を表示する', 'picot-schedule-suite'); ?>
                </label>
            </p>

        </div>

        <!-- Calendar Editor -->
        <div class="bd-section">
            <h3><?php echo esc_html__('カレンダー編集', 'picot-schedule-suite'); ?></h3>
            <p><?php echo esc_html__('日付をクリックしてステータスを切り替えてください。', 'picot-schedule-suite'); ?></p>

            <div class="bd-controls">
                <label><?php echo esc_html__('表示期間:', 'picot-schedule-suite'); ?>
                    <select id="bd-display-months">
                        <option value="3"><?php echo esc_html__('3ヶ月', 'picot-schedule-suite'); ?></option>
                        <option value="6" selected><?php echo esc_html__('6ヶ月', 'picot-schedule-suite'); ?></option>
                        <option value="12"><?php echo esc_html__('12ヶ月', 'picot-schedule-suite'); ?></option>
                    </select>
                </label>
                <label><?php echo esc_html__('開始月:', 'picot-schedule-suite'); ?>
                    <input type="month" id="bd-start-month" value="<?php echo esc_attr(wp_date('Y-m')); ?>">
                </label>
                <button type="button" class="button button-primary" id="bd-refresh-calendar"><?php echo esc_html__('表示更新', 'picot-schedule-suite'); ?></button>
            </div>

            <div class="bd-tools">
                <span><?php echo esc_html__('一括設定:', 'picot-schedule-suite'); ?> </span>
                <?php foreach (pss_weekday_labels() as $pss_day_index => $pss_day_label) : ?>
                    <button type="button" class="button bd-bulk-btn" data-day="<?php echo esc_attr($pss_day_index); ?>"><?php echo esc_html($pss_day_label); ?></button>
                <?php endforeach; ?>
                <span><?php echo esc_html__('を', 'picot-schedule-suite'); ?></span>
                <select id="bd-bulk-status-select">
                    <!-- JS will render options -->
                </select>
                <span><?php echo esc_html__('にする', 'picot-schedule-suite'); ?></span>
                <button type="button" class="button" id="bd-apply-bulk"><?php echo esc_html__('適用', 'picot-schedule-suite'); ?></button>
            </div>

            <div id="bd-calendar-grid" class="bd-calendar-grid">
                <!-- Calendar will be rendered here -->
            </div>
        </div>
    </div>


    <div id="bd-tab-css" class="bd-tab-content">
        <div class="bd-section">
            <h3><?php echo esc_html__('カスタムCSS', 'picot-schedule-suite'); ?></h3>
            <p><?php echo esc_html__('カレンダーの見た目をCSSでカスタマイズできます。以下のセレクターを利用してください。', 'picot-schedule-suite'); ?></p>
            <textarea id="bd-config-custom-css" rows="20" class="large-text code" placeholder="/* Custom CSS */"></textarea>
        </div>
    </div>

</div>