<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once(ABSPATH . '/wp-content/plugins/wp-user-avatar/includes/wpua-functions.php');

class ChatBroAvatar_WP_User_Avatar extends ChatBroAvatar {
  protected function url($user_id) {
    return get_wp_user_avatar_src($user_id);
  }
}
?>
