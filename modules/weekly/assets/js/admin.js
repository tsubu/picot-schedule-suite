jQuery(document).ready(function($) {
    const l10n = window.pssWeeklyL10n || {};
    const t = (key, fallback) => (l10n[key] !== undefined ? l10n[key] : fallback);

    // データ初期化
    let weeklyData = {};
    
    // 要素取得
    const $weeklyInput = $('#bd_weekly_schedule');
    
    // Weekly Elements
    const $timeSlotsList = $('#bd-time-slots-list');
    const $weeklyTbody = $('#bd-weekly-tbody');
    const $cornerLabelInput = $('#bd-corner-label');
    const $weekStartRadios = $('input[name="bd_week_start"]');

    // 初期化関数
    function init() {
        try {
            weeklyData = JSON.parse($weeklyInput.val() || '{"time_slots":[], "schedule":{}}');
        } catch(e) {
            weeklyData = { time_slots: [], schedule: {} };
        }
        
        // Ensure structure
        if (!weeklyData.time_slots) weeklyData.time_slots = [];
        // PHP の空配列は JSON で [] になる。配列のままだと曜日キーが JSON.stringify で消えるため object に直す
        if (!weeklyData.schedule || Array.isArray(weeklyData.schedule)) weeklyData.schedule = {};
        if (typeof weeklyData.corner_label === 'undefined') weeklyData.corner_label = t('cornerLabel', '時間帯 / 曜日');
        if (!weeklyData.week_start) weeklyData.week_start = 'sun';

        $cornerLabelInput.val(weeklyData.corner_label);
        $weekStartRadios.filter(`[value="${weeklyData.week_start}"]`).prop('checked', true);
        
        renderTimeSlots();
        renderWeeklyTable();
    }

    // --- 週間スケジュール関連 ---

    function renderTimeSlots() {
        $timeSlotsList.empty();
        weeklyData.time_slots.forEach((slot, index) => {
            const $row = $('<div>').addClass('bd-status-row').data('index', index);
            
            $row.append($('<input>').attr('type', 'text').val(slot.label).addClass('regular-text bd-slot-label').attr('placeholder', t('slotLabel', 'ラベル (例: 午前)')));
            $row.append($('<input>').attr('type', 'text').val(slot.time).addClass('regular-text bd-slot-time').attr('placeholder', t('slotTime', '時間 (例: 9:00-12:00)')));
            
            const $delBtn = $('<button>').attr('type', 'button').addClass('button bd-remove-slot').text(t('remove', '削除'));
            $row.append($delBtn);

            $timeSlotsList.append($row);
        });
    }

    $('#bd-add-time-slot-btn').on('click', function() {
        weeklyData.time_slots.push({ label: '', time: '' });
        saveWeeklyData();
        renderTimeSlots();
        renderWeeklyTable();
    });

    $(document).on('click', '.bd-remove-slot', function() {
        if (!confirm(t('confirmDelete', 'この時間帯を削除しますか？'))) return;
        const index = $(this).closest('.bd-status-row').data('index');
        weeklyData.time_slots.splice(index, 1);
        saveWeeklyData();
        renderTimeSlots();
        renderWeeklyTable();
    });

    $(document).on('change', '.bd-slot-label, .bd-slot-time', function() {
        const index = $(this).closest('.bd-status-row').data('index');
        weeklyData.time_slots[index].label = $(this).closest('.bd-status-row').find('.bd-slot-label').val();
        weeklyData.time_slots[index].time = $(this).closest('.bd-status-row').find('.bd-slot-time').val();
        saveWeeklyData();
        renderWeeklyTable(); // ラベルが変わるので再描画
    });

    // Corner Label Change
    $cornerLabelInput.on('change', function() {
        weeklyData.corner_label = $(this).val();
        saveWeeklyData();
    });

    // Week Start Change
    $weekStartRadios.on('change', function() {
        weeklyData.week_start = $('input[name="bd_week_start"]:checked').val();
        saveWeeklyData();
        renderWeeklyTable();
    });

    function renderWeeklyTable() {
        $weeklyTbody.empty();
        
        let days = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
        if (weeklyData.week_start === 'mon') {
            days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        }
        
        // Header
        const $headerRow = $('.bd-weekly-table-editor tr').first();
        // Clear existing ths after first one (corner)
        $headerRow.find('th:gt(0)').remove();
        
        const dayLabels = l10n.weekdays || {
            'sun': '日', 'mon': '月', 'tue': '火', 'wed': '水',
            'thu': '木', 'fri': '金', 'sat': '土'
        };

        days.forEach(day => {
             $headerRow.append($('<th>').text(dayLabels[day]));
        });

        // 各時間帯ごとに行を作成
        weeklyData.time_slots.forEach((slot, slotIndex) => {
            const $tr = $('<tr>');
            const label = slot.label || t('untitled', '（名称未設定）');
            const time = slot.time ? `(${slot.time})` : '';
            
            $tr.append($('<th>').text(`${label} ${time}`));
            
            days.forEach((day, dayIndex) => { // dayIndex: 0=sun
                const symbol = getWeeklySymbol(day, slotIndex);
                const $td = $('<td>').addClass('bd-weekly-cell').data('day', day).data('slot', slotIndex).text(symbol);
                $tr.append($td);
            });
            
            $weeklyTbody.append($tr);
        });
    }

    function getWeeklySymbol(day, slotIndex) {
        if (weeklyData.schedule && weeklyData.schedule[day] && weeklyData.schedule[day][slotIndex]) {
            return weeklyData.schedule[day][slotIndex];
        }
        return '-';
    }

    // 週間スケジュールのセルクリック
    $(document).on('click', '.bd-weekly-cell', function() {
        const day = $(this).data('day');
        const slot = $(this).data('slot');
        const currentSymbol = $(this).text();
        
        const symbols = ['-', '○', '△', '×', '休'];
        let nextIndex = symbols.indexOf(currentSymbol) + 1;
        if (nextIndex >= symbols.length) nextIndex = 0;
        
        const nextSymbol = symbols[nextIndex];
        
        if (!weeklyData.schedule[day]) weeklyData.schedule[day] = {};
        weeklyData.schedule[day][slot] = nextSymbol;
        
        $(this).text(nextSymbol);
        saveWeeklyData();
    });

    function saveWeeklyData() {
        $weeklyInput.val(JSON.stringify(weeklyData));
    }

    // 実行
    init();
});
