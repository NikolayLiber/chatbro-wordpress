<?php
/**
* Plugin Name: ChatBro 2
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if (!class_exists("ChatBroPlugin")) {
    class InputType {
        const checkbox = 'checkbox';
        const text = 'text';
        const select = 'select';
    };

    class ChatBroPlugin {
        const page = "chatbro_plugin";
        const settings = "chatbro_plugin_settings";

        const guid_setting = "chatbro_chat_guid";
        const hash_setting = "chatbro_chat_hash";
        const display_to_guests_setting = "chatbro_chat_diplay_to_guests";
        const display_setting = "chatbro_chat_display";

        const options = array(
            ChatBroPlugin::guid_setting => array(
                'id' => ChatBroPlugin::guid_setting,
                'type' => 'text',
                'label' => 'Chat Id'
            ),

            ChatBroPlugin::display_to_guests_setting => array(
                'id' => ChatBroPlugin::display_to_guests_setting,
                'type' => 'checkbox',
                'label' => 'Display chat to not logged-in users'
            ),

            ChatBroPlugin::display_setting => array(
                'id' => ChatBroPlugin::display_setting,
                'type' => 'select',
                'label' => 'Show popup chat',
                'options' => array(
                    'everywhere' =>    'Everywhere',
                    'frontend_only' => 'Frontend only',
                    'except_listed' => 'Everywhere except those listed',
                    'only_listed' =>   'Only the listed pages',
                    'disable' =>       'Disable'
                )
            )
        );

        function ChatBroPlugin() {
            add_action('admin_init', array(&$this, 'init_settings'));
            add_action('admin_menu', array(&$this, 'add_menu_option'));
            add_action('wp_footer', array(&$this, 'chat'));
        }

        public static function gen_uuid() {
            return strtoupper(sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
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

        function constructor_page() {
            $guid = ChatBroPlugin::get_option(ChatBroPlugin::guid_setting);

            $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'constructor';
            ?>
            <div class="wrap">
                <h1><?php __('Plugin Settings', 'chatbro_plugin'); ?></h1>
                <h2 class="nav-tab-wrapper">
                    <a href="?page=chatbro_settings&tab=constructor"
                       class="nav-tab <?php echo $active_tab == 'constructor' ? 'nav-tab-active' : ''; ?>"><?php echo __("Chat Constructor", "chatbro_plugin"); ?></a>
                    <a href="?page=chatbro_settings&tab=plugin_settings"
                       class="nav-tab <?php echo $active_tab == 'plugin_settings' ? 'nav-tab-active' : ''; ?>"><?php echo __("Plugin Settings", "chatbro_plugin"); ?></a>
                </h2>


                <?php
                if ($active_tab == "plugin_settings") {
                    ?>
                    <form method="post" action="options.php">
                        <?php
                            settings_fields(ChatBroPlugin::settings);
                            do_settings_sections(ChatBroPlugin::page);
                            // do_settings_fields(ChatBroPlugin::page, "chbro_plugin_settings");
                        ?>
                        <p class="submit">
                            <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                        </p>
                    </form>
                    <?php
                }
                else {
                    ?>
                    <iframe src="https://www.chatbro.com/constructor/<?php echo $guid; ?>" style="width: 100%; height: 85vh"></iframe>
                    <?php
                }
                ?>
            </div>
            <?php
        }

        function init_settings() {
            add_settings_section("chbro_plugin_settings", "", "", ChatBroPlugin::page);
            foreach(ChatBroPlugin::options as $name => $args) {
                register_setting(ChatBroPlugin::settings, $name);
                add_settings_field($name, __($args['label'], 'chatbro_plugin'), array(&$this, "render_field"), ChatBroPlugin::page, "chbro_plugin_settings", $args);
            }

            if ($this->get_option(ChatBroPlugin::guid_setting) == false)
                $this->set_default_settings();
        }

        function set_default_settings() {
            $guid = $this->gen_uuid();
            $hash = md5($guid);

            if (!ChatBroPlugin::add_option(ChatBroPlugin::guid_setting, $guid))
                ChatBroPlugin::update_option(ChatBroPlugin::guid_setting, $guid);

            if (!ChatBroPlugin::add_option(ChatBroPlugin::hash_setting, $hash))
                ChatBroPlugin::update_option(ChatBroPlugin::hash_setting, $hash);

            if (!ChatBroPlugin::add_option(ChatBroPlugin::display_to_guests_setting, true))
                ChatBroPlugin::update_option(ChatBroPlugin::display_to_guests_setting, true);
        }

        function render_field($args) {

            $tag = $args['type'] == InputType::select ? 'select' : 'input';
            $class = $args['type'] == 'text' ? 'class="regular-text" ' : '';

            $value = $this->get_option($args['id']);
            $valueAttr = $args['type'] == InputType::text ? "value=\"{$value}\" " : "";
            $checked = $args['type'] == InputType::checkbox && isset($value) ? 'checked="checked"' : '';

            echo "<{$tag} id=\"${args[id]}\" name=\"{$args[id]}\" {$class} type=\"{$args[type]}\" {$valueAttr} {$checked}>";

            if ($args['type'] == InputType::select) {
                if (!$value)
                    $value = array_keys($args['options'])[0];

                foreach($args['options'] as $val => $desc) {
                    $selected = $val == $value ? 'selected="selected"' : '';
                    echo "<option {$selected} name=\"{$args[id]}\" value=\"{$val}\">{$desc}</option>";
                }

                echo "</select>";
            }
        }

        function add_menu_option() {
            add_menu_page("ChatBro", "ChatBro", "manage_options", "chatbro_settings", array(&$this, 'constructor_page'), plugins_url()."/chatbro2/favicon_small.png");
        }

        function chat() {
            $hash = chatbro_get_option("chatbro_chat_hash");

            if (!$hash)
                return;
            ?>
            <script id="chatBroEmbedCode">
            /* Chatbro Widget Embed Code Start */
            function ChatbroLoader(chats,async) {async=async!==false;var params={embedChatsParameters:chats instanceof Array?chats:[chats],needLoadCode:typeof Chatbro==='undefined'};var xhr=new XMLHttpRequest();xhr.onload=function(){eval(xhr.responseText)};xhr.onerror=function(){console.error('Chatbro loading error')};xhr.open('POST','//www.chatbro.com/embed_chats/',async);xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');xhr.send('parameters='+encodeURIComponent(JSON.stringify(params)))}
            /* Chatbro Widget Embed Code End */
            ChatbroLoader({encodedChatGuid: "<?php echo $hash ?>"});
            </script>
            <?php
        }
    }
}

new ChatBroPlugin();
