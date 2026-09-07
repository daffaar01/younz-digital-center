'use client';

import { useEffect, useRef } from 'react';

const INTERACTIVE_SELECTOR = 'a, button, [role="button"], summary, label[for], input[type="checkbox"], input[type="radio"]';
const TEXT_SELECTOR = 'input:not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]), textarea, select, [contenteditable="true"]';
const AURA_COUNT = 14;

export default function YounzCursor() {
  const cursorRef = useRef<HTMLDivElement>(null);
  const auraRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const cursor = cursorRef.current;
    const auraLayer = auraRef.current;
    const precisePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    if (!cursor || !auraLayer || !precisePointer.matches || reducedMotion.matches) return;

    const root = document.documentElement;
    const auraNodes = Array.from(auraLayer.children) as HTMLElement[];
    let frame = 0;
    let x = -100;
    let y = -100;
    let previousX = -100;
    let previousY = -100;
    let previousSpawnX = -100;
    let previousSpawnY = -100;
    let auraIndex = 0;
    let lastSpawnAt = 0;
    let textMode = false;

    const render = () => {
      cursor.style.transform = `translate3d(${x}px, ${y}px, 0)`;
      frame = 0;
    };

    const updateTarget = (target: EventTarget | null) => {
      const element = target instanceof Element ? target : null;
      textMode = Boolean(element?.closest(TEXT_SELECTOR));
      cursor.dataset.mode = textMode
        ? 'text'
        : element?.closest(INTERACTIVE_SELECTOR)
          ? 'action'
          : 'default';
    };

    const spawnAura = (nextX: number, nextY: number, now: number) => {
      const deltaX = nextX - previousX;
      const deltaY = nextY - previousY;
      const speed = Math.hypot(deltaX, deltaY);
      const spawnDistance = Math.hypot(nextX - previousSpawnX, nextY - previousSpawnY);
      if (textMode || speed < 2 || spawnDistance < 9 || now - lastSpawnAt < 30) return;

      const aura = auraNodes[auraIndex];
      auraIndex = (auraIndex + 1) % auraNodes.length;
      lastSpawnAt = now;
      previousSpawnX = nextX;
      previousSpawnY = nextY;

      const length = Math.min(104, Math.max(34, speed * 4.2));
      const angle = Math.atan2(deltaY, deltaX) * 180 / Math.PI;
      aura.style.setProperty('--aura-x', `${nextX}px`);
      aura.style.setProperty('--aura-y', `${nextY}px`);
      aura.style.setProperty('--aura-length', `${length}px`);
      aura.style.setProperty('--aura-angle', `${angle}deg`);
      aura.classList.remove('is-active');
      void aura.offsetWidth;
      aura.classList.add('is-active');
    };

    const onPointerMove = (event: PointerEvent) => {
      if (event.pointerType && event.pointerType !== 'mouse') return;
      x = event.clientX;
      y = event.clientY;
      updateTarget(event.target);
      cursor.classList.add('is-visible');
      spawnAura(x, y, event.timeStamp);
      previousX = x;
      previousY = y;
      if (!frame) frame = window.requestAnimationFrame(render);
    };

    const onPointerDown = () => cursor.classList.add('is-pressed');
    const onPointerUp = () => cursor.classList.remove('is-pressed');
    const onPointerOut = (event: PointerEvent) => {
      if (!event.relatedTarget) cursor.classList.remove('is-visible');
    };
    const onWindowBlur = () => cursor.classList.remove('is-visible');

    root.classList.add('younz-cursor-enabled');
    window.addEventListener('pointermove', onPointerMove, { passive: true });
    window.addEventListener('pointerdown', onPointerDown, { passive: true });
    window.addEventListener('pointerup', onPointerUp, { passive: true });
    window.addEventListener('pointercancel', onPointerUp, { passive: true });
    window.addEventListener('pointerout', onPointerOut, { passive: true });
    window.addEventListener('blur', onWindowBlur);

    return () => {
      if (frame) window.cancelAnimationFrame(frame);
      root.classList.remove('younz-cursor-enabled');
      window.removeEventListener('pointermove', onPointerMove);
      window.removeEventListener('pointerdown', onPointerDown);
      window.removeEventListener('pointerup', onPointerUp);
      window.removeEventListener('pointercancel', onPointerUp);
      window.removeEventListener('pointerout', onPointerOut);
      window.removeEventListener('blur', onWindowBlur);
    };
  }, []);

  return (
    <>
      <div ref={auraRef} className="younz-cursor-aura-layer" aria-hidden="true">
        {Array.from({ length: AURA_COUNT }, (_, index) => <span className="younz-cursor-aura" key={index} />)}
      </div>
      <div ref={cursorRef} className="younz-cursor" data-mode="default" aria-hidden="true">
        <span className="younz-cursor-core"><img src="/brand/younz-wordmark-v1.svg" alt="" /></span>
        <span className="younz-cursor-dot" />
      </div>
    </>
  );
}
