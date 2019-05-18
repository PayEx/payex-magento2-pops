define(
    [
        'mage/url',
        'Magento_Ui/js/model/messageList'
    ],
    function (url, globalMessageList) {
        'use strict';

        return function (target) {
            // Override "process" function
            target.process = function (response, messageContainer) {
                /**
                 * Convert MessageObj to Message
                 * @param messageObj
                 * @returns {*}
                 */
                var getMessage = function (messageObj) {
                    if (!messageObj.hasOwnProperty('parameters')) {
                        return messageObj.message;
                    }

                    var expr = /([%])\w+/g;
                    return messageObj.message.replace(expr, function (varName) {
                        varName = varName.substr(1);
                        if (messageObj.parameters.hasOwnProperty(varName)) {
                            return messageObj.parameters[varName];
                        }
                        return messageObj.parameters.shift();
                    });
                };

                messageContainer = messageContainer || globalMessageList;
                if (response.status == 401) {
                    window.location.replace(url.build('customer/account/login/'));
                } else {
                    var error = JSON.parse(response.responseText);
                    // Workaround to prevent "strange" messages
                    if (getMessage(error).indexOf('No such entity with') != -1 || getMessage(error).indexOf('cartId is a required') != -1) {
                        return;
                    }

                    // Magento 2.3
                    if (getMessage(error).indexOf('Current customer does not have an active cart') != -1) {
                        return;
                    }

                    messageContainer.addErrorMessage(error);
                }
            };
            return target;
        };
    }
);

