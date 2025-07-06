(function($) {
    'use strict';
    console.log('DEBUG: admin.js script loaded and executing.');

    $(function() {
        var modal = $('#ai-featured-image-modal');
        var closeModal = $('.ai-modal-close');
        var errorContainer = $('#ai-modal-error-container');
        var spinner = modal.find('.spinner');

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
            console.log('DEBUG: Generate button clicked.');
            var button = $(this);
            button.prop('disabled', true);
            spinner.addClass('is-active');
            errorContainer.hide().empty();
            $('#ai-image-preview-container').empty();

            var data = {
                action: 'generate_ai_image',
                post_id: aiFeaturedImageData.post_id,
                style: $('#ai-image-style').val(),
                quality: $('#ai-image-quality').val(),
                nonce: aiFeaturedImageData.nonce
            };

            $.post(aiFeaturedImageData.ajax_url, data, function(response) {
                if (response.success) {
                    var previewContainer = $('#ai-image-preview-container');
                    previewContainer.empty();

                    if (response.data.images && response.data.images.length > 0) {
                        response.data.images.forEach(function(image) {
                            var imgElement = $('<img>', {
                                src: image.url,
                                'data-attachment-id': image.attachment_id,
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
                console.error('DEBUG: AJAX request failed. Status:', textStatus, 'Error:', errorThrown);
                console.error('DEBUG: Server Response:', jqXHR.responseText);
                errorContainer.text('An unknown server error occurred. Check the browser console for details.').show();
            }).always(function() {
                button.prop('disabled', false);
                spinner.removeClass('is-active');
            });
        });

        // Set Featured Image button click - Use event delegation
        $(document).on('click', '#ai-set-featured-image-button', function() {
            console.log('DEBUG: "Set as Featured Image" button clicked.');
            var selectedImage = $('#ai-image-preview-container img.selected');
            
            if (selectedImage.length === 0) {
                console.log('DEBUG: No image selected.');
                alert('Please select an image first.');
                return;
            }
            
            var attachmentId = selectedImage.data('attachment-id');
            console.log('DEBUG: Attachment ID found:', attachmentId);

            if (!attachmentId) {
                console.error('DEBUG: Attachment ID is missing!');
                errorContainer.text('Could not find attachment ID. Please try regenerating the image.').show();
                return;
            }

            // Set the featured image in the editor
            if (aiFeaturedImageData.is_gutenberg) {
                console.log('DEBUG: Gutenberg editor detected. Dispatching action.');
                wp.data.dispatch('core/editor').editPost({ featured_media: attachmentId });
            } else {
                console.log('DEBUG: Classic editor detected. Sending AJAX request.');
                // For classic editor, we need a different approach.
                // We'll use a simple AJAX call to set the thumbnail.
                 $.post(aiFeaturedImageData.ajax_url, {
                    action: 'set_ai_featured_image',
                    post_id: aiFeaturedImageData.post_id,
                    attachment_id: attachmentId,
                    nonce: aiFeaturedImageData.nonce
                }, function(response) {
                    console.log('DEBUG: AJAX success. Response:', response);
                    if (response.success) {
                        // 1. Set the hidden input value. This is THE CRUCIAL step for saving the post.
                        // This ensures the change is saved when the user clicks "Update".
                        $('#_thumbnail_id').val(attachmentId);

                        // 2. Update the visual representation in the meta box.
                        // The AJAX response contains the new HTML for the image preview.
                        $('#set-post-thumbnail').html(response.data.thumbnail_html);

                        // 3. Show the "remove" link. It might be hidden by a class or display:none.
                        $('#remove-post-thumbnail').removeClass('hidden').show();
                        
                    } else {
                        console.error('DEBUG: AJAX request returned an error.', response.data.message);
                        errorContainer.text(response.data.message).show();
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('DEBUG: AJAX request failed. Status:', textStatus, 'Error:', errorThrown);
                    console.error('DEBUG: Server Response:', jqXHR.responseText);
                    errorContainer.text('An unknown server error occurred while setting the image.').show();
                });
            }
            
            console.log('DEBUG: Closing modal.');
            hideModal();
        });

    });

})(jQuery); 