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
        $table_name = "{$wpdb->prefix}wpunewsletter_subscribers";

        // Try table creation
        $created = $this->plugin->wpunewsletter_activate();
        $this->assertStringStartsWith('Created table', current($created));
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

    function test_invalid_email_registration() {

        // Ensure plugin activation
        $this->plugin->wpunewsletter_activate();

        // Test invalid email registration
        $insInvalid = $this->plugin->register_mail('test_yopmail.com');
        $this->assertNull($insInvalid);
    }
}

