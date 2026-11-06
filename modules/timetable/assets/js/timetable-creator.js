jQuery(document).ready(function($) {
    // 時間割表示の初期化
    $('.timetable-display').each(function() {
        var $timetable = $(this);
        
        // 見学可能セルのスタイル適用
        $timetable.find('.observable').addClass('observable-cell');
        
        // コメント表示の制御
        $timetable.find('.cell-comment').each(function() {
            var $comment = $(this);
            if ($comment.text().trim() !== '') {
                $comment.show();
            } else {
                $comment.hide();
            }
        });
    });
    
    // レスポンシブ対応
    function adjustTimetableLayout() {
        $('.timetable-grid').each(function() {
            var $grid = $(this);
            var windowWidth = $(window).width();
            
            if (windowWidth < 768) {
                $grid.addClass('mobile-layout');
            } else {
                $grid.removeClass('mobile-layout');
            }
        });
    }
    
    // 初期化とリサイズ時の調整
    adjustTimetableLayout();
    $(window).resize(adjustTimetableLayout);
}); 