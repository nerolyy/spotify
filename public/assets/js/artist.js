// Artist page functionality with robust image path handling and fallbacks
let currentArtist = null;
let artistTracks = [];

// Simple SVG placeholder for images (avoids missing asset files)
const PLACEHOLDER_COVER = 'data:image/svg+xml;utf8,' + encodeURIComponent(`
<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300" viewBox="0 0 300 300">
  <defs>
    <linearGradient id="g" x1="0" x2="1" y1="0" y2="1">
      <stop offset="0%" stop-color="#667eea"/>
      <stop offset="100%" stop-color="#764ba2"/>
    </linearGradient>
  </defs>
  <rect width="300" height="300" fill="url(#g)"/>
  <g fill="#ffffff" opacity="0.9">
    <circle cx="100" cy="150" r="35"/>
    <rect x="145" y="115" width="60" height="70" rx="10"/>
  </g>
</svg>`);

// Normalize/resolve cover path for usage from /public/* pages
function resolveCoverPath(p) {
    if (!p || typeof p !== 'string' || p.trim() === '') return PLACEHOLDER_COVER;
    const s = p.trim();
    if (s.startsWith('http://') || s.startsWith('https://') || s.startsWith('data:')) return s;
    // Absolute paths handling
    if (s.startsWith('/muzic2/')) return s;
    if (s.startsWith('/tracks/')) return '/muzic2' + s;
    if (s.startsWith('/')) return s;
    // Relative to project
    const idx = s.indexOf('tracks/');
    if (idx !== -1) return '/muzic2/' + s.slice(idx);
    if (s.startsWith('assets/')) return s;
    return '/muzic2/' + s;
}

function attachImgFallback(imgEl) {
    if (!imgEl) return;
    imgEl.addEventListener('error', () => {
        imgEl.src = PLACEHOLDER_COVER;
    }, { once: true });
}

// Initialize artist page
document.addEventListener('DOMContentLoaded', async function() {
    const urlParams = new URLSearchParams(window.location.search);
    const artistName = urlParams.get('artist');
    
    if (artistName) {
        // Update header auth state (hide login/register if authenticated)
        try {
            const res = await fetch('/muzic2/src/api/user.php', { credentials: 'include' });
            const data = await res.json();
            const panel = document.getElementById('user-panel');
            if (panel) {
                if (data && data.authenticated && data.user) {
                    panel.innerHTML = `<div class="user-info"><span class="username">${(data.user.username||'').replace(/[&<>"]/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]))}</span></div>`;
                } else {
                    panel.innerHTML = `<button onclick="openLoginModal()" id="login-btn">Войти</button><button onclick="openRegisterModal()" id="register-btn">Регистрация</button>`;
                }
            }
        } catch (e) {
            // ignore header update errors
        }
        // Preload images if it's Kai Angel
        if (artistName.toLowerCase().includes('kai angel')) {
            preloadKaiAngelImages();
        }
        // Load liked tracks set for current user
        try {
            const resp = await fetch('/muzic2/src/api/windows_likes.php?lite=1', { credentials: 'include' });
            const json = await resp.json();
            const ids = json.lite && Array.isArray(json.track_ids)
                ? json.track_ids.map((n) => Number(n)).filter((n) => n > 0)
                : (json.tracks || []).map((t) => t.id);
            window.__likedSet = new Set(ids);
            window.__likedAlbums = new Set((json.albums || []).map((a) => a.album_title || a.title));
        } catch(e) { 
            window.__likedSet = new Set();
            window.__likedAlbums = new Set();
        }

        resetTrackCounters();
        loadArtistData(artistName);
    } else {
        // Redirect to home if no artist specified
        window.location.href = 'index.php';
    }
});

// Load artist data from API
async function loadArtistData(artistName) {
    try {
        const response = await fetch(`src/api/artist.php?artist=${encodeURIComponent(artistName)}`);
        const data = await response.json();
        
        if (data.error) {
            console.error('Error loading artist:', data.error);
            showError('Артист не найден');
            return;
        }
        
        currentArtist = data;
        artistTracks = data.top_tracks || [];
        
        console.log('Artist data loaded:', data);
        console.log('Top tracks count:', artistTracks.length);
        console.log('First few tracks:', artistTracks.slice(0, 3).map(t => ({ title: t.title, artist: t.artist })));
        
        renderArtistPage(data);
        
    } catch (error) {
        console.error('Error fetching artist data:', error);
        showError('Ошибка загрузки данных артиста');
    }
}

// Render artist page
function renderArtistPage(artist) {
    // Set artist avatar with better image handling
    const avatar = document.getElementById('artist-avatar');
    if (avatar) {
        const rc = resolveCoverPath(artist.cover);
        avatar.src = rc.startsWith('data:') ? rc : encodeURI(rc);
        avatar.alt = artist.name || 'Artist';
        attachImgFallback(avatar);
        
        // Add loading animation
        avatar.style.opacity = '0';
        avatar.onload = () => {
            avatar.style.transition = 'opacity 0.3s ease';
            avatar.style.opacity = '1';
        };
    }
    // Set hero background to artist cover (full-width block behind name)
    const hero = document.querySelector('.artist-hero');
    if (hero) {
        // Build absolute URL under /muzic2 to be CSS-safe
        const raw = artist.cover || '';
        let abs = '';
        if (raw.startsWith('http') || raw.startsWith('data:')) {
            abs = raw;
        } else if (raw.includes('tracks/')) {
            const idx = raw.indexOf('tracks/');
            abs = '/muzic2/' + raw.slice(idx);
        } else if (raw.startsWith('/')) {
            abs = raw;
        } else if (raw) {
            abs = '/muzic2/' + raw;
        } else {
            abs = '/muzic2/tracks/covers/m1000x1000.jpeg';
        }
        hero.style.setProperty('--artist-bg', `url('${encodeURI(abs)}')`);
    }
    
    // Set artist name with better text handling
    const nameElement = document.getElementById('artist-name');
    if (nameElement) {
        nameElement.textContent = artist.name || '';
        // Add class for specific styling
        if (artist.name && artist.name.toLowerCase().includes('kai angel')) {
            nameElement.parentElement.parentElement.classList.add('kai-angel');
        }
    }
    
    // Set monthly listeners
    const listenersElement = document.getElementById('artist-listeners');
    if (listenersElement) {
        // Hide monthly listeners per requirements
        listenersElement.textContent = '';
        listenersElement.style.display = 'none';
    }
    
    // Render popular tracks
    console.log('Rendering popular tracks:', artist.top_tracks);
    renderPopularTracks(artist.top_tracks || []);
    
    // Render albums
    renderAlbums(artist.albums || []);

    // Render videos
    renderVideos(artist.name || '');
    
    // Set up event listeners
    setupEventListeners();
}

// Render popular tracks (show only first 10, rest hidden)
let allArtistTracks = [];
let visibleTracksCount = 10;

// Reset counters when loading new artist
function resetTrackCounters() {
    allArtistTracks = [];
    visibleTracksCount = 10;
}

function renderPopularTracks(tracks) {
    const container = document.getElementById('popular-tracks');
    if (!container) return;
    
    // Сохраняем все треки
    allArtistTracks = tracks;
    
    container.innerHTML = '';
    
    // Показываем только первые 10 треков
    const tracksToShow = tracks.slice(0, visibleTracksCount);
    
    tracksToShow.forEach((track, index) => {
        const trackElement = createTrackElement(track, index + 1);
        container.appendChild(trackElement);
    });
    
    // Обновляем кнопку "показать ещё"
    updateShowMoreButton();
}

// Create track element
function createTrackElement(track, number) {
    console.log('createTrackElement called with track:', track);
    console.log('Track file_path:', track.file_path);
    console.log('Track src:', track.src);
    
    const trackDiv = document.createElement('div');
    trackDiv.className = 'track-item-numbered';
    trackDiv.dataset.trackId = track.id;
    
    // Format duration
    const duration = formatDuration(track.duration);
    
    // Generate realistic play count based on track position
    const basePlays = 1000000 - (number * 100000);
    const playCount = formatNumber(Math.max(basePlays, 100000));
    
    // Use better cover images for Kai Angel tracks
    const resolvedCover = resolveCoverPath(track.cover);
    const finalCover = resolvedCover.startsWith('data:') ? resolvedCover : encodeURI(resolvedCover);
    
    trackDiv.innerHTML = `
        <div class="track-number">${number}</div>
        <div class="track-play-icon" style="display: none;">
            <i class="fas fa-play"></i>
        </div>
        <img class="track-cover-small" src="${finalCover}" alt="${track.title || ''}" loading="lazy">
        <div class="track-details">
            <div class="track-title-primary" title="${track.title || ''}">${track.title || ''}</div>
            
        </div>
        <div class="track-duration">${duration}</div>
        <button class="track-like-btn">
            <i class="far fa-heart"></i>
        </button>
        <button class="track-more-btn">
            <i class="fas fa-ellipsis-h"></i>
        </button>
    `;
    
    // Fallback for image
    const img = trackDiv.querySelector('img.track-cover-small');
    attachImgFallback(img);
    
    // Add click event to play track
    trackDiv.addEventListener('click', async (e) => {
        if (!e.target.closest('.track-like-btn') && !e.target.closest('.track-more-btn')) {
        await artistPlayTrack(track);
        }
    });
    
    // Init like button state and functionality
    const likeBtn = trackDiv.querySelector('.track-like-btn');
    const icon = likeBtn.querySelector('i');
    const isLiked = window.__likedSet && window.__likedSet.has(track.id);
    if (isLiked) { 
        icon.classList.remove('far'); 
        icon.classList.add('fas'); 
        likeBtn.classList.add('liked');
        likeBtn.style.color = '#1ed760'; 
    }
    likeBtn.addEventListener('click', async (e) => {
        e.stopPropagation();
        if (!window.__likedSet) window.__likedSet = new Set();
        if (window.__likedSet.has(track.id)) {
            await fetch('/muzic2/src/api/windows_likes.php', { method:'DELETE', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ track_id: track.id })});
            window.__likedSet.delete(track.id);
            icon.classList.remove('fas'); 
            icon.classList.add('far'); 
            likeBtn.classList.remove('liked');
            likeBtn.style.color = '#b3b3b3';
            try{ document.dispatchEvent(new CustomEvent('likes:updated', { detail:{ trackId: track.id, liked:false } })); }catch(_){ }
        } else {
            await fetch('/muzic2/src/api/windows_likes.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ track_id: track.id })});
            window.__likedSet.add(track.id);
            icon.classList.remove('far'); 
            icon.classList.add('fas'); 
            likeBtn.classList.add('liked');
            likeBtn.style.color = '#1ed760';
            try{ document.dispatchEvent(new CustomEvent('likes:updated', { detail:{ trackId: track.id, liked:true } })); }catch(_){ }
        }
    });
    
    return trackDiv;
}

// Render albums
function renderAlbums(albums) {
    const container = document.getElementById('albums-list');
    if (!container) return;
    
    container.innerHTML = '';
    
    albums.forEach(album => {
        const albumElement = createAlbumElement(album);
        container.appendChild(albumElement);
    });
}

// Create album element
function createAlbumElement(album) {
    const albumDiv = document.createElement('div');
    albumDiv.className = 'album-card';
    albumDiv.dataset.albumTitle = album.title;
    
    // Format album type - проверяем и type и album_type для совместимости
    const albumTypeValue = (album.type || album.album_type || 'album').toLowerCase().trim();
    const albumType = albumTypeValue === 'album' ? 'Альбом' : 
                     albumTypeValue === 'ep' ? 'EP' : 
                     albumTypeValue === 'single' ? 'Сингл' : 'Альбом'; // По умолчанию Альбом, а не Сингл
    
    // Get artist name from current artist or album data
    const artistName = currentArtist ? currentArtist.name : (album.artist || '');

    (function(){
        // ensure album cover is encoded
    })();
    const _rcAlbum = resolveCoverPath(album.cover);
    const finalCover = _rcAlbum.startsWith('data:') ? _rcAlbum : encodeURI(_rcAlbum);
    
    // Check if album is liked
    const isLiked = window.__likedAlbums && window.__likedAlbums.has(album.title);
    
    albumDiv.innerHTML = `
        <button class="album-heart-btn ${isLiked ? 'liked' : ''}" data-album-title="${album.title || ''}" title="В избранные альбомы">
            <i class="fas fa-heart"></i>
        </button>
        <img class="album-cover" src="${finalCover}" alt="${album.title || ''}" loading="lazy">
        <div class="album-title">${album.title || ''}</div>
        <div class="album-artist">${artistName || ''}</div>
        <div class="album-info">${albumType} • ${album.track_count} трек${getTrackWordEnding(album.track_count)}</div>
        <button class="album-play-btn">
            <i class="fas fa-play"></i>
        </button>
    `;

    const img = albumDiv.querySelector('img.album-cover');
    attachImgFallback(img);
    
    // Add like button functionality
    const likeBtn = albumDiv.querySelector('.album-heart-btn');
    if (likeBtn) {
        likeBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            if (!window.__likedAlbums) window.__likedAlbums = new Set();
            const icon = likeBtn.querySelector('i');
            if (window.__likedAlbums.has(album.title)) {
                await fetch('/muzic2/src/api/windows_likes.php', { method:'DELETE', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ album_title: album.title })});
                window.__likedAlbums.delete(album.title);
                likeBtn.classList.remove('liked');
            } else {
                await fetch('/muzic2/src/api/windows_likes.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ album_title: album.title })});
                window.__likedAlbums.add(album.title);
                likeBtn.classList.add('liked');
            }
        });
    }
    
    // Add click event to open album
    albumDiv.addEventListener('click', (e) => {
        if (!e.target.closest('.album-play-btn') && !e.target.closest('.album-heart-btn')) {
            openAlbum(album.title);
        }
    });
    
    // Add play button functionality
    const playBtn = albumDiv.querySelector('.album-play-btn');
    playBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        playAlbum(album.title);
    });
    
    return albumDiv;
}

// Render videos
async function renderVideos(artistName) {
    const container = document.getElementById('videos-list');
    if (!container) return;
    container.innerHTML = '<div class="loading">Загрузка...</div>';
    try {
        const res = await fetch(`src/api/videos.php?artist=${encodeURIComponent(artistName)}`);
        const json = await res.json();
        const items = (json && json.success && Array.isArray(json.data)) ? json.data : [];
        if (!items.length) {
            container.innerHTML = '<div style="color:#9aa0a6;padding:8px 0;">Видео не найдены</div>';
            return;
        }
        container.innerHTML = '';
        items.forEach(v => {
            const card = document.createElement('div');
            card.className = 'album-card';
            const _rcThumb = resolveCoverPath(v.cover || 'tracks/covers/m1000x1000.jpeg');
            const thumb = _rcThumb.startsWith('data:') ? _rcThumb : encodeURI(_rcThumb);
            card.innerHTML = `
                <img class="album-cover" src="${thumb}" alt="${(v.title||'').replace(/"/g,'&quot;')}" loading="lazy">
                <div class="album-title">${(v.title||'')}</div>
                <div class="album-info">Видео</div>
                <button class="album-play-btn"><i class="fas fa-play"></i></button>
            `;
            const img = card.querySelector('img.album-cover');
            attachImgFallback(img);
            const btn = card.querySelector('.album-play-btn');
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                window.playTrack && window.playTrack({
                    src: v.src,
                    title: v.title || '',
                    artist: artistName || '',
                    cover: thumb,
                    duration: 0
                });
            });
            container.appendChild(card);
        });
    } catch (e) {
        container.innerHTML = '<div class="error">Ошибка загрузки видео</div>';
    }
}

// Setup event listeners
function setupEventListeners() {
    // Play all button
    const playAllBtn = document.getElementById('play-all-btn');
    if (playAllBtn) {
        playAllBtn.addEventListener('click', async () => {
            if (artistTracks.length > 0) {
                await artistPlayTrack(artistTracks[0]);
            }
        });
    }
    
    // Shuffle button
    const shuffleBtn = document.getElementById('shuffle-btn');
    if (shuffleBtn) {
        shuffleBtn.addEventListener('click', async () => {
            if (artistTracks.length > 0) {
                const randomTrack = artistTracks[Math.floor(Math.random() * artistTracks.length)];
                await artistPlayTrack(randomTrack);
            }
        });
    }
    
    // Follow button
    const followBtn = document.getElementById('follow-btn');
    if (followBtn) {
        followBtn.addEventListener('click', toggleFollow);
    }
    
    // Show more tracks button
    const showMoreBtn = document.getElementById('show-more-tracks');
    if (showMoreBtn) {
        showMoreBtn.addEventListener('click', showMoreTracks);
    }
}

// Play track function
async function artistPlayTrack(track) {
    if (!track) return;
    
    // Update player with track info
    const trackTitle = document.getElementById('track-title');
    const trackArtist = document.getElementById('track-artist');
    const currentCover = document.getElementById('cover') || document.getElementById('current-cover');
    
    if (trackTitle) trackTitle.textContent = track.title || '';
    if (trackArtist) {
        const base = (track && typeof track.artist === 'string') ? track.artist.trim() : '';
        const feats = (track && typeof track.feats === 'string') ? track.feats.trim() : '';
        trackArtist.textContent = feats ? (base ? `${base}, ${feats}` : feats) : base;
    }
    if (currentCover) {
        const rc = resolveCoverPath(track.cover);
        currentCover.src = rc.startsWith('data:') ? rc : encodeURI(rc);
        attachImgFallback(currentCover);
    }
    
    // Build absolute src for audio
    console.log('artistPlayTrack called with track:', track);
    console.log('track.src:', track.src);
    console.log('track.file_path:', track.file_path);
    
    // КАРДИНАЛЬНО НОВЫЙ ПОДХОД: НЕ используем track вообще, создаем src с нуля
    let src = '';
    
    // Создаем src на основе file_path
    if (track.file_path) {
        if (track.file_path.startsWith('/muzic2/')) {
            src = track.file_path;
        } else if (track.file_path.startsWith('/tracks/')) {
            src = '/muzic2' + track.file_path;
        } else {
            const idx = track.file_path.indexOf('tracks/');
            src = idx !== -1 ? ('/muzic2/' + track.file_path.slice(idx)) : ('/muzic2/' + track.file_path.replace(/^\/+/, ''));
        }
    }
    
    console.log('Created src from scratch:', src);
    
    // КРИТИЧЕСКАЯ ПРОВЕРКА: если src все еще содержит URL страницы, используем только file_path
    if (src && src.includes('artist.html')) {
        console.log('CRITICAL: src still contains URL page, using only file_path');
        src = track.file_path || '';
        console.log('Critical fix - using file_path only:', src);
    }
    if (src) {
        if (!/^https?:|^data:/i.test(src)) {
            if (src.startsWith('/muzic2/')) {
                // keep as is
            } else if (src.startsWith('/tracks/')) {
                src = '/muzic2' + src;
            } else {
                const idx = src.indexOf('tracks/');
                src = idx !== -1 ? ('/muzic2/' + src.slice(idx)) : ('/muzic2/' + src.replace(/^\/+/, ''));
            }
        }
        if (!/^data:/i.test(src)) src = encodeURI(src);
    }
    // Use global player
    if (window.playTrack) {
        console.log('Setting up artist queue with', artistTracks.length, 'tracks');
        console.log('artistTracks:', artistTracks);
        
        // Если треки артиста не загружены, загружаем их
        if (artistTracks.length === 0) {
            console.log('No artist tracks loaded, loading artist data...');
            const urlParams = new URLSearchParams(window.location.search);
            const artistName = urlParams.get('artist');
            if (artistName) {
                await loadArtistData(artistName);
            } else {
                console.error('No artist name available for loading tracks');
                return;
            }
        }
        
        // Проверяем что треки загрузились
        if (artistTracks.length === 0) {
            console.error('Still no tracks after loading artist data');
            return;
        }
        
        console.log('Creating queue with', artistTracks.length, 'tracks');
        
        // Создаем очередь из всех треков артиста
        const artistQueue = artistTracks.map(t => {
            let trackSrc = t.file_path || '';
            console.log('Original track file_path:', t.file_path);
            console.log('Original track title:', t.title);
            
            if (trackSrc && !trackSrc.startsWith('http') && !trackSrc.startsWith('data:')) {
                if (trackSrc.startsWith('/muzic2/')) {
                    // keep as is
                } else if (trackSrc.startsWith('/tracks/')) {
                    trackSrc = '/muzic2' + trackSrc;
                } else {
                    const idx = trackSrc.indexOf('tracks/');
                    trackSrc = idx !== -1 ? ('/muzic2/' + trackSrc.slice(idx)) : ('/muzic2/' + trackSrc.replace(/^\/+/, ''));
                }
            }
            
            console.log('Processed trackSrc:', trackSrc);
            
            const trackObj = {
                src: trackSrc,
                title: t.title || '',
                artist: (t.feats && String(t.feats).trim()) ? `${t.artist}, ${t.feats}` : (t.artist || ''),
                feats: t.feats || '',
                cover: resolveCoverPath(t.cover),
                duration: t.duration || 0,
                video_url: t.video_url || '',
                explicit: t.explicit || false
            };
            console.log('Created track object:', trackObj);
            return trackObj;
        });
        
        // Находим индекс текущего трека в очереди
        const currentIndex = artistQueue.findIndex(t => t.title === track.title);
        console.log('Current track index in queue:', currentIndex);
        
        // Устанавливаем очередь
        if (window.setQueue && typeof window.setQueue === 'function') {
            window.setQueue(artistQueue, currentIndex >= 0 ? currentIndex : 0);
        } else {
            console.log('setQueue function not available');
        }
        
        // Воспроизводим текущий трек
        // Устанавливаем очередь и воспроизводим трек напрямую
        if (window.setQueue && typeof window.setQueue === 'function') {
            window.setQueue(artistQueue, currentIndex >= 0 ? currentIndex : 0);
            // Воспроизводим трек напрямую через playFromQueue, не через playTrack
            if (window.playFromQueue && typeof window.playFromQueue === 'function') {
                window.playFromQueue(currentIndex >= 0 ? currentIndex : 0);
            } else {
                // Fallback: используем playTrack с параметром queue
                window.playTrack({
                    queue: artistQueue,
                    queueStartIndex: currentIndex >= 0 ? currentIndex : 0
                });
            }
        } else {
            // Fallback: используем обычный playTrack
            window.playTrack({
                src,
                title: track.title || '',
                artist: track.artist || '',
                cover: (function(){ const c = resolveCoverPath(track.cover); return c.startsWith('data:') ? c : encodeURI(c); })(),
                duration: track.duration || 0,
                video_url: track.video_url || ''
            });
        }
    } else if (window.loadTrack) {
        window.loadTrack({ src, title: track.title || '', artist: track.artist || '', cover: (function(){ const c = resolveCoverPath(track.cover); return c.startsWith('data:') ? c : encodeURI(c); })(), duration: track.duration || 0 });
    }
    
    console.log('Playing track:', track.title);
}

// Play album function
function playAlbum(albumTitle) {
    window.location.href = `album.html?album=${encodeURIComponent(albumTitle)}`;
}

// Open album function
function openAlbum(albumTitle) {
    window.location.href = `album.html?album=${encodeURIComponent(albumTitle)}`;
}

// Toggle like function
function toggleLike(likeBtn) {
    const icon = likeBtn.querySelector('i');
    if (icon.classList.contains('far')) {
        icon.classList.remove('far');
        icon.classList.add('fas');
        likeBtn.style.color = '#1ed760';
    } else {
        icon.classList.remove('fas');
        icon.classList.add('far');
        likeBtn.style.color = '#b3b3b3';
    }
}

// Toggle follow function
function toggleFollow() {
    const followBtn = document.getElementById('follow-btn');
    if (followBtn.textContent.trim() === 'Уже подписаны') {
        followBtn.textContent = 'Подписаться';
        followBtn.style.background = '#1ed760';
        followBtn.style.color = '#000';
    } else {
        followBtn.textContent = 'Уже подписаны';
        followBtn.style.background = 'none';
        followBtn.style.color = '#fff';
    }
}

// Show more tracks function
function showMoreTracks() {
    const container = document.getElementById('popular-tracks');
    if (!container) return;
    
    // Показываем следующие 10 треков
    const nextBatch = allArtistTracks.slice(visibleTracksCount, visibleTracksCount + 10);
    
    nextBatch.forEach((track, index) => {
        const trackElement = createTrackElement(track, visibleTracksCount + index + 1);
        container.appendChild(trackElement);
    });
    
    visibleTracksCount += nextBatch.length;
    
    // Обновляем кнопку
    updateShowMoreButton();
}

// Update show more button visibility
function updateShowMoreButton() {
    const showMoreBtn = document.getElementById('show-more-tracks');
    if (!showMoreBtn) return;
    
    if (visibleTracksCount >= allArtistTracks.length) {
        showMoreBtn.style.display = 'none';
    } else {
        showMoreBtn.style.display = 'block';
        const remaining = allArtistTracks.length - visibleTracksCount;
        showMoreBtn.textContent = `Показать ещё ${remaining} трек${getTrackWordEnding(remaining)}`;
    }
}

// Utility functions
function formatDuration(seconds) {
    if (!seconds) return '0:00';
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}

// Image loading optimization
function preloadImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = resolveCoverPath(src);
    });
}

// Preload Kai Angel images for better performance
async function preloadKaiAngelImages() {
    const images = [
        'tracks/covers/Снимок экрана 2025-07-19 в 11.56.58.png',
        'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg',
        'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png'
    ];
    
    try {
        await Promise.all(images.map(src => preloadImage(src)));
        console.log('Kai Angel images preloaded successfully');
    } catch (error) {
        console.warn('Some images failed to preload:', error);
    }
}

function formatNumber(num) {
    if (typeof num !== 'number') return '0';
    return num.toLocaleString('ru-RU');
}

function getTrackWordEnding(count) {
    if (count % 10 === 1 && count % 100 !== 11) {
        return '';
    } else if ([2, 3, 4].includes(count % 10) && ![12, 13, 14].includes(count % 100)) {
        return 'а';
    } else {
        return 'ов';
    }
}

function showError(message) {
    const mainContent = document.getElementById('main-content');
    if (mainContent) {
        mainContent.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: #fff;">
                <h2>Ошибка</h2>
                <p>${message}</p>
                <button onclick="window.location.href='index.php'" style="background: #1ed760; color: #000; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer;">
                    Вернуться на главную
                </button>
            </div>
        `;
    }
}
