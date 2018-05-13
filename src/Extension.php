<?php

namespace Bolt\Extension\CsvExport;

use Bolt\Collection\Bag;
use Bolt\Collection\MutableBag;
use Bolt\EventListener\RedirectListener;
use Bolt\Exception\InvalidRepositoryException;
use Bolt\Extension\SimpleExtension;
use Bolt\Menu\MenuEntry;
use Bolt\Storage\Migration\Export;
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

        // We shouldn't be able to get here with an invalid CT but if we do, just use an empty array
        if (!$this->canExport($ct)) {
            return new CsvResponse([]);
        }

        $responseBag = MutableBag::fromRecursive(['error' => [], 'warning' => [], 'success' => []]);
        $migration = new Export($app['storage'], $app['query']);

        try {
            $migrationOutput = $migration->run([$ct], $responseBag, false);
        } catch (InvalidRepositoryException $e) {
            return new CsvResponse([]);
        }

        $recordsToExport = $migrationOutput[1];
        foreach ((array)$recordsToExport as $record) {
            $compiled = [];
            foreach ($record as $fieldname => $field) {
                if (isset($config['mappings'][$ct][$fieldname])) {
                    $outputKey = $config['mappings'][$ct][$fieldname];
                } else {
                    $outputKey = $fieldname;
                }
                $outputVal = $field;
                $compiled[$outputKey] = $outputVal;
            }
            $data[] = $compiled;
        }
        dump($data); exit;

        return new CsvResponse($data);
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
