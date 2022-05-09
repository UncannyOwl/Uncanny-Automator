jQuery( document ).ready(function() {

    const automatoOptinMonsterCookie = 'ua_show_campaign';
    const linkId = 'automator-manual-optin-trigger';

    // Create a hidden campaign link
    let link = createLink( linkId );

    // Wait for the cookie from PHP
    const automatorTimeout = setInterval( checkCookie, 250 );

    function createLink( id ) {
    
        var a = document.createElement('a'); 

        a.id = id;
        a.target = '_blank';
        a.style.display = 'none';
    
        // Set the href property.
        a.href = "https://app.monstercampaigns.com/c/"; 
        
        // Append the anchor element to the body.
        return document.body.appendChild(a); 
        
    }

    function checkCookie() {

        // Only check cookie on an active tab
        if ( document.hidden ) {
            return;
        }

        let campaign = getCookie( automatoOptinMonsterCookie );

        if ( campaign ) {

            console.log( 'Automator will now show the OptinMonster campaign ' + campaign );

            // Delete the cookie
            deleteCookie( automatoOptinMonsterCookie );
            
            // Set the hidden link url to the campaign-specific url
            link.href = "https://app.monstercampaigns.com/c/" + campaign;

            // Click the link
            link.click();
        }
    
    }

    function getCookie( cname ) {
        let name = cname + "=";
        let decodedCookie = decodeURIComponent( document.cookie );
        let ca = decodedCookie.split( ';' );
        for( let i = 0; i < ca.length; i++ ) {
            let c = ca[i];
            while ( c.charAt( 0 ) == ' ' ) {
                c = c.substring( 1 );
            }
            if ( c.indexOf( name ) == 0 ) {
                return c.substring( name.length, c.length );
            }
        }
        return false;
    }
    
    function deleteCookie( cname ) {
        document.cookie = cname + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    }

});


