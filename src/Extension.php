<?php

namespace Bolt\Extension\CsvExport;

use Bolt\Collection\Bag;
use Bolt\Extension\SimpleExtension;
use Bolt\Menu\MenuEntry;
use Bolt\Translation\Translator as Trans;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Csv Export extension for Bolt
 *
 * @author    Ross Riley <riley.Ross@gmail.com>
 *
 * @license   https://opensource.org/licenses/MIT MIT
 */
class Extension extends SimpleExtension
{

    protected function registerMenuEntries()
    {
        $roles = isset($config['roles']['admin']) ? $config['roles']['admin'] : ['root'];


        $parent = (new MenuEntry('export', 'export'))
            ->setLabel(Trans::__('CSV Export'))
            ->setIcon('fa:file')
            ->setPermission(implode('||', $roles))
            ->setGroup(true)
        ;

        foreach ($this->getAvailableExports() as $key => $export) {
            $parent->add(
                (new MenuEntry('export '. $key, '/export/'.$key))
                    ->setLabel('Export ' . $export['name'])
                    ->setIcon('fa:file')
            );
        }

        return [
            $parent
        ];


    }

    /**
     * {@inheritdoc}
     */
    protected function registerBackendRoutes(ControllerCollection $collection)
    {
        $collection->get('/export/{contenttype}', [$this, 'doExport']);
    }

    public function doExport(Request $request)
    {
        $ct = $request->get('contenttype');
        if (!$this->canExport($ct)) {
            throw new AccessDeniedHttpException();
        }
    }

    /**
     *
     */
    protected function getAvailableExports()
    {
        $app = $this->getContainer();
        $config = $this->getConfig();
        $contentTypes = Bag::from($app['config']->get('contenttypes'));

        $exports = $contentTypes->filter(function($key, $item) use ($config) {
            if (!is_array($config['disabled'])) {
                return true;
            }
            if (!in_array($key, $config['disabled'])) {
                return true;
            }
        });

        return $exports;
    }

    protected function canExport($ct)
    {
        $exports = $this->getAvailableExports();

        return $exports->has($ct);
    }

}
