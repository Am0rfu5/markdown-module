<?php

namespace Drupal\markdown\Plugin\Markdown\Parsedown;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\BaseParser;
use Drupal\markdown\Plugin\Markdown\ParserInterface;
use Drupal\markdown\Plugin\Markdown\SettingsInterface;
use Drupal\markdown\Traits\ParserAllowedHtmlTrait;
use Drupal\markdown\Util\FilterHtml;

/**
 * Support for Parsedown by Emanuil Rusev.
 *
 * @MarkdownAllowedHtml(
 *   id = "parsedown",
 * )
 * @MarkdownParser(
 *   id = "parsedown",
 *   label = @Translation("Parsedown"),
 *   description = @Translation("Parser for Markdown."),
 *   weight = 21,
 *   libraries = {
 *     @ComposerPackage(
 *       id = "erusev/parsedown",
 *       object = "\Parsedown",
 *       url = "https://parsedown.org",
 *     ),
 *   }
 * )
 */
class Parsedown extends BaseParser implements AllowedHtmlInterface, SettingsInterface {

  use ParserAllowedHtmlTrait {
    allowedHtmlTags as allowedHtmlTagsTrait;
  }

  /**
   * The Parsedown class to use.
   *
   * @var string
   */
  protected static $parsedownClass = '\\Parsedown';

  /**
   * The parser version.
   *
   * @var string
   */
  protected static $version;

  /**
   * The Parsedown instance.
   *
   * @var \Parsedown
   */
  protected $parsedown;

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    unset($this->parsedown);
    return parent::__sleep();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings($pluginDefinition) {
    /** @var \Drupal\markdown\Annotation\InstallablePlugin $pluginDefinition */
    return [
      'breaks_enabled' => FALSE,
      'markup_escaped' => FALSE,
      'safe_mode' => FALSE,
      'strict_mode' => FALSE,
      'urls_linked' => TRUE,
    ] + parent::defaultSettings($pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public function allowedHtmlTags(ParserInterface $parser, ActiveTheme $activeTheme = NULL) {
    return FilterHtml::mergeAllowedTags($this->allowedHtmlTagsTrait($parser, $activeTheme), [
      'caption' => [],
      'col' => [
        'span' => TRUE,
      ],
      'colgroup' => [
        'span' => TRUE,
      ],
      'del' => [],
      'table' => [],
      'tbody' => [],
      'td' => [
        'colspan' => TRUE,
        'headers' => TRUE,
        'rowspan' => TRUE,
      ],
      'tfoot' => [],
      'th' => [
        'abbr' => TRUE,
        'colspan' => TRUE,
        'headers' => TRUE,
        'rowspan' => TRUE,
        'scope' => TRUE,
      ],
      'thead' => [],
      'tr' => [],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */
    $element = parent::buildConfigurationForm($element, $form_state);

    $element += $this->createSettingElement('breaks_enabled', [
      '#access' => !!$this->getSettingMethod('breaks_enabled'),
      '#type' => 'checkbox',
      '#title' => $this->t('Automatic line breaks'),
      '#description' => $this->t('Enabling this will use line breaks (<code>&lt;br&gt;</code>) when a new line is detected instead of creating separate paragraphs (<code>&lt;p&gt;</code>).'),
    ], $form_state);

    $element += $this->createSettingElement('markup_escaped', [
      '#access' => !!$this->getSettingMethod('markup_escaped'),
      '#type' => 'checkbox',
      '#title' => $this->t('Markup Escaped'),
      '#description' => $this->t('Enabling this will escape HTML markup.'),
    ], $form_state);
    $this->renderStrategyDisabledSettingState($form_state, $element['markup_escaped']);

    $element += $this->createSettingElement('safe_mode', [
      '#access' => !!$this->getSettingMethod('safe_mode'),
      '#type' => 'checkbox',
      '#title' => $this->t('Safe Mode'),
      '#description' => $this->t('Enabling this will apply sanitization to additional scripting vectors (such as scripting link destinations) that are introduced by the markdown syntax itself.'),
    ], $form_state);
    $this->renderStrategyDisabledSettingState($form_state, $element['safe_mode']);

    // Always disable safe_mode and markup_escaped when using a render strategy.
    if ($this->getRenderStrategy() !== static::NONE) {
      $element['markup_escaped']['#value'] = FALSE;
      $element['safe_mode']['#value'] = FALSE;
    }

    $element += $this->createSettingElement('strict_mode', [
      '#access' => !!$this->getSettingMethod('strict_mode'),
      '#type' => 'checkbox',
      '#title' => $this->t('Strict Mode'),
      '#description' => $this->t('Enables strict CommonMark compliance.'),
    ], $form_state);

    $element += $this->createSettingElement('urls_linked', [
      '#access' => !!$this->getSettingMethod('urls_linked'),
      '#type' => 'checkbox',
      '#title' => $this->t('URLs linked'),
      '#description' => $this->t('Enabling this will automatically create links for URLs.'),
    ], $form_state);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function convertToHtml($markdown, LanguageInterface $language = NULL) {
    return $this->getParsedown()->text($markdown);
  }

  /**
   * Retrieves the Parsedown instance.
   *
   * @return \Parsedown
   *   A PHP Markdown parser.
   */
  public function getParsedown() {
    if (!$this->parsedown) {
      $this->parsedown = new static::$parsedownClass();
      $settings = $this->getSettings();

      // Unless the render strategy is set to "none", force the
      // following settings to be disabled.
      if ($this->getRenderStrategy() !== static::NONE) {
        $settings['markup_escaped'] = FALSE;
        $settings['safe_mode'] = FALSE;
      }

      foreach ($settings as $name => $value) {
        if ($method = $this->getSettingMethod($name)) {
          $this->parsedown->$method($value);
        }
      }
    }
    return $this->parsedown;
  }

  /**
   * {@inheritdoc}
   */
  public function settingExists($name) {
    return !!$this->getSettingMethod($name);
  }

  /**
   * Retrieves the method used to configure a specific setting.
   *
   * @param string $name
   *   The name of the setting.
   *
   * @return string|null
   *   The method name or NULL if method does not exist.
   */
  protected function getSettingMethod($name) {
    $map = static::settingMethodMap();
    return isset($map[$name]) && method_exists(static::$parsedownClass, $map[$name]) ? $map[$name] : NULL;
  }

  /**
   * A map of setting <-> method.
   *
   * @return array
   *   An associative array containing key/value pairs, where the key is the
   *   setting and the value is the method.
   */
  protected static function settingMethodMap() {
    return [
      'breaks_enabled' => 'setBreaksEnabled',
      'markup_escaped' => 'setMarkupEscaped',
      'safe_mode' => 'setSafeMode',
      'strict_mode' => 'setStrictMode',
      'urls_linked' => 'setUrlsLinked',
    ];
  }

}
