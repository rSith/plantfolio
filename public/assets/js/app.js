// Shared page behaviour, loaded on every page by includes/header.php. Stage F: UI only. Popups: MOD-01.

// A link with data-dialog="some-id" opens <dialog id="some-id"> as a popup.
// The link keeps a real href, so without JavaScript it still leads somewhere useful.
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

// A click on the dimmed area closes the popup. Only there does the click land on the <dialog> itself,
// because its content fills the whole box (see .modal in components.css).
document.querySelectorAll('dialog.modal').forEach((dialog) => {
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) {
      dialog.close();
    }
  });
});
