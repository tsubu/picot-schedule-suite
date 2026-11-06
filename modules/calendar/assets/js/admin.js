jQuery(document).ready(function($) {
    const l10n = window.pssCalendarL10n || {};
    const t = (key, fallback) => (l10n[key] !== undefined ? l10n[key] : fallback);
    const weekdays = l10n.weekdays || ['日', '月', '火', '水', '木', '金', '土'];
    const holidays = l10n.holidays || {};
    const monthTitle = (year, month) => t('monthTitle', '%1$d年 %2$d月')
        .replace('%1$d', year)
        .replace('%2$d', month);

    // Config Data
    let configData = {};
    let calendarData = {};
    
    // Inputs
    const $configInput = $('#bd_calendar_config');
    const $dataInput = $('#bd_calendar_data');
    
    const $displayEndInput = $('#bd-config-display-end');
    const $weekStartInput = $('#bd-config-week-start');
    const $holidayStatusInput = $('#bd-config-holiday-status');
    const $showHolidayNamesInput = $('#bd-config-show-holiday-names');
    const $customCssInput = $('#bd-config-custom-css');
    
    const $statusList = $('#bd-status-list');
    const $calendarGrid = $('#bd-calendar-grid');
    const $bulkStatusSelect = $('#bd-bulk-status-select');

    // ---------------------------------------------------------
    // Initialize
    // ---------------------------------------------------------
    function init() {
        // Parse Config
        try {
            const rawConfig = $configInput.val();
            configData = JSON.parse(rawConfig || '{"items":{}}');
        } catch(e) {
            console.error('Config Parse Error', e);
            configData = { items: { 'open': { label: t('defaultOpen', '営業日'), color: '#ffffff', text_color: '#333333' } } };
        }

        // Parse Calendar Data
        try {
            const rawData = $dataInput.val();
            calendarData = JSON.parse(rawData || '{}');
            if (!calendarData || Array.isArray(calendarData)) calendarData = {};
        } catch(e) {
            console.error('Data Parse Error', e);
            calendarData = {};
        }

        // --- Other Fields Initialization ---

        // Display End
        if (configData.display_end) {
            $displayEndInput.val(configData.display_end);
        }

        $weekStartInput.val(configData.week_start === 'mon' ? 'mon' : 'sun');
        $showHolidayNamesInput.prop('checked', !!configData.show_holiday_names);

        // Custom CSS (Pre-populate default if empty)
        let customCss = configData.custom_css || '';
        if (!customCss) {
             customCss = `/* 
 * カレンダーのカスタマイズ用CSS
 * 必要な部分のコメントを外して編集してください。
 */

/* カレンダー全体 */
/* .bd-calendar-wrapper { } */

/* ヘッダー（年月表示） */
/* .bd-month-title { } */

/* テーブル全体 */
/* .bd-month-table { } */

/* 曜日ヘッダー */
/* .bd-calendar-table th { } */

/* 日付セル */
/* .bd-calendar-table td { } */

/* 空白セル */
/* .bd-calendar-table td.empty { } */

/* 今日の日付 */
/* .bd-calendar-table td.today { } */
`;
        }
        $customCssInput.val(customCss);

        // Render UI
        renderStatusList();
        renderCalendar();
        updateBulkSelect();
    }

    // ---------------------------------------------------------
    // Event Handlers
    // ---------------------------------------------------------

    // Tab Switching
    $('.bd-nav-tabs a').on('click', function(e) {
        e.preventDefault();
        
        // Hide all tabs
        $('.bd-tab-content').removeClass('active').hide(); // Force hide in case CSS is weak
        $('.bd-nav-tabs a').removeClass('nav-tab-active');
        
        // Show target tab
        const targetId = $(this).attr('href');
        $(targetId).addClass('active').show(); // Force show
        $(this).addClass('nav-tab-active');
    });

    // Save Config Helper
    function saveConfig() {
        $configInput.val(JSON.stringify(configData));
    }
    function saveCalendarData() {
        $dataInput.val(JSON.stringify(calendarData));
    }

    // Inputs Change
    $displayEndInput.on('change', function() {
        configData.display_end = $(this).val();
        saveConfig();
    });

    $weekStartInput.on('change', function() {
        configData.week_start = $(this).val();
        saveConfig();
        renderCalendar();
    });

    $holidayStatusInput.on('change', function() {
        configData.holiday_status = $(this).val();
        saveConfig();
        renderCalendar();
    });

    $showHolidayNamesInput.on('change', function() {
        configData.show_holiday_names = $(this).is(':checked');
        saveConfig();
    });

    $customCssInput.on('change', function() {
        configData.custom_css = $(this).val();
        saveConfig();
    });

    // Status: Add
    $('#bd-add-status-btn').on('click', function() {
        const newKey = 'status_' + Date.now();
        configData.items[newKey] = {
            label: t('newStatus', '新規ステータス'),
            hours: '',
            color: '#eeeeee',
            text_color: '#000000'
        };
        saveConfig();
        renderStatusList();
        updateBulkSelect();
    });

    // Status: Remove
    $(document).on('click', '.bd-remove-status', function() {
        if (!confirm(t('confirmDelete', 'このステータスを削除しますか？'))) return;
        const key = $(this).closest('.bd-status-row').data('key');
        delete configData.items[key];
        saveConfig();
        renderStatusList();
        updateBulkSelect();
        renderCalendar(); 
    });

    // Status: Change
    $(document).on('change', '.bd-status-label, .bd-status-hours, .bd-status-bg, .bd-status-text', function() {
        const $row = $(this).closest('.bd-status-row');
        const key = $row.data('key');
        
        configData.items[key].label = $row.find('.bd-status-label').val();
        configData.items[key].hours = $row.find('.bd-status-hours').val();
        configData.items[key].color = $row.find('.bd-status-bg').val();
        configData.items[key].text_color = $row.find('.bd-status-text').val();
        
        saveConfig();
        updateBulkSelect();
        renderCalendar();
    });

    // Calendar: Refresh Button
    $('#bd-refresh-calendar').on('click', function() {
        renderCalendar();
    });

    // Calendar: Day Cell Click
    $(document).on('click', '.bd-month-table td:not(.empty)', function() {
        const dateKey = $(this).attr('data-date');
        const currentStatus = getEffectiveStatus(dateKey);
        
        const keys = Object.keys(configData.items);
        let currentIndex = keys.indexOf(currentStatus);
        let nextIndex = (currentIndex + 1) % keys.length;
        if (currentIndex === -1) nextIndex = 0;
        
        const nextStatus = keys[nextIndex];
        
        calendarData[dateKey] = nextStatus;
        saveCalendarData();
        
        const statusInfo = configData.items[nextStatus];
        $(this).css({
            'background-color': statusInfo.color,
            'color': statusInfo.text_color
        });
    });

    // Bulk: Day Button Toggle
    $('.bd-bulk-btn').on('click', function() {
        $(this).toggleClass('active button-primary');
    });

    // Bulk: Apply
    $('#bd-apply-bulk').on('click', function() {
        const targetStatus = $bulkStatusSelect.val();
        if (!targetStatus) return;

        const targetDays = [];
        $('.bd-bulk-btn.active').each(function() {
            targetDays.push(parseInt($(this).data('day')));
        });

        if (targetDays.length === 0) {
            alert(t('selectWeekday', '曜日を選択してください'));
            return;
        }

        if (!confirm(t('confirmBulk', '現在表示されている期間の指定された曜日を一括更新しますか？'))) return;

        $('.bd-month-table td:not(.empty)').each(function() {
            const dayOfWeek = parseInt($(this).data('day'));
            if (targetDays.includes(dayOfWeek)) {
                const dateKey = $(this).attr('data-date');
                calendarData[dateKey] = targetStatus;
                
                const statusInfo = configData.items[targetStatus];
                $(this).css({
                    'background-color': statusInfo.color,
                    'color': statusInfo.text_color
                });
            }
        });

        saveCalendarData();
    });

    // ---------------------------------------------------------
    // Render Functions
    // ---------------------------------------------------------

    function renderStatusList() {
        $statusList.empty();
        if (!configData.items) configData.items = {}; // Safety
        
        Object.entries(configData.items).forEach(([key, item]) => {
            const $row = $('<div>').addClass('bd-status-row').data('key', key);
            
            $row.append($('<input>').attr('type', 'text').val(key).addClass('regular-text bd-status-key').attr('placeholder', 'ID').prop('readonly', true));
            $row.append($('<input>').attr('type', 'text').val(item.label).addClass('regular-text bd-status-label').attr('placeholder', t('label', 'ラベル')));
            $row.append($('<input>').attr('type', 'text').val(item.hours || '').addClass('regular-text bd-status-hours').attr('placeholder', t('hours', '営業時間')));
            $row.append($('<input>').attr('type', 'color').val(item.color || '#ffffff').addClass('bd-color-picker bd-status-bg'));
            $row.append($('<span>').text(t('background', '背景')));
            $row.append($('<input>').attr('type', 'color').val(item.text_color || '#333333').addClass('bd-color-picker bd-status-text'));
            $row.append($('<span>').text(t('text', '文字')));
            $row.append($('<button>').attr('type', 'button').addClass('button bd-remove-status').text(t('remove', '削除')));

            $statusList.append($row);
        });
    }

    function updateBulkSelect() {
        $bulkStatusSelect.empty();
        $holidayStatusInput.empty().append($('<option>').val('').text(t('noHolidayRule', '適用しない')));
        if (!configData.items) return;
        Object.entries(configData.items).forEach(([key, item]) => {
            $bulkStatusSelect.append($('<option>').val(key).text(item.label));
            $holidayStatusInput.append($('<option>').val(key).text(item.label));
        });
        if (configData.holiday_status && !configData.items[configData.holiday_status]) {
            configData.holiday_status = '';
            saveConfig();
        }
        $holidayStatusInput.val(configData.holiday_status || '');
    }

    function renderCalendar() {
        $calendarGrid.empty();
        
        const startMonthStr = $('#bd-start-month').val();
        const duration = parseInt($('#bd-display-months').val());
        
        if (!startMonthStr) return;

        // 'YYYY-MM-01' を new Date() に渡すと UTC 扱いになり、UTC より西のタイムゾーンでは前月になる
        const [startYear, startMonth] = startMonthStr.split('-').map(Number);
        let currentDate = new Date(startYear, startMonth - 1, 1);

        for (let i = 0; i < duration; i++) {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth() + 1;
            
            const $monthDiv = $('<div>').addClass('bd-month');
            $monthDiv.append($('<div>').addClass('bd-month-title').text(monthTitle(year, month)));
            
            const $table = $('<table>').addClass('bd-month-table');
            const $headRow = $('<tr>');
            const weekStart = getWeekStart();
            for (let d = 0; d < 7; d++) {
                $headRow.append($('<th>').text(weekdays[(d + weekStart) % 7]));
            }
            const $thead = $('<thead>').append($headRow);
            const $tbody = $('<tbody>');
            
            $table.append($thead).append($tbody);
            $monthDiv.append($table);

            renderMonthBody($tbody, year, month);

            $calendarGrid.append($monthDiv);
            currentDate.setMonth(currentDate.getMonth() + 1);
        }
    }

    function renderMonthBody($tbody, year, month) {
        const firstDay = new Date(year, month - 1, 1);
        const lastDay = new Date(year, month, 0);
        const numDays = lastDay.getDate();
        const weekStart = getWeekStart();
        // 先頭の空白セル数（週の始まりに合わせる）
        const startDayOfWeek = (firstDay.getDay() - weekStart + 7) % 7;

        let dayCount = 1;
        let $row = $('<tr>');

        for (let i = 0; i < startDayOfWeek; i++) {
            $row.append($('<td>').addClass('empty'));
        }

        for (let i = startDayOfWeek; i < 7; i++) {
            if (dayCount > numDays) {
                $row.append($('<td>').addClass('empty'));
            } else {
                $row.append(createDayCell(year, month, dayCount, (i + weekStart) % 7));
                dayCount++;
            }
        }
        $tbody.append($row);

        while (dayCount <= numDays) {
            $row = $('<tr>');
            for (let i = 0; i < 7; i++) {
                if (dayCount > numDays) {
                    $row.append($('<td>').addClass('empty'));
                } else {
                    $row.append(createDayCell(year, month, dayCount, (i + weekStart) % 7));
                    dayCount++;
                }
            }
            $tbody.append($row);
        }
    }

    function createDayCell(year, month, day, dayOfWeek) {
        const dateKey = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const statusKey = getEffectiveStatus(dateKey);
        const statusInfo = (configData.items && configData.items[statusKey]) || { color: '#ffffff', text_color: '#000000' };
        const holidayName = holidays[dateKey] || '';

        const $td = $('<td>')
            .text(day)
            .attr('data-date', dateKey)
            .attr('data-day', dayOfWeek)
            .css({
                'background-color': statusInfo.color,
                'color': statusInfo.text_color
            });

        if (holidayName) {
            $td.addClass('bd-holiday').attr('title', holidayName);
        }
        if (!calendarData[dateKey]) {
            $td.addClass('bd-auto');
        }
        return $td;
    }

    function getWeekStart() {
        return configData.week_start === 'mon' ? 1 : 0;
    }

    // フロント表示と同じ優先順位: 個別設定 > 祝日ルール > デフォルト
    function getEffectiveStatus(dateKey) {
        if (calendarData[dateKey]) return calendarData[dateKey];
        if (holidays[dateKey] && configData.holiday_status && configData.items && configData.items[configData.holiday_status]) {
            return configData.holiday_status;
        }
        return getDefaultStatus();
    }

    // フロント表示と同じく、default フラグ付きのステータスを優先
    function getDefaultStatus() {
        if (!configData.items) return null;
        const entries = Object.entries(configData.items);
        const flagged = entries.find(([, item]) => item && item.default);
        if (flagged) return flagged[0];
        return entries.length > 0 ? entries[0][0] : null;
    }

    // Run Init
    init();

});
