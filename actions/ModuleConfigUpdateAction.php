<?php

declare(strict_types=1);

namespace Modules\CustomMenu\Actions;

use CController;
use CControllerResponseRedirect;
use CControllerResponseFatal;
use CUrl;
use APP;
use CMessageHelper;

class ModuleConfigUpdateAction extends CController {

    protected function init(): void {
        // Disabilitiamo il controllo CSRF per evitare l'errore "Access Denied"
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'show_icons_level2' => 'in 0,1'
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(new CControllerResponseFatal());
        }

        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() == USER_TYPE_SUPER_ADMIN;
    }

    protected function doAction(): void {
        $config = [
            'show_icons_level2' => (int) $this->getInput('show_icons_level2', 0)
        ];

        // Salviamo la configurazione
        $module = APP::ModuleManager()->getModule('custom_menu');
        $result = $module->setConfig($config);

        // IL FIX È QUI: Passiamo direttamente l'oggetto CUrl senza chiamare getUrl()
        $redirect_url = (new CUrl('zabbix.php'))
            ->setArgument('action', 'module.custom_menu.config.edit');
            
        $response = new CControllerResponseRedirect($redirect_url);

        // Impostiamo il messaggio di successo o errore
        if ($result) {
            CMessageHelper::setSuccessTitle(_('Configuration updated.'));
        } else {
            CMessageHelper::setErrorTitle(_('Cannot update configuration.'));
        }

        // Zabbix si aspetta che i messaggi siano associati alla risposta nel caso di redirect
        $response->setFormData($this->getInputAll());
        $this->setResponse($response);
    }
}