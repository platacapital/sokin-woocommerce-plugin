const sokinPaySettings       = window.wc.wcSettings.getSetting( 'sokinpay_gateway_data', {} );
const sokinPayLabel          = window.wp.htmlEntities.decodeEntities( sokinPaySettings.title ) || window.wp.i18n.__( 'Sokin Pay', 'sokin-pay' );
const sokinPayContent        = () => {
	return window.wp.htmlEntities.decodeEntities( sokinPaySettings.description || '' );
};
const platasokinBlocksGateway = {
	name: 'sokinpay_gateway',
	label: sokinPayLabel,
	content: Object( window.wp.element.createElement )( sokinPayContent, null ),
	edit: Object( window.wp.element.createElement )( sokinPayContent, null ),
	canMakePayment: () => true,
	ariaLabel: sokinPayLabel,
	supports: {
		features: sokinPaySettings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod( platasokinBlocksGateway );