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

    // Invoke dockerComposeService() on each Service class.
    foreach ($this->context->services_invoke('dockerComposeService') as $service => $compose_service) {
      if ($compose_service) {
        $compose['services'][$service] = $compose_service;
        $compose['services'][$service]['hostname'] = "{$server_name}.{$service}";
      }
    }

    // Invoke dockerComposeServices() on each Service class.
    foreach ($this->context->services_invoke('dockerComposeServices') as $service => $compose_services) {
      if ($compose_services) {
        foreach ($compose_services as $sub_service => $compose_service) {
          $compose['services'][$sub_service] = $compose_service;
          $compose['services'][$sub_service]['hostname'] = "{$server_name}.{$service}";
        }
      }
    }

    // Allow all services to alter the final docker compose array.
    // @TODO: Replace with a services_invoke_all() method, and move the network alter to
    $this->context->services_invoke('dockerComposeAlter', [&$compose]);

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
