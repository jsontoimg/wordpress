(function( $ ) {
	'use strict';

	$( function() {
		var $button = $( '#jsontoimg-test-connection' );
		var $result = $( '#jsontoimg-test-result' );

		if ( ! $button.length || typeof jsontoimgAdmin === 'undefined' ) {
			return;
		}

		$button.on( 'click', function( event ) {
			event.preventDefault();

			$button.prop( 'disabled', true );
			$result.removeClass( 'notice notice-success notice-error' ).text( '' );

			$.post( jsontoimgAdmin.ajaxUrl, {
				action: 'jsontoimg_test_connection',
				nonce: jsontoimgAdmin.nonce,
				api_key: $( '#jsontoimg_api_key' ).val(),
				base_url: $( '#jsontoimg_base_url' ).val()
			} )
				.done( function( response ) {
					var message = response && response.data && response.data.message
						? response.data.message
						: jsontoimgAdmin.i18n.failed;

					if ( response && response.success ) {
						$result.addClass( 'notice notice-success inline' ).text( message );
					} else {
						$result.addClass( 'notice notice-error inline' ).text( message );
					}
				} )
				.fail( function() {
					$result.addClass( 'notice notice-error inline' ).text( jsontoimgAdmin.i18n.failed );
				} )
				.always( function() {
					$button.prop( 'disabled', false );
				} );
		} );
	} );

})( jQuery );
