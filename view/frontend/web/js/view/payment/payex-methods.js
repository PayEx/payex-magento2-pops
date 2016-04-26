define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'payex_cc',
                component: 'PayEx_Payments/js/view/payment/method-renderer/payex-cc-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
