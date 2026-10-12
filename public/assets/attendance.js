/* ParkDesk attendance. Locations are requested only following a deliberate button click.
 * Fresh location is captured for both Punch In and Punch Out. No continuous background tracking.
 */
(() => {
  'use strict';
  const geoErrors = {
    1: 'Location permission was denied. Please allow location access for this site in your browser settings, then tap the button again.',
    2: 'Your location is unavailable. Turn on Location/GPS in your phone settings and ensure network connectivity.',
    3: 'Location acquisition timed out. Move to an open area and try again.'
  };

  function locate() {
    return new Promise((resolve, reject) => {
      if (!window.isSecureContext && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        return reject(new Error('Browser geolocation requires a secure context (HTTPS or localhost). If testing from a phone on LAN, use an HTTPS tunnel or configure your browser to treat the local origin as secure.'));
      }
      if (!navigator.geolocation) {
        return reject(new Error('This browser does not support location. Please use a modern mobile browser (Chrome, Safari, Firefox).'));
      }
      navigator.geolocation.getCurrentPosition(pos => {
        const c = pos.coords;
        if (![c.latitude, c.longitude, c.accuracy].every(Number.isFinite) || c.accuracy < 0) {
          return reject(new Error('The device returned invalid location coordinates. Please try again.'));
        }
        resolve({latitude: c.latitude, longitude: c.longitude, accuracy_m: c.accuracy});
      }, error => {
        reject(new Error(geoErrors[error.code] || 'Could not acquire device location. Please enable GPS and try again.'));
      }, {enableHighAccuracy: true, timeout: 20000, maximumAge: 0});
    });
  }

  function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const h = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
  }

  const card = document.querySelector('[data-attendance]');
  if (card) {
    const punchInBtn = card.querySelector('[data-punch-in]');
    const punchOutBtn = card.querySelector('[data-punch-out]');
    const punchGenericBtn = card.querySelector('[data-punch]');
    const checkBtn = card.querySelector('[data-check-location]');
    const title = card.querySelector('[data-location-title]');
    const detail = card.querySelector('[data-location-detail]');
    const map = card.querySelector('[data-location-map]');
    const feedback = card.querySelector('[data-punch-feedback]');
    const box = card.querySelector('[data-location-box]');
    const note = card.querySelector('[data-punch-note]');

    const accuracyLimit = Number(card.dataset.accuracyLimit) || 100;
    let busy = false, pending = null, navigating = false;
    const serverTime = Date.parse(card.dataset.serverNow), loadedAt = performance.now();

    let formatter;
    try {
      formatter = new Intl.DateTimeFormat('en-US', {
        timeZone: card.dataset.timezone || 'Asia/Kolkata',
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
      });
    } catch {
      formatter = null;
    }

    function clock() {
      const ts = serverTime + performance.now() - loadedAt;
      if (!Number.isFinite(ts)) return;
      const node = card.querySelector('[data-clock]');
      if (node && formatter) {
        const parts = formatter.formatToParts(new Date(ts));
        node.replaceChildren(document.createTextNode(parts.filter(p => p.type !== 'dayPeriod').map(p => p.value).join('').trim() + ' '));
        const am = document.createElement('small');
        am.textContent = parts.find(p => p.type === 'dayPeriod')?.value || '';
        node.append(am);
      }
      const elapsed = card.querySelector('[data-elapsed]'), start = Date.parse(card.dataset.startedAt);
      if (elapsed && Number.isFinite(start)) {
        const minutes = Math.floor(Math.max(0, ts - start) / 60000);
        elapsed.textContent = `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m`;
      }
    }
    clock();
    setInterval(clock, 1000);

    function message(text, isError = false) {
      if (!feedback) return;
      feedback.hidden = false;
      feedback.textContent = text;
      feedback.classList.toggle('error', isError);
      feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function setBusy(value) {
      busy = value;
      if (punchInBtn) punchInBtn.disabled = value || card.dataset.hasActive === 'true';
      if (punchOutBtn) punchOutBtn.disabled = value || card.dataset.hasActive !== 'true';
      if (punchGenericBtn) punchGenericBtn.disabled = value;
      if (checkBtn) checkBtn.disabled = value;
    }

    function renderFix(fix) {
      if (box) box.classList.remove('error');
      if (title) title.textContent = 'Location Captured';
      if (detail) detail.textContent = `Reported accuracy ±${Math.round(fix.accuracy_m)}m · ${fix.latitude.toFixed(5)}, ${fix.longitude.toFixed(5)}. Server will verify coordinates.`;
      if (map) {
        map.href = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(`${fix.latitude},${fix.longitude}`);
        map.hidden = false;
      }
    }

    function renderError(error) {
      if (box) box.classList.add('error');
      if (title) title.textContent = 'Location Needs Attention';
      if (detail) detail.textContent = error.message;
      if (map) map.hidden = true;
    }

    if (!window.isSecureContext && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1' && card.dataset.preview !== 'true') {
      if (title) title.textContent = 'HTTPS Required for Location';
      if (detail) detail.textContent = 'Mobile browsers require HTTPS to access GPS. Open the secure park URL.';
    }

    checkBtn?.addEventListener('click', async () => {
      if (busy) return;
      if (card.dataset.preview === 'true') {
        message('UI preview mode. No attendance is saved.');
        return;
      }
      setBusy(true);
      if (title) title.textContent = 'Detecting current location…';
      try {
        renderFix(await locate());
      } catch (e) {
        renderError(e);
      } finally {
        setBusy(false);
      }
    });

    async function executePunch(action) {
      if (busy || navigating) return;
      if (card.dataset.preview === 'true') {
        message('UI preview mode. Install the Laravel application to record live attendance.');
        return;
      }
      if (!navigator.onLine) {
        message('You appear to be offline. Reconnect to the internet and check your attendance before trying again.', true);
        return;
      }

      // Check current state to prevent duplicate submissions
      const hasActive = card.dataset.hasActive === 'true';
      if (action === 'in' && hasActive) {
        message('You already have an open attendance session. Punch out before starting another shift.', true);
        return;
      }
      if (action === 'out' && !hasActive) {
        message('You do not have an active shift to punch out of. Please punch in first.', true);
        return;
      }

      setBusy(true);

      try {
        message(`Acquiring fresh location for ${action === 'in' ? 'Punch In' : 'Punch Out'}…`);
        const fix = await locate();
        renderFix(fix);

        if (action === 'in' && accuracyLimit && fix.accuracy_m > accuracyLimit) {
          message(`Location accuracy is ±${Math.round(fix.accuracy_m)}m; park site requires ${accuracyLimit}m. Move to an open area and try again.`, true);
          setBusy(false);
          return;
        }

        pending = Object.freeze({
          ...fix,
          action,
          request_id: uuid(),
          ...(action === 'out' ? {session_id: Number(card.dataset.sessionId)} : {}),
          note: note?.value?.trim() || ''
        });

        message('Saving attendance record with location…');
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 25000);
        let response;
        try {
          response = await fetch(card.dataset.punchUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(pending),
            signal: controller.signal
          });
        } finally {
          clearTimeout(timer);
        }

        if (response.status === 401 || response.status === 419) {
          pending = null;
          message('Your login session expired. Please sign in again and check your history.', true);
          return;
        }
        if (response.status === 429) {
          throw new Error('Too many requests. Please wait a moment and try again.');
        }
        if (response.status >= 500) {
          throw new Error('The server encountered an error saving this punch. Please check your history or retry.');
        }

        const data = await response.json();
        if (!response.ok || !data.ok) {
          pending = null;
          const errors = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
          message(errors || 'The attendance record could not be saved. Please refresh and check status.', true);
          return;
        }

        pending = null;
        message(data.message || (action === 'in' ? 'Punch In recorded successfully!' : 'Punch Out recorded successfully!'));

        const savedEvent = new CustomEvent('parkdesk:attendance-saved', {detail: data, cancelable: true});
        if (!card.dispatchEvent(savedEvent)) {
          navigating = true;
          return;
        }

        const target = new URL(data.redirect || '/staff', window.location.origin);
        navigating = true;
        setTimeout(() => {
          window.location.assign(target.href);
        }, 600);
      } catch (error) {
        if (pending) {
          message('No confirmation received from server. Check your attendance history before retrying.', true);
        } else {
          renderError(error);
          message(error.message, true);
        }
      } finally {
        if (!navigating) setBusy(false);
      }
    }

    punchInBtn?.addEventListener('click', () => executePunch('in'));
    punchOutBtn?.addEventListener('click', () => executePunch('out'));
    punchGenericBtn?.addEventListener('click', () => {
      const act = card.dataset.action || (card.dataset.hasActive === 'true' ? 'out' : 'in');
      executePunch(act);
    });
  }

  const centreButton = document.querySelector('[data-site-location]');
  centreButton?.addEventListener('click', async () => {
    const status = document.querySelector('[data-site-location-status]');
    centreButton.disabled = true;
    if (status) status.textContent = 'Finding current location…';
    try {
      const fix = await locate();
      document.querySelector('#site-latitude').value = fix.latitude.toFixed(7);
      document.querySelector('#site-longitude').value = fix.longitude.toFixed(7);
      if (status) status.textContent = `Location captured (±${Math.round(fix.accuracy_m)}m). Save form to update site coordinates.`;
    } catch (error) {
      if (status) status.textContent = error.message;
    } finally {
      centreButton.disabled = false;
    }
  });

  const sessionSelect = document.querySelector('[data-request-session]');
  if (sessionSelect) {
    const updateDate = () => {
      const date = sessionSelect.selectedOptions[0]?.dataset.date;
      if (date) document.querySelector('[data-work-date]').value = date;
    };
    sessionSelect.addEventListener('change', updateDate);
    updateDate();
  }
})();
