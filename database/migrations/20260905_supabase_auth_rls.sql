-- ==============================================================================
-- 2026-09-05 — SUPABASE AUTH + ROW LEVEL SECURITY (RLS)
-- ==============================================================================
-- Execute no SQL Editor do Supabase APÓS supabase_postgresql_schema.sql.
--
-- Garante a regra: cada responsável de igreja acessa APENAS as obras que o
-- administrador designou a ele (tabela obra_responsaveis). Perfis admin,
-- suporte, engenheiro e mestre_obras têm acesso total (hasFullProjectAccess).
-- ==============================================================================

-- 1. Vínculo do perfil da aplicação com o usuário do Supabase Auth ------------
alter table public.usuarios add column if not exists auth_uid uuid unique;
alter table public.usuarios add column if not exists ativo boolean not null default true;
alter table public.usuarios alter column senha drop not null; -- senha fica no Supabase Auth

-- 2. Dados complementares da obra (usados pela SPA) ----------------------------
alter table public.obras add column if not exists cidade varchar(120);

-- 3. Funções auxiliares -------------------------------------------------------
-- security definer: roda com o dono da função, evitando recursão de RLS
-- ao consultar `usuarios` dentro de políticas da própria tabela.
create or replace function public.current_app_user_id()
returns int
language sql stable security definer
set search_path = public
as $$
    select id from public.usuarios
    where auth_uid = auth.uid() and ativo
    limit 1
$$;

create or replace function public.current_app_role()
returns text
language sql stable security definer
set search_path = public
as $$
    select role from public.usuarios
    where auth_uid = auth.uid() and ativo
    limit 1
$$;

create or replace function public.is_admin()
returns boolean
language sql stable security definer
set search_path = public
as $$
    select coalesce(public.current_app_role() = 'admin', false)
$$;

create or replace function public.is_full_access()
returns boolean
language sql stable security definer
set search_path = public
as $$
    select coalesce(public.current_app_role() in ('admin', 'suporte', 'engenheiro', 'mestre_obras'), false)
$$;

-- Regra central: acesso à obra se for perfil de acesso total OU responsável designado.
create or replace function public.can_access_obra(p_obra_id int)
returns boolean
language sql stable security definer
set search_path = public
as $$
    select public.is_full_access()
        or exists (
            select 1 from public.obra_responsaveis r
            where r.obra_id = p_obra_id
              and r.usuario_id = public.current_app_user_id()
        )
$$;

-- 4. Habilitar RLS em todas as tabelas ----------------------------------------
alter table public.usuarios               enable row level security;
alter table public.obras                  enable row level security;
alter table public.obra_responsaveis      enable row level security;
alter table public.atividades             enable row level security;
alter table public.lancamentos_financeiros enable row level security;
alter table public.orcamentos_obras       enable row level security;
alter table public.documentos_obras       enable row level security;
alter table public.compras                enable row level security;
alter table public.chamados               enable row level security;
alter table public.fila_emails            enable row level security;
alter table public.notificacoes_email     enable row level security;

-- 5. Políticas ----------------------------------------------------------------

-- usuarios: qualquer autenticado lê perfis (necessário p/ exibir responsáveis);
-- edição do próprio perfil ou por admin; criação/exclusão só admin.
drop policy if exists usuarios_select on public.usuarios;
create policy usuarios_select on public.usuarios
    for select to authenticated using (true);

drop policy if exists usuarios_update on public.usuarios;
create policy usuarios_update on public.usuarios
    for update to authenticated
    using (auth_uid = auth.uid() or public.is_admin())
    with check (auth_uid = auth.uid() or public.is_admin());

drop policy if exists usuarios_insert on public.usuarios;
create policy usuarios_insert on public.usuarios
    for insert to authenticated with check (public.is_admin());

drop policy if exists usuarios_delete on public.usuarios;
create policy usuarios_delete on public.usuarios
    for delete to authenticated using (public.is_admin());

-- obras: visível apenas se designada ao responsável (ou acesso total).
drop policy if exists obras_select on public.obras;
create policy obras_select on public.obras
    for select to authenticated using (public.can_access_obra(id));

drop policy if exists obras_insert on public.obras;
create policy obras_insert on public.obras
    for insert to authenticated with check (public.is_full_access());

drop policy if exists obras_update on public.obras;
create policy obras_update on public.obras
    for update to authenticated
    using (public.can_access_obra(id))
    with check (public.can_access_obra(id));

drop policy if exists obras_delete on public.obras;
create policy obras_delete on public.obras
    for delete to authenticated using (public.is_admin());

-- obra_responsaveis: o usuário vê as próprias designações; só o admin designa.
drop policy if exists responsaveis_select on public.obra_responsaveis;
create policy responsaveis_select on public.obra_responsaveis
    for select to authenticated
    using (public.is_full_access() or usuario_id = public.current_app_user_id());

drop policy if exists responsaveis_insert on public.obra_responsaveis;
create policy responsaveis_insert on public.obra_responsaveis
    for insert to authenticated with check (public.is_admin());

drop policy if exists responsaveis_delete on public.obra_responsaveis;
create policy responsaveis_delete on public.obra_responsaveis
    for delete to authenticated using (public.is_admin());

-- atividades: leitura/escrita limitadas às obras acessíveis.
drop policy if exists atividades_select on public.atividades;
create policy atividades_select on public.atividades
    for select to authenticated
    using (obra_id is null or public.can_access_obra(obra_id));

drop policy if exists atividades_insert on public.atividades;
create policy atividades_insert on public.atividades
    for insert to authenticated
    with check (obra_id is null ? public.is_full_access() : public.can_access_obra(obra_id));

drop policy if exists atividades_update on public.atividades;
create policy atividades_update on public.atividades
    for update to authenticated
    using (obra_id is null or public.can_access_obra(obra_id))
    with check (obra_id is null or public.can_access_obra(obra_id));

drop policy if exists atividades_delete on public.atividades;
create policy atividades_delete on public.atividades
    for delete to authenticated
    using (obra_id is null or public.can_access_obra(obra_id));

-- lancamentos_financeiros
drop policy if exists financeiro_select on public.lancamentos_financeiros;
create policy financeiro_select on public.lancamentos_financeiros
    for select to authenticated using (public.can_access_obra(obra_id));

drop policy if exists financeiro_insert on public.lancamentos_financeiros;
create policy financeiro_insert on public.lancamentos_financeiros
    for insert to authenticated with check (public.can_access_obra(obra_id));

drop policy if exists financeiro_update on public.lancamentos_financeiros;
create policy financeiro_update on public.lancamentos_financeiros
    for update to authenticated
    using (public.can_access_obra(obra_id))
    with check (public.can_access_obra(obra_id));

drop policy if exists financeiro_delete on public.lancamentos_financeiros;
create policy financeiro_delete on public.lancamentos_financeiros
    for delete to authenticated using (public.can_access_obra(obra_id));

-- orcamentos_obras: leitura por acesso à obra; definição só por acesso total.
drop policy if exists orcamentos_select on public.orcamentos_obras;
create policy orcamentos_select on public.orcamentos_obras
    for select to authenticated using (public.can_access_obra(obra_id));

drop policy if exists orcamentos_write on public.orcamentos_obras;
create policy orcamentos_write on public.orcamentos_obras
    for all to authenticated
    using (public.is_full_access())
    with check (public.is_full_access());

-- documentos_obras
drop policy if exists documentos_select on public.documentos_obras;
create policy documentos_select on public.documentos_obras
    for select to authenticated using (public.can_access_obra(obra_id));

drop policy if exists documentos_insert on public.documentos_obras;
create policy documentos_insert on public.documentos_obras
    for insert to authenticated with check (public.can_access_obra(obra_id));

drop policy if exists documentos_delete on public.documentos_obras;
create policy documentos_delete on public.documentos_obras
    for delete to authenticated using (public.can_access_obra(obra_id));

-- compras
drop policy if exists compras_select on public.compras;
create policy compras_select on public.compras
    for select to authenticated using (public.can_access_obra(obra_id));

drop policy if exists compras_write on public.compras;
create policy compras_write on public.compras
    for all to authenticated
    using (public.can_access_obra(obra_id))
    with check (public.can_access_obra(obra_id));

-- chamados: acesso total, responsável da obra ou autor do chamado.
drop policy if exists chamados_select on public.chamados;
create policy chamados_select on public.chamados
    for select to authenticated
    using (
        public.is_full_access()
        or usuario_id = public.current_app_user_id()
        or (obra_id is not null and public.can_access_obra(obra_id))
    );

drop policy if exists chamados_insert on public.chamados;
create policy chamados_insert on public.chamados
    for insert to authenticated
    with check (usuario_id is null or usuario_id = public.current_app_user_id());

drop policy if exists chamados_update on public.chamados;
create policy chamados_update on public.chamados
    for update to authenticated
    using (
        public.is_full_access()
        or usuario_id = public.current_app_user_id()
        or (obra_id is not null and public.can_access_obra(obra_id))
    )
    with check (
        public.is_full_access()
        or usuario_id = public.current_app_user_id()
        or (obra_id is not null and public.can_access_obra(obra_id))
    );

drop policy if exists chamados_delete on public.chamados;
create policy chamados_delete on public.chamados
    for delete to authenticated using (public.is_admin());

-- fila_emails e notificacoes_email: apenas acesso total (equivale aos logs do PHP).
drop policy if exists fila_all on public.fila_emails;
create policy fila_all on public.fila_emails
    for all to authenticated
    using (public.is_full_access())
    with check (public.is_full_access());

drop policy if exists notificacoes_all on public.notificacoes_email;
create policy notificacoes_all on public.notificacoes_email
    for all to authenticated
    using (public.is_full_access())
    with check (public.is_full_access());

-- 6. Storage: bucket privado "documentos" com acesso por obra ------------------
-- Os arquivos são gravados no caminho {obra_id}/{arquivo}; a política extrai o
-- obra_id da primeira pasta e aplica a mesma regra can_access_obra.
insert into storage.buckets (id, name, public)
values ('documentos', 'documentos', false)
on conflict (id) do nothing;

drop policy if exists documentos_storage_select on storage.objects;
create policy documentos_storage_select on storage.objects
    for select to authenticated
    using (
        bucket_id = 'documentos'
        and public.can_access_obra(((storage.foldername(name))[1])::int)
    );

drop policy if exists documentos_storage_insert on storage.objects;
create policy documentos_storage_insert on storage.objects
    for insert to authenticated
    with check (
        bucket_id = 'documentos'
        and public.can_access_obra(((storage.foldername(name))[1])::int)
    );

drop policy if exists documentos_storage_delete on storage.objects;
create policy documentos_storage_delete on storage.objects
    for delete to authenticated
    using (
        bucket_id = 'documentos'
        and public.can_access_obra(((storage.foldername(name))[1])::int)
    );

-- ==============================================================================
-- PRIMEIRO ADMINISTRADOR
-- ==============================================================================
-- 1. Crie o usuário em Authentication → Users → Add user (e-mail + senha),
--    com "Auto Confirm User" marcado.
-- 2. Copie o UUID gerado e rode (substituindo os valores):
--
--    insert into public.usuarios (nome, email, role, tipo, ativo, auth_uid)
--    values ('Administrador', 'admin@suaigreja.com', 'admin', 'admin', true,
--            'UUID-DO-USUARIO-DO-AUTH')
--    on conflict (email) do update
--        set auth_uid = excluded.auth_uid, role = 'admin', ativo = true;
--
-- 3. Desative a confirmação de e-mail em Authentication → Providers → Email
--    para que os usuários cadastrados pelo admin entrem imediatamente.
-- ==============================================================================
