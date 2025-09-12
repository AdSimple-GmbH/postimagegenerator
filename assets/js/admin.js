(function($) {
    'use strict';

    function addKeywordButton(container) {
        if (container.length && container.find('.ai-keyword-button').length === 0) {
            var button = $('<button>', {
                type: 'button',
                class: 'button button-small ai-keyword-button',
                text: aiFeaturedImageData.i18n.add_keywords_button
            }).css('margin-left', '10px');
            container.append(button);
        }
    }

    $(function() {
        var modal = $('#ai-featured-image-modal');
        var closeModal = $('.ai-modal-close');
        var errorContainer = $('#ai-modal-error-container');

        // Function to open the modal
        function openModal() {
            modal.show();
        }

        // Function to close the modal
        function hideModal() {
            modal.hide();
        }

        // Event listener for the close button
        closeModal.on('click', hideModal);

        // Event listener for clicks outside the modal content
        $(window).on('click', function(event) {
            if (event.target === modal[0]) {
                hideModal();
            }
        });
        
        // Classic Editor Button
        $(document).on('click', '#ai-featured-image-generate-button', function(e) {
            e.preventDefault();
            openModal();
        });

        // Gutenberg / Block Editor Button
        // Use a MutationObserver because the button is rendered with React
        var observer = new MutationObserver(function(mutations, me) {
            var gutenbergButton = $('#ai-featured-image-generate-button-gutenberg');
            if (gutenbergButton.length) {
                gutenbergButton.off('click').on('click', function(e) {
                    e.preventDefault();
                    openModal();
                });
                // me.disconnect(); // We keep observing in case React re-renders the button
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // -- AI Keyword generator button insertion --
        
        // Classic Editor
        var classicTagsBox = $('#tagsdiv-post_tag .inside');
        if (classicTagsBox.length) {
            addKeywordButton(classicTagsBox.find('.tagadd'));
        }

        // Gutenberg Editor
        var gutenbergObserver = new MutationObserver(function(mutations, me) {
            var $gutenbergTagsPanel = $("h2.components-panel__body-title button:contains('Schlagwörter')").parent();
            if ($gutenbergTagsPanel.length) {
                addKeywordButton($gutenbergTagsPanel);
                // me.disconnect(); // We could disconnect, but let's keep it in case of re-renders
            }
        });

        gutenbergObserver.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Keyword generator button click
        $(document).on('click', '.ai-keyword-button', function() {
            var button = $(this);
            var originalText = button.text();
            button.text(aiFeaturedImageData.i18n.generating_keywords).prop('disabled', true);

            var data = {
                action: 'generate_ai_keywords',
                post_id: aiFeaturedImageData.post_id,
                nonce: aiFeaturedImageData.nonce
            };

            $.post(aiFeaturedImageData.ajax_url, data, function(response) {
                if (response.success) {
                    if (aiFeaturedImageData.is_gutenberg) {
                        // Gutenberg: Get existing tags, merge with new ones, and update.
                        var existingTags = wp.data.select('core/editor').getEditedPostAttribute('tags');
                        var newKeywords = response.data.keywords.split(',').map(function(item) { return item.trim(); });
                        
                        // This part is complex because we need to resolve/create tags.
                        // A simpler approach for now is to alert the user. A better UX would be to create/assign them.
                        alert("Suggested Keywords:\n" + response.data.keywords);
                        // For a full implementation, we'd need to use wp.data.dispatch('core').createEntityRecord for new tags.

                    } else {
                        // Classic Editor: Append keywords to the input field
                        var input = $('#new-tag-post_tag');
                        var currentTags = input.val();
                        var newTags = currentTags ? currentTags + ',' + response.data.keywords : response.data.keywords;
                        input.val(newTags);
                    }
                } else {
                    alert('Error: ' + response.data.message);
                }
            }).fail(function() {
                alert('An unknown error occurred.');
            }).always(function() {
                button.text(originalText).prop('disabled', false);
            });
        });

        // Generate button click
        $('#ai-generate-image-button').on('click', function() {
            var button = $(this);
            var originalText = button.text();
            button.text(aiFeaturedImageData.i18n.generating_keywords || 'Generating...').prop('disabled', true);
            errorContainer.hide().empty();
            $('#ai-image-preview-container').empty();
            $('#ai-loading').show();

            var data = {
                action: 'generate_ai_image',
                post_id: aiFeaturedImageData.post_id,
                n: parseInt($('#ai-num-images').val() || '1', 10),
                nonce: aiFeaturedImageData.nonce
            };

            $.post(aiFeaturedImageData.ajax_url, data, function(response) {
                if (response.success) {
                    var previewContainer = $('#ai-image-preview-container');
                    previewContainer.empty();

                    if (response.data.images && response.data.images.length > 0) {
                        response.data.images.forEach(function(image) {
                            var src = image.url ? image.url : (image.b64_json ? ('data:image/png;base64,' + image.b64_json) : '');
                            if (!src) { return; }
                            var imgElement = $('<img>', {
                                src: src,
                                'data-b64': image.b64_json || '',
                                'data-src': src,
                                css: {
                                    'max-width': '150px',
                                    'height': 'auto',
                                    'margin': '5px',
                                    'cursor': 'pointer',
                                    'border': '2px solid transparent'
                                }
                            });
                            previewContainer.append(imgElement);
                        });

                        // Generate success: bind selection click only
                        // ... inside success where images appended ...
                        $('#ai-image-preview-container img').on('click', function() {
                            $('#ai-image-preview-container img').removeClass('selected');
                            $(this).addClass('selected');
                            $('#ai-set-featured-image-button').prop('disabled', false).show();
                            console.debug('Preview selected, waiting for explicit confirm click');
                        });
                    } else {
                        previewContainer.text('No images were generated.');
                    }
                } else {
                    errorContainer.text(response.data.message).show();
                }
            }).fail(function() {
                errorContainer.text('An unknown error occurred.').show();
            }).always(function() {
                $('#ai-loading').hide();
                button.text(originalText).prop('disabled', false);
            });
        });

        function startUpload(selectedImage) {
            if (!selectedImage || !selectedImage.length) {
                return;
            }
            $('#ai-loading .ai-loading-text').text('Uploading...');
            $('#ai-loading').show();

            var payload = {
                action: 'upload_ai_image',
                post_id: aiFeaturedImageData.post_id,
                nonce: aiFeaturedImageData.nonce
            };

            var dataSrc = selectedImage.attr('data-src') || '';
            var b64 = selectedImage.attr('data-b64');
            if ((b64 && b64.length > 0) || (dataSrc.indexOf('data:image/') === 0)) {
                // Compress in browser to JPEG to reduce payload size
                compressDataUrl(dataSrc || ('data:image/png;base64,' + b64), 0.85, function(compressed) {
                    if (!compressed) {
                        // Fallback to original
                        payload.image_b64 = b64;
                        sendUpload(payload);
                        return;
                    }
                    var parts = compressed.split(',');
                    var mime = (parts[0].match(/data:(.*?);base64/) || [null, 'image/jpeg'])[1];
                    payload.image_b64 = parts[1];
                    payload.image_mime = mime;
                    sendUpload(payload);
                });
                return; // will continue in callback
            } else {
                payload.image_url = selectedImage.attr('src');
                sendUpload(payload);
            }
        }

        function sendUpload(payload) {
            $.ajax({
                url: aiFeaturedImageData.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: payload
            }).done(function(response) {
                if (response && response.success) {
                    if (aiFeaturedImageData.is_gutenberg) {
                        wp.data.dispatch('core/editor').editPost({ featured_media: response.data.attachment_id });
                    } else {
                        $('#set-post-thumbnail').html('<img src="' + response.data.thumbnail_url + '" />');
                        $('#remove-post-thumbnail').show();
                    }
                    hideModal();
                    window.location.reload();
                } else {
                    var msg = response && response.data && response.data.message ? response.data.message : 'Upload failed';
                    errorContainer.text(msg).show();
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('Upload failed', textStatus, errorThrown, jqXHR && jqXHR.responseText);
                errorContainer.text('An unknown error occurred during upload.').show();
            }).always(function() {
                $('#ai-loading').hide();
                $('#ai-loading .ai-loading-text').text('Generating...');
            });
        }

        function compressDataUrl(dataUrl, quality, cb) {
            try {
                if (!dataUrl || dataUrl.indexOf('data:image/') !== 0) { cb(null); return; }
                var img = new Image();
                img.onload = function() {
                    var canvas = document.createElement('canvas');
                    var maxW = 1024; // limit to reduce size
                    var scale = Math.min(1, maxW / img.width);
                    canvas.width = Math.round(img.width * scale);
                    canvas.height = Math.round(img.height * scale);
                    var ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    var out = canvas.toDataURL('image/jpeg', quality || 0.85);
                    cb(out);
                };
                img.onerror = function(){ cb(null); };
                img.src = dataUrl;
            } catch (e) {
                console.error('compressDataUrl error', e);
                cb(null);
            }
        }

        // Initialize state: disable confirm button until selection exists
        $('#ai-set-featured-image-button').prop('disabled', true).hide();

        $('#ai-set-featured-image-button').on('click', function() {
            var selectedImage = $('#ai-image-preview-container img.selected');
            if (selectedImage.length === 0) {
                alert('Please select an image first.');
                return;
            }

            var button = $(this);
            var originalText = button.text();
            button.text('Uploading...').prop('disabled', true);
            errorContainer.hide().empty();
            startUpload(selectedImage);
            // Button state will be reset in AJAX always handler via spinner reset
            setTimeout(function(){
                button.text(originalText).prop('disabled', false);
            }, 1500);
        });

    });

})(jQuery); 