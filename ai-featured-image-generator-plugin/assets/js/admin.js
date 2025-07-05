(function($) {
    'use strict';

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

        // Generate button click - Use event delegation for dynamically added modal
        $(document).on('click', '#ai-generate-image-button', function() {
            console.log('Generate button clicked.'); // Debugging line
            var button = $(this);
            var originalText = button.text();
            button.text('Generating...').prop('disabled', true);
            errorContainer.hide().empty();
            $('#ai-image-preview-container').empty();

            var data = {
                action: 'generate_ai_image',
                post_id: aiFeaturedImageData.post_id,
                style: $('#ai-image-style').val(),
                quality: $('#ai-image-quality').val(),
                nonce: aiFeaturedImageData.nonce
            };

            console.log('Sending data to server:', data); // Debugging line

            $.post(aiFeaturedImageData.ajax_url, data, function(response) {
                console.log('Received response from server:', response); // Debugging line
                if (response.success) {
                    var previewContainer = $('#ai-image-preview-container');
                    previewContainer.empty();

                    if (response.data.images && response.data.images.length > 0) {
                        response.data.images.forEach(function(image) {
                            var imgElement = $('<img>', {
                                src: image.url,
                                'data-b64': image.b64_json, // If you get base64 data
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

                        $('#ai-image-preview-container img').on('click', function() {
                            $('#ai-image-preview-container img').removeClass('selected');
                            $(this).addClass('selected');
                            $('#ai-set-featured-image-button').show();
                        });
                    } else {
                        previewContainer.text('No images were generated.');
                    }
                } else {
                    errorContainer.text(response.data.message).show();
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX request failed:', textStatus, errorThrown); // Debugging line
                errorContainer.text('An unknown error occurred. Check the browser console for details.').show();
            }).always(function() {
                button.text(originalText).prop('disabled', false);
            });
        });

        // Set Featured Image button click - Use event delegation
        $(document).on('click', '#ai-set-featured-image-button', function() {
            var selectedImage = $('#ai-image-preview-container img.selected');
            if (selectedImage.length === 0) {
                alert('Please select an image first.');
                return;
            }

            var button = $(this);
            var originalText = button.text();
            button.text('Uploading...').prop('disabled', true);
            errorContainer.hide().empty();

            var data = {
                action: 'upload_ai_image',
                image_url: selectedImage.attr('src'),
                post_id: aiFeaturedImageData.post_id,
                nonce: aiFeaturedImageData.nonce
            };

            $.post(aiFeaturedImageData.ajax_url, data, function(response) {
                if (response.success) {
                    // Update the featured image in the editor
                    if (aiFeaturedImageData.is_gutenberg) {
                        wp.data.dispatch('core/editor').editPost({ featured_media: response.data.attachment_id });
                    } else {
                        // For classic editor
                        $('#set-post-thumbnail').html('<img src="' + response.data.thumbnail_url + '" />');
                        $('#remove-post-thumbnail').show();
                    }
                    hideModal();
                } else {
                    errorContainer.text(response.data.message).show();
                }
            }).fail(function() {
                errorContainer.text('An unknown error occurred during upload.').show();
            }).always(function() {
                button.text(originalText).prop('disabled', false);
            });
        });

    });

})(jQuery); 