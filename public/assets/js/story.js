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

// ── Edit plant: "Add to <name>'s story" card ────────────────────────────────
// Adds, edits and deletes entries in the short list without a reload. A Watered or Fertilised entry also says
// when that reminder is next due. Everything typed is put on the page with textContent, never as HTML.

const card = document.getElementById('story-update');
const storyForm = document.getElementById('story-form');

if (card && storyForm) {
  const plantName = card.dataset.plantName;
  const intervals = JSON.parse(card.dataset.intervals || '{}');
  const kindLabels = {
    water: 'Watered', fertilise: 'Fertilised', prune: 'Pruned', repot: 'Repotted', note: 'Note', milestone: 'Milestone',
  };
  const fields = storyForm.elements;
  const note = document.getElementById('story-note');
  const noteError = card.querySelector('[data-note-error]');
  const noteOptional = card.querySelector('[data-note-optional]');
  const photo = document.getElementById('story-photo');
  const photoPreview = card.querySelector('[data-photo-preview]');
  const photoHint = card.querySelector('[data-photo-hint]');
  const photoButton = card.querySelector('[data-photo-button]');
  const submit = card.querySelector('[data-story-submit]');
  const cancel = card.querySelector('[data-story-cancel]');
  const message = card.querySelector('[data-story-message]');
  const recent = card.querySelector('[data-story-recent]');
  const recentEmpty = card.querySelector('[data-story-recent-empty]');
  const deleteDialog = document.getElementById('delete-entry');
  const todayValue = () => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
  };
  let editing = null;    // the <li> being edited, or null when adding
  let deleting = null;   // the <li> waiting for the delete confirmation

  // A note or milestone needs words; a care chip does not
  const needsNote = () => ['note', 'milestone'].includes(fields.kind.value);

  function showNoteRule() {
    noteOptional.hidden = needsNote();
  }

  function showMessage(text) {
    message.textContent = text;
    message.hidden = false;
  }

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
    fields.date.value = todayValue();
    clearPhoto();
    noteError.hidden = true;
    note.removeAttribute('aria-invalid');
    editing = null;
    submit.textContent = 'Add to story';
    cancel.hidden = true;
    showNoteRule();
  }

  // Fill one list item from the form's values
  function fillEntry(item, values) {
    const date = new Date(`${values.date}T12:00:00`);
    item.dataset.kind = values.kind;
    item.dataset.note = values.note;
    item.dataset.status = values.status;
    item.dataset.date = values.date;
    item.dataset.label = kindLabels[values.kind];
    item.querySelector('[data-entry-label]').textContent = kindLabels[values.kind];
    item.querySelector('[data-entry-date]').textContent = shortDate(date);
    item.querySelector('[data-entry-note]').textContent = values.note ? ` · ${values.note}` : '';
  }

  // A new list item with the same structure as the ones PHP prints, built element by element (no HTML strings)
  function newEntry() {
    const make = (tag, className, data) => {
      const element = document.createElement(tag);
      if (className) {
        element.className = className;
      }
      if (data) {
        element.dataset[data] = '';
      }
      return element;
    };
    const item = make('li', '', 'entry');
    const text = make('span', 'story-recent-text');
    const meta = make('span', 'story-recent-meta');
    meta.append('· ', make('span', '', 'entryDate'), make('span', '', 'entryNote'));
    text.append(make('strong', '', 'entryLabel'), ' ', meta);

    const actions = make('span', 'story-recent-actions');
    const edit = make('button', 'btn btn-ghost btn-sm', 'entryEdit');
    edit.type = 'button';
    edit.textContent = 'Edit';
    const remove = make('button', 'btn btn-ghost btn-sm story-recent-delete', 'entryDelete');
    remove.type = 'button';
    remove.textContent = 'Delete';
    actions.append(edit, remove);

    item.append(text, actions);
    return item;
  }

  card.querySelectorAll('.story-kind-input').forEach((input) => input.addEventListener('change', showNoteRule));
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
    // Stage F: no server yet, so the page changes by itself.
    // Stage B: fetch(storyForm.action, { method: 'POST', body: new FormData(storyForm) }) first, then the same.
    event.preventDefault();

    const values = {
      kind: fields.kind.value,
      note: note.value.trim(),
      status: fields.status.value,
      date: fields.date.value || todayValue(),
    };
    if (needsNote() && values.note === '') {
      noteError.hidden = false;
      note.setAttribute('aria-invalid', 'true');
      note.focus();
      return;
    }

    let text;
    if (editing) {
      fillEntry(editing, values);
      text = 'Entry updated.';
    } else {
      const item = newEntry();
      fillEntry(item, values);
      recent.prepend(item);
      // The card lists only the three newest entries
      while (recent.children.length > 3) {
        recent.lastElementChild.remove();
      }
      recentEmpty.hidden = true;
      text = `Added to ${plantName}’s story.`;
      // Watered or Fertilised: the matching reminder starts again from this date
      if (intervals[values.kind]) {
        const next = addDays(new Date(`${values.date}T12:00:00`), intervals[values.kind]);
        text += ` Next ${values.kind === 'water' ? 'watering' : 'feeding'} on ${shortDate(next)}.`;
      }
    }
    resetForm();
    showMessage(text);
  });

  cancel.addEventListener('click', () => {
    resetForm();
    message.hidden = true;
  });

  // Edit and Delete in the list: one listener on the list, so it also works for entries added later
  recent.addEventListener('click', (event) => {
    const item = event.target.closest('[data-entry]');
    if (!item) {
      return;
    }
    if (event.target.closest('[data-entry-edit]')) {
      resetForm();
      editing = item;
      const chip = document.getElementById(`story-kind-${item.dataset.kind}`);
      if (chip) {
        chip.checked = true;
      }
      note.value = item.dataset.note;
      fields.status.value = item.dataset.status;
      fields.date.value = item.dataset.date;
      submit.textContent = 'Save entry';
      cancel.hidden = false;
      message.hidden = true;
      showNoteRule();
      note.focus();
    } else if (event.target.closest('[data-entry-delete]')) {
      deleting = item;
      deleteDialog.querySelector('[data-delete-name]').textContent =
        `${item.dataset.label} · ${item.querySelector('[data-entry-date]').textContent}`;
      deleteDialog.showModal();
    }
  });

  // The popup's buttons close it with a value: "delete" or "cancel"
  deleteDialog.addEventListener('close', () => {
    if (deleteDialog.returnValue === 'delete' && deleting) {
      if (deleting === editing) {
        resetForm();
      }
      deleting.remove();
      recentEmpty.hidden = recent.children.length > 0;
      showMessage('Entry deleted.');
    }
    deleting = null;
    deleteDialog.returnValue = '';
  });
}
