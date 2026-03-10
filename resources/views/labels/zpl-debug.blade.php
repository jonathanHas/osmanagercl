<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ZPL Renderer Debug</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <h3 class="font-bold text-lg">Diagnostics</h3>

                <div id="log" class="bg-gray-900 text-green-400 text-xs font-mono p-4 rounded max-h-96 overflow-y-auto whitespace-pre-wrap"></div>

                <div id="preview-container" class="hidden border rounded p-4 text-center">
                    <p class="text-sm text-gray-500 mb-2">Rendered preview:</p>
                    <img id="preview-img" class="mx-auto border" alt="ZPL preview">
                </div>
            </div>
        </div>
    </div>

    <script type="module">
        const log = document.getElementById('log');
        const previewImg = document.getElementById('preview-img');
        const previewContainer = document.getElementById('preview-container');

        function write(msg) {
            log.textContent += msg + '\n';
            log.scrollTop = log.scrollHeight;
        }

        const assetUrl = '{{ Vite::asset("resources/js/zpl-preview.js") }}';
        write('1. Vite asset URL: ' + assetUrl);

        try {
            write('\n2. Attempting dynamic import...');
            const mod = await import(assetUrl);
            write('   Import succeeded.');
            write('   Module type: ' + typeof mod);
            write('   Module keys: ' + Object.keys(mod).join(', '));
            write('   renderToBase64: ' + typeof mod.renderToBase64);
            write('   renderToImg: ' + typeof mod.renderToImg);

            if (mod.default) {
                write('   default export type: ' + typeof mod.default);
                if (typeof mod.default === 'object') {
                    write('   default export keys: ' + Object.keys(mod.default).join(', '));
                }
            }

            // Test with simple ZPL
            const testZpl = '^XA^FO50,50^A0N,40,40^FDHello World^FS^XZ';
            write('\n3. Testing renderToBase64 with simple ZPL...');

            if (typeof mod.renderToBase64 === 'function') {
                const base64 = await mod.renderToBase64(testZpl, 76, 50, 12);
                write('   Success! Base64 length: ' + base64.length);
                previewImg.src = 'data:image/png;base64,' + base64;
                previewContainer.classList.remove('hidden');
            } else {
                write('   ERROR: renderToBase64 is not a function');

                // Try alternative access patterns
                write('\n4. Trying alternative access patterns...');

                if (mod.default && typeof mod.default.renderToBase64 === 'function') {
                    write('   Found at mod.default.renderToBase64');
                }

                // Inspect all properties deeply
                for (const [key, val] of Object.entries(mod)) {
                    write('   mod.' + key + ' = ' + typeof val);
                    if (typeof val === 'object' && val !== null) {
                        for (const [k2, v2] of Object.entries(val)) {
                            write('     .' + k2 + ' = ' + typeof v2);
                        }
                    }
                }
            }

            if (typeof mod.renderToImg === 'function') {
                write('\n5. Testing renderToImg...');
                await mod.renderToImg(testZpl, previewImg, 76, 50, 12);
                write('   renderToImg succeeded.');
                previewContainer.classList.remove('hidden');
            } else {
                write('\n5. renderToImg is NOT a function');
            }

        } catch (err) {
            write('\nERROR: ' + err.message);
            write('Stack: ' + err.stack);
        }
    </script>
</x-admin-layout>
