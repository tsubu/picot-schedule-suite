<?php
/**
 * Block editor blocks for Picot Schedule Suite.
 *
 * Each block is a dynamic (server-rendered) wrapper around the matching
 * shortcode, so output and styling stay identical. The editor script is
 * plain JavaScript (no build step) and receives the list of selectable items
 * via wp_localize_script, so the custom post types need not be exposed to
 * the REST API.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PSS_Blocks')) {
class PSS_Blocks
{
    /** @var Picot_Schedule_Suite */
    private $suite;

    /**
     * @param Picot_Schedule_Suite $suite Suite instance.
     */
    public function __construct($suite)
    {
        $this->suite = $suite;
        add_action('init', array($this, 'register'), 20);
        add_action('enqueue_block_editor_assets', array($this, 'localize_editor_data'));
    }

    /**
     * Block definitions keyed by block directory.
     *
     * @return array<string,array{module:string,post_type:string,style:string[],script:string[]}>
     */
    private function definitions()
    {
        return array(
            'timetable' => array(
                'module'    => 'timetable',
                'post_type' => 'timetable',
                'style'     => array('pss-timetable'),
                'script'    => array(),
            ),
            'weekly-schedule' => array(
                'module'    => 'weekly',
                'post_type' => 'bd_weekly',
                'style'     => array('pss-weekly-style'),
                'script'    => array(),
            ),
            'business-calendar' => array(
                'module'    => 'calendar',
                'post_type' => 'bd_calendar',
                'style'     => array('pss-calendar-style'),
                'script'    => array('pss-calendar-script'),
            ),
        );
    }

    /**
     * Register the editor script and the blocks of enabled modules.
     */
    public function register()
    {
        wp_register_script(
            'pss-blocks-editor',
            PSS_PLUGIN_URL . 'blocks/editor.js',
            array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n'),
            PSS_VERSION,
            true
        );
        wp_set_script_translations('pss-blocks-editor', 'picot-schedule-suite');

        foreach ($this->definitions() as $dir => $def) {
            if (empty($this->suite->{$def['module']})) {
                continue; // モジュールが無効（単体プラグイン稼働中など）
            }

            register_block_type(
                PSS_PLUGIN_DIR . 'blocks/' . $dir,
                array(
                    'style_handles'       => $def['style'],
                    'view_script_handles' => $def['script'],
                    'render_callback'     => function ($attributes) use ($def) {
                        return $this->render($def['module'], $attributes);
                    },
                )
            );
        }
    }

    /**
     * Server-side render for a block.
     *
     * @param string $module     Module key.
     * @param array  $attributes Block attributes.
     * @return string
     */
    private function render($module, $attributes)
    {
        $id = isset($attributes['id']) ? absint($attributes['id']) : 0;
        if (!$id) {
            return '';
        }

        switch ($module) {
            case 'timetable':
                $html = function_exists('pss_timetable_shortcode') ? pss_timetable_shortcode(array('id' => $id)) : '';
                break;
            case 'weekly':
                $html = $this->suite->weekly->render_shortcode(array('id' => $id));
                break;
            case 'calendar':
                $html = $this->suite->calendar->render_shortcode(array('id' => $id));
                break;
            default:
                $html = '';
        }

        if ($html === '') {
            return '';
        }

        return sprintf('<div %s>%s</div>', get_block_wrapper_attributes(), $html);
    }

    /**
     * Pass selectable items (id + title) to the editor script.
     */
    public function localize_editor_data()
    {
        if (!wp_script_is('pss-blocks-editor', 'registered')) {
            return;
        }

        $items = array();
        foreach ($this->definitions() as $dir => $def) {
            if (empty($this->suite->{$def['module']})) {
                continue;
            }

            $posts = get_posts(array(
                'post_type'        => $def['post_type'],
                'post_status'      => array('publish', 'private', 'draft'),
                'numberposts'      => 200,
                'orderby'          => 'title',
                'order'            => 'ASC',
                'suppress_filters' => false,
            ));

            $items[$dir] = array_map(
                static function ($post) {
                    return array(
                        'id'    => (int) $post->ID,
                        'title' => $post->post_title !== ''
                            ? $post->post_title
                            /* translators: %d: post ID */
                            : sprintf(__('（無題 #%d）', 'picot-schedule-suite'), $post->ID),
                    );
                },
                $posts
            );
        }

        wp_localize_script('pss-blocks-editor', 'pssBlocksData', array(
            'items'    => $items,
            'adminUrl' => array(
                'timetable'         => admin_url('admin.php?page=timetable-creator'),
                'weekly-schedule'   => admin_url('edit.php?post_type=bd_weekly'),
                'business-calendar' => admin_url('edit.php?post_type=bd_calendar'),
            ),
        ));
    }
}
}
