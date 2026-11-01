// Redesigned player: start-from-beginning on play, persistent state, full controls, queue panel
(function () {
  function initPlayer() {
  const playerRoot = document.getElementById('player-root');
    if (!playerRoot) {
      // Если элемент еще не создан, попробуем еще раз через небольшую задержку
      setTimeout(initPlayer, 100);
      return;
    }

  playerRoot.innerHTML = `
    <style>
      #player { display: grid; grid-template-columns: 1fr 2fr 1fr; align-items: center; gap: 12px; padding: 10px 12px; border-top: 1px solid #222; background: #121212; color: #fff; width: 100%; max-width: 100vw; box-sizing: border-box; padding-left: calc(12px + env(safe-area-inset-left)); padding-right: calc(12px + env(safe-area-inset-right)); }
      .player-left { display: flex; align-items: center; gap: 10px; min-width: 0; }
      .cover { width: 56px; height: 56px; object-fit: cover; border-radius: 8px; background: #222; }
      .track-info { min-width: 0; }
      #track-title { font-weight: 700; font-size: 1.2rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; }
      #track-artist { color: #b3b3b3; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; margin-top: 2px; }
      #track-artist .artist-link { color: inherit; cursor: pointer; text-decoration: none; }
      #track-artist .artist-link:hover { text-decoration: underline; }
      #track-status { color: #1ed760; font-size: 0.85rem; }
      .player-center { display: flex; flex-direction: column; gap: 6px; }
      .player-controls { display: flex; align-items: center; justify-content: center; gap: 12px; }
      .player-controls button { background: transparent; border: none; color: #fff; cursor: pointer; padding: 6px; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
      .player-controls button:hover { background: #1f1f1f; }
      #play-btn { background: #fff !important; color: #000 !important; border-radius: 50% !important; padding: 0 !important; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
      #play-btn:hover { background: #f0f0f0 !important; }
      #play-btn svg { width: 24px !important; height: 24px !important; }
      #pause-icon { width: 24px !important; height: 24px !important; }
      #prev-btn svg, #next-btn svg { width: 24px; height: 24px; }
      .player-progress { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 10px; }
      #seek-bar { width: 100%; }
      .player-right { display: flex; align-items: center; gap: 8px; justify-content: flex-end; }
      /* Lyrics */
      #lyrics-container { display: none; position: fixed; left: 50%; transform: translateX(-50%); bottom: 76px; width: min(1200px, 95vw); max-height: 42vh; overflow: hidden; padding: 18px 14px; background: rgba(0,0,0,0.7); backdrop-filter: blur(6px); border: 1px solid #242424; border-radius: 14px; box-shadow: 0 12px 30px rgba(0,0,0,.55); z-index: 9500; }
      #lyrics-list { display: flex; flex-direction: column; align-items: center; gap: 8px; will-change: transform; transform: translateZ(0); }
      #lyrics-container .lyric-line { 
        display: flex;
        align-items: center;
        justify-content: center;
        transform: translateZ(0);
        contain: strict;
        box-sizing: border-box;
        will-change: opacity;
        transition: opacity 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        backface-visibility: hidden;
      }
      #lyrics-container .lyric-text-inner {
        color: #b3b3b3; 
        opacity: .35; 
        text-align: center; 
        font-size: 1.6rem !important; 
        line-height: 1.6 !important;
        font-weight: 400 !important;
        transform: translateZ(0);
        display: block;
        box-sizing: border-box;
        will-change: color, opacity;
        transition: color 0.4s cubic-bezier(0.4, 0, 0.2, 1), 
                    opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        -webkit-font-smoothing: antialiased;
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: normal;
      }
      #lyrics-container .lyric-line.active { 
        opacity: 1;
      }
      #lyrics-container .lyric-line.active .lyric-text-inner {
        color: #fff !important; 
        opacity: 1 !important;
        font-size: 1.6rem !important;
        line-height: 1.6 !important;
        font-weight: 400 !important;
      }
      #lyrics-container .lyric-word,
      #lyrics-fs-inner .lyric-word { 
        display: inline-block; 
        transition: color 0.5s cubic-bezier(0.34, 1.56, 0.64, 1), transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.4s ease, text-shadow 0.5s ease;
        opacity: 0.6;
        text-shadow: none;
      }
      #lyrics-container .lyric-word.active-word,
      #lyrics-fs-inner .lyric-word.active-word { 
        color: #1ed760; 
        transform: scale(1.12);
        opacity: 1;
        text-shadow: 0 0 7px rgba(30, 215, 96, 0.5), 0 0 10px rgba(30, 215, 96, 0.3);
        transition: color 0.5s cubic-bezier(0.34, 1.56, 0.64, 1), transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.4s ease, text-shadow 0.5s ease;
      }
      /* Уничтожаем фантомные строчки в inline лирике */
      #lyrics-container .lyric-line.past { display: none !important; opacity: 0 !important; visibility: hidden !important; }

      /* Fullscreen karaoke (Yandex-style) */
      #lyrics-fs { position: fixed; top: 0; left: 0; right: 0; bottom: 110px; background: #0f0f0f; display: none; z-index: 9000; }
      #lyrics-fs-underlay { position: fixed; inset: 0; z-index: 0; display: none; overflow: hidden; pointer-events: none; }
      #lyrics-fs-underlay img, #lyrics-fs-underlay video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; filter: blur(16px) brightness(0.45) saturate(1.05); transform: scale(1.06); }
      #lyrics-fs-underlay video { opacity: 0.85; }
      #lyrics-fs-bg { position: absolute; inset: 0; overflow: hidden; z-index: 0; }
      #lyrics-fs-bg img, #lyrics-fs-bg video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; filter: blur(16px) brightness(0.45) saturate(1.05); transform: scale(1.06); }
      #lyrics-fs-bg video { opacity: 0.85; }
      #lyrics-fs-close { position: absolute; top: 20px; right: 24px; width: 44px; height: 44px; border-radius: 50%; background: rgba(255,255,255,0.08); color: #fff; border: 1px solid #2a2a2a; cursor: pointer; font-size: 24px; line-height: 44px; text-align: center; z-index: 2; }
      #lyrics-fs-mode { position: absolute; top: 20px; right: 78px; width: 44px; height: 44px; border-radius: 50%; background: rgba(255,255,255,0.08); color: #fff; border: 1px solid #2a2a2a; cursor: pointer; font-size: 18px; line-height: 44px; text-align: center; z-index: 2; }
      #lyrics-fs-grid { position: absolute; inset: 0; display: grid; grid-template-columns: 520px 1fr; gap: 48px; align-items: center; padding: 80px 72px 120px; z-index: 1; box-sizing: border-box; max-width: 100%; }
      #lyrics-fs-meta { display: flex; flex-direction: column; gap: 18px; justify-content: center; }
      #lyrics-fs-cover { width: 260px; height: 260px; border-radius: 18px; object-fit: cover; background: #111; box-shadow: 0 24px 70px rgba(0,0,0,.55); }
      #lyrics-fs-video-wrap { width: 100%; max-width: 100%; aspect-ratio: 16/9; background:#000; border-radius:18px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.45); display:none; }
      #lyrics-fs-video-embed { width:100%; height:100%; display:none; background:#000; }
      #lyrics-fs-video-embed::-webkit-media-controls { display: none !important; }
      #lyrics-fs.promo-mode #lyrics-fs-video-embed::-webkit-media-controls { display: flex !important; }
      #lyrics-fs-video-cover { width:100%; height:100%; object-fit:cover; display:none; background:#000; }
      #lyrics-fs.promo-mode #lyrics-fs-panel,
      #lyrics-fs.promo-mode #lyrics-fs-inner,
      #lyrics-fs.promo-mode #lyrics-fs-dots,
      #lyrics-fs.promo-mode .lyrics-fs-fade { display:none !important; }
      #lyrics-fs.promo-mode { bottom: 0 !important; }
      #lyrics-fs.promo-mode #lyrics-fs-grid { grid-template-columns: 1fr; gap: 0; padding: 0; position: absolute; inset: 0; display: block; width: 100%; height: 100%; }
      #lyrics-fs.promo-mode #lyrics-fs-bg { display:none !important; }
      #lyrics-fs.promo-mode #lyrics-fs-meta { align-items: center; text-align: center; gap: 12px; padding: 20px 24px; position: absolute; top: 0; left: 0; right: 0; z-index: 3; background: linear-gradient(to bottom, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0) 100%); pointer-events: none; }
      #lyrics-fs.promo-mode #lyrics-fs-meta * { pointer-events: auto; }
      #lyrics-fs.promo-mode #lyrics-fs-cover { width: clamp(80px, 12vw, 120px); height: clamp(80px, 12vw, 120px); border-radius: 12px; display: none !important; }
      #lyrics-fs.promo-mode #lyrics-fs-video-wrap { display:block !important; width: 100vw !important; height: 100vh !important; max-width: none !important; max-height: none !important; aspect-ratio: unset !important; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; z-index: 1; overflow: hidden; margin: 0; padding: 0; }
      #lyrics-fs.promo-mode #lyrics-fs-video-embed { display:block !important; width: 100% !important; height: 100% !important; min-width: 100% !important; min-height: 100% !important; object-fit: cover !important; background: #000; position: absolute; top: 0; left: 0; right: 0; bottom: 0; margin: 0; padding: 0; }
      #lyrics-fs.promo-mode #lyrics-fs-video-embed video { width: 100% !important; height: 100% !important; min-width: 100% !important; min-height: 100% !important; object-fit: cover !important; position: absolute; top: 0; left: 0; }
      #lyrics-fs.promo-mode #lyrics-fs-mode { display:none !important; }
      #lyrics-fs.promo-mode #lyrics-fs-title { font-size: clamp(20px, 3.5vw, 32px); margin: 0; }
      #lyrics-fs.promo-mode #lyrics-fs-artist { display: none !important; }
      #lyrics-fs.promo-mode #promo-track-info { display: flex; align-items: center; gap: 12px; position: absolute; bottom: 120px; left: 24px; z-index: 4; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); padding: 12px 16px; border-radius: 12px; max-width: 320px; }
      #lyrics-fs.promo-mode #promo-track-info img { width: 56px; height: 56px; border-radius: 8px; object-fit: cover; }
      #lyrics-fs.promo-mode #promo-track-info-text { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
      #lyrics-fs.promo-mode #promo-track-title { color: #fff; font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      #lyrics-fs.promo-mode #promo-track-artist { color: #b3b3b3; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      #lyrics-fs-title { color: #fff; font-size: 30px; font-weight: 800; }
      #lyrics-fs-artist { color: #bdbdbd; font-size: 18px; }
      #lyrics-fs-panel { position: relative; width: 100%; margin: 0 auto; overflow: visible; }
      #lyrics-fs-inner { position: relative; height: calc(100vh - 80px - 120px); max-height: 70vh; display: block; overflow: auto; scroll-behavior: auto; scrollbar-width: none; box-sizing: border-box; max-width: 100%; }
      #lyrics-fs-inner::-webkit-scrollbar { width: 0; height: 0; }
      #lyrics-fs.static #lyrics-fs-inner { overflow: auto; align-items: flex-start; justify-content: center; }
      #lyrics-fs.static #lyrics-fs-list { transform: none; padding: 24px 16px 24px; }
      #lyrics-fs.static .lyric-line { opacity: .6; }
      #lyrics-fs.static .lyric-line.active { opacity: 1; }
      #lyrics-fs-list { display: flex; flex-direction: column; align-items: center; gap: 20px; will-change: transform; padding: 0 16px; width: 100%; max-width: 1250px; box-sizing: border-box; margin-top: 0; }
      #lyrics-fs-inner .lyric-line { 
        color: #7e7e7e; 
        opacity: .26; 
        text-align: center; 
        font-weight: 700; 
        letter-spacing: 0.1px; 
        font-size: clamp(22px, 1.90vw, 25px) !important; 
        line-height: 1.6; 
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        word-wrap: break-word;
        overflow-wrap: break-word;
        word-break: break-word;
        white-space: normal;
        padding: 0 20px;
        transform: translateY(10px);
        transition: opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1), 
                    color 0.8s cubic-bezier(0.4, 0, 0.2, 1), 
                    font-weight 0.8s cubic-bezier(0.4, 0, 0.2, 1), 
                    text-shadow 0.8s cubic-bezier(0.4, 0, 0.2, 1),
                    transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        will-change: opacity, color, font-weight, text-shadow, transform;
        hyphens: auto;
        -webkit-hyphens: auto;
      }
      /* Плавные переключения между активными строками */
      #lyrics-fs-inner .lyric-line.active { 
        opacity: 1 !important; 
        color: #ffffff !important; 
        font-weight: 800 !important; 
        font-size: clamp(22px, 1.9vw, 24px) !important; 
        text-shadow: 0 0 7px rgba(30, 215, 96, 0.5), 0 0 10px rgba(30, 215, 96, 0.3);
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
        transform: translateY(0);
        transition: opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1), 
                    color 0.8s cubic-bezier(0.4, 0, 0.2, 1), 
                    font-weight 0.8s cubic-bezier(0.4, 0, 0.2, 1), 
                    text-shadow 0.8s cubic-bezier(0.4, 0, 0.2, 1),
                    transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        word-wrap: break-word;
        overflow-wrap: break-word;
        word-break: break-word;
      }
      /* Уничтожаем фантомные строчки раз и навсегда */
      #lyrics-fs-inner .lyric-line.past { display: none !important; opacity: 0 !important; visibility: hidden !important; }
      .lyrics-fs-fade { display: none; position: absolute; left: 0; right: 0; z-index: 2; pointer-events: none; }
      .lyrics-fs-fade.top { top: 0; height: 56px; background: linear-gradient(to bottom, rgba(15,15,15,1), rgba(15,15,15,0)); }
      .lyrics-fs-fade.bottom { bottom: 0; height: 56px; background: linear-gradient(to top, rgba(15,15,15,1), rgba(15,15,15,0)); }
      /* Tablet: промежуточные размеры */
      @media (max-width: 1200px) {
        #lyrics-fs-grid { grid-template-columns: 400px 1fr; gap: 32px; padding: 60px 48px 100px; }
        #lyrics-fs-cover { width: 200px; height: 200px; }
        #lyrics-fs-title { font-size: 26px; }
        #lyrics-fs-artist { font-size: 16px; }
      }
      /* Mobile: keep lyrics away from title/artist area and auto-hide past lines */
      @media (max-width: 960px) {
        #lyrics-fs-grid { grid-template-columns: 1fr; gap: 12px; padding: 0px 12px 0px; max-width: 100% !important; box-sizing: border-box !important; padding-top: 96px; }
        #lyrics-fs-meta { align-items: center; text-align: center; gap: 8px; margin-top: -8px; }
        #lyrics-fs-cover { width: 120px; height: 120px; }
        #lyrics-fs-title { font-size: 18px !important; }
        #lyrics-fs-artist { font-size: 14px !important; }
        #lyrics-fs.promo-mode #lyrics-fs-grid { padding: 0; }
        #lyrics-fs.promo-mode #lyrics-fs-meta { padding: 12px 16px; }
        #lyrics-fs.promo-mode #lyrics-fs-cover { width: 60px; height: 60px; }
        #lyrics-fs.promo-mode #lyrics-fs-title { font-size: 16px !important; }
        #lyrics-fs.promo-mode #lyrics-fs-artist { font-size: 13px !important; }
        #lyrics-fs-panel { margin-top: 0 !important; }
        #lyrics-fs-inner { max-height: calc(100vh - 280px); padding: 0px 0 8px 0; box-sizing: border-box; padding-top: 0px; }
        #lyrics-fs-inner .lyric-line { font-size: clamp(14px, 4vw, 18px) !important; line-height: 1.4 !important; padding: 4px 4px !important; word-break: break-word !important; overflow-wrap: break-word !important; word-wrap: break-word !important; white-space: normal !important; max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; }
        #lyrics-fs-inner .lyric-line.active { font-size: clamp(18px, 5.5vw, 24px) !important; line-height: 1.5 !important; white-space: normal !important; text-overflow: clip !important; overflow: visible !important; max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; }
        #lyrics-fs-list { gap: 8px !important; padding: 0 4px !important; margin-top: 0 !important; max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; transition: none; padding-top: 0 !important; }
        #lyrics-fs-close { top: 8px !important; right: 8px !important; width: 32px !important; height: 32px !important; font-size: 18px !important; line-height: 32px !important; }
        #lyrics-fs-mode { top: 8px !important; right: 48px !important; width: 32px !important; height: 32px !important; font-size: 12px !important; line-height: 32px !important; }
        #queue-panel { right: 8px; left: 8px; width: auto; }
        #video-panel { right: 8px !important; left: 8px; width: auto !important; }
        .lyrics-fs-fade { display: none; }
        /* Исправлено: принудительно скрываем фантомные строки на мобильных устройствах */
        #lyrics-fs-inner .lyric-line.past { display: none !important; opacity: 0 !important; }
        #lyrics-fs.reviewing #lyrics-fs-inner .lyric-line.past { display: block; opacity: .6; }
      }
      /* Small tablets */
      @media (max-width: 768px) {
        #lyrics-fs-grid { padding-top: 60px; }
        #lyrics-fs-cover { width: 140px; height: 140px; }
        #lyrics-fs-title { font-size: 20px !important; }
        #lyrics-fs-artist { font-size: 15px !important; }
        #lyrics-fs-inner { max-height: calc(100vh - 260px); }
      }
      @media (max-width: 600px) {
        #player { grid-template-columns: 1fr; padding: 6px calc(8px + env(safe-area-inset-right)); padding-left: calc(8px + env(safe-area-inset-left)); justify-items: center; }
        #queue-panel { bottom: 120px; max-height: 40vh; }
        #lyrics-fs { bottom: 120px; }
        /* give extra headroom for title/artist on very small screens */
        #lyrics-fs-grid { padding-top: 40px; padding-left: 8px; padding-right: 8px; gap: 8px; max-width: 100% !important; box-sizing: border-box !important; }
        #lyrics-fs-meta { margin-top: 0; gap: 6px; }
        #lyrics-fs-cover { width: 100px !important; height: 100px !important; }
        #lyrics-fs-title { font-size: 16px !important; line-height: 1.2 !important; }
        #lyrics-fs-artist { font-size: 12px !important; line-height: 1.2 !important; }
        #lyrics-fs-panel { margin-top: 0 !important; }
        #lyrics-fs-inner { padding-top: 0px !important; max-height: calc(100vh - 240px) !important; box-sizing: border-box !important; }
        #lyrics-fs-inner .lyric-line { font-size: clamp(13px, 3.5vw, 16px) !important; line-height: 1.3 !important; padding: 3px 4px !important; word-wrap: break-word !important; overflow-wrap: break-word !important; word-break: break-word !important; white-space: normal !important; max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; }
        #lyrics-fs-inner .lyric-line.active { font-size: clamp(16px, 5vw, 20px) !important; line-height: 1.4 !important; white-space: normal !important; text-overflow: clip !important; overflow: visible !important; max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; }
        #lyrics-fs-list { gap: 6px !important; padding: 0 2px !important; margin-top: 230px !important; max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; }
        .lyrics-fs-fade { display: none; }
        /* Исправлено: принудительно скрываем фантомные строки на маленьких экранах */
        #lyrics-fs-inner .lyric-line.past { display: none !important; opacity: 0 !important; }
        /* Compact player controls */
        #cover { width: 40px; height: 40px; border-radius: 8px; }
        #title { font-size: 13px; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 70vw; }
        #artist { font-size: 11px; line-height: 1.1; color: #bdbdbd; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 70vw; }
        .player-left { gap: 8px; }
        .player-controls { gap: 6px; }
        .player-controls button { padding: 5px; }
        .volume-bar { display: none; }
        /* hide right-side control row to reduce height */
        .player-right { display: none; }
        /* hide secondary buttons to save space */
        #video-btn, #crossfade-btn, #queue-btn { display: none; }
        #video-mini { display: inline-flex !important; }
      }
      #like-btn { background: transparent; border: none; color: #bbb; cursor: pointer; padding: 6px; border-radius: 50%; pointer-events: auto; }
      #like-btn.btn-active { color: #1ed760; }
      .volume-bar { width: 120px; }
      .btn-active { color: #1ed760; }
      /* Queue panel */
      /* Inline mobile queue button (inside controls) */
      #queue-mini, #video-mini { display: none; }
      #video-btn .video-btn-icon, #video-mini .video-btn-icon { width: 22px; height: 22px; object-fit: contain; display: block; opacity: 0.92; }
      #video-btn.btn-active .video-btn-icon, #video-mini.btn-active .video-btn-icon { opacity: 1; filter: brightness(1.2); }
      #video-btn:disabled, #video-mini:disabled { opacity: 0.35; cursor: not-allowed; }
      @media (max-width: 600px) {
        #queue-mini, #video-mini { display: inline-flex; align-items: center; justify-content: center; padding: 4px; }
        #queue-mini svg { width: 22px; height: 22px; }
      }
      #queue-panel { position: fixed; right: 12px; bottom: 76px; width: 320px; max-height: 50vh; overflow: hidden; background: rgba(40, 40, 40, 0.98); color: #fff; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.05) inset; display: none; z-index: 10000; backdrop-filter: blur(20px) saturate(180%); -webkit-backdrop-filter: blur(20px) saturate(180%); }
      #queue-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); position: sticky; top: 0; background: rgba(40, 40, 40, 0.95); z-index: 1; backdrop-filter: blur(10px); }
      #queue-title { font-weight: 700; font-size: 0.95rem; letter-spacing: -0.2px; }
      #queue-close { background: transparent; border: none; color: #b3b3b3; cursor: pointer; font-size: 1rem; padding: 4px 6px; border-radius: 6px; transition: all 0.2s ease; }
      #queue-close:hover { background: rgba(255, 255, 255, 0.1); color: #fff; }
      #queue-list { list-style: none; margin: 0; padding: 6px; overflow-y: auto; max-height: calc(50vh - 50px); }
      .queue-item { display: grid; grid-template-columns: 28px 1fr auto; gap: 10px; align-items: center; padding: 8px 10px; border-radius: 8px; cursor: pointer; transition: all 0.15s ease; margin: 1px 0; }
      .queue-item:hover { background: rgba(255, 255, 255, 0.08); transform: translateX(2px); }
      .queue-idx { color: #777; text-align: right; font-size: 0.85rem; font-weight: 500; }
      .queue-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; font-size: 0.9rem; }
      .queue-artist { color: #9aa0a6; font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }
      .queue-artist .artist-link { color: inherit; cursor: pointer; text-decoration: none; transition: color 0.2s; }
      .queue-artist .artist-link:hover { color: #1ed760; text-decoration: underline; }
      .queue-meta { color: #666; font-size: 0.75rem; font-weight: 500; }
      .queue-current { background: rgba(30, 215, 96, 0.15); border: 1px solid rgba(30, 215, 96, 0.2); }
      .queue-current .queue-idx { color: #1ed760; font-weight: 700; }
      .queue-current .queue-title { color: #fff; font-weight: 600; }

      /* Fullscreen mode */
      #fullscreen-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #000; z-index: 9999; display: none; }
      #fullscreen-content { position: absolute; top: 0; left: 0; width: 100%; height: calc(100vh - 100px); display: flex; flex-direction: column; align-items: center; justify-content: center; }
      #fullscreen-cover { max-width: 50vh; max-height: 50vh; width: auto; height: auto; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.8); }
      #fullscreen-video { max-width: 100vw; max-height: calc(100vh - 100px); width: auto; height: auto; }
      #fullscreen-info { position: absolute; bottom: 60px; left: 50%; transform: translateX(-50%); text-align: center; color: #fff; }
      #fullscreen-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.3rem; }
      #fullscreen-artist { font-size: 1rem; color: #b3b3b3; }
      .exp-badge{ display:inline-block; width:16px; height:16px; line-height:16px; text-align:center; margin:0 6px 0 0; border:0; border-radius:3px; font-size:10px; font-weight:800; color:#2b2b2b; background:#cfcfcf; vertical-align:middle }
      #fullscreen-close { position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.5); border: none; color: #fff; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
      #fullscreen-back { position: absolute; top: 20px; left: 20px; background: rgba(0,0,0,0.5); border: none; color: #fff; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
      
      /* Carousel mode - точно по коду из CoverFlow.tsx */
      #carousel-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: linear-gradient(to bottom, #000000 0%, #111111 100%); z-index: 8000; display: none; overflow: hidden; }
      #carousel-overlay[style*="display: block"], #carousel-overlay[style*="display:block"] { display: block !important; }
      #carousel-container { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; flex-direction: column; }
      #carousel-track-wrapper { position: relative; width: 100%; max-width: 80rem; height: 24rem; perspective: 1500px; perspective-origin: center center; display: flex; align-items: center; justify-content: center; overflow: visible; cursor: grab; user-select: none; margin: 0 auto; }
      #carousel-track-wrapper:active { cursor: grabbing; }
      #carousel-track { position: relative; width: 100%; height: 100%; display: flex; justify-content: center; align-items: center; transform-style: preserve-3d; }
      .carousel-cover-wrapper { position: absolute; transform-style: preserve-3d; transform-origin: center center; transition: all 400ms cubic-bezier(0.23, 1, 0.32, 1); cursor: pointer; user-select: none; }
      .carousel-cover-wrapper.dragging { transition: none; }
      .carousel-cover-container { position: relative; width: 200px; height: 200px; }
      .carousel-cover-wrapper.active .carousel-cover-container { width: 200px; height: 200px; }
      @media (max-width: 768px) {
        .carousel-cover-container { width: 170px; height: 170px; }
        .carousel-cover-wrapper.active .carousel-cover-container { width: 170px; height: 170px; }
      }
      .carousel-cover { width: 100%; height: 100%; border-radius: 0.625rem; object-fit: cover; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.9), 0 8px 32px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.1); border: 1px solid rgba(55, 65, 81, 0.3); display: block; user-select: none; }
      .carousel-cover-reflection { position: absolute; top: 100%; left: 0; width: 100%; height: 100%; border-radius: 0.625rem; pointer-events: none; background-size: cover; background-position: center; background-repeat: no-repeat; transform: scaleY(-1) translateY(0px); opacity: 0.75; mask-image: linear-gradient(to top, rgba(255, 255, 255, 0.75) 0%, rgba(255, 255, 255, 0.4) 20%, rgba(255, 255, 255, 0.15) 40%, transparent 60%); -webkit-mask-image: linear-gradient(to top, rgba(255, 255, 255, 0.75) 0%, rgba(255, 255, 255, 0.4) 20%, rgba(255, 255, 255, 0.15) 40%, transparent 60%); filter: blur(0.5px) brightness(0.6) contrast(1.3); transition: all 500ms; }
      .carousel-cover-wrapper.active .carousel-cover-reflection { opacity: 0.9; }
      .carousel-cover-glass { position: absolute; inset: 0; border-radius: 0.625rem; pointer-events: none; background: linear-gradient(135deg, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0.04) 25%, transparent 50%, rgba(0,0,0,0.02) 75%, rgba(0,0,0,0.08) 100%); }
      .carousel-cover-floor { position: absolute; top: 100%; left: 0; width: 100%; height: 12px; border-radius: 0.625rem; pointer-events: none; background: linear-gradient(to bottom, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0.12) 40%, transparent 100%); transform: translateY(6px); opacity: 0.35; transition: opacity 1000ms; }
      .carousel-cover-wrapper.active .carousel-cover-floor { opacity: 0.6; }
      .carousel-cover-wrapper.active .carousel-cover { border: 1px solid rgba(255, 255, 255, 0.3); }
      .carousel-cover-border { position: absolute; inset: 0; border-radius: 0.625rem; border: 1px solid rgba(255, 255, 255, 0.3); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.9); pointer-events: none; display: none; }
      .carousel-cover-wrapper.active .carousel-cover-border { display: block; }
      .carousel-cover-indicator { position: absolute; top: 12px; right: 12px; width: 10px; height: 10px; background: #fff; border-radius: 50%; opacity: 0.9; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1); display: none; animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
      .carousel-cover-wrapper.active .carousel-cover-indicator { display: block; }
      .carousel-cover-top-light { position: absolute; top: 0; left: 25%; right: 25%; height: 4px; background: rgba(255, 255, 255, 0.2); border-radius: 9999px; filter: blur(8px); display: none; }
      .carousel-cover-wrapper.active .carousel-cover-top-light { display: block; }
      #carousel-close { position: fixed; top: 20px; right: 20px; background: rgba(0, 0, 0, 0.6); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 50%; color: #fff; width: 48px; height: 48px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 2rem; z-index: 10001; transition: all 0.2s; pointer-events: auto; backdrop-filter: blur(12px); font-weight: 300; }
      #carousel-close:hover { background: rgba(0, 0, 0, 0.8); border-color: rgba(255, 255, 255, 0.25); }
      #carousel-info { display: flex; flex-direction: column; align-items: center; gap: 6px; margin-top: 2.5rem; z-index: 10000; text-align: center; }
      #carousel-track-title { font-weight: 700; font-size: 1.2rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; max-width: 80vw; }
      #carousel-track-artist { color: #b3b3b3; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; max-width: 80vw; }
      #carousel-progress { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 10px; margin-top: 1rem; width: 100%; max-width: 480px; z-index: 10000; box-sizing: border-box; padding: 0; }
      #carousel-seek-bar-wrapper { position: relative; width: 100%; height: 4px; background: #535353; border-radius: 2px; cursor: pointer; }
      #carousel-seek-bar-progress { position: absolute; left: 0; top: 0; height: 100%; background: #1ed760; border-radius: 2px; width: 0%; transition: width 0.1s linear; pointer-events: none; }
      #carousel-seek-bar { position: absolute; left: 0; top: 0; width: 100%; height: 100%; -webkit-appearance: none; appearance: none; background: transparent; cursor: pointer; margin: 0; padding: 0; border: none; outline: none; z-index: 2; }
      #carousel-seek-bar::-webkit-slider-track { background: transparent; height: 4px; }
      #carousel-seek-bar::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 0; height: 0; background: transparent; border: none; pointer-events: none; }
      #carousel-seek-bar::-moz-range-track { background: transparent; height: 4px; border: none; }
      #carousel-seek-bar::-moz-range-thumb { width: 0; height: 0; border: none; background: transparent; pointer-events: none; }
      #carousel-current-time, #carousel-duration { font-size: 0.95rem; color: #b3b3b3; min-width: 38px; text-align: center; }
      #carousel-controls { display: flex; align-items: center; justify-content: center; gap: 16px; margin-top: 1.5rem; z-index: 10000; }
      #carousel-controls button { background: transparent; border: none; color: #fff; cursor: pointer; padding: 8px; border-radius: 50%; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
      #carousel-controls button:hover { background: rgba(255, 255, 255, 0.1); }
      #carousel-play-btn { background: #fff !important; color: #000 !important; width: 72px !important; height: 72px !important; border-radius: 50% !important; padding: 0 !important; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
      #carousel-play-btn:hover { background: #f0f0f0 !important; }
      #carousel-play-btn svg { width: 28px !important; height: 28px !important; }
      #carousel-pause-icon { width: 28px !important; height: 28px !important; }
      #carousel-prev-btn svg, #carousel-next-btn svg { width: 28px; height: 28px; }
      #carousel-shuffle-btn svg, #carousel-volume-btn svg { width: 22px; height: 22px; }
      @media (max-width: 768px) {
        #carousel-info { margin-top: 2rem; }
        #carousel-track-title { font-size: 1.1rem; max-width: 90vw; }
        #carousel-track-artist { font-size: 0.9rem; max-width: 90vw; }
        #carousel-progress { gap: 8px; margin-top: 0.8rem; max-width: 85vw; }
        #carousel-current-time, #carousel-duration { min-width: 32px; }
        #carousel-current-time, #carousel-duration { font-size: 0.85rem; }
        #carousel-controls { gap: 12px; margin-top: 1.2rem; }
        #carousel-controls button { width: 44px; height: 44px; padding: 6px; }
        #carousel-play-btn svg { width: 24px !important; height: 24px !important; }
        #carousel-pause-icon { width: 24px !important; height: 24px !important; }
        #carousel-prev-btn svg, #carousel-next-btn svg { width: 24px; height: 24px; }
        #carousel-shuffle-btn svg, #carousel-volume-btn svg { width: 20px; height: 20px; }
      }
      @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

      /* Responsive player layout */
      @media (max-width: 900px) {
        #player { grid-template-columns: 1fr; row-gap: 8px; padding: 8px calc(8px + env(safe-area-inset-right)); padding-left: calc(8px + env(safe-area-inset-left)); justify-items: center; }
        .player-left { justify-content: center; }
        .player-right { justify-content: center; }
        .player-progress { grid-template-columns: auto 1fr auto; gap: 8px; }
      }
      @media (max-width: 600px) {
        .cover { width: 44px; height: 44px; }
        .volume-bar { width: 80px; }
        .player-controls { gap: 8px; }
        #current-time, #duration { font-size: 0.85rem; }
      }
      /* Very small/landscape phones: make player extra compact */
      @media (orientation: landscape) and (max-height: 480px), (max-height: 420px) {
        #player { grid-template-columns: 1fr; padding: 4px 6px; row-gap: 4px; }
        .cover { width: 36px; height: 36px; }
        #track-title { font-size: 0.95rem; }
        #track-artist { font-size: 0.8rem; }
        .player-left { gap: 6px; }
        .player-controls { gap: 6px; }
        .player-controls button svg { width: 20px; height: 20px; }
        #play-btn svg { width: 26px; height: 26px; }
        .player-progress { gap: 6px; }
        #current-time, #duration { font-size: 0.8rem; }
        #lyrics-btn, #video-btn, #device-btn, #crossfade-btn { display: none; }
      }
    </style>
    <div id="player">
      <div class="player-left">
        <img id="cover" src="https://via.placeholder.com/56x56?text=%E2%99%AA" alt="cover" class="cover">
        <div class="track-info">
          <div id="track-title">Название трека</div>
          <div id="track-artist">Артист</div>
        </div>
        <span id="track-status" class="track-status"></span>
      </div>
      <div class="player-center">
        <div class="player-controls">
          <button id="queue-mini" title="Очередь">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="17" y2="18"/><polyline points="19 16 21 18 19 20"/></svg>
          </button>
          <button id="shuffle-btn" title="Случайно">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/></svg>
          </button>
          <button id="crossfade-btn" title="Умный кроссфейд">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
              <line x1="8" y1="21" x2="16" y2="21"/>
              <line x1="12" y1="17" x2="12" y2="21"/>
              <line x1="7" y1="8" x2="7" y2="12"/>
              <line x1="12" y1="8" x2="12" y2="12"/>
              <line x1="17" y1="8" x2="17" y2="12"/>
            </svg>
          </button>
          <button id="prev-btn" title="Назад">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5"/></svg>
          </button>
          <button id="play-btn" title="Воспроизвести">
            <svg id="play-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="6 3 20 12 6 21 6 3"/></svg>
            <svg id="pause-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
          </button>
          <button id="next-btn" title="Вперёд">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/></svg>
          </button>
          <button id="repeat-btn" title="Повтор: выкл">
            <svg id="repeat-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
          </button>
          <button id="lyrics-btn" title="Текст (караоке)">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h9"/><path d="M17 3h4v4"/><path d="M21 3l-7 7"/></svg>
          </button>
          <button id="video-mini" type="button" title="Клип к треку" disabled>
            <img class="video-btn-icon" src="/muzic2/public/assets/img/video-clip-icon.png" alt="">
          </button>
        </div>
        <div class="player-progress">
          <span id="current-time">0:00</span>
          <input type="range" id="seek-bar" min="0" max="100" value="0">
          <span id="duration">0:00</span>
        </div>
        <div id="lyrics-container"></div>
      </div>
      <div class="player-right">
        <button id="like-btn" type="button" title="В избранное">❤</button>
        <button id="queue-btn" title="Очередь">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="17" y2="18"/><polyline points="19 16 21 18 19 20"/></svg>
        </button>
        <button id="video-btn" type="button" title="Клип к треку" disabled>
          <img class="video-btn-icon" src="/muzic2/public/assets/img/video-clip-icon.png" alt="">
        </button>
        <button id="device-btn" title="Устройство">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2h3"/><path d="M3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2H3"/></svg>
        </button>
        <button id="volume-btn" title="Громкость">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
        </button>
        <input type="range" id="volume-bar" min="0" max="100" value="100" class="volume-bar">
        <button id="fullscreen-btn" title="Fullscreen">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M16 3h3a2 2 0 0 1 2 2v3"/><path d="M8 21H5a2 2 0 0 1-2-2v-3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
        </button>
      </div>
      <audio id="audio" preload="auto"></audio>
    </div>
    <div id="fullscreen-overlay">
      <div id="fullscreen-content">
        <img id="fullscreen-cover" src="" alt="cover" style="display: none;">
        <video id="fullscreen-video" style="display: none;" controls></video>
        <div id="fullscreen-info">
          <div id="fullscreen-title"></div>
          <div id="fullscreen-artist"></div>
        </div>
        <button id="fullscreen-back">←</button>
        <button id="fullscreen-close">×</button>
      </div>
    </div>
    <div id="carousel-overlay">
      <div id="carousel-container">
        <div id="carousel-track-wrapper">
          <div id="carousel-track"></div>
        </div>
        <div id="carousel-info">
          <div id="carousel-track-title"></div>
          <div id="carousel-track-artist"></div>
        </div>
        <div id="carousel-progress">
          <span id="carousel-current-time">0:00</span>
          <div id="carousel-seek-bar-wrapper">
            <div id="carousel-seek-bar-progress"></div>
            <input type="range" id="carousel-seek-bar" min="0" max="100" value="0">
          </div>
          <span id="carousel-duration">0:00</span>
        </div>
        <div id="carousel-controls">
          <button id="carousel-shuffle-btn" title="Случайно">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/></svg>
          </button>
          <button id="carousel-prev-btn" title="Назад">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5"/></svg>
          </button>
          <button id="carousel-play-btn" title="Воспроизвести">
            <svg id="carousel-play-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="6 3 20 12 6 21 6 3"/></svg>
            <svg id="carousel-pause-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
          </button>
          <button id="carousel-next-btn" title="Вперёд">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/></svg>
          </button>
          <button id="carousel-volume-btn" title="Громкость">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
          </button>
        </div>
      </div>
      <button id="carousel-close">×</button>
    </div>
    <div id="lyrics-fs-underlay"><img id="lyrics-fs-underlay-img" alt="bg" style="display:none;"><video id="lyrics-fs-underlay-video" playsinline muted loop style="display:none;"></video></div>
    <div id="lyrics-fs">
      <div id="lyrics-fs-bg"><img id="lyrics-fs-bg-img" alt="bg" style="display:none;"><video id="lyrics-fs-bg-video" playsinline muted loop style="display:none;"></video></div>
      <button id="lyrics-fs-close" title="Закрыть">×</button>
      <button id="lyrics-fs-mode" title="Показать весь текст">≡</button>
      <div id="promo-track-info" style="display:none;">
        <img id="promo-track-cover" src="" alt="Track cover" />
        <div id="promo-track-info-text">
          <div id="promo-track-title"></div>
          <div id="promo-track-artist"></div>
        </div>
      </div>
      <div id="lyrics-fs-grid">
        <div id="lyrics-fs-meta">
          <img id="lyrics-fs-cover" src="" alt="cover" />
          <div id="lyrics-fs-video-wrap">
          <video id="lyrics-fs-video-embed" playsinline></video>
            <img id="lyrics-fs-video-cover" alt="cover" />
          </div>
          <div id="lyrics-fs-title"></div>
          <div id="lyrics-fs-artist"></div>
        </div>
        <div id="lyrics-fs-panel">
          <div class="lyrics-fs-fade top"></div>
          <div id="lyrics-fs-inner"><div id="lyrics-fs-list"></div><div id="lyrics-fs-dots"><span class="lyrics-dot"></span><span class="lyrics-dot"></span><span class="lyrics-dot"></span><span class="lyrics-dot"></span></div></div>
          <div class="lyrics-fs-fade bottom"></div>
        </div>
      </div>
    </div>
    <div id="queue-panel">
      <div id="queue-header">
        <span id="queue-title">Очередь воспроизведения</span>
        <button id="queue-close">Закрыть</button>
      </div>
      <ul id="queue-list"></ul>
    </div>
    <button id="queue-fab"><span class="icon">☰</span> Очередь</button>
      <div id="video-panel" style="display:none; position: fixed; right: 12px; bottom: 76px; width: 420px; max-height: 55vh; background: #000; color: #fff; border: 1px solid #242424; border-radius: 12px; box-shadow: 0 12px 30px rgba(0,0,0,.5); overflow: hidden;">
        <button id="video-close" title="Закрыть" style="position:absolute; top:8px; right:8px; width:28px; height:28px; border:none; border-radius:50%; background:#2a2a2a; color:#b3b3b3; cursor:pointer; display:grid; place-items:center; z-index:2;">×</button>
        <div style="margin:0; padding:0;">
          <video id="inline-video" style="display:none; width:100%; height:auto; max-height:55vh; background:#000;" controls playsinline></video>
          <img id="inline-cover" alt="cover" style="display:none; width:100%; height:auto; max-height:55vh; object-fit:cover; background:#000;" />
        </div>
      </div>
      <div id="crossfade-panel" style="display:none; position: fixed; right: 12px; bottom: 76px; width: 380px; max-height: 60vh; background: rgba(40, 40, 40, 0.98); color: #fff; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.05) inset; overflow: hidden; z-index: 10000; backdrop-filter: blur(20px) saturate(180%); -webkit-backdrop-filter: blur(20px) saturate(180%);">
        <button id="crossfade-close" title="Закрыть" style="position:absolute; top:12px; right:12px; width:32px; height:32px; border:none; border-radius:50%; background:rgba(255,255,255,0.1); color:#b3b3b3; cursor:pointer; display:grid; place-items:center; z-index:2; font-size:1.2rem; transition:all 0.2s ease;">×</button>
        <div style="padding:20px;">
          <h3 style="margin:0 0 20px 0; font-size:1.2rem; font-weight:700; letter-spacing:-0.3px;">Умный кроссфейд</h3>
          <div style="margin-bottom:20px;">
            <label style="display:flex; align-items:center; gap:12px; margin-bottom:0; cursor:pointer; padding:10px; border-radius:10px; transition:background 0.2s ease;">
              <input type="checkbox" id="crossfade-toggle" style="margin:0; width:20px; height:20px; accent-color:#1ed760; cursor:pointer;">
              <span style="font-size:0.95rem; font-weight:500;">Включить кроссфейд</span>
            </label>
          </div>
          <div style="margin-bottom:20px;">
            <label style="display:block; margin-bottom:12px; font-size:0.9rem; font-weight:500; color:#b3b3b3;">Длительность (сек):</label>
            <input type="range" id="crossfade-duration" min="2" max="20" value="8" style="width:100%; margin-bottom:8px; accent-color:#1ed760; cursor:pointer;">
            <div style="display:flex; justify-content:space-between; font-size:0.85rem; color:#888; font-weight:500;">
              <span>2с</span>
              <span id="crossfade-duration-value" style="color:#1ed760; font-weight:600;">8с</span>
              <span>20с</span>
            </div>
          </div>
          <div style="margin-bottom:20px;">
            <label style="display:block; margin-bottom:12px; font-size:0.9rem; font-weight:500; color:#b3b3b3;">Агрессивность:</label>
            <input type="range" id="crossfade-aggressiveness" min="0" max="1" step="0.1" value="0.7" style="width:100%; margin-bottom:8px; accent-color:#1ed760; cursor:pointer;">
            <div style="display:flex; justify-content:space-between; font-size:0.85rem; color:#888; font-weight:500;">
              <span>Мягко</span>
              <span id="crossfade-aggressiveness-value" style="color:#1ed760; font-weight:600;">70%</span>
              <span>Жёстко</span>
            </div>
          </div>
          <div id="crossfade-preview" style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:16px; font-size:0.85rem; color:#b3b3b3; line-height:1.6;">
            <div style="font-weight:600; color:#fff; margin-bottom:8px;">Анализ совместимости:</div>
            <div>Анализ треков для оптимального перехода...</div>
          </div>
        </div>
      </div>
  `;
  
  // Global back button removed per design (caused overlap). Use SPA navigation instead.
  
  const API_ORIGIN = (window.location && window.location.protocol && window.location.protocol.startsWith('http'))
    ? window.location.origin
    : (window.API_ORIGIN || 'http://localhost:8888');
  const api = (path) => (String(path).startsWith('http') ? path : API_ORIGIN + path);
  const getLikesAPI = (opts) => (typeof window.getLikesAPI === 'function'
    ? window.getLikesAPI(opts)
    : api('/muzic2/src/api/windows_likes.php' + (opts && opts.lite ? '?lite=1&limit=2000' : '')));
  
  // Elements (scoped to player container to avoid ID conflicts on page)
  const playerContainer = playerRoot.querySelector('#player');
  const audio = playerRoot.querySelector('#audio');
  if (audio) {
    // Reduce network pressure on initial page render.
    audio.preload = 'none';
  }
  
  const playBtn = playerContainer.querySelector('#play-btn');
  const playIcon = playerContainer.querySelector('#play-icon');
  const pauseIcon = playerContainer.querySelector('#pause-icon');
  const prevBtn = playerContainer.querySelector('#prev-btn');
  const nextBtn = playerContainer.querySelector('#next-btn');
  
  const shuffleBtn = playerContainer.querySelector('#shuffle-btn');
  const repeatBtn = playerContainer.querySelector('#repeat-btn');
  const lyricsBtn = playerContainer.querySelector('#lyrics-btn');
  const seekBar = playerContainer.querySelector('#seek-bar');
  const trackTitle = playerContainer.querySelector('#track-title');
  const trackArtist = playerContainer.querySelector('#track-artist');
  const currentTimeEl = playerContainer.querySelector('#current-time');
  const durationEl = playerContainer.querySelector('#duration');
  const cover = playerContainer.querySelector('#cover');
  const volumeBar = playerContainer.querySelector('#volume-bar');
  const volumeBtn = playerContainer.querySelector('#volume-btn');
  const fullscreenBtn = playerContainer.querySelector('#fullscreen-btn');
  const videoBtn = playerContainer.querySelector('#video-btn');
  const videoMiniBtn = playerContainer.querySelector('#video-mini');
  const queueBtn = playerContainer.querySelector('#queue-btn');
  const likeBtn = playerContainer.querySelector('#like-btn');
  const crossfadeBtn = playerContainer.querySelector('#crossfade-btn');
  const queuePanel = playerRoot.querySelector('#queue-panel');
  const queueFab = playerRoot.querySelector('#queue-fab');
  const queueMini = playerRoot.querySelector('#queue-mini');
  const queueClose = playerRoot.querySelector('#queue-close');
  const queueList = playerRoot.querySelector('#queue-list');
  const videoPanel = playerRoot.querySelector('#video-panel');
  const videoClose = playerRoot.querySelector('#video-close');
  const inlineVideo = playerRoot.querySelector('#inline-video');
  const inlineCover = playerRoot.querySelector('#inline-cover');
  const crossfadePanel = playerRoot.querySelector('#crossfade-panel');
  const crossfadeClose = playerRoot.querySelector('#crossfade-close');
  const fullscreenOverlay = playerRoot.querySelector('#fullscreen-overlay');
  const fullscreenCover = playerRoot.querySelector('#fullscreen-cover');
  const fullscreenVideo = playerRoot.querySelector('#fullscreen-video');
  const fullscreenTitle = playerRoot.querySelector('#fullscreen-title');
  const fullscreenArtist = playerRoot.querySelector('#fullscreen-artist');
  const fullscreenClose = playerRoot.querySelector('#fullscreen-close');
  const fullscreenBack = playerRoot.querySelector('#fullscreen-back');
  const carouselOverlay = playerRoot.querySelector('#carousel-overlay');
  const carouselTrack = playerRoot.querySelector('#carousel-track');
  const carouselCurrentCover = playerRoot.querySelector('#carousel-current-cover');
  const carouselCurrentReflection = playerRoot.querySelector('#carousel-current-reflection');
  const carouselClose = playerRoot.querySelector('#carousel-close');
  const carouselShuffle = playerRoot.querySelector('#carousel-shuffle-btn');
  const carouselPrev = playerRoot.querySelector('#carousel-prev-btn');
  const carouselNext = playerRoot.querySelector('#carousel-next-btn');
  const carouselPlay = playerRoot.querySelector('#carousel-play-btn');
  const carouselPlayIcon = playerRoot.querySelector('#carousel-play-icon');
  const carouselPauseIcon = playerRoot.querySelector('#carousel-pause-icon');
  const carouselVolume = playerRoot.querySelector('#carousel-volume-btn');
  const carouselTrackTitle = playerRoot.querySelector('#carousel-track-title');
  const carouselTrackArtist = playerRoot.querySelector('#carousel-track-artist');
  const carouselCurrentTime = playerRoot.querySelector('#carousel-current-time');
  const carouselDuration = playerRoot.querySelector('#carousel-duration');
  const carouselSeekBar = playerRoot.querySelector('#carousel-seek-bar');
  const carouselSeekBarProgress = playerRoot.querySelector('#carousel-seek-bar-progress');
  let isCarouselMode = false;
  let carouselCurrentIndex = 0;
  let carouselIsDragging = false;
  let carouselDragStartPos = 0;
  let carouselDragOffset = 0;
  let carouselVelocity = 0;
  let carouselLastDragTime = Date.now();
  const lyricsContainer = playerContainer.querySelector('#lyrics-container');
  let lyricsFs = playerRoot.querySelector('#lyrics-fs');
  if (!lyricsFs) {
    console.warn('⚠️ lyricsFs not found initially, will try again on click');
  }
  const lyricsFsUnderlay = playerRoot.querySelector('#lyrics-fs-underlay');
  const lyricsFsUnderlayImg = playerRoot.querySelector('#lyrics-fs-underlay-img');
  const lyricsFsUnderlayVideo = playerRoot.querySelector('#lyrics-fs-underlay-video');
  const lyricsFsBgImg = playerRoot.querySelector('#lyrics-fs-bg-img');
  const lyricsFsBgVideo = playerRoot.querySelector('#lyrics-fs-bg-video');
  const lyricsFsClose = playerRoot.querySelector('#lyrics-fs-close');
  const lyricsFsMode = playerRoot.querySelector('#lyrics-fs-mode');
  const lyricsFsInner = playerRoot.querySelector('#lyrics-fs-inner');
  const lyricsFsList = playerRoot.querySelector('#lyrics-fs-list');
  const lyricsFsCover = playerRoot.querySelector('#lyrics-fs-cover');
  const lyricsFsTitle = playerRoot.querySelector('#lyrics-fs-title');
  const lyricsFsArtist = playerRoot.querySelector('#lyrics-fs-artist');
  const lyricsFsDots = playerRoot.querySelector('#lyrics-fs-dots');
  const lyricsFsPanel = playerRoot.querySelector('#lyrics-fs-panel');
  const lyricsFsMeta = playerRoot.querySelector('#lyrics-fs-meta');
  const lyricsFsFades = playerRoot.querySelectorAll('.lyrics-fs-fade');
  // Prevent pausing video when audio pause is triggered as part of handing control to video
  let suppressVideoPauseOnce = false;
  // Visibility-based rendering control for karaoke (do not pause media)
  let lyricsRenderSuspended = false;
  
  // Initialize Smart Crossfade
  let smartCrossfade = null;
  try {
    if (typeof SmartCrossfade !== 'undefined') {
      smartCrossfade = new SmartCrossfade();
      // Initialize button state after a short delay to ensure DOM is ready
      setTimeout(() => {
        updateCrossfadeButtonState();
      }, 100);
    }
  } catch (e) {
    console.warn('SmartCrossfade not available:', e);
  }


  // Media keys support
  function setupMediaKeys() {
    // Handle keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      // Don't interfere with input fields
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
        return;
      }

      switch (e.key) {
        case 'F7':
          e.preventDefault();
          if (prevBtn) prevBtn.click();
          break;
        case 'F9':
          e.preventDefault();
          if (nextBtn) nextBtn.click();
          break;
        case 'F8':
          e.preventDefault();
          if (playBtn) playBtn.click();
          break;
        case 'F6':
          e.preventDefault();
          if (shuffleBtn) shuffleBtn.click();
          break;
        case 'F10':
          e.preventDefault();
          if (repeatBtn) repeatBtn.click();
          break;
        case ' ': // Spacebar
          e.preventDefault();
          if (playBtn) playBtn.click();
          break;
        case 'ArrowLeft':
          e.preventDefault();
          if (audio.currentTime > 10) {
            audio.currentTime -= 10;
          } else {
            audio.currentTime = 0;
          }
          break;
        case 'ArrowRight':
          e.preventDefault();
          if (audio.currentTime < audio.duration - 10) {
            audio.currentTime += 10;
          } else {
            audio.currentTime = audio.duration;
          }
          break;
        case 'ArrowUp':
          e.preventDefault();
          if (volumeBar) {
            const currentVolume = parseFloat(volumeBar.value) || 0;
            const newVolume = Math.min(1, currentVolume + 0.1);
            volumeBar.value = newVolume;
            audio.volume = newVolume;
            updateVolumeUI();
          }
          break;
        case 'ArrowDown':
          e.preventDefault();
          if (volumeBar) {
            const currentVolume = parseFloat(volumeBar.value) || 0;
            const newVolume = Math.max(0, currentVolume - 0.1);
            volumeBar.value = newVolume;
            audio.volume = newVolume;
            updateVolumeUI();
          }
          break;
      }
    });

    // Handle media session API for browser media controls
    if ('mediaSession' in navigator) {
      navigator.mediaSession.setActionHandler('play', () => {
        if (playBtn) playBtn.click();
      });

      navigator.mediaSession.setActionHandler('pause', () => {
        if (playBtn) playBtn.click();
      });

      navigator.mediaSession.setActionHandler('previoustrack', () => {
        if (prevBtn) prevBtn.click();
      });

      navigator.mediaSession.setActionHandler('nexttrack', () => {
        if (nextBtn) nextBtn.click();
      });

      navigator.mediaSession.setActionHandler('seekbackward', (details) => {
        const skipTime = details.seekOffset || 10;
        if (audio.currentTime > skipTime) {
          audio.currentTime -= skipTime;
        } else {
          audio.currentTime = 0;
        }
      });

      navigator.mediaSession.setActionHandler('seekforward', (details) => {
        const skipTime = details.seekOffset || 10;
        if (audio.currentTime < audio.duration - skipTime) {
          audio.currentTime += skipTime;
        } else {
          audio.currentTime = audio.duration;
        }
      });

      navigator.mediaSession.setActionHandler('seekto', (details) => {
        if (details.seekTime !== undefined) {
          audio.currentTime = details.seekTime;
        }
      });

      // Update media session metadata when track changes
      function updateMediaSessionMetadata(track) {
        if (!track) return;
        
        navigator.mediaSession.metadata = new MediaMetadata({
          title: track.title || 'Неизвестный трек',
          artist: track.artist || 'Неизвестный артист',
          album: track.album || 'Неизвестный альбом',
          artwork: track.cover ? [
            { src: track.cover, sizes: '96x96', type: 'image/jpeg' },
            { src: track.cover, sizes: '128x128', type: 'image/jpeg' },
            { src: track.cover, sizes: '192x192', type: 'image/jpeg' },
            { src: track.cover, sizes: '256x256', type: 'image/jpeg' },
            { src: track.cover, sizes: '384x384', type: 'image/jpeg' },
            { src: track.cover, sizes: '512x512', type: 'image/jpeg' }
          ] : []
        });
      }

      // Update playback state
      function updateMediaSessionPlaybackState() {
        navigator.mediaSession.playbackState = isPlaying ? 'playing' : 'paused';
      }

      // Make functions available globally for use in other parts of the code
      window.updateMediaSessionMetadata = updateMediaSessionMetadata;
      window.updateMediaSessionPlaybackState = updateMediaSessionPlaybackState;
    }

    // Handle visibility change to update media session
    document.addEventListener('visibilitychange', () => {
      if ('mediaSession' in navigator && !document.hidden) {
        updateMediaSessionPlaybackState();
      }
    });
  }

  // Initialize media keys
  setupMediaKeys();


  const lyricsFsDotsArr = lyricsFsDots ? Array.from(lyricsFsDots.querySelectorAll('.lyrics-dot')) : [];
  const lyricsFsVideoWrap = playerRoot.querySelector('#lyrics-fs-video-wrap');
  const lyricsFsVideo = playerRoot.querySelector('#lyrics-fs-video-embed');
  const lyricsFsVideoCover = playerRoot.querySelector('#lyrics-fs-video-cover');
  
  function pinPlayerForOverlay(active) {
    if (!playerContainer) return;
    if (active) {
      playerContainer.style.position = 'fixed';
      playerContainer.style.left = '0';
      playerContainer.style.right = '0';
      playerContainer.style.bottom = '0';
      playerContainer.style.zIndex = '12000';
    } else {
      playerContainer.style.position = '';
      playerContainer.style.left = '';
      playerContainer.style.right = '';
      playerContainer.style.bottom = '';
      playerContainer.style.zIndex = '';
    }
  }

  // Lyrics state
  let lyricsLines = []; // [{time:number, text:string}]
  let lyricsVisible = false;
  let promoModeActive = false;
  let promoWasPlaying = false;
  let promoPreviousCoverSrc = '';
  let promoPayload = null;
  const promoVideoListeners = [];
  let promoPrevPlayerCover = '';
  let promoPrevPlayerTitle = '';
  let promoPrevPlayerArtist = '';
  let lyricsRenderingLock = false; // Флаг для блокировки updateLyricsHighlight во время рендеринга
  // Top guard so lyrics can't overlap artist/cover block in fullscreen
  function getLyricsTopGuardPx() {
    try {
      const meta = playerRoot.querySelector('#lyrics-fs-meta');
      const grid = playerRoot.querySelector('#lyrics-fs-grid');
      const metaRect = meta && meta.getBoundingClientRect ? meta.getBoundingClientRect() : null;
      const gridRect = grid && grid.getBoundingClientRect ? grid.getBoundingClientRect() : null;
      if (metaRect && gridRect) {
        const metaH = Math.max(0, Math.round(metaRect.bottom - gridRect.top));
        return metaH + 24; // safety margin
      }
      const isSmall = window.matchMedia && window.matchMedia('(max-width: 600px)').matches;
      const isMobile = window.matchMedia && window.matchMedia('(max-width: 960px)').matches;
      // На мобильных минимальный guard чтобы видеть первые строки
      return isSmall ? 100 : (isMobile ? 90 : 110);
    } catch(_) { return 132; }
  }
  // Ensure karaoke panel sits flush against the player (no gap)
  function adjustKaraokeBottom() {
    try {
      const playerEl = playerRoot && playerRoot.querySelector ? playerRoot.querySelector('#player') : null;
      const h = playerEl ? playerEl.offsetHeight || 0 : 0;
      if (lyricsFs && typeof h === 'number') {
        lyricsFs.style.bottom = h + 'px';
      }
      if (lyricsContainer && typeof h === 'number') {
        // inline karaoke container should also align just above the player
        lyricsContainer.style.bottom = h + 'px';
      }
    } catch(_) {}
  }

  let currentLyricsIndex = -1;
  let lastTrackTitle = '';
  let lastTrackArtist = '';
  let userScrolling = false;
  let scrollResumeTimer = null;
  let lyricsSyncLocked = false; // lock centering until first timestamp

  // When tab visibility changes, suspend/resume lyrics rendering only
  document.addEventListener('visibilitychange', () => {
    lyricsRenderSuspended = !!document.hidden;
    if (lyricsRenderSuspended && lyricsVisible && lyricsFsList) {
      // While hidden: clear visual highlight to avoid a stuck active line
      try {
        const activeNodes = lyricsFsList.querySelectorAll('.lyric-line.active');
        activeNodes && activeNodes.forEach && activeNodes.forEach(el => { try { el.classList.remove('active'); } catch(_){} });
      } catch(_) {}
    }
    if (!lyricsRenderSuspended && lyricsVisible) {
      // Resync highlight to current playback position without seeking
      let t = 0;
      try {
        if (lyricsFsVideo && lyricsFsVideoWrap && lyricsFsVideo.style.display === 'block' && !isNaN(lyricsFsVideo.currentTime)) {
          t = lyricsFsVideo.currentTime || 0;
        } else if (videoPanel && videoPanel.style.display === 'block' && inlineVideo && !isNaN(inlineVideo.currentTime)) {
          t = inlineVideo.currentTime || 0;
        } else {
          t = audio.currentTime || 0;
        }
      } catch(_) { t = audio.currentTime || 0; }
      try { currentLyricsIndex = -1; } catch(_) {}
      try { lyricsFs && lyricsFs.classList.remove('reviewing'); } catch(_) {}
      try { updateLyricsHighlight(t); } catch(_) {}
      try {
        if (typeof scrollLyricsToAnchorForIndex === 'function' && Array.isArray(lyricsLines) && lyricsLines.length) {
          let idx = 0; for (let i=0;i<lyricsLines.length;i++){ if (lyricsLines[i].time <= t) idx = i; else break; }
          scrollLyricsToAnchorForIndex(idx);
        }
      } catch(_) {}
      try { adjustKaraokeBottomOffset && adjustKaraokeBottomOffset(); } catch(_) {}
    }
  });

  // Normalize karaoke on page show (e.g., returning from app switch on iOS)
  window.addEventListener('pageshow', () => {
    try { lyricsFs && lyricsFs.classList.remove('reviewing'); } catch(_) {}
    let t = 0; try { t = audio.currentTime || 0; } catch(_) {}
    try { currentLyricsIndex = -1; } catch(_) {}
    
    // Исправлено: агрессивно очищаем ВСЕ фантомные строки при перезаходе в караоке режим
    try {
      if (lyricsFsList) {
        const allLines = lyricsFsList.querySelectorAll('.lyric-line');
        allLines.forEach(line => {
          line.classList.remove('active', 'past');
          // Принудительно очищаем все стили
          line.style.display = '';
          line.style.opacity = '';
          line.style.visibility = '';
          line.style.transform = '';
        });
      }
      if (lyricsContainer) {
        const inlineLines = lyricsContainer.querySelectorAll('.lyric-line');
        inlineLines.forEach(line => {
          line.classList.remove('active', 'past');
          line.style.display = '';
          line.style.opacity = '';
          line.style.visibility = '';
          line.style.transform = '';
        });
      }
    } catch(_) {}
    
    try { updateLyricsHighlight(t); } catch(_) {}
    try {
      if (typeof scrollLyricsToAnchorForIndex === 'function' && Array.isArray(lyricsLines) && lyricsLines.length) {
        let idx = 0; for (let i=0;i<lyricsLines.length;i++){ if (lyricsLines[i].time <= t) idx = i; else break; }
        scrollLyricsToAnchorForIndex(idx);
      }
    } catch(_) {}
    try { adjustKaraokeBottomOffset && adjustKaraokeBottomOffset(); } catch(_) {}
  });

  // Keep active line anchored at ~32% helper
  function scrollLyricsToAnchorForIndex(targetIndex) {
    if (!lyricsVisible || !lyricsFsInner || !lyricsFsList) return;
    const fsLines = lyricsFsList.querySelectorAll('.lyric-line');
    const el = fsLines && fsLines[targetIndex] ? fsLines[targetIndex] : null;
    if (!el) return;
    const isSmall = window.matchMedia && window.matchMedia('(max-width: 600px)').matches;
    const isMobile = window.matchMedia && window.matchMedia('(max-width: 960px)').matches;
    // На мобильных просто позиционируем активную строку в верхней части видимой области
    if (isSmall || isMobile) {
      // На мобильных: активная строка на 25% от верха видимой области
      const anchor = Math.round(lyricsFsInner.clientHeight * 0.25);
      const top = Math.max(0, el.offsetTop - anchor);
      try { lyricsFsInner.scrollTo({ top, behavior: 'smooth' }); } catch(_) {}
      return;
    }
    // На десктопе используем обычную логику
    const anchor = Math.round(lyricsFsInner.clientHeight * 0.32);
    const guard = getLyricsTopGuardPx();
    const top = Math.max(guard, el.offsetTop - anchor);
    try { lyricsFsInner.scrollTo({ top, behavior: 'smooth' }); } catch(_) {}
  }

  // Click-to-seek on lyrics lines (inline and fullscreen karaoke)
  function handleLyricClick(event) {
    const target = event.target && event.target.closest ? event.target.closest('.lyric-line') : null;
    if (!target) return;
    const timeAttr = target.getAttribute('data-time');
    const idxAttr = target.getAttribute('data-idx');
    const seekTime = parseFloat(timeAttr || '0');
    const targetIdx = parseInt(idxAttr || '-1', 10);
    if (!isFinite(seekTime) || seekTime < 0) return;

    // Pre-scroll to avoid overlapping with meta/cover before seeking
    try {
      if (lyricsFsInner && lyricsFsList && isFinite(targetIdx) && targetIdx >= 0) {
        const isSmall = window.matchMedia && window.matchMedia('(max-width: 600px)').matches;
        const isMobile = window.matchMedia && window.matchMedia('(max-width: 960px)').matches;
        const fsLines = lyricsFsList.querySelectorAll('.lyric-line');
        const el = fsLines && fsLines[targetIdx] ? fsLines[targetIdx] : null;
        if (el) {
          if (isSmall || isMobile) {
            // На мобильных: активная строка на 25% от верха
            const anchor = Math.round(lyricsFsInner.clientHeight * 0.25);
            const top = Math.max(0, el.offsetTop - anchor);
            lyricsFsInner.scrollTo({ top, behavior: 'auto' });
          } else {
            // На десктопе обычная логика
            const anchor = Math.round((lyricsFsInner.clientHeight) * 0.32);
            const guard = getLyricsTopGuardPx();
          const top = Math.max(guard, el.offsetTop - anchor);
          lyricsFsInner.scrollTo({ top, behavior: 'auto' });
          }
        }
      }
    } catch(_) {}

    let usedVideo = false;
    // If karaoke video is shown, seek video; otherwise seek audio
    if (lyricsFsVideo && lyricsFsVideo.style.display === 'block' && !lyricsFsVideo.ended) {
      try { lyricsFsVideo.currentTime = seekTime; lyricsFsVideo.play().catch(()=>{}); usedVideo = true; } catch(_) {}
    }
    if (!usedVideo && typeof audio !== 'undefined' && audio) {
      try { audio.currentTime = seekTime; audio.play().catch(()=>{}); } catch(_) {}
    }

    // Reset active classes to avoid stale highlight, then re-evaluate and scroll to anchor
    try {
      const inlineActives = lyricsContainer ? lyricsContainer.querySelectorAll('.lyric-line.active') : [];
      inlineActives && inlineActives.forEach && inlineActives.forEach(el => el.classList.remove('active'));
      const fsActives = lyricsFsList ? lyricsFsList.querySelectorAll('.lyric-line.active') : [];
      fsActives && fsActives.forEach && fsActives.forEach(el => el.classList.remove('active'));
    } catch(_) {}
    try { currentLyricsIndex = -1; } catch(_) { currentLyricsIndex = -1; }
    try { updateLyricsHighlight(seekTime); } catch(_) {}
    if (lyricsVisible) {
      userScrolling = false;
      if (scrollResumeTimer) { try { clearTimeout(scrollResumeTimer); } catch(_) {} scrollResumeTimer = null; }
      try { scrollLyricsToAnchorForIndex(Math.max(0, isFinite(targetIdx) ? targetIdx : 0)); } catch(_) {}
    }
  }

  function parseLRC(lrcText) {
    const lines = [];
    if (!lrcText) return lines;
    const lineTagRe = /\[(\d{1,2})[:.](\d{2})(?:[.:](\d{1,2}))?\]/g; // supports mm:ss.xx and mm.ss.cc
    const wordTagRe = /<(\d{1,2})[:.](\d{2})(?:[.:](\d{1,2}))?>\s*([^<]*)/g;
    const cleanupWordTagRe = /<(\d{1,2})[:.](\d{2})(?:[.:](\d{1,2}))?>/g;

    lrcText.split(/\r?\n/).forEach(raw => {
      if (!raw) return;
      const lineTimes = [];
      let m;
      lineTagRe.lastIndex = 0;
      while ((m = lineTagRe.exec(raw)) !== null) {
        const min = parseInt(m[1], 10) || 0;
        const sec = parseInt(m[2], 10) || 0;
        const cs = parseInt(m[3] || '0', 10) || 0;
        const t = min * 60 + sec + (cs / 100);
        lineTimes.push(t);
      }

      // Remove line-level tags for further processing
      const withoutLineTags = raw.replace(lineTagRe, '').trim();
      if (!withoutLineTags) return;

      // Parse word-level timings, if present
      const words = [];
      wordTagRe.lastIndex = 0;
      while ((m = wordTagRe.exec(withoutLineTags)) !== null) {
        const min = parseInt(m[1], 10) || 0;
        const sec = parseInt(m[2], 10) || 0;
        const cs = parseInt(m[3] || '0', 10) || 0;
        const t = min * 60 + sec + (cs / 100);
        const rawSegment = (m[4] || '').replace(/\s+/g, ' ').trim();
        if (rawSegment)
          words.push({ time: t, text: rawSegment });
      }

      // Build display text by stripping word timing tags
      let displayText = withoutLineTags.replace(cleanupWordTagRe, ' ').replace(/\s+/g, ' ').trim();
      if (!displayText && words.length) {
        displayText = words.map(w => w.text).join(' ');
      }
      if (!displayText) return;

      // If no explicit line timestamp but words exist, anchor to first word time
      if (!lineTimes.length && words.length) {
        lineTimes.push(words[0].time);
      }

      if (!lineTimes.length) return;

      const sortedWords = words.slice().sort((a, b) => a.time - b.time);

      lineTimes.forEach(t => {
        lines.push({
          time: t,
          text: displayText,
          words: sortedWords.map(w => ({ ...w })),
          activeWord: -1
        });
      });
    });

    return lines.sort((a, b) => a.time - b.time);
  }

  async function loadLyricsForTrack(trackId) {
    try {
      if (!lyricsContainer) return;
      // Always query lyrics API; if id нет, отправляем title/artist
      let url = '';
      if (trackId) { url = '/muzic2/src/api/lyrics.php?track_id='+encodeURIComponent(trackId); }
      else {
        const params = new URLSearchParams();
        if (lastTrackTitle) params.set('title', lastTrackTitle);
        if (lastTrackArtist) params.set('artist', lastTrackArtist);
        url = '/muzic2/src/api/lyrics.php?'+params.toString();
      }
      lyricsContainer.innerHTML = '<div class="lyric-line">Загрузка…</div>';
      const res = await fetch(url);
      if (!res.ok) { lyricsLines = []; renderLyrics(); return; }
      let data = null; try { data = await res.json(); } catch(e){ data = null; }
      const lrcText = data && typeof data.lrc === 'string' ? data.lrc : '';
      lyricsLines = parseLRC(lrcText);
      // If no [mm:ss.xx] tags, approximate timings line-by-line
      if (!lyricsLines.length && lrcText.trim()) {
        const rawLines = lrcText.split(/\r?\n/).filter(s=>s.trim()!=='');
        if (rawLines.length > 0) {
          const total = (isFinite(audio && audio.duration) && audio.duration>0) ? audio.duration : (rawLines.length * 3);
          const step = Math.max(1, total / (rawLines.length + 1));
          lyricsLines = rawLines.map((txt, i) => ({ time: (i+1)*step, text: txt, words: [], activeWord: -1 }));
        } else {
          lyricsLines = [{ time: 0, text: lrcText, words: [], activeWord: -1 }];
        }
      }
      renderLyrics();
    } catch(e) {
      lyricsLines = [];
      renderLyrics();
    }
  }

  async function fetchAndRenderLyricsDirect() {
    try {
      // Находим актуальный список (может быть создан динамически)
      const currentLyricsFsList = lyricsFsList || window._lyricsFsList || document.getElementById('lyrics-fs-list');
      
      // Показываем загрузку в нужном контейнере
      if (currentLyricsFsList) {
        currentLyricsFsList.innerHTML = '<div class="lyric-line">Загрузка…</div>';
      } else if (lyricsContainer) {
        lyricsContainer.innerHTML = '<div class="lyric-line">Загрузка…</div>';
      }
      
      // Try title+artist first
      const params = new URLSearchParams();
      if (lastTrackTitle) params.set('title', lastTrackTitle);
      if (lastTrackArtist) params.set('artist', lastTrackArtist);
      let res = await fetch(api('/muzic2/src/api/lyrics.php?' + params.toString()));
      let data = null; try { data = await res.json(); } catch(_) { data = null; }
      let lrcText = data && typeof data.lrc === 'string' ? data.lrc : '';
      if (!lrcText && currentTrackId) {
        // fallback to id
        res = await fetch(api('/muzic2/src/api/lyrics.php?track_id=' + encodeURIComponent(currentTrackId)));
        try { data = await res.json(); } catch(_) { data = null; }
        lrcText = data && typeof data.lrc === 'string' ? data.lrc : '';
      }
      lyricsLines = parseLRC(lrcText);
      if (!lyricsLines.length && lrcText.trim()) {
        lyricsLines = [{ time: 0, text: lrcText, words: [], activeWord: -1 }];
      }
      renderLyrics();
      // НЕ вызываем updateLyricsHighlight сразу - строка уже рендерится с правильным классом
      // Обновление произойдет автоматически при следующем timeupdate
    } catch(_) {
      lyricsContainer.innerHTML = '<div class="lyric-line">Нет текста</div>';
    }
  }

  function buildLyricLineHTML(line, idx, isActive = false) {
    const safeIdx = Number(idx);
    const activeClass = isActive ? ' active' : '';
    const baseAttrs = `data-idx="${safeIdx}" data-time="${line.time}"`;
    if (Array.isArray(line.words) && line.words.length) {
      const punctuationRe = /^[,.;:!?…\-—)]+$/;
      let html = '';
      let renderedWordIndex = 0; // Индекс для отрендеренных слов
      line.words.forEach((word, wi) => {
        if (!word || typeof word.text !== 'string') return;
        const wordText = word.text.trim();
        if (!wordText) return;
        const needsSpace = renderedWordIndex > 0 && !punctuationRe.test(wordText);
        // Используем реальный индекс слова в массиве words, а не индекс отрендеренных слов
        html += (needsSpace ? ' ' : '') + `<span class="lyric-word" data-word-idx="${wi}" data-time="${word.time}">${escapeHtml(wordText)}</span>`;
        renderedWordIndex++;
      });
      if (!html) html = escapeHtml(line.text || '');
      return `<div class="lyric-line${activeClass}" ${baseAttrs}><span class="lyric-text-inner">${html}</span></div>`;
    }
    return `<div class="lyric-line${activeClass}" ${baseAttrs}><span class="lyric-text-inner">${escapeHtml(line.text || '')}</span></div>`;
  }

  function renderLyrics() {
    if (!lyricsContainer) return;
    if (!lyricsLines.length) { 
      lyricsContainer.innerHTML = '<div id="lyrics-list"><div class="lyric-line">Нет текста</div></div>'; 
      if (lyricsFsList) lyricsFsList.innerHTML = '<div class="lyric-line">Нет текста</div>'; 
      return; 
    }
    
    lyricsLines.forEach(line => {
      if (!Array.isArray(line.words)) line.words = [];
      if (typeof line.activeWord !== 'number') line.activeWord = -1;
    });
    
    // Определяем активную строку
    let initialActiveIdx = -1;
    try {
      const currentTime = audio && audio.currentTime ? audio.currentTime : 0;
      let idx = -1;
      while ((idx + 1) < lyricsLines.length && lyricsLines[idx + 1].time <= currentTime + 0.05) {
        idx++;
      }
      initialActiveIdx = idx;
    } catch(_) {}
    
    // Блокируем updateLyricsHighlight во время рендеринга
    lyricsRenderingLock = true;
    
    // Рендерим строки БЕЗ класса .active
    const listHtml = lyricsLines.map((l, i) => buildLyricLineHTML(l, i, false)).join('');
    
    // Устанавливаем currentLyricsIndex ПЕРЕД рендерингом
    currentLyricsIndex = initialActiveIdx;
    
    // Рендерим строки полностью скрытыми
    lyricsContainer.innerHTML = `<div id="lyrics-list" style="opacity: 0; visibility: hidden;">${listHtml}</div>`;
    if (lyricsFsList) {
      lyricsFsList.innerHTML = listHtml;
    }
    
    // Получаем все строки
    const allLines = lyricsContainer.querySelectorAll('.lyric-line');
    const fsAllLines = lyricsFsList ? lyricsFsList.querySelectorAll('.lyric-line') : [];
    const listEl = lyricsContainer.querySelector('#lyrics-list');
    
    // Подход Яндекса: ВСЕ строки одного размера, при активации только меняется цвет и opacity
    // Все строки уже имеют font-size: 1.6rem - переносы одинаковые автоматически
    // Сначала устанавливаем начальное состояние (opacity: 0) для плавного появления
    allLines.forEach((line, idx) => {
      const textInner = line.querySelector('.lyric-text-inner') || line;
      // Начальное состояние - все строки скрыты
      if (textInner) {
        textInner.style.transition = 'none';
        textInner.style.opacity = '0';
      }
      line.style.transition = 'none';
      line.style.opacity = '0';
    });
    
    // Для fullscreen строк тоже устанавливаем начальное состояние
    fsAllLines.forEach((line) => {
      line.style.transition = 'none';
      line.style.opacity = '0';
    });
    
    // Принудительно триггерим reflow
    void listEl?.offsetHeight;
    
    // Теперь устанавливаем финальные состояния и включаем transition для плавного появления
    allLines.forEach((line, idx) => {
      const textInner = line.querySelector('.lyric-text-inner') || line;
      if (idx !== initialActiveIdx) {
        // Неактивная строка - серый цвет, низкая opacity
        if (textInner) {
          textInner.style.transition = '';
          textInner.style.color = '#b3b3b3';
          textInner.style.opacity = '0.35';
        }
        line.style.transition = '';
        line.style.opacity = '0.26';
        line.classList.remove('active');
        line.classList.add('past');
      } else {
        // Активная строка - белый цвет, полная opacity
        if (textInner) {
          textInner.style.transition = '';
          textInner.style.color = '#fff';
          textInner.style.opacity = '1';
        }
        line.style.transition = '';
        line.style.opacity = '1';
        line.classList.add('active');
        line.classList.remove('past');
      }
    });
    
    // Для активной строки в fullscreen
    if (initialActiveIdx >= 0 && fsAllLines[initialActiveIdx]) {
      const activeLine = fsAllLines[initialActiveIdx];
      activeLine.style.transition = '';
      activeLine.style.opacity = '1';
      activeLine.classList.add('active');
    }
    
    // Плавно показываем остальные fullscreen строки
    fsAllLines.forEach((line, idx) => {
      if (idx !== initialActiveIdx) {
        line.style.transition = '';
        line.style.opacity = '0.26';
      }
    });
    
    // ШАГ 5: Показываем строки плавно
    if (listEl) {
      listEl.style.visibility = 'visible';
      // Используем requestAnimationFrame для синхронизации с кадром браузера
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          listEl.style.opacity = '1';
          listEl.style.transition = 'opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
        });
      });
    }
    
    // Обновляем подсветку слов для активной строки
    if (initialActiveIdx >= 0 && Array.isArray(lyricsLines[initialActiveIdx].words) && lyricsLines[initialActiveIdx].words.length) {
      const words = lyricsLines[initialActiveIdx].words;
      const currentTime = audio && audio.currentTime ? audio.currentTime : 0;
      let wordIdx = -1;
      for (let i = 0; i < words.length; i++) {
        if (words[i] && typeof words[i].time === 'number' && words[i].time <= currentTime + 0.02) {
          wordIdx = i;
        } else {
          break;
        }
      }
      setActiveWord(initialActiveIdx, wordIdx);
    }
    
    // Разблокируем updateLyricsHighlight
    lyricsRenderingLock = false;
    // Bind click handlers (event delegation) once per render
    try {
      const inlineList = lyricsContainer.querySelector('#lyrics-list');
      if (inlineList) {
        inlineList.removeEventListener('click', handleLyricClick);
        inlineList.addEventListener('click', handleLyricClick);
      }
    } catch(_) {}
    try {
      if (lyricsFsInner) {
        lyricsFsInner.removeEventListener('click', handleLyricClick);
        lyricsFsInner.addEventListener('click', handleLyricClick);
      }
    } catch(_) {}
    // Center from the start
    const list = lyricsContainer.querySelector('#lyrics-list');
    // Убираем анимации появления
    // No forced highlight; render neutral until timeupdate
    if (lyricsFsList && lyricsFsInner) {
      try {
        // НЕ сбрасываем currentLyricsIndex - он уже установлен правильно при рендеринге
        // Show preroll dots initially if there is a gap before first line
        if (lyricsFsDots) {
          // remove dots feature per user request
          lyricsFsDots.style.display = 'none';
          lyricsFsDotsArr && lyricsFsDotsArr.forEach(d => d.classList.remove('on'));
        }
        // Add guard padding so текст не уходит под мета-блок
        const isSmall = window.matchMedia && window.matchMedia('(max-width: 600px)').matches;
        const isMobile = window.matchMedia && window.matchMedia('(max-width: 960px)').matches;
        const anchor = Math.round((lyricsFsInner.clientHeight) * (isSmall ? 0.05 : (isMobile ? 0.05 : 0.32)));
        const guard = getLyricsTopGuardPx();
        lyricsFsList.style.paddingTop = (guard + anchor) + 'px';
        try { lyricsFsInner.scrollTop = guard; } catch(_) {}
      } catch(_) {}
    }
  }

  function setActiveWord(lineIndex, wordIndex) {
    if (lineIndex == null || lineIndex < 0) return;
    const line = lyricsLines[lineIndex];
    if (!line || !Array.isArray(line.words) || !line.words.length) {
      // Если нет слов, убираем подсветку со всех слов в строке
      const inlineLine = lyricsContainer ? lyricsContainer.querySelector(`.lyric-line[data-idx="${lineIndex}"]`) : null;
      const fsLine = lyricsFsList ? lyricsFsList.querySelector(`.lyric-line[data-idx="${lineIndex}"]`) : null;
      if (inlineLine) {
        inlineLine.querySelectorAll('.lyric-word').forEach(el => el.classList.remove('active-word'));
      }
      if (fsLine) {
        fsLine.querySelectorAll('.lyric-word').forEach(el => el.classList.remove('active-word'));
      }
      return;
    }
    const apply = (root, idx) => {
      if (!root) return;
      const wordEls = root.querySelectorAll('.lyric-word');
      if (wordEls.length === 0) return;
      wordEls.forEach((el) => {
        const wordIdxAttr = el.getAttribute('data-word-idx');
        if (wordIdxAttr === null) return;
        const wordIdx = parseInt(wordIdxAttr, 10);
        if (isNaN(wordIdx)) return;
        if (wordIdx === idx && idx >= 0) {
          el.classList.add('active-word');
        } else {
          el.classList.remove('active-word');
        }
      });
    };
    const inlineLine = lyricsContainer ? lyricsContainer.querySelector(`.lyric-line[data-idx="${lineIndex}"]`) : null;
    const fsLine = lyricsFsList ? lyricsFsList.querySelector(`.lyric-line[data-idx="${lineIndex}"]`) : null;
    apply(inlineLine, wordIndex);
    apply(fsLine, wordIndex);
    if (line) line.activeWord = wordIndex;
  }

  function updateLyricsHighlight(currentSec) {
    if (!lyricsVisible || !lyricsLines.length) return;
    
    // Блокируем updateLyricsHighlight во время рендеринга
    if (lyricsRenderingLock) {
      return;
    }
    
    // Проверяем, не скрыт ли список - если да, не меняем классы
    const listEl = lyricsContainer ? lyricsContainer.querySelector('#lyrics-list') : null;
    if (listEl && listEl.style.opacity === '0') {
      return; // Список еще скрыт, не меняем классы
    }
    
    // Определяем активную строку
    let idx = -1;
    while ((idx + 1) < lyricsLines.length && lyricsLines[idx + 1].time <= currentSec + 0.05) {
      idx++;
    }

    // Меняем активную строку только если она изменилась
      const prev = currentLyricsIndex;
    if (idx !== currentLyricsIndex) {
      if (prev >= 0) setActiveWord(prev, -1);
      currentLyricsIndex = idx;
      
      const lines = lyricsContainer.querySelectorAll('.lyric-line');
      const fsLines = lyricsFsList ? lyricsFsList.querySelectorAll('.lyric-line') : [];
      
      // Обновляем классы для inline lyrics (подход Яндекса)
      // Все строки одного размера - при активации только меняется цвет и opacity
      if (lines.length) {
        // Сначала устанавливаем начальное состояние для всех строк (для плавного перемещения)
        lines.forEach((line) => {
          line.style.transition = 'none';
          if (!line.classList.contains('active')) {
            line.style.transform = 'translateY(10px)';
          }
        });
        
        // Принудительно триггерим reflow
        void lines[0]?.offsetHeight;
        
        // Теперь включаем transition для всех строк и устанавливаем финальные состояния
        requestAnimationFrame(() => {
          lines.forEach((line, lineIdx) => {
            line.style.transition = '';
            
            if (prev >= 0 && lineIdx === prev) {
              // Предыдущая активная строка - возвращаем в неактивное состояние
              const prevTextInner = line.querySelector('.lyric-text-inner') || line;
              if (prevTextInner) {
                prevTextInner.style.color = '#b3b3b3';
                prevTextInner.style.opacity = '0.35';
              }
              line.style.transform = 'translateY(10px)';
              line.style.opacity = '0.26';
              line.classList.remove('active');
              line.classList.add('past');
            } else if (idx >= 0 && lineIdx === idx) {
              // Новая активная строка - плавное выплывание
              const newTextInner = line.querySelector('.lyric-text-inner') || line;
              if (newTextInner) {
                newTextInner.style.color = '#fff';
                newTextInner.style.opacity = '1';
              }
              line.style.transform = 'translateY(0)';
              line.style.opacity = '1';
              line.classList.add('active');
              line.classList.remove('past');
            } else {
              // Остальные неактивные строки - плавно перемещаются на свои места
              line.style.transform = 'translateY(10px)';
              line.style.opacity = '0.26';
            }
          });
        });
      }
      
      // Обновляем классы для fullscreen lyrics с плавным выплыванием
      if (fsLines.length) {
        // Сначала устанавливаем начальное состояние для всех fullscreen строк
        fsLines.forEach((line) => {
          line.style.transition = 'none';
          if (!line.classList.contains('active')) {
            line.style.transform = 'translateY(10px)';
          }
        });
        
        // Принудительно триггерим reflow
        void fsLines[0]?.offsetHeight;
        
        // Теперь включаем transition для всех строк и устанавливаем финальные состояния
        requestAnimationFrame(() => {
          fsLines.forEach((line, lineIdx) => {
            line.style.transition = '';
            
            if (prev >= 0 && lineIdx === prev) {
              // Предыдущая активная строка - возвращаем в неактивное состояние
              line.style.transform = 'translateY(10px)';
              line.style.opacity = '0.26';
              line.classList.remove('active'); 
              line.classList.add('past');
            } else if (idx >= 0 && lineIdx === idx) {
              // Новая активная строка - плавное выплывание
              const newActiveFsLine = line;
              newActiveFsLine.style.transform = 'translateY(0)';
              newActiveFsLine.style.opacity = '1';
              newActiveFsLine.classList.add('active'); 
              newActiveFsLine.classList.remove('past');
              
              // Синхронизируем прокрутку
          if (lyricsFsInner && !userScrolling) {
            const isSmall = window.matchMedia && window.matchMedia('(max-width: 600px)').matches;
            const isMobile = window.matchMedia && window.matchMedia('(max-width: 960px)').matches;
            const anchor = Math.round((lyricsFsInner.clientHeight) * (isSmall ? 0.05 : (isMobile ? 0.05 : 0.32)));
            const guard = getLyricsTopGuardPx();
                const targetTop = newActiveFsLine.offsetTop - anchor;
            lyricsFsInner.scrollTo({ top: Math.max(guard, targetTop), behavior: 'smooth' });
          }
            } else {
              // Остальные неактивные строки - плавно перемещаются на свои места
              line.style.transform = 'translateY(10px)';
              line.style.opacity = '0.26';
            }
          });
        });
      }
    }

    // ВСЕГДА обновляем подсветку слов для активной строки
    if (idx >= 0 && Array.isArray(lyricsLines[idx].words) && lyricsLines[idx].words.length) {
      const words = lyricsLines[idx].words;
      let wordIdx = -1;
      // Находим последнее слово, которое должно быть активным
      for (let i = 0; i < words.length; i++) {
        if (words[i] && typeof words[i].time === 'number' && words[i].time <= currentSec + 0.02) {
          wordIdx = i;
        } else {
          break;
        }
      }
      // Обновляем подсветку всегда для активной строки
      setActiveWord(idx, wordIdx);
    } else if (idx >= 0) {
      setActiveWord(idx, -1);
    }
  }

  // Pause auto-scroll when user scrolls; resume after inactivity
  if (lyricsFsInner) {
    const pauseMs = 1800;
    const onUserScroll = () => {
      userScrolling = true;
      // In review mode, show past lines temporarily
      try { lyricsFs && lyricsFs.classList.add('reviewing'); } catch(_) {}
      if (scrollResumeTimer) clearTimeout(scrollResumeTimer);
      scrollResumeTimer = setTimeout(() => {
        userScrolling = false;
        // Exit review mode after inactivity
        try { lyricsFs && lyricsFs.classList.remove('reviewing'); } catch(_) {}
        
        // Исправлено: при возобновлении автоматической прокрутки позиционируемся на текущей активной строке
        try {
          if (lyricsVisible && lyricsFsInner && lyricsFsList && typeof scrollLyricsToAnchorForIndex === 'function') {
            // Находим текущую активную строку
            const activeLine = lyricsFsList.querySelector('.lyric-line.active');
            if (activeLine) {
              const activeIndex = Array.from(lyricsFsList.querySelectorAll('.lyric-line')).indexOf(activeLine);
              if (activeIndex >= 0) {
                scrollLyricsToAnchorForIndex(activeIndex);
              }
            } else if (Array.isArray(lyricsLines) && lyricsLines.length) {
              // Если нет активной строки, находим ближайшую к текущему времени
              let t = 0;
              try { t = audio.currentTime || 0; } catch(_) {}
              let idx = 0;
              for (let i = 0; i < lyricsLines.length; i++) {
                if (lyricsLines[i].time <= t) idx = i;
                else break;
              }
              scrollLyricsToAnchorForIndex(idx);
            }
          }
        } catch(_) {}
      }, pauseMs);
    };
    lyricsFsInner.addEventListener('wheel', onUserScroll, { passive: true });
    lyricsFsInner.addEventListener('touchmove', onUserScroll, { passive: true });
    lyricsFsInner.addEventListener('scroll', onUserScroll, { passive: true });

    // Hard clamp: do not allow scroll above the meta/cover area (infinite guard)
    const clampTopGuard = () => {
      try {
        const guard = getLyricsTopGuardPx();
        if (lyricsFsInner.scrollTop < guard) {
          lyricsFsInner.scrollTop = guard;
        }
      } catch(_) {}
    };
    lyricsFsInner.addEventListener('scroll', clampTopGuard, { passive: true });
    window.addEventListener('resize', clampTopGuard);
    window.addEventListener('orientationchange', clampTopGuard);
  }

  // Ensure page content is not covered by the fixed player: add bottom padding dynamically
  function adjustContentPadding() {
    try {
      const h = playerContainer ? playerContainer.getBoundingClientRect().height : 0;
      const extra = 28; // small breathing room above player
      document.body.style.paddingBottom = (h + extra) + 'px';
    } catch (_) {}
  }
  adjustContentPadding();
  // Adjust fullscreen karaoke bottom offset to sit flush above the player (no gap)
  function adjustKaraokeBottomOffset() {
    try {
      const h = playerContainer ? playerContainer.getBoundingClientRect().height : 110;
      const safeGap = 0; // no gap requested
      if (lyricsFs) lyricsFs.style.bottom = (h + safeGap) + 'px';
      if (lyricsContainer) lyricsContainer.style.bottom = (h + safeGap) + 'px';
    } catch (_) {}
  }
  adjustKaraokeBottomOffset();
  window.addEventListener('resize', function onResize() {
    adjustContentPadding();
    adjustKaraokeBottomOffset();
  });

  // Artist links helpers
  function escapeAttr(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }
  function renderArtistInline(artistString) {
    if (!artistString) return '';
    try {
      const parts = String(artistString).split(',').map(s => s.trim()).filter(Boolean);
      return parts.map(p => `<span class="artist-link" data-artist='${escapeAttr(p)}'>${typeof escapeHtml === 'function' ? escapeHtml(p) : p}</span>`).join(', ');
    } catch (_) { return typeof escapeHtml === 'function' ? escapeHtml(artistString) : String(artistString); }
  }
  function bindArtistLinks(rootEl) {
    if (!rootEl) return;
    rootEl.addEventListener('click', function onClick(e) {
      const link = e.target && e.target.closest ? e.target.closest('.artist-link') : null;
      if (!link) return;
      e.preventDefault();
      e.stopPropagation();
      const name = link.getAttribute('data-artist') || '';
      try { (window.navigateTo || navigateTo)('artist', { artist: name }); } catch (_) {}
    });
  }

  // Persistent popup player integration
  let popupWin = null;
  let popupActive = false;
  let popupState = { currentTime: 0, duration: 0, isPlaying: false, src: '', volume: 1, title: '', artist: '', cover: '' };
  function reconnectPopup() {
    // Do not call window.open here to avoid creating about:blank popups on load
    popupWin = null;
    popupActive = false;
    return false;
  }
  function ensurePopup(allowOpen) {
    // Disable popup usage; always use inline <audio>
    return false;
  }
  window.addEventListener('message', (e) => {
    if (!e.data || typeof e.data !== 'object') return;
    if (e.data.cmd === 'popupReady') {
      popupActive = true;
      // Request current state asap
      try { popupWin && popupWin.postMessage({ cmd: 'requestState' }, '*'); } catch (err) {}
      return;
    }
    if (e.data.cmd === 'playerState') {
      popupActive = true;
      popupState = e.data;
      // Update UI from popup state
      seekBar.value = popupState.duration ? (popupState.currentTime / popupState.duration) * 100 : 0;
      currentTimeEl.textContent = formatTime(popupState.currentTime || 0);
      durationEl.textContent = formatTime(popupState.duration || 0);
      if (popupState.title) trackTitle.textContent = popupState.title;
      if (popupState.artist) {
        trackArtist.innerHTML = renderArtistInline(popupState.artist);
        bindArtistLinks(trackArtist);
      }
      if (popupState.cover) cover.src = popupState.cover;
      updatePlayPauseUI();
      savePlayerStateThrottled();
    }
    if (e.data.cmd === 'playerEnded') {
      playNext(true);
    }
  });
  reconnectPopup();

  // Safe posting to popup with readiness checks and retries
  function postToPopup(message, opts = {}) {
    // Popup disabled
    return false;
    function trySend() {
      if (!popupWin || popupWin.closed) return false;
      let ready = true;
      try {
        ready = popupWin.document && popupWin.document.readyState === 'complete';
      } catch (e) {
        ready = true;
      }
      if (!ready) {
        if (attempts++ < retries) {
          setTimeout(trySend, delay);
          return true;
        }
        return false;
      }
      try {
        popupWin.postMessage(message, '*');
        return true;
      } catch (e) {
        if (attempts++ < retries) {
          setTimeout(trySend, delay);
          return true;
        }
        return false;
      }
    }
    return trySend();
  }

  // State
  const PLAYER_STATE_KEY = 'muzic2_player_state';
  const QUEUE_KEY = 'muzic2_player_queue';
  const QUEUE_INDEX_KEY = 'muzic2_player_queue_index';
  const ORIGINAL_QUEUE_KEY = 'muzic2_player_original_queue';
  const SHUFFLE_KEY = 'muzic2_player_shuffle';
  const REPEAT_KEY = 'muzic2_player_repeat'; // none | one | all
  const REPLAY_ENABLED_KEY = 'muzic2_player_replay_once_enabled';
  const REPLAY_TOKEN_KEY = 'muzic2_player_replay_token';
  const VIDEO_STATE_KEY = 'muzic2_player_video_state';
  const RECENTS_KEY = 'muzic2_recent_listening_v1';

  let isPlaying = false;
  let trackQueue = [];
  let queueIndex = 0;
  let shuffleEnabled = false;
  let repeatMode = 'none';
  // One-time replay toggle state
  let replayOnceEnabled = false;
  let replayToken = null;
  let isMuted = false;
  let previousVolume = 1;
  let isFullscreen = false;
  let originalQueue = [];
  let currentTrackId = null;
  let likedSet = new Set();
  let autoplayEnabled = true; // Always enabled by default

  // Helpers
  function formatTime(sec) {
    sec = Math.floor(sec || 0);
    return `${Math.floor(sec / 60)}:${('0' + (sec % 60)).slice(-2)}`;
  }
  
  // Load random tracks for autoplay
  async function loadRandomTracks(count = 20) {
    try {
      console.log('Loading random tracks for autoplay, count:', count);
      const response = await fetch(api(`/muzic2/public/src/api/random_tracks.php?limit=${count}`), {
        credentials: 'include'
      });
      const data = await response.json();
      
      if (data.success && data.tracks) {
        console.log('Loaded random tracks:', data.tracks.length);
        return data.tracks;
      } else {
        console.error('Failed to load random tracks:', data.error);
        return [];
      }
    } catch (error) {
      console.error('Error loading random tracks:', error);
      return [];
    }
  }
  function updatePlayPauseUI() {
    // Prefer inline video state if video panel is open
    let playing;
    if (isPromoVideoActive()) {
      try { playing = !lyricsFsVideo.paused && !lyricsFsVideo.ended; } catch(_) { playing = false; }
    } else if (videoPanel && videoPanel.style.display === 'block' && inlineVideo) {
      try { playing = !inlineVideo.paused && !inlineVideo.ended; } catch(_) { playing = !audio.paused; }
    } else {
      playing = popupActive ? !!popupState.isPlaying : !audio.paused;
    }
    if (!playing) {
      playIcon.style.display = '';
      pauseIcon.style.display = 'none';
      if (carouselPlayIcon) carouselPlayIcon.style.display = '';
      if (carouselPauseIcon) carouselPauseIcon.style.display = 'none';
    } else {
      playIcon.style.display = 'none';
      pauseIcon.style.display = '';
      if (carouselPlayIcon) carouselPlayIcon.style.display = 'none';
      if (carouselPauseIcon) carouselPauseIcon.style.display = '';
    }
  }
  function updateShuffleUI() {
    const isOn = !!shuffleEnabled;
    shuffleBtn.classList.toggle('btn-active', isOn);
    shuffleBtn.setAttribute('aria-pressed', String(isOn));
    // Force visual state to persist regardless of external styles
    shuffleBtn.style.color = isOn ? '#1ed760' : '';
    // Update carousel shuffle button
    if (carouselShuffle) {
      if (isOn) {
        carouselShuffle.classList.add('btn-active');
        carouselShuffle.style.color = '#1ed760';
      } else {
        carouselShuffle.classList.remove('btn-active');
        carouselShuffle.style.color = '';
      }
    }
  }
  function updateRepeatUI() {
    // Repurposed: show one-time replay visual state
    const isOn = !!replayOnceEnabled;
    repeatBtn.title = isOn ? 'Повторить текущий трек один раз (вкл)' : 'Повторить текущий трек один раз (выкл)';
    repeatBtn.classList.toggle('btn-active', isOn);
    repeatBtn.setAttribute('aria-pressed', String(isOn));
    repeatBtn.style.color = isOn ? '#1ed760' : '';
    // Update carousel repeat button
    if (isCarouselMode && carouselRepeat) {
      if (isOn) {
        carouselRepeat.classList.add('active');
        carouselRepeat.style.color = '#1ed760';
      } else {
        carouselRepeat.classList.remove('active');
        carouselRepeat.style.color = '';
      }
    }
  }
  

  // Likes helpers (id всегда число — совпадение с Set из API)
  function normalizeTrackId(t) {
    if (!t) return null;
    const raw = t.id != null ? t.id : t.track_id;
    if (raw === null || raw === undefined || raw === '') return null;
    const n = Number(raw);
    return Number.isFinite(n) && n > 0 ? n : null;
  }
  function extractAudioFileParam(src) {
    if (!src || typeof src !== 'string') return '';
    try {
      const u = new URL(src, window.location.origin || 'http://localhost');
      const f = u.searchParams.get('f');
      return f || '';
    } catch (_) {
      const m = String(src).match(/[?&]f=([^&]+)/);
      if (!m) return '';
      try {
        return decodeURIComponent(m[1].replace(/\+/g, ' '));
      } catch (e2) {
        return m[1];
      }
    }
  }
  async function resolveTrackIdFromPlayer() {
    const src = (audio && audio.currentSrc) || (audio && audio.src) || '';
    const f = extractAudioFileParam(src);
    if (!f) return null;
    try {
      const r = await fetch(api('/muzic2/src/api/track_resolve.php?f=' + encodeURIComponent(f)), { credentials: 'include' });
      const j = await r.json();
      const id = j && j.id != null ? Number(j.id) : null;
      return Number.isFinite(id) && id > 0 ? id : null;
    } catch (_) {
      return null;
    }
  }
  async function loadLikes() {
    try {
      if (window.__likedSet instanceof Set && window.__likedSet.size) {
        likedSet = new Set(Array.from(window.__likedSet));
        return;
      }
      const j = (typeof window.fetchLikesLiteCached === 'function')
        ? await window.fetchLikesLiteCached(false)
        : await fetch(getLikesAPI({ lite: true }), { credentials: 'include' }).then((r) => r.json());
      const ids = j.lite && Array.isArray(j.track_ids)
        ? j.track_ids.map((n) => Number(n)).filter((n) => n > 0)
        : (j.tracks || []).map((t) => Number(t.id)).filter((n) => n > 0);
      likedSet = new Set(ids);
    } catch (e) {
      likedSet = new Set();
    }
  }
  function updatePlayerLikeUI() {
    if (!likeBtn) return;
    const tid = currentTrackId != null ? Number(currentTrackId) : null;
    const liked = tid > 0 && likedSet.has(tid);
    likeBtn.classList.toggle('btn-active', !!liked);
    likeBtn.title = liked ? 'Убрать из любимых' : 'В избранное';
  }
  document.addEventListener('likes:updated', (e) => {
    const d = e.detail || {};
    const id = Number(d.trackId);
    const liked = !!d.liked;
    if (!id) return;
    if (liked) likedSet.add(id); else likedSet.delete(id);
    if (Number(currentTrackId) === id) updatePlayerLikeUI();
  });

  function updateMuteUI() {
    if (!volumeBtn) return;
    if (isMuted) {
      volumeBtn.title = 'Включить звук';
      volumeBtn.classList.add('btn-active');
      volumeBtn.setAttribute('aria-pressed', 'true');
      volumeBtn.style.color = '#1ed760';
      // Change icon to muted speaker
      volumeBtn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>';
    } else {
      volumeBtn.title = 'Отключить звук';
      volumeBtn.classList.remove('btn-active');
      volumeBtn.setAttribute('aria-pressed', 'false');
      volumeBtn.style.color = '';
      // Change icon to normal speaker
      volumeBtn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>';
    }
    // Update carousel volume button
    if (isCarouselMode && carouselVolume) {
      const volumeIcon = carouselVolume.querySelector('#carousel-volume-icon');
      if (volumeIcon) {
        if (isMuted || audio.volume === 0) {
          carouselVolume.classList.add('muted');
          volumeIcon.innerHTML = '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/>';
        } else if (audio.volume < 0.5) {
          carouselVolume.classList.remove('muted');
          volumeIcon.innerHTML = '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>';
        } else {
          carouselVolume.classList.remove('muted');
          volumeIcon.innerHTML = '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/>';
        }
      }
    }
  }

  function updateFullscreenUI() {
    if (!fullscreenBtn) return;
    if (isFullscreen) {
      fullscreenBtn.title = 'Выйти из полноэкранного режима';
      fullscreenBtn.classList.add('btn-active');
      fullscreenBtn.setAttribute('aria-pressed', 'true');
      fullscreenBtn.style.color = '#1ed760';
      // Change icon to exit fullscreen
      fullscreenBtn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v3a2 2 0 0 1-2 2H3"/><path d="M21 8h-3a2 2 0 0 1-2-2V3"/><path d="M3 16h3a2 2 0 0 1 2 2v3"/><path d="M16 21v-3a2 2 0 0 1 2-2h3"/></svg>';
    } else {
      fullscreenBtn.title = 'Полноэкранный режим';
      fullscreenBtn.classList.remove('btn-active');
      fullscreenBtn.setAttribute('aria-pressed', 'false');
      fullscreenBtn.style.color = '';
      // Change icon to enter fullscreen
      fullscreenBtn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M16 3h3a2 2 0 0 1 2 2v3"/><path d="M8 21H5a2 2 0 0 1-2-2v-3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>';
    }
  }

  function enterFullscreen() {
    if (!fullscreenOverlay || !trackQueue[queueIndex]) return;
    
    const currentTrack = trackQueue[queueIndex];
    isFullscreen = true;
    
    // Update fullscreen content
    fullscreenTitle.textContent = currentTrack.title || '';
    fullscreenArtist.innerHTML = renderArtistInline(currentTrack.artist || '');
    bindArtistLinks(fullscreenArtist);
    
    // Check if there's a video source (you can extend this logic)
    const hasVideo = currentTrack.video || false; // Add video property to tracks if needed
    
    if (hasVideo && currentTrack.video) {
      fullscreenVideo.src = currentTrack.video;
      fullscreenVideo.style.display = 'block';
      fullscreenCover.style.display = 'none';
      fullscreenVideo.play().catch(() => {});
      
      // Remove existing ended listener to avoid duplicates
      try {
        fullscreenVideo.removeEventListener('ended', handleFullscreenVideoEnded);
      } catch(_) {}
      
      // Add ended event listener for fullscreen video
      const handleFullscreenVideoEnded = () => {
        console.log('Fullscreen video ended, playing next track');
        playNext(true);
      };
      fullscreenVideo.addEventListener('ended', handleFullscreenVideoEnded);
    } else {
      fullscreenCover.src = currentTrack.cover || '';
      fullscreenCover.style.display = 'block';
      fullscreenVideo.style.display = 'none';
      fullscreenVideo.src = '';
    }
    
    // Move player to fullscreen overlay
    if (playerContainer && fullscreenOverlay) {
      fullscreenOverlay.appendChild(playerContainer);
      playerContainer.style.position = 'absolute';
      playerContainer.style.bottom = '0';
      playerContainer.style.left = '0';
      playerContainer.style.width = '100%';
      playerContainer.style.zIndex = '10000';
    }
    
    fullscreenOverlay.style.display = 'flex';
    updateFullscreenUI();
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
  }


  function exitFullscreen() {
    if (!fullscreenOverlay) return;
    
    isFullscreen = false;
    fullscreenOverlay.style.display = 'none';
    fullscreenVideo.pause();
    fullscreenVideo.src = '';
    
    // Move player back to original position
    if (playerContainer && playerRoot) {
      playerRoot.appendChild(playerContainer);
      playerContainer.style.position = '';
      playerContainer.style.bottom = '';
      playerContainer.style.left = '';
      playerContainer.style.width = '';
      playerContainer.style.zIndex = '';
    }
    
    updateFullscreenUI();
    
    // Restore body scroll
    document.body.style.overflow = '';
  }

  // Throttle helper to limit frequent storage writes
  function throttle(fn, ms) {
    let last = 0;
    return function (...args) {
      const now = Date.now();
      if (now - last >= ms) {
        last = now;
        return fn.apply(this, args);
      }
    };
  }

  // Persistence
  function savePlayerState() {
    const usingPopup = popupActive;
    const state = {
      src: usingPopup ? (popupState.src || '') : audio.src,
      title: trackTitle.textContent,
      artist: trackArtist.textContent,
      cover: cover.src,
      currentTime: usingPopup ? (popupState.currentTime || 0) : audio.currentTime,
      duration: usingPopup ? (popupState.duration || 0) : audio.duration,
      volume: usingPopup ? (popupState.volume || audio.volume) : audio.volume,
      isPlaying,
      shuffle: shuffleEnabled,
      repeat: repeatMode,
      queueIndex,
      replayOnceEnabled,
      replayToken,
    };
    localStorage.setItem(PLAYER_STATE_KEY, JSON.stringify(state));
    // Update Continue Listening recents
    saveRecentProgress();
    // Persist replay toggle separately for robustness
    localStorage.setItem(REPLAY_ENABLED_KEY, replayOnceEnabled ? '1' : '0');
    if (replayToken) localStorage.setItem(REPLAY_TOKEN_KEY, replayToken); else localStorage.removeItem(REPLAY_TOKEN_KEY);
  }
  const savePlayerStateThrottled = throttle(savePlayerState, 1000);
  function getSavedState() {
    try { return JSON.parse(localStorage.getItem(PLAYER_STATE_KEY) || 'null'); } catch (e) { return null; }
  }
  function loadPlayerState() {
    const state = getSavedState();
    if (state && state.src) {
      trackTitle.textContent = state.title || '';
      trackArtist.textContent = state.artist || '';
      cover.src = state.cover || '';
      audio.volume = state.volume !== undefined ? state.volume : 1;
      volumeBar.value = Math.round(audio.volume * 100);
      shuffleEnabled = !!state.shuffle;
      repeatMode = state.repeat || 'none';
      queueIndex = typeof state.queueIndex === 'number' ? state.queueIndex : 0;
      replayOnceEnabled = !!(state.replayOnceEnabled || (localStorage.getItem(REPLAY_ENABLED_KEY) === '1'));
      replayToken = state.replayToken || localStorage.getItem(REPLAY_TOKEN_KEY) || null;
      updateShuffleUI();
      updateRepeatUI();
      updateMuteUI();
      updateFullscreenUI();

      // Performance: do not start downloading audio on first page paint
      // unless track was actively playing before reload.
      if (!state.isPlaying) {
        return;
      }
      audio.src = state.src;

      audio.addEventListener('loadedmetadata', function restoreOnce() {
        audio.currentTime = state.currentTime || 0;
        seekBar.value = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
        currentTimeEl.textContent = formatTime(audio.currentTime);
        durationEl.textContent = formatTime(audio.duration);
        if (state.isPlaying) {
          audio.play().catch(() => {});
        }
        audio.removeEventListener('loadedmetadata', restoreOnce);
      });
    }
  }
  function saveQueue() {
    localStorage.setItem(QUEUE_KEY, JSON.stringify(trackQueue));
    localStorage.setItem(QUEUE_INDEX_KEY, String(queueIndex));
    // Persist original order to allow restoring after shuffle off
    localStorage.setItem(ORIGINAL_QUEUE_KEY, JSON.stringify(originalQueue && originalQueue.length ? originalQueue : trackQueue));
  }

  // Video state persistence
  function saveVideoState(state) {
    try {
      const toSave = {
        open: !!(state && state.open),
        url: state && state.url ? String(state.url) : (playerContainer.dataset && playerContainer.dataset.videoUrl) || '',
        currentTime: typeof state.currentTime === 'number' ? state.currentTime : (inlineVideo && !isNaN(inlineVideo.currentTime) ? inlineVideo.currentTime : (audio.currentTime||0)),
        playing: !!(state && state.playing)
      };
      localStorage.setItem(VIDEO_STATE_KEY, JSON.stringify(toSave));
    } catch(_) {}
  }
  function loadVideoState() {
    try { return JSON.parse(localStorage.getItem(VIDEO_STATE_KEY) || 'null'); } catch(_) { return null; }
  }
  function loadQueue() {
    const storedQueue = localStorage.getItem(QUEUE_KEY) || '[]';
    console.log('Loading queue from localStorage:', storedQueue);
    trackQueue = JSON.parse(storedQueue);
    queueIndex = parseInt(localStorage.getItem(QUEUE_INDEX_KEY) || '0', 10);
    if (isNaN(queueIndex) || queueIndex < 0) queueIndex = 0;
    try { originalQueue = JSON.parse(localStorage.getItem(ORIGINAL_QUEUE_KEY) || '[]'); } catch (e) { originalQueue = []; }
    console.log('Loaded queue length:', trackQueue.length);
    console.log('Loaded queue index:', queueIndex);
    if (trackQueue.length > 0) {
      console.log('First track in loaded queue:', trackQueue[0]);
    }
  }

  // Queue UI
  function toggleQueuePanel(show) {
    const isVisible = queuePanel.style.display === 'block';
    const willShow = show !== undefined ? show : !isVisible;
    queuePanel.style.display = willShow ? 'block' : 'none';
    if (willShow) renderQueueUI();
  }
  // Expose for external UI triggers
  try { window.toggleQueuePanel = toggleQueuePanel; } catch(_) {}
  function renderQueueUI() {
    queueList.innerHTML = '';
    if (!trackQueue.length) {
      const li = document.createElement('li');
      li.style.padding = '12px';
      li.style.color = '#9aa0a6';
      li.textContent = 'Очередь пуста';
      queueList.appendChild(li);
      return;
    }
    // Show tracks in normal order with current track highlighted
    trackQueue.forEach((t, idx) => {
      const li = document.createElement('li');
      li.className = 'queue-item' + (idx === queueIndex ? ' queue-current' : '');
      const base = (t && typeof t.artist === 'string') ? t.artist.trim() : '';
      const feats = (t && typeof t.feats === 'string') ? t.feats.trim() : '';
      const combined = feats ? (base ? `${base}, ${feats}` : feats) : base;
      const artistHTML = renderArtistInline(combined);
      li.innerHTML = `
        <div class="queue-idx">${idx + 1}</div>
        <div>
          <div class="queue-title">${escapeHtml(t.title || '')}</div>
          <div class="queue-artist">${artistHTML}</div>
        </div>
        <div class="queue-meta">${t.duration ? formatTime(t.duration) : ''}</div>
      `;
      li.onclick = (e) => {
        const a = e.target && e.target.closest ? e.target.closest('.artist-link') : null;
        if (a) {
          e.stopPropagation();
          const name = a.getAttribute('data-artist') || '';
          try { (window.navigateTo || navigateTo)('artist', { artist: name }); } catch (_) {}
          return;
        }
        playFromQueue(idx);
      };
      queueList.appendChild(li);
    });
  }

  // Core playback
  // Функция для нормализации src перед использованием
  function ensureAudioProxy(src) {
    if (!src) return src;
    if (src.startsWith('http://') || src.startsWith('https://') || src.startsWith('data:')) return src;
    if (src.includes('/public/src/api/audio.php')) return src; // Уже через прокси
    
    // Нормализуем путь
    let path = src;
    if (path.startsWith('/muzic2/')) {
      path = path.substring('/muzic2/'.length);
    } else if (path.startsWith('/')) {
      path = path.substring(1);
    }
    
    // Декодируем если нужно
    try {
      if (path.includes('%')) {
        path = decodeURIComponent(path);
      }
    } catch(e) {}
    
    // ВАЖНО: НЕ преобразуем пути к видео в audio.php!
    // Проверяем, является ли это видео файлом
    const videoExts = ['.mp4', '.webm', '.mov', '.avi', '.m4v', '.ogg'];
    const isVideo = videoExts.some(ext => path.toLowerCase().endsWith(ext));
    if (isVideo) {
      // Для видео возвращаем прямой путь, НЕ через audio.php
      return '/muzic2/' + path.replace(/^\/+/, '');
    }
    
    // Если путь содержит tracks/music/, используем прокси
    const i = path.indexOf('tracks/music/');
    if (i !== -1) {
      const tracksPath = path.substring(i);
      return '/muzic2/public/src/api/audio.php?f=' + encodeURIComponent(tracksPath);
    }
    
    // Если путь содержит tracks/video/ или tracks/videos/, возвращаем прямой путь (НЕ через audio.php)
    const videoIdx = path.indexOf('tracks/video');
    if (videoIdx !== -1) {
      return '/muzic2/' + path.replace(/^\/+/, '');
    }
    
    // Если путь содержит tracks/, используем прокси (только для аудио)
    const j = path.indexOf('tracks/');
    if (j !== -1) {
      const tracksPath = path.substring(j);
      return '/muzic2/public/src/api/audio.php?f=' + encodeURIComponent(tracksPath);
    }
    
    return src;
  }
  
  function setNowPlaying(t) {
    trackTitle.textContent = t.title || '';
    // Render E badge before artist name (consistent with cards)
    try {
      const base = (t && typeof t.artist === 'string') ? t.artist.trim() : '';
      const feats = (t && typeof t.feats === 'string') ? t.feats.trim() : '';
      const combinedArtist = feats ? (base ? `${base}, ${feats}` : feats) : base;
      const artistHtml = renderArtistInline(combinedArtist);
      trackArtist.innerHTML = (t.explicit ? '<span class="exp-badge" title="Нецензурная лексика">E</span>' : '') + artistHtml;
      bindArtistLinks(trackArtist);
      // Update carousel info
      if (carouselTrackTitle) carouselTrackTitle.textContent = t.title || '';
      if (carouselTrackArtist) carouselTrackArtist.textContent = combinedArtist;
    } catch(_) { 
      trackArtist.textContent = t.artist || '';
      if (carouselTrackTitle) carouselTrackTitle.textContent = t.title || '';
      if (carouselTrackArtist) carouselTrackArtist.textContent = t.artist || '';
    }
    if (cover) cover.src = t.cover || (cover.src || '');
    currentTrackId = normalizeTrackId(t);
    lastTrackTitle = String(t.title||'');
    lastTrackArtist = String(t.artist||'');
    
    // Update media session metadata
    if (window.updateMediaSessionMetadata) {
      window.updateMediaSessionMetadata(t);
    }
    
    // Dispatch custom event for track change
    try {
      document.dispatchEvent(new CustomEvent('track:change', { detail: { trackId: currentTrackId } }));
    } catch(_) {}
    
    // Update karaoke meta immediately on track change when overlay is visible
    try {
      if (lyricsVisible && !promoModeActive) {
        if (lyricsFsCover) lyricsFsCover.src = t.cover || (cover && cover.src) || '';
        if (lyricsFsTitle) lyricsFsTitle.textContent = t.title || '';
        if (lyricsFsArtist) lyricsFsArtist.textContent = t.artist || '';
        // Update background cover/video in karaoke using new system
        // Update background cover/video in karaoke
        const bgCover = t.cover || (cover && cover.src) || '';
        if (lyricsFsBgImg) { 
          lyricsFsBgImg.src = bgCover; 
          lyricsFsBgImg.style.display = bgCover ? 'block' : 'none'; 
        }
        if (lyricsFsBgVideo) {
          let bgVideoUrl = '';
          try {
            const ds = (playerContainer.dataset && typeof playerContainer.dataset.videoUrl !== 'undefined') ? playerContainer.dataset.videoUrl : '';
            bgVideoUrl = ds && ds.trim() !== '' ? ds : (t.video_url || '');
            if (bgVideoUrl && !/^https?:/i.test(bgVideoUrl) && bgVideoUrl.indexOf('/public/src/api/video.php?f=') === -1) {
              const i = bgVideoUrl.indexOf('tracks/');
              const rel = i !== -1 ? bgVideoUrl.slice(i) : bgVideoUrl.replace(/^\/+/, '');
              bgVideoUrl = '/muzic2/public/src/api/video.php?f=' + encodeURIComponent(rel);
            }
          } catch(_) {}
          if (bgVideoUrl) {
            try { 
              lyricsFsBgVideo.src = bgVideoUrl; 
              lyricsFsBgVideo.currentTime = 0; 
              lyricsFsBgVideo.play().catch(()=>{}); 
              lyricsFsBgVideo.style.display = 'block'; 
            } catch(_) {}
          } else {
            try { 
              lyricsFsBgVideo.pause(); 
              lyricsFsBgVideo.removeAttribute('src'); 
              lyricsFsBgVideo.load(); 
              lyricsFsBgVideo.style.display = 'none'; 
            } catch(_) {}
          }
        }
        // Keep underlay (fills sides around player) in sync on track change
        try {
          if (lyricsFsUnderlay) lyricsFsUnderlay.style.display = 'block';
          if (lyricsFsUnderlayImg) {
            lyricsFsUnderlayImg.src = bgCover;
            lyricsFsUnderlayImg.style.display = bgCover ? 'block' : 'none';
          }
          if (lyricsFsUnderlayVideo) {
            let u = '';
            const ds2 = (playerContainer.dataset && typeof playerContainer.dataset.videoUrl !== 'undefined') ? playerContainer.dataset.videoUrl : '';
            u = ds2 && ds2.trim() !== '' ? ds2 : (t.video_url || '');
            if (u && !/^https?:/i.test(u) && u.indexOf('/public/src/api/video.php?f=') === -1) {
              const j = u.indexOf('tracks/');
              const rel2 = j !== -1 ? u.slice(j) : u.replace(/^\/+/, '');
              u = '/muzic2/public/src/api/video.php?f=' + encodeURIComponent(rel2);
            }
            if (u) {
              lyricsFsUnderlayVideo.src = u; lyricsFsUnderlayVideo.currentTime = 0; lyricsFsUnderlayVideo.play().catch(()=>{}); lyricsFsUnderlayVideo.style.display='block';
            } else {
              lyricsFsUnderlayVideo.pause(); lyricsFsUnderlayVideo.removeAttribute('src'); lyricsFsUnderlayVideo.load(); lyricsFsUnderlayVideo.style.display='none';
            }
          }
        } catch(_) {}
        // reset video cover in karaoke panel
        if (lyricsFsVideo && !t.video_url) {
          try { lyricsFsVideo.pause(); lyricsFsVideo.removeAttribute('src'); lyricsFsVideo.load(); } catch(_) {}
          if (lyricsFsVideoCover) lyricsFsVideoCover.src = t.cover || '';
        }
        // Ensure karaoke layout aligns with player after metadata update
        try { adjustKaraokeBottomOffset && adjustKaraokeBottomOffset(); } catch(_) {}
      }
    } catch(_) {}
    // Reset scroll pause and re-anchor to first line for new track
    userScrolling = false;
    if (scrollResumeTimer) { try { clearTimeout(scrollResumeTimer); } catch(_) {} scrollResumeTimer = null; }
    currentLyricsIndex = -1;
    
    // Исправлено: агрессивно очищаем фантомные строки при смене трека
    try {
      if (lyricsFsList) {
        const allLines = lyricsFsList.querySelectorAll('.lyric-line');
        allLines.forEach(line => {
          line.classList.remove('active', 'past');
          line.style.display = '';
          line.style.opacity = '';
          line.style.visibility = '';
          line.style.transform = '';
        });
      }
    } catch(_) {}
    
    // Reset karaoke on track change
    if (lyricsContainer) lyricsContainer.innerHTML = lyricsVisible ? '<div class="lyric-line">Загрузка…</div>' : '';
    currentLyricsIndex = -1;
    lyricsLines = [];
    if (lyricsVisible) {
      fetchAndRenderLyricsDirect();
      // Ensure initial anchor after render - на мобильных просто скроллим вверх
      setTimeout(() => {
        const isSmall = window.matchMedia && window.matchMedia('(max-width: 600px)').matches;
        const isMobile = window.matchMedia && window.matchMedia('(max-width: 960px)').matches;
        if (isSmall || isMobile) {
          // На мобильных просто скроллим в самый верх
          if (lyricsFsInner) {
            lyricsFsInner.scrollTo({ top: 0, behavior: 'auto' });
          }
        } else {
          scrollLyricsToAnchorForIndex(0);
        }
      }, 50);
    } else if (currentTrackId) {
      // Preload silently in background if hidden
      loadLyricsForTrack(currentTrackId);
    }
    // Attach video URL (if any) to current track
    playerContainer.dataset.videoUrl = t.video_url || '';
    try { console.debug('[player] setNowPlaying video_url =', t.video_url||''); } catch(_){ }
    updateVideoButtonUI();
    updatePlayerLikeUI();
    // If video panel opened, refresh its media to reflect the new track
    try {
      if (videoPanel && videoPanel.style.display === 'block') {
        openInlineMedia(playerContainer.dataset.videoUrl || '', cover.src || '');
      }
    } catch (_) {}
  }

  // Toggle lyrics button
  if (lyricsBtn) {
    lyricsBtn.onclick = () => {
      lyricsVisible = !lyricsVisible;
    
    // Исправлено: агрессивно очищаем ВСЕ фантомные строки при открытии караоке режима
    if (lyricsVisible) {
      try {
        if (lyricsFsList) {
          const allLines = lyricsFsList.querySelectorAll('.lyric-line');
          allLines.forEach(line => {
            line.classList.remove('active', 'past');
            // Принудительно очищаем все стили
            line.style.display = '';
            line.style.opacity = '';
            line.style.visibility = '';
            line.style.transform = '';
          });
        }
        if (lyricsContainer) {
          const inlineLines = lyricsContainer.querySelectorAll('.lyric-line');
          inlineLines.forEach(line => {
            line.classList.remove('active', 'past');
            line.style.display = '';
            line.style.opacity = '';
            line.style.visibility = '';
            line.style.transform = '';
          });
        }
      } catch(_) {}
    }
    
    const useFullscreen = true;
    if (useFullscreen) {
      if (lyricsFs) {
        lyricsFs.style.display = lyricsVisible ? 'block' : 'none';
      }
      try { adjustKaraokeBottomOffset && adjustKaraokeBottomOffset(); } catch(_) {}
      try { pinPlayerForOverlay(lyricsVisible); } catch(_) {}
      // Show underlay background behind player too (fills gray corners) while karaoke open
      try {
        if (lyricsFsUnderlay) lyricsFsUnderlay.style.display = lyricsVisible ? 'block' : 'none';
        // sync the same background source
        const bgCover = (cover && cover.src) ? cover.src : '';
        if (lyricsFsUnderlayImg) { lyricsFsUnderlayImg.src = bgCover; lyricsFsUnderlayImg.style.display = bgCover ? 'block' : 'none'; }
        if (lyricsFsUnderlayVideo) {
          let bgVideoUrl = '';
          const ds = (playerContainer.dataset && typeof playerContainer.dataset.videoUrl !== 'undefined') ? playerContainer.dataset.videoUrl : '';
          bgVideoUrl = ds && ds.trim() !== '' ? ds : ((trackQueue[queueIndex] && trackQueue[queueIndex].video_url) ? trackQueue[queueIndex].video_url : '');
          if (bgVideoUrl && !/^https?:/i.test(bgVideoUrl) && bgVideoUrl.indexOf('/public/src/api/video.php?f=') === -1) {
            const i = bgVideoUrl.indexOf('tracks/');
            const rel = i !== -1 ? bgVideoUrl.slice(i) : bgVideoUrl.replace(/^\/+/, '');
            bgVideoUrl = '/muzic2/public/src/api/video.php?f=' + encodeURIComponent(rel);
          }
          if (bgVideoUrl) {
            lyricsFsUnderlayVideo.src = bgVideoUrl; lyricsFsUnderlayVideo.currentTime = 0; lyricsFsUnderlayVideo.play().catch(()=>{}); lyricsFsUnderlayVideo.style.display='block';
          } else {
            lyricsFsUnderlayVideo.pause(); lyricsFsUnderlayVideo.removeAttribute('src'); lyricsFsUnderlayVideo.load(); lyricsFsUnderlayVideo.style.display='none';
          }
        }
      } catch(_) {}
      if (lyricsContainer) lyricsContainer.style.display = 'none';
      // Keep player in place; overlay leaves bottom 120px free for it
      document.body.style.overflow = lyricsVisible ? 'hidden' : '';
      // Keep player visible above background (no gray gaps)
    } else {
      if (lyricsContainer) lyricsContainer.style.display = lyricsVisible ? 'block' : 'none';
    }
    if (lyricsVisible && !promoModeActive) {
      lyricsSyncLocked = true;
      // Fill meta like on the screenshot
      try {
        if (lyricsFsCover) lyricsFsCover.src = cover && cover.src ? cover.src : '';
        if (lyricsFsTitle) lyricsFsTitle.textContent = lastTrackTitle || '';
        if (lyricsFsArtist) lyricsFsArtist.textContent = lastTrackArtist || '';
        // Background: show video if available via karaoke slot, else cover
        const bgCover = (cover && cover.src) ? cover.src : '';
        if (lyricsFsBgImg) { lyricsFsBgImg.src = bgCover; lyricsFsBgImg.style.display = bgCover ? 'block' : 'none'; }
        if (lyricsFsBgVideo) {
          let bgVideoUrl = '';
          try {
            const ds = (playerContainer.dataset && typeof playerContainer.dataset.videoUrl !== 'undefined') ? playerContainer.dataset.videoUrl : '';
            bgVideoUrl = ds && ds.trim() !== '' ? ds : ((trackQueue[queueIndex] && trackQueue[queueIndex].video_url) ? trackQueue[queueIndex].video_url : '');
            if (bgVideoUrl && !/^https?:/i.test(bgVideoUrl) && bgVideoUrl.indexOf('/public/src/api/video.php?f=') === -1) {
              const i = bgVideoUrl.indexOf('tracks/');
              const rel = i !== -1 ? bgVideoUrl.slice(i) : bgVideoUrl.replace(/^\/+/, '');
              bgVideoUrl = '/muzic2/public/src/api/video.php?f=' + encodeURIComponent(rel);
            }
          } catch(_) {}
          if (bgVideoUrl) {
            try { lyricsFsBgVideo.src = bgVideoUrl; lyricsFsBgVideo.currentTime = 0; lyricsFsBgVideo.play().catch(()=>{}); lyricsFsBgVideo.style.display = 'block'; } catch(_) {}
          } else {
            try { lyricsFsBgVideo.pause(); lyricsFsBgVideo.removeAttribute('src'); lyricsFsBgVideo.load(); lyricsFsBgVideo.style.display = 'none'; } catch(_) {}
          }
        }
      } catch(_) {}
      
      fetchAndRenderLyricsDirect();
    }
    lyricsBtn.classList.toggle('btn-active', lyricsVisible);
    };
  } else {
    console.warn('Lyrics button not found!');
  }

  const hideKaraoke = () => {
    const wasPromo = promoModeActive;
    lyricsVisible = false;
    promoModeActive = false;
    detachPromoVideoSync();
    if (lyricsFs) {
      lyricsFs.style.display = 'none';
      lyricsFs.classList.remove('promo-mode');
    }
    
    try { adjustKaraokeBottomOffset && adjustKaraokeBottomOffset(); } catch(_) {}
    // Unpin player
    try { pinPlayerForOverlay(false); } catch(_) {}
    try {
      if (lyricsFsUnderlay) lyricsFsUnderlay.style.display = 'none';
      if (lyricsFsUnderlayVideo) { lyricsFsUnderlayVideo.pause(); lyricsFsUnderlayVideo.removeAttribute('src'); lyricsFsUnderlayVideo.load(); lyricsFsUnderlayVideo.style.display='none'; }
    } catch(_) {}
    document.body.style.overflow = '';
    lyricsBtn && lyricsBtn.classList.remove('btn-active');
    lyricsSyncLocked = false;
    // Stop karaoke video if playing
    try {
      if (lyricsFsVideo) {
        lyricsFsVideo.pause();
        lyricsFsVideo.removeAttribute('src');
        lyricsFsVideo.innerHTML = '';
        lyricsFsVideo.load();
        lyricsFsVideo.removeAttribute('poster');
        lyricsFsVideo.controls = false;
        lyricsFsVideo.style.display='none';
      }
    } catch(_) {}
    try { if (lyricsFsVideoWrap) lyricsFsVideoWrap.style.display='none'; } catch(_) {}
    // Hide promo track info
    try {
      const promoTrackInfo = playerRoot.querySelector('#promo-track-info');
      if (promoTrackInfo) promoTrackInfo.style.display = 'none';
    } catch(_) {}
    // Restore cover in the left panel when video is hidden
    try { if (lyricsFsCover) { lyricsFsCover.style.display = 'block'; } } catch(_) {}
    try { if (lyricsFsVideoCover) { lyricsFsVideoCover.style.display = 'none'; } } catch(_) {}
    // Stop background video
    try { if (lyricsFsBgVideo) { lyricsFsBgVideo.pause(); lyricsFsBgVideo.removeAttribute('src'); lyricsFsBgVideo.load(); lyricsFsBgVideo.style.display='none'; } } catch(_) {}
    // Restore audio sound
    try { audio.muted = false; } catch(_) {}
    if (wasPromo) {
      try {
        if (lyricsFsBgImg) {
          lyricsFsBgImg.src = lyricsFsBgImg.src || '';
        }
      } catch(_) {}
      if (lyricsFsArtist) {
        lyricsFsArtist.style.display = '';
      }
      if (lyricsFsCover && promoPreviousCoverSrc) {
        lyricsFsCover.src = promoPreviousCoverSrc;
      }
      promoPreviousCoverSrc = '';
      promoPayload = null;
      if (cover && promoPrevPlayerCover) {
        cover.src = promoPrevPlayerCover;
      }
      promoPrevPlayerCover = '';
      if (trackTitle && promoPrevPlayerTitle) {
        trackTitle.textContent = promoPrevPlayerTitle;
      }
      promoPrevPlayerTitle = '';
      if (trackArtist && promoPrevPlayerArtist) {
        trackArtist.textContent = promoPrevPlayerArtist;
      }
      promoPrevPlayerArtist = '';
      // Clean up resize handlers
      if (window._promoResizeHandlers) {
        window._promoResizeHandlers.forEach(({ handler, mode }) => {
          if (mode === 'promo') {
            window.removeEventListener('resize', handler);
          }
        });
        window._promoResizeHandlers = window._promoResizeHandlers.filter(h => h.mode !== 'promo');
      }
      if (promoWasPlaying) {
        try { audio.play().catch(()=>{}); } catch(_) {}
      }
      promoWasPlaying = false;
    }
    // Player remains visible during karaoke; nothing to restore
  };
  if (lyricsFsClose) lyricsFsClose.onclick = hideKaraoke;
  document.addEventListener('keydown', (e)=>{ if (lyricsVisible && e.key==='Escape') hideKaraoke(); });
  // Extra safety: delegate click on close inside the overlay (covers any bubbling issues)
  if (lyricsFs) {
    try {
      lyricsFs.addEventListener('click', function(e) {
        const btn = e.target && e.target.closest ? e.target.closest('#lyrics-fs-close') : null;
        if (btn) {
          e.preventDefault();
          e.stopPropagation();
          hideKaraoke();
        }
      });
      // Ensure close button is always clickable above backgrounds
      if (lyricsFsClose && !lyricsFsClose.style.zIndex) {
        lyricsFsClose.style.zIndex = '2';
        lyricsFsClose.style.pointerEvents = 'auto';
      }
    } catch(_) {}
  }

  if (lyricsFsMode) {
    try { lyricsFsMode.style.display = 'none'; } catch(_) {}
    lyricsFsMode.onclick = () => {
    if (!lyricsFs) return;
    const isStatic = lyricsFs.classList.toggle('static');
    lyricsFsMode.title = isStatic ? 'Режим караоке (по строкам)' : 'Показать весь текст';
    };
  }

  function isPromoVideoActive() {
    return promoModeActive && lyricsFsVideo && lyricsFsVideo.style.display === 'block';
  }

  function syncPromoVideoUI() {
    if (!isPromoVideoActive()) return;
    const duration = Number.isFinite(lyricsFsVideo.duration) ? lyricsFsVideo.duration : 0;
    const current = lyricsFsVideo.currentTime || 0;
    durationEl.textContent = formatTime(duration || 0);
    currentTimeEl.textContent = formatTime(current);
    if (carouselCurrentTime) carouselCurrentTime.textContent = formatTime(current);
    if (carouselDuration) carouselDuration.textContent = formatTime(duration || 0);
    if (duration > 0) {
      const progress = (current / duration) * 100;
      const progressValue = Math.max(0, Math.min(100, progress));
      seekBar.value = progressValue;
      if (carouselSeekBar) {
        carouselSeekBar.value = progressValue;
        if (carouselSeekBarProgress) carouselSeekBarProgress.style.width = progressValue + '%';
      }
    } else {
      seekBar.value = 0;
      if (carouselSeekBar) {
        carouselSeekBar.value = 0;
        if (carouselSeekBarProgress) carouselSeekBarProgress.style.width = '0%';
      }
    }
  }

  function detachPromoVideoSync() {
    if (promoVideoListeners.length && lyricsFsVideo) {
      promoVideoListeners.forEach(({ evt, handler }) => {
        try { lyricsFsVideo.removeEventListener(evt, handler); } catch(_) {}
      });
    }
    promoVideoListeners.length = 0;
  }

  function attachPromoVideoSync() {
    detachPromoVideoSync();
    if (!lyricsFsVideo) return;
    const syncEvents = ['timeupdate', 'durationchange', 'loadedmetadata', 'seeking', 'seeked'];
    syncEvents.forEach(evt => {
      const handler = () => syncPromoVideoUI();
      lyricsFsVideo.addEventListener(evt, handler);
      promoVideoListeners.push({ evt, handler });
    });
    ['play', 'pause', 'ended'].forEach(evt => {
      const handler = () => {
        if (!isPromoVideoActive()) return;
        isPlaying = evt === 'play';
        if (evt === 'ended') {
          isPlaying = false;
          if (!lyricsFsVideo.loop) {
            seekBar.value = 0;
            currentTimeEl.textContent = formatTime(0);
          }
        }
        updatePlayPauseUI();
      };
      lyricsFsVideo.addEventListener(evt, handler);
      promoVideoListeners.push({ evt, handler });
    });
    syncPromoVideoUI();
  }

  function openArtistPromoFromSPA(payload = {}) {
    if (!payload || !Array.isArray(payload.sources) || !payload.sources.length) {
      console.warn('[promo] payload missing sources');
      return;
    }
    if (lyricsVisible && !promoModeActive) {
      hideKaraoke();
    }
    lyricsVisible = true;
    promoModeActive = true;
    promoPayload = payload;
    promoWasPlaying = !audio.paused && !audio.ended;
    if (promoWasPlaying) {
      suppressVideoPauseOnce = true;
      try { audio.pause(); } catch(_) {}
    }
    const posterSrc = payload.poster || payload.cover || (cover ? cover.src : '');
    const bgSrc = payload.background || posterSrc || '';
    const promoTitle = payload.title || (payload.artist || 'Видео-промо');
    const promoArtist = payload.artist || '';
    if (cover && posterSrc) {
      if (!promoPrevPlayerCover) promoPrevPlayerCover = cover.src || '';
      cover.src = posterSrc;
    }
    // Update player track info to show promo
    if (trackTitle) {
      if (!promoPrevPlayerTitle) promoPrevPlayerTitle = trackTitle.textContent || '';
      trackTitle.textContent = promoTitle;
    }
    if (trackArtist) {
      if (!promoPrevPlayerArtist) promoPrevPlayerArtist = trackArtist.textContent || '';
      trackArtist.textContent = promoArtist;
    }
    if (lyricsFs) {
      lyricsFs.style.display = 'block';
      lyricsFs.classList.add('promo-mode');
    }
    if (lyricsBtn) lyricsBtn.classList.remove('btn-active');
    document.body.style.overflow = 'hidden';
    try { pinPlayerForOverlay(true); } catch(_) {}
    if (lyricsFsUnderlay) lyricsFsUnderlay.style.display = 'block';
    if (lyricsFsCover) {
      if (!promoPreviousCoverSrc) promoPreviousCoverSrc = lyricsFsCover.src || '';
      if (posterSrc) {
        lyricsFsCover.src = posterSrc;
      }
      lyricsFsCover.style.display = 'none';
    }
    if (lyricsFsTitle) lyricsFsTitle.textContent = promoTitle;
    if (lyricsFsArtist) {
      lyricsFsArtist.textContent = promoArtist;
      lyricsFsArtist.style.display = promoArtist ? '' : 'none';
    }
    // Display promo track info if available
    const promoTrackInfo = playerRoot.querySelector('#promo-track-info');
    const promoTrackCover = playerRoot.querySelector('#promo-track-cover');
    const promoTrackTitle = playerRoot.querySelector('#promo-track-title');
    const promoTrackArtist = playerRoot.querySelector('#promo-track-artist');
    if (payload.promo_track && promoTrackInfo) {
      const track = payload.promo_track;
      const trackCoverUrl = track.cover ? (track.cover.startsWith('http') ? track.cover : `/muzic2/${track.cover}`) : '/muzic2/tracks/covers/placeholder.jpg';
      if (promoTrackCover) promoTrackCover.src = trackCoverUrl;
      if (promoTrackTitle) promoTrackTitle.textContent = track.title || '';
      if (promoTrackArtist) promoTrackArtist.textContent = track.artist || '';
      promoTrackInfo.style.display = 'flex';
    } else if (promoTrackInfo) {
      promoTrackInfo.style.display = 'none';
    }
    if (lyricsFsBgImg) {
      lyricsFsBgImg.src = bgSrc;
      lyricsFsBgImg.style.display = bgSrc ? 'block' : 'none';
    }
    if (lyricsFsBgVideo) {
      try {
        lyricsFsBgVideo.pause();
        lyricsFsBgVideo.removeAttribute('src');
        lyricsFsBgVideo.load();
        lyricsFsBgVideo.style.display = 'none';
      } catch(_) {}
    }
    if (lyricsFsUnderlayImg) {
      lyricsFsUnderlayImg.src = bgSrc;
      lyricsFsUnderlayImg.style.display = bgSrc ? 'block' : 'none';
    }
    if (lyricsFsUnderlayVideo) {
      try {
        lyricsFsUnderlayVideo.pause();
        lyricsFsUnderlayVideo.removeAttribute('src');
        lyricsFsUnderlayVideo.load();
        lyricsFsUnderlayVideo.style.display = 'none';
      } catch(_) {}
    }
    if (lyricsContainer) {
      lyricsContainer.innerHTML = '';
    }
    if (lyricsFsVideoWrap) lyricsFsVideoWrap.style.display = 'block';
    if (lyricsFsVideo) {
      try {
        lyricsFsVideo.pause();
        lyricsFsVideo.removeAttribute('src');
        lyricsFsVideo.innerHTML = '';
        lyricsFsVideo.load();
      } catch(_) {}
      if (posterSrc) {
        try { lyricsFsVideo.setAttribute('poster', posterSrc); } catch(_) {}
      } else {
        lyricsFsVideo.removeAttribute('poster');
      }
      lyricsFsVideo.controls = true;
      lyricsFsVideo.loop = payload.loop !== false;
      lyricsFsVideo.muted = !!isMuted;
      const initialVolume = isMuted ? 0 : audio.volume;
      try { lyricsFsVideo.volume = initialVolume; } catch(_) {}
      lyricsFsVideo.playsInline = true;
      lyricsFsVideo.style.display = 'block';
      payload.sources.forEach(srcObj => {
        if (!srcObj || !srcObj.src) return;
        const sourceEl = document.createElement('source');
        sourceEl.src = srcObj.src;
        if (srcObj.type) sourceEl.type = srcObj.type;
        lyricsFsVideo.appendChild(sourceEl);
      });
      const ensureFullscreen = () => {
        try {
          if (lyricsFsVideoWrap) {
            lyricsFsVideoWrap.style.width = '100vw';
            lyricsFsVideoWrap.style.height = '100vh';
            lyricsFsVideoWrap.style.position = 'fixed';
            lyricsFsVideoWrap.style.top = '0';
            lyricsFsVideoWrap.style.left = '0';
            lyricsFsVideoWrap.style.right = '0';
            lyricsFsVideoWrap.style.bottom = '0';
          }
          if (lyricsFsVideo) {
            lyricsFsVideo.style.width = '100%';
            lyricsFsVideo.style.height = '100%';
            lyricsFsVideo.style.minWidth = '100%';
            lyricsFsVideo.style.minHeight = '100%';
            lyricsFsVideo.style.objectFit = 'cover';
          }
        } catch(_) {}
      };
      const startVideo = () => {
        try {
          ensureFullscreen();
          lyricsFsVideo.currentTime = 0;
          lyricsFsVideo.play().catch(err => {
            console.warn('⚠️ Promo video autoplay prevented:', err);
          });
        } catch(err) {
          console.warn('⚠️ Promo video start failed:', err);
        }
      };
      ensureFullscreen();
      if (lyricsFsVideo.readyState >= 2) {
        startVideo();
      } else {
        const onLoaded = () => {
          lyricsFsVideo.removeEventListener('loadeddata', onLoaded);
          ensureFullscreen();
          startVideo();
        };
        lyricsFsVideo.addEventListener('loadeddata', onLoaded, { once: true });
        lyricsFsVideo.addEventListener('loadedmetadata', ensureFullscreen, { once: true });
      }
      // Add resize handler for promo mode
      const promoResizeHandler = () => {
        if (promoModeActive && lyricsVisible) {
          ensureFullscreen();
        }
      };
      window.addEventListener('resize', promoResizeHandler);
      // Store handler for cleanup
      if (!window._promoResizeHandlers) window._promoResizeHandlers = [];
      window._promoResizeHandlers.push({ handler: promoResizeHandler, mode: 'promo' });
      seekBar.value = 0;
      currentTimeEl.textContent = formatTime(0);
      durationEl.textContent = formatTime(lyricsFsVideo.duration || 0);
      attachPromoVideoSync();
      updatePlayPauseUI();
    }
    try { adjustKaraokeBottomOffset && adjustKaraokeBottomOffset(); } catch(_) {}
  }

  window.openArtistPromoFromSPA = openArtistPromoFromSPA;


  // Update lyrics on timeupdate
  audio.addEventListener('timeupdate', () => {
    if (!lyricsVisible) return;
    if (lyricsRenderSuspended) return;
    // If karaoke video is playing in left slot, sync from it to keep scrolling
    let t = audio.currentTime || 0;
    try {
      if (lyricsFsVideo && lyricsFsVideoWrap && lyricsFsVideo.style.display === 'block' && !lyricsFsVideo.paused && !lyricsFsVideo.ended) {
        t = isNaN(lyricsFsVideo.currentTime) ? t : lyricsFsVideo.currentTime;
      }
    } catch(_) {}
    updateLyricsHighlight(t);
  });
  // Re-highlight on seek (user dragged to start or anywhere)
  audio.addEventListener('seeked', () => {
    if (!lyricsVisible) return;
    let t = audio.currentTime || 0;
    try {
      if (lyricsFsVideo && lyricsFsVideoWrap && lyricsFsVideo.style.display === 'block' && !lyricsFsVideo.paused && !lyricsFsVideo.ended) {
        t = isNaN(lyricsFsVideo.currentTime) ? t : lyricsFsVideo.currentTime;
      }
    } catch(_) {}
    // Reset current index so highlight recalculates cleanly and scroll to anchor
    try { currentLyricsIndex = -1; } catch(_) {}
    updateLyricsHighlight(t);
    if (lyricsVisible && typeof scrollLyricsToAnchorForIndex === 'function') {
      // Find nearest index to current time for centering
      let idx = 0;
      for (let i = 0; i < lyricsLines.length; i++) { if (lyricsLines[i].time <= t) idx = i; else break; }
      try { scrollLyricsToAnchorForIndex(idx); } catch(_) {}
    }
  });
  window.playFromQueue = function(idx) {
    try { crossfadeStarted = false; } catch (_) {}
    console.log('playFromQueue called with index:', idx);
    console.log('trackQueue length:', trackQueue.length);
    console.log('Full trackQueue:', trackQueue);
    if (!trackQueue[idx]) {
      console.log('No track at index', idx);
      console.log('Available indices:', trackQueue.map((t, i) => i));
      return;
    }
    queueIndex = idx;
    // Update carousel if active
    if (isCarouselMode && carouselCurrentIndex !== idx) {
      carouselCurrentIndex = idx;
      updateCarousel();
    }
    const t = trackQueue[idx];
    console.log('Playing track:', t.title, 'from queue');
    console.log('Track src:', t.src);
    console.log('Track cover:', t.cover);
    console.log('Track object:', t);
    
    // Check if current track has video, if not and video panel is open, close it
    if (!t.video_url && videoPanel && videoPanel.style.display === 'block') {
      console.log('Current track has no video, closing video panel');
      // Close video panel and resume audio playback
      try { inlineVideo.pause(); } catch(_) {}
      inlineVideo.removeAttribute('src'); 
      inlineVideo.load();
      if (inlineCover) inlineCover.style.display = 'none';
      videoPanel.style.display = 'none';
      // Resume audio playback
      try { audio.play().catch(()=>{}); } catch(_) {}
      updatePlayPauseUI();
    }
    
    // ПРОВЕРЯЕМ: если src содержит URL страницы, заменяем на file_path
    if (t.src && t.src.includes('artist.html')) {
      console.log('WARNING: Track src contains URL page, replacing with file_path');
      t.src = t.file_path || '';
      console.log('Replaced src with file_path:', t.src);
    }
    
    // Нормализуем src через прокси перед использованием
    if (t.src) {
      t.src = ensureAudioProxy(t.src);
    }
    
    setNowPlaying(t);
    if (popupActive || ensurePopup(true)) {
      const ok = postToPopup({ cmd: 'playTrack', src: t.src, title: t.title, artist: t.artist, cover: t.cover, currentTime: 0, volume: volumeBar.value/100, autoplay: true });
      if (!ok) {
        // fallback to inline audio if cannot control popup
        audio.src = t.src;
        audio.currentTime = 0;
        audio.play().catch(() => {});
      } else {
        saveQueue();
        savePlayerState();
        renderQueueUI();
        return;
      }
    } else {
      console.log('Setting audio.src to:', t.src);
      audio.src = t.src;
      // start from beginning always when starting playback via control
      audio.currentTime = 0;
      console.log('Attempting to play audio...');
      audio.play().catch((error) => {
        console.error('Audio play failed:', error);
        console.error('Audio src:', audio.src);
        console.error('Audio readyState:', audio.readyState);
      });
    }
    saveQueue();
    savePlayerState();
    renderQueueUI();
    // keep shuffle button visual in-sync after any track change
    updateShuffleUI();
    // If the just-started track is the one-time replay duplicate, turn the toggle off
    try {
      const t = trackQueue[queueIndex];
      if (t && replayOnceEnabled && replayToken && t._replayToken === replayToken) {
        replayOnceEnabled = false;
        replayToken = null;
        updateRepeatUI();
      }
    } catch (e) {}
  }
  async function playNext(auto = false) {
    console.log('playNext called, auto:', auto, 'trackQueue length:', trackQueue.length, 'queueIndex:', queueIndex);
    console.log('repeatMode:', repeatMode);
    if (!trackQueue.length) {
      console.log('No tracks in queue, cannot play next');
      return;
    }
    if (repeatMode === 'one' && auto) {
      // replay same
      console.log('Replaying same track due to repeat mode');
      playFromQueue(queueIndex);
      return;
    }
    
    // Crossfade is now handled in timeupdate event, so we just do normal transition here
    
    // Always follow current queue order; shuffle affects queue order, not next-pick randomness
    if (queueIndex + 1 < trackQueue.length) {
      console.log('Playing next track at index:', queueIndex + 1);
      console.log('Next track:', trackQueue[queueIndex + 1] ? trackQueue[queueIndex + 1].title : 'none');
      
      // Check if next track has video, if not and video panel is open, close it
      const nextTrack = trackQueue[queueIndex + 1];
      if (nextTrack && !nextTrack.video_url && videoPanel && videoPanel.style.display === 'block') {
        console.log('Next track has no video, closing video panel');
        // Close video panel and resume audio playback
        try { inlineVideo.pause(); } catch(_) {}
        inlineVideo.removeAttribute('src'); 
        inlineVideo.load();
        if (inlineCover) inlineCover.style.display = 'none';
        videoPanel.style.display = 'none';
        // Resume audio playback
        try { audio.play().catch(()=>{}); } catch(_) {}
        updatePlayPauseUI();
      }
      
      playFromQueue(queueIndex + 1);
    } else if (repeatMode === 'all') {
      console.log('Repeating from beginning');
      
      // Check if first track has video, if not and video panel is open, close it
      const firstTrack = trackQueue[0];
      if (firstTrack && !firstTrack.video_url && videoPanel && videoPanel.style.display === 'block') {
        console.log('First track has no video, closing video panel');
        // Close video panel and resume audio playback
        try { inlineVideo.pause(); } catch(_) {}
        inlineVideo.removeAttribute('src'); 
        inlineVideo.load();
        if (inlineCover) inlineCover.style.display = 'none';
        videoPanel.style.display = 'none';
        // Resume audio playback
        try { audio.play().catch(()=>{}); } catch(_) {}
        updatePlayPauseUI();
      }
      
      playFromQueue(0);
    } else {
      console.log('No more tracks in queue');
      // If autoplay is enabled, load random tracks
      if (autoplayEnabled) {
        console.log('Autoplay enabled, loading random tracks...');
        try {
          const randomTracks = await loadRandomTracks(20);
          if (randomTracks.length > 0) {
            console.log('Loaded random tracks for autoplay:', randomTracks.length);
            // Replace current queue with random tracks
            trackQueue = randomTracks;
            originalQueue = randomTracks.slice();
            queueIndex = 0;
            saveQueue();
            renderQueueUI();
            playFromQueue(0);
            return;
          }
        } catch (error) {
          console.error('Failed to load random tracks for autoplay:', error);
        }
      }
    }
  }
  function playPrev() {
    if (!trackQueue.length) return;
    if (audio.currentTime > 3) {
      // restart current if played > 3s
      audio.currentTime = 0;
      return;
    }
    // Always follow current queue order; shuffle affects queue order, not prev-pick randomness
    if (queueIndex > 0) {
      const prevTrack = trackQueue[queueIndex - 1];
      // Check if previous track has video, if not and video panel is open, close it
      if (prevTrack && !prevTrack.video_url && videoPanel && videoPanel.style.display === 'block') {
        console.log('Previous track has no video, closing video panel');
        // Close video panel and resume audio playback
        try { inlineVideo.pause(); } catch(_) {}
        inlineVideo.removeAttribute('src'); 
        inlineVideo.load();
        if (inlineCover) inlineCover.style.display = 'none';
        videoPanel.style.display = 'none';
        // Resume audio playback
        try { audio.play().catch(()=>{}); } catch(_) {}
        updatePlayPauseUI();
      }
      playFromQueue(queueIndex - 1);
    } else if (repeatMode === 'all') {
      const lastTrack = trackQueue[trackQueue.length - 1];
      // Check if last track has video, if not and video panel is open, close it
      if (lastTrack && !lastTrack.video_url && videoPanel && videoPanel.style.display === 'block') {
        console.log('Last track has no video, closing video panel');
        // Close video panel and resume audio playback
        try { inlineVideo.pause(); } catch(_) {}
        inlineVideo.removeAttribute('src'); 
        inlineVideo.load();
        if (inlineCover) inlineCover.style.display = 'none';
        videoPanel.style.display = 'none';
        // Resume audio playback
        try { audio.play().catch(()=>{}); } catch(_) {}
        updatePlayPauseUI();
      }
      playFromQueue(trackQueue.length - 1);
    } else {
      audio.currentTime = 0;
    }
  }

  // Navigation functions
  async function playNext(auto = false) {
    if (!trackQueue.length) return;
    if (repeatMode === 'one' && auto) {
      playFromQueue(queueIndex);
      return;
    }
    
    if (queueIndex + 1 < trackQueue.length) {
      playFromQueue(queueIndex + 1);
    } else if (repeatMode === 'all') {
      playFromQueue(0);
    } else {
      if (autoplayEnabled) {
        try {
          const randomTracks = await loadRandomTracks(20);
          if (randomTracks.length > 0) {
            trackQueue = randomTracks;
            originalQueue = randomTracks.slice();
            queueIndex = 0;
            saveQueue();
            playFromQueue(0);
          }
        } catch (error) {
          console.error('Failed to load random tracks:', error);
        }
      }
    }
  }

  function playPrev() {
    if (!trackQueue.length) return;
    if (audio.currentTime > 3) {
      audio.currentTime = 0;
      return;
    }
    
    if (queueIndex > 0) {
      playFromQueue(queueIndex - 1);
    } else if (repeatMode === 'all') {
      playFromQueue(trackQueue.length - 1);
    } else {
      audio.currentTime = 0;
    }
  }

  // Events
  playBtn.onclick = () => {
    // If popup is active or can be opened via this gesture, control it
    if (popupActive || ensurePopup(true)) {
      if (!popupState.src && trackQueue[queueIndex]) {
        const t = trackQueue[queueIndex];
        const ok = postToPopup({ cmd: 'playTrack', src: t.src, title: t.title, artist: t.artist, cover: t.cover, currentTime: 0, volume: volumeBar.value/100, autoplay: true });
        if (ok) return; // handled by popup
      } else {
        const ok = postToPopup({ cmd: popupState.isPlaying ? 'pause' : 'play' });
        if (ok) return; // handled by popup
      }
      // if posting failed, fall through to local audio
    }
    // If promo video is active, control it directly
    if (isPromoVideoActive() && lyricsFsVideo) {
      const paused = lyricsFsVideo.paused || lyricsFsVideo.ended;
      if (paused) {
        lyricsFsVideo.play().catch(()=>{});
      } else {
        try { lyricsFsVideo.pause(); } catch(_) {}
      }
      return;
    }
    // If inline video is visible, control video instead of audio
    if (videoPanel && videoPanel.style.display === 'block' && inlineVideo) {
      const paused = inlineVideo.paused || inlineVideo.ended;
      if (paused) {
        try { inlineVideo.currentTime = isNaN(audio.currentTime) ? (inlineVideo.currentTime||0) : (audio.currentTime||0); } catch(_) {}
        inlineVideo.play().catch(()=>{});
        try { audio.pause(); } catch(_) {}
      } else {
        try { inlineVideo.pause(); } catch(_) {}
      }
      return;
    }
    if (!audio.src) {
      if (trackQueue[queueIndex]) { playFromQueue(queueIndex); return; }
      if (window.initialQueue && Array.isArray(window.initialQueue) && window.initialQueue.length > 0) {
        trackQueue = window.initialQueue.slice();
        queueIndex = 0;
        saveQueue();
        playFromQueue(0);
        return;
      }
      // last resort: restore from saved state
      try {
        const state = JSON.parse(localStorage.getItem('muzic2_player_state') || 'null');
        if (state && state.src) {
          audio.src = state.src;
          audio.currentTime = state.currentTime || 0;
          trackTitle.textContent = state.title || '';
          trackArtist.textContent = state.artist || '';
          cover.src = state.cover || cover.src;
          audio.play().catch(() => {});
        }
      } catch (e) {}
      return;
    }
    if (audio.paused) {
      audio.play();
    } else {
      audio.pause();
    }
  };
  // Отслеживание прослушиваний
  let trackPlayStartTime = null;
  let currentPlayingTrack = null;
  let trackPlayUpdateInterval = null;
  
  async function trackPlayStart(track) {
    if (!track || !track.id) {
      console.warn('trackPlayStart: track or track.id is missing', track);
      return;
    }
    try {
      console.log('trackPlayStart: tracking track', track.id, track.title);
      const response = await fetch('/muzic2/src/api/track_play.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'start',
          track_id: track.id,
          title: track.title || '',
          artist: track.artist || '',
          album: track.album || '',
          duration: track.duration || 0
        })
      });
      
      if (response.ok) {
        const data = await response.json();
        console.log('trackPlayStart: response', data);
        if (data.history_id || data.success) {
          currentPlayingTrack = { ...track, history_id: data.history_id };
          trackPlayStartTime = Date.now();
          
          // Запускаем периодическое обновление времени прослушивания
          if (trackPlayUpdateInterval) clearInterval(trackPlayUpdateInterval);
          trackPlayUpdateInterval = setInterval(() => {
            trackPlayUpdate(track);
          }, 10000); // Обновляем каждые 10 секунд
        }
      } else {
        const errorText = await response.text();
        console.error('trackPlayStart: API error', response.status, errorText);
      }
    } catch (e) {
      console.error('Error tracking play start:', e);
    }
  }
  
  async function trackPlayUpdate(track) {
    if (!currentPlayingTrack || !track || !audio) return;
    try {
      const playDuration = Math.floor(audio.currentTime || 0);
      await fetch('/muzic2/src/api/track_play.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'update',
          track_id: track.id,
          play_duration: playDuration,
          title: track.title || '',
          artist: track.artist || '',
          album: track.album || '',
          duration: track.duration || 0
        })
      });
    } catch (e) {
      console.error('Error updating play duration:', e);
    }
  }
  
  async function trackPlayComplete(track) {
    if (!currentPlayingTrack || !track || !audio) return;
    try {
      // Останавливаем периодическое обновление
      if (trackPlayUpdateInterval) {
        clearInterval(trackPlayUpdateInterval);
        trackPlayUpdateInterval = null;
      }
      
      const playDuration = Math.floor(audio.currentTime || 0);
      console.log('trackPlayComplete: completing track', track.id, track.title, 'duration:', playDuration);
      const response = await fetch('/muzic2/src/api/track_play.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'complete',
          track_id: track.id,
          play_duration: playDuration,
          title: track.title || '',
          artist: track.artist || '',
          album: track.album || '',
          duration: track.duration || 0
        })
      });
      
      if (response.ok) {
        const data = await response.json();
        console.log('trackPlayComplete: success', data);
      } else {
        const errorText = await response.text();
        console.error('trackPlayComplete: API error', response.status, errorText);
      }
      
      if (response.ok) {
        currentPlayingTrack = null;
        trackPlayStartTime = null;
      }
    } catch (e) {
      console.error('Error tracking play complete:', e);
    }
  }
  
  audio.addEventListener('play', () => {
    if (popupActive) return;
    isPlaying = true;
    updatePlayPauseUI();
    document.getElementById('track-status').textContent = '';
    savePlayerState();
    
    
    // Reset crossfade state when new track starts
    crossfadeStarted = false;
    
    // Отслеживание прослушивания
    if (trackQueue[queueIndex]) {
      const currentTrack = trackQueue[queueIndex];
      // Используем ID из трека, если есть, иначе генерируем временный ID на основе названия и артиста
      const trackId = currentTrackId || currentTrack.id || (currentTrack.title && currentTrack.artist ? 
        (currentTrack.title + currentTrack.artist).replace(/\s+/g, '').toLowerCase().substring(0, 50) : null);
      if (trackId) {
        console.log('Audio play: tracking track', trackId, currentTrack.title);
        trackPlayStart({
          id: trackId,
          title: currentTrack.title || '',
          artist: currentTrack.artist || '',
          album: currentTrack.album || '',
          duration: currentTrack.duration || 0
        });
      } else {
        console.warn('Audio play: no track ID available', currentTrack);
      }
    }
    
    // Update media session playback state
    if (window.updateMediaSessionPlaybackState) {
      window.updateMediaSessionPlaybackState();
    }
    
    // Dispatch custom event for track play
    try {
      document.dispatchEvent(new CustomEvent('track:play', { detail: { trackId: currentTrackId } }));
    } catch(_) {}
    
    // Update carousel if active
    if (isCarouselMode && carouselCurrentIndex !== queueIndex) {
      carouselCurrentIndex = queueIndex;
      updateCarousel();
    }
    
    // If video panel is open, let video take over sound and sync time
    if (videoPanel && videoPanel.style.display === 'block' && inlineVideo) {
      try { inlineVideo.currentTime = isNaN(audio.currentTime) ? (inlineVideo.currentTime||0) : (audio.currentTime||0); } catch(_) {}
      try { inlineVideo.play().catch(()=>{}); } catch(_) {}
      suppressVideoPauseOnce = true;
      try { audio.pause(); } catch(_) {}
    }
    // If karaoke video is visible, let it drive playback and pause audio
    try {
      if (lyricsVisible && lyricsFsVideoWrap && lyricsFsVideo && lyricsFsVideoWrap.style.display === 'block') {
        try { lyricsFsVideo.currentTime = isNaN(audio.currentTime) ? (lyricsFsVideo.currentTime||0) : (audio.currentTime||0); } catch(_) {}
        try { lyricsFsVideo.play().catch(()=>{}); } catch(_) {}
        suppressVideoPauseOnce = true;
        try { audio.pause(); } catch(_) {}
      }
    } catch(_) {}
  });
  audio.addEventListener('pause', () => {
    if (popupActive) return;
    isPlaying = false;
    updatePlayPauseUI();
    document.getElementById('track-status').textContent = '';
    savePlayerState();
    
    
    // Update media session playback state
    if (window.updateMediaSessionPlaybackState) {
      window.updateMediaSessionPlaybackState();
    }
    
    // Dispatch custom event for track pause
    try {
      document.dispatchEvent(new CustomEvent('track:pause', { detail: { trackId: currentTrackId } }));
    } catch(_) {}
    
    // Pause any active videos when audio is paused to keep them in sync
    if (suppressVideoPauseOnce) {
      suppressVideoPauseOnce = false; // skip once: pause came from handing off to video
    } else {
      try { if (videoPanel && videoPanel.style.display === 'block' && inlineVideo && !inlineVideo.paused) inlineVideo.pause(); } catch(_) {}
      try { if (lyricsVisible && lyricsFsVideoWrap && lyricsFsVideo && lyricsFsVideoWrap.style.display === 'block' && !lyricsFsVideo.paused) lyricsFsVideo.pause(); } catch(_) {}
    }
  });
  // Crossfade state
  let crossfadeStarted = false;
  let crossfadeTimeout = null;

  audio.addEventListener('timeupdate', () => {
    if (popupActive) return;
    if (lyricsRenderSuspended) return;
    // If crossfade is active, don't update progress bar here - let crossfade handle it
    if (crossfadeStarted) {
      return;
    }
    // If video is playing, don't update progress bar - let video handle it
    if ((videoPanel && videoPanel.style.display === 'block' && inlineVideo && !inlineVideo.ended) || isPromoVideoActive()) {
      return;
    }
    const progress = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
    seekBar.value = progress;
    if (carouselSeekBar) {
      carouselSeekBar.value = progress;
      if (carouselSeekBarProgress) carouselSeekBarProgress.style.width = progress + '%';
    }
    currentTimeEl.textContent = formatTime(audio.currentTime);
    if (carouselCurrentTime) carouselCurrentTime.textContent = formatTime(audio.currentTime);
    savePlayerStateThrottled();
    
    // Check for crossfade trigger
    if (smartCrossfade && smartCrossfade.isEnabled && !crossfadeStarted && trackQueue.length > 1) {
      const currentTime = audio.currentTime || 0;
      const audioDuration = audio.duration || 0;
      const timeUntilEnd = audioDuration - currentTime;
      const crossfadeDuration = smartCrossfade.crossfadeDuration || 8;
      
      // Start crossfade when we have enough time left
      if (timeUntilEnd <= crossfadeDuration && timeUntilEnd > 0) {
        console.log('Starting crossfade from timeupdate!');
        crossfadeStarted = true;
        
        const currentTrack = trackQueue[queueIndex];
        const nextTrack = trackQueue[(queueIndex + 1) % trackQueue.length];
        const nextSrc = ensureAudioProxy(nextTrack.src);
        
        // Update UI immediately when crossfade starts
        trackTitle.textContent = nextTrack.title || '';
        trackArtist.textContent = nextTrack.artist || '';
        cover.src = nextTrack.cover || cover.src;
        
        // Create next audio element
        const nextAudio = new Audio(nextSrc);
        nextAudio.volume = 0;
        nextAudio.currentTime = 0;
        
        // Start next track
        nextAudio.play().catch(e => console.warn('Next track play failed:', e));
        
        // Fade transition
        const fadeSteps = 100;
        const stepDuration = (crossfadeDuration * 1000) / fadeSteps;
        let step = 0;
        
        const fadeInterval = setInterval(() => {
          step++;
          const progress = step / fadeSteps;
          
          // Smooth fade curve
          const currentVolume = Math.pow(1 - progress, 2);
          const nextVolume = Math.pow(progress, 2);
          
          audio.volume = Math.max(0, Math.min(1, currentVolume));
          nextAudio.volume = Math.max(0, Math.min(1, nextVolume));
          
          // Update progress bar to show next track progress
          if (nextAudio.duration) {
            seekBar.value = nextAudio.duration ? (nextAudio.currentTime / nextAudio.duration) * 100 : 0;
            currentTimeEl.textContent = formatTime(nextAudio.currentTime);
            durationEl.textContent = formatTime(nextAudio.duration);
          }
          
          if (step >= fadeSteps) {
            clearInterval(fadeInterval);
            console.log('Crossfade completed from timeupdate!');
            
            // Switch to next track
            queueIndex = (queueIndex + 1) % trackQueue.length;
            
            // Stop current audio and switch to next
            audio.pause();
            audio.src = nextSrc;
            audio.currentTime = nextAudio.currentTime; // Sync position
            audio.volume = 1;
            audio.play().catch(() => {});
            
            updatePlayPauseUI();
            
            // Clean up temporary audio
            nextAudio.pause();
            nextAudio.src = '';
            
            // Reset crossfade state
            crossfadeStarted = false;
          }
        }, stepDuration);
      }
    }
  });
  audio.addEventListener('loadedmetadata', () => {
    if (popupActive) return;
    // If video is playing, don't update duration - let video handle it
    if (videoPanel && videoPanel.style.display === 'block' && inlineVideo && !inlineVideo.ended) {
      return;
    }
    durationEl.textContent = formatTime(audio.duration);
    if (carouselDuration) carouselDuration.textContent = formatTime(audio.duration);
  });
  audio.addEventListener('ended', () => {
    console.log('Audio ended event triggered');
    try { crossfadeStarted = false; } catch (_) {}

    // Отслеживание завершения прослушивания
    if (trackQueue[queueIndex]) {
      const currentTrack = trackQueue[queueIndex];
      // Используем ID из трека, если есть, иначе генерируем временный ID на основе названия и артиста
      const trackId = currentTrackId || currentTrack.id || (currentTrack.title && currentTrack.artist ? 
        (currentTrack.title + currentTrack.artist).replace(/\s+/g, '').toLowerCase().substring(0, 50) : null);
      if (trackId) {
        console.log('Audio ended: tracking completion', trackId, currentTrack.title);
        trackPlayComplete({
          id: trackId,
          title: currentTrack.title || '',
          artist: currentTrack.artist || '',
          album: currentTrack.album || '',
          duration: currentTrack.duration || 0
        });
      } else {
        console.warn('Audio ended: no track ID available', currentTrack);
      }
    }
    
    if (popupActive) {
      console.log('Popup is active, skipping playNext');
      return;
    }
    // If video is playing, don't trigger playNext - let video handle it
    if (videoPanel && videoPanel.style.display === 'block' && inlineVideo && !inlineVideo.ended) {
      console.log('Video is still playing, not calling playNext yet');
      return;
    }
    // Keep karaoke anchor on the last active line before switching
    try { if (lyricsVisible && currentLyricsIndex >= 0) scrollLyricsToAnchorForIndex(currentLyricsIndex); } catch(_) {}
    console.log('Calling playNext(true)');
    playNext(true);
  });

  const handleSeekBarInput = (value) => {
    if (isPromoVideoActive() && lyricsFsVideo && Number.isFinite(lyricsFsVideo.duration) && lyricsFsVideo.duration > 0) {
      const target = (value / 100) * lyricsFsVideo.duration;
      try { lyricsFsVideo.currentTime = target; } catch(_) {}
      syncPromoVideoUI();
      return;
    }
    if (popupActive) {
      if (popupState.duration) {
        const t = (value / 100) * popupState.duration;
        const ok = postToPopup({ cmd: 'seek', currentTime: t }, { retries: 3, delay: 100 });
        if (!ok) {
          // fallback to local if needed
          if (audio.duration) audio.currentTime = (value / 100) * audio.duration;
        }
      }
      return;
    }
    if (audio.duration) {
      audio.currentTime = (value / 100) * audio.duration;
    }
    // Sync inline video position when visible
    if (videoPanel && videoPanel.style.display === 'block' && inlineVideo && inlineVideo.duration) {
      try { inlineVideo.currentTime = (value / 100) * inlineVideo.duration; } catch(_) {}
    }
  };
  
  seekBar.oninput = () => {
    handleSeekBarInput(seekBar.value);
  };
  
  if (carouselSeekBar) {
    carouselSeekBar.oninput = () => {
      const value = carouselSeekBar.value;
      if (carouselSeekBarProgress) carouselSeekBarProgress.style.width = value + '%';
      handleSeekBarInput(value);
      // Also sync main seek bar
      seekBar.value = value;
    };
  }
  volumeBar.oninput = () => {
    const newVolume = volumeBar.value / 100;
    const promoActive = isPromoVideoActive() && lyricsFsVideo;
    if (popupActive) {
      const ok = postToPopup({ cmd: 'setVolume', volume: newVolume }, { retries: 3, delay: 100 });
      if (!ok) {
        // fallback to local update
        audio.volume = newVolume;
      }
    } else {
      audio.volume = newVolume;
    }
    if (promoActive) {
      try { lyricsFsVideo.volume = newVolume; } catch(_) {}
      lyricsFsVideo.muted = isMuted || newVolume === 0;
    }
    // If user manually changes volume, unmute
    if (isMuted && newVolume > 0) {
      isMuted = false;
      if (promoActive) {
        lyricsFsVideo.muted = false;
      }
      updateMuteUI();
    }
    savePlayerState();
  };
  shuffleBtn.onclick = () => {
    shuffleEnabled = !shuffleEnabled;
    if (shuffleEnabled) {
      // Shuffle current queue but keep the current track first
      const current = trackQueue[queueIndex];
      if (!originalQueue || !originalQueue.length) originalQueue = trackQueue.slice();
      const rest = trackQueue.filter((_, idx) => idx !== queueIndex);
      for (let i = rest.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        const tmp = rest[i]; rest[i] = rest[j]; rest[j] = tmp;
      }
      trackQueue = [current, ...rest];
      queueIndex = 0;
      saveQueue();
      renderQueueUI();
    } else {
      // Restore original album/order and keep current track position
      if (originalQueue && originalQueue.length) {
        const current = trackQueue[queueIndex];
        trackQueue = originalQueue.slice();
        const idx = current ? trackQueue.findIndex(t => t && current && t.src === current.src) : -1;
        queueIndex = idx >= 0 ? idx : 0;
        saveQueue();
        renderQueueUI();
      }
    }
    updateShuffleUI();
    
    // Update fullscreen content if in fullscreen mode
    if (isFullscreen && fullscreenOverlay) {
      const currentTrack = trackQueue[queueIndex];
      if (currentTrack) {
        fullscreenTitle.textContent = currentTrack.title || '';
        fullscreenArtist.textContent = currentTrack.artist || '';
        
        const hasVideo = currentTrack.video || false;
        if (hasVideo && currentTrack.video) {
          fullscreenVideo.src = currentTrack.video;
          fullscreenVideo.style.display = 'block';
          fullscreenCover.style.display = 'none';
          fullscreenVideo.play().catch(() => {});
        } else {
          fullscreenCover.src = currentTrack.cover || '';
          fullscreenCover.style.display = 'block';
          fullscreenVideo.style.display = 'none';
          fullscreenVideo.src = '';
        }
      }
    }
    
    savePlayerState();
  };
  repeatBtn.onclick = () => {
    // Toggle one-time replay of the current track
    if (!trackQueue.length) return;
    if (!replayOnceEnabled) {
      // Enable: insert a duplicate of current track right after it
      const current = trackQueue[queueIndex];
      if (!current) return;
      // Remove existing pending duplicate if any
      if (replayToken) {
        const idx = trackQueue.findIndex(x => x && x._replayToken === replayToken);
        if (idx >= 0 && idx !== queueIndex) trackQueue.splice(idx, 1);
      }
      const next = trackQueue[queueIndex + 1];
      if (next && next.src === current.src) {
        // Reuse next as the duplicate and tag it instead of adding a new one
        replayToken = 'replay_' + Date.now() + '_' + Math.random().toString(36).slice(2);
        trackQueue[queueIndex + 1] = { ...next, _replayToken: replayToken };
      } else {
        replayToken = 'replay_' + Date.now() + '_' + Math.random().toString(36).slice(2);
        const dup = { ...current, _replayToken: replayToken };
        trackQueue.splice(queueIndex + 1, 0, dup);
      }
      replayOnceEnabled = true;
      saveQueue();
      renderQueueUI();
    } else {
      // Disable: remove the pending duplicate from the queue
      if (replayToken) {
        const idx = trackQueue.findIndex(x => x && x._replayToken === replayToken);
        if (idx >= 0) {
          // Adjust queueIndex if needed
          if (idx < queueIndex) queueIndex = Math.max(0, queueIndex - 1);
          trackQueue.splice(idx, 1);
          saveQueue();
          renderQueueUI();
        }
      }
      // Also handle immediate-next duplicate without token
      if (!replayToken) {
        const cur = trackQueue[queueIndex];
        const next = trackQueue[queueIndex + 1];
        if (cur && next && cur.src === next.src) {
          trackQueue.splice(queueIndex + 1, 1);
          saveQueue();
          renderQueueUI();
        }
      }
      replayOnceEnabled = false;
      replayToken = null;
    }
    updateRepeatUI();
    savePlayerState();
  };
  nextBtn.onclick = () => playNext(false);
  
  prevBtn.onclick = () => playPrev();

  queueBtn.onclick = () => toggleQueuePanel();
  queueClose.onclick = () => toggleQueuePanel(false);
  if (queueFab) { try { queueFab.remove(); } catch(_) {} }
  if (queueMini) queueMini.onclick = () => toggleQueuePanel(true);

  // Keep queue fab glued above the player height (no random position)
  // No floating button anymore

  // Helper: open inline media panel with robust fallback from video to cover
  function openInlineMedia(url, coverSrc) {
    if (!videoPanel) return;
    videoPanel.style.display = 'block';
    // Reset states
    try { inlineVideo.pause(); } catch(_) {}
    // Reset sources
    try { while (inlineVideo.firstChild) inlineVideo.removeChild(inlineVideo.firstChild); } catch(_){ }
    inlineVideo.removeAttribute('src');
    inlineVideo.load();
    inlineVideo.style.display = 'none';
    if (inlineCover) { inlineCover.style.display = 'none'; }

    // If no URL – show cover but DO NOT restart or seek audio
    if (!url) {
      if (inlineCover) { inlineCover.src = coverSrc || ''; inlineCover.style.display = 'block'; }
      // Keep video panel open as visualizer; ensure UI reflects current play state
      updatePlayPauseUI();
      return;
    }

    // Setup event handlers for fallback
    const onError = () => {
      inlineVideo.style.display = 'none';
      if (inlineCover) { inlineCover.src = coverSrc || ''; inlineCover.style.display = 'block'; }
      inlineVideo.removeEventListener('error', onError);
      inlineVideo.removeEventListener('loadeddata', onLoaded);
      inlineVideo.removeEventListener('canplay', onLoaded);
      saveVideoState({ open: true, url, currentTime: audio.currentTime||0, playing: false });
    };
    const onLoaded = () => {
      if (inlineCover) inlineCover.style.display = 'none';
      inlineVideo.style.display = 'block';
      // Align to captured resume time (dataset) or current audio time with retries to avoid starting from 0
      let resumeTime = 0; try { resumeTime = parseFloat(playerContainer.dataset.resumeTime || '0') || 0; } catch(_) {}
      if (!resumeTime) { try { resumeTime = audio.currentTime || 0; } catch(_) {} }
      const trySetTime = () => { try { inlineVideo.currentTime = resumeTime; } catch(_) {} };
      trySetTime();
      let attempts = 0;
      const ensurePosition = () => {
        attempts++;
        try {
          const ok = !isNaN(inlineVideo.currentTime) && Math.abs(inlineVideo.currentTime - resumeTime) < 0.35;
          if (!ok && attempts < 10) { setTimeout(ensurePosition, 60); trySetTime(); return; }
        } catch(_) { if (attempts < 10) { setTimeout(ensurePosition, 60); trySetTime(); return; } }
        const shouldPlay = playerContainer.dataset.wasPlayingVideoSwitch === '1' || !audio.paused;
        if (shouldPlay) { try { inlineVideo.play().catch(()=>{}); } catch(_) {} try { audio.pause(); } catch(_) {} }
        updatePlayPauseUI();
        saveVideoState({ open: true, url, currentTime: inlineVideo.currentTime||0, playing: shouldPlay });
      };
      setTimeout(ensurePosition, 0);
      inlineVideo.removeEventListener('error', onError);
      inlineVideo.removeEventListener('loadeddata', onLoaded);
      inlineVideo.removeEventListener('canplay', onLoaded);
      clearTimeout(fallbackTimer);
    };
    inlineVideo.addEventListener('error', onError);
    inlineVideo.addEventListener('loadeddata', onLoaded);
    inlineVideo.addEventListener('canplay', onLoaded);
    // Показать видео сразу, а не ждать событий (если что — сработает onError)
    inlineVideo.style.display = 'block';
    if (inlineCover) inlineCover.style.display = 'none';
    inlineVideo.poster = coverSrc || '';
    // Safety fallback: если через 4s нет readyState>=2, оставляем видео, не скрываем его
    const fallbackTimer = setTimeout(() => { /* noop, держим видео видимым */ }, 4000);
    // Build <source> with explicit type to avoid MIME sniffing issues
    const lower = (url||'').toLowerCase();
    const type = lower.endsWith('.webm') ? 'video/webm' : 'video/mp4';
    const source = document.createElement('source');
    // Do not double-encode. Assume url already properly encoded.
    source.src = url;
    source.type = type;
    inlineVideo.appendChild(source);
    inlineVideo.load();

    // Remove existing event listeners to avoid duplicates
    try {
      inlineVideo.removeEventListener('timeupdate', handleVideoTimeUpdate);
      inlineVideo.removeEventListener('loadedmetadata', handleVideoLoadedMetadata);
      inlineVideo.removeEventListener('seeked', handleVideoSeeked);
      inlineVideo.removeEventListener('ended', handleVideoEnded);
      inlineVideo.removeEventListener('play', handleVideoPlay);
      inlineVideo.removeEventListener('pause', handleVideoPause);
    } catch(_) {}

    // Define event handlers
    const handleVideoTimeUpdate = () => {
      if (videoPanel && videoPanel.style.display === 'block') {
        try {
          seekBar.value = inlineVideo.duration ? (inlineVideo.currentTime / inlineVideo.duration) * 100 : 0;
          currentTimeEl.textContent = formatTime(inlineVideo.currentTime || 0);
          if (!isNaN(inlineVideo.currentTime)) audio.currentTime = inlineVideo.currentTime;
          savePlayerStateThrottled();
        } catch(_) {}
      }
    };
    
    const handleVideoLoadedMetadata = () => {
      if (videoPanel && videoPanel.style.display === 'block') {
        try { durationEl.textContent = formatTime(inlineVideo.duration || 0); } catch(_) {}
      }
    };
    
    const handleVideoSeeked = () => {
      if (videoPanel && videoPanel.style.display === 'block') {
        try { audio.currentTime = inlineVideo.currentTime || 0; } catch(_) {}
      }
    };
    
    const handleVideoEnded = () => {
      console.log('Inline video ended, playing next track');
      if (videoPanel && videoPanel.style.display === 'block') {
        playNext(true);
      }
    };
    
    const handleVideoPlay = () => { 
      try { audio.pause(); } catch(_) {} 
      updatePlayPauseUI(); 
      saveVideoState({ open: true, url, currentTime: inlineVideo.currentTime||0, playing: true }); 
    };
    
    const handleVideoPause = () => { 
      updatePlayPauseUI(); 
      saveVideoState({ open: true, url, currentTime: inlineVideo.currentTime||0, playing: false }); 
    };

    // Keep UI and audio time in sync with video when panel visible
    try {
      inlineVideo.addEventListener('timeupdate', handleVideoTimeUpdate);
      inlineVideo.addEventListener('loadedmetadata', handleVideoLoadedMetadata);
      inlineVideo.addEventListener('seeked', handleVideoSeeked);
      inlineVideo.addEventListener('ended', handleVideoEnded);
      inlineVideo.addEventListener('play', handleVideoPlay);
      inlineVideo.addEventListener('pause', handleVideoPause);
    } catch(_) {}
  }

  function updateVideoButtonUI() {
    const url = String((playerContainer.dataset && playerContainer.dataset.videoUrl) || '').trim();
    const hasVideo = !!url;
    const panelOpen = !!(videoPanel && videoPanel.style.display === 'block');
    const karaokeVideoOpen = !!(lyricsVisible && lyricsFsVideoWrap && lyricsFsVideoWrap.style.display === 'block');
    const active = panelOpen || karaokeVideoOpen;
    [videoBtn, videoMiniBtn].forEach((btn) => {
      if (!btn) return;
      btn.disabled = !hasVideo;
      btn.classList.toggle('btn-active', hasVideo && active);
      btn.title = hasVideo ? (active ? 'Скрыть клип' : 'Показать клип') : 'Клип недоступен';
    });
  }

  function handleVideoButtonClick() {
    if (lyricsVisible && lyricsFsVideoWrap) {
      // Toggle video in karaoke slot: if already visible, close without restarting
      try {
        if (lyricsFsVideoWrap.style.display === 'block') {
          let t = 0; let wasPlaying = false; let hadMedia = false;
          try { hadMedia = !!(lyricsFsVideo.currentSrc || lyricsFsVideo.readyState >= 1); } catch(_){}
          try { t = isNaN(lyricsFsVideo.currentTime) ? 0 : (lyricsFsVideo.currentTime||0); } catch(_){}
          try { wasPlaying = !lyricsFsVideo.paused && !lyricsFsVideo.ended; } catch(_){}
          try { lyricsFsVideo.pause(); } catch(_){}
          try { lyricsFsVideo.removeAttribute('src'); lyricsFsVideo.load(); lyricsFsVideo.style.display='none'; } catch(_){}
          // Hide video-cover placeholder and show main cover back
          try { if (lyricsFsVideoCover) lyricsFsVideoCover.style.display='none'; } catch(_){}
          lyricsFsVideoWrap.style.display = 'none';
          try { if (lyricsFsCover) lyricsFsCover.style.display = 'block'; } catch(_){}
          // Restore audio if there was media
          try { if (hadMedia) audio.currentTime = t; } catch(_){}
          try { if (wasPlaying) audio.play().catch(()=>{}); } catch(_){}
          // Ensure audio is unmuted after closing karaoke video
          try { audio.muted = false; } catch(_) {}
          updatePlayPauseUI();
          updateVideoButtonUI();
          return;
        }
      } catch(_) {}
      // Open video in karaoke slot
      const ds = (playerContainer.dataset && typeof playerContainer.dataset.videoUrl !== 'undefined') ? playerContainer.dataset.videoUrl : '';
      let url = ds && ds.trim() !== '' ? ds : ((trackQueue[queueIndex] && trackQueue[queueIndex].video_url) ? trackQueue[queueIndex].video_url : '');
      // Normalize relative paths like "tracks/video/.." via API proxy for proper serving
      if (url && !/^https?:/i.test(url) && url.indexOf('/public/src/api/video.php?f=') === -1) {
        const i = url.indexOf('tracks/');
        const rel = i !== -1 ? url.slice(i) : url.replace(/^\/+/, '');
        url = '/muzic2/public/src/api/video.php?f=' + encodeURIComponent(rel);
      }
      if (!url) {
        // no video: show cover placeholder (keep at top of left column)
        lyricsFsVideoWrap.style.display = 'block';
        if (lyricsFsCover) lyricsFsCover.style.display = 'none';
        if (lyricsFsVideo) { try { lyricsFsVideo.pause(); } catch(_) {}; lyricsFsVideo.src=''; lyricsFsVideo.style.display='none'; }
        if (lyricsFsVideoCover) { lyricsFsVideoCover.src = (cover && cover.src) ? cover.src : ''; lyricsFsVideoCover.style.display='block'; }
        updateVideoButtonUI();
        return;
      }
      // Reset states
      try { lyricsFsVideo.pause(); } catch(_) {}
      if (lyricsFsVideo) {
        lyricsFsVideo.style.display = 'block';
        lyricsFsVideo.src = url;
        lyricsFsVideo.currentTime = isNaN(audio.currentTime) ? 0 : (audio.currentTime||0);
        // ensure no native controls
        try { lyricsFsVideo.controls = false; } catch(_) {}
        lyricsFsVideo.play().catch(()=>{});
      }
      if (lyricsFsVideoCover) lyricsFsVideoCover.style.display = 'none';
      if (lyricsFsCover) lyricsFsCover.style.display = 'none';
      lyricsFsVideoWrap.style.display = 'block';
      // Make sure audio element is muted and video outputs the sound
      try { audio.muted = true; } catch(_) {}
      try { lyricsFsVideo.muted = false; } catch(_) {}
      // Ensure timeupdate keeps running while video plays
      try {
        if (lyricsFsVideo) {
          const rebroadcast = () => { try { updateLyricsHighlight(lyricsFsVideo.currentTime||0); } catch(_) {} };
          lyricsFsVideo.removeEventListener('timeupdate', rebroadcast);
          lyricsFsVideo.addEventListener('timeupdate', rebroadcast);
          // Also fix highlight after manual seek in karaoke video
          const onSeeked = () => {
            try { currentLyricsIndex = -1; } catch(_) {}
            try {
              const t = isNaN(lyricsFsVideo.currentTime) ? 0 : (lyricsFsVideo.currentTime||0);
              updateLyricsHighlight(t);
              // Scroll to nearest line to keep anchor
              if (lyricsVisible && typeof scrollLyricsToAnchorForIndex === 'function') {
                let idx = 0;
                for (let i = 0; i < lyricsLines.length; i++) { if (lyricsLines[i].time <= t) idx = i; else break; }
                scrollLyricsToAnchorForIndex(idx);
              }
            } catch(_) {}
          };
          lyricsFsVideo.removeEventListener('seeked', onSeeked);
          lyricsFsVideo.addEventListener('seeked', onSeeked);
        }
      } catch(_) {}
      updateVideoButtonUI();
      return;
    }
    const ds = (playerContainer.dataset && typeof playerContainer.dataset.videoUrl !== 'undefined') ? playerContainer.dataset.videoUrl : '';
    let url = ds && ds.trim() !== '' ? ds : ((trackQueue[queueIndex] && trackQueue[queueIndex].video_url) ? trackQueue[queueIndex].video_url : '');
    // If looks like raw tracks/... path, route via proxy for proper Content-Type
    if (url && !/^https?:/i.test(url) && url.indexOf('/public/src/api/video.php?f=') === -1) {
      const i = url.indexOf('tracks/');
      const rel = i !== -1 ? url.slice(i) : url.replace(/^\/+/, '');
      url = '/muzic2/public/src/api/video.php?f=' + encodeURIComponent(rel);
    }
    try { console.debug('[player] video button URL =', url); } catch(_){ }
    if (!videoPanel) return;
    const visible = videoPanel.style.display === 'block';
    if (visible) {
      // Capture position and playing state BEFORE clearing the source
      let t = 0; let wasPlaying = false; let hadMedia = false;
      try { hadMedia = !!(inlineVideo.currentSrc || inlineVideo.readyState >= 1); } catch(_) { hadMedia=false; }
      try { t = inlineVideo.currentTime || 0; wasPlaying = !inlineVideo.paused && !inlineVideo.ended; } catch(_) {}
      try { inlineVideo.pause(); } catch(_) {}
      inlineVideo.removeAttribute('src'); inlineVideo.load();
      if (inlineCover) inlineCover.style.display = 'none';
      videoPanel.style.display = 'none';
      // Restore audio to captured position and resume if video was playing
      const resumeAudio = () => {
        // Only seek if video actually had media; otherwise don't jump to 0
        if (hadMedia) { try { audio.currentTime = t; } catch(_) {} }
        if (wasPlaying) { try { audio.play().catch(()=>{}); } catch(_) {} }
      };
      if (isNaN(audio.duration) || !isFinite(audio.duration) || audio.readyState < 1) {
        const once = function(){ audio.removeEventListener('loadedmetadata', once); resumeAudio(); };
        audio.addEventListener('loadedmetadata', once);
      } else {
        resumeAudio();
      }
      updatePlayPauseUI();
      updateVideoButtonUI();
      return;
    }
    // Capture current audio time and play-state for precise resume inside openInlineMedia
    try { playerContainer.dataset.resumeTime = String(audio.currentTime || 0); playerContainer.dataset.wasPlayingVideoSwitch = (!audio.paused) ? '1' : '0'; } catch(_) {}
    openInlineMedia(url, (cover && cover.src) ? cover.src : '');
    updateVideoButtonUI();
  }

  if (videoBtn) videoBtn.onclick = () => { if (videoBtn.disabled) return; handleVideoButtonClick(); };
  if (videoMiniBtn) videoMiniBtn.onclick = () => { if (videoMiniBtn.disabled) return; handleVideoButtonClick(); };
  if (videoClose) videoClose.onclick = () => { let t=0, wasPlaying=false, hadMedia=false; try { hadMedia = !!(inlineVideo.currentSrc || inlineVideo.readyState >= 1); t = inlineVideo.currentTime || 0; wasPlaying = !inlineVideo.paused && !inlineVideo.ended; } catch(_){}; try { inlineVideo.pause(); } catch(_){}; inlineVideo.src=''; if (inlineCover) inlineCover.style.display='none'; videoPanel.style.display='none'; if (hadMedia) { try { audio.currentTime = t; } catch(_){} } if (wasPlaying) { try { audio.play().catch(()=>{}); } catch(_) {} } updatePlayPauseUI(); updateVideoButtonUI(); };

  // Crossfade panel controls
  if (crossfadeBtn) crossfadeBtn.onclick = () => {
    if (crossfadePanel) {
      const visible = crossfadePanel.style.display === 'block';
      crossfadePanel.style.display = visible ? 'none' : 'block';
      if (!visible && smartCrossfade) {
        smartCrossfade.updateUI();
        updateCrossfadePreview();
      }
    }
  };

  // Update crossfade button visual state
  function updateCrossfadeButtonState() {
    if (crossfadeBtn && smartCrossfade) {
      crossfadeBtn.classList.toggle('btn-active', smartCrossfade.isEnabled);
      crossfadeBtn.title = smartCrossfade.isEnabled ? 'Кроссфейд включён' : 'Умный кроссфейд';
    }
  }

  // Test crossfade function for debugging
  window.testCrossfade = function() {
    console.log('Manual crossfade test triggered');
    if (smartCrossfade && smartCrossfade.isEnabled && trackQueue.length > 1) {
      console.log('Starting manual crossfade test...');
      
      const currentTrack = trackQueue[queueIndex];
      const nextTrack = trackQueue[(queueIndex + 1) % trackQueue.length];
      
      console.log('Current track:', currentTrack.title);
      console.log('Next track:', nextTrack.title);
      
      // Create next audio element
      const nextAudio = new Audio(nextTrack.src);
      nextAudio.volume = 0;
      nextAudio.currentTime = 0;
      
      // Start next track
      nextAudio.play().catch(e => console.warn('Next track play failed:', e));
      
      // Fade transition
      const crossfadeDuration = smartCrossfade.crossfadeDuration || 8;
      const fadeSteps = 100;
      const stepDuration = (crossfadeDuration * 1000) / fadeSteps;
      let step = 0;
      
      const fadeInterval = setInterval(() => {
        step++;
        const progress = step / fadeSteps;
        
        // Smooth fade curve
        const currentVolume = Math.pow(1 - progress, 2);
        const nextVolume = Math.pow(progress, 2);
        
        audio.volume = Math.max(0, Math.min(1, currentVolume));
        nextAudio.volume = Math.max(0, Math.min(1, nextVolume));
        
        console.log(`Manual fade step ${step}/${fadeSteps}: current=${currentVolume.toFixed(2)}, next=${nextVolume.toFixed(2)}`);
        
        if (step >= fadeSteps) {
          clearInterval(fadeInterval);
          console.log('Manual crossfade completed!');
          
          // Switch to next track
          queueIndex = (queueIndex + 1) % trackQueue.length;
          audio.pause();
          audio.src = nextTrack.src;
          audio.currentTime = 0;
          audio.volume = 1;
          audio.play().catch(() => {});
          
          // Update UI
          trackTitle.textContent = nextTrack.title || '';
          trackArtist.textContent = nextTrack.artist || '';
          cover.src = nextTrack.cover || cover.src;
          updatePlayPauseUI();
          
          // Clean up
          nextAudio.pause();
          nextAudio.src = '';
        }
      }, stepDuration);
      
    } else {
      console.log('Cannot test crossfade: not enabled or not enough tracks');
      console.log('smartCrossfade:', !!smartCrossfade);
      console.log('isEnabled:', smartCrossfade?.isEnabled);
      console.log('trackQueue.length:', trackQueue.length);
    }
  };
  
  if (crossfadeClose) crossfadeClose.onclick = () => {
    if (crossfadePanel) crossfadePanel.style.display = 'none';
  };

  // Crossfade settings handlers
  const crossfadeToggle = playerRoot.querySelector('#crossfade-toggle');
  const crossfadeDuration = playerRoot.querySelector('#crossfade-duration');
  const crossfadeAggressiveness = playerRoot.querySelector('#crossfade-aggressiveness');
  const crossfadeDurationValue = playerRoot.querySelector('#crossfade-duration-value');
  const crossfadeAggressivenessValue = playerRoot.querySelector('#crossfade-aggressiveness-value');

  if (crossfadeToggle && smartCrossfade) {
    crossfadeToggle.onchange = () => {
      smartCrossfade.updateSettings({ enabled: crossfadeToggle.checked });
      updateCrossfadeButtonState();
    };
  }

  if (crossfadeDuration && smartCrossfade) {
    crossfadeDuration.oninput = () => {
      const value = crossfadeDuration.value;
      if (crossfadeDurationValue) crossfadeDurationValue.textContent = value + 'с';
      smartCrossfade.updateSettings({ duration: parseInt(value) });
    };
  }

  if (crossfadeAggressiveness && smartCrossfade) {
    crossfadeAggressiveness.oninput = () => {
      const value = crossfadeAggressiveness.value;
      if (crossfadeAggressivenessValue) crossfadeAggressivenessValue.textContent = Math.round(value * 100) + '%';
      smartCrossfade.updateSettings({ aggressiveness: parseFloat(value) });
    };
  }

  // Update crossfade preview with current track analysis
  function updateCrossfadePreview() {
    if (!smartCrossfade || !crossfadePanel) return;
    
    const preview = playerRoot.querySelector('#crossfade-preview');
    if (!preview) return;
    
    if (trackQueue.length > 1) {
      const currentTrack = trackQueue[queueIndex];
      const nextTrack = trackQueue[(queueIndex + 1) % trackQueue.length];
      
      preview.innerHTML = `
        <div style="margin-bottom:8px; font-weight:600;">Анализ совместимости:</div>
        <div style="font-size:11px; color:#aaa;">
          <div>Текущий: ${currentTrack.title || 'Неизвестно'}</div>
          <div>Следующий: ${nextTrack.title || 'Неизвестно'}</div>
          <div style="margin-top:8px; color:#888;">Анализ BPM и тональности...</div>
        </div>
      `;
      
      // Analyze tracks for compatibility
      if (currentTrack.src && nextTrack.src) {
        Promise.all([
          smartCrossfade.analyzeTrack(currentTrack.src),
          smartCrossfade.analyzeTrack(nextTrack.src)
        ]).then(([currentAnalysis, nextAnalysis]) => {
          const compatibility = smartCrossfade.calculateCompatibility(currentAnalysis, nextAnalysis);
          const transition = smartCrossfade.findOptimalTransition(currentAnalysis, nextAnalysis);
          
          preview.innerHTML = `
            <div style="margin-bottom:8px; font-weight:600;">Совместимость: ${Math.round(compatibility * 100)}%</div>
            <div style="font-size:11px; color:#aaa;">
              <div>BPM: ${currentAnalysis.bpm} → ${nextAnalysis.bpm}</div>
              <div>Тональность: ${currentAnalysis.key} → ${nextAnalysis.key}</div>
              <div>Переход: ${transition.fadeType === 'smooth' ? 'Плавный' : 'Быстрый'}</div>
              <div style="margin-top:8px; color:#888;">Длительность: ${transition.duration.toFixed(1)}с</div>
            </div>
          `;
        }).catch(e => {
          preview.innerHTML = '<div style="color:#f44;">Ошибка анализа треков</div>';
        });
      }
    } else {
      preview.innerHTML = '<div style="color:#888;">Добавьте треки в очередь для анализа</div>';
    }
  }

  // Carousel mode functions - точно по коду из CoverFlow.tsx
  function getCarouselTransform(index) {
    const baseOffset = index - carouselCurrentIndex;
    const offset = baseOffset + (carouselIsDragging ? carouselDragOffset / 100 : 0);
    
    const isMobile = window.innerWidth <= 768;
    const SPACING = isMobile ? 120 : 160;
    const ROTATION = 55;
    
    if (Math.abs(offset) < 0.1) {
      return `translateX(0px) translateZ(300px) rotateY(0deg) scale(1.3)`;
    } else if (offset < 0) {
      const distance = Math.abs(offset);
      const x = -SPACING * distance;
      const z = -80 * distance;
      const scale = Math.max(0.8, 1.15 - (distance * 0.04));
      return `translateX(${x}px) translateZ(${z}px) rotateY(${ROTATION}deg) scale(${scale})`;
    } else {
      const distance = Math.abs(offset);
      const x = SPACING * distance;
      const z = -80 * distance;
      const scale = Math.max(0.8, 1.15 - (distance * 0.04));
      return `translateX(${x}px) translateZ(${z}px) rotateY(-${ROTATION}deg) scale(${scale})`;
    }
  }
  
  function getCarouselZIndex(index) {
    const MAX_ZINDEX = 1000;
    const offset = Math.abs(index - carouselCurrentIndex);
    
    if (offset === 0) return MAX_ZINDEX;
    if (offset === 1) return 100;
    if (offset === 2) return 50;
    if (offset === 3) return 25;
    if (offset === 4) return 15;
    return Math.max(1, 10 - offset);
  }
  
  function getCarouselOpacity(index) {
    const baseOffset = index - carouselCurrentIndex;
    const offset = Math.abs(baseOffset + (carouselIsDragging ? carouselDragOffset / 100 : 0));
    
    if (offset < 0.1) return 1;
    if (offset <= 1) return 1;
    if (offset <= 2) return 1;
    if (offset <= 3) return 0.95;
    if (offset <= 4) return 0.9;
    return Math.max(0.8, 0.9 - ((offset - 4) * 0.05));
  }
  
  function updateCarouselInfo() {
    const currentTrack = trackQueue[carouselCurrentIndex];
    if (currentTrack && carouselTrackTitle && carouselTrackArtist) {
      carouselTrackTitle.textContent = currentTrack.title || '';
      try {
        const base = (currentTrack && typeof currentTrack.artist === 'string') ? currentTrack.artist.trim() : '';
        const feats = (currentTrack && typeof currentTrack.feats === 'string') ? currentTrack.feats.trim() : '';
        const combinedArtist = feats ? (base ? `${base}, ${feats}` : feats) : base;
        carouselTrackArtist.textContent = combinedArtist;
      } catch(_) {
        carouselTrackArtist.textContent = currentTrack.artist || '';
      }
    }
  }
  
  function updateCarousel() {
    const carouselTrackEl = playerRoot.querySelector('#carousel-track');
    if (!carouselTrackEl || !trackQueue.length) return;
    
    carouselTrackEl.innerHTML = '';
    
    trackQueue.forEach((track, index) => {
      if (!track) return;
    
      const isActive = index === carouselCurrentIndex;
      
      const wrapper = document.createElement('div');
      wrapper.className = 'carousel-cover-wrapper' + (isActive ? ' active' : '') + (carouselIsDragging ? ' dragging' : '');
      
      const container = document.createElement('div');
      container.className = 'carousel-cover-container';
      const isMobile = window.innerWidth <= 768;
      const albumSize = isMobile ? 170 : 200;
      container.style.width = albumSize + 'px';
      container.style.height = albumSize + 'px';
      
      const cover = document.createElement('img');
      cover.className = 'carousel-cover';
      cover.src = track.cover || '';
      cover.alt = track.title || '';
      cover.draggable = false;
      container.appendChild(cover);
      
      const glass = document.createElement('div');
      glass.className = 'carousel-cover-glass';
      container.appendChild(glass);
      
      if (track.cover) {
      const reflection = document.createElement('div');
      reflection.className = 'carousel-cover-reflection';
        reflection.style.backgroundImage = `url(${track.cover})`;
      container.appendChild(reflection);
      }
      
      const floor = document.createElement('div');
      floor.className = 'carousel-cover-floor';
      container.appendChild(floor);
      
      if (isActive) {
        const border = document.createElement('div');
        border.className = 'carousel-cover-border';
        container.appendChild(border);
        
        const indicator = document.createElement('div');
        indicator.className = 'carousel-cover-indicator';
        container.appendChild(indicator);
        
        const topLight = document.createElement('div');
        topLight.className = 'carousel-cover-top-light';
        container.appendChild(topLight);
      }
      
      wrapper.appendChild(container);
      
      wrapper.style.transform = getCarouselTransform(index);
      wrapper.style.zIndex = getCarouselZIndex(index) + 1000;
      wrapper.style.opacity = getCarouselOpacity(index);
      wrapper.style.transformOrigin = 'center center';
      wrapper.style.transformStyle = 'preserve-3d';
      wrapper.style.transition = carouselIsDragging ? 'none' : 'all 400ms cubic-bezier(0.23, 1, 0.32, 1)';
      
      wrapper.onclick = (e) => {
        e.stopPropagation();
        if (!carouselIsDragging && index !== carouselCurrentIndex) {
          carouselCurrentIndex = index;
          updateCarousel();
          if (window.setQueue && window.playFromQueue) {
            window.setQueue(trackQueue, index);
            window.playFromQueue(index);
          }
        }
      };
      
      carouselTrackEl.appendChild(wrapper);
    });
    
    // Update carousel info after rendering
    updateCarouselInfo();
  }
  
  function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  }
  
  function enterCarouselMode() {
    const overlay = playerRoot.querySelector('#carousel-overlay');
    if (!overlay) return;
    if (!trackQueue.length) return;
    isCarouselMode = true;
    // Use current queueIndex, or 0 if not set, ensuring we start with the currently playing track
    carouselCurrentIndex = (typeof queueIndex === 'number' && queueIndex >= 0) ? queueIndex : 0;
    // Ensure index is within bounds
    if (carouselCurrentIndex >= trackQueue.length) {
      carouselCurrentIndex = 0;
    }
    carouselIsDragging = false;
    carouselDragOffset = 0;
    updateCarousel();
    overlay.style.display = 'block';
    overlay.style.setProperty('display', 'block', 'important');
    document.body.style.overflow = 'hidden';
    // Sync button states
    updatePlayPauseUI();
    updateShuffleUI();
  }
  
  function exitCarouselMode() {
    const overlay = playerRoot.querySelector('#carousel-overlay');
    if (!overlay) return;
    isCarouselMode = false;
    overlay.style.display = 'none';
    document.body.style.overflow = '';
  }

  fullscreenBtn.onclick = () => {
    if (isCarouselMode) {
      exitCarouselMode();
    } else {
      enterCarouselMode();
    }
  };
  // Like button - single handler
  if (likeBtn) {
    likeBtn.onclick = async () => {
      if (!likedSet || typeof likedSet.has !== 'function') likedSet = new Set();
      let tid = normalizeTrackId({ id: currentTrackId });
      if (!tid && trackQueue[queueIndex]) {
        tid = normalizeTrackId(trackQueue[queueIndex]);
      }
      if (!tid) {
        tid = await resolveTrackIdFromPlayer();
        if (tid) {
          currentTrackId = tid;
          if (trackQueue[queueIndex]) {
            trackQueue[queueIndex].id = tid;
            try { saveQueue(); } catch (_) {}
          }
        }
      }
      if (!tid) {
        likeBtn.title = 'Не удалось определить трек в базе';
        setTimeout(() => { updatePlayerLikeUI(); }, 2500);
        return;
      }
      currentTrackId = tid;
      try {
        if (likedSet.has(tid)) {
          const r = await fetch(getLikesAPI(), { method: 'DELETE', credentials: 'include', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ track_id: tid }) });
          if (r.ok) likedSet.delete(tid);
        } else {
          const r = await fetch(getLikesAPI(), { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ track_id: tid }) });
          if (r.ok) likedSet.add(tid);
        }
      } catch (err) {
        console.error('Like request failed', err);
      }
      updatePlayerLikeUI();
      try {
        document.dispatchEvent(new CustomEvent('likes:updated', { detail: { trackId: tid, liked: likedSet.has(tid) } }));
      } catch (_) {}
      if (isCarouselMode) {
        updateCarousel();
      }
    };
  }

  fullscreenClose.onclick = () => exitFullscreen();
  fullscreenBack.onclick = () => exitFullscreen();
  
  if (carouselClose) {
    carouselClose.onclick = (e) => {
      e.stopPropagation();
      exitCarouselMode();
    };
  } else {
    // Fallback: find button dynamically
    const closeBtn = playerRoot.querySelector('#carousel-close');
    if (closeBtn) {
      closeBtn.onclick = (e) => {
        e.stopPropagation();
        exitCarouselMode();
      };
    }
  }
  
  if (carouselPrev) {
    carouselPrev.onclick = (e) => {
      e.stopPropagation();
      if (carouselCurrentIndex > 0) {
        carouselCurrentIndex--;
        updateCarousel();
        if (window.setQueue && window.playFromQueue) {
          window.setQueue(trackQueue, carouselCurrentIndex);
          window.playFromQueue(carouselCurrentIndex);
        }
      }
    };
  }
  
  if (carouselNext) {
    carouselNext.onclick = (e) => {
      e.stopPropagation();
      if (carouselCurrentIndex < trackQueue.length - 1) {
        carouselCurrentIndex++;
        updateCarousel();
        if (window.setQueue && window.playFromQueue) {
          window.setQueue(trackQueue, carouselCurrentIndex);
          window.playFromQueue(carouselCurrentIndex);
        }
      }
    };
  }
  
  if (carouselPlay) {
    carouselPlay.onclick = (e) => {
      e.stopPropagation();
      if (playBtn) playBtn.click();
    };
  }
  
  if (carouselShuffle) {
    carouselShuffle.onclick = (e) => {
      e.stopPropagation();
      if (shuffleBtn) shuffleBtn.click();
    };
  }
  
  if (carouselVolume) {
    carouselVolume.onclick = (e) => {
      e.stopPropagation();
      if (volumeBtn) volumeBtn.click();
    };
  }
  
  // Carousel menu button
  const carouselMenuBtn = playerRoot.querySelector('#carousel-menu-btn');
  if (carouselMenuBtn) {
    carouselMenuBtn.onclick = (e) => {
      e.stopPropagation();
      // TODO: Add menu functionality
      console.log('Menu clicked');
    };
  }
  
  // Carousel like button
  const carouselLikeBtn = playerRoot.querySelector('#carousel-like-btn');
  if (carouselLikeBtn) {
    carouselLikeBtn.onclick = (e) => {
      e.stopPropagation();
      if (likeBtn) likeBtn.click();
    };
    
    // Sync like state
    if (likeBtn) {
      const updateCarouselLikeState = () => {
        if (carouselLikeBtn && likeBtn) {
          const isLiked = likeBtn.classList.contains('liked') || likeBtn.getAttribute('aria-pressed') === 'true';
          if (isLiked) {
            carouselLikeBtn.classList.add('liked');
          } else {
            carouselLikeBtn.classList.remove('liked');
          }
        }
      };
      
      // Watch for like button changes
      const likeObserver = new MutationObserver(updateCarouselLikeState);
      if (likeBtn) {
        likeObserver.observe(likeBtn, { attributes: true, attributeFilter: ['class', 'aria-pressed'] });
      }
      updateCarouselLikeState();
    }
  }
  

  // Handle Escape key to exit fullscreen and carousel
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isFullscreen) {
      exitFullscreen();
    }
    if (e.key === 'Escape' && isCarouselMode) {
      exitCarouselMode();
    }
  });
  
  // Update carousel when track changes
  audio.addEventListener('play', () => {
    if (isCarouselMode && carouselCurrentIndex !== queueIndex) {
      carouselCurrentIndex = queueIndex;
      updateCarousel();
    }
  });
  
  // Update carousel when likes change
  document.addEventListener('likes:updated', (e) => {
    if (isCarouselMode && e.detail && e.detail.trackId) {
      const track = trackQueue[carouselCurrentIndex];
      if (track && track.id === e.detail.trackId) {
        updateCarousel();
      }
    }
  });
  
  // Drag/swipe support - точно по коду из CoverFlow.tsx
  const carouselTrackWrapper = playerRoot.querySelector('#carousel-track-wrapper');
  
  function handleCarouselDragStart(clientX) {
    if (!isCarouselMode) return;
    carouselIsDragging = true;
    carouselDragStartPos = clientX;
    carouselDragOffset = 0;
    carouselVelocity = 0;
    carouselLastDragTime = Date.now();
    updateCarousel();
  }
  
  function handleCarouselDragMove(clientX) {
    if (!isCarouselMode || !carouselIsDragging) return;
    
    const currentTime = Date.now();
    const deltaTime = currentTime - carouselLastDragTime;
    const newOffset = clientX - carouselDragStartPos;
    const deltaOffset = newOffset - carouselDragOffset;
    
    carouselDragOffset = newOffset;
    carouselVelocity = deltaOffset / Math.max(deltaTime, 1);
    carouselLastDragTime = currentTime;
    updateCarousel();
  }
  
  function handleCarouselDragEnd() {
    if (!isCarouselMode || !carouselIsDragging) return;
    carouselIsDragging = false;
    
    const threshold = 50;
    const velocityThreshold = 0.5;
    const highVelocityThreshold = 2.0;
    
    let targetIndex = carouselCurrentIndex;
    const dragDistance = Math.abs(carouselDragOffset);
    const dragVelocity = Math.abs(carouselVelocity);
    
    if (dragDistance > threshold || dragVelocity > velocityThreshold) {
      let jumpCount = 1;
      
      if (dragVelocity > highVelocityThreshold) {
        jumpCount = Math.min(2, Math.ceil(dragVelocity / 1.5));
      }
      
      if (dragDistance > threshold * 2) {
        jumpCount = Math.max(jumpCount, Math.floor(dragDistance / (threshold * 1.5)));
      }
      
      if (carouselDragOffset < 0 || carouselVelocity < -velocityThreshold) {
        targetIndex = Math.min(trackQueue.length - 1, carouselCurrentIndex + jumpCount);
      } else if (carouselDragOffset > 0 || carouselVelocity > velocityThreshold) {
        targetIndex = Math.max(0, carouselCurrentIndex - jumpCount);
      }
    }
    
    carouselCurrentIndex = targetIndex;
    carouselDragOffset = 0;
    carouselVelocity = 0;
    updateCarousel();
    if (window.setQueue && window.playFromQueue) {
      window.setQueue(trackQueue, carouselCurrentIndex);
      window.playFromQueue(carouselCurrentIndex);
    }
  }
  
  if (carouselTrackWrapper) {
    carouselTrackWrapper.addEventListener('mousedown', (e) => {
      e.preventDefault();
      handleCarouselDragStart(e.clientX);
    });
    
    document.addEventListener('mousemove', (e) => {
      if (!isCarouselMode || !carouselIsDragging) return;
      handleCarouselDragMove(e.clientX);
    });
    
    document.addEventListener('mouseup', () => {
      if (!isCarouselMode || !carouselIsDragging) return;
      handleCarouselDragEnd();
    });
    
    carouselTrackWrapper.addEventListener('touchstart', (e) => {
      handleCarouselDragStart(e.touches[0].clientX);
    });
    
    carouselTrackWrapper.addEventListener('touchmove', (e) => {
      e.preventDefault();
      handleCarouselDragMove(e.touches[0].clientX);
    });
    
    carouselTrackWrapper.addEventListener('touchend', () => {
      handleCarouselDragEnd();
    });
  }
  
  // Keyboard navigation - точно по коду из CoverFlow.tsx
  document.addEventListener('keydown', (e) => {
    if (!isCarouselMode) return;
    if (e.key === 'ArrowLeft') {
      e.preventDefault();
      const newIndex = Math.max(0, carouselCurrentIndex - 1);
      carouselCurrentIndex = newIndex;
      updateCarousel();
      if (window.setQueue && window.playFromQueue) {
        window.setQueue(trackQueue, newIndex);
        window.playFromQueue(newIndex);
      }
    } else if (e.key === 'ArrowRight') {
      e.preventDefault();
      const newIndex = Math.min(trackQueue.length - 1, carouselCurrentIndex + 1);
      carouselCurrentIndex = newIndex;
      updateCarousel();
      if (window.setQueue && window.playFromQueue) {
        window.setQueue(trackQueue, newIndex);
        window.playFromQueue(newIndex);
      }
    } else if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      if (window.setQueue && window.playFromQueue) {
        window.setQueue(trackQueue, carouselCurrentIndex);
        window.playFromQueue(carouselCurrentIndex);
      }
    }
  });

  volumeBtn.onclick = () => {
    const promoVideo = isPromoVideoActive() && lyricsFsVideo ? lyricsFsVideo : null;
    if (isMuted) {
      // Unmute: restore previous volume
      isMuted = false;
      audio.volume = previousVolume;
      volumeBar.value = Math.round(previousVolume * 100);
      if (promoVideo) {
        try { promoVideo.volume = previousVolume; } catch(_) {}
        promoVideo.muted = false;
      }
      if (popupActive) {
        postToPopup({ cmd: 'setVolume', volume: previousVolume });
      }
    } else {
      // Mute: save current volume and set to 0
      previousVolume = promoVideo ? (promoVideo.volume || audio.volume || 1) : audio.volume;
      isMuted = true;
      audio.volume = 0;
      volumeBar.value = 0;
      if (promoVideo) {
        promoVideo.muted = true;
        try { promoVideo.volume = 0; } catch(_) {}
      }
      if (popupActive) {
        postToPopup({ cmd: 'setVolume', volume: 0 });
      }
    }
    if (promoVideo && !isMuted) {
      promoVideo.muted = false;
    }
    updateMuteUI();
    savePlayerState();
  };

  // Persistence wiring
  ;['play', 'pause', 'seeked', 'volumechange', 'ended'].forEach(event => {
    audio.addEventListener(event, savePlayerState);
  });
  // Save on tab hide and before page unload to not lose position
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
      // Save current state on hide
      savePlayerState();
      // If video is playing, note it so we can resume on return
      try {
        const wasVideoOpen = (videoPanel && videoPanel.style.display==='block');
        const wasVideoPlaying = wasVideoOpen && inlineVideo && !inlineVideo.paused;
        playerContainer.dataset.wasPlayingOnHide = (wasVideoPlaying || (!wasVideoOpen && !audio.paused)) ? '1' : '0';
        // Persist video panel state
        if (wasVideoOpen) {
          saveVideoState({ open: true, url: (playerContainer.dataset && playerContainer.dataset.videoUrl) || '', currentTime: inlineVideo && !isNaN(inlineVideo.currentTime)? inlineVideo.currentTime : (audio.currentTime||0), playing: !!wasVideoPlaying });
        } else {
          saveVideoState({ open: false, url: (playerContainer.dataset && playerContainer.dataset.videoUrl) || '', currentTime: audio.currentTime||0, playing: !audio.paused });
        }
      } catch(_) {}
    } else if (document.visibilityState === 'visible') {
      // On return, if video panel open, resume video playback at synced time
      const vstate = loadVideoState();
      if (vstate && vstate.open) {
        // Re-open video panel if it was open before navigation/tab switch
        try {
          const url = vstate.url || (playerContainer.dataset && playerContainer.dataset.videoUrl) || '';
          openInlineMedia(url, (cover && cover.src) ? cover.src : '');
          const apply = () => {
            try { inlineVideo.currentTime = vstate.currentTime || 0; } catch(_) {}
            if (vstate.playing) {
              let tries = 0; const tryPlay = () => { tries++; inlineVideo.play().then(updatePlayPauseUI).catch(()=>{ if (tries < 5) setTimeout(tryPlay, 120); }); };
              tryPlay();
              try { audio.pause(); } catch(_) {}
            }
          };
          if (inlineVideo && inlineVideo.readyState >= 1) apply(); else setTimeout(apply, 120);
        } catch(_) {}
      } else {
        // If only audio was playing before hide, resume audio
        const shouldResume = playerContainer.dataset.wasPlayingOnHide === '1';
        if (shouldResume && audio.paused) {
          // Make sure we don't jump to start if metadata not ready
          const resume = () => { try { audio.play().catch(()=>{}); } catch(_) {} };
          if (isNaN(audio.duration) || !isFinite(audio.duration) || audio.readyState < 1) {
            const once = function(){ audio.removeEventListener('loadedmetadata', once); resume(); };
            audio.addEventListener('loadedmetadata', once);
          } else { resume(); }
        }
      }
      // Clear flag
      delete playerContainer.dataset.wasPlayingOnHide;
      updatePlayPauseUI();
    }
  });
  window.addEventListener('beforeunload', savePlayerState);
  // Ensure state persists on back/forward with bfcache
  window.addEventListener('pagehide', savePlayerState);
  window.addEventListener('pageshow', () => {
    // Re-apply state if needed
    const s = getSavedState();
    if (!s || !s.src) return;
    // Restore queue and settings to avoid losing album order after back/forward
    try {
      loadQueue();
      if (typeof s.queueIndex === 'number') {
        queueIndex = s.queueIndex;
      }
      if (typeof s.shuffle !== 'undefined') {
        shuffleEnabled = !!s.shuffle;
        updateShuffleUI();
      }
      // Restore one-time replay state and reconcile duplicate
      replayOnceEnabled = !!(s.replayOnceEnabled || (localStorage.getItem(REPLAY_ENABLED_KEY) === '1'));
      replayToken = s.replayToken || localStorage.getItem(REPLAY_TOKEN_KEY) || null;
      if (replayOnceEnabled) {
        let dupIdx = -1;
        if (replayToken) dupIdx = trackQueue.findIndex(x => x && x._replayToken === replayToken);
        if (dupIdx === -1) {
          const cur = trackQueue[queueIndex];
          const next = trackQueue[queueIndex + 1];
          if (cur && next && cur.src === next.src) {
            replayToken = replayToken || ('replay_' + Date.now());
            trackQueue[queueIndex + 1] = { ...next, _replayToken: replayToken };
            saveQueue();
          } else {
            // no duplicate present anymore; keep toggle off to avoid re-adding
            replayOnceEnabled = false;
            replayToken = null;
          }
        }
      }
      updateRepeatUI();
      renderQueueUI();
    } catch (e) {}
    // If current audio source differs from saved one, switch to the saved, most recent track
    const currentSrc = audio.currentSrc || audio.src || '';
    if (!currentSrc || currentSrc !== s.src) {
      audio.src = s.src;
      trackTitle.textContent = s.title || '';
      trackArtist.textContent = s.artist || '';
      cover.src = s.cover || cover.src;
      audio.volume = s.volume !== undefined ? s.volume : audio.volume;
    }
    if (s.currentTime != null) {
      const t = Number(s.currentTime) || 0;
      // Wait for metadata to apply time accurately
      const restore = () => { audio.currentTime = t; updatePlayPauseUI(); };
      if (isNaN(audio.duration) || !isFinite(audio.duration)) {
        audio.addEventListener('loadedmetadata', function once(){ audio.removeEventListener('loadedmetadata', once); restore(); });
      } else { restore(); }
    }
    if (s.isPlaying && audio.paused) {
      audio.play().catch(()=>{});
    }
  });

  // Public API for other pages
  window.playTrack = function (arg) {
    console.log('playTrack called with:', arg);
    console.log('Current trackQueue length before playTrack:', trackQueue.length);
    
    // Support legacy positional signature: playTrack(src, title, artist, cover)
    let src, title, artist, coverUrl, queue = null, queueStartIndex = 0, duration = 0, id = undefined;
    if (typeof arg === 'object' && arg !== null) {
      ({ src, title, artist, cover: coverUrl, queue = null, queueStartIndex = 0, duration = 0, id } = arg);
    } else {
      src = arguments[0]; title = arguments[1]; artist = arguments[2]; coverUrl = arguments[3];
    }
    
    console.log('playTrack queue parameter:', queue);
    console.log('playTrack queueStartIndex:', queueStartIndex);
    function normalizeSrc(u){
      if (!u) return '';
      if (u.startsWith('http://') || u.startsWith('https://') || u.startsWith('data:')) return u;
      
      // Нормализуем путь: убираем /muzic2/ если есть, затем добавляем обратно
      let path = u;
      
      // Убираем /muzic2/ если есть
      if (path.startsWith('/muzic2/')) {
        path = path.substring('/muzic2/'.length);
      } else if (path.startsWith('/')) {
        path = path.substring(1);
      }
      
      // Декодируем путь если он уже закодирован, чтобы избежать двойного кодирования
      try {
        if (path.includes('%')) {
          path = decodeURIComponent(path);
        }
      } catch(e) {
        // Если декодирование не удалось, используем исходный путь
      }
      
      // Ищем tracks/ в пути
      const i = path.indexOf('tracks/');
      if (i !== -1) {
        // Используем PHP прокси для аудио файлов
        const tracksPath = path.substring(i);
        return '/muzic2/public/src/api/audio.php?f=' + encodeURIComponent(tracksPath);
      }
      
      // Если tracks/ не найден, предполагаем что это имя файла в tracks/music/
      if (path && !path.includes('/')) {
        return '/muzic2/public/src/api/audio.php?f=' + encodeURIComponent('tracks/music/' + path);
      }
      
      // Иначе просто добавляем /muzic2/
      return '/muzic2/' + path.replace(/^\/+/, '');
    }
    function normalizeCover(u){
      if (!u) return '';
      if (u.startsWith('http') || u.startsWith('data:')) return u;
      
      // Нормализуем путь: убираем /muzic2/ если есть, затем добавляем обратно
      let path = u;
      
      // Убираем /muzic2/ если есть
      if (path.startsWith('/muzic2/')) {
        path = path.substring('/muzic2/'.length);
      } else if (path.startsWith('/')) {
        path = path.substring(1);
      }
      
      // Ищем tracks/ в пути
      const i = path.indexOf('tracks/');
      if (i !== -1) {
        return '/muzic2/' + path.substring(i);
      }
      
      // Если tracks/ не найден, предполагаем что это имя файла в tracks/covers/
      if (path && !path.includes('/')) {
        return '/muzic2/tracks/covers/' + path;
      }
      
      return '/muzic2/' + path.replace(/^\/+/, '');
    }
    function normalizeVideo(u){
      if (!u) return '';
      if (u.startsWith('http://') || u.startsWith('https://') || u.startsWith('data:')) return u;
      
      // Нормализуем путь: убираем /muzic2/ если есть, затем добавляем обратно
      let path = u;
      
      // Убираем /muzic2/ если есть
      if (path.startsWith('/muzic2/')) {
        path = path.substring('/muzic2/'.length);
      } else if (path.startsWith('/')) {
        path = path.substring(1);
      }
      
      // Ищем tracks/ в пути
      const i = path.indexOf('tracks/');
      if (i !== -1) {
        return '/muzic2/' + path.substring(i);
      }
      
      // Если tracks/ не найден, предполагаем что это имя файла в tracks/video/
      if (path && !path.includes('/')) {
        return '/muzic2/tracks/video/' + path;
      }
      
      return '/muzic2/' + path.replace(/^\/+/, '');
    }
    if (queue && Array.isArray(queue) && queue.length > 0) {
      console.log('Using provided queue with', queue.length, 'tracks');
      // Ensure ordered playback when album queue starts
      shuffleEnabled = false;
      updateShuffleUI();
      trackQueue = queue.map(q => {
        const normalizedSrc = normalizeSrc(q.src || q.file_path || q.url || '');
        return {
          src: normalizedSrc,
          title: q.title || '',
          artist: q.artist || '',
          feats: q.feats || '',
          cover: normalizeCover(q.cover || coverUrl || ''),
          duration: q.duration || 0,
          id: q.id || q.track_id || undefined,
          video_url: normalizeVideo(q.video_url || '')
        };
      });
      originalQueue = trackQueue.slice();
      queueIndex = Math.max(0, Math.min(queueStartIndex, trackQueue.length - 1));
      saveQueue();
      playFromQueue(queueIndex);
      toggleQueuePanel(true);
    } else {
      console.log('No queue provided, creating single track queue');
      console.log('This will overwrite existing queue of', trackQueue.length, 'tracks');
      // Single track
      trackQueue = [{ src: normalizeSrc(src), title, artist, feats: (arguments[0] && arguments[0].feats) ? arguments[0].feats : '', cover: normalizeCover(coverUrl || ''), duration, id, video_url: normalizeVideo((arguments[0] && arguments[0].video_url) ? arguments[0].video_url : '') }];
      originalQueue = trackQueue.slice();
      queueIndex = 0;
      saveQueue();
      playFromQueue(0);
    }
  };

  // Backward compatibility for artist.js which may call loadTrack(track)
  window.loadTrack = function (track) {
    function normalizeSrc(u){
      if (!u) return '';
      if (u.startsWith('http://') || u.startsWith('https://') || u.startsWith('data:')) return u;
      
      // Нормализуем путь: убираем /muzic2/ если есть, затем добавляем обратно
      let path = u;
      
      // Убираем /muzic2/ если есть
      if (path.startsWith('/muzic2/')) {
        path = path.substring('/muzic2/'.length);
      } else if (path.startsWith('/')) {
        path = path.substring(1);
      }
      
      // Декодируем путь если он уже закодирован, чтобы избежать двойного кодирования
      try {
        if (path.includes('%')) {
          path = decodeURIComponent(path);
        }
      } catch(e) {
        // Если декодирование не удалось, используем исходный путь
      }
      
      // Ищем tracks/ в пути
      const i = path.indexOf('tracks/');
      if (i !== -1) {
        // Используем PHP прокси для аудио файлов
        const tracksPath = path.substring(i);
        return '/muzic2/public/src/api/audio.php?f=' + encodeURIComponent(tracksPath);
      }
      
      // Если tracks/ не найден, предполагаем что это имя файла в tracks/music/
      if (path && !path.includes('/')) {
        return '/muzic2/public/src/api/audio.php?f=' + encodeURIComponent('tracks/music/' + path);
      }
      
      // Иначе просто добавляем /muzic2/
      return '/muzic2/' + path.replace(/^\/+/, '');
    }
    function normalizeCover(u){
      if (!u) return '';
      if (u.startsWith('http') || u.startsWith('data:')) return u;
      
      // Нормализуем путь: убираем /muzic2/ если есть, затем добавляем обратно
      let path = u;
      
      // Убираем /muzic2/ если есть
      if (path.startsWith('/muzic2/')) {
        path = path.substring('/muzic2/'.length);
      } else if (path.startsWith('/')) {
        path = path.substring(1);
      }
      
      // Ищем tracks/ в пути
      const i = path.indexOf('tracks/');
      if (i !== -1) {
        return '/muzic2/' + path.substring(i);
      }
      
      // Если tracks/ не найден, предполагаем что это имя файла в tracks/covers/
      if (path && !path.includes('/')) {
        return '/muzic2/tracks/covers/' + path;
      }
      
      return '/muzic2/' + path.replace(/^\/+/, '');
    }
    const t = {
      src: normalizeSrc(track.file_path || ''),
      title: track.title || '',
      artist: track.artist || '',
      cover: normalizeCover(track.cover || ''),
      duration: track.duration || 0,
      id: track.id != null ? track.id : track.track_id
    };
    window.playTrack({ ...t });
  };

  // Utility
  function escapeHtml(str) {
    return String(str || '').replace(/[&<>"]/g, function (m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m];
    });
  }

  // Global function to check if a track is currently playing
  window.isTrackPlaying = function(trackId) {
    if (!trackId) return false;
    try {
      const playerRoot = document.getElementById('player-root');
      if (!playerRoot) return false;
      const audio = playerRoot.querySelector('#audio');
      if (!audio) return false;
      // Check if this track is the current track and is playing
      return currentTrackId === trackId && !audio.paused;
    } catch(_) {
      return false;
    }
  };

  // Global function to pause current track
  window.pauseCurrentTrack = function() {
    try {
      const playerRoot = document.getElementById('player-root');
      if (!playerRoot) return;
      const audio = playerRoot.querySelector('#audio');
      if (audio && !audio.paused) {
        audio.pause();
      }
      // Also pause popup if active
      if (popupActive && window.postToPopup) {
        postToPopup({ cmd: 'pause' });
      }
    } catch(_) {}
  };
  
  // Global function to get current track ID
  window.getCurrentTrackId = function() {
    return currentTrackId;
  };
  
  // Global function to check if player is paused
  window.isPlayerPaused = function() {
    try {
      const playerRoot = document.getElementById('player-root');
      if (!playerRoot) return true;
      const audio = playerRoot.querySelector('#audio');
      return audio ? audio.paused : true;
    } catch(_) {
      return true;
    }
  };


  window.setQueue = function(queue, startIndex = 0) {
    console.log('setQueue called with queue length:', queue ? queue.length : 'null', 'startIndex:', startIndex);
    console.log('Current trackQueue length before setQueue:', trackQueue.length);
    
    if (!Array.isArray(queue) || queue.length === 0) {
      console.log('Invalid queue provided to setQueue');
      return;
    }
    
    console.log('Setting queue with', queue.length, 'tracks, starting from index', startIndex);
    console.log('First few tracks:', queue.slice(0, 3).map(t => ({ title: t.title, artist: t.artist, src: t.src })));
    
    // Update internal queue variables
    trackQueue = queue;
    queueIndex = startIndex;
    
    // Save queue to localStorage
    localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
    localStorage.setItem(QUEUE_INDEX_KEY, String(queueIndex));
    
    // Update queue UI
    renderQueueUI();
    
    console.log('Queue set successfully, trackQueue length:', trackQueue.length, 'queueIndex:', queueIndex);
    console.log('Current track in queue:', trackQueue[queueIndex] ? trackQueue[queueIndex].title : 'none');
    console.log('Current track src:', trackQueue[queueIndex] ? trackQueue[queueIndex].src : 'none');
  };

  // Initialize from storage
  loadQueue();
  loadPlayerState();
  updateShuffleUI();
  updateRepeatUI();
  updateMuteUI();
  updateFullscreenUI();
  // Load likes after render
  loadLikes().then(updatePlayerLikeUI).catch(()=>{});
  renderQueueUI();
  // Ensure UI reflects current state after initial render
  setTimeout(updateShuffleUI, 0);
  // Sync UI with popup if already open
  if (popupActive && popupWin) {
    postToPopup({ cmd: 'play' }, { retries: 3, delay: 150 });
  }

  const isWindows = /Win/i.test(navigator.userAgent || '') || /Win/i.test(navigator.platform || '');
  
  // F7 and F9 keys handler
  document.addEventListener('keydown', (e) => {
    // Try multiple approaches for F-keys
    const isF7 = e.keyCode === 118 || e.code === 'F7' || e.key === 'F7';
    const isF9 = e.keyCode === 120 || e.code === 'F9' || e.key === 'F9';
    
    if (isF7 || isF9) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      
      if (isF7) {
        console.log('F7 pressed - Previous track');
        playPrev();
      } else if (isF9) {
        console.log('F9 pressed - Next track');
        playNext(false);
      }
      return false;
    }
  }, true); // Use capture phase
  
  // Windows-specific keys
  if (isWindows) {
    document.addEventListener('keydown', (e) => {
      // Windows media keys and additional shortcuts
      if (e.code === 'MediaPlayPause' || e.keyCode === 179) {
        e.preventDefault();
        if (isPlaying) {
          audio.pause();
        } else {
          audio.play();
        }
      } else if (e.code === 'MediaTrackNext' || e.keyCode === 176) {
        e.preventDefault();
        playNext(false);
      } else if (e.code === 'MediaTrackPrevious' || e.keyCode === 177) {
        e.preventDefault();
        playPrev();
      } else if (e.code === 'MediaStop' || e.keyCode === 178) {
        e.preventDefault();
        audio.pause();
      }
      // Windows-specific F-key combinations
      else if (e.keyCode === 118 && e.ctrlKey) { // Ctrl+F7
        e.preventDefault();
        playPrev();
      } else if (e.keyCode === 120 && e.ctrlKey) { // Ctrl+F9
        e.preventDefault();
        playNext(false);
      }
    });
  }
  
  // Debug: Log all key events to see what's happening
  document.addEventListener('keydown', (e) => {
    if (e.keyCode >= 112 && e.keyCode <= 123) { // F1-F12 range
      console.log('F-key detected:', {
        keyCode: e.keyCode,
        key: e.key,
        code: e.code,
        altKey: e.altKey,
        metaKey: e.metaKey,
        ctrlKey: e.ctrlKey,
        shiftKey: e.shiftKey
      });
    }
  });
  
  if (isWindows) {
    console.log('Клавиши: F7/F9 — треки; Ctrl+F7/F9; медиаклавиши');
  }

  // Universal media keys support
  document.addEventListener('keydown', (e) => {
    if (e.code === 'MediaPlayPause') {
      e.preventDefault();
      if (isPlaying) {
        audio.pause();
      } else {
        audio.play();
      }
    } else if (e.code === 'MediaTrackNext') {
      e.preventDefault();
      playNext(false);
    } else if (e.code === 'MediaTrackPrevious') {
      e.preventDefault();
      playPrev();
    } else if (e.code === 'MediaStop') {
      e.preventDefault();
      audio.pause();
    }
  });

  function loadRecents() {
    try { return JSON.parse(localStorage.getItem(RECENTS_KEY) || '[]'); } catch(_) { return []; }
  }
  function saveRecents(list) {
    try { localStorage.setItem(RECENTS_KEY, JSON.stringify(list.slice(0, 24))); } catch(_) {}
  }
  function upsertRecent(entry) {
    try {
      const list = loadRecents();
      const idx = list.findIndex(x => x && x.src === entry.src);
      if (idx >= 0) list.splice(idx, 1);
      list.unshift(entry);
      saveRecents(list);
    } catch(_) {}
  }

  function saveRecentProgress() {
    try {
      const src = audio.currentSrc || audio.src || '';
      if (!src) return;
      const duration = isFinite(audio.duration) ? audio.duration : 0;
      const currentTime = isFinite(audio.currentTime) ? audio.currentTime : 0;
      if (!duration || currentTime < 3) return; // avoid noise
      const percent = duration ? (currentTime / duration) : 0;
      const coverUrl = (cover && cover.src) ? cover.src : '';
      const title = (trackTitle && trackTitle.textContent) ? trackTitle.textContent : '';
      const artist = (trackArtist && trackArtist.textContent) ? trackArtist.textContent : '';
      const entry = { src, title, artist, cover: coverUrl, currentTime, duration, updatedAt: Date.now() };
      // If near the end, mark as completed (progress 100%) and reset currentTime
      if (duration - currentTime < 2) { entry.currentTime = 0; }
      upsertRecent(entry);
    } catch(_) {}
  }

  updateVideoButtonUI();
  
  } // Конец функции initPlayer
  
  // Инициализируем плеер при загрузке DOM
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPlayer);
  } else {
    initPlayer();
  }
})();
