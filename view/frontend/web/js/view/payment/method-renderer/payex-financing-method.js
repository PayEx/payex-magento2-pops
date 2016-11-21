/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'mage/translate',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/modal/alert'
    ],
    function (ko, $, Component, $t, fullScreenLoader, alert) {
        'use strict';
        var appliedSSN = window.checkoutConfig.payexSSN.appliedSSN;

        return Component.extend({
            defaults: {
                self: this,
                template: 'PayEx_Payments/payment/financing'
            },
            /**
             * @override
             */
            getData: function () {
                return {
                    'method': this.getCode(),
                    'additional_data': {
                        'social_security_number': $('#' + this.getCode() + '_social_security_number').val(),
                        'tos': $('#' + this.getCode() + '_tos').prop('checked')
                    }
                };
            },

            /**
             * Is Applied SSN
             */
            isAppliedSSN: function() {
                return !!appliedSSN;
            },

            /**
             * Get Applied SSN
             */
            getAppliedSSN: function() {
                return appliedSSN;
            },

            /**
             * Show Terms Of Service Window
             * @returns {boolean}
             */
            showTOS: function() {
                $.ajax('/payex/checkout/termsOfService', {
                    data: {
                        method: this.getCode()
                    },
                    beforeSend: function() {
                        fullScreenLoader.startLoader();
                    }
                }).always(function() {
                    fullScreenLoader.stopLoader();
                }).done(function(response) {
                    alert({
                        title: response.title,
                        content: response.content,
                        actions: {
                            always: function(){}
                        }
                    });
                });

                return false;
            }

        });
    }
);
