jQuery(document).ready(function($) {
    var $form = $('form.wpunewsletter-form');

    $form.each(function() {
        $form = jQuery(this);
        var $messages = $form.find('.messages');
        $messages.empty();
        $form.on('submit', function(e) {
            $form.attr('data-ajaxloading', '1');
            if (!$messages.length) {
                return;
            }
            e.preventDefault();
            $.ajax({
                type: "POST",
                url: window.location.href,
                data: $form.serialize() + '&ajax=1',
                dataType: "json",
                complete: function(data) {
                    $messages.empty();
                    $form.attr('data-ajaxloading', '0');
                    $messages.append(jQuery(data.responseText));
                    if(data.responseText.indexOf('\"error\"') > -1){
                        $form.attr('data-status','error');
                    }
                    if(data.responseText.indexOf('\"success\"') > -1){
                        $form.attr('data-status','success');
                    }
                }
            });
        });
    });
});
