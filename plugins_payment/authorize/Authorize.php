<?php

require_once dirname(__FILE__) . '/../PaymentMethodAbstract.php';
require_once dirname(__FILE__) . '/anet_php_sdk/AuthorizeNet.php';

class Authorize extends PaymentMethodAbstract
{
    const APPROVED = 1,
        DECLINED = 2,
        ERROR = 3,
        HELD_FOR_REVIEW = 4;

    public function getDisplayName()
    {
        return 'Authorize.net AIM';
    }

    public function getSelectPaymentHtml()
    {
        return '<img src="modules/shop/plugins_payment/authorize/visa_mastercard.gif"
                    align="left"
                    style="margin-right:7px;">
                    <span style="font-size:11px; font-family: Arial, Verdana;">
                    Credit card
                    </span>';
    }

    /**
     * Retrieve custom properties input by user and validate them
     */
    public function validatePaymentForm(&$error_message)
    {
        $cc_number = Context::get('cc_number');
        $cc_exp_month = Context::get('cc_exp_month');
        $cc_exp_year = Context::get('cc_exp_year');
        $cc_cvv = Context::get('cc_cvv');

        if(!$cc_number)
        {
            $error_message = "Please enter you credit card number"; return FALSE;
        }
        if(!$cc_exp_month || !$cc_exp_year)
        {
            $error_message = "Please enter you credit card expiration date"; return FALSE;
        }
        if(!$cc_cvv)
        {
            $error_message = "Please enter you credit card verification number"; return FALSE;
        }

        $cc_number = str_replace(array(' ', '-'), '', $cc_number);
        if (!preg_match ('/^4[0-9]{12}(?:[0-9]{3})?$/', $cc_number) // Visa
            && !preg_match ('/^5[1-5][0-9]{14}$/', $cc_number) // MasterCard
            && !preg_match ('/^3[47][0-9]{13}$/', $cc_number) // American Express
            && !preg_match ('/^6(?:011|5[0-9]{2})[0-9]{12}$/', $cc_number) //Discover
        ){
            $error_message = 'Please enter your credit card number!';
        }

        $cc_exp = sprintf('%02d%d', $cc_exp_month, $cc_exp_year);




        return TRUE;
    }

    public function authorizePayment(Cart $cart)
    {
        $data = array();

        // Transaction info
        $data['x_type'] = 'AUTH_ONLY';
        $data['x_card_num'] = '4007000000027';
        $data['x_exp_date'] = '201412';
        $data['x_card_code'] = '278';

        // Setup login info
        $data['x_login'] = $this->api_login_id;
        $data['x_tran_key'] = $this->transaction_key;

        // Setup Advanced Integration Method values (AIM)
        $data['x_version'] = '3.1';
        $data['x_delim_data'] = 'TRUE';
        $data['x_delim_char'] = '|';
        $data['x_relay_response'] = 'FALSE';

        // Indicate transaction method; CC = credit card; another option would be ECHECK
        $data['x_method'] = 'CC';

        // Setup order information
        // TODO Retrieve values from cart
        $data['x_amount'] = '100';
        $data['x_invoice_num'] = '1';
        $data['x_cust_id'] = '1';

        // Convert data to name-value pairs string
        $post_string = '';
        foreach( $data as $k => $v ) {
            $post_string .= "$k=" . urlencode($v) . "&";
        }
        $post_string = rtrim($post_string, '& ');

        // Request
        $request = curl_init($this->gateway_api_url);
        curl_setopt($request, CURLOPT_HEADER, 0);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($request, CURLOPT_POSTFIELDS, $post_string);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE);
        $response = curl_exec($request);
        curl_close ($request);

        $response_array = explode($data["x_delim_char"], $response);

        var_dump($response_array);
        exit();

    }

    public function processPayment(Cart $cart, &$error_message)
    {
        // TODO Add code for live calls
        $transaction = new AuthorizeNetAim($this->api_login_id, $this->transaction_key);
        $transaction->amount = '9.99';
        $transaction->card_num = '4007000000027';
        $transaction->exp_date = '10/16';

        $response = $transaction->authorizeAndCapture();

        if ($response->approved) {

            // echo "<h1>Success! The test credit card has been charged!</h1>";
            // echo "Transaction ID: " . $response->transaction_id;
            return TRUE;
        } else {
            $error_message = $response->error_message;
            return FALSE;
        }
    }

	/**
	 * Make sure all mandatory fields are set
	 */
	public function isConfigured()
	{
		if(!isset($this->api_login_id) && !isset($this->transaction_key) && !isset($this->gateway_api))
			return false;
		return true;
	}
}

?>