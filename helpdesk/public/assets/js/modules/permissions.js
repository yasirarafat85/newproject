// পারমিশন ম্যাট্রিক্স — "সব বাছুন" / "কিছু না" বোতাম।
import { $$, delegate } from '../core/dom.js';

delegate(document, '[data-perm-all]', 'click', (event, button) => {
  const shouldCheck = button.dataset.permAll === '1';
  $$('.perm-list input[type="checkbox"]').forEach((box) => { box.checked = shouldCheck; });
});

delegate(document, '[data-perm-group-all]', 'click', (event, button) => {
  const group = document.querySelector(`[data-perm-group="${button.dataset.permGroupAll}"]`);
  const shouldCheck = button.dataset.on === '1';
  group?.querySelectorAll('input[type="checkbox"]').forEach((box) => { box.checked = shouldCheck; });
});
