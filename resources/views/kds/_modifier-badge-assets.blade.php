{{--
    Modifier badge assets: CSS, the SVG symbol sprite, and the JS renderer.

    Include this once per page that renders modifier badges (currently
    resources/views/kds/index.blade.php and resources/views/coffee/metadata.blade.php),
    in the body, before anything that calls window.kdsModifierBadgeHtml().

    ⚠ LOCKSTEP: this file and resources/views/components/kds/modifier-badge.blade.php
    must emit the same markup. The JS path re-renders every card after an SSE or
    poll update, so a mismatch shows up as a badge changing shape a second after
    first paint. The geometry both paths share lives in
    App\Models\CoffeeProductMetadata::BADGE_KINDS / ::BADGE_DECOS.

    Shapes, colours, offsets and paths are copied verbatim from
    ModifierBadge.dc.html in the Claude Design project "KDS Modifier Icons"
    (47420d2a-5362-4f13-bc11-44b10205f5bc). The design's `size`/`deco` props are
    not exposed: badges render at --mb-fs with decorations always on.
--}}
<style>
    .mb {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 1.9em;
        padding: 0 0.8em;
        font-family: 'Public Sans', 'Geist', system-ui, -apple-system, sans-serif;
        font-weight: 800;
        font-size: var(--mb-fs, 15px);
        line-height: 1;
        white-space: nowrap;
        isolation: isolate;
        color: var(--mb-ink);
    }
    .mb__bg,
    .mb__deco {
        position: absolute;
        z-index: -1;
        overflow: visible;
        pointer-events: none;
    }
    /* Belt and braces with the overflow="visible" attribute on each <symbol>:
       a symbol establishes its own viewport and would otherwise clip the milk
       puddle, which overshoots its viewBox. */
    #mb-sprite symbol { overflow: visible; }

    /* Per-family placement of the background shape (design offsets). */
    .mb--f-ice .mb__bg   { left: -0.15em; top: -0.1em; width: calc(100% + 0.3em); height: calc(100% + 0.2em); }
    .mb--f-milk .mb__bg  { left: -0.3em;  top: -0.2em; width: calc(100% + 0.6em); height: calc(100% + 0.4em); }
    .mb--f-syrup .mb__bg { left: -0.1em;  top: -0.1em; width: calc(100% + 0.2em); height: calc(100% + 0.8em); }
    .mb--f-shot .mb__bg  { left: -0.1em;  top: -0.1em; width: calc(100% + 0.2em); height: calc(100% + 0.2em); }

    .mb--f-ice .mb__label,
    .mb--f-shot .mb__label { letter-spacing: 0.02em; }

    /* Per-kind colours. Custom properties inherit into the <use> shadow tree,
       which is what lets one sprite serve all twelve kinds. */
    .mb--ice      { --mb-fill: #dcf0fb; --mb-stroke: #7fbfe3; --mb-ink: #0c4867; }
    .mb--oat      { --mb-fill: #f6eedb; --mb-stroke: #d6c49c; --mb-ink: #5e4722; }
    .mb--almond   { --mb-fill: #f5e6d6; --mb-stroke: #d4b394; --mb-ink: #6a3f1e; }
    .mb--soy      { --mb-fill: #f8f5e4; --mb-stroke: #cfc79a; --mb-ink: #57501d; }
    .mb--coconut  { --mb-fill: #ffffff; --mb-stroke: #c9c3b8; --mb-ink: #3d3a35; }
    .mb--whole    { --mb-fill: #ffffff; --mb-stroke: #b9c3cc; --mb-ink: #2c3844; }
    .mb--caramel  { --mb-fill: #dd963f; --mb-stroke: #b06d1c; --mb-ink: #3a1d04; }
    .mb--vanilla  { --mb-fill: #f4e3ad; --mb-stroke: #cdb065; --mb-ink: #54400c; }
    .mb--hazelnut { --mb-fill: #8a5733; --mb-stroke: #6a3f20; --mb-ink: #ffffff; }
    .mb--mocha    { --mb-fill: #4a2c1c; --mb-stroke: #2f1b10; --mb-ink: #ffffff; }
    .mb--shot     { --mb-fill: #3a2416; --mb-stroke: #24150c; --mb-ink: #ffffff; --mb-crema: #c98c55; --mb-dash: none; }
    .mb--decaf    { --mb-fill: #f1e8de; --mb-stroke: #6b4a33; --mb-ink: #4a2f1c; --mb-crema: #e3cdb3; --mb-dash: 5 3; }
</style>

{{-- Symbol sprite. stroke-dasharray must be a style, not an attribute, or var() is ignored. --}}
<svg id="mb-sprite" width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <symbol id="mb-shape-ice" viewBox="0 0 120 40" preserveAspectRatio="none" overflow="visible">
        <rect x="1" y="1" width="118" height="38" rx="7" style="fill:var(--mb-fill);stroke:var(--mb-stroke)" stroke-width="1.5" vector-effect="non-scaling-stroke"></rect>
        <path d="M1 29 H119 V32 Q119 39 112 39 H8 Q1 39 1 32Z" fill="#c3e3f5"></path>
        <path d="M8 7 H34" stroke="#ffffff" stroke-width="3" stroke-linecap="round" vector-effect="non-scaling-stroke"></path>
        <path d="M8 13 V20" stroke="#ffffff" stroke-width="3" stroke-linecap="round" vector-effect="non-scaling-stroke" opacity="0.8"></path>
    </symbol>
    <symbol id="mb-shape-milk" viewBox="0 0 120 40" preserveAspectRatio="none" overflow="visible">
        <path d="M9 21 C3 10 18 2 36 5 C52 0 78 3 94 4 C111 5 119 13 115 21 C120 32 106 40 88 37 C72 42 46 39 30 38 C13 41 2 32 9 21Z" style="fill:var(--mb-fill);stroke:var(--mb-stroke)" stroke-width="1.5" vector-effect="non-scaling-stroke"></path>
        <ellipse cx="30" cy="11" rx="13" ry="2.6" fill="#ffffff" opacity="0.85"></ellipse>
    </symbol>
    <symbol id="mb-shape-syrup" viewBox="0 0 120 46" preserveAspectRatio="none" overflow="visible">
        <path d="M10 2 H110 Q118 2 118 10 V28 Q118 36 110 36 H92 Q89 36 89 39 V40 Q89 44 85.5 44 Q82 44 82 40 V39 Q82 36 79 36 H36 Q33 36 33 39 V41 Q33 46 28.5 46 Q24 46 24 41 V39 Q24 36 21 36 H10 Q2 36 2 28 V10 Q2 2 10 2Z" style="fill:var(--mb-fill);stroke:var(--mb-stroke)" stroke-width="1.5" vector-effect="non-scaling-stroke"></path>
        <path d="M10 8 H48" stroke="#ffffff" stroke-width="3" stroke-linecap="round" opacity="0.5" vector-effect="non-scaling-stroke"></path>
    </symbol>
    <symbol id="mb-shape-shot" viewBox="0 0 120 40" preserveAspectRatio="none" overflow="visible">
        <rect x="1" y="1" width="118" height="38" rx="10" style="fill:var(--mb-fill);stroke:var(--mb-stroke);stroke-dasharray:var(--mb-dash)" stroke-width="1.5" vector-effect="non-scaling-stroke"></rect>
        <path d="M1.5 11 Q1.5 1.5 11 1.5 H109 Q118.5 1.5 118.5 11 C102 14 92 8 76 11 C60 14 46 8 30 11 C18 13 10 9 1.5 11Z" style="fill:var(--mb-crema)"></path>
    </symbol>

    {{-- Ice cubes use fixed colours in the design, so they need no variables. --}}
    <symbol id="mb-deco-cube" viewBox="0 0 20 20" overflow="visible">
        <rect x="1.5" y="1.5" width="17" height="17" rx="4" fill="#eaf7fe" stroke="#7fbfe3" stroke-width="1.5"></rect>
        <path d="M5 5.5 H10" stroke="#ffffff" stroke-width="2" stroke-linecap="round"></path>
    </symbol>
    <symbol id="mb-deco-cube-plain" viewBox="0 0 20 20" overflow="visible">
        <rect x="1.5" y="1.5" width="17" height="17" rx="4" fill="#eaf7fe" stroke="#7fbfe3" stroke-width="1.8"></rect>
    </symbol>
    {{-- Milk droplets take the kind's own colours. Two symbols because the
         design uses r=8.5/sw=2 once and r=8/sw=2.5 twice. --}}
    <symbol id="mb-deco-dot-a" viewBox="0 0 20 20" overflow="visible">
        <circle cx="10" cy="10" r="8.5" style="fill:var(--mb-fill);stroke:var(--mb-stroke)" stroke-width="2"></circle>
    </symbol>
    <symbol id="mb-deco-dot-b" viewBox="0 0 20 20" overflow="visible">
        <circle cx="10" cy="10" r="8" style="fill:var(--mb-fill);stroke:var(--mb-stroke)" stroke-width="2.5"></circle>
    </symbol>
    <symbol id="mb-deco-drop" viewBox="0 0 10 14" overflow="visible">
        <path d="M5 0.8 C6.5 3.5 9.2 6.5 9.2 9.2 A4.2 4.2 0 0 1 0.8 9.2 C0.8 6.5 3.5 3.5 5 0.8Z" style="fill:var(--mb-fill);stroke:var(--mb-stroke)" stroke-width="1"></path>
    </symbol>
</svg>

<script>
    window.KDS_BADGE_KINDS = @js(\App\Models\CoffeeProductMetadata::BADGE_KINDS);
    window.KDS_BADGE_DECOS = @js(\App\Models\CoffeeProductMetadata::BADGE_DECOS);

    /**
     * Render one modifier badge. Mirrors the x-kds.modifier-badge Blade
     * component — change both together. An unknown or empty kind falls back to
     * the plain .item__mod chip, which is what options without a badge get.
     * (Written without angle brackets on purpose: Blade would compile a literal
     * component tag here, even inside a JS comment.)
     */
    window.kdsModifierBadgeHtml = function (kind, label) {
        // Local on purpose: this partial must not depend on the host page's helpers.
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c =>
            ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const text = esc(label);
        const known = kind && Object.prototype.hasOwnProperty.call(window.KDS_BADGE_KINDS, kind);
        if (!known) {
            return `<span class="item__mod">${text}</span>`;
        }
        const family = window.KDS_BADGE_KINDS[kind].family;
        const decos = (window.KDS_BADGE_DECOS[family] || []).map(d =>
            `<svg class="mb__deco" style="${d.style}" aria-hidden="true"><use href="#${d.symbol}"/></svg>`
        ).join('');
        return `<span class="mb mb--f-${family} mb--${kind}">`
            + `<svg class="mb__bg" aria-hidden="true"><use href="#mb-shape-${family}"/></svg>`
            + decos
            + `<span class="mb__label">${text}</span>`
            + `</span>`;
    };
</script>
