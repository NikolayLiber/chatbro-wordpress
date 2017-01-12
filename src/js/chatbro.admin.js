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

	$(".control-message").hide();

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
			rrr = response;
			console.log(response);

			var msgDiv = $("#chatbro-message");
			msgDiv.removeClass();
			msgDiv.html("");

			if (response.hasOwnProperty("message")) {
				if (response.hasOwnProperty("msg_type") && response["msg_type"] == "error") {
					msgDiv.addClass("bs-callout-small bs-callout-small-danger");
				}
				else {
					msgDiv.addClass("bs-callout-small bs-callout-small-success");
				}

				msgDiv.html(response['message']);
			}

			$(".control-message").hide();
			$(".form-group").removeClass("has-error has-feedback");

			if (response.hasOwnProperty("field_messages")) {
				console.log("Has messages\n");
				Object.keys(response.field_messages).forEach(function(id) {
					console.log("#" + id + "-message > span\n");
					var m = response.field_messages[id];

					$("#" + id + "-message > span").html(m.message);
					$("#" + id + "-message").show();

					if (m.type == "error")
						$("#" + id + "-group").addClass("has-error has-feedback");
				});
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
