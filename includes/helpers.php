<?php
/**
 * Shared helpers for Picot Schedule Suite modules.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pss_get_json_array_from_post')) {
    /**
     * Read a JSON object/array from $_POST safely.
     *
     * Protects JSON \uXXXX sequences from wp_unslash corruption and
     * validates the payload as an array.
     *
     * @param string $key POST field name.
     * @return array|null
     */
    function pss_get_json_array_from_post($key)
    {
        if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
            return null;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
        $raw = $_POST[$key];

        // Protect \uXXXX so wp_unslash does not turn "\u591c" into "u591c".
        $protected = preg_replace('/\\\\u([0-9a-fA-F]{4})/', "\0u$1", $raw);
        $unslashed = wp_unslash($protected);
        $json = preg_replace('/\0u([0-9a-fA-F]{4})/', '\\u$1', $unslashed);

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}

if (!function_exists('pss_encode_json_meta')) {
    /**
     * Encode meta JSON with readable Unicode.
     *
     * @param array $data Data to encode.
     * @return string
     */
    function pss_encode_json_meta(array $data)
    {
        return wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('pss_prepare_css_for_output')) {
    /**
     * Make user-supplied CSS safe to print inside a <style> element.
     *
     * esc_html() would turn child selectors (">") into "&gt;" and break the CSS,
     * so instead we strip tags and remove every "<" so the code can never close
     * the <style> element or open a new tag. "<" has no legitimate use in CSS.
     *
     * @param string $css Raw CSS.
     * @return string
     */
    function pss_prepare_css_for_output($css)
    {
        if (!is_string($css) || $css === '') {
            return '';
        }

        $css = wp_strip_all_tags($css);
        // Older versions stored entity-encoded selectors; restore them.
        $css = str_replace(array('&gt;', '&#62;', '&#x3e;', '&#x3E;'), '>', $css);
        $css = str_replace('<', '', $css);

        return trim($css);
    }
}

if (!function_exists('pss_style_block')) {
    /**
     * Return a <style> block for user-supplied CSS (empty string if none).
     *
     * @param string $css Raw CSS.
     * @return string
     */
    function pss_style_block($css)
    {
        $css = pss_prepare_css_for_output($css);
        if ($css === '') {
            return '';
        }
        return '<style>' . $css . '</style>';
    }
}

if (!function_exists('pss_current_content_has_shortcode')) {
    /**
     * Whether the main queried post(s) contain the given shortcode.
     *
     * Used to enqueue front-end assets in <head> only where needed. Shortcodes
     * rendered elsewhere (widgets, template parts) are covered by enqueuing
     * again from the shortcode callback, which prints the assets in the footer.
     *
     * @param string $tag Shortcode tag.
     * @return bool
     */
    function pss_current_content_has_shortcode($tag)
    {
        global $wp_query;

        if (!($wp_query instanceof WP_Query) || empty($wp_query->posts)) {
            return false;
        }

        foreach ($wp_query->posts as $post) {
            if ($post instanceof WP_Post && has_shortcode($post->post_content, $tag)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('pss_missing_data_notice')) {
    /**
     * Message for a shortcode whose data is missing.
     *
     * Visitors get nothing (no broken text on the public page); users who can
     * edit content see a hint so they can fix the shortcode ID.
     *
     * @param string $message Message for editors.
     * @param int    $post_id Requested ID.
     * @return string
     */
    function pss_missing_data_notice($message, $post_id = 0)
    {
        if (!current_user_can('edit_posts')) {
            return '';
        }

        return sprintf(
            '<p class="pss-missing-data">%s %s</p>',
            esc_html($message),
            $post_id ? esc_html(sprintf('(ID: %d)', (int) $post_id)) : ''
        );
    }
}

if (!function_exists('pss_weekday_labels')) {
    /**
     * Short weekday labels indexed 0 (Sunday) – 6 (Saturday), in the site locale.
     *
     * @return string[]
     */
    function pss_weekday_labels()
    {
        global $wp_locale;

        $labels = array();
        for ($i = 0; $i < 7; $i++) {
            $labels[$i] = $wp_locale ? $wp_locale->get_weekday_abbrev($wp_locale->get_weekday($i)) : (string) $i;
        }
        return $labels;
    }
}

if (!function_exists('pss_weekday_labels_by_key')) {
    /**
     * Short weekday labels keyed sun … sat.
     *
     * @return array<string,string>
     */
    function pss_weekday_labels_by_key()
    {
        return array_combine(
            array('sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'),
            pss_weekday_labels()
        );
    }
}

if (!function_exists('pss_month_title')) {
    /**
     * Calendar month heading, e.g. "2026年 11月".
     *
     * @param int $year  Year.
     * @param int $month Month (1-12).
     * @return string
     */
    function pss_month_title($year, $month)
    {
        /* translators: 1: year, 2: month number */
        return sprintf(__('%1$d年 %2$d月', 'picot-schedule-suite'), (int) $year, (int) $month);
    }
}

if (!function_exists('pss_decode_meta_array')) {
    /**
     * Normalise a JSON meta value (stored as a JSON string by this suite,
     * possibly as an array by older versions) to an array.
     *
     * @param mixed $value Raw meta value.
     * @return array
     */
    function pss_decode_meta_array($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            // 旧データで二重にエンコードされた文字列も救済する
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            return is_array($decoded) ? $decoded : array();
        }
        return array();
    }
}

if (!function_exists('pss_json_for_editor')) {
    /**
     * JSON for the hidden editor fields. Always an object, so an empty
     * value becomes "{}" (an empty JS array would drop date keys on save).
     *
     * @param array $data Data.
     * @return string
     */
    function pss_json_for_editor(array $data)
    {
        return (string) wp_json_encode((object) $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
