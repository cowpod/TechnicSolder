
const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');

const navbar = document.getElementById('navbar');
const navlogo = document.getElementById('techniclogo');
function applyTheme(e) {
    if (e.matches) { // dark
      navbar.classList.remove('navbar-light');
      navbar.classList.add('navbar-dark');
      navbar.classList.remove('bg-white');
      navbar.classList.add('bg-dark');
      navlogo.src='./resources/wrenchIconW.svg';
    } else { // light
      navbar.classList.remove('navbar-dark');
      navbar.classList.add('navbar-light');
      navbar.classList.remove('bg-dark');
      navbar.classList.add('bg-white');
      navlogo.src='./resources/wrenchIcon.svg';
    }
}

applyTheme(darkModeQuery);
darkModeQuery.addEventListener('change', applyTheme);