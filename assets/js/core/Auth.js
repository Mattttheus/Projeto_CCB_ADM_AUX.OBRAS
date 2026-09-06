// Core — espelha app/Core/Auth.php (requireUser, requireAdmin, hasFullProjectAccess).
// Dois modos, mesma interface:
//   supabase → Supabase Auth (e-mail/senha) + perfil na tabela `usuarios` (via auth_uid)
//   local    → sessão demonstrativa em sessionStorage (como a versão estática original)
import { store, BACKEND_MODE } from '../infrastructure/persistence/Store.js';
import { getSupabase } from '../infrastructure/supabase/SupabaseClient.js';

const SESSION_KEY = 'auxiliar-obras-session-v1';

/** Perfis com acesso total a qualquer obra, como no PHP (hasFullProjectAccess). */
export const FULL_ACCESS_ROLES = Object.freeze(['admin', 'suporte', 'engenheiro', 'mestre_obras']);

export const ROLE_LABELS = Object.freeze({
    admin: 'Administrador',
    suporte: 'Suporte',
    engenheiro: 'Engenheiro',
    mestre_obras: 'Mestre de obras',
    colaborador: 'Colaborador',
    comum: 'Comum',
    user: 'Comum',
});

let currentUser = null;

function readSession() {
    try {
        return JSON.parse(sessionStorage.getItem(SESSION_KEY));
    } catch {
        return null;
    }
}

async function loadRemoteProfile() {
    const { data: { user: authUser } } = await getSupabase().auth.getUser();
    if (!authUser) return null;
    const profile = await store.profileForAuthUid(authUser.id);
    if (profile && !profile.active) {
        await getSupabase().auth.signOut();
        throw new Error('Usuário aguardando liberação de acesso pelo administrador.');
    }
    return profile;
}

export const Auth = {
    /** Restaura a sessão ao abrir o app (chamado uma vez no bootstrap). */
    async init() {
        if (BACKEND_MODE !== 'supabase') {
            const session = readSession();
            const user = session ? store.state.users.find(item => item.id === session.userId) : null;
            currentUser = user && user.active ? user : null;
            return;
        }
        getSupabase().auth.onAuthStateChange(event => {
            if (event === 'SIGNED_OUT') currentUser = null;
        });
        try {
            currentUser = await loadRemoteProfile();
        } catch {
            currentUser = null;
        }
    },

    async login(email, password) {
        if (BACKEND_MODE !== 'supabase') {
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
            currentUser = user;
            return user;
        }

        const { error } = await getSupabase().auth.signInWithPassword({
            email: String(email ?? '').trim(),
            password,
        });
        if (error) throw new Error('Credenciais inválidas.');
        currentUser = await loadRemoteProfile();
        if (!currentUser) {
            await getSupabase().auth.signOut();
            throw new Error('Perfil não encontrado. Peça ao administrador para concluir seu cadastro.');
        }
        store.log(`Login realizado por ${currentUser.name}.`);
        return currentUser;
    },

    async logout() {
        const user = this.user();
        if (user) store.log(`Sessão encerrada por ${user.name}.`);
        currentUser = null;
        sessionStorage.removeItem(SESSION_KEY);
        if (BACKEND_MODE === 'supabase') await getSupabase().auth.signOut();
    },

    /** Usuário da sessão; null se não existir ou tiver sido desativado. */
    user() {
        return currentUser;
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
