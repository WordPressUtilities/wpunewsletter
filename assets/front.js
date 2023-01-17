jQuery(document).ready(function($) {

    /* Form submit */
    $('form.wpunewsletter-form').each(wpunewsletter_set_form_events);

    /* Email validation */
    jQuery('[name="wpunewsletter_email"]').each(function() {
        var $this = jQuery(this),
            $hid = $this.parent().find('[name="wpunewsletter_email_hid"]');

        $this.on('change keydown keyup focus blur', function(e) {
            $hid.val(wpunewsletter_rot13($this.val()));
        });
    });

});

/* <3 https://codereview.stackexchange.com/a/192241 */
function wpunewsletter_rot13(str) {
    return (str + '').replace(/[a-zA-Z]/gi, function(s) {
        return String.fromCharCode(s.charCodeAt(0) + (s.toLowerCase() < 'n' ? 13 : -13));
    });
}

function wpunewsletter_set_form_events() {
    $form = jQuery(this);
    var $messages = $form.find('.messages'),
        $button = $form.find('button[type="submit"]');
    if (!$messages.length) {
        $messages = jQuery('<div class="messages"></div>');
        $form.append($messages);
    }
    $messages.empty();
    var jsSettings = {};
    if (window.wpunewsletter_forms && window.wpunewsletter_forms[$form.attr('id')]) {
        jsSettings = window.wpunewsletter_forms[$form.attr('id')];
    }
    $form.on('submit', function(e) {
        e.preventDefault();
        if (jsSettings && jsSettings.js_callback_before_submit) {
            if (!window[jsSettings.js_callback_before_submit]($form, $messages)) {
                return false;
            }
        }
        $button.attr('disabled', 'disabled');
        $form.attr('data-ajaxloading', '1');
        jQuery.ajax({
            type: "POST",
            url: window.location.href,
            data: $form.serialize() + '&ajax=1',
            dataType: "json",
            complete: function(data) {
                $messages.empty();
                $form.attr('data-ajaxloading', '0');
                $button.removeAttr('disabled');
                $messages.append(jQuery(data.responseText));
                if (data.responseText.indexOf('\"error\"') > -1) {
                    $form.attr('data-status', 'error');
                }
                if (data.responseText.indexOf('\"success\"') > -1) {
                    $form.attr('data-status', 'success');
                }
                $form.trigger('wpunewsletter_form_ajaxcomplete');
            }
        });
    });
}
