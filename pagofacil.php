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

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PagoFacil extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    var $token_service;
    var $token_secret;
    //TODO Change servers!
    var $server_desarrollo = "https://t.pagofacil.xyz/v1";
    var $server_produccion = "https://t.pagofacil.xyz/v1";

    public function __construct()
    {
        $this->name = 'pagofacil';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Cristian Tejada';
        $this->controllers = array('validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Pago Fácil');
        $this->description = $this->l('Pago Fácil');

        $config = Configuration::getMultiple(array('TOKEN_SERVICE', 'TOKEN_SECRET'));
        if (!empty($config['TOKEN_SERVICE'])) {
            $this->token_service = $config['TOKEN_SERVICE'];
        }
        if (!empty($config['TOKEN_SECRET'])) {
            $this->token_secret = $config['TOKEN_SECRET'];
        }

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }

        if (!isset($this->token_secret) || !isset($this->token_service)) {
            $this->warning = $this->l('Token Service y Token Secret deben de estar configurados para continuar.');
        }
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
            return false;
        }
        return true;
    }

    /*
    * For configuring the plugin
    */

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $token_service = strval(Tools::getValue('TOKEN_SERVICE'));
            $token_secret = strval(Tools::getValue('TOKEN_SECRET'));
            $is_devel = strval(Tools::getValue('ES_DEVEL'));

            if (!$token_service || empty($token_service) || !Validate::isGenericName($token_service)) {
                $output .= $this->displayError($this->l('Token Service no válido'));
                return $output . $this->displayForm();
            }
            if (!$token_secret || empty($token_secret) || !Validate::isGenericName($token_secret)) {
                $output .= $this->displayError($this->l('Token Secret no válido'));
                return $output . $this->displayForm();
            }

            Configuration::updateValue('TOKEN_SERVICE', $token_service);
            Configuration::updateValue('TOKEN_SECRET', $token_secret);
            Configuration::updateValue('ES_DEVEL', $is_devel);

            $output .= $this->displayConfirmation($this->l('Actualizado exitosamente'));
            $output .= $this->displayConfirmation($this->l("$token_service"));
            $output .= $this->displayConfirmation($this->l("$token_secret"));
            $output .= $this->displayConfirmation($this->l("$is_devel"));
        }
        return $output . $this->displayForm();
    }

    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $optionsforselect = array(
            array('id_seleccion' => 'SI', 'name' => 'Si'),
            array('id_seleccion' => 'NO', 'name' => 'No'),
        );

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Token Service'),
                    'name' => 'TOKEN_SERVICE',
                    'size' => 80,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Token Secret'),
                    'name' => 'TOKEN_SECRET',
                    'size' => 80,
                    'required' => true
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Es desarollo ?'),
                    'name' => 'ES_DEVEL',
                    'size' => 2,
                    'options' => array(
                        'query' => $optionsforselect,
                        'id' => 'id_seleccion',
                        'name' => 'name'
                    ),
                    'default' => 1,
                    'required' => true
                )
            ),
            'submit' => array(
                'title' => $this->l('Guardar'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                        '&token=' . Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['TOKEN_SERVICE'] = Configuration::get('TOKEN_SERVICE');
        $helper->fields_value['TOKEN_SECRET'] = Configuration::get('TOKEN_SECRET');
        $helper->fields_value['ES_DEVEL'] = Configuration::get('ES_DEVEL');

        return $helper->generateForm($fields_form);
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = [
            $this->getExternalPaymentOption($params),
        ];

        return $payment_options;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getExternalPaymentOption($params)
    {

        $order = $params['order'];

        if (!Validate::isLoadedObject($order)) {
            //do what you need to do
            $order_id = (int)$order->id;

        }

        $externalOption = new PaymentOption();
        $cart_amount = Context::getContext()->cart->getOrderTotal(true);
        $customer_email = Context::getContext()->customer->email;
        $token_store = md5(date('m/d/Y h:i:s a', time()) . $order_id . $this->token_service);

        var_dump("viene token secret", $this->token_secret);

        $signature = $this->generateSignature($cart_amount, $customer_email, $order_id, $this->token_service, $token_store, $this->token_secret);

        var_dump("viene signature ", $signature);

        $externalOption->setCallToActionText($this->l('Pagar con Tarjeta de Crédito o Débito'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
            ->setInputs([
                'pf_amount' => [
                    'name' => 'pf_amount',
                    'type' => 'hidden',
                    'value' => $cart_amount
                ],
                'pf_email' => [
                    'name' => 'pf_email',
                    'type' => 'hidden',
                    'value' => $customer_email
                ],
                'pf_order_id' => [
                    'name' => 'pf_order_id',
                    'type' => 'hidden',
                    'value' => $order_id
                ],
                'pf_token_service' => [
                    'name' => 'pf_token_service',
                    'type' => 'hidden',
                    'value' => $this->token_service
                ],
                'pf_token_store' => [
                    'name' => 'pf_token_store',
                    'type' => 'hidden',
                    'value' => $token_store
                ],
                'pf_signature' => [
                    'name' => 'pf_signature',
                    'type' => 'hidden',
                    'value' => $signature
                ]

            ])
            ->setAdditionalInformation($this->context->smarty->fetch('module:pagofacil/views/templates/front/payment_infos.tpl'));
        //->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/payment.jpg'));

        return $externalOption;
    }

    public function generateSignature($pf_amount, $pf_email, $pf_order_id, $pf_token_service, $pf_token_store, $pf_token_secret)
    {
        $signatureArray = array();
        $signatureString = "";
        array_push($signatureArray, "pf_amount=" . $pf_amount, "pf_email=" . $pf_email, "pf_order_id=" . $pf_order_id, "pf_token_service=" . $pf_token_service, "pf_token_store=" . $pf_token_store);
        ksort($signatureArray);

        foreach ($signatureArray as $key => $value) {
            $signatureString .= $value;
        }

        $finalSignature = hash_hmac('sha256', $signatureString, $pf_token_secret);

        return ($finalSignature);
    }

}
