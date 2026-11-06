<?php
if (!defined('ABSPATH')) {
    exit;
}


/**
 * 言語管理クラス
 */
if (!class_exists('PSS_Timetable_Language_Manager')) {
class PSS_Timetable_Language_Manager
{
    const USER_META_KEY = 'timetable_creator_language';

    private $current_language;
    public $translations;
    private $default_language = 'ja';

    public function __construct()
    {
        $this->current_language = $this->get_current_language();
        $this->load_translations();
    }

    /**
     * 現在の言語を取得
     */
    private function get_current_language()
    {
        $saved_language = $this->get_saved_language();
        if (! empty($saved_language)) {
            return $saved_language;
        }

        // WordPressの言語設定を取得
        $locale = get_locale();

        // 言語コードを抽出
        if (strpos($locale, 'ja') === 0) {
            return 'ja';
        } elseif (strpos($locale, 'zh_TW') === 0 || strpos($locale, 'zh-tw') === 0) {
            return 'zh-tw';
        } elseif (strpos($locale, 'zh') === 0) {
            return 'zh';
        } elseif (strpos($locale, 'es') === 0) {
            return 'es';
        } elseif (strpos($locale, 'de') === 0) {
            return 'de';
        } elseif (strpos($locale, 'fr') === 0) {
            return 'fr';
        } elseif (strpos($locale, 'ru') === 0) {
            return 'ru';
        } elseif (strpos($locale, 'mn') === 0) {
            return 'mn';
        } elseif (strpos($locale, 'ar') === 0) {
            return 'ar';
        } elseif (strpos($locale, 'ko') === 0) {
            return 'ko';
        } else {
            return 'en';
        }
    }

    /**
     * 現在のユーザーに保存された言語設定を取得
     *
     * @return string
     */
    private function get_saved_language()
    {
        $user_id = get_current_user_id();
        if (! $user_id) {
            return '';
        }

        $language = get_user_meta($user_id, self::USER_META_KEY, true);
        if (! is_string($language) || '' === $language) {
            return '';
        }

        $language = sanitize_key($language);

        return isset($this->get_available_languages()[ $language ]) ? $language : '';
    }

    /**
     * 現在のユーザーに言語設定を保存
     *
     * @param string $language 言語コード
     * @return void
     */
    private function save_language($language)
    {
        $user_id = get_current_user_id();
        if (! $user_id) {
            return;
        }

        update_user_meta($user_id, self::USER_META_KEY, sanitize_key($language));
    }

    /**
     * 翻訳を読み込み
     */
    private function load_translations()
    {
        // JSONファイルを優先的に読み込み
        $json_file = plugin_dir_path(__FILE__) . '../languages/' . $this->current_language . '.json';

        if (file_exists($json_file)) {
            $json_content = file_get_contents($json_file);
            $this->translations = json_decode($json_content, true);
        } else {
            // PHPファイルをフォールバックとして使用
            $php_file = plugin_dir_path(__FILE__) . '../languages/' . $this->current_language . '.php';

            if (file_exists($php_file)) {
                $this->translations = include $php_file;
            } else {
                // デフォルト言語ファイルを読み込み
                $default_json = plugin_dir_path(__FILE__) . '../languages/' . $this->default_language . '.json';
                $default_php = plugin_dir_path(__FILE__) . '../languages/' . $this->default_language . '.php';

                if (file_exists($default_json)) {
                    $json_content = file_get_contents($default_json);
                    $this->translations = json_decode($json_content, true);
                } elseif (file_exists($default_php)) {
                    $this->translations = include $default_php;
                } else {
                    $this->translations = array();
                }
            }
        }
    }

    /**
     * 翻訳テキストを取得
     */
    public function get_text($key, $default = '')
    {
        if (isset($this->translations[$key])) {
            return $this->translations[$key];
        }

        // デフォルト値が指定されている場合はそれを返す
        if (!empty($default)) {
            return $default;
        }

        // キーをそのまま返す
        return $key;
    }

    /**
     * 現在の言語を取得
     */
    public function get_language()
    {
        return $this->current_language;
    }

    /**
     * 利用可能な言語リストを取得
     */
    public function get_available_languages()
    {
        return array(
            'ja' => '日本語',
            'en' => 'English',
            'zh' => '中文（简体）',
            'zh-tw' => '台湾語',
            'es' => 'Español',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'ru' => 'Русский',
            'mn' => 'Монгол',
            'ar' => 'العربية',
            'ko' => '한국어'
        );
    }

    /**
     * 言語を変更
     */
    public function set_language($language)
    {
        $available_languages = $this->get_available_languages();

        if (isset($available_languages[$language])) {
            $this->current_language = $language;
            $this->load_translations();
            return true;
        }

        return false;
    }

    /**
     * 言語切り替えフォームを表示
     */
    public function display_language_switcher()
    {
        $current_lang = $this->get_language();
        $available_languages = $this->get_available_languages();

        echo '<div class="language-switcher">';
        echo '<form method="post" action="">';
        wp_nonce_field('timetable_language_switch', 'timetable_creator_nonce');
        echo '<label for="language_select">言語設定 / Language Settings</label>';
        echo '<select name="timetable_language" id="language_select" onchange="this.form.submit()">';

        foreach ($available_languages as $code => $name) {
            $selected = ($code === $current_lang) ? 'selected' : '';
            echo '<option value="' . esc_attr($code) . '" ' . esc_attr($selected) . '>' . esc_html($name) . '</option>';
        }

        echo '</select>';
        echo '<input type="hidden" name="page" value="timetable-creator">';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['timetable_id'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<input type="hidden" name="timetable_id" value="' . esc_attr(sanitize_text_field(wp_unslash($_GET['timetable_id']))) . '">';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['tab'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<input type="hidden" name="tab" value="' . esc_attr(sanitize_text_field(wp_unslash($_GET['tab']))) . '">';
        }
        echo '</form>';
        echo '</div>';
    }

    /**
     * 言語切り替えを処理
     */
    public function handle_language_switch()
    {
        // POSTで言語変更が要求された場合
        if (isset($_POST['timetable_language'])) {
            // CSRF対策
            if (!isset($_POST['timetable_creator_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['timetable_creator_nonce'])), 'timetable_language_switch')) {
                return;
            }

            $new_language = sanitize_text_field(wp_unslash($_POST['timetable_language']));

            // 利用可能な言語かチェック
            $available_languages = $this->get_available_languages();
            if (isset($available_languages[$new_language])) {
                $this->save_language($new_language);
                $this->set_language($new_language);

                // ページをリロード（GETパラメータを保持）
                $redirect_url = add_query_arg(array(
                    'page' => 'timetable-creator'
                ), admin_url('admin.php'));

                if (isset($_POST['timetable_id'])) {
                    $redirect_url = add_query_arg('timetable_id', absint(wp_unslash($_POST['timetable_id'])), $redirect_url);
                }
                if (isset($_POST['tab']) && is_string($_POST['tab'])) {
                    $redirect_url = add_query_arg('tab', sanitize_key(wp_unslash($_POST['tab'])), $redirect_url);
                }

                wp_safe_redirect($redirect_url);
                exit;
            }
        }
    }
}
} // class_exists PSS_Timetable_Language_Manager

// グローバル関数として翻訳テキストを取得する関数
if (!function_exists('pss_timetable_text')) {
function pss_timetable_text($key, $default = '')
{
    global $pss_timetable_language_manager;

    if (!isset($pss_timetable_language_manager)) {
        $pss_timetable_language_manager = new PSS_Timetable_Language_Manager();
    }

    return $pss_timetable_language_manager->get_text($key, $default);
}
}
