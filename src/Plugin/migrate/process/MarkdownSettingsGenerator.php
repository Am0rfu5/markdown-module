<?php

namespace Drupal\markdown\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * @MigrateProcessPlugin(
 *   id = "markdown_settings_generator",
 *   handle_multiples = TRUE
 * )
 */
class MarkdownSettingsGenerator extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($row->getDestinationProperty('id') === 'markdown') {
      $parser_manager = \Drupal::service('plugin.manager.markdown.parser');
      // The markdown module during installation will automatically detect the
      // available parsers and select a default.
      // @see markdown_requirements()
      // @todo Add support for generating valid configuration for other parsers?
      if ($parser_manager->getDefaultParser()->getPluginId() === 'commonmark') {
        // @see markdown_migration_plugins_alter()
        $source_format = $row->getSourceProperty('source_format');
        $enabled_filters = array_column($source_format['filters'], 'name');
        $value = [
          'id' => 'commonmark',
          'enabled' => TRUE,
          'render_strategy' => [
            'type' => 'filter_output',
            'custom_allowed_html' => '',
            'plugins' => [
              'commonmark' => TRUE,
              // Footnotes were supported by default in the D7 version.
              'commonmark-footnotes' => TRUE,
              // Enable Markdown support for specific filters if the filters are
              // enabled.
              'filter_align' => in_array('filter_align', $enabled_filters),
              'filter_caption' => in_array('filter_caption', $enabled_filters),
              'media_embed' => in_array('filter_caption', $enabled_filters),
            ],
          ],
          'settings' => [
            'html_input' => 'allow',
            'max_nesting_level' => 0,
          ],
          'extensions' => [
            // Footnotes were supported by default in the D7 version.
            [
              'id' => 'commonmark-footnotes',
              'enabled' => TRUE,
              'weight' => 0,
              'settings' => [
                'container_add_hr' => TRUE,
              ],
            ],
          ],
          'override' => TRUE,
        ];
      }
    }

    return $value;
  }

}
