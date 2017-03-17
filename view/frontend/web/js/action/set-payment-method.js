define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/place-order'
    ],
    function ($, quote, urlBuilder, storage, errorProcessor, customer, fullScreenLoader, placeOrderService) {
        'use strict';

        return function (paymentData, messageContainer) {
            var serviceUrl, payload;

            if (!paymentData) {
                paymentData = quote.paymentMethod();
            }

            payload = {
                cartId: quote.getQuoteId(),
                billingAddress: quote.billingAddress(),
                paymentMethod: paymentData
            };

            if (customer.isLoggedIn()) {
                serviceUrl = urlBuilder.createUrl('/carts/mine/payment-information', {});
            } else {
                serviceUrl = urlBuilder.createUrl('/guest-carts/:quoteId/payment-information', {
                    quoteId: quote.getQuoteId()
                });
                payload.email = quote.guestEmail;
            }

            return placeOrderService(serviceUrl, payload, messageContainer);

            var payload;

            if (!paymentData) {
                paymentData = quote.paymentMethod();
            }

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
