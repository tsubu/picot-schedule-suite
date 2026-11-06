<?php
/**
 * Suite dashboard view.
 *
 * @var array $cards
 * @var array $recent
 * @var bool  $delete_on_uninstall
 * @var bool  $settings_updated
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap pss-dashboard">
    <h1><?php echo esc_html__('スケジュール管理', 'picot-schedule-suite'); ?></h1>
    <?php if ($settings_updated) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('設定を保存しました。', 'picot-schedule-suite'); ?></p></div>
    <?php endif; ?>
    <p class="pss-dashboard__lead">
        <?php echo esc_html__('時間割・週間スケジュール・営業日カレンダーをまとめて管理できます。', 'picot-schedule-suite'); ?>
    </p>

    <div class="pss-dashboard__grid">
        <?php foreach ($cards as $card) : ?>
            <section class="pss-card<?php echo empty($card['enabled']) ? ' pss-card--disabled' : ''; ?>">
                <div class="pss-card__icon">
                    <span class="dashicons <?php echo esc_attr($card['icon']); ?>" aria-hidden="true"></span>
                </div>
                <h2 class="pss-card__title">
                    <?php echo esc_html($card['title']); ?>
                    <?php if ($card['count'] !== null) : ?>
                        <span class="pss-card__count">
                            <?php
                            /* translators: %s: number of items */
                            echo esc_html(sprintf(_n('%s件', '%s件', $card['count'], 'picot-schedule-suite'), number_format_i18n($card['count'])));
                            ?>
                        </span>
                    <?php endif; ?>
                </h2>
                <p class="pss-card__desc"><?php echo esc_html($card['description']); ?></p>
                <p class="pss-card__shortcode">
                    <code><?php echo esc_html($card['shortcode']); ?></code>
                </p>
                <?php if (!empty($card['enabled'])) : ?>
                    <p class="pss-card__actions">
                        <a class="button button-primary" href="<?php echo esc_url($card['url']); ?>">
                            <?php echo esc_html__('開く', 'picot-schedule-suite'); ?>
                        </a>
                        <a class="button" href="<?php echo esc_url($card['new_url']); ?>">
                            <?php echo esc_html__('新規作成', 'picot-schedule-suite'); ?>
                        </a>
                    </p>
                <?php else : ?>
                    <p class="pss-card__actions pss-card__actions--muted">
                        <?php echo esc_html__('単体プラグインが有効なため、こちらでは無効です。', 'picot-schedule-suite'); ?>
                    </p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <h2 class="pss-dashboard__section-title"><?php echo esc_html__('最近の更新', 'picot-schedule-suite'); ?></h2>
    <?php if (empty($recent)) : ?>
        <p class="pss-dashboard__empty"><?php echo esc_html__('まだ項目がありません。上のカードから作成してください。', 'picot-schedule-suite'); ?></p>
    <?php else : ?>
        <table class="widefat striped pss-recent">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('名前', 'picot-schedule-suite'); ?></th>
                    <th scope="col"><?php echo esc_html__('種類', 'picot-schedule-suite'); ?></th>
                    <th scope="col"><?php echo esc_html__('ショートコード', 'picot-schedule-suite'); ?></th>
                    <th scope="col"><?php echo esc_html__('更新', 'picot-schedule-suite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $item) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url($item['edit_url']); ?>"><strong><?php echo esc_html($item['title']); ?></strong></a></td>
                        <td><?php echo esc_html($item['type']); ?></td>
                        <td><code><?php echo esc_html($item['shortcode']); ?></code></td>
                        <td>
                            <time datetime="<?php echo esc_attr(gmdate('c', $item['modified'])); ?>" title="<?php echo esc_attr(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $item['modified'])); ?>">
                                <?php
                                /* translators: %s: human-readable time difference */
                                echo esc_html(sprintf(__('%s前', 'picot-schedule-suite'), human_time_diff($item['modified'])));
                                ?>
                            </time>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2 class="pss-dashboard__section-title"><?php echo esc_html__('設定', 'picot-schedule-suite'); ?></h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pss-settings">
        <input type="hidden" name="action" value="pss_save_settings">
        <?php wp_nonce_field('pss_save_settings'); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php echo esc_html__('アンインストール時', 'picot-schedule-suite'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="pss_delete_data_on_uninstall" value="1" <?php checked($delete_on_uninstall); ?>>
                        <?php echo esc_html__('プラグイン削除時にすべてのスケジュールデータを削除する', 'picot-schedule-suite'); ?>
                    </label>
                    <p class="description">
                        <?php echo esc_html__('時間割・週間スケジュール・営業日カレンダーの投稿と、時間割用のデータベーステーブルを削除します。元に戻せません。単体プラグインへ戻す予定がある場合はオフのままにしてください。', 'picot-schedule-suite'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php submit_button(__('設定を保存', 'picot-schedule-suite')); ?>
    </form>
</div>
