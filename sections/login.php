<!-- ===== LOGIN ===== -->
<div id="page-login" class="page">
  <div class="login-bg-blob blob1"></div>
  <div class="login-bg-blob blob2"></div>
  <div class="login-grid"></div>
  <div class="login-card-wrap">
    <div class="login-brand">
      <div class="login-logo"><span class="login-logo-icon"><img src="Ipeh/corecraft-logo-mark.png" alt=""></span><span class="login-logo-text">CoreCraft</span></div>
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
