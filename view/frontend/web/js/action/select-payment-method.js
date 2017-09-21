/*jshint browser:true jquery:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader',
        'jquery',
        'Magento_Checkout/js/action/get-totals'
    ],
    function (urlBuilder, storage, quote, fullScreenLoader, jQuery, getTotalsAction) {
        'use strict';
        return function (paymentMethod) {
            quote.paymentMethod(paymentMethod);

            var serviceUrl = urlBuilder.createUrl('/payex/payments/apply_payment_method', {});
            fullScreenLoader.startLoader();
            return storage.post(
                serviceUrl,
                JSON.stringify({payment_method: paymentMethod.method}),
                true
            ).always(function () {
                fullScreenLoader.stopLoader();
            }).complete(function () {
                getTotalsAction([]);
                fullScreenLoader.stopLoader();
            });
        }
    }
);
