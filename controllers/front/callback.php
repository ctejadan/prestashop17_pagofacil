<?php

/*
 * Copyright 2017 Cristian Tala <yomismo@cristiantala.cl>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * author: Cristian Tejada - https://github.com/ctejadan
 */
class PagoFacilCallbackModuleFrontController extends ModuleFrontController
{
    var $token_secret;
    var $token_service;

    public function initContent()
    {

        $config = Configuration::getMultiple(array('TOKEN_SERVICE', 'TOKEN_SECRET'));
        $this->token_service = $config['TOKEN_SERVICE'];
        $this->token_secret = $config['TOKEN_SECRET'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processCallback();
            $this->my_http_response_code(200);
        } else {
            $this->my_http_response_code(405);
        }
    }

    protected function processCallback()
    {
        $POSTsignaturePayload = json_decode(Tools::file_get_contents(), true);
        $POSTsignature = $POSTsignaturePayload['pf_signature'];
        unset($POSTsignaturePayload['pf_signature']);
        $generatedSignature = $this->generateSignature($POSTsignaturePayload, $this->token_secret);

        if ($generatedSignature !== $POSTsignature) {
            print_r("NOT THE SAME SIGNATURE!!");
            $this->my_http_response_code(400);
        }

        $cart = new Cart((int)Cart::getCartIdByOrderId($POSTsignaturePayload['pf_order_id']));

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 ||
            $cart->id_address_invoice == 0 || !$this->module->active) {
            $this->my_http_response_code(404);
        }

        // Check if customer exists
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $this->my_http_response_code(412);
        }

        //Obtenemos la orden
        $order = new Order($POSTsignaturePayload['pf_order_id']);

        //Si la orden está completada no hago nada.
        $PS_OS_PAYMENT = Configuration::get('PS_OS_PAYMENT');//Accepted

        if ($PS_OS_PAYMENT == $order->getCurrentState()) {
            $this->my_http_response_code(400);
        }

        if ($POSTsignaturePayload['pf_status_code'] == 1) {//completed
            //check the amounts
            if (round($order->total_paid) != $POSTsignaturePayload['pf_amount']) {
                print_r("NOT THE SAME AMOUNT!!");
                $this->my_http_response_code(400);
            } else {
                $order->setCurrentState($PS_OS_PAYMENT);//paid
                $order->save();
                $this->my_http_response_code(200);
            }
        } else {
            if ($POSTsignaturePayload['pf_status'] == "failed") {
                $order->setCurrentState(8);//FAILED
                $order->save();
                $this->my_http_response_code(200);
            } else {
                $order->setCurrentState(14);//PENDING
                $order->save();
                $this->my_http_response_code(200);
            }
        }

    }

    public function my_http_response_code($response_code)
    {
        if ($response_code !== NULL) {

            switch ($response_code) {
                case 100:
                    $text = 'Continue';
                    break;
                case 101:
                    $text = 'Switching Protocols';
                    break;
                case 200:
                    $text = 'OK';
                    break;
                case 201:
                    $text = 'Created';
                    break;
                case 202:
                    $text = 'Accepted';
                    break;
                case 203:
                    $text = 'Non-Authoritative Information';
                    break;
                case 204:
                    $text = 'No Content';
                    break;
                case 205:
                    $text = 'Reset Content';
                    break;
                case 206:
                    $text = 'Partial Content';
                    break;
                case 300:
                    $text = 'Multiple Choices';
                    break;
                case 301:
                    $text = 'Moved Permanently';
                    break;
                case 302:
                    $text = 'Moved Temporarily';
                    break;
                case 303:
                    $text = 'See Other';
                    break;
                case 304:
                    $text = 'Not Modified';
                    break;
                case 305:
                    $text = 'Use Proxy';
                    break;
                case 400:
                    $text = 'Bad Request';
                    break;
                case 401:
                    $text = 'Unauthorized';
                    break;
                case 402:
                    $text = 'Payment Required';
                    break;
                case 403:
                    $text = 'Prohibido';
                    break;
                case 404:
                    $text = 'No encontrado';
                    break;
                case 405:
                    $text = 'Método no permitido';
                    break;
                case 406:
                    $text = 'Not Acceptable';
                    break;
                case 407:
                    $text = 'Proxy Authentication Required';
                    break;
                case 408:
                    $text = 'Request Time-out';
                    break;
                case 409:
                    $text = 'Conflict';
                    break;
                case 410:
                    $text = 'Gone';
                    break;
                case 411:
                    $text = 'Length Required';
                    break;
                case 412:
                    $text = 'Precondition Failed';
                    break;
                case 413:
                    $text = 'Request Entity Too Large';
                    break;
                case 414:
                    $text = 'Request-URI Too Large';
                    break;
                case 415:
                    $text = 'Unsupported Media Type';
                    break;
                case 500:
                    $text = 'Internal Server Error';
                    break;
                case 501:
                    $text = 'Not Implemented';
                    break;
                case 502:
                    $text = 'Bad Gateway';
                    break;
                case 503:
                    $text = 'Service Unavailable';
                    break;
                case 504:
                    $text = 'Gateway Time-out';
                    break;
                case 505:
                    $text = 'HTTP Version not supported';
                    break;
                default:
                    exit('Unknown http status code "' . htmlentities($response_code) . '"');
                    break;
            }

            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

            $header = $protocol . ' ' . $response_code . ' ' . $text;
            header($header);
            die($text);
        }
    }


    public function generateSignature($payload, $tokenSecret)
    {
        $signatureString = "";
        ksort($payload);
        foreach ($payload as $key => $value) {
            $valueWithoutNull = str_replace("null", "", $value);
            $signatureString .= $key . $valueWithoutNull;
        }

        $signature = hash_hmac('sha256', $signatureString, $tokenSecret);
        return $signature;
    }
}
