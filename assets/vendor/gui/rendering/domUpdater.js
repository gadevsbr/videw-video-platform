const BOOLEAN_ATTRIBUTES = new Set([
  "checked",
  "disabled",
  "hidden",
  "open",
  "readonly",
  "required",
  "selected",
]);

const PROPERTY_ALIASES = {
  class: "className",
  readonly: "readOnly",
};

let domUpdateHook = null;

export function setDomUpdateHook(hook) {
  domUpdateHook = typeof hook === "function" ? hook : null;
}

function emit(payload) {
  if (domUpdateHook) {
    domUpdateHook(payload);
  }
}

function normalizeTextValue(value) {
  if (value === null || value === undefined || value === false) {
    return "";
  }

  return String(value);
}

function getPropertyName(name) {
  return PROPERTY_ALIASES[name] ?? name;
}

export function setTextContent(node, value) {
  const nextValue = normalizeTextValue(value);

  if (node.data === nextValue) {
    return;
  }

  node.data = nextValue;
  emit({
    type: "text",
    node,
    value: nextValue,
  });
}

export function setAttributeValue(element, name, value) {
  const propertyName = getPropertyName(name);
  const hasProperty = propertyName in element;
  let mutated = false;

  if (value === null || value === undefined || value === false) {
    if (hasProperty && BOOLEAN_ATTRIBUTES.has(name) && element[propertyName] !== false) {
      element[propertyName] = false;
      mutated = true;
    }

    if (name === "value" && hasProperty && element[propertyName] !== "") {
      element[propertyName] = "";
      mutated = true;
    }

    if (element.hasAttribute(name)) {
      element.removeAttribute(name);
      mutated = true;
    }

    if (mutated) {
      emit({
        type: "attribute",
        element,
        name,
        value: null,
      });
    }

    return;
  }

  if (value === true) {
    if (hasProperty && BOOLEAN_ATTRIBUTES.has(name) && element[propertyName] !== true) {
      element[propertyName] = true;
      mutated = true;
    }

    if (element.getAttribute(name) !== "") {
      element.setAttribute(name, "");
      mutated = true;
    }

    if (mutated) {
      emit({
        type: "attribute",
        element,
        name,
        value: true,
      });
    }

    return;
  }

  if (hasProperty && element[propertyName] !== value) {
    element[propertyName] = value;
    mutated = true;
  }

  const serializedValue = String(value);

  if (element.getAttribute(name) !== serializedValue) {
    element.setAttribute(name, serializedValue);
    mutated = true;
  }

  if (mutated) {
    emit({
      type: "attribute",
      element,
      name,
      value,
    });
  }
}

export function insertNodesBefore(anchor, nodes) {
  const parent = anchor.parentNode;

  for (const node of nodes) {
    parent.insertBefore(node, anchor);
    emit({
      type: "structure",
      action: "insert",
      node,
      anchor,
    });
  }
}

export function removeNodes(nodes) {
  for (const node of nodes) {
    if (!node.parentNode) {
      continue;
    }

    node.parentNode.removeChild(node);
    emit({
      type: "structure",
      action: "remove",
      node,
    });
  }
}
