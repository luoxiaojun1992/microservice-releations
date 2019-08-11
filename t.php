<?php

$jsonData = file_get_contents(__DIR__ . '/data.json');
$arrData = json_decode($jsonData, true);

$services = [];
$serviceNameCoordMapping = [];
$relations = [];

$startX = 300;
$startY = 100;

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

var_dump(json_encode($services));
var_dump(json_encode($relations));
