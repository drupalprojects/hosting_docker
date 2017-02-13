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
  );
}
