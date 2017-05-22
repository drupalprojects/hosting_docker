<?php
/**
 * @file
 * Expose the web_server feature to hostmaster.
 */

/**
 * Implements hook_hosting_feature().
 */
function hosting_docker_server_hosting_feature() {
  $features['docker_server'] = array(
    'title' => t('Docker Services'),
    'description' => t('Use docker containers for aegir services.'),
    'status' => HOSTING_FEATURE_DISABLED,
    'module' => 'hosting_docker_server',
    'group' => 'experimental',
  );
  return $features;
}
