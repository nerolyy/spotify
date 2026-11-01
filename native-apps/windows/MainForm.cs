using System;
using System.Diagnostics;
using System.IO;
using System.Threading;
using System.Windows.Forms;
using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.WinForms;

namespace Muzic2
{
    public partial class MainForm : Form
    {
        private WebView2 webView;
        private Process phpServer;
        private const int ServerPort = 8888;
        private const string ServerUrl = "http://localhost:8888/";

        public MainForm()
        {
            InitializeComponent();
            InitializeWebView();
            StartPHPServer();
        }

        private void InitializeComponent()
        {
            this.Text = "Muzic2";
            this.WindowState = FormWindowState.Maximized;
            this.Size = new System.Drawing.Size(1400, 900);
            this.MinimumSize = new System.Drawing.Size(800, 600);
            this.StartPosition = FormStartPosition.CenterScreen;
            this.FormClosing += MainForm_FormClosing;
            this.BackColor = System.Drawing.Color.Black;
        }

        private async void InitializeWebView()
        {
            webView = new WebView2();
            webView.Dock = DockStyle.Fill;
            this.Controls.Add(webView);

            // Wait for WebView2 to initialize
            await webView.EnsureCoreWebView2Async();

            // Enable developer tools in debug mode
            #if DEBUG
            webView.CoreWebView2.Settings.AreDevToolsEnabled = true;
            #endif

            // Navigate after server starts
            await Task.Delay(2000); // Give PHP server time to start
            webView.CoreWebView2.Navigate(ServerUrl);
        }

        private void StartPHPServer()
        {
            Task.Run(() =>
            {
                try
                {
                    // Find PHP executable
                    string phpPath = FindPHP();
                    if (string.IsNullOrEmpty(phpPath))
                    {
                        MessageBox.Show(
                            "PHP не найден. Установите PHP или используйте встроенный.\n\n" +
                            "Скачайте PHP с https://windows.php.net/download/",
                            "Ошибка",
                            MessageBoxButtons.OK,
                            MessageBoxIcon.Error);
                        return;
                    }

                    // Get application directory
                    string appDirectory = Application.StartupPath;
                    string publicPath = Path.Combine(appDirectory, "public");

                    // If running from IDE, try to find public directory relative to project
                    if (!Directory.Exists(publicPath))
                    {
                        string projectRoot = Path.GetFullPath(Path.Combine(appDirectory, "..", "..", "..", ".."));
                        publicPath = Path.Combine(projectRoot, "public");
                    }

                    if (!Directory.Exists(publicPath))
                    {
                        MessageBox.Show(
                            $"Директория public не найдена: {publicPath}",
                            "Ошибка",
                            MessageBoxButtons.OK,
                            MessageBoxIcon.Error);
                        return;
                    }

                    // Start PHP server
                    ProcessStartInfo startInfo = new ProcessStartInfo
                    {
                        FileName = phpPath,
                        Arguments = $"-S localhost:{ServerPort} -t \"{publicPath}\"",
                        WorkingDirectory = publicPath,
                        UseShellExecute = false,
                        CreateNoWindow = true,
                        RedirectStandardOutput = true,
                        RedirectStandardError = true
                    };

                    phpServer = Process.Start(startInfo);
                    if (phpServer != null)
                    {
                        // Handle output
                        phpServer.OutputDataReceived += (sender, e) =>
                        {
                            if (!string.IsNullOrEmpty(e.Data))
                            {
                                Debug.WriteLine($"[PHP Server]: {e.Data}");
                            }
                        };
                        phpServer.ErrorDataReceived += (sender, e) =>
                        {
                            if (!string.IsNullOrEmpty(e.Data))
                            {
                                Debug.WriteLine($"[PHP Server Error]: {e.Data}");
                            }
                        };
                        phpServer.BeginOutputReadLine();
                        phpServer.BeginErrorReadLine();
                    }
                }
                catch (Exception ex)
                {
                    MessageBox.Show(
                        $"Ошибка запуска PHP сервера: {ex.Message}",
                        "Ошибка",
                        MessageBoxButtons.OK,
                        MessageBoxIcon.Error);
                }
            });
        }

        private string FindPHP()
        {
            // Try common PHP locations
            string[] paths = new[]
            {
                @"C:\php\php.exe",
                @"C:\xampp\php\php.exe",
                @"C:\wamp64\bin\php\php8.2.0\php.exe",
                @"C:\wamp64\bin\php\php8.1.0\php.exe",
                @"C:\Program Files\PHP\php.exe",
                @"C:\Program Files (x86)\PHP\php.exe",
                Path.Combine(Application.StartupPath, "php", "php.exe"),
                Path.Combine(Application.StartupPath, "php.exe")
            };

            foreach (string path in paths)
            {
                if (File.Exists(path))
                {
                    return path;
                }
            }

            // Try to find PHP in PATH
            try
            {
                Process process = new Process
                {
                    StartInfo = new ProcessStartInfo
                    {
                        FileName = "where",
                        Arguments = "php",
                        UseShellExecute = false,
                        RedirectStandardOutput = true,
                        CreateNoWindow = true
                    }
                };
                process.Start();
                string output = process.StandardOutput.ReadToEnd();
                process.WaitForExit();

                if (!string.IsNullOrWhiteSpace(output))
                {
                    string phpPath = output.Trim().Split('\n')[0].Trim();
                    if (File.Exists(phpPath))
                    {
                        return phpPath;
                    }
                }
            }
            catch
            {
                // Ignore
            }

            return null;
        }

        private void MainForm_FormClosing(object sender, FormClosingEventArgs e)
        {
            StopPHPServer();
        }

        private void StopPHPServer()
        {
            if (phpServer != null && !phpServer.HasExited)
            {
                try
                {
                    phpServer.Kill();
                    phpServer.WaitForExit(3000);
                }
                catch (Exception ex)
                {
                    Debug.WriteLine($"Ошибка остановки PHP сервера: {ex.Message}");
                }
                finally
                {
                    phpServer?.Dispose();
                    phpServer = null;
                }
            }
        }
    }
}

