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
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return '[name="' + window.CSS.escape(String(name)) + '"]';
        }

        return '[name="' + String(name).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]';
    }

    function notifyFieldChanged(field) {
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function coerceBoolean(value) {
        return value === true || value === '1' || value === 1 || value === 'true' || value === 'on';
    }

    function applyNamedValue(name, value) {
        var selector = cssNameSelector(name);
        var fields = Array.prototype.slice.call(document.querySelectorAll(selector));

        if (fields.length === 0) {
            return false;
        }

        if (fields[0].type === 'radio') {
            fields.forEach(function (field) {
                field.checked = String(field.value) === String(value);
                notifyFieldChanged(field);
            });
            return true;
        }

        if (fields[0].type === 'checkbox') {
            fields[0].checked = coerceBoolean(value);
            notifyFieldChanged(fields[0]);
            return true;
        }

        fields[0].value = String(value);
        notifyFieldChanged(fields[0]);
        return true;
    }

    function decodePalettePayload(button) {
        var raw = button.getAttribute('data-tenant-palette') || '';

        if (!raw && button.dataset) {
            raw = button.dataset.tenantPalette || '';
        }

        if (!raw) {
            return {};
        }

        try {
            return JSON.parse(raw);
        } catch (error) {
            /* A malformed palette should not break the rest of the settings page. */
            console.error('Could not parse tenant palette payload.', error, button);
            return {};
        }
    }

    function applyPalette(button) {
        var values = decodePalettePayload(button);
        var applied = 0;

        Object.keys(values).forEach(function (name) {
            if (applyNamedValue(name, values[name])) {
                applied += 1;
            }
        });

        document.querySelectorAll('.tenant-palette-button[aria-pressed="true"]').forEach(function (pressed) {
            pressed.setAttribute('aria-pressed', 'false');
        });
        button.setAttribute('aria-pressed', 'true');

        if (applied > 0) {
            button.classList.add('tenant-palette-button-applied');
            window.setTimeout(function () {
                button.classList.remove('tenant-palette-button-applied');
            }, 900);
        }

        return applied;
    }

    function enhanceColorFields() {
        document.querySelectorAll('input[type="text"], input:not([type])').forEach(enhance);
    }

    function findPaletteButton(target) {
        while (target && target !== document) {
            if (target.classList && target.classList.contains('tenant-palette-button')) {
                return target;
            }
            target = target.parentNode;
        }

        return null;
    }

    function bindPaletteButtons() {
        if (paletteBound) {
            return;
        }

        paletteBound = true;
        document.addEventListener('click', function (event) {
            var button = findPaletteButton(event.target);

            if (!button || !button.hasAttribute('data-tenant-palette')) {
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

    window.ArtsFolioApplyTenantPalette = applyPalette;
    window.ArtsFolioTenantPaletteBoot = boot;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());

// End of file.
