<!-- NAV -->
<nav>
  <a class="logo" onclick="showPage('home')">
    <div class="logo-icon"><svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="logoGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#00e5ff"/><stop offset="100%" stop-color="#7c3aed"/></linearGradient></defs><circle cx="32" cy="32" r="26" fill="rgba(0,229,255,.08)"/><circle cx="32" cy="32" r="18" fill="rgba(255,255,255,.05)" stroke="white" stroke-width="1.6"/><path d="M32 6v8M32 50v8M6 32h8M50 32h8M16.97 16.97l5.66 5.66M41.37 41.37l5.66 5.66M16.97 47.03l5.66-5.66M41.37 22.63l5.66-5.66" stroke="url(#logoGrad)" stroke-width="1.8" stroke-linecap="round"/><rect x="22" y="22" width="20" height="20" rx="4" fill="rgba(0,229,255,.15)" stroke="white" stroke-width="1.6"/><path d="M28 28h8v8h-8z" fill="url(#logoGrad)" opacity=".7"/><path d="M32 24c-3 0-4 1.5-4 4s1.5 4 4 4 4-1.5 4-4-1-4-4-4z" stroke="white" stroke-width="1.6" fill="none" stroke-linecap="round"/><path d="M31 30h4" stroke="white" stroke-width="1.6" stroke-linecap="round"/></svg></div>
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
