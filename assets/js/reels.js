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
 * Dependencies:
 *   - Swiper v11   (homepage carousel + popup)
 *   - hls.js 1.5.x (HLS playback on Chrome / Firefox / Edge — Safari/iOS use native HLS)
 */

/* ==========================================================================
   0. SHARED — attach an HLS source (Cloudflare Stream) to a <video> element.
   ========================================================================== */
window.floatReels = window.floatReels || {};

/**
 * Wire a <video data-hls="..."> element up to its source.
 * - Safari / iOS  : native HLS via <video src>.
 * - Other browsers: hls.js when available.
 * Idempotent: calling twice on the same element is a no-op.
 */
window.floatReels.attachHls = function (video) {
  if (!video || video.__flReelsAttached) return;
  var url = video.getAttribute('data-hls');
  if (!url) return;

  // Native HLS (Safari desktop, iOS) — cheapest path.
  if (video.canPlayType('application/vnd.apple.mpegurl')) {
    video.src = url;
    video.__flReelsAttached = true;
    // iOS Safari sometimes needs an explicit .load() to render the poster
    // frame after the src swap. Harmless on other browsers with native HLS.
    try { video.load(); } catch (e) {}
    return;
  }

  // hls.js for Chromium / Firefox.
  //
  // IMPORTANT: never call video.load() after attachMedia() — it detaches the
  // MediaSource that hls.js just wired up and playback silently drops to an
  // empty frame. Any external caller that used to pair attachHls() with a
  // defensive .load() must drop it for this branch.
  if (window.Hls && window.Hls.isSupported()) {
    var hls = new window.Hls({
      capLevelToPlayerSize: true, // never download a variant larger than the rendered size
      maxBufferLength: 10,        // keep the forward buffer tight for mobile data
      startLevel: -1              // auto-select start bitrate
    });
    hls.loadSource(url);
    hls.attachMedia(video);
    video.__flReelsAttached = true;
    video.__flHls = hls;
    return;
  }

  // Last resort: hand the manifest to the browser and hope for the best.
  video.src = url;
  video.__flReelsAttached = true;
};

/**
 * Play a <video>, retrying on `canplay` if the first attempt is rejected
 * because the source isn't buffered yet (common with hls.js on slow
 * connections, or Safari still fetching the manifest).
 */
window.floatReels.play = function (video) {
  if (!video) return;
  var attempt = function () { return video.play(); };
  var onCanPlay = function () {
    video.removeEventListener('canplay', onCanPlay);
    attempt().catch(function () {});
  };
  attempt().catch(function () {
    video.addEventListener('canplay', onCanPlay);
  });
};

/* ==========================================================================
   1. REELS SLIDER — interaction-gated playback
   --------------------------------------------------------------------------
   Carousel cards may carry an ACF thumbnail overlay (`<img.reel-card__thumb-
   nail>`) sitting on top of the <video>. Both layers ship with `preload="none"`
   and no HLS source attached. The video stays inert until the user actually
   interacts with the card:

     - Desktop: mouseenter on a card triggers attach + play; mouseleave pauses
       and rewinds. The thumbnail fades via CSS (`is-revealed` class + the
       `:hover` fallback in the stylesheet).
     - Mobile : tap fires both mouseenter (sticky hover) and click. We hook
       `click` too as a defensive backup so the reveal happens even on
       browsers that don't synthesise hover events on tap. The popup module
       (#2) is what actually opens the full-screen viewer on click — these
       two effects cohabit.

   Net effect: zero video bytes for users who scroll past the section without
   ever interacting. Only the lightweight thumbnail/poster image is fetched.
   ========================================================================== */
(function () {
  'use strict';

  var container = document.querySelector('.reels-carousel');
  if (!container || !container.querySelector('.swiper-wrapper')) return;

  // Swiper still drives navigation between cards; we just don't auto-play
  // anything on slide change anymore.
  // eslint-disable-next-line no-new, no-unused-vars
  var reelsSwiper = new Swiper(container, {
    slidesPerView: 'auto',
    spaceBetween:  0,
    grabCursor:    true,
    a11y:          true,
  });

  function revealCard(card) {
    if (!card || card.classList.contains('is-revealed')) return;
    card.classList.add('is-revealed');
    var video = card.querySelector('video');
    if (!video) return;
    video.setAttribute('preload', 'metadata');
    window.floatReels.attachHls(video);
    window.floatReels.play(video);
  }

  function hideCard(card) {
    if (!card || !card.classList.contains('is-revealed')) return;
    card.classList.remove('is-revealed');
    var video = card.querySelector('video');
    if (!video) return;
    video.pause();
    if (video.__flReelsAttached) video.currentTime = 0;
  }

  function hideAllCards() {
    container.querySelectorAll('.reel-card').forEach(hideCard);
  }

  // ── Wire up reveal/hide on each card ────────────────────────────────────
  container.querySelectorAll('.reels__item').forEach(function (item) {
    var card = item.querySelector('.reel-card');
    if (!card) return;

    // Desktop hover (also fires on touchscreens via sticky-hover emulation).
    item.addEventListener('mouseenter', function () { revealCard(card); });
    item.addEventListener('mouseleave', function () { hideCard(card); });

    // Belt-and-braces for touch browsers that skip mouseenter on tap.
    // revealCard() is idempotent, so a duplicate fire is harmless.
    item.addEventListener('click', function () { revealCard(card); });
  });

  // ── Pause everything when the section scrolls out of view ───────────────
  // Prevents a hovered/tapped card from continuing to stream while it's no
  // longer on screen.
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) hideAllCards();
      });
    }, { rootMargin: '0px', threshold: 0 });
    io.observe(container);
  }
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

  // ── Lazy-loading of popup videos ────────────────────────────────────────────
  // Popup <video> elements ship with `data-hls` + `preload="none"` so nothing
  // loads at page render. We hydrate on demand (active slide + neighbours),
  // deferring to `floatReels.attachHls()` to pick the right playback path
  // (native HLS on Safari/iOS, hls.js elsewhere).
  function getPopupVideos() {
    return popup.querySelectorAll('.reels-popup__slide video');
  }

  function ensureVideoLoaded(video) {
    if (!video) return;
    if (video.__flReelsAttached) return;
    video.setAttribute('preload', 'metadata');
    // attachHls() handles .load() internally for the native-HLS path.
    // No external .load() — would detach hls.js's MediaSource on Chromium.
    window.floatReels.attachHls(video);
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
        window.floatReels.play(v);
      } else {
        v.pause();
        // Only rewind videos that have actually been attached — touching
        // currentTime on an unattached (no MediaSource) video is a no-op
        // under hls.js and can emit a warning.
        if (v.__flReelsAttached) v.currentTime = 0;
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
      if (v.__flReelsAttached) v.currentTime = 0;
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

  // Archive tiles ship with `preload="none"` + `data-hls`. We attach the HLS
  // source on the first user interaction (click) rather than page load —
  // typical archives have many cards and it would be wasteful to wire every
  // one up front. Posters are shown from the <video poster> attribute.
  document.querySelectorAll('[data-reel-archive] .reel-card__media').forEach(function (media) {
    var video   = media.querySelector('video');
    var playBtn = media.querySelector('.video__play');
    if (!video) return;

    media.addEventListener('click', function () {
      if (!video.__flReelsAttached) {
        video.setAttribute('preload', 'metadata');
        window.floatReels.attachHls(video);
      }
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
