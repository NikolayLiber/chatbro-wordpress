jQuery(document).ready(function($) {
	var t = ['except_listed', 'only_listed'];

	$("#chatbro_chat_display").change(function() {
		if (t.indexOf($("#chatbro_chat_display").val()) != -1)
			$('#chatbro_chat_selected_pages-group').show();
		else
			$('#chatbro_chat_selected_pages-group').hide();
	});

	$("#chatbro_chat_display").change();

	$("#settings-tabs a").click(function(e) {
		e.preventDefault();
		$(this).tab('show');
	});

	$("#chatbro-settings-form").ajaxForm({
		url: ajaxurl,
		type: 'POST',

		beforeSubmit: function() {
			disableSubmit();
			return true;
		},

		success: function(response) {
			if (response == "0") {
				window.location = $("#chb-login-url").val();
				return;
			}

			response = JSON.parse(response);

			console.log(response);

			var msgDiv = $("#chatbro-message");
			msgDiv.removeClass();
			msgDiv.html("");

			if (response.hasOwnProperty("message")) {
				console.log("has message");
				if (response.hasOwnProperty("msg_type") && response["msg_type"] == "error") {
					console.log("adding danger")
					msgDiv.addClass("alert alert-danger");
				}
				else {
					msgDiv.addClass("alert alert-success");
					console.log("adding success");
				}

				msgDiv.html(response['message']);
			}

			enableSubmit();
		}
	});

	function disableSubmit() {
		$("#chatbro-save").button('saving').addClass('disabled').blur();
	}

	function enableSubmit() {
		$("#chatbro-save").button('reset').removeClass('disabled');
	}
});
