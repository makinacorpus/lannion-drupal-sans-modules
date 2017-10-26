<?php
use Drupal\Core\DrupalKernel;
use MakinaCorpus\Lannion\Lannion;
use Symfony\Component\HttpFoundation\Request;

$autoloader = require_once dirname(__DIR__).'/vendor/autoload.php';

$kernel = new DrupalKernel('prod', $autoloader, true, Lannion::getProjectRoot().'/web');

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();

$kernel->terminate($request, $response);
