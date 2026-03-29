const seenWarnings = new Set();

export function warn(message, { once = false } = {}) {
  if (once) {
    if (seenWarnings.has(message)) {
      return;
    }

    seenWarnings.add(message);
  }

  console.warn(`[gUI] ${message}`);
}
