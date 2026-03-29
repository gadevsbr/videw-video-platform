export interface ReactiveNodeSnapshot {
  id: string;
  kind: string;
  label?: string;
  version: number;
  dirty: boolean;
  initialized: boolean;
  sourceCount: number;
  subscriberCount: number;
}

export interface Signal<T> {
  value: T;
  set(nextValue: T): T;
  update(updater: (current: T) => T): T;
  peek(): T;
  inspect(): ReactiveNodeSnapshot;
}

export interface Computed<T> {
  readonly value: T;
  peek(): T;
  inspect(): ReactiveNodeSnapshot;
  dispose(): void;
}

export interface EffectHandle {
  (): void;
  inspect(): ReactiveNodeSnapshot;
}

export interface TemplateResult {
  readonly fragment: DocumentFragment;
  readonly nodes: Node[];
}

export interface MountHandle {
  container: Element | Node;
  nodes: Node[];
  unmount(): void;
}

export interface AppHandle {
  owner: unknown;
  target: Element | Node;
  unmount(): void;
}

export declare function signal<T>(initialValue: T, options?: { label?: string }): Signal<T>;

export declare function computed<T>(
  compute: () => T,
  options?: { label?: string },
): Computed<T>;

export declare function effect(run: () => void | (() => void), options?: { label?: string }): EffectHandle;

export declare function html(
  strings: TemplateStringsArray,
  ...values: unknown[]
): TemplateResult;

export declare function isTemplateResult(value: unknown): value is TemplateResult;

export declare function mount(target: string | Node, value: unknown): MountHandle;

export declare function createApp(
  target: string | Node,
  component: (() => unknown) | unknown,
): AppHandle;

export declare function setDomUpdateHook(
  hook:
    | ((payload: {
        type: "text" | "attribute" | "structure";
        node?: Node;
        element?: Element;
        name?: string;
        value?: unknown;
        action?: "insert" | "remove";
        anchor?: Node;
      }) => void)
    | null,
): void;
