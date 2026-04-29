/**
 * Label test admin page scripts.
 */
( function() {
	'use strict';

	var labelData = null;

	var btn       = document.getElementById( 'cclee-shipping-generate-btn' );
	var spinner   = document.getElementById( 'cclee-shipping-spinner' );
	var resultDiv = document.getElementById( 'cclee-shipping-result' );
	var errorDiv  = document.getElementById( 'cclee-shipping-error' );
	var successDiv = document.getElementById( 'cclee-shipping-success' );

	var previewBtn = document.getElementById( 'cclee-shipping-preview-btn' );
	var downloadBtn = document.getElementById( 'cclee-shipping-download-btn' );
	var printBtn   = document.getElementById( 'cclee-shipping-print-btn' );
	var previewContainer = document.getElementById( 'cclee-shipping-preview-container' );
	var previewFrame = document.getElementById( 'cclee-shipping-preview-frame' );

	if ( ! btn ) {
		return;
	}

	btn.addEventListener( 'click', function() {
		btn.disabled = true;
		spinner.classList.add( 'is-active' );
		resultDiv.style.display = 'block';
		errorDiv.style.display = 'none';
		successDiv.style.display = 'none';
		previewContainer.style.display = 'none';
		labelData = null;

		var formData = new FormData();
		formData.append( 'action', 'cclee_shipping_test_label' );
		formData.append( 'nonce', ccleeShippingTools.nonce );

		fetch( ccleeShippingTools.ajax_url, {
			method: 'POST',
			body: formData
		} )
		.then( function( response ) { return response.json(); } )
		.then( function( res ) {
			spinner.classList.remove( 'is-active' );
			btn.disabled = false;

			if ( res.success ) {
				labelData = res.data.label;
				document.getElementById( 'cclee-shipping-tracking' ).textContent = res.data.tracking || '(none)';
				successDiv.style.display = 'block';
			} else {
				errorDiv.querySelector( 'p' ).textContent = res.data.message || 'Unknown error';
				errorDiv.style.display = 'block';
			}
		} )
		.catch( function( err ) {
			spinner.classList.remove( 'is-active' );
			btn.disabled = false;
			errorDiv.querySelector( 'p' ).textContent = err.message || 'Request failed';
			errorDiv.style.display = 'block';
		} );
	} );

	// Preview — embed PDF in iframe via data URI.
	previewBtn.addEventListener( 'click', function() {
		if ( ! labelData ) return;
		var pdfSrc = 'data:application/pdf;base64,' + labelData;
		previewFrame.src = pdfSrc;
		previewContainer.style.display = 'block';
	} );

	// Download — trigger file download.
	downloadBtn.addEventListener( 'click', function() {
		if ( ! labelData ) return;
		var byteChars = atob( labelData );
		var byteNumbers = new Array( byteChars.length );
		for ( var i = 0; i < byteChars.length; i++ ) {
			byteNumbers[i] = byteChars.charCodeAt( i );
		}
		var byteArray = new Uint8Array( byteNumbers );
		var blob = new Blob( [ byteArray ], { type: 'application/pdf' } );
		var url = URL.createObjectURL( blob );
		var a = document.createElement( 'a' );
		a.href = url;
		a.download = 'fedex-test-label.pdf';
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		URL.revokeObjectURL( url );
	} );

	// Print — open PDF in new window and print.
	printBtn.addEventListener( 'click', function() {
		if ( ! labelData ) return;
		var pdfSrc = 'data:application/pdf;base64,' + labelData;
		var printWin = window.open( pdfSrc, '_blank' );
		if ( printWin ) {
			printWin.addEventListener( 'load', function() {
				printWin.print();
			} );
		}
	} );

} )();
