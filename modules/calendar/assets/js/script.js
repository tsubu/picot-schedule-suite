jQuery(function ($) {
    var $popup = $('<div class="bd-popup" role="tooltip" style="display:none;"></div>');
    $('body').append($popup);

    // ポップアップ外をクリックしたら閉じる
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.bd-date-cell, .bd-popup').length) {
            $popup.fadeOut(200);
        }
    });

    // 日付クリック: 祝日名・営業時間があればポップアップ表示
    $(document).on('click', '.bd-date-cell', function () {
        var $cell = $(this);
        var hours = String($cell.attr('data-hours') || '');
        var holiday = String($cell.attr('data-holiday') || '');

        if (!hours && !holiday) {
            $popup.fadeOut(200);
            return;
        }

        // 値は投稿者が入力したものなので、HTML として解釈させない（.text() のみ使用）
        var $content = $('<div class="bd-popup-content"></div>');
        if (holiday) {
            $content.append($('<span class="bd-popup-holiday"></span>').text(holiday));
        }
        if (hours) {
            $content.append($('<span class="bd-popup-hours"></span>').text(hours));
        }
        $popup.empty().append($content).show();

        // セルの上に表示し、画面の左右からはみ出さないよう調整
        var offset = $cell.offset();
        var popupWidth = $popup.outerWidth();
        var popupHeight = $popup.outerHeight();
        var viewportLeft = $(window).scrollLeft();
        var viewportRight = viewportLeft + $(window).width();

        var left = offset.left + ($cell.outerWidth() / 2) - (popupWidth / 2);
        left = Math.max(viewportLeft + 8, Math.min(left, viewportRight - popupWidth - 8));

        $popup.hide().css({
            top: offset.top - popupHeight - 10,
            left: left
        }).fadeIn(200);
    });
});
