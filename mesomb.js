var successCallback = function(data) {

    var checkout_form = $( 'form.woocommerce-checkout' );

    // add a token to our hidden input field
    // console.log(data) to find the token
    checkout_form.find('#mesomb_token').val(data.token);

    // deactivate the tokenRequest function event
    checkout_form.off( 'checkout_place_order', tokenRequest );

    // submit the form now
    checkout_form.submit();

};

var errorCallback = function(data) {
    console.log(data);
};

var tokenRequest = function() {

    // here will be a payment gateway function that process all the card data from your form,
    // maybe it will need your Publishable API key which is misha_params.publishableKey
    // and fires successCallback() on success and errorCallback on failure
    return false;

};

const { __, _x, _n, _nx } = wp.i18n;

jQuery(function($){
    // var checkout_form = $( 'form.woocommerce-checkout' );
    // checkout_form.on( 'checkout_place_order', tokenRequest );
    const placholders = {
        MTN: __('Mobile Money Number', 'mesomb-for-woocommerce'),
        ORANGE: __('Orange Money Number', 'mesomb-for-woocommerce'),
        AIRTEL: __('Airtel Money Number', 'mesomb-for-woocommerce'),
    };
    $('input[name=billing_phone]').on('change', function (evt) {
        $('#mesomb-payer').val(evt.target.value)
    })
    $('body').on('change', 'input[name=country]', function (evt) {
        const country = evt.target.value;
        if (country) {
            $('.provider-row input').prop('checked', false);
            $('.provider-row').hide();
            $('.' + country).show();
        }
    });
    $('body').on('change', 'input[name=service]', function (evt) {
        const service = evt.target.value;
        if (service) {
            $('#mesomb-payer').attr('placeholder', placholders[service])
        }
    });
    $('form[name=checkout]').on('submit', function (evt) {
        $('#mesomb-alert').show();
        setTimeout(function(){ $('#mesomb-alert').hide(); }, 6000);
    })
});