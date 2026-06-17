/* ============================================================
   STORE — клієнтський концепт-магазин (localStorage)
   Каталог + кошик + mock-авторизація.
   Інжектить у будь-яку сторінку: іконки кошика/акаунта в хедер,
   міні-кошик (сайдбар) і модалку входу. Рендерить /cart і /checkout.
   Завантажується як звичайний <script> на всіх сторінках.
   ============================================================ */
(function () {
  "use strict";

  var CART_KEY = "shatailo_cart";
  var USER_KEY = "shatailo_user";
  var ORDERS_KEY = "shatailo_orders";

  var PRODUCTS = {
    kosmichnyi: {
      id: "kosmichnyi", title: "Сольник «Космічний А#уй»", price: 500,
      poster: "/assets/posters/poster-kosmichnyi.jpg", trailer: "h5b3UE8VI-s", start: 0,
      meta: "Четвертий всеукраїнський сольник за бабки · 76 хв",
      info: "2026 · 76 хв · четвертий сольник",
      desc: "До вашої уваги новий сольний стендап концерт 2026-го року про вітчизняну пропаганду, новинну дихотомію, наступну мить, двері божевілля спортсменів та первородний гріх українців. Купуючи цей сольник, ви отримуєте право дивитись його на цьому сайті необмежено з моменту придбання. Присутнє матюччя. Приємного перегляду."
    },
    povitryane: {
      id: "povitryane", title: "Сольник «Повітряне Зашибісь»", price: 400,
      poster: "/assets/posters/poster-povitryane.png", trailer: "lYx-0zywj8E", start: 421,
      meta: "Третій всеукраїнський сольник за бабки · 70 хв",
      info: "2024 · 70 хв · третій сольник",
      desc: "Новий сольник «Повітряне Зашибісь» 2024-го року про відносини, супер зброю США, країни західного блоку, спокій, євробачення, західну аудиторію та неймовірний кайф від радості бути українцем. Купуючи цей сольник, ви отримуєте право дивитись його на цьому сайті необмежено з моменту придбання. Присутнє матюччя. Приємного перегляду."
    },
    sekunda: {
      id: "sekunda", title: "Сольник «Секунда Бабака»", price: 300,
      poster: "/assets/posters/poster-sekunda.jpg", trailer: "qaG_LTXGEhk", start: 25,
      meta: "Другий всеукраїнський сольник за бабки · 83 хв",
      info: "2023 · 83 хв · другий сольник",
      desc: "Мій новий сольник «Секунда Бабака» 2023-го року про божевілля, попайку, транквілізатори, суїцидальні думки, повітряні тривоги, поплавського, політичних експертів, синю філософію та часово-просторовий парадокс життя в Україні під час повномасштабного вторгнення росії. Купуючи цей сольник, ви отримуєте право дивитись його на цьому сайті необмежено з моменту придбання. Як завжди – присутнє матюччя. Приємного перегляду."
    },
    tymchasovi: {
      id: "tymchasovi", title: "Сольник «Тимчасові незручності»", price: 250,
      poster: "/assets/posters/poster-tymchasovi.jpg", trailer: "G8nOmNe1TBs", start: 0,
      meta: "Перший всеукраїнський сольник за бабки · 67 хв",
      info: "2021 · 67 хв · перший сольник",
      desc: "Включає в себе такі біти: розвиток України та її місце в світі, правоохоронні органи, Чікатіло, українські селеби, легкі наркотичні речовини, волелюбність українського народу, конституція, апокаліпсис, судова реформа та гражданє Зємлі. Купуючи цей сольник ви отримуєте право дивитись його на цьому сайті необмежено з моменту придбання. Присутнє матюччя. Приємного перегляду."
    }
  };

  /* ---------- стан ---------- */
  function readJSON(k, fallback) {
    try { var v = JSON.parse(localStorage.getItem(k)); return v == null ? fallback : v; }
    catch (e) { return fallback; }
  }
  function writeJSON(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }

  var cart = readJSON(CART_KEY, []);     // [{ id, qty }]
  var user = readJSON(USER_KEY, null);   // { name, lastName, displayName, email, provider }
  var orders = readJSON(ORDERS_KEY, {}); // { email: [{ num, date, items, total, name, email, status }] }
  if (Array.isArray(orders)) orders = {}; // міграція зі старого (плоского) формату

  var listeners = [];
  function emit() { listeners.forEach(function (fn) { fn(); }); }

  function saveCart() { writeJSON(CART_KEY, cart); emit(); }
  function saveUser() {
    if (user) writeJSON(USER_KEY, user); else localStorage.removeItem(USER_KEY);
    emit();
  }
  function saveOrders() { writeJSON(ORDERS_KEY, orders); emit(); }

  /* ---------- замовлення + бібліотека (per-user, за email) ---------- */
  var DEMO_EMAIL = "demo@gmail.com";

  function userEmail() { return (user && user.email) || "guest"; }
  function userOrders() {
    var e = userEmail();
    if (!orders[e]) orders[e] = [];
    return orders[e];
  }
  function addOrder(buyer) {
    var items = cartItems().map(function (i) { return { id: i.id, title: i.title, price: i.price, qty: i.qty }; });
    if (!items.length) return null;
    var d = new Date();
    var order = {
      num: String(Date.now()).slice(-6),
      date: d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2) + "-" + ("0" + d.getDate()).slice(-2),
      items: items,
      total: cartSubtotal(),
      name: (buyer && buyer.name) || (user && user.displayName) || (user && user.name) || "",
      email: (buyer && buyer.email) || (user && user.email) || "",
      status: "Виконано"
    };
    userOrders().unshift(order);
    saveOrders();
    return order;
  }
  function getOrders() { return userOrders().slice(); }
  function getOrder(num) { return userOrders().find(function (o) { return o.num === num; }); }
  // бібліотека = унікальні сольники з усіх замовлень користувача
  function library() {
    var ids = {};
    userOrders().forEach(function (o) { o.items.forEach(function (it) { if (PRODUCTS[it.id]) ids[it.id] = true; }); });
    return Object.keys(ids).map(function (id) { return PRODUCTS[id]; });
  }
  // демо-акаунт: при вході під demo@gmail.com наповнюємо тестовими замовленнями
  function seedDemoOrders() {
    if (orders[DEMO_EMAIL] && orders[DEMO_EMAIL].length) return;
    var o = function (num, date, ids, total) {
      return {
        num: num, date: date,
        items: ids.map(function (id) { return { id: id, title: PRODUCTS[id].title, price: PRODUCTS[id].price, qty: 1 }; }),
        total: total, name: "Демо Користувач", email: DEMO_EMAIL, status: "Виконано"
      };
    };
    orders[DEMO_EMAIL] = [
      o("100231", "2026-06-14", ["kosmichnyi"], 500),
      o("100198", "2026-05-02", ["povitryane", "sekunda"], 700),
      o("100042", "2026-02-18", ["tymchasovi"], 250)
    ];
    saveOrders();
  }

  /* ---------- кошик ---------- */
  function addToCart(id, qty) {
    if (!PRODUCTS[id]) return;
    qty = qty || 1;
    var it = cart.find(function (c) { return c.id === id; });
    if (it) it.qty += qty; else cart.push({ id: id, qty: qty });
    saveCart();
  }
  function setQty(id, qty) {
    var it = cart.find(function (c) { return c.id === id; });
    if (!it) return;
    it.qty = Math.max(1, qty | 0);
    saveCart();
  }
  function removeFromCart(id) { cart = cart.filter(function (c) { return c.id !== id; }); saveCart(); }
  function clearCart() { cart = []; saveCart(); }
  function cartItems() {
    return cart
      .filter(function (c) { return PRODUCTS[c.id]; })
      .map(function (c) {
        var p = PRODUCTS[c.id];
        return { id: p.id, title: p.title, price: p.price, poster: p.poster, qty: c.qty, lineTotal: p.price * c.qty };
      });
  }
  function cartCount() { return cart.reduce(function (s, c) { return s + c.qty; }, 0); }
  function cartSubtotal() { return cartItems().reduce(function (s, i) { return s + i.lineTotal; }, 0); }
  function uah(n) { return (n || 0).toLocaleString("uk-UA") + " ₴"; }

  /* ---------- авторизація (mock) ---------- */
  function isLoggedIn() { return !!user; }
  function getUser() { return user; }
  function login(u) {
    user = u;
    saveUser();
    if (u && u.email && u.email.toLowerCase() === DEMO_EMAIL) seedDemoOrders();
  }
  function logout() { user = null; saveUser(); }

  /* ---------- іконки ---------- */
  var ICON_USER = '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true"><path d="M12 2C15.3137 2 18 4.68629 18 8C18 9.94598 17.0726 11.6742 15.6367 12.7705C16.6434 13.2154 17.571 13.8424 18.3643 14.6357C20.0521 16.3236 21 18.6131 21 21C21 21.5523 20.5523 22 20 22C19.4477 22 19 21.5523 19 21C19 19.1435 18.2629 17.3626 16.9502 16.0498C15.6374 14.7371 13.8565 14 12 14C10.1435 14 8.36256 14.7371 7.0498 16.0498C5.73705 17.3626 5 19.1435 5 21C5 21.5523 4.55228 22 4 22C3.44772 22 3 21.5523 3 21C3 18.6131 3.94791 16.3236 5.63574 14.6357C6.42882 13.8427 7.35593 13.2153 8.3623 12.7705C6.92681 11.6742 6 9.9457 6 8C6 4.68629 8.68629 2 12 2ZM12 4C9.79086 4 8 5.79086 8 8C8 10.2091 9.79086 12 12 12C14.2091 12 16 10.2091 16 8C16 5.79086 14.2091 4 12 4Z"/></svg>';
  var ICON_CART = '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true"><path d="M10 19C11.1044 19.0001 11.9999 19.8956 12 21C11.9999 22.1044 11.1044 22.9999 10 23C8.89558 22.9999 8.00011 22.1044 8 21C8.00013 19.8956 8.8956 19.0001 10 19ZM17 19C18.1044 19.0001 18.9999 19.8956 19 21C18.9999 22.1044 18.1044 22.9999 17 23C15.8956 22.9999 15.0001 22.1044 15 21C15.0001 19.8956 15.8956 19.0001 17 19ZM4.0498 1.0498C4.52129 1.0498 4.92848 1.37984 5.02734 1.84082L5.92871 6.0498H22.0898C22.393 6.04982 22.6803 6.18741 22.8701 6.42383C23.0598 6.66032 23.1321 6.97062 23.0664 7.2666L21.416 14.6963L21.415 14.6953C21.2681 15.3618 20.8997 15.9587 20.3682 16.3867C19.8363 16.8149 19.174 17.0487 18.4912 17.0498H8.70996V17.0488C8.0201 17.0591 7.3474 16.8323 6.80566 16.4043C6.25828 15.9718 5.8788 15.3618 5.73242 14.6797L4.14844 7.28711C4.14336 7.26626 4.13851 7.24497 4.13477 7.22363L3.24121 3.0498H2.0498C1.4976 3.04971 1.0498 2.60203 1.0498 2.0498C1.04994 1.49769 1.49769 1.0499 2.0498 1.0498H4.0498ZM7.6875 14.2598C7.73627 14.4871 7.8635 14.6908 8.0459 14.835C8.22831 14.979 8.45515 15.0549 8.6875 15.0498H18.4883C18.7156 15.0494 18.9361 14.9716 19.1133 14.8291C19.2905 14.6864 19.414 14.4869 19.4629 14.2646L19.4639 14.2627L20.8428 8.0498H6.35742L7.6875 14.2598Z"/></svg>';
  var ICON_CLOSE = '✕';

  /* ============================================================
     UI: інжект хедер-інструментів, сайдбара, модалки входу
     ============================================================ */
  function injectHeaderTools() {
    var header = document.querySelector(".header");
    if (!header || header.querySelector(".header__tools")) return;
    var tools = document.createElement("div");
    tools.className = "header__tools";
    tools.innerHTML =
      '<button class="hicon" data-account type="button" aria-label="Акаунт" data-hover>' + ICON_USER + '</button>' +
      '<button class="hicon" data-cart-toggle type="button" aria-label="Кошик" data-hover>' + ICON_CART +
      '<span class="hicon__badge" data-cart-count hidden>0</span></button>';
    // якщо є правий блок хедера — іконки зліва від кнопки; інакше — в кінець хедера
    var right = header.querySelector(".header__right");
    if (right) right.insertBefore(tools, right.firstChild);
    else header.appendChild(tools);
  }

  function injectMiniCart() {
    if (document.getElementById("miniCart")) return;
    var el = document.createElement("div");
    el.className = "minicart";
    el.id = "miniCart";
    el.setAttribute("aria-hidden", "true");
    el.innerHTML =
      '<div class="minicart__backdrop" data-cart-close></div>' +
      '<aside class="minicart__panel" role="dialog" aria-modal="true" aria-label="Кошик">' +
      '  <header class="minicart__head"><h3>Кошик</h3>' +
      '    <button class="minicart__close" data-cart-close aria-label="Закрити" data-hover>' + ICON_CLOSE + '</button></header>' +
      '  <div class="minicart__body" data-cart-body></div>' +
      '  <footer class="minicart__foot" data-cart-foot></footer>' +
      "</aside>";
    document.body.appendChild(el);
  }

  function injectAuthModal() {
    if (document.getElementById("authModal")) return;
    var el = document.createElement("div");
    el.className = "authmodal";
    el.id = "authModal";
    el.setAttribute("aria-hidden", "true");
    el.innerHTML =
      '<div class="authmodal__backdrop" data-auth-close></div>' +
      '<div class="authmodal__panel" role="dialog" aria-modal="true" aria-label="Увійти">' +
      '  <button class="authmodal__close" data-auth-close aria-label="Закрити" data-hover>' + ICON_CLOSE + '</button>' +
      '  <div data-auth-view></div>' +
      "</div>";
    document.body.appendChild(el);
  }

  /* ---------- рендер міні-кошика ---------- */
  function renderMiniCart() {
    var body = document.querySelector("#miniCart [data-cart-body]");
    var foot = document.querySelector("#miniCart [data-cart-foot]");
    if (!body || !foot) return;
    var items = cartItems();
    if (!items.length) {
      body.innerHTML = '<p class="minicart__empty">Кошик порожній.<br>Оберіть сольник нижче.</p>';
      foot.innerHTML = "";
      return;
    }
    body.innerHTML = items.map(function (i) {
      return '<div class="mc-item">' +
        '<img class="mc-item__img" src="' + i.poster + '" alt="">' +
        '<div class="mc-item__info">' +
          '<p class="mc-item__title">' + i.title + '</p>' +
          '<div class="mc-item__row">' +
            '<div class="qty"><button class="qty__btn" data-qty-dec="' + i.id + '" aria-label="Менше">–</button>' +
            '<span class="qty__val">' + i.qty + '</span>' +
            '<button class="qty__btn" data-qty-inc="' + i.id + '" aria-label="Більше">+</button></div>' +
            '<span class="mc-item__price">' + uah(i.lineTotal) + '</span>' +
          '</div>' +
        '</div>' +
        '<button class="mc-item__rm" data-rm="' + i.id + '" aria-label="Прибрати">' + ICON_CLOSE + '</button>' +
        '</div>';
    }).join("");
    foot.innerHTML =
      '<div class="minicart__subtotal"><span>Проміжний підсумок</span><b>' + uah(cartSubtotal()) + '</b></div>' +
      '<div class="minicart__actions">' +
      '  <a class="btn btn--ghost btn--sm" data-hover href="/cart">Переглянути кошик</a>' +
      '  <a class="btn btn--solid btn--sm" data-hover href="/checkout">Оформлення замовлення</a>' +
      "</div>";
  }

  function updateBadges() {
    var n = cartCount();
    document.querySelectorAll("[data-cart-count]").forEach(function (b) {
      b.textContent = n;
      if (n > 0) b.removeAttribute("hidden"); else b.setAttribute("hidden", "");
    });
    document.querySelectorAll("[data-account]").forEach(function (b) {
      b.classList.toggle("is-auth", isLoggedIn());
    });
  }

  /* ---------- сайдбар open/close ---------- */
  function openCart() {
    renderMiniCart();
    var m = document.getElementById("miniCart");
    if (m) { m.classList.add("is-open"); m.setAttribute("aria-hidden", "false"); document.body.style.overflow = "hidden"; }
  }
  function closeCart() {
    var m = document.getElementById("miniCart");
    if (m) { m.classList.remove("is-open"); m.setAttribute("aria-hidden", "true"); document.body.style.overflow = ""; }
  }

  /* ---------- модалка авторизації / акаунта ---------- */
  function authViewLogin() {
    return '' +
      '<h3 class="authmodal__title">Увійти</h3>' +
      '<button class="btn btn--white auth-google" data-google type="button" data-hover>' +
        '<span class="auth-google__g">G</span> Продовжити з Google</button>' +
      '<div class="auth-or"><span>або</span></div>' +
      '<form class="auth-form" data-auth-form>' +
        '<label class="auth-field"><span>Email</span><input type="email" name="email" required placeholder="you@example.com" autocomplete="email"></label>' +
        '<label class="auth-field"><span>Пароль</span><input type="password" name="password" required placeholder="••••••••" autocomplete="current-password"></label>' +
        '<button class="btn btn--solid auth-submit" type="submit" data-hover>Увійти</button>' +
        '<p class="auth-note">Це концепт — авторизація демонстраційна, дані нікуди не йдуть.</p>' +
      '</form>';
  }
  function authViewAccount() {
    var u = user || {};
    return '' +
      '<h3 class="authmodal__title">Акаунт</h3>' +
      '<div class="auth-account">' +
        '<div class="auth-account__avatar">' + ((u.name || "?").charAt(0).toUpperCase()) + '</div>' +
        '<div><p class="auth-account__name">' + (u.name || "Користувач") + '</p>' +
        '<p class="auth-account__email">' + (u.email || "") + '</p></div>' +
      '</div>' +
      '<a class="btn btn--ghost btn--sm auth-cabinet-link" data-hover href="/account">Особистий кабінет</a>' +
      '<button class="btn btn--solid btn--sm" data-logout type="button" data-hover>Вийти</button>';
  }
  function renderAuthView() {
    var v = document.querySelector("#authModal [data-auth-view]");
    if (!v) return;
    v.innerHTML = isLoggedIn() ? authViewAccount() : authViewLogin();
  }
  function openAuth() {
    renderAuthView();
    var m = document.getElementById("authModal");
    if (m) { m.classList.add("is-open"); m.setAttribute("aria-hidden", "false"); document.body.style.overflow = "hidden"; }
  }
  function closeAuth() {
    var m = document.getElementById("authModal");
    if (m) { m.classList.remove("is-open"); m.setAttribute("aria-hidden", "true"); document.body.style.overflow = ""; }
  }

  /* ============================================================
     Рендер повних сторінок (/cart, /checkout) за data-атрибутами
     ============================================================ */
  function renderCartPage() {
    var root = document.querySelector("[data-cart-page]");
    if (!root) return;
    var items = cartItems();
    if (!items.length) {
      root.innerHTML =
        '<div class="cartpage__empty"><p>Ваш кошик порожній.</p>' +
        '<a class="btn btn--solid" data-hover href="/#solnyky">До сольників</a></div>';
      return;
    }
    var rows = items.map(function (i) {
      return '<tr>' +
        '<td class="cp-cell-rm"><button class="cp-rm" data-rm="' + i.id + '" aria-label="Прибрати">' + ICON_CLOSE + '</button></td>' +
        '<td class="cp-cell-prod"><img src="' + i.poster + '" alt=""><span>' + i.title + '</span></td>' +
        '<td data-label="Ціна">' + uah(i.price) + '</td>' +
        '<td data-label="Кількість"><div class="qty"><button class="qty__btn" data-qty-dec="' + i.id + '">–</button><span class="qty__val">' + i.qty + '</span><button class="qty__btn" data-qty-inc="' + i.id + '">+</button></div></td>' +
        '<td data-label="Підсумок"><b>' + uah(i.lineTotal) + '</b></td>' +
        '</tr>';
    }).join("");
    root.innerHTML =
      '<div class="cartpage__table-wrap"><table class="cartpage__table">' +
      '<thead><tr><th></th><th>Товар</th><th>Ціна</th><th>Кількість</th><th>Проміжний підсумок</th></tr></thead>' +
      '<tbody>' + rows + '</tbody></table></div>' +
      '<div class="cartpage__totals">' +
        '<h2 class="cartpage__totals-title">Підсумки кошика</h2>' +
        '<div class="cartpage__totals-row"><span>Проміжний підсумок</span><b>' + uah(cartSubtotal()) + '</b></div>' +
        '<div class="cartpage__totals-row cartpage__totals-row--grand"><span>Загалом</span><b>' + uah(cartSubtotal()) + '</b></div>' +
        '<a class="btn btn--solid cartpage__checkout" data-hover href="/checkout">Перейти до оформлення</a>' +
      '</div>';
  }

  function renderCheckout() {
    var root = document.querySelector("[data-checkout-page]");
    if (!root) return;
    var items = cartItems();

    if (!items.length && !root.dataset.done) {
      root.innerHTML =
        '<div class="cartpage__empty"><p>Кошик порожній — нема що оформлювати.</p>' +
        '<a class="btn btn--solid" data-hover href="/#solnyky">До сольників</a></div>';
      return;
    }
    if (root.dataset.done) return; // показано екран успіху

    var u = user || {};
    var rows = items.map(function (i) {
      return '<tr><td>' + i.title + ' <span class="co-x">× ' + i.qty + '</span></td><td>' + uah(i.lineTotal) + '</td></tr>';
    }).join("");
    root.innerHTML =
      '<form class="checkout" data-checkout-form>' +
      '  <div class="checkout__col checkout__col--form">' +
      '    <h2 class="checkout__h">Платіжні дані</h2>' +
      (isLoggedIn()
        ? '<p class="checkout__as">Ви увійшли як <b>' + (u.email || u.name) + '</b></p>'
        : '<p class="checkout__as">Уже маєте акаунт? <button type="button" class="checkout__login" data-account-open>Увійти</button></p>') +
      '    <label class="auth-field"><span>Ім’я</span><input type="text" name="name" required value="' + (u.name || "") + '" placeholder="Як вас звати"></label>' +
      '    <label class="auth-field"><span>Email</span><input type="email" name="email" required value="' + (u.email || "") + '" placeholder="you@example.com"></label>' +
      '    <div class="checkout__pay">' +
      '      <span class="checkout__pay-label">Інтернет-еквайринг</span>' +
      '      <p class="checkout__pay-note">Сплатіть безпечно карткою через сервіс еквайрингу. (Демо — реальна оплата не відбувається.)</p>' +
      '      <img class="checkout__pay-logo" src="/assets/payment/wayforpay-2.png" alt="WayForPay — інтернет-еквайринг" width="180">' +
      '    </div>' +
      '  </div>' +
      '  <aside class="checkout__col checkout__col--summary">' +
      '    <h3 class="checkout__h">Ваше замовлення</h3>' +
      '    <table class="checkout__summary"><tbody>' + rows +
      '      <tr class="checkout__summary-sub"><td>Проміжний підсумок</td><td>' + uah(cartSubtotal()) + '</td></tr>' +
      '      <tr class="checkout__summary-grand"><td>Загалом</td><td>' + uah(cartSubtotal()) + '</td></tr>' +
      '    </tbody></table>' +
      '    <button class="btn btn--solid btn--sm checkout__submit" type="submit" data-hover>Підтвердити</button>' +
      '    <p class="checkout__legal"><a href="/dogovir-oferty">Договір оферти</a> · <a href="/privacy-policy">Політика конфіденційності</a></p>' +
      '  </aside>' +
      '</form>';
  }

  function renderCheckoutDone() {
    var root = document.querySelector("[data-checkout-page]");
    if (!root) return;
    root.dataset.done = "1";
    root.innerHTML =
      '<div class="checkout__done">' +
      '  <div class="checkout__done-mark">✓</div>' +
      '  <h2>Замовлення прийнято!</h2>' +
      '  <p>Дякуємо! Сольники додано у ваш кабінет — дивіться їх у розділі «Сольники». (Концепт — реальної оплати немає.)</p>' +
      '  <div class="checkout__done-actions">' +
      '    <a class="btn btn--solid" data-hover href="/account">До кабінету</a>' +
      '    <a class="btn btn--ghost" data-hover href="/">На головну</a>' +
      '  </div>' +
      '</div>';
  }

  /* ============================================================
     Глобальне делегування подій
     ============================================================ */
  function onClick(e) {
    var t = e.target;
    var closest = function (sel) { return t.closest(sel); };

    if (closest("[data-cart-toggle]")) { e.preventDefault(); openCart(); return; }
    if (closest("[data-cart-close]")) { closeCart(); return; }
    if (closest("[data-account-open]")) { e.preventDefault(); openAuth(); return; }
    var acct = closest("[data-account]");
    if (acct) { e.preventDefault(); if (isLoggedIn()) window.location.assign("/account"); else openAuth(); return; }
    if (closest("[data-auth-close]")) { closeAuth(); return; }

    var add = closest("[data-add-to-cart]");
    if (add) { e.preventDefault(); addToCart(add.getAttribute("data-add-to-cart")); openCart(); return; }

    var inc = closest("[data-qty-inc]"); if (inc) { var id = inc.getAttribute("data-qty-inc"); setQty(id, (cart.find(function(c){return c.id===id;})||{}).qty + 1); return; }
    var dec = closest("[data-qty-dec]"); if (dec) { var id2 = dec.getAttribute("data-qty-dec"); setQty(id2, (cart.find(function(c){return c.id===id2;})||{}).qty - 1); return; }
    var rm = closest("[data-rm]"); if (rm) { removeFromCart(rm.getAttribute("data-rm")); return; }

    if (closest("[data-google]")) { login({ name: "Гість", lastName: "", displayName: "Гість Google", email: "guest@gmail.com", provider: "google" }); window.location.assign("/account"); return; }

    /* кабінет */
    var play = closest("[data-play]"); if (play) { e.preventDefault(); openPlayer(play.getAttribute("data-play")); return; }
    if (closest("[data-player-close]")) { closePlayer(); return; }
    var ord = closest("[data-order]"); if (ord) { e.preventDefault(); accountOrderNum = ord.getAttribute("data-order"); renderAccountPage(); return; }
    if (closest("[data-order-back]")) { e.preventDefault(); accountOrderNum = null; renderAccountPage(); return; }
    if (closest("[data-account-logout], [data-logout]")) {
      e.preventDefault(); logout();
      if (document.querySelector("[data-account-page]")) window.location.assign("/"); else closeAuth();
      return;
    }
  }

  function onSubmit(e) {
    var form = e.target;
    if (form.matches("[data-auth-form]")) {
      e.preventDefault();
      var email = form.email.value.trim();
      if (email.toLowerCase() === DEMO_EMAIL) {
        login({ name: "Демо", lastName: "Користувач", displayName: "Демо", email: DEMO_EMAIL, provider: "email" });
      } else {
        var nm = email.split("@")[0] || "Користувач";
        login({ name: nm, lastName: "", displayName: nm, email: email, provider: "email" });
      }
      window.location.assign("/account");
      return;
    }
    if (form.matches("[data-checkout-form]")) {
      e.preventDefault();
      var buyer = { name: form.name.value.trim(), email: form.email.value.trim() };
      if (!isLoggedIn()) login({ name: buyer.name, displayName: buyer.name, email: buyer.email, provider: "guest" });
      addOrder(buyer);   // зберегти замовлення (читає кошик) ДО очищення
      clearCart();
      renderCheckoutDone();
      return;
    }
    if (form.matches("[data-profile-form]")) {
      e.preventDefault();
      user = user || {};
      user.name = form.firstName.value.trim();
      user.lastName = form.lastName.value.trim();
      user.displayName = form.displayName.value.trim() || user.name;
      user.email = form.email.value.trim();
      saveUser();
      renderAccountPage();
      var msg = document.querySelector("[data-profile-saved]");
      if (msg) { msg.hidden = false; setTimeout(function () { if (msg) msg.hidden = true; }, 2500); }
      return;
    }
  }

  /* ============================================================
     Плеєр (перегляд сольника з бібліотеки)
     ============================================================ */
  function injectPlayer() {
    if (document.getElementById("playerModal")) return;
    var el = document.createElement("div");
    el.className = "playermodal";
    el.id = "playerModal";
    el.setAttribute("aria-hidden", "true");
    el.innerHTML =
      '<div class="playermodal__backdrop" data-player-close></div>' +
      '<div class="playermodal__panel" role="dialog" aria-modal="true">' +
      '  <header class="playermodal__head"><h3 data-player-title>Перегляд</h3>' +
      '    <button class="playermodal__close" data-player-close aria-label="Закрити" data-hover>✕</button></header>' +
      '  <div class="playermodal__video"><iframe data-player-frame src="" title="Перегляд сольника" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>' +
      '  <footer class="playermodal__foot">' +
      '    <p class="playermodal__meta" data-player-meta></p>' +
      '    <p class="playermodal__desc" data-player-desc></p>' +
      '    <p class="playermodal__note">Це концепт — показано фрагмент. Повний сольник доступний на shatailo.com.</p>' +
      "  </footer></div>";
    document.body.appendChild(el);
  }
  function openPlayer(id) {
    var p = PRODUCTS[id]; if (!p) return;
    injectPlayer();
    var m = document.getElementById("playerModal");
    m.querySelector("[data-player-title]").textContent = (p.title || "Перегляд").replace("Сольник ", "");
    m.querySelector("[data-player-meta]").textContent = p.meta || "";
    m.querySelector("[data-player-desc]").textContent = p.desc || "";
    var startParam = p.start ? "&start=" + p.start : "";
    m.querySelector("[data-player-frame]").src = "https://www.youtube-nocookie.com/embed/" + p.trailer + "?autoplay=1&rel=0" + startParam;
    m.classList.add("is-open"); m.setAttribute("aria-hidden", "false"); document.body.style.overflow = "hidden";
  }
  function closePlayer() {
    var m = document.getElementById("playerModal");
    if (!m) return;
    m.classList.remove("is-open"); m.setAttribute("aria-hidden", "true");
    var f = m.querySelector("[data-player-frame]"); if (f) f.src = "";
    document.body.style.overflow = "";
  }

  /* ============================================================
     Кабінет /account (вкладки: Сольники / Замовлення / Профіль / Вийти)
     ============================================================ */
  var accountOrderNum = null;

  function accountTab() {
    var h = (location.hash || "").replace("#", "");
    return (h === "orders" || h === "profile") ? h : "library";
  }
  function accNav(active) {
    var tab = function (hash, label) {
      return '<a class="acc-tab' + (active === hash ? " is-active" : "") + '" href="#' + hash + '" data-hover>' + label + "</a>";
    };
    return '<nav class="acc-nav">' +
      tab("library", "Сольники") + tab("orders", "Замовлення") + tab("profile", "Профіль") +
      '<button class="acc-tab acc-tab--logout" data-account-logout type="button" data-hover>Вийти</button></nav>';
  }
  function accLibrary() {
    var lib = library();
    if (!lib.length) {
      return '<div class="acc-empty"><p>У вас поки немає куплених сольників.</p>' +
        '<a class="btn btn--solid" data-hover href="/#solnyky">Обрати сольник</a></div>';
    }
    return '<div class="acc-lib">' + lib.map(function (p) {
      return '<article class="acc-lib__card">' +
        '<div class="acc-lib__poster"><img src="' + p.poster + '" alt="">' +
          '<button class="acc-lib__play" data-play="' + p.id + '" aria-label="Дивитись"><span>▶</span></button></div>' +
        '<div class="acc-lib__body"><h3>' + p.title.replace("Сольник ", "") + "</h3>" +
          '<p class="acc-lib__info">' + (p.info || "") + "</p>" +
          '<button class="btn btn--solid btn--sm" data-play="' + p.id + '" data-hover>Дивитись</button></div></article>';
    }).join("") + "</div>";
  }
  function accOrders() {
    if (accountOrderNum) {
      var o = getOrder(accountOrderNum);
      if (!o) { accountOrderNum = null; return accOrders(); }
      var rows = o.items.map(function (it) {
        return "<tr><td>" + it.title + ' <span class="co-x">× ' + it.qty + "</span></td><td>" + uah(it.price * it.qty) + "</td></tr>";
      }).join("");
      return '<button class="acc-back" data-order-back data-hover>← Усі замовлення</button>' +
        '<h2 class="acc-h">Замовлення №' + o.num + "</h2>" +
        '<p class="acc-sub">' + o.date + " · " + o.status + "</p>" +
        '<table class="checkout__summary"><tbody>' + rows +
        '<tr class="checkout__summary-grand"><td>Загалом</td><td>' + uah(o.total) + "</td></tr></tbody></table>" +
        '<h3 class="acc-h acc-h--sm">Платіжні дані</h3>' +
        '<p class="acc-bill">' + (o.name || "—") + "<br>" + (o.email || "") + "</p>";
    }
    var os = getOrders();
    if (!os.length) {
      return '<div class="acc-empty"><p>Замовлень поки немає.</p>' +
        '<a class="btn btn--solid" data-hover href="/#solnyky">До сольників</a></div>';
    }
    var trs = os.map(function (o) {
      return "<tr>" +
        '<td data-label="Замовлення">№' + o.num + "</td>" +
        '<td data-label="Дата">' + o.date + "</td>" +
        '<td data-label="Статус"><span class="acc-status">' + o.status + "</span></td>" +
        '<td data-label="Сума">' + uah(o.total) + " · " + o.items.length + " шт.</td>" +
        '<td><button class="btn btn--ghost btn--sm" data-order="' + o.num + '" data-hover>Переглянути</button></td></tr>';
    }).join("");
    return '<div class="cartpage__table-wrap"><table class="acc-orders cartpage__table"><thead><tr><th>Замовлення</th><th>Дата</th><th>Статус</th><th>Сума</th><th></th></tr></thead><tbody>' + trs + "</tbody></table></div>";
  }
  function accProfile() {
    var u = user || {};
    return '<form class="acc-profile" data-profile-form>' +
      '<div class="acc-profile__row">' +
        '<label class="auth-field"><span>Ім’я</span><input type="text" name="firstName" value="' + (u.name || "") + '" placeholder="Ім’я"></label>' +
        '<label class="auth-field"><span>Прізвище</span><input type="text" name="lastName" value="' + (u.lastName || "") + '" placeholder="Прізвище"></label>' +
      "</div>" +
      '<label class="auth-field"><span>Відображуване ім’я</span><input type="text" name="displayName" value="' + (u.displayName || u.name || "") + '"><small class="acc-hint">Так ваше ім’я відображатиметься в кабінеті.</small></label>' +
      '<label class="auth-field"><span>E-mail адреса</span><input type="email" name="email" value="' + (u.email || "") + '"></label>' +
      '<fieldset class="acc-pass"><legend>Зміна пароля</legend>' +
        '<label class="auth-field"><span>Поточний пароль</span><input type="password" name="curpass" placeholder="залиште порожнім, щоб не змінювати"></label>' +
        '<label class="auth-field"><span>Новий пароль</span><input type="password" name="newpass"></label>' +
        '<label class="auth-field"><span>Повторіть новий пароль</span><input type="password" name="newpass2"></label>' +
      "</fieldset>" +
      '<div class="acc-profile__save"><button class="btn btn--solid" type="submit" data-hover>Зберегти зміни</button>' +
      '<span class="acc-saved" data-profile-saved hidden>✓ Збережено</span></div></form>';
  }
  function renderAccountPage() {
    var root = document.querySelector("[data-account-page]");
    if (!root) return;
    if (!isLoggedIn()) {
      root.innerHTML = '<div class="acc-empty"><p>Щоб переглянути кабінет, увійдіть.</p>' +
        '<button class="btn btn--solid" data-account-open type="button" data-hover>Увійти</button></div>';
      return;
    }
    var u = user || {};
    var tab = accountTab();
    var content = tab === "orders" ? accOrders() : (tab === "profile" ? accProfile() : accLibrary());
    root.innerHTML =
      '<p class="acc-welcome">Вітаємо, <b>' + (u.displayName || u.name || "друже") + "</b> 👋</p>" +
      accNav(tab) + '<div class="acc-content">' + content + "</div>";
  }

  /* ============================================================
     Ініціалізація
     ============================================================ */
  function syncAll() {
    updateBadges();
    if (document.getElementById("miniCart") && document.getElementById("miniCart").classList.contains("is-open")) renderMiniCart();
    renderCartPage();
    renderCheckout();
    renderAccountPage();
  }

  function init() {
    injectHeaderTools();
    injectMiniCart();
    injectAuthModal();

    document.addEventListener("click", onClick);
    document.addEventListener("submit", onSubmit);
    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      closeCart(); closeAuth(); closePlayer();
    });
    window.addEventListener("hashchange", function () {
      if (!document.querySelector("[data-account-page]")) return;
      accountOrderNum = null;
      renderAccountPage();
    });

    listeners.push(syncAll);
    syncAll();
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();

  /* публічний API */
  window.Store = {
    PRODUCTS: PRODUCTS,
    addToCart: addToCart, setQty: setQty, removeFromCart: removeFromCart, clearCart: clearCart,
    cartItems: cartItems, cartCount: cartCount, cartSubtotal: cartSubtotal, uah: uah,
    isLoggedIn: isLoggedIn, getUser: getUser, login: login, logout: logout,
    getOrders: getOrders, library: library, addOrder: addOrder,
    openCart: openCart, closeCart: closeCart, openAuth: openAuth, openPlayer: openPlayer
  };
})();
