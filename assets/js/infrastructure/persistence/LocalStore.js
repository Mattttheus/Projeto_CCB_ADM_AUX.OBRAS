// Infrastructure/Persistence — substitui os MySql*Repository no modo estático.
// Persiste todo o estado no localStorage do navegador (sem servidor/banco de dados).
const STORAGE_KEY = 'auxiliar-obras-static-v2';

const seed = {
    obras: [
        { id: 1, name: 'Templo Jardim das Flores', city: 'São Paulo, SP', status: 'Em andamento', progress: 68, budget: 184500 },
        { id: 2, name: 'Salão Vila Aurora', city: 'Campinas, SP', status: 'Em andamento', progress: 42, budget: 96500 },
        { id: 3, name: 'Reforma Central Norte', city: 'Jundiaí, SP', status: 'Planejamento', progress: 12, budget: 72500 },
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
        { id: 1, title: 'Atraso na entrega de materiais', priority: 'Alta', status: 'Aberto', description: 'Fornecedor não entregou o lote 3.' },
        { id: 2, title: 'Dúvida sobre orçamento', priority: 'Normal', status: 'Em análise', description: 'Validar item de revestimento.' },
    ],
    users: [
        { id: 1, name: 'Matheus', email: 'admin@auxiliarobras.local', password: 'demo123', role: 'admin', active: true },
        { id: 2, name: 'Suporte Técnico', email: 'suporte@auxiliarobras.local', password: 'suporte123', role: 'suporte', active: true },
        { id: 3, name: 'Equipe de obras', email: 'obras@auxiliarobras.local', password: 'obras123', role: 'colaborador', active: true },
    ],
    logs: [
        { id: 1, message: 'Resumo semanal de obras preparado (simulação — sem envio real no modo estático).', date: '2026-09-01T08:00:00' },
    ],
};

function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

export class LocalStore {
    constructor() {
        this.state = this.load();
    }

    load() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (raw) return { ...clone(seed), ...JSON.parse(raw) };
        } catch {
            // estado corrompido: volta ao seed
        }
        return clone(seed);
    }

    save() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(this.state));
    }

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

    log(message) {
        this.state.logs.unshift({ id: Date.now(), message, date: new Date().toISOString() });
        this.state.logs = this.state.logs.slice(0, 100);
        this.save();
    }
}

export const store = new LocalStore();
