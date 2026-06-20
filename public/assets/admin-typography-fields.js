/* Tenant typography live preview helper.
 *
 * The settings form stores tenant typography as CSS variables. This helper keeps
 * font previews live and turns each size field into a friendly pixel slider plus
 * number input. Hidden inputs keep the submitted values compatible with the
 * existing settings save path.
 */
(function () {
  'use strict';

  var FAMILY_TO_SIZE = {
    font_family_body: 'font_size_body',
    font_family_heading: 'font_size_heading',
    font_family_brand: 'font_size_brand',
    font_family_nav: 'font_size_nav',
    font_family_artwork_title: 'font_size_artwork_title',
    font_family_artwork_meta: 'font_size_artwork_meta',
    font_family_form: 'font_size_form',
    font_family_footer: 'font_size_footer'
  };

  var FAMILY_TO_VAR = {
    font_family_body: '--tenant-font-body',
    font_family_heading: '--tenant-font-heading',
    font_family_brand: '--tenant-font-brand',
    font_family_nav: '--tenant-font-nav',
    font_family_artwork_title: '--tenant-font-artwork-title',
    font_family_artwork_meta: '--tenant-font-artwork-meta',
    font_family_form: '--tenant-font-form',
    font_family_footer: '--tenant-font-footer'
  };

  var SIZE_TO_VAR = {
    font_size_body: '--tenant-font-size-body',
    font_size_heading: '--tenant-font-size-heading',
    font_size_subheading: '--tenant-font-size-subheading',
    font_size_brand: '--tenant-font-size-brand',
    font_size_nav: '--tenant-font-size-nav',
    font_size_prose: '--tenant-font-size-prose',
    font_size_artwork_title: '--tenant-font-size-artwork-title',
    font_size_artwork_meta: '--tenant-font-size-artwork-meta',
    font_size_form: '--tenant-font-size-form',
    font_size_footer: '--tenant-font-size-footer'
  };

  var SIZE_TO_PREVIEW = {
    font_size_body: 'font_size_body',
    font_size_heading: 'font_size_heading',
    font_size_subheading: 'font_size_heading',
    font_size_brand: 'font_size_brand',
    font_size_nav: 'font_size_nav',
    font_size_prose: 'font_size_body',
    font_size_artwork_title: 'font_size_artwork_title',
    font_size_artwork_meta: 'font_size_artwork_meta',
    font_size_form: 'font_size_form',
    font_size_footer: 'font_size_footer'
  };

  function closestLabel(node) {
    while (node && node !== document) {
      if (node.tagName && node.tagName.toLowerCase() === 'label') {
        return node;
      }
      node = node.parentNode;
    }
    return null;
  }

  function field(name) {
    return document.querySelector('[name="' + name + '"]');
  }

  function isSafePreviewSize(value) {
    return /^(?:[0-9]+(?:\.[0-9]+)?(?:px|rem|em|%)|clamp\([0-9.]+(?:px|rem|em|%),\s*[0-9.]+(?:vw|vh|rem|em|%),\s*[0-9.]+(?:px|rem|em|%)\))$/.test(String(value || '').trim());
  }

  function clamp(value, min, max) {
    var number = parseInt(value, 10);
    if (Number.isNaN(number)) {
      number = min;
    }
    return Math.max(min, Math.min(max, number));
  }

  function pxValue(name) {
    var hidden = document.querySelector('[data-font-size-value="' + name + '"]');
    if (hidden && hidden.value) {
      return hidden.value;
    }
    var input = field(name);
    return input ? input.value : '';
  }

  function sizeForFamily(familyFieldName) {
    var sizeFieldName = FAMILY_TO_SIZE[familyFieldName];
    return sizeFieldName ? pxValue(sizeFieldName) : '';
  }

  function previewForSize(sizeName) {
    var target = SIZE_TO_PREVIEW[sizeName] || sizeName;
    return document.querySelector('[data-font-preview="' + target + '"]');
  }

  function updateOnePreview(select) {
    if (!select || !select.name) {
      return;
    }

    var label = closestLabel(select);
    var preview = label ? label.querySelector('.font-picker-preview') : null;
    if (!preview) {
      return;
    }

    var family = select.value || '';
    var size = sizeForFamily(select.name);
    preview.style.fontFamily = family;
    if (isSafePreviewSize(size)) {
      preview.style.fontSize = size;
    }
    preview.textContent = select.options && select.selectedIndex >= 0
      ? select.options[select.selectedIndex].text + ' preview'
      : 'Font preview';
  }

  function syncOneSizeControl(name, source) {
    var range = document.querySelector('[data-font-size-range="' + name + '"]');
    var number = document.querySelector('[data-font-size-number="' + name + '"]');
    var hidden = document.querySelector('[data-font-size-value="' + name + '"]');
    var preview = previewForSize(name);

    if (!range || !number || !hidden) {
      return;
    }

    var min = parseInt(range.getAttribute('min') || number.getAttribute('min') || '8', 10);
    var max = parseInt(range.getAttribute('max') || number.getAttribute('max') || '160', 10);
    var rawValue = source && source.value ? source.value : (hidden.value || number.value || range.value);
    var value = clamp(rawValue, min, max);
    var cssValue = value + 'px';

    range.value = String(value);
    number.value = String(value);
    hidden.value = cssValue;

    if (preview) {
      preview.style.fontSize = cssValue;
    }
  }

  function syncSizeControls() {
    var controls = document.querySelectorAll('[data-font-size-control]');
    for (var index = 0; index < controls.length; index += 1) {
      syncOneSizeControl(controls[index].getAttribute('data-font-size-control'));
    }
  }

  function updateCssVariables() {
    var target = document.body || document.documentElement;

    Object.keys(FAMILY_TO_VAR).forEach(function (name) {
      var input = field(name);
      if (input && input.value) {
        target.style.setProperty(FAMILY_TO_VAR[name], input.value);
      }
    });

    Object.keys(SIZE_TO_VAR).forEach(function (name) {
      var value = pxValue(name);
      if (isSafePreviewSize(value)) {
        target.style.setProperty(SIZE_TO_VAR[name], value);
      }
    });
  }

  function refreshAll() {
    syncSizeControls();

    var selects = document.querySelectorAll('.tenant-font-picker');
    for (var index = 0; index < selects.length; index += 1) {
      updateOnePreview(selects[index]);
    }
    updateCssVariables();
  }

  function handleSizeInput(event) {
    var target = event.target;
    var name = target.getAttribute('data-font-size-range') || target.getAttribute('data-font-size-number');
    if (!name) {
      return;
    }

    syncOneSizeControl(name, target);
    updateCssVariables();
  }

  function bindSizeControls() {
    var fields = document.querySelectorAll('[data-font-size-range], [data-font-size-number]');
    for (var index = 0; index < fields.length; index += 1) {
      fields[index].addEventListener('input', handleSizeInput);
      fields[index].addEventListener('change', handleSizeInput);
    }
  }

  function boot() {
    var fields = document.querySelectorAll('.tenant-font-picker');
    for (var index = 0; index < fields.length; index += 1) {
      fields[index].addEventListener('input', refreshAll);
      fields[index].addEventListener('change', refreshAll);
    }
    bindSizeControls();
    refreshAll();
  }

  window.ArtsFolioTenantTypographyRefresh = refreshAll;
  window.ArtsFolioTenantTypographyBoot = boot;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
}());

/* End of file. */
