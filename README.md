PayEx Payments for Magento 2
============================

Official PayEx Payments Extension for Magento2

Version
=======
At the moment the module is in the process of development.

Install
=======

1. Go to Magento2 root folder

2. Enter following commands to install module:

    ```bash    
    composer require payex/magento2-payments
    ```
   Wait while dependencies are updated.

3. Enter following commands to enable module:

    ```bash
    php bin/magento module:enable PayEx_Payments --clear-static-content
    php bin/magento setup:upgrade
    php bin/magento cache:clean
    ```

4. Enable and configure PayEx Payments in Magento Admin under Stores > Configuration > Sales > Payment Methods > PayEx Payments
