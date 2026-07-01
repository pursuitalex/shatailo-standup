# Локальний staging-клон живого сайту (Duplicator → Docker LAMP)

Мета — підняти точну копію продакшн-сайту **локально**, щоб проаналізувати внутрянку
(товари, WooCommerce Memberships, member-сторінку з відео, WayForPay) і протестувати
флоу покупки **не торкаючись проду**. Це середовище **лише для аналізу**, у ньому ПІІ
реальних покупців — **нікуди не публікувати й не деплоїти**.

Стек контейнерів: **PHP 7.4 + Apache** (web) + **MariaDB 10.6** (db).

---

## 0. Потрібно
- **Docker Desktop** (Windows) — запущений.
- ~2 ГБ вільного місця (розпакований WP + БД).

## 1. Покласти файли бекапу у webroot
З кореня проєкту (щоб **не чіпати** саму папку `!!!backup/`, копіюємо в окремий `www/`):

```bash
mkdir -p staging/www
cp "!!!backup/installer.php" staging/www/
cp "!!!backup/"*.zip staging/www/
```

## 2. Підняти контейнери
```bash
cd staging
docker compose up -d --build
```
Перша збірка web-образу — кілька хвилин. Перевір: `docker compose ps` (обидва `running`).

## 3. Запустити Duplicator-інсталятор
Відкрий у браузері: **http://localhost:8080/installer.php**

У майстрі:
- **Крок 1 (Deploy):** прийми умови.
- **Database:**
  - Action: **Empty database** (вона порожня)
  - Host: **`db`**  ← важливо, це імʼя сервісу, не `localhost`
  - Database: **`shatailo`**
  - User: **`shatailo`** · Password: **`shatailo`**  (або `root`/`root`)
  - «Test Database» → має бути ОК → **Next** (розпакування + імпорт SQL, ~кілька хвилин).
- **Крок 3 (Update):** URL має стати **http://localhost:8080** (Duplicator підставить сам). Далі.

## 4. Пост-фікси (щоб локалка не зламалась)
Деякі плагіни заважають локально. **Найпростіше — вимкнути їх, перейменувавши папки**
(WP автоматично деактивує «зниклі» плагіни):

```bash
cd staging/www/wp-content/plugins
for p in really-simple-ssl change-wp-admin-login jetpack limit-login-attempts-reloaded; do
  [ -d "$p" ] && mv "$p" "_off_$p"
done
```
- `really-simple-ssl` — форсує HTTPS → редірект-луп на http-localhost.
- `change-wp-admin-login` — ховає `/wp-admin` (інакше не зайдеш).
- `jetpack` / `limit-login-attempts` — потребують зовнішнього зʼєднання / блокують входи.

Якщо `/wp-admin` кидає редірект-луп — примусово виставити URL:
```bash
docker compose exec db mysql -uroot -proot shatailo -e \
"UPDATE mVcT2m_options SET option_value='http://localhost:8080' WHERE option_name IN ('siteurl','home');"
```

## 5. Готово
- Фронт: **http://localhost:8080/**
- Адмінка: **http://localhost:8080/wp-admin/**
  - Логін адміна дізнаємось із БД (див. нижче); пароль за потреби скинемо.

---

## Для Claude: як я аналізуватиму (без адмінки)
Коли клон піднято, мені для глибокого аналізу потрібен лише доступ до **БД і файлів** —
я їх читатиму так:

**БД (точні дані):**
```bash
# знайти адмінів
docker compose exec db mysql -uroot -proot shatailo -e \
"SELECT u.ID,u.user_login,u.user_email FROM mVcT2m_users u \
 JOIN mVcT2m_usermeta m ON m.user_id=u.ID \
 WHERE m.meta_key='mVcT2m_capabilities' AND m.meta_value LIKE '%administrator%';"

# 4 товари-сольники: назва, ціна, статус, permalink-slug
docker compose exec db mysql -uroot -proot shatailo -e \
"SELECT ID,post_title,post_name,post_status FROM mVcT2m_posts \
 WHERE post_type='product' AND post_status='publish';"
```
**Файли:** тему `wp-content/themes/hello-elementor-child/`, WayForPay-плагін і
шаблон member-сторінки читатиму прямо з `staging/www/`.

**Скинути пароль адміна** (WP приймає MD5 як fallback):
```bash
docker compose exec db mysql -uroot -proot shatailo -e \
"UPDATE mVcT2m_users SET user_pass=MD5('staging123') WHERE user_login='ЛОГІН_АДМІНА';"
```

---

## Зупинити / видалити
```bash
docker compose down          # зупинити (БД зберігається у volume)
docker compose down -v       # + видалити БД-volume (повне обнулення)
```

## ⚠️ Безпека
- Після аналізу **видали інсталятор**: `rm -f staging/www/installer.php staging/www/*.zip` і теку `staging/www/dup-installer/`.
- `staging/www/` і БД-volume **у git не потрапляють** (додано в `.gitignore`).
- Це середовище містить реальні ПІІ — тримати лише локально.
