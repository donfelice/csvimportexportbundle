<?php

namespace Donfelice\CSVImportExportBundle\EventListener;

use EzSystems\EzPlatformAdminUi\Menu\Event\ConfigureMenuEvent;
use EzSystems\EzPlatformAdminUi\Menu\MainMenuBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Menu class extending Admin UI's KNPMenu based menu system.
 */

class CSVAdminUIMenuListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents() : array
    {
        return [
            ConfigureMenuEvent::MAIN_MENU => 'onMenuConfigure',
        ];
    }
    public function onMenuConfigure( ConfigureMenuEvent $event ) : void
    {
        $menu = $event->getMenu();

        // Add own top level section
        $menu->addChild(
            'csv_menu',
            ['label' => 'CSV Import/Export']
        );

        $menu['csv_menu']->addChild(
            'csv_menu_import',
            [
                // Example of trnslating menu items
                //'label' => 'translation.key',
                //'translation_domain' => 'messages',
                'label' => 'Import',
                'uri' => '/admin/csv/import/0',
            ]
        );

        $menu['csv_menu']->addChild(
            'csv_menu_export',
            [
                'label' => 'Export',
                'uri' => '/admin/csv/export',
            ]
        );


    }

}
