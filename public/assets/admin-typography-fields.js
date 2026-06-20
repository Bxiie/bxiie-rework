/* Tenant typography live preview helper.
 *
 * The settings form stores tenant typography as plain inputs/selects. This
 * script mirrors selected local/system font stacks and size values into the
 * small admin preview samples immediately, before the full settings form is
 * saved. It also writes matching CSS variables onto the settings page body so
 * the admin page itself gives a quick smoke-test of the chosen typography.
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

  function sizeForFamily(familyFieldName) {
    var sizeFieldName = FAMILY_TO_SIZE[familyFieldName];
    var sizeField = sizeFieldName ? field(sizeFieldName) : null;
    return sizeField ? sizeField.value : '';
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

  function updateCssVariables() {
    var target = document.body || document.documentElement;

    Object.keys(FAMILY_TO_VAR).forEach(function (name) {
      var input = field(name);
      if (input && input.value) {
        target.style.setProperty(FAMILY_TO_VAR[name], input.value);
      }
    });

    Object.keys(SIZE_TO_VAR).forEach(function (name) {
      var input = field(name);
      if (input && isSafePreviewSize(input.value)) {
        target.style.setProperty(SIZE_TO_VAR[name], input.value);
      }
    });
  }

  function refreshAll() {
    var selects = document.querySelectorAll('.tenant-font-picker');
    for (var index = 0; index < selects.length; index += 1) {
      updateOnePreview(selects[index]);
    }
    updateCssVariables();
  }

  function boot() {
    var fields = document.querySelectorAll('.tenant-font-picker, input[name^="font_size_"]');
    for (var index = 0; index < fields.length; index += 1) {
      fields[index].addEventListener('input', refreshAll);
      fields[index].addEventListener('change', refreshAll);
    }
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
