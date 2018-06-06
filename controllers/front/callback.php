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

require_once(_PS_MODULE_DIR_ . 'pagofacil' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'pfhelper' . DIRECTORY_SEPARATOR . 'PagoFacilHelper.php');

class PagoFacilCallbackModuleFrontController extends ModuleFrontController
{
    var $token_secret;
    var $token_service;

    public function initContent()
    {
        $PFHelper = new PagoFacilHelper();

        $config = Configuration::getMultiple(array('TOKEN_SERVICE', 'TOKEN_SECRET'));
        $this->token_service = $config['TOKEN_SERVICE'];
        $this->token_secret = $config['TOKEN_SECRET'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processCallback();

            $PFHelper->httpResponseCode(200);
        } else {
            $PFHelper->httpResponseCode(405);
        }
    }

    protected function processCallback()
    {
        $PFHelper = new PagoFacilHelper();

        $POSTsignaturePayload = json_decode(file_get_contents('php://input'), true);
        $POSTsignature = $POSTsignaturePayload['pf_signature'];
        unset($POSTsignaturePayload['pf_signature']);

        $generatedSignature = $PFHelper->generateSignature($POSTsignaturePayload, $this->token_secret);

        if ($generatedSignature !== $POSTsignature) {
            print_r("NOT THE SAME SIGNATURE!!");
            $PFHelper->httpResponseCode(400);
        }

        $cart = new Cart((int)Cart::getCartIdByOrderId($POSTsignaturePayload['pf_order_id']));

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 ||
            $cart->id_address_invoice == 0 || !$this->module->active) {
            $PFHelper->httpResponseCode(404);
        }

        // Check if customer exists
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $PFHelper->httpResponseCode(412);
        }

        //Obtenemos la orden
        $order = new Order($POSTsignaturePayload['pf_order_id']);

        //Si la orden está completada no hago nada.
        $PS_OS_PAYMENT = Configuration::get('PS_OS_PAYMENT');//Accepted

        if ($PS_OS_PAYMENT == $order->getCurrentState()) {
            print_r("THE ORDER IS ALREADY PAID!");
            $PFHelper->httpResponseCode(400);
        }

        if ($POSTsignaturePayload['pf_status_code'] == 1) {//completed
            //check the amounts
            if (round($order->total_paid) != $POSTsignaturePayload['pf_amount']) {
                print_r("NOT THE SAME AMOUNT!!");
                $PFHelper->httpResponseCode(400);
            } else {
                $order->setCurrentState($PS_OS_PAYMENT);//paid
                $order->save();
                $PFHelper->httpResponseCode(200);
            }
        } else {
            if ($POSTsignaturePayload['pf_status'] == "failed") {
                $order->setCurrentState(8);//FAILED
                $order->save();
                $PFHelper->httpResponseCode(200);
            } else {
                $order->setCurrentState(14);//PENDING
                $order->save();
                $PFHelper->httpResponseCode(200);
            }
        }

    }

}
