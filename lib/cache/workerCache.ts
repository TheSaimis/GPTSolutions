const workerCache = new Map<string, unknown>();

export const workerCacheKeys = {
  all: "workers-all",
  byId: (id: number) => `workers-id-${id}`,
};

export function getWorkerCache<T>(key: string): T | undefined {
  return workerCache.get(key) as T | undefined;
}

export function setWorkerCache<T>(key: string, value: T): void {
  workerCache.set(key, value);
}

export function clearWorkerCache(key?: string): void {
  if (key) {
    workerCache.delete(key);
    return;
  }

  workerCache.clear();
}

export function getWorkerByIdFromCache(id: number) {
  return getWorkerCache(workerCacheKeys.byId(id));
}

export function upsertWorkerInCache(worker: { id: number }) {
  setWorkerCache(workerCacheKeys.byId(worker.id), worker);

  const all = getWorkerCache<Array<{ id: number }>>(workerCacheKeys.all);
  if (!all) return;

  const index = all.findIndex((item) => item.id === worker.id);
  if (index === -1) {
    setWorkerCache(workerCacheKeys.all, [...all, worker]);
    return;
  }

  const copy = [...all];
  copy[index] = worker;
  setWorkerCache(workerCacheKeys.all, copy);
}

export function removeWorkerFromCache(id: number) {
  clearWorkerCache(workerCacheKeys.byId(id));
  const all = getWorkerCache<Array<{ id: number }>>(workerCacheKeys.all);
  if (!all) return;
  setWorkerCache(
    workerCacheKeys.all,
    all.filter((item) => item.id !== id)
  );
}
