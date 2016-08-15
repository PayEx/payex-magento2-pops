/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'mage/translate',
        'Magento_Checkout/js/model/payment/additional-validators',
        'PayEx_Payments/js/action/set-payment-method',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data'
    ],
    function (ko, $, Component, placeOrderAction, $t, additionalValidators, setPaymentMethodAction, selectPaymentMethodAction, quote, checkoutData) {
        'use strict';
        var appliedSSN = window.checkoutConfig.payexSSN.appliedSSN;

        return Component.extend({
            defaults: {
                self: this,
                template: 'PayEx_Payments/payment/financing'
            },
            /**
             * @override
             */
            getData: function () {
                return {
                    'method': this.getCode(),
                    'additional_data': {
                        'social_security_number': $('#' + this.getCode() + '_social_security_number').val()
                    }
                };
            },

            /**
             * Is Applied SSN
             */
            isAppliedSSN: function() {
                return !!appliedSSN;
            },

            /**
             * Get Applied SSN
             */
            getAppliedSSN: function() {
                return appliedSSN;
            }

        });
    }
);
