<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\markdown\Annotation\InstallableLibrary;

/**
 * Interface for installable plugins.
 *
 * @method \Drupal\markdown\Annotation\InstallablePlugin getPluginDefinition()
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
interface InstallablePluginInterface extends AnnotatedPluginInterface, DependentPluginInterface {

  /**
   * Builds a display for a library.
   *
   * @param \Drupal\markdown\Annotation\InstallableLibrary $library
   *   The library to build.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   */
  public function buildLibrary(InstallableLibrary $library = NULL);

  /**
   * Builds a display status based on the current state of the plugin.
   *
   * @param bool $all
   *   Flag indicating whether to build status for all potential libraries.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   */
  public function buildStatus($all = FALSE);

  /**
   * Retrieves the config instance for this plugin.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   An immutable config instance for this plugin's configuration.
   */
  public function config();

  /**
   * Retrieves the deprecation message, if any.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|void
   *   The deprecated message, if set.
   */
  public function getDeprecated();

  /**
   * Retrieves the experimental message.
   *
   * @return bool|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   TRUE if plugin is experimental or a TranslatableMarkup object if plugin
   *   is experimental, but has an additional message; FALSE otherwise.
   */
  public function getExperimental();

  /**
   * Retrieves the composer package name of the installable library, if any.
   *
   * @return string|void
   *   The installed identifier, if any.
   */
  public function getInstalledId();

  /**
   * Retrieves the installed library used by the plugin.
   *
   * @return \Drupal\markdown\Annotation\InstallableLibrary|void
   *   The installed library, if any.
   */
  public function getInstalledLibrary();

  /**
   * Displays the human-readable label of the plugin.
   *
   * @param bool $version
   *   Flag indicating whether to show the version with the label.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label.
   */
  public function getLabel($version = TRUE);

  /**
   * Retrieves the plugin as a link using its label and URL.
   *
   * @param string|\Drupal\Component\Render\MarkupInterface $label
   *   Optional. A specific label to use for the link. If not specified, it
   *   will default to the label or plugin identifier if present.
   * @param array $options
   *   An array of options to pass to the Url object constructor.
   * @param bool $fallback
   *   Flag indicating whether to fallback to the original label or plugin
   *   identifier if no link could be generated.
   *
   * @return \Drupal\Core\GeneratedLink|mixed|void
   *   The link if one was generated or the label if $fallback was provided.
   */
  public function getLink($label = NULL, array $options = [], $fallback = TRUE);

  /**
   * Instantiates a new instance of the object defined by the installed library.
   *
   * @param mixed $args
   *   An array of arguments.
   * @param mixed $_
   *   Additional arguments.
   *
   * @return mixed
   *   A newly instantiated class.
   *
   * @todo Refactor to use variadic parameters.
   */
  public function getObject($args = NULL, $_ = NULL);

  /**
   * Retrieves the class name of the object defined by the installed library.
   *
   * @return string
   *   The object class name.
   */
  public function getObjectClass();

  /**
   * Retrieves the preferred library of the plugin.
   *
   * @return \Drupal\markdown\Annotation\InstallableLibrary|void
   *   The preferred library, if any.
   */
  public function getPreferredLibrary();

  /**
   * Retrieves the configuration for the plugin, but sorted.
   *
   * @return array
   *   The sorted configuration array.
   */
  public function getSortedConfiguration();

  /**
   * Retrieves the URL of the plugin, if set.
   *
   * @param array $options
   *   Optional. An array of \Drupal\Core\Url options.
   *
   * @return \Drupal\Core\Url|void
   *   A Url object of the plugin's URL or NULL if no URL was provided.
   *
   * @see \Drupal\Core\Url::fromUri
   * @see \Drupal\Core\Url::fromUserInput
   */
  public function getUrl(array $options = []);

  /**
   * The current version of the plugin.
   *
   * @return string|null
   *   The plugin version.
   */
  public function getVersion();

  /**
   * Indicates whether plugin has multiple installs to check.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function hasMultipleLibraries();

  /**
   * Indicates whether the plugin is installed.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isInstalled();

  /**
   * Indicates whether the plugin is using the preferred library.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isPreferred();

  /**
   * Indicates whether the preferred library is installed.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isPreferredLibraryInstalled();

  /**
   * Indicates whether the plugin should be shown in the UI.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function showInUi();

}
