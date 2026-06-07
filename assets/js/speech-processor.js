/**
 * Orateur Ultra - Speech Processor V5.1 (IA Double Flux)
 */

class SpeechProcessor {
    constructor() {
        this.recognition = null;
        this.isListening = false;
        this.history = JSON.parse(localStorage.getItem('orateur_pro_ultra_history') || '[]');

        this.onInterimResult = null;
        this.onRawFinalResult = null; // Nouveau: pour voir le texte brut avant l'IA
        this.onFinalResult = null;
        this.onAIProcessing = null;
    }

    init() {
        if (!('webkitSpeechRecognition' in window)) return;

        this.recognition = new webkitSpeechRecognition();
        this.recognition.lang = 'fr-FR';
        this.recognition.continuous = true;
        this.recognition.interimResults = true;

        this.recognition.onresult = async (event) => {
            let interim = '';
            let final = '';

            for (let i = event.resultIndex; i < event.results.length; ++i) {
                if (event.results[i].isFinal) final += event.results[i][0].transcript;
                else interim += event.results[i][0].transcript;
            }

            if (interim && this.onInterimResult) {
                this.onInterimResult(interim);
                this.broadcast({ type: 'interim', text: interim });
            }

            if (final) {
                const rawText = final.trim();
                if (rawText) {
                    if (this.onRawFinalResult) this.onRawFinalResult(rawText);
                    await this.processFinalText(rawText);
                }
            }
        };

        this.recognition.onend = () => { if (this.isListening) this.recognition.start(); };
    }

    async processFinalText(text) {
        if (this.onAIProcessing) this.onAIProcessing(true);

        const refinedText = await this.refineWithAI(text);

        const item = {
            id: Date.now(),
            text: refinedText,
            raw: text,
            time: new Date().toLocaleTimeString('fr-FR')
        };

        this.history.unshift(item);
        localStorage.setItem('orateur_pro_ultra_history', JSON.stringify(this.history.slice(0, 50)));

        if (this.onFinalResult) this.onFinalResult(item);
        if (this.onAIProcessing) this.onAIProcessing(false);

        this.broadcast({ type: 'final', text: refinedText });
    }

    async refineWithAI(text) {
        const key = localStorage.getItem('openai_key');
        if (!key || text.length < 5) return text;
        try {
            const res = await fetch('https://api.openai.com/v1/chat/completions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${key}` },
                body: JSON.stringify({
                    model: "gpt-4o-mini",
                    messages: [
                        { role: "system", content: "Tu es un assistant de prédication. Reformule le texte suivant pour qu'il soit professionnel, sans fautes, et élégant. Garde le sens original. Retourne uniquement le texte corrigé." },
                        { role: "user", content: text }
                    ],
                    temperature: 0.3
                })
            });
            const data = await res.json();
            return data.choices?.[0]?.message?.content?.trim() || text;
        } catch (e) { return text; }
    }

    start() {
        this.isListening = true;
        if (!this.recognition) this.init();
        this.recognition.start();
    }

    broadcast(data) {
        // P2P WebRTC
        if (window.StreamManager?.dc?.readyState === 'open') {
            window.StreamManager.dc.send(JSON.stringify({
                ...data,
                timestamp: Date.now(),
                is_speaking: window.AudioEngine?.metrics.isSpeaking
            }));
        }
        // Backend Fallback
        const sessionId = window.SessionManager?.currentSession?.id;
        if (!sessionId) return;
        const formData = new URLSearchParams();
        formData.append('session_id', sessionId);
        formData.append('msg', data.text);
        formData.append('type', data.type);
        formData.append('speaking', window.AudioEngine?.metrics.isSpeaking ? 'yes' : 'no');
        fetch('api/broadcast.php', { method: 'POST', body: formData }).catch(() => {});
    }
}
window.SpeechProcessor = new SpeechProcessor();
