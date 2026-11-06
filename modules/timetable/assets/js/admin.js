jQuery(document).ready(function($) {
    

    

    
    // タブ切り替え
    $('.timetable-tabs .nav-tab').click(function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        
        // タブのアクティブ状態を切り替え
        $('.timetable-tabs .nav-tab').removeClass('active');
        $(this).addClass('active');
        
        // コンテンツの表示を切り替え
        $('.timetable-tab-content').removeClass('active');
        $(target).addClass('active');
    });
    
    // ショートコードコピー機能
    $('.copy-shortcode').click(function() {
        var shortcode = $(this).data('shortcode');
        var textArea = document.createElement('textarea');
        textArea.value = shortcode;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        // コピー成功メッセージ
        var originalText = $(this).text();
        $(this).text('コピー完了!').addClass('button-primary');
        setTimeout(function() {
            $('.copy-shortcode').text(originalText).removeClass('button-primary');
        }, 2000);
    });
    
    // 時間設定選択から時間帯を追加
    $('#add-time-slot').click(function(e) {

        
        var selectedOption = $('#time-slot-selector option:selected');
        var timeSlot = selectedOption.val();
        

        
        // 条件判定の詳細チェック
        var isValidSelection = timeSlot && 
                              timeSlot !== '' && 
                              timeSlot !== null && 
                              timeSlot !== undefined && 
                              timeSlot !== '時間設定を選択してください' &&
                              timeSlot !== '時間マスターが登録されていません' &&
                              timeSlot.trim() !== '';
        

        
        if (isValidSelection) {
            var startTime = selectedOption.data('start');
            var endTime = selectedOption.data('end');
            

            
            // 既に同じ時間帯が追加されているかチェック
            var existingRows = $('.time-row[data-time="' + timeSlot + '"]');
            if (existingRows.length > 0) {
                return;
            }
            
            // 新しい行を追加
            
            // 時間から秒数を除去（HH:MM:SS → HH:MM）
            if (startTime && startTime.includes(':')) {
                var startParts = startTime.split(':');
                if (startParts.length >= 2) {
                    startTime = startParts[0] + ':' + startParts[1];
                }
            }
            if (endTime && endTime.includes(':')) {
                var endParts = endTime.split(':');
                if (endParts.length >= 2) {
                    endTime = endParts[0] + ':' + endParts[1];
                }
            }
            
            // 多言語対応のテキスト取得
            var deleteText = (typeof timetableTexts !== 'undefined' && tcTexts.delete) ? tcTexts.delete : '削除';
            var selectPleaseText = (typeof timetableTexts !== 'undefined' && tcTexts.select_please) ? tcTexts.select_please : '選択してください';
            var observableText = (typeof timetableTexts !== 'undefined' && tcTexts.observable) ? tcTexts.observable : '見学可';
            var commentText = (typeof timetableTexts !== 'undefined' && tcTexts.comment) ? tcTexts.comment : 'コメント';
            
            var newRow = '<tr class="time-row" data-time="' + timeSlot + '">' +
                '<td class="time-header">' +
                    '<div class="time-info">' +
                        '<strong>' + timeSlot + '</strong><br>' +
                        '<small>' + startTime + ' - ' + endTime + '</small>' +
                    '</div>' +
                    '<button type="button" class="remove-time-row button button-small button-link-delete">' + deleteText + '</button>' +
                '</td>';
            
            // 7日分のセルを追加
            for (var day = 0; day < 7; day++) {
                newRow += '<td class="timetable-cell" data-day="' + day + '" data-time="' + timeSlot + '">' +
                    '<div class="cell-content">' +
                        '<div class="cell-main">' +
                            '<select name="content[' + day + '][' + timeSlot + ']" class="content-select">' +
                                '<option value="">' + selectPleaseText + '</option>';
                
                // コンテンツオプションを追加
                $('#content-selector option').each(function() {
                    if ($(this).val()) {
                        newRow += '<option value="' + $(this).val() + '">' + $(this).text() + '</option>';
                    }
                });
                
                newRow += '</select>' +
                        '</div>' +
                        '<div class="cell-options">' +
                            '<label class="observable-checkbox">' +
                                '<input type="checkbox" name="observable[' + day + '][' + timeSlot + ']" value="1">' +
                                observableText +
                            '</label>' +
                            '<textarea name="comment[' + day + '][' + timeSlot + ']" placeholder="' + commentText + '" class="cell-comment"></textarea>' +
                        '</div>' +
                    '</div>' +
                '</td>';
            }
            
            newRow += '</tr>';
            

            
            // テーブルに追加
            $('#timetable-tbody').append(newRow);
            

            
            // 隠しフィールドに時間帯を追加
            var hiddenField = '<input type="hidden" name="time_slots[]" value="' + timeSlot + '">';
            $('#timetable-form').append(hiddenField);
            

            
            // 選択をリセット
            $('#time-slot-selector').val('');
            

            

        } else {

            
            // オプションの詳細分析
            var optionAnalysis = {
                totalOptions: $('#time-slot-selector option').length,
                selectedOption: selectedOption.length > 0 ? selectedOption.val() : 'なし',
                firstOption: $('#time-slot-selector option:first').val(),
                lastOption: $('#time-slot-selector option:last').val()
            };
            

            
            // 時間マスターの確認
            if ($('#time-slot-selector option').length === 0) {

                alert('時間マスターが登録されていません。先に時間マスターを登録してください。');
                return;
            }
            
            // 有効なオプションの確認
            var validOptions = $('#time-slot-selector option').filter(function() {
                return $(this).val() && $(this).val() !== '';
            });
            
            if (validOptions.length === 0) {

                alert('有効な時間マスターがありません。');
                return;
            }
            

            
            alert('時間設定を選択してください。');
        }
    });
    
    // 時間帯行の削除
    $(document).on('click', '.remove-time-row', function() {
        var row = $(this).closest('.time-row');
        var timeSlot = row.data('time');
        
        // 隠しフィールドからも削除
        $('input[name="time_slots[]"][value="' + timeSlot + '"]').remove();
        
        row.remove();
    });
    
    // 時間帯の追加（旧バージョン）
    $('.add-time-slot').click(function() {
        var newRow = '<tr>' +
            '<td><input type="text" name="time_slot[]" class="regular-text" placeholder="例: 1限"></td>' +
            '<td><input type="text" name="time_range[]" class="regular-text" placeholder="例: 09:00〜09:50"></td>' +
            '<td><button type="button" class="button remove-time-slot">削除</button></td>' +
            '</tr>';
        $('#time-slots-tbody').append(newRow);
    });
    
    // 時間帯の削除
    $(document).on('click', '.remove-time-slot', function() {
        if (confirm('この時間帯を削除しますか？')) {
            $(this).closest('tr').remove();
        }
    });
    
    // 見学可能チェックボックスの処理
    $(document).on('change', 'input[name*="observable"]', function() {
        var cell = $(this).closest('.timetable-cell');
        if ($(this).is(':checked')) {
            cell.addClass('observable');
        } else {
            cell.removeClass('observable');
        }
    });
    
    // 時間設定フォームの送信
    $('#time-settings-form').submit(function(e) {
        var timeSlots = [];
        var hasError = false;
        
        $('#time-slots-tbody tr').each(function() {
            var timeSlot = $(this).find('input[name="time_slot[]"]').val();
            var timeRange = $(this).find('input[name="time_range[]"]').val();
            
            if (timeSlot && timeRange) {
                // 時間範囲の形式チェック（hh:ii〜hh:ii）
                var timeRangePattern = /^\d{1,2}:\d{2}〜\d{1,2}:\d{2}$/;
                if (timeRangePattern.test(timeRange)) {
                    timeSlots.push({
                        slot: timeSlot,
                        range: timeRange
                    });
                } else {
                    hasError = true;
                }
            } else if (timeSlot || timeRange) {
                hasError = true;
            }
        });
        
        if (hasError) {
            e.preventDefault();
            return false;
        }
        
        if (timeSlots.length === 0) {
            e.preventDefault();
            return false;
        }
        
        // 重複チェック
        var slotNames = timeSlots.map(function(item) { return item.slot; });
        var uniqueSlots = slotNames.filter(function(item, pos) { return slotNames.indexOf(item) === pos; });
        
        if (slotNames.length !== uniqueSlots.length) {
            e.preventDefault();
            return false;
        }
    });
    
    // 時間割フォームの送信
    $('#timetable-form').submit(function(e) {
        var hasContent = false;
        
        $('.content-select').each(function() {
            if ($(this).val()) {
                hasContent = true;
                return false; // break
            }
        });
        
        if (!hasContent) {
            if (!confirm('コンテンツが選択されていません。このまま保存しますか？')) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // コンテンツ選択時の処理
    $(document).on('change', '.content-select', function() {
        var cell = $(this).closest('.timetable-cell');
        if ($(this).val()) {
            cell.addClass('has-content');
        } else {
            cell.removeClass('has-content');
        }
    });
    
    // コメント入力時の処理
    $(document).on('input', '.cell-comment', function() {
        var cell = $(this).closest('.timetable-cell');
        if ($(this).val().trim()) {
            cell.addClass('has-comment');
        } else {
            cell.removeClass('has-comment');
        }
    });
    
    // 初期状態の設定
    $('.content-select').each(function() {
        if ($(this).val()) {
            $(this).closest('.timetable-cell').addClass('has-content');
        }
    });
    
    $('.cell-comment').each(function() {
        if ($(this).val().trim()) {
            $(this).closest('.timetable-cell').addClass('has-comment');
        }
    });
    
    // 見学可能チェックボックスの初期状態
    $('input[name*="observable"]:checked').each(function() {
        $(this).closest('.timetable-cell').addClass('observable');
    });
    
    // 成功メッセージの自動非表示
    setTimeout(function() {
        $('.notice-success').fadeOut();
    }, 5000);
    
    // フォームの変更検知
    var formChanged = false;
    var initialFormState = {};
    
    // 初期状態を保存
    function saveInitialState() {
        $('input, select, textarea').not('.timetable-selector select').each(function() {
            var $element = $(this);
            var key = $element.attr('name') || $element.attr('id') || 'element_' + Math.random();
            if ($element.attr('type') === 'checkbox' || $element.attr('type') === 'radio') {
                initialFormState[key] = $element.is(':checked');
            } else {
                initialFormState[key] = $element.val();
            }
        });
    }
    
    // 初期状態を保存
    saveInitialState();
    
    // フォーム要素の変更を監視（時間割選択フォームは除外）
    $('input, select, textarea').not('.timetable-selector select').on('change input', function() {
        var $element = $(this);
        var key = $element.attr('name') || $element.attr('id') || 'element_' + Math.random();
        var currentValue;
        
        if ($element.attr('type') === 'checkbox' || $element.attr('type') === 'radio') {
            currentValue = $element.is(':checked');
        } else {
            currentValue = $element.val();
        }
        
        // 初期値と現在の値を比較
        if (initialFormState[key] !== currentValue) {
            formChanged = true;
        } else {
            // 他の要素もチェックして、全て初期状態に戻っているか確認（時間割選択フォームは除外）
            var allUnchanged = true;
            $('input, select, textarea').not('.timetable-selector select').each(function() {
                var $el = $(this);
                var elKey = $el.attr('name') || $el.attr('id') || 'element_' + Math.random();
                var elCurrentValue;
                
                if ($el.attr('type') === 'checkbox' || $el.attr('type') === 'radio') {
                    elCurrentValue = $el.is(':checked');
                } else {
                    elCurrentValue = $el.val();
                }
                
                if (initialFormState[elKey] !== elCurrentValue) {
                    allUnchanged = false;
                    return false; // break
                }
            });
            
            if (allUnchanged) {
                formChanged = false;
            }
        }
    });
    
    // ページ離脱時の警告
    $(window).on('beforeunload', function(e) {
        if (formChanged) {
            var message = '変更が保存されていません。このページを離れますか？';
            e.returnValue = message;
            return message;
        }
    });
    
    // フォーム送信時は警告を無効化
    $('form').on('submit', function() {
        formChanged = false;
    });
    
    // 時間割選択フォームの変更時は警告を無効化
    $('.timetable-selector select').on('change', function() {
        formChanged = false;
    });
    
    // タブ切り替え時も警告を無効化（タブ内での変更は保持）
    $('.timetable-tabs .nav-tab').click(function() {
        // タブ切り替え時は警告を一時的に無効化
        var originalFormChanged = formChanged;
        setTimeout(function() {
            formChanged = originalFormChanged;
        }, 100);
    });
    
    // 直感的な時間割編集機能
    if ($('#timetable-grid').length || $('.timetable-grid').length) {
        var selectedTimeSlot = '';
        
        // 時間帯追加
        $('#add-time-slot').click(function() {
            var timeSlot = $('#time-slot-selector').val();
            var timeSlotText = $('#time-slot-selector option:selected').text();
            var startTime = $('#time-slot-selector option:selected').data('start');
            var endTime = $('#time-slot-selector option:selected').data('end');
            
            // 時間から秒数を除去（HH:MM:SS → HH:MM）
            if (startTime && startTime.includes(':')) {
                var startParts = startTime.split(':');
                if (startParts.length >= 2) {
                    startTime = startParts[0] + ':' + startParts[1];
                }
            }
            if (endTime && endTime.includes(':')) {
                var endParts = endTime.split(':');
                if (endParts.length >= 2) {
                    endTime = endParts[0] + ':' + endParts[1];
                }
            }
            
            if (!timeSlot) {
                return;
            }
            
            // 既に存在するかチェック
            if ($('.time-row[data-time="' + timeSlot + '"]').length > 0) {
                return;
            }
            
            var newRow = createTimeRow(timeSlot, timeSlotText, startTime, endTime);
            $('#timetable-tbody').append(newRow);
            formChanged = true;
        });
        
        // 時間帯削除
        $(document).on('click', '.remove-time-row', function() {
            if (confirm('この時間帯を削除しますか？')) {
                $(this).closest('.time-row').remove();
                formChanged = true;
            }
        });
        

        
        // 時間割クリア
        $('#clear-timetable').click(function() {
            if (confirm('時間割の内容を全てクリアしますか？')) {
                $('.content-select').val('').trigger('change');
                $('input[name*="observable"]').prop('checked', false).trigger('change');
                $('.cell-comment').val('');
                formChanged = true;
            }
        });
        
        // 時間帯行の作成
        function createTimeRow(timeSlot, timeSlotText, startTime, endTime) {
            // テキストの取得
            var deleteText = (typeof timetableTexts !== 'undefined' && tcTexts.delete) ? tcTexts.delete : '削除';
            var selectPleaseText = (typeof timetableTexts !== 'undefined' && tcTexts.select_please) ? tcTexts.select_please : '選択してください';
            var observableText = (typeof timetableTexts !== 'undefined' && tcTexts.observable) ? tcTexts.observable : '見学可';
            var commentText = (typeof timetableTexts !== 'undefined' && tcTexts.comment) ? tcTexts.comment : 'コメント';

            // 時間から秒数を除去（HH:MM:SS → HH:MM）
            if (startTime && startTime.includes(':')) {
                var startParts = startTime.split(':');
                if (startParts.length >= 2) {
                    startTime = startParts[0] + ':' + startParts[1];
                }
            }
            if (endTime && endTime.includes(':')) {
                var endParts = endTime.split(':');
                if (endParts.length >= 2) {
                    endTime = endParts[0] + ':' + endParts[1];
                }
            }
            
            var row = '<tr class="time-row" data-time="' + timeSlot + '">';
            row += '<td class="time-header">';
            row += '<div class="time-info">';
            row += '<strong>' + timeSlot + '</strong><br>';
            row += '<small>' + startTime + ' - ' + endTime + '</small>';
            row += '</div>';
            row += '<div class="time-actions">';
            row += '<button type="button" class="remove-time-row button button-small button-link-delete">' + deleteText + '</button>';
            row += '</div>';
            row += '</td>';
            
            // 設定された曜日のみを表示
            var enabledDays = [];
            $('#timetable-grid thead th').each(function(index) {
                if (index > 0) { // 時間列以外
                    var dayNum = $(this).data('day');
                    if (dayNum !== undefined) {
                        enabledDays.push(dayNum);
                    }
                }
            });
            
            // デフォルトは平日（月〜金）を有効にする
            if (enabledDays.length === 0) {
                enabledDays = [1, 2, 3, 4, 5];
            }
            
            for (var i = 0; i < enabledDays.length; i++) {
                var day = enabledDays[i];
                row += '<td class="timetable-cell" data-day="' + day + '" data-time="' + timeSlot + '">';
                row += '<div class="cell-content">';
                row += '<div class="cell-main">';
                row += '<select name="content[' + day + '][' + timeSlot + ']" class="content-select">';
                row += '<option value="">' + selectPleaseText + '</option>';
                // コンテンツオプションは既存のセルから取得
                $('.content-select option').each(function() {
                    var contentId = $(this).val();
                    var contentName = $(this).text();
                    if (contentId) {
                        row += '<option value="' + contentId + '">' + contentName + '</option>';
                    }
                });
                row += '</select>';
                row += '</div>';
                row += '<div class="cell-options">';
                row += '<label class="observable-checkbox">';
                row += '<input type="checkbox" name="observable[' + day + '][' + timeSlot + ']" value="1">';
                row += observableText;
                row += '</label>';
                row += '<textarea name="comment[' + day + '][' + timeSlot + ']" placeholder="' + commentText + '" class="cell-comment"></textarea>';
                row += '</div>';
                row += '</div>';
                row += '</td>';
            }
            
            row += '</tr>';
            return row;
        }
        
        // 曜日名から曜日番号を取得する関数
        function getDayNumber(dayName) {
            var dayMap = {
                '日': 0,
                '月': 1,
                '火': 2,
                '水': 3,
                '木': 4,
                '金': 5,
                '土': 6,
                'Sun': 0,
                'Mon': 1,
                'Tue': 2,
                'Wed': 3,
                'Thu': 4,
                'Fri': 5,
                'Sat': 6
            };
            return dayMap[dayName] !== undefined ? dayMap[dayName] : -1;
        }
    }
}); 