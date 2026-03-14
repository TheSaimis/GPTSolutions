export function parseJwtToken(token: string) {
    const base64Url = token.split(".")[1];
    const base64 = base64Url.replace(/-/g, "+").replace(/_/g, "/");
    const binary = atob(base64);
    const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
    const jsonPayload = new TextDecoder("utf-8").decode(bytes);
  
    return JSON.parse(jsonPayload);
  }