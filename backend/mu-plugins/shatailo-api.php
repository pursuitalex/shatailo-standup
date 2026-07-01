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

/* ---- Бібліотека: сольники, до яких у користувача активний доступ ---- */
function shatailo_library($uid) {
  $lib = array();
  if (!function_exists('wc_memberships_is_user_active_member')) return $lib;
  foreach (shatailo_solnyky() as $plan_id => $s) {
    if (wc_memberships_is_user_active_member($uid, $plan_id)) {
      $lib[] = array(
        'slug'  => $s['slug'],
        'title' => $s['title'],
        'info'  => $s['info'],
        'vimeo' => $s['vimeo'],
      );
    }
  }
  return $lib;
}

/* ---- Проста заслінка від брутфорсу логіну ---- */
function shatailo_login_blocked($ip) {
  return ((int) get_transient('shatailo_lf_' . md5($ip))) >= 8;
}
function shatailo_login_fail($ip) {
  $k = 'shatailo_lf_' . md5($ip);
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
