<?php
require_once('config.php');

$result;
$accessToken;
$refreshToken;
$isError = false;

function auth() {
  $baseURL = 'https://mixi.jp/connect_authorize.pl?';
  $params = array(
    'client_id'     => CONSUMER_KEY,
    'response_type' => 'code',
    'scope'         => 'w_voice',
    'display'       => 'touch'
  );
  header('location: ' . $baseURL . http_build_query($params));
}

function isValidImage($format, $size) {
  if ($format != 'image/jpeg') {
    return false;
  }

  if ($size > 1000000) {
    return false;
  }

  return true;
}

function getTokens() {
  if (isset($_GET['code'])) {
    $code = $_GET['code'];
  }

  $params = array(
    'grant_type'    => 'authorization_code',
    'client_id'     => CONSUMER_KEY,
    'client_secret' => CONSUMER_SECRET,
    'code'          => $code,
    'redirect_uri'  => REDIRECT_URI
  );

  $client = curl_init();
  curl_setopt($client, CURLOPT_URL, 'https://secure.mixi-platform.com/2/token');
  curl_setopt($client, CURLOPT_POST, true);
  curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($params));
  curl_setopt($client, CURLOPT_RETURNTRANSFER, true);
  $tokens = json_decode(curl_exec($client), true);
  curl_close($client);
  return $tokens;
}

function main() {
  global $result;
  global $accessToken;

  if (isset($_GET['auth'])) {
    auth();
  }

  if (!isset($_GET['code'])) {
    auth();
  }

  if (!isset($_POST['access_token'])) {
    $tokens = getTokens();
    $refreshToken = $tokens['refresh_token'];
    $accessToken = $tokens['access_token'];
  } else {
    $accessToken = $_POST['access_token'];
  }

  if (!isset($_POST['voice_body']) && !isset($_FILES['voice_image'])) {
    return;
  }

  $params = array();
  $voice_body = '';
  if (isset($_POST['voice_body'])) {
    if (mb_strlen($_POST['voice_body']) <= 150) {
      $voice_body = $_POST['voice_body'];
    }
  }

  $params['status'] = $voice_body;

  if (!$_FILES['voice_image']['error']) {
    $image     = $_FILES['voice_image'];
    $filePath  = $image['tmp_name'];
    $format    = $image['type'];
    $size      = $image['size'];
    $extension = $image['extenstion'];
    $error     = $image['error'];
    if ($error || !isValidImage($format, $size)) {
      return;
    }
    $params['photo'] = '@' . $filePath;
  }

  $client = curl_init();
  curl_setopt($client, CURLOPT_URL, 'https://api.mixi-platform.com/2/voice/statuses/update?oauth_token=' . $accessToken);
  curl_setopt($client, CURLOPT_POST, true);
  curl_setopt($client, CURLOPT_POSTFIELDS, $params);
  curl_setopt($client, CURLOPT_RETURNTRANSFER, true);
  $result = json_decode(curl_exec($client), true);
  $isError = is_null($result);
  curl_close($client);
}

//entry point
main();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja">
  <head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta http-equiv="content-language" content="ja">
    <meta http-equiv="content-script-type" content="text/javascript">
    <meta http-equiv="content-style-type" content="text/css">
    <meta name="viewport" content="width=device-width, user-scalable=no">
    <link rel="stylesheet" type="text/css" href="./style.css">
    <title>つぶやきの森</title>
  </head>
  <body>
    <div id="main">
      <div id="errors" style="<?php if ($isError) {echo "display: block;";} ?>">
      <p>
      認証が切れてしまったかも...。<br><a href="./">再認証</a>してください。
      </p>
      </div>
      <h1>つぶ森</h1>
      <form method="post" action="" enctype="multipart/form-data">
      <p><textarea name="voice_body" maxlength="150" placeholder="つぶやき"></textarea></p>
      <input type="hidden" name="access_token" value="<?php echo htmlspecialchars($accessToken); ?>">
      <p><input type="file" name="voice_image"></p>
      <p id="submitButton"><input type="submit" value="つぶやく"></p>
      <p class="caution">デフォルトの公開範囲につぶやきます</p>
      </form>
    </div>
  </body>
</html>
