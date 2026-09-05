{{--
    Invoice Folder Sync
    -------------------
    Lets the browser read invoice files straight out of a folder on the user's own
    machine and, once the invoices have been created, move the originals into a
    "Processed/YYYY-MM" subfolder of that same folder.

    The server has no access to that folder, so this is done entirely client side
    with the File System Access API (Chrome/Edge desktop only, secure context only).
    The picked directory handle is stashed in IndexedDB so it survives the
    navigation from the upload page to the preview page.

    Included by invoices/bulk-upload.blade.php and invoices/bulk-upload-preview.blade.php.
--}}
@push('scripts')
<script>
(function () {
    const DB_NAME = 'invoice-folder-sync';
    const DB_VERSION = 1;
    const STORE = 'handles';
    const PROCESSED_DIR = 'Processed';
    // The user's default invoice folder, remembered across sessions. Distinct from the
    // per-batch records, which only exist so the preview page can move that batch's files.
    const INBOX_KEY = '__inbox__';
    const MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000; // prune remembered folders after a week

    function openDb() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            request.onupgradeneeded = () => {
                if (!request.result.objectStoreNames.contains(STORE)) {
                    request.result.createObjectStore(STORE);
                }
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    function tx(db, mode, fn) {
        return new Promise((resolve, reject) => {
            const transaction = db.transaction(STORE, mode);
            const store = transaction.objectStore(STORE);
            let result;
            try {
                result = fn(store);
            } catch (error) {
                reject(error);
                return;
            }
            transaction.oncomplete = () => resolve(result && result.result !== undefined ? result.result : result);
            transaction.onerror = () => reject(transaction.error);
            transaction.onabort = () => reject(transaction.error);
        });
    }

    function request(req) {
        return new Promise((resolve, reject) => {
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    async function prune(db) {
        const cutoff = Date.now() - MAX_AGE_MS;
        const transaction = db.transaction(STORE, 'readwrite');
        const store = transaction.objectStore(STORE);
        const keys = await request(store.getAllKeys());
        for (const key of keys) {
            if (key === INBOX_KEY) {
                continue; // the default folder is kept until the user changes it
            }
            const value = await request(store.get(key));
            if (!value || !value.pickedAt || value.pickedAt < cutoff) {
                store.delete(key);
            }
        }
    }

    function splitName(name) {
        const dot = name.lastIndexOf('.');
        if (dot <= 0) {
            return { base: name, ext: '' };
        }
        return { base: name.slice(0, dot), ext: name.slice(dot) };
    }

    async function uniqueName(dirHandle, name) {
        const { base, ext } = splitName(name);
        let candidate = name;
        let counter = 1;

        // getFileHandle without create throws NotFoundError when the name is free.
        for (;;) {
            try {
                await dirHandle.getFileHandle(candidate);
            } catch (error) {
                if (error && error.name === 'NotFoundError') {
                    return candidate;
                }
                throw error;
            }
            counter += 1;
            candidate = base + ' (' + counter + ')' + ext;
        }
    }

    const InvoiceFolderSync = {
        /**
         * The File System Access API needs a secure context. This app is served over
         * plain HTTP, so Chrome must have the origin allowlisted under
         * chrome://flags/#unsafely-treat-insecure-origin-as-secure.
         */
        isSupported() {
            return typeof window.showDirectoryPicker === 'function' && window.isSecureContext === true;
        },

        /** Returns null when the user cancels the picker. */
        async pickFolder() {
            try {
                return await window.showDirectoryPicker({
                    id: 'invoice-inbox',
                    mode: 'readwrite',
                    startIn: 'documents',
                });
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return null;
                }
                throw error;
            }
        },

        /** Top level files only, filtered by extension. The Processed subfolder is skipped. */
        async listFiles(dirHandle, allowedExtensions) {
            const allowed = (allowedExtensions || []).map(ext => ext.toLowerCase().replace(/^\./, ''));
            const files = [];

            for await (const entry of dirHandle.values()) {
                if (entry.kind !== 'file') {
                    continue;
                }
                const ext = entry.name.split('.').pop().toLowerCase();
                if (allowed.length && !allowed.includes(ext)) {
                    continue;
                }
                files.push(await entry.getFile());
            }

            return files.sort((a, b) => a.name.localeCompare(b.name));
        },

        async remember(batchId, dirHandle, filenames) {
            if (!dirHandle || !batchId) {
                return;
            }
            const db = await openDb();
            await prune(db);
            await tx(db, 'readwrite', store => store.put({
                dirHandle,
                filenames: filenames || [],
                folderName: dirHandle.name,
                pickedAt: Date.now(),
            }, batchId));
            db.close();
        },

        async recall(batchId) {
            if (!batchId) {
                return null;
            }
            try {
                const db = await openDb();
                const transaction = db.transaction(STORE, 'readonly');
                const value = await request(transaction.objectStore(STORE).get(batchId));
                db.close();
                return value || null;
            } catch (error) {
                console.warn('InvoiceFolderSync: could not recall folder handle', error);
                return null;
            }
        },

        async forget(batchId) {
            try {
                const db = await openDb();
                await tx(db, 'readwrite', store => store.delete(batchId));
                db.close();
            } catch (error) {
                console.warn('InvoiceFolderSync: could not forget folder handle', error);
            }
        },

        /**
         * Remember this folder as the user's default invoice inbox, so later visits can
         * reuse it without showing the directory picker again.
         */
        async rememberInbox(dirHandle) {
            if (!dirHandle) {
                return;
            }
            try {
                const db = await openDb();
                await tx(db, 'readwrite', store => store.put({
                    dirHandle,
                    folderName: dirHandle.name,
                    pickedAt: Date.now(),
                }, INBOX_KEY));
                db.close();
            } catch (error) {
                console.warn('InvoiceFolderSync: could not remember inbox folder', error);
            }
        },

        async recallInbox() {
            return this.recall(INBOX_KEY);
        },

        async forgetInbox() {
            return this.forget(INBOX_KEY);
        },

        /**
         * 'granted' means we can read the folder with no prompt and no user gesture,
         * which is what makes auto-loading on page open possible. Chrome only reports
         * this when the user chose "Allow on every visit"; otherwise it is 'prompt'.
         */
        async permissionState(dirHandle) {
            if (!dirHandle || typeof dirHandle.queryPermission !== 'function') {
                return null;
            }
            try {
                return await dirHandle.queryPermission({ mode: 'readwrite' });
            } catch (error) {
                return null;
            }
        },

        /** Must be called from a user gesture or Chrome rejects the prompt. */
        async ensurePermission(dirHandle) {
            const options = { mode: 'readwrite' };
            if ((await dirHandle.queryPermission(options)) === 'granted') {
                return true;
            }
            return (await dirHandle.requestPermission(options)) === 'granted';
        },

        /**
         * Moves the named files into Processed/YYYY-MM. Files that are already gone
         * are reported as skipped rather than failed. One bad file never aborts the rest.
         */
        async moveToProcessed(dirHandle, filenames) {
            const now = new Date();
            const monthFolder = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');

            const processedDir = await dirHandle.getDirectoryHandle(PROCESSED_DIR, { create: true });
            const destDir = await processedDir.getDirectoryHandle(monthFolder, { create: true });

            const moved = [];
            const skipped = [];
            const failed = [];

            for (const name of filenames) {
                let sourceHandle;
                try {
                    sourceHandle = await dirHandle.getFileHandle(name);
                } catch (error) {
                    // Already moved by an earlier run, or renamed outside the browser.
                    skipped.push(name);
                    continue;
                }

                try {
                    const target = await uniqueName(destDir, name);

                    if (typeof sourceHandle.move === 'function') {
                        await sourceHandle.move(destDir, target);
                    } else {
                        const file = await sourceHandle.getFile();
                        const destHandle = await destDir.getFileHandle(target, { create: true });
                        const writable = await destHandle.createWritable();
                        await writable.write(file);
                        await writable.close();
                        await dirHandle.removeEntry(name);
                    }

                    moved.push(target);
                } catch (error) {
                    failed.push({ name, reason: (error && error.message) || 'Unknown error' });
                }
            }

            return { moved, skipped, failed, destination: PROCESSED_DIR + '/' + monthFolder };
        },
    };

    window.InvoiceFolderSync = InvoiceFolderSync;
})();
</script>
@endpush
