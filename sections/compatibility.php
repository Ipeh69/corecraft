<!-- ===== COMPATIBILITY ===== -->
<div id="page-compatibility" class="page">
  <div class="section-wrap" style="padding-top:20px">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:28px">
      <button class="back-link" onclick="showPage('home')">← Back to Home</button>
    </div>
    <div style="margin-bottom:32px">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#00bfff,#087cff);display:flex;align-items:center;justify-content:center;font-size:20px">🤖</div>
        <div style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800">AI Compatibility Checker</div>
      </div>
      <p style="color:var(--muted);font-size:15px;line-height:1.6;margin-top:12px">Enter your PC parts below to check socket compatibility, RAM support, and power requirements. Any issues will include a clear explanation on the affected component cards.</p>
    </div>
    <div class="card" style="padding:32px;margin-bottom:24px">
        <div class="compat-grid" style="margin-bottom:20px">
        <div class="compat-field">
          <label class="compat-label">CPU</label>
          <div class="compat-row">
            <input type="text" id="compat-cpu" placeholder="e.g. Intel Core i5-13400F, AMD Ryzen 5 7600X" onkeydown="if(event.key==='Enter')runCompatCheck()" />
            <button type="button" class="btn-secondary compat-btn" onclick="openComponentSelector('cpu')">Pick CPU</button>
          </div>
        </div>
        <div class="compat-field">
          <label class="compat-label">Motherboard</label>
          <div class="compat-row">
            <input type="text" id="compat-mb" placeholder="e.g. MSI B760M Mortar, ASUS ROG Strix B650-A" onkeydown="if(event.key==='Enter')runCompatCheck()" />
            <button type="button" class="btn-secondary compat-btn" onclick="openComponentSelector('motherboard')">Pick MB</button>
          </div>
        </div>
        <div class="compat-field">
          <label class="compat-label">RAM</label>
          <div class="compat-row">
            <input type="text" id="compat-ram" placeholder="e.g. 16GB DDR5 5200MHz, 32GB DDR4 3600MHz" onkeydown="if(event.key==='Enter')runCompatCheck()" />
            <button type="button" class="btn-secondary compat-btn" onclick="openComponentSelector('ram')">Pick RAM</button>
          </div>
        </div>
        <div class="compat-field">
          <label class="compat-label">GPU</label>
          <div class="compat-row">
            <input type="text" id="compat-gpu" placeholder="e.g. RTX 4060, RX 7600, or None (iGPU)" onkeydown="if(event.key==='Enter')runCompatCheck()" />
            <button type="button" class="btn-secondary compat-btn" onclick="openComponentSelector('gpu')">Pick GPU</button>
          </div>
        </div>
        <div class="compat-field">
          <label class="compat-label">Storage</label>
          <div class="compat-row">
            <input type="text" id="compat-storage" placeholder="e.g. 1TB NVMe Gen4 SSD, 2TB HDD" onkeydown="if(event.key==='Enter')runCompatCheck()" />
            <button type="button" class="btn-secondary compat-btn" onclick="openComponentSelector('storage')">Pick Storage</button>
          </div>
        </div>
        <div class="compat-field">
          <label class="compat-label">PSU</label>
          <div class="compat-row">
            <input type="text" id="compat-psu" placeholder="e.g. Corsair 650W 80+ Gold, Seasonic 750W" onkeydown="if(event.key==='Enter')runCompatCheck()" />
            <button type="button" class="btn-secondary compat-btn" onclick="openComponentSelector('psu')">Pick PSU</button>
          </div>
        </div>
        <div class="compat-field" style="grid-column:1/-1">
          <label class="compat-label">PC Case (Optional)</label>
          <div class="compat-row">
            <input type="text" id="compat-case" placeholder="e.g. Lian Li Lancool 216, NZXT H510" onkeydown="if(event.key==='Enter')runCompatCheck()" />
          </div>
        </div>
        <div class="compat-field" style="grid-column:1/-1">
          <label class="compat-label">Price Store or City (Optional)</label>
          <input id="compat-price-store" class="chat-textarea" type="text" placeholder="e.g. Dynaquest Gilmore, PC Express Cebu, or Manila" />
          <div style="font-size:12px;color:var(--muted);margin-top:8px">Gemini will estimate for this store or location. Confirm the current listing before buying.</div>
        </div>
      </div>
      <div class="compat-actions">
        <button class="btn-gradient" onclick="runCompatCheck()">✓ Check Compatibility</button>
        <button class="btn-secondary" onclick="saveCurrentBuild()">💾 Save Build</button>
      </div>
      <div class="compat-header" style="margin-top:24px">
        <div class="compat-status" id="compat-status">✓ 0 of 6 Parts Compatible</div>
      </div>
      <div id="components-grid" class="grid-3" style="margin-top:24px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));"></div>
      <div id="ai-compat-result" class="card" style="display:none;margin-top:24px;padding:24px"></div>
      <div id="budget-list" style="margin-top:20px"></div>
    </div>
  </div>
  <footer>
    <span>© 2026 CoreCraft · BS Information Technology Capstone</span>
    <div class="footer-links"><a href="about.html">About</a><a href="contact.html">Contact</a><a href="privacy.html">Privacy</a></div>
  </footer>
</div>
