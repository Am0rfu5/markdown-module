(function ($, Drupal) {
  var $document = $(document);

  var overviewForm = document.querySelector('form[data-drupal-selector="markdown-overview"]');
  if (overviewForm && !overviewForm.jsProcessed) {
    overviewForm.jsProcessed = true;
    var submit = overviewForm.querySelector('input[name="op"],button[name="op"]');
    var weights = overviewForm.querySelectorAll('input.parser-weight');
    if (submit && weights.length) {
      submit.originalDisplay = submit.style.display;
      submit.style.display = 'none';
      var resetSubmit = function () {
        submit.style.display = submit.originalDisplay;
      }
      weights.forEach(function (weight) {
        weight.addEventListener('change', resetSubmit);
      });
      if (Drupal.tableDrag) {
        var onDrop = Drupal.tableDrag.prototype.onDrop;
        Drupal.tableDrag.prototype.onDrop = function () {
          onDrop.apply(this);
          if (this.changed) {
            resetSubmit();
          }
        }
      }
    }
  }

  // @todo Extract input history/dependents into its own library.
  var savePreviousInput = function (input) {
    var change = false;
    var $input = $(input);
    if ($input.is('[type="checkbox"]')) {
      if ($input.data('originalChecked') === void 0) {
        $input.data('originalChecked', $input.prop('checked'));
        change = true;
      }
    }
    else if ($input.data('originalValue') === void 0) {
      $input.data('originalValue', $input.val());
      change = true;
    }
    if ($input.data('originalDisabled') === void 0) {
      $input.data('originalDisabled', $input.prop('disabled'));
      change = true;
    }
    if (change) {
      $input.trigger('change');
    }
  }

  var restorePreviousInput = function (input) {
    var change = false;
    var $input = $(input);
    if ($input.is('[type="checkbox"]') && $input.data('originalChecked') !== void 0) {
      $input.prop('checked', $input.data('originalChecked'));
      $input.removeData('originalChecked');
      change = true;
    }
    else if ($input.data('originalValue') !== void 0) {
      $input.val('checked', $input.data('originalValue'));
      $input.removeData('originalValue');
      change = true;
    }
    if ($input.data('originalDisabled') !== void 0) {
      $input.prop('disabled', $input.data('originalDisabled'))
      $input.removeData('originalDisabled');
      change = true;
    }
    if (change) {
      $input.trigger('change');
    }
    return change;
  }

  $document
      .off('state:checked')
      .on('state:checked', function (e) {
        if (e.trigger && e.target) {
          var $target = $(e.target);
          var defaultValue = $target.data('markdownDefaultValue');

          // Act normally if there is not default value provided.
          if (defaultValue === void 0) {
            $target.prop('checked', e.value);
            return;
          }

          // Handle checked state so its default value is restored, not
          // automatically "checked" because its state says to.
          var states = $(e.target).data('drupalStates') || {};
          if ((states['!checked'] && e.value) || states['checked'] && !e.value) {
            if (!restorePreviousInput(e.target)) {
              $target.prop('checked', defaultValue);
            }
          }
          else {
            savePreviousInput(e.target);
            $target.prop('checked', e.value);
          }
        }
      });

  Drupal.behaviors.markdownSummary = {
    attach: function attach(context) {
      var $context = $(context);

      var $wrapper = $context.find('[data-markdown-element="wrapper"]');
      // 
      $wrapper.once('markdown-summary').each(function () {
        // Vertical tab summaries.
        var $inputs = $(this).find(':input[data-markdown-summary]');
        $inputs.each(function () {
          var $input = $(this);
          var summaryType = $input.data('markdownSummary');
          var $item = $input.closest('.js-vertical-tabs-pane,.vertical-tabs__pane');
          var verticalTab = $item.data('verticalTab');
          if (verticalTab) {
            $input.on('click.markdownSummary', function () {
              verticalTab.updateSummary();
            });

            verticalTab.details.drupalSetSummary(function () {
              var summary = [];
              switch (summaryType) {
                case 'parser':
                  if ($input[0].nodeName === 'SELECT') {
                    var $selected = $input.find(':selected:first');
                    if ($selected[0]) {
                      var parser = $selected.text();
                      if (/^site:/.test($selected.val())) {
                        summary.push(Drupal.t('Site-wide') + ' ' + parser);
                      }
                      else {
                        summary.push(parser);
                      }
                    }
                  }
                  else {
                    var parser = $input.data('markdownSummaryValue') || $input.val();
                    summary.push(parser)
                  }
                  break;

                case 'render_strategy':
                  var $selected = $input.find(':selected:first');
                  var renderStrategy = $selected.text();
                  if ($selected.val() === 'filter') {
                    var $allowedHtml = $item.find('[data-markdown-element="allowed_html"]');
                    var $reset = $item.find('[data-markdown-element="allowed_html_reset"]');
                    var defaultValue = allowedHtmlDefaultValue($reset);
                    if (defaultValue && $allowedHtml.val() !== defaultValue) {
                      renderStrategy += ' (' + Drupal.t('overridden') + ')';
                    }
                  }
                  summary.push(renderStrategy);
                  break;

                case 'extension':
                  var $parent = $input.parent();
                  var labelSelector = 'label[for="' + $input.attr('id') + '"]';
                  var $label = $parent.is(labelSelector) ? $parent : $parent.find(labelSelector);
                  if (!$label.data('original-label')) {
                    $label.data('original-label', $label.html());
                  }
                  var originalLabel = $label.data('original-label') || Drupal.t('Enable');
                  var variables = {'@label': originalLabel};

                  if (!$input.data('markdownInstalled')) {
                    $label.html(Drupal.t('@label (not installed)', variables))
                    summary.push(Drupal.t('Not Installed'))
                    if (verticalTab.item && verticalTab.item[0]) {
                      verticalTab.item[0].classList.add('installable-library-not-installed')
                    }
                  }
                  else {
                    var enabled = false;
                    var bundle = $input.data('markdownBundle');
                    var requiredBy = [].concat($input.data('markdownRequiredBy')).map(function (id) {
                      var $dependent = $inputs.filter('[data-markdown-element="extension"][data-markdown-id="' + id + '"]');
                      if ($dependent[0]) {
                        return $dependent.is(':checked') ? $dependent.data('markdownLabel') : '';
                      }
                    }).filter(Boolean);
                    if (requiredBy.length) {
                      variables['@extensions'] = requiredBy.join(', ');
                      $label.html(Drupal.t('@label (required by: @extensions)', variables))
                      summary.push(Drupal.t('Required by: @extensions', variables));
                      savePreviousInput($input);
                      enabled = true;
                      $input.prop('checked', true);
                      $input.prop('disabled', true);
                    }
                    else if (bundle) {
                      variables['@bundle'] = bundle;
                      $label.html(Drupal.t('@label (required by: @bundle)', variables))
                      summary.push(Drupal.t('Required by: @bundle', variables));
                      savePreviousInput($input);
                      enabled = true;
                      $input.prop('checked', true);
                      $input.prop('disabled', true);
                    }
                    else {
                      $label.html(originalLabel);
                      restorePreviousInput($input);
                      enabled = $input.is(':checked');
                      summary.push(enabled ? Drupal.t('Enabled') : Drupal.t('Disabled'));
                    }

                    if (verticalTab.item && verticalTab.item[0]) {
                      verticalTab.item[0].classList.remove('installable-library-enabled', 'installable-library-disabled')
                      verticalTab.item[0].classList.add(enabled ? 'installable-library-enabled' : 'installable-library-disabled')
                    }

                    // Trigger requirement summary updates.
                    [].concat($input.data('markdownRequires')).map(function (id) {
                      var $requirement = $inputs.filter('[data-markdown-element="extension"][data-markdown-id="' + id + '"]');
                      if ($requirement[0]) {
                        setTimeout(function () {
                          $requirement.triggerHandler('click.markdownSummary');
                          $requirement.trigger('change');
                        }, 10);
                      }
                    });
                  }
                  break;
              }
              return summary.join(', ');
            });

            verticalTab.updateSummary();
          }
        });
      });
    }
  };
})(jQuery, Drupal);
