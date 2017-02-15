<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if (!class_exists("ChatBroUtils")) {
  class ChatBroUtils {
        public static function load_my_textdomain() {
            load_plugin_textdomain('chatbro', false, dirname(plugin_basename(__FILE__)) . '/languages');
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
            if (!preg_match('/^.+:\/\/([^\/\:]+)/', $url, $m))
                return '';

            return $m[1];
        }

        public static function get_option($name) {
            $is_setting = array_key_exists($name, ChatBroPlugin::$options) && array_key_exists('default', ChatBroPlugin::$options[$name]);
            $option_desc = null;

            if ($is_setting)
              $option_desc = ChatBroPlugin::$options[$name];

            $default = ($is_setting && array_key_exists('default', $option_desc)) ? ChatBroPlugin::$options[$name]['default'] : null;

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

        public static function delete_option($name) {
            if (is_multisite() && is_plugin_active_for_network(plugin_basename(__FILE__))) {
                return delete_site_option($name);
            } else {
                return delete_option($name);
            }
        }

        public static function call_constructor($guid, &$messages) {
            $url = "https://www.chatbro.com/constructor/{$guid}";
            $response = wp_safe_remote_get($url);

            if (is_wp_error($response)) {
                $messages['fatal'] = __('Failed to call chat constructor', 'chatbro') . ": " . $response->get_error_message();
                return false;
            }

            return true;
        }

        public static function sanitize_guid($guid, &$messages) {
            $guid = trim(strtolower($guid));

            if (!preg_match('/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/', $guid)) {
                $messages['fields'][ChatBroPlugin::guid_setting] = array(
                    "message" => __("Invalid chat secret key", 'chatbro'),
                    "type" => "error"
                );
                return self::get_option(ChatBroPlugin::guid_setting);
            }

            if (!self::call_constructor($guid, $messages))
                return self::get_option(ChatBroPlugin::guid_setting);

            return $guid;
        }

        public static function sanitize_display($val, &$messages) {
            if (!in_array($val, array_keys($options['display_setting']['options']))) {
                $messages['fields'][ChatBroPlugin::display_setting] = array(
                    "message" => __("Invalid show popup chat option value", 'chatbro'),
                    "type" => "error"
                );
                return ChatBroPlugin::get_option(ChatBroPlugin::display_setting);
            }

            return $val;
        }

        public static function sanitize_checkbox($val, &$messages) {
            return ($val == "on");
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

        public static function get_avatar_url() {
          $user_id = get_current_user_id();
          $site_user_avatar_url = "";

          preg_match("/src=['\"]([^'\"]+)['\"]/i", get_avatar($user_id), $avatar_path);

          if(count($avatar_path)!=0)
            $site_user_avatar_url = $avatar_path[1];

          if($site_user_avatar_url == "")
              $site_user_avatar_url = get_avatar_url($user_id);

          return $site_user_avatar_url;
        }

        public static function get_profile_url()
        {
            $user = wp_get_current_user();
            $profile_path = self::get_option(ChatBroPlugin::user_profile_path_setting);
            $profile_url = '';

            if ($profile_path) {
                $profile_url = get_home_url() . ($profile_path[0] == '/' ? '' : '/') . $profile_path;
                $profile_url = str_ireplace('{$username}', $user->user_login, $profile_url);
                $profile_url = str_ireplace('{$userid}', $user->ID, $profile_url);
            }

            return $profile_url;
        }

        public static function generate_chat_code($guid, $container_id = null, $static = false) {
            $hash = md5($guid);
            $user = wp_get_current_user();
            $siteurl = self::get_option('siteurl');
            $site_domain = self::get_site_domain();
            $site_user_avatar_url = self::get_avatar_url();
            $profile_url = self::get_profile_url();

            $permissions = array();

            if (current_user_can(ChatBroPlugin::cap_delete))
                array_push($permissions, 'delete');

            if (current_user_can(ChatBroPlugin::cap_ban))
                array_push($permissions, 'ban');

            $params = "encodedChatGuid: '{$hash}', siteDomain: '{$site_domain}'";
            $sig_source = "";

            if (is_user_logged_in()) {
                $sig_source = $site_domain . $user->ID . $user->display_name . $site_user_avatar_url . $profile_url . implode('', $permissions);
                $params .= ", siteUserFullName: '{$user->display_name}', siteUserExternalId: '{$user->ID}'";

                if ($site_user_avatar_url != "")
                    $params .= ", siteUserAvatarUrl: '{$site_user_avatar_url}'";

                if ($profile_url != '')
                    $params .= ", siteUserProfileUrl: '{$profile_url}'";
            }
            else
                $sig_source = $site_domain;

            $signature = md5($sig_source . $guid);

            if ($container_id)
                $params .= ", containerDivId: '{$container_id}'";

            if ($static)
                $params .= ", isStatic: true";

            $params .= ", signature: '{$signature}'";
            $params .= ", wpPluginVersion: '" . ChatBroPlugin::version . "'";

            if (!empty($permissions))
                $params .= ", permissions: ['" . implode("','", $permissions) . "']";

            ob_start();

            ?>
            <script id="chatBroEmbedCode">
            /* Chatbro Widget Embed Code Start */
            function ChatbroLoader(chats,async) {async=async!==false;var params={embedChatsParameters:chats instanceof Array?chats:[chats],needLoadCode:typeof Chatbro==='undefined'};var xhr=new XMLHttpRequest();xhr.withCredentials = true;xhr.onload=function(){eval(xhr.responseText)};xhr.onerror=function(){console.error('Chatbro loading error')};xhr.open('POST','//www.chatbro.com/embed_chats/',async);xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');xhr.send('parameters='+encodeURIComponent(JSON.stringify(params)))}
            /* Chatbro Widget Embed Code End */
            if (typeof chatBroHistoryPage === 'undefined' || !chatBroHistoryPage)
                ChatbroLoader({<?php echo $params; ?>});
            </script>
            <?php

            $code = ob_get_contents();
            ob_end_clean();

            return $code;
        }

        public static function user_can_view($display_to_guests) {
            $logged_in = is_user_logged_in();
            $can_view = $logged_in ? current_user_can(ChatBroPlugin::cap_view) : false;

            if ((!$display_to_guests && !$logged_in) || ($logged_in && !$can_view))
                return false;

            if (!$display_to_guests && !$logged_in)
                // Don't show the chat to unregistered users
                return false;

            return true;
        }
    }
}
?>
