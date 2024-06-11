<?php

namespace Drupal\markdown\Twig;

use Drupal\markdown\MarkdownInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig Markdown Extension.
 */
class Extension extends AbstractExtension implements GlobalsInterface {

  /**
   * An instance of a markdown processor to use.
   *
   * @var \Drupal\markdown\MarkdownInterface
   */
  protected $markdown;

  /**
   * {@inheritdoc}
   */
  public function __construct(MarkdownInterface $markdown) {
    $this->markdown = $markdown;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'markdown';
  }

  /**
   * {@inheritdoc}
   */
  public function getGlobals(): array {
    return [
      'markdown' => $this->markdown,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      'markdown' => new TwigFilter('markdown', [$this->markdown, 'parse'], ['is_safe' => ['html']]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      'markdown' => new TwigFunction('markdown', [$this->markdown, 'parse'], ['is_safe' => ['html']]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenParsers() {
    return [new TokenParser($this->markdown)];
  }

}
