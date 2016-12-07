<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

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
        const page = "chatbro_plugin";
        const settings = "chatbro_plugin_settings";

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
                'sanitize_callback' => array('ChatBroUtils', 'sanitize_guid')
            ),

            ChatBroPlugin::display_to_guests_setting => array(
                'id' => ChatBroPlugin::display_to_guests_setting,
                'type' => InputType::checkbox,
                'label' => 'Display chat to guests',
                'sanitize_callback' => array('ChatBroUtils', 'sanitize_checkbox')
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
        }

        private static $instance;
        public static function get_instance() {
            if (self::$instance == null)
                self::$instance = new ChatBroPlugin();

            return self::$instance;
        }

        public static function on_activation() {
            $guid = ChatBroUtils::get_option(self::guid_setting);

            if (!$guid)
                $guid = self::get_instance()->set_default_settings();

            ChatBroUtils::call_constructor($guid);
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
            $old = ChatBroUtils::get_option(self::old_options);
            if (ChatBroUtils::get_option(self::guid_setting) == false && $old != false && $old['chatPath']) {
                $this->convert_from_old_page($old);
                return;
            }

            wp_enqueue_script( 'chatbro-admin', plugin_dir_url( __FILE__ ) . 'js/chatbro.admin.js', array('jquery'));

            $guid = ChatBroUtils::get_option(self::guid_setting);
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
                            settings_fields(self::settings);
                            do_settings_sections(self::page);
                            // do_settings_fields(ChatBroPlugin::page, "chbro_plugin_settings");
                        ?>
                        <p class="submit">
                            <input type="submit" class="button-primary" value="<?php _e('Save Changes', 'chatbro-plugin'); ?>" />
                        </p>
                    </form>
                    <?php
                }
                else {
                    ?>
                    <iframe name="chatbro-constructor" style="width: 100%; height: 85vh"></iframe>
                    <form id="load-constructor" target="chatbro-constructor" action="https://www.chatbro.com/constructor/<?php echo $guid; ?>" method="GET">
                        <input type="hidden" name="guid" value="<?php echo $guid; ?>">
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
            add_settings_section("chbro_plugin_settings", "", "", self::page);
            foreach(self::$options as $name => $args) {
                register_setting(self::settings, $name, array_key_exists('sanitize_callback', $args) ? $args['sanitize_callback'] : null);
                add_settings_field($name, __($args['label'], 'chatbro-plugin'), array(&$this, "render_field"), self::page, "chbro_plugin_settings", $args);
            }

            $old_options = ChatBroUtils::get_option(self::old_options);
            if (ChatBroUtils::get_option(self::guid_setting) == false && ($old_options == false || $old_options['chatPath'] == 'tg/208397015/Ask your own question'))
                $this->set_default_settings();
        }

        public static function clenup_settings() {
            foreach (array_keys(self::$options) as $name)
                if (ChatBroUtils::get_option($name) === false)
                    continue;

                delete_option($name);
        }

        function set_default_settings() {
            $guid = ChatBroUtils::gen_uuid();

            ChatBroUtils::add_or_update_option(self::guid_setting, $guid);
            ChatBroUtils::add_or_update_option(self::display_to_guests_setting, true);
            ChatBroUtils::add_or_update_option(self::user_profile_path_setting, self::default_profile_path);
            ChatBroUtils::add_or_update_option(self::display_setting, 'everywhere');

            return $guid;
        }

        function render_field($args) {
            $tag = $args['type'] == InputType::select || $args['type'] == InputType::textarea ? $args['type'] : 'input';
            $class = $args['type'] == 'text' ? 'class="regular-text" ' : '';

            $value = ChatBroUtils::get_option($args['id']);
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

        public static function generate_chat_code($guid, $container_id = null, $is_static = true) {
            $hash = md5($guid);
            $user = wp_get_current_user();
            $siteurl = ChatBroUtils::get_option('siteurl');
            $site_domain = ChatBroUtils::get_site_domain();

            $site_user_avatar_url = "";
            preg_match("/src='(.*)' alt/i", get_avatar($user->ID, 120), $avatar_path);
                if(count($avatar_path)!=0)
                    $site_user_avatar_url = $avatar_path[1];
            if($site_user_avatar_url == "")
                $site_user_avatar_url = get_avatar_url($user->ID);

            $site_user_avatar_url = strpos($site_user_avatar_url, 'wp_user_avatar') == FALSE ? $site_user_avatar_url : '';

            $profile_path = ChatBroUtils::get_option(self::user_profile_path_setting);

            $profile_url = '';

            if ($profile_path)
                $profile_url = str_ireplace('{$username}', $user->user_login, $siteurl . $profile_path);

            $params = "encodedChatGuid: '{$hash}'";
            if (is_user_logged_in()) {
                $signature = md5($site_domain . $user->ID . $user->display_name . $site_user_avatar_url . $profile_url . $guid);
                $params .= ", siteUserFullName: '{$user->display_name}', siteUserExternalId: '{$user->ID}', siteDomain: '{$site_domain}'";

                if ($site_user_avatar_url != "")
                    $params .= ", siteUserAvatarUrl: '{$site_user_avatar_url}'";

                if ($profile_url != '')
                    $params .= ", siteUserProfileUrl: '{$profile_url}'";

                if ($container_id)
                	$params .= ", containerDivId: '{$container_id}'";

                if (!$is_static)
                	$params .= ", isStatic: false";
            }
            else {
                $signature = md5($site_domain . $guid);
            }

            $params .= ", signature: '{$signature}'";
            ob_start();

            ?>
            <script id="chatBroEmbedCode">
            /* Chatbro Widget Embed Code Start */
            function ChatbroLoader(chats,async) {async=async!==false;var params={embedChatsParameters:chats instanceof Array?chats:[chats],needLoadCode:typeof Chatbro==='undefined'};var xhr=new XMLHttpRequest();xhr.onload=function(){eval(xhr.responseText)};xhr.onerror=function(){console.error('Chatbro loading error')};xhr.open('POST','//www.chatbro.com/embed_chats/',async);xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');xhr.send('parameters='+encodeURIComponent(JSON.stringify(params)))}
            /* Chatbro Widget Embed Code End */
            ChatbroLoader({<?php echo $params; ?>});
            </script>
            <?php

            $code = ob_get_contents();
            ob_end_clean();

            return $code;
        }

        function chat() {
            $guid = ChatBroUtils::get_option(self::guid_setting);

            if (!$guid) {
                $opts = ChatBroUtils::get_option(self::old_options);

                if ($opts != false)
                    $this->chat_old($opts);

                return;
            }

            $display_to_guests = ChatBroUtils::get_option(self::display_to_guests_setting);

            if (!$display_to_guests && !is_user_logged_in())
                // Don't show the chat to unregistered users
                return;

            $where_to_display = ChatBroUtils::get_option(self::display_setting);

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
                    if (!ChatBroUtils::check_path())
                        return;
                    break;

                default:
                    return;
            }

            echo self::generate_chat_code($guid);
        }
    }
}

?>
