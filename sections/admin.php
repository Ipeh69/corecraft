<!-- ===== ADMIN CONSOLE ===== -->
<div id="page-admin" class="page">
  <div class="admin-shell">
    <aside class="admin-side">
      <div class="admin-side-head">
        <div class="admin-side-title">Admin Console</div>
        <div class="admin-side-sub" id="admin-side-user">Signed in</div>
      </div>
      <nav class="admin-nav">
        <button class="admin-nav-item active" data-admin-view="dashboard" onclick="switchAdminView('dashboard',this)">Dashboard</button>
        <button class="admin-nav-item" data-admin-view="users" onclick="switchAdminView('users',this)">Users &amp; Roles</button>
        <button class="admin-nav-item" data-admin-view="moderation" onclick="switchAdminView('moderation',this)">Moderation</button>
        <button class="admin-nav-item" data-admin-view="logs" onclick="switchAdminView('logs',this)">System Logs</button>
      </nav>
      <button class="admin-back" onclick="showPage('home')">â† Back to CoreCraft</button>
    </aside>

    <main class="admin-main">
      <header class="admin-head">
        <div>
          <h1 class="admin-h1" id="admin-view-title">Dashboard</h1>
          <p class="admin-view-note" id="admin-view-note">Platform health at a glance.</p>
        </div>
        <div class="admin-head-actions">
          <span class="admin-status" id="admin-status" role="status" aria-live="polite"></span>
          <button class="admin-refresh" onclick="refreshAdminView()">â†» Refresh</button>
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


      <!-- Users -->
      <section class="admin-view" id="admin-view-users">
        <div class="admin-toolbar">
          <input class="admin-input" id="user-search" type="search" placeholder="Search name or emailâ€¦" onkeyup="if(event.key==='Enter')loadAdminUsers()"/>
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
