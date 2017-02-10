<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class ChatBroAvatar_Default extends ChatBroAvatar {
  protected function url($user_id) {
    $user = wp_get_current_user();
    $site_user_avatar_url = "";
    preg_match("/src='(.*)' alt/i", get_avatar($user_id, 120), $avatar_path);

    if(count($avatar_path)!=0)
        $site_user_avatar_url = $avatar_path[1];

    if($site_user_avatar_url == "")
        $site_user_avatar_url = get_avatar_url($user_id);

    $site_user_avatar_url = strpos($site_user_avatar_url, 'wp_user_avatar') == FALSE ? $site_user_avatar_url : '';

    return $site_user_avatar_url;
  }
}

?>
