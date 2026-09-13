// অ্যাপের সাধারণ আচরণ — থিম, সাইডবার, অ্যালার্ট, কনফার্মেশন।
import { $, $$, on, delegate } from './core/dom.js';

/* ---- থিম (লাইট / ডার্ক) ---- */
const root = document.documentElement;

function systemPrefersDark() {
  return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function applyTheme(theme) {
  root.setAttribute('data-theme', theme);
  try { localStorage.setItem('hd-theme', theme); } catch { /* প্রাইভেট মোড */ }
}

(function initTheme() {
  let saved = null;
  try { saved = localStorage.getItem('hd-theme'); } catch { /* উপেক্ষা */ }
  root.setAttribute('data-theme', saved ?? (systemPrefersDark() ? 'dark' : 'light'));
})();

on($('#theme-toggle'), 'click', () => {
  applyTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
});

/* ---- মোবাইল সাইডবার ---- */
const sidebar = $('.sidebar');
const backdrop = $('.sidebar-backdrop');

function closeSidebar() {
  sidebar?.classList.remove('open');
  backdrop?.classList.remove('show');
}

on($('.menu-toggle'), 'click', () => {
  sidebar?.classList.toggle('open');
  backdrop?.classList.toggle('show');
});
on(backdrop, 'click', closeSidebar);
on(document, 'keydown', (e) => { if (e.key === 'Escape') closeSidebar(); });

/* ---- অ্যালার্ট নিজে থেকে মিলিয়ে যাবে ---- */
$$('.alert-hd[data-autohide]').forEach((alert) => {
  setTimeout(() => {
    alert.style.transition = 'opacity .3s ease';
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 320);
  }, 5000);
});

/* ---- ধ্বংসাত্মক কাজের আগে নিশ্চিত করা ---- */
delegate(document, '[data-confirm]', 'submit', (e, form) => {
  if (!window.confirm(form.dataset.confirm)) e.preventDefault();
});
delegate(document, 'a[data-confirm]', 'click', (e, link) => {
  if (!window.confirm(link.dataset.confirm)) e.preventDefault();
});

/* ---- ফর্ম দুবার সাবমিট ঠেকানো ---- */
delegate(document, 'form[data-once]', 'submit', (e, form) => {
  const button = form.querySelector('[type="submit"]');
  if (!button) return;
  if (form.dataset.submitted === '1') { e.preventDefault(); return; }
  form.dataset.submitted = '1';
  button.disabled = true;
  button.dataset.label = button.textContent;
  button.textContent = 'অপেক্ষা করুন…';
});
