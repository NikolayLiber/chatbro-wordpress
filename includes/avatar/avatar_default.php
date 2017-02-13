<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class ChatBroAvatar_Default extends ChatBroAvatar {
  protected function url($user_id) {
    return get_avatar_url($user_id);
  }
}

?>
