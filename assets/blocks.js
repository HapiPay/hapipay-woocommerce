/* HapiPay dans le tunnel de paiement « en blocs ». Aucun champ : le client paie sur la page HapiPay. */
( function () {
	const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
	const { createElement: el } = window.wp.element;
	const { decodeEntities } = window.wp.htmlEntities;
	const data = window.wc.wcSettings.getSetting( 'hapipay_data', {} );
	const title = decodeEntities( data.title || 'HapiPay' );

	const Label = () =>
		el( 'span', { style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
			data.icon ? el( 'img', { src: data.icon, alt: '', width: 24, height: 24 } ) : null,
			title
		);
	const Content = () => el( 'p', null, decodeEntities( data.description || '' ) );

	registerPaymentMethod( {
		name: 'hapipay',
		label: el( Label ),
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: () => true,
		ariaLabel: title,
		supports: { features: [ 'products' ] },
	} );
} )();
