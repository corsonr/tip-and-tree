jQuery(document).ready(function($){
	jQuery( "#donate_charity" ).on( "click", function() {
		jQuery( 'input[name="update_cart"]' ).trigger( "click" );
	});
});