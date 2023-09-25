function uap_mautic_render_fields($button, data, modules) {

    // Add loading animation to the button
    $button.addClass('uap-btn--loading uap-btn--disabled');

    // Get the notices container
    let $noticesContainer = jQuery('.item[data-id="' + data.item.id + '"] .item-options__notices');

    $noticesContainer.html('');

    let $notice = jQuery('<div/>', {
        'class': 'item-options__notice item-options__notice--warning'
    });

    // Header repeater field.
    let $repeaterFields = data.item.options.CONTACT_UPSERT_META.fields[1];

    let queryParameters = {
        action: 'automator_mautic_retrieve_fields',
        nonce: UncannyAutomator.nonce,
        recipe_id: UncannyAutomator.recipe.id,
        item_id: data.item.id,
    }

    jQuery.ajax({
        type: "POST",
        dataType: "json",
        url: ajaxurl,
        data: queryParameters,
        // Success callback.
        success: function (response) {

            // Remove loading animation to the button
            $button.removeClass('uap-btn--loading uap-btn--disabled');

            if (response.success === false) {
                // Add notice message
                $notice.html('Unknown error');
                // Add notice
                $noticesContainer.html($notice);

            } else {

                // Populate fields.
                console.log(response);

            }
        },
        // Handle generic errors.
        error: function (response, code, message) {

            // Add notice message
            $notice.html(message);
            // Add notice
            $noticesContainer.html($notice);

            $button.removeClass('uap-btn--loading uap-btn--disabled');
        }
    });
}