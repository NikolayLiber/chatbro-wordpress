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
                'static' => true,
                'registered_only' => false
            ), $atts);


            if (!ChatBroUtils::get_option(ChatBroPlugin::enable_shortcodes_setting))
                return "";

            // If "registered_only" attribute is explicitly set in shortcode then it will be used or global display_to_guests_setting will be used
            $registered_only = $atts && array_key_exists('registered_only', $atts) ? (strtolower($a['registered_only']) == 'true' || $a['registered_only'] == '1') : !ChatBroUtils::get_option(ChatBroPlugin::display_to_guests_setting);
            $static = strtolower($a['static']) == 'true' || $a['static'] == '1';
            $logged_in = is_user_logged_in();

            if ((!$logged_in && $registered_only) || ($logged_in && !current_user_can(ChatBroPlugin::cap_view)))
                return "";

            $guid = strtolower(ChatBroUtils::get_option(ChatBroPlugin::guid_setting));
            $encoded_guid = md5($guid);
            $container_id = $static ? "chatbro-{$encoded_guid}-" . rand(0, 99999) : null;
            $code = $container_id ? "<div id=\"{$container_id}\"></div>" : "";

            return $code . ChatBroUtils::generate_chat_code($guid, $container_id, $static);
        }
    }
}

?>
