// Liquid Glass: the light on the rim of a glass surface follows the pointer, like a reflection that moves with it.
// Only the surface under the pointer is touched, once per frame: nothing runs while the pointer does not move.
(function () {
    'use strict';

    if (!window.matchMedia || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (window.matchMedia('(hover: none)').matches) return;

    var SURFACES = '.lg-glass, .lg-rim, .box, .pd-tile';
    var current = null;
    var frame = 0;
    var last = null;

    function forget(el) {
        el.style.removeProperty('--lg-x');
        el.style.removeProperty('--lg-y');
        el.classList.remove('lg-lit');
    }

    function paint() {
        frame = 0;
        var target = last && last.target && last.target.closest ? last.target.closest(SURFACES) : null;
        if (current && current !== target) forget(current);
        current = target;
        if (!target) return;
        var box = target.getBoundingClientRect();
        target.style.setProperty('--lg-x', Math.round(last.clientX - box.left) + 'px');
        target.style.setProperty('--lg-y', Math.round(last.clientY - box.top) + 'px');
        target.classList.add('lg-lit');
    }

    document.addEventListener('pointermove', function (event) {
        last = event;
        if (!frame) frame = window.requestAnimationFrame(paint);
    }, { passive: true });

    document.addEventListener('pointerleave', function () {
        if (current) forget(current);
        current = null;
    });
})();
