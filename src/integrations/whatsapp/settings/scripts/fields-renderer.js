
function uap_whatsapp_render_fields($button, data, modules) {

    // Add loading animation to the button
    $button.addClass('uap-btn--loading uap-btn--disabled');

    // Get the notices container
    let $noticesContainer = jQuery('.item[data-id="' + data.item.id + '"] .item-options__notices');

    $noticesContainer.html('');

    let $notice = jQuery('<div/>', {
        'class': 'item-options__notice item-options__notice--warning'
    });

    // Header repeater field.
    let $repeaterFieldHead = data.item.options.WHATSAPP_SEND_MESSAGE_TEMPLATE_META.fields[1];

    // Body repeater field.
    let $repeaterField = data.item.options.WHATSAPP_SEND_MESSAGE_TEMPLATE_META.fields[2];

    // Button repeater field.
    let $repeaterFieldButton = data.item.options.WHATSAPP_SEND_MESSAGE_TEMPLATE_META.fields[3];

    let sendData = {
        action: 'automator_whatsapp_retrieve_template',
        nonce: UncannyAutomator._site.rest.nonce,
        recipe_id: UncannyAutomator._recipe.recipe_id,
        item_id: data.item.id,
        template: data.values.WHATSAPP_SEND_MESSAGE_TEMPLATE_META
    }

    jQuery.ajax({
        type: "POST",
        dataType: "json",
        url: ajaxurl,
        data: sendData,
        success: function (response) {
            // Remove loading animation to the button
            $button.removeClass('uap-btn--loading uap-btn--disabled');

            if (response.success === false) {
                // Add notice message
                $notice.html('Unknown error');
                // Add notice
                $noticesContainer.html($notice);

            } else {

                // Render header fields.
                uap_whatsapp_populate_repeater__head($repeaterFieldHead, response);

                // Render body fields.
                uap_whatsapp_populate_repeater__body($repeaterField, response);

                // Render button fields.
                uap_whatsapp_populate_repeater__footer($repeaterFieldButton, response);

            }
        },
        error: function (response) {
            // Add notice message
            $notice.html(response.responseJSON.data.message);
            // Add notice
            $noticesContainer.html($notice);

            $button.removeClass('uap-btn--loading uap-btn--disabled');
        }
    });
}

function uap_whatsapp_populate_repeater__footer(buttonRepeaterField, response) {

    // Remove all the current fields
    var hasButtons = false;

    jQuery.each(response.components, function (index, component) {

        buttonRepeaterField.fieldRows = [];

        if ('BUTTONS' === component.type) {

            hasButtons = true;

            jQuery.each(component.buttons, function (index, button) {

                let count = 0;

                if (button.url) {
                    count = (button.url.match(/\{\{\d+\}\}/g) || []).length;
                }

                if (count >= 1) {

                    buttonRepeaterField.addRow({
                        BUTTON_FORMAT: button.type,
                    }, false);

                    buttonRepeaterField.fieldRows[index][1].attributes.placeholder = 'Provide content for {{1}}';


                }

            });

        }

    });

    buttonRepeaterField.reRender();

}

function uap_whatsapp_populate_repeater__head(headerRepeaterField, response) {

    // Remove all the current fields
    headerRepeaterField.fieldRows = [];

    var hasHeader = false;

    jQuery.each(response.components, function (index, component) {

        if ('HEADER' === component.type) {

            hasHeader = true;

            headerRepeaterField.addRow({
                HEADER_VARIABLE_FORMAT: component.format,
            }, false);

            headerRepeaterField.fieldRows[0][1].attributes.placeholder = 'https://';

            if ("TEXT" === component.format) {

                headerRepeaterField.fieldRows = [];

                // Check if there are any tokens.
                const count = (component.text.match(/\{\{\d+\}\}/g) || []).length;

                if (count >= 1) {

                    headerRepeaterField.addRow({
                        HEADER_VARIABLE_FORMAT: component.format,
                    }, false);

                    headerRepeaterField.fieldRows[0][1].attributes.placeholder = 'Provide content for {{1}}';
                    headerRepeaterField.fieldRows[0][1].attributes.isReadOnly = false;

                    return;

                }


            }

        }

    });

    headerRepeaterField.reRender();

}

function uap_whatsapp_populate_repeater__body(repeaterField, response) {

    // Remove all the current fields
    repeaterField.fieldRows = [];

    jQuery.each(response.components, function (index, component) {

        if ('BODY' === component.type) {

            let tokens = (component.text.match(/\{\{\d+\}\}/g) || []);

            let unique_tokens = [...new Set(tokens)];

            let unique_tokens__count = unique_tokens.length;

            if (0 === unique_tokens__count) {

                return;

            }

            for (var i = 0; i < unique_tokens__count; i++) {

                repeaterField.addRow({
                    BODY_VARIABLE: ''
                }, false);

            }

            jQuery.each(repeaterField.fieldRows, function (index, fieldRow) {
                fieldRow[0].attributes.placeholder = 'Please provide content for {{' + (index + 1) + '}}';
            });

        }

    });

    // Render again.
    repeaterField.reRender();

}
