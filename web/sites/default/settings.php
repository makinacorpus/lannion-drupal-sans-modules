<?php

use Drupal\Component\PhpStorage\FileStorage;
use MakinaCorpus\Lannion\Lannion;

define('APP_ROOT', Lannion::getProjectRoot());

$config_directories = [
    CONFIG_ACTIVE_DIRECTORY => APP_ROOT.'/app/config/active',
    CONFIG_SYNC_DIRECTORY => APP_ROOT.'/app/config/sync',
];

$settings['container_yamls'][] = APP_ROOT.'/app/parameters.yml';
$settings['container_yamls'][] = APP_ROOT.'/app/config/services/core.yml';

$GLOBALS['conf']['container_service_providers']['Lannion'] = Lannion::class;

/**
 * Et pourquoi pas mettre ça dans un répertoire de cache, ailleurs ?
 */
$settings['php_storage']['default'] = [
    'class' => FileStorage::class,
    'directory' => APP_ROOT.'/var/cache',
];

$config['system.site']['page']['front'] = '/contenu-recent';
$config['system.file']['path']['temporary'] = APP_ROOT.'/var/tmp';
$settings['hash_salt'] = 'mxH4-RXvavG__QIL4SDou7nUCQb2LqJSUCY8G5fHgKjMJ-v1DFD1XN_BemnBscKQD-OcSupb-w';
$settings['update_free_access'] = false;

$config['system.performance']['fast_404']['exclude_paths'] = '/\/(?:styles)|(?:system\/files)\//';
$config['system.performance']['fast_404']['paths'] = '/\.(?:txt|png|gif|jpe?g|css|js|ico|swf|flv|cgi|bat|pl|dll|exe|asp)$/i';
$config['system.performance']['fast_404']['html'] = '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL "@path" was not found on this server.</p></body></html>';

$settings['file_scan_ignore_directories'] = [
    'node_modules',
    'bower_components',
];

$settings['entity_update_batch_size'] = 50;
$settings['install_profile'] = 'minimal';

$databases['default']['default'] = [
    'database' => 'd8lannion',
    'username' => 'd8lannion',
    'password' => 'd8lannion',
    'prefix' => '',
    'host' => '192.168.57.102',
    'port' => '5432',
    'namespace' => 'Drupal\\Core\\Database\\Driver\\pgsql',
    'driver' => 'pgsql',
];
