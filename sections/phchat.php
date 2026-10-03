<!-- ===== PHCHAT ===== -->
<div id="page-PHchat" class="page">
  <div class="section-wrap ph-community-shell" style="padding-top:20px">
    <button class="back-link" onclick="showPage('home')" style="margin-bottom:28px">← Back to Home</button>
    <div class="label-tag">Feature 5 · Community</div>
    <div class="section-title">PH Community</div>
    <div class="section-sub">Share builds and concerns, connect with PC builders, and message members across the Philippines.</div>
    <div class="phchat-tabs" role="tablist">
      <button class="phchat-tab active" type="button" role="tab" aria-selected="true" onclick="switchPHChatView('posts',this)">Posts</button>
      <button class="phchat-tab phchat-icon-tab" type="button" role="tab" aria-label="Private messages" aria-selected="false" title="Private messages" onclick="switchPHChatView('messages',this)">
        <svg viewBox="0 0 24 24" width="19" height="19" aria-hidden="true" focusable="false"><path d="M3.5 6.75A2.75 2.75 0 0 1 6.25 4h11.5a2.75 2.75 0 0 1 2.75 2.75v10.5A2.75 2.75 0 0 1 17.75 20h-11.5A2.75 2.75 0 0 1 3.5 17.25V6.75Zm2.1-.9 6.4 5.1 6.4-5.1H5.6Zm12.9 1.9-5.88 4.69a1 1 0 0 1-1.24 0L5.5 7.75v9.5c0 .41.34.75.75.75h11.5c.41 0 .75-.34.75-.75v-9.5Z" fill="currentColor"/></svg>
      </button>
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
            <textarea id="community-post-text" class="chat-textarea" rows="3" maxlength="2000" placeholder="Share a PC concern, build, or question with this community..."></textarea>
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
