/*jshint browser:true jquery:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/translate',
        'Magento_Ui/js/modal/alert',
        'mage/validation'
    ],
    function ($, fullScreenLoader, $t, alert) {
        'use strict';
        return function () {
            var form = $('#shipping, #shipping-new-address-form');
            var ssn = $('#customer-ssn').val();
            var country_code = $('[name="country_id"]', form).val();
            var postcode = $('[name="postcode"]', form).val();

            $.ajax(window.checkoutConfig.payex.address_url, {
                data: {
                    ssn: ssn,
                    country_code: country_code,
                    postcode: postcode
                },
                beforeSend: function () {
                    fullScreenLoader.startLoader();
                }
            }).always(function () {
                fullScreenLoader.stopLoader();
            }).done(function (response) {
                if (!response.success) {
                    alert({
                        title: $t('Social Security Number Error'),
                        content: response.message,
                        actions: {
                            always: function (){}
                        }
                    });
                    return;
                }

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
            });
        }
    }
);
