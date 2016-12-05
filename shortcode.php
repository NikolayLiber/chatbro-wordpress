<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if (!class_exists("ChatBroShortCode")) {
    class ChatBroShortCode {
        function __construct() {
            add_shortcode('chatbro', array(&$this, 'render'));
        }

        public static function render($atts, $content = null) {
            $guid = strtolower(ChatBroPlugin::get_option(ChatBroPlugin::guid_setting));
            $encoded_guid = md5($guid);
            $container_id = "chatbro-{$encoded_guid}-" . rand(0, 99999);

            $a = shortcode_atts(array(
                'id' => $encoded_guid,
                'static' => true,
                'container_id' => $container_id
            ), $atts);

            return "<h1>ChatBro</h1>";
        }
    }
}

?>
