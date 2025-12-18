<?php

namespace Drupal\helga_storybook\Drush\Commands;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\File\FileSystemInterface;
use Drupal\storybook\Drush\RegexRecursiveFilterIterator;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use TwigStorybook\Service\StoryRenderer;

/**
 * Contains a replacement hook for 'storybook:generate-all-stories'.
 *
 * @see http://drupal.org/project/storybook
 */
final class ReplaceGenerateAllStories extends DrushCommands {

  use GenerateStoriesTrait;

  /**
   * Constructs a StorybookCommands object.
   */
  public function __construct(
    private readonly string $root,
    private readonly StoryRenderer $storyRenderer,
    private readonly FileSystemInterface $filesystem,
  ) {
    // Ensure the stories directory exists.
    $stories_dir = $root . DIRECTORY_SEPARATOR . static::JSON_STORIES_DIRECTORY;
    $this->filesystem->prepareDirectory($stories_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    parent::__construct();
  }

  /**
   * Replaces the original 'generate-all-stories' command.
   *
   * This implementation is identical to the original, but it uses
   * a different method to generate stories for each template.
   * That method places the generated JSON files in a different directory,
   * outside of the component's directory.
   */
  #[CLI\Hook(type: HookManager::REPLACE_COMMAND_HOOK, target: 'storybook:generate-all-stories')]
  public function generateAllStories($options = ['force' => FALSE, 'include-server-url' => FALSE]): void {
    // Find all templates in the site and call generateStoriesForTemplate.
    $scan_dirs = ['themes', 'modules', 'profiles'];
    $template_files = array_reduce(
      $scan_dirs,
      fn(array $files, string $scan_dir) => [
        ...$files,
        ...$this->scanDirectory($scan_dir),
      ],
      [],
    );
    array_walk(
      $template_files,
      fn (\SplFileInfo $template_file) => $this->generateStoriesForTemplateUtil(
        $template_file,
        [
          // If 'omit-server-url' is TRUE, it stays TRUE.
          // Otherwise, if 'omit-server-url' is FALSE,
          // and if 'include-server-url' is FALSE (default), then 'omit-server-url' is set to TRUE.
          // Finally, if none of the above, then 'omit-server-url' is FALSE.
          // Which means the following three scenarios are covered:
          // 1. User sets no arguments: server URL is omitted (default).
          // 2. User sets --omit-server-url: server URL is omitted.
          // 3. User sets --include-server-url: server URL is included.
          'omit-server-url' => $options['omit-server-url'] || !$options['include-server-url'],
        ] + $options
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
    $regex = '/^([a-z0-9_-])+\.stories\.twig$/i';
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
    if (!str_ends_with($template_path, '.stories.twig')) {
      throw new UnprocessableEntityHttpException(sprintf(
        'Invalid template path for the stories "%s".',
        $template_path
      ));
    }
    if (!str_starts_with(realpath($template_path), $this->root)) {
      throw new UnprocessableEntityHttpException(sprintf(
        'Invalid template name for the stories "%s". Paths outside the Drupal application are not allowed.',
        $template_path
      ));
    }
  }

}
