/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'PayEx_Payments/js/action/set-payment-method',
        'PayEx_Payments/js/action/select-payment-method',
        'PayEx_Payments/js/action/get-payment-url',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/model/messageList',
        'mage/translate'
    ],
    function (
        $,
        Component,
        setPaymentMethodAction,
        selectPaymentMethodAction,
        getPaymentUrlAction,
        additionalValidators,
        quote,
        customerData,
        fullScreenLoader,
        globalMessageList,
        $t
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
                    var self = this;
                    selectPaymentMethodAction(this.getData()).done(function () {
                        //update payment method information if additional data was changed
                        //this.selectPaymentMethod();
                        //var method = this.getCode();
                        setPaymentMethodAction(self.getData(), this.messageContainer).done(function () {
                            getPaymentUrlAction().always(function() {
                                fullScreenLoader.stopLoader();
                            }).done(function(response) {
                                if (response.hasOwnProperty('error')) {
                                    globalMessageList.addErrorMessage({
                                        message: response['error']
                                    });
                                }

                                if (response.hasOwnProperty('redirect_url')) {
                                    fullScreenLoader.startLoader();
                                    customerData.invalidate(['cart']);
                                    $.mage.redirect(response['redirect_url']);
                                }
                            }).error(function() {
                                globalMessageList.addErrorMessage({
                                    message: $t('An error occurred on the server. Please try to place the order again.')
                                });
                            });
                        });
                    });

                    return false;
                }
            }
        });
    }
);
