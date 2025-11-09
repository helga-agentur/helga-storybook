<?php

declare(strict_types = 1);

namespace Drupal\helga_storybook\Drush\Commands;

use Drupal\Core\Url;
use Drush\Exceptions\CommandFailedException;

/**
 * Trait to generate stories for templates.
 */
trait GenerateStoriesTrait {

  /**
   * Directory where the generated Twig stories JSON files are stored.
   */
  public const JSON_STORIES_DIRECTORY = '../storybook/stories';

  /**
   * Generate stories for a given template.
   */
  private function generateStoriesForTemplateUtil(\SplFileInfo $template_path_finfo, $options = ['force' => FALSE, 'omit-server-url' => FALSE]): void {
    $url = '';
    if (!$options['omit-server-url']) {
      $url = Url::fromUri('internal:/storybook/stories/render', ['absolute' => TRUE])
        ->toString(TRUE)
        ->getGeneratedUrl();
    }
    $destination_path = $this->getDestinationPath($template_path_finfo->getFilename());
    $should_generate = TRUE;
    if (file_exists($destination_path)) {
      $destination_file = new \SplFileInfo($destination_path);
      $should_generate = $destination_file->getMTime() < $template_path_finfo->getMTime();
    }
    $should_generate = $should_generate || $options['force'];
    if (!$should_generate) {
      $this->logger()->success('Skipping JSON file generation for ' . $destination_path);
      return;
    }
    $data = $this->storyRenderer
      ->generateStoriesJsonFile($template_path_finfo->getPathname(), $url);
    try {
      file_put_contents($destination_path, json_encode($data, JSON_THROW_ON_ERROR));
    }
    catch (\JsonException $e) {
      throw new CommandFailedException('JSON encoding failed.', previous: $e);
    }
    $options['verbose'] ?? FALSE
      ? $this->logger()->success("JSON file generated for $destination_path.\n\n" . json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))
      : $this->logger()->success('JSON file generated for ' . $destination_path);
  }

  /**
   * Get the destination path for the generated stories JSON file.
   *
   * @param string $template_path
   *   The template path.
   * @return string
   *   The full path to destination.
   */
  private function getDestinationPath(string $template_path): string {
    return \Drupal::root() . DIRECTORY_SEPARATOR .
      static::JSON_STORIES_DIRECTORY . DIRECTORY_SEPARATOR .
      basename($template_path, 'twig') . 'json';
  }

}
