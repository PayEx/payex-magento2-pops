/*jshint browser:true jquery:true*/
/*global alert*/
define(
    [
        'PayEx_Payments/js/view/checkout/summary/fee'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'PayEx_Payments/checkout/cart/totals/fee'
            },
            /**
             * @override
             */
            isDisplayed: function () {
                return this.getValue(true) > 0;
            }
        });
    }
);
