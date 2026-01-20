/**
 * JG Banner Admin - Media Library Integration
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('[JG Banner Admin] Script loaded');
        console.log('[JG Banner Admin] wp.media available:', typeof wp !== 'undefined' && typeof wp.media !== 'undefined');

        // Media uploader for banner image
        var mediaUploader;

        $(document).on('click', '#jg-upload-banner-image', function(e) {
            e.preventDefault();
            console.log('[JG Banner Admin] Upload button clicked');

            // Check if wp.media is available
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert('WordPress Media Library nie jest załadowana. Odśwież stronę i spróbuj ponownie.');
                console.error('[JG Banner Admin] wp.media is not defined');
                return;
            }

            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            mediaUploader = wp.media({
                title: 'Wybierz baner 728x90',
                button: {
                    text: 'Użyj tego obrazka'
                },
                multiple: false
            });

            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                console.log('[JG Banner Admin] Image selected:', attachment.url);
                $('#banner_image_url').val(attachment.url);
                var previewContainer = $('#jg-banner-image-preview-container');
                previewContainer.html('<img src="' + attachment.url + '" alt="Preview">');
                previewContainer.show();
            });

            mediaUploader.open();
        });

        // Toggle edit form
        $('.jg-edit-banner').on('click', function(e) {
            e.preventDefault();
            var bannerId = $(this).data('id');
            $('#edit-form-' + bannerId).slideToggle();
        });

        // Confirm delete
        $('.jg-delete-banner').on('click', function(e) {
            if (!confirm('Czy na pewno chcesz usunąć ten baner? Ta operacja jest nieodwracalna.')) {
                e.preventDefault();
            }
        });
    });

})(jQuery);
