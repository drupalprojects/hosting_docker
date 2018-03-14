<?php

require_once('vendor/autoload.php');

/**
 * Register our directory as a place to find provision classes.
 */
function docker_register_autoload() {
  static $loaded = FALSE;
  if (!$loaded) {
    $loaded = TRUE;
    provision_autoload_register_prefix('Provision_', dirname(__FILE__));
  }
}

/**
 * Implements hook_drush_init().
 */
function docker_drush_init() {
  docker_register_autoload();
}

/**
 * Implements hook_provision_services().
 */
function docker_provision_services() {
  docker_register_autoload();
  return array(
    'docker' => 'compose',
    'load' => NULL,
//    'cache' => 'varnish',
  );
}


/**
 * Implements drush_hook_pre_COMMAND()
 * Run pre-verify on the docker compose service.
 */
function drush_docker_pre_provision_install () {
  drush_docker_pre_provision_verify();
}

/**
 * Implements drush_hook_pre_COMMAND()
 * Run pre-verify on the docker compose service.
 */
function drush_docker_pre_provision_verify () {
  if ((d()->type == 'server' || d()->type == 'site') && _server_has_docker_services(d())) {
    $service = new Provision_Service_docker_compose(d());
    $service->pre_verify_server_cmd();
  }
}

/**
 * Implements drush_hook_pre_COMMAND()
 * Run pre-verify on the docker compose service.
 */
function drush_docker_pre_provision_delete () {
  
  if (d()->type == 'server' && _server_has_docker_services(d())) {
    $service = new Provision_Service_docker_compose(d());
    $service->pre_delete_server_cmd();
  }
}

/**
 * Loop through get_services() to see if any of them ar docker services.
 */
function _server_has_docker_services($server) {
  // Detect if this server is using any docker services return TRUE;
  if ($server->type == 'server') {
    foreach ($server->get_services() as $service_name => $service) {
      if (isset($server->service($service_name)->docker_service) && $server->service($service_name)->docker_service) {
        return TRUE;
      }
    }
  }
}

/**
 * Implements hook_provision_config_variables_alter()
 *
 * The vhost template is used inside the container. Always listen on 80.
 */
function docker_provision_config_variables_alter(&$variables, $template, $config){
  
  // If this is the vhost template and the http service is Docker...
  if (is_a($config, 'Provision_Config_Apache_Site') && is_a(d()->platform->service('http'), 'Provision_Service_http_apache_docker')) {
    
    // Force the listen port to be 80.
    $variables['http_port'] = '80';
  }
}

/**
 * Implements hook_provision_context_alter().
 *
 * Replaces server classes with our own class.
 *
 * @param $context
 */
function docker_provision_context_alter(&$context) {
  if (_server_has_docker_services($context)) {
    drush_log(dt('Reloading server context using Docker server: ' . $context->name), 'notice');
    $context = new Provision_Context_Server_docker($context->name);
    $context->method_invoke('init');
    $context->type_invoke('init');
  }
}
