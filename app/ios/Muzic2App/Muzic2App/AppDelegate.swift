import UIKit
import WebKit

@main
class AppDelegate: UIResponder, UIApplicationDelegate {
    var window: UIWindow?

    func application(_ application: UIApplication, didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?) -> Bool {
        window = UIWindow(frame: UIScreen.main.bounds)
        
        // Создаем конфигурацию WKWebView с правильными настройками
        let config = WKWebViewConfiguration()
        config.preferences.setValue(true, forKey: "allowFileAccessFromFileURLs")
        config.setValue(true, forKey: "allowUniversalAccessFromFileURLs")
        
        // Включаем JavaScript
        config.preferences.javaScriptEnabled = true
        
        let webView = WKWebView(frame: .zero, configuration: config)
        let viewController = WebViewController(webView: webView)
        
        window?.rootViewController = viewController
        window?.makeKeyAndVisible()
        
        // Логируем пути для отладки
        if let resourcePath = Bundle.main.resourcePath {
            print("📦 Resource Path: \(resourcePath)")
            let contentPath = resourcePath + "/content"
            print("📁 Content Path: \(contentPath)")
            
            // Проверяем существование папки content
            let fileManager = FileManager.default
            if fileManager.fileExists(atPath: contentPath) {
                print("✅ Папка content существует")
                
                // Пробуем найти index.html
                let htmlPath = contentPath + "/index.html"
                if fileManager.fileExists(atPath: htmlPath) {
                    print("✅ index.html найден: \(htmlPath)")
                    
                    let htmlURL = URL(fileURLWithPath: htmlPath)
                    let contentURL = URL(fileURLWithPath: contentPath, isDirectory: true)
                    
                    print("🌐 Загружаем URL: \(htmlURL)")
                    print("📂 Доступ к: \(contentURL)")
                    
                    // Загружаем с доступом ко всей папке content
                    webView.loadFileURL(htmlURL, allowingReadAccessTo: contentURL)
                } else {
                    print("❌ index.html НЕ найден в: \(htmlPath)")
                    // Пробуем index.php
                    let phpPath = contentPath + "/index.php"
                    if fileManager.fileExists(atPath: phpPath) {
                        print("✅ index.php найден, переименуйте в index.html")
                        showError("index.php найден, но нужен index.html")
                    } else {
                        print("❌ Ни index.html, ни index.php не найдены")
                        showError("index.html не найден в папке content")
                    }
                }
            } else {
                print("❌ Папка content НЕ существует: \(contentPath)")
                showError("Папка content не найдена в bundle")
            }
        } else {
            print("❌ Resource Path не найден")
            showError("Не удалось найти resource path")
        }
        
        // Добавляем обработчик ошибок загрузки
        webView.navigationDelegate = WebViewNavigationDelegate()
        
        return true
    }
    
    func showError(_ message: String) {
        DispatchQueue.main.async {
            let alert = UIAlertController(title: "Ошибка загрузки", message: message, preferredStyle: .alert)
            alert.addAction(UIAlertAction(title: "OK", style: .default))
            self.window?.rootViewController?.present(alert, animated: true)
        }
    }
}

class WebViewNavigationDelegate: NSObject, WKNavigationDelegate {
    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
        print("❌ Ошибка загрузки: \(error.localizedDescription)")
    }
    
    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
        print("✅ Страница загружена успешно")
    }
    
    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
        print("❌ Ошибка навигации: \(error.localizedDescription)")
    }
}

class WebViewController: UIViewController {
    let webView: WKWebView
    
    init(webView: WKWebView) {
        self.webView = webView
        super.init(nibName: nil, bundle: nil)
    }
    
    required init?(coder: NSCoder) {
        fatalError("init(coder:) has not been implemented")
    }
    
    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .black
        
        view.addSubview(webView)
        webView.translatesAutoresizingMaskIntoConstraints = false
        NSLayoutConstraint.activate([
            webView.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor),
            webView.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            webView.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            webView.bottomAnchor.constraint(equalTo: view.bottomAnchor)
        ])
        
        // Включаем инспектор для отладки (только в Debug)
        #if DEBUG
        if #available(iOS 16.4, *) {
            webView.isInspectable = true
        }
        #endif
    }
}

