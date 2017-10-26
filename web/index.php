<?php
use Drupal\Core\DrupalKernel;
use MakinaCorpus\Lannion\Lannion;
use Symfony\Component\HttpFoundation\Request;

define('APP_ROOT', Lannion::getProjectRoot());

$autoloader = require_once dirname(__DIR__).'/vendor/autoload.php';

$kernel = new DrupalKernel('prod', $autoloader, true, APP_ROOT);

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();

$kernel->terminate($request, $response);
