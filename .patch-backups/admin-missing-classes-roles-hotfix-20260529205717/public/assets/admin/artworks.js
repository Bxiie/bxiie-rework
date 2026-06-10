document.addEventListener('submit', async function (event) {
    const form = event.target.closest('.js-artwork-action');
    if (!form) return;

    event.preventDefault();

    const row = form.closest('tr');
    const notice = document.getElementById('artwork-action-notice');
    const button = form.querySelector('button[type="submit"], button');

    if (button) {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.textContent = 'Working...';
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-Requested-With': 'fetch',
                'Accept': 'application/json'
            }
        });

        const payload = await response.json();

        if (!response.ok || !payload.ok) {
            throw new Error(payload.message || 'Action failed.');
        }

        if (payload.archived) {
            row?.remove();
        } else if (payload.status && row) {
            const statusCell = row.querySelector('.js-artwork-status');
            if (statusCell) statusCell.textContent = payload.status;
        }

        if (notice) {
            notice.innerHTML = '<p style="padding:.75rem;background:#eef8ee;border:1px solid #9ac99a;">' + payload.message + '</p>';
        }
    } catch (error) {
        if (notice) {
            notice.innerHTML = '<p style="padding:.75rem;background:#fff0f0;border:1px solid #d88;">' + error.message + '</p>';
        }
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = button.dataset.originalText || 'Submit';
        }
    }
});

// End of file.
