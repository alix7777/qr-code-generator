<?php
// qr-generator.php
// Simple QR code generator page.
// Tries to use endroid/qr-code (composer) if available, otherwise falls back to Google Chart API.

// Helper: safely get param
function param($name, $default = '') {
    return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $default;
}

$text = trim(param('text'));
$size = (int) param('size', 300);
if ($size <= 0) $size = 300;
$ec = strtoupper(param('ec', 'M'));

// If a QR is requested and text provided, output image
if ($text !== '') {
    // Try using endroid/qr-code via Composer autoload
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require $autoload;
        try {
            // Use the Builder if available (endroid/qr-code v4+)
            if (class_exists('\Endroid\QrCode\Builder\Builder')) {
                $builder = \Endroid\QrCode\Builder\Builder::create()
                    ->data($text)
                    ->size($size)
                    ->margin(10)
                    ->build();
                header('Content-Type: ' . $builder->getMimeType());
                echo $builder->getString();
                exit;
            }

            // Fallback to older Endroid usage if class exists
            if (class_exists('\Endroid\QrCode\QrCode')) {
                $qr = new \Endroid\QrCode\QrCode($text);
                if (method_exists($qr, 'setSize')) {
                    $qr->setSize($size);
                    $qr->setMargin(10);
                }
                // Try to write string
                if (method_exists($qr, 'writeString')) {
                    $mime = 'image/png';
                    if (method_exists($qr, 'getContentType')) {
                        $mime = $qr->getContentType();
                    }
                    header('Content-Type: ' . $mime);
                    echo $qr->writeString();
                    exit;
                }
            }
        } catch (Throwable $e) {
            // If endroid fails, we'll fall back to Google Chart API below
        }
    }

    // Fallback: Google Chart API (public image generation)
    $chs = $size . 'x' . $size;
    $chl = urlencode($text);
    $googleUrl = "https://chart.googleapis.com/chart?cht=qr&chs={$chs}&chl={$chl}&choe=UTF-8";

    // Proxy the image content
    $img = @file_get_contents($googleUrl);
    if ($img !== false) {
        header('Content-Type: image/png');
        echo $img;
        exit;
    }

    // Last resort: simple error
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo "Unable to generate QR code.\n";
    exit;
}

// If no text provided, render the HTML form
?><!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>QR-генератор</title>
  <style>
    body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; padding: 2rem; }
    .card { max-width: 720px; margin: 0 auto; background:#fff; padding:1.5rem; border-radius:8px; box-shadow:0 4px 18px rgba(0,0,0,0.06);} 
    label { display:block; margin-top:0.75rem; font-weight:600 }
    input[type=text], textarea, select { width:100%; padding:.5rem; margin-top:.25rem; border:1px solid #ddd; border-radius:6px }
    .row { display:flex; gap:.5rem }
    .row > * { flex:1 }
    button { margin-top:1rem; padding:.6rem 1.1rem; border:none; background:#0366d6; color:#fff; border-radius:6px; cursor:pointer }
    img.qr { display:block; margin-top:1rem; border:1px solid #eee; }
    .note { color:#666; font-size:.9rem; margin-top:.5rem }
  </style>
</head>
<body>
  <div class="card">
    <h1>QR-генератор</h1>
    <form method="get" action="">
      <label for="text">Текст / URL</label>
      <textarea id="text" name="text" rows="3" placeholder="Введите текст или ссылку" required></textarea>

      <div class="row">
        <div>
          <label for="size">Размер (px)</label>
          <input id="size" name="size" type="number" value="300" min="100" max="2000">
        </div>
        <div>
          <label for="ec">Уровень коррекции ошибок</label>
          <select id="ec" name="ec">
            <option value="L">L (низкий)</option>
            <option value="M" selected>M (средний)</option>
            <option value="Q">Q (квартиль)</option>
            <option value="H">H (высокий)</option>
          </select>
        </div>
      </div>

      <button type="submit">Сгенерировать</button>
    </form>

    <div class="note">Примечание: если в проекте установлен пакет endroid/qr-code через Composer (vendor/autoload.php), страница использует его для генерации. Иначе используется публичный Google Chart API для создания изображения.</div>

    <?php if (isset($_GET['text']) && trim($_GET['text']) !== ''): 
       $t = htmlspecialchars($_GET['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
       $s = (int)($_GET['size'] ?? 300);
       $imgUrl = htmlspecialchars(sprintf('%s?text=%s&size=%d&ec=%s', basename(__FILE__), urlencode($_GET['text']), $s, urlencode($_GET['ec'] ?? 'M')));
    ?>
      <h2>Результат</h2>
      <img class="qr" src="<?php echo $imgUrl; ?>" width="<?php echo $s; ?>" height="<?php echo $s; ?>" alt="QR: <?php echo $t; ?>">
      <p class="note">Правой кнопкой -> Сохранить изображение как... для загрузки PNG.</p>
    <?php endif; ?>

  </div>
</body>
</html>
