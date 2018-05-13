<?php

namespace Bolt\Extension\CsvExport;

use Bolt\Collection\Bag;
use Bolt\EventListener\RedirectListener;
use Bolt\Extension\SimpleExtension;
use Bolt\Menu\MenuEntry;
use Bolt\Translation\Translator as Trans;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
        $config = $this->getConfig();
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

        return [$parent];
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
        $app = $this->getContainer();
        $config = $this->getConfig();
        $ct = $request->get('contenttype');
        if (!$this->canExport($ct)) {
            return;
        }

        $data = [];

        $records = $app['query']->getContent($ct);

        if (!count($records)) {
            return new CsvResponse([]);
        }

        $headers = [];
        foreach ($records as $record) {
            if (!count($headers)) {
                $headers = array_keys($record->toArray());
            }
            $data[] = array_values($record->toArray());
        }

        return new CsvResponse($data);


        return new CsvResponse($records);
    }

    /**
     * @return Bag
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
            if (!in_array($key, $config['disabled'], true)) {
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
