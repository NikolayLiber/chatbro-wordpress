<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if (!class_exists("ChatBroShortCode")) {
    class ChatBroShortCode {
        private static $instance = null;

        private function __construct() {
            add_shortcode('chatbro', array(&$this, 'render'));
        }

        public static function get_instance() {
        	if (!self::$instance)
        		self::$instance = new ChatBroShortCode();

        	return self::$instance;
        }

        public static function render($atts, $content = null) {
            $a = shortcode_atts(array(
                'popup' => false,
            ), $atts);

            $guid = strtolower(ChatBroUtils::get_option(ChatBroPlugin::guid_setting));
            $encoded_guid = md5($guid);
            $container_id = !$a['popup'] ? "chatbro-{$encoded_guid}-" . rand(0, 99999) : null;
            $code = $container_id ? "<div id=\"{$container_id}\"></div>" : "";

            return $code . ChatBroPlugin::generate_chat_code($guid, $container_id, $a['popup']);
        }
    }
}

?>
