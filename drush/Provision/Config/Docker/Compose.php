<?php

use Symfony\Component\Yaml\Yaml;

/**
 * Docker-compose.yml file generator for an Aegir Server.
 */
class Provision_Config_Docker_Compose extends Provision_Config
{
  public $template = '';
  public $description = 'docker compose YML for this server.';
  
  function filename()
  {
    return d()->docker_compose_path;
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
  
  function getDockerCompose()
  {
    $server_name = ltrim(d()->name, '@server_');
    $compose = array();
    
    foreach (d()->get_services() as $service => $server) {
      if (method_exists(d()->service($service), 'dockerComposeService')) {
        $compose[$service] = d()->service($service)->dockerComposeService();
        $compose[$service]['hostname'] = "{$server_name}.{$service}";
      }
    }
    return $compose;
  }
  
  /**
   * This is needed until we patch Provision_Context to be able to write files directly.
   *
   * @return bool
   */
  function write() {
    drush_log('WRTIE>>>>>>>>>>>>', 'warning');
    drush_log('data>>>>>>>>>>>>' . print_r($this->data, 1), 'warning');
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
