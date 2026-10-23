<!-- ===== ADMIN CONSOLE ===== -->
<div id="page-admin" class="page">
  <div class="admin-shell">
    <aside class="admin-side">
      <div class="admin-side-head">
        <div class="admin-side-title">Admin Console</div>
        <div class="admin-side-sub" id="admin-side-user">Signed in</div>
      </div>
      <nav class="admin-nav">
        <button class="admin-nav-item active" data-admin-view="dashboard" onclick="switchAdminView('dashboard',this)"><span>📊</span> Dashboard</button>
        <button class="admin-nav-item" data-admin-view="components" onclick="switchAdminView('components',this)"><span>🧩</span> Component Catalog</button>
        <button class="admin-nav-item" data-admin-view="rules" onclick="switchAdminView('rules',this)"><span>⚙️</span> Compatibility Rules</button>
        <button class="admin-nav-item" data-admin-view="users" onclick="switchAdminView('users',this)"><span>👥</span> Users &amp; Roles</button>
        <button class="admin-nav-item" data-admin-view="moderation" onclick="switchAdminView('moderation',this)"><span>🛡️</span> Moderation</button>
        <button class="admin-nav-item" data-admin-view="pricing" onclick="switchAdminView('pricing',this)"><span>💸</span> Pricing Feeds</button>
        <button class="admin-nav-item" data-admin-view="analytics" onclick="switchAdminView('analytics',this)"><span>📈</span> Analytics &amp; Health</button>
        <button class="admin-nav-item" data-admin-view="logs" onclick="switchAdminView('logs',this)"><span>🗂️</span> System Logs</button>
      </nav>
      <button class="admin-back" onclick="showPage('home')">← Back to CoreCraft</button>
    </aside>

    <main class="admin-main">
      <header class="admin-head">
        <div>
          <h1 class="admin-h1" id="admin-view-title">Dashboard</h1>
          <p class="admin-view-note" id="admin-view-note">Platform health at a glance.</p>
        </div>
        <div class="admin-head-actions">
          <span class="admin-status" id="admin-status" role="status" aria-live="polite"></span>
          <button class="admin-refresh" onclick="refreshAdminView()">↻ Refresh</button>
        </div>
      </header>

      <!-- Dashboard -->
      <section class="admin-view active" id="admin-view-dashboard">
        <div class="admin-stat-grid" id="admin-stat-grid"></div>
        <div class="admin-chart-row">
          <div class="admin-card">
            <div class="admin-card-title with-actions">
              <span>Activity trend</span>
              <span class="admin-legend" id="admin-trend-legend"></span>
            </div>
            <div id="admin-trend-chart" class="admin-chart"></div>
          </div>
          <div class="admin-card">
            <div class="admin-card-title">Users by role</div>
            <div id="admin-role-donut" class="admin-donut-wrap"></div>
          </div>
        </div>
        <div class="admin-grad-grid" id="admin-grad-grid"></div>
        <div class="admin-cols">
          <div class="admin-card">
            <div class="admin-card-title">Recent sign-ups</div>
            <div id="admin-recent-users" class="admin-list"></div>
          </div>
          <div class="admin-card">
            <div class="admin-card-title">Latest system activity</div>
            <div id="admin-recent-logs" class="admin-list"></div>
          </div>
        </div>
      </section>

      <!-- Components -->
      <section class="admin-view" id="admin-view-components">
        <div class="admin-toolbar">
          <input class="admin-input" id="component-search" type="search" placeholder="Search brand or model…" onkeyup="if(event.key==='Enter')loadAdminComponents()"/>
          <select class="admin-input admin-select" id="component-category-filter" onchange="loadAdminComponents()">
            <option value="">All categories</option>
            <option value="cpu">CPU</option>
            <option value="motherboard">Motherboard</option>
            <option value="gpu">GPU</option>
            <option value="ram">RAM</option>
            <option value="storage">Storage</option>
            <option value="psu">PSU</option>
            <option value="case">Case</option>
            <option value="cooler">Cooler</option>
            <option value="other">Other</option>
          </select>
          <button class="admin-btn primary" onclick="openComponentForm()">+ New component</button>
          <button class="admin-btn" onclick="openBulkPrice()">Bulk price update</button>
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr><th>Category</th><th>Brand / Model</th><th>Socket</th><th>TDP</th><th>Form factor</th><th>Price (PHP)</th><th>USD</th><th>Stock</th><th>Active</th><th></th></tr>
            </thead>
            <tbody id="admin-component-rows"></tbody>
          </table>
        </div>
      </section>

      <!-- Compatibility rules -->
      <section class="admin-view" id="admin-view-rules">
        <div class="admin-toolbar">
          <span class="admin-toolbar-note">Toggle rules to control which checks run. Override edge cases by disabling a rule or adjusting its severity.</span>
          <button class="admin-btn primary" onclick="openRuleForm()">+ New rule</button>
        </div>
        <div id="admin-rule-list" class="admin-rule-list"></div>
      </section>

      <!-- Users -->
      <section class="admin-view" id="admin-view-users">
        <div class="admin-toolbar">
          <input class="admin-input" id="user-search" type="search" placeholder="Search name or email…" onkeyup="if(event.key==='Enter')loadAdminUsers()"/>
          <select class="admin-input admin-select" id="user-role-filter" onchange="loadAdminUsers()">
            <option value="">All roles</option>
            <option value="user">Standard users</option>
            <option value="verified">Verified builders</option>
            <option value="moderator">Moderators</option>
            <option value="admin">Admins</option>
          </select>
          <button class="admin-btn" onclick="loadAdminUsers()">Search</button>
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Builds</th><th>Joined</th><th>Last login</th><th></th></tr>
            </thead>
            <tbody id="admin-user-rows"></tbody>
          </table>
        </div>
      </section>

      <!-- Moderation -->
      <section class="admin-view" id="admin-view-moderation">
        <div class="admin-cols">
          <div class="admin-card">
            <div class="admin-card-title with-actions">
              <span>Reported content</span>
              <select class="admin-input admin-select small" id="report-status-filter" onchange="loadAdminReports()">
                <option value="open">Open</option>
                <option value="">All</option>
                <option value="resolved">Resolved</option>
                <option value="dismissed">Dismissed</option>
              </select>
            </div>
            <div id="admin-report-list" class="admin-list"></div>
          </div>
          <div class="admin-card">
            <div class="admin-card-title with-actions">
              <span>Community content</span>
              <select class="admin-input admin-select small" id="content-type-filter" onchange="loadAdminContent()">
                <option value="posts">Posts</option>
                <option value="comments">Comments</option>
                <option value="messages">Live messages</option>
              </select>
            </div>
            <div id="admin-content-list" class="admin-list"></div>
          </div>
        </div>
      </section>

      <!-- Pricing -->
      <section class="admin-view" id="admin-view-pricing">
        <div class="admin-cols">
          <div class="admin-card">
            <div class="admin-card-title with-actions">
              <span>Regional pricing multipliers</span>
              <button class="admin-btn primary small" onclick="openRegionForm()">+ Region</button>
            </div>
            <div id="admin-region-list" class="admin-list"></div>
          </div>
          <div class="admin-card">
            <div class="admin-card-title">AI pricing estimator sync</div>
            <p class="admin-hint">Batch-adjust catalog prices so the AI pricing estimation stays aligned with regional markets.</p>
            <div class="admin-form-grid">
              <label class="admin-label">Scope
                <select class="admin-input" id="bulk-category">
                  <option value="">All categories</option>
                  <option value="cpu">CPU</option>
                  <option value="motherboard">Motherboard</option>
                  <option value="gpu">GPU</option>
                  <option value="ram">RAM</option>
                  <option value="storage">Storage</option>
                  <option value="psu">PSU</option>
                  <option value="case">Case</option>
                  <option value="cooler">Cooler</option>
                </select>
              </label>
              <label class="admin-label">Adjustment mode
                <select class="admin-input" id="bulk-mode">
                  <option value="percent">Percentage change (%)</option>
                  <option value="multiplier">Multiplier (×)</option>
                  <option value="usd_rate">Recalculate from USD rate</option>
                </select>
              </label>
              <label class="admin-label">Value
                <input class="admin-input" id="bulk-value" type="number" step="0.01" placeholder="e.g. 5 or 55.5"/>
              </label>
            </div>
            <button class="admin-btn primary" onclick="applyBulkPrice()">Apply price update</button>
          </div>
        </div>
      </section>

      <!-- Analytics -->
      <section class="admin-view" id="admin-view-analytics">
        <div class="admin-stat-grid" id="admin-health-grid"></div>
        <div class="admin-chart-row">
          <div class="admin-card">
            <div class="admin-card-title with-actions">
              <span>Events per day (14 days)</span>
              <span class="admin-legend" id="admin-an-legend"></span>
            </div>
            <div id="admin-an-trend" class="admin-chart"></div>
          </div>
          <div class="admin-card">
            <div class="admin-card-title">Traffic by event type (7d)</div>
            <div id="admin-event-donut" class="admin-donut-wrap"></div>
          </div>
        </div>
        <div class="admin-grad-grid" id="admin-an-grad"></div>
        <div class="admin-chart-row even">
          <div class="admin-card">
            <div class="admin-card-title">New sign-ups per day</div>
            <div id="admin-signup-bars" class="admin-chart short"></div>
          </div>
          <div class="admin-card">
            <div class="admin-card-title">Active parts by category</div>
            <div id="admin-category-bars" class="admin-bars"></div>
          </div>
        </div>
        <div class="admin-cols">
          <div class="admin-card">
            <div class="admin-card-title">Most searched parts</div>
            <div id="admin-top-searches" class="admin-list"></div>
          </div>
          <div class="admin-card">
            <div class="admin-card-title">Trending saved builds</div>
            <div id="admin-trending-builds" class="admin-list"></div>
          </div>
          <div class="admin-card">
            <div class="admin-card-title">Failed compatibility queries</div>
            <div id="admin-failed-queries" class="admin-list"></div>
          </div>
          <div class="admin-card">
            <div class="admin-card-title">Peak activity hours (7 days)</div>
            <div id="admin-peak-hours" class="admin-bars"></div>
          </div>
        </div>
      </section>

      <!-- Logs -->
      <section class="admin-view" id="admin-view-logs">
        <div class="admin-toolbar">
          <select class="admin-input admin-select" id="log-level-filter" onchange="loadAdminLogs()">
            <option value="">All levels</option>
            <option value="error">Errors</option>
            <option value="warning">Warnings</option>
            <option value="info">Info</option>
          </select>
          <button class="admin-btn" onclick="loadAdminLogs()">Refresh</button>
          <button class="admin-btn danger" onclick="clearAdminLogs(30)">Clear older than 30 days</button>
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Level</th><th>Source</th><th>Message</th><th>When</th></tr></thead>
            <tbody id="admin-log-rows"></tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</div>
