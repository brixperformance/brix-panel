// assets/js/idle-timeout.js
(function () {
  // ====== CONFIG ======
  const IDLE_MS = 890_000; // show warning 10s before 15-min timeout
  const WARNING_MS = 10_000; // 10s countdown
  const REDIRECT_URL = "/login?timeout=1";
  const LOGOUT_URL = "/logout";
  const KEEPALIVE_URL = "/keepalive";

  if (!("Swal" in window)) {
    console.warn("[idle-timeout] SweetAlert2 not found. Skipping idle logic.");
    return;
  }

  // ====== STATE ======
  let idleTO = null;
  let countdownInterval = null;
  let warningVisible = false;
  let openedAt = 0;
  const OPEN_DEBOUNCE_MS = 400; // ignore events for first 400ms after open
  const MIN_MOVE_PX = 8; // meaningful mouse movement threshold
  const MOVE_THROTTLE_MS = 120; // don't process move more than every 120ms
  let lastMove = { x: null, y: null };
  let lastMoveHandledAt = 0;

  const modalListeners = [];

  // ====== HELPERS ======
  function startIdleTimer() {
    clearTimeout(idleTO);
    idleTO = setTimeout(showWarning, IDLE_MS);
  }

  function keepAlive() {
    try {
      fetch(KEEPALIVE_URL, { method: "POST", credentials: "include" });
    } catch (_) {}
  }

  function doLogout() {
    try {
      fetch(LOGOUT_URL, { method: "POST", credentials: "include" })
        .catch(() => {})
        .finally(() => {
          window.location.href = REDIRECT_URL;
        });
    } catch (_) {
      window.location.href = REDIRECT_URL;
    }
  }

  function attachModalListener(target, type, handler) {
    if (!target) return;
    target.addEventListener(type, handler, { passive: true, capture: true });
    modalListeners.push({ target, type, handler });
  }

  function detachModalListeners() {
    modalListeners.splice(0).forEach(({ target, type, handler }) => {
      try {
        target.removeEventListener(type, handler, { capture: true });
      } catch (_) {}
    });
  }

  function closeModalCleanup() {
    if (countdownInterval) {
      clearInterval(countdownInterval);
      countdownInterval = null;
    }
    detachModalListeners();
  }

  // ====== WARNING FLOW ======
  function showWarning() {
    if (warningVisible) return;
    warningVisible = true;
    openedAt = Date.now();
    lastMove = { x: null, y: null };
    lastMoveHandledAt = 0;

    Swal.fire({
      title: "Session expiring",
      html: `You will be logged out in <b>${Math.ceil(WARNING_MS / 1000)}</b> seconds.`,
      icon: "warning",
      timer: WARNING_MS,
      timerProgressBar: true,
      showConfirmButton: false,
      allowOutsideClick: () => {
        resumeFromWarning();
        return false;
      },
      allowEscapeKey: () => {
        resumeFromWarning();
        return false;
      },
      didOpen: () => {
        const b = Swal.getHtmlContainer()?.querySelector("b");
        countdownInterval = setInterval(() => {
          const left = Swal.getTimerLeft?.() ?? WARNING_MS;
          const sec = Math.max(0, Math.ceil(left / 1000));
          if (b) b.textContent = String(sec);
        }, 200);

        const container = Swal.getContainer?.();
        const popup = Swal.getPopup?.();

        const onStrong = () => resumeFromWarning();
        ["click", "mousedown", "keydown", "touchstart", "pointerdown"].forEach((evt) => {
          attachModalListener(container, evt, onStrong);
          attachModalListener(popup, evt, onStrong);
        });

        const onMove = (e) => {
          if (Date.now() - openedAt < OPEN_DEBOUNCE_MS) return;
          if (!e.isTrusted) return;
          const now = Date.now();
          if (now - lastMoveHandledAt < MOVE_THROTTLE_MS) return;

          const x = e.clientX;
          const y = e.clientY;
          if (lastMove.x == null) {
            lastMove = { x, y };
            return;
          }
          const dx = Math.abs(x - lastMove.x);
          const dy = Math.abs(y - lastMove.y);
          lastMove = { x, y };
          if (dx >= MIN_MOVE_PX || dy >= MIN_MOVE_PX) {
            lastMoveHandledAt = now;
            resumeFromWarning();
          }
        };

        ["mousemove", "pointermove", "touchmove"].forEach((evt) => {
          attachModalListener(container, evt, onMove);
          attachModalListener(popup, evt, onMove);
        });
      },
      willClose: () => {
        closeModalCleanup();
      }
    }).then((result) => {
      if (result.dismiss === Swal.DismissReason.timer && warningVisible) {
        warningVisible = false;
        doLogout();
      }
    });
  }

  function resumeFromWarning() {
    if (!warningVisible) return;
    if (Date.now() - openedAt < OPEN_DEBOUNCE_MS) return;

    try {
      Swal.stopTimer && Swal.stopTimer();
    } catch (_) {}
    if (Swal.isVisible()) {
      try {
        Swal.close();
      } catch (_) {}
    }
    warningVisible = false;
    closeModalCleanup();

    keepAlive();
    startIdleTimer();
  }

  // ====== GLOBAL ACTIVITY ======
  function onUserActivity(e) {
    if (e && e.isTrusted === false) return;

    if (warningVisible) {
      if (["click", "mousedown", "keydown", "touchstart", "pointerdown"].includes(e.type)) {
        resumeFromWarning();
        return;
      }

      if (["mousemove", "pointermove", "touchmove", "wheel"].includes(e.type)) {
        if (Date.now() - openedAt < OPEN_DEBOUNCE_MS) return;
        const x = e.clientX ?? 0;
        const y = e.clientY ?? 0;
        if (lastMove.x == null) {
          lastMove = { x, y };
          return;
        }
        const dx = Math.abs(x - lastMove.x);
        const dy = Math.abs(y - lastMove.y);
        lastMove = { x, y };
        if (dx >= MIN_MOVE_PX || dy >= MIN_MOVE_PX) resumeFromWarning();
        return;
      }
      return;
    }

    startIdleTimer();
  }

  const GLOBAL_EVENTS = [
    "click", "mousedown", "keydown", "touchstart", "pointerdown",
    "mousemove", "pointermove", "touchmove", "wheel"
  ];

  GLOBAL_EVENTS.forEach((evt) => {
    window.addEventListener(evt, onUserActivity, { passive: true, capture: true });
    document.addEventListener(evt, onUserActivity, { passive: true, capture: true });
  });

  document.addEventListener("visibilitychange", () => {
    if (!document.hidden && !warningVisible) startIdleTimer();
  }, { passive: true });

  // ====== BOOT ======
  startIdleTimer();
})();
