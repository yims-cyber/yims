/**
 * Orateur Ultra - Moteur Audio V5.1 (Pro Studio)
 */

class AudioEngine {
    constructor() {
        this.context = null;
        this.stream = null;
        this.source = null;
        this.analyser = null;

        // Chaîne de traitement
        this.highPass = null;   // Coupe-bas (80Hz)
        this.clarity = null;    // EQ Clarté (3kHz)
        this.gate = null;       // Noise Gate (GainNode)
        this.limiter = null;    // Peak Limiter
        this.outputGain = null; // AGC / Volume final

        this.metrics = {
            db: -100,
            noiseFloor: -100,
            snr: 0,
            isSpeaking: false
        };

        this.agcEnabled = true;
        this.onMetricsUpdate = null;
    }

    async init(sourceType = 'mic') {
        try {
            if (this.stream) this.stream.getTracks().forEach(t => t.stop());

            this.stream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: false // On gère nous-même pour plus de contrôle
                }
            });

            this.context = new (window.AudioContext || window.webkitAudioContext)();
            this.source = this.context.createMediaStreamSource(this.stream);

            // 1. Coupe-bas (High-pass 100Hz) - Élimine les vibrations
            this.highPass = this.context.createBiquadFilter();
            this.highPass.type = 'highpass';
            this.highPass.frequency.value = 100;

            // 2. EQ Clarté (Peaking 3000Hz) - Rend la voix plus présente
            this.clarity = this.context.createBiquadFilter();
            this.clarity.type = 'peaking';
            this.clarity.frequency.value = 3000;
            this.clarity.Q.value = 1.0;
            this.clarity.gain.value = 3; // Boost de 3dB

            // 3. Noise Gate (GainNode piloté par l'analyse)
            this.gate = this.context.createGain();
            this.gate.gain.value = 1.0;

            // 4. Limiteur de Crête (Hard Limiter) - Évite TOUTE saturation
            this.limiter = this.context.createDynamicsCompressor();
            this.limiter.threshold.value = -1.0; // Limite à -1dB
            this.limiter.knee.value = 0;
            this.limiter.ratio.value = 20;
            this.limiter.attack.value = 0.001; // Instantané
            this.limiter.release.value = 0.1;

            this.outputGain = this.context.createGain();

            this.analyser = this.context.createAnalyser();
            this.analyser.fftSize = 256;

            // Chaînage professionnel
            this.source.connect(this.highPass);
            this.highPass.connect(this.clarity);
            this.clarity.connect(this.gate);
            this.gate.connect(this.limiter);
            this.limiter.connect(this.outputGain);
            this.outputGain.connect(this.analyser);

            this.startProcessing();
            return true;
        } catch (e) {
            console.error("AudioEngine Error:", e);
            throw e;
        }
    }

    startProcessing() {
        const dataArray = new Uint8Array(this.analyser.frequencyBinCount);

        const loop = () => {
            if (!this.stream) return;
            requestAnimationFrame(loop);

            this.analyser.getByteFrequencyData(dataArray);

            let sum = 0;
            for(let i=0; i<dataArray.length; i++) sum += dataArray[i];
            let avg = sum / dataArray.length;
            let currentDb = 20 * Math.log10(avg / 255);
            if (isNaN(currentDb) || currentDb === -Infinity) currentDb = -100;

            this.metrics.db = currentDb;

            // Logic de Noise Gate & Détection de parole
            if (currentDb < -55) {
                this.metrics.noiseFloor = this.metrics.noiseFloor * 0.95 + currentDb * 0.05;
                this.metrics.isSpeaking = false;
                // Fermeture douce du gate
                this.gate.gain.setTargetAtTime(0.01, this.context.currentTime, 0.05);
            } else {
                const threshold = this.metrics.noiseFloor + 12;
                this.metrics.isSpeaking = currentDb > threshold;

                if (this.metrics.isSpeaking) {
                    this.metrics.snr = currentDb - this.metrics.noiseFloor;
                    // Ouverture instantanée du gate
                    this.gate.gain.setTargetAtTime(1.0, this.context.currentTime, 0.01);
                }
            }

            // AGC Logiciel
            if (this.agcEnabled && currentDb > -100) {
                if (currentDb < -30) this.outputGain.gain.value = Math.min(6.0, this.outputGain.gain.value + 0.05);
                else if (currentDb > -5) this.outputGain.gain.value = Math.max(0.5, this.outputGain.gain.value - 0.1);
            }

            if (this.onMetricsUpdate) this.onMetricsUpdate(this.metrics, dataArray);
        };
        loop();
    }

    setAgc(enabled) { this.agcEnabled = enabled; }
}

window.AudioEngine = new AudioEngine();
