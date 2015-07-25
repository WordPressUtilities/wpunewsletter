<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit();

/* ----------------------------------------------------------
  Remove options
---------------------------------------------------------- */

$options = array(
    'wpunewsletter_useremailfromname',
    'wpunewsletter_useremailfromaddress',
    'wpunewsletter_send_confirmation_email',
    'wpunewsletter_use_jquery_ajax',
    'wpunewsletter_mailchimp_active',
    'wpunewsletter_mailchimp_double_optin',
    'wpunewsletter_mailchimp_apikey',
    'wpunewsletter_mailchimp_listid',
);
foreach ($options as $option_name) {
    delete_option($option_name);
}

/* ----------------------------------------------------------
  Remove table
---------------------------------------------------------- */

global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpunewsletter_subscribers");

