
jQuery(document).on('click', '.wpem-help', function(e) {
    e.preventDefault();
    const context = jQuery(this).data('context');

    jQuery.get(ajaxurl, { action: 'wpem_help', context }, function(response) {
        if (response.success) {
            jQuery('#wpem-help-modal .wpem-help-title').text(response.data.title);
            jQuery('#wpem-help-modal .wpem-help-content').html(response.data.content);
            jQuery('#wpem-help-overlay, #wpem-help-modal').fadeIn(200);
        } else {
            alert('Keine Hilfe gefunden.');
        }
    });
});

// Schließen
jQuery(document).on('click', '#wpem-help-modal .close, #wpem-help-overlay', function() {
    jQuery('#wpem-help-overlay, #wpem-help-modal').fadeOut(200);
});
