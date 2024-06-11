<?php

namespace Drupal\markdown\Plugin\Markdown\PhpMarkdown;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\ParserInterface;
use Drupal\markdown\Util\KeyValuePipeConverter;

/**
 * Support for PHP Markdown Extra by Michel Fortin.
 *
 * @MarkdownAllowedHtml(
 *   id = "php-markdown-extra",
 * )
 * @MarkdownParser(
 *   id = "php-markdown-extra",
 *   label = @Translation("PHP Markdown Extra"),
 *   description = @Translation("Parser for Markdown with extra functionality."),
 *   weight = 30,
 *   libraries = {
 *     @ComposerPackage(
 *       id = "michelf/php-markdown",
 *       object = "\Michelf\MarkdownExtra",
 *       url = "https://michelf.ca/projects/php-markdown/extra",
 *     ),
 *   }
 * )
 * @method \Michelf\MarkdownExtra getPhpMarkdown()
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
class PhpMarkdownExtra extends PhpMarkdown implements AllowedHtmlInterface {

  /**
   * {@inheritdoc}
   */
  protected static $phpMarkdownClass = '\\Michelf\\MarkdownExtra';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings($pluginDefinition) {
    /** @var \Drupal\markdown\Annotation\InstallablePlugin $pluginDefinition */
    return [
      'code_attr_on_pre' => FALSE,
      'code_class_prefix' => '',
      'enhanced_ordered_list' => TRUE,
      'fn_backlink_class' => 'footnote-backref',
      'fn_backlink_html' => '&#8617;&#xFE0E;',
      'fn_backlink_label' => '',
      'fn_backlink_title' => '',
      'fn_id_prefix' => '',
      'fn_link_class' => 'footnote-ref',
      'fn_link_title' => '',
      'hashtag_protection' => FALSE,
      'omit_footnotes' => FALSE,
      'predef_abbr' => [],
      'table_align_class_tmpl' => '',
    ] + parent::defaultSettings($pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public function allowedHtmlTags(ParserInterface $parser, ActiveTheme $activeTheme = NULL) {
    return [
      '*' => [
        'role' => TRUE,
      ],
      'abbr' => [],
      'caption' => [],
      'col' => [
        'span' => TRUE,
      ],
      'colgroup' => [
        'span' => TRUE,
      ],
      'dd' => [],
      'dl' => [],
      'dt' => [],
      'sup' => [],
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
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */
    $element = parent::buildConfigurationForm($element, $form_state);

    $element += $this->createSettingElement('code_attr_on_pre', [
      '#type' => 'checkbox',
      '#title' => $this->t('Code Attribute on <code>&lt;pre&gt;</code>'),
      '#description' => $this->t('When enabled, attributes for code blocks will go on the <code>&lt;pre&gt;</code> tag; otherwise they will be placed on the <code>&lt;code&gt;</code> tag.'),
    ], $form_state);

    $element += $this->createSettingElement('code_class_prefix', [
      '#type' => 'textfield',
      '#title' => $this->t('Code Class Prefix'),
      '#description' => $this->t('An optional prefix for the class names associated with fenced code blocks.'),
    ], $form_state);

    $element += $this->createSettingElement('hashtag_protection', [
      '#type' => 'checkbox',
      '#title' => $this->t('Hashtag Protection'),
      '#description' => $this->t('When enabled, prevents ATX-style headers with no space after the initial hash from being interpreted as headers.'),
    ], $form_state);

    $element += $this->createSettingElement('table_align_class_tmpl', [
      '#type' => 'textfield',
      '#title' => $this->t('Table Align Class Template'),
      '#description' => $this->t('The class attribute determining the alignment of table cells. The default value, which is empty, will cause the align attribute to be used instead of class to specify the alignment.<br>If not empty, the align attribute will not appear. Instead, the value for the class attribute will be determined by replacing any occurrence of <code>%%</code> within the string by left, center, or right. For instance, if set to <code>go-%%</code> and the cell is right-aligned, the result will be: <code>class="go-right"</code>.'),
    ], $form_state);

    // Footnotes.
    $footnote_variable = $this->t('Occurrences of <code>^^</code> in the string will be replaced by the corresponding footnote number in the HTML output. Occurrences of <code>%%</code> will be replaced by a number for the reference (footnotes can have multiple references).');
    $element['footnotes'] = [
      '#weight' => 9,
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Footnotes'),
      '#parents' => $form_state->createParents(),
    ];
    $element['footnotes'] += $this->createSettingElement('fn_backlink_class', [
      '#type' => 'textfield',
      '#title' => $this->t('Backlink Class'),
      '#description' => $this->t('The <code>class</code> attribute to use for footnotes backlinks.<br>@var', ['@var' => $footnote_variable]),
    ], $form_state);

    $element['footnotes'] += $this->createSettingElement('fn_backlink_html', [
      '#type' => 'textfield',
      '#title' => $this->t('Backlink HTML'),
      '#description' => $this->t('HTML content for a footnote backlink. The <code>&amp;#xFE0E;</code> suffix in the default value is there to avoid the curled arrow being rendered as an emoji on mobile devices, but it might cause an unrecognized character to appear on older browsers.<br>@var', ['@var' => $footnote_variable]),
    ], $form_state);

    $element['footnotes'] += $this->createSettingElement('fn_backlink_label', [
      '#type' => 'textfield',
      '#title' => $this->t('Backlink Label'),
      '#description' => $this->t('Add an accessibility label for backlinks (the <code>aria-label</code> attribute), when you want it to be different from the title attribute.<br>@var', ['@var' => $footnote_variable]),
    ], $form_state);

    $element['footnotes'] += $this->createSettingElement('fn_backlink_title', [
      '#type' => 'textfield',
      '#title' => $this->t('Backlink Title'),
      '#description' => $this->t('An optional <code>title</code> attribute for footnotes links and backlinks. Browsers usually show this as a tooltip when the mouse is over the link.<br>@var', ['@var' => $footnote_variable]),
    ], $form_state);

    $element['footnotes'] += $this->createSettingElement('fn_id_prefix', [
      '#type' => 'textfield',
      '#title' => $this->t('ID Prefix'),
      '#description' => $this->t('A prefix for the <code>id</code> attributes generated by footnotes. This is useful if you have multiple markdown documents displayed inside one HTML document to avoid footnote ids to clash each other.'),
    ], $form_state);

    $element['footnotes'] += $this->createSettingElement('fn_link_class', [
      '#type' => 'textfield',
      '#title' => $this->t('Link Class'),
      '#description' => $this->t('The class attribute to use for footnotes links.<br>@var', ['@var' => $footnote_variable]),
    ], $form_state);

    $element['footnotes'] += $this->createSettingElement('fn_link_title', [
      '#type' => 'textfield',
      '#title' => $this->t('Link Title'),
      '#description' => $this->t('An optional <code>title</code> attribute for footnotes links. Browsers usually show this as a tooltip when the mouse is over the link.<br>@var', ['@var' => $footnote_variable]),
    ], $form_state);

    $element['footnotes'] += $this->createSettingElement('omit_footnotes', [
      '#type' => 'checkbox',
      '#title' => $this->t('Omit Footnotes'),
      '#description' => $this->t('When enabled, footnotes are not appended at the end of the generated HTML and the <code>footnotes_assembled</code> variable on the parser object (see <code>hook_markdown_html_alter()</code>) will contain the HTML for the footnote list, allowing footnotes to be moved somewhere else on the page.<br>NOTE: when placing the content of footnotes_assembled on the page, consider adding the attribute <code>role="doc-endnotes"</code> to the HTML element that will enclose the list of footnotes so they are reachable to accessibility tools the same way they would be with the default HTML output.'),
    ], $form_state);

    // Predefined.
    $element['predefined'] += $this->createSettingElement('predef_abbr', [
      '#weight' => -1,
      '#type' => 'textarea',
      '#title' => $this->t('Abbreviations'),
      '#description' => $this->t('A predefined key|value pipe list of abbreviations, where the key is the value left of a pipe (<code>|</code>) and the abbreviation is to the right of it; only one key|value pipe item per line.<br>For example, adding the following <code>html|Hyper Text Markup Language</code> allows the following in markdown <code>*[html]</code> to be parsed and replaced with <code>&lt;abbr title="Hyper Text Markup Language"&gt;HTML&lt;/abbr&gt;</code>.'),
    ], $form_state, '\Drupal\markdown\Util\KeyValuePipeConverter::denormalize');

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    $configuration = parent::getConfiguration();

    // Normalize settings from a key|value string into an associative array.
    foreach (['predef_abbr'] as $name) {
      if (isset($configuration['settings'][$name])) {
        $configuration['settings'][$name] = KeyValuePipeConverter::normalize($configuration['settings'][$name]);
      }
    }

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // Normalize settings from a key|value string into an associative array.
    foreach (['predef_abbr'] as $name) {
      if (isset($configuration['settings'][$name])) {
        $configuration['settings'][$name] = KeyValuePipeConverter::normalize($configuration['settings'][$name]);
      }
    }
    return parent::setConfiguration($configuration);
  }

}
