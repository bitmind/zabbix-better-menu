<?php

declare(strict_types=1);

namespace Modules\CustomMenu\Actions;

use CController;
use CControllerResponseData;
use APP;

/**
 * Controller per la visualizzazione della pagina di configurazione del modulo.
 */
class ModuleConfigEditAction extends CController {

    /**
     * Inizializzazione del controller.
     */
    public function init(): void {
        // Disabilita il controllo SID (CSRF) per la visualizzazione della pagina di edit (GET)
        $this->disableCsrfValidation();
    }

    /**
     * Verifica i parametri di input. In questo caso nessuno è richiesto per caricare la pagina.
     * * @return bool
     */
    protected function checkInput(): bool {
        return true;
    }

    /**
     * Verifica che l'utente abbia i permessi di Super Admin per configurare i moduli.
     * * @return bool
     */
    protected function checkPermissions(): bool {
        return $this->getUserType() == USER_TYPE_SUPER_ADMIN;
    }

    /**
     * Esegue l'azione: recupera la configurazione attuale e prepara la vista.
     */
	protected function doAction(): void {
	    // Recuperiamo l'istanza del modulo tramite il suo ID (definito nel manifest.json)
	    $module = APP::ModuleManager()->getModule('custom_menu');
    
	    // Ora possiamo leggere la configurazione
	    $config = $module->getConfig();

	    $data = [
	        'config' => $config + [
	            'show_icons_level2' => 1
	        ]
	    ];

	    $response = new CControllerResponseData($data);
	    $response->setTitle(_('Configuration of Custom Menu'));
	    $this->setResponse($response);
	}
}