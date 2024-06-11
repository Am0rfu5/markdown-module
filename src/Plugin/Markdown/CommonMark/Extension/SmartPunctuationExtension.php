<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark\Extension;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\markdown\Plugin\Markdown\CommonMark\BaseExtension;
use Drupal\markdown\Plugin\Markdown\SettingsInterface;
use Drupal\markdown\Traits\FeatureDetectionTrait;
use Drupal\markdown\Traits\SettingsTrait;

/**
 * Smart Punctuation extension.
 *
 * @MarkdownExtension(
 *   id = "commonmark-smart-punctuation",
 *   label = @Translation("Smart Punctuation"),
 *   description = @Translation("Intelligently converts ASCII quotes, dashes, and ellipses to their Unicode equivalents."),
 *   libraries = {
 *     @ComposerPackage(
 *       id = "league/commonmark",
 *       object = "\League\CommonMark\Extension\SmartPunct\SmartPunctExtension",
 *       customLabel = "commonmark-smart-punctuation",
 *       url = "https://commonmark.thephpleague.com/extensions/smart-punctuation/",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = "^1.3 || ^2.0"},
 *          ),
 *       },
 *     ),
 *     @ComposerPackage(
 *       id = "league/commonmark-ext-smartpunct",
 *       deprecated = @Translation("Support for this library was deprecated in markdown:8.x-2.0 and will be removed from markdown:3.0.0."),
 *       object = "\League\CommonMark\Ext\SmartPunct\SmartPunctExtension",
 *       url = "https://github.com/thephpleague/commonmark-ext-smartpunct",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = ">=0.13 <1.0.0 || ^1.0"},
 *          ),
 *       },
 *     ),
 *   },
 * )
 */
class SmartPunctuationExtension extends BaseExtension implements PluginFormInterface, SettingsInterface {

  use FeatureDetectionTrait;
  use SettingsTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings($pluginDefinition) {
    /** @var \Drupal\markdown\Annotation\InstallablePlugin $pluginDefinition */

    // Older versions of the deprecated extension didn't have settings.
    if (!static::featureExists('settings')) {
      return [];
    }

    return [
      'double_quote_opener' => '“',
      'double_quote_closer' => '”',
      'single_quote_opener' => '‘',
      'single_quote_closer' => '’',
    ];
  }

  /**
   * Feature detection for settings.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public static function featureSettings() {
    return class_exists('\\League\\CommonMark\\Ext\\SmartPunct\\SmartPunctExtension') && defined('\\League\\CommonMark\\Ext\\SmartPunct\\Quote::DOUBLE_QUOTE_OPENER') || class_exists('\\League\\CommonMark\\Extension\\SmartPunct\\SmartPunctExtension');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */

    // Immediately return if extension doesn't support settings.
    if (!static::featureExists('settings')) {
      return $element;
    }

    $element += $this->createSettingElement('double_quote_opener', [
      '#type' => 'textfield',
      '#title' => $this->t('Double Quote Opener'),
    ], $form_state);

    $element += $this->createSettingElement('double_quote_closer', [
      '#type' => 'textfield',
      '#title' => $this->t('Double Quote Closer'),
    ], $form_state);

    $element += $this->createSettingElement('single_quote_opener', [
      '#type' => 'textfield',
      '#title' => $this->t('Single Quote Opener'),
    ], $form_state);

    $element += $this->createSettingElement('single_quote_closer', [
      '#type' => 'textfield',
      '#title' => $this->t('Single Quote Closer'),
    ], $form_state);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsKey() {
    return static::featureExists('settings') ? 'smartpunct' : FALSE;
  }

}
