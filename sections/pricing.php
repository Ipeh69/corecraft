<!-- ===== PART PRICING ===== -->
<div id="page-pricing" class="page">
  <div class="section-wrap" style="padding-top:20px">
    <button class="back-link" onclick="showPage('home')" style="margin-bottom:28px">← Back to Home</button>
    <div class="label-tag">Feature 3 · AI Powered</div>
    <div class="section-title">Component Pricing by Location</div>
    <div class="section-sub">Enter your area and the PC part you need, then search nearby stores, current listings, and prices together.</div>
    <div class="pricing-map-layout">
      <div class="map-panel">
        <div class="map-toolbar">
          <div>
            <div class="map-panel-title">Computer parts stores</div>
            <div class="map-panel-sub">Mapped computer stores that sell PC parts near your selected area. OpenStreetMap coverage varies, so not every business will appear.</div>
          </div>
          <div class="map-search-row">
            <button class="btn-secondary map-search-btn" type="button" onclick="locateOnMap(true)">Use my location</button>
          </div>
        </div>
        <div id="philippines-map" class="philippines-map" aria-label="Map of computer parts stores in the Philippines"></div>
        <div class="map-legend"><span class="shop-legend-dot" aria-hidden="true"></span><span>Computer parts store</span><span class="user-location-legend-dot" aria-hidden="true"></span><span>Your location</span><span class="map-legend-note">OpenStreetMap listing</span></div>
        <div id="map-status" class="map-status">Loading Leaflet map of the Philippines...</div>
        <div id="shop-results" class="shop-results" aria-live="polite"></div>
      </div>
      <div class="stock-panel card">
        <div class="map-panel-title">Gemini stock assistant</div>
        <p class="map-panel-sub">One search checks the stores on the map against current web listings for the part. Gemini links sources and flags stock that cannot be verified.</p>
        <label class="compat-label" for="stock-part">Part requested</label>
        <input id="stock-part" class="stock-input" type="text" placeholder="e.g. RTX 4060 8GB" onkeydown="if(event.key==='Enter')checkPartStock()" />
        <label class="compat-label" for="stock-location">Your city or area</label>
        <input id="stock-location" class="stock-input" type="text" placeholder="e.g. Manila, Cebu, or use my location" onkeydown="if(event.key==='Enter')checkPartStock()" />
        <label class="compat-label" for="stock-source">Retailer product URL (optional)</label>
        <textarea id="stock-source" class="stock-input stock-source" rows="3" placeholder="Add a store product page to check it alongside web search..."></textarea>
        <button id="stock-check-btn" class="btn-gradient" type="button" onclick="checkPartStock()">Search stores and part together</button>
        <div id="stock-result" class="stock-result" aria-live="polite"></div>
      </div>
    </div>
  </div>
  <footer>
    <span>© 2026 CoreCraft · BS Information Technology Capstone</span>
    <div class="footer-links"><a href="about.html">About</a><a href="contact.html">Contact</a><a href="privacy.html">Privacy</a></div>
  </footer>
</div>
