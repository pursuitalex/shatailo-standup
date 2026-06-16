/* ============================================================
   ШАТАЙЛО one-pager — GSAP-анімації, UI, інтеграція 3D-сцени
   ============================================================ */
import { createHeroScene } from "./scene.js";

gsap.registerPlugin(ScrollTrigger, ScrollToPlugin);

const prefersReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const isDesktop = window.matchMedia("(min-width: 900px)").matches;

/* ---------- 3D-сцена ---------- */
const heroScene = createHeroScene(document.getElementById("scene"));

/* ---------- розбивка заголовка на літери ---------- */
document.querySelectorAll(".hero__title .split").forEach((el) => {
  const text = el.textContent;
  el.textContent = "";
  [...text].forEach((ch) => {
    const span = document.createElement("span");
    span.className = "char";
    span.textContent = ch === " " ? " " : ch;
    el.appendChild(span);
  });
});

/* ---------- прелоадер ---------- */
const counter = { v: 0 };
const countEl = document.getElementById("preloaderCount");
const barEl = document.getElementById("preloaderBar");
const preloader = document.getElementById("preloader");

const intro = gsap.timeline();

intro
  .to(counter, {
    v: 100,
    duration: prefersReduced ? 0.1 : 1.6,
    ease: "power2.inOut",
    onUpdate: () => {
      countEl.textContent = Math.round(counter.v);
      barEl.style.transform = `scaleX(${counter.v / 100})`;
    },
  })
  .to(preloader, {
    yPercent: -100,
    duration: 0.9,
    ease: "power4.inOut",
    onComplete: () => preloader.remove(),
  })
  /* інтро hero */
  .from(".hero__title .char", {
    yPercent: 120,
    rotate: 6,
    duration: 1.1,
    stagger: 0.035,
    ease: "power4.out",
  }, "-=0.45")
  .from("#heroLabel", { opacity: 0, y: 20, duration: 0.7, ease: "power3.out" }, "-=0.7")
  .from("#heroMeta", { opacity: 0, y: 20, duration: 0.7, ease: "power3.out" }, "-=0.55")
  .from("#heroActions .btn", {
    opacity: 0,
    y: 26,
    duration: 0.7,
    stagger: 0.12,
    ease: "power3.out",
  }, "-=0.5")
  .from(".marquee--hero", { opacity: 0, duration: 0.8 }, "-=0.4")
  .from("#scrollHint", { opacity: 0, duration: 0.8 }, "-=0.6")
  /* transform хедера належить CSS (.is-hidden), тому інтро — лише opacity */
  .from(".header", { opacity: 0, duration: 0.8, ease: "power2.out" }, "-=0.8");

/* ---------- курсор ---------- */
const cursor = document.getElementById("cursor");
const ring = document.getElementById("cursorRing");
if (window.matchMedia("(hover: hover) and (pointer: fine)").matches) {
  const pos = { x: -100, y: -100, rx: -100, ry: -100 };
  window.addEventListener("pointermove", (e) => {
    pos.x = e.clientX;
    pos.y = e.clientY;
  }, { passive: true });

  gsap.ticker.add(() => {
    pos.rx += (pos.x - pos.rx) * 0.18;
    pos.ry += (pos.y - pos.ry) * 0.18;
    cursor.style.transform = `translate(${pos.x - 4}px, ${pos.y - 4}px)`;
    ring.style.transform = `translate(${pos.rx - 19}px, ${pos.ry - 19}px)`;
  });

  document.addEventListener("pointerover", (e) => {
    if (e.target.closest("[data-hover], a, button")) ring.classList.add("is-hover");
  });
  document.addEventListener("pointerout", (e) => {
    if (e.target.closest("[data-hover], a, button")) ring.classList.remove("is-hover");
  });
}

/* ---------- хедер: ховається при скролі вниз ---------- */
const header = document.getElementById("header");
ScrollTrigger.create({
  start: 60,
  onUpdate: (self) => {
    header.classList.toggle("is-hidden", self.direction === 1 && self.scroll() > 300);
    header.classList.toggle("is-scrolled", self.scroll() > 60);
  },
});

/* ---------- скрол-прогрес ---------- */
gsap.to("#scrollProgress", {
  scaleX: 1,
  ease: "none",
  scrollTrigger: { start: 0, end: "max", scrub: 0.3 },
});

/* ---------- hero: камера від'їжджає, контент спливає ---------- */
ScrollTrigger.create({
  trigger: "#hero",
  start: "top top",
  end: "bottom top",
  scrub: 0.4,
  onUpdate: (self) => {
    heroScene.progress = self.progress;
  },
});
gsap.to(".hero__content", {
  yPercent: -18,
  opacity: 0.15,
  ease: "none",
  scrollTrigger: { trigger: "#hero", start: "top top", end: "75% top", scrub: 0.4 },
});

/* пауза рендера, коли hero не видно */
ScrollTrigger.create({
  trigger: "#hero",
  start: "top bottom",
  end: "bottom top",
  onToggle: (self) => { heroScene.paused = !self.isActive; },
});

/* ---------- маркі-стрічки: безшовний нескінченний рух ---------- */
function initMarquee(track) {
  // скидаємо попередній стан (для re-init на resize / після шрифтів)
  if (track._marqueeTween) track._marqueeTween.kill();
  if (track._marqueeBase) {
    track.replaceChildren(...track._marqueeBase.map((n) => n.cloneNode(true)));
  } else {
    track._marqueeBase = Array.from(track.children).map((n) => n.cloneNode(true));
  }

  const speed = parseFloat(track.dataset.speed) || 24; // секунд на одну плитку
  const container = track.parentElement;
  const tile = track.children[0];
  if (!tile) return;

  // ширина однієї повторюваної плитки (один <span>) = крок безшовного циклу
  const unitWidth = tile.getBoundingClientRect().width;
  if (!unitWidth) return;

  // доклоновуємо плитки, доки стрічка не перекриває в'юпорт + запас на крок
  let guard = 0;
  while (track.scrollWidth < container.offsetWidth + unitWidth * 2 && guard < 50) {
    track.appendChild(tile.cloneNode(true));
    guard++;
  }

  if (prefersReduced) return; // без руху для reduced-motion

  gsap.set(track, { x: 0 });
  track._marqueeTween = gsap.to(track, {
    x: -unitWidth,
    duration: speed,
    ease: "none",
    repeat: -1,
    // тримаємо x у межах [-unitWidth, 0) → стик плиток ідеально безшовний
    modifiers: { x: gsap.utils.unitize(gsap.utils.wrap(-unitWidth, 0), "px") },
  });
}

function initMarquees() {
  document.querySelectorAll("[data-marquee]").forEach(initMarquee);
}

if (document.fonts && document.fonts.ready) {
  document.fonts.ready.then(initMarquees);
} else {
  initMarquees();
}

let marqueeResizeTimer;
window.addEventListener("resize", () => {
  clearTimeout(marqueeResizeTimer);
  marqueeResizeTimer = setTimeout(initMarquees, 200);
});

/* ---------- заголовки секцій: рядки виїжджають ---------- */
document.querySelectorAll(".reveal-line > span").forEach((line) => {
  gsap.from(line, {
    yPercent: 110,
    duration: 1.1,
    ease: "power4.out",
    scrollTrigger: { trigger: line, start: "top 88%" },
  });
});

/* ---------- universal reveal ---------- */
document.querySelectorAll(".reveal").forEach((el) => {
  gsap.to(el, {
    opacity: 1,
    y: 0,
    duration: 0.9,
    ease: "power3.out",
    scrollTrigger: { trigger: el, start: "top 88%" },
  });
});

/* ---------- великий екран: розкриття маскою + паралакс-зум ---------- */
const featureMedia = document.getElementById("featureMedia");
if (featureMedia && !prefersReduced) {
  const featureImg = featureMedia.querySelector("img");
  // розкриття маскою (clip-path знизу вгору) при вході в кадр
  gsap.fromTo(
    featureMedia,
    { clipPath: "inset(100% 0% 0% 0%)" },
    {
      clipPath: "inset(0% 0% 0% 0%)",
      duration: 1.5,
      ease: "power4.out",
      scrollTrigger: { trigger: "#feature", start: "top 80%" },
    }
  );
  // паралакс-зум зображення на скрол
  gsap.fromTo(
    featureImg,
    { scale: 1.18, yPercent: -3 },
    {
      scale: 1,
      yPercent: 3,
      ease: "none",
      scrollTrigger: { trigger: "#feature", start: "top bottom", end: "bottom top", scrub: 0.5 },
    }
  );
}

/* ---------- сольники: горизонтальний пін (десктоп) ---------- */
const track = document.getElementById("specialsTrack");
const pin = document.getElementById("specialsPin");

const mm = gsap.matchMedia();
mm.add("(min-width: 900px) and (prefers-reduced-motion: no-preference)", () => {
  const getDistance = () => track.scrollWidth - window.innerWidth;
  const tween = gsap.to(track, {
    x: () => -getDistance(),
    ease: "none",
    scrollTrigger: {
      trigger: pin,
      start: "top 12%",
      end: () => `+=${getDistance()}`,
      pin: true,
      scrub: 0.6,
      invalidateOnRefresh: true,
      anticipatePin: 1,
      // пін зсуває позиції секцій нижче (about, tour) — рахуємо його ПЕРШИМ,
      // щоб reveal-и тих секцій отримали коректний старт і анімувались у кадрі
      refreshPriority: 1,
      onUpdate: (self) => {
        document.getElementById("specialsProgress").style.transform =
          `scaleX(${self.progress})`;
      },
    },
  });
  return () => tween.scrollTrigger?.kill();
});

/* картки на мобільному — простий стаггер */
mm.add("(max-width: 899px)", () => {
  document.querySelectorAll(".card").forEach((card) => {
    gsap.from(card, {
      opacity: 0,
      y: 60,
      duration: 0.9,
      ease: "power3.out",
      scrollTrigger: { trigger: card, start: "top 90%" },
    });
  });
});

/* ---------- 3D-тілт карток + позиція бліку ---------- */
if (isDesktop && !prefersReduced) {
  document.querySelectorAll("[data-tilt]").forEach((card) => {
    // перспектива — один раз і назавжди, щоб вона НЕ анімувалась від ~0
    // (інакше постер із translateZ на мить спалахує величезним)
    gsap.set(card, { transformPerspective: 900 });
    let raf = 0;
    card.addEventListener("pointermove", (e) => {
      cancelAnimationFrame(raf);
      raf = requestAnimationFrame(() => {
        const r = card.getBoundingClientRect();
        const px = (e.clientX - r.left) / r.width;
        const py = (e.clientY - r.top) / r.height;
        card.style.setProperty("--mx", `${px * 100}%`);
        card.style.setProperty("--my", `${py * 100}%`);
        gsap.to(card, {
          rotateY: (px - 0.5) * 7,
          rotateX: (0.5 - py) * 7,
          duration: 0.5,
          ease: "power2.out",
        });
      });
    });
    card.addEventListener("pointerleave", () => {
      gsap.to(card, { rotateX: 0, rotateY: 0, duration: 0.8, ease: "elastic.out(1, 0.5)" });
    });
  });
}

/* ---------- про Єгора: паралакс фото + фоновий текст ---------- */
gsap.to("#aboutBgText", {
  xPercent: -22,
  ease: "none",
  scrollTrigger: { trigger: "#about", start: "top bottom", end: "bottom top", scrub: 0.5 },
});
gsap.from("#aboutPhoto img", {
  yPercent: 12,
  scale: 1.12,
  ease: "none",
  scrollTrigger: { trigger: "#aboutPhoto", start: "top bottom", end: "bottom top", scrub: 0.5 },
});

/* ---------- лічильники ---------- */
document.querySelectorAll("[data-count]").forEach((el) => {
  const target = parseInt(el.dataset.count, 10);
  const obj = { v: 0 };
  ScrollTrigger.create({
    trigger: el,
    start: "top 88%",
    once: true,
    onEnter: () =>
      gsap.to(obj, {
        v: target,
        duration: 1.6,
        ease: "power2.out",
        onUpdate: () => { el.textContent = Math.round(obj.v); },
      }),
  });
});

/* ---------- штамп СОЛД АУТ ---------- */
gsap.from(".tour__stamp", {
  scale: 2.4,
  opacity: 0,
  rotate: 18,
  duration: 0.7,
  ease: "back.out(2.5)",
  scrollTrigger: { trigger: ".tour__stamp", start: "top 85%" },
});

/* ---------- магнітні кнопки ---------- */
if (isDesktop && !prefersReduced) {
  document.querySelectorAll("[data-magnetic]").forEach((btn) => {
    btn.addEventListener("pointermove", (e) => {
      const r = btn.getBoundingClientRect();
      const dx = e.clientX - (r.left + r.width / 2);
      const dy = e.clientY - (r.top + r.height / 2);
      gsap.to(btn, { x: dx * 0.25, y: dy * 0.35, duration: 0.4, ease: "power2.out" });
    });
    btn.addEventListener("pointerleave", () => {
      gsap.to(btn, { x: 0, y: 0, duration: 0.7, ease: "elastic.out(1, 0.4)" });
    });
  });
}

/* ---------- плавні якорі ---------- */
document.querySelectorAll('a[href^="#"]').forEach((a) => {
  a.addEventListener("click", (e) => {
    const target = document.querySelector(a.getAttribute("href"));
    if (!target) return;
    e.preventDefault();
    gsap.to(window, {
      scrollTo: { y: target, offsetY: 0 },
      duration: 1.1,
      ease: "power3.inOut",
    });
  });
});

/* ---------- модалка фрагментів ---------- */
const modal = document.getElementById("modal");
const modalFrame = document.getElementById("modalFrame");
const modalTitle = document.getElementById("modalTitle");
const modalBuy = document.getElementById("modalBuy");
const backdrop = document.getElementById("modalBackdrop");
const panel = modal.querySelector(".modal__panel");
let lastFocus = null;

function openModal(ytId, title, buyHref, start) {
  lastFocus = document.activeElement;
  modalTitle.textContent = title || "Фрагмент виступу";
  if (buyHref) modalBuy.href = buyHref;
  const startParam = start ? `&start=${parseInt(start, 10)}` : "";
  modalFrame.src = `https://www.youtube-nocookie.com/embed/${ytId}?autoplay=1&rel=0${startParam}`;
  modal.classList.add("is-open");
  modal.setAttribute("aria-hidden", "false");
  document.body.style.overflow = "hidden";
  gsap.timeline()
    .to(backdrop, { opacity: 1, duration: 0.35, ease: "power2.out" })
    .to(panel, { opacity: 1, y: 0, scale: 1, duration: 0.5, ease: "power3.out" }, "-=0.15");
  document.getElementById("modalClose").focus();
}

function closeModal() {
  gsap.timeline({
    onComplete: () => {
      modal.classList.remove("is-open");
      modal.setAttribute("aria-hidden", "true");
      modalFrame.src = "";
      document.body.style.overflow = "";
      lastFocus?.focus();
    },
  })
    .to(panel, { opacity: 0, y: 40, scale: 0.97, duration: 0.35, ease: "power2.in" })
    .to(backdrop, { opacity: 0, duration: 0.3 }, "-=0.15");
}

document.querySelectorAll("[data-trailer]").forEach((btn) => {
  btn.addEventListener("click", () => {
    const card = btn.closest(".card");
    const buyLink = card?.querySelector('a[href*="shatailo.com/shop"]')?.href
      || "https://shatailo.com/shop/video/solnyk-kosmichnyy-ahui/";
    openModal(btn.dataset.trailer, btn.dataset.trailerTitle, buyLink, btn.dataset.trailerStart);
  });
});
document.getElementById("modalClose").addEventListener("click", closeModal);
backdrop.addEventListener("click", closeModal);
window.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && modal.classList.contains("is-open")) closeModal();
});

/* ---------- перерахунок після завантаження зображень ---------- */
window.addEventListener("load", () => ScrollTrigger.refresh());
