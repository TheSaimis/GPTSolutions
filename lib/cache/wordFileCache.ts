const wordFileCache = new Map<string, Blob>();

export function getCachedWordFile(key: string) {
  return wordFileCache.get(key);
}

export function setCachedWordFile(key: string, blob: Blob) {
  wordFileCache.set(key, blob);
}

export function hasCachedWordFile(key: string) {
  return wordFileCache.has(key);
}

export function clearWordFileCache() {
  wordFileCache.clear();
}