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
