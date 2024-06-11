(function (Drupal) {

  if (!Element.prototype.matches) {
    Element.prototype.matches = Element.prototype.msMatchesSelector ||
        Element.prototype.webkitMatchesSelector;
  }

  if (!Element.prototype.closest) {
    Element.prototype.closest = function (s) {
      var el = this;

      do {
        if (Element.prototype.matches.call(el, s)) {
          return el;
        }
        el = el.parentElement || el.parentNode;
      } while (el !== null && el.nodeType === 1);
      return null;
    };
  }

  Drupal.markdown = Drupal.markdown || {
    dispatchEvent: function dispatchEvent(element, type) {
      var event = document.createEvent('HTMLEvents');
      event.initEvent(type, true, false);
      element.dispatchEvent(event);
    },
    throwError: function throwError(error) {
      setTimeout(function () {
        throw new Error('[markdown]' + error);
      });
    }
  }

})(window.Drupal);
