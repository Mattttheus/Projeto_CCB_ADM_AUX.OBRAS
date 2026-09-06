// Infrastructure/Persistence — fachada que escolhe o repositório conforme o modo:
//   BACKEND_MODE 'supabase' → SupabaseStore (PostgreSQL real com RLS)
//   BACKEND_MODE 'local'    → LocalStore (demonstração em localStorage)
// Ambos expõem exatamente a mesma interface pública (state, init, refresh,
// obraName, obrasFor, canAccessObra, obraProgress e mutações assíncronas),
// então as camadas superiores não mudam ao alternar entre os modos.
import { BACKEND_MODE } from '../supabase/config.js';
import { LocalStore } from './LocalStore.js';

export { BACKEND_MODE };

// Import dinâmico: no modo demonstração a biblioteca supabase-js (CDN) nem é
// baixada — a SPA local funciona inclusive offline.
let store;
if (BACKEND_MODE === 'supabase') {
    const { SupabaseStore } = await import('../supabase/SupabaseStore.js');
    store = new SupabaseStore();
} else {
    store = new LocalStore();
}

export { store };
