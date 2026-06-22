(() => {
    'use strict';

    const rootSelector = '[data-artwork-pager-root]';
    let requestController = null;
    let placementColumnQuery = '';
    let placementAssignmentFilter = '';

    const currentRoot = () => document.querySelector(rootSelector);

    const normalize = (value) => String(value || '').trim().toLocaleLowerCase();

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

        let visibleRows = 0;
        matrix.querySelectorAll('tbody tr').forEach((row) => {
            if (!placementAssignmentFilter) {
                row.hidden = false;
                visibleRows += 1;
                return;
            }
            const cell = row.querySelector(`[data-placement-assignment="${CSS.escape(placementAssignmentFilter)}"]`);
            const checkbox = cell?.querySelector('input[type="checkbox"]');
            row.hidden = !checkbox?.checked;
            if (!row.hidden) {
                visibleRows += 1;
            }
        });

        root.querySelectorAll('[data-placement-assignment-filter]').forEach((button) => {
            button.setAttribute('aria-pressed', button.dataset.placementAssignmentFilter === placementAssignmentFilter ? 'true' : 'false');
        });
        const reset = root.querySelector('[data-placement-assignment-reset]');
        if (reset) {
            reset.hidden = placementAssignmentFilter === '';
        }
        const search = root.querySelector('[data-placement-column-search]');
        if (search && search.value !== placementColumnQuery) {
            search.value = placementColumnQuery;
        }
        const status = root.querySelector('[data-placement-filter-status]');
        if (status) {
            const parts = [];
            if (query) {
                parts.push(`Columns matching “${placementColumnQuery}”`);
            }
            if (placementAssignmentFilter) {
                const active = root.querySelector(`[data-placement-assignment-filter="${CSS.escape(placementAssignmentFilter)}"]`);
                parts.push(`${visibleRows} artworks assigned to ${active?.textContent?.trim() || 'selected column'}`);
            }
            status.textContent = parts.join(' · ');
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
        const select = event.target.closest(`${rootSelector} select[name="per_page"]`);
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
        const columnFilter = event.target.closest(`${rootSelector} [data-placement-assignment-filter]`);
        if (columnFilter) {
            event.preventDefault();
            const next = columnFilter.dataset.placementAssignmentFilter || '';
            placementAssignmentFilter = placementAssignmentFilter === next ? '' : next;
            applyPlacementFilters();
            return;
        }

        const columnReset = event.target.closest(`${rootSelector} [data-placement-column-reset]`);
        if (columnReset) {
            event.preventDefault();
            placementColumnQuery = '';
            applyPlacementFilters();
            return;
        }

        const assignmentReset = event.target.closest(`${rootSelector} [data-placement-assignment-reset]`);
        if (assignmentReset) {
            event.preventDefault();
            placementAssignmentFilter = '';
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
