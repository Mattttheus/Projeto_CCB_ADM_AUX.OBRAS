// Infrastructure/Persistence — substitui os MySql*Repository no modo demonstração.
// Persiste todo o estado no localStorage do navegador (sem servidor/banco de dados).
// Expõe a MESMA interface do SupabaseStore (métodos assíncronos), permitindo
// alternar os modos sem tocar nas camadas superiores.
import { normalizeObraStatus } from '../../domain/obra/ObraStatus.js';

const STORAGE_KEY = 'auxiliar-obras-static-v3';

const seed = {
    obras: [
        { id: 1, name: 'Templo Jardim das Flores', city: 'São Paulo, SP', status: 'em_andamento', budget: 184500, responsaveis: [3] },
        { id: 2, name: 'Salão Vila Aurora', city: 'Campinas, SP', status: 'em_andamento', budget: 96500, responsaveis: [3] },
        { id: 3, name: 'Reforma Central Norte', city: 'Jundiaí, SP', status: 'pausada', budget: 72500, responsaveis: [] },
    ],
    activities: [
        { id: 1, obraId: 1, title: 'Instalação elétrica do salão', description: '', date: '2026-09-06', status: 'em_andamento' },
        { id: 2, obraId: 2, title: 'Entrega de revestimentos', description: '', date: '2026-09-04', status: 'pendente' },
        { id: 3, obraId: 1, title: 'Vistoria da cobertura', description: '', date: '2026-09-02', status: 'pendente' },
        { id: 4, obraId: 3, title: 'Aprovação do orçamento', description: '', date: '2026-09-12', status: 'pendente' },
    ],
    transactions: [
        { id: 1, obraId: 1, description: 'Compra de materiais elétricos', category: 'material', quantity: 40, unitCost: 320, date: '2026-08-29' },
        { id: 2, obraId: 2, description: 'Mão de obra — agosto', category: 'servico', quantity: 1, unitCost: 18600, date: '2026-08-27' },
        { id: 3, obraId: 1, description: 'Revestimentos cerâmicos', category: 'material', quantity: 20, unitCost: 467, date: '2026-08-21' },
    ],
    documents: [],
    chamados: [
        { id: 1, obraId: 1, userId: 3, title: 'Atraso na entrega de materiais', priority: 'amarelo', status: 'aberto', description: 'Fornecedor não entregou o lote 3.', date: '2026-09-01T09:30:00' },
        { id: 2, obraId: 2, userId: 3, title: 'Dúvida sobre orçamento', priority: 'verde', status: 'em_atendimento', description: 'Validar item de revestimento.', date: '2026-09-02T14:10:00' },
    ],
    users: [
        { id: 1, name: 'Matheus', email: 'admin@auxiliarobras.local', password: 'demo123', role: 'admin', active: true },
        { id: 2, name: 'Suporte Técnico', email: 'suporte@auxiliarobras.local', password: 'suporte123', role: 'suporte', active: true },
        { id: 3, name: 'Equipe de obras', email: 'obras@auxiliarobras.local', password: 'obras123', role: 'colaborador', active: true },
    ],
    logs: [
        { id: 1, message: 'Resumo semanal de obras preparado (simulação — sem envio real no modo demonstração).', date: '2026-09-01T08:00:00' },
    ],
};

function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

/** Migra estados antigos (v2) para os enums do banco. */
function normalize(state) {
    state.obras.forEach(obra => {
        obra.status = normalizeObraStatus(obra.status);
        obra.responsaveis ??= [];
    });
    state.chamados.forEach(chamado => {
        const priorityMap = { Normal: 'verde', Alta: 'amarelo', Urgente: 'vermelho' };
        const statusMap = { Aberto: 'aberto', 'Em análise': 'em_atendimento', Fechado: 'fechado' };
        chamado.priority = priorityMap[chamado.priority] ?? chamado.priority ?? 'verde';
        chamado.status = statusMap[chamado.status] ?? chamado.status ?? 'aberto';
    });
    state.obraResponsaveis = undefined; // responsáveis agora vivem em obra.responsaveis
    return state;
}

export class LocalStore {
    constructor() {
        this.state = this.load();
    }

    load() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (raw) return normalize({ ...clone(seed), ...JSON.parse(raw) });
        } catch {
            // estado corrompido: volta ao seed
        }
        return clone(seed);
    }

    save() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(this.state));
    }

    async init() { /* estado carregado no construtor */ }
    async refresh() { /* localStorage é síncrono; nada a recarregar */ }

    reset() {
        this.state = clone(seed);
        this.save();
    }

    nextId(items) {
        return items.reduce((max, item) => Math.max(max, Number(item.id) || 0), 0) + 1;
    }

    obraName(obraId) {
        return this.state.obras.find(obra => obra.id === Number(obraId))?.name ?? '—';
    }

    /** No modo demonstração o filtro por responsável é aplicado no cliente. */
    obrasFor(user) {
        if (!user || ['admin', 'suporte', 'engenheiro', 'mestre_obras'].includes(user.role)) {
            return this.state.obras;
        }
        return this.state.obras.filter(obra => obra.responsaveis?.includes(user.id));
    }

    canAccessObra(user, obraId) {
        return this.obrasFor(user).some(obra => obra.id === Number(obraId));
    }

    obraProgress(obraId) {
        const items = this.state.activities.filter(item => item.obraId === Number(obraId));
        if (!items.length) return 0;
        const done = items.filter(item => item.status === 'concluida').length;
        return Math.round((done / items.length) * 100);
    }

    log(message) {
        this.state.logs.unshift({ id: Date.now(), message, date: new Date().toISOString() });
        this.state.logs = this.state.logs.slice(0, 100);
        this.save();
    }

    // --- Obras ---

    async addObra({ name, city, budget }) {
        const id = this.nextId(this.state.obras);
        this.state.obras.push({ id, name, city, status: 'em_andamento', budget, responsaveis: [] });
        this.log(`Obra "${name}" criada.`);
        this.save();
        return id;
    }

    async deleteObra(id) {
        this.state.obras = this.state.obras.filter(item => item.id !== Number(id));
        this.save();
    }

    async setResponsaveis(obraId, userIds) {
        const obra = this.state.obras.find(item => item.id === Number(obraId));
        if (obra) {
            obra.responsaveis = userIds.map(Number);
            this.save();
        }
    }

    /** Inverso de setResponsaveis: redefine TODAS as obras designadas a um usuário. */
    async setObrasForUser(userId, obraIds) {
        const uid = Number(userId);
        const wanted = new Set(obraIds.map(Number));
        this.state.obras.forEach(obra => {
            obra.responsaveis ??= [];
            const has = obra.responsaveis.includes(uid);
            if (wanted.has(obra.id) && !has) obra.responsaveis.push(uid);
            if (!wanted.has(obra.id) && has) obra.responsaveis = obra.responsaveis.filter(id => id !== uid);
        });
        this.save();
    }

    // --- Atividades ---

    async addActivity({ obraId, title, description, date, status, type = 'unico', dayOfWeek = null }) {
        this.state.activities.push({
            id: this.nextId(this.state.activities),
            obraId: Number(obraId), title, description, date, status, type, dayOfWeek,
        });
        this.log(`Atividade "${title}" criada.`);
        this.save();
    }

    async updateActivityStatus(id, status) {
        const activity = this.state.activities.find(item => item.id === Number(id));
        if (activity) {
            activity.status = status;
            this.save();
        }
    }

    async deleteActivity(id) {
        this.state.activities = this.state.activities.filter(item => item.id !== Number(id));
        this.save();
    }

    // --- Financeiro ---

    async addTransaction({ obraId, category, description, quantity, unitCost, date }) {
        this.state.transactions.push({
            id: this.nextId(this.state.transactions),
            obraId: Number(obraId), category, description, quantity, unitCost, date,
        });
        this.log(`Lançamento financeiro "${description}" registrado.`);
        this.save();
    }

    async deleteTransaction(id) {
        this.state.transactions = this.state.transactions.filter(item => item.id !== Number(id));
        this.save();
    }

    async setBudget(obraId, value) {
        const obra = this.state.obras.find(item => item.id === Number(obraId));
        if (!obra) throw new Error('Obra não encontrada.');
        obra.budget = value;
        this.save();
    }

    // --- Documentos (modo demonstração: apenas registro local, sem upload real) ---

    async addDocument({ obraId, file, type }) {
        this.state.documents.unshift({
            id: this.nextId(this.state.documents),
            obraId: Number(obraId),
            name: file.name,
            type: type || 'Geral',
            date: new Date().toISOString().slice(0, 10),
        });
        this.log(`Documento "${file.name}" registrado localmente.`);
        this.save();
    }

    async deleteDocument(id) {
        this.state.documents = this.state.documents.filter(item => item.id !== Number(id));
        this.save();
    }

    async documentUrl() {
        throw new Error('Download disponível apenas no modo Supabase (Storage).');
    }

    // --- Chamados ---

    async addChamado({ obraId, title, description, priority, userId }) {
        this.state.chamados.push({
            id: this.nextId(this.state.chamados),
            obraId: obraId ? Number(obraId) : null,
            userId: userId ?? null,
            title, description, priority,
            status: 'aberto',
            date: new Date().toISOString(),
        });
        this.log(`Chamado "${title}" aberto.`);
        this.save();
    }

    async setChamadoStatus(id, status) {
        const chamado = this.state.chamados.find(item => item.id === Number(id));
        if (chamado) {
            chamado.status = status;
            this.save();
        }
    }

    // --- Usuários ---

    async createUser({ name, email, password, role }) {
        if (this.state.users.some(item => item.email.toLowerCase() === email.toLowerCase())) {
            throw new Error('E-mail já cadastrado.');
        }
        this.state.users.push({
            id: this.nextId(this.state.users),
            name, email, password, role, active: false,
        });
        this.log(`Usuário ${name} cadastrado (aguardando liberação).`);
        this.save();
    }

    async toggleUser(id) {
        const user = this.state.users.find(item => item.id === Number(id));
        if (user) {
            user.active = !user.active;
            this.log(`Acesso de ${user.name} ${user.active ? 'liberado' : 'bloqueado'}.`);
            this.save();
        }
    }

    async setUserRole(id, role) {
        const user = this.state.users.find(item => item.id === Number(id));
        if (user) {
            user.role = role;
            this.log(`Perfil de ${user.name} alterado.`);
            this.save();
        }
    }

    async updateProfile(id, { name, email }) {
        const user = this.state.users.find(item => item.id === Number(id));
        if (user) {
            user.name = name;
            user.email = email;
            this.save();
        }
    }

    async profileForAuthUid() {
        return null; // modo demonstração não usa Supabase Auth
    }
}
