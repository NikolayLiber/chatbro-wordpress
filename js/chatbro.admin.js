jQuery(document).ready(function($) {
	var t = ['except_listed', 'only_listed'];

	$("#chatbro_chat_display").change(function() {
		if (t.indexOf($("#chatbro_chat_display").val()) != -1)
			$('#chatbro_chat_selected_pages').parent().parent().show();
		else
			$('#chatbro_chat_selected_pages').parent().parent().hide();
	});

	$("#chatbro_chat_display").change();

	$("#chatbro-settings-form").submit(function(event) {
		console.log("Submit!");
		if ($("#chatbro_chat_guid").val() != chatbro_secret_key) {
			console.log("key changed");
			// event.preventDefault();
		}
	});
});
