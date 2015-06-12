<?php

/*
Plugin Name: WP Utilities Newsletter
Description: Allow subscriptions to a newsletter.
Version: 1.6.1
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

/*
 * To test :
 * - Init
 * - Register user
 * - Delete user
 * - CSV Export
 * - Widget Front
 * - Widget Admin
 *
 * To do :
 * - Use messages without global
 * - Use methods to get/add/delete mails
 * - Define only once URL routes : confirm / admin paged / admin page
 * - Add tests
 * - Allow additional fields (stock in text)
 * - Import mails
 * - Hook uninstall
 *
*/

$wpunewsletteradmin_messages = array();
$wpunewsletter_messages = array();

class WPUNewsletter {
    public $plugin_version = '1.6';
    public $table_name;
    function __construct() {
        global $wpdb;

        /* Vars */
        $this->plugin_id = 'wpunewsletter';
        $this->table_name = $wpdb->prefix . $this->plugin_id . "_subscribers";
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->plugin_dir = dirname(plugin_basename(__FILE__)) . '/';
        $this->perpage = 50;

        /* Hooks */
        add_action('init', array(&$this,
            'load_translation'
        ));
        add_action('admin_menu', array(&$this,
            'menu_page'
        ));
        add_action('wp_dashboard_setup', array(&$this,
            'add_dashboard_widget'
        ));
        add_filter("plugin_action_links_" . plugin_basename(__FILE__) , array(&$this,
            'settings_link'
        ));
        add_action('admin_init', array(&$this,
            'delete_postAction'
        ));
        add_action('admin_init', array(&$this,
            'export_postAction'
        ));
        add_action('admin_init', array(&$this,
            'settings_postAction'
        ));
        add_action('template_redirect', array(&$this,
            'postAction'
        ));
        add_action('template_redirect', array(&$this,
            'confirm_address'
        ));

        // Admin boxes
        add_filter('wpu_options_tabs', array(&$this,
            'add_tabs'
        ) , 99, 1);
        add_filter('wpu_options_boxes', array(&$this,
            'add_boxes'
        ) , 99, 1);
        add_filter('wpu_options_fields', array(&$this,
            'add_fields'
        ) , 99, 1);

        if (isset($_GET['page']) && $_GET['page'] == 'wpunewsletter') {
            add_action('admin_enqueue_scripts', array(&$this,
                'admin_enqueue_scripts'
            ));
        }
        add_action('wp_enqueue_scripts', array(&$this,
            'enqueue_scripts'
        ));
    }

    // Translation
    function load_translation() {
        load_plugin_textdomain('wpunewsletter', false, $this->plugin_dir . 'lang/');
    }

    // Admin JS
    function admin_enqueue_scripts() {
        wp_enqueue_script('wpunewsletter_admin_js', $this->plugin_url . 'assets/script.js');
    }
    function enqueue_scripts() {
        wp_enqueue_script('wpunewsletter_js', $this->plugin_url . 'assets/front.js', array(
            'jquery'
        ) , $this->plugin_version, 1);
    }

    /* ----------------------------------------------------------
      Widget dashboard
    ---------------------------------------------------------- */

    function add_dashboard_widget() {
        wp_add_dashboard_widget('wpunewsletter_dashboard_widget', 'Newsletter - ' . __('Latest subscribers', 'wpunewsletter') , array(&$this,
            'content_dashboard_widget'
        ));
    }

    function content_dashboard_widget() {
        global $wpdb, $wpunewsletteradmin_messages;

        $results = $wpdb->get_results("SELECT * FROM " . $this->table_name . " ORDER BY id DESC LIMIT 0, 10");
        if (!empty($results)) {
            foreach ($results as $result) {
                echo '<p>' . $result->id . ' - ' . $result->email . '</p>';
            }
        }
        else {
            echo '<p>' . __('No subscriber for now.', 'wpunewsletter') . '</p>';
        }
    }

    /* ----------------------------------------------------------
      Admin Options
    ---------------------------------------------------------- */

    function add_tabs($tabs) {
        $tabs['wpunewsletter'] = array(
            'name' => 'Options Newsletter'
        );
        return $tabs;
    }

    function add_boxes($boxes) {
        $boxes['wpunewsletter_confirm'] = array(
            'name' => 'Confirm email',
            'tab' => 'wpunewsletter'
        );
        return $boxes;
    }

    function add_fields($options) {

        $options['wpunewsletter_useremailfromname'] = array(
            'label' => __('From name', 'wpunewsletter') ,
            'type' => 'text',
            'box' => 'wpunewsletter_confirm'
        );
        $options['wpunewsletter_useremailfromaddress'] = array(
            'label' => __('From address', 'wpunewsletter') ,
            'type' => 'text',
            'box' => 'wpunewsletter_confirm'
        );

        return $options;
    }

    /* ----------------------------------------------------------
      Admin page
    ---------------------------------------------------------- */

    // Menu item
    function menu_page() {
        add_menu_page('Newsletter', 'Newsletter', 'manage_options', 'wpunewsletter', array(&$this,
            'page_content'
        ));
        add_submenu_page('wpunewsletter', 'Newsletter - Export', 'Export', 'manage_options', 'wpunewsletter-export', array(&$this,
            'page_content_export'
        ));
        add_submenu_page('wpunewsletter', 'Newsletter - Settings', 'Settings', 'manage_options', 'wpunewsletter-settings', array(&$this,
            'page_content_settings'
        ));
    }

    // Admin page content
    function page_content() {
        global $wpdb, $wpunewsletteradmin_messages;

        // Paginate
        $current_page = ((isset($_GET['paged']) && is_numeric($_GET['paged'])) ? $_GET['paged'] : 1);
        $nb_start = ($current_page * $this->perpage) - $this->perpage;
        $nb_results_req = $wpdb->get_row("SELECT COUNT(id) as count_id FROM " . $this->table_name);
        $nb_results_total = (int)$nb_results_req->count_id;
        $max_page = ceil($nb_results_total / $this->perpage);

        // Get page results
        $results = $wpdb->get_results("SELECT * FROM " . $this->table_name . " ORDER BY id DESC LIMIT " . $nb_start . ", " . $this->perpage);
        $nb_results = count($results);

        // Display wrapper
        echo '<div class="wrap"><h2 class="title">Newsletter</h2>';

        if (!empty($wpunewsletteradmin_messages)) {
            echo '<p>' . implode('<br />', $wpunewsletteradmin_messages) . '</p>';
        }

        echo '<h3>' . sprintf(__('Subscribers list : %s', 'wpunewsletter') , $nb_results_total) . '</h3>';

        // If empty
        if ($nb_results < 1) {

            // - Display blank slate message
            echo '<p>' . __('No subscriber for now.', 'wpunewsletter') . '</p>';
        }
        else {
            echo '<form action="" method="post">';

            // - Display results
            echo '<table class="widefat">';
            $cols = '<tr>';
            $cols.= '<th><input type="checkbox" class="wpunewsletter_element_check" name="wpunewsletter_element_check" /></th>';
            $cols.= '<th>' . __('ID', 'wpunewsletter') . '</th>';
            $cols.= '<th>' . __('Email', 'wpunewsletter') . '</th>';
            $cols.= '<th>' . __('Locale', 'wpunewsletter') . '</th>';
            $cols.= '<th>' . __('Date', 'wpunewsletter') . '</th>';
            $cols.= '</tr>';

            echo '<thead>' . $cols . '</thead>';
            echo '<tfoot>' . $cols . '</tfoot>';
            foreach ($results as $result) {
                echo '<tbody><tr>
            <td style="width: 15px; text-align: right;">
            <input type="checkbox" class="wpunewsletter_element" name="wpunewsletter_element[]" value="' . $result->id . '" />
            </td>
            <td>' . $result->id . '</td>
            <td>' . $result->email . '</td>
            <td>' . $result->locale . '</td>
            <td>' . $result->date_register . '</td>
            </tr></tbody>';
            }
            echo '</table>';
            echo wp_nonce_field('wpunewsletter_delete', 'wpunewsletter_delete_nonce');
            echo submit_button(__('Delete selected lines', 'wpunewsletter'));
            echo '</form>';
        }
        echo '</div>';

        if ($max_page > 1) {
            $big = 999999999;
            $replace = '%#%';

            // need an unlikely integer
            echo '<p>' . paginate_links(array(
                'base' => str_replace($big, $replace, esc_url(get_pagenum_link($big))) ,
                'format' => '/admin.php?page=wpunewsletter&paged=' . $replace,
                'current' => max(1, $current_page) ,
                'total' => $max_page
            )) . '</p>';
        }
    }

    // Delete element
    function delete_postAction() {
        global $wpdb, $wpunewsletteradmin_messages;

        $nb_delete = 0;
        if (isset($_POST['wpunewsletter_delete_nonce']) && wp_verify_nonce($_POST['wpunewsletter_delete_nonce'], 'wpunewsletter_delete') && isset($_POST['wpunewsletter_element']) && is_array($_POST['wpunewsletter_element']) && !empty($_POST['wpunewsletter_element'])) {
            foreach ($_POST['wpunewsletter_element'] as $id) {
                $wpdb->delete($this->table_name, array(
                    'id' => $id
                ));
                $nb_delete++;
            }
        }

        if ($nb_delete > 0) {
            $wpunewsletteradmin_messages[] = 'Mail suppressions : ' . $nb_delete;
        }
    }

    /* ----------------------------------------------------------
      Admin Page - Export
    ---------------------------------------------------------- */

    function page_content_export() {
        echo '<div class="wrap"><h2 class="title">Newsletter - Export</h2>
        <form action="" method="post"><p>';
        echo '<label for="wpunewsletter_export_type">' . __('Addresses to export:', 'wpunewsletter') . '</label> ';
        echo '<select name="wpunewsletter_export_type" id="wpunewsletter_export_type">
        <option value="validated">' . __('Only validated', 'wpunewsletter') . '</option>
        <option value="all">' . __('All', 'wpunewsletter') . '</option>
    </select></p>';
        echo wp_nonce_field('wpunewsletter_export', 'wpunewsletter_export_nonce');
        echo submit_button(__('Export addresses', 'wpunewsletter'));
        echo '</form>
        </div>';
    }

    // Generate CSV for export
    function export_postAction() {
        global $wpdb;

        // Check if export is correctly asked
        if (isset($_POST['wpunewsletter_export_nonce']) && wp_verify_nonce($_POST['wpunewsletter_export_nonce'], 'wpunewsletter_export')) {

            $request_more = '';
            if (isset($_POST['wpunewsletter_export_type']) && $_POST['wpunewsletter_export_type'] == 'validated') {
                $request_more = ' WHERE is_valid = 1';
            }

            $results = $wpdb->get_results("SELECT * FROM " . $this->table_name . $request_more, ARRAY_N);
            $file_name = sanitize_title(get_bloginfo('name')) . '-' . date('Y-m-d') . '-wpunewsletter' . '.csv';

            $this->export_csv($results, $file_name);
        }
    }

    // Add settings link on plugin page
    function settings_link($links) {
        $settings_link = '<a href="admin.php?page=wpunewsletter">' . __('Subscribers', 'wpunewsletter') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /* ----------------------------------------------------------
      Actions
    ---------------------------------------------------------- */

    function mail_is_subscribed($email) {
        global $wpdb;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $testbase = $wpdb->get_row($wpdb->prepare('SELECT email FROM ' . $this->table_name . ' WHERE email = %s', $email));
        return isset($testbase->email);
    }

    function register_mail($email, $send_confirmation_mail = false, $check_subscription = false) {
        global $wpunewsletter_messages, $wpdb;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // If mail is subscribed
        if ($check_subscription && $this->mail_is_subscribed($email)) {
            return;
        }

        $is_valid = $send_confirmation_mail ? 0 : 1;

        $secretkey = md5(microtime() . $email);
        $insert = $wpdb->insert($this->table_name, array(
            'email' => $email,
            'locale' => get_locale() ,
            'secretkey' => $secretkey,
            'is_valid' => $is_valid
        ));

        if ($send_confirmation_mail && $insert !== false) {
            $this->send_confirmation_email($email, $secretkey);
        }

        return $insert;
    }

    // Widget POST Action
    function postAction() {
        global $wpunewsletter_messages, $wpdb;

        // If there is a valid email address
        if (isset($_POST['wpunewsletter_email'])) {
            if ($this->mail_is_subscribed($_POST['wpunewsletter_email'])) {
                $wpunewsletter_messages[] = apply_filters('wpunewsletter_message_register_already', __('This mail is already registered', 'wpunewsletter'));
            }
            else {
                $subscription = $this->register_mail($_POST['wpunewsletter_email'], true, false);
                if ($subscription === false) {
                    $wpunewsletter_messages[] = apply_filters('wpunewsletter_message_register_nok', __("This mail can't be registered", 'wpunewsletter'));
                }
                else {
                    $wpunewsletter_messages[] = apply_filters('wpunewsletter_message_register_ok', __('This mail is now registered', 'wpunewsletter'));
                }
            }
        }

        if (isset($_POST['ajax'])) {
            if (!empty($wpunewsletter_messages)) {
                echo '<p>' . implode('<br />', $wpunewsletter_messages) . '</p>';
            }
            die;
        }
    }

    function confirm_address() {
        if (!isset($_GET['wpunewsletter_key'], $_GET['wpunewsletter_email'])) {
            return;
        }
        global $wpdb;

        $message = apply_filters('wpunewsletter_failuremsg', __("Your subscription couldn't be confirmed", 'wpunewsletter'));
        $address_exists = $wpdb->get_row($wpdb->prepare("SELECT id FROM " . $this->table_name . " WHERE email = %s AND secretkey = %s", $_GET['wpunewsletter_email'], $_GET['wpunewsletter_key']));
        if (isset($address_exists->id)) {

            // Update
            $update = $wpdb->update($this->table_name, array(
                'is_valid' => '1'
            ) , array(
                'id' => $address_exists->id
            ) , array(
                '%d'
            ));
            if ($update !== FALSE) {
                $message = apply_filters('wpunewsletter_successmsg', __("Your subscription has been successfully confirmed", 'wpunewsletter'));
            }
        }

        $this->display_message_in_page($message);
    }

    /* ----------------------------------------------------------
      Settings
    ---------------------------------------------------------- */

    function page_content_settings() {
        echo '<div class="wrap"><h2 class="title">Newsletter - Settings</h2>';
        echo '<form action="" method="post">';
        echo '<p>';
        echo '<label>';
        echo '<input type="checkbox" name="wpunewsletter_use_jquery_ajax" ' . checked(get_option('wpunewsletter_use_jquery_ajax') , 1, false) . ' value="1" />' . __('Use jQuery AJAX', 'wpunewsletter');
        echo '</label> ';
        echo '</p>';
        echo wp_nonce_field('wpunewsletter_settings', 'wpunewsletter_settings_nonce');
        echo submit_button(__('Update options', 'wpunewsletter'));
        echo '</form></div>';
    }

    function settings_postAction() {
        $nonce = 'wpunewsletter_settings_nonce';
        $action = 'wpunewsletter_settings';
        if (empty($_POST)) {
            return;
        }
        if (!isset($_POST[$nonce]) || !wp_verify_nonce($_POST[$nonce], 'wpunewsletter_settings')) {
            return;
        }

        update_option('wpunewsletter_use_jquery_ajax', (isset($_POST['wpunewsletter_use_jquery_ajax']) ? 1 : ''));
    }

    /* ----------------------------------------------------------
      Model
    ---------------------------------------------------------- */

    function wpunewsletter_activate() {

        // Create or update database
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE " . $this->table_name . " (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `email` varchar(100) DEFAULT NULL,
            `locale` varchar(20) DEFAULT NULL,
            `secretkey` varchar(100) DEFAULT NULL,
            `date_register` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `is_valid` tinyint(1) unsigned DEFAULT '0' NOT NULL,
            PRIMARY KEY (`id`)
        );");
    }

    /* ----------------------------------------------------------
      Utilities
    ---------------------------------------------------------- */

    function send_confirmation_email($email, $secretkey) {
        add_filter('wp_mail_content_type', array(&$this,
            'html_content'
        ));
        add_filter('wp_mail_from', array(&$this,
            'wp_mail_from'
        ));
        add_filter('wp_mail_from_name', array(&$this,
            'wp_mail_from_name'
        ));
        $confirm_url = site_url() . '?wpunewsletter_key=' . urlencode($secretkey) . '&amp;wpunewsletter_email=' . urlencode($email);
        $email_title = get_bloginfo('name') . ' - ' . __('Confirm your subscription to our newsletter', 'wpunewsletter');

        $email_content = '<p>' . __('Hi !', 'wpunewsletter') . '</p>';
        $email_content.= '<p>' . __('Please click on the link below to confirm your subscription to our newsletter:', 'wpunewsletter');
        $email_content.= '<br /><a href="' . $confirm_url . '">' . $confirm_url . '</a></p>' . '<p>' . __('Thank you !', 'wpunewsletter') . '</p>';

        $email_title = apply_filters('wpunewsletter_confirm_email_title', $email_title);
        $email_content = apply_filters('wpunewsletter_confirm_email_content', $email_content, $confirm_url);

        wp_mail($email, $email_title, $email_content);
        remove_filter('wp_mail_content_type', array(&$this,
            'html_content'
        ));
        remove_filter('wp_mail_from', array(&$this,
            'wp_mail_from'
        ));
        remove_filter('wp_mail_from_name', array(&$this,
            'wp_mail_from_name'
        ));
    }

    function display_message_in_page($message) {
        get_header();
        echo apply_filters('wpunewsletter_display_after_header', '');
        echo '<p>' . $message . '</p>';
        echo apply_filters('wpunewsletter_display_before_footer', '');
        get_footer();
        die();
    }

    function export_csv($results, $file_name) {

        // Send CSV Headers
        $handle = @fopen('php://output', 'w');
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header('Content-Description: File Transfer');
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=" . $file_name);
        header("Expires: 0");
        header("Pragma: public");

        // Export as CSV lines
        foreach ($results as $data) {
            fputcsv($handle, $data);
        }

        // Send CSV File
        fclose($handle);
        exit;
    }

    function html_content() {
        return 'text/html';
    }

    function wp_mail_from_name($email_name) {
        $wpunewsletter_useremailfromname = trim(get_option('wpunewsletter_useremailfromname'));
        if (!empty($wpunewsletter_useremailfromname)) {
            $email_name = $wpunewsletter_useremailfromname;
        }
        return $email_name;
    }

    function wp_mail_from($email_address) {
        $wpunewsletter_useremailfromaddress = trim(get_option('wpunewsletter_useremailfromaddress'));
        if (!empty($wpunewsletter_useremailfromaddress)) {
            $email_address = $wpunewsletter_useremailfromaddress;
        }
        return $email_address;
    }
}

/* ----------------------------------------------------------
  Widget
---------------------------------------------------------- */

// Create widget Form
add_action('widgets_init', 'wpunewsletter_form_register_widgets');
function wpunewsletter_form_register_widgets() {
    register_widget('wpunewsletter_form');
}
class wpunewsletter_form extends WP_Widget {
    function wpunewsletter_form() {
        parent::WP_Widget(false, '[WPU] Newsletter Form', array(
            'description' => 'Newsletter Form'
        ));
    }
    function form($instance) {
    }
    function update($new_instance, $old_instance) {
        return $new_instance;
    }
    function widget($args, $instance) {
        global $wpunewsletter_messages;

        $default_widget_content = '<form id="wpunewsletter-form" action="" method="post"><div>';
        $default_widget_content.= '<label for="wpunewsletter_email">' . __('Email', 'wpunewsletter') . '</label>
            <input type="email" name="wpunewsletter_email" id="wpunewsletter_email" value="" required />
            <button type="submit" class="cssc-button cssc-button--default">' . __('Register', 'wpunewsletter') . '</button>
        </div><div class="messages"></div></form>';

        echo $args['before_widget'];
        if (!empty($wpunewsletter_messages)) {
            echo '<p>' . implode('<br />', $wpunewsletter_messages) . '</p>';
        }
        echo apply_filters('wpunewsletter_form_widget_content', $default_widget_content);
        echo $args['after_widget'];
    }
}

/* ----------------------------------------------------------
  Launch
---------------------------------------------------------- */

$WPUNewsletter = new WPUNewsletter();

register_activation_hook(__FILE__, array(&$WPUNewsletter,
    'wpunewsletter_activate'
));
