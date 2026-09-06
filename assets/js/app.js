// Bootstrap da SPA — roteador por hash com guarda de acesso por perfil,
// espelhando o controle de sessão do app/Core/Auth.php.
// Modo duplo: Supabase (produção, com RLS por responsável) ou demonstração local.
import { store, BACKEND_MODE } from './infrastructure/persistence/Store.js';
import { Auth, ROLE_LABELS } from './core/Auth.js';
import { Validator } from './core/Validator.js';
import { ActivityService } from './application/activity/ActivityService.js';
import { FinancialService } from './application/finance/FinancialService.js';
import { FINANCIAL_CATEGORY_LABELS } from './domain/finance/FinancialCategory.js';
import * as ui from './presentation/ui.js';
import * as dashboard from './presentation/pages/dashboard.js';
import * as obras from './presentation/pages/obras.js';
import * as atividades from './presentation/pages/atividades.js';
import * as calendario from './presentation/pages/calendario.js';
import * as financeiro from './presentation/pages/financeiro.js';
import * as documentos from './presentation/pages/documentos.js';
import * as relatorios from './presentation/pages/relatorios.js';
import * as chamados from './presentation/pages/chamados.js';
import * as usuarios from './presentation/pages/usuarios.js';
import * as conta from './presentation/pages/conta.js';

const activityService = new ActivityService(store);
const financialService = new FinancialService(store);

const app = document.querySelector('#app');

// Tabela de rotas: `roles` restringe o acesso (como requireAdmin no PHP).
const routes = {
    login: { title: 'Login', public: true, render: conta.renderLogin, bind: conta.bindLogin },
    dashboard: { title: 'Visão geral', render: dashboard.render, mount: dashboard.mount },
    obras: { title: 'Obras', render: obras.render, bind: obras.bind },
    atividades: { title: 'Atividades', render: atividades.render, bind: atividades.bind },
    calendario: { title: 'Calendário', render: calendario.render, bind: calendario.bind, mount: calendario.mount },
    financeiro: { title: 'Financeiro', render: financeiro.render, bind: financeiro.bind, mount: financeiro.mount },
    documentos: { title: 'Documentos', render: documentos.render, bind: documentos.bind },
    relatorios: { title: 'Relatórios', render: relatorios.render, bind: relatorios.bind },
    chamados: { title: 'Chamados', render: chamados.renderChamados },
    suporte: { title: 'Abrir chamado', render: chamados.renderSuporte, bind: chamados.bindSuporte },
    usuarios: { title: 'Usuários', roles: ['admin'], render: usuarios.renderUsuarios, bind: usuarios.bindUsuarios },
    logs: { title: 'Logs de e-mail', roles: ['admin', 'suporte'], render: usuarios.renderLogs },
    perfil: { title: 'Meu perfil', render: conta.renderPerfil, bind: conta.bindPerfil },
};

const ctx = {
    store,
    auth: Auth,
    activityService,
    financialService,
    notify: ui.notify,
    rerender: () => render(),
    navigate: route => { location.hash = route; },
    openActivityModal: (prefillDate) => openEntityModal('activity', prefillDate),
};

function currentRoute() {
    return location.hash.slice(1) || 'dashboard';
}

let rendering = false;

async function render() {
    if (rendering) return;
    rendering = true;
    try {
        let key = currentRoute();

        // Guarda de sessão (requireUser): sem login, só a rota pública é acessível.
        if (!Auth.isAuthenticated() && !routes[key]?.public) {
            location.hash = 'login';
            return;
        }
        if (Auth.isAuthenticated() && key === 'login') {
            location.hash = 'dashboard';
            return;
        }

        const route = routes[key] ?? routes.dashboard;
        if (!routes[key]) key = 'dashboard';

        document.body.classList.toggle('logged-out', !Auth.isAuthenticated());
        syncChrome(key);

        // Guarda de perfil (requireAdmin / controle de papel).
        if (route.roles && !Auth.canAccess(route.roles)) {
            app.innerHTML = ui.layout('Acesso negado', 'Seu perfil não tem permissão para este módulo.')
                + ui.panel('Permissão necessária', `<div class="empty-state">Fale com um administrador para liberar o acesso (perfis permitidos: ${route.roles.join(', ')}).</div>`);
            return;
        }

        if (Auth.isAuthenticated()) await store.refresh();
        app.innerHTML = route.render(ctx);
        route.bind?.(ctx);
        await route.mount?.(ctx); // gráficos (Chart.js) e calendário (FullCalendar)
    } catch (error) {
        app.innerHTML = ui.layout('Erro ao carregar', 'Não foi possível concluir a operação.')
            + ui.panel('Detalhes', `<div class="empty-state">${ui.escapeHtml(error.message)}</div>`);
    } finally {
        rendering = false;
    }
}

function syncChrome(key) {
    document.querySelectorAll('[data-route]').forEach(link => {
        link.classList.toggle('active', link.dataset.route === key);
        const roles = link.dataset.roles?.split(' ');
        if (roles) link.style.display = Auth.canAccess(roles) ? '' : 'none';
    });
    document.querySelector('#breadcrumb-current').textContent = routes[key]?.title ?? 'Visão geral';

    const badge = document.querySelector('#backend-badge');
    if (badge) {
        badge.textContent = BACKEND_MODE === 'supabase' ? 'Supabase conectado' : 'Modo demonstração local';
        badge.classList.toggle('remote', BACKEND_MODE === 'supabase');
    }

    const user = Auth.user();
    const chip = document.querySelector('#user-chip');
    if (chip && user) {
        const initials = user.name.split(' ').map(part => part[0]).join('').slice(0, 2).toUpperCase();
        chip.innerHTML = `<span class="avatar">${ui.escapeHtml(initials)}</span><span><strong>${ui.escapeHtml(user.name)}</strong><small>${ui.escapeHtml(ROLE_LABELS[user.role] ?? user.role)}</small></span>`;
    }
}

// --- Modais de criação (formulários com os mesmos campos do backend PHP) ---

/** Apenas as obras que o usuário pode acessar (responsável vê só as designadas). */
function obraOptions() {
    return store.obrasFor(Auth.user())
        .map(item => `<option value="${item.id}">${ui.escapeHtml(item.name)}</option>`).join('');
}

function categoryOptions() {
    return Object.entries(FINANCIAL_CATEGORY_LABELS)
        .map(([key, label]) => `<option value="${key}">${label}</option>`).join('');
}

function openEntityModal(type, prefillDate) {
    if (type === 'obra') {
        ui.openModal('Nova obra', `
            <div class="form-grid">
                <div class="form-field full"><label for="f-name">Nome do projeto</label><input class="field" id="f-name" name="nome" required></div>
                <div class="form-field"><label for="f-city">Cidade / UF</label><input class="field" id="f-city" name="cidade" required></div>
                <div class="form-field"><label for="f-budget">Orçamento (R$)</label><input class="field" id="f-budget" name="orcamento" type="number" min="0" step="0.01" required></div>
            </div>`,
            async data => {
                await store.addObra({
                    name: Validator.requiredText(data.nome, 'o nome do projeto'),
                    city: Validator.requiredText(data.cidade, 'a cidade'),
                    budget: Validator.nonNegativeNumber(data.orcamento, 'o orçamento'),
                });
            },
            () => { render(); ui.notify('Obra adicionada.'); });
    }

    if (type === 'activity') {
        ui.openModal('Nova atividade', `
            <div class="form-grid">
                <div class="form-field full"><label for="f-title">Título</label><input class="field" id="f-title" name="titulo" required></div>
                <div class="form-field"><label for="f-obra">Obra</label><select class="field" id="f-obra" name="obra_id">${obraOptions()}</select></div>
                <div class="form-field"><label for="f-date">Prazo</label><input class="field" id="f-date" name="data_limite" type="date" value="${prefillDate ?? ''}" required></div>
                <div class="form-field full"><label for="f-desc">Descrição</label><textarea class="field" id="f-desc" name="descricao" rows="3"></textarea></div>
            </div>`,
            data => activityService.createProjectActivity(data),
            () => { render(); ui.notify('Atividade adicionada.'); });
    }

    if (type === 'transaction') {
        ui.openModal('Novo lançamento', `
            <div class="form-grid">
                <div class="form-field full"><label for="f-description">Descrição</label><input class="field" id="f-description" name="descricao" required></div>
                <div class="form-field"><label for="f-tobra">Obra</label><select class="field" id="f-tobra" name="obra_id">${obraOptions()}</select></div>
                <div class="form-field"><label for="f-category">Categoria</label><select class="field" id="f-category" name="categoria">${categoryOptions()}</select></div>
                <div class="form-field"><label for="f-qty">Quantidade</label><input class="field" id="f-qty" name="quantidade" type="number" min="0.01" step="0.01" required></div>
                <div class="form-field"><label for="f-unit">Valor unitário (R$)</label><input class="field" id="f-unit" name="valor_unitario" type="number" min="0" step="0.01" required></div>
                <div class="form-field"><label for="f-tdate">Data</label><input class="field" id="f-tdate" name="data_lancamento" type="date" value="${new Date().toISOString().slice(0, 10)}" required></div>
            </div>`,
            data => financialService.register(data),
            () => { render(); ui.notify('Lançamento salvo.'); });
    }

    if (type === 'budget') {
        ui.openModal('Definir orçamento', `
            <div class="form-grid">
                <div class="form-field"><label for="f-bobra">Obra</label><select class="field" id="f-bobra" name="obra_id">${obraOptions()}</select></div>
                <div class="form-field"><label for="f-bvalue">Orçamento (R$)</label><input class="field" id="f-bvalue" name="valor_orcado" type="number" min="0" step="0.01" required></div>
            </div>`,
            data => financialService.setBudget(data),
            () => { render(); ui.notify('Orçamento atualizado.'); });
    }

    if (type === 'document') {
        ui.openModal('Adicionar documento', `
            <div class="form-grid">
                <div class="form-field"><label for="f-dobra">Obra</label><select class="field" id="f-dobra" name="obra_id">${obraOptions()}</select></div>
                <div class="form-field"><label for="f-dtype">Tipo</label><select class="field" id="f-dtype" name="tipo">
                    <option>Geral</option><option>Nota fiscal</option><option>Contrato</option><option>Planta</option><option>Vistoria</option><option>Alvará</option>
                </select></div>
                <div class="form-field full"><label for="f-dfile">Arquivo</label><input class="field" id="f-dfile" name="arquivo" type="file" required></div>
            </div>`,
            async data => {
                const file = document.querySelector('#f-dfile').files[0];
                if (!file) throw new Error('Selecione um arquivo.');
                await store.addDocument({
                    obraId: Validator.id(data.obra_id),
                    file,
                    type: data.tipo,
                });
            },
            () => { render(); ui.notify('Documento adicionado.'); });
    }
}

// --- Delegação global de eventos (ações de tabela e navegação auxiliar) ---

document.addEventListener('click', event => {
    const action = event.target.closest('[data-action]')?.dataset.action;
    if (action === 'new-obra') openEntityModal('obra');
    if (action === 'new-activity') openEntityModal('activity');
    if (action === 'new-transaction') openEntityModal('transaction');
    if (action === 'new-budget') openEntityModal('budget');
    if (action === 'new-document') openEntityModal('document');
    if (action === 'close-modal') ui.closeModal();

    const routeLink = event.target.closest('[data-route-link]')?.dataset.routeLink;
    if (routeLink) location.hash = routeLink;

    const deletion = event.target.closest('[data-delete]')?.dataset.delete;
    if (deletion) {
        const [type, id] = deletion.split(':');
        if (!confirm('Confirmar exclusão?')) return;
        void (async () => {
            try {
                if (type === 'obra') await store.deleteObra(id);
                if (type === 'activity') await activityService.remove(id);
                if (type === 'transaction') await financialService.remove(id);
                if (type === 'document') await store.deleteDocument(id);
                ui.closeModal();
                render();
                ui.notify('Registro excluído.');
            } catch (error) {
                ui.notify(error.message);
            }
        })();
    }

    const toggleStatus = event.target.closest('[data-toggle-status]')?.dataset.toggleStatus;
    if (toggleStatus) {
        void (async () => {
            try {
                await activityService.cycleStatus(toggleStatus);
                ui.closeModal();
                render();
            } catch (error) {
                ui.notify(error.message);
            }
        })();
    }

    const chamadoStatus = event.target.closest('[data-chamado-status]')?.dataset.chamadoStatus;
    if (chamadoStatus) {
        const [id, status] = chamadoStatus.split(':');
        void (async () => {
            try {
                await store.setChamadoStatus(id, status);
                render();
                ui.notify(status === 'fechado' ? 'Chamado encerrado.' : 'Chamado atualizado.');
            } catch (error) {
                ui.notify(error.message);
            }
        })();
    }

    const download = event.target.closest('[data-download]')?.dataset.download;
    if (download) {
        void (async () => {
            try {
                const doc = store.state.documents.find(item => item.id === Number(download));
                if (doc) window.open(await store.documentUrl(doc), '_blank', 'noopener');
            } catch (error) {
                ui.notify(error.message);
            }
        })();
    }

    const toggleUser = event.target.closest('[data-toggle-user]')?.dataset.toggleUser;
    if (toggleUser && Auth.isAdmin()) {
        void (async () => {
            try {
                await store.toggleUser(toggleUser);
                render();
            } catch (error) {
                ui.notify(error.message);
            }
        })();
    }
});

// Logout: o link "Sair" (data-route="login") encerra a sessão antes de navegar.
document.querySelector('[data-route="login"]').addEventListener('click', () => {
    if (Auth.isAuthenticated()) void Auth.logout();
});

document.querySelector('#reset-data').addEventListener('click', () => {
    if (BACKEND_MODE !== 'local') return; // sem seed para restaurar no modo Supabase
    if (confirm('Restaurar os dados de demonstração? A sessão atual será encerrada.')) {
        void Auth.logout();
        store.reset();
        location.hash = 'login';
        render();
        ui.notify('Dados restaurados.');
    }
});

document.querySelector('#menu-toggle').addEventListener('click', () => {
    document.querySelector('#sidebar').classList.toggle('open');
});

window.addEventListener('hashchange', () => {
    document.querySelector('#sidebar').classList.remove('open');
    void render();
});

// Boot: restaura a sessão (Supabase Auth ou sessão local) e carrega os dados.
void (async () => {
    await Auth.init();
    await store.init();
    await render();
})();
