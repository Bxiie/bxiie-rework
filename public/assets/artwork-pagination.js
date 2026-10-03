(() => {
    'use strict';

    const rootSelector = '[data-artwork-pager-root]';
    let requestController = null;
    let placementColumnQuery = '';

    const currentRoot = () => document.querySelector(rootSelector);

    const normalize = (value) => String(value || '').trim().toLocaleLowerCase();

    // Shows/hides whole columns by name — a client-side declutter aid only.
    // Which artworks are assigned to Home/a section is a real server-side
    // filter (the ?filter= query param, rendered as a link in the column
    // header) rather than client-side row hiding: a tenant's handful of
    // assigned artworks can easily be scattered across many pages, so
    // hiding rows within only the current page's load would show an
    // arbitrary, often near-empty, subset instead of the real result.
    const applyPlacementFilters = () => {
        const root = currentRoot();
        const matrix = root?.querySelector('[data-placement-matrix]');
        if (!matrix) {
            return;
        }

        const query = normalize(placementColumnQuery);
        const columns = Array.from(matrix.querySelectorAll('[data-placement-column]'));
        columns.forEach((cell) => {
            const columnName = normalize(cell.dataset.placementColumnName);
            cell.hidden = query !== '' && !columnName.includes(query);
        });

        const search = root.querySelector('[data-placement-column-search]');
        if (search && search.value !== placementColumnQuery) {
            search.value = placementColumnQuery;
        }
        const status = root.querySelector('[data-placement-filter-status]');
        if (status) {
            status.textContent = query ? `Columns matching “${placementColumnQuery}”` : '';
        }
    };

    const loadArtworkPage = async (url, {push = true, focus = true} = {}) => {
        const root = currentRoot();
        if (!root) {
            window.location.assign(url);
            return;
        }

        if (requestController) {
            requestController.abort();
        }
        requestController = new AbortController();
        root.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'text/html',
                    'X-ArtsFolio-Partial': 'artwork-list'
                },
                signal: requestController.signal
            });
            if (!response.ok) {
                throw new Error(`Artwork page request failed with ${response.status}`);
            }

            const html = await response.text();
            const nextDocument = new DOMParser().parseFromString(html, 'text/html');
            const replacement = nextDocument.querySelector(rootSelector);
            if (!replacement) {
                throw new Error('Artwork page response did not contain the expected region.');
            }

            root.replaceWith(replacement);
            document.title = nextDocument.title || document.title;
            if (push) {
                window.history.pushState({artsfolioArtworkPage: true}, '', url);
            }
            applyPlacementFilters();
            if (focus) {
                replacement.scrollIntoView({block: 'start', behavior: 'smooth'});
                replacement.focus({preventScroll: true});
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            window.location.assign(url);
        } finally {
            const activeRoot = currentRoot();
            if (activeRoot) {
                activeRoot.removeAttribute('aria-busy');
            }
        }
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest(`${rootSelector} a[data-artwork-page-link]`);
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        if (link.target && link.target !== '_self') {
            return;
        }
        event.preventDefault();
        loadArtworkPage(link.href);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest(`${rootSelector} form[data-artwork-page-form]`);
        if (!form || form.method.toLowerCase() !== 'get') {
            return;
        }
        event.preventDefault();
        const url = new URL(form.action, window.location.href);
        const params = new URLSearchParams(new FormData(form));
        params.delete('page');
        url.search = params.toString();
        loadArtworkPage(url.toString());
    });

    document.addEventListener('change', (event) => {
        const select = event.target.closest(`${rootSelector} select[name="per_page"], ${rootSelector} select[name="sort"]`);
        if (!select) {
            return;
        }
        const form = select.closest('form[data-artwork-page-form]');
        if (form) {
            form.requestSubmit();
        }
    });


    document.addEventListener('input', (event) => {
        const input = event.target.closest(`${rootSelector} [data-placement-column-search]`);
        if (!input) {
            return;
        }
        placementColumnQuery = input.value;
        applyPlacementFilters();
    });

    document.addEventListener('click', (event) => {
        const columnReset = event.target.closest(`${rootSelector} [data-placement-column-reset]`);
        if (columnReset) {
            event.preventDefault();
            placementColumnQuery = '';
            applyPlacementFilters();
        }
    });

    window.addEventListener('popstate', () => {
        if (currentRoot()) {
            loadArtworkPage(window.location.href, {push: false, focus: false});
        }
    });

    applyPlacementFilters();
})();
