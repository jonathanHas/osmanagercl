@props(['streamUrl', 'formSelector' => 'form'])

<!-- Progress Overlay -->
<div id="order-progress-overlay" class="fixed inset-0 z-50 hidden">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"></div>

    <!-- Modal -->
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-8">
            <!-- Header -->
            <div class="text-center mb-6">
                <div id="progress-spinner" class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 mb-3">
                    <svg class="animate-spin h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <div id="progress-done-icon" class="hidden inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 mb-3">
                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <div id="progress-error-icon" class="hidden inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-100 mb-3">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>
                <h3 id="progress-title" class="text-lg font-semibold text-gray-900">Generating Order...</h3>
            </div>

            <!-- Progress Bar -->
            <div class="w-full bg-gray-200 rounded-full h-2.5 mb-6">
                <div id="progress-bar" class="bg-blue-600 h-2.5 rounded-full transition-all duration-500 ease-out" style="width: 0%"></div>
            </div>

            <!-- Steps List -->
            <div id="progress-steps" class="space-y-3">
                <!-- Steps are added dynamically -->
            </div>

            <!-- Error Message -->
            <div id="progress-error" class="hidden mt-4">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p id="progress-error-message" class="text-sm text-red-700"></p>
                </div>
                <div class="mt-4 flex gap-3 justify-center">
                    <button id="progress-retry-btn" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                        Try Again
                    </button>
                    <button id="progress-close-btn" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const overlay = document.getElementById('order-progress-overlay');
    const progressBar = document.getElementById('progress-bar');
    const progressSteps = document.getElementById('progress-steps');
    const progressTitle = document.getElementById('progress-title');
    const progressSpinner = document.getElementById('progress-spinner');
    const progressDoneIcon = document.getElementById('progress-done-icon');
    const progressErrorIcon = document.getElementById('progress-error-icon');
    const progressError = document.getElementById('progress-error');
    const progressErrorMessage = document.getElementById('progress-error-message');
    const retryBtn = document.getElementById('progress-retry-btn');
    const closeBtn = document.getElementById('progress-close-btn');

    const streamUrl = @json($streamUrl);
    let lastForm = null;

    function showOverlay() {
        overlay.classList.remove('hidden');
        progressBar.style.width = '0%';
        progressBar.classList.remove('bg-green-500', 'bg-red-500');
        progressBar.classList.add('bg-blue-600');
        progressSteps.innerHTML = '';
        progressTitle.textContent = 'Generating Order...';
        progressSpinner.classList.remove('hidden');
        progressDoneIcon.classList.add('hidden');
        progressErrorIcon.classList.add('hidden');
        progressError.classList.add('hidden');
    }

    function hideOverlay() {
        overlay.classList.add('hidden');
    }

    function addOrUpdateStep(step, message, status) {
        let stepEl = document.getElementById('step-' + step);

        if (!stepEl) {
            stepEl = document.createElement('div');
            stepEl.id = 'step-' + step;
            stepEl.className = 'flex items-center gap-3';
            progressSteps.appendChild(stepEl);
        }

        let icon = '';
        let textClass = 'text-gray-500';

        if (status === 'active') {
            icon = '<svg class="animate-spin h-4 w-4 text-blue-600 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
            textClass = 'text-blue-700 font-medium';
        } else if (status === 'done') {
            icon = '<svg class="h-4 w-4 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
            textClass = 'text-green-700';
        } else if (status === 'error') {
            icon = '<svg class="h-4 w-4 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
            textClass = 'text-red-700';
        }

        stepEl.innerHTML = icon + '<span class="text-sm ' + textClass + '">' + escapeHtml(message) + '</span>';
    }

    function markPreviousStepsDone() {
        const steps = progressSteps.querySelectorAll('[id^="step-"]');
        steps.forEach(function(step) {
            if (step.querySelector('.animate-spin')) {
                const text = step.querySelector('span').textContent;
                const stepId = step.id.replace('step-', '');
                addOrUpdateStep(stepId, text, 'done');
            }
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function startStream(form) {
        lastForm = form;
        showOverlay();

        const formData = new FormData(form);
        let receivedComplete = false;

        fetch(streamUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'text/event-stream',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function(response) {
            const contentType = response.headers.get('content-type') || '';

            if (!response.ok) {
                // Try to extract error message from JSON validation errors
                if (contentType.includes('application/json')) {
                    return response.json().then(function(json) {
                        const msg = json.message || 'Validation failed';
                        throw new Error(msg);
                    });
                }
                throw new Error('Server returned ' + response.status);
            }

            // If server returned HTML instead of SSE (e.g. redirect was followed), fall back
            if (!contentType.includes('text/event-stream')) {
                throw new Error('Unexpected response type: ' + contentType + '. Please try again.');
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            function processBuffer() {
                const lines = buffer.split('\n');
                buffer = lines.pop();

                lines.forEach(function(line) {
                    if (line.startsWith('data: ')) {
                        try {
                            const data = JSON.parse(line.substring(6));
                            handleEvent(data);
                            if (data.type === 'complete') receivedComplete = true;
                        } catch (e) {
                            // ignore parse errors
                        }
                    }
                });
            }

            function read() {
                reader.read().then(function(result) {
                    if (result.done) {
                        // Process any remaining buffer
                        if (buffer.trim()) {
                            buffer += '\n';
                            processBuffer();
                        }
                        // If stream ended without a complete/error event, show error
                        if (!receivedComplete) {
                            showError('Stream ended unexpectedly. The order may still have been created - check the orders list.');
                        }
                        return;
                    }

                    buffer += decoder.decode(result.value, { stream: true });
                    processBuffer();
                    read();
                }).catch(function(err) {
                    if (!receivedComplete) {
                        showError('Connection lost: ' + err.message);
                    }
                });
            }

            read();
        }).catch(function(err) {
            showError('Failed to connect: ' + err.message);
        });
    }

    function handleEvent(data) {
        if (data.type === 'progress') {
            markPreviousStepsDone();
            addOrUpdateStep(data.step, data.message, 'active');
            if (data.progress) {
                progressBar.style.width = Math.min(data.progress, 95) + '%';
            }
        } else if (data.type === 'complete') {
            markPreviousStepsDone();
            addOrUpdateStep('complete', data.message || 'Done!', 'done');
            progressBar.style.width = '100%';
            progressBar.classList.remove('bg-blue-600');
            progressBar.classList.add('bg-green-500');
            progressTitle.textContent = 'Order Generated!';
            progressSpinner.classList.add('hidden');
            progressDoneIcon.classList.remove('hidden');

            if (data.redirect_url) {
                setTimeout(function() {
                    window.location.href = data.redirect_url;
                }, 600);
            } else if (data.html) {
                setTimeout(function() {
                    document.open();
                    document.write(data.html);
                    document.close();
                }, 600);
            }
        } else if (data.type === 'error') {
            showError(data.message);
        }
    }

    function showError(message) {
        progressTitle.textContent = 'Something went wrong';
        progressSpinner.classList.add('hidden');
        progressErrorIcon.classList.remove('hidden');
        progressBar.classList.remove('bg-blue-600');
        progressBar.classList.add('bg-red-500');
        progressErrorMessage.textContent = message;
        progressError.classList.remove('hidden');

        // Mark current active step as error
        const steps = progressSteps.querySelectorAll('[id^="step-"]');
        steps.forEach(function(step) {
            if (step.querySelector('.animate-spin')) {
                const text = step.querySelector('span').textContent;
                const stepId = step.id.replace('step-', '');
                addOrUpdateStep(stepId, text, 'error');
            }
        });
    }

    // Retry button
    if (retryBtn) {
        retryBtn.addEventListener('click', function() {
            if (lastForm) {
                startStream(lastForm);
            }
        });
    }

    // Close button
    if (closeBtn) {
        closeBtn.addEventListener('click', hideOverlay);
    }

    // Intercept form submissions
    document.querySelectorAll({!! json_encode($formSelector) !!}).forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            startStream(form);
        });
    });
})();
</script>
