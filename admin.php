<?php 
require 'config.php';

// 退出登录
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_login']);
    header('Location: admin.php');
    exit;
}

// 登录验证
$isLogin = isset($_SESSION['admin_login']) && $_SESSION['admin_login'] === true;
if (!$isLogin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === ADMIN_USER && password_verify($_POST['password'], ADMIN_PASS)) {
        $_SESSION['admin_login'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $loginError = '账号或密码错误';
    }
}

if (!$isLogin):
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>后台登录</title>
<style>
  body { font-family: sans-serif; background: #0f172a; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
  .login-box { background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); width: 320px; color: #e2e8f0; }
  h2 { text-align: center; margin-bottom: 20px; font-weight: 600; }
  input { width: 100%; padding: 10px 12px; margin: 8px 0; border: 1px solid #334155; border-radius: 6px; background: #0f172a; color: #e2e8f0; }
  button { width: 100%; padding: 10px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: #fff; border: none; border-radius: 6px; cursor: pointer; margin-top: 10px; }
  .error { color: #f87171; text-align: center; font-size: 14px; margin-top: 8px; }
</style>
</head>
<body>
<div class="login-box">
  <h2>音乐后台登录</h2>
  <?php if (isset($loginError)) echo '<p class="error">'.$loginError.'</p>'; ?>
  <form method="post">
    <input type="text" name="username" placeholder="管理员账号" required>
    <input type="password" name="password" placeholder="管理员密码" required>
    <button type="submit" name="login">登录</button>
  </form>
</div>
</body>
</html>
<?php exit; endif;

$settings = getSettings();
$activeTab = $_GET['tab'] ?? 'local';

// ========== 导出TXT ==========
if (isset($_GET['export'])) {
    $songs = scanLocalAudio();
    $content = "本地音乐列表\n导出时间：" . date('Y-m-d H:i:s') . "\n" . str_repeat("═", 64) . "\n\n";
    
    foreach ($songs as $i => $s) {
        $num = str_pad($i + 1, 3, '0', STR_PAD_LEFT);
        $style = $s['style'] ? (MUSIC_STYLES[$s['style']] ?? '未知') : '未分类';
        $fn = urldecode(substr($s['url'], 6));
        $fp = AUDIO_DIR . '/' . $fn;
        $size = file_exists($fp) ? round(filesize($fp)/1024/1024, 2) . ' MB' : '未知';
        $ext = strtoupper(pathinfo($fp, PATHINFO_EXTENSION));
        
        $content .= "【{$num}】{$s['title']}\n      歌手：{$s['artist']}\n      风格：{$style}\n      格式：{$ext}  大小：{$size}\n\n";
    }
    $content .= str_repeat("═", 64) . "\n总计：" . count($songs) . " 首\n";
    
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="本地音乐列表_' . date('Ymd') . '.txt"');
    echo $content;
    exit;
}

// ========== 删除本地歌曲 ==========
if (isset($_GET['del_local'])) {
    $fn = basename($_GET['del_local']);
    $fp = AUDIO_DIR . '/' . $fn;
    if (file_exists($fp) && is_file($fp)) {
        @unlink($fp);
        $all = getSongs();
        $all = array_values(array_filter($all, function($s) use ($fn) {
            return $s['url'] !== 'audio/' . rawurlencode($fn);
        }));
        saveSongs($all);
    }
    header('Location: admin.php?tab=local');
    exit;
}

// ========== 保存单首歌曲风格 ==========
if (isset($_POST['save_style'])) {
    $url = trim($_POST['url']);
    $style = trim($_POST['style']);
    $title = trim($_POST['title']);
    
    $all = getSongs();
    $found = false;
    
    foreach ($all as $k => $s) {
        if ($s['url'] === $url) {
            $all[$k]['style'] = $style;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $all[] = [
            'id' => 'local_' . time(),
            'title' => $title,
            'artist' => '本地音乐',
            'cover' => '',
            'url' => $url,
            'sort' => 100,
            'status' => 1,
            'style' => $style
        ];
    }
    
    saveSongs($all);
    header('Location: admin.php?tab=local');
    exit;
}

// ========== 保存主题设置 ==========
if (isset($_POST['save_settings'])) {
    saveSettings([
        'site' => [
            'name' => trim($_POST['site_name']),
            'desc' => trim($_POST['site_desc']),
            'since' => trim($_POST['site_since'])
        ],
        'theme' => [
            'primary_color' => trim($_POST['primary_color']),
            'secondary_color' => trim($_POST['secondary_color']),
            'bg_mode' => trim($_POST['bg_mode']),
            'bg_image_url' => trim($_POST['bg_image_url']),
            'bg_mask_opacity' => floatval($_POST['bg_mask_opacity']),
            'glass_effect' => isset($_POST['glass_effect']),
            'hover_glow' => isset($_POST['hover_glow']),
            'fade_animation' => isset($_POST['fade_animation']),
            'bg_light' => isset($_POST['bg_light'])
        ]
    ]);
    header('Location: admin.php?tab=theme');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>音乐后台管理</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #0f172a; padding: 20px; color: #e2e8f0; }
  .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  .header a { color: #94a3b8; text-decoration: none; margin-left: 15px; transition: color 0.2s; }
  .header a:hover { color: #3b82f6; }
  .tabs { display: flex; gap: 2px; margin-bottom: 20px; border-bottom: 2px solid #1e293b; }
  .tab { padding: 12px 24px; border-radius: 8px 8px 0 0; color: #94a3b8; text-decoration: none; transition: all 0.2s; }
  .tab.active { background: #1e293b; color: #3b82f6; font-weight: 600; }
  .card { background: #1e293b; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 12px; text-align: left; border-bottom: 1px solid #334155; }
  th { background: #0f172a; font-size: 14px; color: #94a3b8; font-weight: 500; }
  .btn { padding: 6px 14px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 13px; border: none; }
  .btn-danger { background: rgba(248,113,113,0.15); color: #f87171; }
  .btn-gray { background: #334155; color: #cbd5e1; }
  .tip { font-size: 12px; color: #64748b; margin-top: 4px; }
  code { background: #0f172a; padding: 2px 6px; border-radius: 4px; color: #3b82f6; }
  .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }
  .cover-thumb { width: 36px; height: 36px; border-radius: 6px; background: linear-gradient(135deg,#3b82f6,#8b5cf6); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; font-weight: 600; overflow: hidden; }
  .cover-thumb img { width: 100%; height: 100%; object-fit: cover; }
  .style-select { padding: 4px 8px; border-radius: 4px; border: 1px solid #334155; background: #0f172a; color: #e2e8f0; font-size: 12px; outline: none; }
  .style-form { display: flex; gap: 6px; align-items: center; }
</style>
</head>
<body>
<div class="header">
  <h2>音乐后台管理系统</h2>
  <div>
    <a href="index.php" target="_blank">查看前台</a>
    <a href="?logout">退出登录</a>
  </div>
</div>

<div class="tabs">
  <a href="?tab=local" class="tab <?= $activeTab == 'local' ? 'active' : '' ?>">本地音乐管理</a>
  <a href="?tab=theme" class="tab <?= $activeTab == 'theme' ? 'active' : '' ?>">主题设置</a>
</div>

<?php if ($activeTab == 'local'): ?>
<div class="card">
  <div class="toolbar">
    <div>
      <h3>本地音乐库</h3>
      <p class="tip">音频文件放 <code>audio/</code> 目录；同名图片自动识别为封面；可手动设置歌曲风格</p>
    </div>
    <a href="?export=txt" class="btn btn-gray">📄 导出TXT列表</a>
  </div>
  
  <table>
    <thead>
      <tr>
        <th>封面</th>
        <th>歌曲名称</th>
        <th>格式</th>
        <th>大小</th>
        <th>风格</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $localSongs = scanLocalAudio();
      if (empty($localSongs)):
      ?>
      <tr>
        <td colspan="6" style="text-align:center; color:#64748b; padding:40px;">
          暂无本地音乐，请将音频文件上传到 <code>audio/</code> 目录
        </td>
      </tr>
      <?php else: foreach ($localSongs as $song):
        $fn = urldecode(substr($song['url'], 6));
        $fp = AUDIO_DIR . '/' . $fn;
        $size = file_exists($fp) ? round(filesize($fp)/1024/1024, 2) . ' MB' : '未知';
        $ext = strtoupper(pathinfo($fp, PATHINFO_EXTENSION));
        $cover = $song['cover'] ?? '';
        $style = $song['style'] ?? '';
      ?>
      <tr>
        <td>
          <div class="cover-thumb">
            <?php if (!empty($cover)): ?>
            <img src="<?= htmlspecialchars($cover) ?>" alt="">
            <?php else: ?>
            <?= getFirstChar($song['title']) ?>
            <?php endif; ?>
          </div>
        </td>
        <td><?= htmlspecialchars($song['title']) ?></td>
        <td><?= $ext ?></td>
        <td><?= $size ?></td>
        <td>
          <form method="post" class="style-form">
            <input type="hidden" name="url" value="<?= htmlspecialchars($song['url']) ?>">
            <input type="hidden" name="title" value="<?= htmlspecialchars($song['title']) ?>">
            <select name="style" class="style-select">
              <option value="">未分类</option>
              <?php foreach (MUSIC_STYLES as $key => $name): ?>
              <option value="<?= $key ?>" <?= $style === $key ? 'selected' : '' ?>><?= $name ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" name="save_style" class="btn btn-gray" style="padding:4px 10px; font-size:12px;">保存</button>
          </form>
        </td>
        <td>
          <a href="?del_local=<?= urlencode($fn) ?>&tab=local" class="btn btn-danger" onclick="return confirm('确定删除该文件吗？删除后无法恢复！')">删除</a>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php elseif ($activeTab == 'theme'): ?>
<div class="card">
  <form method="post">
    <h3 style="margin-bottom:16px; font-size:16px;">站点基础信息</h3>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
      <div>
        <label style="display:block; margin-bottom:4px; font-size:14px; color:#cbd5e1;">页面标题</label>
        <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site']['name']) ?>" required>
      </div>
      <div>
        <label style="display:block; margin-bottom:4px; font-size:14px; color:#cbd5e1;">页面副标题</label>
        <input type="text" name="site_desc" value="<?= htmlspecialchars($settings['site']['desc']) ?>">
      </div>
    </div>
    
    <h3 style="margin:24px 0 12px; font-size:16px; padding-bottom:8px; border-bottom:1px solid #334155;">主题配色</h3>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
      <div>
        <label style="display:block; margin-bottom:4px; font-size:14px; color:#cbd5e1;">主色调</label>
        <div style="display:flex; gap:10px; align-items:center;">
          <input type="color" name="primary_color" value="<?= htmlspecialchars($settings['theme']['primary_color']) ?>">
          <span><?= htmlspecialchars($settings['theme']['primary_color']) ?></span>
        </div>
      </div>
      <div>
        <label style="display:block; margin-bottom:4px; font-size:14px; color:#cbd5e1;">辅助色</label>
        <div style="display:flex; gap:10px; align-items:center;">
          <input type="color" name="secondary_color" value="<?= htmlspecialchars($settings['theme']['secondary_color']) ?>">
          <span><?= htmlspecialchars($settings['theme']['secondary_color']) ?></span>
        </div>
      </div>
    </div>
    
    <h3 style="margin:24px 0 12px; font-size:16px; padding-bottom:8px; border-bottom:1px solid #334155;">背景设置</h3>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
      <div>
        <label style="display:block; margin-bottom:4px; font-size:14px; color:#cbd5e1;">背景模式</label>
        <select name="bg_mode" id="bgMode">
          <option value="dark" <?= $settings['theme']['bg_mode'] == 'dark' ? 'selected' : '' ?>>深色模式</option>
          <option value="glow" <?= $settings['theme']['bg_mode'] == 'glow' ? 'selected' : '' ?>>柔光渐变</option>
          <option value="white" <?= $settings['theme']['bg_mode'] == 'white' ? 'selected' : '' ?>>极简纯白</option>
          <option value="solid" <?= $settings['theme']['bg_mode'] == 'solid' ? 'selected' : '' ?>>纯色浅灰</option>
          <option value="image" <?= $settings['theme']['bg_mode'] == 'image' ? 'selected' : '' ?>>自定义图片</option>
        </select>
      </div>
      <div>
        <label style="display:block; margin-bottom:4px; font-size:14px; color:#cbd5e1;">遮罩透明度</label>
        <input type="number" step="0.05" min="0" max="1" name="bg_mask_opacity" value="<?= $settings['theme']['bg_mask_opacity'] ?>">
      </div>
    </div>
    
    <div id="bgImageBox" style="margin-top:12px;">
      <label style="display:block; margin-bottom:4px; font-size:14px; color:#cbd5e1;">背景图片地址</label>
      <input type="text" name="bg_image_url" value="<?= htmlspecialchars($settings['theme']['bg_image_url']) ?>">
      <p class="tip">仅「自定义图片」模式生效</p>
    </div>
    
    <h3 style="margin:24px 0 12px; font-size:16px; padding-bottom:8px; border-bottom:1px solid #334155;">特效开关</h3>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
      <div style="display:flex; align-items:center; gap:10px;">
        <input type="checkbox" name="glass_effect" id="glass" <?= $settings['theme']['glass_effect'] ? 'checked' : '' ?>>
        <label for="glass" style="margin:0; font-size:14px;">启用玻璃拟态效果</label>
      </div>
      <div style="display:flex; align-items:center; gap:10px;">
        <input type="checkbox" name="fade_animation" id="fade" <?= $settings['theme']['fade_animation'] ? 'checked' : '' ?>>
        <label for="fade" style="margin:0; font-size:14px;">入场淡入动画</label>
      </div>
      <div style="display:flex; align-items:center; gap:10px;">
        <input type="checkbox" name="bg_light" id="bglight" <?= $settings['theme']['bg_light'] ? 'checked' : '' ?>>
        <label for="bglight" style="margin:0; font-size:14px;">背景流动光斑</label>
      </div>
    </div>
    
    <button type="submit" name="save_settings" class="btn btn-gray" style="margin-top:24px; background:linear-gradient(135deg,#3b82f6,#8b5cf6); color:#fff; border:none;">保存所有设置</button>
  </form>
</div>

<script>
  const bgMode = document.getElementById('bgMode');
  const bgImageBox = document.getElementById('bgImageBox');
  bgImageBox.style.display = bgMode.value === 'image' ? 'block' : 'none';
  bgMode.addEventListener('change', () => {
    bgImageBox.style.display = bgMode.value === 'image' ? 'block' : 'none';
  });
</script>
<?php endif; ?>
</body>
</html>
