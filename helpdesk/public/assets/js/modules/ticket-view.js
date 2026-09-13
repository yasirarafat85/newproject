// এজেন্ট টিকেট ভিউ — কম্পোজার ট্যাব ও তৈরি উত্তর বসানো।
import { $, $$, delegate } from '../core/dom.js';

/* ---- উত্তর / নোট ট্যাব ---- */
delegate(document, '.composer-tab', 'click', (event, tab) => {
  const target = tab.dataset.pane;

  $$('.composer-tab').forEach((button) => button.classList.toggle('active', button === tab));
  $$('.composer-pane').forEach((pane) => { pane.hidden = pane.dataset.pane !== target; });

  $(`.composer-pane[data-pane="${target}"] textarea`)?.focus();
});

/* ---- তৈরি উত্তর (canned response) ---- */
delegate(document, '[data-canned-for]', 'change', (event, select) => {
  const textarea = document.getElementById(select.dataset.cannedFor);
  if (!textarea || select.value === '') return;

  // HTML নয়, পড়ার উপযোগী লেখা বসাই — এডিটর এখনো প্লেইন টেক্সটএরিয়া
  const text = htmlToText(select.value);
  textarea.value = textarea.value.trim() === '' ? text : `${textarea.value.trim()}\n\n${text}`;
  textarea.focus();
  select.value = '';
});

function htmlToText(html) {
  const holder = document.createElement('div');
  holder.innerHTML = html
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/(p|div|li|h[1-6])>/gi, '\n\n');
  return (holder.textContent ?? '').replace(/\n{3,}/g, '\n\n').trim();
}
