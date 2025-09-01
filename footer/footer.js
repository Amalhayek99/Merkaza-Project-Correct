// footer/footer.js
const track = document.querySelector('.image-track');

if (track) {
  const LOOP_MS = 35000;                     // same speed you had (35s)
  const STORAGE_KEY = 'footerScrollStart';   // shared across pages

  // Persist a single logical start time so every page can compute the same offset
  let start = parseInt(localStorage.getItem(STORAGE_KEY) || Date.now(), 10);
  localStorage.setItem(STORAGE_KEY, String(start));

  let paused = false;
  let pauseAt = 0;

  // --- NEW: measure real widths so the loop resets at exact edges ---
  const container = track.parentElement;
  let Wc = container.clientWidth;  // container width (visible area)
  let Wt = track.scrollWidth;      // track width (content width)
  let totalTravel = Wt + Wc;       // distance from fully right to fully left

  function recalc() {
    Wc = container.clientWidth;
    Wt = track.scrollWidth;
    totalTravel = Wt + Wc;
  }
  // recalc on load/resize (images/fonts can change widths)
  window.addEventListener('load', recalc);
  window.addEventListener('resize', recalc);

  function tick() {
    if (!paused) {
      // keep widths current (cheap; avoids drift if images load late)
      recalc();

      const elapsed = (Date.now() - start) % LOOP_MS;
      const progress = elapsed / LOOP_MS;    // 0..1

      // OLD (percent based; can drift): const offset = 100 - progress * 200;
      // NEW (pixel accurate): start just off-screen right ( +Wc ), travel to -Wt
      const x = Wc - progress * totalTravel; // px

      track.style.transform = `translateX(${x}px)`;
    }
    requestAnimationFrame(tick);
  }

  // Pause on hover
  track.addEventListener('mouseenter', () => {
    paused = true;
    pauseAt = Date.now();
  });

  track.addEventListener('mouseleave', () => {
    paused = false;
    const pausedFor = Date.now() - pauseAt;
    start += pausedFor;                              // maintain continuity
    localStorage.setItem(STORAGE_KEY, String(start));
    pauseAt = 0;
  });

  // Also handle tab visibility (optional but smooth)
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      paused = true;
      pauseAt = Date.now();
    } else {
      if (pauseAt) {
        const pausedFor = Date.now() - pauseAt;
        start += pausedFor;
        localStorage.setItem(STORAGE_KEY, String(start));
        pauseAt = 0;
      }
      paused = false;
    }
  });

  requestAnimationFrame(tick);
}
