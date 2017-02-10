<?php

use Symfony\Component\Yaml\Yaml;

/**
 * Docker-compose.yml file generator for an Aegir Server.
 */
class Provision_Config_Apache_Docker_Compose extends Provision_Config {
  public $template = '';
  public $description = 'docker compose YML for this server.';
  
  function filename() {
    return $this->data['server']->config_path . '/docker-compose.yml';
  }
  
  /**
   * @param $name
   *   String '\@name' for named context.
   * @param $options
   *   Array of string option names to save.
   */
  function __construct($context, $data = array())
  {
    $data['compose'] = $this->getDockerCompose();
    $data['server_name'] = ltrim($this->data['server']->name, '@server_');
    parent::__construct($context, $data);
  }
  
  function getDockerCompose() {
    $server_name = ltrim(d()->name, '@server_');
    $port = d()->http_port;
    $compose = array();
     
    // Web Server
    if (d()->service('http')->docker_service) {
      $compose['http'] = array(
        'image'  => d()->service('http')->docker_image,
        'hostname'  => $server_name,
        'restart'  => 'on-failure:10',
        'ports'  => array(
          "{$port}:80"
        ),
        'volumes' => $this->getVolumes(),
        'environment' => $this->getEnvironment()
      );
    }
  
  
    // Web Server
    if (d()->service('http')->docker_service) {
      $compose['http'] = array(
        'image'  => 'aegir/web',
        'hostname'  => $server_name,
        'restart'  => 'on-failure:10',
        'ports'  => array(
          "{$port}:80"
        ),
        'volumes' => $this->getVolumes(),
        'environment' => array(
          'AEGIR_SERVER_NAME' => $server_name,
        ),
      );
    }
  
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
    $environment['AEGIR_SERVER_NAME'] = ltrim(d()->name, '@server_');
    
    if (d()->service('http')->docker_service) {
      $environment = array_merge($environment, d()->service('http')->environment());
    }
    if (d()->service('db')->docker_service) {
      $environment = array_merge($environment, d()->service('db')->environment());
    }
    return $environment;
  }
  
  /**
   * This is needed until we patch Provision_Context to be able to write files directly.
   *
   * @return bool
   */
  function write() {
    $filename = $this->filename();
    // Make directory structure if it does not exist.
    if ($filename && !provision_file()->exists(dirname($filename))->status()) {
      provision_file()->mkdir(dirname($filename))
        ->succeed('Created directory @path.')
        ->fail('Could not create directory @path.');
    }
    $status = FALSE;
    if ($filename && is_writeable(dirname($filename))) {
      // Make sure we can write to the file
      if (!is_null($this->mode) && !($this->mode & 0200) && provision_file()->exists($filename)->status()) {
        provision_file()->chmod($filename, $this->mode | 0200)
          ->succeed('Changed permissions of @path to @perm')
          ->fail('Could not change permissions of @path to @perm');
      }
      $status = provision_file()->file_put_contents($filename, $this->getYmlDump())
        ->succeed('Generated docker-compose.yml file: ' . (empty($this->description) ? $filename : $this->description . ' (' . $filename. ')'), 'success')
        ->fail('Could not generate docker-compose.yml file: ' . (empty($this->description) ? $filename : $this->description . ' (' . $filename. ')'))->status();
      // Change the permissions of the file if needed
      if (!is_null($this->mode)) {
        provision_file()->chmod($filename, $this->mode)
          ->succeed('Changed permissions of @path to @perm')
          ->fail('Could not change permissions of @path to @perm');
      }
      if (!is_null($this->group)) {
        provision_file()->chgrp($filename, $this->group)
          ->succeed('Change group ownership of @path to @gid')
          ->fail('Could not change group ownership of @path to @gid');
      }
    }
    return $status;
  }
  
  /**
   * Render template, making variables available from $variables associative
   * array.
   */
  function getYmlDump() {
    return Yaml::dump($this->data['compose'], 5, 2);
  }
}
