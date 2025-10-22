import './bootstrap';
import Alpine from 'alpinejs';
import './modules/order-date-range-picker';

// Dual Alpine.js approach:
// - Livewire pages use their bundled Alpine.js (inject_assets: true)
// - Non-Livewire pages use this global Alpine.js instance
// Conditional loading prevents "multiple instances" error

if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
    console.log('Alpine.js started for non-Livewire pages');
} else {
    console.log('Alpine.js already loaded by Livewire');
}
