// Applies the saved light/dark theme before paint, then wires the toggle and mobile menu.
(function () {
  var html = document.documentElement;
  var saved = null;
  try { saved = localStorage.getItem('theme'); } catch (e) {}
  if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    html.setAttribute('data-theme', 'dark');
  }

  document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('theme-toggle');
    if (toggle) {
      toggle.addEventListener('click', function () {
        var dark = html.getAttribute('data-theme') === 'dark';
        html.setAttribute('data-theme', dark ? 'light' : 'dark');
        try { localStorage.setItem('theme', dark ? 'light' : 'dark'); } catch (e) {}
      });
    }

    var menu = document.getElementById('menu-btn');
    var sidebar = document.getElementById('sidebar');
    if (menu && sidebar) {
      menu.addEventListener('click', function () { sidebar.classList.toggle('open'); });
    }
  });
})();
