/**
 * QUAD - Custom Menu Cleanup
 * Nasconde le vecchie sezioni standard di Zabbix e mantiene solo il nuovo menu riorganizzato.
 * Preserva le voci aggiunte da altri moduli nei sottomenu standard.
 */
(function () {
    'use strict';

    // URL/azioni di default per ogni sottomenu Zabbix
    // Se un link contiene una di queste stringhe, è un elemento standard da nascondere
    const DEFAULT_SUBMENU_URLS = {
        'dashboard': [
            'dashboard.view', 'dashboard.list'
        ],
        'view': [  // Monitoring
            'problem.view', 'host.view', 'latest.view',
            'map.view', 'discovery.view', 'web.view',
            'charts.view', 'chart2.php', 'chart3.php',
            'chart6.php', 'chart7.php', 'httpdetails.php'
        ],
        'services': [
            'service.list', 'sla.list', 'slareport.list'
        ],
        'cm': [  // Inventory
            'hostinventoriesoverview.php', 'hostinventories.php'
        ],
        'reports': [
            'report.status', 'scheduledreport.list', 'report2.php',
            'toptriggers.list', 'toptriggers.php', 'auditlog.list',
            'actionlog.list', 'report4.php'
        ],
        'config': [  // Data collection
            'templategroup.list', 'hostgroup.list', 'template.list',
            'templates.php', 'host.list', 'maintenance.list',
            'correlation.list', 'discovery.list'
        ],
        'alerts': [
            'action.list', 'mediatype.list', 'script.list'
        ],
        'users-menu': [
            'usergroup.list', 'userrole.list', 'user.list',
            'token.list', 'authentication.edit'
        ],
        'admin': [
            'gui.edit', 'autoreg.edit', 'timeouts.edit', 'image.list',
            'iconmap.list', 'regex.list', 'trigdisplay.edit', 'geomaps.edit',
            'module.list', 'connector.list', 'miscconfig.edit',
            'audit.settings.edit', 'housekeeping.edit', 'proxygroup.list',
            'proxy.list', 'macros.edit', 'queue.overview',
            'queue.overview.proxy', 'queue.details'
        ]
    };

    // Verifica se un href corrisponde a un URL standard
    function isDefaultUrl(href, menuId) {
        if (!href || !menuId) return false;

        const defaultUrls = DEFAULT_SUBMENU_URLS[menuId];
        if (!defaultUrls) return false;

        return defaultUrls.some(url => href.includes(url));
    }

    // Processa ricorsivamente i sottomenu
    function processSubmenu(submenu, menuId) {
        if (!submenu) return 0;

        const items = submenu.querySelectorAll(':scope > li');
        let visibleCount = 0;

        items.forEach(item => {
            const link = item.querySelector(':scope > a');
            const nestedSubmenu = item.querySelector(':scope > ul.submenu');

            if (nestedSubmenu) {
                // È un sottomenu annidato (es. "Actions", "General", "Queue")
                const nestedVisible = processSubmenu(nestedSubmenu, menuId);
                if (nestedVisible === 0) {
                    item.style.display = 'none';
                } else {
                    visibleCount += nestedVisible;
                }
            } else if (link) {
                const href = link.getAttribute('href');
                if (isDefaultUrl(href, menuId)) {
                    item.style.display = 'none';
                } else {
                    visibleCount++;
                }
            }
        });

        return visibleCount;
    }

    function hideOldMenuItems() {
        const menuMain = document.querySelector('ul.menu-main');
        if (!menuMain) return;

        const menuItems = menuMain.querySelectorAll(':scope > li');

        menuItems.forEach(function (item) {
            const itemId = item.id;

            // Salta i menu QUAD (iniziano con 'quad-')
            if (itemId && itemId.startsWith('quad-')) {
                return;
            }

            // Se è un menu standard di Zabbix
            if (itemId && DEFAULT_SUBMENU_URLS.hasOwnProperty(itemId)) {
                const submenu = item.querySelector(':scope > ul.submenu');

                if (submenu) {
                    // Processa il sottomenu e conta gli elementi extra
                    const visibleItems = processSubmenu(submenu, itemId);

                    if (visibleItems === 0) {
                        // Nessun elemento extra, nascondi tutto il menu
                        item.style.display = 'none';
                    }
                    // Se ci sono elementi extra, il menu rimane visibile
                } else {
                    // Menu senza sottomenu (es. dashboard)
                    const link = item.querySelector(':scope > a');
                    if (link && isDefaultUrl(link.getAttribute('href'), itemId)) {
                        item.style.display = 'none';
                    }
                }
            }
        });
    }

    // Esegui quando il DOM è pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideOldMenuItems);
    } else {
        hideOldMenuItems();
    }

    // Osserva modifiche al menu in caso di aggiornamenti dinamici
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.addedNodes.length) {
                hideOldMenuItems();
            }
        });
    });

    const menuContainer = document.querySelector('.sidebar-nav');
    if (menuContainer) {
        observer.observe(menuContainer, { childList: true, subtree: true });
    }
})();
