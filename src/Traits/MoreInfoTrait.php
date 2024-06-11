<?php

namespace Drupal\markdown\Traits;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;

/**
 * Trait for appending content with "[More Info]" links.
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
trait MoreInfoTrait {

  use RendererTrait;

  /**
   * Appends existing content with a "More Info" link.
   *
   * @param mixed $existing
   *   The existing content to append to.
   * @param string|\Drupal\Core\Url $url
   *   The URL to use.
   * @param string $label
   *   Optional. The "More Info" label to use for the link.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The new joined content.
   */
  protected function moreInfo($existing, $url, $label = 'More Info') {
    if (!$url) {
      return $existing;
    }
    if (!($url instanceof Url)) {
      $url = UrlHelper::isExternal($url) ? Url::fromUri($url)->setOption('attributes', ['target' => '_blank']) : Url::fromUserInput($url);
    }
    if (!($label instanceof MarkupInterface)) {
      $label = $this->t($label); // phpcs:ignore
    }
    $build = [
      '#type' => 'link',
      '#title' => new FormattableMarkup('[@label]', [
        '@label' => $label,
      ]),
      '#url' => $url,
      '#options' => [
        'attributes' => [
          'class' => ['installable-more-info'],
          'title' => $label,
        ],
      ],
      '#prefix' => ' ',
    ];
    $moreInfo = $this->renderer()->renderPlain($build);
    if (empty($existing) || empty(trim($existing))) {
      return $moreInfo;
    }
    return new FormattableMarkup('@existing @moreInfo', [
      '@existing' => $existing,
      '@moreInfo' => $moreInfo,
    ]);
  }

}
