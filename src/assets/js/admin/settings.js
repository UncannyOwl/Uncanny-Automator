if (typeof PRIVATE_PLUGIN_BOILERPLATE_ADMIN === 'undefined') {
    // the global variable is not defined
    var PRIVATE_PLUGIN_BOILERPLATE_ADMIN = {};
}

(function ($) { // Self Executing function with $ alias for jQuery

    /**
     *  Initialization  similar to include once but since all js is loaded by the browser automatically the all
     *   we have to do is call our functions to initialize them, his is only run in the main configuration file
     */
    $(document).ready(function () {

        // Power-up ZURB UI
        $(document).foundation();

        // ppbRestApiSetup is a wp localized script that contains the rest route, nonce, and maybe other information
        PRIVATE_PLUGIN_BOILERPLATE_ADMIN.apiSetup = ppbApiSetup;


        /**
         * Make jQuery contains search case insensitive
         */
        $.expr[":"].contains = jQuery.expr.createPseudo(function (arg) {
            return function (elem) {
                return jQuery(elem).text().toUpperCase().indexOf(arg.toUpperCase()) >= 0;
            };
        });

        $('#ppb-search').on('keyup', function(){

            var search = $(this).val();

            if( ! search.length ){

                $('.single-module').hide();
                $('#settings-home').show();

            }else{

                $('.single-module').hide();

                // Search TITLES
                var in_title = $(".module-title h4:contains('"+search+"')");

                $.each( in_title, function(){
                    $(this).closest('.single-module').show();
                });

                // Search Description
                var in_description =  $(".module-description p:contains('"+search+"')");

                $.each( in_description, function(){
                    $(this).closest('.single-module').show();
                });
            }





        });




        PRIVATE_PLUGIN_BOILERPLATE_ADMIN.restApiForms.constructor();


        $('.module-link').on('click', function (event) {

            event.preventDefault();

            var targetModule = $(this).attr('href');
            $('.single-module').hide();
            $(targetModule).show();

        });

    });

    PRIVATE_PLUGIN_BOILERPLATE_ADMIN.restApiForms = {

        // initialize the code
        constructor: function () {

            // Add events for the group management page
            this.addApiFormEvents();

        },

        addApiFormEvents: function () {

            $('.switch-input').on('change', this.sendRestCall);

            $('.module-save-settings button').on('click', this.sendRestCall);

        },

        sendRestCall: function (e) {

            var currentTarget = $(e.currentTarget);

            // The form or container of the input data
            var associatedForm = $(e.currentTarget).closest('.api-form');

            var restMessageCallOut = $('.rest-message');

            // Get the end point for the rest call from the clicked element
            var endPoint = $(e.currentTarget).data('end-point');

            // Sometimes wp editor doesn't update the textarea input right away.
            // Let's save iFrame editors to textarea so we can serialize
            if (typeof tinymce !== 'undefined') {
                for (edId in tinyMCE.editors) {
                    tinyMCE.editors[edId].save();
                }

            }
            // Get the input data from the closet form or contain of user inputs to the clicked element
            var restData = associatedForm.find(':input').serializeObject();

            //console.log(restData);

            restMessageCallOut
                .removeClass('success')
                .addClass('primary')
                .show()
                .children('h5').html('Saving...');

            $.ajax({

                method: "POST",
                url: PRIVATE_PLUGIN_BOILERPLATE_ADMIN.apiSetup.root + endPoint + '/',
                data: restData,

                // Attach Nonce the the header of the request
                beforeSend: function (xhr) {
                    //console.log(xhr);
                    xhr.setRequestHeader('X-WP-Nonce', PRIVATE_PLUGIN_BOILERPLATE_ADMIN.apiSetup.nonce);

                },

                success: function (response) {

                   // console.log(response);

                    if (true === response.success) {

                        var url = window.location.href;

                        //append rest message to url
                        if (url.indexOf('?') > -1) {
                            url += '&message=' + encodeURIComponent(response.message);
                        } else {
                            url += '?message=' + encodeURIComponent(response.message);
                        }

                        if (typeof response.reload !== 'undefined') {

                            var name = 'message';
                            var value = response.message;

                            var str = location.search;
                            if (new RegExp("[&?]" + name + "([=&].+)?$").test(str)) {
                                str = str.replace(new RegExp("(?:[&?])" + name + "[^&]*", "g"), "")
                            }
                            str += "&";
                            str += name + "=" + value;
                            str = "?" + str.slice(1);

                            // there is an official order for the query and the hash if you didn't know.
                            location.assign(location.origin + location.pathname + str + location.hash);

                        } else if (typeof response.call_function !== 'undefined') {

                            if (typeof response.function_vars !== 'undefined') {
                                PRIVATE_PLUGIN_BOILERPLATE_ADMIN[response.call_function](response.function_vars);
                            } else {
                                PRIVATE_PLUGIN_BOILERPLATE_ADMIN[response.call_function]();
                            }

                        } else {

                            restMessageCallOut
                                .removeClass('primary')
                                .addClass('success')
                                .children('h5').html(response.message);

                            setTimeout(function () {
                                restMessageCallOut.trigger('close');
                            }, 2000);

                        }

                    } else {
                        restMessageCallOut
                            .removeClass('primary')
                            .addClass('warning')
                            .children('h5').html(response.message);
                    }

                },

                fail: function (response) {
                    associatedForm.children('#rest-message').html(response.message);
                }

            });

        }


    };

    /**
     * Extend serialize array to create a serialized object. This is the format that the rest call expects
     *
     * @returns object Returns key value pairs where the key is the input name and the value is the input value
     */
    // Extend serialize array to create an serialized object
    $.fn.serializeObject = function () {

        //console.log(this);

        var o = {};
        var a = this.serializeArray();
        $.each(a, function () {
            if (o[this.name] !== undefined) {
                if (!o[this.name].push) {
                    o[this.name] = [o[this.name]];
                }
                o[this.name].push(this.value || '');
            } else {
                o[this.name] = this.value || '';
            }
        });

        var $b = this;
        $.each($b, function () {
            if (!o.hasOwnProperty(this.name)) {
               // console.log(this.type);
                if ('button' === this.type) {
                    o[this.name] = this.value;
                } else {
                    o[this.name] = 'off';
                }

            }
        });

        return o;
    };


})(jQuery);
