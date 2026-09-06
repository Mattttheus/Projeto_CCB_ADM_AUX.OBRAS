// Infrastructure/Supabase — cliente supabase-js via CDN (ES Module oficial).
// Mantido em módulo próprio para que o modo demonstração nunca precise
// instanciar o cliente remoto.
import { createClient } from 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.45.4/+esm';
import { SUPABASE_URL, SUPABASE_ANON_KEY } from './config.js';

let client = null;
let signupClient = null;

/** Cliente principal (persiste a sessão do usuário no navegador). */
export function getSupabase() {
    if (!client) {
        client = createClient(SUPABASE_URL, SUPABASE_ANON_KEY, {
            auth: { persistSession: true, autoRefreshToken: true, storageKey: 'auxiliar-obras-auth' },
        });
    }
    return client;
}

/**
 * Cliente auxiliar SEM persistência de sessão — usado pelo admin para
 * cadastrar novos usuários (signUp) sem derrubar a própria sessão.
 */
export function getSignupClient() {
    if (!signupClient) {
        signupClient = createClient(SUPABASE_URL, SUPABASE_ANON_KEY, {
            auth: { persistSession: false, autoRefreshToken: false, storageKey: 'auxiliar-obras-signup' },
        });
    }
    return signupClient;
}
