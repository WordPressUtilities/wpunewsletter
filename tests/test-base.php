<?php
class BaseTest extends WP_UnitTestCase {

    public $plugin;

    function setUp() {
        parent::setUp();
        $this->plugin = new WPUNewsletter;
    }

    function test_init_plugin() {

        // Test plugin init
        do_action('plugins_loaded');
        $this->assertEquals(10, has_action('plugins_loaded', array(
            $this->plugin,
            'load_translation'
        )));
    }

    function test_table_creation() {
        global $wpdb;

        // Try table creation
        $created = $this->plugin->wpunewsletter_activate();
        $this->assertGreaterThan(0, count($created));
    }

    function test_valid_email_registration() {

        // Ensure plugin activation
        $this->plugin->wpunewsletter_activate();

        // Test email registration
        $insValid = $this->plugin->register_mail('test@yopmail.com');
        $this->assertEquals(1, $insValid);

        // Test email in database
        $isSubscribed = $this->plugin->mail_is_subscribed('test@yopmail.com');
        $this->assertTrue($isSubscribed);
    }

    function test_email_extra_field() {

        $extra = array(
            'test' => 'test',
            'test2' => 'Internet',
        );
        $stored_extra = json_encode($extra);

        // Ensure plugin activation
        $this->plugin->wpunewsletter_activate();

        // Test email registration with an extra field
        $insValid = $this->plugin->register_mail('test2@yopmail.com', false, false, $extra);
        $this->assertEquals(1, $insValid);

        // Test correct insertion
        $mailInfos = $this->plugin->get_mail_infos('test2@yopmail.com');
        $this->assertEquals($stored_extra, $mailInfos->extra);
    }

    function test_extra_field_format() {
        $base_test_values = array(
            'az' => array() ,
            'bz' => array(
                'type' => 'checkbox'
            ) ,
            'cz' => array(
                'type' => 'email'
            ) ,
            'dz' => array(
                'type' => 'url'
            ) ,
            'ez' => array(
                'char_limit' => 10
            ) ,
        );

        // Load extra fields
        $this->plugin->load_extra_fields($base_test_values);

        // Test valid values
        $valid_values = array(
            'az' => 'test',
            'bz' => '1',
            'cz' => 'test@yopmail.com',
            'dz' => 'https://github.com',
            'ez' => 'loremipsum',
        );
        $test_values = array();
        foreach ($valid_values as $id => $val) {
            $test_values['wpunewsletter_extra__' . $id] = $val;
        }
        $values = $this->plugin->get_extras_from($test_values);
        $this->assertEquals($valid_values, $values);

        // Test invalid values
        $values = $this->plugin->get_extras_from(array(
            'wpunewsletter_extra__az' => '<hr />',
            'wpunewsletter_extra__cz' => 'tesyopmailcom',
            'wpunewsletter_extra__dz' => 'lorem',
            'wpunewsletter_extra__ez' => 'loremipsumaz',
        ));

        // HTML must be encoded
        $this->assertEquals('&lt;hr /&gt;', $values['az']);

        // Empty checkboxes get a value of 0
        $this->assertEquals(0, $values['bz']);

        // Invalid email must be emptied
        $this->assertEquals('', $values['cz']);

        // Invalid url must be emptied
        $this->assertEquals('', $values['dz']);

        // Text over the char limit must be truncated
        $this->assertEquals('loremipsum', $values['ez']);

        // Test required values
        $base_test_values['bz']['required'] = true;
        $this->plugin->load_extra_fields($base_test_values);
        $values = $this->plugin->get_extras_from(array(
            'wpunewsletter_extra__az' => 'az'
        ));

        // Incomplete values return fale
        $this->assertEquals(false, $values);
    }

    function test_invalid_email_registration() {

        // Ensure plugin activation
        $this->plugin->wpunewsletter_activate();

        // Test invalid email registration
        $insInvalid = $this->plugin->register_mail('test_yopmail.com');
        $this->assertNull($insInvalid);

        // Test invalid email in database
        $isSubscribed = $this->plugin->mail_is_subscribed('test_yopmail.com');
        $this->assertNull($isSubscribed);
    }

    function test_import_addresses() {

        // Mix invalid & valid addresses
        $addresses = "kevin@yopmail.com\ninvalid.com\naz\ntest@yopmail.com";

        // Ensure plugin activation
        $this->plugin->wpunewsletter_activate();

        $nb_addresses = $this->plugin->import_addresses_from_text($addresses);
        $this->assertEquals(2, $nb_addresses);
    }

    function test_admin_messages() {

        // Ensure plugin activation
        $this->plugin->wpunewsletter_activate();

        $this->plugin->admin_messages[] = 'az';

        // Test if messages are correctly displayed
        $this->assertEquals('<p>az</p>', $this->plugin->display_messages());

        // Test if messages are emptied after display
        $this->assertEquals(array() , $this->plugin->admin_messages);
    }
}

