<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// 変数の準備
// $calendar_data, $config_data は呼び出し元から渡される
// 期間の設定
$config_data = is_array($config_data) ? $config_data : array();
$calendar_data = is_array($calendar_data) ? $calendar_data : array();
$config = $config_data; // Alias
$display_months = isset($config['display_months']) ? intval($config['display_months']) : 6;

// 「今月」はサイトのタイムゾーン基準（UTC 基準だと JST の月初 0〜9 時に前月が表示される）
$pss_tz = wp_timezone();
$start_date = new DateTimeImmutable('first day of this month 00:00', $pss_tz);

// 表示終了月 (YYYY-MM) が設定されている場合の計算
if (!empty($config['display_end']) && preg_match('/^\d{4}-\d{2}$/', (string) $config['display_end'])) {
    $current_date = $start_date;
    $end_date = new DateTimeImmutable($config['display_end'] . '-01 00:00', $pss_tz);

    if ($end_date >= $current_date) {
        $interval = $current_date->diff($end_date);
        $months_diff = ($interval->y * 12) + $interval->m + 1; // +1 to include end month
        $display_months = max(1, $months_diff);
    }
}
$months_to_show = min(36, max(1, $display_months));

// カスタムCSSの出力（</style> の混入で抜け出ししないよう除去）
if (!empty($config['custom_css'])) {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by pss_prepare_css_for_output().
    echo pss_style_block($config['custom_css']);
}


// ステータス設定の整理
$status_config = isset($config_data['items']) ? $config_data['items'] : array();

// デフォルトステータス
$default_status = '';
foreach ($status_config as $key => $val) {
    if (isset($val['default']) && $val['default']) {
        $default_status = $key;
        break;
    }
}
if (!$default_status && !empty($status_config)) {
    $keys = array_keys($status_config);
    $default_status = $keys[0];
}

// 週の始まり（0 = 日曜, 1 = 月曜）
$week_start = (isset($config['week_start']) && $config['week_start'] === 'mon') ? 1 : 0;
$all_weekdays = pss_weekday_labels();
$header_days = array();
for ($d = 0; $d < 7; $d++) {
    $dow = ($d + $week_start) % 7;
    $header_days[$dow] = $all_weekdays[$dow];
}

// 祝日の扱い: holiday_status にステータスキーがあれば、個別設定のない祝日にそのステータスを適用
$holiday_status = (isset($config['holiday_status']) && isset($status_config[$config['holiday_status']])) ? $config['holiday_status'] : '';
$show_holidays  = $holiday_status !== '' || !empty($config['show_holiday_names']);

$today_key = wp_date('Y-m-d');
?>

<div class="bd-calendar-wrapper bd-week-start-<?php echo $week_start ? 'mon' : 'sun'; ?>">
    <!-- Legend -->
    <div class="bd-legend">
        <?php foreach ($status_config as $key => $item): ?>
            <div class="bd-legend-item">
                <span class="bd-legend-color" style="background-color: <?php echo esc_attr(isset($item['color']) ? $item['color'] : '#fff'); ?>; border: 1px solid #ddd;"></span>
                <span class="bd-legend-label">
                    <?php echo esc_html(isset($item['label']) ? $item['label'] : $key); ?>
                    <?php if (!empty($item['hours'])): ?>
                        <span class="bd-legend-hours">(<?php echo esc_html($item['hours']); ?>)</span>
                    <?php endif; ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="bd-calendar-container">
        <?php for ($i = 0; $i < $months_to_show; $i++):
            $current_month = $start_date->modify("+$i month");
            $year = (int) $current_month->format('Y');
            $month = (int) $current_month->format('n');
            $days_in_month = (int) $current_month->format('t');
            $first_dow = (int) $current_month->format('w');
            $leading_blanks = ($first_dow - $week_start + 7) % 7;
            $holidays = $show_holidays && class_exists('PSS_Japan_Holidays') ? PSS_Japan_Holidays::for_year($year) : array();
        ?>
            <div class="bd-calendar-month">
                <h4 class="bd-month-title"><?php echo esc_html(pss_month_title($year, $month)); ?></h4>
                <table class="bd-calendar-table">
                    <thead>
                        <tr>
                            <?php foreach ($header_days as $dow => $day_name): ?>
                                <th class="bd-dow-<?php echo esc_attr($dow); ?>"><?php echo esc_html($day_name); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php
                            for ($j = 0; $j < $leading_blanks; $j++) {
                                echo '<td class="empty"></td>';
                            }

                            for ($day = 1; $day <= $days_in_month; $day++) {
                                $date_key = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                $dow = ($first_dow + $day - 1) % 7;
                                $holiday_name = isset($holidays[$date_key]) ? $holidays[$date_key] : '';

                                if (isset($calendar_data[$date_key])) {
                                    $status = $calendar_data[$date_key];
                                } elseif ($holiday_name !== '' && $holiday_status !== '') {
                                    $status = $holiday_status;
                                } else {
                                    $status = $default_status;
                                }

                                $style = '';
                                if (isset($status_config[$status])) {
                                    $bg_color = isset($status_config[$status]['color']) ? $status_config[$status]['color'] : '#fff';
                                    $text_color = isset($status_config[$status]['text_color']) ? $status_config[$status]['text_color'] : '#000';
                                    $style = "background-color: {$bg_color}; color: {$text_color};";
                                }

                                $hours = isset($status_config[$status]['hours']) ? $status_config[$status]['hours'] : '';
                                $label = isset($status_config[$status]['label']) ? $status_config[$status]['label'] : '';

                                if (($leading_blanks + $day - 1) % 7 === 0 && $day !== 1) {
                                    echo '</tr><tr>';
                                }

                                $classes = array('bd-date-cell', 'bd-status-' . $status, 'bd-dow-' . $dow);
                                if ($holiday_name !== '') {
                                    $classes[] = 'bd-holiday';
                                }
                                if ($date_key === $today_key) {
                                    $classes[] = 'today';
                                }

                                printf(
                                    '<td class="%s" style="%s" data-date="%s" data-hours="%s" data-label="%s" data-holiday="%s"%s>%d%s</td>',
                                    esc_attr(implode(' ', $classes)),
                                    esc_attr($style),
                                    esc_attr($date_key),
                                    esc_attr($hours),
                                    esc_attr($label),
                                    esc_attr($holiday_name),
                                    $holiday_name !== '' ? ' title="' . esc_attr($holiday_name) . '"' : '',
                                    absint($day),
                                    $holiday_name !== '' && !empty($config['show_holiday_names'])
                                        ? '<span class="bd-holiday-name">' . esc_html($holiday_name) . '</span>'
                                        : ''
                                );
                            }

                            $trailing = (7 - (($leading_blanks + $days_in_month) % 7)) % 7;
                            for ($j = 0; $j < $trailing; $j++) {
                                echo '<td class="empty"></td>';
                            }
                            ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endfor; ?>
    </div>
</div>
