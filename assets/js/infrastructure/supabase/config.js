// Infrastructure/Supabase — credenciais do projeto Supabase.
//
// COMO CONFIGURAR (produção):
//   1. Crie um projeto em https://supabase.com (gratuito).
//   2. No SQL Editor, execute:
//        a) database/migrations/supabase_postgresql_schema.sql
//        b) database/migrations/20260905_supabase_auth_rls.sql
//   3. Em Project Settings → API, copie a "Project URL" e a chave "anon public"
//      e cole abaixo (ou crie um arquivo assets/js/infrastructure/supabase/config.local.js
//      definindo window.__AUXILIAR_OBRAS_CONFIG__ = { url, anonKey } — recomendado,
//      pois o config.local.js não precisa ser commitado).
//
// Sem configuração, o painel roda em MODO DEMONSTRAÇÃO (localStorage),
// exatamente como a versão estática original.

const override = globalThis.__AUXILIAR_OBRAS_CONFIG__ ?? {};

export const SUPABASE_URL = override.url ?? 'https://SEU-PROJETO.supabase.co';
export const SUPABASE_ANON_KEY = override.anonKey ?? 'SUA-CHAVE-ANON-PUBLICA';

const configured = /^https:\/\/[\w-]+\.supabase\.co/.test(SUPABASE_URL)
    && SUPABASE_ANON_KEY.length > 40
    && !SUPABASE_ANON_KEY.startsWith('SUA-');

/** 'supabase' → banco real com RLS · 'local' → demonstração em localStorage. */
export const BACKEND_MODE = configured ? 'supabase' : 'local';
