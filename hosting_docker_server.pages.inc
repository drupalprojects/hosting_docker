<?php

/**
 *
 */
function hosting_docker_node_docker_page($node) {

  $output['shell_command'] = array(
    '#type' => 'item',
    '#title' => t('Shell Access'),
    '#markup' => "<pre>docker-compose -f {$node->docker['docker-compose-file']} exec http bash</pre>",
    '#description' => t('Use this command to gain shell access to the web server container.'),
  );
  $output['logs_command'] = array(
    '#type' => 'item',
    '#title' => t('Log Access'),
    '#markup' => "<pre>docker-compose -f {$node->docker['docker-compose-file']} logs -f</pre>",
    '#description' => t('Use this command to gain shell access to the web server container.'),
  );
  $output['docker_compose_path'] = array(
    '#type' => 'item',
    '#title' => t('Docker Compose File'),
    '#markup' => $node->docker['docker-compose-file'],
  );
  $output['docker_compose_file'] = array(
    '#type' => 'markup',
    '#markup' => file_get_contents($node->docker['docker-compose-file']),
    '#prefix' => '<pre>',
    '#suffix' => '</pre>',
  );

  return $output;
}