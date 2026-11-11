// A plant's Story on the Plant Profile (P05): the Add to story popup and the reminders' Done buttons.
// CORE-19 and CORE-23, UI half. Loaded through $page_js by plant.php.
// Stage F changes the page only: nothing is saved, so a reload shows the mock data again.
// Stage B sends each change to the server with fetch() first and updates the page when the server says OK.
// Everything a member typed is put on the page with textContent, never as HTML.

const story = document.querySelector('[data-story]');
const storyEmpty = document.querySelector('[data-story-empty]');
const storyStatus = document.querySelector('[data-story-status]');

const doneWords = { water: 'Watered', fertilise: 'Fertilised', prune: 'Pruned', repot: 'Repotted' };

const shortDate = (date) => date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
const longDate = (date) => date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
const fromInput = (value) => new Date(`${value}T12:00:00`);   // midday: no time-zone surprise at midnight

const isoDay = (date) => {
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${date.getFullYear()}-${month}-${day}`;
};

const addDays = (date, days) => {
  const result = new Date(date);
  result.setDate(result.getDate() + days);
  return result;
};

// Whole days from today to a date: negative when it lies in the past
const daysFromToday = (date) => Math.round((fromInput(isoDay(date)) - fromInput(isoDay(new Date()))) / 86400000);

// The same words as due_label() in helpers.php
const dueLabel = (days) => {
  if (days < 0) {
    return { text: 'Overdue', state: 'overdue' };
  }
  if (days === 0) {
    return { text: 'Today', state: 'due' };
  }
  return { text: days === 1 ? 'Tomorrow' : `In ${days} days`, state: 'upcoming' };
};

// A new entry goes before the first entry of the same day or older, so the Story stays newest first
function placeEntry(entry, day) {
  const later = [...story.children].find((other) => other.dataset.date <= day);
  story.insertBefore(entry, later || null);
  storyEmpty.hidden = true;
}

// A care line: "Watered · 11 Nov", in the task's colour, with an optional note
function addCareEntry(task, date, note) {
  const entry = document.getElementById('story-care-template').content.firstElementChild.cloneNode(true);
  const label = entry.querySelector('.story-care-task');
  const icon = document.querySelector(`.story-kind[for="story-kind-${task}"] .icon`);
  label.classList.add('task', `task-${task}`);
  label.append(icon ? icon.cloneNode(true) : '', ` ${doneWords[task]}`);
  entry.querySelector('.story-care-date').textContent = `· ${shortDate(date)}`;
  entry.querySelector('.story-care-note').textContent = note ? `· ${note}` : '';
  entry.dataset.date = isoDay(date);
  placeEntry(entry, entry.dataset.date);
}

// The task's reminder starts again from the day it was done: next = that day + its interval
function moveReminder(task, date) {
  const row = document.querySelector(`[data-reminder][data-task="${task}"]`);
  if (!row || !row.dataset.interval) {
    return null;
  }
  const next = addDays(date, Number(row.dataset.interval));
  const label = dueLabel(daysFromToday(next));
  const due = row.querySelector('[data-due]');
  due.textContent = label.text;
  due.className = `side-due side-due-${label.state}`;
  return next;
}

// ── Done on a reminder ──────────────────────────────────────────────────────

document.querySelectorAll('[data-reminder-done]').forEach((button) => {
  button.addEventListener('click', () => {
    const row = button.closest('[data-reminder]');
    const task = row.dataset.task;
    const today = new Date();
    if (doneWords[task]) {
      addCareEntry(task, today, '');
    }
    const next = moveReminder(task, today);
    // Done once is enough until the next date: the button stays pressed
    button.disabled = true;
    button.classList.add('is-done');
    storyStatus.textContent = next
      ? `${row.dataset.doneLabel}. Next on ${shortDate(next)}.`
      : `${row.dataset.doneLabel}.`;
  });
});

// ── Add to story popup ──────────────────────────────────────────────────────

const storyDialog = document.getElementById('add-story');
const storyForm = document.getElementById('story-form');

if (storyDialog && storyForm) {
  const fields = storyForm.elements;
  const note = fields.note;
  const noteError = storyForm.querySelector('[data-note-error]');
  const noteOptional = storyForm.querySelector('[data-note-optional]');
  const photo = fields.photo;
  const photoPreview = storyForm.querySelector('[data-photo-preview]');
  const photoHint = storyForm.querySelector('[data-photo-hint]');
  const photoButton = storyForm.querySelector('[data-photo-button]');
  const plantName = storyDialog.dataset.plantName;

  // A note or milestone needs words; a care chip does not
  const needsNote = () => ['note', 'milestone'].includes(fields.kind.value);
  const showNoteRule = () => {
    noteOptional.hidden = needsNote();
  };

  function clearPhoto() {
    photo.value = '';
    photoPreview.hidden = true;
    photoPreview.removeAttribute('src');
    photoButton.textContent = 'Add a photo';
    photoHint.textContent = 'JPG or PNG, up to 5 MB.';
    photoHint.classList.remove('field-error');
  }

  function resetForm() {
    storyForm.reset();
    fields.date.value = isoDay(new Date());
    clearPhoto();
    noteError.hidden = true;
    note.removeAttribute('aria-invalid');
    showNoteRule();
  }

  // A note or milestone card, with its status badge and photo when given
  function addCardEntry(kind, date, text, status, photoSrc) {
    const entry = document.getElementById('story-card-template').content.firstElementChild.cloneNode(true);
    const card = entry.querySelector('.story-card');
    const body = entry.querySelector('.story-body');
    entry.querySelector('.story-date').textContent = `${longDate(date)} · ${kind === 'milestone' ? 'Milestone' : 'Update'}`;
    entry.querySelector('.story-note').textContent = text;
    if (kind === 'milestone') {
      entry.classList.add('story-entry-milestone');
      card.classList.add('story-milestone');
      card.querySelector('.story-milestone-icon').hidden = false;
    }
    if (status) {
      const badge = document.createElement('span');
      badge.className = `badge badge-${status.replace('_', '-')}`;
      badge.textContent = fields.status.selectedOptions[0].textContent;
      body.prepend(badge);
    }
    if (photoSrc && kind !== 'milestone') {
      const image = document.createElement('img');
      image.className = 'story-photo';
      image.src = photoSrc;
      image.alt = `Photo from ${longDate(date)}`;
      image.width = 160;
      image.height = 160;
      body.append(image);
    }
    entry.dataset.date = isoDay(date);
    placeEntry(entry, entry.dataset.date);
  }

  storyForm.querySelectorAll('.story-kind-input').forEach((input) => input.addEventListener('change', showNoteRule));
  showNoteRule();

  // Photo: check the type and size before showing a preview (the server checks again in stage B)
  photo.addEventListener('change', () => {
    const file = photo.files[0];
    if (!file) {
      clearPhoto();
      return;
    }
    if (!['image/jpeg', 'image/png'].includes(file.type) || file.size > 5 * 1024 * 1024) {
      clearPhoto();
      photoHint.textContent = 'Choose a JPG or PNG photo of 5 MB or less.';
      photoHint.classList.add('field-error');
      return;
    }
    photoPreview.src = URL.createObjectURL(file);
    photoPreview.hidden = false;
    photoButton.textContent = 'Change photo';
    photoHint.textContent = `${file.name} · ${(file.size / 1048576).toFixed(1)} MB`;
    photoHint.classList.remove('field-error');
  });

  storyForm.addEventListener('submit', (event) => {
    // Cancel and × close the popup (formmethod="dialog"): let them
    if (event.submitter && event.submitter.getAttribute('formmethod') === 'dialog') {
      resetForm();
      return;
    }
    // Stage F: no server yet. Stage B: fetch(storyForm.action, { method: 'POST', body: new FormData(storyForm) }) first.
    event.preventDefault();

    const kind = fields.kind.value;
    const text = note.value.trim();
    if (needsNote() && text === '') {
      noteError.hidden = false;
      note.setAttribute('aria-invalid', 'true');
      note.focus();
      return;
    }
    const date = fromInput(fields.date.value || isoDay(new Date()));
    let message = `Added to ${plantName}’s story.`;

    if (doneWords[kind]) {
      addCareEntry(kind, date, text);
      // Watered or Fertilised: the matching reminder starts again from this date
      const next = moveReminder(kind, date);
      if (next) {
        message += ` Next ${kind === 'water' ? 'watering' : 'feeding'} on ${shortDate(next)}.`;
      }
    } else {
      addCardEntry(kind, date, text, fields.status.value, photoPreview.hidden ? '' : photoPreview.src);
    }

    storyStatus.textContent = message;
    showToast(message);
    resetForm();
    storyDialog.close();
  });
}

// A short message at the bottom of the screen, like the server's toast, closed with ×
function showToast(text) {
  document.querySelectorAll('.toast-region[data-js-toast]').forEach((old) => old.remove());
  const region = document.createElement('div');
  region.className = 'toast-region';
  region.dataset.jsToast = '';
  const toast = document.createElement('div');
  toast.className = 'toast';
  const message = document.createElement('span');
  message.className = 'toast-message';
  message.textContent = text;
  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'toast-close';
  close.setAttribute('aria-label', 'Close message');
  close.textContent = '×';
  close.addEventListener('click', () => region.remove());
  toast.append(message, close);
  region.append(toast);
  document.querySelector('main').append(region);
}
