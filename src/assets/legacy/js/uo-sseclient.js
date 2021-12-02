var uap_redirect_in_progress = false;
var uap_redirect_check = function(){
    if( window.uap_redirect_in_progress == true ){
        return;
    }
    window.uap_redirect_in_progress = true;
    jQuery.ajax({
        method: 'POST',
        url: uoAppRestApiSetup.root,
        data: 'user_id=' + uoAppRestApiSetup.user_id + '&client_secret=' + uoAppRestApiSetup.client_secret_key,
        beforeSend: function ( xhr ) {
            xhr.setRequestHeader( 'X-WP-Nonce', uoAppRestApiSetup.nonce );
        },
        success : function( response ) {
            window.uap_redirect_in_progress = false;
            if( response.redirect_url ){
                window.parent.location = response.redirect_url;
            }
        },
        fail : function( response ) {
            window.uap_redirect_in_progress = false;
            if( response.redirect_url ){
                window.parent.location = response.redirect_url;
            }
        }
    });
}
window.onload = function () {
    var captured_check_iframe = false;
    setInterval(function () {
        var frames = jQuery(document).find('iframe');
        if (captured_check_iframe == false) {
            jQuery(frames).each(function (index, element) {
                var x = element;
                try {
                    if (typeof x.contentWindow.XMLHttpRequest != 'undefined') {
                        captured_check_iframe = true;
                        (function () {
                            var origOpen = x.contentWindow.XMLHttpRequest.prototype.open;
                            x.contentWindow.XMLHttpRequest.prototype.open = function () {
                                this.addEventListener('load', function () {
                                    if (this.readyState == 4) {
                                        window.parent.uap_redirect_check();
                                    }
                                });
                                origOpen.apply(this, arguments);
                            };
                        })(x.contentWindow.XMLHttpRequest.prototype.open);
                    }
                }catch (e) {
                    //console.log((e);
                }
            });
        }
    }, 5000);

    jQuery(document).ajaxSuccess(function (event, request, settings) {
        if (request.status == "200") {
            window.uap_redirect_check();
        }
    });
}