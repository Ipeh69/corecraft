<!-- ===== TROUBLESHOOT AI ===== -->
<div id="page-troubleshoot" class="page">
  <div class="section-wrap" style="padding-top:20px">
    <button class="back-link" onclick="showPage('home')" style="margin-bottom:28px">← Back to Home</button>
    <div class="label-tag">Feature 4 · AI Powered</div>
    <div class="section-title">Basic Troubleshooting</div>
    <div class="section-sub">Tell the bot your PC problem and get step-by-step repair guidance to fix it.</div>
    <div class="ai-page-wrap">
      <div class="chat-wrap">
        <div class="chat-messages" id="trouble-messages"></div>
        <div class="quick-bar">
          <div class="quick-label">Basic Troubleshooting:</div>
          <div class="quick-btns">
            <button class="quick-btn" onclick="setInput('trouble','My PC won\'t turn on at all')">PC won't turn on</button>
            <button class="quick-btn" onclick="setInput('trouble','No display or black screen on boot')">No display / black screen</button>
            <button class="quick-btn" onclick="setInput('trouble','PC keeps restarting randomly')">Random restarts</button>
            <button class="quick-btn" onclick="setInput('trouble','PC is overheating and shutting down')">Overheating</button>
            <button class="quick-btn" onclick="setInput('trouble','Blue screen of death BSOD error')">BSOD blue screen</button>
            <button class="quick-btn" onclick="setInput('trouble','PC is very slow and lagging')">PC slow / lagging</button>
          </div>
        </div>
        <div class="chat-input-wrap">
          <div class="chat-row">
            <textarea id="trouble-input" class="chat-textarea" rows="2" placeholder="Describe your PC problem and I will help you fix it..." onkeydown="handleKey(event,'trouble')"></textarea>
            <button class="send-btn" id="trouble-send" onclick="sendChat('trouble')">Send</button>
          </div>
        </div>
      </div>
      <div class="ai-info-cards">
        <div class="ai-info-card"><div class="ai-info-emoji">🔍</div><div class="ai-info-num">Diagnosis</div><div class="ai-info-sub">Identify root cause</div></div>
        <div class="ai-info-card"><div class="ai-info-emoji">🛠️</div><div class="ai-info-num">Step-by-step</div><div class="ai-info-sub">Clear fix instructions</div></div>
        <div class="ai-info-card"><div class="ai-info-emoji">⚡</div><div class="ai-info-num">Instant</div><div class="ai-info-sub">AI answers fast</div></div>
      </div>
    </div>
  </div>
  <footer>
    <span>© 2026 CoreCraft · BS Information Technology Capstone</span>
    <div class="footer-links"><a href="about.html">About</a><a href="contact.html">Contact</a><a href="privacy.html">Privacy</a></div>
  </footer>
</div>
