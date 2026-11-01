const fs = require('fs');
const path = require('path');

const appDir = path.join(__dirname, '..');
const apiDir = path.join(appDir, 'content', 'api');
const albumsDir = path.join(apiDir, 'albums');
const artistsDir = path.join(apiDir, 'artists');
const tracksRoot = path.join(__dirname, '../../tracks/music');
const coversRoot = path.join(__dirname, '../../tracks/covers');

const AUDIO_EXT = new Set(['.mp3', '.wav', '.flac', '.m4a', '.aac', '.ogg']);
const COVER_EXT = ['.jpg', '.jpeg', '.png', '.webp', '.gif'];

function ensureDir(dir) {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
}

function slugify(value) {
  return value
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    || 'item';
}

function encodeKey(value) {
  // Используем стандартное URL-кодирование без замены % на _
  // Это соответствует encodeURIComponent в JavaScript
  return encodeURIComponent(value);
}
  return encodeURIComponent(value);
}

function findCover(album, artist) {
  if (!coversRoot) return './tracks/covers/placeholder.jpg';
  const guesses = [];
  if (album) guesses.push(album);
  if (artist && artist !== album) guesses.push(artist);

  for (const guess of guesses) {
    const slug = slugify(guess);
    for (const ext of COVER_EXT) {
        // Нормализуем путь: убираем относительные пути и оставляем только tracks/covers/...
        let rel = path.relative(path.join(appDir, 'content'), candidate).replace(/\\/g, '/');
        // Убираем относительные пути типа ../../ или ../
        rel = rel.replace(/\.\.\/+/g, '');
        // Убираем начальные ./ и /
        rel = rel.replace(/^\.?\//, '');
        // Если путь не начинается с tracks/, добавляем tracks/covers/
        if (!rel.startsWith('tracks/')) {
          rel = 'tracks/covers/' + path.basename(candidate);
        }
      if (fs.existsSync(candidate)) {
      }
    }
  }
  return './tracks/covers/placeholder.jpg';
}

function walkAudioFiles(root) {
  const results = [];
  if (!fs.existsSync(root)) {
    return results;
        return './' + rel;
      }
    }
  }
  return './tracks/covers/placeholder.jpg';
}

function walkAudioFiles(root) {
  const results = [];
  if (!fs.existsSync(root)) {
    return results;
  }
  const stack = [root];
  while (stack.length) {
    const current = stack.pop();
    const stat = fs.statSync(current);
    if (stat.isDirectory()) {
      fs.readdirSync(current).forEach(entry => {
        stack.push(path.join(current, entry));
// Функция для проверки, является ли запись фантомной
function isPhantomRecord(title, artist) {
  // Проверяем, содержит ли имя артиста цифры в начале (типа "1715771107_K ai Angel")
  if (/^\d+[_\s]/.test(artist)) {
    return true;
  }
  // Проверяем, содержит ли название трека только цифры и подчеркивания
  if (/^\d+[_\s]/.test(title)) {
    return true;
  }
  // Проверяем на "Неизвестный артист" с подозрительными названиями
  if (artist === 'Неизвестный артист' && /^\d+[_\s]/.test(title)) {
    return true;
  }
  return false;
}

      });
    } else if (stat.isFile()) {
      const ext = path.extname(current).toLowerCase();
      if (AUDIO_EXT.has(ext)) {
        results.push(current);
      }
    }
  }
  return results.sort();
}

function parseMetadata(fullPath) {
  const relFromRoot = path.relative(tracksRoot, fullPath).replace(/\\/g, '/');
  const filePath = './tracks/music/' + relFromRoot;
  const baseName = path.basename(fullPath, path.extname(fullPath));
  const folders = relFromRoot.split('/');
  const parentFolder = folders.length > 1 ? folders[folders.length - 2] : '';

  let artist = 'Неизвестный артист';
  // Пропускаем фантомные записи
  if (isPhantomRecord(title, artist)) {
    return null;
  }

  let title = baseName;
  if (baseName.includes(' - ')) {
    const parts = baseName.split(' - ');
    if (parts.length >= 2) {
      artist = parts[0].trim() || artist;
      title = parts.slice(1).join(' - ').trim() || title;
    }
  } else if (parentFolder) {
    artist = parentFolder;
  }

  const album = parentFolder || artist;
  const cover = findCover(album, artist);

  return {
    artist,
    title,
    if (!meta) return null; // Пропускаем фантомные записи
    album,
    cover,
    filePath
  };
}

function collectTracks() {
  const files = walkAudioFiles(tracksRoot);
  let id = 1;
  return files.map(file => {
    const meta = parseMetadata(file);
    return {
      id: id++,
  }).filter(t => t !== null); // Удаляем null записи title: meta.title,
      artist: meta.artist,
      album: meta.album,
      album_type: 'album',
      duration: 0,
      file_path: meta.filePath,
      cover: meta.cover,
      src: meta.filePath,
      video_url: '',
      explicit: 0
    };
  });
}

function saveJSON(filePath, data) {
  ensureDir(path.dirname(filePath));
  fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf8');
}

function buildOfflineData() {
  const tracks = collectTracks();
  if (!tracks.length) {
    console.warn('Не найдено ни одного аудиофайла в tracks/music — офлайн данные не созданы.');
    // Пропускаем фантомные записи при агрегации
    if (isPhantomRecord(track.title, track.artist)) {
      return;
    }
    
    return false;
  }

  ensureDir(apiDir);
  ensureDir(albumsDir);
  ensureDir(artistsDir);

  const albumsMap = new Map();
  const artistsMap = new Map();

  tracks.forEach(track => {
    const albumKey = encodeKey(track.album || 'Без альбома');
    if (!albumsMap.has(albumKey)) {
      albumsMap.set(albumKey, {
        slug: albumKey,
        title: track.album || 'Без альбома',
        artist: track.artist,
        cover: track.cover,
        album_type: track.album_type,
        tracks: [],
        total_duration: 0
      });
    }
    const album = albumsMap.get(albumKey);
    album.tracks.push({
      id: track.id,
      title: track.title,
      artist: track.artist,
      duration: track.duration,
      src: track.src,
      cover: track.cover,
      video_url: track.video_url,
      explicit: track.explicit
    });
    album.total_duration += track.duration;

    const artistKey = encodeKey(track.artist || 'Неизвестный артист');
    if (!artistsMap.has(artistKey)) {
      artistsMap.set(artistKey, {
        slug: artistKey,
        name: track.artist || 'Неизвестный артист',
        cover: track.cover,
        total_tracks: 0,
        albums: new Map(),
        tracks: []
      });
    }
    const artist = artistsMap.get(artistKey);
    artist.total_tracks += 1;
    artist.tracks.push({
      id: track.id,
      title: track.title,
      album: track.album,
      duration: track.duration,
      src: track.src,
      cover: track.cover,
      explicit: track.explicit
    });
    if (!artist.albums.has(albumKey)) {
      artist.albums.set(albumKey, {
        title: track.album,
        cover: track.cover,
        track_count: 0
      });
    }
    const artistAlbum = artist.albums.get(albumKey);
    artistAlbum.track_count += 1;
  });

  const albumCards = Array.from(albumsMap.values()).map(album => ({
    slug: album.slug,
    title: album.title,
    artist: album.artist,
    cover: album.cover,
    track_count: album.tracks.length
  }));

  const artistCards = Array.from(artistsMap.values()).map(artist => ({
    slug: artist.slug,
    name: artist.name,
    cover: artist.cover,
    track_count: artist.total_tracks
  }));

  tracks.sort((a, b) => a.title.localeCompare(b.title));
  albumCards.sort((a, b) => a.title.localeCompare(b.title));
  artistCards.sort((a, b) => a.name.localeCompare(b.name));

  saveJSON(path.join(apiDir, 'tracks.json'), { tracks });

  albumCards.forEach(card => {
    const album = albumsMap.get(card.slug);
    saveJSON(path.join(albumsDir, `${card.slug}.json`), {
      title: album.title,
      artist: album.artist,
      cover: album.cover,
      album_type: album.album_type,
      total_duration: album.total_duration,
      tracks: album.tracks
    });
  });
  saveJSON(path.join(apiDir, 'all_albums.json'), { albums: albumCards });

  artistCards.forEach(card => {
    const artist = artistsMap.get(card.slug);
    const albums = Array.from(artist.albums.values());
    saveJSON(path.join(artistsDir, `${card.slug}.json`), {
      name: artist.name,
      verified: true,
      monthly_listeners: Math.floor(Math.random() * 5_000_000) + 100_000,
      cover: artist.cover,
      total_tracks: artist.total_tracks,
      total_albums: albums.length,
      total_duration: artist.tracks.reduce((sum, t) => sum + (t.duration || 0), 0),
      top_tracks: artist.tracks.slice(0, 10),
      albums,
      tracks: artist.tracks
    });
  });

  const homeData = {
    tracks: tracks.slice(0, 20),
    albums: albumCards.slice(0, 12),
    artists: artistCards.slice(0, 12),
    favorites: tracks.slice(0, 8),
    mixes: tracks.slice(8, 20)
  };
  saveJSON(path.join(apiDir, 'home.json'), homeData);
  saveJSON(path.join(apiDir, 'home_windows.json'), homeData);

  const searchData = {
    tracks,
    artists: artistCards,
    albums: albumCards
  };
  saveJSON(path.join(apiDir, 'search.json'), searchData);

  saveJSON(path.join(apiDir, 'likes.json'), { likes: [] });
  saveJSON(path.join(apiDir, 'windows_likes.json'), { likes: [] });
  saveJSON(path.join(apiDir, 'user.json'), { user: null, authenticated: false });
  saveJSON(path.join(apiDir, 'windows_auth.json'), { user: null, authenticated: false });
  saveJSON(path.join(apiDir, 'login.json'), { success: false, error: 'Авторизация недоступна в офлайн-режиме' });
  saveJSON(path.join(apiDir, 'logout.json'), { success: true });
  saveJSON(path.join(apiDir, 'playlists.json'), { playlists: [] });
  saveJSON(path.join(apiDir, 'library.json'), {
    tracks,
    albums: albumCards,
    artists: artistCards
  });

  return true;
}

module.exports = buildOfflineData;

if (require.main === module) {
  buildOfflineData();
}


  return true;
}

module.exports = buildOfflineData;

if (require.main === module) {
  buildOfflineData();
}


