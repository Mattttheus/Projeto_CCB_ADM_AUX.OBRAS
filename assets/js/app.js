// Bootstrap da SPA estática — roteador por hash com guarda de acesso por perfil,
// espelhando o controle de sessão do app/Core/Auth.php (modo demonstração local).
import { store } from './infrastructure/persistence/LocalStore.js';
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
    dashboard: { title: 'Visão geral', render: dashboard.render },
    obras: { title: 'Obras', render: obras.render, bind: obras.bind },
    atividades: { title: 'Atividades', render: atividades.render, bind: atividades.bind },
    calendario: { title: 'Calendário', render: calendario.render },
    financeiro: { title: 'Financeiro', render: financeiro.render },
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
};

function currentRoute() {
    return location.hash.slice(1) || 'dashboard';
}

function render() {
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

    app.innerHTML = route.render(ctx);
    route.bind?.(ctx);
}

function syncChrome(key) {
    document.querySelectorAll('[data-route]').forEach(link => {
        link.classList.toggle('active', link.dataset.route === key);
        const roles = link.dataset.roles?.split(' ');
        if (roles) link.style.display = Auth.canAccess(roles) ? '' : 'none';
    });
    document.querySelector('#breadcrumb-current').textContent = routes[key]?.title ?? 'Visão geral';

    const user = Auth.user();
    const chip = document.querySelector('#user-chip');
    if (chip && user) {
        const initials = user.name.split(' ').map(part => part[0]).join('').slice(0, 2).toUpperCase();
        chip.innerHTML = `<span class="avatar">${ui.escapeHtml(initials)}</span><span><strong>${ui.escapeHtml(user.name)}</strong><small>${ui.escapeHtml(ROLE_LABELS[user.role] ?? user.role)}</small></span>`;
    }
}

// --- Modais de criação (formulários com os mesmos campos do backend PHP) ---

function obraOptions() {
    return store.state.obras.map(item => `<option value="${item.id}">${ui.escapeHtml(item.name)}</option>`).join('');
}

function categoryOptions() {
    return Object.entries(FINANCIAL_CATEGORY_LABELS)
        .map(([key, label]) => `<option value="${key}">${label}</option>`).join('');
}

function openEntityModal(type) {
    if (type === 'obra') {
        ui.openModal('Nova obra', `
            <div class="form-grid">
                <div class="form-field full"><label for="f-name">Nome do projeto</label><input class="field" id="f-name" name="nome" required></div>
                <div class="form-field"><label for="f-city">Cidade / UF</label><input class="field" id="f-city" name="cidade" required></div>
                <div class="form-field"><label for="f-budget">Orçamento (R$)</label><input class="field" id="f-budget" name="orcamento" type="number" min="0" step="0.01" required></div>
            </div>`,
            data => {
                store.state.obras.push({
                    id: store.nextId(store.state.obras),
                    name: Validator.requiredText(data.nome, 'o nome do projeto'),
                    city: Validator.requiredText(data.cidade, 'a cidade'),
                    status: 'Planejamento',
                    progress: 0,
                    budget: Validator.nonNegativeNumber(data.orcamento, 'o orçamento'),
                });
                store.log(`Obra "${data.nome}" criada.`);
                store.save();
            },
            () => { render(); ui.notify('Obra adicionada.'); });
    }

    if (type === 'activity') {
        ui.openModal('Nova atividade', `
            <div class="form-grid">
                <div class="form-field full"><label for="f-title">Título</label><input class="field" id="f-title" name="titulo" required></div>
                <div class="form-field"><label for="f-obra">Obra</label><select class="field" id="f-obra" name="obra_id">${obraOptions()}</select></div>
                <div class="form-field"><label for="f-date">Prazo</label><input class="field" id="f-date" name="data_limite" type="date" required></div>
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
}

// --- Delegação global de eventos (ações de tabela e navegação auxiliar) ---

document.addEventListener('click', event => {
    const action = event.target.closest('[data-action]')?.dataset.action;
    if (action === 'new-obra') openEntityModal('obra');
    if (action === 'new-activity') openEntityModal('activity');
    if (action === 'new-transaction') openEntityModal('transaction');
    if (action === 'close-modal') ui.closeModal();

    const routeLink = event.target.closest('[data-route-link]')?.dataset.routeLink;
    if (routeLink) location.hash = routeLink;

    const deletion = event.target.closest('[data-delete]')?.dataset.delete;
    if (deletion) {
        const [type, id] = deletion.split(':');
        if (type === 'obra') store.state.obras = store.state.obras.filter(item => item.id !== Number(id));
        if (type === 'activity') activityService.remove(id);
        if (type === 'transaction') financialService.remove(id);
        if (type === 'document') store.state.documents = store.state.documents.filter(item => item.id !== Number(id));
        store.save();
        render();
        ui.notify('Registro excluído.');
    }

    const toggleStatus = event.target.closest('[data-toggle-status]')?.dataset.toggleStatus;
    if (toggleStatus) {
        activityService.cycleStatus(toggleStatus);
        render();
    }

    const closeTicket = event.target.closest('[data-close-ticket]')?.dataset.closeTicket;
    if (closeTicket) {
        const ticket = store.state.chamados.find(item => item.id === Number(closeTicket));
        if (ticket) {
            ticket.status = 'Fechado';
            store.log(`Chamado "${ticket.title}" encerrado.`);
            store.save();
            render();
            ui.notify('Chamado encerrado.');
        }
    }

    const toggleUser = event.target.closest('[data-toggle-user]')?.dataset.toggleUser;
    if (toggleUser && Auth.isAdmin()) {
        const user = store.state.users.find(item => item.id === Number(toggleUser));
        if (user) {
            user.active = !user.active;
            store.log(`Acesso de ${user.name} ${user.active ? 'liberado' : 'bloqueado'}.`);
            store.save();
            render();
        }
    }
});

// Logout: o link "Sair" (data-route="login") encerra a sessão antes de navegar.
document.querySelector('[data-route="login"]').addEventListener('click', () => {
    if (Auth.isAuthenticated()) Auth.logout();
});

document.querySelector('#reset-data').addEventListener('click', () => {
    if (confirm('Restaurar os dados de demonstração? A sessão atual será encerrada.')) {
        Auth.logout();
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
    render();
});

render();
