/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'PayEx_Payments/js/action/set-payment-method',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data'
    ],
    function (ko, $, Component, setPaymentMethodAction, selectPaymentMethodAction, quote, checkoutData) {
        'use strict';

        return Component.extend({
            defaults: {
                self: this,
                template: 'PayEx_Payments/payment/bankdebit'
            },
            /**
             * @override
             */
            getData: function () {
                return {
                    'method': this.getCode(),
                    'additional_data': {
                        'bank_id': $('select[name="bank_id"]').val()
                    }
                };
            },
            availableBanks: function() {
                return ko.observableArray(window.checkoutConfig.payment.payex_bankdebit.banks);
            },
            /** Redirect to PayEx */
            continueToPayEx: function () {
                //update payment method information if additional data was changed
                this.selectPaymentMethod();
                setPaymentMethodAction();
                return false;
            },
        });
    }
);
