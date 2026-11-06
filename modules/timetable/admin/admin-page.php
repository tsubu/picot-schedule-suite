<?php
// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

// ダッシュボード用CSSスタイル
?>
<style>
    .timetable-dashboard-overview {
        margin: 20px 0;
        padding: 20px;
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
    }

    .dashboard-stats {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .stat-card {
        flex: 1;
        min-width: 150px;
        padding: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 8px;
        text-align: center;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    }

    .stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 2.5em;
        font-weight: bold;
        color: white;
    }

    .stat-card p {
        margin: 0;
        font-size: 1.1em;
        opacity: 0.9;
    }

    @media (max-width: 768px) {
        .dashboard-stats {
            flex-direction: column;
        }

        .stat-card {
            min-width: auto;
        }
    }
</style>
<?php

// 言語管理の初期化
global $pss_timetable_language_manager;
if (!isset($pss_timetable_language_manager)) {
    $pss_timetable_language_manager = new PSS_Timetable_Language_Manager();
}

// 言語切り替えの処理
$pss_timetable_language_manager->handle_language_switch();

// グローバル変数としてプラグインインスタンスを取得
global $pss_timetable_module;

// 時間割一覧の取得
$timetable_creator_timetables = get_posts(array(
    'post_type' => 'timetable',
    'numberposts' => -1,
    'orderby' => 'title',
    'order' => 'ASC'
));

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$timetable_creator_current_timetable_id = isset($_GET['timetable_id']) ? intval(wp_unslash($_GET['timetable_id'])) : 0;
$timetable_creator_current_timetable = null;
$timetable_creator_timetable_data = array();

if ($timetable_creator_current_timetable_id) {
    $timetable_creator_current_timetable = get_post($timetable_creator_current_timetable_id);
    if ($timetable_creator_current_timetable && $timetable_creator_current_timetable->post_type === 'timetable') {
        $timetable_creator_timetable_data = $pss_timetable_module->get_timetable_data($timetable_creator_current_timetable_id);
    }
}

// コンテンツ一覧の取得
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$timetable_creator_contents = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}timetable_contents WHERE is_active = 1 ORDER BY id");
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$timetable_creator_all_contents = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}timetable_contents ORDER BY id");
if ($timetable_creator_all_contents === null) {
    $timetable_creator_all_contents = array();
}

// エラーチェック
if ($timetable_creator_all_contents === null) {
    $timetable_creator_all_contents = array();
}

// 時間設定の取得
$timetable_creator_time_slots = array();
if ($timetable_creator_current_timetable_id) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $timetable_creator_time_slots = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}timetable_times WHERE timetable_id = %d ORDER BY sort_order",
        $timetable_creator_current_timetable_id
    ));
}

// 現在のタブ
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$timetable_creator_current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'edit';

// 時間マスター一覧の取得
$timetable_creator_time_master_list = $pss_timetable_module->get_time_master_data();


?>

<div class="wrap">
    <h1>
        <?php
        if (!$timetable_creator_current_timetable_id) {
            // ダッシュボード（時間割が選択されていない場合）
            echo esc_html(pss_timetable_text('timetable_management'));
        } else {
            // 時間割編集管理（時間割が選択されている場合）
            echo esc_html(pss_timetable_text('timetable_edit_management')) . '（' . esc_html($timetable_creator_current_timetable->post_title) . '）';
        }
        ?>
    </h1>

    <!-- 言語切り替え（ダッシュボードでのみ表示） -->
    <?php
    // ダッシュボードでのみ言語切り替えを表示（時間割が選択されていない場合）
    if (!$timetable_creator_current_timetable_id) {
        $pss_timetable_language_manager->display_language_switcher();
    }
    ?>

    <?php
    // メッセージ表示
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['error'])) {
        $timetable_creator_error_message = '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        switch ($_GET['error']) {
            case 'duplicate_title':
                $timetable_creator_error_message = '同じ名前の時間割が既に存在します。別の名前を入力してください。';
                break;
            case 'creation_failed':
                $timetable_creator_error_message = '時間割の作成に失敗しました。もう一度お試しください。';
                break;
            case 'invalid_time_format':
                $timetable_creator_error_message = '時間の形式が正しくありません。HH:MM形式で入力してください。';
                break;
            case 'invalid_time_order':
                $timetable_creator_error_message = '開始時刻は終了時刻より前である必要があります。';
                break;
            case 'update_failed':
                $timetable_creator_error_message = '更新に失敗しました。もう一度お試しください。';
                break;
            case 'no-selection':
                $timetable_creator_error_message = '適用する時間マスターを選択してください。';
                break;
            default:
                $timetable_creator_error_message = 'エラーが発生しました。';
        }
        if ($timetable_creator_error_message) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($timetable_creator_error_message) . '</p></div>';
        }
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['created'])) {
        echo wp_kses_post('<div class="notice notice-success is-dismissible"><p>' . esc_html(pss_timetable_text('notice_created', '時間割が正常に作成されました。')) . '</p></div>');
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['updated'])) {
        echo wp_kses_post('<div class="notice notice-success is-dismissible"><p>' . esc_html(pss_timetable_text('notice_updated', '時間割が正常に更新されました。')) . '</p></div>');
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['deleted'])) {
        echo wp_kses_post('<div class="notice notice-success is-dismissible"><p>' . esc_html(pss_timetable_text('notice_deleted', '時間割が正常に削除されました。')) . '</p></div>');
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['added'])) {
        echo wp_kses_post('<div class="notice notice-success is-dismissible"><p>' . esc_html(pss_timetable_text('notice_time_added', '時間マスターが正常に追加されました。')) . '</p></div>');
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['deleted'])) {
        echo wp_kses_post('<div class="notice notice-success is-dismissible"><p>' . esc_html(pss_timetable_text('notice_time_deleted', '時間マスターが正常に削除されました。')) . '</p></div>');
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['applied'])) {
        echo wp_kses_post('<div class="notice notice-success is-dismissible"><p>' . esc_html(pss_timetable_text('notice_time_applied', '時間マスターが正常に適用されました。')) . '</p></div>');
    }

    // 曜日関連のメッセージ
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['tab']) && $_GET['tab'] === 'days') {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['days_updated'])) {
            echo wp_kses_post('<div class="notice notice-success is-dismissible"><p>' . esc_html(pss_timetable_text('notice_days_updated', '曜日設定が正常に更新されました。')) . '</p></div>');
        }
    }

    // コンテンツ関連のメッセージ
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['tab']) && $_GET['tab'] === 'content') {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['added'])) {
            echo wp_kses_post('<div class="notice notice-success is-dismissible"><p>' . esc_html(pss_timetable_text('notice_content_added', 'コンテンツが正常に追加されました。')) . '</p></div>');
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['updated'])) {
            echo wp_kses_post('<div class="notice notice-success is-dismissible"><p>' . esc_html(pss_timetable_text('notice_content_updated', 'コンテンツが正常に更新されました。')) . '</p></div>');
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['deleted'])) {
            echo wp_kses_post('<div class="notice notice-success is-dismissible"><p>' . esc_html(pss_timetable_text('notice_content_deleted', 'コンテンツが正常に削除されました。')) . '</p></div>');
        }
    }

    // コピー関連のメッセージ
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['tab']) && $_GET['tab'] === 'copy') {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['copied'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $timetable_creator_new_id = isset($_GET['new_id']) ? intval($_GET['new_id']) : 0;
            $timetable_creator_message = '時間割が正常にコピーされました。';
            if ($timetable_creator_new_id) {
                $timetable_creator_message .= ' <a href="?page=timetable-creator&timetable_id=' . $timetable_creator_new_id . '">新しい時間割を編集</a>';
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . wp_kses($timetable_creator_message, array('a' => array('href' => array()))) . '</p></div>';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['error'])) {
            $timetable_creator_error_message = '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            switch ($_GET['error']) {
                case 'missing_fields':
                    $timetable_creator_error_message = '必須項目が入力されていません。';
                    break;
                case 'invalid_source':
                    $timetable_creator_error_message = '元の時間割が見つかりません。';
                    break;
                case 'duplicate_title':
                    $timetable_creator_error_message = '同じ名前の時間割が既に存在します。';
                    break;
                case 'creation_failed':
                    $timetable_creator_error_message = '時間割のコピーに失敗しました。';
                    break;
                default:
                    $timetable_creator_error_message = 'エラーが発生しました。';
            }
            if ($timetable_creator_error_message) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($timetable_creator_error_message) . '</p></div>';
            }
        }
    }

    // 名称変更関連のメッセージ
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['tab']) && $_GET['tab'] === 'rename') {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['renamed'])) {
            echo '<div class="notice notice-success is-dismissible"><p>'  . esc_html(pss_timetable_text('rename_success')) .  '</p></div>';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['error'])) {
            $timetable_creator_error_message = '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            switch ($_GET['error']) {
                case 'missing_fields':
                    $timetable_creator_error_message = '必須項目が入力されていません。';
                    break;
                case 'invalid_timetable':
                    $timetable_creator_error_message = '時間割が見つかりません。';
                    break;
                case 'duplicate_title':
                    $timetable_creator_error_message = '同じ名前の時間割が既に存在します。';
                    break;
                case 'rename_failed':
                    $timetable_creator_error_message = pss_timetable_text('rename_error');
                    break;
                default:
                    $timetable_creator_error_message = 'エラーが発生しました。';
            }
            if ($timetable_creator_error_message) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($timetable_creator_error_message) . '</p></div>';
            }
        }
    }

    // CSS関連のメッセージ
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['message']) && $_GET['message'] === 'css_updated') {
        echo '<div class="notice notice-success is-dismissible"><p>'  . esc_html(pss_timetable_text('css_saved')) .  '</p></div>';
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['error']) && $_GET['error'] === 'css_update_failed') {
        echo '<div class="notice notice-error is-dismissible"><p>'  . esc_html(pss_timetable_text('css_save_error')) .  '</p></div>';
    }
    ?>



    <!-- 時間割選択 -->
    <div class="timetable-selector">
        <h2><?php echo esc_html(pss_timetable_text('timetable_title')); ?></h2>
        <form method="get" action="">
            <input type="hidden" name="page" value="timetable-creator">
            <select name="timetable_id" onchange="this.form.submit()">
                <option value=""><?php echo esc_html(pss_timetable_text('select_time_setting')); ?></option>
                <?php foreach ($timetable_creator_timetables as $timetable_creator_timetable): ?>
                    <option value="<?php echo esc_attr($timetable_creator_timetable->ID); ?>" <?php selected($timetable_creator_current_timetable_id, $timetable_creator_timetable->ID); ?>>
                        <?php echo esc_html($timetable_creator_timetable->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($timetable_creator_current_timetable): ?>
        <!-- タブ切り替え -->
        <div class="timetable-tabs">
            <h2 class="nav-tab-wrapper">
                <a href="#edit-tab" class="nav-tab <?php echo esc_attr($timetable_creator_current_tab === 'edit' ? 'active' : ''); ?>"><?php echo esc_html(pss_timetable_text('tab_timetable')); ?></a>
                <a href="#time-tab" class="nav-tab <?php echo esc_attr($timetable_creator_current_tab === 'time' ? 'active' : ''); ?>"><?php echo esc_html(pss_timetable_text('tab_time')); ?></a>
                <a href="#content-tab" class="nav-tab <?php echo esc_attr($timetable_creator_current_tab === 'content' ? 'active' : ''); ?>"><?php echo esc_html(pss_timetable_text('tab_content')); ?></a>
                <a href="#shortcode-tab" class="nav-tab <?php echo esc_attr($timetable_creator_current_tab === 'shortcode' ? 'active' : ''); ?>"><?php echo esc_html(pss_timetable_text('tab_shortcodes')); ?></a>
                <a href="#individual-css-tab" class="nav-tab <?php echo esc_attr($timetable_creator_current_tab === 'individual-css' ? 'active' : ''); ?>"><?php echo esc_html(pss_timetable_text('tab_individual_css')); ?></a>
                <a href="#days-tab" class="nav-tab <?php echo esc_attr($timetable_creator_current_tab === 'days' ? 'active' : ''); ?>"><?php echo esc_html(pss_timetable_text('tab_days')); ?></a>
                <a href="#rename-tab" class="nav-tab <?php echo esc_attr($timetable_creator_current_tab === 'rename' ? 'active' : ''); ?>"><?php echo esc_html(pss_timetable_text('tab_rename')); ?></a>
            </h2>
        </div>



        <!-- タブ2: 時間割編集 -->
        <div id="edit-tab" class="timetable-tab-content <?php echo esc_attr($timetable_creator_current_tab === 'edit' ? 'active' : ''); ?>">
            <h3><?php echo esc_html(pss_timetable_text('edit_timetable')); ?></h3>

            <!-- 新規作成フォーム（時間割が選択されていない場合のみ表示） -->
            <?php if (!$timetable_creator_current_timetable): ?>
                <div class="create-timetable-section">
                    <h4><?php echo esc_html(pss_timetable_text('create_timetable')); ?></h4>
                    <form method="post" action="" class="create-timetable-form"><?php wp_nonce_field('timetable_creator_action', 'timetable_creator_nonce'); ?>
                        <input type="hidden" name="action" value="create_timetable">
                        <table class="form-table">
                            <tr>
                                <th><label for="timetable_title"><?php echo esc_html(pss_timetable_text('timetable_name')); ?> <span class="required"><?php echo esc_html(pss_timetable_text('required')); ?></span></label></th>
                                <td><input type="text" name="timetable_title" id="timetable_title" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="timetable_description"><?php echo esc_html(pss_timetable_text('timetable_name_desc')); ?></label></th>
                                <td><textarea name="timetable_description" id="timetable_description" rows="3" class="large-text"></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="timetable_days[]"><?php echo esc_html(pss_timetable_text('timetable_days')); ?></label></th>
                                <td>
                                    <?php
                                    $timetable_creator_days_of_week = [
                                        0 => pss_timetable_text('sun'),
                                        1 => pss_timetable_text('mon'),
                                        2 => pss_timetable_text('tue'),
                                        3 => pss_timetable_text('wed'),
                                        4 => pss_timetable_text('thu'),
                                        5 => pss_timetable_text('fri'),
                                        6 => pss_timetable_text('sat'),
                                    ];
                                    foreach ($timetable_creator_days_of_week as $timetable_creator_num => $timetable_creator_label):
                                        $timetable_creator_checked = ($timetable_creator_num >= 1 && $timetable_creator_num <= 5) ? 'checked' : '';
                                    ?>
                                        <label style="margin-right: 10px;">
                                            <input type="checkbox" name="timetable_days[]" value="<?php echo esc_attr($timetable_creator_num); ?>" <?php checked($timetable_creator_num >= 1 && $timetable_creator_num <= 5); ?>>
                                            <?php echo esc_html($timetable_creator_label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="submit" class="button button-primary" value="<?php echo esc_attr(pss_timetable_text('create_timetable_button')); ?>">
                        </p>
                    </form>
                </div>
            <?php endif; ?>

            <!-- 時間割編集 -->
            <?php if ($timetable_creator_current_timetable): ?>
                <div class="edit-timetable-section">
                    <h4><?php echo esc_html($timetable_creator_current_timetable->post_title); ?> <?php echo esc_html(pss_timetable_text('edit_timetable')); ?></h4>

                    <!-- 直感的な時間割編集UI -->
                    <div class="intuitive-timetable-editor">
                        <div class="editor-controls">
                            <div class="time-slot-selector">
                                <h5><?php echo esc_html(pss_timetable_text('time_slot_selector')); ?></h5>
                                <p><?php echo esc_html(pss_timetable_text('time_slot_selector_desc')); ?></p>
                                <select id="time-slot-selector" class="regular-text">
                                    <option value=""><?php echo esc_html(pss_timetable_text('select_time_setting')); ?></option>
                                    <?php
                                    if (empty($timetable_creator_time_master_list)) {
                                        echo '<option value="" disabled>'  . esc_html(pss_timetable_text('no_time_master')) .  '</option>';
                                    } else {
                                        foreach ($timetable_creator_time_master_list as $timetable_creator_time_master):
                                    ?>
                                            <option value="<?php echo esc_attr($timetable_creator_time_master->time_slot); ?>"
                                                data-start="<?php echo esc_attr(gmdate('H:i', strtotime($timetable_creator_time_master->start_time))); ?>"
                                                data-end="<?php echo esc_attr(gmdate('H:i', strtotime($timetable_creator_time_master->end_time))); ?>">
                                                <?php echo esc_html($timetable_creator_time_master->time_slot); ?> (<?php echo esc_html(gmdate('H:i', strtotime($timetable_creator_time_master->start_time))); ?> - <?php echo esc_html(gmdate('H:i', strtotime($timetable_creator_time_master->end_time))); ?>)
                                            </option>
                                    <?php endforeach;
                                    }
                                    ?>
                                </select>
                                <button type="button" id="add-time-slot" class="button"><?php echo esc_html(pss_timetable_text('add_time_row')); ?></button>
                            </div>


                        </div>

                        <form method="post" action="" id="timetable-form"><?php wp_nonce_field('timetable_creator_action', 'timetable_creator_nonce'); ?>
                            <input type="hidden" name="action" value="update_timetable">
                            <input type="hidden" name="timetable_id" value="<?php echo esc_attr($timetable_creator_current_timetable_id); ?>">

                            <!-- 時間設定の隠しフィールド -->
                            <?php if (!empty($timetable_creator_time_slots)): ?>
                                <?php foreach ($timetable_creator_time_slots as $timetable_creator_index => $timetable_creator_slot): ?>
                                    <input type="hidden" name="time_slots[]" value="<?php echo esc_attr($timetable_creator_slot->time_slot); ?>">
                                    <input type="hidden" name="start_times[]" value="<?php echo esc_attr($timetable_creator_slot->start_time); ?>">
                                    <input type="hidden" name="end_times[]" value="<?php echo esc_attr($timetable_creator_slot->end_time); ?>">
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <div class="timetable-grid-container">
                                <table class="timetable-grid" id="timetable-grid">
                                    <thead>
                                        <tr>
                                            <th><?php echo esc_html(pss_timetable_text('time')); ?></th>
                                            <?php
                                            // 現在の時間割の曜日設定を取得
                                            $timetable_creator_current_days_list = array();
                                            if ($timetable_creator_current_timetable_id) {
                                                $timetable_creator_current_days_list = get_post_meta($timetable_creator_current_timetable_id, '_timetable_days', true);
                                                if (!is_array($timetable_creator_current_days_list)) {
                                                    $timetable_creator_current_days_list = array(1, 2, 3, 4, 5); // デフォルトは平日
                                                }
                                            }

                                            $timetable_creator_days_of_week = [
                                                0 => pss_timetable_text('sun'),
                                                1 => pss_timetable_text('mon'),
                                                2 => pss_timetable_text('tue'),
                                                3 => pss_timetable_text('wed'),
                                                4 => pss_timetable_text('thu'),
                                                5 => pss_timetable_text('fri'),
                                                6 => pss_timetable_text('sat'),
                                            ];

                                            foreach ($timetable_creator_days_of_week as $timetable_creator_day_num => $timetable_creator_day_name):
                                                if (in_array($timetable_creator_day_num, $timetable_creator_current_days_list)):
                                            ?>
                                                    <th data-day="<?php echo esc_attr($timetable_creator_day_num); ?>"><?php echo esc_html($timetable_creator_day_name); ?></th>
                                            <?php
                                                endif;
                                            endforeach;
                                            ?>
                                        </tr>
                                    </thead>
                                    <tbody id="timetable-tbody">
                                        <?php if (!empty($timetable_creator_time_slots)): ?>
                                            <?php foreach ($timetable_creator_time_slots as $timetable_creator_slot): ?>
                                                <?php $timetable_creator_time_key = $timetable_creator_slot->time_slot; ?>
                                                <tr class="time-row" data-time="<?php echo esc_attr($timetable_creator_time_key); ?>">
                                                    <td class="time-header">
                                                        <div class="time-info">
                                                            <strong><?php echo esc_html($timetable_creator_slot->time_slot); ?></strong><br>
                                                            <small><?php echo esc_html(gmdate('H:i', strtotime($timetable_creator_slot->start_time))); ?> - <?php echo esc_html(gmdate('H:i', strtotime($timetable_creator_slot->end_time))); ?></small>
                                                        </div>
                                                        <div class="time-actions">
                                                            <button type="button" class="remove-time-row button button-small button-link-delete"><?php echo esc_html(pss_timetable_text('remove_time_row')); ?></button>
                                                        </div>
                                                    </td>
                                                    <?php foreach ($timetable_creator_days_of_week as $timetable_creator_day_num => $timetable_creator_day_name): ?>
                                                        <?php if (in_array($timetable_creator_day_num, $timetable_creator_current_days_list)): ?>
                                                            <?php
                                                            $timetable_creator_cell_data = isset($pss_timetable_module->get_timetable_data($timetable_creator_current_timetable_id)[$timetable_creator_day_num][$timetable_creator_time_key]) ? $pss_timetable_module->get_timetable_data($timetable_creator_current_timetable_id)[$timetable_creator_day_num][$timetable_creator_time_key] : array();
                                                            ?>
                                                            <td class="timetable-cell" data-day="<?php echo esc_attr($timetable_creator_day_num); ?>" data-time="<?php echo esc_attr($timetable_creator_time_key); ?>">
                                                                <div class="cell-content">
                                                                    <div class="cell-main">
                                                                        <select name="content[<?php echo esc_attr($timetable_creator_day_num); ?>][<?php echo esc_attr($timetable_creator_time_key); ?>]" class="content-select">
                                                                            <option value=""><?php echo esc_html(pss_timetable_text('select_content')); ?></option>
                                                                            <?php foreach ($timetable_creator_contents as $timetable_creator_content): ?>
                                                                                <option value="<?php echo esc_attr($timetable_creator_content->id); ?>" <?php selected($timetable_creator_cell_data['content_id'] ?? '', $timetable_creator_content->id); ?>>
                                                                                    <?php echo esc_html($timetable_creator_content->name); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="cell-options">
                                                                        <label class="observable-checkbox">
                                                                            <input type="checkbox" name="observable[<?php echo esc_attr($timetable_creator_day_num); ?>][<?php echo esc_attr($timetable_creator_time_key); ?>]" value="1" <?php checked($timetable_creator_cell_data['is_observable'] ?? false); ?>>
                                                                            <?php echo esc_html(pss_timetable_text('is_observable')); ?>
                                                                        </label>
                                                                        <textarea name="comment[<?php echo esc_attr($timetable_creator_day_num); ?>][<?php echo esc_attr($timetable_creator_time_key); ?>]" placeholder="<?php echo esc_attr(pss_timetable_text('comment_placeholder')); ?>" class="cell-comment"><?php echo esc_textarea($timetable_creator_cell_data['comment'] ?? ''); ?></textarea>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="timetable-actions">
                                <p class="submit">
                                    <input type="submit" name="submit" class="button button-primary" value="<?php echo esc_attr(pss_timetable_text('update')); ?>">
                                    <button type="button" id="clear-timetable" class="button"><?php echo esc_html(pss_timetable_text('clear_timetable')); ?></button>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- タブ3: 時間設定 -->
        <div id="time-tab" class="timetable-tab-content <?php echo esc_attr($timetable_creator_current_tab === 'time' ? 'active' : ''); ?>">
            <h3><?php echo esc_html(pss_timetable_text('time_slot_management')); ?></h3>
            <p class="description"><?php echo esc_html(pss_timetable_text('time_slot_desc')); ?></p>

            <!-- 時間マスター管理 -->
            <div class="time-master-management">
                <h4><?php echo esc_html(pss_timetable_text('time_master_management')); ?></h4>
                <p><?php echo esc_html(pss_timetable_text('time_master_desc')); ?></p>

                <!-- 時間マスター一覧 -->
                <?php
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $timetable_creator_all_time_master = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}timetable_time_master ORDER BY sort_order, time_slot");
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $timetable_creator_current_time_master_id = isset($_GET['time_master_id']) ? intval(wp_unslash($_GET['time_master_id'])) : 0;
                $timetable_creator_current_time_master = null;

                if ($timetable_creator_current_time_master_id) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $timetable_creator_current_time_master = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}timetable_time_master WHERE id = %d",
                        $timetable_creator_current_time_master_id
                    ));
                }
                ?>

                <div class="time-master-list">
                    <h5><?php echo esc_html(pss_timetable_text('time_master_list')); ?></h5>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><?php echo esc_html(pss_timetable_text('time_slot')); ?></th>
                                <th><?php echo esc_html(pss_timetable_text('time_range')); ?></th>
                                <th><?php echo esc_html(pss_timetable_text('status')); ?></th>
                                <th><?php echo esc_html(pss_timetable_text('actions')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timetable_creator_all_time_master as $timetable_creator_time_master): ?>
                                <tr>
                                    <td><?php echo esc_html($timetable_creator_time_master->id); ?></td>
                                    <td><?php echo esc_html($timetable_creator_time_master->time_slot); ?></td>
                                    <td><?php echo esc_html(gmdate('H:i', strtotime($timetable_creator_time_master->start_time)) . '〜' . gmdate('H:i', strtotime($timetable_creator_time_master->end_time))); ?></td>
                                    <td>
                                        <?php if ($timetable_creator_time_master->is_active): ?>
                                            <span class="status-active"><?php echo esc_html(pss_timetable_text('active')); ?></span>
                                        <?php else: ?>
                                            <span class="status-inactive"><?php echo esc_html(pss_timetable_text('inactive')); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?page=timetable-creator&timetable_id=<?php echo esc_attr($timetable_creator_current_timetable_id); ?>&tab=time&time_master_id=<?php echo esc_attr($timetable_creator_time_master->id); ?>" class="button button-small"><?php echo esc_html(pss_timetable_text('edit')); ?></a>
                                        <form method="post" action="" style="display:inline;"><?php wp_nonce_field('timetable_creator_action', 'timetable_creator_nonce'); ?>
                                            <input type="hidden" name="action" value="delete_time_master">
                                            <input type="hidden" name="time_master_id" value="<?php echo esc_attr($timetable_creator_time_master->id); ?>">
                                            <?php if ($timetable_creator_current_timetable_id): ?>
                                                <input type="hidden" name="timetable_id" value="<?php echo esc_attr($timetable_creator_current_timetable_id); ?>">
                                            <?php endif; ?>
                                            <input type="submit" value="<?php echo esc_attr(pss_timetable_text('delete')); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(pss_timetable_text('confirm_delete')); ?>');">
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 時間マスター追加・編集フォーム -->
                <div class="time-master-form">
                    <h5><?php echo esc_html($timetable_creator_current_time_master ? pss_timetable_text('time_master_edit') : pss_timetable_text('time_master_add')); ?></h5>
                    <form method="post" action=""><?php wp_nonce_field('timetable_creator_action', 'timetable_creator_nonce'); ?>
                        <input type="hidden" name="action" value="<?php echo esc_attr($timetable_creator_current_time_master ? 'update_time_master' : 'add_time_master'); ?>">
                        <?php if ($timetable_creator_current_time_master): ?>
                            <input type="hidden" name="time_master_id" value="<?php echo esc_attr($timetable_creator_current_time_master->id); ?>">
                        <?php endif; ?>
                        <?php if ($timetable_creator_current_timetable_id): ?>
                            <input type="hidden" name="timetable_id" value="<?php echo esc_attr($timetable_creator_current_timetable_id); ?>">
                        <?php endif; ?>

                        <table class="form-table">
                            <tr>
                                <th><label for="time_slot"><?php echo esc_html(pss_timetable_text('time_slot')); ?> <span class="required"><?php echo esc_html(pss_timetable_text('required')); ?></span></label></th>
                                <td>
                                    <input type="text" name="time_slot" id="time_slot" class="regular-text"
                                        value="<?php echo $timetable_creator_current_time_master ? esc_attr($timetable_creator_current_time_master->time_slot) : ''; ?>" required>
                                    <p class="description"><?php echo esc_html(pss_timetable_text('time_slot_desc')); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="start_time"><?php echo esc_html(pss_timetable_text('start_time')); ?> <span class="required"><?php echo esc_html(pss_timetable_text('required')); ?></span></label></th>
                                <td>
                                    <input type="time" name="start_time" id="start_time" class="regular-text" step="60"
                                        value="<?php echo $timetable_creator_current_time_master ? esc_attr(gmdate('H:i', strtotime($timetable_creator_current_time_master->start_time))) : ''; ?>" required>
                                    <p class="description"><?php echo esc_html(pss_timetable_text('start_time_desc')); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="end_time"><?php echo esc_html(pss_timetable_text('end_time')); ?> <span class="required"><?php echo esc_html(pss_timetable_text('required')); ?></span></label></th>
                                <td>
                                    <input type="time" name="end_time" id="end_time" class="regular-text" step="60"
                                        value="<?php echo $timetable_creator_current_time_master ? esc_attr(gmdate('H:i', strtotime($timetable_creator_current_time_master->end_time))) : ''; ?>" required>
                                    <p class="description"><?php echo esc_html(pss_timetable_text('end_time_desc')); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="time_status"><?php echo esc_html(pss_timetable_text('status')); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="time_active" value="1"
                                            <?php checked($timetable_creator_current_time_master ? $timetable_creator_current_time_master->is_active : true); ?>>
                                        <?php echo esc_html(pss_timetable_text('active')); ?>
                                    </label>
                                    <p class="description"><?php echo esc_html(pss_timetable_text('status_desc')); ?></p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" name="submit" class="button button-primary"
                                value="<?php echo esc_attr($timetable_creator_current_time_master ? pss_timetable_text('update') : pss_timetable_text('add')); ?>">
                            <?php if ($timetable_creator_current_time_master): ?>
                                <a href="?page=timetable-creator&timetable_id=<?php echo esc_attr($timetable_creator_current_timetable_id); ?>&tab=time" class="button"><?php echo esc_html(pss_timetable_text('cancel')); ?></a>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>
            </div>

            <!-- 時間マスター適用 -->
            <?php if ($timetable_creator_current_timetable_id): ?>
                <div class="time-master-application">
                    <h4><?php echo esc_html(pss_timetable_text('apply_time_master')); ?></h4>
                    <p><?php echo esc_html(pss_timetable_text('apply_time_master_desc')); ?></p>

                    <form method="post" action="" id="time-master-form"><?php wp_nonce_field('timetable_creator_action', 'timetable_creator_nonce'); ?>
                        <input type="hidden" name="action" value="apply_time_master">
                        <input type="hidden" name="timetable_id" value="<?php echo esc_attr($timetable_creator_current_timetable_id); ?>">

                        <table class="time-master-table">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="select-all-time-master"></th>
                                    <th><?php echo esc_html(pss_timetable_text('time_slot')); ?></th>
                                    <th><?php echo esc_html(pss_timetable_text('time_range')); ?></th>
                                    <th><?php echo esc_html(pss_timetable_text('status')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($timetable_creator_time_master_list as $timetable_creator_time_master): ?>
                                    <tr>
                                        <td><input type="checkbox" name="selected_time_master[]" value="<?php echo esc_attr($timetable_creator_time_master->id); ?>"></td>
                                        <td><?php echo esc_html($timetable_creator_time_master->time_slot); ?></td>
                                        <td><?php echo esc_html(gmdate('H:i', strtotime($timetable_creator_time_master->start_time)) . '〜' . gmdate('H:i', strtotime($timetable_creator_time_master->end_time))); ?></td>
                                        <td>
                                            <?php if ($timetable_creator_time_master->is_active): ?>
                                                <span class="status-active"><?php echo esc_html(pss_timetable_text('active')); ?></span>
                                            <?php else: ?>
                                                <span class="status-inactive"><?php echo esc_html(pss_timetable_text('inactive')); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="submit">
                            <input type="submit" name="submit" class="button button-secondary" value="<?php echo esc_attr(pss_timetable_text('apply_selected')); ?>">
                        </p>
                    </form>
                </div>
            <?php endif; ?>

            <div class="time-master-help">
                <h4><?php echo esc_html(pss_timetable_text('help_title')); ?></h4>
                <ul>
                    <li><?php echo esc_html(pss_timetable_text('help_1')); ?></li>
                    <li><?php echo esc_html(pss_timetable_text('help_2')); ?></li>
                    <li><?php echo esc_html(pss_timetable_text('help_3')); ?></li>
                </ul>
            </div>
        </div>

        <!-- タブ4: 名称変更 -->
        <div id="rename-tab" class="timetable-tab-content <?php echo esc_attr($timetable_creator_current_tab === 'rename' ? 'active' : ''); ?>">
            <h3><?php echo esc_html(pss_timetable_text('rename_timetable')); ?></h3>
            <p><?php echo esc_html(pss_timetable_text('rename_desc')); ?></p>

            <div class="rename-form">
                <form method="post" action=""><?php wp_nonce_field('timetable_creator_action', 'timetable_creator_nonce'); ?>
                    <input type="hidden" name="action" value="rename_timetable">
                    <input type="hidden" name="timetable_id" value="<?php echo esc_attr($timetable_creator_current_timetable_id); ?>">

                    <table class="form-table">
                        <tr>
                            <th><label for="current_timetable_name"><?php echo esc_html(pss_timetable_text('current_name')); ?></label></th>
                            <td>
                                <input type="text" name="current_timetable_name" id="current_timetable_name" class="regular-text"
                                    value="<?php echo esc_attr($timetable_creator_current_timetable->post_title); ?>" readonly>
                                <p class="description"><?php echo esc_html(pss_timetable_text('current_name_desc')); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="new_timetable_name"><?php echo esc_html(pss_timetable_text('new_name')); ?> <span class="required"><?php echo esc_html(pss_timetable_text('required')); ?></span></label></th>
                            <td>
                                <input type="text" name="new_timetable_name" id="new_timetable_name" class="regular-text" required>
                                <p class="description"><?php echo esc_html(pss_timetable_text('new_name_desc')); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="submit" class="button button-primary" value="<?php echo esc_attr(pss_timetable_text('rename_button')); ?>">
                    </p>
                </form>
            </div>
        </div>

        <!-- タブ5: ショートコード -->
        <div id="shortcode-tab" class="timetable-tab-content <?php echo esc_attr($timetable_creator_current_tab === 'shortcode' ? 'active' : ''); ?>">
            <h3><?php echo esc_html(pss_timetable_text('shortcode_management')); ?></h3>
            <p><?php echo esc_html(pss_timetable_text('shortcode_desc')); ?></p>

            <div class="shortcode-display">
                <div class="shortcode-box">
                    <code>[timetable id="<?php echo esc_html($timetable_creator_current_timetable_id); ?>"]</code>
                    <button type="button" class="button button-small copy-shortcode"
                        data-shortcode="[timetable id=&quot;<?php echo esc_attr($timetable_creator_current_timetable_id); ?>&quot;]">
                        <?php echo esc_html(pss_timetable_text('copy_to_clipboard')); ?>
                    </button>
                </div>
                <p class="description"><?php echo esc_html(pss_timetable_text('shortcode_usage')); ?></p>
            </div>
        </div>

        <!-- タブ2: 曜日編集 -->
        <div id="days-tab" class="timetable-tab-content <?php echo esc_attr($timetable_creator_current_tab === 'days' ? 'active' : ''); ?>">
            <h3><?php echo esc_html(pss_timetable_text('day_management')); ?></h3>

            <?php
            // 現在の時間割の曜日設定を取得
            $timetable_creator_current_days_list = array();
            if ($timetable_creator_current_timetable_id) {
                $timetable_creator_current_days_list = get_post_meta($timetable_creator_current_timetable_id, '_timetable_days', true);
                if (!is_array($timetable_creator_current_days_list)) {
                    $timetable_creator_current_days_list = array(1, 2, 3, 4, 5); // デフォルトは平日
                }
            }

            $timetable_creator_days_of_week = [
                0 => array('name' => pss_timetable_text('sun'), 'label' => pss_timetable_text('sun_full')),
                1 => array('name' => pss_timetable_text('mon'), 'label' => pss_timetable_text('mon_full')),
                2 => array('name' => pss_timetable_text('tue'), 'label' => pss_timetable_text('tue_full')),
                3 => array('name' => pss_timetable_text('wed'), 'label' => pss_timetable_text('wed_full')),
                4 => array('name' => pss_timetable_text('thu'), 'label' => pss_timetable_text('thu_full')),
                5 => array('name' => pss_timetable_text('fri'), 'label' => pss_timetable_text('fri_full')),
                6 => array('name' => pss_timetable_text('sat'), 'label' => pss_timetable_text('sat_full')),
            ];
            ?>

            <div class="days-management">
                <h4><?php echo esc_html(pss_timetable_text('day_settings')); ?></h4>
                <p><?php echo esc_html(pss_timetable_text('day_settings_desc')); ?></p>

                <form method="post" action="" class="days-form"><?php wp_nonce_field('timetable_creator_action', 'timetable_creator_nonce'); ?>
                    <input type="hidden" name="action" value="update_days">
                    <?php if ($timetable_creator_current_timetable_id): ?>
                        <input type="hidden" name="timetable_id" value="<?php echo esc_attr($timetable_creator_current_timetable_id); ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><?php echo esc_html(pss_timetable_text('select_days')); ?></th>
                            <td>
                                <?php foreach ($timetable_creator_days_of_week as $timetable_creator_day_num => $timetable_creator_day_info): ?>
                                    <label style="display: inline-block; margin-right: 15px; margin-bottom: 10px;">
                                        <input type="checkbox" name="timetable_days[]" value="<?php echo esc_attr($timetable_creator_day_num); ?>"
                                            <?php checked(in_array($timetable_creator_day_num, $timetable_creator_current_days_list)); ?>>
                                        <?php echo esc_html($timetable_creator_day_info['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="submit" class="button button-primary" value="<?php echo esc_attr(pss_timetable_text('update_days')); ?>">
                    </p>
                </form>

                <div class="days-help">
                    <h4><?php echo esc_html(pss_timetable_text('days_help_title')); ?></h4>
                    <ul>
                        <li><?php echo esc_html(pss_timetable_text('days_help_1')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('days_help_2')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('days_help_3')); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- タブ3: コンテンツ管理 -->
        <div id="content-tab" class="timetable-tab-content <?php echo esc_attr($timetable_creator_current_tab === 'content' ? 'active' : ''); ?>">
            <h3><?php echo esc_html(pss_timetable_text('content_management')); ?></h3>

            <!-- コンテンツ一覧 -->
            <?php
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $timetable_creator_all_contents = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}timetable_contents ORDER BY id");
            if ($timetable_creator_all_contents === null) {
                $timetable_creator_all_contents = array();
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $timetable_creator_current_content_id = isset($_GET['content_id']) ? intval(wp_unslash($_GET['content_id'])) : 0;
            $timetable_creator_current_content = null;

            if ($timetable_creator_current_content_id) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $timetable_creator_current_content = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}timetable_contents WHERE id = %d",
                    $timetable_creator_current_content_id
                ));
            }
            ?>

            <div class="contents-list">
                <h4><?php echo esc_html(pss_timetable_text('registered_contents')); ?></h4>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?php echo esc_html(pss_timetable_text('content_name')); ?></th>
                            <th><?php echo esc_html(pss_timetable_text('content_description')); ?></th>
                            <th><?php echo esc_html(pss_timetable_text('status')); ?></th>
                            <th><?php echo esc_html(pss_timetable_text('actions')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timetable_creator_all_contents as $timetable_creator_content): ?>
                            <tr>
                                <td><?php echo esc_html($timetable_creator_content->id); ?></td>
                                <td><?php echo esc_html($timetable_creator_content->name); ?></td>
                                <td><?php echo esc_html($timetable_creator_content->description); ?></td>
                                <td>
                                    <?php if ($timetable_creator_content->is_active): ?>
                                        <span class="status-active"><?php echo esc_html(pss_timetable_text('status_active')); ?></span>
                                    <?php else: ?>
                                        <span class="status-inactive"><?php echo esc_html(pss_timetable_text('status_inactive')); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=timetable-creator&timetable_id=<?php echo esc_attr($timetable_creator_current_timetable_id); ?>&tab=content&content_id=<?php echo esc_attr($timetable_creator_content->id); ?>" class="button button-small"><?php echo esc_html(pss_timetable_text('edit')); ?></a>
                                    <form method="post" action="" style="display:inline;"><?php wp_nonce_field('timetable_creator_action', 'timetable_creator_nonce'); ?>
                                        <input type="hidden" name="action" value="delete_content">
                                        <input type="hidden" name="content_id" value="<?php echo esc_attr($timetable_creator_content->id); ?>">
                                        <?php if ($timetable_creator_current_timetable_id): ?>
                                            <input type="hidden" name="timetable_id" value="<?php echo esc_attr($timetable_creator_current_timetable_id); ?>">
                                        <?php endif; ?>
                                        <input type="submit" value="<?php echo esc_attr(pss_timetable_text('delete')); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(pss_timetable_text('confirm_delete')); ?>');">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- コンテンツ追加・編集フォーム -->
            <div class="content-form">
                <h4><?php echo esc_html($timetable_creator_current_content ? pss_timetable_text('content_edit') : pss_timetable_text('content_add')); ?></h4>
                <form method="post" action=""><?php wp_nonce_field('timetable_creator_action', 'timetable_creator_nonce'); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr($timetable_creator_current_content ? 'update_content' : 'add_content'); ?>">
                    <?php if ($timetable_creator_current_content): ?>
                        <input type="hidden" name="content_id" value="<?php echo esc_attr($timetable_creator_current_content->id); ?>">
                    <?php endif; ?>
                    <?php if ($timetable_creator_current_timetable_id): ?>
                        <input type="hidden" name="timetable_id" value="<?php echo esc_attr($timetable_creator_current_timetable_id); ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="content_name"><?php echo esc_html(pss_timetable_text('content_name')); ?> <span class="required"><?php echo esc_html(pss_timetable_text('required')); ?></span></label></th>
                            <td>
                                <input type="text" name="content_name" id="content_name" class="regular-text"
                                    value="<?php echo $timetable_creator_current_content ? esc_attr($timetable_creator_current_content->name) : ''; ?>" required>
                                <p class="description"><?php echo esc_html(pss_timetable_text('content_name_description')); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="content_description"><?php echo esc_html(pss_timetable_text('content_description')); ?></label></th>
                            <td>
                                <textarea name="content_description" id="content_description" rows="3" class="large-text"><?php echo $timetable_creator_current_content ? esc_textarea($timetable_creator_current_content->description) : ''; ?></textarea>
                                <p class="description"><?php echo esc_html(pss_timetable_text('content_description_desc')); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="content_status"><?php echo esc_html(pss_timetable_text('status')); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="content_active" value="1"
                                        <?php checked($timetable_creator_current_content ? $timetable_creator_current_content->is_active : true); ?>>
                                    <?php echo esc_html(pss_timetable_text('status_active')); ?>
                                </label>
                                <p class="description"><?php echo esc_html(pss_timetable_text('content_status_description')); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="submit" class="button button-primary"
                            value="<?php echo esc_attr($timetable_creator_current_content ? pss_timetable_text('update') : pss_timetable_text('content_add')); ?>">
                        <?php if ($timetable_creator_current_content): ?>
                            <a href="?page=timetable-creator&timetable_id=<?php echo esc_attr($timetable_creator_current_timetable_id); ?>&tab=content" class="button"><?php echo esc_html(pss_timetable_text('cancel')); ?></a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>

        <!-- タブ6: 個別CSS -->
        <div id="individual-css-tab" class="timetable-tab-content <?php echo esc_attr($timetable_creator_current_tab === 'individual-css' ? 'active' : ''); ?>">
            <h3><?php echo esc_html(pss_timetable_text('individual_css_title')); ?></h3>
            <p><?php echo esc_html(pss_timetable_text('individual_css_desc')); ?></p>

            <?php
            // 個別CSSの取得
            $timetable_creator_individual_css = $pss_timetable_module->get_individual_css($timetable_creator_current_timetable_id);
            ?>

            <div class="css-management">
                <form method="post" action="" class="css-form"><?php wp_nonce_field('timetable_creator_action', 'timetable_creator_nonce'); ?>
                    <input type="hidden" name="action" value="update_individual_css">
                    <input type="hidden" name="timetable_id" value="<?php echo esc_attr($timetable_creator_current_timetable_id); ?>">

                    <table class="form-table">
                        <tr>
                            <th><label for="css_code"><?php echo esc_html(pss_timetable_text('css_code')); ?></label></th>
                            <td>
                                <textarea name="css_code" id="css_code" rows="20" class="large-text code" style="font-family: monospace;"><?php
                                                                                                                                            if ($timetable_creator_individual_css && !empty($timetable_creator_individual_css->css_code)) {
                                                                                                                                                echo esc_textarea($timetable_creator_individual_css->css_code);
                                                                                                                                            } else {
                                                                                                                                                echo esc_textarea('.timetable-container {
    margin: 20px 0;
    font-family: "Hiragino Kaku Gothic ProN", "Hiragino Sans", "Yu Gothic", "Meiryo", sans-serif;
}

.timetable-table {
    width: 100%;
    border-collapse: collapse;
    border: 2px solid #333;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.timetable-header {
    background: #4a90e2;
    color: #fff;
    font-weight: bold;
    text-align: center;
    padding: 12px 8px;
    border: 1px solid #2c5aa0;
}

.timetable-cell {
    border: 1px solid #ddd;
    padding: 8px;
    vertical-align: top;
    min-height: 60px;
}

.timetable-time {
    background: #f8f9fa;
    font-weight: bold;
    text-align: center;
    padding: 10px 8px;
    border: 1px solid #ddd;
    color: #333;
}

.timetable-content {
    font-weight: 500;
    color: #333;
    margin-bottom: 4px;
}

.timetable-observable {
    font-size: 0.8em;
    color: #e74c3c;
    font-weight: bold;
    background: #ffeaea;
    padding: 2px 6px;
    border-radius: 3px;
    display: inline-block;
    margin-top: 4px;
}

.timetable-comment {
    font-size: 0.8em;
    color: #666;
    font-style: italic;
    margin-top: 4px;
    padding: 2px 0;
}

@media (max-width: 768px) {
    .timetable-table {
        font-size: 0.9em;
    }
    
    .timetable-header,
    .timetable-cell,
    .timetable-time {
        padding: 6px 4px;
    }
    
    .timetable-content {
        font-size: 0.9em;
    }
    
    .timetable-observable,
    .timetable-comment {
        font-size: 0.7em;
    }
}');
                                                                                                                                            }
                                                                                                                                            ?></textarea>
                                <p class="description"><?php echo esc_html(pss_timetable_text('css_code_desc')); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="css_active"><?php echo esc_html(pss_timetable_text('css_active')); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="is_active" value="1"
                                        <?php checked($timetable_creator_individual_css ? $timetable_creator_individual_css->is_active : true); ?>>
                                    <?php echo esc_html(pss_timetable_text('enable_status')); ?>
                                </label>
                                <p class="description"><?php echo esc_html(pss_timetable_text('css_active_desc')); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="submit" class="button button-primary" value="<?php echo esc_attr(pss_timetable_text('css_save')); ?>">
                    </p>
                </form>

                <div class="css-help">
                    <h4><?php echo esc_html(pss_timetable_text('css_selectors')); ?></h4>
                    <p><?php echo esc_html(pss_timetable_text('css_base_code_desc')); ?></p>
                    <ul>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_timetable')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_table')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_header')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_cell')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_time')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_content')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_observable')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_comment')); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- 時間割が選択されていない場合 -->
        <div class="timetable-tabs">
            <h2 class="nav-tab-wrapper">
                <a href="#new-tab" class="nav-tab active"><?php echo esc_html(pss_timetable_text('add_timetable')); ?></a>
                <a href="#content-tab" class="nav-tab"><?php echo esc_html(pss_timetable_text('tab_content')); ?></a>
                <a href="#shortcode-tab" class="nav-tab"><?php echo esc_html(pss_timetable_text('tab_shortcode')); ?></a>
                <a href="#copy-tab" class="nav-tab"><?php echo esc_html(pss_timetable_text('tab_copy')); ?></a>
                <a href="#common-css-tab" class="nav-tab"><?php echo esc_html(pss_timetable_text('tab_common_css')); ?></a>
            </h2>
        </div>



        <!-- 新規登録タブ -->
        <div id="new-tab" class="timetable-tab-content active">
            <h3><?php echo esc_html(pss_timetable_text('create_timetable')); ?></h3>
            <form method="post" action="" class="create-timetable-form"><?php wp_nonce_field('timetable_creator_action', 'timetable_creator_nonce'); ?>
                <input type="hidden" name="action" value="create_timetable">
                <table class="form-table">
                    <tr>
                        <th><label for="timetable_title"><?php echo esc_html(pss_timetable_text('timetable_name')); ?> <span class="required"><?php echo esc_html(pss_timetable_text('required')); ?></span></label></th>
                        <td><input type="text" name="timetable_title" id="timetable_title" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="timetable_description"><?php echo esc_html(pss_timetable_text('timetable_name_desc')); ?></label></th>
                        <td><textarea name="timetable_description" id="timetable_description" rows="3" class="large-text"></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="timetable_days[]"><?php echo esc_html(pss_timetable_text('timetable_days')); ?></label></th>
                        <td>
                            <?php
                            $timetable_creator_days_of_week = [
                                0 => pss_timetable_text('sun'),
                                1 => pss_timetable_text('mon'),
                                2 => pss_timetable_text('tue'),
                                3 => pss_timetable_text('wed'),
                                4 => pss_timetable_text('thu'),
                                5 => pss_timetable_text('fri'),
                                6 => pss_timetable_text('sat'),
                            ];
                            foreach ($timetable_creator_days_of_week as $timetable_creator_num => $timetable_creator_label):
                            ?>
                                <label style="margin-right: 10px;">
                                    <input type="checkbox" name="timetable_days[]" value="<?php echo esc_attr($timetable_creator_num); ?>" <?php checked($timetable_creator_num >= 1 && $timetable_creator_num <= 5); ?>>
                                    <?php echo esc_html($timetable_creator_label); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="<?php echo esc_attr(pss_timetable_text('create_timetable_button')); ?>">
                </p>
            </form>
        </div>

        <!-- コンテンツ管理タブ -->
        <div id="content-tab" class="timetable-tab-content">
            <h3><?php echo esc_html(pss_timetable_text('content_management')); ?></h3>

            <!-- コンテンツ一覧 -->
            <?php
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $timetable_creator_all_contents = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}timetable_contents ORDER BY id");
            if ($timetable_creator_all_contents === null) {
                $timetable_creator_all_contents = array();
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $timetable_creator_current_content_id = isset($_GET['content_id']) ? intval(wp_unslash($_GET['content_id'])) : 0;
            $timetable_creator_current_content = null;

            if ($timetable_creator_current_content_id) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $timetable_creator_current_content = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}timetable_contents WHERE id = %d",
                    $timetable_creator_current_content_id
                ));
            }
            ?>

            <div class="contents-list">
                <h4><?php echo esc_html(pss_timetable_text('registered_contents')); ?></h4>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?php echo esc_html(pss_timetable_text('content_name')); ?></th>
                            <th><?php echo esc_html(pss_timetable_text('content_description')); ?></th>
                            <th><?php echo esc_html(pss_timetable_text('status')); ?></th>
                            <th><?php echo esc_html(pss_timetable_text('actions')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timetable_creator_all_contents as $timetable_creator_content): ?>
                            <tr>
                                <td><?php echo esc_html($timetable_creator_content->id); ?></td>
                                <td><?php echo esc_html($timetable_creator_content->name); ?></td>
                                <td><?php echo esc_html($timetable_creator_content->description); ?></td>
                                <td>
                                    <?php if ($timetable_creator_content->is_active): ?>
                                        <span class="status-active"><?php echo esc_html(pss_timetable_text('status_active')); ?></span>
                                    <?php else: ?>
                                        <span class="status-inactive"><?php echo esc_html(pss_timetable_text('status_inactive')); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=timetable-creator&tab=content&content_id=<?php echo esc_attr($timetable_creator_content->id); ?>" class="button button-small"><?php echo esc_html(pss_timetable_text('edit')); ?></a>
                                    <form method="post" action="" style="display:inline;"><?php wp_nonce_field('timetable_creator_action', 'timetable_creator_nonce'); ?>
                                        <input type="hidden" name="action" value="delete_content">
                                        <input type="hidden" name="content_id" value="<?php echo esc_attr($timetable_creator_content->id); ?>">
                                        <input type="submit" value="<?php echo esc_attr(pss_timetable_text('delete')); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(pss_timetable_text('confirm_delete')); ?>');">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- コンテンツ追加・編集フォーム -->
            <div class="content-form">
                <h4><?php echo esc_html($timetable_creator_current_content ? pss_timetable_text('content_edit') : pss_timetable_text('content_add')); ?></h4>
                <form method="post" action=""><?php wp_nonce_field('timetable_creator_action', 'timetable_creator_nonce'); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr($timetable_creator_current_content ? 'update_content' : 'add_content'); ?>">
                    <?php if ($timetable_creator_current_content): ?>
                        <input type="hidden" name="content_id" value="<?php echo esc_attr($timetable_creator_current_content->id); ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="content_name"><?php echo esc_html(pss_timetable_text('content_name')); ?> <span class="required"><?php echo esc_html(pss_timetable_text('required')); ?></span></label></th>
                            <td>
                                <input type="text" name="content_name" id="content_name" class="regular-text"
                                    value="<?php echo $timetable_creator_current_content ? esc_attr($timetable_creator_current_content->name) : ''; ?>" required>
                                <p class="description"><?php echo esc_html(pss_timetable_text('content_name_description')); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="content_description"><?php echo esc_html(pss_timetable_text('content_description')); ?></label></th>
                            <td>
                                <textarea name="content_description" id="content_description" rows="3" class="large-text"><?php echo $timetable_creator_current_content ? esc_textarea($timetable_creator_current_content->description) : ''; ?></textarea>
                                <p class="description"><?php echo esc_html(pss_timetable_text('content_description_desc')); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="content_status"><?php echo esc_html(pss_timetable_text('status')); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="content_active" value="1"
                                        <?php checked($timetable_creator_current_content ? $timetable_creator_current_content->is_active : true); ?>>
                                    <?php echo esc_html(pss_timetable_text('status_active')); ?>
                                </label>
                                <p class="description"><?php echo esc_html(pss_timetable_text('content_status_description')); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="submit" class="button button-primary"
                            value="<?php echo esc_attr($timetable_creator_current_content ? pss_timetable_text('update') : pss_timetable_text('add')); ?>">
                        <?php if ($timetable_creator_current_content): ?>
                            <a href="?page=timetable-creator&tab=content" class="button"><?php echo esc_html(pss_timetable_text('cancel')); ?></a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>

        <!-- ショートコード一覧タブ -->
        <div id="shortcode-tab" class="timetable-tab-content">
            <h3><?php echo esc_html(pss_timetable_text('shortcode_list')); ?></h3>
            <p><?php echo esc_html(pss_timetable_text('shortcode_list_desc')); ?></p>

            <div class="shortcode-list">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html(pss_timetable_text('timetable_name')); ?></th>
                            <th><?php echo esc_html(pss_timetable_text('shortcode')); ?></th>
                            <th><?php echo esc_html(pss_timetable_text('actions')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timetable_creator_timetables as $timetable_creator_timetable): ?>
                            <tr>
                                <td><?php echo esc_html($timetable_creator_timetable->post_title); ?></td>
                                <td>
                                    <code>[timetable id="<?php echo esc_html($timetable_creator_timetable->ID); ?>"]</code>
                                </td>
                                <td>
                                    <button type="button" class="button button-small copy-shortcode"
                                        data-shortcode='[timetable id="<?php echo esc_attr($timetable_creator_timetable->ID); ?>"]'>
                                        <?php echo esc_html(pss_timetable_text('copy')); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="shortcode-help">
                <h4><?php echo esc_html(pss_timetable_text('shortcode_usage')); ?></h4>
                <ul>
                    <li><?php echo esc_html(pss_timetable_text('shortcode_usage_1')); ?></li>
                    <li><?php echo esc_html(pss_timetable_text('shortcode_usage_2')); ?></li>
                    <li><?php echo esc_html(pss_timetable_text('shortcode_usage_3')); ?></li>
                </ul>
            </div>
        </div>

        <!-- 時間割コピータブ -->
        <div id="copy-tab" class="timetable-tab-content">
            <h3><?php echo esc_html(pss_timetable_text('timetable_copy')); ?></h3>
            <p><?php echo esc_html(pss_timetable_text('timetable_copy_desc')); ?></p>

            <div class="copy-form">
                <form method="post" action=""><?php wp_nonce_field('timetable_creator_action', 'timetable_creator_nonce'); ?>
                    <input type="hidden" name="action" value="copy_timetable">

                    <table class="form-table">
                        <tr>
                            <th><label for="source_timetable_id"><?php echo esc_html(pss_timetable_text('source_timetable')); ?> <span class="required"><?php echo esc_html(pss_timetable_text('required')); ?></span></label></th>
                            <td>
                                <select name="source_timetable_id" id="source_timetable_id" required>
                                    <option value=""><?php echo esc_html(pss_timetable_text('select_source_timetable')); ?></option>
                                    <?php foreach ($timetable_creator_timetables as $timetable_creator_timetable): ?>
                                        <option value="<?php echo esc_attr($timetable_creator_timetable->ID); ?>"><?php echo esc_html($timetable_creator_timetable->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php echo esc_html(pss_timetable_text('source_timetable_desc')); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="new_timetable_name"><?php echo esc_html(pss_timetable_text('new_timetable_name')); ?> <span class="required"><?php echo esc_html(pss_timetable_text('required')); ?></span></label></th>
                            <td>
                                <input type="text" name="new_timetable_name" id="new_timetable_name" class="regular-text" required>
                                <p class="description"><?php echo esc_html(pss_timetable_text('new_timetable_name_desc')); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="copy_description"><?php echo esc_html(pss_timetable_text('copy_description')); ?></label></th>
                            <td>
                                <textarea name="copy_description" id="copy_description" rows="3" class="large-text"></textarea>
                                <p class="description"><?php echo esc_html(pss_timetable_text('copy_description_desc')); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="submit" class="button button-primary" value="<?php echo esc_attr(pss_timetable_text('copy_timetable')); ?>">
                    </p>
                </form>
            </div>

            <div class="copy-help">
                <h4><?php echo esc_html(pss_timetable_text('copy_help_title')); ?></h4>
                <ul>
                    <li><?php echo esc_html(pss_timetable_text('copy_help_1')); ?></li>
                    <li><?php echo esc_html(pss_timetable_text('copy_help_2')); ?></li>
                    <li><?php echo esc_html(pss_timetable_text('copy_help_3')); ?></li>
                </ul>
            </div>
        </div>

        <!-- 共通CSSタブ -->
        <div id="common-css-tab" class="timetable-tab-content">
            <h3><?php echo esc_html(pss_timetable_text('common_css_title')); ?></h3>
            <p><?php echo esc_html(pss_timetable_text('common_css_desc')); ?></p>

            <?php
            // 共通CSSの取得
            $timetable_creator_common_css = $pss_timetable_module->get_common_css();
            ?>

            <div class="css-management">
                <form method="post" action="" class="css-form"><?php wp_nonce_field('timetable_creator_action', 'timetable_creator_nonce'); ?>
                    <input type="hidden" name="action" value="update_common_css">

                    <table class="form-table">
                        <tr>
                            <th><label for="common_css_code"><?php echo esc_html(pss_timetable_text('css_code')); ?></label></th>
                            <td>
                                <textarea name="css_code" id="common_css_code" rows="20" class="large-text code" style="font-family: monospace;"><?php
                                                                                                                                                    if ($timetable_creator_common_css && !empty($timetable_creator_common_css->css_code)) {
                                                                                                                                                        echo esc_textarea($timetable_creator_common_css->css_code);
                                                                                                                                                    } else {
                                                                                                                                                        echo esc_textarea('.timetable-container {
    margin: 20px 0;
    font-family: "Hiragino Kaku Gothic ProN", "Hiragino Sans", "Yu Gothic", "Meiryo", sans-serif;
}

.timetable-table {
    width: 100%;
    border-collapse: collapse;
    border: 2px solid #333;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.timetable-header {
    background: #4a90e2;
    color: #fff;
    font-weight: bold;
    text-align: center;
    padding: 12px 8px;
    border: 1px solid #2c5aa0;
}

.timetable-cell {
    border: 1px solid #ddd;
    padding: 8px;
    vertical-align: top;
    min-height: 60px;
}

.timetable-time {
    background: #f8f9fa;
    font-weight: bold;
    text-align: center;
    padding: 10px 8px;
    border: 1px solid #ddd;
    color: #333;
}

.timetable-content {
    font-weight: 500;
    color: #333;
    margin-bottom: 4px;
}

.timetable-observable {
    font-size: 0.8em;
    color: #e74c3c;
    font-weight: bold;
    background: #ffeaea;
    padding: 2px 6px;
    border-radius: 3px;
    display: inline-block;
    margin-top: 4px;
}

.timetable-comment {
    font-size: 0.8em;
    color: #666;
    font-style: italic;
    margin-top: 4px;
    padding: 2px 0;
}

@media (max-width: 768px) {
    .timetable-table {
        font-size: 0.9em;
    }
    
    .timetable-header,
    .timetable-cell,
    .timetable-time {
        padding: 6px 4px;
    }
    
    .timetable-content {
        font-size: 0.9em;
    }
    
    .timetable-observable,
    .timetable-comment {
        font-size: 0.7em;
    }
}');
                                                                                                                                                    }
                                                                                                                                                    ?></textarea>
                                <p class="description"><?php echo esc_html(pss_timetable_text('css_code_desc')); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="common_css_active"><?php echo esc_html(pss_timetable_text('css_active')); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="is_active" value="1"
                                        <?php checked($timetable_creator_common_css ? $timetable_creator_common_css->is_active : true); ?>>
                                    <?php echo esc_html(pss_timetable_text('enable_status')); ?>
                                </label>
                                <p class="description"><?php echo esc_html(pss_timetable_text('css_active_desc')); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="submit" class="button button-primary" value="<?php echo esc_attr(pss_timetable_text('css_save')); ?>">
                    </p>
                </form>

                <div class="css-help">
                    <h4><?php echo esc_html(pss_timetable_text('css_selectors')); ?></h4>
                    <p><?php echo esc_html(pss_timetable_text('css_base_code_desc')); ?></p>
                    <ul>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_timetable')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_table')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_header')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_cell')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_time')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_content')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_observable')); ?></li>
                        <li><?php echo esc_html(pss_timetable_text('css_selector_comment')); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>