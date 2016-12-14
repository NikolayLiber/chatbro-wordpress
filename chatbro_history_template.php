<?php

require_once('util.php');
require_once('plugin.php');

get_header();

function get_opt() {
    global $wp;
    $guid = ChatBroUtils::get_option(ChatBroPlugin::guid_setting);

    $current_url_param = $wp->query_string;
    $param = array(
        'encodedChatGuid'                  => md5($guid),
        'containerDivId'                   => "div_chatbro_history",
        'currentUrlParam'                  => $current_url_param,
        'useStandardHistoryWidgetSettings' => true,
        'chatPaginatorUrlPrefix'           => get_permalink()

    );
    $json_param = json_encode($param);
    return $json_param;
}

?>

<div id="div_chatbro_history" class="content-area">

</div><!-- .content-area -->
<script>
/* Chatbro Widget Embed Code Start*/
var chatBroHistoryPage = true;
function ChatbroLoader(chats, async) {
    async = async !== false;
    var params = {
        embedChatsParameters: chats instanceof Array ? chats : [chats],
        needLoadCode: typeof Chatbro === 'undefined'
    };
    var xhr = new XMLHttpRequest();
    xhr.withCredentials = true;
    xhr.onload = function () {
        eval(xhr.responseText);
    };
    xhr.onerror = function () {
        console.error('Chatbro loading error');
    };
    xhr.open('POST', '//www.chatbro.com/embed_chats/', async);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('parameters=' + encodeURIComponent(JSON.stringify(params)));
}
/* Chatbro Widget Embed Code End*/

var param = <?php echo get_opt(); ?>;
var batchId = /page=(.*)&/i.exec(param.currentUrlParam);
if(batchId != null)
    param.batchId=batchId[1];

ChatbroLoader(param);
</script>

<?php get_footer(); ?>
