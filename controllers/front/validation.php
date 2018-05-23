<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */


class PagoFacilValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'pagofacil') {
                $authorized = true;
                break;
            }
        }

        //if no customer, return to step 1 (just in case)
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }
        //get data
        $extra_vars = array();
        $currency = new Currency($cart->id_currency);
        $cart_amount = Context::getContext()->cart->getOrderTotal(true);
        $customer_email = Context::getContext()->customer->email;
        $token_service = Configuration::get('TOKEN_SERVICE');
        $token_secret = Configuration::get('TOKEN_SECRET');
        $token_store = md5(date('m/d/Y h:i:s a', time()) . $cart->id . $token_service);

        //setting order as pending payment
        $this->module->validateOrder($cart->id, Configuration::get('PS_OS_PAGOFACIL_PENDING_PAYMENT'), $cart_amount, $this->module->displayName, NULL, $extra_vars, (int)$currency->id, false, $customer->secure_key);

        //set payload
        $signaturePayload = array(
            'pf_amount' => $cart_amount,
            'pf_email' => $customer_email,
            'pf_order_id' => Order::getOrderByCartId((int)($cart->id)), //exist after validateOrder
            'pf_token_service' => $token_service,
            'pf_token_store' => $token_store
        );
        //get signature
        $signature = $this->generateSignature($signaturePayload, $token_secret);

        //add signature to the payload
        $signaturePayload['pf_signature'] = $signature;

        //post parameters
        $postVars = '';

        foreach ($_REQUEST as $key => $value) {
            $postVars .= $key . "=" . $value . "&";
        }

        //add transaction
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://t.pagofacil.xyz/v1");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postVars);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        $result = json_decode($server_output, true);

        curl_close($ch);

        print_r("viene result");
        print_r($result);

        print_r("viene request");
        print_r($_REQUEST);

        if ($result['errorMessage'] || $result['status'] == 0) {
            var_dump("ITS BROKEN!");
            //broken, show broken advice
        } else {
            var_dump("ITS FINE!");

            if (Configuration::get('SHOW_ALL_PAYMENT_PLATFORMS') === 'SI') {
                //show all platforms
                Tools::redirect($result['redirect']);

            } else {
                $ch = curl_init();

                curl_setopt($ch, CURLOPT_URL, $_REQUEST['endpoint']);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded'));
                curl_setopt($ch, CURLOPT_POSTFIELDS, '{"transaction": ' . $result[transactionId] . '}');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $server_output = curl_exec($ch);

                $result = json_decode($server_output, true);

                curl_close($ch);
            }
            //set order as pending payment
        }


        //$this->context->smarty->assign(['result' => $server_output,]);

        //$this->setTemplate('payment_return.tpl');

        //THIS TO SHOW TEMPLATE
        $this->setTemplate('module:pagofacil/views/templates/front/payment_return.tpl');


        // $customer = new Customer($cart->id_customer);
        // if (!Validate::isLoadedObject($customer))
        //     Tools::redirect('index.php?controller=order&step=1');

        // $currency = $this->context->currency;
        // $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        // $mailVars = array(
        //     '{bankwire_owner}' => Configuration::get('BANK_WIRE_OWNER'),
        //     '{bankwire_details}' => nl2br(Configuration::get('BANK_WIRE_DETAILS')),
        //     '{bankwire_address}' => nl2br(Configuration::get('BANK_WIRE_ADDRESS'))
        // );

        // $this->module->validateOrder($cart->id, Configuration::get('PS_OS_BANKWIRE'), $total, $this->module->displayName, NULL, $mailVars, (int)$currency->id, false, $customer->secure_key);
        // Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
    }

    function generateSignature($payload, $tokenSecret)
    {
        $signatureString = "";
        ksort($payload);
        foreach ($payload as $key => $value) {
            $signatureString .= $key . $value;
        }
        $signature = hash_hmac('sha256', $signatureString, $tokenSecret);

        print_r("viene signature STRING");
        print_r($signatureString);
        print_r("viene signature");
        print_r($signature);
        print_r("viene token secret");
        print_r($tokenSecret);
        print_r("viene payload");
        print_r($payload);
        return $signature;
    }
}
