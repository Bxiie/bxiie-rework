(() => {
    'use strict';
    const key = 'artsfolio.export-folder';
    let preference;
    try { preference = JSON.parse(sessionStorage.getItem(key)); } catch (_) {}
    if (!preference || !/^[a-z0-9-]{1,32}$/.test(preference.id)) {
        preference = { id: `export-${crypto.randomUUID().slice(0, 24)}`, name: '' };
    }
    const remember = () => {
        try { sessionStorage.setItem(key, JSON.stringify(preference)); } catch (_) {}
    };
    remember();

    document.querySelectorAll('form.artwork-export-controls').forEach(form => {
        if (form.dataset.exportReady) return;
        form.dataset.exportReady = '1';
        const status = document.createElement('p');
        status.setAttribute('role', 'status');
        status.style.flexBasis = '100%';
        form.append(status);
        if (typeof window.showDirectoryPicker !== 'function') {
            status.textContent = 'Your browser controls the download folder. Enable “Ask where to save” in browser settings to choose a folder.';
            return;
        }
        const folderLabel = () => `Save folder: ${preference.name || 'Downloads'}. You can change it when exporting.`;
        status.textContent = folderLabel();
        let busy = false;
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (busy) return;
            busy = true;
            const submit = form.querySelector('button[type="submit"]');
            submit.disabled = true;
            let writable;
            try {
                const options = { id: preference.id, mode: 'readwrite' };
                if (!preference.name) options.startIn = 'downloads';
                const folder = await window.showDirectoryPicker(options);
                preference.name = folder.name;
                remember();
                status.textContent = 'Preparing image…';
                const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
                if (!response.ok || !/^image\//i.test(response.headers.get('Content-Type') || '')) {
                    throw new Error('Export failed. Refresh the page and try again.');
                }
                const disposition = response.headers.get('Content-Disposition') || '';
                const filename = (disposition.match(/filename="([^"]+)"/i)?.[1] || 'artwork-image')
                    .replace(/[\\/\x00-\x1f<>:"|?*]/g, '_').replace(/^\.+/, '_');
                const blob = await response.blob();
                // Keep existing exports intact by numbering duplicate filenames.
                let candidate = filename;
                for (let n = 1; ; n++) {
                    try {
                        await folder.getFileHandle(candidate);
                    } catch (error) {
                        if (error.name === 'NotFoundError') break;
                        throw error;
                    }
                    const dot = filename.lastIndexOf('.');
                    candidate = dot > 0 ? `${filename.slice(0, dot)} (${n})${filename.slice(dot)}` : `${filename} (${n})`;
                }
                const file = await folder.getFileHandle(candidate, { create: true });
                writable = await file.createWritable();
                await writable.write(blob);
                await writable.close();
                writable = null;
                status.textContent = `Saved ${candidate} in ${folder.name}.`;
            } catch (error) {
                if (writable) { try { await writable.abort(); } catch (_) {} }
                status.textContent = error.name === 'AbortError' ? folderLabel() : 'Could not save the image. Check folder access and try again.';
            } finally {
                busy = false;
                submit.disabled = false;
            }
        });
    });
})();
