define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function ($, quote, urlBuilder, storage, errorProcessor, customer, fullScreenLoader) {
        'use strict';

        return function (messageContainer) {
            var payload,
                paymentData = quote.paymentMethod();

            /**
             * Checkout for guest and registered customer.
             */
            payload = {
                cartId: quote.getQuoteId(),
                email: !customer.isLoggedIn() ? quote.guestEmail : '',
                paymentMethod: paymentData,
                billingAddress: quote.billingAddress(),
                mode: customer.isLoggedIn() ? 'guest' : 'registered'
            };

            return $.ajax('/payex/checkout/placeorder', {
                data: JSON.stringify(payload),
                method: 'POST',
                beforeSend: function() {
                    fullScreenLoader.startLoader();
                }
            }).always(function() {
                fullScreenLoader.stopLoader();
            }).done(function(response) {
                if (!response.success) {
                    errorProcessor.process(response, messageContainer);
                }
            });
        };
    }
);
