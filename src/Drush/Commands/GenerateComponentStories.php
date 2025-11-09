<?php

namespace Drupal\helga_storybook\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 */
final class GenerateComponentStories extends DrushCommands {

  use GenerateComponentStoriesTrait;

  /**
   * Command description here.
   */
  #[CLI\Command(name: 'storybook:generate-component-stories', aliases: ['generate-component-stories'])]
  #[CLI\Argument(name: 'component_id', description: 'Component id to generate JSON stories for.')]
  #[CLI\Option(name: 'force', description: 'Generate JSON files even for stories that have not changed.')]
  public function generateStoriesForComponent(string $component_id, $options = ['force' => FALSE]): void {
    $pluginManager = \Drupal::service('plugin.manager.sdc');
    /** @var \Drupal\Core\Plugin\Component $component */
    $component = $pluginManager->find($component_id) 
      ?: throw new \InvalidArgumentException("Component with id '$component_id' not found.");
    $this->generateStoriesForComponentUtil(
      $component,
      $options,
    );
  }

}
