<?php

declare(strict_types = 1);

namespace Drupal\helga_storybook\Drush\Commands;

use Drupal\Core\Plugin\Component;
use Drush\Exceptions\CommandFailedException;

/**
 * Trait to generate yaml stories for component.yml.
 */
trait GenerateComponentStoriesTrait {

  /**
   * Generate stories for a given component.yml.
   */
  private function generateStoriesForComponentUtil(Component $component, $options = ['force' => FALSE]): void {
    $componentMetadata = $component->metadata;
    $component_path = $componentMetadata->path;
    $destination_path = $component_path . DIRECTORY_SEPARATOR . $componentMetadata->machineName . '.stories.twig';
    $should_generate = TRUE;
    if (file_exists($destination_path)) {
      $should_generate = FALSE;
    }
    $should_generate = $should_generate || $options['force'];
    if (!$should_generate) {
      $this->logger()->success('Skipping component stories yaml file generation for ' . $destination_path);
      return;
    }
    $data = $this->generateStoryFromComponentFile($component->getPluginId(), $componentMetadata);
    try {
      file_put_contents($destination_path, $data);
    }
    catch (\JsonException $e) {
      throw new CommandFailedException('JSON encoding failed.', previous: $e);
    }
    $options['verbose'] ?? FALSE
      ? $this->logger()->success("JSON file generated for $destination_path.\n\n" . json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))
      : $this->logger()->success('JSON file generated for ' . $destination_path);
  }

  /**
   * Generate story YAML from a component file.
   *
   * @param string $sdc_id
   *   The component ID.
   * @param mixed $sdc_metadata
   *   The component metadata.
   *
   * @return array
   *   The generated story yaml data.
   */
  private function generateStoryFromComponentFile(string $sdc_id, mixed $sdc_metadata): ?string {
    $vars = [
      'component_id' => $sdc_id,
      'component_title_namespaced' => 'Components/SDC/' . $sdc_metadata->name,
      'component_machine_name' => str_replace('-', '_', $sdc_metadata->machineName),
      'component_args' => [],
      'component_arg_types' => [],
      'component_args_with_defaults' => [],
    ];

    // Merge props and slots as this is a SDC concept: everything is an argType
    // for Storybook. Identify slots for later use.
    $props = $sdc_metadata->schema['properties'];
    $slots = array_map(function (array $slot) {
      return array_merge($slot, ['slot' => TRUE]);
    }, $sdc_metadata->slots);
    $args = array_merge($props, $slots);
    if (!$args) {
      return NULL;
    }
    $args_types = [];
    $required = $sdc_metadata->schema['required'] ?? [];
    foreach ($args as $arg_name => $arg_data) {
      $is_slot = !empty($arg_data['slot']) && $arg_data['slot'];
      $arg_definition = [
        'name' => $arg_name,
      ];
      if (!empty($arg_data['title'])) {
        $arg_definition['title'] = $arg_data['title'];
      }
      $type = is_array($arg_data['type']) ? $arg_data['type'][0] : $arg_data['type'];
      $arg_definition['type'] = $type;
      if ($required && in_array($arg_name, $required)) {
        $arg_definition['required'] = $required;
      };
      if (!empty($arg_data['description'])) {
        $arg_definition['description'] = $arg_data['description'];
      };
      $arg_definition['category'] = $is_slot ? 'Slots' : 'Props';
      if (!empty($arg_data['enum'])) {
        $arg_definition['options'] = $arg_data['enum'];
      };

      if (!empty($arg_data['default'])) {
        $arg_definition['default'] = $arg_data['default'];
      };
      if ($options = $arg_data['enum'] ?? NULL) {
        $arg_definition['arg_type'] = "{control: 'select', options: ['" . implode("','", $options) . "']}";
      }
      else if ($arg_definition['type'] === 'string') {
        $arg_definition['arg_type'] = '{control: "text"}';
      }
      else if ($arg_definition['type'] === 'number') {
        $arg_definition['arg_type'] = '{control: "number"}';
      }
      else {
        $arg_definition['arg_type'] = '{control: "object"}';
      }
      $vars['story_args'][$arg_name] = $arg_definition;
    }
    $indentationA = '        ';
    $indentationB = '            ';
    $vars['component_args'] = implode(',' . PHP_EOL . $indentationA, array_keys($vars['story_args']));
    $vars['component_args_with_defaults'] = implode(',' . PHP_EOL . $indentationB, array_map(
      function ($value) {
        if (($value['type'] == 'array') || ($value['type'] == 'object')) {
          return $value['name'] . ': ' . ($value['default'] ?? '[]');
        }
        return $value['name'] . ': ' . '"' . (
          $value['default'] ?? 'placeholder') . '"';
      },
      ['title' => ['name' => 'title', 'type' => 'string', 'default' => $sdc_metadata->name]] +
      $vars['story_args'])
    );
    $vars['component_arg_types'] = implode(',' . PHP_EOL . $indentationB, array_map(
      function ($value) {
        return $value['name'] . ': ' . $value['arg_type'];
      },
      $vars['story_args'])
    );
    return $this->getDefaultComponentStoryTwig($vars);
  }

  /**
   * Render a Twig template with given variables.
   *
   * @param string $template_file
   *   The template file path.
   * @param array $variables
   *   The variables to pass to the template.
   *
   * @return string|null
   *   The rendered markup or NULL on failure.
   *
   * @throws \Twig\Error\LoaderError
   * @throws \Twig\Error\RuntimeError
   * @throws \Twig\Error\SyntaxError
   */
  private function getDefaultComponentStoryTwig(array $variables = []): ?string {
    extract($variables, EXTR_SKIP);
    return <<<TEMPLATE
{% stories $component_machine_name with { title: "$component_title_namespaced" } %}

    {% story default with {
        name: 'Default SDC',
        args: {
            $component_args_with_defaults
        },
        argTypes: {
            $component_arg_types
        },
        tags: ['project']
    } %}

    {% embed "$component_id" with {
        $component_args
    } %}
    {% endembed %}

  {% endstory %}

{% endstories %}
TEMPLATE;
  }

}
