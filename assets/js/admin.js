(function($){
	'use strict';
	$(function(){
		var modal = $('#ai-featured-image-modal');
		var closeModal = $('.ai-modal-close');
		var errorContainer = $('#ai-modal-error-container');
		$('#ai-set-featured-image-button').prop('disabled', true).hide();

		function openModal(){ modal.show(); }
		function hideModal(){ modal.hide(); }

		$(document).on('click', '#ai-featured-image-generate-button', function(e){ e.preventDefault(); openModal(); });
		closeModal.on('click', hideModal);
		$(window).on('click', function(e){ if(e.target===modal[0]) hideModal(); });

		$('#ai-generate-image-button').on('click', function(){
			var button = $(this), original = button.text();
			button.text('Generating...').prop('disabled', true);
			errorContainer.hide().empty();
			$('#ai-image-preview-container').empty();
			$('#ai-loading').show();
			var data = { action:'generate_ai_image', post_id: aiFeaturedImageData.post_id, n: parseInt($('#ai-num-images').val()||'1',10), nonce: aiFeaturedImageData.nonce };
			$.post(aiFeaturedImageData.ajax_url, data, function(resp){
				if(resp && resp.success){
					var cont = $('#ai-image-preview-container');
					(resp.data.images||[]).forEach(function(img){
						var src = img.url ? img.url : (img.b64_json ? ('data:image/png;base64,'+img.b64_json) : '');
						if(!src) return;
						var el = $('<img>',{ src:src, 'data-b64': img.b64_json||'', 'data-src': src, css:{maxWidth:'150px',height:'auto',margin:'5px',cursor:'pointer',border:'2px solid transparent'} });
						cont.append(el);
					});
					$('#ai-image-preview-container img').on('click', function(){
						$('#ai-image-preview-container img').removeClass('selected').css('border','2px solid transparent');
						$(this).addClass('selected').css('border','2px solid #0073aa');
						$('#ai-set-featured-image-button').prop('disabled', false).show();
					});
				}else{
					errorContainer.text(resp && resp.data && resp.data.message ? resp.data.message : 'Generation failed').show();
				}
			}).fail(function(){ errorContainer.text('An unknown error occurred.').show(); })
			.always(function(){ $('#ai-loading').hide(); button.text(original).prop('disabled', false); });
		});

		function compressDataUrl(dataUrl, q, cb){
			try{ if(!dataUrl || dataUrl.indexOf('data:image/')!==0){ cb(null); return; }
				var img=new Image(); img.onload=function(){ var c=document.createElement('canvas'); var maxW=1024; var s=Math.min(1,maxW/img.width); c.width=Math.round(img.width*s); c.height=Math.round(img.height*s); var ctx=c.getContext('2d'); ctx.drawImage(img,0,0,c.width,c.height); var out=c.toDataURL('image/jpeg', q||0.85); cb(out); }; img.onerror=function(){cb(null)}; img.src=dataUrl; }catch(e){ cb(null); }
		}

		function sendUpload(payload){
			$.ajax({ url: aiFeaturedImageData.ajax_url, method:'POST', dataType:'json', data: payload })
			.done(function(resp){
				if(resp && resp.success){
					if(aiFeaturedImageData.is_gutenberg){ wp.data.dispatch('core/editor').editPost({ featured_media: resp.data.attachment_id }); }
					hideModal(); window.location.reload();
				}else{ errorContainer.text(resp && resp.data && resp.data.message ? resp.data.message : 'Upload failed').show(); }
			})
			.fail(function(jqXHR, text, err){ console.error('Upload failed', text, err, jqXHR && jqXHR.responseText); errorContainer.text('An unknown error occurred during upload.').show(); })
			.always(function(){ $('#ai-loading').hide(); $('#ai-loading .ai-loading-text').text('Generating...'); });
		}

		$('#ai-set-featured-image-button').on('click', function(){
			var sel = $('#ai-image-preview-container img.selected');
			if(!sel.length){ alert('Please select an image first.'); return; }
			var btn=$(this), orig=btn.text(); btn.text('Uploading...').prop('disabled', true); errorContainer.hide().empty(); $('#ai-loading .ai-loading-text').text('Uploading...'); $('#ai-loading').show();
			var payload = { action:'upload_ai_image', post_id: aiFeaturedImageData.post_id, nonce: aiFeaturedImageData.nonce };
			var dataSrc = sel.attr('data-src')||''; var b64 = sel.attr('data-b64');
			if((b64 && b64.length>0) || dataSrc.indexOf('data:image/')===0){
				compressDataUrl(dataSrc || ('data:image/png;base64,'+b64), 0.85, function(out){ if(!out){ payload.image_b64 = b64; sendUpload(payload); return; } var parts=out.split(','); var mime=(parts[0].match(/data:(.*?);base64/)||[null,'image/jpeg'])[1]; payload.image_b64 = parts[1]; payload.image_mime = mime; sendUpload(payload); });
			}else{ payload.image_url = sel.attr('src'); sendUpload(payload); }
			setTimeout(function(){ btn.text(orig).prop('disabled', false); }, 1500);
		});
	});
})(jQuery);
