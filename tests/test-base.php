<?php
class BaseTest extends WP_UnitTestCase {

    public $plugin;

    function setUp() {
        parent::setUp();
        $this->plugin = new WPUNewsletter;
    }

    function test_init_plugin() {

        // Test plugin init
        do_action('init');
        $this->assertEquals(10, has_action('init', array(
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
}

