const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
  onMediaKey: (callback) => ipcRenderer.on('media-key', callback),
  sendMediaKeyResponse: (key) => ipcRenderer.send('media-key-response', key)
});


