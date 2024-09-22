<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT Free License
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/license/mit
 *
 * @author    Andrei H
 * @copyright Since 2024 Andrei H
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class ImageFillColor extends Module
{
    private const SELECTED_COLOR = 'IMAGEFILLCOLOR_SELECTED_COLOR';

    public function __construct()
    {
        $this->name = 'imagefillcolor';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Andrei H';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_,
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Image Fill Color', [], 'Modules.Imagefillcolor.Admin');
        $this->description = $this->trans('PrestaShop module that enables setting the image fill color for a product image.', [], 'Modules.Imagefillcolor.Admin');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', [], 'Modules.Imagefillcolor.Admin');
    }

    /**
     * {@inheritDoc}
     */
    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        Configuration::updateValue(self::SELECTED_COLOR, $this->hexToRgb('#FFFFFF'));

        return parent::install();
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall()
    {
        Configuration::deleteByName(self::SELECTED_COLOR);

        return parent::uninstall();
    }

    /**
     * {@inheritDoc}
     */
    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getContent()
    {
        if (Tools::isSubmit('submit_' . $this->name)) {
            $this->postProcess();
            $this->context->smarty->assign('success', 1);
        }

        $this->context->smarty->assign(array_merge($this->getConfigFormValues(), $this->getConfigFormAdditonalData()));

        return $this->display($this->_path, '/views/templates/admin/configure.tpl');
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return [
            'selectedColor' => [
                'value' => $this->rgbToHex(Configuration::get(self::SELECTED_COLOR)),
                'name' => self::SELECTED_COLOR,
            ],
        ];
    }

    /**
     * Get config form additonal data.
     *
     * @return array
     */
    protected function getConfigFormAdditonalData()
    {
        return [
            'currentIdex' => $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' .
                $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            'submitAction' => 'submit_' . $this->name,
        ];
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $formValues = $this->getConfigFormValues();

        foreach ($formValues as $formValue) {
            if ($formValue['name'] === self::SELECTED_COLOR) {
                Configuration::updateValue($formValue['name'], $this->hexToRgb(Tools::getValue($formValue['name'])));
            } else {
                Configuration::updateValue($formValue['name'], Tools::getValue($formValue['name']));
            }
        }
    }

    protected function hexToRgb($hex)
    {
        $hex = str_replace('#', '', $hex);

        if (strlen($hex) === 3) {
            $hex = $hex . $hex;
        }

        $color = [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];

        return json_encode($color);
    }

    protected function rgbToHex($color)
    {
        $color = json_decode($color, true);

        return sprintf('#%02x%02x%02x', $color['r'], $color['g'], $color['b']);
    }
}
