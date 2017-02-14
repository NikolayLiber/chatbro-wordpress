<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once(ABSPATH . '/wp-admin/includes/user.php');

if (!class_exists("ChatBroPlugin")) {
    __('Chat secret key', 'chatbro');
    __('Display chat to guests', 'chatbro');
    __('Show popup chat', 'chatbro');
    __('Everywhere', 'chatbro');
    __('Front page only', 'chatbro');
    __('Everywhere except those listed', 'chatbro');
    __('Only the listed pages', 'chatbro');
    __('Disable', 'chatbro');
    __("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are /2012/10/my-post for a single post and /2012/* for a group of posts. The path should always start with a forward slash(/).", 'chatbro');
    __('User profile path', 'chatbro');
    __('Enable shortcodes', 'chatbro');
    __('Invalid chat key', 'chatbro');


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
        const cap_view = "chatbro_view_chat";

        const guid_setting = "chatbro_chat_guid";
        const display_to_guests_setting = "chatbro_chat_display_to_guests";
        const display_setting = "chatbro_chat_display";
        const selected_pages_setting = 'chatbro_chat_selected_pages';
        const user_profile_path_setting = 'chatbro_chat_user_profile_url';
        const enable_shortcodes_setting = 'chatbro_enable_shortcodes';
        const old_options = 'chatbro_options';
        const default_profile_path = 'authors/{$username}';
        const caps_initialized = 'chatbro_caps_initialized';
        const plugin_version_setting = 'chatbro_plugin_version';

        public static $options;
        private function __construct() {
            self::$options = array(
                self::guid_setting => array(
                    'id' => self::guid_setting,
                    'type' => InputType::text,
                    'label' => 'Chat secret key',
                    'sanitize_callback' => array('ChatBroUtils', 'sanitize_guid'),
                    'default' => false,
                    'required' => true,
                    'pattern' => "[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$",
                    'pattern_error' => "Invalid chat key"
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
                    'default' => 'everywhere',
                    'required' => true
                ),

                self::selected_pages_setting => array(
                    'id' => self::selected_pages_setting,
                    'type' => InputType::textarea,
                    'label' => "Pages",
                    'help_block' => "Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are /2012/10/my-post for a single post and /2012/* for a group of posts. The path should always start with a forward slash(/).",
                    'default' => false,
                    'required' => false
                ),

                self::user_profile_path_setting => array(
                    'id' => self::user_profile_path_setting,
                    'type' => InputType::text,
                    'label' => 'User profile path',
                    'default' => self::default_profile_path,
                    'addon' => get_home_url() . '/',
                    'required' => false
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
                    'sanitize_callback' => array('ChatBroUtils', 'sanitize_checkbox'),
                    'default' => true
                )
            );

            add_action('admin_init', array(&$this, 'init_settings'));
            add_action('admin_menu', array(&$this, 'add_menu_option'));
            add_action('wp_footer', array(&$this, 'chat'));
            add_action('wp_ajax_chatbro_save_settings', array('ChatBroPlugin', 'ajax_save_settings'));
            add_action('wp_ajax_chatbro_get_faq', array('ChatBroPlugin', 'ajax_get_faq'));

            if (!ChatBroUtils::get_option(self::caps_initialized)) {
                // Initializing capabilities with default values
                $adm = get_role('administrator');
                $adm->add_cap(self::cap_delete);
                $adm->add_cap(self::cap_ban);

                foreach(get_editable_roles() as $name => $info) {
                    $role = get_role($name);
                    $role->add_cap(self::cap_view);
                }

                ChatBroUtils::add_option(self::caps_initialized, true);
            }
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

            $messages = array('fields' => array());
            if (!ChatBroUtils::call_constructor($guid, $messages)) {
                deactivate_plugins(plugin_basename( __FILE__ ));
                wp_die($messages["fatal"]);
            }

            if (ChatBroUtils::get_option(self::plugin_version_setting) != self::version)
              ChatBroUtils::add_or_update_option(self::plugin_version_setting, self::version);
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
                    $this->render_contact_us_tab();
                    ?>
                </div>
            </div>
            <?php
        }

        function render_tabs() {
            ?>
            </style>
            <ul id="settings-tabs" class="nav nav-tabs" role="tablist" style="margin-top: 1.5rem;">
                <li role="presentation" class="active">
                    <a href="#constructor" aria-controls="constructor" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-wrench"></span><span class="tab-title hidden-xs"><?php _e("Chat Constructor", 'chatbro'); ?></span></a>
                </li>
                <li role="presentation">
                    <a href="#plugin-settings" aria-controls="plugin-settings" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-cog"></span><span class="tab-title hidden-xs"><?php _e("Plugin Settings", 'chatbro'); ?></span></a>
                </li>
                <li role="presentation">
                    <a href="#contact-us" aria-controls="contact-us" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-question-sign"></span><span class="tab-title hidden-xs"><?php _e("Help", "chatbro"); ?></span></a>
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
                </script>
            </div>
            <?php
        }

        function render_settings_tab($guid) {
            ?>
            <div role="tabpanel" class="tab-pane fade in container-fluid" id="plugin-settings">
                <?php $this->render_guid_confirmation_modal(); ?>

                <div class="row">
                    <div class="col-lg-8" style="margin-top: 1.5rem;">
                        <div id="chatbro-message" style="margin-bottom: 1.5rem;" role="alert"></div>
                        <?php
                            $this->render_settings_form($guid);
                        ?>
                    </div>
                    <?php $this->render_help_block(); ?>
                </div>
            </div>
            <?php
        }

        static function get_support_chat_id() {
          $chats = array (
            'en' => '083y',
            'ru' => '47cs'
          );

          $locale = get_locale();

          if (isset($chats[$locale]))
            return $chats[$locale];

          $t = explode('_', $locale);
          $lang = $t[0];

          if (isset($chats[$lang]))
            return $chats[$lang];

          return $chats['en'];
        }

        function render_contact_us_tab() {
          ?>
          <div id="contact-us" role="tabpanel" class="tab-pane fade in container-fluid" >
            <div class="row">
              <div id="chatbro-chat-panel" class="col-lg-6 col-lg-push-6">
                <div id="support-chat" data-spy="affix" data-offset-top="53"></div>
              </div>
              <div id="chatbro-faq-panel" class="col-lg-6 col-lg-pull-6">
                <h2><?php _e("Frequently Asked Questions", "chatbro"); ?></h2>
                <p id="chatbro-faq"></p>
              </div>
            </div>
            <script id="chatBroEmbedCode">
              /* Chatbro Widget Embed Code Start */function ChatbroLoader(chats,async) {async=async!==false;var params={embedChatsParameters:chats instanceof Array?chats:[chats],needLoadCode:typeof Chatbro==='undefined'};var xhr=new XMLHttpRequest();xhr.withCredentials = true;xhr.onload=function(){eval(xhr.responseText)};xhr.onerror=function(){console.error('Chatbro loading error')};xhr.open('POST','//www.chatbro.com/embed_chats/',async);xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');xhr.send('parameters='+encodeURIComponent(JSON.stringify(params)))}/* Chatbro Widget Embed Code End */
              ChatbroLoader({encodedChatId: '<?php echo self::get_support_chat_id(); ?>'});
            </script>
          </div>
          <?php
        }

        function render_guid_confirmation_modal() {
            ?>
            <div class="modal fade" id="chb-confirm-guid-modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="myModalLabel"><?php _e("You are about to change the secret key", "chatbro"); ?></h4>
                      </div>
                      <p class="modal-body">
                        <?php
                        _e('Please be noticed that your current chat configuration and content are identified by your secret key and if you lose it there
                        will be no way to restore access to you current chat unless you have registered an account at
                        <a href="https://chatbro.com">Chatbro.com</a>. Please make sure that you have saved your old secret key and fully understand
                        what are you going to do.', 'chatbro');
                        ?>
                        <p id="chb-old-key">
                            <?php _e("Your old secret key: <span></span>", "chatbro"); ?>
                        </p>
                      </p>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal"><?php _e("Cancel", "chatbro"); ?></button>
                        <button type="button" class="btn btn-primary"><?php _e("Change Secret Key", "chatbro"); ?></button>
                      </div>
                    </div>
                </div>
            </div>
            <?php
        }

        function render_settings_form($guid) {
            ?>
            <form id="chatbro-settings-form" class="form-horizontal" data-toggle="validator" role="form">
                <input name="action" type="hidden" value="chatbro_save_settings">
                <?php wp_create_nonce("chatbro_save_settings", "chb-sec"); ?>
                <input id="chb-login-url" name="chb-login-url" type="hidden" value="<?php echo wp_login_url(get_permalink()); ?>">
                <input id="chb-sec-key" name="chb-sec-key" type="hidden" value = "<?php echo $guid ?>">
                <?php
                    foreach(self::$options as $name => $args) {
                        $this->render_field($args);
                    }

                    $this->render_permissions();
                ?>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button id="chatbro-save" type="button" class="btn btn-primary" data-saving-text="<i class='fa fa-circle-o-notch fa-spin'></i> Saving Changes"><?php _e('Save Changes', 'chatbro'); ?></button>
                   </div>
                </div>
            </form>
            <?php
        }

        function render_help_block() {
            ?>
            <div id="chatbro-shortcode-tip" class="col-lg-4">
                <div class="bs-callout bs-callout-info">
                    <h3><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span><span style="padding-left: 0.7rem"><?php _e("Useful Tip", "chatbro"); ?></span></h3>
                    <?php _e('Use shortcode <span>[chatbro]</span> to add the chat widget to the desired place of your page or post.', 'chatbro'); ?>
                    <h4><?php _e('Supported shortcode attributes:', 'chatbro'); ?></h4>
                    <ul>
                        <li>
                            <?php
                              // Translators: Attribute name "static" and attribut value "true" shouldn't be translated
                              _e('<em><b>static</b></em> &ndash; static not movable chat widget (default <em>true</em>).', 'chatbro');
                            ?>
                        </li>
                        <li>
                            <?php
                              // Translators: Attribute name "egistered_only" and attribut value "false" shouldn't be translated
                              _e('<em><b>registered_only</b></em> &ndash; display chat widget to logged in users only (default <em>false</em>). If this attribute is explicitly set it precedes the global <em>"Display chat to guests"</em> setting value.', 'chatbro');
                            ?>
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
                    $this->render_checkbox($id, $label);
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
                        <?php _e($label, 'chatbro'); ?>
                    </label>
                </div>
            </div>
            <?php
        }

        function render_other($id, $label, $args) {
            ?>
            <label for="<?php echo $id; ?>" class="col-sm-2 control-label"><?php _e($label, 'chatbro'); ?></label>
            <div class="col-sm-10">
                <?php
                    if (array_key_exists('addon', $args))
                        $this->render_addon($id, $args);
                    else
                        $this->render_control($id, $args);

                    ?>
                    <div class="help-block with-errors"></div>
                    <?php

                    if (array_key_exists('help_block', $args)) {
                        $help_block = $args['help_block'];
                        ?>
                        <div class="input-group">
                            <span class="help-block"><?php _e($help_block, 'chatbro'); ?></span>
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
            $required = (array_key_exists('required', $args) && $args['required']) ? "required " : "";

            switch($args['type']) {
                case InputType::text:
                    $pattern = array_key_exists('pattern', $args) ? "pattern=\"{$args['pattern']}\" " : "";
                    $pattern_error = (array_key_exists('pattern_error', $args) ? ('data-pattern-error="' . __($args['pattern_error'], 'chatbro') . '" ') : "");
                    ?>
                    <input id="<?php echo $id; ?>" name="<?php echo $id; ?>" type="text" class="form-control" value="<?php echo $value; ?>" <?php echo "{$required}{$pattern}{$pattern_error}"; ?>>
                    <span class="field-icon form-control-feedback glyphicon" aria-hidden="true"></span>
                    <?php
                    break;

                case InputType::textarea:
                    ?>
                    <textarea id="<?php echo $id; ?>" name="<?php echo $id; ?>" class="form-control" cols="80" rows="6" <?php echo $required; ?>><?php echo $value; ?></textarea>
                    <?php
                    break;

                case InputType::select:
                    ?>
                    <select id="<?php echo $id; ?>" name="<?php echo $id; ?>" class="form-control" <?php echo $required; ?>>
                        <?php
                        foreach($args['options'] as $val => $desc) {
                            $desc = __($desc, 'chatbro');
                            $selected = $val == $value ? 'selected="selected"' : '';
                            echo "<option {$selected} name=\"$id\" value=\"{$val}\">{$desc}</option>";
                        }
                        ?>
                    </select>
                    <?php
                    break;
            }
        }

        function render_permissions() {
            ?>
            <div id="permissions-group" class="form-group">
                <label class="control-label col-sm-2"><?php _e("Permissions", "chatbro"); ?></label>
                <div class="col-sm-10">
                    <table id="chatbro-permissions" class="table table-active">
                        <tr>
                            <th><?php _e("Role", "chatbro"); ?></th>
                            <th><?php _e("View", "chatbro"); ?></th>
                            <th><?php _e("Ban", "chatbro"); ?></th>
                            <th><?php _e("Delete", "chatbro"); ?></th>
                        </tr>
                        <?php
                        foreach(get_editable_roles() as $name => $info) {
                            $ctrlViewId = "chatbro_" . $name . "_view";
                            $ctrlBanId = "chatbro_" . $name . "_ban";
                            $ctrlDeleteId = "chatbro_" . $name . "_delete";

                            $role = get_role($name);

                            $chkView = $role->has_cap(self::cap_view) ? "checked" : "";
                            $chkBan = $role->has_cap(self::cap_ban) ? "checked" : "";
                            $chkDelete = $role->has_cap(self::cap_delete) ? "checked" : "";
                            ?>
                            <tr>
                                <td><?php echo $info["name"] ?></td>
                                <td><input id="<?php _e($ctrlViewId); ?>" name="<?php _e($ctrlViewId); ?>" type="checkbox" <?php echo $chkView; ?>></td>
                                <td><input id="<?php _e($ctrlBanId); ?>" name="<?php _e($ctrlBanId); ?>" <?php echo $chkBan; ?> type="checkbox"></td>
                                <td><input id="<?php _e($ctrlDeleteId); ?>" name="<?php _e($ctrlDeleteId); ?>"type="checkbox" <?php echo $chkDelete; ?>></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </table>
                </div>
            </div>
            <?php
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

                ChatBroUtils::delete_option($name);
            }

            foreach(get_editable_roles() as $name => $info) {
                $role = get_role($name);
                $role->remove_cap(self::cap_view);
                $role->remove_cap(self::cap_ban);
                $role->remove_cap(self::cap_delete);
            }

            ChatBroUtils::delete_option(self::caps_initialized);
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
                  ChatBroDeprecated::chat_old($opts);

              return;
          }

          if (!ChatBroUtils::user_can_view(ChatBroUtils::get_option(self::display_to_guests_setting)))
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
                die('{"success":false,"message":"' . __("You are not allowed to modify settings","chatbro") . '","msg_type":"error"}');

            $messages = array('fields' => array());
            $new_vals = array();
            foreach(self::$options as $op_name => $op_desc) {
              $value = isset($_POST[$op_name]) ? trim(wp_unslash($_POST[$op_name])) : false;

              if (array_key_exists('sanitize_callback', $op_desc)) {
                  $new_vals[$op_name] = call_user_func_array($op_desc['sanitize_callback'], array($value, &$messages));
              }
              else {
                $new_vals[$op_name] = $value;
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

                // Saving permissions
                foreach(get_editable_roles() as $name => $info) {
                    $viewCap = $_POST['chatbro_' . $name . '_view'] == 'on' ? true : false;
                    $banCap = $_POST['chatbro_' . $name . '_ban'] == 'on' ? true : false;
                    $deleteCap = $_POST['chatbro_' . $name . '_delete'] == 'on' ? true : false;

                    $role = get_role($name);

                    if ($viewCap)
                        $role->add_cap(self::cap_view);
                    else
                        $role->remove_cap(self::cap_view);

                    if ($banCap)
                        $role->add_cap(self::cap_ban);
                    else
                        $role->remove_cap(self::cap_ban);

                    if ($deleteCap)
                        $role->add_cap(self::cap_delete);
                    else
                        $role->remove_cap(self::cap_delete);
                }

                $reply['message'] = "<strong>" . __("Settings was successfuly saved", "chatbro") . "</strong>";
                $reply['msg_type'] = "info";
            }

            if (count($messages['fields']))
                $reply['field_messages'] = $messages['fields'];

            die(json_encode($reply));
        }

        function ajax_get_faq() {
          $url = 'https://www.chatbro.com/faq.html';
          $response = wp_safe_remote_get($url);

          if (is_wp_error($response))
            die("");

          die($response['body']);
        }
    }
}

?>
