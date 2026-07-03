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
  register_rest_route('shatailo/v1', '/lost-password', array(
    'methods' => 'POST', 'callback' => 'shatailo_route_lostpassword', 'permission_callback' => '__return_true',
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

/* заголовок «Персональні дані» замість «Платіжні дані» на checkout */
add_action('woocommerce_before_checkout_form', function () {
  add_filter('gettext', 'shatailo_rename_billing_heading', 20, 2);
}, 1);
function shatailo_rename_billing_heading($tr, $text) {
  if ($text === 'Billing details') return 'Персональні дані';
  return $tr;
}

/* стилі розкладки реєстрації гостя (одна колонка, форма обмеженої ширини зліва) */
add_action('wp_head', function () {
  if (!function_exists('is_checkout') || !is_checkout() || is_user_logged_in()) return;
  echo <<<CSS
<style>
/* секція «Персональні дані» — на всю ширину (Woo робить її пів-колонкою) */
body.woocommerce-checkout #customer_details { display:block !important; }
body.woocommerce-checkout #customer_details .col-1 { width:100% !important; max-width:none !important; float:none !important; }
body.woocommerce-checkout #customer_details .col-2 { display:none !important; }
body.woocommerce-checkout .woocommerce-billing-fields > h3 { margin:0 0 26px !important; }
.shatailo-subhead { font-family:"Unbounded",sans-serif; font-weight:400; font-size:.82rem; letter-spacing:.08em; text-transform:uppercase; color:#f4f2ec; margin:0 0 16px; }
.shatailo-loginbox { display:inline-block; max-width:100%; border:1px solid rgba(255,255,255,.12); border-radius:4px; padding:18px 22px; }
.shatailo-loginbox p { margin:0; color:#8d8d86; font-family:"Inter",sans-serif; font-size:.9rem; white-space:nowrap; }
.shatailo-loginbox .showlogin { color:#f2ff00 !important; text-decoration:underline; white-space:nowrap; }
@media (max-width:600px){ .shatailo-loginbox { display:block; } .shatailo-loginbox p { white-space:normal; } }
.shatailo-loginbox .showlogin:hover { color:#f4f2ec !important; }
.shatailo-divider { display:flex; align-items:center; gap:16px; margin:30px 0; color:#8d8d86; font-size:.76rem; text-transform:uppercase; letter-spacing:.06em; }
.shatailo-divider::before, .shatailo-divider::after { content:""; flex:1; height:1px; background:rgba(255,255,255,.12); }
.shatailo-cg { max-width:440px; }
.shatailo-cg__btn { display:inline-flex; align-items:center; justify-content:center; gap:10px; width:100%; background:#f4f2ec !important; color:#0a0a0a !important; border:1px solid #f4f2ec !important; border-radius:2px; font-family:"Unbounded",sans-serif; font-weight:600; font-size:.82rem; letter-spacing:.06em; text-transform:uppercase; padding:16px 28px; cursor:pointer; transition:background .25s,box-shadow .25s; }
.shatailo-cg__btn:hover { background:#f2ff00 !important; color:#0a0a0a !important; box-shadow:0 0 32px rgba(242,255,0,.35); }
.shatailo-cg__btn:hover .shatailo-cg__g { color:#0a0a0a !important; }
.shatailo-cg__g { font-family:"Unbounded",sans-serif; font-weight:800; color:#0a0a0a !important; }
.shatailo-cg__status { min-height:1.1em; margin:8px 0 0; font-size:.85rem; color:#f2ff00; }
.shatailo-cg__status.is-err { color:#ff5555; }
.shatailo-or { display:flex; align-items:center; gap:14px; max-width:440px; margin:16px 0; color:#8d8d86; font-size:.76rem; }
.shatailo-or::before, .shatailo-or::after { content:""; flex:1; height:1px; background:rgba(255,255,255,.12); }
body.woocommerce-checkout .woocommerce-billing-fields .form-row,
body.woocommerce-checkout .woocommerce-account-fields .form-row { max-width:440px; width:100% !important; float:none !important; clear:none !important; margin:0 0 18px !important; }
body.woocommerce-checkout form.woocommerce-form-login, body.woocommerce-checkout .woocommerce-form-login-toggle { display:none !important; }
body.woocommerce-checkout #order_review_heading { margin-top:56px !important; padding-top:44px !important; border-top:1px solid rgba(255,255,255,.1) !important; }
</style>
CSS;
});

/* перенести Woo-форму «Вже замовляли у нас?» на початок блоку «Платіжні дані»
   (за замовчуванням вона над формою — логічніше згрупувати з платіжними даними) */
add_action('wp', function () {
  if (!function_exists('is_checkout') || !is_checkout()) return;
  // прибираємо інлайн-форму входу Woo повністю — вхід відбувається в нашій модалці
  remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10);
});
/* розкладка реєстрації гостя (одна колонка): «Вже замовляли?» + бокс + розділювач
   + «Дані для входу» + кнопка Google + «або»; далі рендеряться поля Woo */
add_action('woocommerce_before_checkout_billing_form', function () {
  if (is_user_logged_in()) return;
  echo '<h4 class="shatailo-subhead">Вже замовляли у нас?</h4>';
  echo '<div class="shatailo-loginbox"><p>Якщо ви купували у нас раніше, будь ласка, <a href="#" class="showlogin">Натисніть сюди, щоб увійти</a></p></div>';
  echo '<div class="shatailo-divider"><span>або заповніть вручну нижче</span></div>';
  echo '<h4 class="shatailo-subhead">Дані для входу</h4>';
  if (shatailo_google_client_id()) {
    $nonce = esc_attr(wp_create_nonce('shatailo_google_prefill'));
    echo <<<HTML
<div class="shatailo-cg" data-nonce="{$nonce}">
  <button type="button" class="shatailo-cg__btn" id="shatailoCgBtn"><span class="shatailo-cg__g">G</span>&nbsp;Реєстрація з Google</button>
  <p class="shatailo-cg__status" id="shatailoCgStatus"></p>
</div>
<div class="shatailo-or"><span>або</span></div>
HTML;
  }
}, 6);

/* GIS + JS на checkout: (1) кнопка реєстрації підтягує поля; (2) модалка входу існуючого клієнта */
add_action('wp_enqueue_scripts', function () {
  if (!function_exists('is_checkout') || !is_checkout()) return;
  if (!shatailo_google_client_id()) return;
  wp_enqueue_script('gsi-client', 'https://accounts.google.com/gsi/client', array(), null, true);
  $cid   = wp_json_encode(shatailo_google_client_id());
  $urlP  = wp_json_encode(add_query_arg('wc-ajax', 'shatailo_google_prefill', home_url('/')));
  $urlPw = wp_json_encode(add_query_arg('wc-ajax', 'shatailo_checkout_pwlogin', home_url('/')));
  $urlGl = wp_json_encode(add_query_arg('wc-ajax', 'shatailo_checkout_googlelogin', home_url('/')));
  $urlLp = wp_json_encode(add_query_arg('wc-ajax', 'shatailo_lostpassword', home_url('/')));
  $js = <<<JS
window.addEventListener("load", function () {
  var CID = {$cid};
  var hasGoogle = !!(window.google && google.accounts && google.accounts.oauth2);
  function el(id){ return document.getElementById(id); }

  /* (1) реєстрація: Google підтягує поля */
  (function(){
    var box = document.querySelector(".shatailo-cg"), btn = el("shatailoCgBtn"), st = el("shatailoCgStatus");
    if (!box || !btn || !st || !hasGoogle) return;
    var nonce = box.getAttribute("data-nonce");
    function fill(id, val){ var e = el(id); if (e && val) { e.value = val; e.dispatchEvent(new Event("change", { bubbles: true })); } }
    var tc = google.accounts.oauth2.initTokenClient({ client_id: CID, scope: "openid email profile", callback: function (r) {
      if (!r || !r.access_token) { st.textContent = "Скасовано."; st.className = "shatailo-cg__status is-err"; return; }
      st.textContent = "Підтягуємо дані…"; st.className = "shatailo-cg__status";
      var fd = new FormData(); fd.append("access_token", r.access_token); fd.append("nonce", nonce);
      fetch({$urlP}, { method: "POST", body: fd, credentials: "same-origin" }).then(function(x){return x.json();}).then(function(j){
        if (j && j.success && j.data && j.data.email) {
          fill("billing_email", j.data.email); fill("billing_first_name", j.data.first); fill("billing_last_name", j.data.last);
          st.textContent = "✓ Дані підтягнуто. Завершіть замовлення нижче."; st.className = "shatailo-cg__status";
        } else { st.textContent = (j && j.data && j.data.message) || "Не вдалося."; st.className = "shatailo-cg__status is-err"; }
      }).catch(function(){ st.textContent = "Помилка мережі."; st.className = "shatailo-cg__status is-err"; });
    }});
    btn.addEventListener("click", function(){ st.textContent = "Відкриваємо Google…"; st.className = "shatailo-cg__status"; tc.requestAccessToken(); });
  })();

  /* (2) модалка входу існуючого клієнта (клік «Натисніть сюди, щоб увійти») */
  (function(){
    var modal = el("shatailoLogin"); if (!modal) return;
    var nonce = modal.getAttribute("data-nonce"), mst = el("shatailoLoginStatus");
    var loginView = el("shatailoLoginView"), resetView = el("shatailoResetView");
    function showLogin(){ if (loginView) loginView.hidden = false; if (resetView) resetView.hidden = true; }
    function showReset(){ if (loginView) loginView.hidden = true; if (resetView) resetView.hidden = false; }
    function open(){ showLogin(); modal.classList.add("is-open"); document.body.style.overflow = "hidden"; }
    function close(){ modal.classList.remove("is-open"); document.body.style.overflow = ""; }
    /* capture-фаза: спрацьовуємо раніше за WC-обробник (він робить return false на body) */
    document.addEventListener("click", function(e){
      if (!e.target || !e.target.closest) return;
      if (e.target.closest(".showlogin")) { e.preventDefault(); e.stopPropagation(); open(); return; }
      if (e.target.closest("[data-login-close]")) { close(); }
    }, true);
    document.addEventListener("keydown", function(e){ if (e.key === "Escape") close(); });
    var lostLink = el("shatailoLostLink"), resetBack = el("shatailoResetBack");
    if (lostLink) lostLink.addEventListener("click", function(e){ e.preventDefault(); showReset(); });
    if (resetBack) resetBack.addEventListener("click", function(e){ e.preventDefault(); showLogin(); });
    var rform = el("shatailoResetForm"), rst = el("shatailoResetStatus");
    if (rform) rform.addEventListener("submit", function(e){
      e.preventDefault();
      rst.textContent = "Надсилаємо…"; rst.className = "authmodal2__status";
      var fd = new FormData(); fd.append("login", rform.login.value.trim()); fd.append("nonce", nonce);
      fetch({$urlLp}, { method: "POST", body: fd, credentials: "same-origin" }).then(function(x){return x.json();}).then(function(j){
        if (j && j.success) { rst.textContent = (j.data && j.data.message) || "Лист надіслано."; rst.className = "authmodal2__status"; }
        else { rst.textContent = (j && j.data && j.data.message) || "Не вдалося."; rst.className = "authmodal2__status is-err"; }
      }).catch(function(){ rst.textContent = "Помилка мережі."; rst.className = "authmodal2__status is-err"; });
    });
    var form = el("shatailoLoginForm");
    if (form) form.addEventListener("submit", function(e){
      e.preventDefault();
      mst.textContent = "Входимо…"; mst.className = "authmodal2__status";
      var fd = new FormData();
      fd.append("email", form.email.value.trim());
      fd.append("password", form.password.value);
      if (form.remember && form.remember.checked) fd.append("remember", "1");
      fd.append("nonce", nonce);
      fetch({$urlPw}, { method: "POST", body: fd, credentials: "same-origin" }).then(function(x){return x.json();}).then(function(j){
        if (j && j.success) { window.location.reload(); }
        else { mst.textContent = (j && j.data && j.data.message) || "Не вдалося увійти."; mst.className = "authmodal2__status is-err"; }
      }).catch(function(){ mst.textContent = "Помилка мережі."; mst.className = "authmodal2__status is-err"; });
    });
    var gbtn = el("shatailoLoginGoogle");
    if (gbtn && hasGoogle) {
      var tcl = google.accounts.oauth2.initTokenClient({ client_id: CID, scope: "openid email profile", callback: function(r){
        if (!r || !r.access_token) { mst.textContent = "Скасовано."; mst.className = "authmodal2__status is-err"; return; }
        mst.textContent = "Входимо через Google…"; mst.className = "authmodal2__status";
        var fd = new FormData(); fd.append("access_token", r.access_token); fd.append("nonce", nonce);
        fetch({$urlGl}, { method: "POST", body: fd, credentials: "same-origin" }).then(function(x){return x.json();}).then(function(j){
          if (j && j.success) { window.location.reload(); }
          else { mst.textContent = (j && j.data && j.data.message) || "Не вдалося."; mst.className = "authmodal2__status is-err"; }
        }).catch(function(){ mst.textContent = "Помилка мережі."; mst.className = "authmodal2__status is-err"; });
      }});
      gbtn.addEventListener("click", function(){ mst.textContent = "Відкриваємо Google…"; mst.className = "authmodal2__status"; tcl.requestAccessToken(); });
    } else if (gbtn) { gbtn.style.display = "none"; }
  })();
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

/* ============================================================
   Модалка входу існуючого клієнта на checkout (клік «Натисніть сюди, щоб увійти»).
   Стиль як у сайт-модалці. Вхід email+пароль або Google → WP-логін → reload
   (дані підтягуються). Woo-інлайн-форму входу ховаємо (використовуємо нашу модалку).
   ============================================================ */
add_action('wp_footer', function () {
  if (!function_exists('is_checkout') || !is_checkout()) return;
  $nonce = esc_attr(wp_create_nonce('shatailo_checkout_login'));
  $lost  = esc_url(function_exists('wc_lostpassword_url') ? wc_lostpassword_url() : wp_lostpassword_url());
  $google = shatailo_google_client_id()
    ? '<button type="button" class="authmodal2__google" id="shatailoLoginGoogle"><span class="authmodal2__g">G</span>&nbsp;Продовжити з Google</button><div class="authmodal2__or"><span>або</span></div>'
    : '';
  echo <<<HTML
<div class="authmodal2" id="shatailoLogin" data-nonce="{$nonce}" aria-hidden="true">
  <div class="authmodal2__backdrop" data-login-close></div>
  <div class="authmodal2__box" role="dialog" aria-modal="true">
    <button class="authmodal2__close" data-login-close type="button" aria-label="Закрити">&times;</button>
    <div class="authmodal2__view" id="shatailoLoginView">
      <h3 class="authmodal2__title">Увійти</h3>
      {$google}
      <form class="authmodal2__form" id="shatailoLoginForm">
        <label class="authmodal2__field"><span>Email</span><input type="email" name="email" required autocomplete="email"></label>
        <label class="authmodal2__field"><span>Пароль</span><input type="password" name="password" required autocomplete="current-password"></label>
        <div class="authmodal2__row">
          <label class="authmodal2__remember"><input type="checkbox" name="remember"> Запамʼятати мене</label>
          <a class="authmodal2__lost" href="#" id="shatailoLostLink">Забули пароль?</a>
        </div>
        <button type="submit" class="authmodal2__submit">Увійти</button>
        <p class="authmodal2__status" id="shatailoLoginStatus"></p>
      </form>
    </div>
    <div class="authmodal2__view" id="shatailoResetView" hidden>
      <a href="#" class="authmodal2__back" id="shatailoResetBack">← Повернутися</a>
      <h3 class="authmodal2__title">Скидання пароля</h3>
      <p class="authmodal2__note">Введіть email або логін — надішлемо посилання для створення нового пароля.</p>
      <form class="authmodal2__form" id="shatailoResetForm">
        <label class="authmodal2__field"><span>Email або логін</span><input type="text" name="login" required autocomplete="username"></label>
        <button type="submit" class="authmodal2__submit">Скинути пароль</button>
        <p class="authmodal2__status" id="shatailoResetStatus"></p>
      </form>
    </div>
  </div>
</div>
<style>
body.woocommerce-checkout form.woocommerce-form-login { display:none !important; }
.authmodal2 { position:fixed; inset:0; z-index:99999; display:none; }
.authmodal2.is-open { display:block; }
.authmodal2__backdrop { position:absolute; inset:0; background:rgba(6,6,6,.72); -webkit-backdrop-filter:blur(6px); backdrop-filter:blur(6px); }
.authmodal2__box { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:min(400px,calc(100% - 40px)); max-height:90vh; overflow:auto; background:#0c0c0c; border:1px solid rgba(255,255,255,.12); border-radius:4px; padding:34px 30px; }
.authmodal2__close { position:absolute; top:12px; right:16px; background:none !important; border:0 !important; box-shadow:none !important; color:#f2ff00; font-size:1.7rem; line-height:1; cursor:pointer; }
.authmodal2__close:hover { background:none !important; color:#f4f2ec !important; }
.authmodal2__title { font-family:"Unbounded",sans-serif; font-weight:800; font-size:1.5rem; text-transform:uppercase; color:#f4f2ec; margin:0 0 22px; }
.authmodal2__back { display:inline-block; color:#8d8d86 !important; font-family:"Unbounded",sans-serif; font-size:.72rem; letter-spacing:.08em; text-transform:uppercase; text-decoration:none !important; margin:0 0 18px; transition:color .2s; }
.authmodal2__back:hover { color:#f2ff00 !important; }
.authmodal2__note { color:#8d8d86; font-family:"Inter",sans-serif; font-size:.88rem; line-height:1.5; margin:0 0 20px; }
.authmodal2__google { display:inline-flex; align-items:center; justify-content:center; gap:10px; width:100%; background:#f4f2ec !important; color:#0a0a0a !important; border:1px solid #f4f2ec !important; border-radius:2px; font-family:"Unbounded",sans-serif; font-weight:600; font-size:.82rem; letter-spacing:.06em; text-transform:uppercase; padding:16px 28px; cursor:pointer; transition:background .25s,box-shadow .25s; }
.authmodal2__google:hover { background:#f2ff00 !important; color:#0a0a0a !important; box-shadow:0 0 32px rgba(242,255,0,.35); }
.authmodal2__google:hover .authmodal2__g { color:#0a0a0a !important; }
.authmodal2__g { font-family:"Unbounded",sans-serif; font-weight:800; color:#0a0a0a !important; }
.authmodal2__or { display:flex; align-items:center; gap:14px; margin:20px 0; color:#8d8d86; font-size:.8rem; }
.authmodal2__or::before, .authmodal2__or::after { content:""; flex:1; height:1px; background:rgba(255,255,255,.12); }
.authmodal2__form { display:flex; flex-direction:column; gap:14px; }
.authmodal2__field { display:flex; flex-direction:column; gap:7px; }
.authmodal2__field > span { font-family:"Unbounded",sans-serif; font-size:.7rem; letter-spacing:.12em; text-transform:uppercase; color:#8d8d86; }
.authmodal2__field input { font-family:"Inter",sans-serif; font-size:.95rem; color:#f4f2ec; background:#060606; border:1px solid rgba(255,255,255,.14); border-radius:3px; padding:13px 15px; width:100%; }
.authmodal2__field input:focus { border-color:#f2ff00; outline:none; }
.authmodal2__row { display:flex; align-items:center; justify-content:space-between; gap:12px; font-size:.82rem; }
.authmodal2__remember { color:#8d8d86; display:inline-flex; align-items:center; gap:8px; cursor:pointer; }
.authmodal2__lost { color:#f2ff00 !important; text-decoration:underline; }
.authmodal2__lost:hover { color:#f4f2ec !important; }
.authmodal2__submit { background:#f2ff00 !important; color:#0a0a0a !important; border:1px solid #f2ff00 !important; border-radius:2px; font-family:"Unbounded",sans-serif; font-weight:600; font-size:.82rem; letter-spacing:.06em; text-transform:uppercase; padding:16px 28px; cursor:pointer; transition:box-shadow .25s; }
.authmodal2__submit:hover { background:#f2ff00 !important; color:#0a0a0a !important; box-shadow:0 0 32px rgba(242,255,0,.35); }
.authmodal2__status { min-height:1.1em; margin:2px 0 0; font-size:.85rem; color:#f2ff00; }
.authmodal2__status.is-err { color:#ff5555; }
</style>
HTML;
});

/* wc-ajax: вхід існуючого email+пароль (модалка checkout) → WP-логін */
add_action('wc_ajax_shatailo_checkout_pwlogin', 'shatailo_checkout_pwlogin');
function shatailo_checkout_pwlogin() {
  if (!wp_verify_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '', 'shatailo_checkout_login')) {
    wp_send_json_error(array('message' => 'Оновіть сторінку.'), 400);
  }
  $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'x';
  if (shatailo_login_blocked($ip)) wp_send_json_error(array('message' => 'Забагато спроб. Спробуйте за 15 хв.'), 429);
  $email = sanitize_email(isset($_POST['email']) ? $_POST['email'] : '');
  $pass  = isset($_POST['password']) ? (string) $_POST['password'] : '';
  $remember = !empty($_POST['remember']);
  if (!$email || !$pass) wp_send_json_error(array('message' => 'Вкажіть email і пароль.'), 400);
  $user = get_user_by('email', $email);
  if (!$user || !wp_check_password($pass, $user->user_pass, $user->ID)) {
    shatailo_login_fail($ip);
    wp_send_json_error(array('message' => 'Невірний email або пароль.'), 401);
  }
  delete_transient('shatailo_lf_' . md5($ip));
  wp_set_current_user($user->ID);
  wp_set_auth_cookie($user->ID, $remember);
  do_action('wp_login', $user->user_login, $user);
  wp_send_json_success();
}

/* wc-ajax: вхід існуючого через Google (модалка checkout) → WP-логін, БЕЗ створення */
add_action('wc_ajax_shatailo_checkout_googlelogin', 'shatailo_checkout_googlelogin');
function shatailo_checkout_googlelogin() {
  if (!wp_verify_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '', 'shatailo_checkout_login')) {
    wp_send_json_error(array('message' => 'Оновіть сторінку.'), 400);
  }
  if (!shatailo_google_client_id()) wp_send_json_error(array('message' => 'Не налаштовано.'), 503);
  $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'x';
  if (shatailo_login_blocked($ip, 'glf')) wp_send_json_error(array('message' => 'Забагато спроб. Спробуйте за 15 хв.'), 429);
  $access = isset($_POST['access_token']) ? (string) $_POST['access_token'] : '';
  if (!$access) wp_send_json_error(array('message' => 'Немає токена Google.'), 400);
  $v = shatailo_google_verify($access, '');
  if (is_wp_error($v)) { shatailo_login_fail($ip, 'glf'); wp_send_json_error(array('message' => $v->get_error_message()), 401); }
  if (empty($v['email']) || empty($v['verified'])) wp_send_json_error(array('message' => 'Google не підтвердив email.'), 401);
  $user = get_user_by('email', $v['email']);
  if (!$user) wp_send_json_error(array('message' => 'Акаунт із цим email не знайдено. Зареєструйтесь нижче.'), 404);
  delete_transient('shatailo_glf_' . md5($ip));
  wp_set_current_user($user->ID);
  wp_set_auth_cookie($user->ID, true);
  do_action('wp_login', $user->user_login, $user);
  wp_send_json_success();
}

/* wc-ajax: скидання пароля (у нашій модалці) — надіслати лист із посиланням */
add_action('wc_ajax_shatailo_lostpassword', 'shatailo_lostpassword');
function shatailo_lostpassword() {
  if (!wp_verify_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '', 'shatailo_checkout_login')) {
    wp_send_json_error(array('message' => 'Оновіть сторінку.'), 400);
  }
  $login = isset($_POST['login']) ? trim(wp_unslash($_POST['login'])) : '';
  if (!$login) wp_send_json_error(array('message' => 'Вкажіть email або логін.'), 400);
  $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'x';
  if (shatailo_login_blocked($ip, 'lp')) wp_send_json_error(array('message' => 'Забагато спроб. Спробуйте за 15 хв.'), 429);
  $result = retrieve_password($login);
  if (is_wp_error($result)) {
    shatailo_login_fail($ip, 'lp');
    wp_send_json_error(array('message' => wp_strip_all_tags($result->get_error_message())), 400);
  }
  wp_send_json_success(array('message' => 'Лист із посиланням для скидання надіслано на вашу пошту.'));
}

/* REST /lost-password — для сайт-модалки new.shatailo.com */
function shatailo_route_lostpassword($req) {
  $login = trim((string) $req->get_param('login'));
  if (!$login) return new WP_Error('bad_request', 'Вкажіть email.', array('status' => 400));
  $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'x';
  if (shatailo_login_blocked($ip, 'lp')) return new WP_Error('too_many', 'Забагато спроб. Спробуйте за 15 хв.', array('status' => 429));
  $result = retrieve_password($login);
  if (is_wp_error($result)) {
    shatailo_login_fail($ip, 'lp');
    return new WP_Error('reset_failed', wp_strip_all_tags($result->get_error_message()), array('status' => 400));
  }
  return array('message' => 'Лист із посиланням для скидання надіслано на вашу пошту.');
}

/* залогінений на checkout: ім'я/email лише для показу (readonly) + лінк «Зайти під іншим акаунтом» */
add_filter('woocommerce_checkout_fields', function ($fields) {
  if (!is_user_logged_in()) return $fields;
  foreach (array('billing_first_name', 'billing_last_name', 'billing_email') as $k) {
    if (isset($fields['billing'][$k])) {
      $fields['billing'][$k]['custom_attributes']['readonly'] = 'readonly';
    }
  }
  return $fields;
});
add_action('woocommerce_before_checkout_billing_form', function () {
  if (!is_user_logged_in()) return;
  $u = wp_get_current_user();
  $name = $u->first_name ? $u->first_name : $u->display_name;
  // «Зайти під іншим акаунтом» → відкриває нашу модалку входу (без logout, щоб кошик не зникав)
  echo '<p class="shatailo-switch">Ви увійшли як <b>' . esc_html($name) . '</b> · <a href="#" class="showlogin">Зайти під іншим акаунтом</a></p>';
  echo '<style>'
    . '.shatailo-switch { color:#8d8d86; font-family:"Inter",sans-serif; font-size:.9rem; margin:0 0 22px; }'
    . '.shatailo-switch b { color:#f4f2ec; }'
    . '.shatailo-switch a { color:#f2ff00 !important; text-decoration:underline; }'
    . '.shatailo-switch a:hover { color:#f4f2ec !important; }'
    . 'body.woocommerce-checkout .woocommerce-billing-fields input[readonly] { background:transparent !important; border:0 !important; border-bottom:1px solid rgba(255,255,255,.1) !important; border-radius:0 !important; color:#f4f2ec !important; padding-left:0 !important; cursor:default !important; box-shadow:none !important; }'
    . 'body.woocommerce-checkout .woocommerce-billing-fields input[readonly]:focus { border-bottom-color:rgba(255,255,255,.1) !important; }'
    . 'body.woocommerce-checkout #order_review_heading { margin-top:56px !important; padding-top:44px !important; border-top:1px solid rgba(255,255,255,.1) !important; }'
    . '</style>';
}, 8);
