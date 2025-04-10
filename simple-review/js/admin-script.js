jQuery(document).ready(function($) {
    // Ouvrir la médiathèque au clic sur le bouton
    $('.sr-upload-image-button').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var input = button.closest('.form-field').find('#sr_category_image');
        var preview = button.closest('.form-field').find('.sr-image-preview');

        var frame = wp.media({
            title: 'Choisir une image',
            button: { text: 'Utiliser cette image' },
            multiple: false
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            input.val(attachment.url);
            preview.html('<img src="' + attachment.url + '" style="max-width: 200px; height: auto;" /><p><a href="#" class="sr-remove-image">Supprimer l’image</a></p>');
        });

        frame.open();
    });

    // Supprimer l’image au clic sur le lien
    $(document).on('click', '.sr-remove-image', function(e) {
        e.preventDefault();
        var preview = $(this).closest('.sr-image-preview');
        var input = preview.closest('.form-field').find('#sr_category_image');
        input.val('');
        preview.empty();
    });
});