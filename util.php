<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if (!class_exists("ChatBroUtils")) {
	class ChatBroUtils {
        public static function load_my_textdomain() {
            $mo_file_path = dirname(__FILE__) . '/languages/chatbro-plugin-'. get_locale() . '.mo';
            load_textdomain('chatbro-plugin', $mo_file_path);
        }

        public static function gen_uuid() {
            return strtolower(sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                // 32 bits for "time_low"
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

                // 16 bits for "time_mid"
                mt_rand( 0, 0xffff ),

                // 16 bits for "time_hi_and_version",
                // four most significant bits holds version number 4
                mt_rand( 0, 0x0fff ) | 0x4000,

                // 16 bits, 8 bits for "clk_seq_hi_res",
                // 8 bits for "clk_seq_low",
                // two most significant bits holds zero and one for variant DCE1.1
                mt_rand( 0, 0x3fff ) | 0x8000,

                // 48 bits for "node"
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
            ));
        }

        public static function get_site_domain() {
            $url = self::get_option('siteurl');
            if (!preg_match('/^.+:\/\/([^\/]+)/', $url, $m))
                return '';

            return $m[1];
        }

        public static function get_option($name) {
            $default = array_key_exists($name, ChatBroPlugin::$options) && array_key_exists('default', ChatBroPlugin::$options[$name]) ? ChatBroPlugin::$options[$name]['default'] : null;

            if (is_multisite() && is_plugin_active_for_network(plugin_basename(__FILE__))) {
                return get_site_option($name, $default);
            } else {
                return get_option($name, $default);
            }
        }

        public static function add_option($name, $value = '', $v2 = '', $v3 = 'yes') {
            if (is_multisite() && is_plugin_active_for_network(plugin_basename(__FILE__))) {
                return add_site_option($name, $value, $v2, $v3);
            } else {
                return add_option($name, $value, $v2, $v3);
            }
        }

        public static function update_option($name, $value) {
            if (is_multisite() && is_plugin_active_for_network(plugin_basename(__FILE__))) {
                return update_site_option($name, $value);
            } else {
                return update_option($name, $value);
            }
        }

        public static function add_or_update_option($name, $value) {
        	if (!self::add_option($name, $value))
        		self::update_option($name, $value);
        }

        public static function call_constructor($guid) {
            $ch = curl_init("http://www.chatbro.com/constructor/{$guid}");
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            if (curl_exec($ch) === false) {
                add_settings_error(ChatBroPlugin::guid_setting, 'constructor-failed', __('Failed to call chat constructor', 'chatbro-plugin') . " " . curl_error($ch), 'error');
                return false;
            }

            return true;
        }

        public static function sanitize_guid($guid) {
            $guid = trim(strtolower($guid));

            if (!preg_match('/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/', $guid)) {
                add_settings_error(ChatBroPlugin::guid_setting, "invalid-guid", __("Invalid chat secret key", 'chatbro-plugin'), "error");
                return self::get_option(ChatBroPlugin::guid_setting);
            }

            if (!self::call_constructor($guid))
                return self::get_option(ChatBroPlugin::guid_setting);

            return $guid;
        }

        public static function sanitize_display($val) {
            if (!in_array($val, array_keys($options['display_setting']['options']))) {
                add_settings_error(ChatBroPlugin::display_setting, "invalid-display", __("Invalid show popup chat option value", 'chatbro-plugin'));
                return ChatBroPlugin::get_option(ChatBroPlugin::display_setting);
            }

            return $val;
        }

        public static function sanitize_checkbox($val) {
            return $val == "on";
        }

        static function match_path($path, $patterns)
        {
            $to_replace = array(
                '/(\r\n?|\n)/',
                '/\\\\\*/',
            );
            $replacements = array(
                '|',
                '.*',
            );
            $patterns_quoted = preg_quote($patterns, '/');
            $regexps = '/^(' . preg_replace($to_replace, $replacements, $patterns_quoted) . ')$/';
            return (bool)preg_match($regexps, $path);
        }


        public static function check_path()
        {
            $page_match = FALSE;
            $selected_pages = trim(self::get_option(ChatBroPlugin::selected_pages_setting));
            $display = self::get_option(ChatBroPlugin::display_setting);

            if ($selected_pages != '') {
                if (function_exists('mb_strtolower')) {
                    $pages = mb_strtolower($selected_pages);
                    $path = mb_strtolower($_SERVER['REQUEST_URI']);
                } else {
                    $pages = strtolower($selected_pages);
                    $path = strtolower($_SERVER['REQUEST_URI']);
                }

                $page_match = self::match_path($path, $pages);

                if($display == 'except_listed')
                    $page_match = !$page_match;
            }

            return $page_match;
        }
	}
}
?>
