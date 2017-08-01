<?php

/**
 * Apache on Docker service class.
 *
 * This service will launch a web server container for each server that implements it.
 */
class Provision_Service_http_apache_docker extends Provision_Service_http_apache {
  // We share the application name with apache.
  protected $application_name = 'docker';
  protected $has_restart_cmd = TRUE;
  
  public $docker_service = TRUE;
  public $docker_image = 'aegir/web';
  
  /**
   * This is passed to the `docker run` command. If you leave it blank, a random port will be assigned by docker, as we will save it.
   * @return string
   */
  function default_port() {
    return "";
  }
  
  function verify_server_cmd() {
    parent::verify_server_cmd();
  }
  
  function environment() {
    
    // Load all sites on this server.
    $sites = $this->getSites();
    return array(
      'AEGIR_DOCKER' => 1,
      'VIRTUAL_HOST' => implode(',', $sites),
    );
  }

  function getSites() {
  
    // Get a list of all that use this platform.
    $aliases_files = _drush_sitealias_find_alias_files();
    $aliases = array();
    foreach ($aliases_files as $filename) {
      if ((@include $filename) === FALSE) {
        drush_log(dt('Cannot open alias file "!alias", ignoring.', array('!alias' => realpath($filename))), LogLevel::BOOTSTRAP);
        continue;
      }
    }
    $platforms = array();
    $platforms_on_this_server = array();
    $sites_on_this_server = array();
    
    foreach ($aliases as $alias_name => $alias) {
      if (isset($alias['context_type']) && $alias['context_type'] == 'site') {
        $sites[$alias_name] = $alias;
        if (d($alias_name)->platform->web_server->name == d()->name) {
          $sites_on_this_server[] = d($alias_name)->uri;
        }
      }
    }
    return $sites_on_this_server;
  }
  
  
  function symlink_service()
  {
    // Don't symlink to the service because the docker images will do this.
    // parent::symlink_service();
  }
  
  function dockerComposeService() {
    $ports = empty(d()->http_port)? '80': d()->http_port . ':80' ;
  
    $compose = array(
        'image'  => $this->docker_image,
        'restart'  => 'on-failure:10',
        'ports'  => array(
          $ports,
        ),
        'volumes' => $this->getVolumes(),
        'environment' => $this->getEnvironment()
      );
    return $compose;
  }
  
  /**
   * Return all volumes for this server.
   *
   * @TODO: Invoke an alter hook of some kinds to allow additional volumes and volume flags.
   *
   * To allow Aegir inside a container to properly launch other containers with mapped volumes, set an environment variable on your aegir/hostmaster container:
   *
   *   HOST_AEGIR_HOME=/home/you/Projects/aegir/aegir-home
   *
   * @return array
   */
  function getVolumes() {
    $volumes = array();
    
    $config_path_host = $config_path_container = d()->config_path;
    $platforms_path_host = $platforms_path_container = d()->http_platforms_path;
    
    if (isset($_SERVER['HOST_AEGIR_HOME'])) {
      $config_path_host = strtr($config_path_host, array(
        '/var/aegir' => $_SERVER['HOST_AEGIR_HOME']
      ));
      $platforms_path_host = strtr($platforms_path_host, array(
        '/var/aegir' => $_SERVER['HOST_AEGIR_HOME']
      ));
    }
    
    $volumes[] = "{$config_path_host}:{$config_path_container}:z";
    $volumes[] = "{$platforms_path_host}:{$platforms_path_container}:z";
    
    return $volumes;
  }
  
  /**
   * Load environment variables for this server.
   * @return array
   */
  function getEnvironment() {
    $environment = array();
    $environment['AEGIR_SERVER_NAME'] = strtr(d()->name, array('@server_' => ''));
    
    if (d()->service('http')->docker_service) {
      $environment = array_merge($environment, d()->service('http')->environment());
    }
    if (d()->service('db')->docker_service) {
      $environment = array_merge($environment, d()->service('db')->environment());
    }
    return $environment;
  }
  
}
