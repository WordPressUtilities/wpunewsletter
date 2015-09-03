<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit();

require_once dirname( __FILE__ ).'/wpunewsletter.php';

$WPUNewsletter->uninstall();
