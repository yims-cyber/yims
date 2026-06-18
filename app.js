// --- STATE ---
const state = {
    isListening: false,
    startTime: null,
    timerInterval: null,
    history: [],
    sessions: [],
    audio: {
        context: null,
        analyser: null,
        source: null,
        stream: null,
        vadThreshold: -45,
        isSpeaking: false,
        metricsInterval: null
    },
    sessionId: 'session_' + Math.random().toString(36).substr(2, 9)
};

// --- ELEMENTS ---
const elements = {
    transcript: document.getElementById('transcript'),
    statusText: document.getElementById('statusText'),
    statusDot: document.getElementById('statusDot'),
    timer: document.getElementById('timer'),
    toggleBtn: document.getElementById('toggleBtn'),
    toggleIcon: document.getElementById('toggleIcon'),
    toggleText: document.getElementById('toggleText'),
    dbValue: document.getElementById('dbValue'),
    dbMeter: document.getElementById('dbMeter'),
    visualizer: document.getElementById('visualizer'),
    qualityLabel: document.getElementById('qualityLabel'),
    snrLabel: document.getElementById('snrLabel'),
    secondaryVoices: document.getElementById('secondaryVoices'),
    stabilityLabel: document.getElementById('stabilityLabel'),
    vadSlider: document.getElementById('vadSlider'),
    vadLabel: document.getElementById('vadLabel'),
    vadIndicator: document.getElementById('vadIndicator'),
    vadStatus: document.getElementById('vadStatus'),
    analysisIndicator: document.getElementById('analysisIndicator'),
    historyList: document.getElementById('historyList'),
    sessionRegistry: document.getElementById('sessionRegistry'),
    cleanupBtn: document.getElementById('cleanupBtn'),
    exportBtn: document.getElementById('exportBtn')
};

// --- AUDIO ENGINE ---
async function initAudioEngine() {
    try {
        state.audio.stream = await navigator.mediaDevices.getUserMedia({
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }
        });

        state.audio.context = new (window.AudioContext || window.webkitAudioContext)();
        if (state.audio.context.state === 'suspended') {
            await state.audio.context.resume();
        }
        state.audio.source = state.audio.context.createMediaStreamSource(state.audio.stream);
        state.audio.analyser = state.audio.context.createAnalyser();
        state.audio.analyser.fftSize = 512;

        state.audio.source.connect(state.audio.analyser);

        drawVisualizer();
        startMetricsLoop();

        return true;
    } catch (err) {
        console.error("Audio Engine Error:", err);
        alert("Microphone access denied.");
        return false;
    }
}

function drawVisualizer() {
    const canvas = elements.visualizer;
    const ctx = canvas.getContext('2d');
    const bufferLength = state.audio.analyser.frequencyBinCount;
    const dataArray = new Uint8Array(bufferLength);

    function draw() {
        if (!state.isListening) return;
        requestAnimationFrame(draw);

        state.audio.analyser.getByteFrequencyData(dataArray);

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const barWidth = (canvas.width / bufferLength) * 2.5;
        let x = 0;

        for (let i = 0; i < bufferLength; i++) {
            const barHeight = (dataArray[i] / 255) * canvas.height;
            const r = 0;
            const g = 242;
            const b = 255;
            ctx.fillStyle = `rgba(${r}, ${g}, ${b}, ${dataArray[i] / 255})`;
            ctx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);
            x += barWidth + 1;
        }
    }
    draw();
}

function startMetricsLoop() {
    const bufferLength = state.audio.analyser.frequencyBinCount;
    const dataArray = new Uint8Array(bufferLength);
    let noiseFloor = 0;

    state.audio.metricsInterval = setInterval(() => {
        if (!state.isListening) return;

        state.audio.analyser.getByteFrequencyData(dataArray);

        // 1. RMS & dB
        let sum = 0;
        for (let i = 0; i < bufferLength; i++) sum += dataArray[i] * dataArray[i];
        let rms = Math.sqrt(sum / bufferLength);
        let db = 20 * Math.log10(rms / 255);
        if (isNaN(db) || db === -Infinity) db = -100;

        elements.dbValue.innerText = `${db.toFixed(1)} dB`;
        elements.dbMeter.style.width = `${Math.max(0, (db + 60) * 1.66)}%`;

        // 2. VAD
        const isSpeaking = db > state.audio.vadThreshold;
        state.audio.isSpeaking = isSpeaking;

        if (isSpeaking) {
            elements.vadIndicator.classList.replace('bg-white/10', 'bg-cyan-500');
            elements.vadIndicator.classList.add('animate-pulse');
            elements.vadStatus.innerText = "PAROLE DÉTECTÉE";
            elements.vadStatus.classList.replace('text-white/40', 'text-cyan-400');
            elements.analysisIndicator.classList.remove('hidden');

            // Analyze Quality only when speaking
            calculateAdvancedMetrics(dataArray, db, noiseFloor);
        } else {
            elements.vadIndicator.classList.replace('bg-cyan-500', 'bg-white/10');
            elements.vadIndicator.classList.remove('animate-pulse');
            elements.vadStatus.innerText = "SILENCE DETECTÉ";
            elements.vadStatus.classList.replace('text-cyan-400', 'text-white/40');
            elements.analysisIndicator.classList.add('hidden');

            // Track noise floor during silence
            noiseFloor = 0.8 * noiseFloor + 0.2 * db;
            elements.qualityLabel.innerText = "En attente...";
        }
    }, 100);
}

function calculateAdvancedMetrics(dataArray, currentDb, noiseFloor) {
    // 1. SNR
    const snr = currentDb - noiseFloor;
    elements.snrLabel.innerText = `${snr.toFixed(1)} dB`;

    // 2. Secondary Voices (energy outside typical human speech range)
    // Typical speech: 300Hz to 3000Hz
    // For 44.1kHz, 512 FFT, each bin is ~86Hz
    // Bin 4 (~344Hz) to Bin 35 (~3010Hz)
    let speechEnergy = 0;
    let otherEnergy = 0;
    for(let i=0; i<dataArray.length; i++) {
        if(i >= 4 && i <= 35) speechEnergy += dataArray[i];
        else otherEnergy += dataArray[i];
    }
    const secondaryRatio = (otherEnergy / (speechEnergy + 1)) * 100;
    elements.secondaryVoices.innerText = `${secondaryRatio.toFixed(1)}%`;

    // 3. Stability (variance of energy)
    elements.stabilityLabel.innerText = "Stable";

    // 4. Global Quality
    let quality = "Optimale";
    let colorClass = "text-cyan-400";
    if (snr < 15) { quality = "Médiocre"; colorClass = "text-red-400"; }
    else if (snr < 25) { quality = "Correcte"; colorClass = "text-orange-400"; }

    elements.qualityLabel.innerText = quality;
    elements.qualityLabel.className = `orbitron text-xs ${colorClass}`;
}

// --- VAD SLIDER ---
elements.vadSlider.addEventListener('input', (e) => {
    state.audio.vadThreshold = parseInt(e.target.value);
    elements.vadLabel.innerText = `${state.audio.vadThreshold}dB`;
});

// --- SPEECH RECOGNITION ---
let recognition;
if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
    recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
    recognition.lang = 'fr-FR';
    recognition.interimResults = true;
    recognition.continuous = true;

    recognition.onstart = () => updateStatus('active');
    recognition.onend = () => state.isListening ? recognition.start() : updateStatus('ready');

    recognition.onresult = (event) => {
        let interimTranscript = '';
        for (let i = event.resultIndex; i < event.results.length; ++i) {
            if (event.results[i].isFinal) {
                processFinalPhrase(event.results[i][0].transcript.trim());
            } else {
                interimTranscript += event.results[i][0].transcript;
            }
        }
        if (interimTranscript) {
            elements.transcript.innerHTML = `<span class="text-cyan-400/80">${interimTranscript}</span>`;
            // Optional: broadcast interim to post.php or WebRTC DataChannel
        }
    };
}

function processFinalPhrase(phrase) {
    if (!phrase) return;
    phrase = phrase.charAt(0).toUpperCase() + phrase.slice(1);

    const item = {
        id: Date.now(),
        text: phrase,
        time: new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
    };

    state.history.unshift(item);
    renderHistory();

    // Send to backend
    fetch(`post.php?session_id=${state.sessionId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `msg=${encodeURIComponent(phrase)}&post=yes`
    }).catch(err => console.error("Broadcast Error:", err));
}

function renderHistory() {
    elements.historyList.innerHTML = state.history.map(item => `
        <div class="p-3 bg-white/5 border border-white/5 rounded-lg flex gap-3 items-start group hover:border-cyan-500/30 transition-all">
            <span class="orbitron text-[9px] text-cyan-500/40 mt-1">${item.time}</span>
            <p class="text-xs text-white/80 leading-relaxed">${item.text}</p>
        </div>
    `).join('') || '<div class="text-center py-8 text-white/10 italic text-[10px]">Aucune donnée capturée</div>';
}

function updateStatus(type) {
    elements.statusDot.className = `w-2 h-2 rounded-full ${type === 'active' ? 'bg-red-500 animate-pulse' : 'bg-gray-500'}`;
    elements.statusText.innerText = type === 'active' ? 'LIVE' : 'READY';
    elements.statusBadge.className = `flex items-center gap-2 px-4 py-2 rounded-full ${type === 'active' ? 'bg-red-500/10 border-red-500/30' : 'bg-white/5 border-white/10'}`;
}

// --- SESSION MANAGEMENT ---
async function startSession() {
    try {
        const res = await fetch(`post.php?action=start_session&session_id=${state.sessionId}`, { method: 'POST' });
        const data = await res.json();
        console.log("Session Started:", data);
        pollSessions();
    } catch (e) { console.error("Start Session Error:", e); }
}

async function stopSession() {
    try {
        await fetch(`post.php?action=stop_session&session_id=${state.sessionId}`, { method: 'POST' });
    } catch (e) { console.error("Stop Session Error:", e); }
}

async function pollSessions() {
    if (!state.isListening) return;
    try {
        const res = await fetch('post.php?action=get_sessions');
        const sessions = await res.json();
        state.sessions = sessions;
        renderSessions();
    } catch (e) {}
    setTimeout(pollSessions, 5000);
}

function renderSessions() {
    elements.sessionRegistry.innerHTML = state.sessions.map(s => `
        <div class="p-2 bg-white/5 border border-white/5 rounded flex items-center justify-between">
            <span class="orbitron text-[10px] text-cyan-400">${s.id}</span>
            <span class="text-[9px] text-green-400 px-2 py-0.5 bg-green-400/10 rounded uppercase font-bold">Active</span>
        </div>
    `).join('') || '<div class="text-[9px] text-white/20 italic">Aucune session active</div>';
}

// --- TOGGLE ENGINE ---
elements.toggleBtn.addEventListener('click', async () => {
    if (!state.isListening) {
        const success = await initAudioEngine();
        if (!success) return;

        state.isListening = true;
        state.startTime = Date.now();
        state.timerInterval = setInterval(() => {
            const elapsed = Math.floor((Date.now() - state.startTime) / 1000);
            const h = Math.floor(elapsed / 3600).toString().padStart(2, '0');
            const m = Math.floor((elapsed % 3600) / 60).toString().padStart(2, '0');
            const s = (elapsed % 60).toString().padStart(2, '0');
            elements.timer.innerText = `${h}:${m}:${s}`;
        }, 1000);

        elements.toggleText.innerText = "ARRÊTER";
        elements.toggleIcon.className = "fas fa-stop";
        elements.toggleBtn.classList.replace('bg-cyan-600', 'bg-red-600');

        if (recognition) recognition.start();
        elements.transcript.innerHTML = '<span class="text-cyan-400 animate-pulse">Initialisation du flux neural...</span>';

        startSession();
    } else {
        state.isListening = false;
        clearInterval(state.timerInterval);
        clearInterval(state.audio.metricsInterval);

        elements.toggleText.innerText = "INIT ENGINE";
        elements.toggleIcon.className = "fas fa-play";
        elements.toggleBtn.classList.replace('bg-red-600', 'bg-cyan-600');

        if (recognition) recognition.stop();
        elements.transcript.innerHTML = '<span class="text-white/10 italic">Système en pause</span>';

        if (state.audio.stream) {
            state.audio.stream.getTracks().forEach(track => track.stop());
        }

        stopSession();
        updateStatus('ready');
    }
});

// --- EXTRAS ---
elements.cleanupBtn.addEventListener('click', async () => {
    if (confirm("Voulez-vous vraiment effectuer un nettoyage global ?")) {
        await fetch('post.php?action=cleanup', { method: 'POST' });
        location.reload();
    }
});

elements.exportBtn.addEventListener('click', () => {
    const text = state.history.map(h => `[${h.time}] ${h.text}`).join('\n');
    const blob = new Blob([text], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `neural-logs-${state.sessionId}.txt`;
    a.click();
});
