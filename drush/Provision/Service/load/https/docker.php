<?php
/**
 * @file
 * Provides the MySQL service driver.
 */
use Symfony\Component\Yaml\Yaml;

/**
 * The MySQL provision service.
 */
class Provision_Service_load_https_docker extends Provision_Service {
  protected $application_name = 'load';

  public $docker_service = TRUE;

  function dockerComposeServices() {
    $default_host_uri = $this->server->name;

    $volumes_path = $this->server->config_path . '/volumes';
    if (isset($_SERVER['HOST_AEGIR_HOME'])) {
      $volumes_path = strtr($volumes_path, [
        '/var/aegir' => $_SERVER['HOST_AEGIR_HOME']
      ]);
    }

    $yml = <<<YML
nginx:
  restart: always
  image: nginx
  container_name: nginx
  ports:
    - "80:80"
    - "443:443"
  volumes:
    - "$volumes_path/proxy/conf.d:/etc/nginx/conf.d"
    - "/etc/nginx/vhost.d"
    - "/usr/share/nginx/html"
    - "$volumes_path/proxy/certs:/etc/nginx/certs:ro"

nginx-gen:
  restart: always
  image: jwilder/docker-gen
  container_name: nginx-gen
  volumes:
    - "/var/run/docker.sock:/tmp/docker.sock"
    - "$volumes_path/proxy/templates:/etc/docker-gen/templates:ro"
  volumes_from:
    - nginx
  entrypoint: /usr/local/bin/docker-gen -notify-sighup nginx -watch -wait 5s:30s /etc/docker-gen/templates/nginx.tmpl /etc/nginx/conf.d/default.conf
  environment:
    - DEFAULT_HOST=$default_host_uri

letsencrypt-nginx-proxy-companion:
  restart: always
  image: jrcs/letsencrypt-nginx-proxy-companion
  container_name: letsencrypt-nginx-proxy-companion
  volumes_from:
    - nginx
  volumes:
    - "/var/run/docker.sock:/var/run/docker.sock:ro"
    - "$volumes_path/proxy/certs:/etc/nginx/certs:rw"
  environment:
    - NGINX_DOCKER_GEN_CONTAINER=nginx-gen
YML;

    $compose_services = Yaml::parse($yml);
    return $compose_services;
  }
}
