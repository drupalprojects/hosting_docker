<?php

/**
 *
 */
class Provision_Service_docker extends Provision_Service
{
}

class Provision_Service_docker_compose extends Provision_Service_docker {
  public $service = 'docker';
  
  function init_server()
  {
    parent::init_server();
    
    // Detect if this server is using any docker services, then we load the config file.
    foreach (d()->get_services() as $service_name => $server) {
      if (isset(d()->service($service_name)->docker_service) && d()->service($service_name)->docker_service) {
        $this->server->remote_host = 'localhost';
        $this->server->setProperty('docker_compose_path', d()->config_path . '/docker-compose.yml');
        break;
      }
    }
  }
  
  /**
   * Called before provision-verify for servers.  Invoked by drush_docker_pre_provision_verify();
   */
  function pre_verify_server_cmd()
  {

    // Write docker-compose.yml file.
    $config = new Provision_Config_Docker_Compose(d());
    $config->write();

    // Run docker-compose up -d
    drush_log("Running docker-compose in " . $this->server->config_path, "devshop_log");
    drush_shell_cd_and_exec($this->server->config_path, "docker-compose up -d");
  }
}
