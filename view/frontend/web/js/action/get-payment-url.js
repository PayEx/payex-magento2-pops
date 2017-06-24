/*jshint browser:true jquery:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (jQuery, fullScreenLoader) {
        'use strict';
        return function () {
            fullScreenLoader.startLoader();
            return jQuery.ajax(window.checkoutConfig.payex.payment_url, {
                cache: false,
                complete: function () {
                    fullScreenLoader.stopLoader();
                }
            });
        }
    }
);
