import { isArray, isNode } from "../utils/is.js";
import { warn } from "../utils/warn.js";
import { isTemplateResult, toTemplateNodes } from "../rendering/html.js";

function resolveTarget(target) {
  if (typeof target === "string") {
    const element = document.querySelector(target);

    if (!element) {
      throw new Error(`[gUI] Mount target "${target}" was not found.`);
    }

    return element;
  }

  if (isNode(target)) {
    return target;
  }

  throw new Error("[gUI] Mount target must be a selector or a DOM node.");
}

function normalizeRootValue(value) {
  if (isTemplateResult(value)) {
    return {
      fragment: value.fragment,
      nodes: toTemplateNodes(value),
    };
  }

  if (isNode(value)) {
    return {
      fragment: value,
      nodes: [value],
    };
  }

  if (isArray(value)) {
    const fragment = document.createDocumentFragment();
    const nodes = [];

    for (const item of value) {
      if (isNode(item)) {
        fragment.appendChild(item);
        nodes.push(item);
        continue;
      }

      const textNode = document.createTextNode(item == null ? "" : String(item));
      fragment.appendChild(textNode);
      nodes.push(textNode);
    }

    return {
      fragment,
      nodes,
    };
  }

  if (value == null) {
    const comment = document.createComment("gui-empty-root");
    return {
      fragment: comment,
      nodes: [comment],
    };
  }

  if (typeof value === "string" || typeof value === "number" || typeof value === "boolean") {
    const textNode = document.createTextNode(String(value));
    return {
      fragment: textNode,
      nodes: [textNode],
    };
  }

  warn(`Unsupported root value "${String(value)}". Rendering it as text.`, { once: true });

  const fallbackText = document.createTextNode(String(value));
  return {
    fragment: fallbackText,
    nodes: [fallbackText],
  };
}

export function mount(target, value) {
  const container = resolveTarget(target);
  const normalized = normalizeRootValue(value);

  container.replaceChildren(normalized.fragment);

  return {
    container,
    nodes: normalized.nodes,
    unmount() {
      for (const node of normalized.nodes) {
        if (node.parentNode === container) {
          container.removeChild(node);
        }
      }
    },
  };
}
