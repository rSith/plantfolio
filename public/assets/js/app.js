// Shared page behaviour, loaded on every page by includes/header.php. Stage F: UI only. Popups: MOD-01.

// A link or button with data-dialog="some-id" opens <dialog id="some-id"> as a popup.
// A link keeps a real href, so without JavaScript it still leads somewhere useful.
document.querySelectorAll('[data-dialog]').forEach((trigger) => {
  const dialog = document.getElementById(trigger.dataset.dialog);
  if (!dialog) {
    return;
  }

  trigger.addEventListener('click', (event) => {
    event.preventDefault();   // stay on this page instead of following the href
    dialog.showModal();
  });
});

// A popup the page wants open at once: a form in it came back from the server with an error to fix.
document.querySelectorAll('dialog[data-open-on-load]').forEach((dialog) => {
  dialog.showModal();
});

// The × of a toast removes the message from the page. It is also a link to the same page, so without
// JavaScript it reloads the page, which no longer shows the toast.
document.querySelectorAll('[data-toast-close]').forEach((button) => {
  button.addEventListener('click', (event) => {
    event.preventDefault();
    button.closest('.toast-region').remove();
  });
});

// A click on the dimmed area closes the popup. Only there does the click land on the <dialog> itself,
// because its content fills the whole box (see .modal in components.css).
document.querySelectorAll('dialog.modal').forEach((dialog) => {
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) {
      dialog.close();
    }
  });
});

// A dropdown of radio buttons, <details data-picker>: the closed box shows the chosen option.
// Choosing with the mouse or a finger closes the menu; with the keyboard, arrows move the choice,
// Enter or Esc closes it. A click anywhere else closes it too.
document.querySelectorAll('details[data-picker]').forEach((picker) => {
  const summary = picker.querySelector('summary');
  const value = picker.querySelector('[data-picker-value]');
  let byPointer = false;

  const close = () => {
    picker.open = false;
    summary.focus();
  };

  picker.addEventListener('pointerdown', () => {
    byPointer = true;
  });

  picker.addEventListener('keydown', (event) => {
    byPointer = false;
    // Enter on a radio button would send the form; Esc would close the whole popup
    if (picker.open && (event.key === 'Escape' || (event.key === 'Enter' && event.target.type === 'radio'))) {
      event.preventDefault();
      close();
    }
  });

  picker.addEventListener('change', (event) => {
    const label = event.target.closest('label').querySelector('[data-picker-label]');
    value.replaceChildren(label.cloneNode(true));   // a copy of the option's icon and word
    if (byPointer) {
      close();
    }
  });

  document.addEventListener('click', (event) => {
    if (picker.open && !picker.contains(event.target)) {
      picker.open = false;
    }
  });
});

// A part of a form that only belongs to one choice: <div data-show-when="repeat=days"> is shown only while
// the field named "repeat" in the same form is set to "days". Without JavaScript every part stays visible.
// A hidden part's fields are also disabled: the browser then neither checks them nor sends them.
document.querySelectorAll('[data-show-when]').forEach((part) => {
  const [name, value] = part.dataset.showWhen.split('=');
  const form = part.closest('form');
  const field = form.elements[name];

  const update = () => {
    part.hidden = field.value !== value;
    part.querySelectorAll('input, select, textarea').forEach((input) => {
      input.disabled = part.hidden;
    });
  };

  field.addEventListener('change', update);
  update();
});

// A photo gallery, <div data-gallery> (P05): a click on a thumbnail shows that photo in the large frame,
// and aria-pressed marks which one is showing. Stage F and B alike: this only changes what is on screen.
document.querySelectorAll('[data-gallery]').forEach((gallery) => {
  const main = gallery.querySelector('[data-gallery-main]');
  const thumbs = gallery.querySelectorAll('[data-photo-src]');

  thumbs.forEach((thumb) => {
    thumb.addEventListener('click', () => {
      main.src = thumb.dataset.photoSrc;
      main.alt = thumb.dataset.photoAlt;
      thumbs.forEach((other) => other.setAttribute('aria-pressed', String(other === thumb)));
    });
  });
});
