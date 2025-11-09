<?php

namespace Drupal\helga_storybook\Drush\Commands;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\File\FileSystemInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use TwigStorybook\Service\StoryRenderer;

/**
 * Contains a replacement hook for 'storybook:generate-stories'.
 *
 * @see http://drupal.org/project/storybook
 */
final class ReplaceGenerateStories extends DrushCommands {

  use GenerateStoriesTrait;

  /**
   * Constructs a StorybookCommands object.
   */
  public function __construct(
    string $root,
    private readonly StoryRenderer $storyRenderer,
    private readonly FileSystemInterface $filesystem,
  ) {
    // Ensure the stories directory exists.
    $stories_dir = $root . DIRECTORY_SEPARATOR . static::JSON_STORIES_DIRECTORY;
    $this->filesystem->prepareDirectory($stories_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    parent::__construct();
  }

  /**
   * Given a template path, relative to the Drupal root, generate the stories.
   *
   * @param string $template_path
   *   The template path, relative to the Drupal root.
   * @param mixed[] $options
   *   The command options.
   */
  #[CLI\Hook(type: HookManager::REPLACE_COMMAND_HOOK, target: 'storybook:generate-stories')]
  public function generateStoriesForTemplate(string $template_path, array $options = ['force' => FALSE, 'omit-server-url' => FALSE]): void {
    $template_path_finfo = new \SplFileInfo($template_path);
    $this->generateStoriesForTemplateUtil($template_path_finfo, $options);
  }

}
