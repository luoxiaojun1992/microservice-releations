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
                                                'kind.keyword' => 'SERVER',
                                            ),
                                    ),
                            ),
                    ),
            ),
        'aggs' =>
            array(
                'chains' =>
                    array(
                        'terms' =>
                            array(
                                'field' => 'traceId.keyword',
                                'size' => 1000,
                            ),
                        'aggs' =>
                            array(
                                'calls' =>
                                    array(
                                        'top_hits' =>
                                            array(
                                                'size' => 10,
                                                '_source' =>
                                                    array(
                                                        'includes' => ['localEndpoint.serviceName', 'parentId'],
                                                    ),
                                                'sort' =>
                                                    array(
                                                        'timestamp' =>
                                                            array(
                                                                'order' => 'asc',
                                                            ),
                                                    ),
                                            ),
                                    ),
                            ),
                    ),
            ),
    ],
    'from' => 0,
    'size' => 0,
));

$services = [];
$serviceNameCoordMapping = [];
$relations = [];

$startX = 300;
$startY = 300;

if (isset($arrData['aggregations']['chains']['buckets'])) {
    if (count($arrData['aggregations']['chains']['buckets']) > 0) {
        $chains = $arrData['aggregations']['chains']['buckets'];

        foreach ($chains as $chain) {
            if (isset($chain['calls']['hits']['hits'])) {
                if (count($chain['calls']['hits']['hits']) > 0) {
                    $calls = $chain['calls']['hits']['hits'];

                    foreach ($calls as $callIndex => $call) {
                        if (!array_key_exists($call['_source']['localEndpoint']['serviceName'], $serviceNameCoordMapping)) {
                            $services[] = [
                                'name' => $call['_source']['localEndpoint']['serviceName'],
                                'value' => [$startX, $startY],
                            ];
                            $serviceNameCoordMapping[$call['_source']['localEndpoint']['serviceName']] = [$startX, $startY];
                            $startX += 100;
                            if ($startX == 1000) {
                                $startY += 100;
                            }
                        }
                    }
                }
            }
        }

        foreach ($chains as $chain) {
            if (isset($chain['calls']['hits']['hits'])) {
                if (count($chain['calls']['hits']['hits']) > 0) {
                    $calls = $chain['calls']['hits']['hits'];

                    foreach ($calls as $callIndex => $call) {
                        if (isset($calls[$callIndex + 1])) {
                            if ($calls[$callIndex + 1]['_source']['parentId'] != $call['_source']['parentId']) {
                                $relation = [
                                    'fromName' => $call['_source']['localEndpoint']['serviceName'],
                                    'toName' => $calls[$callIndex + 1]['_source']['localEndpoint']['serviceName'],
                                    'coords' => [
                                        $serviceNameCoordMapping[$call['_source']['localEndpoint']['serviceName']],
                                        $serviceNameCoordMapping[$calls[$callIndex + 1]['_source']['localEndpoint']['serviceName']],
                                    ]
                                ];
                                if (!in_array($relation, $relations)) {
                                    $relations[] = $relation;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

header('Content-type: application/json');

echo json_encode([
    'services' => $services,
    'relations' => $relations,
]);
