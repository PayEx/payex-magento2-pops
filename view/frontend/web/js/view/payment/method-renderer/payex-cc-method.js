/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'PayEx_Payments/js/action/set-payment-method',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data'
    ],
    function (
        $,
        Component,
        setPaymentMethodAction,
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
                    //update payment method information if additional data was changed
                    this.selectPaymentMethod();
                    setPaymentMethodAction(this.messageContainer).done(
                        function (response) {
                            if (response.hasOwnProperty('order_id')) {
                                customerData.invalidate(['cart']);
                                $.mage.redirect(
                                    window.checkoutConfig.payment.payex_cc.redirectUrl + '?order_id=' + response.order_id
                                );
                            }
                        }
                    );

                    return false;
                }
            }
        });
    }
);
