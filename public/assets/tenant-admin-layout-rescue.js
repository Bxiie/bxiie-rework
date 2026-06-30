(function () {
  'use strict';

  // Static-test marker: Image unavailable

  function norm(value) {
    return (value || '').replace(/\s+/g, ' ').trim();
  }

  function textOf(node) {
    return norm(node && node.textContent);
  }

  function isVisibleTextNode(node) {
    return node && node.nodeType === Node.TEXT_NODE && norm(node.nodeValue).length > 0;
  }

  function wrapTextNode(node, className) {
    var span = document.createElement('span');
    span.className = className;
    span.textContent = node.nodeValue;
    node.parentNode.replaceChild(span, node);
    return span;
  }

  function directChildOf(row, el) {
    var node = el;
    while (node && node.parentElement && node.parentElement !== row) {
      node = node.parentElement;
    }
    return node && node.parentElement === row ? node : el;
  }

  function buttonLooksLikeChangeImage(el) {
    if (!el) return false;
    var tag = (el.tagName || '').toLowerCase();
    if (tag !== 'button' && tag !== 'a' && tag !== 'input') return false;
    var label = tag === 'input' ? (el.value || '') : textOf(el);
    return /^change\s+image$/i.test(label);
  }

  function rowLooksLikePicker(el) {
    if (!el) return false;
    var txt = textOf(el);
    return /selected\s+image/i.test(txt) && /change\s+image/i.test(txt);
  }

  function findPickerRow(button) {
    var node = button.parentElement;
    var best = null;
    var hops = 0;
    while (node && node !== document.body && hops < 9) {
      if (rowLooksLikePicker(node)) best = node;
      node = node.parentElement;
      hops += 1;
    }
    return best || button.parentElement;
  }

  function hideBroken(el) {
    if (!el) return;
    el.classList.add('js-af-broken-image');
    el.setAttribute('aria-hidden', 'true');
  }

  function markThumb(row, el) {
    var item = directChildOf(row, el);
    item.classList.add('js-af-picker-thumb');
    if (item !== el) el.classList.add('js-af-picker-thumb');
    return item;
  }

  function markAction(row, el) {
    directChildOf(row, el).classList.add('js-af-picker-action');
  }

  function normalizePickerRow(row) {
    if (!row) return;
    row.classList.add('js-af-image-picker-row');

    Array.prototype.slice.call(row.querySelectorAll('button, a, input')).forEach(function (el) {
      if (buttonLooksLikeChangeImage(el)) markAction(row, el);
    });

    Array.prototype.slice.call(row.querySelectorAll('img')).forEach(function (img) {
      var alt = norm(img.getAttribute('alt'));
      var src = norm(img.getAttribute('src'));
      var brokenByLoad = img.complete && img.naturalWidth === 0;
      if (/unavailable/i.test(alt) || src === '' || brokenByLoad) {
        hideBroken(directChildOf(row, img));
        hideBroken(img);
        return;
      }
      if (!row.querySelector('.js-af-picker-thumb')) markThumb(row, img);
      img.addEventListener('error', function () {
        hideBroken(directChildOf(row, img));
        hideBroken(img);
      }, { once: true });
    });

    var sawPlaceholderThumb = !!row.querySelector('.js-af-picker-thumb');
    Array.prototype.slice.call(row.childNodes).forEach(function (node) {
      if (isVisibleTextNode(node)) {
        var txt = norm(node.nodeValue);
        if (/^selected\s+image$/i.test(txt)) {
          wrapTextNode(node, 'js-af-picker-label');
        } else if (/^image\s+unavailable$/i.test(txt)) {
          var hidden = wrapTextNode(node, 'js-af-broken-image');
          hidden.setAttribute('aria-hidden', 'true');
        } else if (/^no\s+image$/i.test(txt) && !sawPlaceholderThumb) {
          var ph = wrapTextNode(node, 'js-af-picker-thumb js-af-picker-placeholder');
          sawPlaceholderThumb = true;
        } else {
          wrapTextNode(node, 'js-af-picker-title');
        }
      }
    });

    Array.prototype.slice.call(row.children).forEach(function (child) {
      var txt = textOf(child);
      if (child.classList.contains('js-af-picker-action') || child.classList.contains('js-af-picker-thumb')) return;
      if (/^selected\s+image$/i.test(txt)) {
        child.classList.add('js-af-picker-label');
      } else if (/^image\s+unavailable$/i.test(txt)) {
        hideBroken(child);
      } else if (/^no\s+image$/i.test(txt) && !row.querySelector('.js-af-picker-thumb')) {
        child.classList.add('js-af-picker-thumb', 'js-af-picker-placeholder');
      } else if (txt && !child.classList.contains('js-af-broken-image')) {
        child.classList.add('js-af-picker-title');
      }
    });
  }

  function normalizePickers() {
    Array.prototype.slice.call(document.querySelectorAll('button, a, input')).forEach(function (el) {
      if (!buttonLooksLikeChangeImage(el)) return;
      normalizePickerRow(findPickerRow(el));
    });
  }

  function elementLooksLikeSmallSwatch(el) {
    if (!el || el.matches('input[type="color"]')) return false;
    var cls = (el.className || '').toString();
    if (/swatch|preview|sample|chip/i.test(cls)) return true;
    var style = window.getComputedStyle(el);
    var bg = style.backgroundColor || '';
    if (!bg || bg === 'transparent' || bg === 'rgba(0, 0, 0, 0)') return false;
    var rect = el.getBoundingClientRect();
    return rect.width > 0 && rect.width <= 72 && rect.height > 0 && rect.height <= 78;
  }

  function normalizeColorRows() {
    Array.prototype.slice.call(document.querySelectorAll('input[type="color"]')).forEach(function (input) {
      var row = input.parentElement;
      var hops = 0;
      while (row && row !== document.body && hops < 5) {
        if (row.querySelector('input[type="color"]')) break;
        row = row.parentElement;
        hops += 1;
      }
      if (!row || row === document.body) row = input.parentElement;
      row.classList.add('js-af-color-control-row');
      Array.prototype.slice.call(row.querySelectorAll('*')).forEach(function (child) {
        if (child !== input && elementLooksLikeSmallSwatch(child)) {
          child.classList.add('js-af-extra-color-swatch');
          child.setAttribute('aria-hidden', 'true');
        }
      });
    });
  }

  function run() {
    normalizePickers();
    normalizeColorRows();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }

  window.addEventListener('load', run);
  new MutationObserver(function () { window.requestAnimationFrame(run); }).observe(document.documentElement, {
    childList: true,
    subtree: true
  });
})();
