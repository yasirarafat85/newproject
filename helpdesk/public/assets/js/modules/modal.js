// নেটিভ <dialog> ভিত্তিক মডাল — ফোকাস ট্র্যাপ ও Esc ব্রাউজারই সামলায়।
import { $, delegate } from '../core/dom.js';

delegate(document, '[data-open-modal]', 'click', (event, trigger) => {
  const dialog = document.getElementById(trigger.dataset.openModal);
  if (!dialog) return;

  if (typeof dialog.showModal === 'function') {
    dialog.showModal();
  } else {
    dialog.setAttribute('open', '');   // খুব পুরনো ব্রাউজারে fallback
  }

  dialog.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
});

delegate(document, '[data-close-modal]', 'click', (event, button) => {
  const dialog = button.closest('dialog');
  if (typeof dialog?.close === 'function') {
    dialog.close();
  } else {
    dialog?.removeAttribute('open');
  }
});

// ব্যাকড্রপে ক্লিক করলে বন্ধ — কিন্তু ফর্মের ভেতরে ক্লিক করলে নয়
delegate(document, 'dialog.hd-modal', 'click', (event, dialog) => {
  if (event.target === dialog) dialog.close();
});

/* ---- ট্রান্সফার মডালের ট্যাব ---- */
delegate(document, '[data-transfer-tab]', 'click', (event, tab) => {
  const target = tab.dataset.transferTab;

  document.querySelectorAll('[data-transfer-tab]').forEach((button) => {
    button.classList.toggle('active', button === tab);
  });

  document.querySelectorAll('.transfer-pane').forEach((pane) => {
    pane.hidden = pane.dataset.pane !== target;
  });

  // সার্ভারকে জানাই কোন ধরনের হস্তান্তর চাওয়া হচ্ছে
  const field = $('#transfer-target');
  if (field) field.value = target;

  document.querySelector(`.transfer-pane[data-pane="${target}"] select`)?.focus();
});
