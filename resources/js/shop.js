/**
 * Shop mode behaviour.
 *
 * Loaded before app.js, which starts Alpine synchronously on evaluation, so
 * every Alpine.data(...) registration must happen inside this alpine:init
 * listener rather than at module scope.
 *
 * Screens under resources/views/shop/ carry no <script>; all behaviour lives in
 * resources/js/shop/*.js and is registered here.
 */
import deliveryScan from './shop/delivery-scan';
import deliverySummary from './shop/delivery-summary';
import findProduct from './shop/find-product';
import startIdleLock from './shop/idle-lock';
import fvHarvest from './shop/fv-harvest';
import fvWaste from './shop/fv-waste';
import labels from './shop/labels';
import orderReview from './shop/order-review';
import pinPad from './shop/pin-pad';
import requestEdit from './shop/request-edit';
import requestForm from './shop/requests';
import requestsBoard from './shop/requests-board';
import scanInput from './shop/scan-input';
import stockScan from './shop/stock-scan';
import vouchers from './shop/vouchers';

// Outside alpine:init: the idle lock is plain DOM and must run even if Alpine
// never boots — a tablet left signed in is the thing this prevents.
const shopRoot = document.getElementById('shop-root');
if (shopRoot?.dataset.idleLockSeconds) {
    startIdleLock(Number(shopRoot.dataset.idleLockSeconds), shopRoot.dataset.idleLockUrl);
}

document.addEventListener('alpine:init', () => {
    Alpine.data('shopScanInput', scanInput);
    Alpine.data('shopStockScan', stockScan);
    Alpine.data('shopFindProduct', findProduct);
    Alpine.data('shopDeliveryScan', deliveryScan);
    Alpine.data('shopDeliverySummary', deliverySummary);
    Alpine.data('shopLabels', labels);
    Alpine.data('shopRequestForm', requestForm);
    Alpine.data('shopRequestEdit', requestEdit);
    Alpine.data('shopRequestsBoard', requestsBoard);
    Alpine.data('shopVouchers', vouchers);
    Alpine.data('shopFvWaste', fvWaste);
    Alpine.data('shopFvHarvest', fvHarvest);
    Alpine.data('shopPinPad', pinPad);
    Alpine.data('shopOrderReview', orderReview);
});
