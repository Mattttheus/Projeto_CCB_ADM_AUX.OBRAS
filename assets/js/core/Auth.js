// Core — espelha app/Core/Auth.php (requireUser, requireAdmin, hasFullProjectAccess).
// Atenção: no modo GitHub Pages a autenticação é apenas demonstrativa (client-side).
// Para produção, a versão PHP com sessão segura continua disponível no repositório.
import { store } from '../infrastructure/persistence/LocalStore.js';

const SESSION_KEY = 'auxiliar-obras-session-v1';

/** Perfis com acesso total a qualquer obra, como no PHP. */
export const FULL_ACCESS_ROLES = Object.freeze(['admin', 'suporte']);

export const ROLE_LABELS = Object.freeze({
    admin: 'Administrador',
    suporte: 'Suporte',
    colaborador: 'Colaborador',
});

function readSession() {
    try {
        return JSON.parse(sessionStorage.getItem(SESSION_KEY));
    } catch {
        return null;
    }
}

export const Auth = {
    login(email, password) {
        const user = store.state.users.find(
            item => item.email.toLowerCase() === String(email ?? '').trim().toLowerCase()
        );
        if (!user || user.password !== password) {
            throw new Error('Credenciais inválidas.');
        }
        if (!user.active) {
            throw new Error('Usuário aguardando liberação de acesso.');
        }
        sessionStorage.setItem(SESSION_KEY, JSON.stringify({ userId: user.id, since: new Date().toISOString() }));
        store.log(`Login realizado por ${user.name} (${ROLE_LABELS[user.role] ?? user.role}).`);
        return user;
    },

    logout() {
        const user = this.user();
        if (user) {
            store.log(`Sessão encerrada por ${user.name}.`);
        }
        sessionStorage.removeItem(SESSION_KEY);
    },

    /** Usuário da sessão; retorna null se não existir ou tiver sido desativado. */
    user() {
        const session = readSession();
        if (!session) return null;
        const user = store.state.users.find(item => item.id === session.userId) ?? null;
        return user && user.active ? user : null;
    },

    isAuthenticated() {
        return this.user() !== null;
    },

    isAdmin() {
        return this.user()?.role === 'admin';
    },

    hasFullProjectAccess() {
        return FULL_ACCESS_ROLES.includes(this.user()?.role);
    },

    canAccess(roles) {
        if (!roles || roles.length === 0) return this.isAuthenticated();
        return roles.includes(this.user()?.role);
    },
};
