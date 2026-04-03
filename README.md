WPU Newsletter
=============

[![PHP workflow](https://github.com/WordPressUtilities/wpunewsletter/actions/workflows/php.yml/badge.svg 'PHP workflow')](https://github.com/WordPressUtilities/wpunewsletter/actions) [![JS workflow](https://github.com/WordPressUtilities/wpunewsletter/actions/workflows/js.yml/badge.svg 'JS workflow')](https://github.com/WordPressUtilities/wpunewsletter/actions)

Allow subscriptions to a newsletter.


## How to use

```php
the_widget('wpunewsletter_form', array(
    'content_label' => __('Email', 'wpunewsletter'),
    'content_placeholder' => __('Your email address', 'wpunewsletter'),
    'content_button' => __('Register', 'wpunewsletter')
));
```

### Available options

```php
the_widget('wpunewsletter_form', array(
    /* Content */
    'content_label' => __('Email', 'wpunewsletter'),
    'content_placeholder' => __('Your email address', 'wpunewsletter'),
    'content_button' => __('Register', 'wpunewsletter'),
    'text' => '',

    /* Layout */
    'form_has_wrapper' => true,
    'fields_has_wrapper' => true,
    'main_field_have_wrapper' => true,
    'main_field_position' => 'before',
    'messages_over_form' => true,

    /* CSS classes */
    'form_id' => 'newsletter-form',
    'classes_form' => 'newsletter-form',
    'classes_mainfield' => '',
    'classes_fieldwrapper' => 'field',
    'classes_label' => 'newsletter-label',
    'classes_button' => 'cssc-button cssc-button--default',

    /* Extra fields */
    'hidden_fields' => array(),
    'extra_fields_values' => array(),

    /* Third-party lists */
    'mailchimp_list_id' => '',
    'brevo_list_id' => '',

    /* JS */
    'js_callback_before_submit' => ''
));
```

## Extra fields

Add custom fields to the newsletter form via the `wpunewsletter_extra_fields` filter.

### Supported types

`text`, `email`, `url`, `checkbox`, `select`, `radio`.

### Example

```php
add_filter('wpunewsletter_extra_fields', function ($fields) {
    $fields['firstname'] = array(
        'name' => 'First name',
        'type' => 'text',
        'required' => true,
        'placeholder' => 'John',
        'brevo_attribute' => 'FIRSTNAME'
    );
    $fields['civility'] = array(
        'name' => 'Civility',
        'type' => 'select',
        'required' => true,
        'placeholder' => '—',
        'options' => array(
            'MR' => 'Mr',
            'MRS' => 'Mrs'
        ),
        'brevo_attribute' => 'CIVILITY'
    );
    $fields['interest'] = array(
        'name' => 'Interest',
        'type' => 'radio',
        'options' => array(
            'sport' => 'Sport',
            'culture' => 'Culture',
            'tech' => 'Tech'
        )
    );
    return $fields;
});
```
