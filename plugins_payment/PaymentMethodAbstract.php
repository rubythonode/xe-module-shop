<?php

abstract class PaymentMethodAbstract
{
    static protected $frontend_form = 'form_payment.html';
    static protected $backend_form = 'form_admin_settings.html';

    public $id = null;
    public $display_name;  /// Display name
    public $name; /// Unique name = folder name
    public $status = 0;
    public $properties;

    public function __construct()
    {
        $this->name = $this->getName();
        $this->display_name = $this->getDisplayName();
    }

    /**
     * Returns the payment gateway's name
     * Defaults: Splits folder name into words and makes them uppercase
     * @return string
     */
    public function getDisplayName()
    {
        if(!isset($this->display_name))
        {
            $name = $this->getName();
            $this->display_name = ucwords(str_replace('_', ' ', $name));
        }
        return $this->display_name;
    }

    /**
     * Returns unique identifier for Payment gateway
     * Represents the folder name where the gateway class is found
     */
    final public function getName()
    {
        if(!isset($this->name))
        {
            $payment_class_directory_path = $this->getPaymentMethodDir();
            $folders = explode(DIRECTORY_SEPARATOR, $payment_class_directory_path);
            $this->name = array_pop($folders);
        }
        return $this->name;
    }

    public function setProperties($data)
    {
        foreach($data as $property_name => $property_value)
        {
            $this->{$property_name} = $property_value;
        }
    }

    public function isActive()
    {
        return $this->status ? true : false;
    }

    public function __set($name, $value)
    {
        $this->properties->$name = $value;
    }

    public function __get($name)
    {
        return $this->properties->$name;
    }


    private function getPaymentMethodDir()
    {
        $reflector = new ReflectionClass(get_class($this));
        return dirname($reflector->getFileName());
    }

    private function getFormHtml($filename)
    {
        if(!file_exists($this->getPaymentMethodDir() . DIRECTORY_SEPARATOR . $filename))
        {
            return '';
        }

        $oTemplate = &TemplateHandler::getInstance();
        return $oTemplate->compile($this->getPaymentMethodDir(), $filename);
    }

    public function getPaymentFormHTML()
    {
        return $this->getFormHtml(self::$frontend_form);
    }

    public function getAdminSettingsFormHTML()
    {
        return $this->getFormHtml(self::$backend_form);
    }

    public function getPaymentFormAction()
    {
        return './';
    }

    public function getPaymentSubmitButtonText()
    {
        return "Place your order";
    }

    public function getSelectPaymentHtml()
    {
        return $this->display_name;
    }

    protected function getCheckoutPageUrl()
    {
        $vid = Context::get('vid');
        return getNotEncodedFullUrl('', 'vid', $vid
            , 'act', 'dispShopCheckout'
            , 'error_return_url', ''
        );
    }

    protected function getPlaceOrderPageUrl()
    {
        $vid = Context::get('vid');
        return getNotEncodedFullUrl('', 'vid', $vid
            , 'act', 'dispShopPlaceOrder'
            , 'payment_method_name', $this->getName()
            , 'error_return_url', ''
        );
    }

    public function getOrderConfirmationPageUrl()
    {
        $vid = Context::get('vid');
        return getNotEncodedFullUrl('', 'vid', $vid
            , 'act', 'dispShopOrderConfirmation'
            , 'payment_method_name', $this->getName()
            , 'error_return_url', ''
        );
    }

    /**
     * Get URL for IPN notifications
     */
    public function getNotifyUrl()
    {
        $vid = Context::get('vid');
        return getNotEncodedFullUrl('', 'vid', $vid
            , 'act', 'procShopPaymentNotify'
            , 'payment_method_name', $this->getName()
            , 'error_return_url', ''
        );
    }

    protected function redirect($url)
    {
        header('location:' . $url);
        exit();
    }

    public function onCheckoutFormSubmit(Cart $cart, &$error_message)
    {
        return true;
    }

    public function onPlaceOrderFormLoad()
    {

    }

    abstract public function processPayment(Cart $cart, &$error_message);

    public function onOrderConfirmationPageLoad($module_srl)
    {
    }

    public function notify()
    {

    }

}

abstract class PaymentAPIAbstract
{
    public function request($url, $data)
    {
        $post_string = http_build_query($data);
        if(__DEBUG__)
        {
            ShopLogger::log('REQUEST ' . $url . ' ' . $post_string);
        }

        // Request
        $request = curl_init($url);
        curl_setopt($request, CURLOPT_HEADER, 0);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($request, CURLOPT_POSTFIELDS, $post_string);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE);
        $response = curl_exec($request);
        if(__DEBUG__)
        {
            ShopLogger::log('RESPONSE ' . $response);
        }

        curl_close ($request);
        return $response;
    }

}