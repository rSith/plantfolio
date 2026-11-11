// Community page behaviour (community.php, COM-01, COM-03, COM-07). Stage F and B alike: UI only, no data changes.
// 1. The filter panel that slides in below 900px. 2. Hiding "How the exchange works" for good.
// 3. Sending the filter form at once for a switch that changes the tab. 4. Leaving empty fields out of the address.

// ── 1. Filter panel ─────────────────────────────────────
// Without JavaScript the Filters link opens the panel through the address (#community-filters, CSS :target).
// With it, a class does the same job, and the script adds what CSS cannot: Esc and the dimmed page close it,
// the page behind stops scrolling, and keyboard focus moves into the panel and back to the button afterwards.
const filters = document.getElementById('community-filters');
const openButton = document.querySelector('[data-drawer-open]');
const scrim = document.querySelector('.filters-scrim');

if (filters && openButton && scrim) {
  const openPanel = () => {
    filters.classList.add('is-open');
    scrim.hidden = false;
    openButton.setAttribute('aria-expanded', 'true');
    document.documentElement.classList.add('filters-locked');
    filters.focus();
  };

  const closePanel = () => {
    filters.classList.remove('is-open');
    scrim.hidden = true;
    openButton.setAttribute('aria-expanded', 'false');
    document.documentElement.classList.remove('filters-locked');
    openButton.focus();
  };

  openButton.addEventListener('click', (event) => {
    event.preventDefault();   // the class opens it; the #hash is only for pages without JavaScript
    openPanel();
  });

  document.querySelectorAll('[data-drawer-close]').forEach((closer) => {
    closer.addEventListener('click', (event) => {
      event.preventDefault();
      closePanel();
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && filters.classList.contains('is-open')) {
      closePanel();
    }
  });

  // Turning a phone sideways past 900px makes the panel a column again: drop the open state with it
  window.matchMedia('(min-width: 900px)').addEventListener('change', (event) => {
    if (event.matches && filters.classList.contains('is-open')) {
      filters.classList.remove('is-open');
      scrim.hidden = true;
      openButton.setAttribute('aria-expanded', 'false');
      document.documentElement.classList.remove('filters-locked');
    }
  });
}

// ── 2. "How the exchange works" can be hidden ───────────
// The choice is remembered in this browser only (localStorage). Private windows and blocked storage throw an error
// when it is read or written, so both are wrapped in try/catch: the strip then simply shows again next time.
document.querySelectorAll('[data-dismissible]').forEach((section) => {
  const key = 'plantfolio.hide.' + section.dataset.dismissible;
  const button = section.querySelector('[data-dismiss]');

  let hidden = false;
  try {
    hidden = localStorage.getItem(key) === '1';
  } catch (error) {
    hidden = false;
  }

  section.hidden = hidden;
  button.hidden = false;   // only shown when this script runs, because only then can the choice be remembered

  button.addEventListener('click', () => {
    section.hidden = true;
    try {
      localStorage.setItem(key, '1');
    } catch (error) {
      // Nothing to do: the strip is hidden for this visit and comes back next time
    }
  });
});

// ── 3. A switch that changes the tab sends the form at once ─
// "Available for exchange" leads to the Exchange tab (community.php redirects), so waiting for Apply makes no sense.
document.querySelectorAll('[data-submit-on-change]').forEach((input) => {
  input.addEventListener('change', () => {
    if (input.checked) {
      input.form.requestSubmit();
    }
  });
});

// ── 4. A short address ──────────────────────────────────
// A GET form sends every field, even empty ones (health=&location=). Disabled fields are not sent, so the empty
// ones are disabled just before sending. They are enabled again when Back brings this page back from the cache.
const form = document.getElementById('community-form');

if (form) {
  form.addEventListener('submit', () => {
    Array.from(form.elements).forEach((field) => {
      if (field.name && field.value === '' && !field.disabled) {
        field.disabled = true;
        field.dataset.wasEmpty = '1';
      }
    });
  });

  // form.elements also holds the sidebar's fields, which sit outside the <form> (form="community-form")
  window.addEventListener('pageshow', () => {
    Array.from(form.elements).forEach((field) => {
      if (field.dataset.wasEmpty) {
        field.disabled = false;
        delete field.dataset.wasEmpty;
      }
    });
  });
}
