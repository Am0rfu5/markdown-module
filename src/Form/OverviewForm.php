<?php

namespace Drupal\markdown\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\markdown\MarkdownInterface;
use Drupal\markdown\PluginManager\ExtensionManagerInterface;
use Drupal\markdown\PluginManager\ParserManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OverviewForm extends ConfigFormBase {

  /**
   * The Cache Tags Invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The Markdown Extension Plugin Manager service.
   *
   * @var \Drupal\markdown\PluginManager\ExtensionManagerInterface
   */
  protected $extensionManager;

  /**
   * The Markdown service.
   *
   * @var \Drupal\markdown\MarkdownInterface
   */
  protected $markdown;

  /**
   * The Markdown Parser Plugin Manager service.
   *
   * @var \Drupal\markdown\PluginManager\ParserManagerInterface
   */
  protected $parserManager;

  /**
   * OverviewForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Config Factory service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The Cache Tags Invalidator service.
   * @param \Drupal\markdown\MarkdownInterface $markdown
   *   The Markdown service.
   * @param \Drupal\markdown\PluginManager\ParserManagerInterface $parserManager
   *   The Markdown Parser Plugin Manager service.
   * @param \Drupal\markdown\PluginManager\ExtensionManagerInterface $extensionManager
   *   The Markdown Extension Plugin Manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CacheTagsInvalidatorInterface $cacheTagsInvalidator, MarkdownInterface $markdown, ParserManagerInterface $parserManager, ExtensionManagerInterface $extensionManager) {
    parent::__construct($config_factory);
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
    $this->extensionManager = $extensionManager;
    $this->markdown = $markdown;
    $this->parserManager = $parserManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('cache_tags.invalidator'),
      $container->get('markdown'),
      $container->get('plugin.manager.markdown.parser'),
      $container->get('plugin.manager.markdown.extension')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    $configNames = ['markdown.settings'];
    foreach (array_keys($this->parserManager->installedDefinitions()) as $name) {
      $configNames[] = "markdown.parser.$name";
    }
    return $configNames;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'markdown_overview';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $defaultParser = $this->parserManager->getDefaultParser();

    $form['#attached']['library'][] = 'markdown/admin';

    $form['weights'] = ['#tree' => TRUE];

    $form['enabled'] = [
      '#type' => 'details',
      '#description' => $this->t('The default parser will be used in the event a parser was requested but not explicitly specified (i.e. Twig template). Ordering parsers will return them in a specific order when multiple parsers are requested (i.e. display benchmarking/parsing differences).'),
      '#description_display' => 'after',
    ];
    $form['enabled']['table'] = [
      '#type' => 'table',
      '#theme' => 'table__markdown_enabled_parsers',
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'self',
          'group' => 'parser-weight',
        ],
      ],
      '#attributes' => [
        'class' => [
          'markdown-enabled-parsers',
        ],
      ],
      '#header' => [
        'parser' => $this->t('Parser'),
        'status' => $this->t('Status'),
        'weight' => $this->t('Weight'),
        'ops' => $this->t('Operations'),
      ],
      '#rows' => [],
    ];
    $enabledParsers = &$form['enabled']['table']['#rows'];

    $form['disabled'] = ['#type' => 'details'];
    $form['disabled']['table'] = [
      '#type' => 'table',
      '#theme' => 'table__markdown_disabled_parsers',
      '#header' => [
        'parser' => $this->t('Parser'),
        'status' => $this->t('Status'),
        'ops' => $this->t('Operations'),
      ],
      '#attributes' => [
        'class' => [
          'markdown-disabled-parsers',
        ],
      ],
      '#rows' => [],
    ];
    $disabledParsers = &$form['disabled']['table']['#rows'];

    $form['unavailable'] = ['#type' => 'details'];
    $form['unavailable']['table'] = [
      '#type' => 'table',
      '#theme' => 'table__markdown_unavailable_parsers',
      '#header' => [
        'parser' => $this->t('Parser'),
        'status' => $this->t('Status'),
      ],
      '#attributes' => [
        'class' => [
          'markdown-unavailable-parsers',
        ],
      ],
      '#rows' => [],
    ];
    $unavailableParsers = &$form['unavailable']['table']['#rows'];

    $configurations = [];
    foreach (array_keys($this->parserManager->getDefinitions(FALSE)) as $parser_id) {
      $configurations[$parser_id] = $this->config("markdown.parser.$parser_id")->get() ?: [];
    }

    // Iterate over the parsers.
    foreach ($this->parserManager->all($configurations) as $name => $parser) {
      $isDefault = $defaultParser->getPluginId() === $name;
      $installed = FALSE;
      $enabled = $parser->isEnabled();
      if ($installedLibrary = $parser->getInstalledLibrary()) {
        $installed = TRUE;
        if ($enabled) {
          $table = &$enabledParsers;
        }
        else {
          $table = &$disabledParsers;
        }
        $library = $installedLibrary;
      }
      elseif ($preferredLibrary = $parser->getPreferredLibrary()) {
        $table = &$unavailableParsers;
        $library = $preferredLibrary;
      }
      else {
        continue;
      }

      $rowClasses = [];

      $label = $parser->getLabel(FALSE);
      $link = $parser->getLink($label);

      $row = [];
      $row['parser'] = [
        'class' => ['parser'],
        'data' => [
          '#type' => 'item',
          '#title' => $parser->getLink($isDefault ? new FormattableMarkup('@link (default)', [
            '@link' => $link,
          ]) : $label),
          '#description' => $parser->getDescription(),
          '#description_display' => 'after',
        ],
      ];
      $row['status'] = [
        'class' => ['status'],
        'data' => [
          '#theme' => 'installable_library',
          '#plugin' => $parser,
          '#library' => $library,
        ],
      ];

      $ops = [];
      $dialogOptions = [
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(['width' => 700]),
        ],
      ];
      $options = [
        'query' => \Drupal::destination()->getAsArray(),
      ];
      if ($installed && $enabled) {
        $ops['edit'] = [
          'title' => $this->t('Edit'),
          'url' => Url::fromRoute(
            'markdown.parser.edit',
            ['parser' => $parser],
            $options
          ),
        ];

        $ops['disable'] = [
          'title' => $this->t('Disable'),
          'url' => Url::fromRoute(
            'markdown.parser.confirm_operation',
            ['parser' => $parser, 'operation' => 'disable'],
            $dialogOptions
          ),
        ];

        if (!$isDefault) {
          $ops['default'] = [
            'title' => $this->t('Set as default'),
            'url' => Url::fromRoute(
              'markdown.parser.confirm_operation',
              ['parser' => $parser, 'operation' => 'default'],
              $dialogOptions
            ),
          ];
        }

        $rowClasses[] = 'draggable';
        $form['weights'][$name] = [
          '#type' => 'number',
          '#title' => $this->t('Weight for @title', ['@title' => $label]),
          '#title_display' => 'invisible',
          '#size' => 6,
          '#default_value' => $parser->getWeight(),
          '#attributes' => ['class' => ['parser-weight']],
        ];
        $row['weight'] = [
          'class' => ['weight'],
          'data' => &$form['weights'][$name],
        ];
        $form['weights'][$name]['#printed'] = TRUE;

        $table[$name] = [
          'class' => ['draggable'],
          'data' => $row,
        ];
      }
      elseif ($installed && !$enabled) {
        $ops['enable'] = [
          'title' => $this->t('Enable'),
          'url' => Url::fromRoute(
            'markdown.parser.confirm_operation',
            ['parser' => $parser, 'operation' => 'enable'],
            $dialogOptions
          ),
        ];
      }

      if ($ops) {
        $row['ops'] = [
          'class' => ['ops'],
          'data' => [
            '#type' => 'dropbutton',
            '#dropbutton_type' => 'small',
            '#attached' => [
              'library' => ['core/drupal.dialog.ajax'],
            ],
            '#links' => $ops,
          ],
        ];
      }

      $table[$name] = [
        'class' => $rowClasses,
        'data' => $row,
      ];
    }

    $form['enabled']['#title'] = $this->t('Enabled Parsers (@count)', [
      '@count' => count($enabledParsers),
    ]);

    $form['disabled']['#title'] = $this->t('Disabled Parsers (@count)', [
      '@count' => count($disabledParsers),
    ]);

    $form['unavailable']['#title'] = $this->t('Unavailable Parsers (@count)', [
      '@count' => count($unavailableParsers),
    ]);

    $form['enabled']['#access'] = !!$enabledParsers;
    $form['disabled']['#access'] = !!$disabledParsers;
    $form['unavailable']['#access'] = !!$unavailableParsers;
    $form['enabled']['#open'] = FALSE;
    $form['disabled']['#open'] = FALSE;
    $form['unavailable']['#open'] = FALSE;

    if ($enabledParsers) {
      $form['enabled']['#open'] = TRUE;
    }
    elseif ($disabledParsers) {
      $form['disabled']['#open'] = TRUE;
    }
    elseif ($unavailableParsers) {
      $form['unavailable']['#open'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $invalidateCacheTags = [];

    if ($weights = $form_state->getValue('weights')) {
      asort($weights, SORT_NUMERIC);

      // Reset weights so they start at 0.
      $i = 0;
      foreach (array_keys($weights) as $name) {
        $configName = "markdown.parser.$name";
        $config = $this->config($configName);
        if ($config->get('weight') !== $i) {
          $config->set('weight', $i)->save();
          $invalidateCacheTags[] = $configName;
        }
        $i++;
      }
    }

    $this->parserManager->clearCachedDefinitions();

    if ($invalidateCacheTags) {
      $this->cacheTagsInvalidator->invalidateTags($invalidateCacheTags);
    }

    parent::submitForm($form, $form_state);
  }

}
