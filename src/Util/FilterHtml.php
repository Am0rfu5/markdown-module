<?php

namespace Drupal\markdown\Util;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\filter\Plugin\Filter\FilterHtml as CoreFilterHtml;
use Drupal\markdown\Plugin\Markdown\ParserInterface;
use Drupal\markdown\PluginManager\AllowedHtmlManager;
use Drupal\markdown\Traits\ParserAwareTrait;

/**
 * Extends FilterHtml to allow more more permissive global attributes.
 */
class FilterHtml extends CoreFilterHtml implements ParserAwareInterface {

  use ParserAwareTrait;

  /**
   * The placeholder value used to protect asterisks in values.
   *
   * @var string
   */
  const ASTERISK_PLACEHOLDER = '__zqh6vxfbk3cg__';

  /**
   * Creates a new instance.
   *
   * @param string $allowedHtml
   *   Optional. The allowed HTML.
   *
   * @return static
   */
  public static function create($allowedHtml = '') {
    return new static([
      'settings' => [
        'allowed_html' => $allowedHtml,
        'filter_html_help' => 1,
        'filter_html_nofollow' => 0,
      ],
    ], 'filter_html', ['provider' => 'markdown']);
  }

  /**
   * Create a new instance from a Markdown Parser instance.
   *
   * @param \Drupal\markdown\Plugin\Markdown\ParserInterface $parser
   *   A Markdown Parser instance.
   *
   * @return static
   */
  public static function fromParser(ParserInterface $parser) {
    return static::create($parser->getCustomAllowedHtml())->setParser($parser);
  }

  /**
   * Merges allowed HTML tags.
   *
   * @param array $normalizedTags
   *   An existing normalized allowed HTML tags array.
   * @param array ...$tags
   *   One or more arrays of allowed HTML tags to merge onto $normalizedTags.
   *
   * @return array
   *   The merged $normalizedTags.
   */
  public static function mergeAllowedTags(array $normalizedTags, array $tags) {
    $args = func_get_args();
    $normalizedTags = array_shift($args);
    foreach ($args as $tags) {
      if (!is_array($tags) || !$tags) {
        continue;
      }
      // Normalize the tags to merge.
      $tags = static::normalizeTags($tags);
      foreach ($tags as $tag => $attributes) {
        // Add tag if it doesn't already exist.
        if (!isset($normalizedTags[$tag])) {
          $normalizedTags[$tag] = $attributes;
          continue;
        }

        // Existing tag already allows all attributes, skip merge.
        if (!empty($normalizedTags[$tag]['*'])) {
          continue;
        }

        // New tag allows all attributes, replace existing tag.
        if (!empty($attributes['*'])) {
          $normalizedTags[$tag] = ['*' => TRUE];
          continue;
        }

        // Now merge in individual attributes from tag.
        foreach ($attributes as $name => $value) {
          // Add attribute if it doesn't already exist.
          if (!isset($normalizedTags[$tag][$name])) {
            $normalizedTags[$tag][$name] = $value;
            continue;
          }

          // Existing tag attribute already allows all values, skip merge.
          if ($normalizedTags[$tag][$name] === TRUE) {
            continue;
          }

          // New tag attribute allows all values, replace existing attribute.
          if ($value === TRUE) {
            $normalizedTags[$tag][$name] = $value;
            continue;
          }

          // Finally, if specific attribute values are specified, merge them.
          if (is_array($value)) {
            if (!is_array($normalizedTags[$tag][$name])) {
              $normalizedTags[$tag][$name] = [];
            }
            $normalizedTags[$tag][$name] = array_replace($normalizedTags[$tag][$name], $value);
          }
        }
      }
    }
    ksort($normalizedTags);
    return $normalizedTags;
  }

  /**
   * Normalizes allowed HTML tags.
   *
   * @param array $tags
   *   The tags to normalize.
   *
   * @return array
   *   The normalized allowed HTML tags.
   */
  public static function normalizeTags(array $tags) {
    $tags = array_map(function ($attributes) {
      if (is_array($attributes)) {
        foreach ($attributes as $name => $value) {
          if (!is_bool($value)) {
            $attributes[$name] = is_array($value) ? $value : [$value => TRUE];
          }
        }
        return $attributes;
      }
      return $attributes === FALSE ? [] : ['*' => TRUE];
    }, $tags);
    ksort($tags);
    return $tags;
  }

  /**
   * Extracts HTML tags (and attributes) from a DOMNode.
   *
   * @param \DOMNode $node
   *   The node to extract from.
   * @param bool $attributeNames
   *   Flag indicating whether to extract attribute names.
   * @param bool $attributeValues
   *   Flag indicating whether to extract attribute values.
   *
   * @return array
   *   The HTML tags extracted from the DOM node.
   */
  protected static function extractDomNodeTags(\DOMNode $node, $attributeNames = TRUE, $attributeValues = FALSE) {
    $tags = [];
    if (!isset($tags[$node->nodeName])) {
      $tags[$node->nodeName] = [];
    }
    if ($attributeNames && $node->attributes) {
      for ($i = 0, $l = $node->attributes->length; $i < $l; ++$i) {
        $attribute = $node->attributes->item($i);
        $name = $attribute->name;
        $tags[$node->nodeName][$name] = $attributeValues ? $attribute->nodeValue : TRUE;
      }
      if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $childNode) {
          $tags = NestedArray::mergeDeep($tags, static::extractDomNodeTags($childNode));
        }
      }
    }
    return $tags;
  }

  /**
   * Extracts an array of tags (and attributes) from an HTML string.
   *
   * @param string $html
   *   The HTML string to extract tags and attributes from.
   * @param bool $attributeNames
   *   Flag indicating whether to extract attribute names.
   * @param bool $attributeValues
   *   Flag indicating whether to extract attribute values.
   *
   * @return array
   *   The HTML tags extracted from the HTML string.
   */
  public static function tagsFromHtml($html = NULL, $attributeNames = TRUE, $attributeValues = FALSE) {
    $tags = [];
    if (!$html || strpos($html, '<') === FALSE) {
      return $tags;
    }
    libxml_use_internal_errors(TRUE);
    $dom = new \DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();
    foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $childNode) {
      $tags = NestedArray::mergeDeep($tags, static::extractDomNodeTags($childNode, $attributeNames, $attributeValues));
    }
    return $tags;
  }

  /**
   * Converts an array of tags (and their potential attributes) to a string.
   *
   * @param array $tags
   *   An associative array of tags, where the key is the tag and the value can
   *   be a boolean (TRUE if allowed, FALSE otherwise) or an associative array
   *   containing key/value pairs of acceptable boolean based attribute values
   *   (i.e. 'dir' => ['ltr' => TRUE, 'rtl' => TRUE]).
   *
   * @return string
   *   The tags, in string format.
   */
  public static function tagsToString(array $tags = []) {
    $items = [];
    ksort($tags);
    foreach (static::normalizeTags($tags) as $tag => $attributes) {
      $tag = "<$tag";
      if (is_array($attributes)) {
        foreach ($attributes as $attribute => $value) {
          if (!$value) {
            continue;
          }
          $tag .= " $attribute";
          if ($value && $value !== TRUE) {
            if (is_array($value)) {
              $value = implode(' ', array_keys(array_filter($value)));
            }
            $tag .= "='$value'";
          }
        }
      }
      $tag .= '>';
      $items[] = $tag;
    }
    return implode(' ', $items);
  }

  /**
   * Retrieves the allowed HTML.
   *
   * @param bool $includeGlobal
   *   Flag indicating whether to include global elements (i.e. *).
   *
   * @return string
   *   The allowed HTML.
   */
  public function getAllowedHtml($includeGlobal = TRUE) {
    $restrictions = $this->getHtmlRestrictions();
    if (!$includeGlobal) {
      unset($restrictions['allowed']['*']);
    }
    return static::tagsToString($restrictions['allowed']);
  }

  /**
   * Retrieves an array of allowed HTML tags.
   *
   * @return string[]
   *   An indexed array of allowed HTML tags.
   */
  public function getAllowedTags() {
    $restrictions = $this->getHtmlRestrictions();
    // Split the work into two parts. For filtering HTML tags out of the content
    // we rely on the well-tested Xss::filter() code. Since there is no '*' tag
    // that needs to be removed from the list.
    unset($restrictions['allowed']['*']);
    return array_keys($restrictions['allowed']);
  }

  /**
   * {@inheritdoc}
   */
  public function getHTMLRestrictions() { // phpcs:ignore
    if ($this->restrictions) {
      return $this->restrictions;
    }

    $activeTheme = \Drupal::theme()->getActiveTheme();
    $parser = $this->getParser();
    $allowedHtmlPlugins = $parser ? AllowedHtmlManager::create()->appliesTo($parser, $activeTheme) : [];
    $cacheTags = $parser ? $parser->getCacheTags() : [];
    $cid = 'markdown_allowed_html:' . Crypt::hashBase64(serialize(array_merge($cacheTags, $allowedHtmlPlugins)));

    // Return cached HTML restrictions.
    $discoveryCache = \Drupal::cache('discovery');
    if (($cached = $discoveryCache->get($cid)) && !empty($cached->data)) {
      $this->restrictions = $cached->data;
      return $this->restrictions;
    }

    $restrictions = parent::getHTMLRestrictions();

    // Save the original global attributes.
    $originalGlobalAttributes = $restrictions['allowed']['*'];
    unset($restrictions['allowed']['*']);

    // Determine if any user global attributes where provided (from a filter).
    $addedGlobalAttributes = [];
    if (isset($restrictions['allowed'][static::ASTERISK_PLACEHOLDER])) {
      $addedGlobalAttributes['*'] = $restrictions['allowed'][static::ASTERISK_PLACEHOLDER];
      $addedGlobalAttributes = static::normalizeTags($addedGlobalAttributes);
      unset($restrictions['allowed'][static::ASTERISK_PLACEHOLDER]);
    }

    // Normalize the allowed tags.
    $normalizedTags = static::normalizeTags($restrictions['allowed']);

    // Merge in plugins allowed HTML tags.
    foreach ($allowedHtmlPlugins as $plugin_id => $allowedHtml) {
      // Retrieve the plugin's allowed HTML tags.
      $tags = $allowedHtml->allowedHtmlTags($parser, $activeTheme);

      // Merge the plugin's global attributes with the user provided ones.
      if (isset($tags['*'])) {
        $addedGlobalAttributes = static::mergeAllowedTags($addedGlobalAttributes, ['*' => $tags['*']]);
        unset($tags['*']);
      }

      // Now merge the plugin's tags with the allowed HTML.
      $normalizedTags = static::mergeAllowedTags($normalizedTags, $tags);
    }

    // Replace the allowed tags with the normalized/merged tags.
    $restrictions['allowed'] = $normalizedTags;

    // Restore the original global attributes.
    $restrictions['allowed']['*'] = $originalGlobalAttributes;

    // Now merge the added global attributes using the array union (+) operator.
    // This ensures that the original core defined global attributes are never
    // overridden so users cannot specify attributes like 'style' and 'on*'
    // which are highly vulnerable to XSS.
    if (!empty($addedGlobalAttributes['*'])) {
      $restrictions['allowed']['*'] += $addedGlobalAttributes['*'];
    }

    $discoveryCache->set($cid, $restrictions, CacheBackendInterface::CACHE_PERMANENT, $cacheTags);

    $this->restrictions = $restrictions;

    return $restrictions;
  }

}
