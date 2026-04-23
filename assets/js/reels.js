/**
 * float Reels — JavaScript
 *
 * Three independent modules, each wrapped in an IIFE:
 *
 *   1. REELS SLIDER      — Horizontal Swiper carousel on the homepage.
 *                          Active slide video plays; others pause.
 *
 *   2. REELS POPUP       — Full-screen viewer opened when a carousel item is clicked.
 *                          Mobile : vertical Swiper, full-screen video.
 *                          Desktop: horizontal Swiper, centred 9:16 column + blurred bg.
 *
 *   3. REELS ARCHIVE     — Click-to-play/pause on the /reels/ archive grid.
 *
 * Dependency: Swiper v11 (loaded separately via wp_enqueue_script / CDN).
 */

/* iOS Safari parfois ignore `preload="metadata"` et n'affiche pas le poster
   tant qu'on n'a pas explicitement appelé `.load()`. On le fait pour chaque
   vidéo du carrousel une fois le DOM prêt. */
(function () {
  'use strict';
  var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
  if (!isIOS) return;

  function primeCarouselVideos() {
    document.querySelectorAll('.reel-card__video').forEach(function (v) {
      try { v.load(); } catch (e) {}
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', primeCarouselVideos);
  } else {
    primeCarouselVideos();
  }
})();

/* ==========================================================================
   1. REELS SLIDER
   ========================================================================== */
(function () {
  'use strict';

  var container = document.querySelector('.reels-carousel');
  if (!container || !container.querySelector('.swiper-wrapper')) return;

  var reelsSwiper = new Swiper(container, {
    slidesPerView: 'auto',
    spaceBetween:  0,
    grabCursor:    true,
    a11y:          true,
  });

  function playVideo(video) {
    video.play().catch(function () {});
  }

  function syncVideo(index) {
    container.querySelectorAll('.reels__item').forEach(function (item, i) {
      var video = item.querySelector('video');
      if (!video) return;
      if (i === index) {
        playVideo(video);
      } else {
        video.pause();
        video.currentTime = 0;
      }
    });
  }

  reelsSwiper.on('slideChangeTransitionEnd', function () {
    syncVideo(reelsSwiper.activeIndex);
  });

  syncVideo(0);
})();


/* ==========================================================================
   2. REELS POPUP — full-screen Swiper
   Mobile  (< 768 px): vertical scroll, full-screen video.
   Desktop (≥ 768 px): horizontal scroll, centred 9:16 video + blurred bg
                       + prev / next arrow buttons.
   ========================================================================== */
(function () {
  'use strict';

  var popup = document.getElementById('reels-popup');
  if (!popup) return;

  var closeBtn  = popup.querySelector('.reels-popup__close');
  var muteBtn   = popup.querySelector('.reels-popup__mute');
  var prevBtn   = popup.querySelector('.reels-popup__nav--prev');
  var nextBtn   = popup.querySelector('.reels-popup__nav--next');
  var swiperEl  = popup.querySelector('.reels-popup__swiper');
  var popupSwiper = null;
  var isMuted     = true; // Persists across slides.

  // ── Lazy-loading des vidéos popup ────────────────────────────────────────────
  // Les <video> du popup sont rendues avec `data-src` (pas de `src`) et
  // `preload="none"` pour éviter un double chargement au render de la page.
  // On hydrate à la demande : slide active + voisines.
  function getPopupVideos() {
    return popup.querySelectorAll('.reels-popup__slide video');
  }

  function ensureVideoLoaded(video) {
    if (!video) return;
    if (video.getAttribute('src')) return; // déjà hydratée
    var src = video.getAttribute('data-src');
    if (!src) return;
    video.setAttribute('src', src);
    // preload="none" empêchait tout chargement ; on passe à metadata et
    // on déclenche explicitement le chargement pour iOS Safari.
    video.setAttribute('preload', 'metadata');
    try { video.load(); } catch (e) {}
  }

  function hydrateAround(index) {
    var videos = getPopupVideos();
    if (!videos.length) return;
    [index - 1, index, index + 1].forEach(function (i) {
      if (i >= 0 && i < videos.length) ensureVideoLoaded(videos[i]);
    });
  }

  // ── Responsive direction ────────────────────────────────────────────────────
  var mobileMedia = window.matchMedia('(max-width: 767px)');

  function getSwiperDirection() {
    return mobileMedia.matches ? 'vertical' : 'horizontal';
  }

  // ── Mute button ─────────────────────────────────────────────────────────────
  function updateMuteBtn() {
    if (!muteBtn) return;
    muteBtn.dataset.muted = isMuted ? 'true' : 'false';
    muteBtn.setAttribute('aria-label', isMuted ? 'Unmute' : 'Mute');
  }

  function applyMute(video) {
    video.muted = isMuted;
  }

  // ── Nav button disabled states ───────────────────────────────────────────────
  function updateNavBtns() {
    if (prevBtn) prevBtn.disabled = popupSwiper ? popupSwiper.isBeginning : true;
    if (nextBtn) nextBtn.disabled = popupSwiper ? popupSwiper.isEnd       : true;
  }

  // ── Play active video, pause others ─────────────────────────────────────────
  function syncPopupVideo() {
    var active = popupSwiper.activeIndex;
    hydrateAround(active);
    getPopupVideos().forEach(function (v, i) {
      if (i === active) {
        applyMute(v);
        v.play().catch(function () {});
      } else {
        v.pause();
        if (v.getAttribute('src')) v.currentTime = 0;
      }
    });
    updateNavBtns();
  }

  // ── Lazy-init Swiper ────────────────────────────────────────────────────────
  function initSwiper() {
    if (popupSwiper) return;

    popupSwiper = new Swiper(swiperEl, {
      direction:     getSwiperDirection(),
      slidesPerView: 1,
      speed:         400,
      mousewheel:    true,
      keyboard:      { enabled: true },
      grabCursor:    true,
      a11y:          true,
    });

    popupSwiper.on('slideChangeTransitionEnd', syncPopupVideo);

    // Adapt direction on viewport change (rotation, resize).
    var onBreakpointChange = function () {
      if (!popupSwiper) return;
      var next = getSwiperDirection();
      if (popupSwiper.params.direction === next) return;
      popupSwiper.changeDirection(next, false);
      popupSwiper.update();
    };

    if (typeof mobileMedia.addEventListener === 'function') {
      mobileMedia.addEventListener('change', onBreakpointChange);
    } else {
      mobileMedia.addListener(onBreakpointChange); // Safari < 14
    }

    // Auto-advance when video ends.
    popup.querySelectorAll('.reels-popup__slide video').forEach(function (v, i) {
      v.addEventListener('ended', function () {
        if (i !== popupSwiper.activeIndex) return;
        popupSwiper.isEnd ? closePopup() : popupSwiper.slideNext();
      });
    });
  }

  // ── Open ────────────────────────────────────────────────────────────────────
  function openPopup(index) {
    initSwiper();
    popup.removeAttribute('hidden');
    document.body.setAttribute('data-popup-open', '');
    popupSwiper.slideTo(index, 0);
    requestAnimationFrame(function () {
      syncPopupVideo();
      updateNavBtns();
    });
    if (closeBtn) closeBtn.focus();
  }

  // ── Close ───────────────────────────────────────────────────────────────────
  function closePopup() {
    popup.setAttribute('hidden', '');
    document.body.removeAttribute('data-popup-open');
    popup.querySelectorAll('video').forEach(function (v) {
      v.pause();
      // Ne pas toucher currentTime sur une vidéo non hydratée (pas de src) :
      // cela déclencherait un warning ou, pire, un load inutile.
      if (v.getAttribute('src')) v.currentTime = 0;
    });
  }

  // ── Mute toggle ─────────────────────────────────────────────────────────────
  if (muteBtn) {
    muteBtn.addEventListener('click', function () {
      isMuted = !isMuted;
      updateMuteBtn();
      var activeIndex = popupSwiper ? popupSwiper.activeIndex : 0;
      var active = popup.querySelectorAll('.reels-popup__slide video')[activeIndex];
      if (active) applyMute(active);
    });
  }

  // ── Prev / Next buttons ──────────────────────────────────────────────────────
  if (prevBtn) {
    prevBtn.addEventListener('click', function () {
      if (!popupSwiper) return;
      popupSwiper.isBeginning ? closePopup() : popupSwiper.slidePrev();
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', function () {
      if (!popupSwiper) return;
      popupSwiper.isEnd ? closePopup() : popupSwiper.slideNext();
    });
  }

  // ── Carousel item click / keydown ────────────────────────────────────────────
  document.querySelectorAll('.reels__item[data-reel-index]').forEach(function (item) {
    item.addEventListener('click', function () {
      openPopup(parseInt(item.dataset.reelIndex, 10));
    });
    item.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openPopup(parseInt(item.dataset.reelIndex, 10));
      }
    });
  });

  // ── Close button + Escape ────────────────────────────────────────────────────
  if (closeBtn) {
    closeBtn.addEventListener('click', closePopup);
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !popup.hasAttribute('hidden')) {
      closePopup();
    }
  });
})();


/* ==========================================================================
   3. REELS ARCHIVE — click to play / pause
   ========================================================================== */
(function () {
  'use strict';

  document.querySelectorAll('[data-reel-archive] .reel-card__media').forEach(function (media) {
    var video   = media.querySelector('video');
    var playBtn = media.querySelector('.video__play');
    if (!video) return;

    media.addEventListener('click', function () {
      if (video.paused) {
        video.play().catch(function () {});
        if (playBtn) playBtn.style.opacity = '0';
      } else {
        video.pause();
        if (playBtn) playBtn.style.opacity = '1';
      }
    });
  });
})();
