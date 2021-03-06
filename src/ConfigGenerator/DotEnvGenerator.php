<?php

namespace RoyGoldman\DrupalEnvSettings\ConfigGenerator;

use RoyGoldman\DrupalEnvSettings\Command\ConfigureCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Defines dotenv generator.
 */
class DotEnvGenerator extends ConfigGeneratorBase {

  const LINE_TEMPLATE = '%s=%s';

  const TEMPLATE_SOURCE = '# Drupal environmental configuration.';

  /**
   * Creates an config generator instance for a .env file.
   *
   * @param \RoyGoldman\DrupalEnvSettings\Command\ConfigureCommand $command
   *   Generate command.
   * @param string $name
   *   Generator name.
   */
  public function __construct(ConfigureCommand $command, $name) {
    parent::__construct($command, $name);
    $this
      ->addOption('template', InputOption::VALUE_OPTIONAL, 'Output template file.')
      ->addOption('out-file', InputOption::VALUE_OPTIONAL, 'Filename to write.', '.env');
  }

  /**
   * {@inheritdoc}
   */
  public function generate(array $variables) {
    // Load config.
    $template = $this->getOption('template');
    $file = $this->getOption('out-file');

    // Construct configuration.
    $lines = [];
    foreach ($variables as $envvar => $value) {
      $lines[] = sprintf(static::LINE_TEMPLATE, $envvar, $value);
    }
    $template_snippet = implode("\n", $lines);

    // Load template, if provided.
    $source = '';
    if (!empty($template)) {
      $source = file_get_contents($template);
    }
    else {
      $source = static::TEMPLATE_SOURCE;
    }

    // Append generated source.
    $source .= "\n" . $template_snippet . "\n";

    // Write file.
    file_put_contents($file, $source);
  }

}
