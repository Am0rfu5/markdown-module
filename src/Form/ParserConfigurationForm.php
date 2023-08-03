<?php

namespace Drupal\markdown\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\markdown\Markdown;
use Drupal\markdown\Plugin\Markdown\ExtensibleParserInterface;
use Drupal\markdown\Plugin\Markdown\ParserInterface;
use Drupal\markdown\Plugin\Markdown\RenderStrategyInterface;
use Drupal\markdown\Plugin\Markdown\SettingsInterface;
use Drupal\markdown\PluginManager\AllowedHtmlManager;
use Drupal\markdown\PluginManager\ParserManagerInterface;
use Drupal\markdown\Traits\FilterAwareTrait;
use Drupal\markdown\Traits\FormTrait;
use Drupal\markdown\Traits\MoreInfoTrait;
use Drupal\markdown\Traits\ParserAwareTrait;
use Drupal\markdown\Util\FilterAwareInterface;
use Drupal\markdown\Util\FilterFormatAwareInterface;
use Drupal\markdown\Util\FilterHtml;
use Drupal\markdown\Util\FormHelper;
use Drupal\markdown\Util\ParserAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for modifying parser configuration.
 */
class ParserConfigurationForm extends FormBase implements FilterAwareInterface, ParserAwareInterface {

  use FilterAwareTrait;
  use FormTrait;
  use MoreInfoTrait;
  use ParserAwareTrait;
  use PluginDependencyTrait;

  /**
   * The Cache Tags Invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The Element Info Plugin Manager service.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected $elementInfo;

  /**
   * The Markdown Parser Plugin Manager service.
   *
   * @var \Drupal\markdown\PluginManager\ParserManagerInterface
   */
  protected $parserManager;

  /***
   * The typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfigManager;

  /**
   * ParserConfigurationForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The Typed Config Manager service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The Cache Tags Invalidator service.
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $elementInfo
   *   The Element Info Plugin Manager service.
   * @param \Drupal\markdown\PluginManager\ParserManagerInterface $parserManager
   *   The Markdown Parser Plugin Manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Drupal messenger service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, TypedConfigManagerInterface $typedConfigManager, CacheTagsInvalidatorInterface $cacheTagsInvalidator, ElementInfoManagerInterface $elementInfo, ParserManagerInterface $parserManager, MessengerInterface $messenger) {
    $this->configFactory = $configFactory;
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
    $this->elementInfo = $elementInfo;
    $this->parserManager = $parserManager;
    $this->typedConfigManager = $typedConfigManager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container = NULL) {
    if (!$container) {
      $container = \Drupal::getContainer();
    }
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('cache_tags.invalidator'),
      $container->get('plugin.manager.element_info'),
      $container->get('plugin.manager.markdown.parser'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'markdown_parser_configuration';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ParserInterface $parser = NULL) {
    // Set the parser.
    $this->setParser($parser);

    $form += [
      '#parents' => [],
      '#title' => $this->t('Configure @parser', [
        '@parser' => $parser->getLabel(FALSE),
      ]),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    // By default, render the form using system-config-form.html.twig.
    $form['#theme'] = 'system_config_form';

    // Due to the way the Form API works, any time a property is explicitly
    // specified, the default property values are not included. It must be
    // manually retrieved and set here.
    $form['#process'] = $this->elementInfo->getInfoProperty('form', '#process', []);

    // Build the subform via a #process callback.
    $form['#process'][] = [$this, 'processSubform'];

    return $form;
  }

  /**
   * Process callback for constructing markdown settings for a parser.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $complete_form
   *   The complete form, passed by reference.
   *
   * @return array
   *   The processed element.
   *
   * @throws \Drupal\Core\Form\EnforcedResponseException
   *   When an invalid parser or no parser is provided.
   */
  public function processSubform(array &$element, FormStateInterface $form_state, array &$complete_form) {
    // Keep track of subform parents for the validation and submit handlers.
    $form_state->set('markdownSubformParents', ($parents = isset($element['#parents']) ? $element['#parents'] : []));
    $form_state->set('markdownSubformArrayParents', $element['#array_parents']);

    // Add the markdown/admin library to update summaries in vertical tabs.
    $element['#attached']['library'][] = 'markdown/admin';

    // Check for installed parsers.
    if (!$this->parserManager->installedDefinitions()) {
      $error = $this->t('No markdown parsers installed.');
    }
    // Check if parser was provided.
    elseif (!($parser = $this->getParser())) {
      $error = $this->t('No markdown parser has been set. Unable to create the parser form.');
    }
    // Check parser is valid.
    elseif ($parser->getPluginId() === $this->parserManager->getFallbackPluginId()) {
      $error = $this->t('Unknown parser: %parser_id.', [
        '%parser_id' => $parser->getOriginalPluginId(),
      ]);
    }

    // Add #validate and #submit handlers. These help validate and submit
    // the various markdown plugin forms for parsers and extensions.
    if ($validationHandlers = $form_state->getValidateHandlers()) {
      if (!in_array([$this, 'validateSubform'], $validationHandlers)) {
        array_unshift($validationHandlers, [$this, 'validateSubform']);
        $form_state->setValidateHandlers($validationHandlers);
      }
    }
    else {
      $complete_form['#validate'][] = [$this, 'validateSubform'];
    }

    // Build a wrapper for the ajax response.
    $form_state->set('markdownAjaxId', ($markdownAjaxId = Html::getUniqueId('markdown-parser-ajax')));
    $element['ajax'] = static::createElement([
      '#type' => 'container',
      '#id' => $markdownAjaxId,
      '#attributes' => [
        'data' => [
          'markdownElement' => 'wrapper',
        ],
      ],
    ]);

    // Build vertical tabs that parser and extensions will go into.
    $element['ajax']['vertical_tabs'] = [
      '#type' => 'vertical_tabs',
      '#parents' => array_merge($parents, ['vertical_tabs']),
    ];

    // Determine the group that details should be referencing for vertical tabs.
    $form_state->set('markdownGroup', ($group = implode('][', array_merge($parents, ['vertical_tabs']))));

    // Create a subform state.
    $subform_state = SubformState::createForSubform($element, $complete_form, $form_state);

    // Build the parser form.
    $element = $this->buildParser($element, $subform_state);

    if (isset($error)) {
      if (($markdownOverview = Url::fromRoute('markdown.overview', [], ['absolute' => TRUE])) && $markdownOverview->access()) {
        $error = $this->t('@error Visit the <a href=":markdown.overview" target="_blank">Markdown Overview</a> page for more details.', [
          '@error' => $error,
          ':markdown.overview' => $markdownOverview->toString(),
        ]);
      }
      else {
        $error = $this->t('@error Ask your site administrator to install a <a href=":supported_parsers" target="_blank">supported markdown parser</a>.', [
          '@error' => $error,
          ':supported_parsers' => Markdown::DOCUMENTATION_URL . '/parsers',
        ]);
      }

      // If there's no filter associated, show the error after the redirect.
      if (!$this->getFilter()) {
        $this->messenger->addError($error);
      }

      throw new EnforcedResponseException($this->redirect('markdown.overview'), $error);
    }

    return $element;
  }

  /**
   * Builds the parser form elements.
   *
   * @param array $element
   *   An element in a render array.
   * @param \Drupal\markdown\Form\SubformStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The $element passed, modified to include the parser element.
   */
  protected function buildParser(array $element, SubformStateInterface $form_state) {
    $parser = $this->getParser();
    $parserId = $parser->getPluginId();

    $markdownGroup = $form_state->get('markdownGroup');
    $markdownParents = $form_state->get('markdownSubformParents');

    $element['parser'] = [
      '#weight' => -20,
      '#type' => 'details',
      '#title' => $this->t('Parser'),
      '#tree' => TRUE,
      '#parents' => $markdownParents,
      '#group' => $markdownGroup,
    ];
    $parserElement = &$element['parser'];
    $parserSubform = SubformState::createForSubform($parserElement, $element, $form_state);

    $parserElement['id'] = static::createElement([
      '#type' => 'hidden',
      '#default_value' => $parserId,
      '#attributes' => [
        'data' => [
          'markdownSummary' => 'parser',
          'markdownSummaryValue' => $parser->getLabel(),
          'markdownId' => $parserId,
        ],
      ],
    ]);

    // Build render strategy.
    $parserElement = $this->buildRenderStrategy($parser, $parserElement, $parserSubform);

    // Build parser settings.
    $parserElement = $this->buildParserSettings($parser, $parserElement, $parserSubform);

    // Build parser extensions.
    $parserElement = $this->buildParserExtensions($parser, $parserElement, $parserSubform);

    return $element;
  }

  /**
   * Builds the settings for a specific parser.
   *
   * @param \Drupal\markdown\Plugin\Markdown\ParserInterface $parser
   *   The parser.
   * @param array $element
   *   An element in a render array.
   * @param \Drupal\markdown\Form\SubformStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The $element passed, modified to include the parser settings element.
   */
  protected function buildParserSettings(ParserInterface $parser, array $element, SubformStateInterface $form_state) {
    $element['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#open' => TRUE,
    ];

    if ($parser instanceof PluginFormInterface) {
      $parserSettingsSubform = SubformState::createForSubform($element['settings'], $element, $form_state);
      $element['settings'] = $parser->buildConfigurationForm($element['settings'], $parserSettingsSubform);
    }

    // If there are no visible settings, add a description so the user knows
    // that is the case and not left with an empty container.
    if (!Element::getVisibleChildren($element['settings'])) {
      $element['settings']['#description'] = $this->t('This parser has no settings to configure.');
    }

    return $element;

  }

  /**
   * Builds the extension settings for a specific parser.
   *
   * @param \Drupal\markdown\Plugin\Markdown\ParserInterface $parser
   *   The parser.
   * @param array $element
   *   An element in a render array.
   * @param \Drupal\markdown\Form\SubformStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The $element passed, modified to include the parser extension elements.
   */
  protected function buildParserExtensions(ParserInterface $parser, array $element, SubformStateInterface $form_state) {
    // Immediately return if parser isn't extensible.
    if (!($parser instanceof ExtensibleParserInterface)) {
      return $element;
    }

    $markdownGroup = $form_state->get('markdownGroup');

    $extensions = $parser->extensions();
    if (!$extensions) {
      return $element;
    }

    $parents = $element['#parents'];

    $element['extensions'] = ['#type' => 'container'];

    // Add any specific extension settings.
    foreach ($extensions as $extensionId => $extension) {
      $label = $extension->getLabel(FALSE);
      $url = $extension->getUrl();

      $element['extensions'][$extensionId] = [
        '#type' => 'details',
        '#title' => $label,
        '#group' => $markdownGroup,
        '#parents' => array_merge($parents, ['extensions', $extensionId]),
      ];

      /** @var array $extensionElement */
      $extensionElement = &$element['extensions'][$extensionId];
      $extensionSubform = SubformState::createForSubform($extensionElement, $element, $form_state);

      $bundled = in_array($extensionId, $parser->getBundledExtensionIds(), TRUE);
      $installed = $extension->isInstalled();
      $enabled = $extensionSubform->getValue('enabled', $extension->isEnabled());

      if ($experimental = $extension->getExperimental()) {
        $extensionElement['experimental'] = static::createInlineMessage([
          'info' => [
            $experimental === TRUE ? $this->t('This is an experimental extension. Not all features or functionality may work.') : $experimental,
          ],
        ]);
      }

      $extensionElement['libraries'] = $extension->buildStatus(!$installed);

      // Extension enabled checkbox.
      $extensionElement['enabled'] = static::createElement([
        '#type' => 'checkbox',
        '#title' => $this->t('Enable'),
        '#description' => $this->moreInfo($extension->getDescription(), $url),
        '#attributes' => [
          'data' => [
            'markdownElement' => 'extension',
            'markdownSummary' => 'extension',
            'markdownId' => $extensionId,
            'markdownLabel' => $label,
            'markdownInstalled' => $installed,
            'markdownBundle' => $bundled ? $parser->getLabel(FALSE) : FALSE,
            'markdownRequires' => $extension->requires(),
            'markdownRequiredBy' => $extension->requiredBy(),
          ],
        ],
        '#default_value' => $bundled || $enabled,
        '#disabled' => $bundled || !$installed,
      ]);

      // Installed extension settings.
      if ($installed && $extension instanceof PluginFormInterface) {
        $extensionElement['settings'] = [
          '#type' => 'details',
          '#title' => $this->t('Settings'),
          '#open' => TRUE,
        ];
        $extensionSettingsElement = &$extensionElement['settings'];
        $extensionSettingsSubform = SubformState::createForSubform($extensionSettingsElement, $extensionElement, $extensionSubform);
        $extensionSubform->addElementState($extensionSettingsElement, 'visible', 'enabled', ['checked' => TRUE]);

        $extensionSettingsElement = $extension->buildConfigurationForm($extensionSettingsElement, $extensionSettingsSubform);
        $extensionSettingsElement['#access'] = !!Element::getVisibleChildren($extensionSettingsElement);
      }
    }

    // Only show extensions if there are extensions.
    $element['extensions']['#access'] = !!Element::getVisibleChildren($element['extensions']);

    return $element;
  }

  /**
   * Builds the render strategy for a specific parser.
   *
   * @param \Drupal\markdown\Plugin\Markdown\ParserInterface $parser
   *   The parser.
   * @param array $element
   *   An element in a render array.
   * @param \Drupal\markdown\Form\SubformStateInterface $form_state
   *   The form state.
   * @param bool $siteWide
   *   Flag indicating whether the parser is the site-wide parser.
   *
   * @return array
   *   The $element passed, modified to include the render strategy elements.
   */
  protected function buildRenderStrategy(ParserInterface $parser, array $element, SubformStateInterface $form_state, $siteWide = FALSE) {
    $element['render_strategy'] = [
      '#weight' => -10,
      '#type' => 'details',
      '#title' => $this->t('Render Strategy'),
      '#group' => $form_state->get('markdownGroup'),
    ];
    $renderStrategySubform = &$element['render_strategy'];
    $renderStrategySubformState = SubformState::createForSubform($renderStrategySubform, $element, $form_state);

    $renderStrategySubform['type'] = [
      '#weight' => -10,
      '#type' => 'select',
      '#description' => $this->t('Determines the strategy to use when dealing with user provided HTML markup.'),
      '#default_value' => $renderStrategySubformState->getValue('type', $parser->getRenderStrategy()),
      '#attributes' => [
        'data-markdown-element' => 'render_strategy',
        'data-markdown-summary' => 'render_strategy',
      ],
      '#options' => [
        RenderStrategyInterface::FILTER_OUTPUT => $this->t('Filter Output'),
        RenderStrategyInterface::ESCAPE_INPUT => $this->t('Escape Input'),
        RenderStrategyInterface::STRIP_INPUT => $this->t('Strip Input'),
        RenderStrategyInterface::NONE => $this->t('None'),
      ],
    ];
    $renderStrategySubform['type']['#description'] = $this->moreInfo($renderStrategySubform['type']['#description'], RenderStrategyInterface::DOCUMENTATION_URL . '#xss');

    // Build allowed HTML plugins.
    $renderStrategySubform['plugins'] = [
      '#weight' => -10,
      '#type' => 'item',
      '#input' => FALSE,
      '#title' => $this->t('Allowed HTML'),
      '#description_display' => 'before',
      '#description' => $this->t('The following are registered <code>@MarkdownAllowedHtml</code> plugins that allow HTML tags and attributes based on configuration. These are typically provided by the parser itself, any of its enabled extensions that convert additional HTML tag and potentially various Drupal filters, modules or themes (if supported).'),
    ];
    $renderStrategySubform['plugins']['#description'] = $this->moreInfo($renderStrategySubform['plugins']['#description'], RenderStrategyInterface::DOCUMENTATION_URL);
    $renderStrategySubformState->addElementState($renderStrategySubform['plugins'], 'visible', 'type', ['value' => RenderStrategyInterface::FILTER_OUTPUT]);

    $allowedHtmlManager = AllowedHtmlManager::create();
    foreach ($allowedHtmlManager->appliesTo($parser) as $plugin_id => $allowedHtml) {
      $pluginDefinition = $allowedHtml->getPluginDefinition();
      $label = isset($pluginDefinition['label']) ? $pluginDefinition['label'] : $plugin_id;
      $description = isset($pluginDefinition['description']) ? $pluginDefinition['description'] : '';
      $type = isset($pluginDefinition['type']) ? $pluginDefinition['type'] : 'other';
      if (!isset($renderStrategySubform['plugins'][$type])) {
        $renderStrategySubform['plugins'][$type] = [
          '#type' => 'details',
          '#open' => TRUE,
          '#title' => $this->t(ucfirst($type) . 's'), //phpcs:ignore
          '#parents' => $renderStrategySubformState->createParents(['plugins']),
        ];
        if ($type === 'module') {
          $renderStrategySubform['plugins'][$type]['#weight'] = -10;
        }
        if ($type === 'filter') {
          $renderStrategySubform['plugins'][$type]['#weight'] = -9;
          $renderStrategySubform['plugins'][$type]['#description'] = $this->t('NOTE: these will only be applied when the filter it represents is actually enabled.');
          $renderStrategySubform['plugins'][$type]['#description_display'] = 'before';
        }
        if ($type === 'parser') {
          $renderStrategySubform['plugins'][$type]['#weight'] = -8;
        }
        if ($type === 'extension') {
          $renderStrategySubform['plugins'][$type]['#weight'] = -7;
          $renderStrategySubform['plugins'][$type]['#title'] = $this->t('Extensions');
          $renderStrategySubform['plugins'][$type]['#description'] = $this->t('NOTE: these will only be applied when the parser extension it represents is actually enabled.');
          $renderStrategySubform['plugins'][$type]['#description_display'] = 'before';
        }
        if ($type === 'theme') {
          $renderStrategySubform['plugins'][$type]['#weight'] = -6;
          $renderStrategySubform['plugins'][$type]['#description'] = $this->t('NOTE: these will only be applied when the theme that provides the plugin is the active theme or is a descendant of the active theme.');
          $renderStrategySubform['plugins'][$type]['#description_display'] = 'before';
        }
      }
      $allowedHtmlTags = $allowedHtml->allowedHtmlTags($parser);
      $allowedHtmlPlugins = $parser->getAllowedHtmlPlugins();

      // Determine the default value.
      $defaultValue = NULL;
      if ($allowedHtmlTags) {
        // Setting value.
        if (!isset($defaultValue) && isset($allowedHtmlPlugins[$plugin_id])) {
          $defaultValue = $allowedHtmlPlugins[$plugin_id];
        }
        if (!isset($defaultValue)) {
          if ($type === 'filter' && ($filter = $this->getFilter()) && $filter instanceof FilterFormatAwareInterface && ($format = $filter->getFilterFormat())) {
            $definition = $allowedHtml->getPluginDefinition();
            $filterId = isset($definition['requiresFilter']) ? $definition['requiresFilter'] : $plugin_id;
            $defaultValue = $format->filters()->has($filterId) ? !!$format->filters($filterId)->status : FALSE;
          }
          elseif ($type === 'extension' && $parser instanceof ExtensibleParserInterface && ($parser->extensions()->has($plugin_id))) {
            $defaultValue = $parser->extension($plugin_id)->isEnabled();
          }
          else {
            $defaultValue = TRUE;
          }
        }
      }

      $renderStrategySubform['plugins'][$type][$plugin_id] = [
        '#type' => 'checkbox',
        '#title' => $label,
        '#disabled' => !$allowedHtmlTags,
        '#description' => Markup::create(sprintf('%s<pre><code>%s</code></pre>', $description, $allowedHtmlTags ? htmlentities(FilterHtml::tagsToString($allowedHtmlTags)) : $this->t('No HTML tags provided.'))),
        '#default_value' => $renderStrategySubformState->getValue(['plugins', $plugin_id], $defaultValue),
        '#attributes' => [
          'data-markdown-default-value' => $renderStrategySubformState->getValue(['plugins', $plugin_id], $defaultValue) ? 'true' : 'false',
        ],
      ];
      if ($plugin_id === 'markdown') {
        $renderStrategySubform['plugins'][$type][$plugin_id]['#weight'] = -10;
      }

      if (!$allowedHtmlTags) {
        continue;
      }

      // Filters should only show based on whether they're enabled.
      if ($type === 'extension') {
        // If using the site-wide parser, then allowed HTML plugins that
        // reference disabled extensions there cannot be enable here.
        if ($siteWide) {
          $extensionDisabled = $defaultValue !== TRUE;
          $renderStrategySubform['plugins'][$type][$plugin_id]['#disabled'] = $extensionDisabled;
          if ($extensionDisabled) {
            $renderStrategySubform['plugins'][$type][$plugin_id]['#title'] = new FormattableMarkup('@title @disabled', [
              '@title' => $renderStrategySubform['plugins'][$type][$plugin_id]['#title'],
              '@disabled' => $this->t('(extension disabled)'),
            ]);
          }
        }
        else {
          $parents = array_merge(array_slice($renderStrategySubformState->createParents(), 0, -1), [
            'extensions', $plugin_id, 'enabled',
          ]);
          $selector = ':input[name="' . array_shift($parents) . '[' . implode('][', $parents) . ']"]';
          $renderStrategySubform['plugins'][$type][$plugin_id]['#title'] = new FormattableMarkup('@title @disabled', [
            '@title' => $renderStrategySubform['plugins'][$type][$plugin_id]['#title'],
            '@disabled' => $renderStrategySubformState->conditionalElement([
              '#value' => $this->t('(extension disabled)'),
            ], 'visible', $selector, ['checked' => FALSE]),
          ]);
          $renderStrategySubform['plugins'][$type][$plugin_id]['#states'] = [
            '!checked' => [$selector => ['checked' => FALSE]],
            'disabled' => [$selector => ['checked' => FALSE]],
          ];
        }
      }
      elseif ($type === 'filter') {
        $selector = ':input[name="filters[' . $plugin_id . '][status]"]';
        $renderStrategySubform['plugins'][$type][$plugin_id]['#title'] = new FormattableMarkup('@title @disabled', [
          '@title' => $renderStrategySubform['plugins'][$type][$plugin_id]['#title'],
          '@disabled' => $renderStrategySubformState->conditionalElement([
            '#value' => $this->t('(filter disabled)'),
          ], 'visible', $selector, ['checked' => FALSE]),
        ]);
        $renderStrategySubform['plugins'][$type][$plugin_id]['#states'] = [
          '!checked' => [$selector => ['checked' => FALSE]],
          'disabled' => [$selector => ['checked' => FALSE]],
        ];
      }
    }
    $renderStrategySubform['plugins']['#access'] = !!Element::getVisibleChildren($renderStrategySubform['plugins']);

    $renderStrategySubform['custom_allowed_html'] = [
      '#weight' => -10,
      '#type' => 'textarea',
      '#title' => $this->t('Custom Allowed HTML'),
      '#description' => $this->t('A list of additional custom allowed HTML tags that can be used. This follows the same rules as above; use cautiously and sparingly.'),
      '#default_value' => $renderStrategySubformState->getValue('custom_allowed_html', $parser->getCustomAllowedHtml()),
      '#attributes' => [
        'data-markdown-element' => 'custom_allowed_html',
      ],
    ];
    $renderStrategySubform['custom_allowed_html']['#description'] = $this->moreInfo($renderStrategySubform['custom_allowed_html']['#description'], RenderStrategyInterface::DOCUMENTATION_URL);
    FormHelper::resetToDefault($renderStrategySubform['custom_allowed_html'], 'custom_allowed_html', '', $renderStrategySubformState);
    $renderStrategySubformState->addElementState($renderStrategySubform['custom_allowed_html'], 'visible', 'type', ['value' => RenderStrategyInterface::FILTER_OUTPUT]);

    return $element;
  }

  /**
   * Retrieves configuration from an array of values.
   *
   * @param string $name
   *   The config name to use.
   * @param array $values
   *   An array of values.
   *
   * @return \Drupal\Core\Config\Config
   *   A Config object.
   */
  public function getConfigFromValues($name, array $values) {
    $config = $this->configFactory->getEditable($name);

    // Some older 8.x-2.x code used to have the parser value as a string.
    // @todo Remove after 8.x-2.0 release.
    if (isset($values['parser']) && is_string($values['parser'])) {
      $values['id'] = $values['parser'];
      unset($values['parser']);
    }
    // Some older 8.x-2.x code used to have the parser value as an array.
    // @todo Remove after 8.x-2.0 release.
    elseif (isset($values['parser']) && is_array($values['parser'])) {
      $values += $values['parser'];
    }

    // Load the parser with the values so it can construct the proper config.
    $parserId = isset($values['id']) ? (string) $values['id'] : '';
    $parser = $this->parserManager->createInstance($parserId, $values);

    // Sort $configuration by using the $defaults keys. This ensures there
    // is a consistent order when saving the config.
    $configuration = $parser->getSortedConfiguration();

    $config->setData($configuration);

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Extract the values from the form.
    $values = $form_state->cleanValues()->getValues();

    // Determine the parser identifier.
    $parserId = isset($values['id']) ? (string) $values['id'] : $this->getParser()->getOriginalPluginId();

    // Normalize parser values into config data.
    $config = $this->getConfigFromValues("markdown.parser.$parserId", $values);

    // Save the config.
    $config->save();

    // Invalidate any tags associated with the parser.
    $this->cacheTagsInvalidator->invalidateTags(["markdown.parser.$parserId"]);

    $this->messenger->addStatus($this->t('The configuration options have been saved.'));
  }

  /**
   * Subform submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitSubform(array &$form, FormStateInterface $form_state) {
    // Immediately return if no subform parents or form hasn't submitted.
    if (!($arrayParents = $form_state->get('markdownSubformArrayParents'))|| !$form_state->isSubmitted()) {
      return;
    }
    $subform = &NestedArray::getValue($form, $arrayParents);
    $subformState = SubformState::createForSubform($subform, $form, $form_state);
    $parserId = $subformState->getValue('id');
    if ($parserId && $this->parserManager->hasDefinition($parserId)) {
      $parser = $this->parserManager->createInstance($parserId, $subformState->getValues());
      if ($parser instanceof SettingsInterface && $parser instanceof PluginFormInterface && !empty($subform['parser']['settings'])) {
        $parser->submitConfigurationForm($subform['parser']['settings'], SubformState::createForSubform($subform['parser']['settings'], $subform, $subformState));
      }
      if ($parser instanceof ExtensibleParserInterface && !empty($subform['parser']['extensions'])) {
        foreach ($parser->extensions() as $extensionId => $extension) {
          if ($extension instanceof SettingsInterface && $extension instanceof PluginFormInterface && isset($subform['parser']['extensions'][$extensionId]['settings'])) {
            $parser->submitConfigurationForm($subform['parser']['extensions'][$extensionId]['settings'], SubformState::createForSubform($subform['parser']['extensions'][$extensionId]['settings'], $subform, $subformState));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Extract the values from the form.
    $values = $form_state->cleanValues()->getValues();

    // Determine the parser identifier.
    $parserId = isset($values['id']) ? (string) $values['id'] : $this->getParser()->getOriginalPluginId();

    // Normalize parser values into config data.
    $config = $this->getConfigFromValues("markdown.parser.$parserId", $values);

    $typed_config = $this->typedConfigManager->createFromNameAndData("markdown.parser.$parserId", $config->get());

    $violations = $typed_config->validate();
    foreach ($violations as $violation) {
      $form_state->setErrorByName(static::mapViolationPropertyPathsToFormNames($violation->getPropertyPath()), $violation->getMessage());
    }
  }

  protected static function mapViolationPropertyPathsToFormNames($property_path) {
    return str_replace('.', '][', $property_path);
  }

  /**
   * Subform validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateSubform(array &$form, FormStateInterface $form_state) {
    // Immediately return if no subform parents or form hasn't submitted.
    if (!($arrayParents = $form_state->get('markdownSubformArrayParents'))|| !$form_state->isSubmitted()) {
      return;
    }

    // Submit handlers aren't necessarily known until a user has started the.
    // process of submitting the form. The triggering element might have
    // specific submit handlers that needs to be intercepted and the only place
    // that this can be done is during the validation phase.
    if ($submitHandlers = $form_state->getSubmitHandlers()) {
      if (!in_array([$this, 'submitSubform'], $submitHandlers)) {
        array_unshift($submitHandlers, [$this, 'submitSubform']);
        $form_state->setSubmitHandlers($submitHandlers);
      }
    }
    else {
      $complete_form = &$form_state->getCompleteForm();
      $complete_form['#submit'][] = [$this, 'submitSubform'];
    }

    $subform = &NestedArray::getValue($form, $arrayParents);
    $subformState = SubformState::createForSubform($subform, $form, $form_state);
    $parserId = $subformState->getValue('id');
    if ($parserId && $this->parserManager->hasDefinition($parserId)) {
      $parser = $this->parserManager->createInstance($parserId, $subformState->getValues());
      if ($parser instanceof SettingsInterface && $parser instanceof PluginFormInterface && !empty($subform['parser']['settings'])) {
        $parser->validateConfigurationForm($subform['parser']['settings'], SubformState::createForSubform($subform['parser']['settings'], $subform, $subformState));
      }
      if ($parser instanceof ExtensibleParserInterface && !empty($subform['parser']['extensions'])) {
        foreach ($parser->extensions() as $extensionId => $extension) {
          if ($extension instanceof SettingsInterface && $extension instanceof PluginFormInterface && isset($subform['parser']['extensions'][$extensionId]['settings'])) {
            $extension->validateConfigurationForm($subform['parser']['extensions'][$extensionId]['settings'], SubformState::createForSubform($subform['parser']['extensions'][$extensionId]['settings'], $subform, $subformState));
          }
        }
      }
    }
  }

}
