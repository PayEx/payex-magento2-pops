/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'PayEx_Payments/js/view/payment/method-renderer/payex-cc-method'
    ],
    function (ko, $, Component) {
        'use strict';

        return Component.extend({
            defaults: {
                self: this,
                template: 'PayEx_Payments/payment/swish'
            }
        });
    }
);
