const aapCache = new Map<string, unknown>();

export function getAAPCache<T>(key: string): T | undefined {
  return aapCache.get(key) as T | undefined;
}

export function setAAPCache<T>(key: string, value: T): void {
  aapCache.set(key, value);
}

export function clearAAPCache(key?: string): void {
  if (key) {
    aapCache.delete(key);
    return;
  }

  aapCache.clear();
}