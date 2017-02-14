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
    drush_log("Running docker-compose in " . d()->config_path, "devshop_log");
    $this->runProcess('docker-compose up -d', d()->config_path);
  }
  
  /**
   * Called before provision-verify for servers.  Invoked by drush_docker_pre_provision_verify();
   */
  function pre_delete_server_cmd()
  {
    // Run docker-compose kill; docker-compose rm -fv
    $this->runProcess('docker-compose kill', d()->config_path);
    $this->runProcess('docker-compose rm -fv', d()->config_path);
  }
  
  /**
   * Run a process while logging the output to drush in real time.
   *
   * @param $command
   *   The command to run.
   *
   * @param null $cwd
   *   The directory to run it in.
   *
   * @param string $label
   *   A string to append to the beginning of the command in logs.
   *
   * @param array $env
   *   A list of environment variables to pass to the process. Will be merged with current $_SERVER variables.
   *
   * @param bool $log_output
   *   Determines whether or not to log the output to drush.
   *
   * @param string $error_message
   *   The string to show when a process fails.
   *
   * @return string
   *   The output from the command.
   */
  protected function runProcess($command, $cwd = NULL, $label = 'Process', $env = array(), $log_output = TRUE, $error_message = 'Process Failed') {
    drush_log("[$label] $command", 'devshop_command');
  
    // Merge in env vars, inheriting the CLI's
    if (is_array($env)) {
      $env = array_merge($_SERVER, $env);
    }
    else {
      $env = $_SERVER;
    }
  
    // Make sure colors always come through
    $env['TERM'] = 'xterm';
  
    $process = new \Symfony\Component\Process\Process(escapeshellcmd($command), $cwd, $env);
    $process->setTimeout(NULL);
    if ($log_output) {
      $exit_code = $process->run(function ($type, $buffer) {
        if (\Symfony\Component\Process\Process::ERR === $type) {
          drush_log($buffer, 'devshop_info');
        } else {
          drush_log($buffer, 'devshop_info');
        }
      });
    }
    else {
      $exit_code = $process->run();
    }
  
    // check exit code
    if ($exit_code === 0) {
      drush_log('', 'devshop_ok');
    }
    else {
      drush_log('', 'devshop_error');
      drush_set_error('DEVSHOP_PROCESS_ERROR', dt($error_message));
    }
    return $process->getOutput();
  }
}
