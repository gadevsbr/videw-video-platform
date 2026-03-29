import {
  createComputedNode,
  describeNode,
  disposeComputed,
  peekNode,
  readReactiveSource,
  refreshComputed,
  registerDisposable,
} from "./dependencyGraph.js";

export function computed(compute, options = {}) {
  const node = createComputedNode(compute, options.label);

  const api = {
    get value() {
      return readReactiveSource(api, node);
    },

    peek() {
      return peekNode(node);
    },

    inspect() {
      refreshComputed(node);
      return describeNode(node);
    },
  };

  Object.defineProperty(api, "__node", {
    value: node,
    enumerable: false,
  });

  const removeOwnerRegistration = registerDisposable(() => {
    disposeComputed(node);
  });

  Object.defineProperty(api, "dispose", {
    value() {
      removeOwnerRegistration();
      disposeComputed(node);
    },
    enumerable: false,
  });

  return api;
}
