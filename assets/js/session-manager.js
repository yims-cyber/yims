/**
 * Orateur Ultra - Session Manager V5
 */

class SessionManager {
    constructor() {
        this.currentSession = null;
        this.participantsCount = 0;
        this.history = [];
    }

    async startSession(name, orator) {
        const id = 'live_' + Math.random().toString(36).substr(2, 6);
        const response = await fetch('api/session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=start&session_id=${id}&session_name=${encodeURIComponent(name)}&orator_name=${encodeURIComponent(orator)}`
        });
        const data = await response.json();
        if (data.status === 'success') {
            this.currentSession = data.session;
            localStorage.setItem('orateur_last_session', JSON.stringify(this.currentSession));
            return this.currentSession;
        }
        throw new Error("Erreur de création de session");
    }

    async stopSession() {
        if (!this.currentSession) return;
        await fetch('api/session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=stop&session_id=${this.currentSession.id}`
        });
        this.currentSession = null;
        localStorage.removeItem('orateur_last_session');
    }

    async updateStats(count) {
        if (!this.currentSession) return;
        this.participantsCount = count;
        await fetch('api/session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=stats&session_id=${this.currentSession.id}&count=${count}`
        });
    }

    async getHistory() {
        const res = await fetch('api/session.php?action=history');
        const data = await res.json();
        return data.history || [];
    }
}

window.SessionManager = new SessionManager();
