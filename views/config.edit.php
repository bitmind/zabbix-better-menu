<?php

declare(strict_types=1);

/**
 * @var CView $this
 * @var array $data
 */

$html_page = (new CHtmlPage())
    ->setTitle(_('Menu settings'));

$form = (new CForm())
    ->setId('menu-config-form')
    ->setName('menu-config-form')
    ->setAction((new CUrl('zabbix.php'))
        ->setArgument('action', 'module.custom_menu.config.update')
        ->getUrl()
    );

$form_list = (new CFormList())
    ->addRow(_('Show icons on submenus'), 
        (new CCheckBox('show_icons_level2', 1))
            ->setChecked($data['config']['show_icons_level2'] == 1)
    );

// Creiamo il TabView e usiamo setFooter() con makeFormFooter()
$tabs = (new CTabView())
    ->addTab('main_tab', _('Menu options'), $form_list)
    ->setFooter(makeFormFooter(
        new CSubmitButton(_('Update'), 'update')
    ));

$form->addItem($tabs);

// Aggiungiamo il fix JS per la checkbox
$form->addItem(
    (new CScriptTag('
        $("#menu-config-form").on("submit", function() {
            if (!$("#show_icons_level2").is(":checked")) {
                $(this).append(\'<input type="hidden" name="show_icons_level2" value="0">\');
            }
        });
    '))
);

$html_page->addItem($form)->show();