<?php
/**
 * Plugin Name: Shatailo Woo Dark Skin
 * Description: Темний скін для сторінок WooCommerce (кошик / оформлення / кабінет Woo / подяка)
 *              під дизайн new.shatailo.com. Лише CSS, підключається тільки на Woo-сторінках.
 * Version: 0.1
 * Author: Shatailo new frontend
 *
 * УСТАНОВКА: покласти цей файл у wp-content/mu-plugins/ (створити папку, якщо немає).
 *            mu-plugins активуються самі. Щоб прибрати скін — просто видалити файл.
 */

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
  // тільки на сторінках WooCommerce (кошик / checkout / thank-you / кабінет)
  if (!function_exists('is_cart')) return;
  if (!(is_cart() || is_checkout() || is_account_page())) return;

  // шрифти бренду
  wp_enqueue_style(
    'shatailo-fonts',
    'https://fonts.googleapis.com/css2?family=Unbounded:wght@400;600;800&family=Inter:wght@400;500;600&display=swap',
    array(),
    null
  );

  // сам скін (scoped на body.woocommerce-* — решту сайту не чіпає)
  wp_register_style('shatailo-woo-skin', false);
  wp_enqueue_style('shatailo-woo-skin');

  $css = <<<'CSS'
body.woocommerce-cart,
body.woocommerce-checkout,
body.woocommerce-account,
body.woocommerce-order-received,
body.woocommerce-page {
  background: #060606 !important;
  color: #f4f2ec !important;
  font-family: "Inter", sans-serif;
}
body.woocommerce-page .elementor-section,
body.woocommerce-page .elementor-column,
body.woocommerce-page .elementor-widget-wrap,
body.woocommerce-page .e-con,
body.woocommerce-page .e-con-inner { background-color: transparent !important; }
body.woocommerce-page h1,
body.woocommerce-page h2,
body.woocommerce-page h3,
body.woocommerce-page .woocommerce-column__title,
body.woocommerce-page #order_review_heading {
  font-family: "Unbounded", sans-serif !important;
  color: #f4f2ec !important;
  text-transform: uppercase;
}
body.woocommerce-page p,
body.woocommerce-page label,
body.woocommerce-page th,
body.woocommerce-page td,
body.woocommerce-page li,
body.woocommerce-page address,
body.woocommerce-page .woocommerce-Price-amount { color: #f4f2ec !important; }
body.woocommerce-page a { color: #f2ff00 !important; }
body.woocommerce-page a:hover { color: #f4f2ec !important; }
body.woocommerce-page ::placeholder { color: #8d8d86 !important; }
body.woocommerce-page table.shop_table,
body.woocommerce-page .cart_totals table {
  background: #0c0c0c !important;
  border: 1px solid rgba(255, 255, 255, 0.1) !important;
  border-radius: 6px;
  overflow: hidden;
}
body.woocommerce-page table.shop_table th,
body.woocommerce-page table.shop_table td {
  background: transparent !important;
  border-color: rgba(255, 255, 255, 0.1) !important;
}
body.woocommerce-page table.shop_table thead th {
  font-family: "Unbounded", sans-serif !important;
  text-transform: uppercase;
  font-size: 0.8rem;
  letter-spacing: 0.04em;
}
body.woocommerce-page input.input-text,
body.woocommerce-page textarea,
body.woocommerce-page select,
body.woocommerce-page .select2-selection {
  background: #0c0c0c !important;
  color: #f4f2ec !important;
  border: 1px solid rgba(255, 255, 255, 0.18) !important;
  border-radius: 4px !important;
}
body.woocommerce-page input.input-text:focus,
body.woocommerce-page textarea:focus,
body.woocommerce-page .select2-container--focus .select2-selection {
  border-color: #f2ff00 !important;
  outline: none !important;
}
body.woocommerce-page .button,
body.woocommerce-page button.button,
body.woocommerce-page input.button,
body.woocommerce-page a.button,
body.woocommerce-page #place_order,
body.woocommerce-page .checkout-button,
body.woocommerce-page .wc-block-components-button {
  background: #f2ff00 !important;
  color: #0a0a0a !important;
  border: 1px solid #f2ff00 !important;
  font-family: "Unbounded", sans-serif !important;
  text-transform: uppercase;
  font-weight: 600 !important;
  font-size: 0.82rem !important;
  letter-spacing: 0.06em !important;
  border-radius: 2px !important;
  transition: background 0.25s, color 0.25s, border-color 0.25s, box-shadow 0.25s !important;
}
body.woocommerce-page .button:hover,
body.woocommerce-page button.button:hover,
body.woocommerce-page input.button:hover,
body.woocommerce-page a.button:hover,
body.woocommerce-page #place_order:hover,
body.woocommerce-page .checkout-button:hover {
  background: transparent !important;
  color: #f2ff00 !important;
}
body.woocommerce-page button[name="update_cart"] {
  background: transparent !important;
  color: #f4f2ec !important;
  border: 1px solid rgba(255, 255, 255, 0.25) !important;
}
body.woocommerce-page button[name="update_cart"]:hover {
  border-color: #f2ff00 !important;
  color: #f2ff00 !important;
}
body.woocommerce-page .woocommerce-message,
body.woocommerce-page .woocommerce-info,
body.woocommerce-page .woocommerce-error,
body.woocommerce-page .cart-empty {
  background: #0c0c0c !important;
  border-top: 3px solid #f2ff00 !important;
  color: #f4f2ec !important;
}
body.woocommerce-page .woocommerce-message::before,
body.woocommerce-page .woocommerce-info::before { color: #f2ff00 !important; }
body.woocommerce-page #order_review,
body.woocommerce-page .woocommerce-checkout-review-order,
body.woocommerce-page #payment,
body.woocommerce-page #payment ul.payment_methods,
body.woocommerce-page .woocommerce-order,
body.woocommerce-account .woocommerce-MyAccount-content,
body.woocommerce-account .woocommerce-MyAccount-navigation li {
  background: #0c0c0c !important;
  border-color: rgba(255, 255, 255, 0.1) !important;
  border-radius: 6px;
}
body.woocommerce-account .woocommerce-MyAccount-navigation li a { color: #f4f2ec !important; }
body.woocommerce-account .woocommerce-MyAccount-navigation li.is-active a { color: #f2ff00 !important; }

/* --- checkout: платіжні методи та опис (був світлий) --- */
body.woocommerce-checkout #payment { background: #0c0c0c !important; }
body.woocommerce-checkout #payment ul.payment_methods,
body.woocommerce-checkout #payment ul.payment_methods li { background: transparent !important; }
body.woocommerce-checkout #payment div.payment_box,
body.woocommerce-checkout #payment .payment_box {
  background: #141414 !important;
  color: #f4f2ec !important;
}
body.woocommerce-checkout #payment div.payment_box::before {
  border-bottom-color: #141414 !important;
}

/* --- checkout: кнопка «Підтвердити замовлення» (куленепробивно) --- */
body.woocommerce-page #payment #place_order,
body.woocommerce-checkout #order_review #place_order,
body.woocommerce-page button#place_order.button.alt {
  background: #f2ff00 !important;
  background-color: #f2ff00 !important;
  color: #0a0a0a !important;
  -webkit-text-fill-color: #0a0a0a !important;
  border: 1px solid #f2ff00 !important;
  border-radius: 2px !important;
  font-family: "Unbounded", sans-serif !important;
  font-weight: 600 !important;
  font-size: 0.82rem !important;
  letter-spacing: 0.06em !important;
  text-transform: uppercase !important;
  padding: 16px 28px !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  width: auto !important;
  opacity: 1 !important;
}
body.woocommerce-page #payment #place_order:hover,
body.woocommerce-checkout #order_review #place_order:hover {
  background: #f2ff00 !important;
  background-color: #f2ff00 !important;
  color: #0a0a0a !important;
  -webkit-text-fill-color: #0a0a0a !important;
  box-shadow: 0 0 32px rgba(242, 255, 0, 0.35) !important;
}

/* --- оверлей завантаження WooCommerce (був білий → робимо темним) --- */
body.woocommerce-page .blockUI.blockOverlay { background: #060606 !important; }

/* ============================================================
   Міні-кошик (Elementor side-cart) — під наш стиль
   ============================================================ */
/* нативні змінні віджета Elementor menu-cart */
body.woocommerce-page .elementor-menu-cart__main,
body.woocommerce-page .elementor-widget-woocommerce-menu-cart {
  --cart-background-color: #0c0c0c !important;
  --menu-cart-subtotal-color: #f4f2ec !important;
  --product-price-color: #f2ff00 !important;
  --remove-item-button-color: #8d8d86 !important;
  --toggle-button-icon-color: #f4f2ec !important;
  --cart-close-button-color: #f2ff00 !important;
  --view-cart-button-text-color: #f4f2ec !important;
  --view-cart-button-background-color: transparent !important;
  --view-cart-button-hover-text-color: #0a0a0a !important;
  --view-cart-button-hover-background-color: #f4f2ec !important;
  --checkout-button-text-color: #0a0a0a !important;
  --checkout-button-background-color: #f2ff00 !important;
  --checkout-button-hover-text-color: #0a0a0a !important;
  --checkout-button-hover-background-color: #f4f2ec !important;
}
/* панель */
body.woocommerce-page .elementor-menu-cart__main,
body.woocommerce-page .elementor-menu-cart__container {
  background: #0c0c0c !important;
  color: #f4f2ec !important;
}
body.woocommerce-page .elementor-menu-cart__main { border-left: 1px solid rgba(255, 255, 255, 0.1) !important; }
/* товари */
body.woocommerce-page .woocommerce-mini-cart-item,
body.woocommerce-page .elementor-menu-cart__product {
  border-color: rgba(255, 255, 255, 0.1) !important;
  color: #f4f2ec !important;
}
body.woocommerce-page .widget_shopping_cart_content a:not(.remove) { color: #f4f2ec !important; }
body.woocommerce-page .widget_shopping_cart_content a:not(.remove):hover { color: #f2ff00 !important; }
body.woocommerce-page .widget_shopping_cart_content .quantity,
body.woocommerce-page .widget_shopping_cart_content .woocommerce-Price-amount { color: #f4f2ec !important; }
/* хрестик видалення */
body.woocommerce-page .woocommerce-mini-cart a.remove,
body.woocommerce-page .elementor-menu-cart__product-remove a {
  color: #8d8d86 !important;
  background: transparent !important;
}
body.woocommerce-page .woocommerce-mini-cart a.remove:hover { color: #f2ff00 !important; }
/* підсумок */
body.woocommerce-page .woocommerce-mini-cart__total,
body.woocommerce-page .elementor-menu-cart__subtotal {
  border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
  color: #f4f2ec !important;
  font-family: "Unbounded", sans-serif !important;
}
body.woocommerce-page .woocommerce-mini-cart__total .amount,
body.woocommerce-page .elementor-menu-cart__subtotal .amount,
body.woocommerce-page .elementor-menu-cart__subtotal .woocommerce-Price-amount { color: #f2ff00 !important; }
/* кнопки міні-кошика */
body.woocommerce-page .woocommerce-mini-cart__buttons .button,
body.woocommerce-page .elementor-menu-cart__footer-buttons .elementor-button {
  font-family: "Unbounded", sans-serif !important;
  text-transform: uppercase;
  font-weight: 600 !important;
  border-radius: 999px !important;
  border: 1px solid #f2ff00 !important;
}
/* «Переглянути кошик» — вторинна (контур) */
body.woocommerce-page .woocommerce-mini-cart__buttons .button:not(.checkout) {
  background: transparent !important;
  color: #f4f2ec !important;
  border-color: rgba(255, 255, 255, 0.25) !important;
}
body.woocommerce-page .woocommerce-mini-cart__buttons .button:not(.checkout):hover {
  border-color: #f2ff00 !important;
  color: #f2ff00 !important;
}
/* «Оформлення замовлення» — основна (жовта) */
body.woocommerce-page .woocommerce-mini-cart__buttons .button.checkout {
  background: #f2ff00 !important;
  color: #0a0a0a !important;
  border-color: #f2ff00 !important;
}
/* кнопка закриття панелі */
body.woocommerce-page .elementor-menu-cart__close-button { color: #f2ff00 !important; }

/* ============================================================
   Сторінка скидання пароля (my-account) — у стилі нашого попапу
   ============================================================ */
body.woocommerce-account form.woocommerce-ResetPassword {
  max-width: 440px; margin: 6px auto 0; background: #0c0c0c;
  border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 4px; padding: 32px 30px;
}
body.woocommerce-account form.woocommerce-ResetPassword > p:first-child {
  color: #8d8d86 !important; font-family: "Inter", sans-serif; font-size: 0.9rem; line-height: 1.5; margin: 0 0 20px !important;
}
body.woocommerce-account form.woocommerce-ResetPassword .form-row {
  width: 100% !important; float: none !important; clear: none !important; margin: 0 0 16px !important;
}
body.woocommerce-account form.woocommerce-ResetPassword label {
  display: block; font-family: "Unbounded", sans-serif !important; font-size: 0.7rem;
  letter-spacing: 0.12em; text-transform: uppercase; color: #8d8d86 !important; margin: 0 0 7px;
}
body.woocommerce-account form.woocommerce-ResetPassword .password-input { display: block; position: relative; }
body.woocommerce-account form.woocommerce-ResetPassword .show-password-input { color: #8d8d86; }
body.woocommerce-account form.woocommerce-ResetPassword .show-password-input.display-password { color: #f2ff00; }
body.woocommerce-account form.woocommerce-ResetPassword .button { width: 100% !important; margin-top: 4px; }
/* індикатор складності пароля — у нашій палітрі (замість зеленого/червоного) */
body.woocommerce-account .woocommerce-password-strength {
  font-family: "Unbounded", sans-serif !important; font-size: 0.66rem !important; letter-spacing: 0.08em;
  text-transform: uppercase; text-align: left !important; background: #141414 !important;
  border: 1px solid rgba(255, 255, 255, 0.14) !important; border-radius: 2px !important;
  padding: 9px 12px !important; margin: 8px 0 0 !important; color: #f4f2ec !important;
}
body.woocommerce-account .woocommerce-password-strength.short,
body.woocommerce-account .woocommerce-password-strength.bad { color: #ff5555 !important; border-color: rgba(255, 85, 85, 0.4) !important; }
body.woocommerce-account .woocommerce-password-strength.good { color: #f4f2ec !important; }
body.woocommerce-account .woocommerce-password-strength.strong { color: #f2ff00 !important; border-color: rgba(242, 255, 0, 0.4) !important; }
body.woocommerce-account .woocommerce-password-hint {
  display: block; color: #8d8d86 !important; font-family: "Inter", sans-serif;
  font-size: 0.8rem; line-height: 1.5; margin: 8px 0 0 !important;
}
CSS;

  wp_add_inline_style('shatailo-woo-skin', $css);
}, 100);
