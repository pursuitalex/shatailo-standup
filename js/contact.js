/* ============================================================
   Контакт-форма. За замовчуванням працює через поштовий застосунок
   (mailto-фолбек). Якщо вписати ключ Web3Forms у hidden-поле
   access_key — слатиме прямо з сайту без бекенду.
   Отримати безкоштовний ключ: https://web3forms.com (вписати email).
   ============================================================ */
const FALLBACK_EMAIL = "egor.shatailo@gmail.com";
const form = document.getElementById("contactForm");
const statusEl = document.getElementById("cformStatus");

function setStatus(msg, type) {
  if (!statusEl) return;
  statusEl.textContent = msg;
  statusEl.className = "cform__status" + (type ? " is-" + type : "");
}

if (form) {
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());

    // honeypot: бот заповнив приховане поле
    if (data.botcheck) return;

    const name = (data.name || "").trim();
    const email = (data.email || "").trim();
    const message = (data.message || "").trim();
    if (!name || !email || !message) {
      setStatus("Заповніть, будь ласка, всі поля.", "err");
      return;
    }

    const key = (form.querySelector("[name=access_key]")?.value || "").trim();
    const keyReady = key && key !== "WEB3FORMS_KEY";

    // Фолбек без ключа: відкриваємо поштовий застосунок
    if (!keyReady) {
      const subject = encodeURIComponent("Повідомлення з сайту Єгора Шатайла");
      const body = encodeURIComponent(`Ім'я: ${name}\nEmail: ${email}\n\n${message}`);
      window.location.href = `mailto:${FALLBACK_EMAIL}?subject=${subject}&body=${body}`;
      setStatus("Відкриваємо ваш поштовий застосунок…", "ok");
      return;
    }

    // Відправка через Web3Forms
    const btn = form.querySelector("button[type=submit]");
    if (btn) btn.disabled = true;
    setStatus("Надсилаємо…", "");
    try {
      const res = await fetch("https://api.web3forms.com/submit", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(data),
      });
      const json = await res.json();
      if (json.success) {
        form.reset();
        setStatus("Дякую! Повідомлення надіслано.", "ok");
      } else {
        setStatus(`Не вдалося надіслати. Напишіть на ${FALLBACK_EMAIL}`, "err");
      }
    } catch (err) {
      setStatus(`Помилка мережі. Напишіть на ${FALLBACK_EMAIL}`, "err");
    } finally {
      if (btn) btn.disabled = false;
    }
  });
}
