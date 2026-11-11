// The Plant Profile's own script (P05): the Add a photo and Add to story popups. CORE-17 and CORE-19, UI half.
// Loaded through $page_js by plant.php. (Mark done in the Reminders card is a server form, like the Dashboard's.)
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

// The same words as care_due() in helpers.php: "Overdue 2 days", "Due today", "Tomorrow", "In 7 days"
const dueLabel = (days) => {
  if (days < 0) {
    return { text: `Overdue ${-days} ${days === -1 ? 'day' : 'days'}`, state: 'overdue' };
  }
  if (days === 0) {
    return { text: 'Due today', state: 'due' };
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
  due.className = `badge badge-${label.state === 'upcoming' ? 'neutral' : label.state}`;   // as partials/care-row.php
  return next;
}

// Mark done in the Reminders card is a normal form (partials/care-row.php): the server moves the date on, adds the
// Story's care line and shows the toast with Undo, the same as the Dashboard's Today's care.

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

// ── Add a photo popup ───────────────────────────────────────────────────────
// Choose or drag a photo, check it, show it in the drop area, then add it: it becomes the first thumbnail and
// the large photo. The strip scrolls back to the start so the new photo is in view.

const photoDialog = document.getElementById('add-photo');
const photoForm = document.getElementById('photo-form');

if (photoDialog && photoForm) {
  const input = photoForm.elements.photo;
  const drop = photoForm.querySelector('[data-photo-drop]');
  const preview = photoForm.querySelector('[data-photo-preview]');
  const empty = photoForm.querySelector('[data-photo-empty]');
  const hint = photoForm.querySelector('[data-photo-hint]');
  const strip = document.querySelector('[data-thumb-strip]');
  const gallery = document.querySelector('[data-gallery]');
  const plantName = photoDialog.dataset.plantName;
  const hintText = hint.textContent;

  function showHint(text, isError) {
    hint.textContent = text;
    hint.classList.toggle('field-error', isError);
  }

  function clearPhoto() {
    photoForm.reset();
    photoForm.elements.date.value = isoDay(new Date());
    preview.hidden = true;
    preview.removeAttribute('src');
    empty.hidden = false;
    showHint(hintText, false);
  }

  // The same checks the server makes in stage B (finfo, 5 MB): JPG or PNG only, and not too big
  function usePhoto(file) {
    if (!file) {
      clearPhoto();
      return;
    }
    if (!['image/jpeg', 'image/png'].includes(file.type) || file.size > 5 * 1024 * 1024) {
      clearPhoto();
      showHint('Choose a JPG or PNG photo of 5 MB or less.', true);
      return;
    }
    preview.src = URL.createObjectURL(file);
    preview.hidden = false;
    empty.hidden = true;
    showHint(`${file.name} · ${(file.size / 1048576).toFixed(1)} MB`, false);
  }

  input.addEventListener('change', () => usePhoto(input.files[0]));

  // Drag and drop: the dropped file is put into the file input, as if it had been chosen
  drop.addEventListener('dragover', (event) => {
    event.preventDefault();
    drop.classList.add('is-dragging');
  });
  drop.addEventListener('dragleave', () => drop.classList.remove('is-dragging'));
  drop.addEventListener('drop', (event) => {
    event.preventDefault();
    drop.classList.remove('is-dragging');
    if (event.dataTransfer.files.length) {
      input.files = event.dataTransfer.files;
      usePhoto(input.files[0]);
    }
  });

  photoForm.addEventListener('submit', (event) => {
    // Cancel and × close the popup (formmethod="dialog"): let them
    if (event.submitter && event.submitter.getAttribute('formmethod') === 'dialog') {
      clearPhoto();
      return;
    }
    // Stage F: no server yet. Stage B: fetch(photoForm.action, { method: 'POST', body: new FormData(photoForm) }) first.
    event.preventDefault();
    if (preview.hidden) {
      showHint('Choose a photo first.', true);
      input.focus();
      return;
    }

    const date = fromInput(photoForm.elements.date.value || isoDay(new Date()));
    const thumb = document.createElement('button');
    thumb.type = 'button';
    thumb.className = 'plant-thumb';
    thumb.dataset.photoSrc = preview.src;
    thumb.dataset.photoAlt = `Photo of ${plantName}, ${longDate(date)}`;
    const image = document.createElement('img');
    image.src = preview.src;
    image.alt = `Show photo from ${longDate(date)}`;
    image.width = 80;
    image.height = 80;
    thumb.append(image);
    strip.prepend(thumb);

    // Show it at once: the gallery's own click handler (app.js) puts it in the large frame and marks it
    thumb.click();
    strip.scrollTo({ left: 0, behavior: 'smooth' });

    showToast(`Photo added to ${plantName}’s gallery.`);
    clearPhoto();   // the photo's object URL stays valid for the new thumbnail; only the form is cleared
    photoDialog.close();
    gallery.querySelector('[data-gallery-main]').scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  });
}
