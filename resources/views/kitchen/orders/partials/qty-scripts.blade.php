{{--
    Shared quantity controls for the kitchen order pages (create + standing).
    Plain JS, no Alpine. Included via @pushOnce('scripts').

    - .qty-decrease / .qty-increase buttons with data-target="<input id>"
    - .qty-input number inputs (min 0)
    - recalcTotals(): counts inputs > 0, sums them, toggles row highlight
      (.qty-row gets bg-indigo-50) and disables #confirm-order while the total is 0
    - #clear-all-qty sets every input to 0
--}}
@pushOnce('scripts')
<script>
    (function () {
        function clampQty(value) {
            var n = parseInt(value, 10);
            if (isNaN(n) || n < 0) n = 0;
            if (n > 999) n = 999;
            return n;
        }

        function recalcTotals() {
            var inputs = document.querySelectorAll('.qty-input');
            var lines = 0;
            var cases = 0;

            inputs.forEach(function (input) {
                var qty = clampQty(input.value);
                if (qty > 0) {
                    lines += 1;
                    cases += qty;
                }
                var row = input.closest('.qty-row');
                if (row) {
                    row.classList.toggle('bg-indigo-50', qty > 0);
                }
            });

            var linesEl = document.getElementById('total-lines');
            var casesEl = document.getElementById('total-cases');
            if (linesEl) linesEl.textContent = lines;
            if (casesEl) casesEl.textContent = cases;

            var confirmBtn = document.getElementById('confirm-order');
            if (confirmBtn) {
                confirmBtn.disabled = cases === 0;
            }
        }

        window.kitchenOrderRecalcTotals = recalcTotals;

        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                var btn = event.target.closest('.qty-decrease, .qty-increase');
                if (!btn) return;

                var input = document.getElementById(btn.dataset.target);
                if (!input) return;

                var current = clampQty(input.value);
                input.value = btn.classList.contains('qty-increase')
                    ? Math.min(999, current + 1)
                    : Math.max(0, current - 1);

                recalcTotals();
            });

            document.addEventListener('input', function (event) {
                if (event.target.classList && event.target.classList.contains('qty-input')) {
                    recalcTotals();
                }
            });

            document.addEventListener('change', function (event) {
                if (event.target.classList && event.target.classList.contains('qty-input')) {
                    // Normalise blanks / negatives / decimals on blur
                    event.target.value = clampQty(event.target.value);
                    recalcTotals();
                }
            });

            var clearAll = document.getElementById('clear-all-qty');
            if (clearAll) {
                clearAll.addEventListener('click', function () {
                    document.querySelectorAll('.qty-input').forEach(function (input) {
                        input.value = 0;
                    });
                    recalcTotals();
                });
            }

            // Pre-filled standing values need the totals populated on load.
            recalcTotals();
        });
    })();
</script>
@endPushOnce
