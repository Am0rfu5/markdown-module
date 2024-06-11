<?php

/**
 * @file
 * Markdown post updates.
 */

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Config;
use Drupal\filter\Entity\FilterFormat;
use Drupal\markdown\PluginManager\ParserManager;

/**
 * Normalizes markdown configuration.
 *
 * @param \Drupal\Core\Config\Config $config
 *   The Config object to be normalized.
 * @param array $defaultData
 *   Optional. An array of default data to merge with any active config.
 * @param bool $save
 *   Flag indicating whether to save the config after its been normalized.
 */
function _markdown_normalize_config(Config $config, array $defaultData = [], $save = TRUE) {
  $name = $config->getName();
  $isSettings = $name === 'markdown.settings';
  $isFilter = strpos($name, 'filter.format.') === 0;
  $isParser = strpos($name, 'markdown.parser.') === 0;
  $prefix = $isFilter ? 'filters.markdown.settings.' : '';

  $plugin_map = [
    'erusev/parsedown' => 'parsedown',
    'erusev/parsedown-extra' => 'parsedown-extra',
    'league/commonmark' => 'commonmark',
    'league/commonmark-gfm' => 'commonmark-gfm',
    'michelf/php-markdown' => 'php-markdown',
    'michelf/php-markdown-extra' => 'php-markdown-extra',
    'league/commonmark-ext-autolink' => 'commonmark-autolink',
    'league/commonmark-ext-disallowed-raw-html' => 'commonmark-disallowed-raw-html',
    'league/commonmark-ext-external-links' => 'commonmark-external-links',
    'league/commonmark-ext-footnotes' => 'commonmark-footnotes',
    'league/commonmark-ext-heading-permalink' => 'commonmark-heading-permalink',
    'league/commonmark-ext-smart-punctuation' => 'commonmark-smart-punctuation',
    'league/commonmark-ext-strikethrough' => 'commonmark-strikethrough',
    'league/commonmark-ext-table' => 'commonmark-table',
    'league/commonmark-ext-toc' => 'commonmark-table-of-contents',
    'league/commonmark-ext-task-list' => 'commonmark-task-list',
    'pecl/cmark' => 'commonmark-pecl',
    'rezozero/commonmark-ext-footnotes' => 'commonmark-footnotes',
    'thephpleague/commonmark' => 'commonmark',
    'webuni/commonmark-attributes-extension' => 'commonmark-attributes',
  ];

  // Fix parser identifier.
  $parserId = $config->get($prefix . 'parser.id') ?: $config->get($prefix . 'parser') ?: $config->get($prefix . 'id') ?: $config->get($prefix . 'default_parser');

  // Handle missing parser.
  if ($parserId === '_missing_parser') {
    $originalParserId = $config->get($prefix . 'parser.original_plugin_id') ?: $config->get($prefix . 'original_plugin_id');
    if ($originalParserId) {
      $parserId = $originalParserId;
    }
  }

  // Extract the parser identifier, if possible.
  if (empty($parserId)) {
    // From the configuration name.
    if ($isParser) {
      $parserId = current(array_reverse(explode('.', $name)));
    }
    // Otherwise, this config cannot be upgraded it must be recreated manually.
    else {
      \Drupal::logger('markdown')->notice('Unable to update config for "%name", it must be updated manually or recreated from the UI.', [
        '%name' => $name,
      ]);
      return;
    }
  }
  if (isset($plugin_map[$parserId])) {
    $parserId = $plugin_map[$parserId];
    if ($isSettings) {
      // Save the original markdown.settings config data.
      $settingData = $config->get('parser');
      if (!is_array($settingData)) {
        $settingData = $config->get() ?: [];
      }

      // Set the default parser and save the new markdown.settings.
      $config->setData(['default_parser' => $parserId]);
      if ($save) {
        $config->save();
      }

      /** @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory */
      $configFactory = \Drupal::service('config.factory');

      // Reload config for the specific parser.
      $config = $configFactory->getEditable("markdown.parser.$parserId");

      // Save any existing parser config (in case a user saved before update).
      $parserConfig = $config->get();

      // Set the original markdown.settings configuration.
      $config->setData($settingData);

      // Merge back any existing parser config.
      $config->merge($parserConfig);

      // Let the normalization continue as parser config.
      $isSettings = FALSE;
      $isParser = TRUE;
    }
  }

  if ($isFilter || $isParser) {
    $config->set($prefix . 'id', $parserId);
  }

  $override = FALSE;
  if ($isFilter) {
    $override = !!$config->get($prefix . 'override');
  }

  // Fix parser render strategy custom allowed HTML.
  $previousDefaultValue = '<a href hreflang> <abbr> <blockquote cite> <br> <cite> <code> <div> <em> <h2> <h3> <h4> <h5> <h6> <hr> <img alt height src width> <li> <ol start type=\'1 A I\'> <p> <pre> <span> <strong> <ul type>';
  $renderStrategyAllowedHtml = $config->get($prefix . 'parser.render_strategy.allowed_html') ?: $config->get($prefix . 'render_strategy.allowed_html');
  if (isset($renderStrategyAllowedHtml)) {
    // Remove default value.
    if (trim($renderStrategyAllowedHtml) === $previousDefaultValue) {
      $renderStrategyAllowedHtml = '';
    }
    // Move to new property name.
    $config->clear($prefix . 'render_strategy.allowed_html');
    $config->set($prefix . 'render_strategy.custom_allowed_html', $renderStrategyAllowedHtml);
    if ($isFilter) {
      $override = TRUE;
    }
  }

  // Fix parser render strategy plugins.
  $renderStrategyPlugins = $config->get($prefix . 'parser.render_strategy.plugins') ?: $config->get($prefix . 'render_strategy.plugins');
  if (isset($renderStrategyPlugins)) {
    foreach ($renderStrategyPlugins as $key => $value) {
      if (is_numeric($key)) {
        unset($renderStrategyPlugins[$key]);
        $key = $value;
        $value = TRUE;
        $renderStrategyPlugins[$key] = $value;
      }
      // The global "markdown" plugin was replaced by a trait on each parser.
      if ($key === 'markdown') {
        unset($renderStrategyPlugins[$key]);
        $key = $parserId;
        $renderStrategyPlugins[$key] = $value;
      }
      if (isset($plugin_map[$key])) {
        $renderStrategyPlugins[$plugin_map[$key]] = $value;
        unset($renderStrategyPlugins[$key]);
      }
      elseif (isset($plugin_map[$value])) {
        $renderStrategyPlugins[$key] = $plugin_map[$value];
      }
    }
    $config->set($prefix . 'render_strategy.plugins', $renderStrategyPlugins);
    if ($isFilter) {
      $override = TRUE;
    }
  }

  // Fix extension identifiers.
  $extensions = $config->get($prefix . 'parser.extensions') ?: $config->get($prefix . 'extensions');
  if (isset($extensions)) {
    foreach ($extensions as $key => &$extension) {
      if (isset($plugin_map[$extension['id']])) {
        $extension['id'] = $plugin_map[$extension['id']];
      }
    }
    $config->set($prefix . 'extensions', $extensions);
    if ($isFilter) {
      $override = TRUE;
    }
  }

  if ($isSettings) {
    $config->setData(['default_parser' => $parserId]);
  }
  else {
    $configuration = $config->get($prefix . 'parser');
    $configuration = array_replace_recursive(is_array($configuration) ? $configuration : [], $config->get(substr($prefix, 0, -1)) ?: [], $defaultData);
    $parser = ParserManager::create()->createInstance($parserId, $configuration);

    $configuration = $parser->getSortedConfiguration();

    if ($isFilter) {
      // Merge parser dependencies into filter's.
      $dependencies = NestedArray::mergeDeep($config->get('dependencies') ?: [], isset($configuration['dependencies']) ? $configuration['dependencies'] : []);
      unset($configuration['dependencies']);
      $config->set('dependencies', array_map('array_unique', $dependencies));
      $config->set(substr($prefix, 0, -1), $configuration);
      $config->set($prefix . 'override', $override);
    }
    elseif ($isParser) {
      $config->setData($configuration);
    }
  }

  if ($save) {
    $config->save();
  }
}

/**
 * Updates all markdown config.
 *
 * @param bool $save
 *   Flag indicating whether to save the config after its been updated.
 * @param array $defaultData
 *   Optional. An array of default data to merge with any active config.
 * @param callable $normalizer
 *   Optional. A specific normalizer callback that will be invoked on each
 *   config. If not specified, it will default to _markdown_normalize_config().
 *
 * @return \Drupal\Core\Config\Config[]
 *   An array of config objects, keyed by config name.
 */
function _markdown_update_config($save = TRUE, array $defaultData = NULL, callable $normalizer = NULL) {
  if (!isset($normalizer)) {
    $normalizer = '_markdown_normalize_config';
  }

  if (!isset($defaultData)) {
    $defaultData = \Drupal::config('markdown.settings')->get('parser') ?: [];
  }

  /** @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory */
  $configFactory = \Drupal::service('config.factory');

  $configNames = ['markdown.settings'];
  $parserManager = ParserManager::create();
  $available = $parserManager->getDefinitions(FALSE);
  $installed = array_keys($parserManager->installedDefinitions());
  foreach (array_keys($available) as $parserId) {
    if (in_array($parserId, $installed)) {
      $configNames[] = "markdown.parser.$parserId";
    }
    else {
      $configFactory->getEditable("markdown.parser.$parserId")->delete();
    };
  }

  /** @var \Drupal\filter\Entity\FilterFormat $format */
  foreach (FilterFormat::loadMultiple() as $format) {
    $configNames[] = $format->getConfigDependencyName();
  }

  $configs = [];
  foreach ($configNames as $name) {
    $config = $configFactory->getEditable($name);

    // Skip filters that don't already have markdown settings.
    if (strpos($name, 'filter.format.') === 0 && ($filters = $config->get('filters') ?: []) && !isset($filters['markdown']['settings'])) {
      continue;
    }

    $normalizer($config, $defaultData, $save);
    $configs[$name] = $config;
  }

  return $configs;
}

/**
 * Update configuration (run config export after).
 */
function markdown_post_update_8950() {
  _markdown_update_config();
}
