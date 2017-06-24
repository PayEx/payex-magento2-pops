<?php

namespace PayEx\Payments\Block\MasterPass;

use Magento\Framework\View\Element\Template;

// See https://www.mastercard.com/mc_us/wallet/guides/merchant/branding/index.html
// See https://developer.mastercard.com/portal/download/attachments/48234577/Masterpass+Digital+Assets+-+Buttons+Learn+More+Links+v6+09+19+2014.pdf

class Button extends Template
{
    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    private $localeResolver;

    /**
     * Constructor
     * @param Template\Context $context
     * @param \Magento\Framework\Locale\Resolver $localeResolver
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        \Magento\Framework\Locale\Resolver $localeResolver,
        array $data = []
    ) {
    
        parent::__construct($context, $data);

        $this->urlBuilder = $context->getUrlBuilder();
        $this->localeResolver = $localeResolver;
    }

    /**
     * Get MasterPass Checkout URL
     * @return string
     */
    public function getCheckoutUrl()
    {
        return $this->urlBuilder->getUrl('payex/masterpass/checkout', ['_secure' => $this->getRequest()->isSecure()]);
    }

    /**
     * Get MasterPass Logo
     * @return string
     */
    public function getImageUrl()
    {
        return 'https://www.mastercard.com/mc_us/wallet/img/en/US/mcpp_wllt_btn_chk_180x042px.png';
    }

    /**
     * Get MasterPass "Read More" Url
     * @return mixed
     */
    public function getReadMoreUrl()
    {
        $locale = $this->localeResolver->getLocale();
        $iso_code = explode('_', $locale);
        $country_code = strtoupper($iso_code[0]);

        $links = [
            'US' => 'https://www.mastercard.com/mc_us/wallet/learnmore/en/',
            'SE' => 'https://www.mastercard.com/mc_us/wallet/learnmore/se/',
            'NO' => 'https://www.mastercard.com/mc_us/wallet/learnmore/en/NO/',
            'DK' => 'https://www.mastercard.com/mc_us/wallet/learnmore/en/DK/',
            'ES' => 'https://www.mastercard.com/mc_us/wallet/learnmore/en/ES/',
            'DE' => 'https://www.mastercard.com/mc_us/wallet/learnmore/de/DE/',
            'FR' => 'https://www.mastercard.com/mc_us/wallet/learnmore/fr/FR/',
            'PL' => 'https://www.mastercard.com/mc_us/wallet/learnmore/pl/PL/',
            'CZ' => 'https://www.mastercard.com/mc_us/wallet/learnmore/cs/CZ/'
        ];

        return isset($links[$country_code]) ? $links[$country_code] : $links['US'];
    }
}
