<?php
/**
 * @package ChatBro
 * @version 1.1.10
 */
/*
Plugin Name: ChatBro
Plugin URI: http://chatbro.com
Description: Live group chat for your community with social networks integration. Chat conversation is being syncronized with popular messengers. Love ChatBro? Spread the word! <a href="https://wordpress.org/support/view/plugin-reviews/chatbro">Click here to review the plugin!</a>.
Version: 1.1.10
Author: ChatBro
Author URI: http://chatbro.com
License: GPL3
Text Domain: chatbro-plugin
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
define('CHATBRO_PLUGIN_VERSION', '1.1.10', true);

if (!class_exists("ChatBroPlugin")) {
    __('Chat secret key', 'chatbro-plugin');
    __('Display chat to guests', 'chatbro-plugin');
    __('Show popup chat', 'chatbro-plugin');
    __('Everywhere', 'chatbro-plugin');
    __('Front page only', 'chatbro-plugin');
    __('Everywhere except those listed', 'chatbro-plugin');
    __('Only the listed pages', 'chatbro-plugin');
    __('Disable', 'chatbro-plugin');
    __("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are /2012/10/my-post for a single post and /2012/* for a group of posts. The path should always start with a forward slash(/).", 'chatbro-plugin');
    __('User profile path', 'chatbro-plugin');

    class InputType {
        const checkbox = 'checkbox';
        const text = 'text';
        const select = 'select';
        const textarea = 'textarea';
    };

    class ChatBroPlugin {
        const version = CHATBRO_PLUGIN_VERSION;
        const page = "chatbro_plugin";
        const settings = "chatbro_plugin_settings";
        const cap_delete = "chatbro_delete_message";
        const cap_ban = "chatbro_ban_user";

        const guid_setting = "chatbro_chat_guid";
        const display_to_guests_setting = "chatbro_chat_display_to_guests";
        const display_setting = "chatbro_chat_display";
        const selected_pages_setting = 'chatbro_chat_selected_pages';
        const user_profile_path_setting = 'chatbro_chat_user_profile_url';
        const old_options = 'chatbro_options';
        const default_profile_path = '/authors/{$username}';

        public static $options = array(
            ChatBroPlugin::guid_setting => array(
                'id' => ChatBroPlugin::guid_setting,
                'type' => InputType::text,
                'label' => 'Chat secret key',
                'sanitize_callback' => array('ChatBroPlugin', 'sanitize_guid')
            ),

            ChatBroPlugin::display_to_guests_setting => array(
                'id' => ChatBroPlugin::display_to_guests_setting,
                'type' => InputType::checkbox,
                'label' => 'Display chat to guests',
                'sanitize_callback' => array('ChatBroPlugin', 'sanitize_checkbox')
            ),

            ChatBroPlugin::display_setting => array(
                'id' => ChatBroPlugin::display_setting,
                'type' => InputType::select,
                'label' => 'Show popup chat',
                'options' => array(
                    'everywhere' =>    'Everywhere',
                    'frontpage_only' => 'Front page only',
                    'except_listed' => 'Everywhere except those listed',
                    'only_listed' =>   'Only the listed pages',
                    'disable' =>       'Disable'
                )
            ),

            ChatBroPlugin::selected_pages_setting => array(
                'id' => ChatBroPlugin::selected_pages_setting,
                'type' => InputType::textarea,
                'label' => "Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are /2012/10/my-post for a single post and /2012/* for a group of posts. The path should always start with a forward slash(/)."
            ),

            ChatBroPlugin::user_profile_path_setting => array(
                'id' => ChatBroPlugin::user_profile_path_setting,
                'type' => InputType::text,
                'label' => 'User profile path'
            )
        );

        private function __construct() {
            add_action('admin_init', array(&$this, 'init_settings'));
            add_action('admin_menu', array(&$this, 'add_menu_option'));
            add_action('wp_footer', array(&$this, 'chat'));

            $adm = get_role('administrator');

            if (!$adm->has_cap(self::cap_delete))
                $adm->add_cap(self::cap_delete);

            if (!$adm->has_cap(self::cap_ban))
                $adm->add_cap(self::cap_ban);
        }

        private static $instance;
        public static function get_instance() {
            if (self::$instance == null)
                self::$instance = new ChatBroPlugin();

            return self::$instance;
        }

        public static function on_activation() {
            $guid = self::get_option(self::guid_setting);

            if (!$guid)
                $guid = self::get_instance()->set_default_settings();
        }

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
            $url = ChatBroPlugin::get_option('siteurl');
            if (!preg_match('/^.+:\/\/([^\/]+)/', $url, $m))
                return '';

            return $m[1];
        }

        public static function get_option($name) {
            if (is_multisite() && is_plugin_active_for_network(plugin_basename(__FILE__))) {
                return get_site_option($name);
            } else {
                return get_option($name);
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

        public static function call_constructor($guid) {
            $response = wp_safe_remote_get("http://www.chatbro.com/constructor/{$guid}");

            if (is_wp_error($response)) {
                add_settings_error(ChatBroPlugin::guid_setting, 'constructor-failed', __('Failed to call chat constructor', 'chatbro-plugin') . " " . $response->get_error_message(), 'error');
                return false;
            }

            return true;
        }

        public static function sanitize_guid($guid) {
            $guid = trim(strtolower($guid));

            if (!preg_match('/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/', $guid)) {
                add_settings_error(self::guid_setting, "invalid-guid", __("Invalid chat secret key", 'chatbro-plugin'), "error");
                return self::get_option(self::guid_setting);
            }

            if (!self::call_constructor($guid))
                return self::get_option(self::guid_setting);

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


        static function check_path()
        {
            $page_match = FALSE;
            $selected_pages = trim(ChatBroPlugin::get_option(ChatBroPlugin::selected_pages_setting));
            $display = ChatBroPlugin::get_option(ChatBroPlugin::display_setting);

            if ($selected_pages != '') {
                if (function_exists('mb_strtolower')) {
                    $pages = mb_strtolower($selected_pages);
                    $path = mb_strtolower($_SERVER['REQUEST_URI']);
                } else {
                    $pages = strtolower($selected_pages);
                    $path = strtolower($_SERVER['REQUEST_URI']);
                }

                $page_match = ChatBroPlugin::match_path($path, $pages);

                if($display == 'except_listed')
                    $page_match = !$page_match;
            }

            return $page_match;
        }

        function convert_from_old_page($old) {
            $chatPath = $old['chatPath'];
            $showGuests = $old['showGuests'];
            ?>
            <p><div class="error"><?php echo __('To manage chat settings in the new version of the plugin, you should submit a chat secret key. Please, log in to your account at <a href="https://www.chatbro.com/account">chatbro.com</a> (use login button below), copy the chat secret key and insert it into the respective field.', 'chatbro-plugin'); ?></div></p>
            <form method="post" action="options.php">
                <?php
                settings_fields(ChatBroPlugin::settings);

                if ($showGuests)
                    echo '<input type="hidden" id="' . ChatBroPlugin::display_to_guests_setting . '" name="' . ChatBroPlugin::display_to_guests_setting . '" value="on">';
                ?>
                <input type="hidden" id="<?php echo ChatBroPlugin::display_setting ?>" name="<?php echo ChatBroPlugin::display_setting ?>" value="everywhere">
                <input type="hidden" id="<?php echo ChatBroPlugin::user_profile_path_setting ?>" name="<?php echo ChatBroPlugin::user_profile_path_setting ?>" value="<?php echo ChatBroPlugin::default_profile_path; ?>">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo __(ChatBroPlugin::$options[ChatBroPlugin::guid_setting]['label'], 'chatbro-plugin'); ?></th>
                        <td><input type="text" class="regular-text" id="<?php echo ChatBroPlugin::guid_setting; ?>" name="<?php echo ChatBroPlugin::guid_setting; ?>">
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php _e('Save secret key', 'chatbro-plugin') ?>" />
                </p>
            </form>
            <iframe id="convert" src="<?php echo "https://www.chatbro.com/get_secretkey_by_path?chatPath={$chatPath}"; ?>" style="width: 100%;"></iframe>
            <?php
        }

        function constructor_page() {
            $old = ChatBroPlugin::get_option(ChatBroPlugin::old_options);
            if (ChatBroPlugin::get_option(ChatBroPlugin::guid_setting) == false && $old != false && $old['chatPath']) {
                $this->convert_from_old_page($old);
                return;
            }

            wp_enqueue_script( 'chatbro-admin', plugin_dir_url( __FILE__ ) . 'js/chatbro.admin.js', array('jquery'));

            $guid = ChatBroPlugin::get_option(ChatBroPlugin::guid_setting);
            $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'constructor';

            ?>
            <div class="wrap">
                <h1><?php __('Plugin Settings', 'chatbro-plugin'); ?></h1>
                <h2 class="nav-tab-wrapper">
                    <a href="?page=chatbro_settings&tab=constructor"
                       class="nav-tab <?php echo $active_tab == 'constructor' ? 'nav-tab-active' : ''; ?>"><?php echo __("Chat Constructor", 'chatbro-plugin'); ?></a>
                    <a href="?page=chatbro_settings&tab=plugin_settings"
                       class="nav-tab <?php echo $active_tab == 'plugin_settings' ? 'nav-tab-active' : ''; ?>"><?php echo __("Plugin Settings", 'chatbro-plugin'); ?></a>
                </h2>


                <?php
                if ($active_tab == "plugin_settings") {
                    settings_errors();
                    ?>
                    <form method="post" action="options.php">
                        <?php
                            settings_fields(ChatBroPlugin::settings);
                            do_settings_sections(ChatBroPlugin::page);
                            // do_settings_fields(ChatBroPlugin::page, "chbro_plugin_settings");
                        ?>
                        <p class="submit">
                            <input type="submit" class="button-primary" value="<?php _e('Save Changes', 'chatbro-plugin') ?>" />
                        </p>
                    </form>
                    <?php
                }
                else {
                    ?>
                    <!--<iframe id="constructor" src="https://www.chatbro.com/constructor/<?php echo $guid; ?>" style="width: 100%; height: 85vh"></iframe>-->
                    <iframe name="chatbro-constructor" style="width: 100%; height: 85vh"></iframe>
                    <form id="load-constructor" target="chatbro-constructor" action="https://www.chatbro.com/constructor/<?php echo $guid; ?>/" method="POST">
                        <input type="hidden" name="guid" value="<?php echo $guid; ?>">
                        <input type="hidden" name="avatarUrl" value="<?php echo self::get_avatar_url(); ?>">
                        <input type="hidden" name="userFullName" value="<?php echo wp_get_current_user()->display_name; ?>">
                        <input type="hidden" name="userProfileUrl" value="<?php echo self::get_profile_url(); ?>">
                    </form>
                    <script>
                        jQuery("#load-constructor").submit();
                    </script>

                    <?php
                }
                ?>
            </div>
            <?php
        }

        function init_settings() {
            add_settings_section("chbro_plugin_settings", "", "", ChatBroPlugin::page);
            foreach(ChatBroPlugin::$options as $name => $args) {
                register_setting(ChatBroPlugin::settings, $name, array_key_exists('sanitize_callback', $args) ? $args['sanitize_callback'] : null);
                add_settings_field($name, __($args['label'], 'chatbro-plugin'), array(&$this, "render_field"), ChatBroPlugin::page, "chbro_plugin_settings", $args);
            }

            $old_options = ChatBroPlugin::get_option(ChatBroPlugin::old_options);
            if (ChatBroPlugin::get_option(ChatBroPlugin::guid_setting) == false && ($old_options == false || $old_options['chatPath'] == 'tg/208397015/Ask your own question'))
                $this->set_default_settings();
        }

        public static function cleanup_settings() {
            foreach (array_keys(ChatBroPlugin::$options) as $name)
                if (ChatBroPlugin::get_option($name) === false)
                    continue;

                delete_option($name);

            $adm = get_role('administrator');
            $adm->remove_cap(self::cap_delete);
            $adm->remove_cap(self::cap_ban);
        }

        function set_default_settings() {
            $guid = $this->gen_uuid();

            if (!ChatBroPlugin::add_option(ChatBroPlugin::guid_setting, $guid))
                ChatBroPlugin::update_option(ChatBroPlugin::guid_setting, $guid);

            if (!ChatBroPlugin::add_option(ChatBroPlugin::display_to_guests_setting, true))
                ChatBroPlugin::update_option(ChatBroPlugin::display_to_guests_setting, true);

            if (!ChatBroPlugin::add_option(ChatBroPlugin::user_profile_path_setting, ChatBroPlugin::default_profile_path))
                ChatBroPlugin::update_option(ChatBroPlugin::user_profile_path_setting, ChatBroPlugin::default_profile_path);

            if (!ChatBroPlugin::add_option(ChatBroPlugin::display_setting, 'everywhere'))
                ChatBroPlugin::update_option(ChatBroPlugin::display_setting, 'everywhere');

            return $guid;
        }

        function render_field($args) {
            $tag = $args['type'] == InputType::select || $args['type'] == InputType::textarea ? $args['type'] : 'input';
            $class = $args['type'] == 'text' ? 'class="regular-text" ' : '';

            $value = $this->get_option($args['id']);
            $valueAttr = $args['type'] == InputType::text ? "value=\"{$value}\" " : "";
            $checked = $args['type'] == InputType::checkbox && $value ? 'checked="checked"' : '';
            $textarea_attrs = $args['type'] == InputType::textarea ? 'cols="80" rows="6"' : '';

            echo "<{$tag} id=\"{$args['id']}\" name=\"{$args['id']}\" {$class} type=\"{$args['type']}\" {$textarea_attrs} {$valueAttr} {$checked}>";

            switch($args['type']) {
            case InputType::select:
                if (!$value) {
                    $t = array_keys($args['options']);
                    $value = $t[0];
                }

                foreach($args['options'] as $val => $desc) {
                    $desc = __($desc, 'chatbro-plugin');
                    $selected = $val == $value ? 'selected="selected"' : '';
                    echo "<option {$selected} name=\"{$args[id]}\" value=\"{$val}\">{$desc}</option>";
                }

                echo "</select>";
                break;

            case InputType::textarea:
                echo $value;
                echo "</textarea>";
                break;
            }
        }

        function add_menu_option() {
            add_menu_page("ChatBro", "ChatBro", "manage_options", "chatbro_settings", array(&$this, 'constructor_page'), plugins_url()."/chatbro/favicon_small.png");
        }

        function chat_old($o) {
            $current_user = wp_get_current_user();
            $display_name = $current_user->display_name;
            $userid = $current_user->ID;
            $siteUrl = get_option('siteurl');

            $siteUserAvatarUrl = "";
            preg_match("/src='(.*)' alt/i", get_avatar($userid, 120), $avatarPath);
                if(count($avatarPath)!=0)
                    $siteUserAvatarUrl = $avatarPath[1];
            if($siteUserAvatarUrl == "")
                $siteUserAvatarUrl =  get_avatar_url($userid);

            $username = $current_user->user_login;

            $param = array(
                'chatPath' =>                 $o['chatPath'],
                'containerDivId' =>           $o['containerDivId'],
                'chatLanguage'=>              $o['chatLanguage'],
                'chatState'=>                 $o['chatState'],
                'chatTop'=>                   $o['chatTop'],
                'chatLeft'=>                  $o['chatLeft'],
                'chatWidth'=>                 $o['chatWidth'],
                'chatHeight'=>                $o['chatHeight'],
                'siteDomain'=>                $o['siteDomain'],
                'chatHeaderTextColor'=>       $o['chatHeaderTextColor'],
                'chatHeaderBackgroundColor'=> $o['chatHeaderBackgroundColor'],
                'chatBodyBackgroundColor'=>   $o['chatBodyBackgroundColor'],
                'chatBodyTextColor'=>         $o['chatBodyTextColor'],
                'chatInputBackgroundColor'=>  $o['chatInputBackgroundColor'],
                'chatInputTextColor'=>        $o['chatInputTextColor'],
                'allowSendMessages'=>         isset($o['allowSendMessages']),
                'allowMoveChat'=>             isset($o['allowMoveChat']),
                'allowUploadMessages'=>       isset($o['allowUploadMessages']),
                'allowResizeChat'=>           isset($o['allowResizeChat']),
                'showChatHeader'=>            isset($o['showChatHeader']),
                'allowMinimizeChat'=>         isset($o['allowMinimizeChat']),
                'showChatMenu'=>              isset($o['showChatMenu']),
                'showChatParticipants'=>      isset($o['showChatParticipants']),
                'showGuests'=>                isset($o['showGuests']),
                );

            $json_param = json_encode($param);
            $signature ="";
            if( $o['siteDomain'] && isset($o['secretKey']) )
                $signature  = md5($o['siteDomain'].$userid.$display_name.$siteUserAvatarUrl.$o['secretKey']);
            $chatBroCode ='<script>
            /* Chatbro Widget Embed Code Start*/
            function ChatbroLoader(chats, async) {
                async = async || true;
                var params = {
                    embedChatsParameters: chats instanceof Array ? chats : [chats],
                    needLoadCode: typeof Chatbro === "undefined"
                };
                var xhr = new XMLHttpRequest();
                xhr.onload = function () {
                    eval(xhr.responseText);
                };
                xhr.onerror = function () {
                    console.error("Chatbro loading error");
                };
                xhr.open("POST", "http://www.chatbro.com/embed_chats", async);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.send("parameters=" + encodeURIComponent(JSON.stringify(params)));
            }
            /* Chatbro Widget Embed Code End*/
            var param={};
            var json_param= '.$json_param.'
            for (var p in json_param) {
                if (json_param[p]!=null)
                    param[p]=json_param[p];
            }
            if("'.is_user_logged_in().'") {
                param.siteUserFullName   = "'.$display_name.'";
                param.siteUserExternalId = "'.$userid.'";
                if("'.$siteUserAvatarUrl.'".indexOf("wp_user_avatar"==-1))
                    param.siteUserAvatarUrl  = "'.$siteUserAvatarUrl.'";
                if( "'.$signature.'" )
                    param.signature = "'.$signature.'";
            }
                ChatbroLoader(param);
            </script>';
                if(isset($o['showGuests']) || is_user_logged_in()) {
                    $systemMessage='';
                if( isset($o['showSystemMessage']) && !is_user_logged_in()) {
                        $registerMessage = __('Only for registered members! ', 'chatbro-plugin');
                    if( $o['registerLink'] ) {
                            $registerTitle =__('Register', 'chatbro-plugin');
                            $registerMessage.='<link><a href='.addslashes($o['registerLink']).'>'.$registerTitle.'</a></link>';
                        }
                        $systemMessage = '<script>
                            document.addEventListener("chatLoaded", function () {
                            document.addEventListener("chatInputClick", function (event) {
                                var chat = event.chat;
                                chat.lockSendMessage();
                                chat.showSystemMessage("'.$registerMessage.'");
                            });
                        });
                    </script>';
                }
                echo $chatBroCode.$systemMessage;
            }

        }

        function chat() {
            $guid = ChatBroPlugin::get_option(ChatBroPlugin::guid_setting);

            if (!$guid) {
                $opts = ChatBroPlugin::get_option(ChatBroPlugin::old_options);

                if ($opts != false)
                    $this->chat_old($opts);

                return;
            }

            $hash = md5($guid);
            $user = wp_get_current_user();
            $siteurl = ChatBroPlugin::get_option('siteurl');
            $site_domain = ChatBroPlugin::get_site_domain();

            $permissions = array();

            if (current_user_can(self::cap_delete))
                array_push($permissions, 'delete');

            if (current_user_can(self::cap_ban))
                array_push($permissions, 'ban');

            $site_user_avatar_url = "";
            preg_match("/src='(.*)' alt/i", get_avatar($user->ID, 120), $avatar_path);
                if(count($avatar_path)!=0)
                    $site_user_avatar_url = $avatar_path[1];
            if($site_user_avatar_url == "")
                $site_user_avatar_url = get_avatar_url($user->ID);

            $site_user_avatar_url = strpos($site_user_avatar_url, 'wp_user_avatar') == FALSE ? $site_user_avatar_url : '';

            $profile_path = ChatBroPlugin::get_option(ChatBroPlugin::user_profile_path_setting);

            $profile_url = '';

            if ($profile_path)
                $profile_url = str_ireplace('{$username}', $user->user_login, $siteurl . $profile_path);

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

            $params .= ", signature: '{$signature}'";
            $params .= ", wpPluginVersion: '" . self::version . "'";

            if (!empty($permissions))
                $params .= ", permissions: ['" . implode("','", $permissions) . "']";

            $display_to_guests = ChatBroPlugin::get_option(ChatBroPlugin::display_to_guests_setting);

            if (!$display_to_guests && !is_user_logged_in())
                // Don't show the chat to unregistered users
                return;

            $where_to_display = ChatBroPlugin::get_option(ChatBroPlugin::display_setting);

            switch($where_to_display) {
                case '':
                case 'everywhere':
                    break;

                case 'frontpage_only':
                    if (!is_front_page())
                        return;
                    break;

                case 'except_listed':
                case 'only_listed':
                    if (!ChatBroPlugin::check_path())
                        return;
                    break;

                default:
                    return;
            }

            $hash = md5($guid);
            ?>
            <script id="chatBroEmbedCode">
            /* Chatbro Widget Embed Code Start */
            function ChatbroLoader(chats,async) {async=async!==false;var params={embedChatsParameters:chats instanceof Array?chats:[chats],needLoadCode:typeof Chatbro==='undefined'};var xhr=new XMLHttpRequest();xhr.onload=function(){eval(xhr.responseText)};xhr.onerror=function(){console.error('Chatbro loading error')};xhr.open('POST','//www.chatbro.com/embed_chats/',async);xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');xhr.send('parameters='+encodeURIComponent(JSON.stringify(params)))}
            /* Chatbro Widget Embed Code End */
            ChatbroLoader({<?php echo $params; ?>});
            </script>
            <?php
        }

        public static function get_avatar_url()
        {
            $user = wp_get_current_user();
            $site_user_avatar_url = "";
            preg_match("/src='(.*)' alt/i", get_avatar($user->ID, 120), $avatar_path);

            if(count($avatar_path)!=0)
                $site_user_avatar_url = $avatar_path[1];

            if($site_user_avatar_url == "")
                $site_user_avatar_url = get_avatar_url($user->ID);

            $site_user_avatar_url = strpos($site_user_avatar_url, 'wp_user_avatar') == FALSE ? $site_user_avatar_url : '';

            return $site_user_avatar_url;
        }

        public static function get_profile_url()
        {
            $user = wp_get_current_user();
            $profile_path = self::get_option(self::user_profile_path_setting);
            $profile_url = '';

            if ($profile_path) {
                $profile_url = get_home_url() . ($profile_path[0] == '/' ? '' : '/') . $profile_path;
                $profile_url = str_ireplace('{$username}', $user->user_login, $profile_url);
                $profile_url = str_ireplace('{$userid}', $user->ID, $profile_url);
            }

            return $profile_url;
        }
    }
}


//----------------------TEMPLATE------------------------//
if (!class_exists("ChatBroPluginTemplater")) {
    class ChatBroPluginTemplater {
        private static $instance;
        protected $templates;
        public static function get_instance() {
            if( null == self::$instance ) {
                self::$instance = new ChatBroPluginTemplater();
            }
            return self::$instance;
        }
        private function __construct() {
            $this->templates = array();
            add_filter(
                'page_attributes_dropdown_pages_args',
                array( $this, 'register_project_templates' )
                );
            add_filter(
                'wp_insert_post_data',
                array( $this, 'register_project_templates' )
                );
            add_filter(
                'template_include',
                array( $this, 'view_project_template')
                );
            $this->templates = array(
                'chatbro_history_template.php' => 'Chat history',
                );
        }
        public function register_project_templates( $atts ) {
            $cache_key = 'page_templates-' . md5( get_theme_root() . '/' . get_stylesheet() );

            $templates = wp_get_theme()->get_page_templates();
            if ( empty( $templates ) ) {
                $templates = array();
            }
            wp_cache_delete( $cache_key , 'themes');
            $templates = array_merge( $templates, $this->templates );
            wp_cache_add( $cache_key, $templates, 'themes', 1800 );
            return $atts;
        }
        public function view_project_template( $template ) {
            global $post;
            $file = '';
            if (isset($post->ID)) {
                if (!isset($this->templates[get_post_meta($post->ID, '_wp_page_template', true)] ) ) {
                    return $template;
                }

                $file = plugin_dir_path(__FILE__). get_post_meta(
                    $post->ID, '_wp_page_template', true
                    );
            }
            if( file_exists( $file ) ) {
                return $file;
            }
            else { echo $file; }
            return $template;
        }
    }
}


add_action('plugins_loaded', array(ChatBroPlugin::get_instance(), 'load_my_textdomain'));
add_action('plugins_loaded', array('ChatBroPluginTemplater', 'get_instance'));

register_uninstall_hook(__FILE__, array('ChatBroPlugin', 'cleanup_settings'));
register_activation_hook(__FILE__, array('ChatBroPlugin', 'on_activation'));
