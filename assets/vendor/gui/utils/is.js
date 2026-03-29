export function isFunction(value) {
  return typeof value === "function";
}

export function isObject(value) {
  return value !== null && typeof value === "object";
}

export function isNode(value) {
  return typeof Node !== "undefined" && value instanceof Node;
}

export function isDocumentFragment(value) {
  return typeof DocumentFragment !== "undefined" && value instanceof DocumentFragment;
}

export function isArray(value) {
  return Array.isArray(value);
}

export function isNullish(value) {
  return value === null || value === undefined;
}

export function isBoolean(value) {
  return typeof value === "boolean";
}
