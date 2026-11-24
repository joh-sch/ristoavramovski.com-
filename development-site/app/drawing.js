/**
 * The drawing editor — a separate bundle, loaded on demand.
 *
 * Fabric is ~292KB minified against a 55KB app bundle, and only the artist
 * ever draws. Visitors see the saved SVG instead and never fetch this file.
 *
 * Exposes a small imperative API on window.RistoDrawing rather than a gia
 * component, because the component that drives it lives in the main bundle
 * and must work whether or not this one has loaded yet.
 */

import { Canvas, PencilBrush } from 'fabric';

let canvas = null;
let host = null;

/**
 * The drawing surface spans the whole document, not the viewport, so marks
 * stay where they were put as the page scrolls.
 */
function documentSize() {
  const body = document.body;
  const html = document.documentElement;

  return {
    width: html.clientWidth,
    height: Math.max(body.scrollHeight, html.scrollHeight, html.clientHeight),
  };
}

export async function open({ colour, width, existing }) {
  if (canvas) return canvas;

  const size = documentSize();

  host = document.createElement('canvas');
  host.className = 'drawing-canvas';

  const mount = document.createElement('div');
  mount.className = 'drawing-canvas-mount';
  mount.appendChild(host);
  document.body.appendChild(mount);

  canvas = new Canvas(host, {
    width: size.width,
    height: size.height,
    isDrawingMode: true,
    // The page shows through; only the strokes are ours.
    backgroundColor: 'transparent',
    selection: false,
  });

  canvas.freeDrawingBrush = new PencilBrush(canvas);
  canvas.freeDrawingBrush.color = colour;
  canvas.freeDrawingBrush.width = width;

  if (existing) {
    try {
      await canvas.loadFromJSON(existing);
      canvas.renderAll();
    } catch {
      // A corrupt payload must not leave the artist unable to draw at all.
    }
  }

  return canvas;
}

export function setBrush({ colour, width }) {
  if (!canvas) return;
  if (colour) canvas.freeDrawingBrush.color = colour;
  if (width) canvas.freeDrawingBrush.width = width;
}

export function undo() {
  if (!canvas) return;

  const objects = canvas.getObjects();
  if (objects.length === 0) return;

  canvas.remove(objects[objects.length - 1]);
  canvas.renderAll();
}

export function clear() {
  if (!canvas) return;
  canvas.clear();
  canvas.backgroundColor = 'transparent';
  canvas.renderAll();
}

/**
 * Both representations: Fabric's JSON to edit again, and a flat SVG to serve.
 */
export function serialise() {
  if (!canvas) return null;

  return {
    json: JSON.stringify(canvas.toJSON()),
    svg: canvas.toSVG(),
    width: Math.round(canvas.getWidth()),
    height: Math.round(canvas.getHeight()),
  };
}

export function close() {
  if (!canvas) return;

  const mount = host?.closest('.drawing-canvas-mount');
  canvas.dispose();
  canvas = null;
  host = null;
  mount?.remove();
}

/**
 * The live Fabric canvas, or null.
 *
 * Exposed so future tools (shapes, erase, selection) can build on the same
 * surface without this module having to grow a method per feature.
 */
export function getCanvas() {
  return canvas;
}

export function isEmpty() {
  return !canvas || canvas.getObjects().length === 0;
}
