<?php
require_once __DIR__ . '/../../src/config/db.php';

// API
if (isset($_GET['api'])) {
  header('Content-Type: application/json; charset=utf-8');
  // Simple server-side logging to diagnose admin API issues
  function __admin_log($m){
    try{ $f=__DIR__.'/admin_api.log'; $p='['.date('Y-m-d H:i:s').'] '.($_SERVER['REQUEST_URI']??'')." "; if(is_array($m)||is_object($m)){$m=json_encode($m,JSON_UNESCAPED_UNICODE);} @file_put_contents($f,$p.$m."\n",FILE_APPEND);}catch(Throwable $e){}
  }
  // Handle preflight/OPTIONS gracefully
  if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); echo json_encode(['success'=>true]); exit; }
  $db = get_db_connection();
  // NOTE: No DDL/migrations here (hot path). Run /muzic2/scripts/setup_db.php if schema is missing.
  $entity = $_GET['entity'] ?? '';
  $method = $_SERVER['REQUEST_METHOD'];
  $body = null;
  if ($method !== 'GET') {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    $body = is_array($j) ? $j : $_POST;
    __admin_log(['entity'=>$entity,'method'=>$method,'raw'=>$raw]);
  }
  try {
    if ($entity === 'artists') {
      if ($method === 'GET') {
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q !== '') {
          // Include artists without tracks
          $st = $db->prepare("SELECT a.name AS artist, a.cover AS artist_cover, a.bio AS bio, a.promo_video AS promo_video, a.promo_track_id AS promo_track_id, COUNT(t.id) AS tracks, MIN(t.cover) AS track_cover FROM artists a LEFT JOIN tracks t ON TRIM(LOWER(a.name))=TRIM(LOWER(t.artist)) WHERE a.name LIKE ? GROUP BY a.name, a.cover, a.bio, a.promo_video, a.promo_track_id ORDER BY a.name ASC LIMIT 500");
          $st->execute(['%'.$q.'%']);
          $rows = $st->fetchAll();
        } else {
          $rows = $db->query("SELECT a.name AS artist, a.cover AS artist_cover, a.bio AS bio, a.promo_video AS promo_video, a.promo_track_id AS promo_track_id, COUNT(t.id) AS tracks, MIN(t.cover) AS track_cover FROM artists a LEFT JOIN tracks t ON TRIM(LOWER(a.name))=TRIM(LOWER(t.artist)) GROUP BY a.name, a.cover, a.bio, a.promo_video, a.promo_track_id ORDER BY a.name ASC LIMIT 200")->fetchAll();
        }
        echo json_encode(['success'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE); exit;
      }
      $action = $body['action'] ?? '';
      if ($action === 'create') {
        $name = trim((string)($body['name'] ?? ''));
        $cover = trim((string)($body['cover'] ?? ''));
        $bio = trim((string)($body['bio'] ?? ''));
        $promoVideo = trim((string)($body['promo_video'] ?? ''));
        $promoTrackId = isset($body['promo_track_id']) && $body['promo_track_id'] !== '' && $body['promo_track_id'] !== null ? (int)$body['promo_track_id'] : null;
        if ($name==='') throw new Exception('Введите имя');
        $st=$db->prepare('INSERT INTO artists (name, cover, bio, promo_video, promo_track_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE cover=VALUES(cover), bio=VALUES(bio), promo_video=VALUES(promo_video), promo_track_id=VALUES(promo_track_id)');
        $st->execute([$name,$cover,$bio,$promoVideo,$promoTrackId]);
        echo json_encode(['success'=>true]); exit;
      }
      if ($action === 'update') {
        $name = trim((string)($body['name'] ?? ''));
        $name_new = trim((string)($body['name_new'] ?? $name));
        $cover = trim((string)($body['cover'] ?? ''));
        $bio = trim((string)($body['bio'] ?? ''));
        $promoVideo = trim((string)($body['promo_video'] ?? ''));
        $promoTrackId = isset($body['promo_track_id']) && $body['promo_track_id'] !== '' ? (int)$body['promo_track_id'] : null;
        if ($name==='') throw new Exception('Введите имя');
        $db->beginTransaction();
        if (strcasecmp($name, $name_new)!==0) {
          $st=$db->prepare('UPDATE tracks SET artist=? WHERE TRIM(LOWER(artist))=TRIM(LOWER(?))');
          $st->execute([$name_new,$name]);
        }
        $st=$db->prepare('INSERT INTO artists (name, cover, bio, promo_video, promo_track_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE cover=VALUES(cover), bio=VALUES(bio), promo_video=VALUES(promo_video), promo_track_id=VALUES(promo_track_id)');
        $st->execute([$name_new,$cover,$bio,$promoVideo,$promoTrackId]);
        $db->commit();
        echo json_encode(['success'=>true]); exit;
      }
      if ($action === 'delete') {
        $name = trim((string)($body['name'] ?? ''));
        if ($name==='') throw new Exception('Введите имя');
        $st=$db->prepare('DELETE FROM artists WHERE TRIM(LOWER(name))=TRIM(LOWER(?))');
        $st->execute([$name]);
        echo json_encode(['success'=>true]); exit;
      }
      throw new Exception('Неизвестное действие');
    }

    if ($entity === 'albums') {
      if ($method === 'GET') {
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q!=='') {
          $st = $db->prepare('SELECT album, MIN(artist) AS artist, MIN(album_type) AS album_type, MIN(cover) AS cover, COUNT(*) AS track_count FROM tracks WHERE album LIKE ? GROUP BY album ORDER BY album ASC LIMIT 500');
          $st->execute(['%'.$q.'%']);
          $rows = $st->fetchAll();
        } else {
          $rows = $db->query('SELECT album, MIN(artist) AS artist, MIN(album_type) AS album_type, MIN(cover) AS cover, COUNT(*) AS track_count FROM tracks GROUP BY album ORDER BY album ASC LIMIT 200')->fetchAll();
        }
        echo json_encode(['success'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE); exit;
      }
      $action = $body['action'] ?? '';
      if ($action === 'create') {
        $album = trim((string)($body['album'] ?? ''));
        $artist = trim((string)($body['artist'] ?? ''));
        $cover  = trim((string)($body['cover'] ?? ''));
        $type   = in_array(($body['album_type'] ?? 'album'), ['album','ep','single']) ? $body['album_type'] : 'album';
        if ($album==='') throw new Exception('Введите название альбома');
        if ($artist==='') throw new Exception('Введите имя артиста');
        $st=$db->prepare('INSERT INTO tracks (title,artist,album,album_type,duration,file_path,cover) VALUES (?,?,?,?,?,?,?)');
        $st->execute(['Intro',$artist,$album,$type,0,'tracks/music/placeholder.mp3',$cover]);
        echo json_encode(['success'=>true]); exit;
      }
      if ($action === 'update') {
        $album = trim((string)($body['album'] ?? ''));
        if ($album==='') throw new Exception('Введите текущее название альбома');
        $album_new = trim((string)($body['album_new'] ?? ''));
        $artist = isset($body['artist']) ? trim((string)$body['artist']) : null;
        $cover = isset($body['cover']) ? trim((string)$body['cover']) : null;
        $type = isset($body['album_type']) && in_array($body['album_type'],['album','ep','single'])? $body['album_type'] : null;
        $db->beginTransaction();
        if ($artist!==null) { $st=$db->prepare('UPDATE tracks SET artist=? WHERE TRIM(LOWER(album))=TRIM(LOWER(?))'); $st->execute([$artist,$album]); }
        if ($cover!==null) { $st=$db->prepare('UPDATE tracks SET cover=? WHERE TRIM(LOWER(album))=TRIM(LOWER(?))'); $st->execute([$cover,$album]); }
        if ($type!==null) { $st=$db->prepare('UPDATE tracks SET album_type=? WHERE TRIM(LOWER(album))=TRIM(LOWER(?))'); $st->execute([$type,$album]); }
        if ($album_new!=='') { $st=$db->prepare('UPDATE tracks SET album=? WHERE TRIM(LOWER(album))=TRIM(LOWER(?))'); $st->execute([$album_new,$album]); }
        $db->commit();
        echo json_encode(['success'=>true]); exit;
      }
      throw new Exception('Неизвестное действие');
    }

    if ($entity === 'tracks') {
      // No schema changes here (hot path).
      if ($method === 'GET') {
        $q = trim((string)($_GET['q'] ?? ''));
        $albumExact = isset($_GET['album']) ? trim((string)$_GET['album']) : '';
        if ($albumExact !== '') {
          $st=$db->prepare('SELECT * FROM tracks WHERE TRIM(LOWER(album))=TRIM(LOWER(?)) ORDER BY id ASC LIMIT 1000');
          $st->execute([$albumExact]);
          $rows = $st->fetchAll();
        } elseif ($q!=='') {
          $st=$db->prepare('SELECT * FROM tracks WHERE title LIKE ? OR artist LIKE ? OR album LIKE ? ORDER BY id DESC LIMIT 500');
          $st->execute(['%'.$q.'%','%'.$q.'%','%'.$q.'%']);
          $rows=$st->fetchAll();
        } else {
          $rows=$db->query('SELECT * FROM tracks ORDER BY id DESC LIMIT 200')->fetchAll();
        }
        // Attach feats (featured artists) as comma-separated string in "feats"
        try {
          if ($rows && count($rows)>0) {
            $ids = array_map(function($r){ return (int)$r['id']; }, $rows);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $qs = $db->prepare("SELECT track_id, GROUP_CONCAT(artist ORDER BY artist SEPARATOR ', ') AS feats FROM track_artists WHERE role='featured' AND track_id IN ($in) GROUP BY track_id");
            $qs->execute($ids);
            $m = [];
            foreach ($qs as $rr) { $m[(int)$rr['track_id']] = (string)$rr['feats']; }
            foreach ($rows as &$r) { $r['feats'] = $m[(int)$r['id']] ?? ''; }
          }
        } catch (Throwable $e) {}
        echo json_encode(['success'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE); exit;
      }
      $action = $body['action'] ?? '';
      if ($action === 'create') {
        $title=trim((string)($body['title']??'')); $artist=trim((string)($body['artist']??'')); $album=trim((string)($body['album']??'')); $file=trim((string)($body['file_path']??''));
        $cover=trim((string)($body['cover']??'')); $type=in_array(($body['album_type']??'album'),['album','ep','single'])?$body['album_type']:'album'; $dur=intval($body['duration']??0); $video_url=trim((string)($body['video_url']??'')); $explicit=!empty($body['explicit'])?1:0;
        if ($title===''||$artist===''||$album===''||$file==='') throw new Exception('Заполните обязательные поля');
        
        // Нормализуем путь к файлу
        function normalizeFilePath($path) {
            if (empty($path)) return '';
            $path = str_replace('\\', '/', $path);
            $path = preg_replace('#^/+muzic2/+#', '', $path);
            $path = ltrim($path, '/');
            if (strpos($path, 'tracks/') === 0) return $path;
            $root = realpath(__DIR__ . '/../../');
            if ($root && (strpos($path, '/') === 0 || strpos($path, $root) === 0)) {
                $fullPath = realpath($path);
                if ($fullPath && strpos($fullPath, $root) === 0) {
                    $path = substr($fullPath, strlen($root) + 1);
                    if (strpos($path, 'tracks/') === 0) return $path;
                }
            }
            $idx = strpos($path, 'tracks/');
            if ($idx !== false) return substr($path, $idx);
            return 'tracks/music/' . basename($path);
        }
        $file = normalizeFilePath($file);
        
        // Проверяем и копируем файл в tracks/music/ если нужно
        $root = realpath(__DIR__ . '/../../');
        $targetPath = $root . '/' . $file;
        $targetDir = dirname($targetPath);
        
        // Если файл не находится в tracks/music/, копируем его туда
        if (strpos($file, 'tracks/music/') === 0) {
            // Путь уже правильный, проверяем существует ли файл
            if (!file_exists($targetPath)) {
                // Пробуем найти исходный файл по оригинальному пути
                $originalPath = trim((string)($body['file_path'] ?? ''));
                if ($originalPath && file_exists($originalPath)) {
                    // Создаем директорию если нужно
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    // Копируем файл
                    if (!copy($originalPath, $targetPath)) {
                        throw new Exception('Не удалось скопировать файл');
                    }
                }
            }
        }
        
        $st=$db->prepare('INSERT INTO tracks (title,artist,album,album_type,duration,file_path,cover,video_url,explicit) VALUES (?,?,?,?,?,?,?,?,?)');
        $st->execute([$title,$artist,$album,$type,$dur,$file,$cover,$video_url,$explicit]);
        $newId = (int)$db->lastInsertId();
        // Save featured artists if provided
        $featsRaw = $body['feats'] ?? '';
        $featsArr = [];
        if (is_array($featsRaw)) { $featsArr = $featsRaw; }
        else { $featsArr = array_filter(array_map('trim', explode(',', (string)$featsRaw)), function($x){ return $x!==''; }); }
        if (!empty($featsArr)) {
          $ins = $db->prepare('INSERT IGNORE INTO track_artists (track_id, artist, role) VALUES (?,?,"featured")');
          foreach ($featsArr as $fa) { $ins->execute([$newId, $fa]); }
        }
        // Propagate album-level feats if not passed
        if (empty($featsArr)) {
          try {
            $qs = $db->prepare('SELECT artist FROM album_artists WHERE TRIM(LOWER(album))=TRIM(LOWER(?)) AND role="featured"');
            $qs->execute([$album]);
            $als = $qs->fetchAll();
            if ($als) {
              $ins = $db->prepare('INSERT IGNORE INTO track_artists (track_id, artist, role) VALUES (?,?,"featured")');
              foreach ($als as $row) { $ins->execute([$newId, (string)$row['artist']]); }
            }
          } catch (Throwable $e) {}
        }
        echo json_encode(['success'=>true,'id'=>$newId]); exit;
      }
      if ($action === 'update') {
        $id=intval($body['id']??0); if ($id<=0) throw new Exception('Неверный ID');
        $fields=['title','artist','album','album_type','duration','file_path','cover','video_url','explicit']; $set=[]; $params=[':id'=>$id];
        foreach($fields as $f){ if(array_key_exists($f,$body)){ $set[]="$f=:$f"; if($f==='duration'){ $params[":$f"]=intval($body[$f]); } elseif($f==='explicit'){ $params[":$f"]= !empty($body[$f])?1:0; } else { $params[":$f"]=trim((string)$body[$f]); } }}
        if (!$set) throw new Exception('Нечего сохранять');
        $st=$db->prepare('UPDATE tracks SET '.implode(',',$set).' WHERE id=:id'); $st->execute($params);
        // Update feats if provided (explicit presence controls update)
        if (array_key_exists('feats', $body)) {
          $db->prepare('DELETE FROM track_artists WHERE track_id=? AND role="featured"')->execute([$id]);
          $featsRaw = $body['feats'];
          $featsArr = [];
          if (is_array($featsRaw)) { $featsArr = $featsRaw; }
          else { $featsArr = array_filter(array_map('trim', explode(',', (string)$featsRaw)), function($x){ return $x!==''; }); }
          if (!empty($featsArr)) {
            $ins = $db->prepare('INSERT IGNORE INTO track_artists (track_id, artist, role) VALUES (?,?,"featured")');
            foreach ($featsArr as $fa) { $ins->execute([$id, $fa]); }
          }
        }
        echo json_encode(['success'=>true]); exit;
      }
      if ($action === 'delete') {
        $id=intval($body['id']??0); if ($id<=0) throw new Exception('Неверный ID');
        // Cascade: remove feats mapping too
        try { $db->prepare('DELETE FROM track_artists WHERE track_id=?')->execute([$id]); } catch (Throwable $e) {}
        $st=$db->prepare('DELETE FROM tracks WHERE id=?'); $st->execute([$id]); echo json_encode(['success'=>true]); exit;
      }
      throw new Exception('Неизвестное действие');
    }

    throw new Exception('Неизвестная сущность');
  } catch (Throwable $e) {
    __admin_log(['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    http_response_code(400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Админка — Очень простая</title>
<style>
  :root{--bg:#0f0f10;--panel:#16171a;--panel2:#1d1f23;--text:#e6e6e6;--muted:#a0a6ad;--accent:#1ed760;--border:#2a2d32;--ok:#8aff8a;--err:#ff9b9b}
  *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}
  header{padding:16px;background:var(--panel);border-bottom:1px solid var(--border)} header h1{margin:0 0 6px} header p{margin:0;color:var(--muted)}
  .bar{display:flex;gap:10px;padding:12px;background:var(--panel);border-bottom:1px solid var(--border);flex-wrap:wrap}
  .tab{padding:12px 16px;border-radius:12px;border:2px solid var(--border);background:var(--panel2);cursor:pointer;font-weight:700}
  .tab.active{border-color:var(--accent)}
  .controls{display:flex;gap:10px;padding:12px;flex-wrap:wrap}
  .controls input{padding:12px;border-radius:10px;border:1px solid var(--border);background:#0f1113;color:#fff;min-width:240px}
  .btn{padding:12px 16px;border-radius:12px;border:0;background:#2a2f34;color:#fff;cursor:pointer;font-weight:700}
  .btn.primary{background:var(--accent);color:#000}
  .list{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;padding:12px}
  .card{background:var(--panel2);border:1px solid var(--border);border-radius:14px;padding:12px}
  .card h3{margin:8px 0 4px}
  .small{color:var(--muted);font-size:13px}
  .img{width:100%;aspect-ratio:1;border-radius:12px;border:1px solid var(--border);object-fit:cover;background:#0e0f11}
  .row{display:flex;gap:10px;margin-top:10px}
  .status{padding:10px 12px}
  .ok{color:var(--ok)} .err{color:var(--err)}
  .modal{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;padding:16px;z-index:1000}
  .box{background:#121317;border:1px solid var(--border);border-radius:14px;max-width:560px;width:100%;padding:16px;max-height:80vh;overflow:auto}
  .field{margin:10px 0} .field label{display:block;margin-bottom:6px} .field input,.field textarea,.field select{width:100%;padding:12px;border-radius:10px;border:1px solid var(--border);background:#0f1113;color:#fff}
  .actions{display:flex;gap:10px;justify-content:flex-end;margin-top:12px}
</style>
</head>
<body>
<header>
  <h1>Админка (просто)</h1>
  <p>Меняйте имена, картинки и описание без кода</p>
</header>
<div class="bar">
  <button class="tab active" data-tab="artists">Артисты</button>
  <button class="tab" data-tab="albums">Альбомы</button>
  <button class="tab" data-tab="tracks">Треки</button>
</div>
<div class="controls">
  <input id="search" placeholder="Поиск..." />
  <button class="btn primary" id="add">+ Добавить</button>
</div>
<div class="list" id="list"></div>
<div class="status" id="status"></div>

<div class="modal" id="modal">
  <div class="box">
    <h2 id="mtitle">Редактирование</h2>
    <div id="mbody"></div>
    <div class="actions">
      <button class="btn" id="close">Закрыть</button>
      <button class="btn primary" id="save">Сохранить</button>
    </div>
    <div class="status" id="mstatus"></div>
  </div>
</div>

<script>
const api='/muzic2/public/admin/index.php?api=1';
const list=document.getElementById('list'); const statusEl=document.getElementById('status'); const search=document.getElementById('search'); const add=document.getElementById('add');
const modal=document.getElementById('modal'); const mtitle=document.getElementById('mtitle'); const mbody=document.getElementById('mbody'); const mstatus=document.getElementById('mstatus'); const closeBtn=document.getElementById('close'); const saveBtn=document.getElementById('save');
let tab='artists', items=[], current=null;
function ok(t){statusEl.textContent=t||''; statusEl.className='status ok'} function err(t){statusEl.textContent=t||''; statusEl.className='status err'} function mok(t){mstatus.textContent=t||''; mstatus.className='status ok'} function merr(t){mstatus.textContent=t||''; mstatus.className='status err'}
function esc(s){return String(s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]))}
function img(src){ if(!src) return ''; const p=src.startsWith('/muzic2/')?src:('/muzic2/'+src.replace(/^\//,'')); return `<img class="img" src="${p}" onerror="this.style.display='none'">`; }
function normVideo(v){ return (v||'').trim(); }
async function readJsonSafe(res){
  const text = await res.text().catch(()=> '');
  try {
    return text ? JSON.parse(text) : null;
  } catch (_) {
    const status = res && typeof res.status === 'number' ? res.status : 0;
    const prefix = status ? `HTTP ${status}. ` : '';
    const body = text ? `Ответ сервера: ${text.slice(0, 300)}` : 'Пустой ответ от сервера';
    throw new Error(prefix + body);
  }
}
function openVideoPreview(raw){ const url=normVideo(raw); let overlay=document.getElementById('video-preview'); if(!overlay){ overlay=document.createElement('div'); overlay.id='video-preview'; overlay.style.cssText='position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;z-index:100000'; const box=document.createElement('div'); box.style.cssText='background:#0f0f0f;border:1px solid #2a2a2a;border-radius:12px;max-width:800px;width:92%;padding:8px;position:relative'; const close=document.createElement('button'); close.textContent='×'; close.title='Закрыть'; close.style.cssText='position:absolute;top:6px;right:6px;width:28px;height:28px;border:none;border-radius:50%;background:#2a2a2a;color:#b3b3b3;cursor:pointer'; close.onclick=()=>{ try{v.pause();}catch(_){} document.body.removeChild(overlay); }; const v=document.createElement('video'); v.id='video-preview-player'; v.style.cssText='width:100%;height:auto;max-height:70vh;background:#000'; v.controls=true; box.appendChild(close); box.appendChild(v); overlay.appendChild(box); document.body.appendChild(overlay); } const vp=document.getElementById('video-preview-player'); if(vp){ try{ while(vp.firstChild) vp.removeChild(vp.firstChild);}catch(_){} const s=document.createElement('source'); s.src=url; s.type= (url.toLowerCase().endsWith('.webm')?'video/webm':'video/mp4'); vp.appendChild(s); vp.load(); setTimeout(()=>{ try{vp.play().catch(()=>{});}catch(_){} },0); } }
async function load(){ try{ ok('Загрузка...'); const q=search.value.trim(); const r=await fetch(`${api}&entity=${tab}${q?`&q=${encodeURIComponent(q)}`:''}`); const j=await readJsonSafe(r); if(!j || !j.success) throw new Error((j&&j.message)||'Ошибка'); items=j.data||[]; render(); ok(items.length?`Найдено: ${items.length}`:'Ничего не найдено'); }catch(e){ err(e.message)} }
 function render(){ list.innerHTML=''; if(tab==='artists'){ items.forEach(a=>{ const c=document.createElement('div'); c.className='card'; const cover=a.artist_cover||a.track_cover||''; c.innerHTML=`${img(cover)}<h3>${esc(a.artist||'Без имени')}</h3><div class='small'>Треков: ${a.tracks||0}</div><div class='row'><button class='btn primary'>Изменить</button><button class='btn' style='background:#3a1416;color:#ffb4b4'>Удалить</button></div>`; c.querySelector('.btn.primary').onclick=()=>editArtist(a); c.querySelectorAll('.btn')[1].onclick=()=>delArtist(a); list.appendChild(c); }); }
 if(tab==='albums'){ items.forEach(al=>{ const c=document.createElement('div'); c.className='card'; c.innerHTML=`${img(al.cover)}<h3>${esc(al.album||'Без названия')}</h3><div class='small'>Артист: ${esc(al.artist||'')}</div><div class='small'>Тип: ${esc(al.album_type||'')}</div><div class='small'>Треков: ${al.track_count||0}</div><div class='row'><button class='btn primary'>Изменить</button></div>`; c.querySelector('.btn.primary').onclick=()=>editAlbum(al); list.appendChild(c); }); }
 if(tab==='tracks'){ items.forEach(t=>{ const c=document.createElement('div'); c.className='card'; const hasVideo = !!(t.video_url && String(t.video_url).trim()); const exp = !!(t.explicit); const featsStr = (t.feats && String(t.feats).trim())? String(t.feats).trim() : ''; const combinedArtists = esc(featsStr? `${t.artist}, ${featsStr}` : (t.artist||'')); c.innerHTML=`${img(t.cover)}<h3>${esc(t.title||'Без названия')} — ${combinedArtists} ${exp?'<span title="Нецензурная лексика" style="display:inline-block;padding:0 6px;border-radius:4px;background:#3a3a3a;color:#fff;font-size:0.8rem;margin-left:6px">E</span>':''}</h3><div class='small'>Альбом: ${esc(t.album||'')}</div><div class='small'>Файл: ${esc(t.file_path||'')}</div><div class='small'>Видео: ${hasVideo?'<span style="color:#8aff8a">есть</span>':'нет'}</div><div class='row'><button class='btn primary'>Изменить</button><button class='btn' data-lyrics='1'>Текст</button>${hasVideo?"<button class='btn' data-preview='1'>Видео</button>":''}<button class='btn' style='background:#3a1416;color:#ffb4b4' >Удалить</button></div>`; c.querySelector('.btn.primary').onclick=()=>editTrack(t); const lyr=c.querySelector('[data-lyrics]'); if(lyr) lyr.onclick=()=>editLyrics(t); const btns=c.querySelectorAll('.btn'); if(hasVideo){ const pv=c.querySelector('[data-preview]'); if(pv) pv.onclick=()=>openVideoPreview(t.video_url); } (hasVideo?btns[3]:btns[2]).onclick=()=>delTrack(t); list.appendChild(c); }); }
}
function openModal(title, html, it){ mtitle.textContent=title; mbody.innerHTML=html; mstatus.textContent=''; modal.style.display='flex'; current=it||null; }
function closeModal(){ modal.style.display='none'; current=null; }
closeBtn.onclick=closeModal;
async function upload(file){ const fd=new FormData(); fd.append('cover', file); const r=await fetch('../upload_cover.php',{method:'POST',body:fd}); const j=await readJsonSafe(r); if(!r.ok||!j||!j.success) throw new Error((j&&j.message)||'Ошибка загрузки'); return j.path; }
async function uploadVideo(file){ const fd=new FormData(); fd.append('video', file); const r=await fetch('../upload_video.php',{method:'POST',body:fd}); const j=await readJsonSafe(r); if(!r.ok||!j||!j.success) throw new Error((j&&j.message)||'Ошибка загрузки'); return j.path; }
async function editArtist(a){
  let tracksOptions = `<option value="">— Не выбран —</option>`;
  try {
    const q = a.artist ? encodeURIComponent(a.artist) : '';
    const res = await fetch(`${api}&entity=tracks${q ? `&q=${q}` : ''}`);
    const data = await readJsonSafe(res);
    if (data.success && Array.isArray(data.data)) {
      data.data
        .filter(t => {
          if (!a.artist) return true;
          return String(t.artist || '').toLowerCase().includes(String(a.artist || '').toLowerCase());
        })
        .slice(0, 200)
        .forEach(t => {
          const selected = a.promo_track_id && Number(a.promo_track_id) === Number(t.id) ? 'selected' : '';
          tracksOptions += `<option value="${t.id}" ${selected}>${esc(t.title||'Без названия')} — ${esc(t.artist||'')}</option>`;
        });
    }
  } catch (err) {
    console.warn('Не удалось загрузить треки артиста', err);
  }
  openModal('Изменить артиста', `
    <div class='field'><label>Имя</label><input id='f-name' value='${esc(a.artist||'')}'></div>
    <div class='field'><label>Фото (путь или загрузите)</label><div style='display:flex;gap:8px'><input id='f-cover' value='${esc(a.artist_cover||a.track_cover||'')}' style='flex:1'><input id='f-file' type='file' accept='image/*' style='flex:1'></div></div>
    <div class='field'><label>Видео-промо/Интро (путь или загрузите)</label><div style='display:flex;gap:8px'><input id='f-promo-video' value='${esc(a.promo_video||'')}' style='flex:1' placeholder='tracks/videos/promo.mp4'><input id='f-video-file' type='file' accept='video/*' style='flex:1'></div><div style='font-size:12px;color:var(--muted);margin-top:4px'>Поддерживаются: MP4, WEBM, MOV, AVI (до 100 МБ)</div></div>
    <div class='field'><label>Трек для промо (отображается в интро)</label><select id='f-promo-track'>${tracksOptions}</select><div style='font-size:12px;color:var(--muted);margin-top:4px'>Эта информация появится поверх видео</div></div>
    <div class='field'><label>Био</label><textarea id='f-bio' rows='4'>${esc(a.bio||'')}</textarea></div>
  ` , a);
  saveBtn.onclick=async()=>{
    try{
      mok('Сохранение...');
      let cover=document.getElementById('f-cover').value.trim();
      const file=document.getElementById('f-file').files[0];
      if(file){
        cover=await upload(file);
        document.getElementById('f-cover').value=cover;
      }
      let promoVideo=document.getElementById('f-promo-video').value.trim();
      const videoFile=document.getElementById('f-video-file').files[0];
      if(videoFile){
        promoVideo=await uploadVideo(videoFile);
        document.getElementById('f-promo-video').value=promoVideo;
      }
      const promoTrackId = document.getElementById('f-promo-track').value || null;
      const payload={
        action:'update',
        name:a.artist,
        name_new:document.getElementById('f-name').value.trim(),
        cover,
        bio:document.getElementById('f-bio').value.trim(),
        promo_video:promoVideo,
        promo_track_id:promoTrackId
      };
      const r=await fetch(`${api}&entity=artists`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
      const j=await readJsonSafe(r); if(!j || !j.success) throw new Error((j&&j.message)||'Ошибка');
      closeModal(); load();
    }catch(e){ merr(e.message) }
  }
}
function editAlbum(al){ openModal(al.album ? 'Изменить альбом' : 'Создать альбом', `
  <div class='field'><label>Альбом</label><input id='f-album' value='${esc(al.album||'')}'></div>
  <div class='field'><label>Артист</label><input id='f-artist' value='${esc(al.artist||'')}'></div>
  <div class='field'><label>Тип</label><select id='f-type'><option value='album' ${al.album_type==='album'?'selected':''}>Альбом</option><option value='ep' ${al.album_type==='ep'?'selected':''}>EP</option><option value='single' ${al.album_type==='single'?'selected':''}>Сингл</option></select></div>
  <div class='field'><label>Обложка (путь или загрузите)</label><div style='display:flex;gap:8px'><input id='f-cover' value='${esc(al.cover||'')}' style='flex:1'><input id='f-file' type='file' accept='image/*' style='flex:1'></div></div>
  <div class='field'><label>Фиты для всех треков альбома (через запятую)</label><input id='f-feats' value='' placeholder='Kai Angel, 9mice'></div>
  <div class='row'>
    <button class='btn' id='btn-album-tracks'>Треки альбома</button>
    ${al.album?`<button class='btn' id='btn-album-delete' style='background:#3a1416;color:#ffb4b4'>Удалить альбом</button>`:''}
  </div>
  <div id='album-tracks-panel' style='display:none; margin-top:8px'>
    <div class='small'>Загрузка треков...</div>
    <div id='album-tracks-list' class='list' style='grid-template-columns:1fr; gap:8px'></div>
    <div class='row'><button class='btn primary' id='btn-add-track'>+ Добавить трек</button></div>
  </div>
`, al);
  // Save create/update
  saveBtn.onclick=async()=>{ try{ mok('Сохранение...'); let cover=document.getElementById('f-cover').value.trim(); const file=document.getElementById('f-file').files[0]; if(file){ cover=await upload(file); document.getElementById('f-cover').value=cover; } const newName = document.getElementById('f-album').value.trim(); const currentName = (al.album||'').trim(); const isCreate = !currentName; const feats = document.getElementById('f-feats').value.trim(); const payload = isCreate ? { action:'create', album:newName, artist: document.getElementById('f-artist').value.trim(), album_type: document.getElementById('f-type').value, cover, feats } : { action:'update', album: currentName ? currentName : newName, album_new: currentName && newName && currentName!==newName ? newName : '', artist: document.getElementById('f-artist').value.trim(), album_type: document.getElementById('f-type').value, cover, feats }; const r=await fetch(`${api}&entity=albums`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}); const j=await readJsonSafe(r); if(!j || !j.success) throw new Error((j&&j.message)||'Ошибка'); closeModal(); load(); }catch(e){ merr(e.message) } };
  // Load/delete/manage tracks
  const tracksBtn=document.getElementById('btn-album-tracks'); const panel=document.getElementById('album-tracks-panel'); const listEl=document.getElementById('album-tracks-list');
  if (tracksBtn && al.album){
    tracksBtn.onclick = async ()=>{
      panel.style.display = panel.style.display==='none'?'block':'none';
      if (panel.style.display==='block') {
        listEl.innerHTML = '<div class="small">Загрузка...</div>';
        try{
          const r=await fetch(`${api}&entity=tracks&album=${encodeURIComponent(al.album)}`);
          const j=await readJsonSafe(r); if(!j || !j.success) throw new Error((j&&j.message)||'Ошибка');
          const rows = j.data||[]; listEl.innerHTML='';
          rows.forEach(t=>{
            const row=document.createElement('div'); row.className='card';
            const hasVideo = !!(t.video_url && String(t.video_url).trim());
            row.innerHTML = `<div class='small'>ID ${t.id}</div><div><b>${esc(t.title||'Без названия')}</b> — ${esc(t.artist||'')}</div><div class='small'>Файл: ${esc(t.file_path||'')}</div><div class='small'>Видео: ${hasVideo?'<span style="color:#8aff8a">есть</span>':'нет'}</div><div class='row'><button class='btn' data-edit='1'>Изменить</button><button class='btn' data-lyrics='1'>Текст</button>${hasVideo?"<button class='btn' data-preview='1'>Видео</button>":''}<button class='btn' data-del='1' style='background:#3a1416;color:#ffb4b4'>Удалить</button></div>`;
            row.querySelector('[data-edit]').onclick=()=>editTrack(t);
            const lyr=row.querySelector('[data-lyrics]'); if (lyr) lyr.onclick=()=>editLyrics(t);
            if (hasVideo) { const pv=row.querySelector('[data-preview]'); pv.onclick=()=>openVideoPreview(t.video_url); }
            row.querySelector('[data-del]').onclick=async()=>{ if(!confirm('Удалить трек?')) return; const rr=await fetch(`${api}&entity=tracks`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',id:t.id})}); const jj=await readJsonSafe(rr); if(jj && jj.success){ row.remove(); } else { alert((jj&&jj.message)||'Ошибка'); } };
            listEl.appendChild(row);
          });
        }catch(e){ listEl.innerHTML = `<div class='small' style='color:#ff9b9b'>${esc(e.message||'Ошибка')}</div>`; }
      }
    };
  }
  const addTrackBtn = document.getElementById('btn-add-track');
  if (addTrackBtn){ addTrackBtn.onclick=()=>{ editTrack({id:0,title:'',artist: (al.artist||''), album:(al.album||document.getElementById('f-album').value.trim()), album_type: (al.album_type||'album'), file_path:'', cover:'', duration:0}); } }
  const delBtn=document.getElementById('btn-album-delete');
  if (delBtn){ delBtn.onclick=async()=>{ if(!confirm('Удалить альбом и все его треки?')) return; try{ mok('Удаление...'); const r=await fetch(`${api}&entity=albums`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',album:al.album})}); const j=await readJsonSafe(r); if(!j || !j.success) throw new Error((j&&j.message)||'Ошибка'); closeModal(); load(); }catch(e){ merr(e.message) } } }
}
function editTrack(t){ openModal('Изменить трек', `<div class='field'><label>Название</label><input id='f-title' value='${esc(t.title||'')}'></div><div class='field'><label>Артист</label><input id='f-artist' value='${esc(t.artist||'')}'></div><div class='field'><label>Фиты (через запятую)</label><input id='f-feats' value='${esc(t.feats||'')}' placeholder='Kai Angel, 9mice'></div><div class='field'><label>Альбом</label><input id='f-album' value='${esc(t.album||'')}'></div><div class='field'><label>Тип</label><select id='f-type'><option value='album' ${t.album_type==='album'?'selected':''}>Альбом</option><option value='ep' ${t.album_type==='ep'?'selected':''}>EP</option><option value='single' ${t.album_type==='single'?'selected':''}>Сингл</option></select></div><div class='field'><label>Файл (tracks/music/...)</label><input id='f-file' value='${esc(t.file_path||'')}'></div><div class='field'><label>Обложка (путь или загрузите)</label><div style='display:flex;gap:8px'><input id='f-cover' value='${esc(t.cover||'')}' style='flex:1'><input id='f-file2' type='file' accept='image/*' style='flex:1'></div></div><div class='field'><label>Длительность (сек)</label><input id='f-dur' type='number' value='${t.duration||0}'></div><div class='field'><label>Нецензурная лексика</label><label style='display:inline-flex;align-items:center;gap:8px'><input id='f-exp' type='checkbox' ${t.explicit? 'checked':''}> Показать значок E</label></div><div class='field'><label>Видео (URL)</label><div style='display:flex;gap:8px'><input id='f-video' value='${esc(t.video_url||'')}' placeholder='tracks/video/file.mp4 или полный URL' style='flex:1'><button class='btn' id='preview-video-btn' type='button'>Проверить видео</button></div></div>`, t); const previewBtn=document.getElementById('preview-video-btn'); if(previewBtn){ previewBtn.onclick=()=>{ openVideoPreview((document.getElementById('f-video').value||'')); }; } saveBtn.onclick=async()=>{ try{ mok('Сохранение...'); let cover=document.getElementById('f-cover').value.trim(); const file=document.getElementById('f-file2').files[0]; if(file){ cover=await upload(file); document.getElementById('f-cover').value=cover; } const isCreate = !t.id || t.id===0; const base = { title:document.getElementById('f-title').value.trim(), artist:document.getElementById('f-artist').value.trim(), feats:document.getElementById('f-feats').value.trim(), album:document.getElementById('f-album').value.trim(), album_type:document.getElementById('f-type').value, file_path:document.getElementById('f-file').value.trim(), cover, duration:parseInt(document.getElementById('f-dur').value||0,10), explicit: !!document.getElementById('f-exp').checked, video_url: normVideo(document.getElementById('f-video').value) }; const payload = isCreate ? ({action:'create', ...base}) : ({action:'update', id:t.id, ...base}); const r=await fetch(`${api}&entity=tracks`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}); const j=await readJsonSafe(r); if(!j || !j.success) throw new Error((j&&j.message)||'Ошибка'); closeModal(); load(); }catch(e){ merr(e.message) } } }

// Lyrics editor
async function editLyrics(t){
  const trackId = parseInt(t.id||0,10);
  openModal('Текст (караоке)', `<div class='field'><label>Трек</label><div class='small'>${esc(t.title||'')} — ${esc(t.artist||'')}</div></div><div class='field'><label>LRC</label><textarea id='f-lrc' rows='10' placeholder='[00:00.00] Первая строка\n[00:10.50] Вторая строка'></textarea></div>`, t);
  // Load existing
  try{ const r=await fetch(`/muzic2/src/api/lyrics.php?track_id=${trackId}`); const j=await readJsonSafe(r); if (j && j.lrc) document.getElementById('f-lrc').value = j.lrc; }catch(_){ }
  saveBtn.onclick = async ()=>{
    try{
      mok('Сохранение...');
      const lrc = document.getElementById('f-lrc').value;
      const r = await fetch(`/muzic2/src/api/lyrics.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ track_id: trackId, lrc })});
      const j = await readJsonSafe(r); if (!r.ok || !j || !j.success) throw new Error((j&&j.error)||'Ошибка сохранения');
      closeModal(); ok('Текст сохранён');
    }catch(e){ merr(e.message); }
  };
}
async function delTrack(t){ if(!confirm(`Удалить трек "${t.title}"?`)) return; const r=await fetch(`${api}&entity=tracks`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',id:t.id})}); const j=await readJsonSafe(r); if(j && j.success){ ok('Трек удалён'); load(); } else { err((j&&j.message)||'Ошибка') } }
async function delArtist(a){ if(!confirm(`Удалить артиста "${a.artist}"?`)) return; const r=await fetch(`${api}&entity=artists`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',name:a.artist})}); const j=await readJsonSafe(r); if(j && j.success){ ok('Артист удалён'); load(); } else { err((j&&j.message)||'Ошибка') } }
add.onclick = async () => {
  if (tab === 'artists') {
    await editArtist({ artist: '', artist_cover: '', bio: '', promo_video: '', promo_track_id: null });
    document.getElementById('f-name').value = '';
    saveBtn.onclick = async () => {
      try {
        mok('Сохранение...');
        let cover = document.getElementById('f-cover').value.trim();
        const file = document.getElementById('f-file').files[0];
        if (file) {
          cover = await upload(file);
          document.getElementById('f-cover').value = cover;
        }
        let promoVideo = document.getElementById('f-promo-video').value.trim();
        const videoFile = document.getElementById('f-video-file').files[0];
        if (videoFile) {
          promoVideo = await uploadVideo(videoFile);
          document.getElementById('f-promo-video').value = promoVideo;
        }
        const promoTrackId = document.getElementById('f-promo-track').value || null;
        const payload = {
          action: 'create',
          name: document.getElementById('f-name').value.trim(),
          cover,
          bio: document.getElementById('f-bio').value.trim(),
          promo_video: promoVideo,
          promo_track_id: promoTrackId
        };
        const r = await fetch(`${api}&entity=artists`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const j = await readJsonSafe(r);
        if (!j || !j.success) throw new Error((j&&j.message) || 'Ошибка');
        closeModal();
        load();
      } catch (e) { merr(e.message); }
    };
  } else if (tab === 'albums') {
    editAlbum({ album: '', artist: '', album_type: 'album', cover: '' });
  } else if (tab === 'tracks') {
    editTrack({ id:0,title:'',artist:'',feats:'',album:'',album_type:'album',file_path:'',cover:'',duration:0, explicit:0}); const originalHandler = saveBtn.onclick; saveBtn.onclick = async ()=>{ try{ // override to create when id is 0
      mok('Сохранение...');
      let cover=document.getElementById('f-cover').value.trim();
      const file=document.getElementById('f-file2').files[0];
      if(file){ cover=await upload(file); document.getElementById('f-cover').value=cover; }
      const payload={action:'create', title:document.getElementById('f-title').value.trim(), artist:document.getElementById('f-artist').value.trim(), feats:document.getElementById('f-feats').value.trim(), album:document.getElementById('f-album').value.trim(), album_type:document.getElementById('f-type').value, file_path:document.getElementById('f-file').value.trim(), cover, duration:parseInt(document.getElementById('f-dur').value||0,10), explicit: !!document.getElementById('f-exp').checked, video_url: normVideo(document.getElementById('f-video').value)};
      const r=await fetch(`${api}&entity=tracks`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
      const j=await readJsonSafe(r); if(!j || !j.success) throw new Error((j&&j.message)||'Ошибка'); closeModal(); load();
    }catch(e){ merr(e.message) } } }
};

document.querySelectorAll('.tab').forEach(b=> b.onclick=()=>{ document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active')); b.classList.add('active'); tab=b.dataset.tab; load(); });
search.oninput=()=>{ clearTimeout(search._t); search._t=setTimeout(load,300) };
load();
</script>
</body>
</html>
