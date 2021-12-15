<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark;

use Composer\Semver\Semver;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\markdown\Form\SubformState;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\BaseExtensibleParser;
use Drupal\markdown\Plugin\Markdown\SettingsInterface;
use Drupal\markdown\Traits\ParserAllowedHtmlTrait;
use Drupal\markdown\Util\KeyValuePipeConverter;

/**
 * Support for CommonMark by The League of Extraordinary Packages.
 *
 * @MarkdownAllowedHtml(
 *   id = "commonmark",
 * )
 * @MarkdownParser(
 *   id = "commonmark",
 *   label = @Translation("CommonMark"),
 *   description = @Translation("A robust, highly-extensible Markdown parser for PHP based on the CommonMark specification."),
 *   extensionInterfaces = {
 *     "\Drupal\markdown\Plugin\Markdown\CommonMark\ExtensionInterface",
 *   },
 *   libraries = {
 *     @ComposerPackage(
 *       id = "league/commonmark",
 *       object = "\League\CommonMark\CommonMarkConverter",
 *       url = "https://commonmark.thephpleague.com",
 *       requirements = {
 *         @InstallableRequirement(
 *           constraints = {"Version" = ">=0.4.0 <=1.0.0 || ^1.0 || ^2.0"}
 *         ),
 *       },
 *     ),
 *     @ComposerPackage(
 *       id = "colinodell/commonmark-php",
 *       deprecated = @Translation("Support for this library was deprecated in markdown:8.x-2.0 and will be removed from markdown:3.0.0."),
 *       object = "\ColinODell\CommonMark\CommonMarkConverter",
 *       ui = false,
 *       url = "https://commonmark.thephpleague.com",
 *       requirements = {
 *         @InstallableRequirement(
 *           constraints = {"Version" = "<0.4.0"},
 *         ),
 *       },
 *     ),
 *   },
 * )
 */
class CommonMark extends BaseExtensibleParser implements AllowedHtmlInterface {

  use ParserAllowedHtmlTrait;

  /**
   * The converter class.
   *
   * @var string
   */
  protected static $converterClass;

  /**
   * The environment class.
   *
   * @var string
   */
  protected static $environmentClass;

  /**
   * The installed version.
   *
   * @var string
   */
  protected static $version;

  /**
   * A CommonMark converter instance.
   *
   * @var \League\CommonMark\CommonMarkConverter|\ColinODell\CommonMark\CommonMarkConverter
   */
  protected $converter;

  /**
   * A CommonMark environment instance.
   *
   * @var \League\CommonMark\Environment\ConfigurableEnvironmentInterface|\League\CommonMark\ConfigurableEnvironmentInterface|\League\CommonMark\Environment
   */
  protected $environment;

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    unset($this->converter);
    unset($this->environment);
    return parent::__sleep();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings($pluginDefinition) {
    /** @var \Drupal\markdown\Annotation\InstallablePlugin $pluginDefinition */

    // CommonMark didn't have configuration until 0.6.0.
    if (!$pluginDefinition->version || Semver::satisfies($pluginDefinition->version, '<0.6.0')) {
      return [];
    }

    return [
      'allow_unsafe_links' => TRUE,
      'enable_em' => TRUE,
      'enable_strong' => TRUE,
      'html_input' => 'escape',
      'max_nesting_level' => 0,
      'renderer' => [
        'block_separator' => "\n",
        'inner_separator' => "\n",
        'soft_break' => "\n",
      ],
      'use_asterisk' => TRUE,
      'use_underscore' => TRUE,
      'unordered_list_markers' => ['-', '*', '+'],
    ] + parent::defaultSettings($pluginDefinition);
  }

  /**
   * Retrieves the converter class to be used.
   *
   * @return string|\League\CommonMark\CommonMarkConverter|\ColinODell\CommonMark\CommonMarkConverter
   *   The converter class.
   */
  public static function converterClass() {
    if (!isset(static::$converterClass)) {
      $classes = [
        // >=0.4.0.
        '\\League\\CommonMark\\CommonMarkConverter',
        // 0.1.0 - 0.3.0.
        '\\ColinODell\\CommonMark\\CommonMarkConverter',
      ];
      foreach ($classes as $class) {
        if (class_exists($class)) {
          static::$converterClass = $class;
        }
      }
    }
    return static::$converterClass;
  }

  /**
   * Retrieves the environment class to be used.
   *
   * @return string|\League\CommonMark\Environment\Environment|\League\CommonMark\Environment
   *   The environment class.
   */
  public static function environmentClass() {
    if (!isset(static::$environmentClass)) {
      $classes = [
        // 2.x.
        '\\League\\CommonMark\\Environment\\Environment',

        // 0.x, 1.x.
        '\\League\\CommonMark\\Environment',
      ];
      foreach ($classes as $class) {
        if (class_exists($class)) {
          static::$environmentClass = $class;
        }
      }
    }
    return static::$environmentClass;
  }

  /**
   * {@inheritdoc}
   */
  protected function convertToHtml($markdown, LanguageInterface $language = NULL) {
    return $this->converter()->convertToHtml($markdown);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */
    $element = parent::buildConfigurationForm($element, $form_state);

    $element += $this->createSettingElement('allow_unsafe_links', [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow Unsafe Links'),
      '#description' => $this->t('Allows potentially risky links and image URLs to remain in the document.'),
    ], $form_state);
    $this->renderStrategyDisabledSettingState($form_state, $element['allow_unsafe_links']);

    $element += $this->createSettingElement('enable_em', [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Emphasis'),
      '#description' => $this->t('Enables <code>&lt;em&gt;</code> parsing.'),
    ], $form_state);

    $element += $this->createSettingElement('enable_strong', [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Strong'),
      '#description' => $this->t('Enables <code>&lt;strong&gt;</code> parsing.'),
    ], $form_state);

    $element += $this->createSettingElement('html_input', [
      '#weight' => -1,
      '#type' => 'select',
      '#title' => $this->t('HTML Input'),
      '#description' => $this->t('Strategy to use when handling raw HTML input.'),
      '#options' => [
        'allow' => $this->t('Allow'),
        'escape' => $this->t('Escape'),
        'strip' => $this->t('Strip'),
      ],
    ], $form_state);
    $this->renderStrategyDisabledSettingState($form_state, $element['html_input']);

    // Always allow html_input when using a render strategy.
    if ($this->getRenderStrategy() !== static::NONE) {
      $element['html_input']['#value'] = 'allow';
    }

    $element += $this->createSettingElement('max_nesting_level', [
      '#type' => 'number',
      '#title' => $this->t('Maximum Nesting Level'),
      '#description' => $this->t('The maximum nesting level for blocks. Setting this to a positive integer can help protect against long parse times and/or segfaults if blocks are too deeply-nested.'),
      '#min' => 0,
      '#max' => 100000,
    ], $form_state, 'intval');

    $element['renderer'] = [
      '#type' => 'container',
    ];
    $rendererSubformState = SubformState::createForSubform($element['renderer'], $element, $form_state);

    $element['renderer'] += $this->createSettingElement('renderer.block_separator', [
      '#type' => 'textfield',
      '#title' => $this->t('Block Separator'),
      '#description' => $this->t('String to use for separating renderer block elements.'),
    ], $rendererSubformState, '\Drupal\markdown\Plugin\Markdown\CommonMark\CommonMark::addcslashes');

    $element['renderer'] += $this->createSettingElement('renderer.inner_separator', [
      '#type' => 'textfield',
      '#title' => $this->t('Inner Separator'),
      '#description' => $this->t('String to use for separating inner block contents.'),
    ], $rendererSubformState, '\Drupal\markdown\Plugin\Markdown\CommonMark\CommonMark::addcslashes');

    $element['renderer'] += $this->createSettingElement('renderer.soft_break', [
      '#type' => 'textfield',
      '#title' => $this->t('Soft Break'),
      '#description' => $this->t('String to use for rendering soft breaks.'),
    ], $rendererSubformState, '\Drupal\markdown\Plugin\Markdown\CommonMark\CommonMark::addcslashes');
    $element['renderer']['#access'] = $element['renderer']['block_separator']['#access'];

    $element += $this->createSettingElement('use_asterisk', [
      '#type' => 'checkbox',
      '#title' => $this->t('Use Asterisk'),
      '#description' => $this->t('Enables parsing of <code>*</code> for emphasis.'),
    ], $form_state);

    $element += $this->createSettingElement('use_underscore', [
      '#type' => 'checkbox',
      '#title' => $this->t('Use Underscore'),
      '#description' => $this->t('Enables parsing of <code>_</code> for emphasis.'),
    ], $form_state);

    $element += $this->createSettingElement('unordered_list_markers', [
      '#type' => 'textarea',
      '#title' => $this->t('Unordered List Markers'),
      '#description' => $this->t('Characters that are used to indicated a bulleted list; only one character per line.'),
    ], $form_state, '\Drupal\markdown\Util\KeyValuePipeConverter::denormalizeNoKeys');

    return $element;
  }

  /**
   * Wrapper method to assist with setting values in form.
   *
   * @param string $string
   *   The string to add slashes.
   * @param string $charlist
   *   The character list that slashes will be added to.
   *
   * @return string
   *   The modified string.
   */
  public static function addcslashes($string, $charlist = "\n\r\t") {
    return \addcslashes($string, $charlist);
  }

  /**
   * Retrieves a CommonMark converter instance.
   *
   * @return \League\CommonMark\CommonMarkConverter|\ColinODell\CommonMark\CommonMarkConverter
   *   A CommonMark converter.
   */
  public function converter() {
    if (!$this->converter) {
      $version = $this->getVersion();
      if (Semver::satisfies($version, '>=0.13.0')) {
        $this->converter = $this->getObject($this->getSettings(TRUE), $this->getEnvironment());
      }
      elseif (Semver::satisfies($version, '>=0.6.0 <0.13.0')) {
        $this->converter = $this->getObject($this->getSettings(TRUE));
      }
      else {
        $this->converter = $this->getObject();
      }
    }
    return $this->converter;
  }

  /**
   * Creates an environment.
   *
   * @return \League\CommonMark\ConfigurableEnvironmentInterface|\League\CommonMark\Environment
   *   A CommonMark environment.
   */
  protected function createEnvironment() {
    $environment = static::environmentClass();
    return $environment::createCommonMarkEnvironment();
  }

  /**
   * {@inheritdoc}
   */
  public function extensionInterfaces() {
    // Some older versions of CommonMark didn't support external extensions.
    if (!interface_exists('\\League\\CommonMark\\Extension\\ExtensionInterface')) {
      return [];
    }
    return parent::extensionInterfaces();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    $configuration = parent::getConfiguration();

    // Unless the render strategy is set to "none", force the following
    // settings so the parser doesn't attempt to filter things.
    if ($this->getRenderStrategy() !== static::NONE) {
      $configuration['settings']['allow_unsafe_links'] = TRUE;
      $configuration['settings']['html_input'] = 'allow';
    }

    // Escape newlines.
    if (isset($configuration['settings']['renderer']) && is_array($configuration['settings']['renderer'])) {
      foreach ($configuration['settings']['renderer'] as &$setting) {
        $setting = addcslashes($setting, "\n\r\t");
      }
    }

    // Set infinite max nesting level to 0.
    if (isset($configuration['settings']['max_nesting_level']) && $configuration['settings']['max_nesting_level'] === INF) {
      $configuration['settings']['max_nesting_level'] = 0;
    }

    // Normalize settings from a key|value string into an associative array.
    foreach (['unordered_list_markers'] as $name) {
      if (isset($configuration['settings'][$name])) {
        $configuration['settings'][$name] = KeyValuePipeConverter::normalize($configuration['settings'][$name]);
      }
    }

    return $configuration;
  }

  /**
   * Retrieves a CommonMark environment, creating it if necessary.
   *
   * @return \League\CommonMark\Environment
   *   The CommonMark environment.
   */
  protected function getEnvironment() {
    if (!$this->environment) {
      $environment = $this->createEnvironment();
      $settings = $this->getSettings(TRUE);

      // Unless the render strategy is set to "none", force the following
      // settings so the parser doesn't attempt to filter things.
      if ($this->getRenderStrategy() !== static::NONE) {
        $settings['allow_unsafe_links'] = TRUE;
        $settings['html_input'] = 'allow';
      }

      $extensions = $this->extensions();
      foreach ($extensions as $extension) {
        /** @var \Drupal\markdown\Plugin\Markdown\CommonMark\ExtensionInterface $extension */

        // Skip disabled extensions.
        if (!$extension->isEnabled()) {
          continue;
        }

        // Add extension settings.
        if ($extension instanceof SettingsInterface) {
          // Because CommonMark is highly extensible, any extension that
          // implements settings should provide a specific and unique settings
          // key to wrap its settings when passing it to the environment config.
          // In the off chance the extension absolutely must merge with the
          // root level, it can pass an empty value (i.e. '' or 0); NULL will
          // throw an exception and FALSE will ignore merging with the parsing
          // config altogether.
          $settingsKey = $extension->settingsKey();
          if ($settingsKey === NULL || $settingsKey === TRUE) {
            throw new InvalidPluginDefinitionException($extension->getPluginId(), sprintf('The "%s" markdown extension must also supply a value in %s. This is a requirement of the parser so it knows how extension settings should be merged.', $extension->getPluginId(), '\Drupal\markdown\Plugin\Markdown\SettingsInterface::settingsKey'));
          }

          // If the extension plugin specifies anything other than FALSE, merge.
          if ($settingsKey !== FALSE) {
            $extensionSettings = $extension->getSettings(TRUE);
            if ($settingsKey) {
              $extensionSettings = [$settingsKey => $extensionSettings];
            }
            $settings = NestedArray::mergeDeep($settings, $extensionSettings);
          }
        }

        // Finally, let the extension register itself with the environment.
        // Note: this is our own custom method, that is used throughout the
        // commonmark based @ MarkdownExtension plugins so they can work
        // across multiple API versions where entire interfaces have changed.
        // @see \Drupal\markdown\Plugin\Markdown\CommonMark\ExtensionInterface::register()
        $extension->register($environment);
      }

      // Merge settings into config.
      $environment->setConfig(NestedArray::mergeDeep($environment->getConfig(), $settings));

      $this->environment = $environment;
    }
    return $this->environment;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // Convert newlines to actual newlines.
    if (isset($configuration['settings']['renderer'])) {
      foreach ($configuration['settings']['renderer'] as &$setting) {
        $setting = stripcslashes($setting);
      }
    }

    // Set the max nesting level to infinite if not a positive number.
    if (isset($configuration['settings']['max_nesting_level']) && $configuration['settings']['max_nesting_level'] <= 0) {
      $configuration['settings']['max_nesting_level'] = INF;
    }

    // Normalize settings from a key|value string into an associative array.
    foreach (['unordered_list_markers'] as $name) {
      if (isset($configuration['settings'][$name])) {
        $configuration['settings'][$name] = KeyValuePipeConverter::normalize($configuration['settings'][$name]);
      }
    }

    return parent::setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    if ($unorderedListMarkers = $form_state->getValue('unordered_list_markers')) {
      $unorderedListMarkers = KeyValuePipeConverter::normalize($unorderedListMarkers);
      foreach ($unorderedListMarkers as $marker) {
        if (strlen($marker) > 1) {
          $form_state->setError($form['unordered_list_markers'], $this->t('The Unordered List Markers must be only one character per line.'));
        }
      }
    }
  }

}
