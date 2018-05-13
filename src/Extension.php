<?php

namespace Bolt\Extension\CsvExport;

use Bolt\Collection\Bag;
use Bolt\Collection\MutableBag;
use Bolt\EventListener\RedirectListener;
use Bolt\Exception\InvalidRepositoryException;
use Bolt\Extension\SimpleExtension;
use Bolt\Menu\MenuEntry;
use Bolt\Storage\Migration\Export;
use Bolt\Storage\Query\QueryResultset;
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

        /** @var QueryResultset $allRecords */
        $allRecords = $app['query']->getContent($ct);

        $outputData = [];
        foreach ($allRecords as $record) {
            $compiled = [];
            $record = $this->processRecord($ct, $record);
            foreach ($record as $fieldName => $field) {
                $outputKey = isset($config['mappings'][$ct][$fieldName]) ? $config['mappings'][$ct][$fieldName]: $fieldName;

                if ($outputKey === false) {
                    continue;
                }

                $outputVal = $this->serializeField($field);
                $compiled[$outputKey] = $outputVal;
            }
            $outputData[] = $compiled;
        }
        if (count($outputData)) {
            $headers = array_keys($outputData[0]);
        }

        $csvData = [];
        $csvData[] = $headers;
        foreach ($outputData as $csvRow){
            $csvData[] = array_values($csvRow);
        }

        return new CsvResponse($csvData);
    }

    /**
     * Method that can be called recursively to handle flattening field values
     * @param $field
     * @return string
     */
    public function serializeField($field)
    {
        $output = '';
        if (is_array($field)) {
            foreach ($field as $item) {
                $output .= $this->serializeField($item) . ',';
            }
        } else {
            $output .= $field . ',';
        }

        return rtrim($output, ',');
    }

    protected function processRecord($contentType, $record)
    {
        $app = $this->getContainer();
        $repo = $app['storage']->getRepository($contentType);
        $metadata = $repo->getClassMetadata();
        $values = [];

        foreach ($metadata->getFieldMappings() as $field) {
            $fieldName = $field['fieldname'];
            $val = $record->$fieldName;
            if (in_array($field['type'], ['date', 'datetime'])) {
                $val = (string) $record->$fieldName;
            }
            if (is_callable([$val, 'serialize'])) {
                /** @var Entity $val */
                $val = $val->serialize();
            }
            $values[$fieldName] = $val;
        }

        return $values;
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
