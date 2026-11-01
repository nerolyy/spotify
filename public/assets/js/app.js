// Принудительно используем HTTP вместо HTTPS
if (location.protocol === 'https:') {
    location.replace('http:' + location.href.substring(5));
}

console.log('User Agent:', navigator.userAgent);

const API_ORIGIN = (window.location && window.location.protocol && window.location.protocol.startsWith('http'))
	? window.location.origin
	: (window.API_ORIGIN || 'http://localhost');
const api = (path) => (String(path).startsWith('http') ? path : API_ORIGIN + path);
const getAuthAPI = () => api('/muzic2/src/api/windows_auth.php');
const getUserAPI = () => api('/muzic2/src/api/windows_auth.php');
/** @param {{ lite?: boolean }} [opts] lite=1 — только id треков, без JOIN с tracks */
function getLikesAPI(opts) {
	const q = opts && opts.lite ? '?lite=1&limit=2000' : '';
	return api('/muzic2/src/api/windows_likes.php' + q);
}
window.API_ORIGIN = API_ORIGIN;
window.getLikesAPI = getLikesAPI;

// Short-lived cache to avoid multiple identical likes-lite requests on initial page load.
const __likesLiteCache = { ts: 0, data: null, promise: null };
async function fetchLikesLiteCached(force) {
	const now = Date.now();
	if (!force && __likesLiteCache.data && (now - __likesLiteCache.ts) < 8000) return __likesLiteCache.data;
	if (__likesLiteCache.promise) return __likesLiteCache.promise;
	__likesLiteCache.promise = fetch(getLikesAPI({ lite: true }), { credentials: 'include' })
		.then((r) => r.json())
		.then((j) => {
			__likesLiteCache.data = j || { lite: true, track_ids: [], albums: [] };
			__likesLiteCache.ts = Date.now();
			return __likesLiteCache.data;
		})
		.catch(() => ({ lite: true, track_ids: [], albums: [] }))
		.finally(() => { __likesLiteCache.promise = null; });
	return __likesLiteCache.promise;
}
window.fetchLikesLiteCached = fetchLikesLiteCached;

function syncLikesFromServerPayload(j) {
	if (!j || typeof j !== 'object') return;
	const ids = j.lite && Array.isArray(j.track_ids)
		? j.track_ids.map((n) => Number(n)).filter((n) => n > 0)
		: (Array.isArray(j.tracks) ? j.tracks.map((t) => Number(t.id)).filter((n) => n > 0) : []);
	window.__likedSet = new Set(ids);
	const alb = Array.isArray(j.albums) ? j.albums : [];
	window.__likedAlbums = new Set(alb.map((a) => a.album_title || a.title).filter(Boolean));
}

/** Used by global helpers (e.g. renderArtistInline) and the main app block */
function escapeHtml(str) {
	if (str === null || str === undefined) return '';
	return String(str).replace(/[&<>"]/g, function (m) {
		return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m];
	});
}

const mainContent = document.getElementById('main-content');
const navHome = document.getElementById('nav-home');
const navSearch = document.getElementById('nav-search');
const navLibrary = document.getElementById('nav-library');
const mainHeader = document.getElementById('main-header');

// Guard: run only if home navigation exists on this page
if (mainContent && navHome && navSearch && navLibrary) {
	function showPage(page) {
		// Проверяем авторизацию перед показом любой страницы
		if (!currentUser) {
			showLoginScreen();
			return;
		}

		window.__openPlaylistState = null;
		
		if (page === 'Главная') {
			renderHome();
	} else if (page === 'Поиск') {
		renderSearch();
		} else if (page === 'Моя музыка') {
			renderMyMusic();
		}
	}

	navHome.onclick = () => navigateTo('home');
	navSearch.onclick = () => showPage('Поиск');
	navLibrary.onclick = () => showPage('Моя музыка');

	// SPA will handle initial page load

	// Session state
	let currentUser = null;

	// Ensure auth modals exist globally
	ensureAuthModals();

	// Wait for DOM to be ready
	function waitForDOM() {
		return new Promise((resolve) => {
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', resolve);
			} else {
				resolve();
			}
		});
	}

	(async function initSession() {
		// Wait for DOM to be ready
		await waitForDOM();
		
		// Скрываем весь контент до проверки авторизации
		if (mainContent) {
			mainContent.style.display = 'none';
		}
		if (mainHeader) {
			mainHeader.style.display = 'none';
		}
		
		let cachedUser = null;
		try {
			const raw = localStorage.getItem('currentUser');
			if (raw) cachedUser = JSON.parse(raw);
		} catch (_) { /* ignore */ }
		currentUser = cachedUser;

		let serverDecided = false;
		try {
			const ac = new AbortController();
			const to = setTimeout(() => ac.abort(), 12000);
			let res;
			try {
				res = await fetch(api('/muzic2/src/api/user_windows.php'), { credentials: 'include', signal: ac.signal });
			} finally {
				clearTimeout(to);
			}
			if (res.ok) {
				const data = await res.json().catch(() => null);
				if (data && typeof data.authenticated === 'boolean') {
					serverDecided = true;
					currentUser = data.authenticated ? data.user : null;
					if (currentUser) {
						try { localStorage.setItem('currentUser', JSON.stringify(currentUser)); } catch (_) { /* ignore */ }
					} else {
						try { localStorage.removeItem('currentUser'); } catch (_) { /* ignore */ }
					}
				}
			}
		} catch (e) {
			console.error('Session init error:', e);
		}
		if (!serverDecided && cachedUser) {
			currentUser = cachedUser;
		}
		if (!currentUser) {
			showLoginScreen();
			return;
		}
		showAuthenticatedContent();
		renderAuthHeader();
	})();
	
	// Функция для показа экрана входа
	function showLoginScreen() {
		// Скрываем весь контент
		if (mainContent) {
			mainContent.style.display = 'none';
		}
		if (mainHeader) {
			mainHeader.style.display = 'none';
		}
		
		// Создаем экран входа
		const loginScreen = document.getElementById('login-screen');
		if (loginScreen) {
			loginScreen.style.display = 'flex';
			return;
		}
		
		const screen = document.createElement('div');
		screen.id = 'login-screen';
		screen.style.cssText = 'position:fixed; inset:0; background:#0f0f0f; display:flex; align-items:center; justify-content:center; z-index:99999; flex-direction:column; gap:2rem;';
		screen.innerHTML = `
			<div style="text-align:center;">
				<h1 style="color:#fff; font-size:2.5rem; font-weight:900; margin:0 0 1rem 0; letter-spacing:-1px;">Muzic2</h1>
				<p style="color:#b3b3b3; font-size:1rem; margin:0;">Войдите для доступа к сервису</p>
			</div>
			<div style="background:rgba(40,40,40,0.98); border:1px solid rgba(255,255,255,0.1); border-radius:16px; padding:2rem; width:90%; max-width:400px; box-shadow:0 8px 32px rgba(0,0,0,0.6);">
				<h2 style="color:#fff; font-size:1.5rem; font-weight:700; margin:0 0 1.5rem 0; text-align:center;">Вход</h2>
				<input id="login-screen-login" placeholder="Email или логин" autocomplete="username" style="width:100%; padding:0.875rem 1rem; margin-bottom:0.75rem; border:1px solid rgba(255,255,255,0.2); border-radius:8px; background:rgba(255,255,255,0.05); color:#fff; font-size:1rem; outline:none; box-sizing:border-box;">
				<input id="login-screen-password" type="password" placeholder="Пароль" autocomplete="current-password" style="width:100%; padding:0.875rem 1rem; margin-bottom:0.75rem; border:1px solid rgba(255,255,255,0.2); border-radius:8px; background:rgba(255,255,255,0.05); color:#fff; font-size:1rem; outline:none; box-sizing:border-box;">
				<div id="login-screen-error" style="display:none; background:rgba(255,77,79,0.15); border:1px solid rgba(255,77,79,0.3); color:#ff6b6b; padding:0.75rem; border-radius:8px; margin-bottom:0.75rem; font-size:0.9rem; text-align:center;"></div>
				<button id="login-screen-submit" style="width:100%; padding:0.875rem; background:#1ed760; color:#000; border:none; border-radius:8px; font-size:1rem; font-weight:700; cursor:pointer; transition:background 0.2s;">Войти</button>
			</div>
		`;
		document.body.appendChild(screen);
		
		// Обработчики
		const loginInput = document.getElementById('login-screen-login');
		const passwordInput = document.getElementById('login-screen-password');
		const submitBtn = document.getElementById('login-screen-submit');
		const errorBox = document.getElementById('login-screen-error');
		
		const handleLogin = async () => {
			const login = loginInput.value.trim();
			const password = passwordInput.value;
			
			if (errorBox) {
				errorBox.style.display = 'none';
				errorBox.textContent = '';
			}
			
			if (!login || !password) {
				if (errorBox) {
					errorBox.textContent = 'Введите логин и пароль';
					errorBox.style.display = 'block';
				}
				return;
			}
			
			try {
				if (submitBtn) {
					submitBtn.disabled = true;
					submitBtn.textContent = 'Входим...';
				}
				
				const authAPI = getAuthAPI();
				const res = await fetch(authAPI, {
					method: 'POST',
					credentials: 'include',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						action: 'login',
						login,
						password
					})
				});
				
				let ok = res.ok;
				let payload = null;
				try { payload = await res.json(); } catch(_) { payload = null; }
				
				if (!ok) {
					const msg = (payload && payload.error) ? payload.error : 'Ошибка авторизации';
					if (errorBox) {
						errorBox.textContent = msg;
						errorBox.style.display = 'block';
					}
					if (submitBtn) {
						submitBtn.disabled = false;
						submitBtn.textContent = 'Войти';
					}
					return;
				}
				
				// Проверяем авторизацию
				if (payload && payload.user) {
					currentUser = payload.user;
					try { localStorage.setItem('currentUser', JSON.stringify(currentUser)); } catch(_) {}
				} else {
					const uRes = await fetch(getUserAPI(), { credentials: 'include' });
					const u = await uRes.json();
					if (u && u.authenticated && u.user) {
						currentUser = u.user;
					} else {
						if (errorBox) {
							errorBox.textContent = 'Сессия не установлена';
							errorBox.style.display = 'block';
						}
						if (submitBtn) {
							submitBtn.disabled = false;
							submitBtn.textContent = 'Войти';
						}
						return;
					}
				}
				
				// Успешный вход - скрываем экран входа и показываем контент
				screen.style.display = 'none';
				showAuthenticatedContent();
				renderAuthHeader();
				
			} catch (e) {
				console.error('Login error:', e);
				if (errorBox) {
					errorBox.textContent = 'Ошибка подключения';
					errorBox.style.display = 'block';
				}
				if (submitBtn) {
					submitBtn.disabled = false;
					submitBtn.textContent = 'Войти';
				}
			}
		};
		
		if (submitBtn) {
			submitBtn.onclick = handleLogin;
		}
		
		[loginInput, passwordInput].forEach(inp => {
			if (inp) {
				inp.addEventListener('keydown', (e) => {
					if (e.key === 'Enter') {
						e.preventDefault();
						handleLogin();
					}
				});
			}
		});
	}
	
	// Функция для показа авторизованного контента
	function showAuthenticatedContent() {
		if (mainContent) {
			mainContent.style.display = 'block';
		}
		if (mainHeader) {
			mainHeader.style.display = 'flex';
		}
		const loginScreen = document.getElementById('login-screen');
		if (loginScreen) {
			loginScreen.style.display = 'none';
		}
		
		// Определяем текущую страницу и загружаем соответствующий контент
		const urlParams = new URLSearchParams(window.location.search);
		if (urlParams.has('album')) {
			// Загружаем страницу альбома
			navigateTo('album', { album: urlParams.get('album') });
		} else if (urlParams.has('artist')) {
			// Загружаем страницу артиста
			navigateTo('artist', { artist: urlParams.get('artist') });
		} else {
			// Главная страница - всегда загружаем контент
			renderHome();
		}
	}

	function mountUserPanel() {
		if (!mainHeader) return null;
		let panel = document.getElementById('user-panel');
		if (!panel) {
			panel = document.createElement('div');
			panel.id = 'user-panel';
			mainHeader.appendChild(panel);
		}
		return panel;
	}

	function renderAuthHeader() {
		const panel = mountUserPanel();
		if (!panel) return;
		
		if (currentUser) {
			const username = String(currentUser.username || '').trim().toLowerCase();
			const email = String(currentUser.email || '').trim().toLowerCase();
			const isAdminUser = username === 'admin' || email === 'admin@test.com';
			panel.innerHTML = `
				<div class="user-menu">
					<button id="user-menu-btn" class="btn">${escapeHtml(currentUser.username || '')}</button>
					<div id="user-menu-popover" class="popover" style="display:none; position:absolute; right:12px; top:52px; background:#1a1a1a; border:1px solid #333; border-radius:12px; padding:12px; min-width:240px; z-index:1000; box-shadow:0 8px 24px rgba(0,0,0,.4)">
						<div style="padding:8px 4px; color:#b3b3b3">${escapeHtml(currentUser.username || '')}</div>
						<hr style="border:0;border-top:1px solid #2a2a2a; margin:8px 0">
						${isAdminUser ? '<button id="open-admin-btn" class="btn" style="width:100%; margin-bottom:.5rem; background:#1db954; color:#111; border:0; padding:.6rem 1rem; border-radius:8px; cursor:pointer; font-weight:700;">Админка</button>' : ''}
						<button id="logout-btn" class="btn" style="width:100%; background:#2a2f34; color:#fff; border:0; padding:.6rem 1rem; border-radius:8px; cursor:pointer;">Выйти</button>
					</div>
				</div>
			`;
            const btn = document.getElementById('user-menu-btn');
			const pop = document.getElementById('user-menu-popover');
			if (btn && pop) {
				btn.onclick = (e) => { e.stopPropagation(); pop.style.display = pop.style.display==='none'?'block':'none'; };
				document.addEventListener('click', (e)=>{ if(pop.style.display==='block' && !e.target.closest('#user-menu-popover') && e.target!==btn){ pop.style.display='none'; } });
				const adminBtn = document.getElementById('open-admin-btn');
				if (adminBtn) {
					adminBtn.onclick = () => { window.location.href = '/muzic2/public/admin/'; };
				}
				const logoutBtn = document.getElementById('logout-btn');
                if (logoutBtn){ logoutBtn.onclick = async ()=>{ try{ await fetch(api('/muzic2/src/api/logout.php'),{ method:'POST', credentials:'include' }); }catch(_){} try{ localStorage.removeItem('currentUser'); }catch(_){} location.reload(); } }
			}
		} else {
			panel.innerHTML = `
				<div class="auth-buttons">
					<button id="header-login" class="btn primary">Войти</button>
					<button id="header-register" class="btn">Регистрация</button>
				</div>
			`;
			// Robust modal opening for header buttons
			attachAuthModalTriggers();
			const headerLogin = document.getElementById('header-login');
			const headerRegister = document.getElementById('header-register');
			const openModal = (id) => {
				try {
					ensureAuthModals();
					const o = document.querySelector('#auth-modals .modal-overlay');
					const c = document.querySelector('#auth-modals .modal-center');
					if (!o || !c) return;
					o.style.display='block';
					c.style.display='flex';
					document.querySelectorAll('#auth-modals .modal').forEach(m=>m.style.display='none');
					const box = document.getElementById(id);
					if (box) box.style.display='block';
					setTimeout(()=>{ const first = document.querySelector(`#${id} input`); if(first) first.focus(); }, 0);
				} catch(e) {}
			};
			if (headerLogin) headerLogin.onclick = () => openModal('login-modal');
			// Регистрация убрана - только админ может регистрировать пользователей
			if (headerRegister) headerRegister.style.display = 'none';
		}
	}

	async function renderHome() {
		// Проверяем авторизацию
		if (!currentUser) {
			showLoginScreen();
			return;
		}
		
    mainContent.innerHTML = '<div class="loading">Загрузка...</div>';
    // Ensure Font Awesome is loaded for icons
    if (!document.querySelector('link[href*="font-awesome"]')) {
        const fontAwesome = document.createElement('link');
        fontAwesome.rel = 'stylesheet';
        fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
        document.head.appendChild(fontAwesome);
    }
    // Inject responsive styles for home cards (mobile)
    const homeMobileStyles = `
    <style id="home-mobile-styles">
      .card-row,.tile-row{display:grid;grid-template-columns:repeat(6,1fr);gap:18px}
      .tile{position:relative !important;background:#181818;border-radius:18px;overflow:visible !important;padding-bottom:10px;transition:transform .15s}
      .tile:hover{transform:translateY(-2px)}
      .tile-cover{width:100%;aspect-ratio:1/1;object-fit:cover;display:block}
      .tile-title{font-weight:800;color:#fff;margin:.6rem .6rem .2rem}
      .tile-desc{color:#bdbdbd;margin:0 .6rem;font-size:.95rem}
      .artist-row .tile-title{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
      @media(max-width:1024px){.card-row,.tile-row{grid-template-columns:repeat(4,1fr)}}
      @media(max-width:768px){.card-row,.tile-row{grid-template-columns:repeat(3,1fr)}.tile-title{font-size:1rem}.tile-desc{font-size:.88rem}}
      @media(max-width:560px){.card-row,.tile-row{grid-template-columns:repeat(2,1fr);gap:14px}.tile{border-radius:16px}.tile-title{font-size:.98rem}.tile-desc{font-size:.84rem}}
      @media(max-width:400px){.card-row,.tile-row{grid-template-columns:1fr;gap:12px}}
    </style>`;
    try { const old = document.getElementById('home-mobile-styles'); if (old) old.remove(); } catch(_) {}
    try { document.head.insertAdjacentHTML('beforeend', homeMobileStyles); } catch(_) {}
		try { ensureStyle('/muzic2/public/assets/css/home_modern.css'); } catch(_) {}
		// Inject compact modern cards for mixes and random tracks
		const homeCardStyles = `
		<style id="home-cards-compact">
		.card-row { display:grid; grid-template-columns:repeat(6,1fr); gap:16px }
		.card { background:#181818; border-radius:18px; padding:10px 12px; display:flex; align-items:center; gap:12px; min-height:72px; position:relative; overflow:visible !important }
		.card-cover { width:56px; height:56px; border-radius:12px; object-fit:cover; flex:0 0 auto }
		.card-info { min-width:0 }
		.card-title { color:#fff; font-weight:800; font-size:1rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis }
		.card-artist { color:#b3b3b3; font-size:.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis }
		.card-type { color:#22c55e; font-size:.86rem; font-weight:700; margin-top:2px }
		.card:hover { background:#1f1f1f }
		.card .heart-btn, .card .kebab { opacity:1 !important; display:flex !important; visibility:visible !important; color:#b3b3b3 !important; background:none !important; border:none !important; width:32px !important; height:32px !important; min-width:32px !important; min-height:32px !important; flex-shrink:0 !important; z-index:10 !important; cursor:pointer !important; transition:all 0.2s ease !important; align-items:center !important; justify-content:center !important; border-radius:50% !important; padding:0.5rem !important }
		.card .heart-btn:hover { color:#1ed760 !important; background:rgba(30,215,96,0.1) !important; transform:scale(1.1) !important }
		.card .heart-btn.liked { color:#1ed760 !important }
		.card .heart-btn.liked:hover { background:rgba(30,215,96,0.15) !important; transform:scale(1.15) !important }
		.card .kebab:hover { color:#fff !important }
		/* Mix pill look */
		#mixes-row .tile { border-radius:22px }
		@media(max-width:1024px){ .card-row{grid-template-columns:repeat(4,1fr)} }
		@media(max-width:768px){ .card-row{grid-template-columns:repeat(2,1fr)} .card{min-height:64px} .card-cover{width:52px;height:52px} }
		@media(max-width:480px){ .card-row{grid-template-columns:1fr} .card-cover{width:48px;height:48px} }
		</style>`;
		try { const old = document.getElementById('home-cards-compact'); if (old) old.remove(); document.head.insertAdjacentHTML('beforeend', homeCardStyles); } catch(_) {}

		function injectHero(preferredAlbumCover) {
			try {
				const heroCover = '/muzic2/' + (String(preferredAlbumCover||'tracks/covers/m1000x1000.jpeg').replace(/^\/+/, ''));
				const hero = `
				<section class="home-hero">
					<div class="hero-bg" style="background-image:url('${heroCover}')"></div>
					<div class="hero-scrim"></div>
					<div class="hero-content">
						<h1>Продолжим слушать</h1>
						<p>Подборка для вас — треки, альбомы и артисты</p>
						<div class="hero-actions">
							<button class="hero-btn" id="hero-search-btn">Поиск</button>
							<button class="hero-btn primary" id="hero-library-btn">Моя музыка</button>
						</div>
						<div class="hero-spotlights">
							<div class="spotlight pulse" id="pulse-tile">
								<div class="pulse-wave"></div>
								<div class="pulse-label">
									<div class="pulse-title">Пульс</div>
									<div class="pulse-sub">Персональный поток прямо сейчас</div>
								</div>
							</div>
						</div>
					</div>
				</section>`;
				mainContent.insertAdjacentHTML('afterbegin', hero);
				const sb = document.getElementById('hero-search-btn'); if (sb) sb.onclick = () => showPage('Поиск');
				const lb = document.getElementById('hero-library-btn'); if (lb) lb.onclick = () => showPage('Моя музыка');
				const pt = document.getElementById('pulse-tile'); if (pt) pt.onclick = startPulse;
				try {
					const bg = document.querySelector('.home-hero .hero-bg');
					if (bg) {
						let ticking = false;
						const onScroll = () => { if (ticking) return; ticking = true; requestAnimationFrame(()=>{ const y = window.scrollY||0; bg.style.transform = `translateY(${Math.min(30, y*0.08)}px) scale(1.08)`; ticking = false; }); };
						window.addEventListener('scroll', onScroll, { passive: true });
					}
				} catch(_) {}
			} catch(_) {}
		}

		async function startPulse() {
			try {
				let recents = [];
				try { recents = JSON.parse(localStorage.getItem('muzic2_recent_listening_v1')||'[]'); } catch(_) { recents = []; }
			const recentArtists = new Set(recents.map(x=>String(x.artist||'').trim()).filter(Boolean));
			const recentGenres = new Set(recents.map(x=>String(x.genre||'').toLowerCase().trim()).filter(Boolean));

			
			let preferredArtists = new Set();
			try {
				const likesRes = await fetch(getLikesAPI({ lite: true }), { credentials: 'include' });
				const likes = await likesRes.json();
				(likes.albums || []).forEach((a) => {
					const ar = String(a.artist || a.artist_name || '').trim();
					if (ar) preferredArtists.add(ar);
				});
			} catch (_) { }
			
			if (!preferredArtists.size) preferredArtists = new Set(recentArtists);
				const likedSet = (window.__likedSet instanceof Set) ? window.__likedSet : new Set();
				const r = await fetch(api('/muzic2/src/api/home_windows.php')); const d = await r.json();
				const mixTracks = (d.mixes || []).flatMap((m) => (Array.isArray(m.tracks) ? m.tracks : (m.file_path ? [m] : [])));
				const candidates = (d.tracks || []).concat(mixTracks).filter(Boolean);
			const scored = candidates.map(t => {
					const artist = String(t.artist||'').trim();
					let score = 0;
				
				if (preferredArtists.has(artist)) score += 6; else if (recentArtists.has(artist)) score += 2.5;
					if (likedSet.size && likedSet.has(t.id)) score += 2;
					const feats = String(t.feats||'').split(',').map(s=>s.trim()).filter(Boolean);
					if (feats.some(f => recentArtists.has(f))) score += 1;
				const genre = String(t.genre||'').toLowerCase().trim();
				if (genre && recentGenres.size && recentGenres.has(genre)) score += 1.5;
					score += Math.random()*0.5;
					return { t, score };
				});
			scored.sort((a,b)=>b.score-a.score);

			
			const byArtist = new Map();
			for (const item of scored) {
				const artist = String(item.t.artist||'').trim();
				if (!artist) continue;
				if (!byArtist.has(artist)) byArtist.set(artist, []);
				byArtist.get(artist).push(item);
			}
			// Sort each artist bucket by score desc
			for (const [,arr] of byArtist) arr.sort((a,b)=>b.score-a.score);

			// Priority list of artists by their top score
			const artistOrder = Array.from(byArtist.keys()).sort((a,b)=>{
				const as = byArtist.get(a)[0]?.score||0; const bs = byArtist.get(b)[0]?.score||0; return bs-as;
			});

			const seen = new Set();
			const queue = [];
			const perArtistLimitFirstChunk = 2; // max per artist in first 10 items
			const firstChunkSize = 10;
			const perArtistCounts = new Map();
			let lastArtist = '';

			function takeNextDifferentArtist() {
				for (let i=0;i<artistOrder.length;i++) {
					const artist = artistOrder[i];
					if (!byArtist.get(artist)?.length) continue;
					if (artist === lastArtist && artistOrder.length>1) continue;
					// Enforce early chunk cap
					if (queue.length < firstChunkSize) {
						const cnt = perArtistCounts.get(artist)||0;
						if (cnt >= perArtistLimitFirstChunk) continue;
					}
					return artist;
				}
				// fallback any
				for (const artist of artistOrder) {
					if (byArtist.get(artist)?.length) return artist;
				}
				return null;
			}

			while (queue.length < 30) {
				const artist = takeNextDifferentArtist();
				if (!artist) break;
				const item = byArtist.get(artist).shift();
				const t = item.t;
				let s = t.src || t.file_path || '';
				if (!s) continue;
				if (!/^https?:/i.test(s)) s = (s.indexOf('tracks/')!==-1) ? ('/muzic2/' + s.slice(s.indexOf('tracks/'))) : ('/muzic2/' + s.replace(/^\/+/, ''));
				if (seen.has(s)) continue; seen.add(s);
				queue.push({ src: encodeURI(s), title: t.title, artist: t.artist, feats: t.feats || '', cover: '/muzic2/' + (t.cover || 'tracks/covers/placeholder.jpg'), video_url: t.video_url || '' });
				perArtistCounts.set(artist, (perArtistCounts.get(artist)||0)+1);
				lastArtist = artist;
			}
				if (queue.length) {
					window.setQueue && window.setQueue(queue, 0);
					window.playFromQueue ? window.playFromQueue(0) : (window.playTrack && window.playTrack(queue[0]));
				}
			} catch (e) { console.error('Pulse error', e); }
		}

		try {
				const homeUrl = api('/muzic2/src/api/home_windows.php');
				const acHome = new AbortController();
				const toH = setTimeout(() => acHome.abort(), 22000);
				let res;
				let likesData = null;
				try {
					[res, likesData] = await Promise.all([
						fetch(homeUrl, { credentials: 'include', signal: acHome.signal }),
						fetchLikesLiteCached(false)
					]);
				} finally {
					clearTimeout(toH);
				}
				if (likesData && typeof likesData === 'object') {
					syncLikesFromServerPayload(likesData);
				} else {
					window.__likedSet = new Set();
					window.__likedAlbums = new Set();
				}
				if (!res.ok) {
					mainContent.innerHTML = '<div class="error" style="padding:2rem;text-align:center">Не удалось загрузить главную (код ' + res.status + '). Проверьте, что MySQL в MAMP запущен и вы вошли в аккаунт.</div>';
					return;
				}
				let data;
				try {
					data = await res.json();
				} catch (_) {
					mainContent.innerHTML = '<div class="error" style="padding:2rem;text-align:center">Главная: сервер вернул не JSON (часто это PHP-ошибка). Смотрите логи Apache/PHP.</div>';
					return;
				}
				if (data.error) {
					mainContent.innerHTML = '<div class="error" style="padding:2rem;text-align:center">' + escapeHtml(String(data.error)) + '</div>';
					return;
				}
                mainContent.innerHTML = `
					<section class="main-filters">
						<button class="filter-btn active">Все</button>
						<button class="filter-btn">Музыка</button>
						<button class="filter-btn">Артисты</button>
					</section>
                
                    <section class="queue-shortcut" id="queue-shortcut" style="display:none;">
                        <button id="open-queue" class="btn" style="display:flex;align-items:center;gap:8px;background:#1db954;color:#000;border:none;border-radius:12px;padding:8px 12px;font-weight:800;">
                            <span style="font-size:16px;">☰</span> Очередь
                        </button>
                    </section>
					<section class="main-section" id="favorites-section">
						<h3>Любимые треки</h3>
						<div class="card-row" id="favorites-row"></div>
					</section>
					<section class="main-section" id="mixes-section">
						<h3>Миксы дня</h3>
						<div class="card-row" id="mixes-row"></div>
					</section>
					<section class="main-section" id="albums-section">
						<h3>Случайные альбомы</h3>
						<div class="card-row" id="albums-row"></div>
					</section>
                    <section class="main-section" id="tracks-section" style="position:relative;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                            <h3 style="margin:0;">Случайные треки</h3>
                            <button id="open-queue-inline" class="btn" style="display:none;align-items:center;gap:8px;background:#262626;color:#fff;border:1px solid #333;border-radius:10px;padding:6px 10px;font-weight:700;">☰ Очередь</button>
                        </div>
						<div class="card-row" id="tracks-row"></div>
					</section>
					<section class="main-section" id="artists-section">
						<h3>Артисты</h3>
						<div class="card-row" id="artists-row"></div>
					</section>
				`;
                // Mobile-only show queue buttons
                try {
                    const isMobile = window.matchMedia && window.matchMedia('(max-width: 600px)').matches;
                    const btn1 = document.getElementById('open-queue');
                    const btn2 = document.getElementById('open-queue-inline');
                    if (isMobile) {
                        if (btn1) btn1.parentElement.style.display = 'block';
                        if (btn2) btn2.style.display = 'inline-flex';
                        const open = () => { try { window.toggleQueuePanel ? window.toggleQueuePanel(true) : (window.dispatchEvent(new CustomEvent('player:queue:open')), null); } catch(_) {} };
                        if (btn1) btn1.onclick = open;
                        if (btn2) btn2.onclick = open;
                    }
                } catch(_) {}
				injectHero((data.albums && data.albums[0] && data.albums[0].cover) || '');
				const favoritesSection = document.getElementById('favorites-section');
				if (favoritesSection) {
					favoritesSection.style.display = (data.favorites && data.favorites.length) ? 'block' : 'none';
				}
				renderCards('favorites-row', data.favorites || [], 'track');
				renderCards('mixes-row', data.mixes, 'mix');
				renderCards('albums-row', data.albums, 'album');
				renderCards('tracks-row', data.tracks, 'track');
				renderCards('artists-row', data.artists, 'artist');
				try {
					document.querySelectorAll('.heart-btn[data-track-id]').forEach((btn) => {
						const id = Number(btn.getAttribute('data-track-id'));
						const isLiked = window.__likedSet && window.__likedSet.has(id);
						const icon = btn.querySelector('i');
						if (icon) {
							if (isLiked) {
								btn.classList.add('liked');
								icon.classList.remove('far');
								icon.classList.add('fas');
							} else {
								btn.classList.remove('liked');
								icon.classList.remove('fas');
								icon.classList.add('far');
							}
						} else {
							applyHeartState(btn, isLiked);
						}
					});
					document.querySelectorAll('.album-heart-btn[data-album-title]').forEach((btn) => {
						const title = btn.getAttribute('data-album-title');
						if (window.__likedAlbums && window.__likedAlbums.has(title)) btn.classList.add('liked');
					});
				} catch (_) { /* ignore */ }
				addFilterButtonHandlers();
				return;
			} catch (e) {
				console.error('Home API error:', e);
				const msg = (e && e.name === 'AbortError') ? 'Главная: превышено время ожидания (25 с). База или сеть не отвечают.' : 'Ошибка загрузки главной страницы';
				mainContent.innerHTML = '<div class="error" style="padding:2rem;text-align:center">' + msg + '</div>';
				return;
			}
	}

	async function addFilterButtonHandlers() {
		const filterBtns = document.querySelectorAll('.filter-btn');
		
		filterBtns.forEach(btn => {
			btn.addEventListener('click', async () => {
				filterBtns.forEach(b => b.classList.remove('active'));
				btn.classList.add('active');
				
				const filterType = btn.textContent.trim();
				console.log('Filter clicked:', filterType);
				
				if (filterType === 'Все') {
					document.querySelectorAll('.main-section').forEach(section => {
						section.style.display = 'block';
					});
				} else if (filterType === 'Музыка') {
					document.querySelectorAll('.main-section').forEach(section => {
						section.style.display = 'none';
					});

					const tracksSection = document.getElementById('tracks-section');
					const albumsSection = document.getElementById('albums-section');
					
					if (tracksSection) {
						tracksSection.style.display = 'block';
						tracksSection.querySelector('h3').textContent = 'Случайные треки';
						await loadMusicContent('tracks', 15);
					}
					
					if (albumsSection) {
						albumsSection.style.display = 'block';
						albumsSection.querySelector('h3').textContent = 'Случайные альбомы';
						await loadMusicContent('albums', 12);
					}
				} else if (filterType === 'Артисты') {
					document.querySelectorAll('.main-section').forEach(section => {
						section.style.display = 'none';
					});
					
					const artistsSection = document.getElementById('artists-section');
					if (artistsSection) {
						artistsSection.style.display = 'block';
						artistsSection.querySelector('h3').textContent = 'Артисты';
						await loadMusicContent('artists', 24);
					}
				}
			});
		});
	}
	
	async function loadMusicContent(type, limit) {
		try {
			let url, containerId;
			
			if (type === 'tracks') {
				url = `/muzic2/public/src/api/random_tracks.php?limit=${limit}`;
				containerId = 'tracks-row';
			} else if (type === 'albums') {
				url = `/muzic2/public/src/api/home.php?limit_albums=${limit}`;
				containerId = 'albums-row';
			} else if (type === 'artists') {
				url = `/muzic2/public/src/api/home.php?limit_artists=${limit}`;
				containerId = 'artists-row';
			}
			
			const response = await fetch(url, { credentials: 'include' });
			const data = await response.json();
			
			if (type === 'tracks') {
				renderCards(containerId, data.tracks || [], 'track');
			} else if (type === 'albums') {
				renderCards(containerId, data.albums || [], 'album');
			} else if (type === 'artists') {
				renderCards(containerId, data.artists || [], 'artist');
			}
		} catch (error) {
			console.error('Error loading music content:', error);
		}
	}
	

	// =====================
	// Helper Functions
	// =====================
	function toCoverSrc(cover) {
		const raw = String(cover || '').trim();
		if (!raw) return '/muzic2/tracks/covers/placeholder.jpg';
		if (/^https?:\/\//i.test(raw) || raw.startsWith('data:')) return raw;
		if (raw.startsWith('/muzic2/')) return raw;
		if (raw.startsWith('/')) return '/muzic2' + raw;
		return '/muzic2/' + raw.replace(/^\/+/, '');
	}

	function releaseTypeLabel(v) {
		const t = String(v || '').trim().toLowerCase();
		if (t === 'single' || t === 'singles') return 'Сингл';
		if (t === 'ep') return 'EP';
		if (t === 'album') return 'Альбом';
		return 'Релиз';
	}

	function createAlbumCard(album) {
		const esc = v => String(v == null ? '' : v).replace(/'/g, "\\'");
		const albumTitle = esc(album.title);
		const playAlbum = `(async ()=>{ try{ const res = await fetch('/muzic2/src/api/album_windows.php?album=' + encodeURIComponent('${albumTitle}')); const data = await res.json(); if(data.tracks && data.tracks.length){ const q = data.tracks.map(tt=>{ const s = tt.src || tt.file_path || ''; if(!s) return null; const src = /^https?:/i.test(s) ? s : (s.indexOf('tracks/') !== -1 ? '/muzic2/' + s.slice(s.indexOf('tracks/')) : '/muzic2/' + s.replace(/^\\/+/, '')); return { id: tt.id || 0, src: encodeURI(src), title: tt.title, artist: tt.artist, feats: tt.feats || '', cover: '/muzic2/' + (tt.cover || data.cover || 'tracks/covers/placeholder.jpg'), video_url: tt.video_url || '' }; }).filter(t => t !== null); if(q.length && window.playTrack){ window.playTrack({ ...q[0], queue: q, queueStartIndex: 0 }); } } }catch(e){ console.error('album play error', e); } })()`;
		const coverSrc = toCoverSrc(album.cover || 'tracks/covers/placeholder.jpg');
		const typeLabel = releaseTypeLabel(album.album_type || album.type);
		return `
            <div class="tile" data-album-title="${escapeHtml(album.title)}">
                <img class="tile-cover" loading="lazy" src="${coverSrc}" alt="cover" onerror="this.onerror=null;this.src='/muzic2/public/assets/img/playlist-placeholder.png';" onclick="navigateTo('album', { album: '${encodeURIComponent(album.title)}' })">
                <div class="tile-title" onclick="navigateTo('album', { album: '${encodeURIComponent(album.title)}' })">${escapeHtml(album.title)}</div>
                <div class="tile-desc" onclick="navigateTo('album', { album: '${encodeURIComponent(album.title)}' })">${escapeHtml(album.artist || '')} • ${typeLabel}</div>
                <div class="tile-play" onclick="event.stopPropagation(); ${playAlbum}">&#9654;</div>
            </div>
		`;
	}

	// =====================
	// My Music (Favorites & Playlists)
	// =====================
	function isFavoritesPlaylistName(name) {
		return String(name || '').trim().toLowerCase() === 'любимые треки';
	}

	function coverPathFromTrack(t) {
		if (!t || !t.cover) return '';
		return String(t.cover).trim();
	}

	function notifyPlaylistsUpdated(detail) {
		try {
			document.dispatchEvent(new CustomEvent('playlists:updated', { detail: detail || {} }));
		} catch (_) { /* ignore */ }
	}

	function playlistCoverSrc(c) {
		if (!c) return '';
		const s = String(c);
		return s.startsWith('http') || s.startsWith('data:') ? s : ('/muzic2/' + s.replace(/^\/+/, ''));
	}

	function bindPlaylistsGridTiles() {
		document.querySelectorAll('#playlists-grid .playlist-tile').forEach((tile) => {
			const playlistId = tile.dataset.playlistId;
			const playlistName = tile.querySelector('.playlist-info h4')?.textContent || '';
			const delBtn = tile.querySelector('.playlist-del-btn');
			if (delBtn) {
				delBtn.addEventListener('click', (e) => {
					e.preventDefault();
					e.stopPropagation();
					void deleteUserPlaylist(playlistId, playlistName);
				});
			}
			tile.addEventListener('click', (e) => {
				if (e.target.closest('.playlist-del-btn')) return;
				e.preventDefault();
				if (playlistId && playlistName) openPlaylist(playlistId, playlistName);
			});
		});
	}

	function paintPlaylistsGrid(playlists) {
		const grid = document.getElementById('playlists-grid');
		if (!grid) return;
		const list = Array.isArray(playlists) ? playlists : [];
		grid.innerHTML = list.length ? list.map((pl) => {
			const fav = isFavoritesPlaylistName(pl.name);
			const delBtn = fav ? '' : '<button type="button" class="playlist-del-btn" title="Удалить плейлист" aria-label="Удалить">&times;</button>';
			return `
			<div class="playlist-tile" data-playlist-id="${pl.id}">
				${delBtn}
				<div class="playlist-cover">${pl.cover ? `<img src="${playlistCoverSrc(pl.cover)}" alt="">` : '<div class="playlist-placeholder">🎵</div>'}</div>
				<div class="playlist-info"><h4>${escapeHtml(pl.name)}</h4><p>${pl.track_count || 0} треков</p></div>
			</div>`;
		}).join('') : '<div class="empty">Плейлистов пока нет</div>';
		bindPlaylistsGridTiles();
	}

	async function refreshMyMusicPlaylists() {
		if (!document.getElementById('playlists-grid') || !currentUser) return;
		try {
			const r = await fetch(api('/muzic2/src/api/playlists_windows.php'), { credentials: 'include' });
			if (!r.ok) return;
			const j = await r.json();
			if (j.error) return;
			paintPlaylistsGrid(j.playlists || []);
		} catch (_) { /* ignore */ }
	}

	async function refreshPlaylistViewIfOpen() {
		const st = window.__openPlaylistState;
		if (!st || !st.id || !document.getElementById('tracks-tbody')) return;
		await openPlaylist(st.id, st.name, st.cover);
	}

	let _debouncePlaylistsRefresh = null;
	function schedulePlaylistsRefresh() {
		if (_debouncePlaylistsRefresh) clearTimeout(_debouncePlaylistsRefresh);
		_debouncePlaylistsRefresh = setTimeout(() => {
			_debouncePlaylistsRefresh = null;
			void refreshMyMusicPlaylists();
			void refreshPlaylistViewIfOpen();
		}, 280);
	}

	document.addEventListener('playlists:updated', schedulePlaylistsRefresh);

	async function deleteUserPlaylist(playlistId, playlistName) {
		if (isFavoritesPlaylistName(playlistName)) return;
		const id = Number(playlistId);
		if (!id) return;
		if (!confirm('Удалить плейлист «' + playlistName + '»?')) return;
		try {
			const res = await fetch(api('/muzic2/src/api/playlists.php/delete'), {
				method: 'POST',
				credentials: 'include',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ playlist_id: id })
			});
			let j = null;
			try { j = await res.json(); } catch (_) {}
			if (!res.ok || !j || !j.success) {
				throw new Error((j && j.error) || (j && j.message) || ('Ошибка ' + res.status));
			}
			const wasViewing = window.__openPlaylistState && Number(window.__openPlaylistState.id) === id;
			if (wasViewing) {
				window.__openPlaylistState = null;
				showPage('Моя музыка');
			} else {
				notifyPlaylistsUpdated({ action: 'delete', playlistId: id });
			}
		} catch (e) {
			alert(e.message || 'Не удалось удалить плейлист');
		}
	}

	async function renderMyMusic() {
		// Проверяем авторизацию
		if (!currentUser) {
			showLoginScreen();
			return;
		}
		
		injectMyMusicStyles();
				mainContent.innerHTML = `
					<div class="my-music-container">
					<div class="my-music-header"><h2>Моя музыка</h2></div>
						<div class="my-music-content">
							<div class="liked-tracks-section" style="margin-bottom:2rem;">
								<h3>Любимые треки</h3>
								<div id="liked-tracks-wrap"><div class="empty">Загрузка…</div></div>
							</div>
							<div class="playlists-section">
								<div class="my-header" style="margin-bottom:1rem;">
									<h3 style="margin:0;color:#fff;font-size:1.25rem;">Плейлисты</h3>
									<div class="my-actions">
										<button type="button" class="btn primary" id="btn-create-playlist">+ Создать плейлист</button>
									</div>
								</div>
							<div class="playlists-grid" id="playlists-grid"><div class="empty">Загрузка плейлистов…</div></div>
							</div>
							<div class="favorite-albums-section">
								<h3>Любимые альбомы</h3>
							<div class="albums-grid" id="favorite-albums-grid"><div class="empty">Загрузка любимых альбомов…</div></div>
								</div>
							</div>
				</div>`;

		const btnCreatePl = document.getElementById('btn-create-playlist');
		if (btnCreatePl) btnCreatePl.onclick = () => { void openCreatePlaylistDialog(); };

			(async function loadMyMusicData() {
				const grid = document.getElementById('playlists-grid');
				const favGrid = document.getElementById('favorite-albums-grid');
				const likedWrap = document.getElementById('liked-tracks-wrap');
				const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
				const playlistsUrl = api('/muzic2/src/api/playlists_windows.php');
				const likesUrl = getLikesAPI();
				const acPl = new AbortController();
				const acLk = new AbortController();
				const toPl = setTimeout(() => acPl.abort(), 22000);
				const toLk = setTimeout(() => acLk.abort(), 22000);
				let listsRes;
				let likesRes;
				try {
					[listsRes, likesRes] = await Promise.all([
						fetch(playlistsUrl, { credentials: 'include', signal: acPl.signal }),
						fetch(likesUrl, { credentials: 'include', signal: acLk.signal })
					]);
				} finally {
					clearTimeout(toPl);
					clearTimeout(toLk);
				}
				try {
					let playlists = [];
					let listsReady = false;
					let likesData = { tracks: [], albums: [] };
					let likesReady = false;
					if (listsRes.ok) {
						try {
							const j = await listsRes.json();
							if (j.error) {
								if (grid) grid.innerHTML = '<div class="empty">Плейлисты: ' + esc(j.error) + '</div>';
							} else {
								playlists = j.playlists || [];
								listsReady = true;
							}
						} catch (_) {
							if (grid) grid.innerHTML = '<div class="empty">Не удалось разобрать ответ плейлистов</div>';
						}
					} else {
						if (grid) grid.innerHTML = '<div class="empty">Ошибка плейлистов (' + listsRes.status + ')</div>';
					}
					if (likesRes.ok) {
						try {
							likesData = await likesRes.json();
							if (likesData.error) {
								if (likedWrap) likedWrap.innerHTML = '<div class="empty">Избранное: ' + esc(likesData.error) + '</div>';
								if (favGrid) favGrid.innerHTML = '<div class="empty">Избранное: ' + esc(likesData.error) + '</div>';
							} else {
								likesReady = true;
							}
						} catch (_) {
							if (likedWrap) likedWrap.innerHTML = '<div class="empty">Не удалось разобрать ответ лайков</div>';
							if (favGrid) favGrid.innerHTML = '<div class="empty">Не удалось разобрать ответ лайков</div>';
						}
					} else {
						if (likedWrap) likedWrap.innerHTML = '<div class="empty">Ошибка избранного (' + likesRes.status + ')</div>';
						if (favGrid) favGrid.innerHTML = '<div class="empty">Ошибка избранного (' + likesRes.status + ')</div>';
					}
					const likedAlbums = likesData.albums || [];
					syncLikesFromServerPayload(likesData);
					if (likesReady) {
						paintMyMusicLikedTracks(likesData.tracks || []);
					}
					if (grid && listsReady) {
						paintPlaylistsGrid(playlists);
					}
					if (favGrid && likesReady) {
						favGrid.innerHTML = likedAlbums.length
							? likedAlbums.map(a => createAlbumCard({ title: a.album_title, artist: 'Любимый альбом', cover: a.cover || '' })).join('')
							: '<div class="empty">Пока нет любимых альбомов</div>';
					}
					document.querySelectorAll('.heart-btn[data-track-id]').forEach(btn => {
						const id = Number(btn.getAttribute('data-track-id'));
						if (window.__likedSet.has(id)) btn.classList.add('liked');
					});
					document.querySelectorAll('.album-heart-btn[data-album-title]').forEach(btn => {
						const title = btn.getAttribute('data-album-title');
						if (window.__likedAlbums.has(title)) btn.classList.add('liked');
					});
				} catch (e) {
					console.error('loadMyMusicData', e);
					const slow = e && e.name === 'AbortError';
					const msg = slow ? 'Таймаут — MySQL не отвечает или сеть медленная. Проверьте MAMP.' : 'Сетевая ошибка при загрузке.';
					const lw = document.getElementById('liked-tracks-wrap');
					if (lw && /Загрузка/i.test(lw.textContent || '')) lw.innerHTML = '<div class="empty">' + msg + '</div>';
					if (grid && /Загрузка/i.test(grid.textContent || '')) grid.innerHTML = '<div class="empty">' + msg + '</div>';
					if (favGrid && /Загрузка/i.test(favGrid.textContent || '')) favGrid.innerHTML = '<div class="empty">' + msg + '</div>';
				}
			})();

	}

	function paintMyMusicLikedTracks(likedTracks) {
		const wrap = document.getElementById('liked-tracks-wrap');
		if (!wrap) return;
		const list = Array.isArray(likedTracks) ? likedTracks : [];
		if (!list.length) {
			wrap.innerHTML = '<div class="empty">Пока нет лайкнутых треков. Поставьте ♥ на главной или в поиске.</div>';
			return;
		}
		wrap.innerHTML = '<div class="card-row" id="liked-tracks-row"></div>';
		renderCards('liked-tracks-row', list, 'track');
	}

	async function refreshMyMusicLikedTracks() {
		if (!document.getElementById('liked-tracks-wrap') || !currentUser) return;
		try {
			const r = await fetch(getLikesAPI(), { credentials: 'include' });
			if (!r.ok) return;
			const j = await r.json();
			if (j.error) return;
			syncLikesFromServerPayload(j);
			paintMyMusicLikedTracks(j.tracks || []);
		} catch (_) { /* ignore */ }
	}

	let _debounceMyMusicLikes = null;
	document.addEventListener('likes:updated', () => {
		if (_debounceMyMusicLikes) clearTimeout(_debounceMyMusicLikes);
		_debounceMyMusicLikes = setTimeout(() => {
			_debounceMyMusicLikes = null;
			try { refreshMyMusicLikedTracks(); } catch (_) { /* ignore */ }
			notifyPlaylistsUpdated({ reason: 'likes' });
		}, 280);
	});

	// Capture-phase guard: prevent autoplay when clicking artist names
	document.addEventListener('click', (e)=>{
		const artistLink = e.target && e.target.closest ? e.target.closest('.artist-link') : null;
		if (artistLink) { e.preventDefault(); e.stopPropagation(); const name = artistLink.getAttribute('data-artist')||''; try { navigateTo('artist', { artist: name }); } catch(_){} return; }
		// For heart clicks we do NOT stop propagation; bubbling handler needs them
		// const heart = e.target && e.target.closest ? e.target.closest('.heart-btn, .album-like-btn') : null;
		// if (heart) { /* allow to bubble */ }
	}, true);

function applyHeartState(btn, liked) {
	if (!btn) return;
	const state = !!liked;
	btn.classList.toggle('liked', state);
	btn.setAttribute('aria-pressed', String(state));
	btn.setAttribute('title', state ? 'Убрать из избранного' : 'Добавить в избранное');
	btn.setAttribute('aria-label', state ? 'Убрать из избранного' : 'Добавить в избранное');
	// Обновляем Font Awesome иконку, если она есть
	const icon = btn.querySelector('i');
	if (icon) {
		if (state) {
			icon.classList.remove('far');
			icon.classList.add('fas');
		} else {
			icon.classList.remove('fas');
			icon.classList.add('far');
		}
	} else {
		// Fallback для старых кнопок без иконок
		try { btn.textContent = state ? '♥' : '♡'; } catch(_){}
	}
}

// Direct like toggle function (reliable, used by inline onclick) — UI сразу, запрос в фоне
function toggleTrackLike(trackId, buttonEl) {
	if (!currentUser) {
		try {
			attachAuthModalTriggers();
			const open = (id) => {
				document.querySelector('#auth-modals .modal-overlay').style.display = 'block';
				const m = document.getElementById(id);
				if (m) m.style.display = 'block';
			};
			open('login-modal');
		} catch (_) { /* ignore */ }
		return;
	}
	const btn = buttonEl || document.querySelector(`.heart-btn[data-track-id="${trackId}"]`);
	if (!btn) return;
	const wasLiked = btn.classList.contains('liked');
	applyHeartState(btn, !wasLiked);
	try {
		btn.classList.add('pulse');
		setTimeout(() => btn.classList.remove('pulse'), 180);
	} catch (_) { /* ignore */ }
	if (!window.__likedSet) window.__likedSet = new Set();
	if (wasLiked) window.__likedSet.delete(Number(trackId));
	else window.__likedSet.add(Number(trackId));
	const method = wasLiked ? 'DELETE' : 'POST';
	const tid = Number(trackId);
	fetch(getLikesAPI(), { method, credentials: 'include', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ track_id: tid }) })
		.then((res) => {
			if (!res.ok) throw new Error('Like request failed: ' + res.status);
			try {
				document.dispatchEvent(new CustomEvent('likes:updated', { detail: { trackId: tid, liked: !wasLiked } }));
			} catch (_) { /* ignore */ }
		})
		.catch((err) => {
			const b = buttonEl || document.querySelector(`.heart-btn[data-track-id="${trackId}"]`);
			if (b) applyHeartState(b, wasLiked);
			try {
				if (wasLiked) window.__likedSet.add(tid);
				else window.__likedSet.delete(tid);
			} catch (_) { /* ignore */ }
			console.error('toggleTrackLike failed', err);
		});
}

window.toggleTrackLike = toggleTrackLike;

// Context menu for albums
function showAlbumContextMenu(event, album, albumIndex) {
	const currentButton = event.target.closest('.album-more-btn');
	
	const existingMenu = document.querySelector('.context-menu');
	if (existingMenu) {
		const menuButtonId = existingMenu.dataset.sourceButtonId;
		const currentButtonId = currentButton ? String(currentButton) : null;
		
		if (menuButtonId && currentButtonId && menuButtonId === currentButtonId) {
			existingMenu.remove();
			return;
		}
		existingMenu.remove();
	}
	
	const menu = document.createElement('div');
	menu.className = 'context-menu show';
	
	if (currentButton) {
		menu.dataset.sourceButtonId = String(currentButton);
	}
	
	const albumTitle = album.album || album.title || '';
	const albumArtist = album.artist || '';
	
	const goToAlbum = document.createElement('button');
	goToAlbum.className = 'context-menu-item';
	goToAlbum.innerHTML = '<i class="fas fa-compact-disc" style="font-size: 0.9rem; color: #b3b3b3;"></i><span>Открыть альбом</span>';
	goToAlbum.onclick = () => {
		if (albumTitle) {
			navigateTo('album', { album: albumTitle });
		}
		menu.remove();
	};
	
	const goToArtist = document.createElement('button');
	goToArtist.className = 'context-menu-item';
	goToArtist.innerHTML = '<i class="fas fa-user" style="font-size: 0.9rem; color: #b3b3b3;"></i><span>Перейти к артисту</span>';
	goToArtist.onclick = () => {
		if (albumArtist) {
			navigateTo('artist', { artist: albumArtist });
		}
		menu.remove();
	};
	
	const toggleLike = document.createElement('button');
	toggleLike.className = 'context-menu-item';
	const isLiked = window.__likedAlbums && window.__likedAlbums.has(albumTitle);
	toggleLike.innerHTML = `<i class="${isLiked ? 'fas' : 'far'} fa-heart" style="font-size: 0.9rem; color: ${isLiked ? '#1ed760' : '#b3b3b3'};"></i><span>${isLiked ? 'Убрать из избранного' : 'Добавить в избранное'}</span>`;
	toggleLike.onclick = async () => {
		if (!window.__likedAlbums) window.__likedAlbums = new Set();
		if (window.__likedAlbums.has(albumTitle)) {
			await fetch(getLikesAPI(), { method:'DELETE', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ album_title: albumTitle })});
			window.__likedAlbums.delete(albumTitle);
		} else {
			await fetch(getLikesAPI(), { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ album_title: albumTitle })});
			window.__likedAlbums.add(albumTitle);
		}
		menu.remove();
	};
	
	menu.appendChild(goToAlbum);
	if (albumArtist) menu.appendChild(goToArtist);
	menu.appendChild(toggleLike);
	
	const rect = event.target.getBoundingClientRect();
	const scrollX = window.pageXOffset || document.documentElement.scrollLeft;
	const scrollY = window.pageYOffset || document.documentElement.scrollTop;
	const menuWidth = 200;
	
	const absoluteLeft = rect.left + scrollX;
	const absoluteTop = rect.bottom + scrollY;
	
	let left = absoluteLeft - menuWidth + rect.width;
	if (left < 10) {
		left = absoluteLeft;
	}
	
	menu.style.position = 'absolute';
	menu.style.left = left + 'px';
	menu.style.top = (absoluteTop + 5) + 'px';
	
	document.body.appendChild(menu);
	
	const closeMenu = (e) => {
		if (!menu.contains(e.target)) {
			menu.remove();
			document.removeEventListener('click', closeMenu);
		}
	};
	
	setTimeout(() => {
		document.addEventListener('click', closeMenu);
	}, 100);
}

	// Global delegation for heart toggle and artist links (bubbling phase business logic)
		document.addEventListener('click', (e) => {
		// Handle artist link clicks
		const artistLink = e.target.closest('.artist-link');
		if (artistLink) {
			e.preventDefault();
			e.stopPropagation();
			const artistName = artistLink.getAttribute('data-artist') || '';
			try { navigateTo('artist', { artist: artistName }); } catch(_) {}
			return;
		}
		
		const btn = (e.target && typeof e.target.closest === 'function')
			? e.target.closest('.heart-btn, .album-like-btn')
			: null;
		if (!btn) return;
		// Consume the event so other click handlers (e.g., play on card) don't interfere
		e.preventDefault();
		e.stopPropagation();

		// Keyboard support: activate on Enter/Space
		if (e.type === 'keydown') {
			const key = e.key || '';
			if (key !== 'Enter' && key !== ' ') return;
		}
		// Require auth for any like actions (tracks or albums)
		if (!currentUser) {
			try{ attachAuthModalTriggers(); const open = id => { document.querySelector('#auth-modals .modal-overlay').style.display='block'; const m=document.getElementById(id); if (m) m.style.display='block'; }; open('login-modal'); }catch(_){ }
			return;
		}
		
		// Handle track likes (delegated to helper)
		if (btn.hasAttribute('data-track-id')) {
			toggleTrackLike(btn.getAttribute('data-track-id'), btn);
			return;
		}
		
		// Handle album likes — сразу UI, без повторного GET loadAlbumLikes
		const albumTitle = btn.getAttribute('data-album-title');
		if (albumTitle) {
			const wasLiked = btn.classList.contains('liked');
			const syncAlbumHearts = (liked) => {
				if (!window.__likedAlbums) window.__likedAlbums = new Set();
				if (liked) window.__likedAlbums.add(albumTitle);
				else window.__likedAlbums.delete(albumTitle);
				document.querySelectorAll('.album-heart-btn[data-album-title], .album-like-btn[data-album-title]').forEach((b) => {
					if (b.getAttribute('data-album-title') === albumTitle) b.classList.toggle('liked', liked);
				});
				const albumLikeBtn = document.getElementById('album-like-btn');
				if (albumLikeBtn && albumLikeBtn.getAttribute('data-album-title') === albumTitle) {
					albumLikeBtn.classList.toggle('liked', liked);
				}
			};
			if (wasLiked) {
				syncAlbumHearts(false);
				fetch(getLikesAPI(), { method: 'DELETE', credentials: 'include', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ album_title: albumTitle }) })
					.then((res) => {
						if (!res.ok) {
							syncAlbumHearts(true);
							console.error('Failed to remove album like:', res.status);
						}
					})
					.catch(() => syncAlbumHearts(true));
			} else {
				syncAlbumHearts(true);
				fetch(getLikesAPI(), { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ album_title: albumTitle }) })
					.then((res) => {
						if (!res.ok) {
							syncAlbumHearts(false);
							console.error('Failed to add album like:', res.status);
						}
					})
					.catch(() => syncAlbumHearts(false));
			}
		}
	});

	function playlistTile(pl) {
		// Use special cover for "Любимые треки" playlist, otherwise use placeholder
		const isFavorites = (String(pl.name||'').trim().toLowerCase() === 'любимые треки');
		const cover = isFavorites ? '/muzic2/public/assets/img/playlist-placeholder.png' : (pl.cover ? `/muzic2/${pl.cover}` : '/muzic2/public/assets/img/playlist-placeholder.png');
		const safeName = escapeHtml(pl.name);
		return `
			<div class="tile playlist-tile" id="pl-${pl.id}" data-playlist-id="${pl.id}" data-playlist-name="${safeName}" data-cover="${cover}" style="cursor: pointer;" onclick="window.openPlaylistProxy && window.openPlaylistProxy('${pl.id}','${safeName.replace(/'/g,"\'")}','${cover}')">
				<img class="tile-cover" src="${cover}" alt="cover">
				<div class="tile-title">${safeName}</div>
				<div class="tile-desc">Плейлист</div>
				<div class="tile-play">&#9654;</div>
			</div>
		`;
	}

    async function openPlaylist(playlistId, playlistName, coverOverride) {
        // Render playlist as album-like page (full-page), even if empty
		window.__openPlaylistState = {
			id: String(playlistId),
			name: playlistName,
			cover: coverOverride || ''
		};
		window.currentPage = 'playlist';
		try {
			const res = await fetch(api(`/muzic2/src/api/playlists.php?playlist_id=${playlistId}`), { credentials: 'include' });
			const data = await res.json();
            const tracks = Array.isArray(data.tracks) ? data.tracks : [];
            // Determine cover: prefer cover passed from tile, else special for favorites or first track
            let cover = coverOverride || '';
            if (!cover) {
                const isFavorites = (String(playlistName||'').trim().toLowerCase() === 'любимые треки');
                cover = isFavorites ? '/muzic2/public/assets/img/playlist-placeholder.png' : ('/muzic2/' + ((tracks[0] && tracks[0].cover) || 'tracks/covers/placeholder.jpg'));
            }
            const finalCover = encodeURI(cover);
            const canDeletePl = !isFavoritesPlaylistName(playlistName);
            const totalDuration = tracks.reduce((s,t)=> s + (parseInt(t.duration)||0), 0);
            const minutes = Math.floor(totalDuration/60); const seconds = totalDuration%60;
            // Build page
            const albumStyles = `
            <style>
            .album-header{display:flex;align-items:flex-end;gap:2.5rem;margin-top:2.5rem;margin-bottom:2.5rem}
            .album-cover{width:220px;height:220px;border-radius:18px;object-fit:cover;box-shadow:0 8px 32px rgba(30,185,84,0.18);background:#181818}
            .album-meta{display:flex;flex-direction:column;gap:1.2rem}
            .album-title{font-size:3.2rem;font-weight:900;color:#fff;margin-bottom:0.5rem}
            .album-artist{font-size:1.3rem;color:#b3b3b3;font-weight:600}
            .tracks-table{width:100%;border-collapse:collapse;margin-bottom:2rem}
            .tracks-table th,.tracks-table td{padding:0.7rem 1rem;text-align:left;color:#fff;font-size:1.08rem}
            .tracks-table th{color:#b3b3b3;font-weight:600;font-size:1.02rem;border-bottom:1px solid #232323}
            .tracks-table tr{transition:background 0.15s;cursor:pointer;position:relative}
            .tracks-table tr:hover{background:#232323}
            .track-num{color:#b3b3b3;width:2.5rem;font-size:1.02rem}
            .track-title .track-artist{color:#b3b3b3;font-size:1.01rem;font-weight:400;margin-top:0.2rem}
            .track-duration{color:#b3b3b3;font-size:1.01rem;text-align:center;width:4.5rem;vertical-align:middle}
            .exp-badge{display:inline-block;width:16px;height:16px;line-height:16px;text-align:center;margin:0 6px 0 0;border:0;border-radius:3px;font-size:10px;font-weight:800;color:#2b2b2b;background:#cfcfcf;vertical-align:middle}
            /* Home cards base (used on main) */
            .card-row,.tile-row{display:grid;grid-template-columns:repeat(6,1fr);gap:18px}
            .tile{position:relative !important;background:#181818;border-radius:18px;overflow:visible !important;padding-bottom:10px;transition:transform .15s}
            .tile:hover{transform:translateY(-2px)}
            .tile-cover{width:100%;aspect-ratio:1/1;object-fit:cover;display:block}
            .tile-title{font-weight:800;color:#fff;margin:.6rem .6rem .2rem}
            .tile-desc{color:#bdbdbd;margin:0 .6rem;font-size:.95rem}
            /* Prevent artist names overlap */
            .artist-row .tile-title{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            /* Responsive grids */
            @media(max-width:1024px){.card-row,.tile-row{grid-template-columns:repeat(4,1fr)}}
            @media(max-width:768px){.card-row,.tile-row{grid-template-columns:repeat(3,1fr)}.tile-title{font-size:1rem}.tile-desc{font-size:.88rem}}
            @media(max-width:560px){.card-row,.tile-row{grid-template-columns:repeat(2,1fr);gap:14px}.tile{border-radius:16px}.tile-title{font-size:.98rem}.tile-desc{font-size:.84rem}}
            @media(max-width:400px){.card-row,.tile-row{grid-template-columns:1fr;gap:12px}}
            </style>`;
            mainContent.innerHTML = albumStyles + `
                <div class="album-header">
                    <img class="album-cover" loading="lazy" src="${finalCover}" alt="cover" onerror="this.onerror=null;this.src='/muzic2/tracks/covers/m1000x1000.jpeg';">
                    <div class="album-meta">
                        <div class="album-title">${escapeHtml(playlistName)}</div>
                        <div class="album-artist">Плейлист • ${tracks.length} треков • ${minutes}:${String(seconds).padStart(2,'0')}</div>
						</div>
					</div>
                <table class="tracks-table">
                    <thead><tr><th>#</th><th>Название</th><th>⏱</th></tr></thead>
                    <tbody id="tracks-tbody"></tbody>
                </table>
            `;
            const tbody = document.getElementById('tracks-tbody');
            if (canDeletePl) {
                const meta = mainContent.querySelector('.album-meta');
                if (meta) {
                    const delBtn = document.createElement('button');
                    delBtn.type = 'button';
                    delBtn.id = 'playlist-view-delete';
                    delBtn.className = 'btn danger';
                    delBtn.textContent = 'Удалить плейлист';
                    delBtn.style.cssText = 'align-self:flex-start;margin-top:0.5rem';
                    delBtn.onclick = () => { void deleteUserPlaylist(playlistId, playlistName); };
                    meta.appendChild(delBtn);
                }
            }
            if (!tracks.length) {
                const tr=document.createElement('tr'); tr.innerHTML = `<td class="track-num">—</td><td class="track-title"><div class="track-artist">Пока нет треков. Лайкайте треки сердечком — они появятся здесь.</div></td><td class="track-duration">0:00</td>`; tbody.appendChild(tr);
            } else {
                tracks.forEach((t,i)=>{
                    const tr=document.createElement('tr');
                    const d = parseInt(t.duration)||0; const mm=Math.floor(d/60); const ss=d%60;
                    tr.innerHTML = `
                        <td class="track-num">${i+1}</td>
                        <td class="track-title">${t.explicit?'<span class="exp-badge">E</span>':''}${escapeHtml(t.title||'')}<div class="track-artist">${renderArtistInline((t.feats && String(t.feats).trim()) ? `${t.artist||''}, ${t.feats}` : (t.artist||''))}</div></td>
                        <td class="track-duration">${mm}:${String(ss).padStart(2,'0')}</td>`;
                    // Click on artist name navigates to artist page, not play
                    tr.addEventListener('click', (e)=>{
                        const link = e.target && e.target.closest ? e.target.closest('.artist-link') : null;
                        if (link) {
                            e.preventDefault(); e.stopPropagation();
                            const name = link.getAttribute('data-artist') || '';
                            try { navigateTo('artist', { artist: name }); } catch(_) {}
                        }
                    });
                    tr.onclick = ()=>{
                        const q = tracks.map(tt=>({
                            src: (/^https?:/i.test(tt.src||'')) ? tt.src : ('/muzic2/' + ((tt.src||'').indexOf('tracks/')!==-1 ? (tt.src||'').slice((tt.src||'').indexOf('tracks/')) : (tt.src||'').replace(/^\/+/, ''))),
                            title: tt.title,
                            artist: (tt.feats && String(tt.feats).trim()) ? `${tt.artist||''}, ${tt.feats}` : (tt.artist||''),
                            feats: tt.feats||'',
                            cover: '/muzic2/' + (tt.cover || 'tracks/covers/placeholder.jpg'),
                            video_url: tt.video_url || ''
                        }));
                        window.playTrack && window.playTrack({ queue:q, queueStartIndex:i });
                    };
                    tbody.appendChild(tr);
                });
            }
		} catch (e) {
            mainContent.innerHTML = '<div class="error">Ошибка загрузки плейлиста</div>';
		}
	}

	// Expose safe proxy for inline onclick
	window.openPlaylistProxy = function(pid, pname, cover){ try{ openPlaylist(pid, pname, cover); }catch(_){} };

	async function uploadPlaylistCoverImage(file) {
		const fd = new FormData();
		fd.append('cover', file);
		const r = await fetch(api('/muzic2/public/upload_cover.php'), { method: 'POST', body: fd });
		let j = null;
		try { j = await r.json(); } catch (_) {}
		if (!r.ok || !j || !j.success) {
			throw new Error((j && j.message) || 'Ошибка загрузки обложки');
		}
		return String(j.path || '').trim();
	}

	const PL_CREATE_LIST_MAX = 400;

	function injectPlCreateModalStyles() {
		if (document.getElementById('pl-create-modal-styles')) return;
		const s = document.createElement('style');
		s.id = 'pl-create-modal-styles';
		s.textContent = `
			#pl-create-overlay {
				position: fixed;
				inset: 0;
				z-index: 10050;
				background: rgba(0,0,0,.65);
				display: flex;
				align-items: center;
				justify-content: center;
				padding: 1rem;
			}
			.pl-create-modal {
				background: #121212;
				color: #eee;
				border-radius: 16px;
				max-width: 480px;
				width: 100%;
				max-height: 85vh;
				overflow: auto;
				padding: 1.25rem 1.5rem;
				box-shadow: 0 8px 32px rgba(0,0,0,.5);
			}
			.pl-create-modal h3 { margin: 0 0 1rem; font-size: 1.15rem; color: #fff; }
			.pl-create-modal label { display: block; font-size: .85rem; color: #aaa; margin: .75rem 0 .35rem; }
			#pl-create-name {
				width: 100%;
				box-sizing: border-box;
				padding: .55rem .75rem;
				border-radius: 8px;
				border: 1px solid #333;
				background: #1a1a1a;
				color: #fff;
			}
			#pl-create-cover { width: 100%; font-size: .85rem; }
			.pl-create-list {
				max-height: 240px;
				overflow: auto;
				border: 1px solid #2a2a2a;
				border-radius: 10px;
				padding: .35rem;
				margin-top: .25rem;
			}
			.pl-create-row {
				display: flex;
				gap: .5rem;
				align-items: flex-start;
				padding: .35rem .25rem;
				cursor: pointer;
				border-radius: 6px;
				font-size: .88rem;
			}
			.pl-create-row:hover { background: #1e1e1e; }
			.pl-create-row input { margin-top: .2rem; flex-shrink: 0; }
			.pl-create-actions { display: flex; gap: .5rem; justify-content: flex-end; margin-top: 1.25rem; }
			.pl-create-muted { color: #888; font-size: .85rem; margin: .5rem 0 0; }
			.pl-create-err { color: #e74c3c; font-size: .85rem; margin-top: .75rem; }
			.pl-create-bulk { font-size: .82rem; margin: .25rem 0 0; display: flex; gap: 1rem; }
			.pl-create-bulk .link-btn {
				background: none;
				border: none;
				color: #1db954;
				cursor: pointer;
				padding: 0;
				font: inherit;
				text-decoration: underline;
			}
		`;
		document.head.appendChild(s);
	}

	async function openCreatePlaylistDialog() {
		injectMyMusicStyles();
		injectPlCreateModalStyles();
		let tracks = [];
		try {
			const r = await fetch(getLikesAPI(), { credentials: 'include' });
			if (r.ok) {
				const j = await r.json();
				tracks = Array.isArray(j.tracks) ? j.tracks : [];
			}
		} catch (_) {}

		const prev = document.getElementById('pl-create-overlay');
		if (prev) prev.remove();

		const shown = tracks.slice(0, PL_CREATE_LIST_MAX);
		const listParts = [];
		if (!shown.length) {
			listParts.push('<p class="pl-create-muted">Нет лайкнутых треков. Можно создать пустой плейлист или добавить треки в «Любимое».</p>');
		} else {
			for (const t of shown) {
				const id = Number(t.id) || 0;
				if (!id) continue;
				const line = `${t.title || ''} — ${t.artist || ''}`;
				listParts.push(`<label class="pl-create-row"><input type="checkbox" class="pl-create-cb" value="${id}"> <span>${escapeHtml(line)}</span></label>`);
			}
			if (tracks.length > PL_CREATE_LIST_MAX) {
				listParts.push(`<p class="pl-create-muted">Показаны первые ${PL_CREATE_LIST_MAX} из ${tracks.length} треков.</p>`);
			}
		}

		const overlay = document.createElement('div');
		overlay.id = 'pl-create-overlay';
		overlay.innerHTML = `
			<div class="pl-create-modal" role="dialog" aria-modal="true" aria-labelledby="pl-create-title">
				<h3 id="pl-create-title">Новый плейлист</h3>
				<label for="pl-create-name">Название</label>
				<input type="text" id="pl-create-name" maxlength="255" placeholder="Мой плейлист" autocomplete="off" />
				<label for="pl-create-cover">Обложка (необязательно)</label>
				<input type="file" id="pl-create-cover" accept="image/jpeg,image/png,image/webp,image/gif" />
				<label>Треки из «Любимое»</label>
				<p class="pl-create-bulk"><button type="button" class="link-btn" id="pl-create-all">Выбрать все</button><button type="button" class="link-btn" id="pl-create-none">Снять все</button></p>
				<div class="pl-create-list">${listParts.join('')}</div>
				<div class="pl-create-actions">
					<button type="button" class="btn" id="pl-create-cancel">Отмена</button>
					<button type="button" class="btn primary" id="pl-create-save">Создать</button>
				</div>
				<div id="pl-create-err" class="pl-create-err" style="display:none"></div>
			</div>
		`;
		document.body.appendChild(overlay);
		const nameEl = overlay.querySelector('#pl-create-name');
		if (nameEl) {
			requestAnimationFrame(() => { try { nameEl.focus(); } catch (_) {} });
		}

		const errBox = overlay.querySelector('#pl-create-err');
		const showErr = (msg) => { errBox.textContent = msg; errBox.style.display = 'block'; };
		const hideErr = () => { errBox.style.display = 'none'; errBox.textContent = ''; };

		function onEsc(e) {
			if (e.key === 'Escape') {
				document.removeEventListener('keydown', onEsc);
				close();
			}
		}
		function close() {
			document.removeEventListener('keydown', onEsc);
			overlay.remove();
		}
		document.addEventListener('keydown', onEsc);
		overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
		overlay.querySelector('#pl-create-cancel').addEventListener('click', close);
		overlay.querySelector('#pl-create-all').addEventListener('click', () => {
			overlay.querySelectorAll('.pl-create-cb').forEach((cb) => { cb.checked = true; });
		});
		overlay.querySelector('#pl-create-none').addEventListener('click', () => {
			overlay.querySelectorAll('.pl-create-cb').forEach((cb) => { cb.checked = false; });
		});

		overlay.querySelector('#pl-create-save').addEventListener('click', async () => {
			hideErr();
			const nameInput = overlay.querySelector('#pl-create-name');
			const name = (nameInput && nameInput.value || '').trim();
			if (!name) {
				showErr('Введите название плейлиста');
				return;
			}
			const ids = Array.from(overlay.querySelectorAll('.pl-create-cb:checked')).map((cb) => Number(cb.value)).filter((n) => n > 0);
			let cover = '';
			const fileInput = overlay.querySelector('#pl-create-cover');
			const file = fileInput && fileInput.files && fileInput.files[0];
			try {
				if (file) {
					cover = await uploadPlaylistCoverImage(file);
				}
			} catch (e) {
				showErr(e.message || 'Не удалось загрузить обложку');
				return;
			}
			if (!cover && ids.length) {
				const byId = new Map(tracks.map((t) => [Number(t.id), t]));
				for (const tid of ids) {
					const c = coverPathFromTrack(byId.get(tid));
					if (c) {
						cover = c;
						break;
					}
				}
			}
			const btn = overlay.querySelector('#pl-create-save');
			btn.disabled = true;
			try {
				const payload = { name, is_public: 0, track_ids: ids };
				if (cover) payload.cover = cover;
				const res = await fetch(api('/muzic2/src/api/playlists.php'), {
					method: 'POST',
					credentials: 'include',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify(payload)
				});
				let j = null;
				try { j = await res.json(); } catch (_) {}
				if (!res.ok || !j || !j.success) {
					throw new Error((j && j.error) || (j && j.message) || ('Ошибка ' + res.status));
				}
				close();
				if (document.getElementById('playlists-grid')) {
					notifyPlaylistsUpdated({ action: 'create', playlistId: j.playlist_id });
				} else {
					showPage('Моя музыка');
				}
			} catch (e) {
				showErr(e.message || 'Ошибка создания');
				btn.disabled = false;
			}
		});
	}

	function injectMyMusicStyles() {
		if (document.getElementById('mymusic-styles')) return;
		const s = document.createElement('style');
		s.id = 'mymusic-styles';
		s.textContent = `
			.my-section { margin: 2rem 0; }
			.my-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
			.my-actions { display: flex; gap: .5rem; }
			.btn { background: #333; color: #fff; border: none; padding: .5rem 1rem; border-radius: 6px; cursor: pointer; }
			.btn.primary { background: #1db954; }
			.btn.danger { background: #c0392b; }
			.empty { color: #666; padding: 1rem; }
			.auth-required { text-align: center; padding: 3rem 1rem; }
			.auth-actions { display: flex; gap: .5rem; justify-content: center; margin-top: 1rem; }
			.card { position: relative; }
			.heart-btn { position:absolute; right:.5rem; bottom:.5rem; background:#222; border:1px solid #333; color:#bbb; border-radius:999px; width:36px; height:36px; cursor:pointer; }
			.heart-btn.liked { background:#1db954; border-color:#1db954; color:#fff; }
			
			#playlist-view {
				display: block;
				margin-top: 2rem;
			}
			
			.playlist-section {
				background: #1a1a1a;
				border-radius: 8px;
				padding: 1.5rem;
			}
			
			.playlist-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 1.5rem;
				padding-bottom: 1rem;
				border-bottom: 1px solid #333;
			}
			
			.playlist-header h3 {
				color: #fff;
				margin: 0;
				font-size: 1.5rem;
			}
			
			.playlist-actions {
				display: flex;
				gap: 0.5rem;
			}
			
			.playlist-actions .btn {
				padding: 0.5rem 1rem;
				font-size: 0.9rem;
			}
			
			.playlist-actions .btn.danger {
				background: #dc3545;
				color: white;
			}
			#liked-tracks-wrap .card-row {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
				gap: 16px;
			}
			#liked-tracks-wrap .card { min-height: 72px; }

			/* My Music: smaller playlist cards */
			#playlists-grid {
				display: grid;
				grid-template-columns: repeat(6, minmax(0, 1fr));
				gap: 12px;
			}
			#playlists-grid .playlist-tile {
				position: relative;
				background: #181818;
				border-radius: 14px;
				padding: 10px;
				cursor: pointer;
			}
			#playlists-grid .playlist-del-btn {
				position: absolute;
				top: 6px;
				right: 6px;
				z-index: 3;
				width: 28px;
				height: 28px;
				border: none;
				border-radius: 50%;
				background: rgba(0,0,0,.65);
				color: #fff;
				font-size: 1.1rem;
				line-height: 1;
				cursor: pointer;
			}
			#playlists-grid .playlist-del-btn:hover {
				background: #c0392b;
			}
			#playlists-grid .playlist-cover {
				width: 100%;
				aspect-ratio: 1 / 1;
				border-radius: 10px;
				overflow: hidden;
				background: #222;
				margin-bottom: 8px;
			}
			#playlists-grid .playlist-cover img {
				width: 100%;
				height: 100%;
				object-fit: cover;
				display: block;
			}
			#playlists-grid .playlist-info h4 {
				margin: 0;
				font-size: .92rem;
				font-weight: 800;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
			#playlists-grid .playlist-info p {
				margin: 3px 0 0;
				font-size: .82rem;
				color: #a9a9a9;
			}

			/* Liked albums: ensure horizontal compact row and visible covers */
			#favorite-albums-grid {
				display: grid;
				grid-template-columns: repeat(5, minmax(0, 1fr));
				gap: 12px;
			}
			#favorite-albums-grid .tile {
				min-width: 0;
				background: #181818;
				border-radius: 14px;
			}
			#favorite-albums-grid .tile-cover {
				width: 100%;
				aspect-ratio: 1 / 1;
				object-fit: cover;
				border-radius: 10px;
			}
			@media (max-width: 1200px) { #playlists-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); } #favorite-albums-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
			@media (max-width: 900px) { #playlists-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } #favorite-albums-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
			@media (max-width: 640px) { #playlists-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } #favorite-albums-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
			
		`;
		document.head.appendChild(s);
	}

	// =====================
	// Auth Modals (basic)
	// =====================
	function ensureAuthModals() {
		if (document.getElementById('auth-modals')) return;
		const wrap = document.createElement('div');
		wrap.id = 'auth-modals';
		wrap.innerHTML = `
			<div class="modal-overlay" style="display:none"></div>
			<div class="modal-center" style="display:none">
				<div class="modal" id="login-modal" role="dialog" aria-modal="true" style="display:none">
					<h3>Вход</h3>
					<input id="login-login" placeholder="Email или логин" autocomplete="username">
					<input id="login-password" type="password" placeholder="Пароль" autocomplete="current-password">
					<div id="login-error" class="modal-error" style="display:none"></div>
					<div class="modal-actions">
						<button id="login-submit" class="btn primary">Войти</button>
						<button id="login-close" class="btn">Отмена</button>
					</div>
				</div>
			</div>
		`;
		document.body.appendChild(wrap);
		const styles = document.createElement('style');
		styles.textContent = `
			.modal-overlay{ position:fixed; inset:0; background:rgba(0,0,0,.7); backdrop-filter: blur(2px); z-index:20000; }
			.modal-center{ position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:20001; pointer-events:none; }
			.modal{ position:relative; background:#181818; color:#fff; border:1px solid #2a2a2a; border-radius:12px; padding:1rem; width:92%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,.6); pointer-events:auto; }
			.modal h3{ margin:0 0 .8rem 0; font-weight:700; }
			.modal input{ width:100%; margin:.4rem 0; padding:.7rem .8rem; border:1px solid #333; border-radius:8px; background:#121212; color:#fff; outline:none; }
			.modal input:focus{ border-color:#1db954; }
			.modal-actions{ margin-top:.7rem; display:flex; gap:.5rem; justify-content:flex-end; }
			.modal-error{ margin-top:.4rem; background:#2a1b1b; color:#ff6b6b; border:1px solid #4b2323; border-radius:8px; padding:.6rem .8rem; font-size:.92rem; }
		`;
		document.head.appendChild(styles);
	}

	function attachAuthModalTriggers() {
		const overlay = () => document.querySelector('#auth-modals .modal-overlay');
		const center = () => document.querySelector('#auth-modals .modal-center');
		const open = id => { ensureAuthModals(); const o=overlay(); const c=center(); o.style.display='block'; c.style.display='flex'; document.querySelectorAll('#auth-modals .modal').forEach(m=>m.style.display='none'); document.getElementById(id).style.display='block'; setTimeout(()=>{ const first=document.querySelector(`#${id} input`); if(first) first.focus(); }, 0); };
		const closeAll = () => { const o=overlay(); const c=center(); if(o) o.style.display='none'; if(c) c.style.display='none'; document.querySelectorAll('#auth-modals .modal').forEach(m=>m.style.display='none'); };
		const loginBtn = document.getElementById('open-login');
		// Регистрация убрана - только админ может регистрировать пользователей
		if (loginBtn) loginBtn.onclick = () => open('login-modal');
		ensureAuthModals();
		const loginClose = document.getElementById('login-close');
		if (loginClose) loginClose.onclick = closeAll;
		const ov = overlay(); if (ov) ov.onclick = closeAll;
		document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') closeAll(); });
		const doLogin = async () => {
			const login = document.getElementById('login-login').value.trim();
			const password = document.getElementById('login-password').value;
			const errBox = document.getElementById('login-error');
			const submitBtn = document.getElementById('login-submit');
			if (errBox) { errBox.style.display='none'; errBox.textContent=''; }
			if (!login || !password) { if (errBox){ errBox.textContent='Введите логин и пароль'; errBox.style.display='block'; } return; }
			try {
				if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Входим...'; }
				console.log('[Auth] Windows doLogin start');
				const authAPI = getAuthAPI();
				const res = await fetch(authAPI, { 
					method: 'POST', 
					credentials: 'include', 
					headers: { 'Content-Type': 'application/json' }, 
					body: JSON.stringify({ 
						action: 'login',
						login, 
						password 
					}) 
				});
				let ok = res.ok;
				let payload = null;
				try { payload = await res.json(); } catch(_) { payload = null; }
				if (!ok) {
					const msg = (payload && payload.error) ? payload.error : 'Ошибка авторизации';
					if (errBox) { errBox.textContent = msg; errBox.style.display='block'; }
					if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Войти'; }
					return;
				}
				// Ultra-fast path for Windows: trust API payload and avoid extra roundtrip
                if (payload && payload.user) {
					currentUser = payload.user;
                    try { localStorage.setItem('currentUser', JSON.stringify(currentUser)); } catch(_) {}
					closeAll();
					showAuthenticatedContent();
					renderAuthHeader();
					return;
				}
				// Mac (or fallback): verify via GET user endpoint
				const uRes = await fetch(getUserAPI(), { credentials: 'include' });
				const u = await uRes.json();
				if (u && u.authenticated && u.user) {
					currentUser = u.user;
                    try { localStorage.setItem('currentUser', JSON.stringify(currentUser)); } catch(_) {}
					closeAll();
					showAuthenticatedContent();
					renderAuthHeader();
				} else {
					if (errBox) { errBox.textContent = 'Сессия не установлена'; errBox.style.display='block'; }
					if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Войти'; }
				}
			} catch (e) {
				if (errBox) { errBox.textContent = 'Сетевая ошибка'; errBox.style.display='block'; }
				if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Войти'; }
			}
		};
		// Регистрация убрана - только админ может регистрировать пользователей через админ панель
		async function doLoginPostRegister(login, password){
			const errBox = document.getElementById('login-error'); if (errBox){ errBox.style.display='none'; errBox.textContent=''; }
			const authAPI = getAuthAPI();
			const res = await fetch(authAPI, { 
				method: 'POST', 
				credentials: 'include', 
				headers: { 'Content-Type': 'application/json' }, 
				body: JSON.stringify({ 
					action: 'login',
					login, 
					password 
				}) 
			});
			let ok = res.ok; let payload=null; try { payload = await res.json(); } catch(_) {}
			if (!ok) { if (errBox){ errBox.textContent=(payload&&payload.error)||'Ошибка авторизации'; errBox.style.display='block'; } return; }
			const uRes = await fetch(getUserAPI(), { credentials: 'include' });
			const u = await uRes.json();
			if (u && u.authenticated && u.user) {
				currentUser = u.user;
				closeAll();
				showAuthenticatedContent();
				renderAuthHeader();
			} else {
				if (errBox){ errBox.textContent='Сессия не установлена'; errBox.style.display='block'; }
			}
		}
		const loginSubmit = document.getElementById('login-submit');
		if (loginSubmit) {
			loginSubmit.onclick = doLogin;
			// Enter key within login inputs
			const loginInput = document.getElementById('login-login');
			const passInput = document.getElementById('login-password');
			[loginInput, passInput].forEach(inp => {
				if (inp) inp.addEventListener('keydown', (e)=>{ if (e.key === 'Enter') { e.preventDefault(); doLogin(); } });
			});
		}
		// Регистрация убрана - только админ может регистрировать пользователей
		// Fallback delegated handler in case direct binding missed
		document.addEventListener('click', (e)=>{
			const btn = e.target && e.target.closest && e.target.closest('#login-submit');
			if (btn) { e.preventDefault(); doLogin(); }
		}, true);
	}

	function renderCards(rowId, items, type) {
		let row = document.getElementById(rowId);
		if (!row) return;
		if (!Array.isArray(items)) items = [];
		let html = '';
		if (type === 'album') {
			row.className = 'tile-row';
			html = items.map((item, idx) => {
				const albumTitle = item.album || item.title || '';
				const isLiked = window.__likedAlbums && window.__likedAlbums.has(albumTitle);
				return `
				<div class="tile" data-album="${encodeURIComponent(albumTitle)}" data-idx="${idx}">
					<button class="album-heart-btn ${isLiked ? 'liked' : ''}" data-album-title="${escapeHtml(albumTitle)}" title="В избранные альбомы">
						<i class="${isLiked ? 'fas' : 'far'} fa-heart"></i>
					</button>
					<button class="album-more-btn" title="Ещё">
						<i class="fas fa-ellipsis-h"></i>
					</button>
					<img class="tile-cover" loading="lazy" src="/muzic2/${item.cover || 'tracks/covers/placeholder.jpg'}" alt="cover">
					<div class="tile-title">${escapeHtml(albumTitle)}</div>
					<div class="tile-desc">${escapeHtml(item.artist || '')}</div>
					<div class="tile-play">&#9654;</div>
				</div>
			`;
			}).join('');
		} else if (type === 'mix') {
			row.className = 'tile-row';
			html = items.map((item, idx) => {
				let coverPath = item.cover || 'tracks/covers/placeholder.jpg';
				if (coverPath && !coverPath.startsWith('/muzic2/') && !coverPath.startsWith('http')) {
					coverPath = '/muzic2/' + coverPath;
				}
				const count = item.track_count || (Array.isArray(item.tracks) ? item.tracks.length : 0);
				const countLabel = count ? `${count} треков` : 'Подборка';
				return `
				<div class="tile mood-mix-tile" data-idx="${idx}" data-mood="${escapeHtml(item.mood || '')}">
					<img class="tile-cover" loading="lazy" src="${coverPath}" alt="cover">
					<div class="tile-title">${escapeHtml(item.title || item.album || 'Микс')}</div>
					<div class="tile-desc">${escapeHtml(item.subtitle || item.artist || '')}${item.subtitle ? ' · ' : ''}${countLabel}</div>
					<div class="tile-play">&#9654;</div>
				</div>
			`;
			}).join('');
		} else if (type === 'artist') {
			row.className = 'artist-row';
			html = items.map(item => `
				<div class="artist-tile" data-artist="${encodeURIComponent(item.artist)}">
					<img class="artist-avatar" loading="lazy" src="/muzic2/${item.cover || 'tracks/covers/placeholder.jpg'}" alt="artist">
					<div class="artist-name">${escapeHtml(item.artist)}</div>
				</div>
			`).join('');
        } else if (type === 'track') {
			row.className = 'card-row';
			html = items.map((item, idx) => {
				// Правильно обрабатываем путь к обложке
				let coverPath = item.cover || 'tracks/covers/placeholder.jpg';
				if (coverPath && !coverPath.startsWith('/muzic2/') && !coverPath.startsWith('http')) {
					coverPath = '/muzic2/' + coverPath;
				}
				const trackId = item.id || idx;
				const isLiked = window.__likedSet && window.__likedSet.has(trackId);
				return `
				<div class="card" data-idx="${idx}">
					<img class="card-cover" loading="lazy" src="${coverPath}" alt="cover">
					<div class="card-info">
                <div class="card-title">${escapeHtml(item.title)}</div>
                <div class="card-artist">${item.explicit? '<span class="exp-badge" title="Нецензурная лексика">E</span>':''}${renderArtistInline(item.feats && String(item.feats).trim() ? `${item.artist}, ${item.feats}` : item.artist)}</div>
						<div class="card-type">${item.album_type || ''}</div>
					</div>
					<button type="button" class="heart-btn ${isLiked ? 'liked' : ''}" data-track-id="${trackId}" title="Добавить в избранное" aria-label="Добавить в избранное" aria-pressed="${isLiked}" tabindex="0">
						<i class="${isLiked ? 'fas' : 'far'} fa-heart"></i>
					</button>
					<button class="kebab" title="Ещё">
						<i class="fas fa-ellipsis-h"></i>
					</button>
				</div>
			`;
			}).join('');
		}
		row.innerHTML = html;

		if (type === 'album') {
			// Add like button handlers for albums
			row.querySelectorAll('.album-heart-btn').forEach((btn, idx) => {
				btn.onclick = async (e) => {
					e.stopPropagation();
					if (!window.__likedAlbums) window.__likedAlbums = new Set();
					const albumTitle = items[idx].album || items[idx].title || '';
					const icon = btn.querySelector('i');
					if (window.__likedAlbums.has(albumTitle)) {
						await fetch(getLikesAPI(), { method:'DELETE', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ album_title: albumTitle })});
						window.__likedAlbums.delete(albumTitle);
						btn.classList.remove('liked');
						if (icon) {
							icon.classList.remove('fas');
							icon.classList.add('far');
						}
					} else {
						await fetch(getLikesAPI(), { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ album_title: albumTitle })});
						window.__likedAlbums.add(albumTitle);
						btn.classList.add('liked');
						if (icon) {
							icon.classList.remove('far');
							icon.classList.add('fas');
						}
					}
				};
			});
			
			// Add more button handlers for albums
			row.querySelectorAll('.album-more-btn').forEach((btn, idx) => {
				btn.onclick = (e) => {
					e.stopPropagation();
					// Check if menu is already open for this button
					const existingMenu = document.querySelector('.context-menu');
					if (existingMenu && existingMenu.dataset.sourceButtonId === String(btn)) {
						existingMenu.remove();
						return;
					}
					showAlbumContextMenu(e, items[idx], idx);
				};
			});
			
			row.onclick = function(e) {
				if (e.target.closest('.album-heart-btn') || e.target.closest('.album-more-btn')) return;
				let el = e.target;
				while (el && el !== row && !el.hasAttribute('data-album')) el = el.parentElement;
				if (el && el.hasAttribute('data-album')) {
					const albumName = el.getAttribute('data-album');
					navigateTo('album', { album: decodeURIComponent(albumName) });
				}
			};
		} else if (type === 'artist') {
			row.onclick = function(e) {
				let el = e.target;
				while (el && el !== row && !el.hasAttribute('data-artist')) el = el.parentElement;
				if (el && el.hasAttribute('data-artist')) {
					const artistName = el.getAttribute('data-artist');
					navigateTo('artist', { artist: decodeURIComponent(artistName) });
				}
			};
		} else if (type === 'mix' || type === 'track') {
			row.onclick = function(e) {
				let el = e.target;
				while (el && el !== row && !el.hasAttribute('data-idx')) el = el.parentElement;
				if (el && el.hasAttribute('data-idx')) {
					const idx = parseInt(el.getAttribute('data-idx'), 10);
					const item = items[idx];
					const sourceTracks = (type === 'mix' && item && Array.isArray(item.tracks) && item.tracks.length)
						? item.tracks
						: items;
					const queue = sourceTracks.map((i) => {
						let src = i.src || i.file_path || '';
						if (src && !/^https?:/i.test(src)) {
							src = (src.indexOf('tracks/') !== -1)
								? ('/muzic2/' + src.slice(src.indexOf('tracks/')))
								: ('/muzic2/' + String(src).replace(/^\/+/, ''));
						}
						let cover = i.cover || 'tracks/covers/placeholder.jpg';
						if (cover && !cover.startsWith('/muzic2/') && !cover.startsWith('http')) {
							cover = '/muzic2/' + cover;
						}
						return {
							src: encodeURI(src),
							title: i.title,
							artist: i.artist || '',
							feats: i.feats || '',
							cover,
							video_url: i.video_url || ''
						};
					}).filter((q) => q.src);
					if (!queue.length) return;
					const startIdx = type === 'mix' ? 0 : idx;
					window.playTrack({
						...queue[startIdx],
						queue,
						queueStartIndex: startIdx
					});
				}
			};
		}

		// Add kebab menu handlers for tracks
		if (type === 'track') {
			// Add like button handlers for tracks
			row.querySelectorAll('.heart-btn').forEach((btn, idx) => {
				btn.onclick = (e) => {
					e.stopPropagation();
					const trackId = items[idx].id || idx;
					toggleTrackLike(trackId, btn);
				};
			});
			
			row.querySelectorAll('.kebab').forEach((kebab, idx) => {
				kebab.onclick = (e) => {
					e.stopPropagation();
					// Check if menu is already open for this button
					const existingMenu = document.querySelector('.context-menu');
					if (existingMenu && existingMenu.dataset.sourceButtonId === String(kebab)) {
						existingMenu.remove();
						return;
					}
					showContextMenu(e, items[idx], idx);
				};
			});
		}
	}

	function ensureStyle(href){
		if (!href) return;
		const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
		if (links.some(l => (l.getAttribute('href')||'').includes(href))) return;
		const link = document.createElement('link');
		link.rel = 'stylesheet';
		link.href = href;
		document.head.appendChild(link);
	}

	// SPA Navigation System
	window.currentPage = 'home';
	let currentParams = {};

	function navigateTo(page, params = {}) {
		// Проверяем авторизацию перед навигацией
		if (!currentUser) {
			showLoginScreen();
			return;
		}
		
		window.currentPage = page;
		currentParams = params;
		if (page !== 'playlist') {
			window.__openPlaylistState = null;
		}
		
		// Update URL without reload
		const url = new URL(window.location);
		if (page === 'home') {
			url.search = '';
		} else if (page === 'album') {
			url.search = `?album=${encodeURIComponent(params.album || '')}`;
		} else if (page === 'artist') {
			url.search = `?artist=${encodeURIComponent(params.artist || '')}`;
		}
		window.history.pushState({ page, params }, '', url);
		
		// Render page
		if (page === 'home') {
			showPage('Главная');
		} else if (page === 'album') {
			renderAlbumSPA(params.album);
		} else if (page === 'artist') {
			renderArtistSPA(params.artist);
		}
	}

	// Handle browser back/forward
	window.addEventListener('popstate', (event) => {
		if (event.state) {
			window.currentPage = event.state.page;
			currentParams = event.state.params;
			if (window.currentPage === 'home') {
				showPage('Главная');
			} else if (window.currentPage === 'album') {
				renderAlbumSPA(currentParams.album);
			} else if (window.currentPage === 'artist') {
				renderArtistSPA(currentParams.artist);
			}
		} else {
			// Handle direct URL access
			const urlParams = new URLSearchParams(window.location.search);
			if (urlParams.has('album')) {
				navigateTo('album', { album: urlParams.get('album') });
			} else if (urlParams.has('artist')) {
				navigateTo('artist', { artist: urlParams.get('artist') });
			} else {
				navigateTo('home');
			}
		}
	});

	// Initialize SPA on page load - вызывается только для авторизованных пользователей
	// Инициализация происходит в showAuthenticatedContent() и initSession()

	// Minimal SPA renderers to avoid reload only when music is playing
	async function renderAlbumSPA(albumName){
			mainContent.innerHTML = '<div class="loading">Загрузка альбома...</div>';
		try {
			// Decode the album name if it's URL encoded
			const decodedAlbumName = decodeURIComponent(albumName);
			
			const apiUrl = `/muzic2/src/api/album_windows.php?album=${encodeURIComponent(decodedAlbumName)}`;
			
			const res = await fetch(apiUrl);
			const data = await res.json();
			if (data.error) { mainContent.innerHTML = '<div class="error">Альбом не найден</div>'; return; }
			
			// Calculate album duration and track count
			const trackCount = (data.tracks || []).length;
			const totalDuration = (data.tracks || []).reduce((sum, track) => sum + (parseInt(track.duration) || 0), 0);
			const minutes = Math.floor(totalDuration / 60);
			const seconds = totalDuration % 60;
			const durationText = `${minutes} мин. ${seconds} сек.`;
			
            // Compose combined artist text with feats aggregated across tracks
            const featsSet = new Set((data.tracks||[]).flatMap(t => (t.feats? String(t.feats).split(',').map(x=>x.trim()).filter(Boolean):[])));
            const featsText = Array.from(featsSet).filter(n=>n && n.toLowerCase()!==String(data.artist||'').toLowerCase()).join(', ');
            const albumArtistCombined = featsText ? `${data.artist||''}, ${featsText}` : (data.artist||'');
			
			const albumStyles = `
				<style>
					#main-content:has(.album-page) { padding: 0; max-width: 100%; margin: 0; background: #121212; }
					.album-page { padding: 2.5rem 2rem 140px; max-width: 1400px; margin: 0 auto; }
					.album-header-section { display: flex; gap: 2rem; margin-bottom: 3rem; align-items: flex-end; }
					.album-cover-wrapper { position: relative; flex-shrink: 0; }
					.album-cover-image { width: 200px; height: 200px; border-radius: 10px; object-fit: cover; box-shadow: 0 6px 24px rgba(0, 0, 0, 0.5); background: #2a2a2a; }
					.album-info-section { flex: 1; min-width: 0; padding-bottom: 0.5rem; }
					.album-type-label { font-size: 0.8125rem; font-weight: 600; color: rgba(255, 255, 255, 0.65); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.875rem; }
					.album-main-title { font-size: 2.25rem; font-weight: 800; color: #fff; line-height: 1.15; margin: 0 0 0.625rem; letter-spacing: -0.01em; }
					.album-main-artist { font-size: 1.0625rem; color: rgba(255, 255, 255, 0.85); font-weight: 400; margin-bottom: 1.125rem; }
					.album-main-artist .artist-link { color: rgba(255, 255, 255, 0.85); text-decoration: none; transition: color 0.2s; }
					.album-main-artist .artist-link:hover { color: #fff; text-decoration: underline; }
					.album-meta-text { font-size: 0.875rem; color: rgba(255, 255, 255, 0.55); margin-bottom: 1.75rem; }
					.album-controls-row { display: flex; align-items: center; gap: 1.25rem; }
					.album-main-play { width: 52px; height: 52px; border-radius: 50%; background: #1ed760; border: none; color: #000; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; font-weight: 800; box-shadow: 0 4px 16px rgba(30, 215, 96, 0.25); line-height: 1; }
					.album-main-play:hover { background: #1db954; transform: scale(1.06); box-shadow: 0 6px 20px rgba(30, 215, 96, 0.35); }
					.album-main-play:active { transform: scale(0.98); }
					.album-main-like { width: 44px; height: 44px; border-radius: 50%; background: transparent; border: 1.5px solid rgba(255, 255, 255, 0.25); color: rgba(255, 255, 255, 0.75); font-size: 1.375rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
					.album-main-like:hover { border-color: rgba(255, 255, 255, 0.5); color: #fff; background: rgba(255, 255, 255, 0.05); }
					.album-main-like.liked { border-color: #1ed760; color: #1ed760; background: rgba(30, 215, 96, 0.12); }
					.album-main-like.liked:hover { border-color: #1db954; color: #1db954; }
					.tracks-wrapper { background: rgba(255, 255, 255, 0.02); border-radius: 10px; padding: 0.75rem; }
					.tracks-table-header { display: grid; grid-template-columns: 36px 1fr 50px 44px; gap: 1rem; padding: 0.875rem 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.08); }
					.tracks-table-header > div { font-size: 0.6875rem; font-weight: 600; color: rgba(255, 255, 255, 0.45); text-transform: uppercase; letter-spacing: 0.06em; }
					.tracks-table-header > div:first-child { text-align: center; }
					.tracks-table-header > div:nth-child(3) { text-align: right; }
					.tracks-table-header > div:last-child { text-align: center; }
					.track-item { display: grid; grid-template-columns: 36px 1fr 50px 44px; gap: 1rem; padding: 0.75rem 1rem; border-radius: 6px; cursor: pointer; transition: all 0.15s ease; position: relative; align-items: center; min-height: 60px; }
					.track-item:hover { background: rgba(255, 255, 255, 0.06); }
					.track-item.active { background: rgba(30, 215, 96, 0.12); }
					.track-item.active .track-num, .track-item.active .track-title-main { color: #1ed760; }
					.track-num { text-align: center; color: rgba(255, 255, 255, 0.45); font-size: 0.875rem; font-weight: 500; font-variant-numeric: tabular-nums; transition: opacity 0.2s; position: relative; }
					.track-item:hover .track-num { opacity: 0; }
					.track-play-button { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 30px; height: 30px; border-radius: 50%; background: #fff; border: none; color: #000; font-size: 0.75rem; display: flex; align-items: center; justify-content: center; cursor: pointer; opacity: 0; transition: all 0.2s; font-weight: 800; line-height: 1; }
					.track-item:hover .track-play-button { opacity: 1; }
					.track-play-button:hover { background: #1ed760; transform: translate(-50%, -50%) scale(1.12); }
					.track-play-button.active { opacity: 1; background: #1ed760; }
					.track-content { display: flex; flex-direction: column; gap: 0.3rem; min-width: 0; }
					.track-title-main { font-size: 0.9375rem; font-weight: 500; color: #fff; line-height: 1.4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 0.5rem; }
					.track-title-main .explicit-label { font-size: 0.625rem; background: rgba(255, 255, 255, 0.18); color: rgba(255, 255, 255, 0.65); padding: 2px 6px; border-radius: 2px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; }
					.track-artist-name { font-size: 0.8125rem; color: rgba(255, 255, 255, 0.55); line-height: 1.4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
					.track-artist-name .artist-link { color: rgba(255, 255, 255, 0.55); text-decoration: none; transition: color 0.2s; }
					.track-artist-name .artist-link:hover { color: #fff; text-decoration: underline; }
					.track-duration-text { text-align: right; color: rgba(255, 255, 255, 0.45); font-size: 0.8125rem; font-variant-numeric: tabular-nums; }
					.track-like-button { width: 36px; height: 36px; border-radius: 50%; background: transparent; border: none; color: rgba(255, 255, 255, 0.45); font-size: 1.0625rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; opacity: 0; margin: 0 auto; }
					.track-item:hover .track-like-button { opacity: 1; }
					.track-like-button:hover { color: #fff; background: rgba(255, 255, 255, 0.08); }
					.track-like-button.liked { color: #1ed760; opacity: 1; }
					.track-like-button.liked:hover { color: #1db954; background: rgba(30, 215, 96, 0.1); }
					@media (max-width: 768px) {
						.album-page { padding: 1.75rem 1.25rem 140px; }
						.album-header-section { flex-direction: column; align-items: center; text-align: center; gap: 1.5rem; margin-bottom: 2.5rem; }
						.album-cover-image { width: 180px; height: 180px; }
						.album-main-title { font-size: 1.875rem; }
						.album-main-artist { font-size: 1rem; }
						.album-controls-row { justify-content: center; }
						.tracks-table-header, .track-item { grid-template-columns: 32px 1fr 45px; }
						.track-like-button { display: none; }
					}
				</style>
			`;
			
			mainContent.innerHTML = albumStyles + `
				<div class="album-page">
					<div class="album-header-section">
						<div class="album-cover-wrapper">
							<img class="album-cover-image" loading="lazy" src="/muzic2/${data.cover || 'tracks/covers/placeholder.jpg'}" alt="cover">
						</div>
						<div class="album-info-section">
						<div class="album-type-label">${releaseTypeLabel(data.album_type || data.type)}</div>
							<h1 class="album-main-title">${escapeHtml(data.title||'')}</h1>
							<div class="album-main-artist">${renderArtistInline(escapeHtml(albumArtistCombined))}</div>
							<div class="album-meta-text">${trackCount} ${trackCount === 1 ? 'песня' : trackCount < 5 ? 'песни' : 'песен'} • ${durationText}</div>
							<div class="album-controls-row">
								<button class="album-main-play" id="album-play-btn">▶</button>
								<button class="album-main-like" id="album-like-btn" data-album-title="${escapeHtml(data.title||'')}" title="В избранные альбомы">❤</button>
							</div>
						</div>
					</div>
					<div class="tracks-wrapper">
						<div class="tracks-table-header">
							<div>#</div>
							<div>Название</div>
							<div>⏱</div>
							<div></div>
						</div>
						<div id="tracks-list"></div>
					</div>
				</div>
			`;
			const tracksList = document.getElementById('tracks-list');
            (data.tracks||[]).forEach((t,i)=>{
				const item = document.createElement('div');
				item.className = 'track-item';
				
				// Format duration from seconds to MM:SS
				const duration = parseInt(t.duration) || 0;
				const minutes = Math.floor(duration / 60);
				const seconds = duration % 60;
				const durationFormatted = `${minutes}:${seconds.toString().padStart(2, '0')}`;
				
				const likedClass = window.__likedSet && window.__likedSet.has(t.id) ? 'liked' : '';
                const combinedArtist = (t.feats && String(t.feats).trim()) ? `${t.artist||''}, ${t.feats}` : (t.artist||'');
				
				const trackId = t.id || 0;
				const isPlaying = window.isTrackPlaying && window.isTrackPlaying(trackId);
				
				if (isPlaying) {
					item.classList.add('active');
				}
				
				item.innerHTML = `
					<div class="track-num">${i+1}</div>
					<div class="track-content">
						<div class="track-title-main">
							${t.explicit ? '<span class="explicit-label">E</span>' : ''}${escapeHtml(t.title||'')}
						</div>
						<div class="track-artist-name">${renderArtistInline(combinedArtist)}</div>
					</div>
					<div class="track-duration-text">${durationFormatted}</div>
					<button class="track-like-button ${likedClass}" data-track-id="${t.id}" title="В избранное">❤</button>
				`;
				
				// Create play button
				const playBtn = document.createElement('button');
				playBtn.className = 'track-play-button' + (isPlaying ? ' active' : '');
				playBtn.innerHTML = isPlaying ? '&#10074;&#10074;' : '&#9654;';
				playBtn.setAttribute('data-track-id', trackId);
				playBtn.setAttribute('data-track-index', i);
				
				const queueData = (data.tracks||[]).map(tt=>{
					const s = tt.src || tt.file_path || '';
					if(!s) return null;
					const src = /^https?:/i.test(s) ? s : (s.indexOf('tracks/') !== -1 ? '/muzic2/' + s.slice(s.indexOf('tracks/')) : '/muzic2/' + s.replace(/^\/+/, ''));
					return { id: tt.id || 0, src: encodeURI(src), title: tt.title, artist: tt.artist, feats: tt.feats || '', cover: '/muzic2/' + (tt.cover || data.cover || 'tracks/covers/placeholder.jpg'), video_url: tt.video_url || '', explicit: tt.explicit || 0 };
				}).filter(t => t !== null);
				
				playBtn.onclick = (e) => {
					e.stopPropagation();
					if (isPlaying && window.pauseCurrentTrack) {
						window.pauseCurrentTrack();
					} else if(queueData.length) {
						window.setQueue && window.setQueue(queueData, i);
						window.playFromQueue && window.playFromQueue(i);
					}
				};
				
				// Add play button to number column
				const numDiv = item.querySelector('.track-num');
				numDiv.style.position = 'relative';
				numDiv.appendChild(playBtn);
				
				// Handle track click
				item.onclick = (e) => {
					if(e.target !== playBtn && !e.target.closest('.track-like-button') && !e.target.closest('.artist-link')) {
						if(queueData.length) {
							window.setQueue && window.setQueue(queueData, i);
							window.playFromQueue && window.playFromQueue(i);
						}
					}
				};
				
				// Make artist names clickable
				item.addEventListener('click', (e) => {
					const link = e.target && e.target.closest ? e.target.closest('.artist-link') : null;
					if (link) {
						e.preventDefault();
						e.stopPropagation();
						const name = link.getAttribute('data-artist') || '';
						try { navigateTo('artist', { artist: name }); } catch(_) {}
					}
				});
				
				tracksList.appendChild(item);
			});
			// Play button handler
			const albumPlayBtn = document.getElementById('album-play-btn');
			if (albumPlayBtn) {
				albumPlayBtn.onclick = () => {
					// Check if any track from this album is currently playing
					const albumTrackIds = (data.tracks||[]).map(t => t.id).filter(id => id);
					const isAnyAlbumTrackPlaying = window.isTrackPlaying && albumTrackIds.some(id => window.isTrackPlaying(id));
					const isPaused = window.isPlayerPaused ? window.isPlayerPaused() : true;
					
					if (isAnyAlbumTrackPlaying && !isPaused && window.pauseCurrentTrack) {
						window.pauseCurrentTrack();
					} else {
						const q = (data.tracks||[]).map(tt=>{
							const s = tt.src || tt.file_path || '';
							if(!s) return null;
							const src = /^https?:/i.test(s) ? s : (s.indexOf('tracks/') !== -1 ? '/muzic2/' + s.slice(s.indexOf('tracks/')) : '/muzic2/' + s.replace(/^\/+/, ''));
							return { id: tt.id || 0, src: encodeURI(src), title: tt.title, artist: tt.artist, feats: tt.feats || '', cover: '/muzic2/' + (tt.cover || data.cover || 'tracks/covers/placeholder.jpg'), video_url: tt.video_url || '', explicit: tt.explicit || 0 };
						}).filter(t => t !== null);
						if(q.length) {
							window.setQueue && window.setQueue(q, 0);
							window.playFromQueue && window.playFromQueue(0);
						}
					}
				};
			}
			
			// Load album likes for this album after DOM is ready
			setTimeout(() => {
				loadAlbumLikes();
			}, 100);
			
			// Update play buttons when track changes
			const updateAlbumTrackButtons = () => {
				if (!window.isTrackPlaying) return;
				const albumTrackIds = (data.tracks||[]).map(t => t.id).filter(id => id);
				const isAnyAlbumTrackPlaying = albumTrackIds.some(id => window.isTrackPlaying(id));
				const isPaused = window.isPlayerPaused ? window.isPlayerPaused() : true;
				
				// Update main album play button
				if (albumPlayBtn) {
					if (isAnyAlbumTrackPlaying && !isPaused) {
						albumPlayBtn.innerHTML = '&#10074;&#10074;';
					} else {
						albumPlayBtn.innerHTML = '▶';
					}
				}
				
				document.querySelectorAll('.track-item').forEach(item => {
					const playBtn = item.querySelector('.track-play-button');
					if (!playBtn) return;
					
					const trackId = parseInt(playBtn.getAttribute('data-track-id') || '0', 10);
					const isPlaying = window.isTrackPlaying(trackId);
					
					if (isPlaying) {
						item.classList.add('active');
						playBtn.classList.add('active');
						playBtn.innerHTML = '&#10074;&#10074;';
						playBtn.onclick = (e) => {
							e.stopPropagation();
							if (window.pauseCurrentTrack) window.pauseCurrentTrack();
						};
					} else {
						item.classList.remove('active');
						playBtn.classList.remove('active');
						playBtn.innerHTML = '&#9654;';
						const trackIndex = parseInt(playBtn.getAttribute('data-track-index') || '0', 10);
						const queueData = (data.tracks||[]).map(tt=>{
							const s = tt.src || tt.file_path || '';
							if(!s) return null;
							const src = /^https?:/i.test(s) ? s : (s.indexOf('tracks/') !== -1 ? '/muzic2/' + s.slice(s.indexOf('tracks/')) : '/muzic2/' + s.replace(/^\/+/, ''));
							return { id: tt.id || 0, src: encodeURI(src), title: tt.title, artist: tt.artist, feats: tt.feats || '', cover: '/muzic2/' + (tt.cover || data.cover || 'tracks/covers/placeholder.jpg'), video_url: tt.video_url || '', explicit: tt.explicit || 0 };
						}).filter(t => t !== null);
						playBtn.onclick = (e) => {
							e.stopPropagation();
							if(queueData.length) {
								window.setQueue && window.setQueue(queueData, trackIndex);
								window.playFromQueue && window.playFromQueue(trackIndex);
							}
						};
					}
				});
			};
			
			// Initial update
			setTimeout(updateAlbumTrackButtons, 100);
			
			document.addEventListener('track:play', () => setTimeout(updateAlbumTrackButtons, 100));
			document.addEventListener('track:pause', () => setTimeout(updateAlbumTrackButtons, 100));
			document.addEventListener('track:change', () => setTimeout(updateAlbumTrackButtons, 100));
		} catch (e) {
			mainContent.innerHTML = '<div class="error">Ошибка загрузки альбома</div>';
		}
	}

	async function renderArtistSPA(artistName){
		mainContent.innerHTML = '<div class="loading">Загрузка артиста...</div>';
		try {
		// Ensure artist.css for proper layout
		ensureStyle('/muzic2/public/assets/css/artist.css');
		
		// Add Font Awesome for icons
		if (!document.querySelector('link[href*="font-awesome"]')) {
			const fontAwesome = document.createElement('link');
			fontAwesome.rel = 'stylesheet';
			fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
			document.head.appendChild(fontAwesome);
		}
		
		// Add artist control button styles
		const artistControlStyles = document.createElement('style');
		artistControlStyles.textContent = `
			.artist-controls {
				display: flex;
				align-items: center;
				gap: 1rem;
				padding: 1.5rem 2rem;
				margin-bottom: 2rem;
			}
			.play-all-btn, .shuffle-btn, .follow-btn, .more-btn {
				background: transparent;
				border: 1px solid #535353;
				color: #fff;
				padding: 0.5rem 1rem;
				border-radius: 20px;
				cursor: pointer;
				font-size: 0.9rem;
				transition: all 0.2s ease;
			}
			.play-all-btn {
				background: #1db954;
				border-color: #1db954;
				padding: 0.75rem 2rem;
				font-size: 1rem;
				font-weight: 600;
			}
			.play-all-btn:hover {
				background: #1ed760;
				transform: scale(1.05);
			}
			.shuffle-btn:hover, .follow-btn:hover, .more-btn:hover {
				border-color: #fff;
				transform: scale(1.05);
			}
			.follow-btn {
				background: transparent;
				border-color: #535353;
			}
			.more-btn {
				background: transparent;
				border: none;
				padding: 0.5rem;
				font-size: 1.2rem;
			}
			.artist-name-large {
				color: #b3b3b3 !important;
				font-size: 3rem;
				font-weight: 900;
				margin: 0;
			}
		`;
		document.head.appendChild(artistControlStyles);
		
			// Decode the artist name if it's URL encoded
			const decodedArtistName = decodeURIComponent(artistName);
			
			const apiUrl = `/muzic2/src/api/artist_windows.php?artist=${encodeURIComponent(decodedArtistName)}`;
			
			const res = await fetch(apiUrl);
			const data = await res.json();
			if (data.error) { mainContent.innerHTML = '<div class="error">Артист не найден</div>'; return; }
			
			// DEBUG: Log full API response
			console.log('🔍 Artist API Response:', {
				name: data.name,
				promo_video: data.promo_video,
				promo_video_type: typeof data.promo_video,
				promo_video_length: data.promo_video ? data.promo_video.length : 0,
				cover: data.cover,
				fullData: JSON.stringify(data, null, 2)
			});
			
			// ВАЖНО: Проверяем, что promo_video не содержит audio.php
			if (data.promo_video && data.promo_video.includes('audio.php')) {
				console.error('❌ CRITICAL: API returned promo_video with audio.php!', data.promo_video);
				// Извлекаем реальный путь
				const match = data.promo_video.match(/audio\.php\?f=([^&]+)/);
				if (match) {
					const decoded = decodeURIComponent(match[1]);
					data.promo_video = decoded.replace(/^\/+/, '');
					console.log('✅ Fixed promo_video from API:', data.promo_video);
				}
			}
			
			// Calculate monthly listeners (random for demo)
			const monthlyListeners = Math.floor(Math.random() * 5000000) + 1000000;
			
			// Формируем путь к видео промо
			let promoVideoUrl = null;
			let finalVideoUrl = null;
			let videoType = 'video/mp4';
			let videoExt = null;
			let cleanPathForVideo = null;
			let heroVideoHTML = '';
			const promoVideoSources = [];
			let promoVideoSourcesHTML = '';
			let promoPosterDataUrl = '';
			
			if (data.promo_video && data.promo_video.trim()) {
				const promoPath = data.promo_video.trim();
				// Убираем лишние слеши в начале
				let cleanPath = promoPath.replace(/^\/+/, '');
				
				// Сохраняем cleanPath для использования в video.php прокси
				cleanPathForVideo = cleanPath;
				
				// Если путь уже содержит tracks/video, используем его напрямую
				if (cleanPath.startsWith('tracks/video') || cleanPath.startsWith('tracks/videos')) {
					// Используем прямой путь к файлу, НЕ через audio.php
					promoVideoUrl = `/muzic2/${cleanPath}`;
				} else if (promoPath.startsWith('http')) {
					promoVideoUrl = promoPath;
				} else {
					// Если путь относительный, добавляем tracks/video если нужно
					if (!cleanPath.includes('tracks/')) {
						cleanPath = `tracks/video/${cleanPath}`;
						cleanPathForVideo = cleanPath;
					}
					// Используем прямой путь к файлу
					promoVideoUrl = `/muzic2/${cleanPath}`;
				}
				
				// Убеждаемся, что это НЕ путь через audio.php - ВАЖНО!
				if (promoVideoUrl.includes('audio.php')) {
					console.error('❌ ERROR: Video path contains audio.php! Fixing...', promoVideoUrl);
					// Извлекаем реальный путь из audio.php
					const match = promoVideoUrl.match(/audio\.php\?f=([^&]+)/);
					if (match) {
						const decoded = decodeURIComponent(match[1]);
						cleanPathForVideo = decoded.replace(/^\/+/, '');
						// Убеждаемся, что путь правильный
						if (!cleanPathForVideo.startsWith('tracks/video') && !cleanPathForVideo.startsWith('tracks/videos')) {
							cleanPathForVideo = `tracks/video/${cleanPathForVideo}`;
						}
						promoVideoUrl = `/muzic2/${cleanPathForVideo}`;
						console.log('✅ Fixed video URL (removed audio.php):', promoVideoUrl);
					}
				}
				
				// Дополнительная проверка - если путь все еще содержит audio.php, используем cleanPath напрямую
				if (promoVideoUrl.includes('audio.php')) {
					console.error('❌ Still contains audio.php, using cleanPath directly');
					promoVideoUrl = `/muzic2/${cleanPathForVideo || cleanPath}`;
				}
				
				// Финальная проверка - убеждаемся, что URL правильный
				if (promoVideoUrl.includes('audio.php')) {
					console.error('❌ CRITICAL: Video URL still contains audio.php! Using fallback');
					// Используем cleanPath напрямую
					const fallbackPath = cleanPathForVideo || cleanPath || data.promo_video.trim().replace(/^\/+/, '');
					promoVideoUrl = `/muzic2/${fallbackPath}`;
				}
				
				// Определяем тип видео по расширению
				videoExt = promoPath.toLowerCase().split('.').pop();
				if (videoExt === 'webm') {
					videoType = 'video/webm';
				} else if (videoExt === 'mov') {
					videoType = 'video/quicktime'; // MOV files use QuickTime MIME type
				} else if (videoExt === 'avi') {
					videoType = 'video/x-msvideo';
				} else if (videoExt === 'mp4' || videoExt === 'm4v') {
					videoType = 'video/mp4';
				} else {
					videoType = 'video/mp4'; // Default fallback
				}
				
				console.log('🎬 Promo video found:', {
					original: data.promo_video,
					cleanPath: cleanPath,
					finalUrl: promoVideoUrl,
					type: videoType,
					ext: videoExt
				});
			} else {
				console.warn('⚠️ No promo video for artist:', data.name);
				console.log('Full data object:', JSON.stringify(data, null, 2));
			}
			
			const coverUrl = data.cover ? (data.cover.startsWith('http') ? data.cover : `/muzic2/${data.cover}`) : '/muzic2/tracks/covers/placeholder.jpg';
			
			if (promoVideoUrl) {
				const videoProxyUrl = cleanPathForVideo ? `/muzic2/public/src/api/video.php?f=${encodeURIComponent(cleanPathForVideo)}` : null;
				
				console.log('📹 Video URLs:', {
					direct: promoVideoUrl,
					proxy: videoProxyUrl,
					cleanPath: cleanPathForVideo
				});
				
				// ВАЖНО: Убеждаемся, что URL не содержит audio.php
				let resolvedVideoUrl = promoVideoUrl;
				if (resolvedVideoUrl && resolvedVideoUrl.includes('audio.php')) {
					console.error('❌ Video URL contains audio.php! Fixing...', resolvedVideoUrl);
					const match = resolvedVideoUrl.match(/audio\.php\?f=([^&]+)/);
					if (match) {
						const decoded = decodeURIComponent(match[1]);
						const fixedPath = decoded.replace(/^\/+/, '');
						// Убеждаемся, что путь правильный
						if (!fixedPath.startsWith('tracks/video') && !fixedPath.startsWith('tracks/videos')) {
							resolvedVideoUrl = `/muzic2/tracks/video/${fixedPath}`;
						} else {
							resolvedVideoUrl = `/muzic2/${fixedPath}`;
						}
						console.log('✅ Fixed promo video URL:', resolvedVideoUrl);
					} else {
						// Fallback - используем cleanPathForVideo
						const fallbackPath = cleanPathForVideo || cleanPath;
						if (fallbackPath && !fallbackPath.startsWith('tracks/video') && !fallbackPath.startsWith('tracks/videos')) {
							resolvedVideoUrl = `/muzic2/tracks/video/${fallbackPath}`;
						} else {
							resolvedVideoUrl = `/muzic2/${fallbackPath}`;
						}
					}
				}
				// Финальная проверка - убеждаемся, что finalVideoUrl не содержит audio.php
				if (resolvedVideoUrl && resolvedVideoUrl.includes('audio.php')) {
					console.error('❌ CRITICAL: finalVideoUrl still contains audio.php! Using cleanPath directly');
					resolvedVideoUrl = `/muzic2/${cleanPathForVideo || cleanPath || data.promo_video.trim().replace(/^\/+/, '')}`;
				}
				const finalProxyUrl = videoProxyUrl && !videoProxyUrl.includes('audio.php') ? videoProxyUrl : null;
				
				console.log('📹 Final video URLs (no audio.php):', {
					direct: resolvedVideoUrl,
					proxy: finalProxyUrl,
					original: promoVideoUrl
				});
				
				const addPromoVideoSource = (src, type) => {
					if (!src) return;
					const normalizedType = type || videoType || 'video/mp4';
					const last = promoVideoSources[promoVideoSources.length - 1];
					if (last && last.src === src && last.type === normalizedType) return;
					promoVideoSources.push({ src, type: normalizedType });
				};
				
				if (videoExt === 'mov') {
					addPromoVideoSource(resolvedVideoUrl, 'video/quicktime');
					if (finalProxyUrl) addPromoVideoSource(finalProxyUrl, 'video/quicktime');
					addPromoVideoSource(resolvedVideoUrl, 'video/mp4');
				} else {
					addPromoVideoSource(resolvedVideoUrl, videoType);
					if (finalProxyUrl) addPromoVideoSource(finalProxyUrl, videoType);
				}
				
				promoVideoSourcesHTML = promoVideoSources
					.map(srcObj => `<source src="${srcObj.src}" type="${srcObj.type || 'video/mp4'}">`)
					.join('\n');
				
				finalVideoUrl = resolvedVideoUrl;
				
				heroVideoHTML = `
					<div class="artist-hero-video-bg">
						<video class="artist-hero-video" autoplay muted loop playsinline preload="auto" crossorigin="anonymous">
							${promoVideoSourcesHTML}
						</video>
					</div>
				`;
			} else {
				console.error('❌ No promo video URL generated!', {
					promo_video: data.promo_video,
					hasPromoVideo: !!data.promo_video,
					data: data
				});
			}
			
			const bioText = String(data.bio || '').trim();
			mainContent.innerHTML = `
				<div class="artist-page">
					<div class="artist-hero" style="--artist-bg: url('${coverUrl}')">
						${heroVideoHTML || ''}
						<div class="artist-avatar-container">
							<div class="artist-avatar-wrapper">
								<div class="artist-avatar-ring"></div>
								<div class="artist-avatar-photo">
									<img class="artist-avatar-large" loading="lazy" src="${coverUrl}" alt="Artist Avatar" onerror="this.onerror=null;this.src='/muzic2/tracks/covers/placeholder.jpg'">
								</div>
							</div>
						</div>
						<div class="artist-info">
							<div class="artist-verified">
								<i class="fas fa-check-circle"></i>
								<span>Подтверждённый исполнитель</span>
							</div>
							<h1 class="artist-name-large">${escapeHtml(data.name||'')}</h1>
							<p class="artist-listeners">${monthlyListeners.toLocaleString('ru-RU')} слушателей за месяц</p>
							${bioText ? `<p class="artist-bio" style="max-width:760px;margin-top:.55rem;color:#d7d7d7;line-height:1.45;">${escapeHtml(bioText)}</p>` : ''}
						</div>
					</div>

					<div class="artist-controls">
						<button class="play-all-btn" id="play-all-btn">
							<i class="fas fa-play"></i>
						</button>
						<button class="shuffle-btn" id="shuffle-btn">
							<i class="fas fa-random"></i>
						</button>
						${promoVideoSourcesHTML ? `
						<button class="promo-btn" id="promo-video-btn">
							<i class="fas fa-video"></i> Смотреть промо
						</button>
						` : ''}
						<button class="follow-btn" id="follow-btn">
							Уже подписаны
						</button>
						<button class="more-btn" id="more-btn">
							<i class="fas fa-ellipsis-h"></i>
						</button>
					</div>

					<div class="popular-tracks-section">
						<h2>Популярные треки</h2>
						<div id="popular-tracks" class="tracks-list-numbered"></div>
						<button class="show-more-btn" id="show-more-tracks">Ещё</button>
					</div>

					<div class="albums-section">
						<div class="section-header">
							<h2>Альбомы</h2>
							<button class="show-all-btn">Показать все</button>
						</div>
						<div id="albums-list" class="albums-grid"></div>
					</div>

					<div class="videos-section">
						<div class="section-header">
							<h2>Видео</h2>
						</div>
						<div id="videos-list" class="albums-grid"></div>
					</div>
				</div>
			`;
			
			// Load popular tracks (show only first 10)
			const list = document.getElementById('popular-tracks'); 
			list.innerHTML='';
			const allTracks = data.top_tracks || [];
			const tracksToShow = allTracks.slice(0, 10);
			let visibleTracksCount = 10;
			
			tracksToShow.forEach((t,i)=>{ 
				const d=document.createElement('div'); 
				d.className='track-item-numbered'; 
				const likedClass = window.__likedSet && window.__likedSet.has(t.id) ? 'liked' : '';
                const combined = (t.feats && String(t.feats).trim()) ? `${t.artist}, ${t.feats}` : (t.artist||'');
				d.innerHTML=`
					<div class="track-number">${i+1}</div>
					<div class="track-play-icon" style="display: none;"><i class="fas fa-play"></i></div>
					<img class="track-cover-small" src="/muzic2/${t.cover || data.cover || 'tracks/covers/placeholder.jpg'}" alt="${escapeHtml(t.title||'')}" loading="lazy">
					<div class="track-details">
						<div class="track-title-primary">${t.explicit ? '<span class="exp-badge">E</span>' : ''}${escapeHtml(t.title||'')}</div>
					</div>
					<div class="track-duration">${Math.floor((t.duration||0)/60)}:${((t.duration||0)%60).toString().padStart(2,'0')}</div>
					<button class="track-like-btn ${likedClass}" data-track-id="${t.id}" title="В избранное">
						<i class="${likedClass ? 'fas' : 'far'} fa-heart"></i>
					</button>
					<button class="track-more-btn"><i class="fas fa-ellipsis-h"></i></button>
				`; 
				d.onclick=(e)=>{
					if(e.target.closest('.track-like-btn') || e.target.closest('.track-more-btn')) return;
					const q=(allTracks).map(tt=>{
						const s = tt.file_path || '';
						if(!s) return null;
						const src = /^https?:/i.test(s) ? s : (s.indexOf('tracks/') !== -1 ? '/muzic2/' + s.slice(s.indexOf('tracks/')) : '/muzic2/' + s.replace(/^\/+/, ''));
						return { src: encodeURI(src), title: tt.title, artist: tt.artist, feats: tt.feats || '', cover: '/muzic2/' + (tt.cover || data.cover || 'tracks/covers/placeholder.jpg'), video_url: tt.video_url || '' };
					}).filter(t => t !== null);
					if(q.length){ 
						window.setQueue && window.setQueue(q, i); 
						window.playFromQueue && window.playFromQueue(i); 
					} 
				};
				// Like button handler
				const likeBtn = d.querySelector('.track-like-btn');
				if(likeBtn) {
					likeBtn.onclick = async (e) => {
						e.stopPropagation();
						if (!window.__likedSet) window.__likedSet = new Set();
						const icon = likeBtn.querySelector('i');
						if (window.__likedSet.has(t.id)) {
							await fetch(getLikesAPI(), { method:'DELETE', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ track_id: t.id })});
							window.__likedSet.delete(t.id);
							icon.classList.remove('fas'); icon.classList.add('far');
							likeBtn.classList.remove('liked');
						} else {
							await fetch(getLikesAPI(), { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ track_id: t.id })});
							window.__likedSet.add(t.id);
							icon.classList.remove('far'); icon.classList.add('fas');
							likeBtn.classList.add('liked');
						}
					};
				}
				list.appendChild(d); 
			});
			
			// Show more button handler
			const showMoreBtn = document.getElementById('show-more-tracks');
			if(showMoreBtn && allTracks.length > 10) {
				showMoreBtn.style.display = 'block';
				showMoreBtn.onclick = () => {
					const nextBatch = allTracks.slice(visibleTracksCount, visibleTracksCount + 10);
					nextBatch.forEach((t, idx) => {
						const d=document.createElement('div'); 
						d.className='track-item-numbered'; 
						const likedClass = window.__likedSet && window.__likedSet.has(t.id) ? 'liked' : '';
						const combined = (t.feats && String(t.feats).trim()) ? `${t.artist}, ${t.feats}` : (t.artist||'');
						d.innerHTML=`
							<div class="track-number">${visibleTracksCount + idx + 1}</div>
							<div class="track-play-icon" style="display: none;"><i class="fas fa-play"></i></div>
							<img class="track-cover-small" src="/muzic2/${t.cover || data.cover || 'tracks/covers/placeholder.jpg'}" alt="${escapeHtml(t.title||'')}" loading="lazy">
							<div class="track-details">
								<div class="track-title-primary">${t.explicit ? '<span class="exp-badge">E</span>' : ''}${escapeHtml(t.title||'')}</div>
							</div>
							<div class="track-duration">${Math.floor((t.duration||0)/60)}:${((t.duration||0)%60).toString().padStart(2,'0')}</div>
							<button class="track-like-btn ${likedClass}" data-track-id="${t.id}" title="В избранное">
								<i class="${likedClass ? 'fas' : 'far'} fa-heart"></i>
							</button>
							<button class="track-more-btn"><i class="fas fa-ellipsis-h"></i></button>
						`;
						d.onclick=(e)=>{
							if(e.target.closest('.track-like-btn') || e.target.closest('.track-more-btn')) return;
							const q=(allTracks).map(tt=>{
								const s = tt.file_path || '';
								if(!s) return null;
								const src = /^https?:/i.test(s) ? s : (s.indexOf('tracks/') !== -1 ? '/muzic2/' + s.slice(s.indexOf('tracks/')) : '/muzic2/' + s.replace(/^\/+/, ''));
								return { src: encodeURI(src), title: tt.title, artist: tt.artist, feats: tt.feats || '', cover: '/muzic2/' + (tt.cover || data.cover || 'tracks/covers/placeholder.jpg'), video_url: tt.video_url || '' };
							}).filter(t => t !== null);
							if(q.length){ 
								window.setQueue && window.setQueue(q, visibleTracksCount + idx); 
								window.playFromQueue && window.playFromQueue(visibleTracksCount + idx); 
							} 
						};
						const likeBtn = d.querySelector('.track-like-btn');
						if(likeBtn) {
							likeBtn.onclick = async (e) => {
								e.stopPropagation();
								if (!window.__likedSet) window.__likedSet = new Set();
								const icon = likeBtn.querySelector('i');
								if (window.__likedSet.has(t.id)) {
									await fetch(getLikesAPI(), { method:'DELETE', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ track_id: t.id })});
									window.__likedSet.delete(t.id);
									icon.classList.remove('fas'); icon.classList.add('far');
									likeBtn.classList.remove('liked');
								} else {
									await fetch(getLikesAPI(), { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ track_id: t.id })});
									window.__likedSet.add(t.id);
									icon.classList.remove('far'); icon.classList.add('fas');
									likeBtn.classList.add('liked');
								}
							};
						}
						list.appendChild(d);
					});
					visibleTracksCount += nextBatch.length;
					if(visibleTracksCount >= allTracks.length) {
						showMoreBtn.style.display = 'none';
					} else {
						const remaining = allTracks.length - visibleTracksCount;
						showMoreBtn.textContent = `Показать ещё ${remaining} трек${remaining % 10 === 1 && remaining % 100 !== 11 ? '' : [2,3,4].includes(remaining % 10) && ![12,13,14].includes(remaining % 100) ? 'а' : 'ов'}`;
					}
				};
				const remaining = allTracks.length - visibleTracksCount;
				showMoreBtn.textContent = `Показать ещё ${remaining} трек${remaining % 10 === 1 && remaining % 100 !== 11 ? '' : [2,3,4].includes(remaining % 10) && ![12,13,14].includes(remaining % 100) ? 'а' : 'ов'}`;
			} else if(showMoreBtn) {
				showMoreBtn.style.display = 'none';
			}
			
			// Ensure hero background video autoplays
			const heroVideoEl = document.querySelector('.artist-hero-video');
			if (heroVideoEl) {
				const tryPlayHeroVideo = () => {
					heroVideoEl.play().catch(err => {
						console.warn('⚠️ Hero video autoplay prevented:', err);
						document.addEventListener('click', () => heroVideoEl.play().catch(() => {}), { once: true });
					});
				};
				heroVideoEl.addEventListener('loadeddata', tryPlayHeroVideo, { once: true });
				heroVideoEl.load();
				tryPlayHeroVideo();
			}
			
			if (promoVideoSources.length) {
				const captureHeroPoster = () => {
					if (!heroVideoEl || promoPosterDataUrl) return;
					try {
						const vw = heroVideoEl.videoWidth || 0;
						const vh = heroVideoEl.videoHeight || 0;
						if (!vw || !vh) return;
						const canvas = document.createElement('canvas');
						canvas.width = vw;
						canvas.height = vh;
						const ctx = canvas.getContext('2d');
						ctx.drawImage(heroVideoEl, 0, 0, vw, vh);
						promoPosterDataUrl = canvas.toDataURL('image/jpeg', 0.85);
					} catch (err) {
						console.warn('⚠️ Не удалось сохранить первый кадр promo видео', err);
					}
				};
				if (heroVideoEl) {
					if (heroVideoEl.readyState >= 2) {
						captureHeroPoster();
					} else {
						heroVideoEl.addEventListener('loadeddata', captureHeroPoster, { once: true });
					}
				}
				const promoBtn = document.getElementById('promo-video-btn');
				if (promoBtn) {
					promoBtn.addEventListener('click', async () => {
						if (!promoPosterDataUrl) {
							try { captureHeroPoster(); } catch(_) {}
						}
						const payload = {
							title: data.name ? `${data.name} — Промо` : 'Видео-промо',
							artist: data.name || '',
							sources: promoVideoSources,
							poster: promoPosterDataUrl || coverUrl,
							cover: coverUrl,
							background: coverUrl,
							promo_track: data.promo_track || null
						};
						if (typeof window.openArtistPromoFromSPA === 'function') {
							window.openArtistPromoFromSPA(payload);
						} else {
							console.warn('⚠️ Promo overlay entry point is not available');
						}
					});
				}
			}
			
			// Play all button
			
			document.getElementById('play-all-btn').onclick=()=>{ 
				const q=(data.top_tracks||[]).map(tt=>{
					const s = tt.file_path || '';
					if(!s) return null;
					const src = /^https?:/i.test(s) ? s : (s.indexOf('tracks/') !== -1 ? '/muzic2/' + s.slice(s.indexOf('tracks/')) : '/muzic2/' + s.replace(/^\/+/, ''));
					return { src: encodeURI(src), title: tt.title, artist: tt.artist, feats: tt.feats || '', cover: '/muzic2/' + (tt.cover || data.cover || 'tracks/covers/placeholder.jpg'), video_url: tt.video_url || '' };
				}).filter(t => t !== null);
				if(q.length){ 
					window.setQueue && window.setQueue(q, 0); 
					window.playFromQueue && window.playFromQueue(0); 
				} 
			};
			
			// Load artist albums
			loadArtistAlbums(artistName);
			
			// Load videos
			loadArtistVideos(artistName);
			
			// Load album likes after DOM is ready
			setTimeout(() => {
				loadAlbumLikes();
			}, 100);
		} catch (e) {
			mainContent.innerHTML = '<div class="error">Ошибка загрузки артиста</div>';
		}
	}

	// Load artist videos
	async function loadArtistVideos(artistName) {
		try {
			const videosList = document.getElementById('videos-list');
			if (!videosList) return;
			
			videosList.innerHTML = '<div class="loading">Загрузка...</div>';
			
			const apiUrl = `/muzic2/public/src/api/videos.php?artist=${encodeURIComponent(artistName)}`;
			
			const res = await fetch(apiUrl);
			const json = await res.json();
			const items = (json && json.success && Array.isArray(json.data)) ? json.data : [];
			
			if (!items.length) {
				videosList.innerHTML = '<div style="color:#9aa0a6;padding:8px 0;">Видео не найдены</div>';
				return;
			}
			
			videosList.innerHTML = '';
			items.forEach(v => {
				const card = document.createElement('div');
				card.className = 'video-card';
				const coverPath = v.cover ? (String(v.cover).startsWith('http') ? v.cover : `/muzic2/${String(v.cover).replace(/^\/+/, '')}`) : '/muzic2/tracks/covers/placeholder.jpg';
				const videoSrc = v.src && v.src.startsWith('http') ? v.src : `${v.src}`;
				const videoExt = (videoSrc.split('.').pop() || '').toLowerCase();
				const videoType = videoExt === 'mov' ? 'video/quicktime'
					: videoExt === 'webm' ? 'video/webm'
					: videoExt === 'avi' ? 'video/x-msvideo'
					: 'video/mp4';
				const titleText = v.title || v.track_title || 'Видео';
				const artistText = v.artist || artistName || 'Видео';
				card.innerHTML = `
					<div class="video-thumb">
						<div class="video-thumb-preview" style="background-image:url('${coverPath}')"></div>
						<video preload="metadata" playsinline muted>
							<source src="${videoSrc}" type="${videoType}">
						</video>
						<button class="video-play-overlay" title="Смотреть видео">
							<i class="fas fa-play"></i>
						</button>
					</div>
					<div class="video-title">${escapeHtml(titleText)}</div>
					<div class="video-info">${escapeHtml(artistText)}</div>
				`;
				const videoEl = card.querySelector('video');
				const previewEl = card.querySelector('.video-thumb-preview');
				if (videoEl && previewEl) {
					const captureFrame = () => {
						try {
							const canvas = document.createElement('canvas');
							canvas.width = videoEl.videoWidth || 640;
							canvas.height = videoEl.videoHeight || 360;
							const ctx = canvas.getContext('2d');
							ctx.drawImage(videoEl, 0, 0, canvas.width, canvas.height);
							const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
							previewEl.style.backgroundImage = `url('${dataUrl}')`;
						} catch (err) {
							console.warn('Не удалось получить первый кадр видео', err);
						} finally {
							try { videoEl.pause(); videoEl.remove(); } catch (_) {}
						}
					};
					if (videoEl.readyState >= 2) {
						captureFrame();
					} else {
						videoEl.addEventListener('loadeddata', captureFrame, { once: true });
					}
				}
				const handlePlay = (e) => {
					e.stopPropagation();
					if (window.playTrack) {
						window.playTrack({
							src: videoSrc,
							title: titleText,
							artist: artistText,
							cover: coverPath,
							duration: v.duration || 0,
							video_url: videoSrc
						});
					}
				};
				card.addEventListener('click', handlePlay);
				const overlayBtn = card.querySelector('.video-play-overlay');
				if (overlayBtn) {
					overlayBtn.addEventListener('click', handlePlay);
				}
				videosList.appendChild(card);
			});
		} catch (e) {
			const videosList = document.getElementById('videos-list');
			if (videosList) {
				videosList.innerHTML = '<div class="error">Ошибка загрузки видео</div>';
			}
			console.error('Error loading videos:', e);
		}
	}

	// Load artist albums
	async function loadArtistAlbums(artistName) {
		try {
			const albumsList = document.getElementById('albums-list');
			if (!albumsList) return;
			
			const apiUrl = `/muzic2/src/api/artist_windows.php?artist=${encodeURIComponent(artistName)}`;
				const res = await fetch(apiUrl);
				const data = await res.json();
				
				if (data.albums && data.albums.length > 0) {
					albumsList.innerHTML = '';
					data.albums.slice(0, 6).forEach(album => {
						const albumDiv = document.createElement('div');
						albumDiv.className = 'album-card';
						const isLiked = window.__likedAlbums && window.__likedAlbums.has(album.album || album.title || '');
						const albumTypeValue = album.type || album.album_type || '';
						const albumType = albumTypeValue === 'album' ? 'Альбом' : 
						                 albumTypeValue === 'ep' ? 'EP' : 
						                 albumTypeValue === 'single' ? 'Сингл' : 'Сингл';
						const trackCount = album.track_count || 0;
						albumDiv.innerHTML = `
							<button class="album-heart-btn ${isLiked ? 'liked' : ''}" data-album-title="${escapeHtml(album.album || album.title || '')}" title="В избранные альбомы">
								<i class="fas fa-heart"></i>
							</button>
							<img class="album-cover" loading="lazy" src="/muzic2/${album.cover || 'tracks/covers/placeholder.jpg'}" alt="album cover">
							<div class="album-title">${escapeHtml(album.album || album.title || '')}</div>
							<div class="album-artist">${escapeHtml(artistName)}</div>
							<div class="album-info">${albumType} • ${trackCount} трек${trackCount % 10 === 1 && trackCount % 100 !== 11 ? '' : [2,3,4].includes(trackCount % 10) && ![12,13,14].includes(trackCount % 100) ? 'а' : 'ов'}</div>
							<button class="album-play-btn"><i class="fas fa-play"></i></button>
						`;
						const likeBtn = albumDiv.querySelector('.album-heart-btn');
						if (likeBtn) {
							likeBtn.onclick = async (e) => {
								e.stopPropagation();
								if (!window.__likedAlbums) window.__likedAlbums = new Set();
								const albumTitle = album.album || album.title || '';
								if (window.__likedAlbums.has(albumTitle)) {
									await fetch(getLikesAPI(), { method:'DELETE', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ album_title: albumTitle })});
									window.__likedAlbums.delete(albumTitle);
									likeBtn.classList.remove('liked');
								} else {
									await fetch(getLikesAPI(), { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ album_title: albumTitle })});
									window.__likedAlbums.add(albumTitle);
									likeBtn.classList.add('liked');
								}
							};
						}
						const playBtn = albumDiv.querySelector('.album-play-btn');
						if (playBtn) {
							playBtn.onclick = (e) => {
								e.stopPropagation();
								navigateTo('album', { album: album.album || album.title });
							};
						}
						albumDiv.onclick = (e) => {
							if (!e.target.closest('.album-play-btn') && !e.target.closest('.album-heart-btn')) {
								navigateTo('album', { album: album.album || album.title });
							}
						};
						albumsList.appendChild(albumDiv);
					});
				}
		} catch (e) {
			console.error('Error loading artist albums:', e);
		}
	}

	// Load album likes
	async function loadAlbumLikes() {
		// Загрузка лайков альбомов
		try {
			const data = await fetchLikesLiteCached(false);
			syncLikesFromServerPayload(data);
			
			// Update album heart buttons on artist pages
			document.querySelectorAll('.album-heart-btn').forEach(btn => {
				const albumTitle = btn.getAttribute('data-album-title');
				if (window.__likedAlbums && window.__likedAlbums.has(albumTitle)) {
					btn.classList.add('liked');
				} else {
					btn.classList.remove('liked');
				}
			});
			
			// Update album like button on album page
			const albumLikeBtn = document.getElementById('album-like-btn');
			if (albumLikeBtn) {
				const albumTitle = albumLikeBtn.getAttribute('data-album-title');
				if (window.__likedAlbums && window.__likedAlbums.has(albumTitle)) {
					albumLikeBtn.classList.add('liked');
				} else {
					albumLikeBtn.classList.remove('liked');
				}
			}
			
			// Update "My Music" section if it's currently displayed
			if (window.currentPage === 'home' && document.getElementById('albums-row')) {
				updateMyMusicAlbums();
			}
		} catch (e) {
			window.__likedAlbums = new Set();
		}
	}
	
	// Update albums in "My Music" section
	async function updateMyMusicAlbums() {
		const albumsRow = document.getElementById('albums-row');
		if (!albumsRow) return;
		
		try {
			const likesData = await fetchLikesLiteCached(false);
			const likedAlbums = likesData.albums || [];
			
			if (likedAlbums.length === 0) {
				albumsRow.innerHTML = '<div class="empty">Пока нет любимых альбомов</div>';
				return;
			}
			
			// Get all albums from dedicated API
			const allAlbumsRes = await fetch(api('/muzic2/src/api/all_albums.php'));
			const allAlbumsData = await allAlbumsRes.json();
			const allAlbums = allAlbumsData.albums || [];
			
			// Match liked albums with full album data
			const matchedAlbums = await Promise.all(likedAlbums.map(async (likedAlbum) => {
				// Try exact match first
				let matched = allAlbums.find(album => 
					album.album && album.album.toLowerCase() === likedAlbum.album_title.toLowerCase()
				);
				
				// If no exact match, try partial match
				if (!matched) {
					matched = allAlbums.find(album => 
						album.album && album.album.toLowerCase().includes(likedAlbum.album_title.toLowerCase())
					);
				}
				
				// If still no match, try reverse partial match
				if (!matched) {
					matched = allAlbums.find(album => 
						album.album && likedAlbum.album_title.toLowerCase().includes(album.album.toLowerCase())
					);
				}
				
				// If still no match, try to find by searching tracks
				if (!matched) {
					try {
						// Используем быстрый API для Windows
						const searchApiUrl = `/muzic2/src/api/search_windows.php?q=${encodeURIComponent(likedAlbum.album_title)}&type=albums`;
						
						const searchRes = await fetch(searchApiUrl);
						const searchData = await searchRes.json();
						if (searchData.albums && searchData.albums.length > 0) {
							matched = searchData.albums[0];
						}
					} catch (e) {
						// Ignore search errors
					}
				}
				
				return matched ? {
					title: matched.album || matched.title,
					artist: matched.artist,
					cover: matched.cover
				} : {
					title: likedAlbum.album_title,
					artist: 'Неизвестный артист',
					cover: 'tracks/covers/placeholder.jpg'
				};
			}));
			
			albumsRow.innerHTML = matchedAlbums.map(album => createAlbumCard(album)).join('');
		} catch (e) {
			// Silent fail
		}
	}

	// Search functionality
	async function renderSearch() {
		// Проверяем авторизацию
		if (!currentUser) {
			showLoginScreen();
			return;
		}
		
		mainContent.innerHTML = `
			<div class="search-container">
				<div class="search-header">
					<div class="search-input-container">
						<input type="text" id="search-input" placeholder="Поиск музыки, артистов, альбомов..." autocomplete="off">
						<button id="search-btn" class="search-btn">🔍</button>
					</div>
					<div class="search-filters">
						<button class="search-filter-btn active" data-type="all">Все</button>
						<button class="search-filter-btn" data-type="tracks">Треки</button>
						<button class="search-filter-btn" data-type="artists">Артисты</button>
						<button class="search-filter-btn" data-type="albums">Альбомы</button>
					</div>
				</div>
				<div id="search-results" class="search-results">
					<div class="search-placeholder">
						<h3>Начните поиск</h3>
						<p>Введите название трека, артиста или альбома</p>
					</div>
				</div>
			</div>
		`;

		// Add search styles
		const searchStyles = document.createElement('style');
		searchStyles.textContent = `
			.search-container { padding: 2rem; max-width: 1200px; margin: 0 auto; }
			.search-header { margin-bottom: 2rem; }
			.search-input-container { position: relative; margin-bottom: 1.5rem; }
			#search-input { 
				width: 100%; 
				padding: 1rem 3rem 1rem 1rem; 
				font-size: 1.1rem; 
				border: 2px solid #333; 
				border-radius: 25px; 
				background: #1a1a1a; 
				color: #fff; 
				outline: none;
				transition: border-color 0.3s;
			}
			#search-input:focus { border-color: #1db954; }
			.search-btn { 
				position: absolute; 
				right: 0.5rem; 
				top: 50%; 
				transform: translateY(-50%); 
				background: #1db954; 
				border: none; 
				border-radius: 50%; 
				width: 40px; 
				height: 40px; 
				cursor: pointer; 
				font-size: 1.2rem;
			}
			.search-filters { display: flex; gap: 1rem; flex-wrap: wrap; }
			.search-filter-btn { 
				padding: 0.5rem 1rem; 
				background: #333; 
				border: none; 
				border-radius: 20px; 
				color: #fff; 
				cursor: pointer; 
				transition: all 0.3s;
			}
			.search-filter-btn.active, .search-filter-btn:hover { background: #1db954; }
			.search-results { min-height: 400px; }
			.search-placeholder { 
				text-align: center; 
				padding: 4rem 2rem; 
				color: #666; 
			}
			.search-placeholder h3 { font-size: 1.5rem; margin-bottom: 0.5rem; }
			.search-section { margin-bottom: 2rem; }
			.search-section h4 { 
				font-size: 1.3rem; 
				margin-bottom: 1rem; 
				color: #fff; 
				border-bottom: 2px solid #333; 
				padding-bottom: 0.5rem; 
			}
			.search-loading { text-align: center; padding: 2rem; color: #666; }
			.search-error { text-align: center; padding: 2rem; color: #ff6b6b; }
			.no-results { text-align: center; padding: 2rem; color: #666; }
			.search-tracks-list { display: flex; flex-direction: column; gap: 0.75rem; }
			.search-track-card { position: relative; display: flex; align-items: center; gap: 1rem; padding: 0.9rem 1.1rem; border-radius: 18px; background: rgba(22, 22, 22, 0.92); border: 1px solid rgba(255, 255, 255, 0.04); transition: transform 0.25s ease, background 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease; }
			.search-track-card:hover { transform: translateY(-2px); background: rgba(30, 30, 30, 0.95); border-color: rgba(29, 185, 84, 0.35); box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35); }
			.search-track-cover-wrap { width: 68px; height: 68px; border-radius: 16px; overflow: hidden; flex-shrink: 0; cursor: default; position: relative; }
			.search-track-cover { width: 100%; height: 100%; object-fit: cover; display: block; }
			.search-track-meta { flex: 1; display: flex; flex-direction: column; gap: 0.25rem; cursor: default; }
			.search-track-title { display: flex; align-items: center; gap: 0.4rem; font-size: 1.05rem; font-weight: 600; color: #fff; }
			.search-track-title .exp-badge { position: relative; top: -1px; }
			.search-track-artist { color: #b3b3b3; font-size: 0.9rem; }
			.search-track-album { color: #6f6f6f; font-size: 0.82rem; }
			.search-track-actions { display: flex; align-items: center; gap: 0.6rem; margin-left: 1rem; }
			.search-track-play { width: 44px; height: 44px; border-radius: 50%; border: none; background: #1db954; color: #000; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: transform 0.2s ease, box-shadow 0.2s ease; font-size: 16px; }
			.search-track-play:hover { transform: scale(1.05); box-shadow: 0 8px 20px rgba(29, 185, 84, 0.35); }
			.search-track-play.playing { background: #1db954; }
			.search-track-play.playing:hover { background: #1ed760; }
			.search-track-card .heart-btn { position: static !important; width: 44px; height: 44px; border-radius: 50%; background: #202020; border: 1px solid #2d2d2d; color: #b5b5b5; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: all 0.25s ease; }
			.search-track-card .heart-btn:hover { color: #ffffff; border-color: #3a3a3a; }
			.search-track-card .heart-btn.liked { background: #1db954; border-color: #1db954; color: #000; }
			.search-track-card .heart-btn.liked:hover { color: #000; }
		`;
		document.head.appendChild(searchStyles);

		// Setup event listeners
		const searchInput = document.getElementById('search-input');
		const searchBtn = document.getElementById('search-btn');
		const searchResults = document.getElementById('search-results');
		const filterBtns = document.querySelectorAll('.search-filter-btn');
		
		// Listen for track play/pause/change events to update buttons
		let updateButtonsTimeout = null;
		document.addEventListener('track:play', () => {
			if (updateButtonsTimeout) clearTimeout(updateButtonsTimeout);
			updateButtonsTimeout = setTimeout(updateSearchTrackButtons, 100);
		});
		document.addEventListener('track:pause', () => {
			setTimeout(updateSearchTrackButtons, 100);
		});
		document.addEventListener('track:change', () => {
			setTimeout(updateSearchTrackButtons, 100);
		});

		let currentType = 'all';
		let searchTimeout;


		// Search button handler
		searchBtn.addEventListener('click', () => {
			const query = searchInput.value.trim();
			if (query.length >= 2) {
				performSearch(query, currentType);
			}
		});

		// Filter buttons handler
		filterBtns.forEach(btn => {
			btn.addEventListener('click', () => {
				filterBtns.forEach(b => b.classList.remove('active'));
				btn.classList.add('active');
				currentType = btn.dataset.type;
				
				const query = searchInput.value.trim();
				if (query.length >= 2) {
					performSearch(query, currentType);
				}
			});
		});

		// Enter key handler
		searchInput.addEventListener('keypress', (e) => {
			if (e.key === 'Enter') {
				const query = searchInput.value.trim();
				if (query.length >= 2) {
					performSearch(query, currentType);
				}
			}
		});

		async function performSearch(query, type) {
			searchResults.innerHTML = '<div class="search-loading">Поиск...</div>';
			
			try {
				// Используем быстрый API для Windows
				const apiUrl = `/muzic2/src/api/search_windows.php?q=${encodeURIComponent(query)}&type=${type}`;
				
				const response = await fetch(apiUrl);
				const data = await response.json();
				
				if (data.error) {
					searchResults.innerHTML = `<div class="search-error">Ошибка: ${data.error}</div>`;
					return;
				}
				
				displaySearchResults(data, query);
			} catch (error) {
				searchResults.innerHTML = '<div class="search-error">Ошибка при поиске</div>';
			}
		}

		function displaySearchResults(data, query) {
			let html = '';
			
			if (currentType === 'all') {
				// Show all results grouped by type
				if (data.tracks.length > 0) {
					html += '<div class="search-section"><h4>Треки</h4><div class="search-tracks-list">';
					html += data.tracks.map(track => createTrackCard(track)).join('');
					html += '</div></div>';
				}
				
				if (data.artists.length > 0) {
					html += '<div class="search-section"><h4>Артисты</h4><div class="artist-row search-artists-row">';
					html += data.artists.map(artist => createArtistCard(artist)).join('');
					html += '</div></div>';
				}
				
				if (data.albums.length > 0) {
					html += '<div class="search-section"><h4>Альбомы</h4><div class="tile-row search-albums-row">';
					html += data.albums.map(album => createAlbumCard(album)).join('');
					html += '</div></div>';
				}
			} else {
				// Show specific type results
				const results = data[currentType] || [];
				if (results.length > 0) {
					if (currentType === 'tracks') {
						html = '<div class="search-tracks-list">' + results.map(item => createTrackCard(item)).join('') + '</div>';
					} else if (currentType === 'artists') {
						html = '<div class="artist-row search-artists-row">' + results.map(item => createArtistCard(item)).join('') + '</div>';
					} else if (currentType === 'albums') {
						html = '<div class="tile-row search-albums-row">' + results.map(item => createAlbumCard(item)).join('') + '</div>';
					}
				}
			}
			
			if (!html) {
				html = '<div class="no-results">Ничего не найдено</div>';
			}
			
			searchResults.innerHTML = html;
			
			// Update play buttons after rendering
			updateSearchTrackButtons();
		}

		// Search input handler
		searchInput.addEventListener('input', (e) => {
			clearTimeout(searchTimeout);
			const query = e.target.value.trim();
			
			if (query.length < 2) {
				showSearchPlaceholder();
				return;
			}

			searchTimeout = setTimeout(() => {
				performSearch(query, currentType);
			}, 300);
		});

		// Search button handler
		searchBtn.addEventListener('click', () => {
			const query = searchInput.value.trim();
			if (query.length >= 2) {
				performSearch(query, currentType);
			}
		});

		// Filter buttons handler
		filterBtns.forEach(btn => {
			btn.addEventListener('click', () => {
				filterBtns.forEach(b => b.classList.remove('active'));
				btn.classList.add('active');
				currentType = btn.dataset.type;
				
				const query = searchInput.value.trim();
				if (query.length >= 2) {
					performSearch(query, currentType);
				}
			});
		});

		// Enter key handler
		searchInput.addEventListener('keypress', (e) => {
			if (e.key === 'Enter') {
				const query = searchInput.value.trim();
				if (query.length >= 2) {
					performSearch(query, currentType);
				}
			}
		});
		
		// Search input handler
		searchInput.addEventListener('input', (e) => {
			clearTimeout(searchTimeout);
			const query = e.target.value.trim();
			
			if (query.length < 2) {
				showSearchPlaceholder();
				return;
			}

			searchTimeout = setTimeout(() => {
				performSearch(query, currentType);
			}, 300);
		});
		
		function showSearchPlaceholder() {
			searchResults.innerHTML = `
				<div class="search-placeholder">
					<h3>Начните поиск</h3>
					<p>Введите название трека, артиста или альбома</p>
				</div>
			`;
		}

		async function performSearch(query, type) {
			searchResults.innerHTML = '<div class="search-loading">Поиск...</div>';
			
			try {
				// Используем быстрый API для Windows
				const apiUrl = `/muzic2/src/api/search_windows.php?q=${encodeURIComponent(query)}&type=${type}`;
				
				const response = await fetch(apiUrl);
				const data = await response.json();
				
				if (data.error) {
					searchResults.innerHTML = `<div class="search-error">Ошибка: ${data.error}</div>`;
					return;
				}
				
				displaySearchResults(data, query);
			} catch (error) {
				searchResults.innerHTML = '<div class="search-error">Ошибка при поиске</div>';
			}
		}

		function displaySearchResults(data, query) {
			let html = '';
			
			if (currentType === 'all') {
				// Show all results grouped by type
				if (data.tracks.length > 0) {
					html += '<div class="search-section"><h4>Треки</h4><div class="search-tracks-list">';
					html += data.tracks.map(track => createTrackCard(track)).join('');
					html += '</div></div>';
				}
				
				if (data.artists.length > 0) {
					html += '<div class="search-section"><h4>Артисты</h4><div class="artist-row search-artists-row">';
					html += data.artists.map(artist => createArtistCard(artist)).join('');
					html += '</div></div>';
				}
				
				if (data.albums.length > 0) {
					html += '<div class="search-section"><h4>Альбомы</h4><div class="tile-row search-albums-row">';
					html += data.albums.map(album => createAlbumCard(album)).join('');
					html += '</div></div>';
				}
			} else {
				// Show specific type results
				const results = data[currentType] || [];
				if (results.length > 0) {
					if (currentType === 'tracks') {
						html = '<div class="search-tracks-list">' + results.map(item => createTrackCard(item)).join('') + '</div>';
					} else if (currentType === 'artists') {
						html = '<div class="artist-row search-artists-row">' + results.map(item => createArtistCard(item)).join('') + '</div>';
					} else if (currentType === 'albums') {
						html = '<div class="tile-row search-albums-row">' + results.map(item => createAlbumCard(item)).join('') + '</div>';
					}
				}
			}
			
			if (!html) {
				html = '<div class="no-results">Ничего не найдено</div>';
			}
			
			searchResults.innerHTML = html;
			
			// Update play buttons after rendering
			updateSearchTrackButtons();
		}

		// Function to update play/pause buttons on search track cards
		function updateSearchTrackButtons() {
			if (!window.isTrackPlaying) return;
			const cards = document.querySelectorAll('.search-track-card');
			cards.forEach(card => {
				const trackId = parseInt(card.getAttribute('data-track-id') || '0', 10);
				const playBtn = card.querySelector('.search-track-play');
				if (!playBtn) return;
				
				const isPlaying = window.isTrackPlaying(trackId);
				
				if (isPlaying) {
					playBtn.classList.add('playing');
					playBtn.innerHTML = '&#10074;&#10074;';
					playBtn.setAttribute('aria-label', 'Остановить трек');
					playBtn.onclick = (e) => {
						e.stopPropagation();
						e.preventDefault();
						try { if(window.pauseCurrentTrack) window.pauseCurrentTrack(); } catch(err){ console.error('pause error', err); }
					};
				} else {
					playBtn.classList.remove('playing');
					playBtn.innerHTML = '&#9654;';
					playBtn.setAttribute('aria-label', 'Слушать трек');
					// Restore original play action from data attribute
					const playAction = playBtn.getAttribute('data-play-action');
					if (playAction) {
						try {
							// Безопасное выполнение функции - оборачиваем в функцию, которая не выполняется сразу
							playBtn.onclick = (e) => {
								e.stopPropagation();
								e.preventDefault();
								try {
							const func = new Function('return ' + playAction)();
									if (typeof func === 'function') {
										func();
									}
								} catch(err) {
									console.error('Error executing play action:', err);
								}
							};
						} catch(e) {
							console.error('Error restoring play action:', e);
						}
					}
				}
			});
		}

		function createTrackCard(track) {
			const likedClass = window.__likedSet && window.__likedSet.has(track.id) ? 'liked' : '';
			const coverPath = '/muzic2/' + String(track.cover || 'tracks/covers/placeholder.jpg').replace(/^\/+/, '');
			const trackId = track.id || 0;
			const isPlaying = window.isTrackPlaying && window.isTrackPlaying(trackId);
			
			// Безопасное создание playAction с использованием JSON.stringify
			const trackData = {
				src: track.src || track.file_path || '',
				title: track.title || '',
				artist: track.artist || '',
				cover: coverPath,
				id: trackId,
				video_url: track.video_url || '',
				explicit: track.explicit ? 1 : 0
			};
			const playActionData = JSON.stringify(trackData);
			const playAction = `(()=>{ try{ const d=${playActionData}; if(window.playTrack) window.playTrack(d); }catch(e){ console.error('search play error', e); } })()`;
			const playPauseAction = isPlaying 
				? `(()=>{ try{ if(window.pauseCurrentTrack) window.pauseCurrentTrack(); }catch(e){ console.error('pause error', e); } })()`
				: playAction;
			const playIcon = isPlaying ? '&#10074;&#10074;' : '&#9654;';
			const playClass = isPlaying ? 'search-track-play playing' : 'search-track-play';
			const artistLine = track.feats && String(track.feats).trim()
				? `${track.artist}, ${track.feats}`
				: track.artist;
			const albumLine = track.album ? `<div class="search-track-album">${escapeHtml(track.album)}</div>` : '';
			const escapedPlayPauseAction = playPauseAction.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
			return `
				<div class="search-track-card" data-track-id="${trackId}" onclick="event.stopPropagation();">
					<div class="search-track-cover-wrap" onclick="event.stopPropagation();">
						<img class="search-track-cover" loading="lazy" decoding="async" src="${escapeHtml(coverPath)}" alt="${escapeHtml(track.title || 'cover')}" onerror="this.onerror=null;this.src='/muzic2/tracks/covers/placeholder.jpg'">
					</div>
					<div class="search-track-meta" onclick="event.stopPropagation();">
						<div class="search-track-title">
							${escapeHtml(track.title)}
							${track.explicit ? ' <span class="exp-badge" title="Нецензурная лексика">E</span>' : ''}
						</div>
						<div class="search-track-artist">${renderArtistInline(artistLine)}</div>
						${albumLine}
					</div>
					<div class="search-track-actions" onclick="event.stopPropagation();">
						<button type="button" class="${playClass}" onclick="event.stopPropagation(); event.preventDefault(); ${escapedPlayPauseAction}" data-play-action="${playAction.replace(/"/g, '&quot;').replace(/'/g, '&#39;')}" aria-label="${isPlaying ? 'Остановить трек' : 'Слушать трек'}">${playIcon}</button>
						<button type="button" class="heart-btn ${likedClass}" data-track-id="${track.id}" title="В избранное" onclick="event.stopPropagation();">❤</button>
					</div>
				</div>
			`;
		}

		function createArtistCard(artist) {
			const name = artist && (artist.name || artist.artist) ? String(artist.name || artist.artist) : '';
			const cover = artist && artist.cover ? String(artist.cover) : 'tracks/covers/placeholder.jpg';
			const trackCount = Number(artist && artist.track_count != null ? artist.track_count : 0) || 0;
			const coverSrc = /^https?:\/\//i.test(cover) || cover.startsWith('/muzic2/') ? cover : ('/muzic2/' + cover.replace(/^\/+/, ''));
			return `
				<div class="artist-tile" onclick="navigateTo('artist', { artist: '${encodeURIComponent(name)}' })">
					<img class="artist-avatar" loading="lazy" src="${coverSrc}" alt="avatar" onerror="this.onerror=null;this.src='/muzic2/tracks/covers/placeholder.jpg'">
					<div class="artist-name">${renderArtistInline(name)}</div>
					<div class="artist-tracks">${trackCount} треков</div>
				</div>
			`;
		}


		function showSearchPlaceholder() {
			searchResults.innerHTML = `
				<div class="search-placeholder">
					<h3>Начните поиск</h3>
					<p>Введите название трека, артиста или альбома</p>
				</div>
			`;
		}
	}

	function renderContinueListening() {
		try {
			const raw = localStorage.getItem('muzic2_recent_listening_v1') || '[]';
			let items = [];
			try { items = JSON.parse(raw) || []; } catch(_) { items = []; }
			items = items.filter(x => x && x.src).slice(0, 8);
			if (!items.length) return;
			const sec = document.createElement('section');
			sec.className = 'continue-listening-section';
			sec.innerHTML = '<h3>Продолжить слушать</h3><div class="continue-grid" id="continue-grid"></div>';
			const anchor = document.querySelector('.home-hero') || mainContent.firstElementChild;
			if (anchor && anchor.parentNode) anchor.parentNode.insertBefore(sec, anchor.nextSibling);
			const grid = sec.querySelector('#continue-grid');
			const html = items.map(it => {
				const cover = it.cover || '/muzic2/tracks/covers/placeholder.jpg';
				const pct = it.duration ? Math.max(0, Math.min(100, Math.round((it.currentTime/it.duration)*100))) : 0;
				return `
				<div class="continue-card" data-src="${escapeHtml(it.src)}" data-title="${escapeHtml(it.title||'')}" data-artist="${escapeHtml(it.artist||'')}" data-cover="${escapeHtml(cover)}">
					<div class="cc-cover-wrap">
						<img class="cc-cover" loading="lazy" decoding="async" src="${escapeHtml(cover)}" alt="cover" onerror="this.onerror=null;this.src='/muzic2/tracks/covers/placeholder.jpg'">
						<div class="cc-progress"><div class="cc-bar" style="width:${pct}%"></div></div>
					</div>
					<div class="cc-meta">
						<div class="cc-title">${escapeHtml(it.title||'')}</div>
						<div class="cc-artist">${escapeHtml(it.artist||'')}</div>
					</div>
				</div>`;
			}).join('');
			grid.innerHTML = html;
			grid.onclick = (e)=>{
				const card = e.target.closest('.continue-card'); if (!card) return;
				const src = card.getAttribute('data-src');
				const title = card.getAttribute('data-title')||'';
				const artist = card.getAttribute('data-artist')||'';
				const cover = card.getAttribute('data-cover')||'';
				try { playTrack({ src, title, artist, cover }); } catch(_) {}
			};
		} catch(_) {}
	}
}

function renderArtistInline(artistString) {
    if (!artistString) return '';
    try {
        const parts = String(artistString).split(',').map(s => s.trim()).filter(Boolean);
        return parts.map(p => `<span class="artist-link" data-artist="${escapeHtml(p)}">${escapeHtml(p)}</span>`).join(', ');
    } catch (_) { return escapeHtml(artistString); }
}

function buildStories(data) {
	try {
		const storiesInner = document.getElementById('stories-inner'); if (!storiesInner) return;
		const bubbles = [];
		if (data.artists && data.artists.length) {
			data.artists.slice(0,12).forEach(a=>{
				bubbles.push({ type:'artist', title: a.name || a.artist, cover: a.cover });
			});
		}
		if (data.albums && data.albums.length) {
			data.albums.slice(0,8).forEach(al=>{
				bubbles.push({ type:'album', title: al.title, cover: al.cover });
			});
		}
		storiesInner.innerHTML = bubbles.map(b=>{
			const img = '/muzic2/' + String(b.cover || 'tracks/covers/placeholder.jpg').replace(/^\/+/, '');
			return `<div class="story" data-type="${b.type}" data-title="${escapeHtml(b.title||'')}"><img src="${img}" alt="${escapeHtml(b.title||'')}"><div class="story-label">${escapeHtml(b.title||'')}</div></div>`;
		}).join('');
		storiesInner.onclick = (e)=>{
			const st = e.target.closest('.story'); if (!st) return;
			const type = st.getAttribute('data-type'); const title = st.getAttribute('data-title');
			if (type === 'artist') navigateTo('artist', { artist: title }); else if (type === 'album') navigateTo('album', { album: title });
		};
	} catch(_) {}
}

function buildSpotlight(data) {
	try {
		const grid = document.getElementById('spotlight-grid'); if (!grid) return;
		const picks = [];
		if (data.albums && data.albums.length) picks.push(...data.albums.slice(0,2).map(a=>({kind:'album', title:a.title, cover:a.cover})));
		if (data.tracks && data.tracks.length) picks.push(...data.tracks.slice(0,4).map(t=>({kind:'track', title:t.title, artist:t.artist, cover:t.cover, src:t.src||t.file_path})));
		grid.innerHTML = picks.map(p=>{
			const img = '/muzic2/' + String(p.cover || 'tracks/covers/placeholder.jpg').replace(/^\/+/, '');
			if (p.kind==='album') return `<div class="spotlight-card album" data-album="${escapeHtml(p.title)}"><img src="${img}"><div class="spotlight-meta"><div class="spotlight-title">${escapeHtml(p.title)}</div><div class="spotlight-sub">Альбом</div></div></div>`;
			return `<div class="spotlight-card track" data-title="${escapeHtml(p.title)}" data-artist="${escapeHtml(p.artist||'')}" data-src="${escapeHtml(p.src||'')}"><img src="${img}"><div class="spotlight-meta"><div class="spotlight-title">${escapeHtml(p.title)}</div><div class="spotlight-sub">${escapeHtml(p.artist||'')}</div></div></div>`;
		}).join('');
		grid.onclick = (e)=>{
			const card = e.target.closest('.spotlight-card'); if (!card) return;
			if (card.classList.contains('album')) { navigateTo('album', { album: card.getAttribute('data-album') }); return; }
			const src = card.getAttribute('data-src')||''; const title = card.getAttribute('data-title')||''; const artist = card.getAttribute('data-artist')||'';
			if (src) {
				try { playTrack({ src, title, artist, cover: card.querySelector('img')?.getAttribute('src')||'' }); } catch(_) {}
			}
		};
	} catch(_) {}
}

function upgradeRowsToHScroll() {
	try {
		['favorites-row','mixes-row','albums-row','tracks-row','artists-row'].forEach(id=>{
			const row = document.getElementById(id); if (!row) return;
			row.classList.add('hscroll');
			const wrap = row.parentElement; if (!wrap) return;
			const navLeft = document.createElement('button'); navLeft.className='row-nav left'; navLeft.innerHTML='‹';
			const navRight = document.createElement('button'); navRight.className='row-nav right'; navRight.innerHTML='›';
			wrap.style.position='relative'; wrap.appendChild(navLeft); wrap.appendChild(navRight);
			navLeft.onclick=()=>{ row.scrollBy({ left: -Math.max(300, row.clientWidth*0.6), behavior: 'smooth' }); };
			navRight.onclick=()=>{ row.scrollBy({ left: Math.max(300, row.clientWidth*0.6), behavior: 'smooth' }); };
		});
	} catch(_) {}
}

// Context menu for kebab button - works on all devices
function showContextMenu(event, track, trackIndex) {
	// Get the button that triggered this menu
	const currentButton = event.target.closest('.kebab');
	
	// Check if there's an existing menu and if it was opened by the same button
	const existingMenu = document.querySelector('.context-menu');
	if (existingMenu) {
		const menuButtonId = existingMenu.dataset.sourceButtonId;
		const currentButtonId = currentButton ? String(currentButton) : null;
		
		// If clicking the same button that opened the menu, close it
		if (menuButtonId && currentButtonId && menuButtonId === currentButtonId) {
			existingMenu.remove();
			return;
		}
		// Otherwise, remove the old menu and create a new one
		existingMenu.remove();
	}
	
	// Create context menu
	const menu = document.createElement('div');
	menu.className = 'context-menu show';
	
	// Store reference to the button that opened this menu
	if (currentButton) {
		menu.dataset.sourceButtonId = String(currentButton);
	}
	
	// Get track data
	const trackTitle = track.title || '';
	const trackArtist = track.artist || '';
	const trackAlbum = track.album || '';
	
	// Create menu items with icons
	const addToFavorites = document.createElement('button');
	addToFavorites.className = 'context-menu-item';
	const isLiked = window.__likedSet && window.__likedSet.has(track.id || trackIndex);
	addToFavorites.innerHTML = `<i class="${isLiked ? 'fas' : 'far'} fa-heart" style="font-size: 0.9rem; color: ${isLiked ? '#1ed760' : '#b3b3b3'};"></i><span>${isLiked ? 'Убрать из избранного' : 'Добавить в избранное'}</span>`;
	addToFavorites.onclick = () => {
		toggleLike(track.id || trackIndex);
		menu.remove();
	};
	
	const goToArtist = document.createElement('button');
	goToArtist.className = 'context-menu-item';
	goToArtist.innerHTML = '<i class="fas fa-user" style="font-size: 0.9rem; color: #b3b3b3;"></i><span>Перейти к артисту</span>';
	goToArtist.onclick = () => {
		if (trackArtist) {
			navigateTo('artist', { artist: trackArtist });
		}
		menu.remove();
	};
	
	const goToAlbum = document.createElement('button');
	goToAlbum.className = 'context-menu-item';
	goToAlbum.innerHTML = '<i class="fas fa-compact-disc" style="font-size: 0.9rem; color: #b3b3b3;"></i><span>Перейти к альбому</span>';
	goToAlbum.onclick = () => {
		if (trackAlbum) {
			navigateTo('album', { album: trackAlbum });
		}
		menu.remove();
	};
	
	// Add items to menu
	menu.appendChild(addToFavorites);
	if (trackArtist) menu.appendChild(goToArtist);
	if (trackAlbum) menu.appendChild(goToAlbum);
	
	// Position menu - fixed positioning relative to button
	const rect = event.target.getBoundingClientRect();
	const scrollX = window.pageXOffset || document.documentElement.scrollLeft;
	const scrollY = window.pageYOffset || document.documentElement.scrollTop;
	const menuWidth = 180;
	
	// Calculate absolute position (including scroll)
	const absoluteLeft = rect.left + scrollX;
	const absoluteTop = rect.bottom + scrollY;
	
	// Calculate horizontal position
	let left = absoluteLeft - menuWidth + rect.width; // Align right edge of menu with right edge of button
	if (left < 10) {
		left = absoluteLeft; // Show to the right if not enough space on left
	}
	
	// Position menu directly below the button
	menu.style.position = 'absolute';
	menu.style.left = left + 'px';
	menu.style.top = (absoluteTop + 5) + 'px';
	
	// Debug positioning
	console.log('Button rect:', rect);
	console.log('Scroll:', { scrollX, scrollY });
	console.log('Menu position:', { left: left + 'px', top: (absoluteTop + 5) + 'px' });
	
	// Add to document
	document.body.appendChild(menu);
	
	// Close menu when clicking outside
	const closeMenu = (e) => {
		if (!menu.contains(e.target)) {
			menu.remove();
			document.removeEventListener('click', closeMenu);
		}
	};
	
	setTimeout(() => {
		document.addEventListener('click', closeMenu);
	}, 100);
}

// =====================
// PWA Service Worker Registration
// =====================
if ('serviceWorker' in navigator) {
	window.addEventListener('load', () => {
		navigator.serviceWorker.register('/muzic2/public/service-worker.js')
			.then((registration) => {
				console.log('[PWA] Service Worker registered successfully:', registration.scope);
				
				// Проверка обновлений каждые 60 секунд
				setInterval(() => {
					registration.update();
				}, 60000);
				
				// Обработка обновлений Service Worker
				registration.addEventListener('updatefound', () => {
					const newWorker = registration.installing;
					newWorker.addEventListener('statechange', () => {
						if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
							// Новый Service Worker доступен, можно показать уведомление
							console.log('[PWA] New Service Worker available. Refresh to update.');
							// Можно показать уведомление пользователю
							if (confirm('Доступна новая версия приложения. Обновить?')) {
								newWorker.postMessage({ type: 'SKIP_WAITING' });
								window.location.reload();
							}
						}
					});
				});
			})
			.catch((error) => {
				console.error('[PWA] Service Worker registration failed:', error);
			});
		
		// Обработка сообщений от Service Worker
		navigator.serviceWorker.addEventListener('message', (event) => {
			console.log('[PWA] Message from Service Worker:', event.data);
		});
		
		// Обработка контроллера Service Worker
		let refreshing = false;
		navigator.serviceWorker.addEventListener('controllerchange', () => {
			if (!refreshing) {
				refreshing = true;
				window.location.reload();
			}
		});
	});
}

// =====================
// PWA Install Prompt
// =====================
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
	// Предотвращаем автоматическое отображение подсказки
	e.preventDefault();
	deferredPrompt = e;
	
	// Можно показать свою кнопку установки
	console.log('[PWA] Install prompt available');
	
	// Добавляем функцию для программной установки
	window.showInstallPrompt = () => {
		if (deferredPrompt) {
			deferredPrompt.prompt();
			deferredPrompt.userChoice.then((choiceResult) => {
				if (choiceResult.outcome === 'accepted') {
					console.log('[PWA] User accepted the install prompt');
				} else {
					console.log('[PWA] User dismissed the install prompt');
				}
				deferredPrompt = null;
			});
		}
	};
});

// Обработка успешной установки
window.addEventListener('appinstalled', () => {
	console.log('[PWA] App installed successfully');
	deferredPrompt = null;
});

// Проверка, установлено ли приложение
if (window.matchMedia('(display-mode: standalone)').matches || 
	window.navigator.standalone === true) {
	console.log('[PWA] Running as installed app');
	document.body.classList.add('pwa-installed');
}

