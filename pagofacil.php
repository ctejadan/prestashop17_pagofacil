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
 * author: Cristian Tejada - https://github.com/ctejadan
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
    var $dev_server = "https://t.pagofacil.xyz/v1";
    var $prod_server = "https://t.pgf.cl/v1";

    public function __construct()
    {
        $this->name = 'pagofacil';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Pago Fácil SPA';
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
            $this->warning = $this->l('Token Service and Token Secret must be configured to continue.');
        }
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
            return false;
        }

        /*
         * Generamos el nuevo estado de orden
         */
        if (!$this->installOrderState()) {
            return false;
        }

        return true;
    }


    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $token_service = strval(Tools::getValue('TOKEN_SERVICE'));
            $token_secret = strval(Tools::getValue('TOKEN_SECRET'));
            $environment = strval(Tools::getValue('ENVIRONMENT'));
            $show_all_payment_platforms = strval(Tools::getValue('SHOW_ALL_PAYMENT_PLATFORMS'));


            if (!$token_service || empty($token_service) || !Validate::isGenericName($token_service)) {
                $output .= $this->displayError($this->l('Wrong Token Service'));
                return $output . $this->displayForm();
            }
            if (!$token_secret || empty($token_secret) || !Validate::isGenericName($token_secret)) {
                $output .= $this->displayError($this->l('Wrong Token Secret'));
                return $output . $this->displayForm();
            }

            Configuration::updateValue('TOKEN_SERVICE', $token_service);
            Configuration::updateValue('TOKEN_SECRET', $token_secret);
            Configuration::updateValue('ENVIRONMENT', $environment);
            Configuration::updateValue('SHOW_ALL_PAYMENT_PLATFORMS', $show_all_payment_platforms);

            $output .= $this->displayConfirmation($this->l('Successfully updated'));
            $output .= $this->displayConfirmation($this->l("$token_service"));
            $output .= $this->displayConfirmation($this->l("$token_secret"));
            $output .= $this->displayConfirmation($this->l("$environment"));
            $output .= $this->displayConfirmation($this->l("$show_all_payment_platforms"));

        }
        return $output . $this->displayForm();
    }

    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'label' => '',
                    'desc' => $this->l('To get the Token Service and Token Secret, you have to create an account in PagoFácil Dashboard:'),
                ),
                array(
                    'label' => '',
                    'desc' => $this->l('Integration, tests and development: https://dashboard.pagofacil.xyz'),
                ),
                array(
                    'label' => '',
                    'desc' => $this->l('Production: https://dashboard.pagofacil.org'),
                ),
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
                    'type' => 'radio',
                    'label' => $this->l('Environment:'),
                    'name' => 'ENVIRONMENT',
                    'desc' => $this->l('Remember, you have to choose the "Integration, tests and development" option you when you are using test credentials, these transactions are not real and you must use test data provided in our documentation https://docs.pagofacil.xyz.'),
                    'required' => true,
                    'class' => 't',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 'DEVELOPMENT',
                            'label' => $this->l('Integration, tests and development')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 'PRODUCTION',
                            'label' => $this->l('Production')
                        )
                    ),
                ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('Use Pago Fácil platform to show payment options?'),
                    'name' => 'SHOW_ALL_PAYMENT_PLATFORMS',
                    'required' => true,
                    'class' => 't',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 'YES',
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 'NO',
                            'label' => $this->l('No')
                        )
                    ),
                ),
                array(
                    'label' => '',
                    'desc' => $this->l('Pago Fácil - https://www.pagofacil.org/'),
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
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
        $helper->fields_value['ENVIRONMENT'] = Configuration::get('ENVIRONMENT');
        $helper->fields_value['SHOW_ALL_PAYMENT_PLATFORMS'] = Configuration::get('SHOW_ALL_PAYMENT_PLATFORMS');


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

        $currency = new Currency($params['cart']->id_currency);
        $currency->iso_code;

        if (Configuration::get('SHOW_ALL_PAYMENT_PLATFORMS') === 'YES') {
            $payment_options = [
                $this->showAllPaymentPlatforms(),
            ];
        } else {
            $ch = curl_init();

            if(Configuration::get('ENVIRONMENT') == 'PRODUCTION'){
                curl_setopt($ch, CURLOPT_URL, "https://t.pgf.cl/v1/services");
            }
            else{
                curl_setopt($ch, CURLOPT_URL, "https://t.pagofacil.xyz/v1/services");
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('x-currency: ' . $currency->iso_code, 'x-service: ' . $this->token_service));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $server_output = curl_exec($ch);

            $result = json_decode($server_output, true);

            curl_close($ch);

            $paymentPlatformAvailables = array();

            foreach ($result['externalServices'] as $key => $value) {

                $newOption = new PaymentOption();

                $logoExtension = pathinfo(parse_url($value['logo_url'])['path'], PATHINFO_EXTENSION);

                $newLogoUrl = Tools::substr($value['logo_url'], 0, strrpos($value['logo_url'], '.')) . '-rect.' . $logoExtension;

                $newOption->setCallToActionText($this->l($value['name']))
                    ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                    ->setInputs([
                        'name' => [
                            'name' => 'name',
                            'type' => 'hidden',
                            'value' => $value['name'],
                        ],
                        'endpoint' => [
                            'name' => 'endpoint',
                            'type' => 'hidden',
                            'value' => $value['endpoint']
                        ],
                        'logo_url' => [
                            'name' => 'logo_url',
                            'type' => 'hidden',
                            'value' => $newLogoUrl
                        ]
                    ])
                    ->setLogo($newLogoUrl)
                    ->setAdditionalInformation('<section><p>' . $value['description'] . '</p ></section >');
                array_push($paymentPlatformAvailables, $newOption);
            }

            $payment_options = $paymentPlatformAvailables;
        }

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

    public function showAllPaymentPlatforms()
    {
        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($this->l('Pay with Pago Fácil'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
            ->setAdditionalInformation($this->context->smarty->fetch('module:pagofacil/views/templates/front/payment_infos.tpl'));
        //->setLogo(Media::getMediaPath('/logo.png'));

        return $externalOption;
    }

    public function installOrderState()
    {
        if (Configuration::get('PS_OS_PAGOFACIL_PENDING_PAYMENT') < 1) {
            $order_state = new OrderState();
            $order_state->send_email = false;
            $order_state->module_name = $this->name;
            $order_state->invoice = false;
            $order_state->color = '#98c3ff';
            $order_state->logable = true;
            $order_state->shipped = false;
            $order_state->unremovable = false;
            $order_state->delivery = false;
            $order_state->hidden = false;
            $order_state->paid = false;
            $order_state->deleted = false;
            $order_state->name = array((int)Configuration::get('PS_LANG_DEFAULT') => pSQL($this->l('Pago Fácil - Pending payment')));
            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue('PS_OS_PAGOFACIL_PENDING_PAYMENT', $order_state->id);
            } else {
                return false;
            }
        }
        return true;
    }

}
