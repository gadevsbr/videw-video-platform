const templateCache = new WeakMap();

function readAttributeContext(chunk) {
  const lastOpen = chunk.lastIndexOf("<");
  const lastClose = chunk.lastIndexOf(">");

  if (lastOpen <= lastClose) {
    return null;
  }

  const match = /([^\s<>"'=\/]+)\s*=\s*["']?$/.exec(chunk);
  return match ? match[1] : null;
}

export function compileTemplate(strings) {
  if (templateCache.has(strings)) {
    return templateCache.get(strings);
  }

  let markup = "";
  const parts = [];

  for (let index = 0; index < strings.length - 1; index += 1) {
    const chunk = strings[index];
    const attributeName = readAttributeContext(chunk);

    markup += chunk;

    if (attributeName) {
      const marker = `__gui_attr_${index}__`;

      parts.push({
        index,
        type: attributeName.startsWith("on:") ? "event" : "attribute",
        name: attributeName,
        marker,
      });

      markup += marker;
      continue;
    }

    const marker = `gui-part:${index}`;

    parts.push({
      index,
      type: "node",
      marker,
    });

    markup += `<!--${marker}-->`;
  }

  markup += strings[strings.length - 1];

  const template = document.createElement("template");
  template.innerHTML = markup;

  const compiled = {
    template,
    parts,
  };

  templateCache.set(strings, compiled);
  return compiled;
}

export function locateTemplateParts(fragment, partDefinitions) {
  const partMap = new Array(partDefinitions.length);
  const commentMarkers = new Map();
  const attributeMarkers = new Map();

  for (const definition of partDefinitions) {
    if (definition.type === "node") {
      commentMarkers.set(definition.marker, definition);
      continue;
    }

    attributeMarkers.set(definition.marker, definition);
  }

  const commentWalker = document.createTreeWalker(fragment, NodeFilter.SHOW_COMMENT);
  let currentComment = commentWalker.nextNode();

  while (currentComment) {
    const definition = commentMarkers.get(currentComment.data);

    if (definition) {
      partMap[definition.index] = {
        ...definition,
        anchor: currentComment,
        currentType: "empty",
        currentNodes: [],
        textNode: null,
      };
    }

    currentComment = commentWalker.nextNode();
  }

  const elementWalker = document.createTreeWalker(fragment, NodeFilter.SHOW_ELEMENT);
  let currentElement = elementWalker.nextNode();

  while (currentElement) {
    for (const attribute of Array.from(currentElement.attributes)) {
      const definition = attributeMarkers.get(attribute.value);

      if (!definition) {
        continue;
      }

      currentElement.removeAttribute(attribute.name);
      partMap[definition.index] = {
        ...definition,
        element: currentElement,
      };
    }

    currentElement = elementWalker.nextNode();
  }

  return partMap;
}
