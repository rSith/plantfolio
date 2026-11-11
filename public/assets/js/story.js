// A plant's Story: filters and the reminders' Done buttons on the Plant Profile (P05), the "Add to story" card on
// Edit plant (P06). CORE-19 and CORE-23, UI half. Loaded through $page_js by plant.php and plant-form.php.
// Stage F changes the page only: nothing is saved, so a reload shows the mock data again.
// Stage B sends each change to the server with fetch() first and updates the page when the server says OK.

const shortDate = (date) => date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

const addDays = (date, days) => {
  const result = new Date(date);
  result.setDate(result.getDate() + days);
  return result;
};

const inDaysLabel = (days) => {
  if (days <= 0) {
    return 'Today';
  }
  return days === 1 ? 'Tomorrow' : `In ${days} days`;
};

// ── Plant Profile: filters (All, Photos, Health, Care) ──────────────────────
// Each entry carries data-kind, data-photo and data-health (partials/story-entry.php); a filter hides the others.

const story = document.querySelector('[data-story]');
const filterButtons = document.querySelectorAll('[data-story-filter]');
let currentFilter = 'all';

function matchesFilter(entry) {
  if (currentFilter === 'photos') {
    return entry.dataset.photo === '1';
  }
  if (currentFilter === 'health') {
    return entry.dataset.health === '1';
  }
  if (currentFilter === 'care') {
    return entry.dataset.kind === 'care';
  }
  return true;
}

function applyFilter() {
  if (!story) {
    return;
  }
  let shown = 0;
  story.querySelectorAll('.story-entry').forEach((entry) => {
    entry.hidden = !matchesFilter(entry);
    if (!entry.hidden) {
      shown += 1;
    }
  });
  document.querySelector('[data-story-empty]').hidden = shown > 0;
}

filterButtons.forEach((button) => {
  button.addEventListener('click', () => {
    currentFilter = button.dataset.storyFilter;
    filterButtons.forEach((other) => {
      const isOn = other === button;
      other.setAttribute('aria-pressed', String(isOn));
      other.classList.toggle('is-selected', isOn);
    });
    applyFilter();
  });
});

// ── Plant Profile: Done on a reminder ───────────────────────────────────────
// a) a care line ("Watered · 11 Nov") goes to the top of the Story, b) the next date moves on by the interval,
// c) the stats follow. A screen reader hears what happened through the hidden status line.

const careTemplate = document.getElementById('story-care-template');
const storyStatus = document.querySelector('[data-story-status]');

function updateStats(task, date) {
  const count = document.querySelector('[data-stat-count]');
  if (!count) {
    return;
  }
  count.textContent = Number(count.textContent) + 1;
  document.querySelector('[data-stat-last]').textContent = shortDate(date);
  if (task === 'water') {
    const watered = document.querySelector('[data-stat-watered]');
    const times = Number(watered.textContent) + 1;
    watered.textContent = times;
    document.querySelector('[data-stat-watered-word]').textContent = times === 1 ? 'time' : 'times';
  }
}

document.querySelectorAll('[data-reminder-done]').forEach((button) => {
  button.addEventListener('click', () => {
    const row = button.closest('[data-reminder]');
    const interval = Number(row.dataset.interval);
    const today = new Date();

    if (story && careTemplate) {
      const entry = careTemplate.content.firstElementChild.cloneNode(true);
      const task = entry.querySelector('.story-care-task');
      task.classList.add(...row.querySelector('.task').classList);   // the task's colour, as in the Reminders card
      task.append(row.querySelector('.task .icon').cloneNode(true), ` ${row.dataset.doneLabel}`);
      entry.querySelector('.story-care-date').textContent = `· ${shortDate(today)}`;
      story.prepend(entry);
      applyFilter();
      updateStats(row.dataset.task, today);
    }

    const due = row.querySelector('[data-due]');
    due.textContent = inDaysLabel(interval);
    due.className = 'side-due side-due-upcoming';

    // Done once is enough until the next date: the button stays pressed
    button.disabled = true;
    button.classList.add('is-done');
    storyStatus.textContent = `${row.dataset.doneLabel}. Next on ${shortDate(addDays(today, interval))}.`;
  });
});
