/**
 * CCLEE Toolkit Admin Settings JS
 *
 * @package CCLEE_Toolkit
 */

(function() {
	// AI toggle -> ALT auto checkbox linkage (batch section is independent)
	var aiToggle = document.getElementById('cclee-ai-toggle');
	var altAutoToggle = document.getElementById('cclee-alt-auto-toggle');
	var altAutoHint = document.getElementById('cclee-alt-auto-hint');
	var altBatchSection = document.getElementById('cclee-alt-batch-section');
	var altBatchToggle = document.querySelector('input[name="cclee_toolkit_alt_batch_enabled"]');
	if (aiToggle) {
		aiToggle.addEventListener('change', function() {
			var on = aiToggle.checked;
			altAutoToggle.disabled = !on;
			altAutoHint.style.display = on ? 'none' : '';
		});
	}
	if (altBatchToggle) {
		altBatchToggle.addEventListener('change', function() {
			altBatchSection.style.display = altBatchToggle.checked ? '' : 'none';
		});
	}

	// IndexNow Generate Key
	var btn = document.getElementById('cclee-indexnow-generate');
	if (btn) {
		btn.addEventListener('click', function() {
			var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
			var key = '';
			for (var i = 0; i < 32; i++) {
				key += chars.charAt(Math.floor(Math.random() * chars.length));
			}
			document.getElementById('cclee_toolkit_seo_indexnow_key').value = key;
		});
	}

	// Manual URL Submission
	var submitBtn = document.getElementById('cclee-manual-submit');
	if (submitBtn) {
		submitBtn.addEventListener('click', function() {
			var url = document.getElementById('cclee-manual-url').value.trim();
			var channels = [];
			if (document.getElementById('cclee-manual-indexnow').checked) channels.push('indexnow');
			if (document.getElementById('cclee-manual-google').checked) channels.push('google');

			var resultEl = document.getElementById('cclee-manual-result');
			resultEl.style.display = 'block';
			resultEl.style.color = '';
			resultEl.textContent = ccleeToolkitAdmin.i18n.submitting;
			submitBtn.disabled = true;

			jQuery.post(ajaxurl, {
				action: 'cclee_manual_submit_url',
				nonce: ccleeToolkitAdmin.manualSubmitNonce,
				url: url,
				channels: channels
			}, function(response) {
				submitBtn.disabled = false;
				if (response.success) {
					resultEl.style.color = 'green';
					resultEl.textContent = response.data.message;
				} else {
					resultEl.style.color = 'red';
					resultEl.textContent = response.data.message;
				}
			}).fail(function() {
				submitBtn.disabled = false;
				resultEl.style.color = 'red';
				resultEl.textContent = ccleeToolkitAdmin.i18n.requestFailed;
			});
		});
	}

	// AI Test Connection
	var aiTestBtn = document.getElementById('cclee-ai-test-btn');
	if (aiTestBtn) {
		aiTestBtn.addEventListener('click', function() {
			var apiKey   = document.getElementById('cclee_toolkit_ai_api_key').value.trim();
			var provider = document.getElementById('cclee_toolkit_ai_provider').value;
			var model    = document.querySelector('input[name="cclee_toolkit_ai_model"]').value.trim();
			var baseUrl  = document.querySelector('input[name="cclee_toolkit_ai_base_url"]').value.trim();
			var resultEl = document.getElementById('cclee-ai-test-result');

			if (!apiKey) {
				resultEl.style.display = 'block';
				resultEl.style.color = '#d63638';
				resultEl.textContent = 'API Key is required.';
				return;
			}

			resultEl.style.display = 'block';
			resultEl.style.color = '';
			resultEl.textContent = ccleeToolkitAdmin.i18n.testing;
			aiTestBtn.disabled = true;

			jQuery.ajax({
				url: ccleeToolkitAdmin.aiTestUrl,
				method: 'POST',
				beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', ccleeToolkitAdmin.restNonce); },
				data: JSON.stringify({ api_key: apiKey, provider: provider, model: model, base_url: baseUrl }),
				contentType: 'application/json',
				success: function(data) {
					aiTestBtn.disabled = false;
					resultEl.style.color = '#00a32a';
					var strong = document.createElement('strong');
					strong.textContent = ccleeToolkitAdmin.i18n.testSuccess;
					resultEl.textContent = '';
					resultEl.appendChild(strong);
					resultEl.appendChild(document.createTextNode(' (' + data.model + ', ' + data.time + ') \u2014 "' + data.content + '"'));
				},
				error: function(xhr) {
					aiTestBtn.disabled = false;
					resultEl.style.color = '#d63638';
					var msg = ccleeToolkitAdmin.i18n.testFailed;
					if (xhr.responseJSON && xhr.responseJSON.message) {
						msg += ': ' + xhr.responseJSON.message;
					}
					var strong = document.createElement('strong');
					strong.textContent = msg;
					resultEl.textContent = '';
					resultEl.appendChild(strong);
				}
			});
		});
	}

	// Alt Batch Processing (auto-continue + result modal)
	var batchBtn = document.getElementById('cclee-alt-batch-btn');
	var altSaveUrl = ccleeToolkitAdmin.altSaveUrl;
	var altBatchUrl = ccleeToolkitAdmin.altBatchUrl;
	var altNonce = ccleeToolkitAdmin.restNonce;

	if (batchBtn) {
		var allItems = [];
		var allFailedItems = [];

		batchBtn.addEventListener('click', function() {
			var batchSize = document.getElementById('cclee-alt-batch-size').value || 10;
			var resultEl = document.getElementById('cclee-alt-batch-result');
			var sizeInput = document.getElementById('cclee-alt-batch-size');
			var totalSuccess = 0;
			var totalFailed = 0;
			var totalProcessed = 0;
			var isRunning = false;

			resultEl.style.display = 'block';
			resultEl.style.color = '';
			batchBtn.disabled = true;
			batchBtn.textContent = ccleeToolkitAdmin.i18n.processing;
			sizeInput.disabled = true;
			isRunning = true;
			allItems = [];
			allFailedItems = [];

			function runBatch() {
				if (!isRunning) return;
				jQuery.ajax({
					url: altBatchUrl,
					method: 'POST',
					beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', altNonce); },
					data: JSON.stringify({ batch_size: parseInt(batchSize), offset: totalProcessed }),
					contentType: 'application/json',
					success: function(data) {
						totalSuccess += data.success;
						totalFailed += data.failed;
						totalProcessed += data.processed;
						if (data.items) allItems = allItems.concat(data.items);
						if (data.failed_items) allFailedItems = allFailedItems.concat(data.failed_items);
						resultEl.textContent =
							ccleeToolkitAdmin.i18n.done + ': ' + (totalSuccess + totalFailed) +
							' | ' + ccleeToolkitAdmin.i18n.success + ': ' + totalSuccess +
							' | ' + ccleeToolkitAdmin.i18n.failed + ': ' + totalFailed +
							' | ' + ccleeToolkitAdmin.i18n.remaining + ': ' + data.remaining;
						if (data.remaining > 0) {
							setTimeout(runBatch, 300);
						} else {
							isRunning = false;
							batchBtn.textContent = ccleeToolkitAdmin.i18n.allDone;
							sizeInput.disabled = false;
							if (allItems.length > 0) showAltModal(allItems, allFailedItems);
						}
					},
					error: function() {
						isRunning = false;
						sizeInput.disabled = false;
						resultEl.style.color = 'red';
						resultEl.textContent = ccleeToolkitAdmin.i18n.requestFailed +
							' (' + ccleeToolkitAdmin.i18n.done + ': ' + (totalSuccess + totalFailed) +
							' | ' + ccleeToolkitAdmin.i18n.success + ': ' + totalSuccess +
							' | ' + ccleeToolkitAdmin.i18n.failed + ': ' + totalFailed + ')';
						batchBtn.textContent = ccleeToolkitAdmin.i18n.continueBatch;
						batchBtn.disabled = false;
					}
				});
			}
			runBatch();
		});

		function showAltModal(items, failedItems) {
			var overlay = document.getElementById('cclee-alt-modal-overlay');
			var body = document.getElementById('cclee-alt-modal-body');
			var title = document.getElementById('cclee-alt-modal-title');
			if (!overlay || !body) return;
			body.textContent = '';
			title.textContent = ccleeToolkitAdmin.i18n.batchAltResults + ' (' + items.length + ')';
			for (var i = 0; i < items.length; i++) {
				var item = items[i];
				var row = document.createElement('div');
				row.style.cssText = 'display:flex;align-items:center;gap:10px;margin-bottom:8px;';
				if (item.thumbnail) {
					var thumb = document.createElement('div');
					thumb.style.cssText = 'background:url(' + item.thumbnail + ') center/cover;width:40px;height:40px;border:1px solid #dcdcde;border-radius:2px;flex-shrink:0;';
					row.appendChild(thumb);
				}
				var info = document.createElement('div');
				info.style.cssText = 'flex:1;min-width:0;';
				var fname = document.createElement('div');
				fname.style.cssText = 'font-size:12px;color:#50575e;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
				fname.title = item.filename;
				fname.textContent = item.filename;
				info.appendChild(fname);
				var input = document.createElement('input');
				input.type = 'text';
				input.className = 'cclee-alt-edit-input';
				input.dataset.id = item.attachment_id;
				input.value = item.alt;
				input.style.cssText = 'width:100%;padding:4px 8px;font-size:13px;box-sizing:border-box;';
				info.appendChild(input);
				row.appendChild(info);
				var saveBtn = document.createElement('button');
				saveBtn.type = 'button';
				saveBtn.className = 'button button-small cclee-alt-save-btn';
				saveBtn.dataset.id = item.attachment_id;
				saveBtn.style.flexShrink = '0';
				saveBtn.textContent = ccleeToolkitAdmin.i18n.save;
				(function(btn, inp) {
					btn.addEventListener('click', function() {
						var id = btn.dataset.id;
						var altVal = inp.value;
						btn.disabled = true;
						btn.textContent = ccleeToolkitAdmin.i18n.saving;
						jQuery.ajax({
							url: altSaveUrl,
							method: 'POST',
							beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', altNonce); },
							data: JSON.stringify({ attachment_id: parseInt(id), alt: altVal }),
							contentType: 'application/json',
							success: function(res) {
								if (res.success) {
									btn.textContent = ccleeToolkitAdmin.i18n.saved;
									btn.classList.add('button-primary');
								} else {
									btn.disabled = false;
									btn.textContent = ccleeToolkitAdmin.i18n.save;
								}
							},
							error: function() {
								btn.disabled = false;
								btn.textContent = ccleeToolkitAdmin.i18n.save;
							}
						});
					});
				})(saveBtn, input);
				row.appendChild(saveBtn);
				body.appendChild(row);
			}
			if (failedItems.length > 0) {
				var failSection = document.createElement('div');
				failSection.style.cssText = 'margin-top:12px;padding-top:12px;border-top:1px solid #dcdcde;';
				var failTitle = document.createElement('p');
				failTitle.style.cssText = 'margin:0 0 8px;font-size:13px;color:#d63638;font-weight:600;';
				failTitle.textContent = ccleeToolkitAdmin.i18n.failedItems + ':';
				failSection.appendChild(failTitle);
				for (var j = 0; j < failedItems.length; j++) {
					var failLine = document.createElement('div');
					failLine.style.cssText = 'font-size:12px;color:#d63638;margin-bottom:4px;';
					var strong = document.createElement('strong');
					strong.textContent = failedItems[j].filename;
					failLine.appendChild(strong);
					failLine.appendChild(document.createTextNode(': ' + failedItems[j].reason));
					failSection.appendChild(failLine);
				}
				body.appendChild(failSection);
			}
			overlay.style.display = 'flex';
		}

		var modalOverlay = document.getElementById('cclee-alt-modal-overlay');
		if (modalOverlay) {
			document.getElementById('cclee-alt-modal-close').addEventListener('click', function() { modalOverlay.style.display = 'none'; });
			document.getElementById('cclee-alt-modal-done').addEventListener('click', function() { modalOverlay.style.display = 'none'; });
			modalOverlay.addEventListener('click', function(e) { if (e.target === modalOverlay) modalOverlay.style.display = 'none'; });
		}
	}
})();
