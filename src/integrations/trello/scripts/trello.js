function automator_trello_get_custom_fields( $button, data, modules ) {

    // Add loading animation to the button
    $button.addClass('uap-btn--loading uap-btn--disabled');

    // Get the notices container
    let $noticesContainer = jQuery('.item[data-id="' + data.item.id + '"] .item-options__notices');
    $noticesContainer.html( '' );

    let $notice = jQuery('<div/>', {
        'class': 'item-options__notice item-options__notice--warning'
    });

    let $repeaterField = data.item.options.CARD_NAME.fields.find( field => {
        return field.attributes.optionCode === 'CUSTOMFIELDS';
    });

    let sendData = {
        action: 'automator_trello_api_get_custom_fields',
        nonce: UncannyAutomator._site.rest.nonce,
        recipe_id: UncannyAutomator._recipe.recipe_id,
        item_id: data.item.id,
        board_id: data.values.BOARD
    }

    console.log(sendData)

    jQuery.ajax({
        type: "POST",
        dataType: "json",
        url: ajaxurl,
        data: sendData,
        success: function( response ){
            console.log(response)
            // Remove loading animation to the button
            $button.removeClass( 'uap-btn--loading uap-btn--disabled' );

            if ( response.success === false ) {
                // Add notice message
                $notice.html( response.data[0].code + ': ' + response.data[0].message );
                // Add notice
                $noticesContainer.html( $notice );
            } else {
                automator_trello_populate_repeater( $repeaterField, response )
            }
        }
    });
}

function automator_trello_populate_repeater( repeaterField, response ) {

    // Remove all the current fields
    repeaterField.fieldRows = [];

    jQuery.each( response.data, function ( index, field ) {
        
        repeaterField.addRow({
            FIELD_NAME: field.name,
            FIELD_OBJ: JSON.stringify( field )
        }, false);
    
    });

    // Render again
    repeaterField.reRender();
}
