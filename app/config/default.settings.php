<?php

$databases['default']['default'] = array();

$config_directories = [
    CONFIG_ACTIVE_DIRECTORY => dirname($app_root).'/app/config/active',
    CONFIG_SYNC_DIRECTORY => dirname($app_root).'/app/config/sync',
];

$settings['container_yamls'][] = dirname($app_root).'/app/services.yml';

$settings['hash_salt'] = 'mxH4-RXvavG__QIL4SDou7nUCQb2LqJSUCY8G5fHgKjMJ-v1DFD1XN_BemnBscKQD-OcSupb-w';
# $settings['deployment_identifier'] = \Drupal::VERSION;
$settings['update_free_access'] = false;
# $settings['http_client_config']['proxy']['http'] = 'http://proxy_user:proxy_pass@example.com:8080';
# $settings['http_client_config']['proxy']['https'] = 'http://proxy_user:proxy_pass@example.com:8080';
# $settings['http_client_config']['proxy']['no'] = ['127.0.0.1', 'localhost'];
# $settings['reverse_proxy'] = TRUE;
# $settings['reverse_proxy_addresses'] = array('a.b.c.d', ...);
# $settings['reverse_proxy_header'] = 'X_CLUSTER_CLIENT_IP';
# $settings['reverse_proxy_proto_header'] = 'X_FORWARDED_PROTO';
# $settings['reverse_proxy_host_header'] = 'X_FORWARDED_HOST';
# $settings['reverse_proxy_port_header'] = 'X_FORWARDED_PORT';
# $settings['reverse_proxy_forwarded_header'] = 'FORWARDED';
# $settings['omit_vary_cookie'] = TRUE;
# $settings['cache_ttl_4xx'] = 3600;
# $settings['form_cache_expiration'] = 21600;
# $settings['class_loader_auto_detect'] = FALSE;

/*
if ($settings['hash_salt']) {
  $prefix = 'drupal.' . hash('sha256', 'drupal.' . $settings['hash_salt']);
  $apc_loader = new \Symfony\Component\ClassLoader\ApcClassLoader($prefix, $class_loader);
  unset($prefix);
  $class_loader->unregister();
  $apc_loader->register();
  $class_loader = $apc_loader;
}
*/

# $settings['allow_authorize_operations'] = FALSE;
# $settings['file_chmod_directory'] = 0775;
# $settings['file_chmod_file'] = 0664;
# $settings['file_public_base_url'] = 'http://downloads.example.com/files';
# $settings['file_public_path'] = 'sites/default/files';
# $settings['file_private_path'] = '';
# $settings['session_write_interval'] = 180;
# $settings['locale_custom_strings_en'][''] = array(
#   'forum'      => 'Discussion board',
#   '@count min' => '@count minutes',
# );
# $settings['maintenance_theme'] = 'bartik';

# $settings['bootstrap_config_storage'] = array('Drupal\Core\Config\BootstrapConfigStorageFactory', 'getFileStorage');

$config['system.file']['path']['temporary'] = dirname($app_root).'/var/tmp';
# $config['system.site']['name'] = 'My Drupal site';
# $config['system.theme']['default'] = 'stark';
# $config['user.settings']['anonymous'] = 'Visitor';

$config['system.performance']['fast_404']['exclude_paths'] = '/\/(?:styles)|(?:system\/files)\//';
$config['system.performance']['fast_404']['paths'] = '/\.(?:txt|png|gif|jpe?g|css|js|ico|swf|flv|cgi|bat|pl|dll|exe|asp)$/i';
$config['system.performance']['fast_404']['html'] = '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL "@path" was not found on this server.</p></body></html>';

# $settings['container_base_class'] = '\Drupal\Core\DependencyInjection\Container';
# $settings['yaml_parser_class'] = NULL;

$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];

$settings['entity_update_batch_size'] = 50;
$settings['install_profile'] = 'minimal';
