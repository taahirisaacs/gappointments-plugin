function gcal_access_link() {
	var obj = {
		client_id       : jQuery('#client_id').val(), 
		response_type   : 'code',
		redirect_uri    : 'urn:ietf:wg:oauth:2.0:oob',
		scope           : 'https://www.googleapis.com/auth/calendar',
		access_type     : 'offline',
	};					

	return 'https://accounts.google.com/o/oauth2/auth?' + jQuery.param(obj);				
}

jQuery('#access_link').attr( 'href', gcal_access_link() ); // insert on page load

jQuery('body').on('change', '#client_id', function() {
	jQuery('#access_link').attr( 'href', gcal_access_link() );
});