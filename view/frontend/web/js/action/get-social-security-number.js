/*jshint browser:true jquery:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/translate',
        'Magento_Ui/js/modal/alert',
        'mage/validation'
    ],
    function ($, urlBuilder, storage, fullScreenLoader, $t, alert) {
        'use strict';
        return function () {
            var form = $('#shipping, #shipping-new-address-form');
            var ssn = $('#customer-ssn').val();
            var country_code = $('[name="country_id"]', form).val();
            var postcode = $('[name="postcode"]', form).val();

            var serviceUrl = urlBuilder.createUrl('/payex/payments/get_address/country_code/countryCode/postcode/postCode/ssn/SSN/lookup', {
                'countryCode': country_code,
                'postCode': postcode,
                'SSN': ssn
            });

            fullScreenLoader.startLoader();

            return storage.get(
                serviceUrl
            ).always(function () {
                fullScreenLoader.stopLoader();
            }).done(function (response) {
                fullScreenLoader.stopLoader();

                response = $.parseJSON(response);

                $('[name="firstname"]', form).val(response.first_name).keyup();
                $('[name="lastname"]', form).val(response.last_name).keyup();
                $('[name="street[0]"]', form).val(response.address_1).keyup();
                $('[name="street[1]"]', form).val(response.address_2).keyup();
                $('[name="city"]', form).val(response.city).keyup();
                $('[name="postcode"]', form).val(response.postcode).keyup();
                $('[name="country_id"]', form).val(response.country);
                $('[name="region_id"]', form).val('');
                $(form).validation();

                // Set Checkout Config value
                if (window.checkoutConfig.hasOwnProperty('payexSSN')) {
                    window.checkoutConfig.payexSSN.appliedSSN = ssn;
                }
            }).error(function (xhr) {
                fullScreenLoader.stopLoader();

                alert({
                    title: $t('Social Security Number Error'),
                    content: xhr.responseJSON.message,
                    actions: {
                        always: function (){}
                    }
                });
            });
        }
    }
);
