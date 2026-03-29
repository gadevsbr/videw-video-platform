import { setupBindings } from "./bindings.js";
import { compileTemplate, locateTemplateParts } from "./templateCompiler.js";

const TEMPLATE_RESULT = Symbol("gui.template.result");

export function html(strings, ...values) {
  const compiled = compileTemplate(strings);
  const fragment = compiled.template.content.cloneNode(true);
  const parts = locateTemplateParts(fragment, compiled.parts);
  const nodes = Array.from(fragment.childNodes);

  setupBindings(parts, values);

  return {
    [TEMPLATE_RESULT]: true,
    fragment,
    nodes,
  };
}

export function isTemplateResult(value) {
  return Boolean(value && value[TEMPLATE_RESULT]);
}

export function toTemplateNodes(templateResult) {
  return templateResult.nodes;
}
