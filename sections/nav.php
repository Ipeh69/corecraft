<!-- NAV -->
<nav>
  <a class="logo" onclick="showPage('home')">
    <div class="logo-icon"><img src="corecraft-logo-mark.png" alt=""></div>
    <div class="logo-text">Core<span>Craft</span></div>
  </a>
  <ul>
    <li><a onclick="showPage('compatibility')">Compatibility Checker</a></li>
    <li><a onclick="showPage('build')">Build Recs</a></li>
    <li><a onclick="showPage('pricing')">Part Pricing</a></li>
    <li><a onclick="showPage('troubleshoot')">Troubleshoot</a></li>
    <li><a onclick="showPage('PHchat')">PH Community</a></li>
  </ul>
  <div class="nav-actions">
    <button class="btn-login" id="nav-login-btn" onclick="showPage('login')">👤 Login</button>
    <div class="user-dropdown" id="user-dropdown">
      <button class="user-pill" onclick="toggleDropdown()" id="user-pill">
        <div class="user-avatar" id="user-avatar">J</div>
        <span id="user-displayname">Juan</span> ▾
      </button>
      <div class="dropdown-menu hidden" id="dropdown-menu">
        <div class="dropdown-header">
          <div class="dropdown-name" id="dropdown-name">Juan Dela Cruz</div>
          <div class="dropdown-email" id="dropdown-email">juan@example.com</div>
        </div>
        <button class="dropdown-item" onclick="showPage('profile');closeDropdown()">👤 My Profile</button>
        <button class="dropdown-item logout" onclick="handleLogout()">🚪 Log Out</button>
      </div>
    </div>
    <button class="btn-start" onclick="showPage('compatibility')">Start Building</button>
  </div>
  <button class="hamburger" onclick="toggleMenu()" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>
<!-- Mobile Menu -->
<div class="mobile-menu" id="mobile-menu">
  <a onclick="showPage('compatibility');closeMenu()">⚡ Compatibility Checker</a>
  <a onclick="showPage('build');closeMenu()">💡 Build Recommendations</a>
  <a onclick="showPage('pricing');closeMenu()">📍 Part Pricing by Location</a>
  <a onclick="showPage('troubleshoot');closeMenu()">🛠️ Troubleshooting</a>
  <a onclick="showPage('PHchat');closeMenu()">🌐 PH Community</a>
  <div class="mobile-menu-actions">
    <button class="btn-login" onclick="showPage('login');closeMenu()">👤 Login</button>
    <button class="btn-start" onclick="showPage('compatibility');closeMenu()">⚡ Start Building</button>
  </div>
</div>
