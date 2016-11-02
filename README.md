# PayEx Payment Gateway for Magento 2

The Official PayEx Payment Gateway Extension for Magento 2 can be used in
Sweden, Norway, Denmark and Finland and provides seamless integration with
[PayEx][payex]' rich Payments API. Empower your Magento shop with a user
friendly way to pay and receive payments for the products you have up for sale!

You can configure the extension to receive payments in a number of different
ways, in all Nordic currencies: SEK, NOK, DKK and EUR. Best of all: The
extension is free!

At the time of purchase, after checkout confirmation, the customer will be
redirected to the secure [PayEx][payex] Payment Gateway. Depending on how you
configure the extension, the customer will be presented with the desired method
of payment and can settle the order in a secure and user friendly manner
directly in the browser. The Payment Gateway is responsive and works just as
well on desktop as on mobile browsers.

All payments will be processed in a secure PCI DSS compliant environment so you
don't have to think about any such compliance requirements in your web shop.
With PayEx, your customers can pay by:

## Installation

### Magento Marketplace

The recommended way of installing is through Magento Marketplace, where you can
find [The Official PayEx Payment Gateway Extension][marketplace].

### Manually

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

4. Enable and configure PayEx Payments in Magento Admin under *Stores* >
   *Configuration* > *Sales* > *Payment Methods* > *PayEx Payments*.

[payex]: http://payex.com/
[marketplace]: https://marketplace.magento.com/payex-magento2-payments.html
