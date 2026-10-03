<?php 
require 'config.php';
$settings = getSettings();
$site = $settings['site'];
$theme = $settings['theme'];

// 合并本地歌曲 + 元数据
$localSongs = scanLocalAudio();
$allMeta = getSongs();
$metaMap = [];
foreach ($allMeta as $m) {
    $metaMap[$m['url']] = $m;
}

// 合并完整数据
$songs = [];
foreach ($localSongs as $song) {
    if (isset($metaMap[$song['url']])) {
        $songs[] = array_merge($song, $metaMap[$song['url']]);
    } else {
        $song['style'] = '';
        $songs[] = $song;
    }
}
usort($songs, function($a, $b) { return $a['sort'] - $b['sort']; });

// 按风格分组
$songGroups = [];
foreach ($songs as $song) {
    $style = $song['style'] ?: 'other';
    if (!isset($songGroups[$style])) $songGroups[$style] = [];
    $songGroups[$style][] = $song;
}

// 今日专属推荐：每日随机10首，同一天固定
$seed = intval(date('Ymd'));
mt_srand($seed);
$recommendSongs = $songs;
shuffle($recommendSongs);
$recommendSongs = array_slice($recommendSongs, 0, 10);

// 生成背景样式
$maskOpacity = $theme['bg_mask_opacity'];
switch ($theme['bg_mode']) {
    case 'white': $bgStyle = 'background: #ffffff;'; break;
    case 'solid': $bgStyle = 'background: #f5f7fa;'; break;
    case 'dark': $bgStyle = 'background: #0f172a; color: #e2e8f0;'; break;
    case 'image':
        $bgImage = htmlspecialchars($theme['bg_image_url']);
        $bgStyle = "background: linear-gradient(rgba(15,23,42,{$maskOpacity}), rgba(15,23,42,{$maskOpacity})), url('{$bgImage}') center/cover fixed no-repeat;";
        break;
    default: $bgStyle = 'background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%, #eef2ff 100%);';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($site['name']) ?></title>
<style>
  :root {
    --primary: <?= $theme['primary_color'] ?>;
    --secondary: <?= $theme['secondary_color'] ?>;
    --text-main: <?= $theme['bg_mode'] == 'dark' ? '#e2e8f0' : '#1e293b' ?>;
    --text-sub: <?= $theme['bg_mode'] == 'dark' ? '#94a3b8' : '#64748b' ?>;
    --card-bg: <?= $theme['glass_effect'] ? 'rgba(30,41,59,0.7)' : '#1e293b' ?>;
    --card-border: <?= $theme['glass_effect'] ? 'rgba(255,255,255,0.1)' : '#334155' ?>;
    --blur: <?= $theme['glass_effect'] ? 'blur(12px)' : 'none' ?>;
  }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  
  html {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
  }
  
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "PingFang SC", sans-serif;
    <?= $bgStyle ?>
    color: var(--text-main);
    line-height: 1.6;
    padding: 24px 20px 80px;
    max-width: 1200px;
    margin: 0 auto;
    min-height: 100vh;
    background-attachment: fixed;
  }

  /* 顶部播放器 - 保留毛玻璃 */
  .player-bar {
    background: var(--card-bg);
    backdrop-filter: var(--blur);
    -webkit-backdrop-filter: var(--blur);
    border-radius: 16px;
    border: 1px solid var(--card-border);
    padding: 14px 24px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 20px;
    position: sticky;
    top: 20px;
    z-index: 100;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    transform: translateZ(0);
    transition: backdrop-filter 0.3s ease, background 0.3s ease;
  }

  <?php if ($theme['glass_effect']): ?>
  .player-bar {
    background: rgba(30, 41, 59, 0.35);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border-color: rgba(255, 255, 255, 0.12);
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.25);
  }
  <?php endif; ?>

  .player-info { flex: 1; min-width: 0; }
  .player-title {
    font-size: 15px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .player-artist {
    font-size: 12px;
    color: var(--text-sub);
    margin-top: 2px;
  }
  .controls {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .ctrl-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: rgba(255,255,255,0.08);
    color: var(--text-main);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    font-size: 14px;
    transform: translateZ(0);
  }
  .ctrl-btn:hover {
    background: rgba(59, 130, 246, 0.2);
    color: var(--primary);
    transform: scale(1.08);
  }
  .play-btn {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #fff;
    font-size: 16px;
  }
  .play-btn:hover { color: #fff; }
  
  .progress-wrap { flex: 2; }
  .progress-bar {
    width: 100%;
    height: 4px;
    background: rgba(255,255,255,0.1);
    border-radius: 2px;
    cursor: pointer;
  }
  .progress-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border-radius: 2px;
    transition: width 0.1s linear;
  }
  .time-row {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: var(--text-sub);
    margin-top: 4px;
  }

  .volume {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
  }
  .volume input {
    width: 70px;
    accent-color: var(--primary);
  }

  /* 搜索框 - 保留毛玻璃 */
  .search-box {
    margin-bottom: 24px;
  }
  .search-box input {
    width: 100%;
    max-width: 360px;
    padding: 10px 18px;
    border-radius: 24px;
    border: 1px solid var(--card-border);
    background: var(--card-bg);
    backdrop-filter: var(--blur);
    color: var(--text-main);
    font-size: 14px;
    outline: none;
    transition: all 0.2s;
    transform: translateZ(0);
  }
  .search-box input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
  }
  .search-box input::placeholder {
    color: var(--text-sub);
  }

  <?php if ($theme['glass_effect']): ?>
  .search-box input {
    background: rgba(30, 41, 59, 0.35);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border-color: rgba(255, 255, 255, 0.12);
  }
  <?php endif; ?>

  /* 区块标题 */
  .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 32px 0 12px;
    transform: translateZ(0);
  }
  .section-title {
    font-size: 18px;
    font-weight: 700;
    position: relative;
    padding-left: 10px;
  }
  .section-title::before {
    content: '';
    position: absolute;
    left: 0; top: 50%;
    transform: translateY(-50%);
    width: 3px; height: 18px;
    background: linear-gradient(180deg, var(--primary), var(--secondary));
    border-radius: 2px;
  }
  .section-more {
    font-size: 13px;
    color: var(--text-sub);
    text-decoration: none;
    transition: color 0.2s;
  }
  .section-more:hover { color: var(--primary); }

  /* 卡片横向滚动 - 轻玻璃感，不压背景 */
  .card-row {
    display: flex;
    gap: 16px;
    overflow-x: auto;
    padding: 18px;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(6px) saturate(140%);
    -webkit-backdrop-filter: blur(6px) saturate(140%);
    border: 1px solid rgba(255, 255, 255, 0.10);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18);
    scroll-behavior: smooth;
    margin-bottom: 8px;
    -webkit-overflow-scrolling: touch;
  }

  .card-row::-webkit-scrollbar { height: 6px; }
  .card-row::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.12);
    border-radius: 3px;
  }

  /* 歌曲卡片 */
  .song-card {
    flex-shrink: 0;
    width: 170px;
    cursor: pointer;
    transition: transform 0.25s ease;
    transform: translateZ(0);
  }
  .song-card:hover { transform: translateY(-4px); }
  .card-cover {
    position: relative;
    width: 170px;
    height: 170px;
    border-radius: 14px;
    overflow: hidden;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    margin-bottom: 12px;
    transform: translateZ(0);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.25);
  }
  .card-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    position: relative;
    z-index: 1;
    transform: translateZ(0);
  }
  .cover-placeholder {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 56px;
    font-weight: 700;
    color: rgba(255,255,255,0.9);
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    user-select: none;
  }
  .card-tag {
    position: absolute;
    top: 10px;
    left: 10px;
    padding: 3px 9px;
    background: rgba(0,0,0,0.45);
    color: #fff;
    font-size: 11px;
    border-radius: 6px;
    backdrop-filter: blur(6px);
    z-index: 2;
  }
  .card-count {
    position: absolute;
    bottom: 10px;
    left: 10px;
    color: #fff;
    font-size: 12px;
    text-shadow: 0 2px 6px rgba(0,0,0,0.6);
    z-index: 2;
  }
  .card-title {
    font-size: 14px;
    font-weight: 500;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    color: #f8fafc;
  }

  /* 全部歌曲列表 - 轻玻璃感 */
  .song-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 14px;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(6px) saturate(140%);
    -webkit-backdrop-filter: blur(6px) saturate(140%);
    border: 1px solid rgba(255, 255, 255, 0.10);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18);
  }

  .song-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 14px;
    border-radius: 12px;
    cursor: pointer;
    transition: background 0.2s ease, transform 0.2s ease;
    transform: translateZ(0);
  }
  .song-item:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateX(2px);
  }
  .song-item.active {
    background: rgba(59, 130, 246, 0.18);
  }
  .song-item-cover {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    overflow: hidden;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 18px;
    font-weight: 700;
    flex-shrink: 0;
    position: relative;
    transform: translateZ(0);
    box-shadow: 0 6px 14px rgba(0, 0, 0, 0.25);
  }
  .song-item-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: translateZ(0);
  }
  .song-item-info { flex: 1; min-width: 0; }
  .song-item-title {
    font-size: 14px;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #f8fafc;
  }
  .song-item-artist {
    font-size: 12px;
    color: #cbd5e1;
    margin-top: 2px;
  }
  .song-item-style {
    font-size: 11px;
    padding: 3px 9px;
    background: rgba(59,130,246,0.15);
    color: #7dd3fc;
    border-radius: 6px;
    border: 1px solid rgba(125, 211, 252, 0.25);
  }

  /* 均衡器 */
  .eq-toggle {
    cursor: pointer;
    font-size: 16px;
    position: relative;
  }
  .eq-panel {
    display: none;
    position: absolute;
    bottom: -150px;
    right: 0;
    background: var(--card-bg);
    backdrop-filter: var(--blur);
    border: 1px solid var(--card-border);
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    z-index: 101;
    min-width: 220px;
    transform: translateZ(0);
  }
  .eq-panel.show { display: block; }
  .eq-sliders {
    display: flex;
    justify-content: space-around;
    align-items: flex-end;
    gap: 10px;
  }
  .eq-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
  }
  .eq-slider {
    writing-mode: vertical-lr;
    direction: rtl;
    width: 4px;
    height: 70px;
    accent-color: var(--primary);
    cursor: pointer;
  }
  .eq-item span {
    font-size: 11px;
    color: var(--text-sub);
  }

  @media (max-width: 768px) {
    body { padding: 15px 12px; }
    .player-bar { flex-wrap: wrap; padding: 12px; gap: 10px; }
    .progress-wrap { order: 3; width: 100%; flex: none; }
    .song-card { width: 140px; }
    .card-cover { width: 140px; height: 140px; }
  }
</style>
</head>
<body>

<!-- 顶部播放器 -->
<div class="player-bar" id="player">
  <div class="player-info">
    <div class="player-title" id="songTitle">选择一首歌曲</div>
    <div class="player-artist" id="songArtist">本地音乐</div>
  </div>
  
  <div class="controls">
    <button class="ctrl-btn" id="prevBtn" title="上一首">⏮</button>
    <button class="ctrl-btn play-btn" id="playBtn" title="播放/暂停">▶</button>
    <button class="ctrl-btn" id="nextBtn" title="下一首">⏭</button>
  </div>

  <div class="progress-wrap">
    <div class="progress-bar" id="progressBar">
      <div class="progress-fill" id="progressFill"></div>
    </div>
    <div class="time-row">
      <span id="currentTime">00:00</span>
      <span id="totalTime">00:00</span>
    </div>
  </div>

  <div class="volume">
    <span>🔊</span>
    <input type="range" id="volumeSlider" min="0" max="1" step="0.01" value="0.8">
  </div>

  <div class="eq-toggle" id="eqToggle" title="均衡器">🎛</div>
  
  <div class="eq-panel" id="eqPanel">
    <div class="eq-sliders">
      <div class="eq-item">
        <span>低频</span>
        <input type="range" class="eq-slider" data-freq="60" min="-12" max="12" value="0">
      </div>
      <div class="eq-item">
        <span>中低</span>
        <input type="range" class="eq-slider" data-freq="250" min="-12" max="12" value="0">
      </div>
      <div class="eq-item">
        <span>中频</span>
        <input type="range" class="eq-slider" data-freq="1000" min="-12" max="12" value="0">
      </div>
      <div class="eq-item">
        <span>中高</span>
        <input type="range" class="eq-slider" data-freq="4000" min="-12" max="12" value="0">
      </div>
      <div class="eq-item">
        <span>高频</span>
        <input type="range" class="eq-slider" data-freq="12000" min="-12" max="12" value="0">
      </div>
    </div>
  </div>
</div>

<!-- 搜索框 -->
<div class="search-box">
  <input type="text" id="searchInput" placeholder="搜索歌曲、歌手...">
</div>

<!-- 今日专属推荐（每日随机10首） -->
<div class="section-header">
  <h2 class="section-title">今日专属推荐</h2>
  <a href="#" class="section-more">歌单广场 ›</a>
</div>
<div class="card-row" id="recommendRow">
  <?php foreach ($recommendSongs as $song): ?>
  <div class="song-card" data-url="<?= htmlspecialchars($song['url']) ?>" data-title="<?= htmlspecialchars($song['title']) ?>" data-artist="<?= htmlspecialchars($song['artist']) ?>">
    <div class="card-cover">
      <?php if (!empty($song['cover'])): ?>
      <img 
        src="<?= htmlspecialchars($song['cover']) ?>" 
        alt="<?= htmlspecialchars($song['title']) ?>"
        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
      >
      <?php endif; ?>
      <div class="cover-placeholder" style="<?= !empty($song['cover']) ? 'display:none;' : '' ?>">
        <?= getFirstChar($song['title']) ?>
      </div>
      <span class="card-tag">推荐</span>
      <span class="card-count">🔥 今日精选</span>
    </div>
    <div class="card-title"><?= htmlspecialchars($song['title']) ?></div>
  </div>
  <?php endforeach; ?>
</div>

<?php
// 按风格输出各个区块
foreach ($songGroups as $styleKey => $styleSongs):
    if (empty($styleSongs)) continue;
    $styleName = MUSIC_STYLES[$styleKey] ?? '其他';
?>
<div class="section-header">
  <h2 class="section-title"><?= $styleName ?></h2>
  <a href="#all" class="section-more">更多 ›</a>
</div>
<div class="card-row">
  <?php foreach ($styleSongs as $song): ?>
  <div class="song-card" data-url="<?= htmlspecialchars($song['url']) ?>" data-title="<?= htmlspecialchars($song['title']) ?>" data-artist="<?= htmlspecialchars($song['artist']) ?>">
    <div class="card-cover">
      <?php if (!empty($song['cover'])): ?>
      <img 
        src="<?= htmlspecialchars($song['cover']) ?>" 
        alt="<?= htmlspecialchars($song['title']) ?>"
        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
      >
      <?php endif; ?>
      <div class="cover-placeholder" style="<?= !empty($song['cover']) ? 'display:none;' : '' ?>">
        <?= getFirstChar($song['title']) ?>
      </div>
      <span class="card-tag"><?= $styleName ?></span>
      <span class="card-count">♪ <?= count($styleSongs) ?>首</span>
    </div>
    <div class="card-title"><?= htmlspecialchars($song['title']) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>

<!-- 全部歌曲列表 -->
<div class="section-header" id="all">
  <h2 class="section-title">全部歌曲（共 <?= count($songs) ?> 首）</h2>
</div>
<div class="song-list" id="allSongList">
  <?php foreach ($songs as $index => $song): ?>
  <div class="song-item" data-index="<?= $index ?>" data-url="<?= htmlspecialchars($song['url']) ?>" data-title="<?= htmlspecialchars($song['title']) ?>" data-artist="<?= htmlspecialchars($song['artist']) ?>">
    <div class="song-item-cover">
      <?php if (!empty($song['cover'])): ?>
      <img 
        src="<?= htmlspecialchars($song['cover']) ?>" 
        alt="<?= htmlspecialchars($song['title']) ?>"
        onerror="this.style.display='none';this.parentElement.innerHTML='<span>'.json_encode(getFirstChar($song['title'])).'</span>';"
      >
      <?php else: ?>
      <span><?= getFirstChar($song['title']) ?></span>
      <?php endif; ?>
    </div>
    <div class="song-item-info">
      <div class="song-item-title"><?= htmlspecialchars($song['title']) ?></div>
      <div class="song-item-artist"><?= htmlspecialchars($song['artist']) ?></div>
    </div>
    <?php if (!empty($song['style'])): ?>
    <div class="song-item-style"><?= MUSIC_STYLES[$song['style']] ?? '未知' ?></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<audio id="audio">

<script>
  const audio = document.getElementById('audio');
  const player = document.getElementById('player');
  const playBtn = document.getElementById('playBtn');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const progressBar = document.getElementById('progressBar');
  const progressFill = document.getElementById('progressFill');
  const currentTimeEl = document.getElementById('currentTime');
  const totalTimeEl = document.getElementById('totalTime');
  const volumeSlider = document.getElementById('volumeSlider');
  const songTitle = document.getElementById('songTitle');
  const songArtist = document.getElementById('songArtist');
  const allSongs = document.querySelectorAll('.song-card, .song-item');
  const searchInput = document.getElementById('searchInput');
  
  let currentIndex = -1;
  audio.volume = 0.8;

  // 格式化时间
  function formatTime(sec) {
    const m = Math.floor(sec / 60).toString().padStart(2, '0');
    const s = Math.floor(sec % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
  }

  // 播放指定歌曲
  function playSong(element) {
    const url = element.dataset.url;
    const title = element.dataset.title;
    const artist = element.dataset.artist;
    
    audio.src = url;
    songTitle.textContent = title;
    songArtist.textContent = artist;
    
    allSongs.forEach(el => {
      if (el.classList.contains('song-item')) el.classList.remove('active');
    });
    if (element.classList.contains('song-item')) {
      element.classList.add('active');
    }
    
    audio.play().catch(() => {
      playBtn.textContent = '▶';
    });
    
    const listItems = Array.from(document.querySelectorAll('.song-item'));
    currentIndex = listItems.findIndex(item => item.dataset.url === url);
  }

  // 播放状态
  audio.addEventListener('play', () => {
    playBtn.textContent = '⏸';
  });
  audio.addEventListener('pause', () => {
    playBtn.textContent = '▶';
  });

  // 播放暂停按钮
  playBtn.addEventListener('click', () => {
    if (currentIndex === -1) {
      const first = document.querySelector('.song-item');
      if (first) playSong(first);
      return;
    }
    if (audio.paused) audio.play();
    else audio.pause();
  });

  // 上一首/下一首
  prevBtn.addEventListener('click', () => {
    const list = Array.from(document.querySelectorAll('.song-item')).filter(i => i.style.display !== 'none');
    if (list.length === 0) return;
    let idx = list.findIndex(i => i.dataset.url === audio.src);
    idx = idx <= 0 ? list.length - 1 : idx - 1;
    playSong(list[idx]);
  });
  nextBtn.addEventListener('click', () => {
    const list = Array.from(document.querySelectorAll('.song-item')).filter(i => i.style.display !== 'none');
    if (list.length === 0) return;
    let idx = list.findIndex(i => i.dataset.url === audio.src);
    idx = (idx + 1) % list.length;
    playSong(list[idx]);
  });

  // 点击播放
  allSongs.forEach(el => {
    el.addEventListener('click', () => playSong(el));
  });

  // 进度更新
  audio.addEventListener('timeupdate', () => {
    const percent = (audio.currentTime / audio.duration) * 100 || 0;
    progressFill.style.width = percent + '%';
    currentTimeEl.textContent = formatTime(audio.currentTime);
  });
  audio.addEventListener('loadedmetadata', () => {
    totalTimeEl.textContent = formatTime(audio.duration);
  });

  // 拖动进度
  progressBar.addEventListener('click', (e) => {
    const rect = progressBar.getBoundingClientRect();
    const percent = (e.clientX - rect.left) / rect.width;
    audio.currentTime = percent * audio.duration;
  });

  // 音量
  volumeSlider.addEventListener('input', () => {
    audio.volume = volumeSlider.value;
  });

  // 搜索过滤
  searchInput.addEventListener('input', () => {
    const keyword = searchInput.value.trim().toLowerCase();
    
    document.querySelectorAll('.song-item').forEach(item => {
      const title = item.dataset.title.toLowerCase();
      const artist = item.dataset.artist.toLowerCase();
      item.style.display = (title.includes(keyword) || artist.includes(keyword)) ? 'flex' : 'none';
    });
    
    document.querySelectorAll('.song-card').forEach(card => {
      const title = card.dataset.title.toLowerCase();
      const artist = card.dataset.artist.toLowerCase();
      card.style.display = (title.includes(keyword) || artist.includes(keyword)) ? 'block' : 'none';
    });
  });

  // 均衡器
  const eqToggle = document.getElementById('eqToggle');
  const eqPanel = document.getElementById('eqPanel');
  const eqSliders = document.querySelectorAll('.eq-slider');
  let audioContext = null;
  let sourceNode = null;
  let eqFilters = [];

  eqToggle.addEventListener('click', (e) => {
    e.stopPropagation();
    eqPanel.classList.toggle('show');
    initEqualizer();
  });
  document.addEventListener('click', () => {
    eqPanel.classList.remove('show');
  });
  eqPanel.addEventListener('click', e => e.stopPropagation());

  function initEqualizer() {
    if (audioContext) return;
    audioContext = new (window.AudioContext || window.webkitAudioContext)();
    sourceNode = audioContext.createMediaElementSource(audio);
    
    const freqConfig = [60, 250, 1000, 4000, 12000];
    freqConfig.forEach((freq, index) => {
      const filter = audioContext.createBiquadFilter();
      filter.type = 'peaking';
      filter.frequency.value = freq;
      filter.Q.value = 1.2;
      filter.gain.value = 0;
      eqFilters.push(filter);
      
      if (index === 0) sourceNode.connect(filter);
      else eqFilters[index - 1].connect(filter);
    });
    eqFilters[eqFilters.length - 1].connect(audioContext.destination);
    
    eqSliders.forEach((slider, index) => {
      slider.addEventListener('input', () => {
        eqFilters[index].gain.value = parseFloat(slider.value);
      });
    });
  }

  // 自动连播
  audio.addEventListener('ended', () => {
    const list = Array.from(document.querySelectorAll('.song-item')).filter(i => i.style.display !== 'none');
    if (list.length === 0) return;
    let idx = list.findIndex(i => i.dataset.url === audio.src);
    idx = (idx + 1) % list.length;
    playSong(list[idx]);
  });
</script>
</body>
</html>
