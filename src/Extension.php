<?php

namespace Bolt\Extension\CsvExport;

use Bolt\Collection\Bag;
use Bolt\Events\ControllerEvents;
use Bolt\Extension\ConfigTrait;
use Bolt\Extension\ControllerMountTrait;
use Bolt\Extension\DatabaseSchemaTrait;
use Bolt\Extension\MenuTrait;
use Bolt\Extension\SimpleExtension;
use Bolt\Extension\StorageTrait;
use Bolt\Extension\TwigTrait;
use Bolt\Extension\TranslationTrait;
use Bolt\Menu\MenuEntry;
use Bolt\Translation\Translator as Trans;

/**
 * Csv Export extension for Bolt
 *
 * @author    Ross Riley <riley.Ross@gmail.com>
 *
 * @license   https://opensource.org/licenses/MIT MIT
 */
class Extension extends SimpleExtension
{
    use ConfigTrait;
    use ControllerMountTrait;
    use DatabaseSchemaTrait;
    use MenuTrait;
    use StorageTrait;
    use TwigTrait;
    use TranslationTrait;

    protected function registerMenuEntries()
    {
        $app = $this->getContainer();
        $config = $this->getConfig();
        $roles = isset($config['roles']['admin']) ? $config['roles']['admin'] : ['root'];
        $contentTypes = Bag::from($app['config']->get('contenttypes'));
        $exports = $contentTypes->filter(function($key, $item) use ($config) {
            if (in_array($key, $config['disabled'], true)) {
                return false;
            }
        });

        $parent = (new MenuEntry('export', 'export'))
                ->setLabel(Trans::__('CSV Export'))
                ->setIcon('fa:file')
                ->setPermission(implode('||', $roles))
                ->setGroup(true)
        ;

        foreach ($exports as $key => $export) {
            $parent->add(
                (new MenuEntry('export '. $key, '/export/'.$key))
                    ->setLabel('Export ' . $export['label'])
                    ->setIcon('fa:file')
            );
        }

        return [
            $parent
        ];


    }

}
