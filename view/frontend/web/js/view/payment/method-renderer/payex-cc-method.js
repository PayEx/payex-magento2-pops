/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'PayEx_Payments/js/action/set-payment-method',
        'PayEx_Payments/js/action/select-payment-method',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data'
    ],
    function (
        $,
        Component,
        setPaymentMethodAction,
        selectPaymentMethodAction,
        additionalValidators,
        quote,
        customerData
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                self: this,
                template: 'PayEx_Payments/payment/cc'
            },
            /** Redirect to PayEx */
            continueToPayEx: function () {
                if (additionalValidators.validate()) {
                    selectPaymentMethodAction(this.getData()).done(function () {
                        //update payment method information if additional data was changed
                        setPaymentMethodAction(this.messageContainer).done(
                            function (response) {
                                if (response.hasOwnProperty('order_id')) {
                                    customerData.invalidate(['cart']);
                                    $.mage.redirect(
                                        window.checkoutConfig.payment.payex_cc.redirectUrl + '?order_id=' + response.order_id
                                    );
                                } else {
                                    console.log(response);
                                }
                            }
                        );
                    });

                    return false;
                }
            }
        });
    }
);
