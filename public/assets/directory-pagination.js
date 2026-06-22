(() => {
    'use strict';

    const rootSelector = '[data-directory-pager-root]';
    let requestController = null;

    const currentRoot = () => document.querySelector(rootSelector);

    const loadDirectoryPage = async (url, {push = true, focus = true} = {}) => {
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
                    'X-ArtsFolio-Partial': 'directory-list'
                },
                signal: requestController.signal
            });
            if (!response.ok) {
                throw new Error(`Directory request failed with ${response.status}`);
            }

            const html = await response.text();
            const nextDocument = new DOMParser().parseFromString(html, 'text/html');
            const replacement = nextDocument.querySelector(rootSelector);
            if (!replacement) {
                throw new Error('Directory response did not contain the expected region.');
            }

            root.replaceWith(replacement);
            document.title = nextDocument.title || document.title;
            if (push) {
                window.history.pushState({artsfolioDirectoryPage: true}, '', url);
            }
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
            currentRoot()?.removeAttribute('aria-busy');
        }
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest(`${rootSelector} a[data-directory-page-link]`);
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        if (link.target && link.target !== '_self') {
            return;
        }
        event.preventDefault();
        loadDirectoryPage(link.href);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest(`${rootSelector} form[data-directory-page-form]`);
        if (!form || form.method.toLowerCase() !== 'get') {
            return;
        }
        event.preventDefault();
        const url = new URL(form.action, window.location.href);
        const params = new URLSearchParams(new FormData(form));
        params.delete('page');
        url.search = params.toString();
        loadDirectoryPage(url.toString());
    });

    document.addEventListener('change', (event) => {
        const select = event.target.closest(`${rootSelector} select[data-directory-sort]`);
        if (!select) {
            return;
        }
        select.closest('form[data-directory-page-form]')?.requestSubmit();
    });

    window.addEventListener('popstate', () => {
        if (currentRoot()) {
            loadDirectoryPage(window.location.href, {push: false, focus: false});
        }
    });
})();
