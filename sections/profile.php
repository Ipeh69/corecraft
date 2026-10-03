<!-- ===== USER PROFILE ===== -->
<div id="page-profile" class="page">
  <div class="section-wrap" style="padding-top:24px;max-width:860px">
    <button class="back-link" onclick="showPage('home')" style="margin-bottom:28px">← Back to Home</button>
    <!-- Profile Header Card -->
    <div style="background:linear-gradient(135deg,rgba(124,58,237,.15),rgba(0,229,255,.08));border:1px solid rgba(0,229,255,.2);border-radius:24px;padding:36px;margin-bottom:24px;display:flex;align-items:center;gap:28px;flex-wrap:wrap">
      <div id="profile-avatar-big" style="width:88px;height:88px;border-radius:50%;background:linear-gradient(135deg,var(--purple),var(--cyan));display:flex;align-items:center;justify-content:center;font-size:34px;font-weight:800;color:#fff;flex-shrink:0;box-shadow:0 0 0 4px rgba(0,229,255,.2)">J</div>
      <div style="flex:1;min-width:200px">
        <div id="profile-name-big" style="font-family:'Syne',sans-serif;font-size:26px;font-weight:800;letter-spacing:-.5px;margin-bottom:4px">Juan Dela Cruz</div>
        <div id="profile-email-big" style="color:var(--muted);font-size:14px;margin-bottom:12px">juan@example.com</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <span class="badge"><span class="pulse"></span> Active Builder</span>
          <span style="display:inline-flex;align-items:center;gap:6px;background:rgba(124,58,237,.12);border:1px solid rgba(124,58,237,.3);color:#a78bfa;padding:5px 14px;border-radius:999px;font-size:13px;font-weight:500">🏅 Member since 2026</span>
        </div>
      </div>
      <button onclick="toggleEditMode()" id="edit-profile-btn" style="background:transparent;border:1px solid var(--border);color:var(--text);padding:10px 20px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;font-family:'DM Sans',sans-serif;white-space:nowrap" onmouseover="this.style.borderColor='var(--cyan)';this.style.color='var(--cyan)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text)'">✏️ Edit Profile</button>
    </div>
    <button class="btn-secondary" onclick="openLoadBuildsModal()" style="width:100%;padding:20px;margin-bottom:24px;font-size:17px;font-weight:700">📂 Saved Builds</button>
    <div class="grid-2" style="margin-bottom:24px">
      <!-- Account Info -->
      <div class="card" style="padding:28px" id="info-view-panel">
        <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px">👤 Account Info</div>
        <div style="display:flex;flex-direction:column;gap:14px">
          <div>
            <div style="font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Full Name</div>
            <div id="view-name" style="font-size:15px;font-weight:500">Juan Dela Cruz</div>
          </div>
          <div>
            <div style="font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Email</div>
            <div id="view-email" style="font-size:15px;font-weight:500">juan@example.com</div>
          </div>
        </div>
      </div>
      <!-- Edit Form (hidden by default) -->
      <div class="card" style="padding:28px;display:none" id="info-edit-panel">
        <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;margin-bottom:20px">✏️ Edit Profile</div>
        <div style="display:flex;flex-direction:column;gap:14px">
          <div>
            <label style="font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:6px">Full Name</label>
            <input id="edit-name" class="chat-textarea" style="width:100%;resize:none;border-radius:8px;padding:10px 14px" placeholder="Your name"/>
          </div>
          <div>
            <label style="font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:6px">Email</label>
            <input id="edit-email" class="chat-textarea" type="email" style="width:100%;resize:none;border-radius:8px;padding:10px 14px" placeholder="your@email.com"/>
          </div>
          <div style="display:flex;gap:10px;margin-top:4px">
            <button onclick="saveProfile()" style="flex:1;background:var(--cyan);color:#000;border:none;padding:11px;border-radius:8px;font-weight:700;font-size:14px;cursor:pointer;font-family:'DM Sans',sans-serif">Save Changes</button>
            <button onclick="toggleEditMode()" style="flex:1;background:transparent;color:var(--muted);border:1px solid var(--border);padding:11px;border-radius:8px;font-weight:600;font-size:14px;cursor:pointer;font-family:'DM Sans',sans-serif">Cancel</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Recent Activity -->
    <div class="card" style="padding:28px;margin-bottom:24px">
      <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;margin-bottom:20px">🕒 Recent Activity</div>
      <div id="recent-activity-list" style="display:flex;flex-direction:column;gap:0"></div>
    </div>
    <!-- Danger Zone -->
    <div style="background:rgba(248,113,113,.05);border:1px solid rgba(248,113,113,.2);border-radius:16px;padding:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
      <div>
        <div style="font-size:15px;font-weight:700;color:#f87171;margin-bottom:4px">Log Out</div>
        <div style="font-size:13px;color:var(--muted)">Sign out of your CoreCraft account on this device.</div>
      </div>
      <button onclick="handleLogout()" style="background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.3);color:#f87171;padding:10px 22px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s" onmouseover="this.style.background='rgba(248,113,113,.22)'" onmouseout="this.style.background='rgba(248,113,113,.12)'">🚪 Log Out</button>
    </div>
  </div>
  <footer>
    <span>© 2026 CoreCraft · BS Information Technology Capstone</span>
    <div class="footer-links"><a href="about.html">About</a><a href="contact.html">Contact</a><a href="privacy.html">Privacy</a></div>
  </footer>
</div>
<!-- Compatibility Modal -->
<div class="modal-overlay" id="compat-modal">
  <div class="modal-box">
    <div class="modal-title">🔍 Compatibility Report</div>
    <div id="modal-content"></div>
    <button class="modal-close" onclick="closeModal()">Close</button>
  </div>
</div>
<div class="modal-overlay" id="component-modal">
  <div class="modal-box">
    <div class="modal-title" id="component-modal-title">Select Component</div>
    <div id="component-list" style="max-height:400px;overflow-y:auto;margin:20px 0;"></div>
    <div style="display:flex;gap:10px;">
      <button class="modal-close" style="flex:1" onclick="closeComponentModal()">Cancel</button>
      <button class="modal-close" style="flex:1;background:var(--cyan);color:#000;border-color:var(--cyan)" onclick="selectComponent()">Select</button>
    </div>
  </div>
</div>
<div class="modal-overlay" id="build-idea-modal">
  <div class="modal-box">
    <div class="modal-title">💡 Add a Build Idea</div>
    <div style="display:flex;flex-direction:column;gap:14px">
      <input id="idea-title" class="chat-textarea" type="text" placeholder="Build name e.g. 1080p Gaming" />
      <select id="idea-category" class="chat-textarea" style="max-width:260px">
        <option value="gaming">🎮 Gaming</option>
        <option value="office">💼 Office</option>
        <option value="workstation">🖥️ Workstation</option>
        <option value="other">🔖 Other</option>
      </select>
      <div class="idea-components-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <input id="idea-cpu" class="chat-textarea" type="text" placeholder="CPU (optional) e.g. Ryzen 5 5600X" />
        <input id="idea-motherboard" class="chat-textarea" type="text" placeholder="Motherboard (optional) e.g. B550-F" />
        <input id="idea-ram" class="chat-textarea" type="text" placeholder="RAM (optional) e.g. 16GB DDR4 3200" />
        <input id="idea-gpu" class="chat-textarea" type="text" placeholder="GPU (optional) e.g. RTX 4060" />
        <input id="idea-storage" class="chat-textarea" type="text" placeholder="Storage (optional) e.g. 1TB NVMe" />
        <input id="idea-psu" class="chat-textarea" type="text" placeholder="PSU (optional) e.g. 650W 80+ Gold" />
        <input id="idea-case" class="chat-textarea" type="text" placeholder="PC Case (optional) e.g. Lian Li Lancool 216" />
      </div>
      <textarea id="idea-description" class="chat-textarea" rows="4" placeholder="Describe your build idea and goals..."></textarea>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn-gradient" style="flex:1;min-width:140px" onclick="saveBuildIdea()">Send</button>
        <button class="btn-secondary" style="flex:1;min-width:140px" onclick="closeBuildIdeaModal()">Cancel</button>
      </div>
    </div>
  </div>
</div>
<div class="modal-overlay" id="saved-builds-modal">
  <div class="modal-box">
    <div class="modal-title">📂 Saved Builds</div>
    <div id="saved-builds-modal-list" style="max-height:400px;overflow-y:auto;margin:20px 0;"></div>
    <button class="modal-close" style="width:100%" onclick="closeSavedBuildsModal()">Close</button>
  </div>
</div>
<div class="modal-overlay" id="create-community-modal">
  <div class="modal-box">
    <div class="modal-title">💬 Create a community</div>
    <p style="color:var(--muted);font-size:13px;line-height:1.6;margin-bottom:18px">Start a room for a shared PC issue, upgrade, or build topic.</p>
    <div style="display:flex;flex-direction:column;gap:12px">
      <label class="compat-label" for="community-name-input">Community name</label>
      <input id="community-name-input" class="chat-textarea" type="text" maxlength="80" placeholder="e.g. AM4 Upgrade Help" />
      <label class="compat-label" for="community-description-input">What is this community about?</label>
      <textarea id="community-description-input" class="chat-textarea" rows="4" maxlength="255" placeholder="e.g. Help each other troubleshoot AM4 builds"></textarea>
      <div id="create-community-status" style="font-size:13px;color:#fca5a5;min-height:18px"></div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn-gradient" style="flex:1;min-width:140px" type="button" onclick="submitCreateCommunity()">Create community</button>
        <button class="btn-secondary" style="flex:1;min-width:140px" type="button" onclick="closeCreateCommunity()">Cancel</button>
      </div>
    </div>
  </div>
</div>
<div class="modal-overlay" id="delete-community-modal" role="dialog" aria-modal="true" aria-labelledby="delete-community-title">
  <div class="modal-box">
    <div class="modal-title" id="delete-community-title">Delete community?</div>
    <p style="color:var(--muted);line-height:1.6">Are you sure you want to delete <strong id="delete-community-name" style="color:var(--text)"></strong>? Its posts, likes, comments, and messages will also be permanently deleted.</p>
    <div id="delete-community-status" role="status" style="min-height:18px;margin-top:12px;color:#fca5a5;font-size:13px"></div>
    <div style="display:flex;gap:10px;margin-top:16px">
      <button type="button" class="btn-secondary" style="flex:1" onclick="closeDeleteCommunityModal()">Cancel</button>
      <button type="button" id="confirm-delete-community-button" style="flex:1;padding:12px;border:1px solid #ef4444;border-radius:8px;background:#ef4444;color:#fff;font-weight:700;cursor:pointer" onclick="confirmDeletePHCommunity()">Delete community</button>
    </div>
  </div>
</div>
