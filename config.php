<?php
// 后台管理员账号密码
define('ADMIN_USER', 'cuiyouxin');
define('ADMIN_PASS', password_hash('cuiyouxin', PASSWORD_DEFAULT));

// 数据文件路径
define('SONGS_FILE', __DIR__ . '/songs.json');
define('SETTINGS_FILE', __DIR__ . '/settings.json');

// 本地音频文件夹
define('AUDIO_DIR', __DIR__ . '/audio');
define('AUDIO_EXTS', ['mp3', 'm4a', 'wav', 'ogg', 'flac']);

// 音乐风格分类（仅用于前台展示，无API调用）
define('MUSIC_STYLES', [
    'dj' => 'DJ热碟',
    'guofeng' => '中国风',
    'pop' => '流行',
    'cantonese' => '粤语',
    'classic' => '经典',
    'dance' => '舞曲',
    'guqin' => '古风',
    'rock' => '摇滚',
    'folk' => '民谣',
    'rap' => '说唱',
    'light' => '轻音乐',
    'ost' => '影视原声'
]);

session_start();

/**
 * 读取歌曲列表（损坏自动降级）
 */
function getSongs() {
    if (!file_exists(SONGS_FILE)) {
        saveSongs([]);
        return [];
    }
    $json = @file_get_contents(SONGS_FILE);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/**
 * 保存歌曲列表（原子写入，不会损坏）
 */
function saveSongs($songs) {
    $json = json_encode($songs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $tmpFile = SONGS_FILE . '.tmp';
    @file_put_contents($tmpFile, $json, LOCK_EX);
    @rename($tmpFile, SONGS_FILE);
}

/**
 * 匹配本地同名封面图片
 */
function getLocalCover($audioFilename) {
    $baseName = pathinfo($audioFilename, PATHINFO_FILENAME);
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        if (file_exists(AUDIO_DIR . '/' . $baseName . '.' . $ext)) {
            return 'audio/' . rawurlencode($baseName . '.' . $ext);
        }
    }
    return '';
}

/**
 * 扫描本地音频
 */
function scanLocalAudio() {
    if (!is_dir(AUDIO_DIR)) {
        @mkdir(AUDIO_DIR, 0755, true);
        return [];
    }
    
    $files = @scandir(AUDIO_DIR);
    if (!$files) return [];
    
    $songs = [];
    $index = 0;
    
    $cacheMap = [];
    foreach (getSongs() as $m) {
        if (isset($m['url'])) $cacheMap[$m['url']] = $m;
    }
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, AUDIO_EXTS)) continue;
        
        $title = pathinfo($file, PATHINFO_FILENAME);
        $url = 'audio/' . rawurlencode($file);
        
        // 本地同名封面优先
        $cover = getLocalCover($file);
        if (!$cover && isset($cacheMap[$url]['cover'])) {
            $cover = $cacheMap[$url]['cover'];
        }

        // 读取已有风格，没有则为空
        $style = isset($cacheMap[$url]['style']) ? $cacheMap[$url]['style'] : '';
        
        if (isset($cacheMap[$url])) {
            $song = $cacheMap[$url];
            $song['title'] = $title;
            $song['url'] = $url;
            $song['cover'] = $cover;
            $song['artist'] = $song['artist'] ?? '本地音乐';
            $song['style'] = $style;
            $song['sort'] = $song['sort'] ?? 100 + $index;
            $song['status'] = $song['status'] ?? 1;
        } else {
            $song = [
                'id' => 'local_' . $index,
                'title' => $title,
                'artist' => '本地音乐',
                'cover' => $cover,
                'url' => $url,
                'desc' => '本地音频文件',
                'sort' => 100 + $index,
                'status' => 1,
                'style' => ''
            ];
        }
        
        $songs[] = $song;
        $index++;
    }
    
    return $songs;
}

/**
 * 读取全局配置
 */
function getSettings() {
    if (!file_exists(SETTINGS_FILE)) {
        $default = [
            'site' => ['name' => '在线音乐', 'desc' => '我的私人音乐库', 'since' => '2026'],
            'theme' => [
                'primary_color' => '#3b82f6',
                'secondary_color' => '#8b5cf6',
                'bg_mode' => 'dark',
                'bg_image_url' => '',
                'bg_mask_opacity' => 0.85,
                'glass_effect' => true,
                'hover_glow' => true,
                'fade_animation' => true,
                'bg_light' => true
            ]
        ];
        saveSettings($default);
        return $default;
    }
    $json = @file_get_contents(SETTINGS_FILE);
    $settings = json_decode($json, true) ?: [];
    
    $defaultTheme = [
        'primary_color' => '#3b82f6',
        'secondary_color' => '#8b5cf6',
        'bg_mode' => 'dark',
        'bg_image_url' => '',
        'bg_mask_opacity' => 0.85,
        'glass_effect' => true,
        'hover_glow' => true,
        'fade_animation' => true,
        'bg_light' => true
    ];
    $settings['theme'] = array_merge($defaultTheme, $settings['theme'] ?? []);
    return $settings;
}

function saveSettings($settings) {
    @file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/**
 * 安全取首字
 */
function getFirstChar($str) {
    $str = trim($str);
    if (function_exists('mb_substr')) return mb_substr($str, 0, 1, 'UTF-8');
    return substr($str, 0, 3);
}
?>
