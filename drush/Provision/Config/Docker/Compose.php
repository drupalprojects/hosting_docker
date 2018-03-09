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
    return $this->context->config_path . '/docker-compose.yml';
  }
  
  /**
   * @param $name
   *   String '\@name' for named context.
   * @param $options
   *   Array of string option names to save.
   */
  function __construct($context, $data = array())
  {
    $this->context = is_object($context) ? $context : d($context);
    $data['compose'] = $this->getDockerCompose();
    $data['server_name'] = ltrim($this->context->name, '@server_');
    parent::__construct($context, $data);
  }
  
  function getDockerCompose()
  {
    $server_name = ltrim($this->context->name, '@server_');
    $compose = array(
      'version' => '2',
    );
    
    foreach ($this->context->get_services() as $service => $server) {
      if (method_exists($this->context->service($service), 'dockerComposeService')) {
        $compose['services'][$service] = $this->context->service($service)->dockerComposeService();
        $compose['services'][$service]['hostname'] = "{$server_name}.{$service}";
      }
    }
    
    // For now, link every service. to http
    if (isset($compose['services']['http'])) {
      foreach ($compose['services'] as $service => $data) {
        if ($service != 'http' && $service != 'cache') {
          $compose['services']['http']['links'][] = $service;
        }
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
