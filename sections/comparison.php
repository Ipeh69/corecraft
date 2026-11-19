<!-- ===== PART COMPARISON ===== -->
<div id="page-comparison" class="page">
  <div class="section-wrap" style="padding-top:20px">
    <button class="back-link" onclick="showPage('home')" style="margin-bottom:28px">&larr; Back to Home</button>
    <div class="label-tag">Feature 3</div>
    <div class="section-title">Part Comparison</div>
    <div class="section-sub">Enter two PC parts to compare their specs, strengths, and trade-offs with AI.</div>
    <div class="comparison-panel card">
      <div class="comparison-inputs">
        <div><label class="compat-label" for="comparison-item-a">First item</label><input id="comparison-item-a" class="stock-input" type="text" placeholder="e.g. RTX 5090 Lightning Z" /></div>
        <div><label class="compat-label" for="comparison-item-b">Second item</label><input id="comparison-item-b" class="stock-input" type="text" placeholder="e.g. RTX 4090 SUPRIM X" /></div>
      </div>
      <button id="comparison-submit" class="btn-gradient" type="button" onclick="comparePartsWithAI()">Compare items with AI</button>
      <div id="comparison-result" class="comparison-result" aria-live="polite"><p>Enter two items to see their photos and a detailed comparison.</p></div>
    </div>
  </div>
  <footer>
    <span>&copy; 2026 CoreCraft &middot; BS Information Technology Capstone</span>
    <div class="footer-links"><a href="about.html">About</a><a href="contact.html">Contact</a><a href="privacy.html">Privacy</a><a href="terms.html">Terms</a></div>
  </footer>
</div>
