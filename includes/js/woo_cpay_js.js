// jQuery Function calls on ready state
jQuery(function ($) {
	/* 
		Make Title and Description fields non-tabbable in the Sokin Pay form of the Payments tab on WooCommerce->Settings page
	*/
	$('#woocommerce_sokinpay_gateway_title').attr('tabindex', '-1');
	$('#woocommerce_sokinpay_gateway_description').attr('tabindex', '-1');

	$(".wc-payment-gateway-method-name").remove();
	
	// Set disclaimer text for Refunding the money on Edit Order page (Admin Panel)
	$('.button.refund-items').click(function () {
		$('table.wc-order-totals tbody tr:nth-child(5) td.label')
			.prepend('<div class="disclaimer" style="float:left;background:#ffefb7;padding:0 10px;"><span class="heading"><strong>Disclaimer:</strong></span> <span>Same day transfer will be fully refunded.</span></div>');
		// Replace the default text
		$('table.wc-order-totals tbody tr:nth-child(5) td.label label').html('<span class="woocommerce-help-tip" tabindex="0" aria-label="Note: the refund reason will be visible by the customer."></span> Reason for refund:');
	});

	// Prevent form submission if Refund Reason field has no value
	$('form#order .do-manual-refund').click(function (e) {
		let refundReason = $('#refund_reason').val();
		e.preventDefault();
		if (refundReason === null || refundReason === undefined || refundReason === '') {
			$('#refund_reason').css('border-color', 'red');
			return false;
		} else {
			$('#refund_reason').css('border-color', '#2c3338');
			return true;
		}
	});

	// Apply border color:red if Reason field is empty and set default color:#2c3338 if it has value
	$('#refund_reason').on('keyup', function () { 
		let refundReason = $('#refund_reason').val();
		if (refundReason === null || refundReason === undefined || refundReason === '') {
			$('#refund_reason').css('border-color', 'red');
		} else {
			$('#refund_reason').css('border-color', '#2c3338');
		}
	});
})