<?php
/**
 * @file
 * Provides the Varnish Cache driver.
 */

/**
 * The Varnish Cache provision service.
 */
class Provision_Service_cache_varnish extends Provision_Service_cache {
  protected $application_name = 'cache';
  
  public $docker_service = TRUE;
  public $docker_image = 'tutum/varnish';
  
  /**
   * Needed otherwise Provision_Service_db_mysql will assign 3306 because port "0" looks empty.
   * @return string
   */
  function default_port() {
    return "";
  }
  
  
  function dockerComposeService() {
    $ports = empty(d()->http_port)? '80': d()->http_port . ':80' ;
  
    $compose = array(
      'image'  => $this->docker_image,
      'restart'  => 'on-failure:10',
      'ports'  => array(
        $ports,
      ),
      'links' => array(
        'http:backend',
      ),
    );
    return $compose;
  }
}
