import { createOwner, disposeOwner, runWithTemplateCapture, withOwner } from "../reactivity/dependencyGraph.js";
import { mount } from "./mount.js";

export function createApp(target, component) {
  const owner = createOwner("app");

  const view = withOwner(owner, () =>
    runWithTemplateCapture(() =>
      typeof component === "function" ? component() : component,
    ),
  );
  const mounted = mount(target, view);

  return {
    owner,
    target: mounted.container,
    unmount() {
      disposeOwner(owner);
      mounted.unmount();
    },
  };
}
