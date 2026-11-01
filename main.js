const { app, BrowserWindow, Menu, ipcMain } = require('electron');
const path = require('path');

let mainWindow;
const isDev = !app.isPackaged;

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1200,
    height: 800,
    minWidth: 800,
    minHeight: 600,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: false,
      contextIsolation: true,
      webSecurity: false
    }
  });

  // Always load from running MAMP URL so PHP works
  const appUrl = 'http://localhost:8888/muzic2/public/';
  mainWindow.loadURL(appUrl);
  if (isDev) mainWindow.webContents.openDevTools({ mode: 'detach' });

  mainWindow.on('closed', () => { mainWindow = null; });
  setupMenu();
}

function setupMenu() {
  const template = [
    {
      label: 'Воспроизведение',
      submenu: [
        { label: 'Воспроизвести/Пауза', accelerator: 'F8', click: () => mainWindow.webContents.send('media-key', 'play-pause') },
        { label: 'Предыдущий трек', accelerator: 'F7', click: () => mainWindow.webContents.send('media-key', 'previous') },
        { label: 'Следующий трек', accelerator: 'F9', click: () => mainWindow.webContents.send('media-key', 'next') },
        { type: 'separator' },
        { label: 'Перемешать', accelerator: 'F6', click: () => mainWindow.webContents.send('media-key', 'shuffle') },
        { label: 'Повтор', accelerator: 'F10', click: () => mainWindow.webContents.send('media-key', 'repeat') }
      ]
    }
  ];
  const menu = Menu.buildFromTemplate(template);
  Menu.setApplicationMenu(menu);

  ipcMain.on('media-key-response', (_e, _key) => {});
}

app.whenReady().then(createWindow);
app.on('window-all-closed', () => { if (process.platform !== 'darwin') app.quit(); });
app.on('activate', () => { if (BrowserWindow.getAllWindows().length === 0) createWindow(); });


