(function (Drupal) {

  var Reset = function Reset(id, element, target, defaultValue) {
    this.id = id;
    this.element = element;
    this.clickHandler = this.reset.bind(this);
    this.element.addEventListener('click', this.clickHandler);
    this.visible = false;

    // Find target.
    if (!target) {
      return this.destroy('Target not provided.');
    }
    try {
      // Remove any jQuery ":input" from the selector.
      target = target.replace(/:input/g, '');
      this.target = this.element.closest('.js-form-item').querySelector(target);
    }
    catch (e) {
      return this.destroy('Unable to find target: "' + target + '"');
    }

    if (defaultValue === void 0) {
      return this.destroy('Default value not provided.');
    }
    try {
      this.defaultValue = JSON.parse(defaultValue);
    }
    catch (e) {
      this.defaultValue = defaultValue;
    }

    this.updateHandler = this.update.bind(this);
    this.observer = new MutationObserver(this.updateHandler);
    this.observer.observe(this.target, {
      attributes: true,
      characterData: true,
      childList: true,
      subtree: true
    });

    this.target.addEventListener('change', this.updateHandler);
    this.target.addEventListener('keydown', this.updateHandler);
    this.target.addEventListener('keyup', this.updateHandler);

    this.update();
  };

  Reset.prototype.destroy = function destroy(error) {
    if (this.target) {
      this.target.removeEventListener('change', this.updateHandler);
      this.target.removeEventListener('keydown', this.updateHandler);
      this.target.removeEventListener('keyup', this.updateHandler);
    }
    if (this.observer) {
      this.observer.disconnect();
      this.observer = null;
    }
    if (this.element) {
      this.element.removeEventListener('click', this.clickHandler);
      this.element.parentElement.removeChild(this.element);
    }
    if (error) {
      Drupal.markdown.throwError('[markdown-id:' + this.id + '] ' + error);
    }
  };

  Reset.prototype.isDefaultValue = function isDefaultValue() {
    switch (this.target.type) {
      case 'checkbox':
        return this.target.checked === this.defaultValue;

      default:
        try {
          return JSON.parse(this.target.value) === this.defaultValue;
        }
        catch (e) {
          return this.target.value === this.defaultValue;
        }
    }
  };

  Reset.prototype.reset = function reset(e) {
    e.preventDefault();
    e.stopImmediatePropagation();

    if (this.target.type === 'checkbox') {
      this.target.checked = this.defaultValue;
    }
    else {
      switch (this.target.nodeName.toLowerCase()) {
        case 'radio':
        case 'select':
          var option = this.target.querySelector(this.defaultValue ? '[value=' + this.defaultValue + ']' : '[value]');
          if (option) {
            option.selected = true;
          }
          break;

        default:
          this.target.value = this.defaultValue;
      }
    }

    Drupal.markdown.dispatchEvent(this.target, 'change');
    if (typeof this.target.focus === 'function') {
      this.target.focus();
    }
  };

  Reset.prototype.update = function update() {
    this.visible = !this.target.disabled && !this.isDefaultValue();
    this.element.style.display = this.visible ? '' : 'none';
  };

  Drupal.behaviors.markdownReset = {
    attach: function attach(context) {
      context.querySelectorAll('[data-markdown-element="reset"]').forEach(function (element) {
        if (element.__markdownReset__) {
          return;
        }
        var id = element.dataset.markdownId || element.name || element.id;
        var target = element.dataset.markdownTarget;
        var defaultValue = element.dataset.markdownDefaultValue;
        element.__markdownReset__ = new Reset(id, element, target, defaultValue);
      });
    },
    detach: function detach(context) {
      context.querySelectorAll('[data-markdown-element="reset"]').forEach(function (element) {
        if (element.__markdownReset__) {
          element.__markdownReset__.destroy();
        }
      });
    }
  };

})(window.Drupal);
