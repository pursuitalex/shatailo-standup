/* ============================================================
   Спільний легкий скрипт для контент-сторінок (курсор, рік)
   ============================================================ */

/* ---------- кастомний курсор ---------- */
const cursor = document.getElementById("cursor");
const ring = document.getElementById("cursorRing");
if (cursor && ring && window.matchMedia("(hover: hover) and (pointer: fine)").matches) {
  const pos = { x: -100, y: -100, rx: -100, ry: -100 };
  window.addEventListener("pointermove", (e) => {
    pos.x = e.clientX;
    pos.y = e.clientY;
  }, { passive: true });

  const tick = () => {
    pos.rx += (pos.x - pos.rx) * 0.18;
    pos.ry += (pos.y - pos.ry) * 0.18;
    cursor.style.transform = `translate(${pos.x - 4}px, ${pos.y - 4}px)`;
    ring.style.transform = `translate(${pos.rx - 19}px, ${pos.ry - 19}px)`;
    requestAnimationFrame(tick);
  };
  tick();

  document.addEventListener("pointerover", (e) => {
    if (e.target.closest("a, button, [data-hover]")) ring.classList.add("is-hover");
  });
  document.addEventListener("pointerout", (e) => {
    if (e.target.closest("a, button, [data-hover]")) ring.classList.remove("is-hover");
  });
}

/* ---------- рік у футері ---------- */
const yearEl = document.getElementById("footerYear");
if (yearEl) yearEl.textContent = new Date().getFullYear();
