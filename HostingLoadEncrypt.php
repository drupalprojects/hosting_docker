<?php


class hostingService_load_https_docker extends hostingService {
  public $service = 'load';
  public $type = 'https_docker';
  public $name = 'LetsEncrypt & NGINX Proxy';
  public $has_restart_cmd = FALSE;

  static $DOCKER_SERVICE = TRUE;


}