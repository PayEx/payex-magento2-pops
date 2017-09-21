/*jshint browser:true jquery:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (jQuery, urlBuilder, storage, errorProcessor, fullScreenLoader) {
        'use strict';
        return function (messageContainer) {
            var serviceUrl = urlBuilder.createUrl('/payex/payments/redirect_url', {});

            fullScreenLoader.startLoader();

            return storage.get(
                serviceUrl
            ).always(function () {
                fullScreenLoader.stopLoader();
            }).fail(
                function (response) {
                    errorProcessor.process(response, messageContainer);
                    fullScreenLoader.stopLoader();
                }
            );
        }
    }
);
