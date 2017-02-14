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
    
    // If current context is a site, set host to "db"
    if (d()->type == 'site') {
      return parent::connect();
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
    return parent::connect();
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
  
  /**
   * Override for grant_host(), because we need to run with docker exec.
   *
   * The only thing we change is $command (to include docker exec CONTAINER) and the "remote_host", which is always "db".
   *
   * @param Provision_Context_server $server
   * @return bool
   */
  function grant_host(Provision_Context_server $server) {
    $container_prefix = preg_replace("/[^A-Za-z0-9 ]/", '', $this->server->name);
    $container_name = "{$container_prefix}_http_1";
  
    $command = sprintf('docker exec %s mysql -u intntnllyInvalid -h %s -P %s -e "SELECT VERSION()"',
      $container_name,
      escapeshellarg('db'),
      escapeshellarg($this->server->db_port));
    
    $server->shell_exec($command);
    $output = implode('', drush_shell_exec_output());
    
    if (preg_match("/Access denied for user 'intntnllyInvalid'@'([^']*)'/", $output, $match)) {
      return $match[1];
    }
    elseif (preg_match("/Host '([^']*)' is not allowed to connect to/", $output, $match)) {
      return $match[1];
    }
    elseif (preg_match("/ERROR 2002 \(HY000\): Can't connect to local MySQL server through socket '([^']*)'/", $output, $match)) {
      return drush_set_error('PROVISION_DB_CONNECT_FAIL', dt('Local database server not running, or not accessible via socket (%socket): %msg', array('%socket' => $match[1], '%msg' => join("\n", drush_shell_exec_output()))));
    }
    elseif (preg_match("/ERROR 2003 \(HY000\): Can't connect to MySQL server on/", $output, $match)) {
      return drush_set_error('PROVISION_DB_CONNECT_FAIL', dt('Connection to database server failed: %msg', array('%msg' => join("\n", drush_shell_exec_output()))));
    }
    elseif (preg_match("/ERROR 2005 \(HY000\): Unknown MySQL server host '([^']*)'/", $output, $match)) {
      return drush_set_error('PROVISION_DB_CONNECT_FAIL', dt('Cannot resolve database server hostname (%host): %msg', array('%host' => $match[1], '%msg' => join("\n", drush_shell_exec_output()))));
    }
    else {
      return drush_set_error('PROVISION_DB_CONNECT_FAIL', dt('Dummy connection failed to fail. Either your MySQL permissions are too lax, or the response was not understood. See http://is.gd/Y6i4FO for more information. %msg', array('%msg' => join("\n", drush_shell_exec_output()))));
    }
  }
  
  function generate_site_credentials() {
    $creds = parent::generate_site_credentials();
    $creds['db_host'] = drush_set_option('db_host', 'db', 'site');
    return $creds;
  }
}
