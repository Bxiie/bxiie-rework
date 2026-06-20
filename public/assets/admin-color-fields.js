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

    function parseHexColor(value) {
        value = normalize(value);
        if (!value) {
            return null;
        }

        return {
            red: parseInt(value.substring(1, 3), 16),
            green: parseInt(value.substring(3, 5), 16),
            blue: parseInt(value.substring(5, 7), 16),
            hex: value
        };
    }

    function safeOpacity(value, fallback) {
        var number = parseFloat(value);

        if (!isFinite(number)) {
            return fallback;
        }

        if (number < 0) {
            return 0;
        }

        if (number > 1) {
            return 1;
        }

        return number;
    }

    function rgbaFromFields(colorValue, opacityValue, fallback) {
        var parsed = parseHexColor(colorValue);
        var alpha = safeOpacity(opacityValue, fallback);

        if (!parsed) {
            return String(colorValue || 'transparent');
        }

        return 'rgba(' + parsed.red + ',' + parsed.green + ',' + parsed.blue + ',' + alpha + ')';
    }

    function fieldValue(name) {
        var field = document.querySelector(cssNameSelector(name));

        if (!field) {
            return '';
        }

        if (field.type === 'checkbox') {
            return field.checked ? '1' : '0';
        }

        return field.value;
    }

    function setRootVariable(name, value) {
        document.documentElement.style.setProperty(name, value);
        if (document.body) {
            document.body.style.setProperty(name, value);
        }
    }

    function syncTenantPreviewVariables() {
        var topbarColor = fieldValue('topbar_background_color');
        var topbarOpacity = fieldValue('topbar_background_opacity') || '0.86';
        var menuColor = fieldValue('menu_background_color');
        var menuOpacity = fieldValue('menu_background_opacity') || '0.86';
        var menuEnabled = fieldValue('menu_background_enabled') !== '0';
        var textColor = fieldValue('text_color');
        var backgroundColor = fieldValue('background_color');
        var headingColor = fieldValue('heading_background_color');
        var headingOpacity = fieldValue('heading_background_opacity') || '0.78';
        var contentColor = fieldValue('content_background_color');
        var contentOpacity = fieldValue('content_background_opacity') || '0';
        var textBackgroundColor = fieldValue('text_background_color');
        var textBackgroundOpacity = fieldValue('text_background_opacity') || '0.72';
        var artworkCardColor = fieldValue('artwork_card_background_color');
        var artworkCardOpacity = fieldValue('artwork_card_background_opacity') || '0.84';

        if (topbarColor) {
            setRootVariable('--topbar-bg', topbarColor);
            setRootVariable('--tenant-topbar-bg', topbarColor);
            setRootVariable('--topbar-bg-overlay', rgbaFromFields(topbarColor, topbarOpacity, 0.86));
            setRootVariable('--topbar-bg-opacity', String(safeOpacity(topbarOpacity, 0.86)));
        }

        if (menuColor) {
            setRootVariable('--menu-bg', menuColor);
            setRootVariable('--menu-bg-overlay', menuEnabled && safeOpacity(menuOpacity, 0.86) > 0 ? rgbaFromFields(menuColor, menuOpacity, 0.86) : 'transparent');
            setRootVariable('--menu-bg-opacity', menuEnabled ? String(safeOpacity(menuOpacity, 0.86)) : '0');
            setRootVariable('--menu-panel-padding', menuEnabled && safeOpacity(menuOpacity, 0.86) > 0 ? '0.35rem 0.55rem' : '0');
            setRootVariable('--menu-panel-radius', menuEnabled && safeOpacity(menuOpacity, 0.86) > 0 ? '999px' : '0');
            setRootVariable('--menu-panel-shadow', menuEnabled && safeOpacity(menuOpacity, 0.86) > 0 ? '0 12px 32px rgba(0,0,0,0.08)' : 'none');
        }

        if (textColor) {
            setRootVariable('--text-color', textColor);
        }

        if (backgroundColor) {
            setRootVariable('--bg', backgroundColor);
        }

        if (headingColor) {
            setRootVariable('--heading-bg', headingColor);
            setRootVariable('--heading-bg-overlay', rgbaFromFields(headingColor, headingOpacity, 0.78));
        }

        if (contentColor) {
            setRootVariable('--content-bg', contentColor);
            setRootVariable('--content-bg-overlay', rgbaFromFields(contentColor, contentOpacity, 0));
        }

        if (textBackgroundColor) {
            setRootVariable('--text-bg', textBackgroundColor);
            setRootVariable('--text-bg-overlay', rgbaFromFields(textBackgroundColor, textBackgroundOpacity, 0.72));
        }

        if (artworkCardColor) {
            setRootVariable('--artwork-card-bg', artworkCardColor);
            setRootVariable('--artwork-card-bg-overlay', rgbaFromFields(artworkCardColor, artworkCardOpacity, 0.84));
        }
    }

    function bindTenantPreviewFields() {
        [
            'topbar_background_color',
            'topbar_background_opacity',
            'menu_background_color',
            'menu_background_opacity',
            'menu_background_enabled',
            'text_color',
            'background_color',
            'heading_background_color',
            'heading_background_opacity',
            'content_background_color',
            'content_background_opacity',
            'text_background_color',
            'text_background_opacity',
            'artwork_card_background_color',
            'artwork_card_background_opacity'
        ].forEach(function (name) {
            document.querySelectorAll(cssNameSelector(name)).forEach(function (field) {
                if (field.dataset.tenantPreviewBound === '1') {
                    return;
                }

                field.dataset.tenantPreviewBound = '1';
                field.addEventListener('input', syncTenantPreviewVariables);
                field.addEventListener('change', syncTenantPreviewVariables);
            });
        });

        syncTenantPreviewVariables();
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
            syncTenantPreviewVariables();
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
        bindTenantPreviewFields();
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
