<?php
/**
 * @package ChatBro
 * @version 2.2.6
 */
/*
Plugin Name: ChatBro
Plugin URI: http://chatbro.com
Description: Live group chat for your community with social networks integration. Chat conversation is being syncronized with popular messengers. Love ChatBro? Spread the word! <a href="https://wordpress.org/support/view/plugin-reviews/chatbro">Click here to review the plugin!</a>.
Version: 2.2.6
Author: ChatBro
Author URI: http://chatbro.com
License: GPL3
Text Domain: chatbro
Domain Path: /languages/
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
define('CHATBRO_PLUGIN_VERSION', '2.2.6', true);

require_once('util.php');
require_once('deprecated.php');
require_once('plugin.php');
require_once('shortcode.php');
require_once('templater.php');
require_once('widget.php');

ChatBroPlugin::get_instance();
ChatBroShortCode::get_instance();

add_action('plugins_loaded', array('ChatBroUtils', 'load_my_textdomain'));
add_action('plugins_loaded', array( 'ChatBroPluginTemplater', 'get_instance'));
add_action('widgets_init', array('ChatBroWidget', 'register'));
register_uninstall_hook(__FILE__, array('ChatBroPlugin', 'clenup_settings'));
register_activation_hook(__FILE__, array('ChatBroPlugin', 'on_activation'));
