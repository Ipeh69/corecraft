<!-- ===== BUILD RECOMMENDATIONS ===== -->
<div id="page-build" class="page">
  <div class="section-wrap" style="padding-top:20px">
    <button class="back-link" onclick="showPage('home')" style="margin-bottom:28px">← Back to Home</button>
    <div class="label-tag">Feature 2</div>
    <div class="section-title">Build Recommendations</div>
    <div class="section-sub">Database-powered build lists by category. Click a category to explore builds.</div>
    <div class="build-tabs">
      <button class="build-tab active" data-type="gaming" onclick="switchBuild('gaming',this)">🎮 Gaming</button>
      <button class="build-tab" data-type="office" onclick="switchBuild('office',this)">💼 Office</button>
      <button class="build-tab" data-type="students" onclick="switchBuild('students',this)">🎓 Students</button>
      <button class="build-tab" data-type="streaming" onclick="switchBuild('streaming',this)">📡 Streaming</button>
      <button class="build-tab" data-type="editing" onclick="switchBuild('editing',this)">🎬 Video Editing</button>
      <button class="build-tab" data-type="workstation" onclick="switchBuild('workstation',this)">🖥️ Workstation</button>
      <button class="build-tab" data-type="home" onclick="switchBuild('home',this)">🏠 Home/Office</button>
    </div>
    <div class="budget-filter card">
      <div class="budget-filter-copy"><strong>Find a build within your budget</strong><span>Recommendations start at ₱20,000.</span></div>
      <label for="build-budget">Maximum budget</label>
      <div class="budget-input-wrap"><span>₱</span><input id="build-budget" type="number" min="20000" step="1000" oninput="renderBuilds(getActiveBuildType())" /></div>
      <button type="button" class="btn-secondary" onclick="document.getElementById('build-budget').value='';renderBuilds(getActiveBuildType())">Clear</button>
    </div>
    <div id="build-content"></div>
    <!-- User-contributed build ideas removed per request -->
  </div>
  <footer>
    <span>© 2026 CoreCraft · BS Information Technology Capstone</span>
    <div class="footer-links"><a href="about.html">About</a><a href="contact.html">Contact</a><a href="privacy.html">Privacy</a></div>
  </footer>
</div>
