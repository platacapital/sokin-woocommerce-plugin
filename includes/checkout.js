//import sokinicon from './assets/images/payment_methods.svg';
let svgUrl = block_params.svg;
// console.log(sokinicon);
const sokinPaySettings       = window.wc.wcSettings.getSetting( 'sokinpay_gateway_data', {} );
//console.log(sokinPaySettings.title);
const sokinPayLabel          = window.wp.htmlEntities.decodeEntities( sokinPaySettings.title ) || window.wp.i18n.__( 'Sokin Pay', 'sokinpay_gateway' );
//const sokinPayLabel = '<span>Test</span>';
const sokinPayContent        = () => {
	return window.wp.htmlEntities.decodeEntities( sokinPaySettings.description || '' );
};


const { createElement } = window.wp.element;


const SokinPay_Block_Gateway = {
	name: 'sokinpay_gateway',
	label: createElement(
    'div',
    { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
    createElement('span', null, 'Sokin'), // The text BEFORE SVG
    createElement('img', { src: svgUrl, alt: 'SokinPay', width: 200, height: 24 })  ),
	content: Object( window.wp.element.createElement )( sokinPayContent, null ),
	edit: Object( window.wp.element.createElement )( sokinPayContent, null ),
	canMakePayment: () => true,
	ariaLabel: sokinPayLabel,
	supports: {
		features: sokinPaySettings.supports,
	},
};

// const SokinPay_Block_Gateway = {
// 	name: 'sokinpay_gateway',
// 	label: sokinPayLabel,
// 	content: Object( window.wp.element.createElement )( sokinPayContent, null ),
// 	edit: Object( window.wp.element.createElement )( sokinPayContent, null ),
// 	canMakePayment: () => true,
// 	ariaLabel: sokinPayLabel,
// 	supports: {
// 		features: sokinPaySettings.supports,
// 	},
// };
window.wc.wcBlocksRegistry.registerPaymentMethod( SokinPay_Block_Gateway );