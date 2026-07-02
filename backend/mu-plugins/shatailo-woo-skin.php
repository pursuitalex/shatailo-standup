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
  border-radius: 999px !important;
  transition: background 0.2s, color 0.2s;
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
CSS;

  wp_add_inline_style('shatailo-woo-skin', $css);
}, 100);
