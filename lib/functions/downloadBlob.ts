export type DownloadResult = {
    blob: Blob;
    filename: string;
  };
  
  export function downloadBlob(file: DownloadResult) {
    const url = URL.createObjectURL(file.blob);
  
    const a = document.createElement("a");
    a.href = url;
    a.download = file.filename;
    document.body.appendChild(a);
  
    a.click();
  
    a.remove();
    URL.revokeObjectURL(url);
  }