/**
 * Progressive enhancement for ArtsFolio social publishing.
 *
 * Server-side authorization remains authoritative. This script only surfaces
 * Instagram controls when /social/context confirms the signed-in user has the
 * tenant-scoped social.publish capability on an eligible paid plan.
 */
(() => {
    'use strict';

    const json = async (url) => {
        const response = await fetch(url, {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
        if (!response.ok) return null;
        return response.json();
    };

    const artworkIdFromRow = (row) => {
        const match = (row?.id || '').match(/^artwork-(\d+)$/);
        return match ? Number(match[1]) : 0;
    };

    const socialButton = (url) => {
        const link = document.createElement('a');
        link.href = url;
        link.className = 'admin-button social-instagram-button';
        link.textContent = 'Instagram';
        link.setAttribute('aria-label', 'Post to Instagram');
        return link;
    };

    async function enhanceAdminNavigation() {
        const sidebarNav = document.querySelector('.tenant-admin-sidebar nav');
        if (!sidebarNav || sidebarNav.querySelector('a[href="/admin/social"]')) return;
        const context = await json('/social/context');
        if (!context?.allowed) return;
        const link = document.createElement('a');
        link.href = '/admin/social';
        link.textContent = 'Instagram';
        sidebarNav.appendChild(link);
    }

    async function enhanceArtworkGrid() {
        const rows = Array.from(document.querySelectorAll('tr[id^="artwork-"]'));
        if (rows.length === 0) return;
        const firstId = artworkIdFromRow(rows[0]);
        if (!firstId) return;
        const context = await json('/social/context?artwork_id=' + encodeURIComponent(firstId));
        if (!context?.allowed) return;
        rows.forEach((row) => {
            const artworkId = artworkIdFromRow(row);
            const actionCell = row.cells[row.cells.length - 1];
            if (!artworkId || !actionCell || actionCell.querySelector('.social-instagram-button')) return;
            actionCell.prepend(document.createTextNode(' '));
            actionCell.prepend(socialButton('/admin/social/compose?artwork_id=' + artworkId));
        });
    }

    async function enhanceArtworkEditor() {
        const form = document.querySelector('form[action="/admin/artworks/edit"]');
        const idInput = form?.querySelector('input[name="id"]');
        const artworkId = Number(idInput?.value || 0);
        if (!form || !artworkId) return;
        const context = await json('/social/context?artwork_id=' + encodeURIComponent(artworkId));
        if (!context?.allowed) return;

        const card = document.createElement('section');
        card.className = 'admin-card social-artwork-metadata-card';
        card.innerHTML = `
            <h2>Social publishing</h2>
            <p class="form-help">Social Caption is used by Instagram templates and is separate from public gallery prose. Internal notes are never sent to Instagram.</p>
            <label>Social Caption<br><textarea rows="5" data-social-caption-field></textarea></label>
            <label>Artwork hashtags<br><input type="text" data-social-hashtags-field placeholder="#sculpture #vermontartist"></label>
            <p><a class="admin-button social-instagram-button" href="${context.compose_url}">Post to Instagram</a></p>`;
        const preview = form.previousElementSibling;
        form.insertBefore(card, form.firstChild);
        card.querySelector('[data-social-caption-field]').value = context.social_caption || '';
        card.querySelector('[data-social-hashtags-field]').value = context.social_hashtags || '';

        form.addEventListener('submit', async (event) => {
            if (form.dataset.socialMetadataSaved === '1') return;
            event.preventDefault();
            const data = new FormData();
            data.set('csrf_token', context.csrf_token || '');
            data.set('artwork_id', String(artworkId));
            data.set('social_caption', card.querySelector('[data-social-caption-field]').value);
            data.set('social_hashtags', card.querySelector('[data-social-hashtags-field]').value);
            try {
                await fetch('/admin/social/artwork-metadata', {method: 'POST', credentials: 'same-origin', body: data, headers: {'Accept': 'application/json'}});
            } finally {
                form.dataset.socialMetadataSaved = '1';
                form.submit();
            }
        });
    }

    async function enhancePublicArtwork() {
        const match = window.location.pathname.match(/^\/artwork\/([^/]+)$/);
        if (!match) return;
        const context = await json('/social/context?artwork_slug=' + encodeURIComponent(decodeURIComponent(match[1])));
        if (!context?.allowed || !context.compose_url) return;
        const main = document.querySelector('.site-main');
        if (!main || main.querySelector('.social-instagram-button')) return;
        const button = socialButton(context.compose_url);
        button.textContent = 'Post to Instagram';
        const imageParagraph = main.querySelector('p:has(img)');
        const wrapper = document.createElement('p');
        wrapper.className = 'social-public-artwork-action';
        wrapper.appendChild(button);
        if (imageParagraph) imageParagraph.insertAdjacentElement('afterend', wrapper);
        else main.prepend(wrapper);
    }

    function enhanceCompose() {
        const form = document.querySelector('[data-social-compose]');
        if (!form) return;
        const caption = form.querySelector('[data-social-caption]');
        const count = form.querySelector('[data-social-character-count]');
        const updateCount = () => { if (count && caption) count.textContent = String(caption.value.length); };
        caption?.addEventListener('input', updateCount);
        updateCount();

        const grid = form.querySelector('[data-social-media-grid]');
        const cards = () => Array.from(grid?.querySelectorAll('[data-social-media-card]') || []);
        const selectedCards = () => cards().filter((card) => card.querySelector('input[type="checkbox"]')?.checked);
        const normalizeOrder = () => {
            selectedCards().forEach((card, index) => {
                card.dataset.order = String(index);
                const field = card.querySelector('[data-social-order]');
                if (field) field.value = String(index);
            });
        };
        cards().forEach((card) => {
            const checkbox = card.querySelector('input[type="checkbox"]');
            checkbox?.addEventListener('change', () => {
                if (selectedCards().length > 10) {
                    checkbox.checked = false;
                    window.alert('Instagram carousels may contain at most 10 images.');
                }
                normalizeOrder();
            });
            card.querySelector('[data-social-up]')?.addEventListener('click', () => {
                const selected = selectedCards();
                const index = selected.indexOf(card);
                if (index > 0) grid.insertBefore(card, selected[index - 1]);
                normalizeOrder();
            });
            card.querySelector('[data-social-down]')?.addEventListener('click', () => {
                const selected = selectedCards();
                const index = selected.indexOf(card);
                if (index >= 0 && index < selected.length - 1) selected[index + 1].insertAdjacentElement('afterend', card);
                normalizeOrder();
            });
            const crop = card.querySelector('[data-social-crop]');
            const image = card.querySelector('img');
            const applyCropPreview = () => {
                if (!crop || !image) return;
                image.style.aspectRatio = crop.value === 'square' ? '1 / 1' : crop.value === 'portrait' ? '4 / 5' : crop.value === 'landscape' ? '1.91 / 1' : 'auto';
                image.style.objectFit = crop.value === 'original' ? 'contain' : 'cover';
            };
            crop?.addEventListener('change', applyCropPreview);
            applyCropPreview();
        });
        normalizeOrder();

        const templateSelect = form.querySelector('[data-social-template]');
        templateSelect?.addEventListener('change', () => {
            const option = templateSelect.selectedOptions[0];
            if (!option || !caption) return;
            if (caption.value.trim() !== '' && !window.confirm('Replace the current editable caption with the selected template text?')) return;
            caption.value = option.dataset.template || '';
            updateCount();
        });
    }

    function addStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .social-instagram-button { white-space: nowrap; }
            .social-media-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:1rem;margin:1rem 0; }
            .social-media-card { border:1px solid #bbb;padding:.75rem;background:rgba(255,255,255,.75);display:grid;gap:.5rem;align-content:start; }
            .social-media-card img { width:100%;height:180px;object-fit:contain;background:#eee;transition:aspect-ratio .15s ease; }
            .social-media-card label { display:block; }
            .social-artwork-metadata-card textarea,.social-artwork-metadata-card input { width:100%;max-width:60rem; }
            .social-public-artwork-action { margin:.75rem 0 1.5rem; }
        `;
        document.head.appendChild(style);
    }

    document.addEventListener('DOMContentLoaded', () => {
        addStyles();
        enhanceAdminNavigation();
        enhanceArtworkGrid();
        enhanceArtworkEditor();
        enhancePublicArtwork();
        enhanceCompose();
    });
})();

// End of file.
