<?php
/**
 * Japanese national holidays, calculated locally (no external API).
 *
 * Rules follow the current 国民の祝日に関する法律 and cover 2007 onward,
 * including substitute holidays (振替休日), citizens' holidays (国民の休日)
 * and the one-off changes of 2019–2021. Equinox days use the standard
 * approximation formula, valid through 2099.
 *
 * If the law changes, sites can adjust the result with the
 * `pss_japan_holidays` filter.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PSS_Japan_Holidays')) {
class PSS_Japan_Holidays
{
    /** @var array<int,array<string,string>> */
    private static $cache = array();

    /**
     * Holidays for one year.
     *
     * @param int $year Year (2007–2099).
     * @return array<string,string> 'Y-m-d' => holiday name, sorted by date.
     */
    public static function for_year($year)
    {
        $year = (int) $year;

        if (isset(self::$cache[$year])) {
            return self::$cache[$year];
        }

        $holidays = ($year >= 2007 && $year <= 2099) ? self::calculate($year) : array();

        /**
         * Filter the Japanese holidays for a year.
         *
         * @param array<string,string> $holidays 'Y-m-d' => name.
         * @param int                  $year     Year.
         */
        $holidays = apply_filters('pss_japan_holidays', $holidays, $year);
        $holidays = is_array($holidays) ? $holidays : array();
        ksort($holidays);

        self::$cache[$year] = $holidays;
        return $holidays;
    }

    /**
     * Holidays for a range of years.
     *
     * @param int $from First year.
     * @param int $to   Last year (inclusive).
     * @return array<string,string>
     */
    public static function for_years($from, $to)
    {
        $all = array();
        for ($y = (int) $from; $y <= (int) $to; $y++) {
            $all += self::for_year($y);
        }
        return $all;
    }

    /**
     * Holiday name for a date, or '' if it is not a holiday.
     *
     * @param string $date 'Y-m-d'.
     * @return string
     */
    public static function name($date)
    {
        $holidays = self::for_year((int) substr((string) $date, 0, 4));
        return isset($holidays[$date]) ? $holidays[$date] : '';
    }

    /**
     * @param int $year Year.
     * @return array<string,string>
     */
    private static function calculate($year)
    {
        $h = array();
        $add = static function ($month, $day, $name) use (&$h, $year) {
            $h[sprintf('%04d-%02d-%02d', $year, $month, $day)] = $name;
        };

        $add(1, 1, __('元日', 'picot-schedule-suite'));
        $add(1, self::nth_monday($year, 1, 2), __('成人の日', 'picot-schedule-suite'));
        $add(2, 11, __('建国記念の日', 'picot-schedule-suite'));
        if ($year >= 2020) {
            $add(2, 23, __('天皇誕生日', 'picot-schedule-suite'));
        }
        $add(3, self::vernal_equinox_day($year), __('春分の日', 'picot-schedule-suite'));
        $add(4, 29, __('昭和の日', 'picot-schedule-suite'));
        $add(5, 3, __('憲法記念日', 'picot-schedule-suite'));
        $add(5, 4, __('みどりの日', 'picot-schedule-suite'));
        $add(5, 5, __('こどもの日', 'picot-schedule-suite'));

        // 海の日 / スポーツの日 / 山の日（東京オリンピックによる 2020・2021 年の特例あり）
        if ($year === 2020) {
            $add(7, 23, __('海の日', 'picot-schedule-suite'));
            $add(7, 24, __('スポーツの日', 'picot-schedule-suite'));
            $add(8, 10, __('山の日', 'picot-schedule-suite'));
        } elseif ($year === 2021) {
            $add(7, 22, __('海の日', 'picot-schedule-suite'));
            $add(7, 23, __('スポーツの日', 'picot-schedule-suite'));
            $add(8, 8, __('山の日', 'picot-schedule-suite'));
        } else {
            $add(7, self::nth_monday($year, 7, 3), __('海の日', 'picot-schedule-suite'));
            if ($year >= 2016) {
                $add(8, 11, __('山の日', 'picot-schedule-suite'));
            }
            $add(
                10,
                self::nth_monday($year, 10, 2),
                $year >= 2020 ? __('スポーツの日', 'picot-schedule-suite') : __('体育の日', 'picot-schedule-suite')
            );
        }

        $add(9, self::nth_monday($year, 9, 3), __('敬老の日', 'picot-schedule-suite'));
        $add(9, self::autumnal_equinox_day($year), __('秋分の日', 'picot-schedule-suite'));
        $add(11, 3, __('文化の日', 'picot-schedule-suite'));
        $add(11, 23, __('勤労感謝の日', 'picot-schedule-suite'));
        if ($year <= 2018) {
            $add(12, 23, __('天皇誕生日', 'picot-schedule-suite'));
        }

        // 2019 年の御即位関連
        if ($year === 2019) {
            $add(5, 1, __('天皇の即位の日', 'picot-schedule-suite'));
            $add(10, 22, __('即位礼正殿の儀の行われる日', 'picot-schedule-suite'));
        }

        ksort($h);

        // 国民の休日: 前後を祝日に挟まれた平日（祝日以外の日）
        $citizens = array();
        foreach (array_keys($h) as $date) {
            $next = self::shift($date, 1);
            $after = self::shift($date, 2);
            if (!isset($h[$next]) && isset($h[$after]) && (int) gmdate('w', strtotime($next)) !== 0) {
                $citizens[$next] = __('国民の休日', 'picot-schedule-suite');
            }
        }
        $h += $citizens;
        ksort($h);

        // 振替休日: 日曜の祝日の後、最初の「祝日でない日」
        $substitutes = array();
        foreach (array_keys($h) as $date) {
            if ((int) gmdate('w', strtotime($date)) !== 0) {
                continue;
            }
            $day = self::shift($date, 1);
            while (isset($h[$day]) || isset($substitutes[$day])) {
                $day = self::shift($day, 1);
            }
            $substitutes[$day] = __('振替休日', 'picot-schedule-suite');
        }
        $h += $substitutes;
        ksort($h);

        // 対象年以外にはみ出した振替休日などは除外
        $prefix = sprintf('%04d-', $year);
        return array_filter(
            $h,
            static function ($date) use ($prefix) {
                return strpos($date, $prefix) === 0;
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Day of month of the nth Monday.
     */
    private static function nth_monday($year, $month, $n)
    {
        $first_dow = (int) gmdate('w', gmmktime(0, 0, 0, $month, 1, $year));
        $first_monday = 1 + ((8 - $first_dow) % 7);
        return $first_monday + 7 * ($n - 1);
    }

    private static function vernal_equinox_day($year)
    {
        return (int) floor(20.8431 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4));
    }

    private static function autumnal_equinox_day($year)
    {
        return (int) floor(23.2488 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4));
    }

    /**
     * 'Y-m-d' shifted by N days (UTC arithmetic, no DST issues).
     */
    private static function shift($date, $days)
    {
        return gmdate('Y-m-d', strtotime($date . ' 00:00:00 UTC') + 86400 * $days);
    }
}
}
