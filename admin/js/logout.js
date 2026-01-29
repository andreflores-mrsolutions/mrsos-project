//TODO: Logout

// Logout robusto con delegación
document.addEventListener('click', function (e) {
  const a = e.target.closest('#btnLogout');
  if (!a) return;

  e.preventDefault();

  const hrefAjax = a.dataset.href || (a.getAttribute('href') + '?ajax=1');
  const redirect = a.dataset.redirect || '../login/login.php';

  fetch(hrefAjax, {
    method: 'GET',               // si prefieres, usa 'POST' y ajusta logout.php
    credentials: 'same-origin'
  })
    .catch(() => { })               // aunque falle, intentamos redirigir
    .finally(() => { window.location.href = redirect; });
});