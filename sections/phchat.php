<!-- ===== PHCHAT ===== -->
<div id="page-PHchat" class="page">
  <div class="section-wrap ph-community-shell" style="padding-top:20px">
    <button class="back-link" onclick="showPage('home')" style="margin-bottom:28px">← Back to Home</button>
    <div class="label-tag">Feature 5 · Community</div>
    <div class="section-title">PH Community</div>
    <div class="section-sub">Share builds and concerns, connect with PC builders, and message members across the Philippines.</div>
    <div class="phchat-tabs" role="tablist">
      <button class="phchat-tab active" type="button" role="tab" aria-selected="true" onclick="switchPHChatView('posts',this)">Posts</button>
      <button class="phchat-tab" id="phchat-messages-tab" type="button" role="tab" aria-selected="false" onclick="switchPHChatView('messages',this)">Messages<span class="phchat-unread-dot" id="phchat-messages-unread-dot" aria-label="Unread messages" hidden></span></button>
    </div>
    <section id="phchat-posts-view" class="phchat-view active">
      <div class="phchat-feed-layout">
        <aside class="card phchat-community-picker">
          <div class="phchat-sidebar-heading">Your communities</div>
          <div id="PHchat-feed-communities" class="phchat-feed-communities"></div>
          <button type="button" class="phchat-create-community" onclick="openCreateCommunity()"><span aria-hidden="true">＋</span> Create community</button>
          <label class="sr-only" for="feed-community">Post destination</label>
          <select id="feed-community" class="phchat-hidden-select" aria-label="Post destination" onchange="loadCommunityPosts()"></select>
        </aside>
        <div class="phchat-feed-main">
          <div class="phchat-feed-toolbar"><strong>Community posts</strong><span class="phchat-sort-label">Latest</span></div>
          <div class="card phchat-composer">
            <div class="phchat-composer-heading"><strong>Start a post</strong><span>Share a build, question, or PC tip with the community.</span></div>
            <textarea id="community-post-text" class="chat-textarea" rows="3" maxlength="2000" placeholder="What would you like to share?"></textarea>
            <div class="phchat-composer-actions"><label class="phchat-image-picker">Add photo <input id="community-post-image" type="file" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewCommunityImage(this)"></label><span id="community-image-name" class="phchat-muted">Images up to 5 MB</span><button id="community-post-submit" class="send-btn" type="button" onclick="publishCommunityPost()">Post</button></div>
            <div id="community-post-preview" class="phchat-image-preview"></div>
            <div id="community-post-status" class="phchat-status" role="status"></div>
          </div>
          <div id="community-post-list" class="phchat-post-list" aria-live="polite"></div>
        </div>
      </div>
    </section>
    <section id="phchat-messages-view" class="phchat-view">
      <div class="phchat-dm-layout">
        <aside class="card phchat-dm-sidebar">
          <strong>Find a member</strong>
          <input id="dm-user-search" class="chat-textarea" type="search" placeholder="Search name or email" oninput="searchDMUsers()">
          <div id="dm-search-results" class="dm-user-results"></div>
          <strong class="dm-conversations-heading">Conversations</strong>
          <div id="dm-conversation-list" class="dm-user-results"></div>
        </aside>
        <div class="chat-wrap phchat-dm-panel">
          <div class="chat-header phchat-dm-header"><strong id="dm-active-name">Select a member to message</strong></div>
          <div id="dm-messages" class="chat-messages" aria-live="polite"></div>
          <div class="chat-input-wrap"><div class="chat-row"><textarea id="dm-message-input" class="chat-textarea" rows="2" maxlength="2000" placeholder="Write a private message..." onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendPrivateMessage()}"></textarea><button id="dm-send-button" class="send-btn" type="button" onclick="sendPrivateMessage()" disabled>Send</button></div></div>
        </div>
      </div>
    </section>
  </div>
  <footer>
    <span>© 2026 CoreCraft · BS Information Technology Capstone</span>
    <div class="footer-links"><a href="about.html">About</a><a href="contact.html">Contact</a><a href="privacy.html">Privacy</a></div>
  </footer>
</div>
<div class="modal-overlay" id="phchat-report-modal" role="dialog" aria-modal="true" aria-labelledby="phchat-report-title" aria-describedby="phchat-report-description">
  <div class="modal-box">
    <div class="modal-title" id="phchat-report-title">Report post</div>
    <p id="phchat-report-description" style="color:var(--muted);line-height:1.6;margin-bottom:12px">Tell us why this post should be reviewed (for example, spam or abusive content).</p>
    <textarea id="phchat-report-reason" class="chat-textarea" rows="4" maxlength="255" placeholder="Describe the issue..."></textarea>
    <div id="phchat-report-error" role="status" style="min-height:18px;margin-top:6px;color:#f87171;font-size:12px"></div>
    <div class="phchat-report-actions">
      <button type="button" class="btn-secondary" onclick="cancelPHChatReport()">Cancel</button>
      <button type="button" class="btn-gradient" onclick="submitPHChatReport()">Submit report</button>
    </div>
  </div>
</div>
<div class="modal-overlay" id="phchat-notice-modal" role="alertdialog" aria-modal="true" aria-labelledby="phchat-notice-title" aria-describedby="phchat-notice-message">
  <div class="modal-box">
    <div class="modal-title" id="phchat-notice-title">Notice</div>
    <p id="phchat-notice-message" style="color:var(--muted);line-height:1.6"></p>
    <button class="modal-close" type="button" onclick="closePHChatNotice()">OK</button>
  </div>
</div>
<div class="modal-overlay" id="phchat-confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="phchat-confirm-title" aria-describedby="phchat-confirm-message" onclick="if(event.target===this)resolvePHChatConfirm(false)">
  <div class="modal-box phchat-confirm-box">
    <div class="modal-title" id="phchat-confirm-title">Confirm action</div>
    <p id="phchat-confirm-message" style="color:var(--muted);line-height:1.6;margin-top:10px">Are you sure?</p>
    <div class="phchat-report-actions">
      <button type="button" class="btn-secondary" onclick="resolvePHChatConfirm(false)">Cancel</button>
      <button type="button" class="phchat-confirm-delete" onclick="resolvePHChatConfirm(true)">Delete</button>
    </div>
  </div>
</div>
