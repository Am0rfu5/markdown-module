<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark\Extension;

use Composer\Semver\Semver;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\CommonMark\BaseExtension;
use Drupal\markdown\Plugin\Markdown\ParserInterface;
use Drupal\markdown\Plugin\Markdown\SettingsInterface;
use Drupal\markdown\Traits\FormTrait;
use Drupal\markdown\Traits\SettingsTrait;
use Drupal\markdown\Util\FormHelper;
use Drupal\markdown\Util\KeyValuePipeConverter;

/**
 * @MarkdownAllowedHtml(
 *   id = "commonmark-external-links",
 * )
 * @MarkdownExtension(
 *   id = "commonmark-external-links",
 *   label = @Translation("External Links"),
 *   description = @Translation("Automatically detect links to external sites and adjust the markup accordingly."),
 *   libraries = {
 *     @ComposerPackage(
 *       id = "league/commonmark",
 *       object = "\League\CommonMark\Extension\ExternalLink\ExternalLinkExtension",
 *       customLabel = "commonmark-external-links",
 *       url = "https://commonmark.thephpleague.com/extensions/external-links/",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = "^1.3 || ^2.0"},
 *          ),
 *       },
 *     ),
 *     @ComposerPackage(
 *       id = "league/commonmark-ext-external-link",
 *       deprecated = @Translation("Support for this library was deprecated in markdown:8.x-2.0 and will be removed from markdown:3.0.0."),
 *       object = "\League\CommonMark\Ext\ExternalLink\ExternalLinkExtension",
 *       url = "https://github.com/thephpleague/commonmark-ext-external-link",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = ">=0.19.2 <1.0.0 || ^1.0"},
 *          ),
 *       },
 *     ),
 *   },
 * )
 */
class ExternalLinkExtension extends BaseExtension implements AllowedHtmlInterface, SettingsInterface, PluginFormInterface {

  use SettingsTrait {
    getSettings as getSettingsTrait;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings($pluginDefinition) {
    /** @var \Drupal\markdown\Annotation\InstallablePlugin $pluginDefinition */
    return [
      'html_class' => '',
      'internal_hosts' => [
        '[current-request:host]',
      ],
      'nofollow' => '',
      'noopener' => 'external',
      'noreferrer' => 'external',
      'open_in_new_window' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function allowedHtmlTags(ParserInterface $parser, ActiveTheme $activeTheme = NULL) {
    return [
      'a' => [
        'href' => TRUE,
        'hreflang' => TRUE,
        'rel' => [
          'nofollow' => TRUE,
          'noopener' => TRUE,
          'noreferrer' => TRUE,
        ],
        'target' => [
          '_blank' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */

    $element += $this->createSettingElement('internal_hosts', [
      '#type' => 'textarea',
      '#title' => $this->t('Internal Hosts'),
      '#description' => $this->t('Defines a whitelist of hosts which are considered non-external and should not receive the external link treatment. This can be a single host name, like <code>example.com</code>, which must match exactly. Wildcard matching is also supported using regular expression like <code>/(^|\.)example\.com$/</code>. Note that you must use <code>/</code> characters to delimit your regex. By default, if no internal hosts are provided, all links will be considered external. One host per line.'),
    ], $form_state, '\Drupal\markdown\Util\KeyValuePipeConverter::denormalizeNoKeys');

    $element['token'] = FormHelper::createTokenBrowser();

    $element += $this->createSettingElement('html_class', [
      '#type' => 'textfield',
      '#title' => $this->t('HTML Class'),
      '#description' => $this->t('An HTML class that should be added to external link <code>&lt;a&gt;</code> tags.'),
    ], $form_state);

    $element += $this->createSettingElement('open_in_new_window', [
      '#type' => 'checkbox',
      '#title' => $this->t('Open in a New Window'),
      '#description' => $this->t('Adds <code>target="_blank"</code> to external link <code>&lt;a&gt;</code> tags.'),
    ], $form_state);

    $relOptions = [
      '' => $this->t('No links'),
      'all' => $this->t('All links'),
      'external' => $this->t('External links only'),
      'internal' => $this->t('Internal links only'),
    ];

    $element += $this->createSettingElement('nofollow', [
      '#type' => 'select',
      '#title' => $this->t('No Follow'),
      '#description' => $this->t('Sets the "nofollow" value in the <code>rel</code> attribute. This value instructs search engines to not influence the ranking of the link\'s target in the search engine\'s index. Using this can negatively impact your site\'s SEO ranking if done improperly.'),
      '#options' => $relOptions,
    ], $form_state);

    $element += $this->createSettingElement('noopener', [
      '#type' => 'select',
      '#title' => $this->t('No Opener'),
      '#description' => $this->t('Sets the "noopener" value in the <code>rel</code> attribute. This value instructs the browser to prevent the new page from being able to access the the window that opened the link and forces it run in a separate process.'),
      '#options' => $relOptions,
    ], $form_state);

    $element += $this->createSettingElement('noreferrer', [
      '#type' => 'select',
      '#title' => $this->t('No Referrer'),
      '#description' => $this->t('Sets the "noreferrer" value in the <code>rel</code> attribute. This value instructs the browser from sending an HTTP referrer header to ensure that no referrer information will be leaked.'),
      '#options' => $relOptions,
    ], $form_state);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    $configuration = parent::getConfiguration();

    // Normalize settings from a key|value string into an associative array.
    foreach (['internal_hosts'] as $name) {
      if (isset($configuration['settings'][$name])) {
        $configuration['settings'][$name] = KeyValuePipeConverter::normalize($configuration['settings'][$name]);
      }
    }

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings($runtime = FALSE, $sorted = TRUE) {
    $settings = $this->getSettingsTrait($runtime, $sorted);

    if (!$runtime) {
      return $settings;
    }

    $token = \Drupal::token();
    foreach ($settings['internal_hosts'] as &$host) {
      $host = $token->replace($host);
    }
    $settings['internal_hosts'] = array_unique($settings['internal_hosts']);

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function register($environment) {
    parent::register($environment);

    // For older versions of this extension, certain features didn't exist.
    // Add an inline rendered to take care of those features.
    if (Semver::satisfies($this->getParser()->getVersion(), '>=0.19.2 <1.5.0')) {
      $environment->addInlineRenderer('\\League\\CommonMark\\Inline\\Element\\Link', new ExternalLinkRenderer($environment));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // Normalize settings from a key|value string into an associative array.
    foreach (['internal_hosts'] as $name) {
      if (isset($configuration['settings'][$name])) {
        $configuration['settings'][$name] = KeyValuePipeConverter::normalize($configuration['settings'][$name]);
      }
    }
    return parent::setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsKey() {
    return 'external_link';
  }

}
