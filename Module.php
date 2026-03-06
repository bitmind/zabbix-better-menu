<?php

declare(strict_types=0);

namespace Modules\CustomMenu;

use Zabbix\Core\CModule;
use APP;
use CMenu;
use CMenuItem;
use CUrl;
use CWebUser;
use CRoleHelper;

class Module extends CModule
{
    // IDs delle sezioni standard di Zabbix (corrispondono agli ID HTML del menu)
    private const STANDARD_MENU_IDS = [
        'dashboard', 'view', 'services', 'cm', 'reports',
        'config', 'alerts', 'users-menu', 'admin'
    ];

    // Azioni/URL standard di Zabbix per identificare voci di sottomenu da ignorare
    private const STANDARD_ACTIONS = [
        // Monitoring (view)
        'dashboard.view', 'dashboard.list', 'problem.view', 'host.view',
        'latest.view', 'map.view', 'discovery.view', 'web.view',
        'charts.view', 'host.dashboard.view',
        // Services
        'service.list', 'sla.list', 'slareport.list',
        // Inventory (cm)
        'hostinventoriesoverview.php', 'hostinventories.php',
        // Reports
        'report.status', 'scheduledreport.list', 'report2.php',
        'toptriggers.list', 'auditlog.list', 'actionlog.list', 'report4.php',
        // Data collection (config)
        'templategroup.list', 'hostgroup.list', 'template.list', 'templates.php',
        'host.list', 'maintenance.list', 'correlation.list', 'discovery.list',
        // Alerts
        'action.list', 'mediatype.list', 'script.list',
        // Users
        'usergroup.list', 'userrole.list', 'user.list', 'token.list', 'authentication.edit',
        // Administration
        'gui.edit', 'autoreg.edit', 'timeouts.edit', 'image.list', 'iconmap.list',
        'regex.list', 'trigdisplay.edit', 'geomaps.edit', 'module.list',
        'connector.list', 'miscconfig.edit', 'audit.settings.edit',
        'housekeeping.edit', 'proxygroup.list', 'proxy.list', 'macros.edit',
        'queue.overview', 'queue.overview.proxy', 'queue.details'
    ];

    /**
     * Verifica se una voce di menu ha un'azione/URL standard di Zabbix
     */
    private function isStandardMenuItem(CMenuItem $item): bool
    {
        $action = $item->getAction();
        $url = $item->getUrl();

        // Controlla l'action
        if ($action !== null && in_array($action, self::STANDARD_ACTIONS, true)) {
            return true;
        }

        // Controlla l'URL
        if ($url !== null) {
            $urlStr = $url->toString();
            foreach (self::STANDARD_ACTIONS as $stdAction) {
                if (strpos($urlStr, $stdAction) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Estrae ricorsivamente le voci extra da un sottomenu, preservando la gerarchia
     */
    private function extractExtraFromSubmenu(?CMenu $submenu): array
    {
        if ($submenu === null) {
            return [];
        }

        $extraItems = [];

        foreach ($submenu->getMenuItems() as $subItem) {
            $hasNested = $subItem->hasSubMenu();
            $isStandard = $this->isStandardMenuItem($subItem);

            if (!$isStandard) {
                if ($hasNested) {
                    // È un contenitore extra con sottomenu (es. "Availability")
                    // Filtra ricorsivamente il sottomenu per tenere solo gli extra
                    $nestedExtras = $this->extractExtraFromSubmenu($subItem->getSubMenu());

                    if (!empty($nestedExtras)) {
                        // Crea una copia del menu item con solo le voci extra
                        $containerItem = (new CMenuItem($subItem->getLabel()))
                            ->setSubMenu(new CMenu($nestedExtras));

                        // Copia l'icona se presente
                        $extraItems[] = $containerItem;
                    }
                } else {
                    // Voce extra singola - preservala così com'è
                    $extraItems[] = $subItem;
                }
            } else {
                // Voce standard ma potrebbe avere sotto-voci extra annidate
                if ($hasNested) {
                    $nestedExtras = $this->extractExtraFromSubmenu($subItem->getSubMenu());
                    // Aggiungi le voci extra annidate direttamente (non preservare il contenitore standard)
                    $extraItems = array_merge($extraItems, $nestedExtras);
                }
            }
        }

        return $extraItems;
    }

    /**
     * Inizializzazione del modulo.
     * Agiamo direttamente sui componenti globali all'avvio.
     * Preserva le voci aggiunte da altri moduli sotto la sezione "Extra".
     */
    public function init(): void
    {
        // 1. Otteniamo il menu principale esistente
        $menu = APP::Component()->get('menu.main');

        // 2. Analizziamo le voci esistenti
        $extraItems = [];
        $labelsToRemove = [];

        foreach ($menu->getMenuItems() as $item) {
            $itemId = $item->getId();
            $label = $item->getLabel();

            // Controlliamo se è una voce standard di Zabbix
            $isStandard = ($itemId !== null && in_array($itemId, self::STANDARD_MENU_IDS, true));

            if ($isStandard) {
                // Menu standard: estrai le voci extra dai sottomenu (se esistono)
                if ($item->hasSubMenu()) {
                    $submenuExtras = $this->extractExtraFromSubmenu($item->getSubMenu());
                    $extraItems = array_merge($extraItems, $submenuExtras);
                }
            } elseif ($label !== null) {
                // Menu non-standard (da altri moduli) - lo preserviamo
                $extraItems[] = $item;
            }

            // Raccogliamo tutte le label per rimuoverle
            if ($label !== null) {
                $labelsToRemove[] = $label;
            }
        }

        // 3. Rimuoviamo tutti gli elementi esistenti uno per uno
        foreach ($labelsToRemove as $label) {
            $menu->remove($label);
        }

        // 4. Aggiungiamo i nuovi elementi del menu personalizzato
        $customMenu = $this->getCustomMainMenu();

		// --- LEGGIAMO LA CONFIGURAZIONE
		$config = $this->getConfig();
		    $showIconsL2 = (bool) ($config['show_icons_level2'] ?? true);

		    if (!$showIconsL2) {
		        foreach ($customMenu->getMenuItems() as $mainItem) {
		            if ($mainItem->hasSubMenu()) {
		                $this->removeIconsRecursively($mainItem->getSubMenu());
		            }
		        }
		    }



        // 5. Trasferisce tutti gli elementi dal menu personalizzato al menu principale
        foreach ($customMenu->getMenuItems() as $item) {
            $menu->add($item);
        }

        // 6. Se ci sono voci extra, le raggruppiamo sotto "Extra"
        if (!empty($extraItems)) {
            $extraSubmenu = new CMenu($extraItems);
            $menu->add(
                (new CMenuItem(_('Extra')))
                    ->setId('quad-extra')
                    //->setIcon(ZBX_ICON_INTEGRATIONS)
                    ->setSubMenu($extraSubmenu)
            );
        }
    }


	/**
	 * Rimuove ricorsivamente le icone da tutti i CMenuItem all'interno di un CMenu
	 */
	private function removeIconsRecursively(CMenu $menu): void
	{
	    foreach ($menu->getMenuItems() as $item) {
	        // Rimuoviamo l'icona da questa voce di sottomenu
	        $item->setIcon('');

	        // Se questa voce ha a sua volta un sottomenu (livello 3+), procediamo
	        if ($item->hasSubMenu()) {
	            $this->removeIconsRecursively($item->getSubMenu());
	        }
	    }
	}


    /**
     * La logica del TUO CMenuHelper personalizzato
     */
    private function getCustomMainMenu(): CMenu
    {
        $menu = new CMenu();

        if (CWebUser::checkAccess(CRoleHelper::UI_MONITORING_DASHBOARD)) {
            $menu->add(
                (new CMenuItem(_('Dashboards')))
                   // ->setId('dashboard')
                    ->setIcon(ZBX_ICON_DASHBOARDS)
                    ->setAction('dashboard.view')
                    ->setAliases(['dashboard.list'])
            );
        }

        // -------------------------------------------------------
        // SEZIONE 1: VIEW (Monitoring, ecc.)
        // -------------------------------------------------------
        $submenu_monitoring = [
            CWebUser::checkAccess(CRoleHelper::UI_MONITORING_HOSTS)
                ? (new CMenuItem(_('Hosts')))
                    ->setAction('host.view')
                    ->setIcon(ZBX_ICON_INVENTORY)
                    ->setAliases(['charts.view', 'chart2.php', 'chart3.php', 'chart6.php', 'chart7.php', 'host.dashboard.view'])
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS)
                ? (new CMenuItem(_('Problems')))
                    ->setAction('problem.view')
                    ->setIcon(ZBX_ICON_BELL)
                    ->setAliases(['tr_events.php'])
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA)
                ? (new CMenuItem(_('Latest data')))
                    ->setAction('latest.view')
                    ->setIcon(ZBX_ICON_DATA_COLLECTION)
                    ->setAliases(['history.php', 'chart.php'])
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_MONITORING_HOSTS)
                ? (new CMenuItem(_('Web monitoring')))
                    ->setUrl(
                        (new CUrl('zabbix.php'))
                            ->setArgument('action', 'web.view')
                            ->setArgument('filter_set', '1')
                    )
                    ->setIcon(ZBX_ICON_HOME)
                    ->setAliases(['web.view','httpdetails.php'])
                : null,
        
                         CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)  //Admin users: show a submenu with all options
                            ? (new CMenuItem(_('Maps')))
                                ->setUrl(new CUrl('sysmaps.php'), 'sysmaps.php')
                                ->setAliases(['image.php', 'sysmaps.php', 'sysmap.php', 'map.php'])
                                ->setIcon(ZBX_ICON_SERVICES)
                                ->setSubMenu(new CMenu(array_filter([
                                    CWebUser::checkAccess(CRoleHelper::UI_MONITORING_MAPS)
                                    ? (new CMenuItem(_('Maps')))
                                        ->setUrl(new CUrl('sysmaps.php'), 'sysmaps.php')
                                        ->setAliases(['image.php', 'sysmaps.php', 'sysmap.php', 'map.php','map.view'])
                                    : null,
                                    CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)
                                    ? (new CMenuItem(_('Icons')))
                                        ->setUrl(
                                            (new CUrl('zabbix.php'))
                                            ->setArgument('action', 'image.list')
                                            ->setArgument('imagetype', '1')
                                        )
                                        ->setAliases(['image.list'])
                                    : null,
                                    CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)
                                    ? (new CMenuItem(_('Backgrounds')))
                                        ->setUrl(
                                            (new CUrl('zabbix.php'))
                                            ->setArgument('action', 'image.list')
                                            ->setArgument('imagetype', '2')
                                        )
                                        ->setAliases(['image.list'])
                                    : null,
                                    CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)
                                    ? (new CMenuItem(_('Icon mapping rules')))
                                        ->setAction('iconmap.list')
                                        ->setAliases(['iconmap.edit'])
                                    : null
                                ])))
                            : (CWebUser::checkAccess(CRoleHelper::UI_MONITORING_MAPS)   //Other users: show a direct link to map list only
                                    ? (new CMenuItem(_('Maps')))
                                        ->setUrl(new CUrl('sysmaps.php'), 'sysmaps.php')
                                        ->setAliases(['image.php', 'sysmaps.php', 'sysmap.php', 'map.php','map.view'])
										->setIcon(ZBX_ICON_SERVICES)
                                    : null),
            CWebUser::checkAccess(CRoleHelper::UI_REPORTS_AVAILABILITY_REPORT)
                ? (new CMenuItem(_('Reports')))
                    ->setIcon(ZBX_ICON_REPORTS)
                    ->setSubMenu(new CMenu(array_filter([
                        CWebUser::checkAccess(CRoleHelper::UI_REPORTS_AVAILABILITY_REPORT)
                            ? (new CMenuItem(_('Availability report')))
                                ->setUrl(new CUrl('report2.php'), 'report2.php')
                                ->setAliases(['chart4.php'])
                            : null,
                        CWebUser::checkAccess(CRoleHelper::UI_REPORTS_TOP_TRIGGERS)
                            ? (new CMenuItem(_('Top 100 triggers')))->setAction('toptriggers.list')
                            : null
                    ])))
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_INVENTORY_OVERVIEW)
                ? (new CMenuItem(_('Inventory')))
                    ->setIcon(ZBX_ICON_INVENTORY)
                    ->setSubMenu(new CMenu(array_filter([
                        CWebUser::checkAccess(CRoleHelper::UI_INVENTORY_OVERVIEW)
                            ? (new CMenuItem(_('Overview')))
                                ->setUrl(new CUrl('hostinventoriesoverview.php'), 'hostinventoriesoverview.php')
                            : null,
                        CWebUser::checkAccess(CRoleHelper::UI_INVENTORY_HOSTS)
                            ? (new CMenuItem(_('Hosts')))
                                ->setUrl(new CUrl('hostinventories.php'), 'hostinventories.php')
                            : null
                    ])))
                : null
        ];

        $submenu_monitoring = array_filter($submenu_monitoring);
        if ($submenu_monitoring) {
            $menu->add(
                (new CMenuItem(_('View')))
                    ->setId('quad-view')
                    ->setIcon(ZBX_ICON_EYE)
                    ->setSubMenu(new CMenu($submenu_monitoring))
            );
        }

        // -------------------------------------------------------
        // SEZIONE 2: CONFIGURE (Data collection, ecc.)
        // -------------------------------------------------------
        $submenu_data_collection = [
            CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
                ? (new CMenuItem(_('Hosts')))
                    ->setAction('host.list')
                    ->setIcon(ZBX_ICON_INVENTORY)
                    ->setAliases([
                        'item.list?context=host', 'trigger.list?context=host', 'graphs.php?context=host',
                        'host_discovery.php?context=host', 'item.prototype.list?context=host',
                        'trigger.prototype.list?context=host', 'host_prototypes.php?context=host',
                        'httpconf.php?context=host', 'host.edit'
                    ])
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOST_GROUPS)
                ? (new CMenuItem(_('Host groups')))->setAction('hostgroup.list')
                    ->setAliases(['hostgroup.edit'])
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
                ? (new CMenuItem(_('Templates')))
                    ->setAction('template.list')
                    ->setIcon(ZBX_ICON_COPY)
                    ->setAliases([
                        'template.dashboard.list', 'template.dashboard.edit', 'item.list?context=template',
                        'trigger.list?context=template', 'graphs.php?context=template',
                        'host_discovery.php?context=template', 'item.prototype.list?context=template',
                        'trigger.prototype.list?context=template', 'host_prototypes.php?context=template',
                        'httpconf.php?context=template'
                    ])
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATE_GROUPS)
                ? (new CMenuItem(_('Template groups')))->setAction('templategroup.list')
                    ->setAliases(['templategroup.edit'])
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
                ? (new CMenuItem(_('Web scenarios')))
                    ->setUrl(
                        (new CUrl('httpconf.php'))
                            ->setArgument('context', 'host')
                            ->setArgument('filter_set', '1')
                    )
                    ->setIcon(ZBX_ICON_HOME)
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_MAINTENANCE)
                ? (new CMenuItem(_('Maintenance')))->setAction('maintenance.list')->setIcon(ZBX_ICON_WRENCH_ALT_SMALL)
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION)
                ? (new CMenuItem(_('Event correlation')))
                    ->setAction('correlation.list')
                    ->setAliases(['correlation.edit'])
                    ->setIcon(ZBX_ICON_MINIMIZE)
                : null,
        ];

        $submenu_data_collection = array_filter($submenu_data_collection);
        if ($submenu_data_collection) {
            $menu->add(
                (new CMenuItem(_('Configure')))
                    ->setId('quad-config')
                    ->setIcon(ZBX_ICON_WRENCH_ALT)
                    ->setSubMenu(new CMenu($submenu_data_collection))
            );
        }

        // -------------------------------------------------------
        // SEZIONE 3: DISCOVER
        // -------------------------------------------------------
        $submenu_discover = [
            CWebUser::checkAccess(CRoleHelper::UI_MONITORING_DISCOVERY)
                ? (new CMenuItem(_('Discovery status')))->setAction('discovery.view')->setIcon(ZBX_ICON_EYE)
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY)
                ? (new CMenuItem(_('Discovery rules')))
                    ->setAction('discovery.list')
                    ->setIcon(ZBX_ICON_WRENCH_ALT)
                    ->setAliases(['discovery.edit'])
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS)
                ? (new CMenuItem(_('Discovery actions')))
                    ->setIcon(ZBX_ICON_BULLET_RIGHT)
                    ->setUrl(
                        (new CUrl('zabbix.php'))
                            ->setArgument('action', 'action.list')
                            ->setArgument('eventsource', EVENT_SOURCE_DISCOVERY),
                        'action.list?eventsource='.EVENT_SOURCE_DISCOVERY
                    )
                : null
        ];

        $submenu_discover = array_filter($submenu_discover);
        if ($submenu_discover) {
            $menu->add(
                (new CMenuItem(_('Discover')))
                    ->setId('quad-discover')
                    ->setIcon(ZBX_ICON_SEARCH)
                    ->setSubMenu(new CMenu($submenu_discover))
            );
        }


        // -------------------------------------------------------
        // SEZIONE 4: EVALUATE (Services)
        // -------------------------------------------------------
        $submenu_services = [
            CWebUser::checkAccess(CRoleHelper::UI_SERVICES_SERVICES)
                ? (new CMenuItem(_('Services')))
                    ->setAction('service.list')
                    ->setIcon(ZBX_ICON_SERVICES)
                    ->setAliases(['service.list.edit'])
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_SERVICES_SLA)
                ? (new CMenuItem(_('SLA')))
                    ->setAction('sla.list')
                    ->setIcon(ZBX_ICON_WRENCH_ALT)
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_SERVICES_SLA_REPORT)
                ? (new CMenuItem(_('SLA report')))
                    ->setAction('slareport.list')
                    ->setIcon(ZBX_ICON_EYE)
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS)
                ? (new CMenuItem(_('Service actions')))
                    ->setIcon(ZBX_ICON_BULLET_RIGHT)
                    ->setUrl(
                        (new CUrl('zabbix.php'))
                            ->setArgument('action', 'action.list')
                            ->setArgument('eventsource', EVENT_SOURCE_SERVICE),
                        'action.list?eventsource='.EVENT_SOURCE_SERVICE
                    )
                : null
        ];

        $submenu_services = array_filter($submenu_services);
        if ($submenu_services) {
            $menu->add(
                (new CMenuItem(_('Evaluate')))
                    ->setId('quad-services')
                    ->setIcon(ZBX_ICON_SERVICES)
                    ->setSubMenu(new CMenu($submenu_services))
            );
        }

        // -------------------------------------------------------
        // SEZIONE 5: REACT (Alerts)
        // -------------------------------------------------------
        $submenu_alerts = [
            (CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS) ||
            CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS) ||
            CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS) ||
            CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_AUTOREGISTRATION_ACTIONS) ||
            CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_INTERNAL_ACTIONS))
                ? (new CMenuItem(_('Actions')))
                    ->setIcon(ZBX_ICON_BULLET_RIGHT)
                    ->setSubMenu(new CMenu(array_filter([
                        CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS)
                            ? (new CMenuItem(_('Trigger actions')))
                                ->setUrl(
                                    (new CUrl('zabbix.php'))
                                        ->setArgument('action', 'action.list')
                                        ->setArgument('eventsource', EVENT_SOURCE_TRIGGERS),
                                    'action.list?eventsource='.EVENT_SOURCE_TRIGGERS
                                )
                            : null,
                        CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS)
                            ? (new CMenuItem(_('Service actions')))
                                ->setUrl(
                                    (new CUrl('zabbix.php'))
                                        ->setArgument('action', 'action.list')
                                        ->setArgument('eventsource', EVENT_SOURCE_SERVICE),
                                    'action.list?eventsource='.EVENT_SOURCE_SERVICE
                                )
                            : null,
                        CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS)
                            ? (new CMenuItem(_('Discovery actions')))
                                ->setUrl(
                                    (new CUrl('zabbix.php'))
                                        ->setArgument('action', 'action.list')
                                        ->setArgument('eventsource', EVENT_SOURCE_DISCOVERY),
                                    'action.list?eventsource='.EVENT_SOURCE_DISCOVERY
                                )
                            : null,
                        CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_AUTOREGISTRATION_ACTIONS)
                            ? (new CMenuItem(_('Autoregistration actions')))
                                ->setUrl(
                                    (new CUrl('zabbix.php'))
                                        ->setArgument('action', 'action.list')
                                        ->setArgument('eventsource', EVENT_SOURCE_AUTOREGISTRATION),
                                    'action.list?eventsource='.EVENT_SOURCE_AUTOREGISTRATION
                                )
                            : null,
                        CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_INTERNAL_ACTIONS)
                            ? (new CMenuItem(_('Internal actions')))
                                ->setUrl(
                                    (new CUrl('zabbix.php'))
                                        ->setArgument('action', 'action.list')
                                        ->setArgument('eventsource', EVENT_SOURCE_INTERNAL),
                                    'action.list?eventsource='.EVENT_SOURCE_INTERNAL
                                )
                            : null
                    ])))
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES)
                ? (new CMenuItem(_('Media types')))
                    ->setAction('mediatype.list')
                    ->setIcon(ZBX_ICON_ENVELOPE_FILLED)
                    ->setAliases(['mediatype.edit'])
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_SCRIPTS)
                ? (new CMenuItem(_('Scripts')))
                    ->setAction('script.list')
                    ->setIcon(ZBX_ICON_COMMAND)
                    ->setAliases(['script.edit'])
                : null,
            CWebUser::checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS)
                ? (new CMenuItem(_('Scheduled reports')))
                    ->setAction('scheduledreport.list')
                    ->setIcon(ZBX_ICON_REPORTS)
                    ->setAliases(['scheduledreport.edit'])
                : null
        ];

        $submenu_alerts = array_filter($submenu_alerts);
        if ($submenu_alerts) {
            $menu->add(
                (new CMenuItem(_('React')))
                    ->setId('quad-alerts')
                    ->setIcon(ZBX_ICON_ALERTS)
                    ->setSubMenu(new CMenu($submenu_alerts))
            );
        }



        // -------------------------------------------------------
        // SEZIONE 6: ADMIN
        // -------------------------------------------------------
        $submenu_administration = [
            CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS)
                ? (new CMenuItem(_('Users')))
                    ->setIcon(ZBX_ICON_USERS)
                    ->setSubMenu(new CMenu(array_filter([
                        CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_USERS)
                            ? (new CMenuItem(_('Users')))
                                ->setAction('user.list')
                                    ->setIcon(ZBX_ICON_USER)
                                ->setAliases(['user.edit'])
                            : null,
                        CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_GROUPS)
                            ? (new CMenuItem(_('User groups')))
                                ->setAction('usergroup.list')
                                    ->setIcon(ZBX_ICON_USERS)
                                ->setAliases(['usergroup.edit'])
                            : null,
                        CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_USER_ROLES)
                            ? (new CMenuItem(_('User roles')))
                                ->setAction('userrole.list')
                                    ->setIcon(ZBX_ICON_USERS_FILLED)
                                ->setAliases(['userrole.edit'])
                            : null,
                        (!CWebUser::isGuest() && CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_API_TOKENS) &&
                            CWebUser::checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS))
                            ? (new CMenuItem(_('API tokens')))
                                ->setAction('token.list')
                            : null
                    ])))
                : null,
                        (CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES) ||
                         CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXY_GROUPS))
                                        ? (new CMenuItem(_('Proxies')))
                                            ->setIcon(ZBX_ICON_SERVICES)
                                            ->setSubMenu(new CMenu(array_filter([

                                    CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)
                                        ? (new CMenuItem(_('Proxies')))
                                            ->setAction('proxy.list')
                                            ->setAliases(['proxy.edit'])
                                        : null,
                                    CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXY_GROUPS)
                                        ? (new CMenuItem(_('Proxy groups')))
                                            ->setAction('proxygroup.list')
                                            ->setIcon('')
                                            ->setAliases(['proxygroup.edit'])
                                        : null,
                                    CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_QUEUE)
                                        ? (new CMenuItem(_('Queue by proxy')))
                                        ->setUrl(
                                            (new CUrl('zabbix.php'))
                                            ->setArgument('action', 'queue.overview.proxy')
                                        )
                                        : null
                                ])))
                                : null,			
											(CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL) ||
											 CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_MACROS) )
												? (new CMenuItem(_('Global')))
													->setIcon(ZBX_ICON_COPY)
													->setSubMenu(new CMenu(array_filter([
														CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_MACROS)
															? (new CMenuItem(_('Macros')))
																->setAction('macros.edit')
															: null,
														(new CMenuItem(_('Regular expressions')))
															->setAction('regex.list')
															->setAliases(['regex.edit']),								
														(new CMenuItem(_('Timeouts')))
															->setAction('timeouts.edit')			
													])))
												: null,		
                                            (CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL) ||
                                              CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION))
                                                ? (new CMenuItem(_('Frontend')))
                                                    ->setSubMenu(new CMenu(array_filter([
                                                        (new CMenuItem(_('Authentication')))
                                                            ->setAction('authentication.edit'),
                                                        (new CMenuItem(_('GUI')))
                                                            ->setAction('gui.edit'),
														(new CMenuItem(_('Menu settings')))
														    ->setAction('module.custom_menu.config.edit'), // Custom menu configuration page
                                                        (new CMenuItem(_('Modules')))
                                                            ->setAction('module.list')
                                                            //->setIcon(ZBX_ICON_INTEGRATIONS)
                                                            ->setAliases(['module.edit', 'module.scan']),
                                                        (new CMenuItem(_('Trigger options')))
                                                            ->setAction('trigdisplay.edit'),
                                                        (new CMenuItem(_('Geomap widget')))
                                                            ->setAction('geomaps.edit')
                                                    ])))
                                                : null,
                                         (CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL) ||
                                         CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_AUDIT_LOG) ||
                                         CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_HOUSEKEEPING))
                                            ? (new CMenuItem(_('General')))
                                                ->setSubMenu(new CMenu(array_filter([
                                                    CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_AUDIT_LOG)
                                                        ? (new CMenuItem(_('Audit settings')))
                                                            ->setAction('audit.settings.edit')
                                                        : null,
                                                    CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)
                                                        ? (new CMenuItem(_('Autoregistration settings')))
                                                        ->setAction('autoreg.edit')
                                                        : null,
                                                    CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)
                                                        ? (new CMenuItem(_('Connectors')))
                                                        ->setAction('connector.list')
                                                        ->setAliases(['connector.edit'])
                                                        : null,
                                                    CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_HOUSEKEEPING)
                                                        ? (new CMenuItem(_('Housekeeping')))
                                                            ->setAction('housekeeping.edit')
                                                        : null,
                                                    CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)
                                                        ? (new CMenuItem(_('Other')))
                                                        ->setAction('miscconfig.edit')
                                                        : null
                                                ])))
                                                : null,
                                        (CWebUser::checkAccess(CRoleHelper::UI_REPORTS_SYSTEM_INFO) ||
                                        CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_QUEUE) ||
                                        CWebUser::checkAccess(CRoleHelper::UI_REPORTS_AUDIT))
                                             ? (new CMenuItem(_('System info')))
                                             ->setSubMenu(new CMenu(array_filter([
                                                 CWebUser::checkAccess(CRoleHelper::UI_REPORTS_SYSTEM_INFO)
                                                       ? (new CMenuItem(_('Zabbix status')))->setAction('report.status')
                                                    : null,
                                                CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_QUEUE)
                                                    ? (new CMenuItem(_('Queue overview')))->setAction('queue.overview')
                                                    : null,
                                                CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_QUEUE)
                                                    ? (new CMenuItem(_('Queue by proxy')))->setAction('queue.overview.proxy')
                                                    : null,
                                                CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_QUEUE)
                                                    ? (new CMenuItem(_('Queue details')))->setAction('queue.details')
                                                    : null,
                                                CWebUser::checkAccess(CRoleHelper::UI_REPORTS_AUDIT)
                                                    ? (new CMenuItem(_('Audit log')))->setAction('auditlog.list')
                                                    : null,
                                                CWebUser::checkAccess(CRoleHelper::UI_REPORTS_ACTION_LOG)
                                                    ? (new CMenuItem(_('Actions log')))->setAction('actionlog.list')
                                                    : null,
                                                CWebUser::checkAccess(CRoleHelper::UI_REPORTS_NOTIFICATIONS)
                                                    ? (new CMenuItem(_('Notifications')))->setUrl(new CUrl('report4.php'), 'report4.php')
                                                    : null
                                                ])))
                                            : null
        ];

        $submenu_administration = array_filter($submenu_administration);
        if ($submenu_administration) {
            $menu->add(
                (new CMenuItem(_('Admin')))
                    ->setId('quad-admin')
                    ->setIcon(ZBX_ICON_ADMINISTRATION)
                    ->setSubMenu(new CMenu($submenu_administration))
            );
        }


        return $menu;
    }
}
