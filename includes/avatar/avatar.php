<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

abstract class ChatBroAvatar {
  private static $instance;

  protected function __construct() {}

  abstract protected function url($user_id);

  public static function create_instance() {
    if (is_plugin_active('wp-user-avatar/wp-user-avatar.php')) {
      require_once('avatar_wp-user-avatar.php');
      return new ChatBroAvatar_WP_User_Avatar();
    }

    require_once('avatar_default.php');
    return new ChatBroAvatar_Default();
  }

  public static function get_url() {
    if (self::$instance == null)
      self::$instance = self::create_instance();

    $user = wp_get_current_user();

    return self::$instance->url($user->ID);
  }
}

?>
