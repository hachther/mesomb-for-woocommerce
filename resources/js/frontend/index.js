
import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import { useEffect, useState } from 'react';
import { SelectControl } from '@wordpress/components';
import { registerBlockType } from '@wordpress/blocks';
import { ComboboxControl } from '@wordpress/components';

const settings = getSetting( 'mesomb_data', {} );

const defaultLabel = __(
	'MeSomb Payments',
	'woo-gutenberg-products-block'
);

const placholders = {
	MTN: __('Mobile Money Number', 'mesomb-for-woocommerce'),
	ORANGE: __('Orange Money Number', 'mesomb-for-woocommerce'),
	AIRTEL: __('Airtel Money Number', 'mesomb-for-woocommerce'),
};

const countries = {
	CM: __('Cameroon', 'mesomb-for-woocommerce'),
	NE: __('Niger', 'mesomb-for-woocommerce'),
}

const label = decodeEntities( settings.title ) || defaultLabel;
/**
 * Content component
 */
const Content = (props) => {
	const [payer, setPayer] = useState('');
	const [service, setService] = useState('');
	const [country, setCountry] = useState(settings.countries.length == 1 ? settings.countries[0] : '');
	const [alert, setAlert] = useState(false);
	const { eventRegistration, emitResponse } = props;
	const { onPaymentProcessing } = eventRegistration;
	console.log(settings);

	useEffect(() => {
		const unsubscribe = onPaymentProcessing( async () => {
			let error = '';
			let customDataIsValid = true;
			if ( !country.length ) {
				error = __('You must select the country of the payment', 'mesomb-for-woocommerce');
				customDataIsValid = false;
			} else if ( !service.length ) {
				error = __('You must select your payment provider', 'mesomb-for-woocommerce');
				customDataIsValid = false;
			} else if ( !payer.length ) {
				error = __('You must enter the payment number', 'mesomb-for-woocommerce');
				customDataIsValid = false;
			}

			if ( customDataIsValid ) {
				setAlert(true);
				setTimeout(() => {
					setAlert(false);
				}, 6000);

				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							service,
							payer,
						},
					},
				};
			}

			return {
				type: emitResponse.responseTypes.ERROR,
				message: error,
			};
		});

		return () => {
			unsubscribe();
		};
	}, [
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS,
		onPaymentProcessing,
		payer,
		service
	]);

	return (
		<div>
			{settings.countries.length > 1 && (
				<div className="wc-blocks-components-select">
					<div className="wc-blocks-components-select__container">
						<label htmlFor={`${settings.id}-service`}
							   className="wc-blocks-components-select__label">{__('Country', 'mesomb-for-woocommerce')}</label>
						<select
							name={'service'}
							className="wc-blocks-components-select__select"
							id={`${settings.id}-service`}
							aria-invalid="false"
							required={true}
							onChange={(e) => setCountry(e.target.value)}
						>
							<option>{__('Select Country', 'mesomb-for-woocommerce')}</option>
							{settings.countries.map((c) => (
								<option value={c}>{countries[c]}</option>
							))}
						</select>
						<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
							 className="wc-blocks-components-select__expand" aria-hidden="true" focusable="false">
							<path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path>
						</svg>
					</div>
				</div>
			)}
			<div className="wc-blocks-components-select">
				<div className="wc-blocks-components-select__container">
					<label htmlFor={`${settings.id}-service`}
						   className="wc-blocks-components-select__label">{__('Service Provider', 'mesomb-for-woocommerce')}</label>
					<select
						name={'service'}
						className="wc-blocks-components-select__select"
						id={`${settings.id}-service`}
						aria-invalid="false"
						required={true}
						onChange={(e) => setService(e.target.value)}
					>
						<option>{__('Select Mobile Operator', 'mesomb-for-woocommerce')}</option>
						{settings.providers.filter(p => p.countries.includes(country)).map((provider) => (
							<option value={provider.key}>{provider.name}</option>
						))}
					</select>
					<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
						 className="wc-blocks-components-select__expand" aria-hidden="true" focusable="false">
						<path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path>
					</svg>
				</div>
			</div>
			<div className={'wc-block-components-text-input is-active'}>
				<input
					placeholder={placholders[service] ?? __('Phone Number', 'mesomb-for-woocommerce')}
					type="tel"
					name="payer"
					id={`${settings.id}-payer`}
					maxLength="9"
					required={true}
					onChange={(e) => setPayer( e.target.value )}
				/>
				<label htmlFor={`${settings.id}-payer`}>{__('Phone Number', 'mesomb-for-woocommerce')}</label>
			</div>
			{alert && (
				<div
					className="alert alert-success"
					role="alert"
					id="mesomb-alert"
					 style={{
						 marginTop: '10px',
						 position: 'relative',
						 padding: '.5rem 1.25rem',
						 marginBottom: '1rem',
						 border: '1px solid transparent',
						 borderRadius: '.25rem',
						 color: '#155724',
						 backgroundColor: '#d4edda',
						 borderColor: '#c3e6cb',
					}}
				>
					<h4 style={{marginTop: '0px', marginBottom: '5px'}}>{__('Check your phone', 'mesomb-for-woocommerce')}</h4>
					<p style={{marginBottom: '5px'}}>{__('Please check your phone to validate payment from Hachther SARL or MeSomb', 'mesomb-for-woocommerce')}</p>
				</div>
			)}
		</div>
	)

	return React.createElement(
		'div',
		null,
		React.createElement(
			'div',
			{class: 'form-row form-row-wide validate-required'},
			React.createElement(
				"label",
				{
					htmlFor: "mesomb-for-woocommerce-provider",
					style: {display: "block", border: "none"},
					class: 'form-label'
				},
				"Operator",
			),
			React.createElement(
				"div",
				{
					id: "providers",
					style: {display: "flex", 'flex-direction': "row", 'flex-wrap': "wrap"},
				},
				...settings.providers.filter(p => p.countries.includes('CM')).map((provider) => {
					return React.createElement(
						"div",
						{
							class: 'form-row provider-row',
							style: {'margin-right': "5px", 'margin-bottom': "5px"},
						},
						React.createElement(
							"label",
							{
								class: "kt-option",
							},
							React.createElement(
								"span",
								{
									class: "kt-option__label",
								},
								React.createElement(
									"span",
									{
										class: "kt-option__head",
									},
									React.createElement(
										"span",
										{
											class: "kt-option__control",
										},
										React.createElement(
											"span",
											{
												class: "kt-radio",
											},
											React.createElement(
												"input",
												{
													class: "input-radio",
													name: 'service',
													value: provider.key,
													type: 'radio',
													onChange(e) {
														setProvider(e.target.value);
														window.document.getElementById(`${settings.id}-payer`).placeholder = placholders[e.target.value];
													},
												},
											),
											React.createElement(
												"span",
												{},
											),
										),
									),
									React.createElement(
										"span",
										{
											class: "kt-option__title",
										},
										provider.name,
									),
									React.createElement(
										"img",
										{
											height: 25,
											width: 25,
											alt: provider.key,
											src: provider.icon,
											class: "kt-option__title",
											style: {
												width: '25px',
												height: '25px',
												'border-radius': '13px',
												position: 'relative',
												top: '-0.75em',
												right: '-0.75em'
											}
										},
									)
								),
								React.createElement(
									"span",
									{
										class: "kt-option__body",
									},
									`${__('Pay with your', 'mesomb-for-woocommerce')} ${provider.name}`,
								),
							),
						),
					);
				}),
			),
		),
		React.createElement(
			"div",
			{class: 'wc-block-components-text-input is-active'},
			React.createElement("input", {
				placeholder: 'Expl: 670000000',
				type: "tel",
				name: "payer",
				id: `${settings.id}-payer`,
				maxLength: '9',
				required: true,
				onChange(e) {
					setPayer(e.target.value);
				},
			}),
			React.createElement(
				"label",
				{
					htmlFor: `${settings.id}-payer`,
					// style: {display: "block", border: "none"},
					// class: 'form-label'
				},
				"Phone Number",
			),
		),
		React.createElement(
			"div",
			{
				class: 'alert alert-success',
				role: 'alert',
				id: 'mesomb-alert',
				style: {display: 'none', 'margin-top': '10px'}
			},
			React.createElement(
				"h4",
				{
					class: 'alert-heading'
				},
				__('Check your phone', 'mesomb-for-woocommerce')
			),
			React.createElement("p", {}, __('Please check your phone to validate payment from Hachther SARL or MeSomb', 'mesomb-for-woocommerce'))
		),
	);

	// return (
	// 	<div>
	// 		<select name="service" id="mesomb-service">
	// 			<option value="MTN">MTN</option>
	// 			<option value="ORANGE">ORANGE</option>
	// 			<option value="AIRTEL">AIRTEL</option>
	// 		</select>
	// 		<input name={'payer'} placeholder={'Phone number'}/>
	// 	</div>
	// );
};
/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = (props) => {
	const {PaymentMethodLabel} = props.components;
	return <div><PaymentMethodLabel text={label}/><img style={{marginLeft: 5}} src={settings.icon} alt={'MeSomb'}/></div>;
};

/**
 * MeSomb payment method config object.
 */
const MeSomb = {
	name: "mesomb",
	label: <Label/>,
	content: <Content/>,
	edit: <Content/>,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
		showSavedCards: false,
		showSaveOption: false,
	},
};

registerPaymentMethod(MeSomb);
