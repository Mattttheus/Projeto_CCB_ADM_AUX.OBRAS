// Infrastructure/Persistence — repositório Supabase (PostgreSQL + Storage).
// Substitui os MySql*Repository do PHP. O isolamento "cada responsável vê
// apenas as obras que o admin designou" é garantido no banco pelas políticas
// RLS (migration 20260905_supabase_auth_rls.sql); aqui apenas consumimos o
// resultado já filtrado e mantemos um cache em memória no mesmo formato de
// estado usado pelo modo demonstração.
import { getSupabase, getSignupClient } from './SupabaseClient.js';
import { ActivityStatus } from '../../domain/activity/ActivityStatus.js';

const emptyState = () => ({
    obras: [],
    activities: [],
    transactions: [],
    documents: [],
    chamados: [],
    users: [],
    logs: [],
});

// --- Mapeadores linha (PostgreSQL) → estado da SPA ---

const mapObra = (row, orcamentos, responsaveis) => ({
    id: row.id,
    name: row.nome,
    city: row.cidade ?? '',
    status: row.status,
    budget: Number(orcamentos.get(row.id) ?? 0),
    responsaveis: responsaveis.filter(item => item.obra_id === row.id).map(item => item.usuario_id),
});

const mapActivity = row => ({
    id: row.id,
    obraId: row.obra_id,
    title: row.titulo,
    description: row.descricao ?? '',
    date: row.data_limite ?? row.data_atividade,
    status: row.status,
    type: row.tipo ?? 'unico',
    dayOfWeek: row.dia_semana,
});

const mapTransaction = row => ({
    id: row.id,
    obraId: row.obra_id,
    description: row.descricao,
    category: row.categoria,
    quantity: Number(row.quantidade),
    unitCost: Number(row.valor_unitario),
    date: row.data_lancamento,
});

const mapDocument = row => ({
    id: row.id,
    obraId: row.obra_id,
    name: row.nome_arquivo,
    type: row.tipo_documento ?? 'Geral',
    path: row.caminho_arquivo,
    date: String(row.data_upload ?? '').slice(0, 10),
});

const mapChamado = row => ({
    id: row.id,
    obraId: row.obra_id,
    userId: row.usuario_id,
    title: row.titulo,
    description: row.descricao,
    priority: row.prioridade, // verde | amarelo | vermelho
    status: row.status,       // aberto | em_atendimento | resolvido | fechado
    date: row.data_abertura,
});

const mapUser = row => ({
    id: row.id,
    authUid: row.auth_uid,
    name: row.nome,
    email: row.email,
    role: row.role,
    active: row.ativo !== false,
});

const mapLog = row => ({
    id: row.id,
    message: `${row.assunto} → ${row.destinatario} [${row.status}]`,
    date: row.data_envio ?? row.created_at,
});

function ensured(result, label) {
    if (result.error) throw new Error(`${label}: ${result.error.message}`);
    return result.data;
}

/** Consulta tolerante: retorna [] quando a política RLS nega acesso (ex.: fila_emails para não-admin). */
async function tryQuery(promise) {
    const { data, error } = await promise;
    return error ? [] : (data ?? []);
}

export class SupabaseStore {
    constructor() {
        this.state = emptyState();
        this.ephemeralLogs = [];
    }

    get db() {
        return getSupabase();
    }

    async init() {
        await this.refresh();
    }

    /** Recarrega todo o estado respeitando o que o RLS liberou para o usuário logado. */
    async refresh() {
        const sb = this.db;
        const [obras, orcamentos, atividades, financeiro, documentos, chamados, usuarios, responsaveis, fila] =
            await Promise.all([
                tryQuery(sb.from('obras').select('*').order('nome')),
                tryQuery(sb.from('orcamentos_obras').select('*')),
                tryQuery(sb.from('atividades').select('*').order('data_limite', { ascending: true, nullsFirst: false })),
                tryQuery(sb.from('lancamentos_financeiros').select('*').order('data_lancamento', { ascending: false })),
                tryQuery(sb.from('documentos_obras').select('*').order('data_upload', { ascending: false })),
                tryQuery(sb.from('chamados').select('*').order('data_abertura', { ascending: false })),
                tryQuery(sb.from('usuarios').select('id,auth_uid,nome,email,role,ativo').order('nome')),
                tryQuery(sb.from('obra_responsaveis').select('*')),
                tryQuery(sb.from('fila_emails').select('*').order('created_at', { ascending: false }).limit(100)),
            ]);

        const orcamentoPorObra = new Map(orcamentos.map(item => [item.obra_id, item.valor_orcado]));
        this.state = {
            obras: obras.map(row => mapObra(row, orcamentoPorObra, responsaveis)),
            activities: atividades.map(mapActivity),
            transactions: financeiro.map(mapTransaction),
            documents: documentos.map(mapDocument),
            chamados: chamados.map(mapChamado),
            users: usuarios.map(mapUser),
            logs: [...this.ephemeralLogs, ...fila.map(mapLog)].slice(0, 100),
        };
    }

    save() { /* no-op: a persistência é imediata no Supabase */ }
    reset() { /* sem seed no modo real */ }

    nextId(items) {
        return items.reduce((max, item) => Math.max(max, Number(item.id) || 0), 0) + 1;
    }

    obraName(obraId) {
        return this.state.obras.find(obra => obra.id === Number(obraId))?.name ?? '—';
    }

    /** Obras visíveis — no modo Supabase o RLS já filtrou; mantido por paridade com o LocalStore. */
    obrasFor() {
        return this.state.obras;
    }

    canAccessObra(_user, obraId) {
        return this.state.obras.some(obra => obra.id === Number(obraId));
    }

    /** Progresso derivado do cronograma: % de atividades concluídas da obra. */
    obraProgress(obraId) {
        const items = this.state.activities.filter(item => item.obraId === Number(obraId));
        if (!items.length) return 0;
        const done = items.filter(item => item.status === ActivityStatus.COMPLETED).length;
        return Math.round((done / items.length) * 100);
    }

    /** Log local efêmero (a trilha permanente fica na fila_emails do banco). */
    log(message) {
        this.ephemeralLogs.unshift({ id: Date.now(), message, date: new Date().toISOString() });
        this.state.logs.unshift({ id: Date.now(), message, date: new Date().toISOString() });
        this.state.logs = this.state.logs.slice(0, 100);
    }

    // --- Obras ---

    async addObra({ name, city, budget }) {
        const row = ensured(await this.db.from('obras')
            .insert({ nome: name, cidade: city || null, status: 'em_andamento' })
            .select().single(), 'Erro ao criar obra');
        if (budget > 0) await this.setBudget(row.id, budget);
        await this.refresh();
        return row.id;
    }

    async deleteObra(id) {
        ensured(await this.db.from('obras').delete().eq('id', Number(id)), 'Erro ao excluir obra');
        await this.refresh();
    }

    async setResponsaveis(obraId, userIds) {
        const id = Number(obraId);
        ensured(await this.db.from('obra_responsaveis').delete().eq('obra_id', id), 'Erro ao atualizar responsáveis');
        if (userIds.length) {
            ensured(await this.db.from('obra_responsaveis')
                .insert(userIds.map(userId => ({ obra_id: id, usuario_id: Number(userId) }))),
                'Erro ao designar responsáveis');
        }
        await this.refresh();
    }

    /** Inverso de setResponsaveis: redefine TODAS as obras designadas a um usuário. */
    async setObrasForUser(userId, obraIds) {
        const uid = Number(userId);
        ensured(await this.db.from('obra_responsaveis').delete().eq('usuario_id', uid),
            'Erro ao atualizar designações');
        if (obraIds.length) {
            ensured(await this.db.from('obra_responsaveis')
                .insert(obraIds.map(obraId => ({ obra_id: Number(obraId), usuario_id: uid }))),
                'Erro ao designar obras');
        }
        await this.refresh();
    }

    // --- Atividades ---

    async addActivity({ obraId, title, description, date, status, type = 'unico', dayOfWeek = null }) {
        const isMaintenance = type === 'recorrente';
        ensured(await this.db.from('atividades').insert({
            obra_id: Number(obraId),
            titulo: title,
            descricao: description || null,
            data_atividade: isMaintenance ? null : date,
            data_limite: isMaintenance ? null : date,
            tipo: type,
            dia_semana: isMaintenance ? dayOfWeek : null,
            status,
        }).select().single(), 'Erro ao criar atividade');
        await this.refresh();
    }

    async updateActivityStatus(id, status) {
        ensured(await this.db.from('atividades').update({ status }).eq('id', Number(id)),
            'Erro ao atualizar atividade');
        const activity = this.state.activities.find(item => item.id === Number(id));
        if (activity) activity.status = status;
    }

    async deleteActivity(id) {
        ensured(await this.db.from('atividades').delete().eq('id', Number(id)), 'Erro ao excluir atividade');
        this.state.activities = this.state.activities.filter(item => item.id !== Number(id));
    }

    // --- Financeiro ---

    async addTransaction({ obraId, category, description, quantity, unitCost, date }) {
        ensured(await this.db.from('lancamentos_financeiros').insert({
            obra_id: Number(obraId),
            categoria: category,
            descricao: description,
            quantidade: quantity,
            valor_unitario: unitCost,
            data_lancamento: date,
        }).select().single(), 'Erro ao lançar despesa');
        await this.refresh();
    }

    async deleteTransaction(id) {
        ensured(await this.db.from('lancamentos_financeiros').delete().eq('id', Number(id)),
            'Erro ao excluir lançamento');
        this.state.transactions = this.state.transactions.filter(item => item.id !== Number(id));
    }

    async setBudget(obraId, value) {
        ensured(await this.db.from('orcamentos_obras')
            .upsert({ obra_id: Number(obraId), valor_orcado: value, atualizado_em: new Date().toISOString() }),
            'Erro ao salvar orçamento');
        const obra = this.state.obras.find(item => item.id === Number(obraId));
        if (obra) obra.budget = value;
    }

    // --- Documentos (Storage bucket 'documentos', caminho {obraId}/{arquivo}) ---

    async addDocument({ obraId, file, type }) {
        const path = `${Number(obraId)}/${Date.now()}-${file.name.replace(/[^\w.-]+/g, '_')}`;
        const upload = await this.db.storage.from('documentos').upload(path, file);
        if (upload.error) {
            throw new Error(`Upload falhou (crie o bucket "documentos" no Supabase Storage): ${upload.error.message}`);
        }
        ensured(await this.db.from('documentos_obras').insert({
            obra_id: Number(obraId),
            nome_arquivo: file.name,
            caminho_arquivo: path,
            tipo_documento: type || 'Geral',
        }).select().single(), 'Erro ao registrar documento');
        await this.refresh();
    }

    async deleteDocument(id) {
        const doc = this.state.documents.find(item => item.id === Number(id));
        ensured(await this.db.from('documentos_obras').delete().eq('id', Number(id)),
            'Erro ao excluir documento');
        if (doc?.path) await this.db.storage.from('documentos').remove([doc.path]);
        this.state.documents = this.state.documents.filter(item => item.id !== Number(id));
    }

    /** URL assinada (1h) para download do arquivo. */
    async documentUrl(doc) {
        const { data, error } = await this.db.storage.from('documentos').createSignedUrl(doc.path, 3600);
        if (error) throw new Error(`Não foi possível gerar o link: ${error.message}`);
        return data.signedUrl;
    }

    // --- Chamados ---

    async addChamado({ obraId, title, description, priority, userId }) {
        ensured(await this.db.from('chamados').insert({
            obra_id: obraId ? Number(obraId) : null,
            usuario_id: userId ?? null,
            titulo: title,
            descricao: description,
            prioridade: priority,
            status: 'aberto',
        }).select().single(), 'Erro ao abrir chamado');
        await this.refresh();
    }

    async setChamadoStatus(id, status) {
        const patch = { status };
        if (status === 'fechado') patch.data_fechamento = new Date().toISOString();
        ensured(await this.db.from('chamados').update(patch).eq('id', Number(id)),
            'Erro ao atualizar chamado');
        await this.refresh();
    }

    // --- Usuários ---

    /**
     * Cadastro feito pelo admin: cria a conta no Supabase Auth com um cliente
     * sem persistência (a sessão do admin não é afetada) e registra o perfil
     * na tabela `usuarios` vinculado pelo auth_uid.
     */
    async createUser({ name, email, password, role }) {
        const { data, error } = await getSignupClient().auth.signUp({ email, password });
        if (error) throw new Error(`Erro ao criar acesso: ${error.message}`);
        if (!data.user?.id) throw new Error('O Supabase não retornou o usuário criado. Desative a confirmação de e-mail em Authentication → Providers.');
        ensured(await this.db.from('usuarios').insert({
            nome: name,
            email,
            senha: null, // senha gerenciada pelo Supabase Auth
            role,
            tipo: role === 'admin' ? 'admin' : 'comum',
            ativo: true,
            auth_uid: data.user.id,
        }), 'Erro ao registrar perfil');
        await this.refresh();
    }

    async toggleUser(id) {
        const user = this.state.users.find(item => item.id === Number(id));
        if (!user) return;
        ensured(await this.db.from('usuarios').update({ ativo: !user.active }).eq('id', user.id),
            'Erro ao alterar acesso');
        user.active = !user.active;
    }

    async setUserRole(id, role) {
        ensured(await this.db.from('usuarios').update({ role }).eq('id', Number(id)),
            'Erro ao alterar nível de acesso');
        const user = this.state.users.find(item => item.id === Number(id));
        if (user) user.role = role;
    }

    async updateProfile(id, { name, email }) {
        ensured(await this.db.from('usuarios').update({ nome: name, email }).eq('id', Number(id)),
            'Erro ao atualizar perfil');
        const user = this.state.users.find(item => item.id === Number(id));
        if (user) { user.name = name; user.email = email; }
    }

    /** Busca o perfil da aplicação vinculado ao usuário autenticado do Supabase Auth. */
    async profileForAuthUid(authUid) {
        const { data, error } = await this.db.from('usuarios')
            .select('id,auth_uid,nome,email,role,ativo')
            .eq('auth_uid', authUid)
            .maybeSingle();
        if (error) return null;
        return data ? mapUser(data) : null;
    }
}
