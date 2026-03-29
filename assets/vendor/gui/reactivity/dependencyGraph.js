import { scheduleJob } from "../core/scheduler.js";
import { warn } from "../utils/warn.js";

let activeSubscriber = null;
let currentOwner = null;
let templateCaptureDepth = 0;
let nextId = 0;

const TOKEN_FLAG = Symbol("gui.reactive.token");

export function createNodeId(prefix) {
  nextId += 1;
  return `${prefix}:${nextId}`;
}

export function createOwner(label = "owner") {
  return {
    id: createNodeId("owner"),
    label,
    disposables: new Set(),
    disposed: false,
  };
}

export function withOwner(owner, callback) {
  const previousOwner = currentOwner;
  currentOwner = owner;

  try {
    return callback();
  } finally {
    currentOwner = previousOwner;
  }
}

export function registerDisposable(dispose) {
  const owner = currentOwner;

  if (!owner || typeof dispose !== "function") {
    return () => {};
  }

  owner.disposables.add(dispose);

  return () => {
    owner.disposables.delete(dispose);
  };
}

export function disposeOwner(owner) {
  if (!owner || owner.disposed) {
    return;
  }

  owner.disposed = true;

  for (const dispose of Array.from(owner.disposables)) {
    owner.disposables.delete(dispose);
    dispose();
  }
}

export function runWithTemplateCapture(callback) {
  templateCaptureDepth += 1;

  try {
    return callback();
  } finally {
    templateCaptureDepth -= 1;
  }
}

export function isTemplateCaptureActive() {
  return templateCaptureDepth > 0;
}

function createReactiveToken(source) {
  return {
    [TOKEN_FLAG]: true,
    source,
    valueOf() {
      return source.peek();
    },
    toString() {
      return String(source.peek());
    },
    [Symbol.toPrimitive]() {
      return source.peek();
    },
  };
}

export function isReactiveToken(value) {
  return Boolean(value && value[TOKEN_FLAG]);
}

export function createSignalNode(initialValue, label) {
  return {
    id: createNodeId("signal"),
    kind: "signal",
    label,
    current: initialValue,
    version: 0,
    subscribers: new Set(),
  };
}

export function createComputedNode(compute, label) {
  return {
    id: createNodeId("computed"),
    kind: "computed",
    label,
    compute,
    current: undefined,
    version: 0,
    dirty: true,
    initialized: false,
    running: false,
    subscribers: new Set(),
    sources: new Set(),
    sourceVersions: new Map(),
    disposed: false,
  };
}

export function createSubscriberNode(kind, run, label) {
  const node = {
    id: createNodeId(kind),
    kind,
    label,
    run,
    sources: new Set(),
    sourceVersions: new Map(),
    pending: false,
    initialized: false,
    running: false,
    cleanup: null,
    disposed: false,
    job: null,
  };

  node.job = () => flushSubscriber(node);
  return node;
}

export function describeNode(node) {
  return {
    id: node.id,
    kind: node.kind,
    label: node.label,
    version: node.version ?? 0,
    dirty: node.dirty ?? false,
    initialized: node.initialized ?? false,
    sourceCount: node.sources ? node.sources.size : 0,
    subscriberCount: node.subscribers ? node.subscribers.size : 0,
  };
}

export function readReactiveSource(sourceApi, node) {
  if (activeSubscriber) {
    trackDependency(node);

    if (node.kind === "computed") {
      refreshComputed(node);
    }

    return node.current;
  }

  if (isTemplateCaptureActive()) {
    return createReactiveToken(sourceApi);
  }

  if (node.kind === "computed") {
    refreshComputed(node);
  }

  return node.current;
}

export function peekNode(node) {
  if (node.kind === "computed") {
    refreshComputed(node);
  }

  return node.current;
}

export function writeSignalNode(node, nextValue) {
  if (Object.is(node.current, nextValue)) {
    return nextValue;
  }

  node.current = nextValue;
  node.version += 1;
  invalidateSubscribers(node.subscribers);
  return nextValue;
}

export function refreshComputed(node) {
  if (!node.dirty || node.disposed) {
    return node.current;
  }

  if (node.running) {
    warn(`Circular computed dependency detected in "${node.label ?? node.id}".`, { once: true });
    return node.current;
  }

  cleanupSources(node);

  const previousSubscriber = activeSubscriber;
  activeSubscriber = node;
  node.running = true;

  try {
    const nextValue = node.compute();
    const changed = !node.initialized || !Object.is(node.current, nextValue);

    node.current = nextValue;
    node.initialized = true;
    node.dirty = false;

    if (changed) {
      node.version += 1;
    }

    captureSourceVersions(node);
    return node.current;
  } finally {
    node.running = false;
    activeSubscriber = previousSubscriber;
  }
}

export function executeSubscriber(node) {
  if (node.disposed) {
    return;
  }

  if (node.running) {
    warn(`Skipping re-entrant execution for "${node.label ?? node.id}".`, { once: true });
    return;
  }

  if (node.initialized && !subscriberNeedsExecution(node)) {
    node.pending = false;
    return;
  }

  node.pending = false;
  node.running = true;

  if (node.cleanup) {
    node.cleanup();
    node.cleanup = null;
  }

  cleanupSources(node);

  const previousSubscriber = activeSubscriber;
  activeSubscriber = node;

  try {
    const cleanup = node.run();
    node.cleanup = typeof cleanup === "function" ? cleanup : null;
    node.initialized = true;
    captureSourceVersions(node);
  } finally {
    activeSubscriber = previousSubscriber;
    node.running = false;
  }
}

export function disposeSubscriber(node) {
  if (!node || node.disposed) {
    return;
  }

  node.disposed = true;
  node.pending = false;

  if (node.cleanup) {
    node.cleanup();
    node.cleanup = null;
  }

  cleanupSources(node);
}

export function disposeComputed(node) {
  if (!node || node.disposed) {
    return;
  }

  node.disposed = true;
  node.dirty = true;
  cleanupSources(node);
  node.subscribers.clear();
}

function flushSubscriber(node) {
  executeSubscriber(node);
}

function subscriberNeedsExecution(node) {
  if (node.sources.size === 0) {
    return true;
  }

  for (const source of node.sources) {
    const previousVersion = node.sourceVersions.get(source);
    const currentVersion = getSourceVersion(source);

    if (previousVersion !== currentVersion) {
      return true;
    }
  }

  return false;
}

function getSourceVersion(source) {
  if (source.kind === "computed") {
    refreshComputed(source);
  }

  return source.version;
}

function trackDependency(source) {
  if (!activeSubscriber) {
    return;
  }

  if (!activeSubscriber.sources.has(source)) {
    activeSubscriber.sources.add(source);
    source.subscribers.add(activeSubscriber);
  }
}

function captureSourceVersions(node) {
  node.sourceVersions.clear();

  for (const source of node.sources) {
    node.sourceVersions.set(source, getSourceVersion(source));
  }
}

function cleanupSources(node) {
  if (!node.sources) {
    return;
  }

  for (const source of node.sources) {
    source.subscribers.delete(node);
  }

  node.sources.clear();
  node.sourceVersions.clear();
}

function invalidateSubscribers(subscribers) {
  for (const subscriber of Array.from(subscribers)) {
    invalidateSubscriber(subscriber);
  }
}

function invalidateSubscriber(node) {
  if (node.disposed) {
    return;
  }

  if (node.kind === "computed") {
    if (node.dirty) {
      return;
    }

    node.dirty = true;
    invalidateSubscribers(node.subscribers);
    return;
  }

  if (node.pending) {
    return;
  }

  node.pending = true;
  scheduleJob(node.job);
}
