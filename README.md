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

* Bank Debit
* Credit and Debit Cards (Visa, MasterCard, Visa Electron, Maestro)
* One-Click Credit Card
* Financing Credit Account (PayEx Delbetala)
* Financing Invoice
* Invoice Ledger Service
* Electronic value codes
* Gift Cards (Generic Cards)
* [MasterPass][masterpass]
* [WyWallet][wywallet]
* [MobilePay Online][mobilepay]
* [Swish][swish]

eCom Payment methods:
* Credit and Debit Cards
* Invoice
* [Vipps][vipps]

Settle your orders with the largest and most complete payment provider in the
Nordics!

## Installation

### Magento Marketplace

The recommended way of installing is through Magento Marketplace, where you can
find [The Official PayEx Payment Gateway Extension][marketplace].

### Composer

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
   
### Manually
   
1. Clone repository with extension:
   ```bash
   git clone https://github.com/PayEx/PayEx.Magento2
   ```

2. Move extension files to {magento_root}/app/code/PayEx/Payments directory:
   ```bash
   mkdir -p /app/code/PayEx/Payments
   mv PayEx.Magento2/* /app/code/PayEx/Payments/
   ```   
   
2. Go to Magento2 root folder and enter following commands to install dependencies:
   
   ```bash
   composer require payex/php-api
   composer require aait/php-name-parser
   composer require guzzlehttp/guzzle
   composer require ramsey/uuid
   ```

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
[masterpass]: https://www.mastercard.se/sv-se/konsument/tjaenster-och-innovation/innovation/masterpass.html
[wywallet]: http://wywallet.se/
[mobilepay]: https://mobilepay.dk/da-dk/Erhverv/Pages/mobilepay-online.aspx
[swish]: https://www.getswish.se/
[vipps]: https://www.vipps.no/