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

// A click on the dimmed area closes the popup. Only there does the click land on the <dialog> itself,
// because its content fills the whole box (see .modal in components.css).
document.querySelectorAll('dialog.modal').forEach((dialog) => {
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) {
      dialog.close();
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
