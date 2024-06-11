<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark\Extension;

use League\CommonMark\ElementRendererInterface;
use League\CommonMark\HtmlElement;
use League\CommonMark\Inline\Element\AbstractInline;
use League\CommonMark\Inline\Element\Link;
use League\CommonMark\Inline\Renderer\InlineRendererInterface;

class ExternalLinkRenderer implements InlineRendererInterface {

  /**
   * The CommonMark Environment.
   *
   * @var \League\CommonMark\Environment\EnvironmentInterface|\League\CommonMark\EnvironmentInterface
   */
  protected $environment;

  /**
   * ExternalLinkRenderer constructor.
   *
   * @param \League\CommonMark\Environment\EnvironmentInterface|\League\CommonMark\EnvironmentInterface $environment
   *   The CommonMark Environment instance. Note: the parameter is purposefully
   *   not typed in the constructor to allow BC.
   */
  public function __construct($environment) {
    $this->environment = $environment;
  }

  /**
   * {@inheritdoc}
   */
  public function render(AbstractInline $inline, ElementRendererInterface $htmlRenderer) {
    if (!($inline instanceof Link)) {
      throw new \InvalidArgumentException('Incompatible inline type: ' . get_class($inline));
    }

    $attributes = $inline->getData('attributes', []);
    $external = $inline->getData('external');
    $attributes['href'] = $inline->getUrl();

    $options = [
      'nofollow'   => $this->environment->getConfig('external_link/nofollow', ''),
      'noopener'   => $this->environment->getConfig('external_link/noopener', 'external'),
      'noreferrer' => $this->environment->getConfig('external_link/noreferrer', 'external'),
    ];

    // Determine which rel attributes to set.
    $rel = [];
    foreach ($options as $type => $option) {
      switch (TRUE) {
        case $option === 'all':
        case $external && $option === 'external':
        case !$external && $option === 'internal':
          $rel[] = $type;
      }
    }

    // Set the rel attribute.
    if ($rel) {
      $attributes['rel'] = \implode(' ', $rel);
    }
    // Otherwise, unset whatever CommonMark set from the extension.
    else {
      unset($attributes['rel']);
    }

    return new HtmlElement('a', $attributes, $htmlRenderer->renderInlines($inline->children()));
  }

}
