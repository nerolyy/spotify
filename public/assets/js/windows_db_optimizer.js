// Windows Database Optimizer - JavaScript версия для фронтенда
class WindowsDBOptimizer {
    constructor() {
        this.cache = new Map();
        this.cacheTimeout = 30000; // 30 секунд
    }
    
    // Получить все данные одним запросом
    async getAllData() {
        const cacheKey = 'all_data';
        const cached = this.cache.get(cacheKey);
        
        if (cached && Date.now() - cached.timestamp < this.cacheTimeout) {
            console.log('📦 Используем кэш данных');
            return cached.data;
        }
        
        try {
            console.log('🔄 Загружаем все данные...');
            const start = performance.now();
            
            const response = await fetch('/muzic2/windows_db_optimizer.php', {
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            const loadTime = Math.round(performance.now() - start);
            
            console.log(`✅ Данные загружены за ${loadTime}ms (сервер: ${data.load_time_ms}ms)`);
            
            // Кэшируем данные
            this.cache.set(cacheKey, {
                data: data,
                timestamp: Date.now()
            });
            
            return data;
            
        } catch (error) {
            console.error('❌ Ошибка загрузки данных:', error);
            throw error;
        }
    }
    
    // Получить только треки
    async getTracks() {
        const data = await this.getAllData();
        return data.tracks || [];
    }
    
    // Получить только альбомы
    async getAlbums() {
        const data = await this.getAllData();
        return data.albums || [];
    }
    
    // Получить только артистов
    async getArtists() {
        const data = await this.getAllData();
        return data.artists || [];
    }
    
    // Получить информацию о пользователе
    async getUser() {
        const data = await this.getAllData();
        return {
            user: data.user,
            authenticated: data.authenticated
        };
    }
    
    // Получить лайки
    async getLikes() {
        const data = await this.getAllData();
        return {
            tracks: data.liked_tracks || [],
            albums: data.liked_albums || []
        };
    }
    
    // Получить плейлисты
    async getPlaylists() {
        const data = await this.getAllData();
        return data.playlists || [];
    }
    
    // Получить статистику
    async getStats() {
        const data = await this.getAllData();
        return data.stats || {};
    }
    
    // Очистить кэш
    clearCache() {
        this.cache.clear();
        console.log('🗑️ Кэш очищен');
    }
    
    // Проверить производительность
    async benchmark() {
        console.log('🏁 Запуск бенчмарка...');
        
        const tests = [
            { name: 'Все данные', fn: () => this.getAllData() },
            { name: 'Только треки', fn: () => this.getTracks() },
            { name: 'Только альбомы', fn: () => this.getAlbums() },
            { name: 'Только артисты', fn: () => this.getArtists() },
            { name: 'Пользователь', fn: () => this.getUser() },
            { name: 'Лайки', fn: () => this.getLikes() },
            { name: 'Плейлисты', fn: () => this.getPlaylists() }
        ];
        
        const results = [];
        
        for (const test of tests) {
            try {
                const start = performance.now();
                await test.fn();
                const time = Math.round(performance.now() - start);
                results.push({ name: test.name, time: time, status: 'success' });
                console.log(`✅ ${test.name}: ${time}ms`);
            } catch (error) {
                results.push({ name: test.name, time: 0, status: 'error', error: error.message });
                console.log(`❌ ${test.name}: ${error.message}`);
            }
        }
        
        return results;
    }
}

// Создаем глобальный экземпляр
window.windowsDBOptimizer = new WindowsDBOptimizer();

// Экспортируем для использования в модулях
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WindowsDBOptimizer;
}
