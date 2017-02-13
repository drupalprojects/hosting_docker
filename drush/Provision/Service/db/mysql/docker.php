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
    
    drush_log('CONNECTED!', 'devshop_log');
//    $user = isset($this->creds['user']) ? $this->creds['user'] : '';
//    $pass = isset($this->creds['pass']) ? $this->creds['pass'] : '';
//    try {
//      $this->conn = new PDO($this->dsn, $user, $pass);
//      return $this->conn;
//    }
//    catch (PDOException $e) {
//      return drush_set_error('PROVISION_DB_CONNECT_FAIL', $e->getMessage());
//    }
  }

  function dockerComposeService() {
    $port = d()->db_port;
    $compose = array(
      'image'  => $this->docker_image,
      'restart'  => 'on-failure:10',
      'ports'  => array(
        "{$port}:3306"
      ),
      'environment' => $this->environment(),
      
      // Recommended way to enable UTF-8 for Drupal.
      // See https://www.drupal.org/node/2754539
      'command' => 'mysqld --innodb-large-prefix --innodb-file-format=barracuda --innodb-file-per-table',
    );
    return $compose;
  }
  
}
