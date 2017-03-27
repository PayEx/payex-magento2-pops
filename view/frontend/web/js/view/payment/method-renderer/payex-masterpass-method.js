/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data'
    ],
    function (
        $,
        Component,
        placeOrderAction,
        additionalValidators,
        quote,
        customerData
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                self: this,
                template: 'PayEx_Payments/payment/masterpass'
            },
            redirectAfterPlaceOrder: false,

            /** Redirect to PayEx */
            placeOrder: function () {
                if (additionalValidators.validate()) {
                    //update payment method information if additional data was changed
                    this.selectPaymentMethod();
                    var method = this.getCode();
                    placeOrderAction(this.getData(), this.messageContainer).done(
                        function () {
                            customerData.invalidate(['cart']);
                            $.mage.redirect(
                                window.checkoutConfig.payment[method].redirectUrl
                            );
                        }
                    );

                    return false;
                }
            }
        });
    }
);
