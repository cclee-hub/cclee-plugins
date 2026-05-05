/**
 * Label test admin page scripts.
 */
( function() {
	'use strict';

	var labelData = null;

	var form      = document.getElementById( 'cclee-shipping-label-form' );
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

	if ( ! form || ! btn ) {
		return;
	}

	form.addEventListener( 'submit', function( e ) {
		e.preventDefault();
		btn.disabled = true;
		spinner.classList.add( 'is-active' );
		resultDiv.style.display = 'block';
		errorDiv.style.display = 'none';
		successDiv.style.display = 'none';
		previewContainer.style.display = 'none';
		labelData = null;

		var formData = new FormData( form );

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

	// Preview — show ZPL text content.
	previewBtn.addEventListener( 'click', function() {
		if ( ! labelData ) return;
		var zplText = atob( labelData );
		previewFrame.style.display = 'none';
		var textEl = document.getElementById( 'cclee-shipping-preview-text' );
		if ( ! textEl ) {
			textEl = document.createElement( 'pre' );
			textEl.id = 'cclee-shipping-preview-text';
			textEl.style.cssText = 'background:#f0f0f1;padding:12px;max-height:600px;overflow:auto;white-space:pre-wrap;word-break:break-all;font-size:12px;border:1px solid #ccc;';
			previewContainer.appendChild( textEl );
		}
		textEl.textContent = zplText;
		previewContainer.style.display = 'block';
	} );

	// Download — save as .zpl file.
	downloadBtn.addEventListener( 'click', function() {
		if ( ! labelData ) return;
		var zplText = atob( labelData );
		var blob = new Blob( [ zplText ], { type: 'text/plain' } );
		var url = URL.createObjectURL( blob );
		var a = document.createElement( 'a' );
		a.href = url;
		a.download = 'fedex-test-label.zpl';
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		URL.revokeObjectURL( url );
	} );

	// Print — ZPL requires thermal printer, disable button.
	printBtn.disabled = true;
	printBtn.title = 'ZPL labels require a thermal printer';

} )();
