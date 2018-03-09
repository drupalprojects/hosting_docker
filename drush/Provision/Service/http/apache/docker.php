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
   * @var string Container Aegir root is static because it's fixed inside the container.
   */
  static $CONTAINER_AEGIR_ROOT = '/var/aegir';

  function default_restart_cmd() {
    $command = parent::default_restart_cmd();
    $default_restart_cmd = "docker-compose -f {$this->server->config_path}/docker-compose.yml exec -T http {$command}";
    return $default_restart_cmd;
  }

  /**
   * This is passed to the `docker run` command. If you leave it blank, a random port will be assigned by docker, as we will save it.
   * @return string
   */
  function default_port() {
    return "";
  }
  
  /**
   * Lock internal http port at 80.
   * Lock http_restart_cmd to self::default_restart_cmd()
   *
   * @See Provision_Service_http_public::init_server()
   */
  function init_server() {
    $this->server->setProperty('http_port', '80');
    $this->server->setProperty('http_restart_cmd', $this->default_restart_cmd());
    parent::init_server();
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
        if (d($alias_name)->platform->web_server->name == $this->server->name) {
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
    $ports = empty($this->server->http_port)? '80': $this->server->http_port . ':80' ;

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

    // Set server config path as a volume.
    $config_path_host = $this->server->config_path;
    if (isset($_SERVER['HOST_AEGIR_HOME'])) {
      $config_path_host = strtr($config_path_host, array(
        '/var/aegir' => $_SERVER['HOST_AEGIR_HOME']
      ));
    }
    $server_name = ltrim($this->server->name, '@');
    $volumes[] = "{$config_path_host}:{$this::$CONTAINER_AEGIR_ROOT}/config/{$server_name}:z";

    // Map a volume for every platform.
    $aliases = _drush_sitealias_all_list();
    foreach ($aliases as $context) {
      if ($context['context_type'] == 'platform' && $context['web_server'] == $this->server->name) {

        $volume_path_container = empty($context['repo_root'])? $context['root']: $context['repo_root'];
        $volume_path_host = strtr($volume_path_container, array(
            '/var/aegir' => $_SERVER['HOST_AEGIR_HOME']
        ));

        // Use the container path as the key so we don't get duplicate volumes at the same path.
        $volumes[$volume_path_container] = $volume_path_host . ':' . $volume_path_container . ':z';
      }
    }
    
    return array_values($volumes);
  }
  
  /**
   * Load environment variables for this server.
   * @return array
   */
  function getEnvironment() {
    $environment = array();
    $environment['AEGIR_SERVER_NAME'] = strtr($this->server->name, array('@server_' => ''));
    
    if ($this->server->service('http')->docker_service) {
      $environment = array_merge($environment, $this->server->service('http')->environment());
    }
    if ($this->server->service('db')->docker_service) {
      $environment = array_merge($environment, $this->server->service('db')->environment());
    }
    return $environment;
  }
}
