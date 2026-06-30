/* artsfolio-content-colors-bg-layout-rescue-v15 */
(function () {
  function txt(n) { return (n && n.textContent || '').replace(/\s+/g, ' ').trim(); }
  function hide(n) { if (n && n.style) n.style.setProperty('display', 'none', 'important'); }

  function normalizePickerRows() {
    var buttons = Array.prototype.slice.call(document.querySelectorAll('button, a'))
      .filter(function (el) { return /change\s+image/i.test(txt(el)); });

    buttons.forEach(function (button) {
      var row = button.parentElement;
      for (var i = 0; row && i < 5; i += 1) {
        var t = txt(row);
        if (/selected\s+image/i.test(t) || /no\s+image/i.test(t) || /published|unpublished/i.test(t)) break;
        row = row.parentElement;
      }
      if (!row) return;
      row.classList.add('tenant-selected-image-row');

      Array.prototype.slice.call(row.childNodes).forEach(function (node) {
        if (node.nodeType === 3) {
          var text = (node.nodeValue || '').replace(/\s+/g, ' ').trim();
          if (/^selected\s+image$/i.test(text) || /^image\s+unavailable$/i.test(text)) {
            node.nodeValue = '';
          }
        } else if (node.nodeType === 1) {
          var label = txt(node);
          var alt = (node.getAttribute && (node.getAttribute('alt') || '') || '');
          if (/^selected\s+image$/i.test(label) || /image\s+unavailable/i.test(label) || /image\s+unavailable/i.test(alt)) {
            hide(node);
          }
        }
      });
    });
  }

  function hideSmallColorSwatches() {
    Array.prototype.slice.call(document.querySelectorAll('input[type="color"]')).forEach(function (input) {
      var parent = input.parentElement;
      if (!parent) return;
      Array.prototype.slice.call(parent.children).forEach(function (child) {
        if (child === input) return;
        var r = child.getBoundingClientRect();
        var cs = window.getComputedStyle(child);
        if (r.width <= 42 && r.height <= 72 && /background|border/.test(cs.cssText || '')) hide(child);
        if (/swatch|preview|sample/i.test(child.className || '')) hide(child);
      });
    });
  }

  function run() {
    normalizePickerRows();
    hideSmallColorSwatches();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
  window.addEventListener('load', run);
})();
