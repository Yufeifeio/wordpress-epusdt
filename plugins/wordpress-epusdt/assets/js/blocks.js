(function () {
	var settings = window.wc && window.wc.wcSettings ? window.wc.wcSettings.getSetting('wordpress_epusdt_data', {}) : {};
	var registry = window.wc && window.wc.wcBlocksRegistry;
	var element = window.wp && window.wp.element;
	var htmlEntities = window.wp && window.wp.htmlEntities;

	if (!registry || !element || !htmlEntities) {
		return;
	}

	var title = htmlEntities.decodeEntities(settings.title || 'EPusdt');
	var description = htmlEntities.decodeEntities(settings.description || '使用 EPusdt 完成 USDT 支付。');
	var icon = settings.icon || '';

	var Label = function () {
		if (!icon) {
			return element.createElement('span', null, title);
		}

		return element.createElement(
			'span',
			{
				style: {
					display: 'inline-flex',
					alignItems: 'center',
					gap: '8px'
				}
			},
			element.createElement('img', {
				src: icon,
				alt: title,
				style: {
					width: '20px',
					height: '20px'
				}
			}),
			element.createElement('span', null, title)
		);
	};

	var Content = function () {
		return element.createElement('span', null, description);
	};

	registry.registerPaymentMethod({
		name: 'wordpress_epusdt',
		label: element.createElement(Label, null),
		content: element.createElement(Content, null),
		edit: element.createElement(Content, null),
		canMakePayment: function () {
			return true;
		},
		ariaLabel: title,
		supports: {
			features: settings.supports || ['products']
		}
	});
})();
