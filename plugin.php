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

                self::display_to_guests_setting => array(
                    'id' => self::display_to_guests_setting,
                    'type' => InputType::checkbox,
                    'label' => 'Display chat to guests',
                    'sanitize_callback' => array('ChatBroUtils', 'sanitize_checkbox'),
                    'default' => true
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

            wp_enqueue_style('chatbro', plugin_dir_url( __FILE__ ) . 'css/chatbro.min.css');
            wp_enqueue_script('chatbro', plugin_dir_url( __FILE__ ) . 'js/chatbro.min.js');

            $guid = ChatBroUtils::get_option(self::guid_setting);

            ?>
            <div>
                <?php
                $this->render_tabs();
                ?>
                <div class="tab-content">
                    <?php
                    $this->render_constructor_tab($guid);
                    $this->render_settings_tab($guid);
                    ?>
                </div>
            </div>
            <?php
        }

        function render_tabs() {
            ?>
            <ul id="settings-tabs" class="nav nav-tabs" role="tablist" style="margin-top: 1.5rem;">
                <li role="presentation" <?php if ($_GET['tab'] != 'plugin_settings') echo 'class="active"'; ?> >
                    <a href="#constructor" aria-controls="constructor" role="tab" data-toggle="tab"><?php _e("Chat Constructor", 'chatbro-plugin'); ?></a>
                </li>
                <li role="presentation" <?php if ($_GET['tab'] == 'plugin_settings') echo 'class="active"'; ?>>
                    <a href="#plugin-settings" aria-controls="plugin-settings" role="tab" data-toggle="tab"><?php _e("Plugin Settings", 'chatbro-plugin'); ?></a>
                </li>
            </ul>
            <?php
        }

        function render_constructor_tab($guid) {
            ?>
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
                </script> -->
            </div>
            <?php
        }

        function render_settings_tab($guid) {
            ?>
            <div role="tabpanel" class="tab-pane fade container-fluid" id="plugin-settings">
                <script>
                    var chatbro_secret_key = '<?php echo $guid ?>';
                </script>
                <div class="row">
                    <div class="col-lg-8" style="margin-top: 1.5rem;">
                        <div id="chatbro-message" style="margin-bottom: 1.5rem;" role="alert"></div>
                        <?php $this->render_settings_form($guid); ?>
                    </div>
                    <?php $this->render_help_block(); ?>
                </div>
            </div>
            <?php
        }

        function render_settings_form($guid) {
            ?>
            <form id="chatbro-settings-form" class="form-horizontal">
                <input name="action" type="hidden" value="chatbro_save_settings">
                <?php wp_create_nonce("chatbro_save_settings", "chb-sec"); ?>
                <input id="chb-login-url" name="chb-login-url" type="hidden" value="<?php echo wp_login_url(get_permalink()); ?>">
                <input id="chb-sec-key" name="chb-sec-key" type="hidden" value = "<?php echo $guid ?>">
                <?php
                    foreach(self::$options as $name => $args) {
                        self::render_field($args);
                    }
                ?>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10" style="padding-top: 1.5rem">
                        <button id="chatbro-save" type="submit" class="btn btn-primary" data-saving-text="<i class='fa fa-circle-o-notch fa-spin'></i> Saving Changes"><?php _e('Save Changes', 'chatbro-plugin'); ?></button>
                   </div>
                </div>
            </form>
            <?php
        }

        function render_help_block() {
            ?>
            <div class="col-lg-4" style="margin-top: 1.5rem;">
                <div class="bs-callout bs-callout-info">
                    <h3><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span><span style="padding-left: 0.7rem">Shortcode</span></h3>
                    <?php _e('Use shortcode <em><b>[chatbro]</b></em> to add the chat widget to the desired place of your page or post.', 'chatbro-plugin'); ?>
                    <h4><?php _e('Supported shortcode attributes:', 'chatbro-plugin'); ?></h4>
                    <ul>
                        <li>
                            <?php _e('<em><b>static</b></em> &ndash; static not movable chat widget (default <em>true</em>).', 'chatbro-plugin'); ?>
                        </li>
                        <li>
                            <?php _e('<em><b>registered_only</b></em> &ndash; display chat widget to logged in users only (default <em>false</em>). If this attribute is explicitly set it precedes the global <em>"Display chat to guests"</em> setting value.', 'chatbro-plugin'); ?>
                        </li>
                    </ul>
                </div>
            </div>
            <?php
        }

        function render_field($args) {
            $id = $args['id'];
            $type = $args['type'];
            $label = $args['label'];

            ?>
            <div id="<?php echo $id; ?>-group" class="form-group">
                <?php
                if($type == InputType::checkbox)
                    $this->render_checkbox($id, $label, $value);
                else
                    $this->render_other($id, $label, $args);
                ?>
            </div>
            <?php
        }

        function render_checkbox($id, $label) {
            $checked = ChatBroUtils::get_option($id) ? 'checked="checked"' : '';
            ?>

            <div class="col-sm-offset-2 col-sm-10">
                <div class="checkbox">
                    <label>
                        <input id="<?php echo $id; ?>" type="checkbox" name="<?php echo $id; ?>" <?php echo $checked; ?> >
                        <?php _e($label, 'chatbro-plugin'); ?>
                    </label>
                </div>
            </div>
            <?php
        }

        function render_other($id, $label, $args) {
            ?>
            <label for="<?php echo $id; ?>" class="col-sm-2 control-label"><?php _e($label, 'chatbro-plugin'); ?></label>
            <div class="col-sm-10">
                <?php
                    if (array_key_exists('addon', $args))
                        $this->render_addon($id, $args);
                    else
                        $this->render_control($id, $args);

                    ?>
                    <div id="<?php echo "{$id}-message"; ?>" class="input-group control-message">
                        <span class="help-block"></span>
                    </div>
                    <?php

                    if (array_key_exists('help_block', $args)) {
                        $help_block = $args['help_block'];
                        ?>
                        <div class="input-group">
                            <span class="help-block"><?php _e($help_block, 'chatbro-plugin'); ?></span>
                        </div>
                        <?php
                    }
                ?>
            </div>
            <?php
        }

        function render_addon($id, $args) {
            $addon = $args['addon'];
            ?>
            <div class="input-group">
                <span class="input-group-addon"><?php echo $addon; ?></span>
                <?php $this->render_control($id, $args); ?>
            </div>
            <?php
        }

        function render_control($id, $args) {
            $value = ChatBroUtils::get_option($id);

            switch($args['type']) {
                case InputType::text:
                    ?>
                    <input id="<?php echo $id; ?>" name="<?php echo $id; ?>" type="text" class="form-control" value="<?php echo $value; ?>">
                    <?php
                    break;

                case InputType::textarea:
                    ?>
                    <textarea id="<?php echo $id; ?>" name="<?php echo $id; ?>" class="form-control" cols="80" rows="6">
                        <?php echo $value; ?>
                    </textarea>
                    <?php
                    break;

                case InputType::select:
                    ?>
                    <select id="<?php echo $id; ?>" name="<?php echo $id; ?>" class="form-control">
                        <?php
                        foreach($args['options'] as $val => $desc) {
                            $desc = __($desc, 'chatbro-plugin');
                            $selected = $val == $value ? 'selected="selected"' : '';
                            echo "<option {$selected} name=\"$id\" value=\"{$val}\">{$desc}</option>";
                        }
                        ?>
                    </select>
                    <?php
                    break;
            }
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

                $reply['message'] = "<strong>" . __("Settings was successfuly saved", "chatbro-plugin") . "</strong>";
                $reply['msg_type'] = "info";
            }

            if (count($messages['fields']))
                $reply['field_messages'] = $messages['fields'];

            die(json_encode($reply));
        }
    }
}

?>
