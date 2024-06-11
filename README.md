> Provides Markdown integration for Drupal and allows content to be formatted
> in a simple plain-text syntax that is transformed into valid HTML.

The Markdown syntax is designed to co-exist with HTML, so you can set
up input formats with both HTML and Markdown support. It is also meant
to be as human-readable as possible when left as "source".

There is current an issue open to make the [CommonMark Spec](http://commonmark.org)
the "official" [Drupal Coding Standard](https://www.drupal.org/project/coding_standards/issues/2952616).


## Requirements

- **PHP >= 5.5.9**:
  
  This is the minimum PHP version for Drupal 8.0.0. Actual
  minimum PHP version depends on which Drupal core version and which markdown
  parser you install.

- **A Markdown parser**:
  
  While there are several types of PHP Markdown parsing libraries out
  there, this module's primary purpose is to bridge the gap between these
  libraries and Drupal by offering an integration wrapper around them.
  Currently, this module supports the following parsers out-of-the-box:
    
  - **CommonMark and CommonMark GFM (highly recommended, [league/commonmark](https://github.com/thephpleague/commonmark)):**
    ```
    composer require league/commonmark
    ```
  - Parsedown ([erusev/parsedown](https://github.com/erusev/parsedown)):
    ```
    composer require erusev/parsedown
    ```
  - Parsedown Extra ([erusev/parsedown-extra](https://github.com/erusev/parsedown-extra)):
    ```
    composer require erusev/parsedown-extra
    ```
  - PHP Markdown and PHP Markdown Extra ([michelf/php-markdown](https://github.com/michelf/php-markdown)):
    ```
    composer require michelf/php-markdown
    ```
  - PHP/PECL Commonmark Extension ([ext-cmark](https://pecl.php.net/package/cmark))
    ```
    pecl install cmark
    ```


## Installation

- Follow the standard Drupal installation instructions:
  
  https://www.drupal.org/docs/extending-drupal/installing-modules

- Choose a Markdown parser from above and install it via Composer:
  
  https://getcomposer.org/doc/

- Enable and edit your global (default/site-wide) Markdown parser in your site:

  `/admin/config/content/markdown`

- Optionally, create a new text format or edit an existing one to add the
  `Markdown` filter to it where you can choose to inherit the site-wide
  settings or override the settings for that particular text format:
  
  `/admin/config/content/formats`
  
  > Note: Markdown may conflict with other text format filters, depending on
  the order in which filters are configured to apply. If unexpected markup
  occurs when the text format is configured with other filters, experiment
  with the order of the filters to attempt to resolve any issues.
  >
  > The "Limit allowed HTML tags and correct faulty HTML" filter is intentionally
  disabled by default because this module duplicates the same
  functionality use its own:
  > [Render Strategy](https://www.drupal.org/docs/contributed-modules/markdown/parsers/render-strategy).


## Documentation

Extensive documentation for this module can be found at:

<https://www.drupal.org/docs/contributed-modules/markdown>


## Editor.md

If you are interested in a Markdown editor please check out the
[Editor.md](https://drupal.org/project/editor_md) module for Drupal.
