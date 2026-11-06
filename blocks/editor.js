/**
 * Picot Schedule Suite – block editor UI (no build step).
 *
 * All three blocks share one edit component: pick an item from a select
 * (sidebar and placeholder), then preview it via ServerSideRender.
 */
(function (wp, data) {
    'use strict';

    if (!wp || !wp.blocks || !data) {
        return;
    }

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var __ = wp.i18n.__;
    var sprintf = wp.i18n.sprintf;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var Placeholder = wp.components.Placeholder;
    var Button = wp.components.Button;
    var Disabled = wp.components.Disabled;
    var ServerSideRender = wp.serverSideRender;

    var BLOCKS = {
        'timetable': {
            shortcode: 'timetable',
            label: __('時間割', 'picot-schedule-suite'),
            empty: __('時間割がまだありません。', 'picot-schedule-suite')
        },
        'weekly-schedule': {
            shortcode: 'weekly_schedule',
            label: __('週間スケジュール', 'picot-schedule-suite'),
            empty: __('週間スケジュールがまだありません。', 'picot-schedule-suite')
        },
        'business-calendar': {
            shortcode: 'business_calendar',
            label: __('営業日カレンダー', 'picot-schedule-suite'),
            empty: __('営業日カレンダーがまだありません。', 'picot-schedule-suite')
        }
    };

    function makeEdit(slug, conf) {
        var items = (data.items && data.items[slug]) || [];
        var manageUrl = data.adminUrl && data.adminUrl[slug];

        var options = [{ value: '0', label: __('— 選択してください —', 'picot-schedule-suite') }].concat(
            items.map(function (item) {
                return { value: String(item.id), label: item.title + ' (ID: ' + item.id + ')' };
            })
        );

        return function Edit(props) {
            var id = props.attributes.id || 0;
            var blockProps = useBlockProps();

            var select = el(SelectControl, {
                label: conf.label,
                value: String(id),
                options: options,
                onChange: function (value) {
                    props.setAttributes({ id: parseInt(value, 10) || 0 });
                },
                __nextHasNoMarginBottom: true,
                __next40pxDefaultSize: true
            });

            var inspector = el(
                InspectorControls,
                null,
                el(PanelBody, { title: __('表示する項目', 'picot-schedule-suite') }, select)
            );

            var body;
            if (!id) {
                body = el(
                    Placeholder,
                    {
                        label: conf.label,
                        instructions: items.length
                            ? __('表示する項目を選んでください。', 'picot-schedule-suite')
                            : conf.empty
                    },
                    items.length ? select : null,
                    manageUrl
                        ? el(Button, { variant: 'link', href: manageUrl, target: '_blank' },
                            items.length ? __('管理画面で編集', 'picot-schedule-suite') : __('管理画面で作成', 'picot-schedule-suite'))
                        : null
                );
            } else {
                body = el(
                    Disabled,
                    null,
                    el(ServerSideRender, {
                        block: props.name,
                        attributes: { id: id },
                        EmptyResponsePlaceholder: function () {
                            return el(Placeholder, {
                                label: conf.label,
                                instructions: sprintf(
                                    /* translators: %d: item ID */
                                    __('ID %d のデータが見つかりません。', 'picot-schedule-suite'),
                                    id
                                )
                            });
                        }
                    })
                );
            }

            return el(Fragment, null, inspector, el('div', blockProps, body));
        };
    }

    Object.keys(BLOCKS).forEach(function (slug) {
        // サーバー側で登録されたブロック（有効なモジュール）だけを登録
        if (!data.items || !Object.prototype.hasOwnProperty.call(data.items, slug)) {
            return;
        }
        wp.blocks.registerBlockType('picot-schedule-suite/' + slug, {
            edit: makeEdit(slug, BLOCKS[slug]),
            // 既存のショートコードブロックからブロックへ変換できるようにする
            transforms: {
                from: [{
                    type: 'shortcode',
                    tag: BLOCKS[slug].shortcode,
                    attributes: {
                        id: {
                            type: 'number',
                            shortcode: function (attrs) {
                                return parseInt(attrs.named.id, 10) || 0;
                            }
                        }
                    }
                }]
            },
            save: function () {
                return null; // dynamic block
            }
        });
    });
})(window.wp, window.pssBlocksData);
