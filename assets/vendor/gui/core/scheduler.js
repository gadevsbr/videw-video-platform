import { createQueue } from "../utils/queue.js";

const queue = createQueue();
let scheduled = false;
let flushing = false;

export function scheduleJob(job) {
  if (!queue.add(job)) {
    return;
  }

  if (!scheduled) {
    scheduled = true;
    queueMicrotask(flushJobs);
  }
}

export function flushJobs() {
  if (flushing) {
    return;
  }

  scheduled = false;
  flushing = true;

  try {
    while (queue.size > 0) {
      const batch = queue.drain();

      for (const job of batch) {
        job();
      }
    }
  } finally {
    flushing = false;

    if (queue.size > 0 && !scheduled) {
      scheduled = true;
      queueMicrotask(flushJobs);
    }
  }
}
