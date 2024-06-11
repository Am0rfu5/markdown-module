<?php

namespace Drupal\markdown\Traits;

use Drupal\markdown\Plugin\Markdown\ParserInterface;

/**
 * Trait for implementing \Drupal\markdown\Util\ParserAwareInterface.
 */
trait ParserAwareTrait {

  /**
   * A Markdown Parser instance.
   *
   * @var \Drupal\markdown\Plugin\Markdown\ParserInterface
   */
  protected $parser;

  /**
   * {@inheritdoc}
   */
  public function getParser() {
    return $this->parser;
  }

  /**
   * {@inheritdoc}
   */
  public function setParser(ParserInterface $parser = NULL) {
    $this->parser = $parser;
    return $this;
  }

}
