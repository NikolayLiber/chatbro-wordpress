<?php get_header(); ?>

<div id="div_chatbro_history" class="content-area">

</div><!-- .content-area -->
<script>
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

var p = <?php echo $o = get_opt() ?>;
var param={
    chatPath: p.chatPath,
    	containerDivId: p.containerDivId,
    	chatLanguage: p.chatLanguage,
    	useStandardHistoryWidgetSettings: true,
        chatPaginatorUrlPrefix: '<?php the_permalink() ?>'
	};
var batchId = /page=(.*)&/i.exec(p.currentUrlParam);
if(batchId != null)
    param.batchId=batchId[1];

ChatbroLoader(param);
</script>

<?php get_footer(); ?>
