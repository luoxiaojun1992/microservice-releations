<?php

require_once __DIR__ . '/vendor/autoload.php';

$zipkinConfig = require_once __DIR__ . '/config/zipkin.php';

$esClient = \Elasticsearch\ClientBuilder::fromConfig($zipkinConfig['es']);

$arrData = $esClient->search(array(
    'index' => 'zipkin:span:processed-*',
    'body' => [
        'query' =>
            array(
                'bool' =>
                    array(
                        'filter' =>
                            array(
                                0 =>
                                    array(
                                        'range' =>
                                            array(
                                                'created_at' =>
                                                    array(
                                                        'gte' => 'now-1h',
                                                    ),
                                            ),
                                    ),
                                1 =>
                                    array(
                                        'term' =>
                                            array(
                                                'kind.keyword' => 'CLIENT',
                                            ),
                                    ),
                            ),
                    ),
            ),
        'sort' => ['timestamp' => ['order' => 'desc']],
        '_source' => ['localEndpoint.serviceName', 'name']
    ],
    'from' => 0,
    'size' => 1000,
));

$services = [];
$serviceNameCoordMapping = [];
$relations = [];

define('INIT_START_X', 250);
$startX = INIT_START_X;
$startY = 300;

function parseServiceFromName($name)
{
    $arr = explode('/', $name);
    foreach ($arr as $key => $value) {
        if ($value === 'api') {
            return $arr[$key + 1];
        }
    }

    return 'unknown';
}

if (isset($arrData['hits']['total'])) {
    if ($arrData['hits']['total'] > 0) {
        $calls = $arrData['hits']['hits'];
        foreach ($calls as $callIndex => $call) {
            $serviceNameList = [$call['_source']['localEndpoint']['serviceName'], parseServiceFromName($call['_source']['name'])];
            foreach ($serviceNameList as $serviceName) {
                if (!array_key_exists($serviceName, $serviceNameCoordMapping)) {
                    $services[] = [
                        'name' => $serviceName,
                        'value' => [$startX, $startY],
                    ];
                    $serviceNameCoordMapping[$serviceName] = [$startX, $startY];
                    $startX += 300;
                    if ($startX >= 1300) {
                        $startY += 100;
                        $startX = INIT_START_X;
                    }
                }
            }
        }

        foreach ($calls as $callIndex => $call) {
            $fromServiceName = $call['_source']['localEndpoint']['serviceName'];
            $toServiceName = parseServiceFromName($call['_source']['name']);
            $relation = [
                'fromName' => $fromServiceName,
                'toName' => $toServiceName,
                'coords' => [
                    $serviceNameCoordMapping[$fromServiceName],
                    $serviceNameCoordMapping[$toServiceName],
                ]
            ];
            if (!in_array($relation, $relations)) {
                $relations[] = $relation;
            }
        }
    }
}

header('Content-type: application/json');

echo json_encode([
    'services' => $services,
    'relations' => $relations,
]);
