<?php

/* ----------------------------------------------------------
  Widget
---------------------------------------------------------- */

// Create widget Form
add_action('widgets_init', 'wpunewsletter_form_register_widgets');
function wpunewsletter_form_register_widgets() {
    register_widget('wpunewsletter_form');
}
class wpunewsletter_form extends WP_Widget {
    public function __construct() {
        parent::__construct(false, '[WPU] Newsletter Form', array(
            'description' => 'Newsletter Form'
        ));
    }
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : esc_html__('New title', 'text_domain');
        ?>
        <p>
        <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_attr_e('Title:', 'text_domain');?></label>
        <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
}
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';

        return $instance;
    }
    public function widget($args, $instance) {
        global $wpunewsletter_messages, $WPUNewsletter;

        $curr_instance = array(
            'js_callback_before_submit' => '',
            'content_label' => __('Email', 'wpunewsletter'),
            'content_placeholder' => __('Your email address', 'wpunewsletter'),
            'content_button' => __('Register', 'wpunewsletter'),
            'mailchimp_list_id' => '',
            'form_has_wrapper' => true,
            'fields_has_wrapper' => true,
            'messages_over_form' => true,
            'main_field_have_wrapper' => true,
            'hidden_fields' => array(),
            'extra_fields_values' => array(),
            'main_field_position' => 'before',
            'form_id' => 'newsletter-form',
            'gprdtext' => get_option('wpunewsletter_gprdtext'),
            'gprdcheckbox_text' => get_option('wpunewsletter_gprdcheckbox_text'),
            'gprdcheckbox_box' => get_option('wpunewsletter_gprdcheckbox_box'),
            'classes_mainfield' => '',
            'classes_fieldwrapper' => 'field',
            'classes_form' => 'newsletter-form',
            'classes_label' => 'newsletter-label',
            'classes_button' => 'cssc-button cssc-button--default'
        );

        $fields_prefix = 'f_' . uniqid() . '_';

        $curr_instance = array_merge($curr_instance, $instance);

        /* - SETTINGS -  */

        $widg_js_callback_before_submit = apply_filters('wpunewsletter_form_widget_js_callback_before_submit', $curr_instance['js_callback_before_submit'], $instance);

        /* Elements */
        $widg_content_label = apply_filters('wpunewsletter_form_widget_content_label', $curr_instance['content_label'], $instance);
        $widg_content_placeholder = apply_filters('wpunewsletter_form_widget_content_placeholder', $curr_instance['content_placeholder'], $instance);
        $widg_content_button = apply_filters('wpunewsletter_form_widget_content_button', $curr_instance['content_button'], $instance);
        if ($widg_content_button == $curr_instance['content_button']) {
            $widg_content_button = '<span>' . $widg_content_button . '</span>';
        }

        /* Display */
        $widg_form_has_wrapper = apply_filters('wpunewsletter_form_widget_form_has_wrapper', $curr_instance['form_has_wrapper'], $instance);
        $widg_fields_has_wrapper = apply_filters('wpunewsletter_form_widget_fields_has_wrapper', $curr_instance['fields_has_wrapper'], $instance);
        $widg_main_field_have_wrapper = apply_filters('wpunewsletter_form_widget_main_field_have_wrapper', $curr_instance['main_field_have_wrapper'], $instance);
        $widg_messages_over_form = apply_filters('wpunewsletter_form_widget_messages_over_form', $curr_instance['messages_over_form'], $instance);
        $widg_hidden_fields = apply_filters('wpunewsletter_form_widget_hidden_fields', $curr_instance['hidden_fields'], $instance);
        $widg_extra_fields_values = apply_filters('wpunewsletter_form_widget_extra_fields_values', $curr_instance['extra_fields_values'], $instance);
        $widg_main_field_position = apply_filters('wpunewsletter_form_widget_main_field_position', $curr_instance['main_field_position'], $instance);

        /* GPRD */
        $widg_gprdtext = trim(apply_filters('wpunewsletter_form_widget_gprdtext', $curr_instance['gprdtext'], $instance));
        $widg_gprdcheckbox_box = trim(apply_filters('wpunewsletter_form_widget_gprdcheckbox_box', $curr_instance['gprdcheckbox_box'], $instance));
        $widg_gprdcheckbox_text = trim(apply_filters('wpunewsletter_form_widget_gprdcheckbox_text', $curr_instance['gprdcheckbox_text'], $instance));

        /* Classes */
        $widg_form_id = apply_filters('wpunewsletter_form_widget_form_id', $curr_instance['form_id'], $instance);
        $widg_classes_mainfield = apply_filters('wpunewsletter_form_widget_classes_mainfield', $curr_instance['classes_mainfield'], $instance);
        $widg_classes_fieldwrapper = apply_filters('wpunewsletter_form_widget_classes_fieldwrapper', $curr_instance['classes_fieldwrapper'], $instance);
        $widg_classes_button = apply_filters('wpunewsletter_form_widget_classes_button', $curr_instance['classes_button'], $instance);
        $widg_classes_form = apply_filters('wpunewsletter_form_widget_classes_form', $curr_instance['classes_form'], $instance);
        $widg_classes_label = apply_filters('wpunewsletter_form_widget_classes_label', $curr_instance['classes_label'], $instance);

        $js_form_settings = array(
            'id' => $widg_form_id,
            'js_callback_before_submit' => $widg_js_callback_before_submit
        );

        /* - FORM -  */

        $default_widget_content = '<form class="wpunewsletter-form ' . $widg_classes_form . '" id="' . $widg_form_id . '" action="" method="post">';
        $default_widget_content .= '<script>if(!window.wpunewsletter_forms){window.wpunewsletter_forms=[];}</script>';
        $default_widget_content .= '<script>window.wpunewsletter_forms[\'' . $widg_form_id . '\']=' . json_encode($js_form_settings) . ';</script>';
        if ($widg_messages_over_form) {
            $default_widget_content .= '<div class="messages" aria-live="polite"></div>';
        }
        $default_widget_content .= $widg_form_has_wrapper ? '<div class="wpunewsletter-form-wrapper">' : '';

        $main_newsletter_field = '';

        $main_newsletter_field .= apply_filters('wpunewsletter__before_main_field', '', $instance);
        $main_newsletter_field .= ($widg_main_field_have_wrapper && $widg_fields_has_wrapper) ? '<p class="' . $widg_classes_fieldwrapper . '">' : '';
        $main_newsletter_field .= '<label class="' . $widg_classes_label . '" for="' . $fields_prefix . 'wpunewsletter_email">' . $widg_content_label . '</label>';
        $main_newsletter_field .= '<input class="' . $widg_classes_mainfield . '" type="email" name="wpunewsletter_email" placeholder="' . $widg_content_placeholder . '" id="' . $fields_prefix . 'wpunewsletter_email" value="" required />';
        $main_newsletter_field .= '<input type="hidden" name="wpunewsletter_email_hid" id="' . $fields_prefix . 'wpunewsletter_email_hid" />';
        if (isset($instance['mailchimp_list_id']) && $instance['mailchimp_list_id']) {
            $main_newsletter_field .= '<input type="hidden" name="wpunewsletter_mclist_id" value="' . md5('wpu_' . $instance['mailchimp_list_id']) . '" />';
        }
        $main_newsletter_field .= ($widg_main_field_have_wrapper && $widg_fields_has_wrapper) ? '</p>' : '';
        $main_newsletter_field .= apply_filters('wpunewsletter__after_main_field', '', $instance);

        if ($widg_gprdcheckbox_box && $widg_gprdcheckbox_text) {
            $main_newsletter_field .= '<div class="wpunewsletter-gprdcheckbox__wrapper">';
            $main_newsletter_field .= '<input required type="checkbox" id="' . $fields_prefix . 'wpunewsletter_gprdcheckbox" name="wpunewsletter_gprdcheckbox" value="1" />';
            $main_newsletter_field .= '<label for="' . $fields_prefix . 'wpunewsletter_gprdcheckbox">' . $widg_gprdcheckbox_text . '</label>';
            $main_newsletter_field .= '</div>';
        }

        if ($main_newsletter_field == 'before') {
            $default_widget_content .= $main_newsletter_field;
        }

        foreach ($WPUNewsletter->extra_fields as $id => $field) {
            $_f_id = 'wpunewsletter_extra__' . $id;
            $_idname = ' name="' . $_f_id . '" id="' . $fields_prefix . $_f_id . '" ';
            if ($field['required']) {
                $_idname .= ' required="required" ';
            }
            $field_value = $field['default_value'];
            if (is_array($widg_extra_fields_values) && isset($widg_extra_fields_values[$id])) {
                $field_value = $widg_extra_fields_values[$id];
            }
            if (in_array($id, $widg_hidden_fields) || array_key_exists($id, $widg_hidden_fields)) {
                $default_widget_content .= '<input type="hidden" ' . $_idname . ' value="' . esc_attr($field_value) . '" />';
                continue;
            }
            $field_name = $field['name'];
            if ($field['type'] == 'checkbox' && $field['label_check']) {
                $field_name = $field['label_check'];
            }

            $default_widget_content .= $widg_fields_has_wrapper ? '<p class="' . $field['wrapper_classname'] . ' ' . $widg_classes_fieldwrapper . ' field--' . $_f_id . '">' : '';
            $_label_before = '<label class="' . $field['label_classname'] . ' ' . $widg_classes_label . ' label--' . $_f_id . '" for="' . $fields_prefix . $_f_id . '">';
            $_label_after = '</label>';

            switch ($field['type']) {
            case 'checkbox':
                $default_widget_content .= $_label_before . '<input class="' . $field['field_classname'] . ' " type="checkbox" ' . $_idname . ' ' . ($field_value == '1' ? 'checked="checked"' : '') . ' value="1" /> ' . '<span>' . $field_name . '</span>' . $_label_after;
                break;
            default:

                // text / email / url
                $default_widget_content .= $_label_before . $field_name . $_label_after . ' <input ' . $field['field_classname'] . ' type="' . $field['type'] . '" ' . $_idname . ' value="" />';
            }
            $default_widget_content .= $widg_fields_has_wrapper ? '</p>' : '';
        }

        if ($main_newsletter_field != 'before') {
            $default_widget_content .= $main_newsletter_field;
        }

        $default_widget_content .= apply_filters('wpunewsletter__before_submit_button', '', $instance);
        $default_widget_content .= '<button type="submit" class="' . $widg_classes_button . '">' . $widg_content_button . '</button>';
        $default_widget_content .= apply_filters('wpunewsletter__after_submit_button', '', $instance);

        $default_widget_content .= $widg_form_has_wrapper ? '</div>' : '';
        if (!$widg_messages_over_form) {
            $default_widget_content .= '<div class="messages" aria-live="polite"></div>';
        }
        $default_widget_content .= '</form>';
        if (!empty($widg_gprdtext)) {
            $default_widget_content .= '<div class="wpunewsletter-gdprtext">' . $widg_gprdtext . '</div>';
        }

        echo $args['before_widget'];
        if ($title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title'])) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        if (!empty($wpunewsletter_messages)) {
            echo '<p>' . implode('<br />', $wpunewsletter_messages) . '</p>';
        }
        echo apply_filters('wpunewsletter_form_widget_content', $default_widget_content);
        echo $args['after_widget'];
    }
}
