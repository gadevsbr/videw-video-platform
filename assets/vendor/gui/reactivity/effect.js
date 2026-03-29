import {
  createSubscriberNode,
  describeNode,
  disposeSubscriber,
  executeSubscriber,
  registerDisposable,
} from "./dependencyGraph.js";

function createEffectRunner(run, kind, options = {}) {
  const node = createSubscriberNode(kind, run, options.label);
  const removeOwnerRegistration = registerDisposable(dispose);

  executeSubscriber(node);

  function dispose() {
    removeOwnerRegistration();
    disposeSubscriber(node);
  }

  dispose.inspect = function inspect() {
    return describeNode(node);
  };

  return dispose;
}

export function effect(run, options = {}) {
  return createEffectRunner(run, "effect", options);
}

export function createBindingEffect(run, options = {}) {
  return createEffectRunner(run, "binding", options);
}
