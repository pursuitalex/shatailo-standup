# Shatailo Headless API (mu-plugin для WordPress)

Серверна частина для нового фронту `new.shatailo.com`: логін і дані кабінету.
**Тільки читання** даних (профіль, замовлення, куплені сольники + Vimeo). БД не змінює.
Токен — HMAC, секрет = `wp_salt('auth')` (у файлі секрету немає → підробити токен не можна).

Це **НЕ** частина Vercel-деплою (виключено в `.vercelignore`) — файл вручну кладеться на WordPress.

## Установка
1. Свіжий бекап БД (вже зроблено).
2. У cPanel → **File Manager** → перейти в `public_html/wp-content/`.
3. Якщо немає папки **`mu-plugins`** — створити її (`wp-content/mu-plugins`).
4. Завантажити туди файл **`mu-plugins/shatailo-api.php`**.
   mu-plugins активуються автоматично (нічого вмикати не треба).

## Перевірка, що плагін живий
Відкрий у браузері:
`https://shatailo.com/wp-json/shatailo/v1/me`
→ має віддати JSON `{"code":"unauthorized",...}` (401). Це **нормально** — означає, що роут працює.

## Ендпоінти
- `POST /wp-json/shatailo/v1/login` — тіло `{ "email": "...", "password": "..." }` → `{ token, user }`.
- `GET  /wp-json/shatailo/v1/me` — заголовок `Authorization: Bearer <token>` → `{ user, orders, library }`.

## Прибрати
Просто видалити файл `shatailo-api.php` — усе стане як було.

## Можливий нюанс (Authorization header)
Якщо `/me` не бачить токен (401 навіть із правильним токеном) — сервер міг зрізати заголовок `Authorization`.
Тоді в корені сайту у `.htaccess` (на початок) додати:
```
RewriteEngine On
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```
Код уже читає й `REDIRECT_HTTP_AUTHORIZATION`, тож частіше додаткове правило не потрібне.

## Далі (не в цьому файлі)
- Google-логін — окремий роут (потрібен Google OAuth Client ID).
- Vimeo — дозволити домен `new.shatailo.com` для вбудови відео.
