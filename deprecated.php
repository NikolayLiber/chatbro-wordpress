<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
/**
* These functions are kept for backward compatibility and not used in newer plugin installations
*/
if (!class_exists("ChatBroDeprecated")) {
	class ChatBroDeprecated {
        public static function convert_from_old_page($old) {
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

        public static function chat_old($o) {
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
	}
}

?>
