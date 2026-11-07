<!-- NAV -->
<nav>
  <a class="logo" onclick="if(document.getElementById('page-admin')&&document.getElementById('page-admin').classList.contains('active')){openAdminDashboard();}else{showPage('home');}">
    <div class="logo-icon"><img src="corecraft-logo-mark.png" alt=""></div>
    <div class="logo-text">Core<span>Craft</span></div>
  </a>
  <ul>
    <li class="public-nav-item"><a data-nav-page="compatibility" onclick="showPage('compatibility')">Compatibility Checker</a></li>
    <li class="public-nav-item"><a data-nav-page="build" onclick="showPage('build')">Build Recommendations</a></li>
    <li class="public-nav-item"><a data-nav-page="pricing" onclick="showPage('pricing')">Part Pricing</a></li>
    <li class="public-nav-item"><a data-nav-page="troubleshoot" onclick="showPage('troubleshoot')">Troubleshoot</a></li>
    <li class="public-nav-item"><a data-nav-page="phchat" onclick="showPage('PHchat')">PH Community</a></li>
    <li id="nav-admin-link" style="display:none"><a onclick="openAdminDashboard()">Admin</a></li>
  </ul>
  <div class="nav-actions">
    <button class="btn-login" id="nav-login-btn" onclick="showPage('login')">ðŸ‘¤ Login</button>
    <div class="user-dropdown" id="user-dropdown">
      <button class="user-pill" onclick="toggleDropdown()" id="user-pill">
        <div class="user-avatar" id="user-avatar">J</div>
        <span id="user-displayname">Juan</span> &#9662;
      </button>
      <div class="dropdown-menu hidden" id="dropdown-menu">
        <div class="dropdown-header">
          <div class="dropdown-name" id="dropdown-name">Juan Dela Cruz</div>
          <div class="dropdown-email" id="dropdown-email">juan@example.com</div>
        </div>
        <button class="dropdown-item" onclick="showPage('profile');closeDropdown()">My Profile</button>
        <button class="dropdown-item" id="dropdown-role-switch-item" style="display:none" onclick="toggleAdminMode()">Switch to Admin</button>
        <button class="dropdown-item" id="dropdown-admin-item" style="display:none" onclick="openAdminDashboard();closeDropdown()">Admin Console</button>
        <button class="dropdown-item logout" onclick="handleLogout()">Log Out</button>
        <select class="theme-select dropdown-theme-select" aria-label="Color theme" onchange="setColorTheme(this.value)">
          <option value="dark">Dark mode</option>
          <option value="light">Light mode</option>
        </select>
      </div>
    </div>
  </div>
  <button class="hamburger" onclick="toggleMenu()" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>
<!-- Mobile Menu -->
<div class="mobile-menu" id="mobile-menu">
  <a class="public-mobile-nav-item" data-nav-page="compatibility" onclick="showPage('compatibility');closeMenu()">Compatibility Checker</a>
  <a class="public-mobile-nav-item" data-nav-page="build" onclick="showPage('build');closeMenu()">Build Recommendations</a>
  <a class="public-mobile-nav-item" data-nav-page="pricing" onclick="showPage('pricing');closeMenu()">Part Pricing by Location</a>
  <a class="public-mobile-nav-item" data-nav-page="troubleshoot" onclick="showPage('troubleshoot');closeMenu()">Troubleshooting</a>
  <a class="public-mobile-nav-item" data-nav-page="phchat" onclick="showPage('PHchat');closeMenu()">PH Community</a>
  <a id="mobile-admin-link" style="display:none" onclick="openAdminDashboard();closeMenu()">Admin Console</a>
  <div class="mobile-menu-actions">
    <select class="theme-select mobile-theme-select" aria-label="Color theme" onchange="setColorTheme(this.value)">
      <option value="dark">Dark mode</option>
      <option value="light">Light mode</option>
    </select>
    <button class="btn-login" onclick="showPage('login');closeMenu()">ðŸ‘¤ Login</button>
  </div>
</div>





