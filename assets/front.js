jQuery(document).ready(function($) {
    var $form = $('#wpunewsletter-form'),
        $messages = $form.find('.messages');
    if (!$form.length) {
        return;
    }
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
            }
        });
    });
});