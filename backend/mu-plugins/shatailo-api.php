<?php
/**
 * Plugin Name: Shatailo Headless API
 * Description: Логін (email+пароль) + дані кабінету (профіль, замовлення, куплені сольники + Vimeo)
 *              для нового фронту на new.shatailo.com. Тільки ЧИТАННЯ даних. Токен — HMAC (секрет = wp_salt).
 *              Google-логін додамо окремим роутом пізніше.
 * Version: 0.1
 * Author: Shatailo new frontend
 *
 * УСТАНОВКА: покласти цей файл у wp-content/mu-plugins/  (створити папку, якщо немає).
 *            mu-plugins активуються самі. Щоб прибрати — просто видалити файл.
 */

if (!defined('ABSPATH')) exit;

/* ---- Дозволені джерела (CORS) ---- */
function shatailo_allowed_origins() {
  return array(
    'https://new.shatailo.com',
    'https://shatailo-standup.vercel.app',
    'http://localhost:4173',
  );
}

/* ---- Каталог сольників: plan_id => дані (product, Vimeo, метадані). Звірено з БД. ---- */
function shatailo_solnyky() {
  return array(
    583   => array('slug'=>'tymchasovi', 'title'=>'Тимчасові незручності', 'product'=>67,    'vimeo'=>'665655524',  'info'=>'2021 · перший сольник'),
    3921  => array('slug'=>'sekunda',    'title'=>'Секунда Бабака',        'product'=>3922,  'vimeo'=>'854094541',  'info'=>'2023 · другий сольник'),
    20841 => array('slug'=>'povitryane', 'title'=>'Повітряне Зашибісь',    'product'=>20812, 'vimeo'=>'1018752390', 'info'=>'2024 · третій сольник'),
    39077 => array('slug'=>'kosmichnyi', 'title'=>'Космічний А#уй',        'product'=>39066, 'vimeo'=>'1195600267', 'info'=>'2026 · четвертий сольник'),
  );
}

/* ---- Google-логін: налаштування ----
   1) client_id — вставити OAuth Client ID (…apps.googleusercontent.com). Поки '' — роут вимкнено (503).
   2) autocreate — політика для входу з Google без існуючого акаунта:
      false = ВІДМОВИТИ (варіант A, рекомендовано); true = СТВОРИТИ клієнта (варіант B). */
function shatailo_google_client_id() {
  return '311300247177-t7faj7frsr2pfcgva2cisa0mikgpf9jk.apps.googleusercontent.com';
}
function shatailo_google_autocreate() {
  return false;
}

/* ---- Передача кошика з нового фронту (Варіант B) ----
   Наш фронт веде на shatailo.com/?shatailo_add=39066,20812 → очищаємо Woo-кошик,
   додаємо ці товари й ведемо на checkout. Кошик/міні-кошик лишаються в нашому
   дизайні на new.shatailo.com, а рідна оплата WayForPay не змінюється. */
add_action('template_redirect', function () {
  if (empty($_GET['shatailo_add'])) return;
  if (!function_exists('WC') || is_null(WC()->cart)) return;
  $ids = array_filter(array_map('intval', explode(',', (string) $_GET['shatailo_add'])));
  if (!$ids) { wp_safe_redirect(function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/')); exit; }
  WC()->cart->empty_cart();
  foreach ($ids as $pid) {
    $product = wc_get_product($pid);
    if ($product && $product->is_purchasable() && $product->is_in_stock()) {
      WC()->cart->add_to_cart($pid);
    }
  }
  wp_safe_redirect(wc_get_checkout_url());
  exit;
});

/* ---- CORS для REST ---- */
add_action('rest_api_init', function () {
  add_filter('rest_pre_serve_request', function ($served) {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    if (in_array($origin, shatailo_allowed_origins(), true)) {
      header('Access-Control-Allow-Origin: ' . $origin);
      header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
      header('Access-Control-Allow-Headers: Authorization, Content-Type');
      header('Vary: Origin');
    }
    return $served;
  });
}, 15);

/* ---- Токен (HMAC, секрет = wp_salt('auth'), 14 днів) ---- */
function shatailo_secret() { return wp_salt('auth'); }

function shatailo_make_token($uid) {
  $payload = $uid . '.' . (time() + 14 * DAY_IN_SECONDS);
  $sig = hash_hmac('sha256', $payload, shatailo_secret());
  return rtrim(strtr(base64_encode($payload . '|' . $sig), '+/', '-_'), '=');
}

function shatailo_uid_from_token($token) {
  $raw = base64_decode(strtr($token, '-_', '+/'));
  if (!$raw || strpos($raw, '|') === false) return 0;
  list($payload, $sig) = explode('|', $raw, 2);
  $expected = hash_hmac('sha256', $payload, shatailo_secret());
  if (!hash_equals($expected, $sig)) return 0;
  $parts = explode('.', $payload, 2);
  $uid = isset($parts[0]) ? (int)$parts[0] : 0;
  $exp = isset($parts[1]) ? (int)$parts[1] : 0;
  if ($uid < 1 || $exp < time()) return 0;
  return $uid;
}

function shatailo_current_uid() {
  $hdr = '';
  if (isset($_SERVER['HTTP_AUTHORIZATION'])) $hdr = $_SERVER['HTTP_AUTHORIZATION'];
  elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $hdr = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
  elseif (function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) { if (strtolower($k) === 'authorization') { $hdr = $v; break; } }
  }
  if (stripos($hdr, 'Bearer ') === 0) return shatailo_uid_from_token(trim(substr($hdr, 7)));
  return 0;
}

/* ---- Публічні дані користувача ---- */
function shatailo_user_public($uid) {
  $u = get_userdata($uid);
  if (!$u) return null;
  $first = get_user_meta($uid, 'first_name', true);
  $last  = get_user_meta($uid, 'last_name', true);
  $name  = trim($first . ' ' . $last);
  return array(
    'id'          => $uid,
    'email'       => $u->user_email,
    'firstName'   => $first,
    'lastName'    => $last,
    'displayName' => $name !== '' ? $name : $u->display_name,
  );
}

/* ---- Замовлення користувача ---- */
function shatailo_orders($uid) {
  if (!function_exists('wc_get_orders')) return array();
  $orders = wc_get_orders(array('customer_id' => $uid, 'limit' => 50, 'orderby' => 'date', 'order' => 'DESC'));
  $out = array();
  foreach ($orders as $o) {
    $items = array();
    foreach ($o->get_items() as $it) {
      $items[] = array('title' => $it->get_name(), 'qty' => $it->get_quantity());
    }
    $date = $o->get_date_created();
    $out[] = array(
      'num'    => $o->get_order_number(),
      'date'   => $date ? $date->date('Y-m-d') : '',
      'total'  => (float) $o->get_total(),
      'status' => function_exists('wc_get_order_status_name') ? wc_get_order_status_name($o->get_status()) : $o->get_status(),
      'items'  => $items,
    );
  }
  return $out;
}

/* ---- Бібліотека: сольники за фактом покупки ----
   Політика «купив = дивишся необмежено»: враховуємо ВСІ членства КРІМ скасованих
   (wcm-cancelled = повернення коштів). Прострочені (wcm-expired) — легасі, доступ лишаємо. */
function shatailo_library($uid) {
  $lib = array();
  $plans = shatailo_solnyky();
  $q = new WP_Query(array(
    'post_type'      => 'wc_user_membership',
    'author'         => $uid,
    'post_status'    => array('wcm-active', 'wcm-expired', 'wcm-paused', 'wcm-pending', 'wcm-delayed', 'wcm-complimentary', 'wcm-free_trial'),
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
  ));
  $seen = array();
  foreach ($q->posts as $mid) {
    $plan_id = (int) get_post_field('post_parent', $mid);
    if (!isset($plans[$plan_id]) || isset($seen[$plan_id])) continue;
    $seen[$plan_id] = true;
    $s = $plans[$plan_id];
    $lib[] = array('slug' => $s['slug'], 'title' => $s['title'], 'info' => $s['info'], 'vimeo' => $s['vimeo']);
  }
  return $lib;
}

/* ---- Проста заслінка від брутфорсу логіну ---- */
function shatailo_login_blocked($ip, $bucket = 'lf') {
  return ((int) get_transient('shatailo_' . $bucket . '_' . md5($ip))) >= 8;
}
function shatailo_login_fail($ip, $bucket = 'lf') {
  $k = 'shatailo_' . $bucket . '_' . md5($ip);
  set_transient($k, ((int) get_transient($k)) + 1, 15 * MINUTE_IN_SECONDS);
}

/* ---- Роути ---- */
add_action('rest_api_init', function () {
  register_rest_route('shatailo/v1', '/login', array(
    'methods' => 'POST', 'callback' => 'shatailo_route_login', 'permission_callback' => '__return_true',
  ));
  register_rest_route('shatailo/v1', '/me', array(
    'methods' => 'GET', 'callback' => 'shatailo_route_me', 'permission_callback' => '__return_true',
  ));
  register_rest_route('shatailo/v1', '/google-login', array(
    'methods' => 'POST', 'callback' => 'shatailo_route_google_login', 'permission_callback' => '__return_true',
  ));
});

function shatailo_route_login($req) {
  $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'x';
  if (shatailo_login_blocked($ip)) {
    return new WP_Error('too_many', 'Забагато спроб. Спробуйте за 15 хв.', array('status' => 429));
  }
  $email = sanitize_email((string) $req->get_param('email'));
  $pass  = (string) $req->get_param('password');
  if (!$email || !$pass) {
    return new WP_Error('bad_request', 'Вкажіть email і пароль.', array('status' => 400));
  }
  $user = get_user_by('email', $email);
  if (!$user || !wp_check_password($pass, $user->user_pass, $user->ID)) {
    shatailo_login_fail($ip);
    return new WP_Error('auth_failed', 'Невірний email або пароль.', array('status' => 401));
  }
  delete_transient('shatailo_lf_' . md5($ip));
  return array(
    'token' => shatailo_make_token($user->ID),
    'user'  => shatailo_user_public($user->ID),
  );
}

function shatailo_route_me($req) {
  $uid = shatailo_current_uid();
  if (!$uid) return new WP_Error('unauthorized', 'Не авторизовано.', array('status' => 401));
  return array(
    'user'    => shatailo_user_public($uid),
    'orders'  => shatailo_orders($uid),
    'library' => shatailo_library($uid),
  );
}

/* ---- Google-логін ----
   Фронт (GIS) присилає ID-token у полі `credential`. Валідуємо через офіційний
   ендпоінт Google tokeninfo (без сторонніх бібліотек — для нашого обсігу достатньо),
   перевіряємо aud/iss/exp/email_verified, далі шукаємо WP-користувача за email. */
/* Верифікація Google-токена → email/verified/профіль.
   Основний шлях — access_token (кастомна кнопка, OAuth2-попап): перевіряємо аудиторію
   (azp/aud == наш client_id, захист від чужих токенів), email беремо з tokeninfo або userinfo.
   Запасний шлях — credential (ID-token): перевіряємо aud/iss/exp. */
function shatailo_google_verify($access, $cred = '') {
  $client_id = shatailo_google_client_id();
  $access = (string) $access;
  $cred   = (string) $cred;

  if ($access !== '') {
    $resp = wp_remote_get('https://oauth2.googleapis.com/tokeninfo?access_token=' . rawurlencode($access), array('timeout' => 10));
    if (is_wp_error($resp)) return new WP_Error('google_unreachable', 'Не вдалося звʼязатися з Google.', array('status' => 502));
    $info = json_decode(wp_remote_retrieve_body($resp), true);
    if ((int) wp_remote_retrieve_response_code($resp) !== 200 || !is_array($info) || isset($info['error']) || isset($info['error_description'])) {
      return new WP_Error('bad_token', 'Недійсний токен Google.', array('status' => 401));
    }
    $azp = isset($info['azp']) ? (string) $info['azp'] : '';
    $aud = isset($info['aud']) ? (string) $info['aud'] : '';
    if (!hash_equals($client_id, $azp) && !hash_equals($client_id, $aud)) {
      return new WP_Error('bad_aud', 'Токен видано для іншого застосунку.', array('status' => 401));
    }
    $exp = isset($info['exp']) ? (int) $info['exp'] : 0;
    if ($exp && $exp < time()) return new WP_Error('token_expired', 'Токен Google протермінований.', array('status' => 401));

    $email    = isset($info['email']) ? sanitize_email($info['email']) : '';
    $verified = isset($info['email_verified']) && ($info['email_verified'] === true || $info['email_verified'] === 'true');
    $profile  = $info;
    if ($email === '') { // tokeninfo не завжди повертає email/імена → добираємо через userinfo
      $ur = wp_remote_get('https://www.googleapis.com/oauth2/v3/userinfo', array(
        'timeout' => 10, 'headers' => array('Authorization' => 'Bearer ' . $access),
      ));
      if (!is_wp_error($ur) && (int) wp_remote_retrieve_response_code($ur) === 200) {
        $ui = json_decode(wp_remote_retrieve_body($ur), true);
        if (is_array($ui)) {
          $email    = isset($ui['email']) ? sanitize_email($ui['email']) : '';
          $verified = isset($ui['email_verified']) && ($ui['email_verified'] === true || $ui['email_verified'] === 'true');
          $profile  = array_merge($profile, $ui);
        }
      }
    }
    return array('email' => $email, 'verified' => $verified, 'profile' => $profile);
  }

  if ($cred !== '') {
    $resp = wp_remote_get('https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($cred), array('timeout' => 10));
    if (is_wp_error($resp)) return new WP_Error('google_unreachable', 'Не вдалося звʼязатися з Google.', array('status' => 502));
    $info = json_decode(wp_remote_retrieve_body($resp), true);
    if ((int) wp_remote_retrieve_response_code($resp) !== 200 || !is_array($info) || isset($info['error'])) {
      return new WP_Error('bad_token', 'Недійсний токен Google.', array('status' => 401));
    }
    $aud = isset($info['aud']) ? (string) $info['aud'] : '';
    $iss = isset($info['iss']) ? (string) $info['iss'] : '';
    $exp = isset($info['exp']) ? (int) $info['exp'] : 0;
    if (!hash_equals($client_id, $aud)) return new WP_Error('bad_aud', 'Токен видано для іншого застосунку.', array('status' => 401));
    if ($iss !== 'accounts.google.com' && $iss !== 'https://accounts.google.com') return new WP_Error('bad_iss', 'Невірний видавець токена.', array('status' => 401));
    if ($exp < time()) return new WP_Error('token_expired', 'Токен Google протермінований.', array('status' => 401));
    return array(
      'email'    => isset($info['email']) ? sanitize_email($info['email']) : '',
      'verified' => isset($info['email_verified']) && ($info['email_verified'] === true || $info['email_verified'] === 'true'),
      'profile'  => $info,
    );
  }

  return new WP_Error('bad_request', 'Немає токена Google.', array('status' => 400));
}

function shatailo_route_google_login($req) {
  $client_id = shatailo_google_client_id();
  if (!$client_id) return new WP_Error('not_configured', 'Google-логін ще не налаштовано.', array('status' => 503));

  $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'x';
  if (shatailo_login_blocked($ip, 'glf')) {
    return new WP_Error('too_many', 'Забагато спроб. Спробуйте за 15 хв.', array('status' => 429));
  }

  $v = shatailo_google_verify((string) $req->get_param('access_token'), (string) $req->get_param('credential'));
  if (is_wp_error($v)) {
    if (in_array($v->get_error_code(), array('bad_token', 'bad_aud', 'bad_iss', 'token_expired'), true)) shatailo_login_fail($ip, 'glf');
    return $v;
  }
  $email = $v['email']; $verified = $v['verified']; $info = $v['profile'];
  if (!$email || !$verified) return new WP_Error('no_email', 'Google не підтвердив email.', array('status' => 401));

  $user = get_user_by('email', $email);
  if (!$user) {
    if (!shatailo_google_autocreate()) {
      return new WP_Error('no_account', 'Акаунт із цим email не знайдено. Увійдіть поштою, якою купували.', array('status' => 404));
    }
    $uid = wp_insert_user(array(
      'user_login'   => $email,
      'user_email'   => $email,
      'user_pass'    => wp_generate_password(24, true, true),
      'first_name'   => isset($info['given_name']) ? sanitize_text_field($info['given_name']) : '',
      'last_name'    => isset($info['family_name']) ? sanitize_text_field($info['family_name']) : '',
      'display_name' => isset($info['name']) ? sanitize_text_field($info['name']) : $email,
      'role'         => 'customer',
    ));
    if (is_wp_error($uid)) return new WP_Error('create_failed', 'Не вдалося створити акаунт.', array('status' => 500));
    $user = get_user_by('id', $uid);
  }

  delete_transient('shatailo_glf_' . md5($ip));
  return array(
    'token' => shatailo_make_token($user->ID),
    'user'  => shatailo_user_public($user->ID),
  );
}

/* ============================================================
   Google на Woo-CHECKOUT — Варіант B: акаунт ЛИШЕ після успішної оплати.
   Кнопка «Продовжити з Google» ПІДТЯГУЄ ім'я+email у форму (НЕ створює акаунт,
   НЕ логінить). Сесію позначаємо як Google. Акаунт створюється в момент
   УСПІШНОЇ ОПЛАТИ з даних замовлення; для Google — БЕЗ листа про пароль
   (вхід через Google). Ручний шлях Woo (ім'я+email+пароль) — окремо, як є.
   Оплату WayForPay не чіпаємо.
   ============================================================ */

/* кнопка над формою checkout — лише гостю */
add_action('woocommerce_before_checkout_form', function () {
  if (is_user_logged_in() || !shatailo_google_client_id()) return;
  $nonce = esc_attr(wp_create_nonce('shatailo_google_prefill'));
  echo <<<HTML
<div class="shatailo-cg" data-nonce="{$nonce}">
  <p class="shatailo-cg__lead">Купуєте вперше? Заповніть дані одним кліком:</p>
  <button type="button" class="shatailo-cg__btn" id="shatailoCgBtn"><span class="shatailo-cg__g">G</span>&nbsp;Продовжити з Google</button>
  <p class="shatailo-cg__status" id="shatailoCgStatus"></p>
  <div class="shatailo-cg__or"><span>або заповніть вручну нижче</span></div>
</div>
<style>
.shatailo-cg { margin: 0 0 30px; }
.shatailo-cg__lead { color:#f4f2ec; font-family:"Unbounded",sans-serif; font-size:.9rem; text-transform:uppercase; margin:0 0 14px; }
.shatailo-cg__btn { display:inline-flex; align-items:center; justify-content:center; gap:10px; background:#f4f2ec; color:#0a0a0a; border:1px solid #f4f2ec; border-radius:2px; font-family:"Unbounded",sans-serif; font-weight:600; font-size:.82rem; letter-spacing:.06em; text-transform:uppercase; padding:16px 28px; cursor:pointer; white-space:nowrap; transition:background .25s, border-color .25s, box-shadow .25s; }
.shatailo-cg__btn:hover { background:#f2ff00; border-color:#f2ff00; box-shadow:0 0 32px rgba(242,255,0,.35); }
.shatailo-cg__g { font-family:"Unbounded",sans-serif; font-weight:800; color:#0a0a0a; }
.shatailo-cg__status { min-height:1.1em; margin:10px 0 0; font-size:.85rem; color:#f2ff00; }
.shatailo-cg__status.is-err { color:#ff5555; }
.shatailo-cg__or { display:flex; align-items:center; gap:14px; margin:22px 0 0; color:#8d8d86; font-size:.76rem; text-transform:uppercase; letter-spacing:.04em; }
.shatailo-cg__or::before, .shatailo-cg__or::after { content:""; flex:1; height:1px; background:rgba(255,255,255,.12); }
</style>
HTML;
});

/* перенести Woo-форму «Вже замовляли у нас?» на початок блоку «Платіжні дані»
   (за замовчуванням вона над формою — логічніше згрупувати з платіжними даними) */
add_action('wp', function () {
  if (!function_exists('is_checkout') || !is_checkout()) return;
  remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10);
  add_action('woocommerce_before_checkout_billing_form', 'woocommerce_checkout_login_form', 5);
});

/* GIS + JS на checkout: Google → підтягнути поля (без входу) */
add_action('wp_enqueue_scripts', function () {
  if (!function_exists('is_checkout') || !is_checkout() || is_user_logged_in()) return;
  if (!shatailo_google_client_id()) return;
  wp_enqueue_script('gsi-client', 'https://accounts.google.com/gsi/client', array(), null, true);
  $cid = wp_json_encode(shatailo_google_client_id());
  $url = wp_json_encode(add_query_arg('wc-ajax', 'shatailo_google_prefill', home_url('/')));
  $js = <<<JS
window.addEventListener("load", function () {
  var box = document.querySelector(".shatailo-cg");
  var btn = document.getElementById("shatailoCgBtn");
  var st  = document.getElementById("shatailoCgStatus");
  if (!box || !btn || !st) return;
  if (!(window.google && google.accounts && google.accounts.oauth2)) return;
  var nonce = box.getAttribute("data-nonce");
  function fill(id, val) { var el = document.getElementById(id); if (el && val) { el.value = val; el.dispatchEvent(new Event("change", { bubbles: true })); } }
  var tc = google.accounts.oauth2.initTokenClient({
    client_id: {$cid}, scope: "openid email profile",
    callback: function (r) {
      if (!r || !r.access_token) { st.textContent = "Скасовано."; st.className = "shatailo-cg__status is-err"; return; }
      st.textContent = "Підтягуємо дані…"; st.className = "shatailo-cg__status";
      var fd = new FormData();
      fd.append("access_token", r.access_token);
      fd.append("nonce", nonce);
      fetch({$url}, { method: "POST", body: fd, credentials: "same-origin" })
        .then(function (x) { return x.json(); })
        .then(function (j) {
          if (j && j.success && j.data && j.data.email) {
            fill("billing_email", j.data.email);
            fill("billing_first_name", j.data.first);
            fill("billing_last_name", j.data.last);
            st.textContent = "✓ Дані підтягнуто. Завершіть замовлення нижче.";
            st.className = "shatailo-cg__status";
          } else {
            st.textContent = (j && j.data && j.data.message) || "Не вдалося.";
            st.className = "shatailo-cg__status is-err";
          }
        })
        .catch(function () { st.textContent = "Помилка мережі. Спробуйте ще."; st.className = "shatailo-cg__status is-err"; });
    }
  });
  btn.addEventListener("click", function () { st.textContent = "Відкриваємо Google…"; st.className = "shatailo-cg__status"; tc.requestAccessToken(); });
});
JS;
  wp_add_inline_script('gsi-client', $js);
});

/* wc-ajax (є WC-сесія): перевірити Google-токен → повернути профіль + позначити сесію як Google */
add_action('wc_ajax_shatailo_google_prefill', 'shatailo_google_prefill');
function shatailo_google_prefill() {
  if (!wp_verify_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '', 'shatailo_google_prefill')) {
    wp_send_json_error(array('message' => 'Оновіть сторінку.'), 400);
  }
  if (!shatailo_google_client_id()) wp_send_json_error(array('message' => 'Не налаштовано.'), 503);
  $access = isset($_POST['access_token']) ? (string) $_POST['access_token'] : '';
  if (!$access) wp_send_json_error(array('message' => 'Немає токена.'), 400);
  $v = shatailo_google_verify($access, '');
  if (is_wp_error($v)) wp_send_json_error(array('message' => $v->get_error_message()), 401);
  if (empty($v['email']) || empty($v['verified'])) wp_send_json_error(array('message' => 'Google не підтвердив email.'), 401);
  $info  = $v['profile'];
  $email = $v['email'];
  if (WC()->session) WC()->session->set('shatailo_g_email', strtolower($email));
  wp_send_json_success(array(
    'email' => $email,
    'first' => isset($info['given_name']) ? sanitize_text_field($info['given_name']) : '',
    'last'  => isset($info['family_name']) ? sanitize_text_field($info['family_name']) : '',
  ));
}

/* позначити замовлення як Google, якщо email збігається з підтвердженим у сесії */
add_action('woocommerce_checkout_create_order', function ($order, $data) {
  if (!WC()->session) return;
  $g = WC()->session->get('shatailo_g_email');
  if ($g && strtolower($order->get_billing_email()) === $g) {
    $order->update_meta_data('_shatailo_google', 1);
  }
}, 10, 2);

/* акаунт створюємо ЛИШЕ після успішної оплати — з даних замовлення */
add_action('woocommerce_payment_complete', 'shatailo_account_on_paid');
add_action('woocommerce_order_status_processing', 'shatailo_account_on_paid');
add_action('woocommerce_order_status_completed', 'shatailo_account_on_paid');
function shatailo_account_on_paid($order_id) {
  $order = wc_get_order($order_id);
  if (!$order || $order->get_customer_id()) return; // вже привʼязано до акаунта
  $email = $order->get_billing_email();
  if (!$email || !function_exists('wc_create_new_customer')) return;

  $existing = get_user_by('email', $email);
  if ($existing) { $order->set_customer_id($existing->ID); $order->save(); return; }

  $is_google = (bool) $order->get_meta('_shatailo_google');
  // для Google-акаунта — без листа «встановіть пароль» (вхід через Google)
  if ($is_google) add_filter('woocommerce_email_enabled_customer_new_account', '__return_false', 999);
  $uid = wc_create_new_customer($email, '', '', array(
    'first_name' => $order->get_billing_first_name(),
    'last_name'  => $order->get_billing_last_name(),
  ));
  if ($is_google) remove_filter('woocommerce_email_enabled_customer_new_account', '__return_false', 999);

  if (is_wp_error($uid)) return;
  $order->set_customer_id($uid);
  $order->save();
  if ($is_google) update_user_meta($uid, '_shatailo_google', 1);
}
