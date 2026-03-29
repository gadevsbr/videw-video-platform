export function createQueue() {
  const items = new Set();

  return {
    add(item) {
      const size = items.size;
      items.add(item);
      return items.size !== size;
    },

    drain() {
      const batch = Array.from(items);
      items.clear();
      return batch;
    },

    get size() {
      return items.size;
    },
  };
}
