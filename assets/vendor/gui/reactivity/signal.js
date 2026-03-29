import {
  createSignalNode,
  describeNode,
  peekNode,
  readReactiveSource,
  writeSignalNode,
} from "./dependencyGraph.js";

export function signal(initialValue, options = {}) {
  const node = createSignalNode(initialValue, options.label);

  const api = {
    get value() {
      return readReactiveSource(api, node);
    },

    set value(nextValue) {
      writeSignalNode(node, nextValue);
    },

    set(nextValue) {
      writeSignalNode(node, nextValue);
      return api.peek();
    },

    update(updater) {
      const nextValue = updater(node.current);
      writeSignalNode(node, nextValue);
      return api.peek();
    },

    peek() {
      return peekNode(node);
    },

    inspect() {
      return describeNode(node);
    },
  };

  Object.defineProperty(api, "__node", {
    value: node,
    enumerable: false,
  });

  return api;
}
