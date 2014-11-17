jQuery( document ).ready(function() {


	jQuery(".hrld-inline-link").bind( "click", function(e) {
		
		e.preventDefault();

		url = jQuery( this ).attr( 'href' );

		jQuery.post(
			hrld_inline_click.ajaxurl,
			{
				action: "ajax-hrld_inline_click_script",
				nonce: hrld_inline_click.nonce,
				hrld_inline_url: url,					// The clicked URL.
				hrld_inline_id: hrld_inline_click.id	// The post ID.
			}, function( response ) { }
			);

		window.open(url,"_blank");

	});

});
