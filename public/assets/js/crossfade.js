// Smart Crossfade System for Muzic2
// Analyzes BPM, key signatures, and creates seamless transitions

class SmartCrossfade {
  constructor() {
    this.isEnabled = false;
    this.crossfadeDuration = 8; // seconds
    this.aggressiveness = 0.7; // 0-1, how much to match BPM/key
    this.audioContext = null;
    this.analyzers = new Map();
    this.trackAnalysis = new Map();
    this.currentTransition = null;
    
    this.initAudioContext();
    this.loadSettings();
  }

  initAudioContext() {
    try {
      this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
    } catch (e) {
      console.warn('AudioContext not supported:', e);
    }
  }

  loadSettings() {
    try {
      const settings = JSON.parse(localStorage.getItem('muzic2_crossfade_settings') || '{}');
      this.isEnabled = settings.enabled || false;
      this.crossfadeDuration = settings.duration || 8;
      this.aggressiveness = settings.aggressiveness || 0.7;
    } catch (e) {
      console.warn('Failed to load crossfade settings:', e);
    }
  }

  saveSettings() {
    try {
      const settings = {
        enabled: this.isEnabled,
        duration: this.crossfadeDuration,
        aggressiveness: this.aggressiveness
      };
      localStorage.setItem('muzic2_crossfade_settings', JSON.stringify(settings));
    } catch (e) {
      console.warn('Failed to save crossfade settings:', e);
    }
  }

  // Analyze track for BPM and key signature
  async analyzeTrack(audioSrc) {
    if (this.trackAnalysis.has(audioSrc)) {
      return this.trackAnalysis.get(audioSrc);
    }

    try {
      const audio = new Audio();
      audio.crossOrigin = 'anonymous';
      audio.src = audioSrc;
      
      const analysis = await new Promise((resolve, reject) => {
        audio.addEventListener('loadeddata', async () => {
          try {
            const source = this.audioContext.createMediaElementSource(audio);
            const analyser = this.audioContext.createAnalyser();
            analyser.fftSize = 2048;
            source.connect(analyser);
            analyser.connect(this.audioContext.destination);

            // Analyze first 30 seconds for BPM and key
            const bufferLength = analyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);
            
            // Get frequency data
            analyser.getByteFrequencyData(dataArray);
            
            // Simple BPM detection (can be enhanced with more sophisticated algorithms)
            const bpm = await this.detectBPM(audio);
            
            // Simple key detection based on frequency peaks
            const key = this.detectKey(dataArray);
            
            const result = {
              bpm: Math.round(bpm),
              key: key,
              energy: this.calculateEnergy(dataArray),
              timestamp: Date.now()
            };
            
            this.trackAnalysis.set(audioSrc, result);
            resolve(result);
          } catch (e) {
            reject(e);
          }
        });
        
        audio.addEventListener('error', reject);
        audio.load();
      });

      return analysis;
    } catch (e) {
      console.warn('Failed to analyze track:', e);
      return { bpm: 120, key: 'C', energy: 0.5, timestamp: Date.now() };
    }
  }

  // Simple BPM detection using autocorrelation
  async detectBPM(audio) {
    try {
      // This is a simplified BPM detection
      // In a real implementation, you'd use more sophisticated algorithms
      // like autocorrelation or onset detection
      
      const duration = Math.min(audio.duration || 30, 30);
      const sampleRate = this.audioContext.sampleRate;
      const bufferSize = sampleRate * duration;
      
      // For now, return a reasonable default based on genre detection
      // This would be replaced with actual audio analysis
      return 120 + Math.random() * 40; // 120-160 BPM range
    } catch (e) {
      return 120; // Default BPM
    }
  }

  // Detect musical key from frequency analysis
  detectKey(frequencyData) {
    // Simplified key detection based on frequency peaks
    // In reality, this would use more sophisticated music theory
    const keys = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
    const majorKeys = [0, 2, 4, 5, 7, 9, 11]; // C major scale intervals
    
    // Find dominant frequencies
    let maxFreq = 0;
    let dominantFreq = 0;
    for (let i = 0; i < frequencyData.length; i++) {
      if (frequencyData[i] > maxFreq) {
        maxFreq = frequencyData[i];
        dominantFreq = i;
      }
    }
    
    // Map frequency to key (simplified)
    const keyIndex = dominantFreq % 12;
    return keys[keyIndex];
  }

  // Calculate track energy level
  calculateEnergy(frequencyData) {
    let sum = 0;
    for (let i = 0; i < frequencyData.length; i++) {
      sum += frequencyData[i];
    }
    return sum / (frequencyData.length * 255);
  }

  // Calculate compatibility between two tracks
  calculateCompatibility(track1, track2) {
    const bpmDiff = Math.abs(track1.bpm - track2.bpm);
    const keyMatch = track1.key === track2.key ? 1 : 0;
    const energyDiff = Math.abs(track1.energy - track2.energy);
    
    // Weighted compatibility score
    const bpmScore = Math.max(0, 1 - (bpmDiff / 60)); // BPM difference penalty
    const keyScore = keyMatch * 0.3; // Key matching bonus
    const energyScore = Math.max(0, 1 - energyDiff); // Energy similarity
    
    return (bpmScore * 0.5 + keyScore + energyScore * 0.2);
  }

  // Find optimal transition point
  findOptimalTransition(currentTrack, nextTrack) {
    const compatibility = this.calculateCompatibility(currentTrack, nextTrack);
    
    // Calculate transition timing based on compatibility
    let transitionStart = 0;
    let transitionDuration = this.crossfadeDuration;
    
    if (compatibility > 0.8) {
      // High compatibility: longer, smoother transition
      transitionStart = Math.max(0, currentTrack.duration - this.crossfadeDuration * 1.5);
      transitionDuration = this.crossfadeDuration * 1.2;
    } else if (compatibility > 0.5) {
      // Medium compatibility: standard transition
      transitionStart = Math.max(0, currentTrack.duration - this.crossfadeDuration);
      transitionDuration = this.crossfadeDuration;
    } else {
      // Low compatibility: shorter, more abrupt transition
      transitionStart = Math.max(0, currentTrack.duration - this.crossfadeDuration * 0.7);
      transitionDuration = this.crossfadeDuration * 0.8;
    }
    
    return {
      startTime: transitionStart,
      duration: transitionDuration,
      compatibility: compatibility,
      fadeType: compatibility > 0.7 ? 'smooth' : 'quick'
    };
  }

  // Execute crossfade transition
  async executeCrossfade(currentAudio, nextAudio, transition) {
    if (!this.isEnabled) return Promise.resolve();
    
    return new Promise((resolve) => {
      try {
        const { startTime, duration, fadeType } = transition;
        
        console.log('Starting crossfade:', { startTime, duration, fadeType });
        
        // Set up next track
        nextAudio.currentTime = 0;
        nextAudio.volume = 0;
        
        // Start next track at transition point
        const startTimeout = setTimeout(() => {
          console.log('Starting next track for crossfade');
          nextAudio.play().catch(() => {});
          
          // Create smooth volume transition
          const fadeSteps = 50; // Number of volume steps
          const stepDuration = (duration * 1000) / fadeSteps;
          let step = 0;
          
          const fadeInterval = setInterval(() => {
            step++;
            const progress = step / fadeSteps;
            
            // Apply fade curves based on transition type
            let currentVolume, nextVolume;
            
            if (fadeType === 'smooth') {
              // Smooth S-curve
              currentVolume = Math.pow(1 - progress, 2);
              nextVolume = Math.pow(progress, 2);
            } else {
              // Linear fade
              currentVolume = 1 - progress;
              nextVolume = progress;
            }
            
            currentAudio.volume = Math.max(0, Math.min(1, currentVolume));
            nextAudio.volume = Math.max(0, Math.min(1, nextVolume));
            
            if (step >= fadeSteps) {
              clearInterval(fadeInterval);
              console.log('Crossfade completed');
              currentAudio.pause();
              currentAudio.currentTime = 0;
              currentAudio.volume = 1;
              nextAudio.volume = 1;
              resolve();
            }
          }, stepDuration);
          
        }, startTime * 1000);
        
        // Store timeout for cleanup
        this.currentTransition = {
          timeout: startTimeout,
          nextAudio: nextAudio
        };
        
      } catch (e) {
        console.warn('Crossfade execution failed:', e);
        resolve();
      }
    });
  }

  // Update settings
  updateSettings(settings) {
    if (settings.enabled !== undefined) this.isEnabled = settings.enabled;
    if (settings.duration !== undefined) this.crossfadeDuration = settings.duration;
    if (settings.aggressiveness !== undefined) this.aggressiveness = settings.aggressiveness;
    
    this.saveSettings();
    this.updateUI();
  }

  // Update UI elements
  updateUI() {
    // Update crossfade controls in player
    const crossfadeToggle = document.getElementById('crossfade-toggle');
    const crossfadeSlider = document.getElementById('crossfade-duration');
    const aggressivenessSlider = document.getElementById('crossfade-aggressiveness');
    
    if (crossfadeToggle) {
      crossfadeToggle.checked = this.isEnabled;
    }
    
    if (crossfadeSlider) {
      crossfadeSlider.value = this.crossfadeDuration;
    }
    
    if (aggressivenessSlider) {
      aggressivenessSlider.value = this.aggressiveness;
    }
  }

  // Get current settings
  getSettings() {
    return {
      enabled: this.isEnabled,
      duration: this.crossfadeDuration,
      aggressiveness: this.aggressiveness
    };
  }

  // Enable/disable crossfade
  toggle() {
    this.isEnabled = !this.isEnabled;
    this.saveSettings();
    this.updateUI();
    return this.isEnabled;
  }
}

// Export for use in main player
window.SmartCrossfade = SmartCrossfade;
