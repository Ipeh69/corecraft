<!-- ===== PRICING AI ===== -->
<div id="page-pricing" class="page">
  <div class="section-wrap" style="padding-top:20px">
    <button class="back-link" onclick="showPage('home')" style="margin-bottom:28px">← Back to Home</button>
    <div class="label-tag">Feature 3 · AI Powered</div>
    <div class="section-title">Component Pricing by Location</div>
    <div class="section-sub">Find computer-parts shops across the Philippines, then ask Gemini to interpret stock information you provide.</div>
    <div class="pricing-map-layout">
      <div class="map-panel">
        <div class="map-toolbar">
          <div>
            <div class="map-panel-title">PC component technicians</div>
            <div class="map-panel-sub">Only PC repair and computer-service technician listings inside the Philippines are shown. Narrow the search by city if needed.</div>
          </div>
          <div class="map-search-row">
            <input id="shop-search" class="chat-textarea" type="text" placeholder="Optional city or area (e.g. Manila, Cebu)" onkeydown="if(event.key==='Enter')searchPhilippinesShops()" />
            <button class="btn-secondary map-search-btn" type="button" onclick="searchPhilippinesShops()">Find technicians</button>
          </div>
        </div>
        <div id="philippines-map" class="philippines-map" aria-label="Map of PC component repair technicians in the Philippines"></div>
        <div class="map-legend"><span class="technician-legend-dot" aria-hidden="true"></span><span>PC component technician</span><span class="map-legend-note">OpenStreetMap listing</span></div>
        <div id="map-status" class="map-status">Loading Leaflet map of the Philippines...</div>
        <div id="shop-results" class="shop-results" aria-live="polite"></div>
      </div>
      <div class="stock-panel card">
        <div class="map-panel-title">Gemini stock assistant</div>
        <p class="map-panel-sub">Paste a shop link, listing, or stock message. Gemini will summarize it and mark anything it cannot verify.</p>
        <label class="compat-label" for="stock-part">Part requested</label>
        <input id="stock-part" class="stock-input" type="text" placeholder="e.g. RTX 4060 8GB" />
        <label class="compat-label" for="stock-location">Preferred city or shop</label>
        <input id="stock-location" class="stock-input" type="text" placeholder="e.g. Gilmore, Quezon City" />
        <label class="compat-label" for="stock-source">Stock information or shop link</label>
        <textarea id="stock-source" class="stock-input stock-source" rows="5" placeholder="Paste the seller's current listing, message, or URL here..."></textarea>
        <button id="stock-check-btn" class="btn-gradient" type="button" onclick="checkPartStock()">Ask Gemini to check stock</button>
        <div id="stock-result" class="stock-result" aria-live="polite"></div>
      </div>
    </div>
    <div class="ai-page-wrap">
      <div class="chat-wrap">
        <div class="chat-messages" id="pricing-messages"></div>
        <div class="quick-bar">
          <div class="quick-label">Quick Questions:</div>
          <div class="quick-btns">
            <button class="quick-btn" onclick="setInput('pricing','RTX 4060 price in Manila')">RTX 4060 in Manila</button>
            <button class="quick-btn" onclick="setInput('pricing','Ryzen 5 5600 price Cebu')">Ryzen 5 5600 in Cebu</button>
            <button class="quick-btn" onclick="setInput('pricing','16GB DDR4 RAM price Davao')">RAM price in Davao</button>
            <button class="quick-btn" onclick="setInput('pricing','Best GPU deals near me')">Best GPU deals</button>
            <button class="quick-btn" onclick="setInput('pricing','Budget gaming PC parts price list')">Budget parts list</button>
          </div>
        </div>
        <div class="chat-input-wrap">
          <div class="chat-row" style="flex-direction:column;align-items:stretch">
            <input id="pricing-location" class="chat-textarea" type="text" placeholder="Your shopping location (e.g. Manila, Cebu, Davao)" />
            <div class="chat-row" style="margin-top:10px">
              <textarea id="pricing-input" class="chat-textarea" rows="2" placeholder="Ask about component prices... e.g. 'RTX 4060 price'" onkeydown="handleKey(event,'pricing')"></textarea>
              <button class="send-btn" id="pricing-send" onclick="sendChat('pricing')">Send</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <footer>
    <span>© 2026 CoreCraft · BS Information Technology Capstone</span>
    <div class="footer-links"><a href="about.html">About</a><a href="contact.html">Contact</a><a href="privacy.html">Privacy</a></div>
  </footer>
</div>
