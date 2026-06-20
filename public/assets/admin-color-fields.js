/*
 * Adds color picker behavior, live swatches, and tenant color palette buttons to
 * admin fields whose names indicate that they store colors. This is intentionally
 * progressive: normal text inputs still work if JavaScript is disabled or a
 * value is not hex.
 */
(function () {
    'use strict';

    var colorNamePattern = /(^|_)(color|colour)($|_)/i;
    var hexPattern = /^#[0-9a-f]{6}$/i;
    var paletteBound = false;

    function normalize(value) {
        value = String(value || '').trim();
        if (/^#[0-9a-f]{3}$/i.test(value)) {
            return '#' + value.charAt(1) + value.charAt(1) + value.charAt(2) + value.charAt(2) + value.charAt(3) + value.charAt(3);
        }
        return hexPattern.test(value) ? value : '';
    }

    function enhance(input) {
        if (!input.name || !colorNamePattern.test(input.name) || input.dataset.colorEnhanced === '1') {
            return;
        }

        var initial = normalize(input.value) || '#000000';
        var wrapper = document.createElement('span');
        wrapper.className = 'admin-color-field';

        var picker = document.createElement('input');
        picker.type = 'color';
        picker.className = 'admin-color-picker';
        picker.value = initial;
        picker.setAttribute('aria-label', 'Pick ' + input.name.replace(/_/g, ' '));

        var swatch = document.createElement('span');
        swatch.className = 'admin-color-swatch';
        swatch.title = input.value || initial;

        function syncFromText() {
            var normalized = normalize(input.value);
            if (normalized) {
                picker.value = normalized;
                swatch.style.backgroundColor = normalized;
                swatch.title = normalized;
            } else {
                swatch.style.backgroundColor = 'transparent';
                swatch.title = input.value || 'No valid hex color';
            }
        }

        function syncFromPicker() {
            input.value = picker.value;
            syncFromText();
        }

        input.dataset.colorEnhanced = '1';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        wrapper.appendChild(picker);
        wrapper.appendChild(swatch);
        input.addEventListener('input', syncFromText);
        picker.addEventListener('input', syncFromPicker);
        syncFromText();
    }

    function cssNameSelector(name) {
        return '[name="' + String(name).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]';
    }

    function notifyFieldChanged(field) {
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function applyNamedValue(name, value) {
        var selector = cssNameSelector(name);
        var fields = Array.prototype.slice.call(document.querySelectorAll(selector));

        if (fields.length === 0) {
            return;
        }

        if (fields[0].type === 'radio') {
            fields.forEach(function (field) {
                field.checked = String(field.value) === String(value);
                if (field.checked) {
                    notifyFieldChanged(field);
                }
            });
            return;
        }

        if (fields[0].type === 'checkbox') {
            fields[0].checked = value === true || value === '1' || value === 1 || value === 'true';
            notifyFieldChanged(fields[0]);
            return;
        }

        fields[0].value = value;
        notifyFieldChanged(fields[0]);
    }

    function applyPalette(button) {
        var values = {};

        try {
            values = JSON.parse(button.getAttribute('data-tenant-palette') || '{}');
        } catch (error) {
            return;
        }

        Object.keys(values).forEach(function (name) {
            applyNamedValue(name, values[name]);
        });

        document.querySelectorAll('.tenant-palette-button[aria-pressed="true"]').forEach(function (pressed) {
            pressed.setAttribute('aria-pressed', 'false');
        });
        button.setAttribute('aria-pressed', 'true');
    }

    function enhanceColorFields() {
        document.querySelectorAll('input[type="text"], input:not([type])').forEach(enhance);
    }

    function bindPaletteButtons() {
        if (paletteBound) {
            return;
        }

        paletteBound = true;
        document.addEventListener('click', function (event) {
            var button = event.target.closest('.tenant-palette-button[data-tenant-palette]');

            if (!button) {
                return;
            }

            event.preventDefault();
            applyPalette(button);
        });

        document.querySelectorAll('.tenant-palette-button[data-tenant-palette]').forEach(function (button) {
            if (!button.hasAttribute('aria-pressed')) {
                button.setAttribute('aria-pressed', 'false');
            }
        });
    }

    function boot() {
        enhanceColorFields();
        bindPaletteButtons();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());

// End of file.
