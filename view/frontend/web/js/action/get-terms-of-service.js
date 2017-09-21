/*jshint browser:true jquery:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/modal/alert',
        'mage/translate'
    ],
    function (jQuery, urlBuilder, storage, errorProcessor, fullScreenLoader, alert, $t) {
        'use strict';
        return function (payment_method) {
            var serviceUrl = urlBuilder.createUrl('/payex/payments/get_service_terms', {});
            fullScreenLoader.startLoader();

            return storage.post(
                serviceUrl,
                JSON.stringify({payment_method: payment_method}),
                true
            ).always(function () {
                fullScreenLoader.stopLoader();
            }).done(function (response) {
                fullScreenLoader.stopLoader();
                alert({
                    title: $t('Terms of the Service'),
                    content: response,
                    actions: {
                        always: function (){}
                    }
                });
            });
        }
    }
);
