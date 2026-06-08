/**
 * Orateur Ultra - Stream Manager V5
 * Gère le flux audio WebRTC et le DataChannel pour la synchronisation ultra-rapide.
 */

class StreamManager {
    constructor() {
        this.pc = null;
        this.dc = null;
        this.candidatesAdded = new Set();
        this.pollInterval = null;
    }

    async init(stream) {
        const sessionId = window.sessionManager.currentSession?.id;
        if (!sessionId) return;

        // Clear previous signaling
        await fetch(`signaling.php?action=clear&session_id=${sessionId}`, { method: 'POST' });

        this.pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });

        // Transcription Data Channel
        this.dc = this.pc.createDataChannel("transcription", { negotiated: true, id: 0 });

        // Add audio tracks
        stream.getAudioTracks().forEach(track => {
            this.pc.addTrack(track, stream);
        });

        this.pc.onicecandidate = ({candidate}) => {
            if (candidate) {
                fetch(`signaling.php?action=push&role=orator&session_id=${sessionId}`, {
                    method: 'POST',
                    body: JSON.stringify({ candidate })
                });
            }
        };

        const offer = await this.pc.createOffer();
        await this.pc.setLocalDescription(offer);
        await fetch(`signaling.php?action=push&role=orator&session_id=${sessionId}`, {
            method: 'POST',
            body: JSON.stringify({ offer })
        });

        // Polling for auditor answers
        this.pollInterval = setInterval(async () => {
            try {
                const res = await fetch(`signaling.php?session_id=${sessionId}`);
                const data = await res.json();

                // Get the last answer (simplified for p2p broadcast)
                const lastAuditorId = Object.keys(data.answers).pop();
                if (lastAuditorId && this.pc.signalingState === 'have-local-offer') {
                    await this.pc.setRemoteDescription(new RTCSessionDescription(data.answers[lastAuditorId]));
                }

                // Add candidates from auditors
                if (data.auditor_candidates && this.pc.remoteDescription) {
                    Object.values(data.auditor_candidates).forEach(candList => {
                        candList.forEach(c => {
                            const s = JSON.stringify(c);
                            if (!this.candidatesAdded.has(s)) {
                                this.pc.addIceCandidate(new RTCIceCandidate(c)).catch(()=>{});
                                this.candidatesAdded.add(s);
                            }
                        });
                    });
                }
            } catch (e) {}
        }, 2000);
    }

    stop() {
        if (this.pollInterval) clearInterval(this.pollInterval);
        if (this.pc) this.pc.close();
        this.pc = null;
        this.dc = null;
    }
}

window.streamManager = new StreamManager();
