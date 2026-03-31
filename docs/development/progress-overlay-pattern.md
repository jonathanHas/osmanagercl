# Progress Overlay Pattern

Reusable pattern for showing step-by-step loading feedback to users. Used on pages that fetch data in multiple stages (cache check, summary, transactions, chart, etc.).

## Two Implementations

We have two versions depending on the page's architecture:

| Variant | Driven by | Used on | Best for |
|---------|-----------|---------|----------|
| **Alpine.js** | `x-data` reactive state | Till Review | Pages using Alpine for all interactivity |
| **Vanilla JS + SSE** | `<x-order-progress-overlay>` component | Order Create | Server-streamed progress (long-running backend tasks) |

Choose **Alpine** when the page already uses `x-data` and progress is driven by sequential frontend fetch calls. Choose **SSE** when the backend controls the steps (e.g. processing an upload or generating a report).

---

## Alpine.js Variant (Frontend-Driven)

### 1. Add reactive data properties

```js
// Inside your x-data return object
showProgress: false,
progressDone: false,
progressTitle: 'Loading...',
progressPercent: 0,
progressSteps: [],
```

### 2. Add helper methods

```js
addStep(id, message, status = 'active') {
    const existing = this.progressSteps.find(s => s.id === id);
    if (existing) {
        existing.message = message;
        existing.status = status;
    } else {
        this.progressSteps.push({ id, message, status });
    }
},

completeStep(id, message) {
    this.addStep(id, message, 'done');
},
```

### 3. Add the overlay HTML

Place this inside the `x-data` root element, before page content:

```html
<!-- Progress Overlay -->
<div x-show="showProgress" x-transition.opacity class="fixed inset-0 z-50">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md p-8">
            <!-- Header with spinner/done icon -->
            <div class="text-center mb-6">
                <div x-show="!progressDone" class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900 mb-3">
                    <i class="fas fa-circle-notch fa-spin text-xl text-blue-600 dark:text-blue-400"></i>
                </div>
                <div x-show="progressDone" x-cloak class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 dark:bg-green-900 mb-3">
                    <i class="fas fa-check text-xl text-green-600 dark:text-green-400"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="progressTitle"></h3>
            </div>

            <!-- Progress bar -->
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-6">
                <div class="h-2 rounded-full transition-all duration-500 ease-out"
                     :class="progressDone ? 'bg-green-500' : 'bg-blue-600'"
                     :style="'width: ' + progressPercent + '%'"></div>
            </div>

            <!-- Steps list -->
            <div class="space-y-2">
                <template x-for="step in progressSteps" :key="step.id">
                    <div class="flex items-center gap-3">
                        <template x-if="step.status === 'active'">
                            <i class="fas fa-circle-notch fa-spin text-sm text-blue-600 dark:text-blue-400 w-4"></i>
                        </template>
                        <template x-if="step.status === 'done'">
                            <i class="fas fa-check text-sm text-green-600 dark:text-green-400 w-4"></i>
                        </template>
                        <template x-if="step.status === 'warning'">
                            <i class="fas fa-exclamation-triangle text-sm text-amber-500 w-4"></i>
                        </template>
                        <template x-if="step.status === 'error'">
                            <i class="fas fa-times text-sm text-red-600 w-4"></i>
                        </template>
                        <span class="text-sm"
                              :class="{
                                  'text-blue-700 dark:text-blue-300 font-medium': step.status === 'active',
                                  'text-green-700 dark:text-green-300': step.status === 'done',
                                  'text-amber-700 dark:text-amber-300': step.status === 'warning',
                                  'text-red-700 dark:text-red-300': step.status === 'error'
                              }"
                              x-text="step.message"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
```

### 4. Wire up your loading function

```js
async loadData() {
    // Show overlay
    this.showProgress = true;
    this.progressDone = false;
    this.progressTitle = 'Loading data...';
    this.progressPercent = 0;
    this.progressSteps = [];

    try {
        // Step 1
        this.addStep('validate', 'Validating cache...');
        this.progressPercent = 10;
        const status = await this.checkSomething();
        this.completeStep('validate', 'Cache valid');
        this.progressPercent = 25;

        // Step 2 - parallel tasks
        this.addStep('data', 'Loading data...');
        this.addStep('summary', 'Loading summary...');
        this.progressPercent = 40;

        await Promise.all([
            this.fetchData().then(() => {
                this.completeStep('data', '150 records loaded');
                this.progressPercent = Math.max(this.progressPercent, 60);
            }),
            this.fetchSummary().then(() => {
                this.completeStep('summary', 'Summary ready');
                this.progressPercent = Math.max(this.progressPercent, 70);
            })
        ]);

        // Done
        this.progressPercent = 100;
        this.progressDone = true;
        this.progressTitle = 'Ready';
        setTimeout(() => { this.showProgress = false; }, 600);

    } catch (error) {
        this.addStep('error', 'Failed: ' + error.message, 'error');
        this.progressTitle = 'Something went wrong';
        setTimeout(() => { this.showProgress = false; }, 3000);
    }
},
```

### Step statuses

| Status | Icon | Colour | Use when |
|--------|------|--------|----------|
| `active` | Spinning | Blue | Step is in progress |
| `done` | Checkmark | Green | Step completed successfully |
| `warning` | Triangle | Amber | Step completed but with a note (e.g. cache rebuilt) |
| `error` | X | Red | Step failed |

### Tips

- **Include counts in completion messages** - "317 transactions loaded" is better than "Transactions loaded"
- **Use `Math.max(this.progressPercent, X)`** for parallel steps so the bar never goes backwards
- **Auto-dismiss on success** with `setTimeout(() => { this.showProgress = false; }, 600)`
- **Longer dismiss on error** (3000ms) so the user can read the message
- **Update a step** by calling `addStep()` with the same `id` - it updates in place rather than adding a duplicate

---

## Vanilla JS + SSE Variant (Server-Driven)

Used via the Blade component at `resources/views/components/order-progress-overlay.blade.php`.

### Usage

```blade
<x-order-progress-overlay
    :streamUrl="route('orders.stream-create')"
    formSelector="#my-form"
/>
```

### Backend SSE format

The controller streams `text/event-stream` responses with JSON data lines:

```php
return response()->stream(function () {
    // Step progress
    echo "data: " . json_encode([
        'type' => 'progress',
        'step' => 'fetch',
        'message' => 'Fetching products...',
        'progress' => 30
    ]) . "\n\n";
    ob_flush(); flush();

    // ... do work ...

    // Complete
    echo "data: " . json_encode([
        'type' => 'complete',
        'message' => 'Order created!',
        'redirect_url' => route('orders.show', $order)
    ]) . "\n\n";
    ob_flush(); flush();

}, 200, ['Content-Type' => 'text/event-stream']);
```

### Event types

| Type | Fields | Behaviour |
|------|--------|-----------|
| `progress` | `step`, `message`, `progress` (0-100) | Shows spinning step, updates bar |
| `complete` | `message`, `redirect_url` or `html` | Shows green done state, redirects |
| `error` | `message` | Shows red error with retry/close buttons |

---

## Existing implementations

| Page | File | Variant |
|------|------|---------|
| Till Review | `resources/views/till-review/index.blade.php` | Alpine |
| Order Create | `resources/views/orders/create.blade.php` | SSE component |

---

## Design tokens (keep consistent)

- **Backdrop**: `bg-gray-900/60 backdrop-blur-sm`
- **Modal**: `bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md`
- **Progress bar height**: `h-2`
- **Bar colour active**: `bg-blue-600`
- **Bar colour done**: `bg-green-500`
- **Transition**: `transition-all duration-500 ease-out`
- **Icon size in header**: `w-12 h-12` circle, icon `text-xl`
- **Icon size in steps**: `w-4`, icon `text-sm`
- **Z-index**: `z-50`
