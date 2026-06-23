/**
 * Adds client-side search and sortable columns to administrative tables.
 * Server-side filters and pagination remain authoritative; this helper refines
 * the rows already visible on the current page without mutating data.
 */
(() => {
    'use strict';

    const normalize = (value) => value.trim().toLocaleLowerCase();
    const cellValue = (row, index) => normalize(row.cells[index]?.innerText ?? '');
    const numeric = (value) => {
        const parsed = Number(value.replace(/[^0-9.-]/g, ''));
        return Number.isFinite(parsed) && /[0-9]/.test(value) ? parsed : null;
    };

    document.querySelectorAll('table.admin-table').forEach((table, tableIndex) => {
        const body = table.tBodies[0];
        const headers = Array.from(table.tHead?.rows[0]?.cells ?? []);
        if (!body || headers.length === 0 || table.dataset.tableTools === 'off') return;

        const wrapper = table.closest('.admin-table-wrap') ?? table.parentElement;
        const toolbar = document.createElement('div');
        toolbar.className = 'admin-table-tools';
        toolbar.innerHTML = `<label for="admin-table-filter-${tableIndex}">Filter visible rows</label><input id="admin-table-filter-${tableIndex}" type="search" placeholder="Type to filter this page" autocomplete="off"><span class="admin-muted" aria-live="polite"></span>`;
        wrapper?.insertBefore(toolbar, table);

        const input = toolbar.querySelector('input');
        const status = toolbar.querySelector('[aria-live]');
        const filter = () => {
            const query = normalize(input.value);
            let shown = 0;
            Array.from(body.rows).forEach((row) => {
                const visible = query === '' || normalize(row.innerText).includes(query);
                row.hidden = !visible;
                if (visible) shown += 1;
            });
            status.textContent = `${shown} row${shown === 1 ? '' : 's'} shown`;
        };
        input.addEventListener('input', filter);
        filter();

        headers.forEach((header, index) => {
            if (normalize(header.innerText) === 'actions' || header.dataset.sort === 'off') return;
            header.tabIndex = 0;
            header.classList.add('admin-sortable-column');
            header.setAttribute('role', 'button');
            header.setAttribute('aria-sort', 'none');
            const sort = () => {
                const ascending = header.getAttribute('aria-sort') !== 'ascending';
                headers.forEach((candidate) => candidate.setAttribute('aria-sort', 'none'));
                header.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');
                const rows = Array.from(body.rows);
                rows.sort((a, b) => {
                    const left = cellValue(a, index);
                    const right = cellValue(b, index);
                    const leftNumber = numeric(left);
                    const rightNumber = numeric(right);
                    const comparison = leftNumber !== null && rightNumber !== null
                        ? leftNumber - rightNumber
                        : left.localeCompare(right, undefined, {numeric: true, sensitivity: 'base'});
                    return ascending ? comparison : -comparison;
                });
                rows.forEach((row) => body.appendChild(row));
                filter();
            };
            header.addEventListener('click', sort);
            header.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    sort();
                }
            });
        });
    });
})();

// End of file.
