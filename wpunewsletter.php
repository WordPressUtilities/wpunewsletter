<?php

/*
Plugin Name: WP Utilities Newsletter
Description: Allow subscriptions to a newsletter.
Version: 1.36.2
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

/*
 * To test :
 * - Delete user
 * - Widget Front
 * - Widget Admin
 *
 * To do :
 * - Use messages without global
 * - Use methods to get/add/delete mails
 * - Define only once URL routes : confirm / admin paged / admin page
 *
*/

$wpunewsletter_messages = array();

class WPUNewsletter {
    public $plugin_version = '1.36.2';
    public $table_name;
    public $extra_fields;
    public $custom_queries;
    public $admin_messages = array();
    public $dash_cache_id = 'wpunewsletter_dashboard_widget_subscribers';

    public function __construct() {
        global $wpdb;

        /* Vars */
        $this->plugin_id = 'wpunewsletter';
        $this->table_name = $wpdb->prefix . $this->plugin_id . "_subscribers";
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->plugin_dir = dirname(plugin_basename(__FILE__)) . '/';
        $this->perpage = apply_filters('wpunewsletter__archive_perpage', 50);
        $this->min_admin_level = apply_filters('wpunewsletter__min_admin_level', 'delete_posts');

        $this->db_version = get_option('wpunewsletter_db_version');
        if ($this->plugin_version != $this->db_version) {
            $this->wpunewsletter_activate();
        }

        if (is_admin() && $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            $this->wpunewsletter_activate_db();
        }

        /* Hooks */
        add_action('plugins_loaded', array(&$this,
            'load_translation'
        ));
        add_action('plugins_loaded', array(&$this,
            'plugins_loaded'
        ));
        add_action('init', array(&$this,
            'load_values'
        ));
        add_action('admin_menu', array(&$this,
            'menu_page'
        ));
        add_action('wp_dashboard_setup', array(&$this,
            'add_dashboard_widget'
        ));
        add_filter("plugin_action_links_" . plugin_basename(__FILE__), array(&$this,
            'settings_link'
        ));
        add_action('admin_init', array(&$this,
            'delete_postAction'
        ));
        add_action('admin_init', array(&$this,
            'import_postAction'
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

        add_shortcode('wpunewsletter', array(&$this,
            'form_shortcode'
        ));

        add_filter('comment_form_fields', array(&$this,
            'comment_form_fields'
        ), 999, 1);
        add_action('comment_post', array(&$this,
            'comment_post'
        ));
        add_action('comment_unapproved_to_approved', array(&$this,
            'comment_unapproved_to_approved'
        ));

        // Mailchimp
        add_action('wpunewsletter_mail_registered', array(&$this,
            'mailchimp_register'
        ), 10, 1);

        if (isset($_GET['page']) && (strpos($_GET['page'], 'wpunewsletter') !== false)) {
            add_action('admin_enqueue_scripts', array(&$this,
                'admin_enqueue_scripts'
            ));
        }
        add_action('wp_enqueue_scripts', array(&$this,
            'enqueue_scripts'
        ));
    }

    public function plugins_loaded() {
        require_once dirname(__FILE__) . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
        $this->settings_update = new \wpunewsletter\WPUBaseUpdate(
            'WordPressUtilities',
            'wpunewsletter',
            $this->plugin_version);
    }

    // Translation
    public function load_translation() {
        load_plugin_textdomain('wpunewsletter', false, $this->plugin_dir . 'lang/');
    }

    public function load_values() {
        $this->load_extra_fields(apply_filters('wpunewsletter_extra_fields', array()));
        $this->custom_queries = apply_filters('wpunewsletter_custom_export_queries', array());
    }

    public function load_extra_fields($extra_fields) {
        $_field_types = array(
            'text',
            'email',
            'url',
            'checkbox'
        );
        $this->extra_fields = array();
        foreach ($extra_fields as $id => $_f) {

            $required = (isset($_f['required']) && $_f['required']);
            $name = isset($_f['name']) ? ucfirst(esc_html($_f['name'])) : $id;
            $default_value = isset($_f['default_value']) ? esc_html($_f['default_value']) : '';
            $label_check = isset($_f['label_check']) ? esc_html($_f['label_check']) : $name;
            $field_classname = isset($_f['field_classname']) ? esc_html($_f['field_classname']) : '';
            $label_classname = isset($_f['label_classname']) ? esc_html($_f['label_classname']) : '';
            $wrapper_classname = isset($_f['wrapper_classname']) ? esc_html($_f['wrapper_classname']) : '';
            $type = isset($_f['type']) && in_array($_f['type'], $_field_types) ? $_f['type'] : $_field_types[0];
            $char_limit = (isset($_f['char_limit']) && is_numeric($_f['char_limit'])) ? $_f['char_limit'] : 200;

            $this->extra_fields[$id] = array(
                'required' => $required,
                'name' => $name,
                'default_value' => $default_value,
                'label_check' => $label_check,
                'field_classname' => $field_classname,
                'label_classname' => $label_classname,
                'wrapper_classname' => $wrapper_classname,
                'type' => $type,
                'char_limit' => $char_limit
            );
        }
    }

    // Admin JS
    public function admin_enqueue_scripts() {
        wp_enqueue_script('wpunewsletter_admin_js', $this->plugin_url . 'assets/script.js');
    }
    public function enqueue_scripts() {
        wp_enqueue_script('wpunewsletter_js', $this->plugin_url . 'assets/front.js', array(
            'jquery'
        ), $this->plugin_version, 1);
    }

    /* ----------------------------------------------------------
      Widget dashboard
    ---------------------------------------------------------- */

    public function add_dashboard_widget() {
        if (!apply_filters('wpunewsletter_enable_dashboard_widget', true)) {
            return;
        }
        if (!current_user_can($this->min_admin_level)) {
            return;
        }
        wp_add_dashboard_widget('wpunewsletter_dashboard_widget', 'Newsletter - ' . __('Latest subscribers', 'wpunewsletter'), array(&$this,
            'content_dashboard_widget'
        ));
    }

    public function content_dashboard_widget() {

        $results = wp_cache_get($this->dash_cache_id);
        if ($results === false) {
            global $wpdb;
            $results = $wpdb->get_results("SELECT id,email FROM " . $this->table_name . " ORDER BY id DESC LIMIT 0, 10");
            wp_cache_set($this->dash_cache_id, $results, '', 0);
        }

        if (!empty($results)) {
            foreach ($results as $result) {
                echo '<p>' . $result->id . ' - ' . $result->email . '</p>';
            }
        } else {
            echo '<p>' . __('No subscriber for now.', 'wpunewsletter') . '</p>';
        }
    }

    /* ----------------------------------------------------------
      Shortcode
    ---------------------------------------------------------- */

    public function form_shortcode($atts) {
        ob_start();
        the_widget('wpunewsletter_form');
        return ob_get_clean();
    }

    /* ----------------------------------------------------------
      Admin page
    ---------------------------------------------------------- */

    // Menu item
    public function menu_page() {
        add_menu_page('Newsletter', 'Newsletter', $this->min_admin_level, 'wpunewsletter', array(&$this,
            'page_content'
        ), 'dashicons-email-alt');
        add_submenu_page('wpunewsletter', __('Newsletter - Subscribers', 'wpunewsletter'), __('Subscribers', 'wpunewsletter'), $this->min_admin_level, 'wpunewsletter');
        add_submenu_page('wpunewsletter', __('Newsletter - Export', 'wpunewsletter'), __('Export', 'wpunewsletter'), $this->min_admin_level, 'wpunewsletter-export', array(&$this,
            'page_content_export'
        ));
        add_submenu_page('wpunewsletter', __('Newsletter - Import', 'wpunewsletter'), __('Import', 'wpunewsletter'), $this->min_admin_level, 'wpunewsletter-import', array(&$this,
            'page_content_import'
        ));
        add_submenu_page('wpunewsletter', __('Newsletter - Settings', 'wpunewsletter'), __('Settings', 'wpunewsletter'), $this->min_admin_level, 'wpunewsletter-settings', array(&$this,
            'page_content_settings'
        ));
    }

    public function display_messages() {

        $html = '';
        if (!empty($this->admin_messages)) {
            $html .= '<p>' . implode('<br />', $this->admin_messages) . '</p>';
            $this->admin_messages = array();
        }
        return $html;
    }

    // Admin page content
    public function page_content() {
        global $wpdb;

        $order_list = array('ASC', 'DESC');
        $orderby_list = array('id', 'email', 'date_register', 'is_valid');

        // Paginate
        $search = (isset($_GET['search']) && !empty($_GET['search']) ? esc_html($_GET['search']) : '');
        if (isset($_GET['reset_search'])) {
            $search = '';
        }
        $search_query = (!empty($search) ? " AND (email LIKE '%" . esc_sql($search) . "%' OR extra LIKE '%" . esc_sql($search) . "%')" : '');
        $current_page = ((isset($_GET['paged']) && is_numeric($_GET['paged'])) ? $_GET['paged'] : 1);
        $nb_start = ($current_page * $this->perpage) - $this->perpage;
        $orderby = 'id';
        $orderby = isset($_GET['orderby']) && in_array($_GET['orderby'], $orderby_list) ? $_GET['orderby'] : 'id';
        $order = isset($_GET['order']) && in_array($_GET['order'], $order_list) ? $_GET['order'] : 'DESC';
        $base_url = '/admin.php?page=wpunewsletter';
        if (!empty($search)) {
            $base_url .= '&search=' . esc_html($search);
        }

        // Get results
        $nb_results_req = $wpdb->get_row("SELECT COUNT(id) as count_id FROM " . $this->table_name . " WHERE 1=1 " . $search_query);
        $nb_results_total = (int) $nb_results_req->count_id;
        $max_page = ceil($nb_results_total / $this->perpage);

        // Get page results
        $results = $wpdb->get_results("SELECT * FROM " . $this->table_name . " WHERE 1=1 " . $search_query . " ORDER BY " . $orderby . " " . $order . " LIMIT " . $nb_start . ", " . $this->perpage);
        $nb_results = count($results);

        // Display wrapper
        echo '<div class="wrap"><h2 class="title">' . get_admin_page_title() . '</h2>';

        echo $this->display_messages();

        echo '<form style="float:right;" action="admin.php" method="get">';
        echo '<input type="hidden" name="page" value="wpunewsletter" />';
        echo '<input type="hidden" name="orderby" value="' . $orderby . '" />';
        echo '<input type="hidden" name="order" value="' . $order . '" />';
        echo '<input type="search" name="search" value="' . esc_attr($search) . '" />';
        echo ' ';
        submit_button(__('Search'), 'primary', 'launch_search', false);
        echo ' ';
        if ($search) {
            submit_button(__('Cancel'), 'secondary', 'reset_search', false);
        }
        echo '</form>';

        echo '<h3>' . sprintf(__('Subscribers list : %s', 'wpunewsletter'), $nb_results_total) . '</h3>';

        // If empty
        if ($nb_results < 1) {

            // - Display blank slate message
            echo '<p>' . __('No subscriber for now.', 'wpunewsletter') . '</p>';
        } else {
            echo '<form action="" method="post">';

            // - Display results
            echo '<table class="widefat">';
            $cols = '<tr>';
            $cols .= '<th><input type="checkbox" class="wpunewsletter_element_check" name="wpunewsletter_element_check" /></th>';
            $cols .= '<th><a href="' . $this->get_admin_url($base_url, ($orderby == 'id' && $order == 'ASC' ? 'DESC' : 'ASC'), 'id') . '">' . __('ID', 'wpunewsletter') . '</a></th>';
            $cols .= '<th><a href="' . $this->get_admin_url($base_url, ($orderby == 'email' && $order == 'ASC' ? 'DESC' : 'ASC'), 'email') . '">' . __('Email', 'wpunewsletter') . '</a></th>';
            foreach ($this->extra_fields as $id => $field) {
                $cols .= '<th>' . $field['name'] . '</th>';
            }
            $cols .= '<th>' . __('Locale', 'wpunewsletter') . '</th>';
            $cols .= '<th><a href="' . $this->get_admin_url($base_url, ($order == 'ASC' ? 'DESC' : 'ASC'), 'date_register') . '">' . __('Date', 'wpunewsletter') . '</a></th>';
            $cols .= '<th><a href="' . $this->get_admin_url($base_url, ($order == 'ASC' ? 'DESC' : 'ASC'), 'is_valid') . '">' . __('Valid', 'wpunewsletter') . '</a></th>';
            $cols .= '</tr>';

            echo '<thead>' . $cols . '</thead>';
            echo '<tfoot>' . $cols . '</tfoot>';
            foreach ($results as $result) {
                echo '<tbody><tr>
            <td style="width: 15px; text-align: right;">
            <input type="checkbox" class="wpunewsletter_element" name="wpunewsletter_element[]" value="' . $result->id . '" />
            </td>
            <td>' . $result->id . '</td>
            <td>' . $result->email . '</td>';
                $result_extra = (array) json_decode($result->extra);
                foreach ($this->extra_fields as $id => $field) {
                    echo '<td>' . (isset($result_extra[$id]) ? esc_html($result_extra[$id]) : '') . '</td>';
                }
                echo '<td>' . $result->locale . '</td>
            <td>' . $result->date_register . '</td>
            <td>' . $result->is_valid . '</td>
            </tr></tbody>';
            }
            echo '</table>';
            echo wp_nonce_field('wpunewsletter_delete', 'wpunewsletter_delete_nonce');
            echo submit_button(__('Delete selected lines', 'wpunewsletter'));
            echo '</form>';
        }
        echo '</div>';

        if ($max_page > 1) {

            // need an unlikely integer
            $big = 999999999;
            $replace = '%#%';
            $admin_url = $this->get_admin_url($base_url, $order, $orderby) . '&paged=' . $replace;
            $url_base = str_replace($big, $replace, esc_url(get_pagenum_link($big, false)));
            $url_base = str_replace('&#038;', '&', $url_base);
            echo '<p>' . paginate_links(array(
                'base' => $url_base,
                'format' => $admin_url,
                'current' => max(1, $current_page),
                'total' => $max_page
            )) . '</p>';
        }
    }

    public function get_admin_url($base_url, $order = 'DESC', $orderby = 'id') {
        return admin_url($base_url . '&order=' . $order . '&orderby=' . $orderby);
    }

    // Delete element
    public function delete_postAction() {
        global $wpdb;

        $nb_delete = 0;
        if (isset($_POST['wpunewsletter_delete_nonce']) && wp_verify_nonce($_POST['wpunewsletter_delete_nonce'], 'wpunewsletter_delete') && isset($_POST['wpunewsletter_element']) && is_array($_POST['wpunewsletter_element']) && !empty($_POST['wpunewsletter_element'])) {
            foreach ($_POST['wpunewsletter_element'] as $id) {
                $wpdb->delete($this->table_name, array(
                    'id' => $id
                ));
                $nb_delete++;
            }
            wp_cache_delete($this->dash_cache_id);
        }

        if ($nb_delete > 0) {
            $this->admin_messages[] = 'Mail suppressions : ' . $nb_delete;
        }
    }

    /* ----------------------------------------------------------
      Admin page - Import
    ---------------------------------------------------------- */

    public function page_content_import() {

        if (!current_user_can($this->min_admin_level)) {
            return;
        }
        echo '<div class="wrap"><h2 class="title">' . get_admin_page_title() . '</h2>';
        echo $this->display_messages();
        echo '<form action="" method="post"><p>';
        echo '<label for="wpunewsletter_import_addresses">' . __('Addresses to import:', 'wpunewsletter') . '<br /></label> ';
        echo '<textarea required name="wpunewsletter_import_addresses" id="wpunewsletter_import_addresses" cols="30" rows="10"></textarea>';
        echo wp_nonce_field('wpunewsletter_import', 'wpunewsletter_import_nonce');
        echo submit_button(__('Import addresses', 'wpunewsletter'));
        echo '</form>
        </div>';
    }

    public function import_postAction() {
        global $wpdb;
        if (!current_user_can($this->min_admin_level)) {
            return;
        }

        // Check if export is correctly asked
        if (isset($_POST['wpunewsletter_import_nonce'], $_POST['wpunewsletter_import_addresses']) && wp_verify_nonce($_POST['wpunewsletter_import_nonce'], 'wpunewsletter_import') && !empty($_POST['wpunewsletter_import_addresses'])) {
            $nb_addresses = $this->import_addresses_from_text($_POST['wpunewsletter_import_addresses']);
            if ($nb_addresses > 0) {
                $this->admin_messages[] = sprintf(__('Mail insertions : %s', 'wputh'), $nb_addresses);
            } else {
                $this->admin_messages[] = __('No mail insertions ', 'wputh');
            }
        }
    }

    public function import_addresses_from_text($text) {

        $nb_addresses = 0;
        $text = str_replace(array(
            ';',
            ',',
            ' '
        ), "\n", $text);
        $text = strtolower($text);

        $addresses = explode("\n", $text);
        foreach ($addresses as $add) {
            $address = trim($add);
            if (empty($address) || !is_email($address)) {
                continue;
            }
            $ins = $this->register_mail($address, false, true);
            if ($ins == 1) {
                $nb_addresses++;
            }
        }
        return $nb_addresses;
    }

    /* ----------------------------------------------------------
      Admin Page - Export
    ---------------------------------------------------------- */

    public function page_content_export() {
        global $wp_roles;

        if (!current_user_can($this->min_admin_level)) {
            return;
        }
        echo '<div class="wrap"><h2 class="title">' . get_admin_page_title() . '</h2>';

        do_action('wpunewsletter_export_page_before');

        /* Subscribers */
        echo '<h3>' . __('Subscribers', 'wpunewsletter') . '</h3>
        <form action="" method="post"><p>';
        echo '<label for="wpunewsletter_export_type">' . __('Addresses to export:', 'wpunewsletter') . '</label><br />';
        echo '<select name="wpunewsletter_export_type" id="wpunewsletter_export_type">
        <option value="validated">' . __('Only valid', 'wpunewsletter') . '</option>
        <option value="all">' . __('All', 'wpunewsletter') . '</option>
    </select></p>';
        echo wp_nonce_field('wpunewsletter_export', 'wpunewsletter_export_nonce');
        echo submit_button(__('Export addresses', 'wpunewsletter'));
        echo '</form>';

        /* Users */
        $result = count_users();
        echo '<h3>' . __('Users', 'wpunewsletter') . '</h3>
        <form action="" method="post"><p>';
        echo '<label for="wpunewsletter_export_role">' . __('Addresses to export:', 'wpunewsletter') . '</label><br />';
        echo '<select multiple name="wpunewsletter_export_role[]" id="wpunewsletter_export_role">';
        echo '<option value="all">' . __('All', 'wpunewsletter') . '</option>';
        foreach ($result['avail_roles'] as $role => $count) {
            if (!$count) {
                continue;
            }
            $obj_role = get_role($role);
            echo '<option value="' . $role . '">' . translate_user_role($wp_roles->roles[$role]['name']) . ' (' . $count . ')' . '</option>';
        }
        echo '</select></p>';
        echo wp_nonce_field('wpunewsletter_exportusers', 'wpunewsletter_exportusers_nonce');
        echo submit_button(__('Export addresses', 'wpunewsletter'));
        echo '</form>';

        /* Custom */
        if (!empty($this->custom_queries)) {

            echo '<h3>' . __('Custom', 'wpunewsletter') . '</h3>
        <form action="" method="post"><p>';
            echo '<label for="wpunewsletter_export_custom">' . __('Addresses to export:', 'wpunewsletter') . '</label><br />';
            echo '<select name="wpunewsletter_export_custom" id="wpunewsletter_export_custom">';
            foreach ($this->custom_queries as $id => $query) {
                $query_name = $id;
                if (isset($query['name'])) {
                    $query_name = $query['name'];
                }
                echo '<option value="' . $id . '">' . $query_name . '</option>';
            }
            echo '</select></p>';
            echo wp_nonce_field('wpunewsletter_exportcustom', 'wpunewsletter_exportcustom_nonce');
            echo submit_button(__('Export addresses', 'wpunewsletter'));
            echo '</form>';
        }

        do_action('wpunewsletter_export_page_after');

        echo '</div>';
    }

    // Generate CSV for export
    public function export_postAction() {
        global $wpdb;
        if (!current_user_can($this->min_admin_level)) {
            return;
        }

        do_action('wpunewsletter_export_postAction');

        // Check if export is correctly asked
        if (isset($_POST['wpunewsletter_exportusers_nonce']) && wp_verify_nonce($_POST['wpunewsletter_exportusers_nonce'], 'wpunewsletter_exportusers')) {

            $args = array();
            if (isset($_POST['wpunewsletter_export_role'])) {
                $args['role__in'] = array();
                foreach ($_POST['wpunewsletter_export_role'] as $role) {
                    $args['role__in'][] = $role;
                }
                if (in_array('all', $args['role__in'])) {
                    unset($args['role__in']);
                }
            }

            $blogusers = get_users(apply_filters('wpunewsletter_exportusers_args', $args));
            $results = array();

            // Array of WP_User objects.
            foreach ($blogusers as $user) {
                $results[] = array(
                    'email' => $user->user_email,
                    'extra' => ''
                );
            }

            $file_name = sanitize_title(get_bloginfo('name')) . '-' . date('Y-m-d') . '-wpunewsletter' . '.csv';
            $this->export_csv($results, $file_name);
        }

        // Check if export is correctly asked
        if (isset($_POST['wpunewsletter_export_nonce']) && wp_verify_nonce($_POST['wpunewsletter_export_nonce'], 'wpunewsletter_export')) {

            $request_more = '';
            if (isset($_POST['wpunewsletter_export_type']) && $_POST['wpunewsletter_export_type'] == 'validated') {
                $request_more = ' WHERE is_valid = 1';
            }

            $results = $wpdb->get_results("SELECT * FROM " . $this->table_name . $request_more, ARRAY_A);
            $file_name = sanitize_title(get_bloginfo('name')) . '-' . date('Y-m-d') . '-wpunewsletter' . '.csv';

            $this->export_csv($results, $file_name);
        }

        // Check if export is correctly asked
        if (isset($_POST['wpunewsletter_exportcustom_nonce'], $_POST['wpunewsletter_export_custom']) && wp_verify_nonce($_POST['wpunewsletter_exportcustom_nonce'], 'wpunewsletter_exportcustom')) {

            if (!array_key_exists($_POST['wpunewsletter_export_custom'], $this->custom_queries)) {
                return;
            }

            $args = $this->custom_queries[$_POST['wpunewsletter_export_custom']];

            if (isset($args['name'])) {
                unset($args['name']);
            }

            $merge_with_validated = false;
            if (isset($args['merge_with_validated'])) {
                unset($args['merge_with_validated']);
                $merge_with_validated = true;
            }

            if (!isset($args['number'])) {
                $args['number'] = 0;
            }

            $blogusers = get_users(apply_filters('wpunewsletter_exportcustom_args', $args));

            // Array of WP_User objects.
            $results = array();
            if ($merge_with_validated) {
                $results = $wpdb->get_results("SELECT * FROM " . $this->table_name . ' WHERE is_valid = 1', ARRAY_A);
            }
            foreach ($blogusers as $user) {
                $results[] = array(
                    'email' => $user->user_email,
                    'extra' => ''
                );
            }

            $file_name = sanitize_title(get_bloginfo('name')) . '-' . date('Y-m-d') . '-wpunewsletter' . '.csv';

            $this->export_csv($results, $file_name);
        }
    }

    // Add settings link on plugin page
    public function settings_link($links) {
        array_unshift($links, '<a href="admin.php?page=wpunewsletter">' . __('Subscribers', 'wpunewsletter') . '</a>');
        array_unshift($links, '<a href="admin.php?page=wpunewsletter-settings">' . __('Settings', 'wpunewsletter') . '</a>');
        return $links;
    }

    /* ----------------------------------------------------------
      Mailchimp
    ---------------------------------------------------------- */

    public function mailchimp_load() {
        require_once dirname(__FILE__) . '/inc/mailchimp/Mailchimp.php';
        $mailchimp_active = get_option('wpunewsletter_mailchimp_active');
        return ($mailchimp_active == 1);
    }

    public function mailchimp_test($api_key, $list_id) {
        if (!$this->mailchimp_load()) {
            return false;
        }

        $Mailchimp = new Mailchimp($api_key);
        $Mailchimp_Lists = new Mailchimp_Lists($Mailchimp);

        $subscriber = $Mailchimp_Lists->getList(array(
            'exact' => 1,
            'list_id' => $list_id
        ));

        return (isset($subscriber['data'], $subscriber['data'][0], $subscriber['data'][0]['id']) && $subscriber['data'][0]['id'] == $list_id);
    }

    public function mailchimp_register($email_vars) {
        if (!$this->mailchimp_load()) {
            return false;
        }

        $api_key = get_option('wpunewsletter_mailchimp_apikey');
        $list_id = get_option('wpunewsletter_mailchimp_listid');
        $double_optin = (get_option('wpunewsletter_mailchimp_double_optin') == '1');

        $Mailchimp = new Mailchimp($api_key);
        $Mailchimp_Lists = new Mailchimp_Lists($Mailchimp);

        $subscriber = $Mailchimp_Lists->subscribe($list_id, array(
            'email' => htmlentities($email_vars['email'])
        ), null, 'html', $double_optin);

        $is_subscribed = !empty($subscriber['leid']);
    }

    /* ----------------------------------------------------------
      Actions
    ---------------------------------------------------------- */

    public function get_mail_infos($email) {
        global $wpdb;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $this->table_name . ' WHERE email = %s', $email));
    }

    public function mail_is_subscribed($email) {
        global $wpdb;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $testbase = $wpdb->get_row($wpdb->prepare('SELECT email FROM ' . $this->table_name . ' WHERE email = %s', $email));
        return isset($testbase->email);
    }

    public function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function register_mail($email, $send_confirmation_mail = false, $check_subscription = false, $extra = array()) {
        global $wpunewsletter_messages, $wpdb;

        if (!$this->is_email($email)) {
            return false;
        }

        // If mail is subscribed
        if ($check_subscription && $this->mail_is_subscribed($email)) {
            return false;
        }

        $secretkey = md5(microtime() . $email);
        $is_valid = $send_confirmation_mail ? 0 : 1;

        $mailchimp_active = get_option('wpunewsletter_mailchimp_active');

        if ($mailchimp_active == 1) {
            $send_confirmation_mail = false;
            $is_valid = 1;
        }

        $email_vars = array(
            'email' => $email,
            'locale' => get_locale(),
            'secretkey' => $secretkey,
            'is_valid' => $is_valid,
            'extra' => json_encode($extra)
        );

        $insert = $wpdb->insert($this->table_name, $email_vars);
        wp_cache_delete($this->dash_cache_id);

        if ($insert !== false) {
            do_action('wpunewsletter_mail_registered', $email_vars);
        }

        if ($send_confirmation_mail && $insert !== false) {
            $this->send_confirmation_email($email, $secretkey);
        }

        return $insert;
    }

    // Widget POST Action
    public function postAction() {
        global $wpunewsletter_messages, $wpdb;

        $check_subscription = false;
        $send_confirmation_mail = (get_option('wpunewsletter_send_confirmation_email') == 1);

        // If there is a valid email address
        if (!isset($_POST['wpunewsletter_email'])) {
            return;
        }

        if (!$this->is_email($_POST['wpunewsletter_email'])) {
            $this->display_error_messages(apply_filters('wpunewsletter_message_not_email', '<span class="error">' . __("This is not an email address.", 'wpunewsletter') . '</span>'));
            die;
        }

        // Honeypot present
        if (!isset($_POST['wpunewsletter_email_hid'])) {
            $this->display_error_messages(apply_filters('wpunewsletter_message_honeypot_missing', '<span class="error">' . __("The form is invalid.", 'wpunewsletter') . '</span>'));
            die;
        }

        // Honeypot valid
        if ($_POST['wpunewsletter_email_hid'] != str_rot13($_POST['wpunewsletter_email'])) {
            $this->display_error_messages(apply_filters('wpunewsletter_message_honeypot_invalid', '<span class="error">' . __("The form is invalid. Is Javascript disabled on your computer ?", 'wpunewsletter') . '</span>'));
            die;
        }

        if ($this->mail_is_subscribed($_POST['wpunewsletter_email'])) {
            $wpunewsletter_messages[] = apply_filters('wpunewsletter_message_register_already', '<span class="error">' . __('This mail is already registered', 'wpunewsletter') . '</span>');
        } else {
            $extra = $this->get_extras_from($_POST);
            if ($extra !== false) {
                $subscription = $this->register_mail($_POST['wpunewsletter_email'], $send_confirmation_mail, $check_subscription, $extra);
                if (!$subscription) {
                    $wpunewsletter_messages[] = apply_filters('wpunewsletter_message_register_nok', '<span class="error">' . __("This mail can't be registered", 'wpunewsletter') . '</span>');
                } else {
                    $wpunewsletter_messages[] = apply_filters('wpunewsletter_message_register_ok', '<span class="success">' . __('This mail is now registered', 'wpunewsletter') . '</span>');
                }
            } else {
                $wpunewsletter_messages[] = apply_filters('wpunewsletter_message_register_missing_extra', '<span class="error">' . __('Some fields are missing', 'wpunewsletter') . '</span>');
            }
        }

        if (isset($_POST['ajax'])) {
            $this->display_error_messages($wpunewsletter_messages);
            die;
        }
    }

    public function display_error_messages($messages = array()) {
        if (empty($messages)) {
            return;
        }
        if (!is_array($messages)) {
            $messages = array($messages);
        }
        echo '<p>' . implode('<br />', $messages) . '</p>';
    }

    public function get_extras_from($from) {
        $required_missing = false;

        $extra = array();
        foreach ($this->extra_fields as $id => $field) {
            $value = '';

            // Get value
            if (isset($from['wpunewsletter_extra__' . $id])) {
                $value = $from['wpunewsletter_extra__' . $id];
            }

            // Test required
            if ((!isset($from['wpunewsletter_extra__' . $id]) || empty($value)) && $field['required']) {
                $required_missing = true;
            }

            // Filter value
            switch ($field['type']) {
            case 'checkbox':
                $value = (!empty($value)) ? 1 : 0;
                break;
            case 'url':
                $value = !filter_var($value, FILTER_VALIDATE_URL) ? '' : $value;
                break;
            case 'email':
                $value = !filter_var($value, FILTER_VALIDATE_EMAIL) ? '' : $value;
                break;
            default:
                $value = esc_html($value);
            }

            // Limit value
            $extra[$id] = substr($value, 0, $field['char_limit']);
        }

        if ($required_missing) {
            $extra = false;
        }

        return $extra;
    }

    public function confirm_address() {
        if (!isset($_GET['wpunewsletter_key'], $_GET['wpunewsletter_email'])) {
            return;
        }
        global $wpdb;
        $message_type = 'failure';
        $message = apply_filters('wpunewsletter_failuremsg', __("Your subscription couldn't be confirmed", 'wpunewsletter'));
        $address_exists = $wpdb->get_row($wpdb->prepare("SELECT id FROM " . $this->table_name . " WHERE email = %s AND secretkey = %s", $_GET['wpunewsletter_email'], $_GET['wpunewsletter_key']));
        if (isset($address_exists->id)) {

            // Update
            $update = $wpdb->update($this->table_name, array(
                'is_valid' => '1'
            ), array(
                'id' => $address_exists->id
            ), array(
                '%d'
            ));
            if ($update !== FALSE) {
                $message_type = 'success';
                $message = apply_filters('wpunewsletter_successmsg', __("Your subscription has been successfully confirmed", 'wpunewsletter'));
            }
        }

        $this->display_message_in_page($message, $message_type);
    }

    /* ----------------------------------------------------------
      Settings
    ---------------------------------------------------------- */

    public function form_item__checkbox($id, $name) {
        $html = '<p><label>';
        $html .= '<input type="checkbox" id="form_item__' . $id . '" name="' . $id . '" ' . checked(get_option($id), 1, false) . ' value="1" />' . $name;
        $html .= '</label></p>';
        return $html;
    }

    public function form_item__text($id, $name, $type = 'text') {
        $html = '<p>';
        $html .= '<strong><label for="' . $id . '">' . $name . '</label></strong><br />';
        $html .= '<input type="' . $type . '" id="' . $id . '" name="' . $id . '" value="' . esc_attr(get_option($id)) . '" />';
        $html .= '</p>';
        return $html;
    }

    public function form_item__editor($id, $name, $settings = array()) {
        $html = '<p>';
        $html .= '<strong><label for="' . $id . '">' . $name . '</label></strong><br />';
        ob_start();
        wp_editor(get_option($id), $id, $settings);
        $html .= ob_get_clean();
        $html .= '</p>';
        return $html;
    }

    public function page_content_settings() {
        if (!current_user_can($this->min_admin_level)) {
            return;
        }
        echo '<div class="wrap"><h2 class="title">' . get_admin_page_title() . '</h2>';
        echo $this->display_messages();
        echo '<form action="" method="post">';

        echo $this->form_item__checkbox('wpunewsletter_checkbox_comments', __('Register in comments', 'wpunewsletter'));

        echo '<hr /><h3>' . __('GPRD', 'wpunewsletter') . '</h3>';
        echo $this->form_item__editor('wpunewsletter_gprdtext', __('GPRD text under newsletter', 'wpunewsletter'), array(
            'media_buttons' => false,
            'teeny' => true,
            'textarea_rows' => 3
        ));
        echo $this->form_item__checkbox('wpunewsletter_send_confirmation_email', __('Send confirmation email', 'wpunewsletter'));

        echo '<hr /><h3>' . __('Outgoing emails', 'wpunewsletter') . '</h3>';
        echo $this->form_item__text('wpunewsletter_useremailfromname', __('From name', 'wpunewsletter'));
        echo $this->form_item__text('wpunewsletter_useremailfromaddress', __('From address', 'wpunewsletter'), 'email');

        echo '<hr /><h3>' . __('Mailchimp', 'wpunewsletter') . '</h3>';
        echo $this->form_item__checkbox('wpunewsletter_mailchimp_active', __('Use Mailchimp', 'wpunewsletter'));
        $_mailchimpIsOpen = (get_option('wpunewsletter_mailchimp_active') == '1');
        echo '<div id="wpunewsletter-mailchimp-detail" style="' . ($_mailchimpIsOpen ? '' : 'display: none;') . '">';
        echo $this->form_item__checkbox('wpunewsletter_mailchimp_double_optin', __('Use double optin', 'wpunewsletter'));
        echo $this->form_item__text('wpunewsletter_mailchimp_apikey', __('API Key', 'wpunewsletter'));
        echo $this->form_item__text('wpunewsletter_mailchimp_listid', __('List ID', 'wpunewsletter'));
        echo '</div>';

        echo '<hr />';
        echo wp_nonce_field('wpunewsletter_settings', 'wpunewsletter_settings_nonce');
        echo '<p>';
        echo submit_button(__('Update and test options', 'wpunewsletter'), 'secondary', 'test', false) . ' ';
        echo submit_button(__('Update options', 'wpunewsletter'), 'primary', 'save', false);
        echo '</p>';
        echo '</form></div>';
    }

    public function settings_postAction() {

        if (!current_user_can($this->min_admin_level)) {
            return;
        }
        $nonce = 'wpunewsletter_settings_nonce';
        $action = 'wpunewsletter_settings';
        if (empty($_POST)) {
            return;
        }
        if (!isset($_POST[$nonce]) || !wp_verify_nonce($_POST[$nonce], 'wpunewsletter_settings')) {
            return;
        }

        /* Update checkbox fields */
        $checkbox_fields = array(
            'wpunewsletter_send_confirmation_email',
            'wpunewsletter_checkbox_comments',
            'wpunewsletter_mailchimp_active',
            'wpunewsletter_mailchimp_double_optin'
        );
        foreach ($checkbox_fields as $field) {
            update_option($field, (isset($_POST[$field]) ? 1 : ''));
        }

        /* Update HTML fields */
        $editor_fields = array(
            'wpunewsletter_gprdtext'
        );
        foreach ($editor_fields as $field) {
            update_option($field, stripslashes($_POST[$field]));
        }

        /* Update text fields */
        $text_fields = array(
            'wpunewsletter_useremailfromaddress' => '',
            'wpunewsletter_useremailfromname' => '',
            'wpunewsletter_mailchimp_apikey' => '',
            'wpunewsletter_mailchimp_listid' => ''
        );
        foreach ($text_fields as $key => $var) {
            $value = get_option($key);
            if (isset($_POST[$key]) && $_POST[$key] != $value) {
                update_option($key, trim(esc_html($_POST[$key])));
            }
        }

        $this->admin_messages[] = __('Success : Updated options', 'wpunewsletter');

        if (isset($_POST['test']) && isset($_POST['wpunewsletter_mailchimp_active'])) {
            $test = $this->mailchimp_test($_POST['wpunewsletter_mailchimp_apikey'], $_POST['wpunewsletter_mailchimp_listid']);
            if ($test) {
                $this->admin_messages[] = __('Success : Mailchimp IDs are correct', 'wpunewsletter');
            } else {
                $this->admin_messages[] = __('Failure : Mailchimp IDs are not correct', 'wpunewsletter');
            }
        }
    }

    /* ----------------------------------------------------------
      Comments
    ---------------------------------------------------------- */

    public function comment_form_fields($fields) {
        if (get_option('wpunewsletter_checkbox_comments') != '1') {
            return $fields;
        }
        $fields['newsletter'] = '<p class="comment-form-register-newsletter">' .
        '<input id="wp-comment-register-newsletter" name="wp-comment-register-newsletter" value="1" type="checkbox" />' .
        ' ' .
        '<label for="wp-comment-register-newsletter">' . apply_filters('wpunewsletter_register_newsletter_comments_label', __('Register to our newsletter', 'wpunewsletter')) . '</label>' .
            '</p>';

        return $fields;
    }

    public function comment_post($comment_id) {
        if (get_option('wpunewsletter_checkbox_comments') != '1') {
            return;
        }
        if (!isset($_POST['wp-comment-register-newsletter']) || $_POST['wp-comment-register-newsletter'] != '1') {
            return;
        }

        $comment_status = wp_get_comment_status($comment_id);
        if ($comment_status != 'approved') {
            update_comment_meta($comment_id, 'wpunewsletter_register', '2', true);
            error_log($comment_status);
            return;
        }

        $this->register_commenter($comment_id);
    }

    public function comment_unapproved_to_approved($comment) {
        if (get_option('wpunewsletter_checkbox_comments') != '1') {
            return;
        }
        if (!is_object($comment)) {
            $comment = get_comment($comment);
        }
        $this->register_commenter($comment->comment_ID);
    }

    public function register_commenter($comment_id) {
        $is_registered = get_comment_meta($comment_id, 'wpunewsletter_register', 1);
        if ($is_registered == '1') {
            return;
        }
        update_comment_meta($comment_id, 'wpunewsletter_register', '1', true);
        $comment_details = get_comment($comment_id);
        if (isset($comment_details->comment_author_email) || is_email($comment_details->comment_author_email)) {
            $this->register_mail($comment_details->comment_author_email, (get_option('wpunewsletter_send_confirmation_email') == 1), true, array());
        }
    }

    /* ----------------------------------------------------------
      Model
    ---------------------------------------------------------- */

    public function wpunewsletter_activate() {
        $this->wpunewsletter_activate_options();
        return $this->wpunewsletter_activate_db();
    }

    public function wpunewsletter_activate_options() {
        // Default values
        update_option('wpunewsletter_send_confirmation_email', 1);
    }

    public function wpunewsletter_activate_db() {
        // DB Version
        update_option('wpunewsletter_db_version', $this->plugin_version);

        // Create or update database
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        return dbDelta("CREATE TABLE " . $this->table_name . " (
            id int(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) DEFAULT NULL,
            extra TEXT DEFAULT NULL,
            locale VARCHAR(20) DEFAULT NULL,
            secretkey VARCHAR(100) DEFAULT NULL,
            date_register TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_valid tinyint(1) unsigned DEFAULT '0' NOT NULL
        );");
    }

    public function uninstall() {

        /* ----------------------------------------------------------
          Remove options
        ---------------------------------------------------------- */

        $options = array(
            'wpunewsletter_checkbox_comments',
            'wpunewsletter_db_version',
            'wpunewsletter_mailchimp_active',
            'wpunewsletter_mailchimp_apikey',
            'wpunewsletter_mailchimp_double_optin',
            'wpunewsletter_mailchimp_listid',
            'wpunewsletter_send_confirmation_email',
            'wpunewsletter_use_jquery_ajax',
            'wpunewsletter_useremailfromaddress',
            'wpunewsletter_useremailfromname'
        );
        foreach ($options as $option_name) {
            delete_option($option_name);
        }

        /* ----------------------------------------------------------
          Remove table
        ---------------------------------------------------------- */

        global $wpdb;
        $q = $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");

        /* ----------------------------------------------------------
          Clear cache
        ---------------------------------------------------------- */

        wp_cache_delete($this->dash_cache_id);
    }

    /* ----------------------------------------------------------
      Utilities
    ---------------------------------------------------------- */

    public function send_confirmation_email($email, $secretkey) {
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
        $email_content .= '<p>' . __('Please click on the link below to confirm your subscription to our newsletter:', 'wpunewsletter');
        $email_content .= '<br /><a href="' . $confirm_url . '">' . $confirm_url . '</a></p>' . '<p>' . __('Thank you !', 'wpunewsletter') . '</p>';

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

    public function display_message_in_page($message, $message_type = 'success') {
        add_filter('body_class', array(&$this, 'add_body_class_confirm_newsletter'));
        get_header();
        echo apply_filters('wpunewsletter_display_after_header', '');
        echo '<div class="wpunewsletter-message-wrapper wpunewsletter-message--' . $message_type . '"><p>' . $message . '</p></div>';
        echo apply_filters('wpunewsletter_display_before_footer', '');
        get_footer();
        die();
    }

    public function add_body_class_confirm_newsletter($classes) {
        $classes[] = 'wpunewsletter-confirmation-page';
        return $classes;
    }

    public function export_csv($results, $file_name) {

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
            $csv_data = $data;
            $extra = (array) json_decode($data['extra']);
            unset($csv_data['extra']);
            foreach ($this->extra_fields as $id => $field) {
                $csv_data[$id] = isset($extra[$id]) ? $extra[$id] : '';
            }

            fputcsv($handle, $csv_data);
        }

        // Send CSV File
        fclose($handle);
        exit;
    }

    public function html_content() {
        return 'text/html';
    }

    public function wp_mail_from_name($email_name) {
        $wpunewsletter_useremailfromname = trim(get_option('wpunewsletter_useremailfromname'));
        if (!empty($wpunewsletter_useremailfromname)) {
            $email_name = $wpunewsletter_useremailfromname;
        }
        return $email_name;
    }

    public function wp_mail_from($email_address) {
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
            'form_has_wrapper' => true,
            'fields_has_wrapper' => true,
            'messages_over_form' => true,
            'main_field_have_wrapper' => true,
            'hidden_fields' => array(),
            'extra_fields_values' => array(),
            'main_field_position' => 'before',
            'form_id' => 'newsletter-form',
            'gprdtext' => get_option('wpunewsletter_gprdtext'),
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

        /* Display */
        $widg_form_has_wrapper = apply_filters('wpunewsletter_form_widget_form_has_wrapper', $curr_instance['form_has_wrapper'], $instance);
        $widg_fields_has_wrapper = apply_filters('wpunewsletter_form_widget_fields_has_wrapper', $curr_instance['fields_has_wrapper'], $instance);
        $widg_main_field_have_wrapper = apply_filters('wpunewsletter_form_widget_main_field_have_wrapper', $curr_instance['main_field_have_wrapper'], $instance);
        $widg_messages_over_form = apply_filters('wpunewsletter_form_widget_messages_over_form', $curr_instance['messages_over_form'], $instance);
        $widg_hidden_fields = apply_filters('wpunewsletter_form_widget_hidden_fields', $curr_instance['hidden_fields'], $instance);
        $widg_extra_fields_values = apply_filters('wpunewsletter_form_widget_extra_fields_values', $curr_instance['extra_fields_values'], $instance);
        $widg_main_field_position = apply_filters('wpunewsletter_form_widget_main_field_position', $curr_instance['main_field_position'], $instance);

        /* Text */
        $widg_gprdtext = trim(apply_filters('wpunewsletter_form_widget_gprdtext', $curr_instance['gprdtext'], $instance));

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
        $main_newsletter_field .= ($widg_main_field_have_wrapper && $widg_fields_has_wrapper) ? '</p>' : '';
        $main_newsletter_field .= apply_filters('wpunewsletter__after_main_field', '', $instance);

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

/* ----------------------------------------------------------
  Launch
---------------------------------------------------------- */

$WPUNewsletter = new WPUNewsletter();

register_activation_hook(__FILE__, array(&$WPUNewsletter,
    'wpunewsletter_activate'
));
