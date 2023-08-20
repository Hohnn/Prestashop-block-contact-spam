<?php
/**
 * 2007-2019 PrestaShop
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
 * @author PrestaShop SA <contact@prestashop.com>
 * @copyright  2007-2019 PrestaShop SA
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

class blockcontactspam extends Module
{

    /** @var string */
    const QUESTION = 'CONTACTFORM_QUESTION';

    /** @var string */
    const ANSWER = 'CONTACTFORM_ANSWER';

    /** @var string */
    const SUBMIT_NAME = 'update-configuration';

    public function __construct()
    {
        // Settings
        $this->name = 'blockcontactspam';
        $this->tab = 'seo';
        $this->version = '1.0.0';
        $this->author = 'DIOQA';
        $this->need_instance = false;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = 'Bloque contact spam';
        $this->description = 'Module qui permet de bloquer les spams sur la page de contact';
    }

    /**
     * install pre-config
     *
     * @return bool
     */
    public function install()
    {
        // Hooks
        return parent::install() &&
        $this->registerHook('displayblockcontactspam');
    }

    public function hookDisplayblockcontactspam($params)
    {
        $this->smarty->assign([
            'question' => Configuration::get(self::QUESTION),
        ]);
        return $this->fetch('module:' . $this->name . '/views/templates/hook/question.tpl');
    }

    /**
     * Uninstall module configuration
     *
     * @return bool
     */
    public function uninstall()
    {
        if (parent::uninstall()) {
            return true;
        }

        $this->_errors[] = 'There was an error during the uninstallation';

        return false;
    }


    /**
     * @return string
     *
     * @throws PrestaShopException
     */
    public function getContent()
    {
        $html = $this->renderForm();

        if (Tools::getValue(self::SUBMIT_NAME)) {

            if (Configuration::updateValue(
                self::QUESTION,
                Tools::getValue(self::QUESTION)
            ) &&
            Configuration::updateValue(
                self::ANSWER,
                Tools::getValue(self::ANSWER)
            )) {
                $html .= $this->displayConfirmation($this->l('Information successfully updated.'));
            } else {
                $html .= $this->displayError($this->l('une erreur inattendue s’est produite.'));
            }
            
        }

        return $html;
    }

    /**
     * @return string
     */
    protected function renderForm()
    {
        $fieldsValue = [
            self::QUESTION => Tools::getValue(
                self::QUESTION,
                Configuration::get(self::QUESTION)
            ),
            self::ANSWER => Tools::getValue(
                self::ANSWER,
                Configuration::get(self::ANSWER)
            )
        ];
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Parameters', [], 'Modules.Contactform.Admin'),
                    'icon' => 'icon-envelope'
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => 'Question',
                        'desc' => 'La question posé aux utilisateur',
                        'name' => self::QUESTION,
                        'is_bool' => false,
                        'required' => true
                    ],
                    [
                        'type' => 'text',
                        'label' => 'Réponse',
                        'desc' => 'La réponse attendu à la question',
                        'name' => self::ANSWER,
                        'is_bool' => false,
                        'required' => true
                    ]
                ],
                'submit' => [
                    'name' => self::SUBMIT_NAME,
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ]
            ],
        ];
        $helper = new HelperForm();
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->submit_action = 'update-configuration';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $fieldsValue,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        ];

        return $helper->generateForm([$form]);
    }

}
