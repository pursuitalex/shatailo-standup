/* ============================================================
   Hero-сцена: сценічні прожектори, мікрофон, пил у промені
   Експортує об'єкт керування для main.js (скрол / згортання)
   ============================================================ */
import * as THREE from "three";

const YELLOW = new THREE.Color("#f2ff00");
const WARM = new THREE.Color("#fffbe0");

export function createHeroScene(canvas) {
  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({
      canvas,
      antialias: true,
      alpha: true,
      powerPreference: "high-performance",
    });
  } catch (e) {
    canvas.style.display = "none";
    return { progress: 0, dispose() {} };
  }

  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.75));
  renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);

  // даємо браузеру шанс відновити втрачений GPU-контекст
  canvas.addEventListener("webglcontextlost", (e) => e.preventDefault());

  const scene = new THREE.Scene();
  scene.fog = new THREE.Fog(0x060606, 6, 22);

  const camera = new THREE.PerspectiveCamera(
    42,
    canvas.clientWidth / canvas.clientHeight,
    0.1,
    60
  );
  camera.position.set(0, 1.7, 8.5);

  const root = new THREE.Group();
  scene.add(root);

  /* -------- світло -------- */
  scene.add(new THREE.AmbientLight(0x404040, 0.5));

  const spot = new THREE.SpotLight(0xfff8c4, 90, 26, 0.42, 0.55, 1.6);
  spot.position.set(0, 8.5, 1.5);
  spot.target.position.set(0, 0.8, 0);
  root.add(spot, spot.target);

  const rim = new THREE.PointLight(YELLOW, 14, 12);
  rim.position.set(-3.5, 2.2, -2.5);
  root.add(rim);

  /* -------- підлога -------- */
  const floor = new THREE.Mesh(
    new THREE.CircleGeometry(16, 48),
    new THREE.MeshStandardMaterial({
      color: 0x0a0a0a,
      roughness: 0.45,
      metalness: 0.55,
    })
  );
  floor.rotation.x = -Math.PI / 2;
  root.add(floor);

  const grid = new THREE.GridHelper(34, 34, 0x1c1c14, 0x101010);
  grid.position.y = 0.001;
  grid.material.transparent = true;
  grid.material.opacity = 0.35;
  root.add(grid);

  /* -------- конуси прожекторів -------- */
  function makeBeam(x, z, targetX, hue, opacity) {
    const h = 9.5;
    const geo = new THREE.CylinderGeometry(0.12, 2.4, h, 32, 1, true);
    const mat = new THREE.MeshBasicMaterial({
      color: hue,
      transparent: true,
      opacity,
      side: THREE.DoubleSide,
      blending: THREE.AdditiveBlending,
      depthWrite: false,
    });
    const beam = new THREE.Mesh(geo, mat);
    beam.position.set(x, h / 2, z);
    const tilt = Math.atan2(targetX - x, h);
    beam.rotation.z = -tilt;
    beam.userData = { baseZRot: -tilt, baseOpacity: opacity };
    root.add(beam);
    return beam;
  }

  const beams = [
    makeBeam(0, 0.2, 0, WARM, 0.075),
    makeBeam(-4.2, -1.6, -0.8, YELLOW, 0.04),
    makeBeam(4.2, -1.6, 0.8, YELLOW, 0.04),
  ];

  /* -------- мікрофон на стійці -------- */
  const mic = new THREE.Group();

  const chromeMat = new THREE.MeshStandardMaterial({
    color: 0x888888,
    roughness: 0.25,
    metalness: 0.95,
  });
  const darkMat = new THREE.MeshStandardMaterial({
    color: 0x1a1a1a,
    roughness: 0.5,
    metalness: 0.6,
  });

  const base = new THREE.Mesh(new THREE.CylinderGeometry(0.42, 0.5, 0.07, 40), darkMat);
  base.position.y = 0.035;
  mic.add(base);

  const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.022, 0.022, 1.55, 16), chromeMat);
  pole.position.y = 0.8;
  mic.add(pole);

  const boom = new THREE.Mesh(new THREE.CylinderGeometry(0.016, 0.016, 0.55, 12), chromeMat);
  boom.position.set(0.16, 1.68, 0);
  boom.rotation.z = -Math.PI / 3.2;
  mic.add(boom);

  const handle = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.06, 0.34, 20), darkMat);
  handle.position.set(0.33, 1.82, 0);
  handle.rotation.z = -Math.PI / 3.2;
  mic.add(handle);

  const headMesh = new THREE.Mesh(
    new THREE.SphereGeometry(0.115, 24, 18),
    new THREE.MeshStandardMaterial({
      color: 0x2c2c2c,
      roughness: 0.35,
      metalness: 0.85,
    })
  );
  headMesh.position.set(0.45, 1.99, 0);
  mic.add(headMesh);

  const headWire = new THREE.Mesh(
    new THREE.SphereGeometry(0.125, 14, 10),
    new THREE.MeshBasicMaterial({ color: 0x666666, wireframe: true, transparent: true, opacity: 0.7 })
  );
  headWire.position.copy(headMesh.position);
  mic.add(headWire);

  mic.position.set(0, 0, 0.4);
  root.add(mic);

  /* -------- пил у промені -------- */
  const COUNT = 700;
  const positions = new Float32Array(COUNT * 3);
  const speeds = new Float32Array(COUNT);
  const VOL = { x: 10, y: 8, z: 7 };
  for (let i = 0; i < COUNT; i++) {
    positions[i * 3 + 0] = (Math.random() - 0.5) * VOL.x;
    positions[i * 3 + 1] = Math.random() * VOL.y;
    positions[i * 3 + 2] = (Math.random() - 0.5) * VOL.z;
    speeds[i] = 0.08 + Math.random() * 0.22;
  }
  const dustGeo = new THREE.BufferGeometry();
  dustGeo.setAttribute("position", new THREE.BufferAttribute(positions, 3));
  const dust = new THREE.Points(
    dustGeo,
    new THREE.PointsMaterial({
      color: 0xfff9b0,
      size: 0.022,
      transparent: true,
      opacity: 0.55,
      blending: THREE.AdditiveBlending,
      depthWrite: false,
      sizeAttenuation: true,
    })
  );
  root.add(dust);

  /* -------- стан, що керується ззовні -------- */
  const ctrl = {
    progress: 0, // 0..1 — прогрес скролу по hero (камера від'їжджає)
    paused: false,
    dispose,
  };

  const mouse = { x: 0, y: 0, tx: 0, ty: 0 };
  function onPointerMove(e) {
    mouse.tx = (e.clientX / window.innerWidth) * 2 - 1;
    mouse.ty = (e.clientY / window.innerHeight) * 2 - 1;
  }
  window.addEventListener("pointermove", onPointerMove, { passive: true });

  function onResize() {
    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    if (!w || !h) return;
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    renderer.setSize(w, h, false);
  }
  window.addEventListener("resize", onResize);
  // на момент ініціалізації модуля layout може бути ще не готовий (clientWidth = 0)
  const ro = new ResizeObserver(onResize);
  ro.observe(canvas);

  const clock = new THREE.Clock();
  let rafId = 0;

  function tick() {
    rafId = requestAnimationFrame(tick);
    if (ctrl.paused) return;

    const t = clock.getElapsedTime();

    // плавний паралакс камери за мишею + від'їзд по скролу
    mouse.x += (mouse.tx - mouse.x) * 0.04;
    mouse.y += (mouse.ty - mouse.y) * 0.04;
    camera.position.x = mouse.x * 0.9;
    camera.position.y = 1.7 - mouse.y * 0.45 + ctrl.progress * 1.6;
    camera.position.z = 8.5 + ctrl.progress * 5.5;
    camera.lookAt(0, 1.15, 0);

    // похитування променів
    beams.forEach((b, i) => {
      const sway = Math.sin(t * (0.35 + i * 0.13) + i * 2.1) * 0.07;
      b.rotation.z = b.userData.baseZRot + sway;
      b.material.opacity =
        b.userData.baseOpacity * (0.85 + Math.sin(t * (0.8 + i * 0.3)) * 0.15) *
        (1 - ctrl.progress * 0.55);
    });

    // мерехтіння рим-світла
    rim.intensity = 14 + Math.sin(t * 2.3) * 3;

    // дрейф пилу вгору з обгортанням
    const pos = dustGeo.attributes.position;
    for (let i = 0; i < COUNT; i++) {
      let y = pos.getY(i) + speeds[i] * 0.016;
      if (y > VOL.y) y = 0;
      pos.setY(i, y);
      pos.setX(i, pos.getX(i) + Math.sin(t * 0.6 + i) * 0.0011);
    }
    pos.needsUpdate = true;

    // мікрофон ледь дихає
    mic.rotation.y = Math.sin(t * 0.4) * 0.07;

    renderer.render(scene, camera);
  }

  function dispose() {
    cancelAnimationFrame(rafId);
    window.removeEventListener("pointermove", onPointerMove);
    window.removeEventListener("resize", onResize);
    ro.disconnect();
    renderer.dispose();
  }

  onResize();
  tick();
  return ctrl;
}
