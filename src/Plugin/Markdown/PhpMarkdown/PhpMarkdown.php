<?php

namespace Drupal\markdown\Plugin\Markdown\PhpMarkdown;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\BaseParser;
use Drupal\markdown\Plugin\Markdown\SettingsInterface;
use Drupal\markdown\Traits\ParserAllowedHtmlTrait;
use Drupal\markdown\Util\KeyValuePipeConverter;

/**
 * Support for PHP Markdown by Michel Fortin.
 *
 * @MarkdownAllowedHtml(
 *   id = "php-markdown",
 * )
 * @MarkdownParser(
 *   id = "php-markdown",
 *   label = @Translation("PHP Markdown"),
 *   description = @Translation("Parser for Markdown."),
 *   weight = 31,
 *   libraries = {
 *     @ComposerPackage(
 *       id = "michelf/php-markdown",
 *       object = "\Michelf\Markdown",
 *       url = "https://michelf.ca/projects/php-markdown",
 *     ),
 *   }
 * )
 */
class PhpMarkdown extends BaseParser implements AllowedHtmlInterface, SettingsInterface {

  use ParserAllowedHtmlTrait;

  /**
   * The PHP Markdown class to use.
   *
   * @var string
   */
  protected static $phpMarkdownClass = '\\Michelf\\Markdown';

  /**
   * The PHP Markdown instance.
   *
   * @var \Michelf\Markdown
   */
  protected $phpMarkdown;

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    unset($this->phpMarkdown);
    return parent::__sleep();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings($pluginDefinition) {
    /** @var \Drupal\markdown\Annotation\InstallablePlugin $pluginDefinition */
    return [
      'empty_element_suffix' => ' />',
      'enhanced_ordered_list' => FALSE,
      'hard_wrap' => FALSE,
      'no_entities' => FALSE,
      'no_markup' => FALSE,
      'predef_titles' => [],
      'predef_urls' => [],
      'tab_width' => 4,
    ] + parent::defaultSettings($pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */
    $element = parent::buildConfigurationForm($element, $form_state);

    $element += $this->createSettingElement('enhanced_ordered_list', [
      '#type' => 'checkbox',
      '#title' => $this->t('Enhanced Ordered List'),
      '#description' => $this->t('Enabling this allows ordered list to start with a number different from 1.'),
    ], $form_state);

    $element += $this->createSettingElement('hard_wrap', [
      '#type' => 'checkbox',
      '#title' => $this->t('Hard Wrap'),
      '#description' => $this->t('Enabling this will change line breaks into <code>&lt;br /&gt;</code> when the context allows it. When disabled, following the standard Markdown syntax these newlines are ignored unless they a preceded by two spaces.'),
    ], $form_state);

    $element += $this->createSettingElement('no_entities', [
      '#type' => 'checkbox',
      '#title' => $this->t('No Entities'),
      '#description' => $this->t('Enabling this will prevent HTML entities (such as <code>&lt;</code>) from being passed verbatim in the output as it is the standard with Markdown. Instead, the HTML output will be <code>&amp;tl;</code> and once shown in shown the browser it will match perfectly what was written.'),
    ], $form_state);
    $this->renderStrategyDisabledSettingState($form_state, $element['no_entities']);

    $element += $this->createSettingElement('no_markup', [
      '#type' => 'checkbox',
      '#title' => $this->t('No Markup'),
      '#description' => $this->t('Enabling this will prevent HTML tags from the input from being passed to the output.'),
    ], $form_state);
    $this->renderStrategyDisabledSettingState($form_state, $element['no_markup']);

    $element += $this->createSettingElement('empty_element_suffix', [
      '#type' => 'textfield',
      '#title' => $this->t('Empty Element Suffix'),
      '#description' => $this->t('This is the string used to close tags for HTML elements with no content such as <code>&lt;br&gt;</code> and <code>&lt;hr&gt;</code>. The default value creates XML-style empty element tags which are also valid in HTML 5.'),
    ], $form_state);

    $element += $this->createSettingElement('tab_width', [
      '#type' => 'number',
      '#title' => $this->t('Tab Width'),
      '#description' => $this->t('The width of a tab character on input. Changing this will affect how many spaces a tab character represents.<br>NOTE: Keep in mind that when the Markdown syntax spec says "four spaces or one tab", it actually means "four spaces after tabs are expanded to spaces". So this to <code>8</code> will make the parser treat a tab character as two levels of indentation.'),
      '#min' => 4,
      '#max' => 32,
    ], $form_state);

    $element['predefined'] = [
      '#weight' => 10,
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Predefined'),
      '#parents' => $form_state->createParents(),
    ];
    $element['predefined'] += $this->createSettingElement('predef_urls', [
      '#type' => 'textarea',
      '#title' => $this->t('URLs'),
      '#description' => $this->t('A predefined key|value pipe list of URLs, where the key is the value left of a pipe (<code>|</code>) and the URL is to the right of it; only one key|value pipe item per line.<br>For example, adding the following <code>example|http://www.example.com</code> allows the following in markdown <code>[click here][example]</code> to be parsed and replaced with <code>&lt;a href="http://www.example.com"&gt;click here&lt;/a&gt;</code>.'),
    ], $form_state, '\Drupal\markdown\Util\KeyValuePipeConverter::denormalize');

    $element['predefined'] += $this->createSettingElement('predef_titles', [
      '#type' => 'textarea',
      '#title' => $this->t('Titles'),
      '#description' => $this->t('A predefined key|value pipe list of titles, where the key is the value left of a pipe (<code>|</code>) and the URL is to the right of it; only one key|value pipe item per line.<br>The key must match a corresponding key in Predefined URLs and the value will be used to set the link title attribute.'),
    ], $form_state, '\Drupal\markdown\Util\KeyValuePipeConverter::denormalize');
    $form_state->addElementState($element['predefined']['predef_titles'], 'disabled', 'predef_urls', ['value' => '']);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function convertToHtml($markdown, LanguageInterface $language = NULL) {
    return $this->getPhpMarkdown()->transform($markdown);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    $configuration = parent::getConfiguration();

    // Normalize settings from a key|value string into an associative array.
    foreach (['predef_titles', 'predef_urls'] as $name) {
      if (isset($configuration['settings'][$name])) {
        $configuration['settings'][$name] = KeyValuePipeConverter::normalize($configuration['settings'][$name]);
      }
    }

    return $configuration;
  }

  /**
   * Retrieves the PHP Markdown parser.
   *
   * @return \Michelf\Markdown
   *   A PHP Markdown parser.
   */
  public function getPhpMarkdown() {
    if (!$this->phpMarkdown) {
      $this->phpMarkdown = new static::$phpMarkdownClass();
      $settings = $this->getSettings();

      // Unless the render strategy is set to "none", force the
      // following settings to be disabled.
      if ($this->getRenderStrategy() !== static::NONE) {
        $settings['no_entities'] = FALSE;
        $settings['no_markup'] = FALSE;
      }

      // Set settings.
      foreach ($this->getSettings() as $name => $value) {
        if ($this->settingExists($name)) {
          $this->phpMarkdown->$name = $value;
        }
      }
    }
    return $this->phpMarkdown;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // Normalize settings from a key|value string into an associative array.
    foreach (['predef_titles', 'predef_urls'] as $name) {
      if (isset($configuration['settings'][$name])) {
        $configuration['settings'][$name] = KeyValuePipeConverter::normalize($configuration['settings'][$name]);
      }
    }
    return parent::setConfiguration($configuration);
  }

  /**
   * Indicates whether the setting exists.
   *
   * @param string $name
   *   The setting name.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function settingExists($name) {
    return property_exists(static::$phpMarkdownClass, $name);
  }

}
