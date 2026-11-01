/**
 * Модуль для эффектов караоке: частицы и AR-режим
 */

(function() {
  'use strict';

  // ========== ЭФФЕКТЫ ЧАСТИЦ ==========
  
  class ParticleSystem {
    constructor(canvas, audioContext, audioSource) {
      this.canvas = canvas;
      this.ctx = canvas.getContext('2d');
      this.audioContext = audioContext;
      this.audioSource = audioSource;
      this.analyser = null;
      this.dataArray = null;
      this.particles = [];
      this.animationId = null;
      this.isActive = false;
      
      // Настройки частиц
      this.particleCount = 150;
      this.beatThreshold = 0.3;
      this.beatSensitivity = 0.6;
      
      // Анализ аудио
      this.setupAudioAnalysis();
      
      // Настройка canvas
      this.resizeCanvas();
      window.addEventListener('resize', () => this.resizeCanvas());
    }
    
    setupAudioAnalysis() {
      if (!this.audioContext || !this.audioSource) return;
      
      try {
        // Создаем анализатор для определения бита
        this.analyser = this.audioContext.createAnalyser();
        this.analyser.fftSize = 256;
        this.analyser.smoothingTimeConstant = 0.8;
        
        // Подключаем источник к анализатору
        // Если источник уже подключен к destination, используем splitter
        try {
          // Пробуем подключить напрямую
          this.audioSource.connect(this.analyser);
          // Анализатор не нужно подключать к destination - он только читает данные
        } catch (e) {
          // Если источник уже подключен к destination, создаем splitter
          console.warn('Прямое подключение не удалось, используем splitter:', e);
          try {
            // Создаем splitter для разделения сигнала
            const splitter = this.audioContext.createChannelSplitter();
            // Отключаем источник от destination (если возможно)
            // И подключаем через splitter
            this.audioSource.disconnect();
            this.audioSource.connect(splitter);
            splitter.connect(this.audioContext.destination);
            splitter.connect(this.analyser);
          } catch (e2) {
            console.error('Не удалось настроить splitter:', e2);
            // В крайнем случае просто создаем анализатор без подключения
            // Эффекты частиц будут работать без синхронизации с битом
          }
        }
        
        // Создаем массив для данных
        const bufferLength = this.analyser.frequencyBinCount;
        this.dataArray = new Uint8Array(bufferLength);
      } catch (e) {
        console.error('Ошибка настройки анализатора:', e);
      }
    }
    
    resizeCanvas() {
      if (!this.canvas) return;
      const rect = this.canvas.getBoundingClientRect();
      this.canvas.width = rect.width || window.innerWidth;
      this.canvas.height = rect.height || window.innerHeight;
    }
    
    initParticles() {
      this.particles = [];
      for (let i = 0; i < this.particleCount; i++) {
        this.particles.push({
          x: Math.random() * this.canvas.width,
          y: Math.random() * this.canvas.height,
          vx: (Math.random() - 0.5) * 2,
          vy: (Math.random() - 0.5) * 2,
          size: Math.random() * 3 + 1,
          color: this.getRandomColor(),
          life: 1.0,
          decay: Math.random() * 0.02 + 0.005
        });
      }
    }
    
    getRandomColor() {
      const colors = [
        '#ff6b6b', '#4ecdc4', '#45b7d1', '#f9ca24', '#f0932b',
        '#eb4d4b', '#6c5ce7', '#a29bfe', '#fd79a8', '#00b894'
      ];
      return colors[Math.floor(Math.random() * colors.length)];
    }
    
    detectBeat() {
      if (!this.analyser || !this.dataArray) return 0;
      
      this.analyser.getByteFrequencyData(this.dataArray);
      
      // Анализируем низкие частоты (басы) для определения бита
      let sum = 0;
      const bassRange = Math.floor(this.dataArray.length * 0.1); // Первые 10% - басы
      for (let i = 0; i < bassRange; i++) {
        sum += this.dataArray[i];
      }
      
      const average = sum / bassRange / 255;
      return average;
    }
    
    updateParticles(beatIntensity) {
      const beatBoost = beatIntensity > this.beatThreshold ? beatIntensity * 2 : 1;
      
      this.particles.forEach(particle => {
        // Обновление позиции
        particle.x += particle.vx * beatBoost;
        particle.y += particle.vy * beatBoost;
        
        // Отскок от краев
        if (particle.x < 0 || particle.x > this.canvas.width) {
          particle.vx *= -1;
          particle.x = Math.max(0, Math.min(this.canvas.width, particle.x));
        }
        if (particle.y < 0 || particle.y > this.canvas.height) {
          particle.vy *= -1;
          particle.y = Math.max(0, Math.min(this.canvas.height, particle.y));
        }
        
        // Эффект бита - увеличение размера и скорости
        if (beatIntensity > this.beatThreshold) {
          particle.size = Math.min(8, particle.size + beatIntensity * 2);
          particle.vx += (Math.random() - 0.5) * beatIntensity * 0.5;
          particle.vy += (Math.random() - 0.5) * beatIntensity * 0.5;
        } else {
          particle.size = Math.max(1, particle.size * 0.95);
        }
        
        // Затухание жизни
        particle.life -= particle.decay;
        if (particle.life <= 0) {
          // Перерождение частицы
          particle.x = Math.random() * this.canvas.width;
          particle.y = Math.random() * this.canvas.height;
          particle.life = 1.0;
          particle.size = Math.random() * 3 + 1;
          particle.color = this.getRandomColor();
        }
      });
    }
    
    drawParticles() {
      // Очистка с эффектом затухания
      this.ctx.fillStyle = 'rgba(15, 15, 15, 0.1)';
      this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
      
      // Рисуем частицы
      this.particles.forEach(particle => {
        this.ctx.save();
        this.ctx.globalAlpha = particle.life;
        this.ctx.fillStyle = particle.color;
        this.ctx.beginPath();
        this.ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
        this.ctx.fill();
        
        // Добавляем свечение
        this.ctx.shadowBlur = 10;
        this.ctx.shadowColor = particle.color;
        this.ctx.fill();
        this.ctx.restore();
      });
      
      // Рисуем связи между близкими частицами
      this.drawConnections();
    }
    
    drawConnections() {
      const maxDistance = 100;
      this.particles.forEach((p1, i) => {
        this.particles.slice(i + 1).forEach(p2 => {
          const dx = p1.x - p2.x;
          const dy = p1.y - p2.y;
          const distance = Math.sqrt(dx * dx + dy * dy);
          
          if (distance < maxDistance) {
            this.ctx.save();
            const opacity = (1 - distance / maxDistance) * 0.3;
            this.ctx.strokeStyle = `rgba(255, 255, 255, ${opacity})`;
            this.ctx.lineWidth = 1;
            this.ctx.beginPath();
            this.ctx.moveTo(p1.x, p1.y);
            this.ctx.lineTo(p2.x, p2.y);
            this.ctx.stroke();
            this.ctx.restore();
          }
        });
      });
    }
    
    animate() {
      if (!this.isActive) return;
      
      const beatIntensity = this.detectBeat();
      this.updateParticles(beatIntensity);
      this.drawParticles();
      
      this.animationId = requestAnimationFrame(() => this.animate());
    }
    
    start() {
      if (this.isActive) return;
      this.isActive = true;
      this.initParticles();
      this.animate();
    }
    
    stop() {
      this.isActive = false;
      if (this.animationId) {
        cancelAnimationFrame(this.animationId);
        this.animationId = null;
      }
      // Очищаем canvas
      if (this.ctx) {
        this.ctx.fillStyle = 'rgba(15, 15, 15, 1)';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
      }
    }
    
    destroy() {
      this.stop();
      this.particles = [];
      this.analyser = null;
      this.dataArray = null;
    }
  }

  // ========== AR-РЕЖИМ ==========
  
  class ARMode {
    constructor(container, lyricsContainer) {
      this.container = container;
      this.lyricsContainer = lyricsContainer;
      this.video = null;
      this.stream = null;
      this.isActive = false;
      this.originalDisplay = null;
    }
    
    async start() {
      if (this.isActive) return;
      
      try {
        // Запрашиваем доступ к камере
        this.stream = await navigator.mediaDevices.getUserMedia({
          video: { 
            facingMode: 'user', // Фронтальная камера
            width: { ideal: 1280 },
            height: { ideal: 720 }
          }
        });
        
        // Создаем video элемент для камеры
        if (!this.video) {
          this.video = document.createElement('video');
          this.video.autoplay = true;
          this.video.playsInline = true;
          this.video.muted = true;
          this.video.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 8999;
            transform: scaleX(-1); /* Зеркальное отражение */
          `;
          document.body.appendChild(this.video);
        }
        
        this.video.srcObject = this.stream;
        this.isActive = true;
        
        // Показываем контейнер с текстом поверх видео
        if (this.lyricsContainer) {
          this.originalDisplay = this.lyricsContainer.style.display;
          this.lyricsContainer.style.display = 'block';
          this.lyricsContainer.style.position = 'fixed';
          this.lyricsContainer.style.zIndex = '9001';
          this.lyricsContainer.style.background = 'rgba(0, 0, 0, 0.3)';
          this.lyricsContainer.style.backdropFilter = 'blur(4px)';
        }
        
        return true;
      } catch (error) {
        console.error('Ошибка доступа к камере:', error);
        alert('Не удалось получить доступ к камере. Убедитесь, что вы разрешили доступ к камере в настройках браузера.');
        return false;
      }
    }
    
    stop() {
      if (!this.isActive) return;
      
      // Останавливаем поток камеры
      if (this.stream) {
        this.stream.getTracks().forEach(track => track.stop());
        this.stream = null;
      }
      
      // Удаляем video элемент
      if (this.video) {
        this.video.srcObject = null;
        this.video.remove();
        this.video = null;
      }
      
      // Восстанавливаем оригинальный стиль контейнера текста
      if (this.lyricsContainer && this.originalDisplay !== null) {
        this.lyricsContainer.style.display = this.originalDisplay;
        this.lyricsContainer.style.position = '';
        this.lyricsContainer.style.zIndex = '';
        this.lyricsContainer.style.background = '';
        this.lyricsContainer.style.backdropFilter = '';
      }
      
      this.isActive = false;
    }
    
    destroy() {
      this.stop();
      this.container = null;
      this.lyricsContainer = null;
    }
  }

  // ========== ЭКСПОРТ ==========
  
  window.KaraokeEffects = {
    ParticleSystem,
    ARMode
  };

})();

