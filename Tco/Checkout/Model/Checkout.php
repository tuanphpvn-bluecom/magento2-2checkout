<?php

namespace Tco\Checkout\Model;

use Tco\Checkout\Helper\Checkout as CheckoutHelper;

class Checkout extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'tco_checkout';
    protected $_code = self::CODE;
    protected $_isGateway = false;
    protected $_isOffline = true;
    protected $helper;
    protected $_minAmount = null;
    protected $_maxAmount = null;
    protected $_supportedCurrencyCodes = array(
        'AFN', 'ALL', 'DZD', 'ARS', 'AUD', 'AZN', 'BSD', 'BDT', 'BBD',
        'BZD', 'BMD', 'BOB', 'BWP', 'BRL', 'GBP', 'BND', 'BGN', 'CAD',
        'CLP', 'CNY', 'COP', 'CRC', 'HRK', 'CZK', 'DKK', 'DOP', 'XCD',
        'EGP', 'EUR', 'FJD', 'GTQ', 'HKD', 'HNL', 'HUF', 'INR', 'IDR',
        'ILS', 'JMD', 'JPY', 'KZT', 'KES', 'LAK', 'MMK', 'LBP', 'LRD',
        'MOP', 'MYR', 'MVR', 'MRO', 'MUR', 'MXN', 'MAD', 'NPR', 'TWD',
        'NZD', 'NIO', 'NOK', 'PKR', 'PGK', 'PEN', 'PHP', 'PLN', 'QAR',
        'RON', 'RUB', 'WST', 'SAR', 'SCR', 'SGF', 'SBD', 'ZAR', 'KRW',
        'LKR', 'SEK', 'CHF', 'SYP', 'THB', 'TOP', 'TTD', 'TRY', 'UAH',
        'AED', 'USD', 'VUV', 'VND', 'XOF', 'YER'
    );
    protected $_formBlockType = 'Tco\Checkout\Block\Form\Checkout';
    protected $_infoBlockType = 'Tco\Checkout\Block\Info\Checkout';

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Tco\Checkout\Helper\Checkout $helper
    ) {
        $this->helper = $helper;
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger
        );

        $this->_minAmount = $this->getConfigData('min_order_total');
        $this->_maxAmount = $this->getConfigData('max_order_total');
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if ($quote && (
                $quote->getBaseGrandTotal() < $this->_minAmount
                || ($this->_maxAmount && $quote->getBaseGrandTotal() > $this->_maxAmount))
        ) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }
        return true;
    }

    public function buildCheckoutRequest($order)
    {
        $billing_address = $order->getBillingAddress();
        $shipping_address = $order->getShippingAddress();

        $params = array();

        $params["sid"]                  = $this->getConfigData("merchant_id");
        $params["merchant_order_id"]    = $order->getRealOrderId();
        $params["cart_order_id"]        = $order->getRealOrderId();
        $params["currency_code"  ]      = $order->getOrderCurrencyCode();
        $params["total"]                = round($order->getGrandTotal(), 2);
        $params["card_holder_name"]     = $billing_address->getName();
        $params["street_address"]       = $billing_address->getStreet()[0];
        if (count($billing_address->getStreet()) > 1) {
            $params["street_address2"]  = $billing_address->getStreet()[1];
        }
        $params["city"]                 = $billing_address->getCity();
        $params["state"]                = $billing_address->getRegion();
        $params["zip"]                  = $billing_address->getPostcode();
        $params["country"]              = $billing_address->getCountryId();
        $params["email"]                = $order->getCustomerEmail();
        $params["phone"]                = $billing_address->getTelephone();
        $params["return_url"]           = $this->getCancelUrl();
        $params["x_receipt_link_url"]   = $this->getReturnUrl();
        $params["purchase_step"]        = "payment-method";

        $url = $this->getConfigData('sandbox') ?
            $this->getConfigData('cgi_url_sandbox') : $this->getConfigData('cgi_url');

        $url .= '?' . http_build_query($params, '', '&amp;');

        return $url;
    }

    public function validateResponse($orderNumber, $total, $key)
    {
        $secretWord = $this->getConfigData('secret_word');
        $merchantId = $this->getConfigData('merchant_id');

        $stringToHash = strtoupper(md5($secretWord . $merchantId . $orderNumber . $total));
        if ($stringToHash != $key) {
            $result = false;
        } else {
            $result = true;
        }
        return $result;
    }

    public function getRedirectUrl()
    {
        $url = $this->helper->getUrl($this->getConfigData('redirect_url'));
        return $url;
    }

    public function getReturnUrl()
    {
        $url = $this->helper->getUrl($this->getConfigData('return_url'));
        return $url;
    }

    public function getCancelUrl()
    {
        $url = $this->helper->getUrl($this->getConfigData('cancel_url'));
        return $url;
    }
}