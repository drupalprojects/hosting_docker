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
  
  /**
   * This is passed to the `docker run` command. If you leave it blank, a random port will be assigned by docker, as we will save it.
   * @return string
   */
  function default_port() {
    return "";
  }
  
  function verify_server_cmd() {
    parent::verify_server_cmd();
    drush_log("Running docker-compose in " . $this->server->config_path, "ok");
    drush_shell_cd_and_exec($this->server->config_path, "docker-compose up -d");
  }
  
  /**
   * Prepare the server context, config files, etc.
   */
  function init_server() {
    
    // This loads the Apache Config files, which we are going to use inside the container.
    parent::init_server();
    
    // If a server is set to use Docker, set remote_host to localhost. This prevents RSYNC and SSH commands from running "remotely".a
    $this->server->remote_host = 'localhost';
  
    // Include the Provision_Config_Apache_Docker_Compose class to write docker-compose.yml.
    $this->configs['server'][] = 'Provision_Config_Apache_Docker_Compose';
  
  }
  
  function symlink_service()
  {
    // Don't symlink to the service because the docker images will do this.
    // parent::symlink_service();
  }
  
  /**
   * Restart apache to pick up the new config files.
   */
  function parse_configs() {
    return $this->restart();
  }
}
