// Community page behaviour (community.php, COM-01, COM-03, COM-04, COM-07). Stage F and B alike: it only changes what
// is on screen; every search still runs on the server. Without JavaScript the page works with plain links and forms.
// 1. Filters apply as soon as they change (from 900px).   2. "Show 7 plants" on the phone panel's button.
// 3. Load more.   4. The filter panel below 900px.   5. Hiding "How the exchange works".
//
// How 1 and 3 work: the script asks the server for the same address a normal form or link would open (fetch),
// reads the returned page with DOMParser, and copies over only the parts marked data-swap="…" (or the new cards).
// So there is one place that decides what the results are (community.php), with or without JavaScript.

// The "js" class lets community.css hide what only matters without JavaScript (the desktop Apply button)
document.documentElement.classList.add('js');

const form = document.getElementById('community-form');
const desktop = window.matchMedia('(min-width: 900px)');

// ── The address of the form's current choices ───────────
// Like a GET form sends it, but without empty fields (location=&q=) and without page= (a new choice starts at
// the top). form.elements also holds the sidebar's fields, which sit outside the <form> (form="community-form").
function formAddress() {
  const address = new URL(form.action);
  Array.from(form.elements).forEach((field) => {
    if (!field.name || field.disabled || field.name === 'page' || field.value === '') {
      return;
    }
    if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
      return;
    }
    address.searchParams.append(field.name, field.value);
  });
  return address.toString();
}

/** Fetches a page of this site and returns it as a document to read parts from. */
async function fetchPage(address, signal) {
  const response = await fetch(address, { signal, headers: { Accept: 'text/html' } });
  if (!response.ok) {
    throw new Error('HTTP ' + response.status);
  }
  return new DOMParser().parseFromString(await response.text(), 'text/html');
}

// ── 1. Filters apply as soon as they change (900px and up) ─
let pending = null;   // the fetch still running, cancelled when a newer change comes in

async function loadResults(address, { push = true } = {}) {
  const main = document.querySelector('[data-swap="main"]');
  if (pending) {
    pending.abort();
  }
  pending = new AbortController();
  main.setAttribute('aria-busy', 'true');

  // Which field had focus, so it can get it back after its part of the page is replaced
  const active = document.activeElement;
  const focusKey = active && active.name ? { name: active.name, value: active.value } : null;

  let page;
  try {
    page = await fetchPage(address, pending.signal);
  } catch (error) {
    if (error.name === 'AbortError') {
      return;   // a newer change is on its way
    }
    window.location.href = address;   // something went wrong: let the browser open the address the normal way
    return;
  }

  document.querySelectorAll('[data-swap]').forEach((part) => {
    const fresh = page.querySelector('[data-swap="' + part.dataset.swap + '"]');
    if (!fresh) {
      return;
    }
    // "+ N more" stays open if the member had opened it, and a filter group they folded stays folded
    const moreWasOpen = part.querySelector('.filter-more[open]') !== null;
    const folded = Array.from(part.querySelectorAll('.filter-section:not([open])')).map((section) => section.dataset.section);
    part.innerHTML = fresh.innerHTML;
    if (moreWasOpen && part.querySelector('.filter-more')) {
      part.querySelector('.filter-more').open = true;
    }
    folded.forEach((name) => {
      const section = part.querySelector('.filter-section[data-section="' + name + '"]');
      if (section) {
        section.open = false;
      }
    });
  });

  // The search box too, for Back and Forward (it is not inside a swapped part)
  const freshQ = page.getElementById('community-q');
  if (freshQ) {
    document.getElementById('community-q').value = freshQ.value;
  }
  document.title = page.title;
  main.removeAttribute('aria-busy');
  pending = null;

  if (focusKey) {
    const again = Array.from(form.elements).find((field) => field.name === focusKey.name
      && (field.type !== 'checkbox' && field.type !== 'radio' || field.value === focusKey.value));
    if (again) {
      again.focus({ preventScroll: true });
    }
  }

  if (push) {
    history.pushState({ community: true }, '', address);   // the address bar shows the filters: share it, use Back
  }
}

if (form) {
  // A sidebar field changed. Only on a desktop: in the phone panel the member chooses several, then presses Show.
  document.addEventListener('change', (event) => {
    if (event.target.form !== form) {
      return;
    }
    if (desktop.matches) {
      loadResults(formAddress());
    } else {
      updateApplyCount();
    }
  });

  // Search (Enter or the Search button) and the desktop's hidden Apply: the same fetch. In the phone panel the
  // page opens normally, which also closes the panel.
  form.addEventListener('submit', (event) => {
    if (desktop.matches) {
      event.preventDefault();
      loadResults(formAddress());
    } else {
      // Leave empty fields out of the address. Disabled fields are not sent; they are enabled again on Back.
      Array.from(form.elements).forEach((field) => {
        if (field.name && field.value === '' && !field.disabled) {
          field.disabled = true;
          field.dataset.wasEmpty = '1';
        }
      });
    }
  });

  window.addEventListener('pageshow', () => {
    Array.from(form.elements).forEach((field) => {
      if (field.dataset.wasEmpty) {
        field.disabled = false;
        delete field.dataset.wasEmpty;
      }
    });
  });

  // Back and Forward between filter choices made here: show that address's results again (without a new history step)
  window.addEventListener('popstate', () => {
    loadResults(window.location.href, { push: false });
  });
}

// ── The distance slider's words ─────────────────────────
// While the slider moves ("input" event), its <output> and the words a screen reader hears follow it: "Within 40 km",
// and at the far end "Any distance". The results change when it is let go ("change" event, handled above).
document.addEventListener('input', (event) => {
  const range = event.target;
  if (range.type !== 'range') {
    return;
  }
  const words = range.value === range.max ? 'Any distance' : 'Within ' + range.value + ' km';
  range.setAttribute('aria-valuetext', words);
  const output = range.closest('.filter-range').querySelector('[data-range-output]');
  if (output) {
    output.textContent = words;
  }
});

// In the phone panel nothing is fetched until "Show", so choosing a town switches the slider on straight away
document.addEventListener('change', (event) => {
  if (event.target.name === 'near') {
    const range = document.getElementById('filter-distance');
    if (range) {
      range.disabled = event.target.value === '';
    }
  }
});

// ── 2. "Show 7 plants" on the phone panel's button ──────
// After each change in the panel the server counts the results for the new choices (the same page, fetched),
// and the button says how many it will show. A short wait first, so ticking three boxes asks once, not three times.
const applyButton = document.querySelector('[data-apply]');
let countTimer = null;
let countRequest = null;

function updateApplyCount() {
  if (!applyButton || desktop.matches) {
    return;
  }
  clearTimeout(countTimer);
  countTimer = setTimeout(async () => {
    if (countRequest) {
      countRequest.abort();
    }
    countRequest = new AbortController();
    try {
      const page = await fetchPage(formAddress(), countRequest.signal);
      const countText = page.querySelector('[data-swap="count"]').textContent;
      setApplyLabel(parseInt(countText, 10));
    } catch (error) {
      // Keep the button as it is; it still works
    }
  }, 250);
}

function setApplyLabel(number) {
  if (Number.isNaN(number)) {
    return;
  }
  const noun = number === 1 ? applyButton.dataset.nounOne : applyButton.dataset.nounMany;
  applyButton.textContent = 'Show ' + number + ' ' + noun;
}

// ── 3. Load more ────────────────────────────────────────
// The button is a link to ?page=2, which shows the first 24. The script fetches that address and appends only the
// cards it does not have yet, then moves keyboard focus to the first new card, so a keyboard user carries on there.
document.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-load-more]');
  if (!button) {
    return;
  }
  event.preventDefault();
  const grid = document.querySelector('[data-grid]');
  button.setAttribute('aria-disabled', 'true');
  button.textContent = 'Loading…';

  let page;
  try {
    page = await fetchPage(button.href);
  } catch (error) {
    window.location.href = button.href;
    return;
  }

  const have = grid.children.length;
  const cards = Array.from(page.querySelector('[data-grid]').children).slice(have);
  cards.forEach((card) => grid.append(card));

  const freshMore = page.querySelector('.load-more');
  document.querySelector('.load-more').replaceWith(freshMore);
  history.replaceState({ community: true }, '', button.href);   // a reload shows the same number of cards

  const firstLink = cards[0] && cards[0].querySelector('.card-link');
  if (firstLink) {
    firstLink.focus();
  }
});

// ── 4. Filter panel below 900px ─────────────────────────
// Without JavaScript the Filters link opens the panel through the address (#community-filters, CSS :target).
// With it, a class does the same job, and the script adds what CSS cannot: Esc and the dimmed page close it,
// the page behind stops scrolling, and keyboard focus moves into the panel and back to the button afterwards.
const filters = document.getElementById('community-filters');
const openButton = document.querySelector('[data-drawer-open]');
const scrim = document.querySelector('.filters-scrim');

if (filters && openButton && scrim) {
  const setOpen = (open) => {
    filters.classList.toggle('is-open', open);
    scrim.hidden = !open;
    openButton.setAttribute('aria-expanded', String(open));
    document.documentElement.classList.toggle('filters-locked', open);
  };

  openButton.addEventListener('click', (event) => {
    event.preventDefault();   // the class opens it; the #hash is only for pages without JavaScript
    setOpen(true);
    filters.focus();
    // Start from the number on the page: "Show 19 plants"
    const shown = document.querySelector('[data-swap="count"]');
    if (applyButton && shown) {
      setApplyLabel(parseInt(shown.textContent, 10));
    }
  });

  const closePanel = () => {
    setOpen(false);
    openButton.focus();
  };

  // The × and the dimmed page; delegated, because the × is not swapped but stays reachable either way
  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-drawer-close]')) {
      event.preventDefault();
      closePanel();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && filters.classList.contains('is-open')) {
      closePanel();
    }
  });

  // Turning a phone sideways past 900px makes the panel a column again: drop the open state with it
  desktop.addEventListener('change', (event) => {
    if (event.matches && filters.classList.contains('is-open')) {
      setOpen(false);
    }
    if (event.matches && applyButton) {
      applyButton.textContent = 'Apply filters';
    }
  });
}

// ── 5. "How the exchange works" can be hidden ───────────
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
