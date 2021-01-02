/**
 * Add appointment cost to total
 */		
( function( $ ) {
	/**
	 * Process Appointment Tax
	 */
	gform.addFilter( 'gform_product_total', function( total, formId ) {
		var costFields = $( '.ginput_appointment_cost_input' ).val();
		
		// Cost Fields	
		if( costFields && costFields.length && $.isNumeric(costFields) ) {
			total += Math.max( 0, parseFloat( costFields ) );
			return total;
		}			
			
		//console.log(total);
		
		return total;
	}, 51 /* coupons applied at 50 */ );		

} )( jQuery );
