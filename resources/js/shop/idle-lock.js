/**
 * Idle lock for a trusted shared device (cycle 26).
 *
 * The tablet on the counter is signed in as whoever last used it, so after a
 * few quiet minutes it should stop being signed in as anybody. Only a trusted
 * device locks: a staff member's own phone is theirs to leave open.
 *
 * Deliberately not Alpine — it has to run on every Shop page including ones
 * with no Alpine scope, and it must survive Alpine failing to boot.
 */
export default function startIdleLock(seconds, url) {
    if (! (seconds > 0) || ! url) {
        return;
    }

    let timer = null;

    const fire = () => {
        window.location.href = url;
    };

    const reset = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(fire, seconds * 1000);
    };

    // The keyboard-wedge barcode scanner on the till PC types, so keydown is
    // as much a sign of life as a finger on the glass.
    ['pointerdown', 'keydown', 'touchstart', 'wheel', 'scroll'].forEach((event) => {
        window.addEventListener(event, reset, { passive: true });
    });

    reset();
}
