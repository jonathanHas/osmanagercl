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

            // Check window global fallback
            write('\n3. Checking window.ZplPreview global...');
            write('   window.ZplPreview: ' + typeof window.ZplPreview);
            if (window.ZplPreview) {
                write('   window.ZplPreview keys: ' + Object.keys(window.ZplPreview).join(', '));
                write('   renderToBase64: ' + typeof window.ZplPreview.renderToBase64);
                write('   renderToImg: ' + typeof window.ZplPreview.renderToImg);
            }

            // Pick the best renderer
            const renderer = (typeof mod.renderToBase64 === 'function') ? mod : window.ZplPreview;
            write('\n4. Using renderer: ' + (renderer === mod ? 'ES module exports' : 'window.ZplPreview'));

            if (!renderer || typeof renderer.renderToBase64 !== 'function') {
                write('   ERROR: No working renderer found!');
            } else {
                // Test with simple ZPL
                const testZpl = '^XA^FO50,50^A0N,40,40^FDHello World^FS^XZ';
                write('\n5. Testing renderToBase64 with simple ZPL...');
                const base64 = await renderer.renderToBase64(testZpl, 76, 50, 12);
                write('   Success! Base64 length: ' + base64.length);
                previewImg.src = 'data:image/png;base64,' + base64;
                previewContainer.classList.remove('hidden');

                write('\n6. Testing renderToImg...');
                await renderer.renderToImg(testZpl, previewImg, 76, 50, 12);
                write('   renderToImg succeeded.');

                write('\n✅ ALL TESTS PASSED - renderer is working!');
            }

        } catch (err) {
            write('\nERROR: ' + err.message);
            write('Stack: ' + err.stack);
        }
    </script>
</x-admin-layout>
