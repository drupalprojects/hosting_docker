<?php
/**
 * @file
 * Provides the MySQL service driver.
 */

/**
 * The MySQL provision service.
 */
class Provision_Service_db_mysql_docker extends Provision_Service_db_mysql {
  protected $application_name = 'docker';
  private $dsn;
  
  public $docker_service = TRUE;
  public $docker_image = 'mariadb';
  
  
  function init_server() {
    parent::init_server();
    $this->dsn = sprintf("%s:host=%s", $this->PDO_type,  'localhost');
    
    if ($this->has_port) {
      $this->dsn = "{$this->dsn};port={$this->server->db_port}";
    }
  }
  
  function environment() {
    return array(
      // MariaDB image does not have a MYSQL_ROOT_USER environment variable.
      // 'MYSQL_ROOT_USER' => 'root',
      'MYSQL_ROOT_PASSWORD' => $this->creds['pass'],
    );
  }
  
  function connect() {
    $context = drush_get_context('command');
    if ($context['command'] == 'provision-save') {
      return;
    }
    
    // User is always root in mysql containers.
    $user = 'root';
    
    // Root password
    $password = $this->creds['pass'];
    
    // Host is always db (this service type) when using docker compose.
    $host = 'db';
    
    // Find the container prefix by removing all non-alphanumeric characters
    $container_prefix = preg_replace("/[^A-Za-z0-9 ]/", '', $this->server->name);
    $cmd = "docker exec {$container_prefix}_http_1 mysqladmin ping -h {$host} -u {$user} --password={$password}";

    // Run mysqladmin ping from the web container.
    // @TODO: Think about a good way to network all DB containers to the hostmaster container so Aegir can use core methods to connect.
    drush_shell_cd_and_exec(d()->config_path, $cmd);
    $output = trim(implode("\n", drush_shell_exec_output()));

    while (strpos($output, 'mysqld is alive') === FALSE) {
      sleep(3);
      drush_log('Waiting for DB container...', 'devshop_log');
      drush_shell_cd_and_exec(d()->config_path, $cmd);
      $output = trim(implode("\n", drush_shell_exec_output()));
      drush_log($output, 'debug');
    }
    drush_log('Database container ready.', 'devshop_log');
  }

  function dockerComposeService() {
    $ports = empty(d()->db_port)? '3306': d()->db_port . ':3306' ;
    $compose = array(
      'image'  => $this->docker_image,
      'restart'  => 'on-failure:10',
      'ports'  => array(
        $ports,
      ),
      'environment' => $this->environment(),
      
      // Recommended way to enable UTF-8 for Drupal.
      // See https://www.drupal.org/node/2754539
      'command' => 'mysqld --innodb-large-prefix --innodb-file-format=barracuda --innodb-file-per-table',
    );
    return $compose;
  }
  
}
