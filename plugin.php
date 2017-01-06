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
    __('Enable shortcodes', 'chatbro_plugin');


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
        const enable_shortcodes_setting = 'chatbro_enable_shortcodes';
        const old_options = 'chatbro_options';
        const default_profile_path = '/authors/{$username}';

        public static $options;
        private function __construct() {
            self::$options = array(
                self::guid_setting => array(
                    'id' => self::guid_setting,
                    'type' => InputType::text,
                    'label' => 'Chat secret key',
                    'sanitize_callback' => array('ChatBroUtils', 'sanitize_guid'),
                    'default' => false
                ),

                self::display_to_guests_setting => array(
                    'id' => self::display_to_guests_setting,
                    'type' => InputType::checkbox,
                    'label' => 'Display chat to guests',
                    'sanitize_callback' => array('ChatBroUtils', 'sanitize_checkbox'),
                    'default' => true
                ),

                self::display_setting => array(
                    'id' => self::display_setting,
                    'type' => InputType::select,
                    'label' => 'Show popup chat',
                    'options' => array(
                        'everywhere' =>    'Everywhere',
                        'frontpage_only' => 'Front page only',
                        'except_listed' => 'Everywhere except those listed',
                        'only_listed' =>   'Only the listed pages',
                        'disable' =>       'Disable'
                    ),
                    'default' => 'everywhere'
                ),

                self::selected_pages_setting => array(
                    'id' => self::selected_pages_setting,
                    'type' => InputType::textarea,
                    'label' => "Pages",
                    'help_block' => "Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are /2012/10/my-post for a single post and /2012/* for a group of posts. The path should always start with a forward slash(/).",
                    'default' => false
                ),

                self::user_profile_path_setting => array(
                    'id' => self::user_profile_path_setting,
                    'type' => InputType::text,
                    'label' => 'User profile path',
                    'default' => '/authors/{$username}',
                    'addon' => get_home_url() . '/'
                ),

                self::enable_shortcodes_setting => array(
                    'id' => self::enable_shortcodes_setting,
                    'type' => InputType::checkbox,
                    'label' => 'Enable shortcodes',
                    'default' => true
                )
            );

            add_action('admin_init', array(&$this, 'init_settings'));
            add_action('admin_menu', array(&$this, 'add_menu_option'));
            add_action('wp_footer', array(&$this, 'chat'));
            add_action('wp_ajax_chatbro_save_settings', array('ChatBroPlugin', 'ajax_save_settings'));

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
            $guid = ChatBroUtils::get_option(self::guid_setting);

            if (!$guid)
                $guid = self::get_instance()->set_default_settings();

            if (!ChatBroUtils::call_constructor($guid)) {
                deactivate_plugins(plugin_basename( __FILE__ ));
                wp_die(__("Failed to connect to chat server", "chatbro-plugin"));
            }
        }

        function constructor_page() {
            $old = ChatBroUtils::get_option(self::old_options);
            if (ChatBroUtils::get_option(self::guid_setting) == false && $old != false && $old['chatPath']) {
                ChatBroDeprecated::convert_from_old_page($old);
                return;
            }

            wp_enqueue_style('bootstrap', plugin_dir_url( __FILE__ ) . 'css/bootstrap.min.css');
            wp_enqueue_style('font-awesome', 'http://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.1.0/css/font-awesome.min.css');
            wp_enqueue_script('bootstrap', plugin_dir_url( __FILE__ ) . 'js/bootstrap.min.js', array('jquery'));
            wp_enqueue_script('chatbro-admin', plugin_dir_url( __FILE__ ) . 'js/chatbro.admin.js', array('jquery-form'));

            $guid = ChatBroUtils::get_option(self::guid_setting);
            $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'constructor';

            ?>
            <ul id="settings-tabs" class="nav nav-tabs" role="tablist" style="margin-top: 1.5rem;">
                <li role="presentation" <?php if ($_GET['tab'] != 'plugin_settings') echo 'class="active"'; ?> >
                    <a href="#constructor" aria-controls="constructor" role="tab" data-toggle="tab"><?php _e("Chat Constructor", 'chatbro-plugin'); ?></a>
                </li>
                <li role="presentation" <?php if ($_GET['tab'] == 'plugin_settings') echo 'class="active"'; ?>>
                    <a href="#plugin-settings" aria-controls="plugin-settings" role="tab" data-toggle="tab"><?php _e("Plugin Settings", 'chatbro-plugin'); ?></a>
                </li>
            </ul>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane fade in active" id="constructor">
                    <iframe name="chatbro-constructor" style="width: 100%; height: 85vh"></iframe>
                    <form id="load-constructor" target="chatbro-constructor" action="https://www.chatbro.com/constructor/<?php echo $guid; ?>/" method="GET">
                        <input type="hidden" name="guid" value="<?php echo $guid; ?>">
                        <input type="hidden" name="avatarUrl" value="<?php echo ChatBroUtils::get_avatar_url(); ?>">
                        <input type="hidden" name="userFullName" value="<?php echo wp_get_current_user()->display_name; ?>">
                        <input type="hidden" name="userProfileUrl" value="<?php echo ChatBroUtils::get_profile_url(); ?>">
                    </form>
                    <script>
                        jQuery("#load-constructor").submit();
                    </script>
                </div>
                <div role="tabpanel" class="tab-pane fade" id="plugin-settings">
                    <script>
                        var chatbro_secret_key = '<?php echo $guid ?>';
                    </script>
                    <div style="margin-top: 1rem; padding: 1.5rem">
                        <div id="chatbro-message" role="alert"></div>
                        <form id="chatbro-settings-form">
                            <input name="action" type="hidden" value="chatbro_save_settings">
                            <?php wp_create_nonce("chatbro_save_settings", "chb-sec"); ?>
                            <input id="chb-login-url" name="chb-login-url" type="hidden" value="<?php echo wp_login_url(get_permalink()); ?>">
                            <input id="chb-sec-key" name="chb-sec-key" type="hidden" value = "<?php echo $guid ?>">
                            <?php
                                foreach(self::$options as $name => $args) {
                                    self::render_field($args);
                                }
                            ?>
                            <p class="form-group">
                                <button id="chatbro-save" type="submit" class="btn btn-primary" data-saving-text="<i class='fa fa-circle-o-notch fa-spin'></i> Saving Changes"><?php _e('Save Changes', 'chatbro-plugin'); ?></button>
                            </p>
                        </form>
                    </div>
                    <!--<div style="float: left; padding: 1em; margin-top: 1em; border: solid 1px #ccc; word-wrap: break-word; width: 50%">
                        <?php _e('Use shortcode <em><b>[chatbro]</b></em> to add the chat widget to the desired place of your page or post.', 'chatbro-plugin'); ?>
                        <h4><?php _e('Supported shortcode attribtes:', 'chatbro-plugin'); ?></h4>
                        <ul>
                            <li>
                                <?php _e('<em><b>static</b></em> &ndash; static not movable chat widget (default <em>true</em>).', 'chatbro-plugin'); ?>
                            </li>
                            <li>
                                <?php _e('<em><b>registered_only</b></em> &ndash; display chat widget to logged in users only (default <em>false</em>). If this attribute is explicitly set it precedes the global <em>"Display chat to guests"</em> setting value.', 'chatbro-plugin'); ?>
                            </li>
                        </ul>
                    </div> -->
                </div>
            </div>
            <?php
        }

        function render_field($args) {
            $tag = $args['type'] == InputType::select || $args['type'] == InputType::textarea ? $args['type'] : 'input';
            $class = $args['type'] == InputType::checkbox ? 'form-check-input' : 'form-control';

            $value = ChatBroUtils::get_option($args['id']);
            $valueAttr = $args['type'] == InputType::text ? "value=\"{$value}\" " : "";
            $checked = $args['type'] == InputType::checkbox && $value ? 'checked="checked"' : '';
            $textarea_attrs = $args['type'] == InputType::textarea ? 'cols="80" rows="6"' : '';


            echo "<div class=\"form-group row\">";
            echo "<label for=\"{$args['id']}\" class=\"col-xs-2 col-form-label\">{$args['label']}</label>";
            echo "<div class=\"input-group col-xs-10\">";

            if (array_key_exists('addon', $args))
                echo "<span class=\"input-group-addon\">{$args['addon']}</span>";

            echo "<{$tag} id=\"{$args['id']}\" name=\"{$args['id']}\" class=\"{$class}\" type=\"{$args['type']}\" {$textarea_attrs} {$valueAttr} {$checked}>";

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

            if (array_key_exists('help_block', $args))
                echo "<span class=\"help-block\">" . __($args['help_block'], 'chatbro-plugin') . "</span>";

            echo "</div>";
            echo "</div>";
        }


        function init_settings() {
            $old_options = ChatBroUtils::get_option(self::old_options);
            if (ChatBroUtils::get_option(self::guid_setting) == false && ($old_options == false || $old_options['chatPath'] == 'tg/208397015/Ask your own question'))
                $this->set_default_settings();
        }

        public static function clenup_settings() {
            foreach (array_keys(self::$options) as $name) {
                if (ChatBroUtils::get_option($name) === false)
                    continue;

                delete_option($name);
            }

            $adm = get_role('administrator');
            $adm->remove_cap(self::delete);
            $adm->remove_cap(self::ban);
        }

        function set_default_settings() {
            $guid = ChatBroUtils::gen_uuid();

            ChatBroUtils::add_or_update_option(self::guid_setting, $guid);
            ChatBroUtils::add_or_update_option(self::display_to_guests_setting, true);
            ChatBroUtils::add_or_update_option(self::user_profile_path_setting, self::default_profile_path);
            ChatBroUtils::add_or_update_option(self::display_setting, 'everywhere');
            ChatBroUtils::add_or_update_option(self::enable_shortcodes_setting, true);

            return $guid;
        }


        function add_menu_option() {
            add_menu_page("ChatBro", "ChatBro", "manage_options", "chatbro_settings", array(&$this, 'constructor_page'), plugins_url()."/chatbro/favicon_small.png");
        }

        function chat() {
            $guid = ChatBroUtils::get_option(self::guid_setting);

            if (!$guid) {
                $opts = ChatBroUtils::get_option(self::old_options);

                if ($opts != false)
                    ChatBro::chat_old($opts);

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

            echo ChatBroUtils::generate_chat_code($guid);
        }

        /**
         * Save settings ajax call
        */
        function ajax_save_settings() {
            global $_POST;
            if (!current_user_can('manage_options'))
                die('{"success":false,"message":"' . __("You are not allowed to modify settings","chatbro-plugin") . '","msg_type":"error"}');

            $messages = array('fields' => array());
            $new_vals = array();
            foreach($_POST as $option => $value) {
                // We are interested only in settings parameters
                if (!array_key_exists($option, self::$options))
                    continue;

                if (!is_array($value))
                    $value = trim($value);

                $value = wp_unslash($value);

                if (array_key_exists($option, self::$options) && array_key_exists('sanitize_callback', self::$options[$option])) {
                    $new_vals[$option] = trim(call_user_func_array(self::$options[$option]['sanitize_callback'], array($value, &$messages)));
                }
            }

            $reply = array('success' => true);

            if (array_key_exists('fatal', $messages)) {
                $reply['success'] = false;
                $reply['message'] = $messages['fatal'];
                $reply['msg_type'] = 'error';
            }
            else {
                foreach($messages['fields'] as $m) {
                    if ($m['type'] == 'error') {
                        $reply['success'] = false;
                        break;
                    }
                }
            }

            if ($reply['success']) {
                foreach($new_vals as $option => $value)
                    ChatBroUtils::update_option($option, $value);

                $reply['message'] = __("Settings was successfuly saved", "chatbro-plugin");
                $reply['msg_type'] = "info";
            }

            if (count($messages['fields']))
                $reply['field_messages'] = $messages['fields'];

            die(json_encode($reply));
        }
    }
}

?>