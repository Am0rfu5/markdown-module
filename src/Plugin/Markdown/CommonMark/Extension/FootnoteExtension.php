<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark\Extension;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\CommonMark\BaseExtension;
use Drupal\markdown\Plugin\Markdown\ExtensibleParserInterface;
use Drupal\markdown\Plugin\Markdown\ParserInterface;
use Drupal\markdown\Plugin\Markdown\SettingsInterface;
use Drupal\markdown\Traits\FormTrait;
use Drupal\markdown\Traits\SettingsTrait;

/**
 * Footnotes extension.
 *
 * @MarkdownAllowedHtml(
 *   id = "commonmark-footnotes",
 * )
 * @MarkdownExtension(
 *   id = "commonmark-footnotes",
 *   label = @Translation("Footnotes"),
 *   description = @Translation("Adds the ability to create footnotes in markdown."),
 *   libraries = {
 *     @ComposerPackage(
 *       id = "league/commonmark",
 *       object = "\League\CommonMark\Extension\Footnote\FootnoteExtension",
 *       customLabel = "commonmark-footnotes",
 *       url = "https://commonmark.thephpleague.com/extensions/footnotes/",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = "^1.5 || ^2.0"},
 *          ),
 *       },
 *     ),
 *     @ComposerPackage(
 *       id = "rezozero/commonmark-ext-footnotes",
 *       deprecated = @Translation("Support for this library was deprecated in markdown:8.x-2.0 and will be removed from markdown:3.0.0."),
 *       object = "\RZ\CommonMark\Ext\Footnote\FootnoteExtension",
 *       url = "https://github.com/rezozero/commonmark-ext-footnotes",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = "^1.1 || ^2.0"},
 *          ),
 *       },
 *     ),
 *   },
 * )
 */
class FootnoteExtension extends BaseExtension implements AllowedHtmlInterface, PluginFormInterface, SettingsInterface {

  use FormTrait;
  use SettingsTrait;

  /**
   * {@inheritdoc}
   */
  public function allowedHtmlTags(ParserInterface $parser, ActiveTheme $activeTheme = NULL) {
    $tags = [
      'a' => [
        'href' => TRUE,
        'role' => TRUE,
        'rev' => TRUE,
      ],
      'div' => [
        'class' => TRUE,
        'id' => TRUE,
        'role' => TRUE,
      ],
      'li' => [
        'class' => TRUE,
        'id' => TRUE,
        'role' => TRUE,
      ],
      'ol' => [],
      'sup' => [
        'id' => TRUE,
      ],
    ];

    if (!$this->isPreferredLibraryInstalled() || ($parser instanceof ExtensibleParserInterface && ($extension = $parser->extension($this->pluginId)) && $extension instanceof SettingsInterface && $extension->getSetting('container_add_hr'))) {
      $tags['hr'] = [];
    }

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings($pluginDefinition) {
    /** @var \Drupal\markdown\Annotation\InstallablePlugin $pluginDefinition */

    // Immediately return if not using the newer bundled extension.
    if ($pluginDefinition->object === 'RZ\\CommonMark\\Ext\\Footnote\\FootnoteExtension') {
      return [];
    }

    return [
      'backref_class'      => 'footnote-backref',
      'container_add_hr'   => TRUE,
      'container_class'    => 'footnotes',
      'footnote_class'     => 'footnote',
      // CommonMark defaults to using a colon delimiter ("fn:"), however
      // this causes core's XSS filter to strip everything past it; use a
      // hyphen (-) instead.
      // @see https://www.drupal.org/project/drupal/issues/2544110
      'footnote_id_prefix' => 'fn-',
      'ref_class'          => 'footnote-ref',
      // CommonMark defaults to using a colon delimiter ("fnref:"), however
      // this causes core's XSS filter to strip everything past it; use a
      // hyphen (-) instead.
      // @see https://www.drupal.org/project/drupal/issues/2544110
      'ref_id_prefix'      => 'fnref-',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */

    // Add a note about core's aggressive XSS and how it affects footnotes.
    // @todo Remove note about core XSS bug/workaround.
    // @see https://www.drupal.org/project/markdown/issues/3136378
    if (!$this->isPreferredLibraryInstalled()) {
      $parent = &$form_state->getParentForm();
      $parent['xss_bug'] = static::createInlineMessage([
        'warning' => [
          $this->t('There is a known bug that prevents footnote identifiers from being rendered properly due to aggressive XSS filtering. There is a <a href=":issue" target="_blank">temporary workaround</a>, but you must manually implement it in a custom module. It is highly recommended that you instead upgrade to a newer version of CommonMark (1.5+) which includes a new bundled Footnotes extension where these settings are customizable.', [
            ':issue' => 'https://www.drupal.org/project/markdown/issues/3131224#comment-13613381',
          ]),
        ],
      ]);
      return $element;
    }

    $element += $this->createSettingElement('backref_class', [
      '#type' => 'textfield',
      '#title' => $this->t('Back Reference Class'),
      '#description' => $this->t('Defines which HTML class should be assigned to rendered footnote back reference elements.'),
    ], $form_state);

    $element += $this->createSettingElement('container_add_hr', [
      '#type' => 'checkbox',
      '#title' => $this->t('Container Add Horizontal Rule'),
      '#description' => $this->t('Controls whether an <code>&lt;hr&gt;</code> element should be added inside the container.  Disable this if you want more control over how the footnote section at the bottom is differentiated from the rest of the document.'),
    ], $form_state);

    $element += $this->createSettingElement('container_class', [
      '#type' => 'textfield',
      '#title' => $this->t('Container Class'),
      '#description' => $this->t('Defines which HTML class should be assigned to the container at the bottom of the page which shows all the footnotes.'),
    ], $form_state);

    $element += $this->createSettingElement('footnote_class', [
      '#type' => 'textfield',
      '#title' => $this->t('Footnote Class'),
      '#description' => $this->t('Defines which HTML class should be assigned to rendered footnote elements.'),
    ], $form_state);

    $element += $this->createSettingElement('footnote_id_prefix', [
      '#type' => 'textfield',
      '#title' => $this->t('Footnote ID Prefix'),
      '#description' => $this->moreInfo($this->t('Defines the prefix prepended to footnote elements. Note: due to a core bug, the use of colons (:) is not possible.'), 'https://www.drupal.org/project/drupal/issues/2544110'),
    ], $form_state);

    $element += $this->createSettingElement('ref_class', [
      '#type' => 'textfield',
      '#title' => $this->t('Reference Class'),
      '#description' => $this->t('Defines which HTML class should be assigned to rendered footnote reference elements.'),
    ], $form_state);

    $element += $this->createSettingElement('ref_id_prefix', [
      '#type' => 'textfield',
      '#title' => $this->t('Reference ID Prefix'),
      '#description' => $this->moreInfo($this->t('Defines the prefix prepended to footnote references. Note: due to a core bug, the use of colons (:) is not possible.'), 'https://www.drupal.org/project/drupal/issues/2544110'),
    ], $form_state);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsKey() {
    return $this->isPreferredLibraryInstalled() ? 'footnote' : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */
    foreach (['footnote_id_prefix', 'ref_id_prefix'] as $name) {
      if (strpos($form_state->getValue($name), ':') !== FALSE) {
        $form_state->setError($form[$name], $this->moreInfo($this->t('Due to a core bug, the use of colons (:) in "@title" is not possible.', [
          '@title' => isset($form[$name]['#title']) ? $form[$name]['#title'] : $name,
        ]), 'https://www.drupal.org/project/drupal/issues/2544110'));
      }
    }
  }

}
