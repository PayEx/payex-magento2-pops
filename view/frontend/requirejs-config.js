/*jshint browser:true jquery:true*/
/*global alert*/
var config = {
    config: {
        //mixins: {
        //    'PayEx_Payments/js/action/place-order': {
        //        'Magento_CheckoutAgreements/js/model/place-order-mixin': true
        //    }
        //}
        mixins: {
            'Magento_Checkout/js/model/error-processor': {
                'PayEx_Payments/js/model/error-processor-mixin': true
            }
        }
    },
    map: {
        '*': {
            'Magento_Checkout/js/action/select-payment-method':
                'PayEx_Payments/js/action/select-payment-method'
        }
    }
};
