function uap_zoom_get_webinar_questions( $button, data, modules ) {

    // Add loading animation to the button
    $button.addClass('uap-btn--loading uap-btn--disabled');

    // Get the notices container
    let $noticesContainer = jQuery('.item[data-id="' + data.item.id + '"] .item-options__notices');
    $noticesContainer.html( '' );

    let $notice = jQuery('<div/>', {
        'class': 'item-options__notice item-options__notice--warning'
    });

    let $repeaterField = data.item.options.ZOOMWEBINAR.fields.find( field => {
        return field.attributes.optionCode === 'WEBINARQUESTIONS';
    });

    let sendData = {
        action: 'uap_zoom_api_get_webinar_questions',
        nonce: UncannyAutomator.nonce,
        recipe_id: UncannyAutomator.recipe.id,
        item_id: data.item.id,
        webinar_id: data.values.ZOOMWEBINAR
    }

    jQuery.ajax({
        type: "POST",
        dataType: "json",
        url: ajaxurl,
        data: sendData,
        success: function( response ){
            // Remove loading animation to the button
            $button.removeClass( 'uap-btn--loading uap-btn--disabled' );

            if ( response.success === false ) {
                // Add notice message
                $notice.html( response.data[0].code + ': ' + response.data[0].message );
                // Add notice
                $noticesContainer.html( $notice );
            } else {
                uap_zoom_webinars_populate_repeater( $repeaterField, response )
            }
        }
    });
}

function uap_zoom_webinars_populate_repeater( repeaterField, response ) {

    // Remove all the current fields
    repeaterField.fieldRows = [];

    jQuery.each( response.data.questions, function ( index, question ) {
        // Do not add last name field because we already have it in the form.
        if ( question.field_name === 'last_name' ) {
            return true;
        }
        
        repeaterField.addRow({
            QUESTION_NAME: question.field_name
        }, false);
    
    });

    jQuery.each( response.data.custom_questions, function ( index, question ) {
        repeaterField.addRow({
            QUESTION_NAME: question.title
        }, false);
    });

    // Render again
    repeaterField.reRender();
}
