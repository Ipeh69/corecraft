<!-- ===== LOGIN ===== -->
<div id="page-login" class="page">
  <div class="login-bg-blob blob1"></div>
  <div class="login-bg-blob blob2"></div>
  <div class="login-grid"></div>
  <div class="login-card-wrap">
    <div class="login-brand">
      <div class="login-logo"><span class="login-logo-icon"><svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="loginGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#00e5ff"/><stop offset="100%" stop-color="#7c3aed"/></linearGradient></defs><circle cx="32" cy="32" r="26" fill="rgba(0,229,255,.08)"/><circle cx="32" cy="32" r="18" fill="rgba(255,255,255,.05)" stroke="white" stroke-width="1.6"/><path d="M32 6v8M32 50v8M6 32h8M50 32h8M16.97 16.97l5.66 5.66M41.37 41.37l5.66 5.66M16.97 47.03l5.66-5.66M41.37 22.63l5.66-5.66" stroke="url(#loginGrad)" stroke-width="1.8" stroke-linecap="round"/><rect x="22" y="22" width="20" height="20" rx="4" fill="rgba(0,229,255,.15)" stroke="white" stroke-width="1.6"/><path d="M28 28h8v8h-8z" fill="url(#loginGrad)" opacity=".7"/><path d="M32 24c-3 0-4 1.5-4 4s1.5 4 4 4 4-1.5 4-4-1-4-4-4z" stroke="white" stroke-width="1.6" fill="none" stroke-linecap="round"/><path d="M31 30h4" stroke="white" stroke-width="1.6" stroke-linecap="round"/></svg></span><span class="login-logo-text">CoreCraft</span></div>
      <p class="login-tagline" id="login-tagline">Welcome back, PC Builder!</p>
    </div>
    <div class="login-box">
      <div class="login-tabs">
        <button class="login-tab active-tab" id="tab-login" onclick="switchTab('login')">Login</button>
        <button class="login-tab" id="tab-signup" onclick="switchTab('signup')">Sign Up</button>
      </div>
      <div class="login-form">
        <div class="hidden-field form-group" id="field-name">
          <label class="form-label">Full Name</label>
          <div class="input-wrap"><span class="input-icon">👤</span><input class="form-input signup-focus" id="name-input" type="text" autocomplete="name" placeholder="Juan Dela Cruz"/></div>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-wrap"><span class="input-icon">✉️</span><input class="form-input" id="email-input" type="email" autocomplete="email" placeholder="juan@example.com"/></div>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input class="form-input" type="password" id="pw-input" autocomplete="current-password" placeholder="••••••••"/>
            <button class="pw-toggle" onclick="togglePw()" id="pw-eye" type="button">👁</button>
          </div>
        </div>
        <div class="hidden-field form-group" id="field-confirm">
          <label class="form-label">Confirm Password</label>
          <div class="input-wrap"><span class="input-icon">🔒</span><input class="form-input signup-focus" id="confirm-input" type="password" autocomplete="new-password" placeholder="••••••••"/></div>
        </div>
        <div class="remember-row" id="remember-row">
          <label class="remember-label"><input id="remember-input" type="checkbox" style="accent-color:var(--cyan)"/> Remember me</label>
          <button class="forgot-btn" type="button">Forgot password?</button>
        </div>
        <button class="submit-btn submit-login" id="submit-btn" onclick="handleLogin()" type="button">Login</button>
        <div class="login-status" id="login-status" role="status" aria-live="polite"></div>
        <div class="security-note">Passwords are salted and hashed in your browser before being stored.</div>
        <div class="divider"><div class="divider-line"></div><span class="divider-text">or continue with</span><div class="divider-line"></div></div>
        <div class="social-grid google-social-grid">
          <div class="google-signin-slot" id="google-signin-button" aria-label="Continue with Google"></div>
        </div>
      </div>
    </div>
    <div class="login-footer"><button class="back-link" onclick="showPage('home')">← Back to Home</button></div>
  </div>
</div>
