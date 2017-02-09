<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once(ABSPATH . '/wp-includes/class-wp-widget.php');
require_once(ABSPATH . '/wp-includes/formatting.php');

if (!class_exists('ChatbroWidget')) {
  class ChatBroWidget extends WP_Widget {
    public function __construct() {
      parent::__construct(
            'chatbro',
            esc_html(__('ChatBro', 'chatbro')),
            array('description' => esc_html(__('ChatBro group chat', 'chatbro')))
      );
    }

    public static function register() {
      register_widget('ChatBroWidget');
    }

    public function widget($args, $instance) {
      $display_to_guests = true;

      if (isset($instance[ChatBroPlugin::display_to_guests_setting]))
        $display_to_guests = !!$instance[ChatBroPlugin::display_to_guests_setting];

      if (!ChatBroUtils::user_can_view($display_to_guests))
        return;

      $guid = strtolower(ChatBroUtils::get_option(ChatBroPlugin::guid_setting));
      $container_id = 'chatbro-widget-' . rand(0, 99999);

      ?>
        <section id="<?php echo $container_id; ?>" class="widget">
          <?php echo ChatBroUtils::generate_chat_code($guid, $container_id, true); ?>
        </section>
      <?php
    }

    public function form($instance) {
      $display_to_guests = true;

      if (isset($instance[ChatBroPlugin::display_to_guests_setting]))
        $display_to_guests = !!$instance[ChatBroPlugin::display_to_guests_setting];

      $dtg_id = $this->get_field_id(ChatBroPlugin::display_to_guests_setting);
      $dtg_name = $this->get_field_name(ChatBroPlugin::display_to_guests_setting);
      ?>
      <p>
        <label for="<?php echo $display_to_guests_id; ?>">
          <input type="checkbox" id="<?php echo $dtg_id; ?>" name="<?php echo $dtg_name; ?>" class="checkbox" <?php echo $display_to_guests ? 'checked' : ''; ?>>
          <?php _e("Display chat to guests", "chatbro"); ?>
        </label>
      </p>
      <?php
    }

    public function update($new_instance, $old_instance) {
      $instance = array();
      $instance[ChatBroPlugin::display_to_guests_setting] = isset($new_instance[ChatBroPlugin::display_to_guests_setting]) ? !!$new_instance[ChatBroPlugin::display_to_guests_setting] : false;
      return $instance;
    }
  }
}

?>
