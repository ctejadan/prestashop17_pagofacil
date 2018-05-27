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

        //getting order_id
        $order_id = Order::getOrderByCartId((int)($cart->id));

        // Complete URL on order confirmation page
        $shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
        $url = Tools::getShopProtocol() .
            $shop->domain . $shop->getBaseURI();
        $return_url = $url .
            'index.php?controller=order-confirmation&id_cart=' .
            $cart->id . '&id_module=' . $this->module->id . '&id_order=' .
            $order_id . '&key=' . $customer->secure_key;

        //set payload
        $signaturePayload = array(
            'pf_amount' => $cart_amount,
            'pf_email' => $customer_email,
            'pf_order_id' => $order_id, //exist after validateOrder
            'pf_token_service' => $token_service,
            'pf_token_store' => $token_store,
            'pf_url_complete' => $return_url
        );

        //generate signature
        $signature = $this->generateSignature($signaturePayload, $token_secret);

        //add signature to the payload
        $signaturePayload['pf_signature'] = $signature;

        //post parameters
        $postVars = '';
        $ix = 0;
        $len = count($signaturePayload);

        foreach ($signaturePayload as $key => $value) {
            if ($ix !== $len - 1) {
                if ($key == "pf_url_complete") {
                    $postVars .= $key . "=" . urlencode($value) . "&";

                } else {
                    $postVars .= $key . "=" . $value . "&";
                }

            } else {
                if ($key == "pf_url_complete") {
                    $postVars .= $key . "=" . urlencode($value);
                } else {
                    $postVars .= $key . "=" . $value;
                }
            }
            $ix++;
        }

        //create transaction in pago facil
        $this->createTransaction($postVars, $_REQUEST, Configuration::get('SHOW_ALL_PAYMENT_PLATFORMS'));

        //use this to show template
        //$this->setTemplate('module:pagofacil/views/templates/front/payment_return.tpl');
    }

    public function generateSignature($payload, $tokenSecret)
    {
        $signatureString = "";
        ksort($payload);
        foreach ($payload as $key => $value) {
            $signatureString .= $key . $value;
        }
        $signature = hash_hmac('sha256', $signatureString, $tokenSecret);
        return $signature;
    }

    public function createTransaction($postVars, $request, $showAllPlatformsInPagoFacil)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://t.pagofacil.xyz/v1");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postVars);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        $result = json_decode($server_output, true);

        curl_close($ch);

        if ($result['errorMessage'] || $result['status'] == 0) {
            $this->setTemplate('module:pagofacil/views/templates/front/create_transaction_failed.tpl');
        } else {
            if ($showAllPlatformsInPagoFacil === 'SI') {
                //show all platforms
                return Tools::redirect($result['redirect']);

            } else {

                $ch = curl_init();

                curl_setopt($ch, CURLOPT_URL, $request['endpoint']);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded'));
                curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"transaction\":\"" . $result['transactionId'] . "\"}");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $server_output_response = curl_exec($ch);

                $response = json_decode($server_output_response, true);

                curl_close($ch);

                if (empty($response)) {
                    echo $server_output_response;
                } else {
                    return Tools::redirect($response['redirect']);
                }

            }
        }
    }

}
