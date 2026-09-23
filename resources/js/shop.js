/**
 * Shop mode behaviour.
 *
 * Loaded before app.js, which starts Alpine synchronously on evaluation, so
 * every Alpine.data(...) registration must happen inside this alpine:init
 * listener rather than at module scope.
 */
document.addEventListener('alpine:init', () => {
    /* shop components register here in later cycles */
});
