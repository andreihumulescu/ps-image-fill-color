{*
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
*}
{if isset($success)}
    <div class="module_confirmation conf confirm alert alert-success">
        {l s='Successfully updated the settings' d='Modules.Imagefillcolor.Admin'}
    </div>
{/if}

<form id="module_form" class="defaultForm form-horizontal" action="{$currentIdex|escape:'html':'UTF-8'}" method="POST" enctype="multipart/form-data">
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-gear"></i>
            {l s='Image Fill Color Configuration' d='Modules.Imagefillcolor.Admin'}
        </div>
        <div class="form-wrapper">
            <div class="form-group">
                <label class="control-label col-lg-4 required" for="{$selectedColor['name']|escape:'html':'UTF-8'}">
                    {l s='Image Fill Color' d='Modules.Imagefillcolor.Admin'}
                </label>
                <div class="col-lg-8">
                    <input
                        type="color"
                        id="{$selectedColor['name']|escape:'html':'UTF-8'}"
                        name="{$selectedColor['name']|escape:'html':'UTF-8'}"
                        value="{$selectedColor['value']|escape:'html':'UTF-8'}"
                    />
                </div>
            </div>
        </div>
        <div class="panel-footer">
            <button type="submit" value="1" id="module_form_submit_btn" name="{$submitAction|escape:'html':'UTF-8'}" class="btn btn-default pull-right">
                {l s='Save' d='Modules.Imagefillcolor.Admin'}
            </button>
        </div>
    </div>
</form>
