/* ============================================================
   AstroTherapia — Celestial Guidance
   Interactions: starfield generation + parallax depth
   ============================================================ */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- Generate twinkling stars ---------------------------- */
  function buildStars() {
    var layer = document.querySelector('.twinkle');
    if (!layer) return;

    var count = window.innerWidth < 720 ? 40 : 75;
    var frag = document.createDocumentFragment();

    for (var i = 0; i < count; i++) {
      var s = document.createElement('span');
      s.className = 'star';
      var size = (Math.random() * 1.6 + 0.8).toFixed(2);   // 0.8 – 2.4 px
      s.style.left = (Math.random() * 100).toFixed(2) + '%';
      s.style.top = (Math.random() * 100).toFixed(2) + '%';
      s.style.width = size + 'px';
      s.style.height = size + 'px';
      s.style.setProperty('--dur', (Math.random() * 3 + 4).toFixed(2) + 's'); // 4 – 7 s
      s.style.setProperty('--delay', (-Math.random() * 7).toFixed(2) + 's');
      // dim the faintest stars a touch
      if (Math.random() < 0.4) s.style.boxShadow = '0 0 5px 1px #bcd6f2';
      frag.appendChild(s);
    }
    layer.appendChild(frag);
  }

  /* ---- Mouse parallax (depth on background + solar) -------- */
  function bindParallax() {
    if (reduceMotion) return;

    var stage = document.querySelector('.stage');
    var solar = document.querySelector('[data-parallax="solar"]');
    var layers = Array.prototype.slice.call(document.querySelectorAll('[data-depth]'));
    if (!stage) return;

    var targetX = 0, targetY = 0, curX = 0, curY = 0;
    var raf = null;

    function onMove(e) {
      var r = stage.getBoundingClientRect();
      targetX = (e.clientX - r.left) / r.width - 0.5;   // -0.5 … 0.5
      targetY = (e.clientY - r.top) / r.height - 0.5;
      if (!raf) raf = requestAnimationFrame(tick);
    }

    function tick() {
      // ease toward the target for a smooth, weighty feel
      curX += (targetX - curX) * 0.08;
      curY += (targetY - curY) * 0.08;

      layers.forEach(function (el) {
        var d = parseFloat(el.getAttribute('data-depth')) || 0;
        el.style.transform = 'translate(' + (-curX * d * 32).toFixed(2) + 'px,' +
                                            (-curY * d * 22).toFixed(2) + 'px)';
      });

      if (solar) {
        solar.style.setProperty('--px', (-curX * 20).toFixed(2) + 'px');
        solar.style.setProperty('--py', (-curY * 12).toFixed(2) + 'px');
      }

      if (Math.abs(targetX - curX) > 0.001 || Math.abs(targetY - curY) > 0.001) {
        raf = requestAnimationFrame(tick);
      } else {
        raf = null;
      }
    }

    stage.addEventListener('mousemove', onMove);
    stage.addEventListener('mouseleave', function () {
      targetX = 0; targetY = 0;
      if (!raf) raf = requestAnimationFrame(tick);
    });
  }

  function init() {
    buildStars();
    bindParallax();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
