<?php

namespace RoyGoldman\DrupalEnvSettings;

use Composer\Plugin\Capability\CommandProvider as CapabilityCommandProvider;

use RoyGoldman\DrupalEnvSettings\Command\GenerateCommand;
use RoyGoldman\DrupalEnvSettings\Command\ConfigureCommand;

/**
 * List of all commands provided by this package.
 */
class CommandProvider implements CapabilityCommandProvider {

  /**
   * {@inheritdoc}
   */
  public function getCommands() {
    return [
      new GenerateCommand(),
      new ConfigureCommand(),
    ];
  }

}
