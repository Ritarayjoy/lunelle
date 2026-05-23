
const state = {
  currentUser: null,
  posts: [],
  moods: [],
  trendingTags: [],
  activeFilter: null,
};

const feed             = document.getElementById('feed');
const postText         = document.getElementById('postText');
const moodSelect       = document.getElementById('moodSelect');
const musicInput       = document.getElementById('musicInput');
const postBtn          = document.getElementById('postBtn');
const trendingList     = document.getElementById('trendingList');
const moodFilter       = document.getElementById('moodFilter');
const profileName      = document.getElementById('profileName');
const profileHandle    = document.getElementById('profileHandle');
const profileAvatar    = document.getElementById('profileAvatar');
const composerAvatar   = document.getElementById('composerAvatar');
const userAvatar       = document.getElementById('userAvatar');
const currentVibe      = document.getElementById('currentVibe');
const postCount        = document.getElementById('postCount');
const followerCount    = document.getElementById('followerCount');
const followingCount   = document.getElementById('followingCount');
const nowPlayingTitle  = document.getElementById('nowPlayingTitle');
const nowPlayingArtist = document.getElementById('nowPlayingArtist');
const playBtn          = document.getElementById('playBtn');
const notifBadge       = document.getElementById('notifBadge');

const API = 'api.php';


async function init() {
  await loadUser();
  await loadMoods();
  await loadPosts();
  await loadTrending();
  renderMoodFilter();
  attachEvents();
}

async function loadUser() {
  const saved = localStorage.getItem('vibecheck_user');

  if (saved) {
    const basic = JSON.parse(saved);
    const res  = await fetch(`${API}?action=get_user&handle=${basic.handle}`);
    const user = await res.json();

    if (user.error) {
      localStorage.removeItem('vibecheck_user');
      await registerNewUser();
    } else {
      state.currentUser = user;
      renderProfile();
    }
  } else {
    await registerNewUser();
  }
}


async function registerNewUser() {
  let name = '';
  while (!name.trim()) {
    name = prompt('Welcome to VibeCheck! Enter your display name:') || '';
  }

  const handle       = '@' + name.trim().toLowerCase().replace(/\s+/g, '_');
  const initials     = name.trim().charAt(0).toUpperCase();
  const avatar_color = generateColor(name);

  const res  = await fetch(`${API}?action=register`, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      display_name: name.trim(),
      handle,
      initials,
      avatar_color,
    }),
  });
  const data = await res.json();

  if (data.error) {
    const newHandle = handle + Math.floor(Math.random() * 999);
    const retry = await fetch(`${API}?action=register`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        display_name: name.trim(),
        handle:       newHandle,
        initials,
        avatar_color,
      }),
    });
    const retryData = await retry.json();
    state.currentUser = retryData;
  } else {
    state.currentUser = data;
  }

  
  localStorage.setItem('vibecheck_user', JSON.stringify({
    handle: state.currentUser.handle,
  }));

  renderProfile();
}

function renderProfile() {
  const user = state.currentUser;
  if (!user) return;

  profileName.textContent    = user.display_name;
  profileHandle.textContent  = user.handle;
  profileAvatar.textContent  = user.initials;
  composerAvatar.textContent = user.initials;
  userAvatar.textContent     = user.initials;

  postCount.textContent     = user.post_count  || 0;
  followerCount.textContent = user.followers   || 0;
  followingCount.textContent= user.following   || 0;

  currentVibe.textContent = user.current_vibe || 'No vibe set yet';

  if (user.now_playing && user.now_playing.music_title) {
    nowPlayingTitle.textContent  = user.now_playing.music_title;
    nowPlayingArtist.textContent = user.now_playing.music_artist || '';
    playBtn.textContent = '▶';
  } else {
    nowPlayingTitle.textContent  = 'Nothing playing';
    nowPlayingArtist.textContent = '';
    playBtn.textContent = '-';
  }


  loadUnreadCount();
}

async function loadMoods() {
  const res   = await fetch(`${API}?action=get_moods`);
  const moods = await res.json();
  state.moods = moods;

  moodSelect.innerHTML = '<option value="">Choose your feeling</option>';
  moods.forEach(mood => {
    const option       = document.createElement('option');
    option.value       = mood.name;
    option.textContent = mood.name;
    moodSelect.appendChild(option);
  });
}

async function loadPosts(filter) {
  const url = filter
    ? `${API}?action=get_posts&mood=${encodeURIComponent(filter)}`
    : `${API}?action=get_posts`;

  const res   = await fetch(url);
  state.posts = await res.json();
  renderFeed();
}


async function loadTrending() {
  const res          = await fetch(`${API}?action=get_trending`);
  state.trendingTags = await res.json();
  renderTrending();
}


async function loadUnreadCount() {
  if (!state.currentUser) return;

  const res  = await fetch(`${API}?action=get_unread_count&user_id=${state.currentUser.id}`);
  const data = await res.json();

  if (data.count > 0) {
    notifBadge.textContent = data.count;
    notifBadge.classList.add('visible');
  } else {
    notifBadge.classList.remove('visible');
  }
}


function renderFeed() {
  feed.innerHTML = '';

  if (!state.posts || state.posts.length === 0) {
    feed.innerHTML = `
      <div class="empty-feed">
        <strong>No vibes here yet</strong>
        Be the first to post your vibe!
      </div>`;
    return;
  }

  state.posts.forEach(post => {
    feed.appendChild(createPostCard(post));
  });
}

function createPostCard(post) {
  const card     = document.createElement('div');
  card.className = 'post-card';
  card.dataset.id = post.id;

  const moodHTML = post.mood
    ? `<div class="feeling-tag">FEELING: ${escapeHtml(post.mood)}</div>`
    : '';

  const musicHTML = post.music_title
    ? `<div class="post-music">
        <div class="music-info">
          <div class="music-title">${escapeHtml(post.music_title)}</div>
          <div class="music-artist">${escapeHtml(post.music_artist || '')}</div>
        </div>
       </div>`
    : '';

  card.innerHTML = `
    <div class="post-header">
      <div class="post-avatar" style="background:${escapeHtml(post.avatar_color || '#333')}">
        ${escapeHtml(post.initials || '?')}
      </div>
      <div class="post-meta">
        <div class="post-name">${escapeHtml(post.display_name)}</div>
        <div class="post-time">${formatTime(post.created_at)}</div>
      </div>
      <div class="post-menu">...</div>
    </div>
    ${moodHTML}
    <div class="post-body">${escapeHtml(post.body)}</div>
    ${musicHTML}
    <div class="post-actions">
      <button class="action-btn like-btn" data-id="${post.id}">
        Like ${post.likes || 0}
      </button>
      <button class="action-btn comment-btn" data-id="${post.id}">
        Comment ${post.comments || 0}
      </button>
      <button class="action-btn relate-btn" data-id="${post.id}">
        Relate ${post.relates || 0}
      </button>
      <button class="action-btn share-btn" data-id="${post.id}">
        Share
      </button>
    </div>`;

  return card;
}
async function submitPost() {
  const text  = postText.value.trim();
  const mood  = moodSelect.value;
  const music = musicInput.value.trim();

  if (!text) {
    postText.style.borderColor = 'var(--accent)';
    postText.focus();
    setTimeout(() => { postText.style.borderColor = ''; }, 1200);
    return;
  }


  let music_title  = music;
  let music_artist = '';
  if (music.includes(' - ')) {
    const parts  = music.split(' - ');
    music_title  = parts[0].trim();
    music_artist = parts[1].trim();
  }

  const res  = await fetch(`${API}?action=create_post`, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      user_id:      state.currentUser.id,
      body:         text,
      mood:         mood,
      music_title,
      music_artist,
    }),
  });
  const data = await res.json();

  if (data.success) {
    state.posts.unshift(data.post);
    renderFeed();

    state.currentUser.post_count += 1;
    if (mood)  state.currentUser.current_vibe = mood;
    if (music) state.currentUser.now_playing  = { music_title, music_artist };
    renderProfile();

    await loadTrending();

    postText.value    = '';
    moodSelect.value  = '';
    musicInput.value  = '';
  }
}

async function likePost(id) {
  const res  = await fetch(`${API}?action=toggle_like`, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      user_id: state.currentUser.id,
      post_id: id,
    }),
  });
  const data = await res.json();

  if (data.success) {
    const post = state.posts.find(p => p.id == id);
    if (post) {
      post.likes      = data.likes;
      post.likedByMe  = data.liked;
    }
    refreshPostCard(id);
  }
}

async function relatePost(id) {
  const res  = await fetch(`${API}?action=toggle_relate`, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      user_id: state.currentUser.id,
      post_id: id,
    }),
  });
  const data = await res.json();

  if (data.success) {
    const post = state.posts.find(p => p.id == id);
    if (post) {
      post.relates     = data.relates;
      post.relatedByMe = data.related;
    }
    refreshPostCard(id);
  }
}

function refreshPostCard(id) {
  const existing = feed.querySelector(`[data-id="${id}"]`);
  const post     = state.posts.find(p => p.id == id);
  if (!existing || !post) return;
  feed.replaceChild(createPostCard(post), existing);
}

function renderTrending() {
  trendingList.innerHTML = '';

  if (!state.trendingTags || state.trendingTags.length === 0) {
    trendingList.innerHTML = '<li style="color:var(--muted);font-size:0.82rem;padding:6px 0">No trending vibes yet</li>';
    return;
  }

  state.trendingTags.forEach((item, index) => {
    const li       = document.createElement('li');
    li.className   = 'trending-item';
    li.innerHTML   = `
      <span class="trend-num">${index + 1}</span>
      <div class="trend-info">
        <div class="trend-tag">${escapeHtml(item.tag)}</div>
        <div class="trend-count">${item.post_count} post${item.post_count != 1 ? 's' : ''}</div>
      </div>`;
    li.addEventListener('click', () => {
      const mood = item.tag.replace('#', '').replace(/([A-Z])/g, ' $1').trim();
      state.activeFilter = mood;
      loadPosts(mood);
    });
    trendingList.appendChild(li);
  });
}

function renderMoodFilter() {
  moodFilter.innerHTML = '';
  const moods = state.moods.length ? state.moods.map(m => m.name) : [];

  moods.forEach(mood => {
    const chip       = document.createElement('button');
    chip.className   = 'mood-chip';
    chip.textContent = mood;
    chip.addEventListener('click', () => {
      if (state.activeFilter === mood) {
        state.activeFilter = null;
        chip.classList.remove('active');
        loadPosts();
      } else {
        state.activeFilter = mood;
        document.querySelectorAll('.mood-chip').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        loadPosts(mood);
      }
    });
    moodFilter.appendChild(chip);
  });
}

function attachEvents() {
  postBtn.addEventListener('click', submitPost);

  postText.addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.ctrlKey) submitPost();
  });

  document.querySelectorAll('.tool-btn').forEach(btn => {
    btn.addEventListener('click', () => btn.classList.toggle('active'));
  });

  feed.addEventListener('click', e => {
    const likeBtn   = e.target.closest('.like-btn');
    const relateBtn = e.target.closest('.relate-btn');
    const shareBtn  = e.target.closest('.share-btn');

    if (likeBtn)   likePost(likeBtn.dataset.id);
    if (relateBtn) relatePost(relateBtn.dataset.id);

    if (shareBtn) {
      const post = state.posts.find(p => p.id == shareBtn.dataset.id);
      if (post && navigator.clipboard) {
        navigator.clipboard.writeText(
          `${post.display_name}: "${post.body}" — VibeCheck`
        );
      }
    }
  });

  document.querySelectorAll('.bn-item').forEach(item => {
    item.addEventListener('click', () => {
      document.querySelectorAll('.bn-item').forEach(i => i.classList.remove('active'));
      item.classList.add('active');
    });
  });

  document.getElementById('bnPost')?.addEventListener('click', () => {
    postText.focus();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

function formatTime(timestamp) {
  const date = new Date(timestamp);
  const diff = Date.now() - date.getTime();
  const secs = Math.floor(diff / 1000);
  const mins = Math.floor(secs / 60);
  const hrs  = Math.floor(mins / 60);
  const days = Math.floor(hrs  / 24);

  if (secs < 60)  return 'Just now';
  if (mins < 60)  return `${mins} minute${mins  !== 1 ? 's' : ''} ago`;
  if (hrs  < 24)  return `${hrs}  hour${hrs   !== 1 ? 's' : ''} ago`;
  return                 `${days} day${days   !== 1 ? 's' : ''} ago`;
}

function generateColor(name) {
  const colors = [
    'linear-gradient(135deg, #ff3c6e, #ff6b35)',
    'linear-gradient(135deg, #7c6af7, #00d4ff)',
    'linear-gradient(135deg, #ffbe00, #ff3c6e)',
    'linear-gradient(135deg, #00d4ff, #00e676)',
    'linear-gradient(135deg, #ff6b35, #ffbe00)',
    'linear-gradient(135deg, #00e676, #7c6af7)',
  ];
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return colors[Math.abs(hash) % colors.length];
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g,  '&amp;')
    .replace(/</g,  '&lt;')
    .replace(/>/g,  '&gt;')
    .replace(/"/g,  '&quot;');
}


init();