<?php

namespace Drupal\helga_storybook\Drush\Commands;

use Drupal\storybook\Drush\RegexRecursiveFilterIterator;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * A Drush commandfile.
 */
final class GenerateAllComponentStories extends DrushCommands {

  use GenerateComponentStoriesTrait;

  /**
   * Command description here.
   */
  #[CLI\Command(name: 'storybook:generate-all-component-stories', aliases: ['generate-all-component-stories'])]
  #[CLI\Option(name: 'force', description: 'Generate JSON files even for stories that have not changed.')]
  public function generateAllStories($options = ['force' => FALSE]): void {
    $pluginManager = \Drupal::service('plugin.manager.sdc');
    array_walk(
      $pluginManager->getAllComponents(),
      fn (mixed $component) => $this->generateStoriesForComponentUtil(
        $component,
        $options,
      ),
    );
  }

  /**
   * Scan a directory recursively for Twig story templates.
   *
   * @param string $directory
   *   The directory to scan.
   *
   * @return \SplFileInfo[]
   *   An array of SplFileInfo objects representing the found templates.
   */
  private function scanDirectory(string $directory): array {

    // Skip if directory doesn't exist.
    if (!is_dir($directory)) {
      return [];
    }

    // Use FilesystemIterator to not iterate over the . and .. directories.
    $flags = \FilesystemIterator::KEY_AS_PATHNAME
      | \FilesystemIterator::CURRENT_AS_FILEINFO
      | \FilesystemIterator::SKIP_DOTS;
    $directory_iterator = new \RecursiveDirectoryIterator($directory, $flags);
    // Detect "my_component.component.yml".
    $regex = '/^([a-z0-9_-])+\.component\.yml$/i';
    $filter = new RegexRecursiveFilterIterator($directory_iterator, $regex);
    $it = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::LEAVES_ONLY, $flags);
    $files = [];
    foreach ($it as $file) {
      $this->validateTemplatePath($file);
      $files[] = $file;
    }
    return $files;
  }

  /**
   * Validate the template path.
   *
   * @param string $template_path
   *   The template path to validate.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   Thrown when the template path is invalid.
   */
  private function validateTemplatePath(string $template_path): void {
    // Validate path.
    if (!str_ends_with($template_path, '.component.yml')) {
      throw new UnprocessableEntityHttpException(sprintf(
        'Invalid path for the component "%s".',
        $template_path
      ));
    }
  }

}
